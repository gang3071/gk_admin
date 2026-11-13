<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminRole;
use addons\webman\model\AdminUser;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\response\Response;
use support\Db;

/**
 * 代理後台 - 子帳號管理
 *
 * 總代理可建立子帳號（股東），並限制子帳號只能看指定的店家。
 * 子帳號固定使用內置角色「代理子帳號」，店家範圍存於 admin_users.visible_store_ids。
 *
 * @group agent
 */
class AgentSubAdminController
{
    /** 子帳號固定使用的內置角色名稱 */
    const SUB_ROLE_NAME = '代理子帳號';

    /**
     * 子帳號列表
     * @auth true
     * @group agent
     */
    public function index(): Grid
    {
        $agentId = (int) Admin::user()->id;

        return Grid::create(new AdminUser(), function (Grid $grid) use ($agentId) {
            $grid->title('子帳號管理');
            $grid->model()
                ->where('type', AdminUser::TYPE_AGENT)
                ->where('parent_admin_id', $agentId)
                ->orderBy('id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('username', '登入帳號')->align('center');
            $grid->column('nickname', '名稱')->align('center');
            $grid->column('visible_store_ids', '可見店家')->display(function ($value) {
                $count = is_array($value) ? count($value) : 0;
                return $count . ' 間';
            })->align('center');
            $grid->column('status', '狀態')->switch()->align('center');
            $grid->column('created_at', '建立時間')->align('center');

            $grid->setForm()->modal($this->form());
            $grid->hideSelection();

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 子帳號表單（新增/編輯）
     * @auth true
     * @group agent
     */
    public function form(): Form
    {
        $agentId = (int) Admin::user()->id;
        $storeOptions = $this->getStoreOptionsArray($agentId);

        return Form::create(new AdminUser(), function (Form $form) use ($agentId, $storeOptions) {
            $form->title($form->isEdit() ? '編輯子帳號' : '新增子帳號');

            $form->text('username', '登入帳號')
                ->required()
                ->disabled($form->isEdit());
            $form->text('nickname', '名稱')->required();
            $form->text('phone', '聯絡電話');
            if (!$form->isEdit()) {
                $form->password('password', '登入密碼')
                    ->rule(['min:6' => '密碼至少 6 位'])
                    ->required();
            }

            $form->select('visible_store_ids', '可見店家')
                ->options($storeOptions)
                ->multiple()
                ->required()
                ->help('只能選擇上級代理底下的店家');

            $form->radio('status', '狀態')
                ->options([1 => '啟用', 0 => '停用'])
                ->default(1);

            $form->hidden('type')->default(AdminUser::TYPE_AGENT);
            $form->hidden('parent_admin_id')->default($agentId);
            $form->hidden('is_super')->default(0);
            $form->hidden('department_id')->default((int) Admin::user()->department_id);

            $form->saving(function (Form $form) use ($agentId) {
                return $this->save($form, $agentId);
            });

            $form->saved(function (Form $form) {
                // 新建時 $form->model()->id 可能為空，改用 username 反查（username 唯一）
                $userId = (int) ($form->model()->id ?? 0);
                if (!$userId) {
                    $username = trim((string) ($form->input('username') ?: ($form->model()->username ?? '')));
                    if ($username !== '') {
                        $userId = (int) AdminUser::query()->where('username', $username)->value('id');
                    }
                }
                $this->syncRole($userId);
            });
        });
    }

    /**
     * 儲存前驗證與強制欄位
     */
    private function save(Form $form, int $agentId)
    {
        $id = (int) ($form->model()->id ?? 0);

        // 編輯時只能操作自己的子帳號
        if ($id) {
            $target = AdminUser::query()
                ->where('id', $id)
                ->where('type', AdminUser::TYPE_AGENT)
                ->where('parent_admin_id', $agentId)
                ->first();
            if (!$target) {
                return message_error('無權限操作此帳號');
            }
        }

        // 登入帳號（編輯時若被 disabled 未送出，沿用原值）
        $username = trim((string) $form->input('username'));
        if ($username === '' && $id) {
            $username = (string) ($form->model()->username ?? '');
            $form->input('username', $username);
        }
        if ($username !== '') {
            $dup = AdminUser::query()
                ->where('username', $username)
                ->when($id, function ($q) use ($id) {
                    $q->where('id', '!=', $id);
                })
                ->exists();
            if ($dup) {
                return message_error('登入帳號已存在');
            }
        }

        // 可見店家必須是上級代理底下的店家
        $storeIds = $form->input('visible_store_ids') ?: [];
        if (is_string($storeIds)) {
            $storeIds = array_filter(explode(',', $storeIds));
        }
        $storeIds = array_values(array_unique(array_map('intval', (array) $storeIds)));

        $allowed = array_keys($this->getStoreOptionsArray($agentId));
        if (empty($storeIds) || !empty(array_diff($storeIds, $allowed))) {
            return message_error('可見店家必須是上級代理底下的店家，請重新選擇');
        }

        $form->input('visible_store_ids', $storeIds);
        $form->input('type', AdminUser::TYPE_AGENT);
        $form->input('parent_admin_id', $agentId);
        $form->input('is_super', 0);
        $form->input('department_id', (int) Admin::user()->department_id);
    }

    /**
     * 同步子帳號角色（固定內置角色）
     */
    private function syncRole(int $userId): void
    {
        $roleId = $this->subRoleId();
        \support\Log::info('AgentSubAdmin::syncRole', [
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
        if (!$roleId || !$userId) {
            \support\Log::warning('AgentSubAdmin::syncRole 跳過（roleId/userId 為空）', [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
            return;
        }
        Db::table('admin_role_users')->where('user_id', $userId)->delete();
        Db::table('admin_role_users')->insert([
            'role_id' => $roleId,
            'user_id' => $userId,
        ]);
    }

    /**
     * 上級代理底下的店家（id => 名稱）
     */
    private function getStoreOptionsArray(int $agentId): array
    {
        return AdminUser::query()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('parent_admin_id', $agentId)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($store) {
                $label = $store->nickname ?: $store->username;
                return [$store->id => $label . " ({$store->username})"];
            })
            ->toArray();
    }

    /**
     * 子帳號內置角色 id
     */
    private function subRoleId(): int
    {
        return (int) AdminRole::query()
            ->where('type', AdminRole::TYPE_AGENT)
            ->where('name', self::SUB_ROLE_NAME)
            ->value('id');
    }

    /**
     * 店家選項（ajax）
     * @auth true
     * @group agent
     */
    public function getStoreOptions(): Response
    {
        $agentId = (int) Admin::user()->id;
        $data = [];
        foreach ($this->getStoreOptionsArray($agentId) as $id => $label) {
            $data[] = ['value' => $id, 'label' => $label];
        }
        return Response::success($data);
    }
}

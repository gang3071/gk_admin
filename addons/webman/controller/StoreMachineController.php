<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\StoreSetting;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use support\Db;

/**
 * 店家管理（线下渠道）
 * @group channel
 */
class StoreMachineController
{
    /**
     * 店家列表
     * @auth true
     * @group channel
     */
    public function index(): Grid
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>'));
            });
        }

        $currentDepartmentId = Admin::user()->department_id;

        return Grid::create(new AdminUser(), function (Grid $grid) use ($currentDepartmentId) {
            $grid->title('店家管理');
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：店家类型 + 数据权限过滤（通过代理的 department_id）
            $grid->model()
                ->join('admin_department as dept', 'admin_users.department_id', '=', 'dept.id')
                ->leftJoin('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
                ->where('admin_users.type', AdminDepartment::TYPE_STORE)
                ->where('parent_admin.department_id', $currentDepartmentId)  // 通过代理的 department_id 过滤
                ->select([
                    'admin_users.*',
                    'dept.name as department_name',
                    'dept.phone as department_phone',
                    'parent_admin.nickname as parent_agent_name'
                ])
                ->orderBy('admin_users.id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('nickname', '店家名称')->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val, 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val)->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('username', '登录账号')->width(120)->align('center');
            $grid->column('department_phone', '联系电话')->width(120)->align('center');
            $grid->column('parent_agent_name', admin_trans('admin.agent'))->width(120)->align('center');
            $grid->column('department_name', '部门名称')->width(150)->ellipsis(true);

            $grid->column('status', '状态')->display(function ($value) {
                return match ($value) {
                    0 => Tag::create('已禁用')->color('red'),
                    1 => Tag::create('正常')->color('green'),
                    default => '',
                };
            })->width(80)->align('center');

            $grid->column('created_at', '创建时间')->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('admin_users.status')
                    ->placeholder('状态')
                    ->options([
                        1 => '正常',
                        0 => '已禁用'
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('admin_users.username')->placeholder('登录账号');
                $filter->like()->text('admin_users.nickname')->placeholder('店家名称');
                $filter->like()->text('dept.phone')->placeholder('联系电话');

                // 代理筛选
                $filter->eq()->select('admin_users.parent_admin_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.agent'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-StoreMachineController',
                        'getAgentOptions'
                    ]));

                $filter->between()->dateTimeRange('admin_users.created_at')
                    ->placeholder(['开始时间', '结束时间']);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->setForm()->drawer($this->createStoreMachineForm());
            $grid->expandFilter();
        });
    }

    /**
     * 创建店家表单
     * @auth true
     * @group channel
     */
    public function createStoreMachineForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>'));
            });
        }

        // 获取代理列表
        $agents = AdminUser::query()
            ->join('admin_department', 'admin_users.department_id', '=', 'admin_department.id')
            ->where('admin_users.type', AdminDepartment::TYPE_AGENT)
            ->where('admin_users.status', 1)
            ->where('admin_department.pid', Admin::user()->department_id)
            ->select('admin_users.id', 'admin_users.nickname as name')
            ->get();

        $agentOptions = [];
        foreach ($agents as $agent) {
            $agentOptions[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'pid' => 0
            ];
        }
        $agentTreeOptions = Arr::tree($agentOptions);

        return Form::create([], function (Form $form) use ($agentTreeOptions) {
            $form->title('创建店家');
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">创建店家后，该店家可登录店家后台</font>'));

            $form->divider()->content('账号信息');

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->text('phone', '联系电话')
                        ->maxlength(20)
                        ->help('选填，用于联系');
                })->span(12);

                $form->column(function (Form $form) {
                    $form->text('admin_username', '后台登录账号')
                        ->maxlength(20)
                        ->help('必填，用于登录店家后台')
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', '店家名称')
                ->maxlength(50)
                ->help('店家的显示名称')
                ->required();

            $form->divider()->content('上级代理');

            $form->treeSelect('recommend_id', '选择上级代理')
                ->options($agentTreeOptions)
                ->help('选择该店家的上级代理')
                ->required();

            $form->divider()->content('头像配置');

            $form->image('avatar', '上传头像')
                ->help('支持jpg、png格式，建议尺寸200x200')
                ->required();

            $form->divider()->content('密码配置');

            $form->password('password', '登录密码')
                ->rule(['min:6' => '密码至少6位'])
                ->help('店家后台登录密码')
                ->required();

            $form->password('password_confirmation', '确认密码')
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->createStoreMachineSaving($form);
            });
        });
    }

    /**
     * 创建店家保存逻辑
     * 店家不创建玩家账号，只创建后台管理账号
     */
    private function createStoreMachineSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error('此功能仅限线下渠道使用');
        }

        $phone = $form->input('phone');
        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $recommendId = $form->input('recommend_id');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        if (empty($avatar)) {
            return message_error('请上传头像');
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error('两次密码输入不一致');
        }

        // 检查上级代理
        $parentAgent = AdminUser::query()
            ->where('id', $recommendId)
            ->where('type', AdminDepartment::TYPE_AGENT)
            ->first();
        if (!$parentAgent) {
            return message_error('上级代理不存在');
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error("登录账号 {$adminUsername} 已存在");
        }

        DB::beginTransaction();
        try {
            // 1. 创建店家部门
            $storeDepartment = new AdminDepartment();
            $storeDepartment->name = $name;
            $storeDepartment->leader = $name;
            $storeDepartment->phone = $phone ?? '';
            $storeDepartment->type = AdminDepartment::TYPE_STORE;
            $storeDepartment->pid = $parentAgent->department_id;
            $storeDepartment->save();

            $parentDept = AdminDepartment::find($parentAgent->department_id);
            $storeDepartment->path = $parentDept->path . ',' . $storeDepartment->id;
            $storeDepartment->save();

            // 2. 创建后台管理员账号（店家后台超管）
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_STORE;
            $adminUser->department_id = $storeDepartment->id;
            $adminUser->player_id = 0; // 店家不绑定玩家
            $adminUser->parent_admin_id = $parentAgent->id; // 保存上级代理ID
            $adminUser->is_super = 1; // 店家后台超管
            $adminUser->save();

            // 3. 分配店家超管角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.store_role'); // 店家超管角色ID（19）
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 4. 创建店家配置
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $storeDepartment->id;
            $storeSetting->player_id = 0; // 店家不绑定玩家
            $storeSetting->admin_user_id = $adminUser->id;
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = '欢迎使用店家后台系统！';
            $storeSetting->status = 1;
            $storeSetting->save();

            $storeSettingMachine = new StoreSetting();
            $storeSettingMachine->department_id = $storeDepartment->id;
            $storeSettingMachine->player_id = 0;
            $storeSettingMachine->admin_user_id = $adminUser->id;
            $storeSettingMachine->feature = 'enable_physical_machine';
            $storeSettingMachine->num = 1;
            $storeSettingMachine->status = 1;
            $storeSettingMachine->save();

            $storeSettingBaccarat = new StoreSetting();
            $storeSettingBaccarat->department_id = $storeDepartment->id;
            $storeSettingBaccarat->player_id = 0;
            $storeSettingBaccarat->admin_user_id = $adminUser->id;
            $storeSettingBaccarat->feature = 'enable_live_baccarat';
            $storeSettingBaccarat->num = 1;
            $storeSettingBaccarat->status = 1;
            $storeSettingBaccarat->save();

            DB::commit();

            return message_success("店家 {$name} 创建成功！登录账号：{$adminUsername}，" . admin_trans('admin.agent') . "：{$parentAgent->nickname}");

        } catch (\Exception $e) {
            DB::rollBack();
            return message_error('创建店家失败：' . $e->getMessage());
        }
    }

    /**
     * 获取代理选项（用于店家筛选）
     */
    public function getAgentOptions(): Response
    {
        $currentDepartmentId = Admin::user()->department_id;

        $agents = AdminUser::query()
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->where('department_id', $currentDepartmentId)
            ->select(['id', 'nickname', 'username'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($agent) {
                return [
                    'label' => $agent->nickname ?: $agent->username,
                    'value' => $agent->id,
                ];
            });

        return Response::success($agents);
    }
}
<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\AdminUserLimitGroup;
use addons\webman\model\PlatformLimitGroup;
use addons\webman\model\PlatformLimitGroupConfig;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;

/**
 * 店家限红分配管理（渠道后台）
 * @group channel
 */
class ChannelAdminUserLimitGroupController
{
    protected $model;

    public function __construct()
    {
        $this->model = AdminUserLimitGroup::class;
    }

    /**
     * 店家限红分配列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()
                ->with(['adminUser', 'limitGroup', 'gamePlatform', 'assignedBy'])
                ->whereHas('adminUser', function ($query) {
                    $query->where('department_id', Admin::user()->department_id);
                })
                ->orderBy('id', 'desc');

            $grid->title('店家限红分配');
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->column('id', 'ID')->align('center')->width(80);

            $grid->column('adminUser.username', '店家')->display(function ($value, $data) {
                // 获取店家头像
                $avatar = null;
                if (isset($data['admin_user']['avatar']) && !empty($data['admin_user']['avatar'])) {
                    $avatar = Avatar::create()->src($data['admin_user']['avatar']);
                } elseif (isset($data['adminUser']['avatar']) && !empty($data['adminUser']['avatar'])) {
                    $avatar = Avatar::create()->src($data['adminUser']['avatar']);
                } else {
                    $avatar = Avatar::create()->icon(Icon::create('UserOutlined'));
                }

                return Html::create()->content([
                    $avatar,
                    Html::div()->content($value)
                ]);
            })->align('center');

            $grid->column('limitGroup.name', '限红组')->display(function ($value, $data) {
                return Tag::create($value)->color('blue');
            })->align('center');

            $grid->column('gamePlatform.name', '游戏平台')->display(function ($value) {
                return $value ?: Tag::create('全平台')->color('green');
            })->align('center');

            $grid->column('platform_code', '平台代码')->display(function ($value) {
                return $value ?: '-';
            })->align('center');

            $grid->column('assignedBy.username', '分配人')->align('center');

            $grid->column('assigned_at', '分配时间')->align('center');

            $grid->column('remark', '备注')->align('center');

            $grid->column('status', '状态')->display(function ($value) {
                return $value == 1
                    ? Tag::create('启用')->color('success')
                    : Tag::create('禁用')->color('default');
            })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('admin_user_id', '店家')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-ChannelAdminUserLimitGroupController',
                        'getStoreAdminOptions'
                    ]));
                $filter->eq()->select('limit_group_id', '限红组')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-ChannelAdminUserLimitGroupController',
                        'getLimitGroupOptions'
                    ]));
                $filter->eq()->select('status')->placeholder('状态')->options([
                    1 => '启用',
                    0 => '禁用'
                ]);
            });
            $grid->expandFilter();

            $grid->setForm()->drawer($this->form());
        });
    }

    /**
     * 店家限红分配表单
     * @group channel
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title($form->isEdit() ? '编辑店家限红' : '分配店家限红');

            $form->select('admin_user_id', '选择店家')
                ->options($this->getStoreAdminOptionsArray())
                ->required()
                ->disabled($form->isEdit())
                ->help('选择要分配限红组的店家');

            $form->select('limit_group_id', '限红组')
                ->options($this->getLimitGroupOptionsArray())
                ->required()
                ->help('选择限红组（游戏平台将自动从限红组配置中获取）');

            $form->textarea('remark', '备注')
                ->rows(3)
                ->help('可选，记录分配原因');

            $form->radio('status', '状态')
                ->options([
                    1 => '启用',
                    0 => '禁用',
                ])
                ->default(1)
                ->required();

            // 自动设置分配人和分配时间，以及从限红组配置中获取游戏平台
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    $form->input('assigned_by', Admin::id());
                    $form->input('assigned_at', date('Y-m-d H:i:s'));
                }

                // 从限红组配置中自动获取游戏平台信息
                $limitGroupId = $form->input('limit_group_id');
                $limitGroupConfig = PlatformLimitGroupConfig::query()
                    ->where('limit_group_id', $limitGroupId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($limitGroupConfig) {
                    $form->input('platform_id', $limitGroupConfig->platform_id);
                    $form->input('platform_code', $limitGroupConfig->platform_code);
                } else {
                    // 如果限红组还没有配置平台，清空平台信息
                    $form->input('platform_id', null);
                    $form->input('platform_code', null);
                }
            });

            // 保存/更新后清理缓存
            $form->saved(function (Form $form) {
                $this->clearAdminUserLimitGroupCache($form);
            });

            // 删除后清理缓存
            $form->deleting(function (Form $form) {
                $this->clearAdminUserLimitGroupCache($form);
            });
        });
    }

    /**
     * 清理店家限红组相关缓存
     * @param Form $form
     */
    private function clearAdminUserLimitGroupCache(Form $form)
    {
        try {
            $redis = \support\Redis::connection('default')->client();

            // 获取店家ID和平台ID
            $adminUserId = $form->model()->admin_user_id ?? $form->input('admin_user_id');
            $platformId = $form->model()->platform_id ?? $form->input('platform_id');

            if ($adminUserId && $platformId) {
                // 清理该店家下所有玩家的限红组配置缓存（使用SCAN避免阻塞）
                $pattern = "limit_group_config:{$platformId}:*:{$adminUserId}";
                $iterator = null;
                while (false !== ($keys = $redis->scan($iterator, $pattern, 100))) {
                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                }

                // 同时清理平台限红组配置缓存（因为可能切换了营运账号）
                $platformLimitConfigKey = "platform_limit_configs:{$platformId}";
                $redis->del($platformLimitConfigKey);

                \support\Log::info('店家限红组缓存已清理', [
                    'admin_user_id' => $adminUserId,
                    'platform_id' => $platformId,
                    'limit_group_id' => $form->model()->limit_group_id ?? $form->input('limit_group_id'),
                ]);
            }
        } catch (\Exception $e) {
            \support\Log::error('清理店家限红组缓存失败', [
                'error' => $e->getMessage(),
                'admin_user_id' => $adminUserId ?? null,
                'platform_id' => $platformId ?? null,
            ]);
        }
    }

    /**
     * 获取店家选项（当前渠道下的店家）
     * @group channel
     * @auth true
     */
    public function getStoreAdminOptions()
    {
        $request = Request::input();
        $query = AdminUser::query()
            ->where('department_id', Admin::user()->department_id)
            ->where('type', AdminUser::TYPE_STORE) // 只显示店家类型
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if (!empty($request['search'])) {
            $query->where(function($q) use ($request) {
                $q->where('username', 'like', '%' . $request['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $request['search'] . '%');
            });
        }

        $list = $query->get();
        $data = [];

        foreach ($list as $item) {
            $data[] = [
                'value' => $item->id,
                'label' => "{$item->username}" . ($item->phone ? " ({$item->phone})" : ''),
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取限红组选项
     * @group channel
     * @auth true
     */
    public function getLimitGroupOptions()
    {
        $request = Request::input();
        $query = PlatformLimitGroup::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc');

        if (!empty($request['search'])) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request['search'] . '%')
                  ->orWhere('code', 'like', '%' . $request['search'] . '%');
            });
        }

        $list = $query->get();
        $data = [];

        foreach ($list as $item) {
            // 获取限红组对应的游戏平台
            $limitGroupConfig = PlatformLimitGroupConfig::query()
                ->where('limit_group_id', $item->id)
                ->whereNull('deleted_at')
                ->with('gamePlatform')
                ->first();

            $platformName = $limitGroupConfig && $limitGroupConfig->gamePlatform
                ? $limitGroupConfig->gamePlatform->name
                : '未配置';

            $data[] = [
                'value' => $item->id,
                'label' => "{$item->name} ({$platformName})",
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取店家选项（Form用，返回数组）
     */
    private function getStoreAdminOptionsArray(): array
    {
        $data = [];
        $list = AdminUser::query()
            ->where('department_id', Admin::user()->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($list as $item) {
            $data[$item->id] = "{$item->username}" . ($item->phone ? " ({$item->phone})" : '');
        }

        return $data;
    }

    /**
     * 获取限红组选项（Form用，返回数组）
     */
    private function getLimitGroupOptionsArray(): array
    {
        $data = [];
        $list = PlatformLimitGroup::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->get();

        foreach ($list as $item) {
            // 获取限红组对应的游戏平台
            $limitGroupConfig = PlatformLimitGroupConfig::query()
                ->where('limit_group_id', $item->id)
                ->whereNull('deleted_at')
                ->with('gamePlatform')
                ->first();

            $platformName = $limitGroupConfig && $limitGroupConfig->gamePlatform
                ? $limitGroupConfig->gamePlatform->name
                : '未配置';

            $data[$item->id] = "{$item->name} ({$platformName})";
        }

        return $data;
    }
}

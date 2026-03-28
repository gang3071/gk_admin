<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\AdminUserLimitGroup;
use addons\webman\model\PlatformLimitGroup;
use addons\webman\model\GamePlatform;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use ExAdmin\ui\response\Response;

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

            $grid->column('adminUser.username', '店家账号')->align('center');

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
                $filter->equal()->select('admin_user_id', '店家')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-ChannelAdminUserLimitGroupController',
                        'getStoreAdminOptions'
                    ]));
                $filter->equal()->select('limit_group_id', '限红组')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-ChannelAdminUserLimitGroupController',
                        'getLimitGroupOptions'
                    ]));
                $filter->equal()->select('platform_id', '游戏平台')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->equal()->select('status')->placeholder('状态')->options([
                    ['value' => 1, 'label' => '启用'],
                    ['value' => 0, 'label' => '禁用'],
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
                ->help('选择限红组');

            $form->select('platform_id', '游戏平台')
                ->options($this->getGamePlatformOptionsArray())
                ->help('留空表示全平台生效');

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

            // 自动设置分配人和分配时间
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    $form->input('assigned_by', Admin::id());
                    $form->input('assigned_at', date('Y-m-d H:i:s'));
                }

                // 自动设置platform_code
                $platformId = $form->input('platform_id');
                if ($platformId) {
                    $platform = GamePlatform::find($platformId);
                    if ($platform) {
                        $form->input('platform_code', $platform->code);
                    }
                } else {
                    $form->input('platform_code', null);
                }
            });
        });
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
            $data[] = [
                'value' => $item->id,
                'label' => "{$item->name} ({$item->code})",
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取游戏平台选项
     * 只返回 ATG 和 RSG 平台
     * @group channel
     * @auth true
     */
    public function getGamePlatformOptions()
    {
        $request = Request::input();
        $query = GamePlatform::query()
            ->where('status', 1)
            ->whereIn('code', ['ATG', 'RSG']) // 只显示 ATG 和 RSG
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($request['search'])) {
            $query->where('name', 'like', '%' . $request['search'] . '%');
        }

        $list = $query->get();
        $data = [];

        foreach ($list as $item) {
            $data[] = [
                'value' => $item->id,
                'label' => "{$item->name} ({$item->code})",
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
            $data[$item->id] = "{$item->name} ({$item->code})";
        }

        return $data;
    }

    /**
     * 获取游戏平台选项（Form用，返回数组）
     * 只返回 ATG 和 RSG 平台
     */
    private function getGamePlatformOptionsArray(): array
    {
        $data = [];
        $list = GamePlatform::query()
            ->where('status', 1)
            ->whereIn('code', ['ATG', 'RSG']) // 只显示 ATG 和 RSG
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($list as $item) {
            $data[$item->id] = "{$item->name} ({$item->code})";
        }

        return $data;
    }
}

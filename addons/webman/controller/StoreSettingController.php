<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\StoreSetting;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;

/**
 * 店家系统配置
 * @group channel
 */
class StoreSettingController
{
    protected $model;

    public function __construct()
    {
        $this->model = StoreSetting::class;
    }

    /**
     * 配置列表
     * @group channel
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        // 获取当前登录用户的AdminUser ID（店家/代理账号）
        $adminUserId = Admin::user()->id;

        // 显示当前店家账号的配置列表
        return Card::create($this->settingList($adminUserId));
    }

    /**
     * 店家配置列表
     * @param int|null $adminUserId 当前店家的AdminUser ID，null表示渠道级配置
     * @return Grid
     */
    public function settingList(?int $adminUserId = null): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($adminUserId) {
            $departmentId = Admin::user()->department_id;

            $grid->title(admin_trans('store_setting.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询该店家的专属配置
            $grid->model()
                ->where('department_id', $departmentId)
                ->where('admin_user_id', $adminUserId);

            // 功能列
            $grid->column('feature', admin_trans('store_setting.fields.feature'))
                ->display(function ($value) {
                    return admin_trans('store_setting.fields.' . $value);
                })->align('center');

            // 配置列
            $grid->column('setting', admin_trans('store_setting.fields.setting'))
                // 首页提醒消息
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'home_notice';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:500' => admin_trans('store_setting.home_notice_max_len')])
                )->display(function ($value, StoreSetting $data) {
                    return Str::of($data->content)->limit(50, ' (...)');
                })->width('30%')->align('center')
                // 店家跑马灯
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'store_marquee';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('store_setting.store_marquee_max_len')])
                )->display(function ($value, StoreSetting $data) {
                    return Str::of($data->content)->limit(50, ' (...)');
                })->width('30%')->align('center')
                // 订单过期时间
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'order_expiration';
                })->editable(
                    (new Editable)->number('num')
                        ->rule([
                            'integer' => admin_trans('store_setting.validation.integer'),
                            'max:180' => admin_trans('store_setting.validation.max', null, ['{max}' => 180]),
                            'min:5' => admin_trans('store_setting.validation.min', null, ['{min}' => 5]),
                        ])->addonAfter(admin_trans('store_setting.minutes'))
                )->display(function ($val, StoreSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('store_setting.minutes');
                    }
                    return '';
                })->align('center')
                // 营业时间
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'business_hours';
                })->display(function ($value, StoreSetting $data) {
                    $time = '';
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= ' ~ ' . $data->date_end;
                    return Tag::create($time)->color('blue')->modal([$this, 'editBusinessHours'], ['data' => $data]);
                })->align('center')
                // 是否开启实体机台
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'enable_physical_machine';
                })->editable(
                    (new Editable)->select('num')
                        ->options([
                            1 => admin_trans('store_setting.enable'),
                            0 => admin_trans('store_setting.disable'),
                        ])
                )->display(function ($val, StoreSetting $data) {
                    if ($data->num == 1) {
                        return Tag::create(admin_trans('store_setting.enable'))->color('green');
                    } else {
                        return Tag::create(admin_trans('store_setting.disable'))->color('red');
                    }
                })->align('center')
                // 是否开启真人百家
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'enable_live_baccarat';
                })->editable(
                    (new Editable)->select('num')
                        ->options([
                            1 => admin_trans('store_setting.enable'),
                            0 => admin_trans('store_setting.disable'),
                        ])
                )->display(function ($val, StoreSetting $data) {
                    if ($data->num == 1) {
                        return Tag::create(admin_trans('store_setting.enable'))->color('green');
                    } else {
                        return Tag::create(admin_trans('store_setting.disable'))->color('red');
                    }
                })->align('center');

            // 状态列
            $grid->column('status', admin_trans('store_setting.fields.status'))
                ->switch()->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }

    /**
     * 编辑营业时间
     * @group channel
     * @auth true
     * @param StoreSetting $data
     * @return Form
     */
    public function editBusinessHours(StoreSetting $data): Form
    {
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('store_setting.edit_business_hours'));
            $form->timeRange('date_start', 'date_end', admin_trans('store_setting.time_range'))
                ->value([$data->date_start, $data->date_end])
                ->required();
        });
    }
}
<?php

namespace addons\webman\controller;

use addons\webman\model\SystemSetting;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;

/**
 * 跑马灯
 * @group channel
 */
class ChannelMarqueeController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.system_setting_model');
    }

    /**
     * 跑马灯
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('system_setting.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('feature', admin_trans('system_setting.fields.feature'))->display(function ($value) {
                return admin_trans('system_setting.fields.' . $value);
            })->align('center');

            $grid->column('setting', admin_trans('system_setting.fields.setting'))
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'marquee' || $data->feature === 'machine_marquee';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:500' => admin_trans('system_setting.marquee_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'register_present';
                })->editable(
                    Editable::number('num')
                        ->min(0)
                        ->max(100000)
                )->display(function ($value, SystemSetting $data) {
                    return $data->num;
                })->align('center')
                ->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'machine_maintain';
                })->display(function ($value, SystemSetting $data) {
                    $time = '';
                    !empty($data->num) && $time .= admin_trans('system_setting.week.' . $data->num);
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= '~' . $data->date_end;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editMachineMaintain'], ['data' => $data]);
                })->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_customer';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_customer_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_discussion_group';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_discussion_group_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_redirect_uri';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_redirect_uri_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_key';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_key_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_secret';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_secret_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'app_password';
                })->display(function ($value, SystemSetting $data) {

                    return Button::create($data->content)
                            ->icon(Icon::create('fas fa-chalkboard'))
                            ->confirm(admin_trans('player.reset_password'),
                                [$this,'resetPassword'],['id'=>$data->id])->gridRefresh();
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'ticket_machine_version';
                })->editable(
                    (new Editable)->text('content')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])
                        ->required()
                )->display(function ($val, SystemSetting $data) {
                    return $data->content ?? '--';
                })->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'ticket_machine_download_url';
                })->editable(
                    (new Editable)->text('content')
                        ->required()
                )->display(function ($val, SystemSetting $data) {
                    return $data->content ?? '--';
                })->align('center');

            $grid->column('status', admin_trans('system_setting.fields.status'))->switch()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }

    /**
     * 电子游戏反水开关
     * @group channel
     * @auth true
     */
    public function electronicGameRebate(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('system_setting.rebate.electronic_game_rebate'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->model()->where('feature', 'electronic_game_rebate');

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('department_id', admin_trans('system_setting.fields.department_id'))->width(100)->align('center');
            $grid->column('feature', admin_trans('system_setting.fields.feature'))
                ->display(function () {
                    return admin_trans('system_setting.rebate.electronic_game_rebate');
                })->align('center');
            $grid->column('status', admin_trans('system_setting.fields.status'))
                ->switch([
                    'onValue' => 1,
                    'offValue' => 0,
                    'checkedChildren' => admin_trans('system_setting.rebate.enabled'),
                    'unCheckedChildren' => admin_trans('system_setting.rebate.disabled'),
                ])->align('center');
            $grid->column('created_at', admin_trans('system_setting.fields.created_at'))->width(180)->align('center');
            $grid->column('updated_at', admin_trans('system_setting.fields.updated_at'))->width(180)->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }

    /**
     * 实体机台反水开关
     * @group channel
     * @auth true
     */
    public function machineRebate(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('system_setting.rebate.machine_rebate'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->model()->where('feature', 'machine_rebate');

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('department_id', admin_trans('system_setting.fields.department_id'))->width(100)->align('center');
            $grid->column('feature', admin_trans('system_setting.fields.feature'))
                ->display(function () {
                    return admin_trans('system_setting.rebate.machine_rebate');
                })->align('center');
            $grid->column('status', admin_trans('system_setting.fields.status'))
                ->switch([
                    'onValue' => 1,
                    'offValue' => 0,
                    'checkedChildren' => admin_trans('system_setting.rebate.enabled'),
                    'unCheckedChildren' => admin_trans('system_setting.rebate.disabled'),
                ])->align('center');
            $grid->column('created_at', admin_trans('system_setting.fields.created_at'))->width(180)->align('center');
            $grid->column('updated_at', admin_trans('system_setting.fields.updated_at'))->width(180)->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }

    public function resetPassword($id)
    {
        SystemSetting::query()->where('id',$id)->update(['content'=>mt_rand(10000,99999)]);

        return jsonSuccessResponse(trans('success', [], 'message'));
    }
}

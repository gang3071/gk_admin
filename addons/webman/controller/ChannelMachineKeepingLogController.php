<?php

namespace addons\webman\controller;

use addons\webman\model\MachineKeepingLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 机台保留日志
 */
class ChannelMachineKeepingLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_keeping_log_model');

    }

    /**
     * 机台保留日志
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->model()->with(['player', 'machine', 'user', 'player.channel'])->where('keep_seconds', '>',
                0)->orderBy('created_at', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('machine_keeping_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_keeping_log.fields.id'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                    $val,
                    MachineKeepingLog $data
                ) {
                    return Html::create()->content([
                        Html::div()->content($val)
                    ]);
                })->align('center');
                $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, MachineKeepingLog $data) {
                    return Html::create()->content([
                        !empty($data->player->is_test) ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                    ]);
                })->fixed(true)->align('center');
                $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                    $val,
                    MachineKeepingLog $data
                ) {
                    if(isset($data->player)){
                        return $data->player->phone;
                    }else{
                        return '';
                    }
                })->align('center');
                $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                    $val,
                    MachineKeepingLog $data
                ) {
                    if(isset($data->player)){
                        return $data->player->channel->name;
                    }else{
                        return '';
                    }
                })->width('150px')->align('center');
            }, admin_trans('machine_keeping_log.player_info'));

            $grid->column(function (Grid $grid) {
                $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    MachineKeepingLog $data
                ) {
                    if ($data->machine) {
                        return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                            'addons-webman-controller-PlayerDeliveryRecordController',
                            'machineInfo'
                        ],
                            ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                    }
                    return '';
                })->align('center');
                $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                    $val,
                    MachineKeepingLog $data
                ) {
                    return $data->machine->code;
                })->align('center');
            }, admin_trans('machine_keeping_log.machine_info'));

            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function (
                $val,
                MachineKeepingLog $data
            ) {
                if ($data->user) {
                    $image = Image::create()
                        ->width(30)
                        ->height(30)
                        ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                        ->src($data->user->avatar);
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->user->nickname)
                    ]);
                }
                return '';
            })->align('center');
            $grid->column('remark', admin_trans('machine_keeping_log.fields.remark'))->align('center');
            $grid->column('keep_seconds', admin_trans('machine_keeping_log.fields.keep_seconds'))->display(function (
                $val,
                MachineKeepingLog $data
            ) {
                $seconds = $data->keep_seconds;
                if ($seconds > 3600) {
                    $hours = intval($seconds / 3600);
                    $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                } else {
                    $time = gmstrftime('%H:%M:%S', $seconds);
                }
                return Html::create()->content([
                    $time ?? '',
                ]);
            })->align('center');
            $grid->column('created_at', admin_trans('machine_keeping_log.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->in()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.admin_user'))
                    ->options(getAdminUserListOptions())
                    ->multiple();
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('machine_keeping_log.created_at_start'),
                    admin_trans('machine_keeping_log.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }
}

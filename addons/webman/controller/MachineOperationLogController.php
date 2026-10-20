<?php

namespace addons\webman\controller;

use addons\webman\model\MachineOperationLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 机台操作日志
 * @group admin
 */
class MachineOperationLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_operation_log_model');
    }

    /**
     * 机台操作日志列表
     * @auth true
     * @group admin
     */
    public function actionsList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $requestFilter = Request::input('ex_admin_filter', []);

            $grid->model()
                ->with(['machine.machineLabel', 'player', 'user'])
                ->orderBy('id', 'desc');

            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }

            $grid->title(admin_trans('machine_operation_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', admin_trans('machine_operation_log.fields.id'))
                ->width(80)->align('center');

            $grid->column('machine_id', admin_trans('machine_operation_log.machine_info'))
                ->display(function ($val, MachineOperationLog $data) {
                    if (!$data->machine) {
                        return '-';
                    }
                    $code = Html::create($data->machine->code ?? '-')
                        ->style(['display' => 'block', 'fontWeight' => 'bold']);
                    $name = Html::create($data->machine->machineLabel->name ?? '-')
                        ->style(['display' => 'block', 'color' => '#999', 'fontSize' => '12px']);
                    return Html::create()->content([$code, $name]);
                })
                ->width(150);

            $grid->column('player_id', admin_trans('machine_operation_log.player_info'))
                ->display(function ($val, MachineOperationLog $data) {
                    if (!$data->player) {
                        return '-';
                    }
                    $name = Html::create($data->player->name ?? $data->player->username ?? '-')
                        ->style(['display' => 'block', 'fontWeight' => 'bold']);
                    $uuid = Html::create('UUID: ' . ($data->player->uuid ?? '-'))
                        ->style(['display' => 'block', 'color' => '#999', 'fontSize' => '12px']);
                    return Html::create()->content([$name, $uuid]);
                })
                ->width(160);

            $grid->column('user_id', admin_trans('machine_operation_log.admin_user'))
                ->display(function ($val, MachineOperationLog $data) {
                    if (!$data->user) {
                        return '-';
                    }
                    return Tag::create($data->user->nickname ?: $data->user->username)->color('blue');
                })
                ->width(120)->align('center');

            $grid->column('action', admin_trans('machine_operation_log.fields.action'))
                ->display(function ($val) {
                    $actionMap = array_merge(
                        admin_trans('machine_operation_log.action.slot') ?? [],
                        admin_trans('machine_operation_log.action.jack_pot') ?? []
                    );
                    $label = $actionMap[$val] ?? $val;
                    return Tag::create($label)->color('orange');
                })
                ->width(160)->align('center');

            $grid->column('content', admin_trans('machine_operation_log.fields.content'))
                ->display(function ($val) {
                    if (empty($val)) {
                        return '-';
                    }
                    $data = json_decode($val, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                        return Html::create($val)->style(['fontSize' => '12px', 'color' => '#666']);
                    }
                    $items = [];
                    foreach ($data as $k => $v) {
                        if (is_array($v)) {
                            $v = json_encode($v);
                        }
                        $items[] = Html::create("{$k}: {$v}")
                            ->style(['display' => 'block', 'fontSize' => '12px', 'color' => '#555']);
                    }
                    return Html::create()->content($items);
                })
                ->width(200);

            $grid->column('status', admin_trans('machine_operation_log.fields.status'))
                ->display(function ($val) {
                    return $val == 1
                        ? Tag::create(admin_trans('machine_operation_log.action_success'))->color('green')
                        : Tag::create(admin_trans('machine_operation_log.action_error'))->color('red');
                })
                ->width(100)->align('center');

            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))
                ->width(160)->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.code')
                    ->placeholder(admin_trans('machine.fields.code'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('machine_operation_log.fields.status'))
                    ->options([
                        1 => admin_trans('machine_operation_log.action_success'),
                        0 => admin_trans('machine_operation_log.action_error'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')
                    ->placeholder([
                        admin_trans('machine_operation_log.created_at_start'),
                        admin_trans('machine_operation_log.created_at_end'),
                    ]);
            });

            $grid->expandFilter();
        });
    }
}

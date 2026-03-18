<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\mongo\MachineReceiveLog;
use app\service\machine\MachineServices;
use DateTime;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\Popover;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Exception;

/**
 * 机台操作日志
 */
class MachineReceiveLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_receive_log_model');

    }

    /**
     * 机台接受指令日志
     * @auth true
     * @return Grid
     * @throws Exception
     */
    public function index(): Grid
    {
        $request = Request::input();
        $machineOperationLog = MachineReceiveLog::query();
        if (!empty($request['ex_admin_filter']['machine_name'])) {
            $machineOperationLog->where('machine_name', 'like',
                '%' . $request['ex_admin_filter']['machine_name'] . '%');
        }
        if (!empty($request['ex_admin_filter']['machine_code'])) {
            $machineOperationLog->where('machine_code', 'like',
                '%' . $request['ex_admin_filter']['machine_code'] . '%');
        }
        if (!empty($request['ex_admin_filter']['uuid'])) {
            $machineOperationLog->where('uuid', 'like', '%' . $request['ex_admin_filter']['uuid'] . '%');
        }
        if (!empty($request['ex_admin_filter']['department_id'])) {
            $machineOperationLog->where('department_id', (int)$request['ex_admin_filter']['department_id']);
        }
        if (!empty($request['ex_admin_filter']['machine_action'])) {
            $machineAction = $request['ex_admin_filter']['machine_action'];
            $machineOperationLog = $machineOperationLog->where(function ($query) use ($machineAction) {
                foreach ($machineAction as $item) {
                    if (!in_array($item, [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL, GameType::TYPE_FISH])) {
                        $actionArr = explode(',', $item);
                        $query->orWhere([
                            ['action', '=', $actionArr[0], 'and'],
                            ['machine_type', '=', (int)$actionArr[1], 'and'],
                        ]);
                    }
                }
                return $query;
            });
        }
        if (!empty($request['ex_admin_filter']['created_at_start'])) {
            $machineOperationLog->where('created_at', '>=',
                new DateTime($request['ex_admin_filter']['created_at_start']));
        }
        if (!empty($request['ex_admin_filter']['created_at_end'])) {
            $machineOperationLog->where('created_at', '<=',
                new DateTime($request['ex_admin_filter']['created_at_end']));
        }
        if (!empty($request['quickSearch'])) {
            $machineOperationLog->where([
                ['machine_name', 'like', '%' . $request['quickSearch'] . '%', 'or'],
                ['machine_code', 'like', '%' . $request['quickSearch'] . '%', 'or'],
                ['uuid', 'like', '%' . $request['quickSearch'] . '%', 'or'],
            ]);
        }
        $countModel = clone $machineOperationLog;
        $total = $countModel->count('*');
        $list = $machineOperationLog
            ->forPage($request['ex_admin_page'] ?? 1, $request['ex_admin_size'] ?? 20)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
        return Grid::create($list, function (Grid $grid) use ($total, $list) {
            $grid->title(admin_trans('machine_operation_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('machine_name', admin_trans('machine.fields.name'))->align('center');
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            $grid->column('msg', admin_trans('machine_operation_log.fields.msg'))->align('center');
            $grid->column('player_phone', admin_trans('player.fields.phone'))->align('center');
            $grid->column('player_name', admin_trans('player.fields.name'))->align('center');
            $grid->column('uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('status', admin_trans('machine_operation_log.fields.status'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('machine_operation_log.' . ($val == 1 ? 'action_success' : 'action_error')))->color(($val == 1 ? '#55acee' : '#cd201f'))
                ]);
            })->align('center');
            $grid->column('action', admin_trans('machine_operation_log.fields.action'))->display(function (
                $val,
                $data
            ) {
                if ($val) {
                    return Tag::create(admin_trans('machine_action.machine_action.' . $data['machine_type'] . '.' . $val));
                }
                return '';
            })->align('center');
            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))->align('center');
            $grid->column('content', admin_trans('machine_operation_log.fields.content'))->display(function (
                $val,
                $data
            ) {
                $html = [];
                $content = json_decode($val, true);
                if (!empty($content)) {
                    foreach ($content as $key => $item) {
                        $htmlItem = MachineServices::getAttributeDes($key, $item, $data['machine_id']);
                        if (!empty($htmlItem)) {
                            $html[] = Tag::create($htmlItem);
                        }
                    }
                } else {
                    $html[] = Html::div()->content('');
                }
                return Popover::create(Button::create(admin_trans('machine_operation_log.view'))->size('small'))
                    ->title(admin_trans('machine_operation_log.fields.content'))
                    ->arrowPointAtCenter(true)
                    ->overlayStyle(['width' => '300px'])
                    ->content($html);
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player_name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('machine_name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('machine.fields.code'));
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
                $filter->in()->cascaderSingle('machine_action')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine_operation_log.fields.action'))
                    ->options(getActionListOptions())
                    ->multiple();
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('machine_operation_log.created_at_start'),
                    admin_trans('machine_operation_log.created_at_end')
                ]);
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }
}

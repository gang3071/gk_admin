<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineProducer;
use addons\webman\model\mongo\MachineOperationLog;
use app\service\machine\Jackpot;
use app\service\machine\MachineServices;
use DateTime;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\Popover;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Exception;
use support\Cache;

/**
 * 机台操作日志
 */
class MachineOperationLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_operation_log_model');

    }

    /**
     * 机台
     * @auth true
     * @return Card
     * @throws Exception
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('machine_operation_log.log_type.0'), $this->getList('admin'))
            ->pane(admin_trans('machine_operation_log.log_type.1'), $this->getList('player'))
            ->pane(admin_trans('machine_operation_log.log_type.2'), $this->getList('system'))
            ->type('card')
        );
    }

    /**
     * 机台操作日志
     * @param $logType
     * @return Grid
     * @throws Exception
     */
    public function getList($logType): Grid
    {
        $request = Request::input();
        $machineOperationLog = MachineOperationLog::query();
        switch ($logType) {
            case 'admin':
                $machineOperationLog->where('user_id', '!=', 0)->where('is_system', 0);
                break;
            case 'player':
                $machineOperationLog->where('player_id', '!=', 0)->where('is_system', 0);
                break;
            case 'system':
                $machineOperationLog->where('is_system', 1);
                break;
        }
        if (!empty($request['ex_admin_filter']['machine_name'])) {
            $machineOperationLog->where('machine_name', 'like', '%' . $request['ex_admin_filter']['machine_name'] . '%');
        }
        if (!empty($request['ex_admin_filter']['machine_code'])) {
            $machineOperationLog->where('machine_code', 'like', '%' . $request['ex_admin_filter']['machine_code'] . '%');
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
            $machineOperationLog->where('created_at', '>=', new DateTime($request['ex_admin_filter']['created_at_start']));
        }
        if (!empty($request['ex_admin_filter']['created_at_end'])) {
            $machineOperationLog->where('created_at', '<=', new DateTime($request['ex_admin_filter']['created_at_end']));
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
        return Grid::create($list, function (Grid $grid) use ($total, $list, $logType) {
            $grid->title(admin_trans('machine_operation_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            if ($logType == 'player') {
                $grid->column('player_phone', admin_trans('player.fields.phone'))->align('center');
                $grid->column('player_name', admin_trans('player.fields.name'))->align('center');
                $grid->column('uuid', admin_trans('player.fields.uuid'))->align('center');
            }
            $grid->column('machine_name', admin_trans('machine.fields.name'))->align('center');
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            if ($logType == 'admin') {
                $grid->column('user_name', admin_trans('admin.admin_user'))->align('center');
            }
            $grid->column('status', admin_trans('machine_operation_log.fields.status'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('machine_operation_log.' . ($val == 1 ? 'action_success' : 'action_error')))->color(($val == 1 ? '#55acee' : '#cd201f'))
                ]);
            })->align('center');
            $grid->column('action', admin_trans('machine_operation_log.fields.action'))->display(function ($val, $data) {
                if ($val) {
                    return Tag::create(admin_trans('machine_action.machine_action.' . $data['machine_type'] . '.' . $val));
                }
                return '';
            })->align('center');
            $grid->column('remark', admin_trans('machine_operation_log.fields.remark'))->align('center');
            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))->align('center');
            $grid->column('content', admin_trans('machine_operation_log.fields.content'))->display(function ($val, $data) {
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('machine_operation_log.created_at_start'), admin_trans('machine_operation_log.created_at_end')]);
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

    /**
     * 机台操作日志
     * @return Grid
     * @throws Exception
     */
    public function actionsList(): Grid
    {
        $request = Request::input();
        $machineOperationLog = MachineOperationLog::query();
        $machineOperationLog->where('user_id', '!=', 0)->where('is_system', 0);
        if (!empty($request['ex_admin_filter']['machine_name'])) {
            $machineOperationLog->where('machine_name', 'like', '%' . $request['ex_admin_filter']['machine_name'] . '%');
        }
        if (!empty($request['ex_admin_filter']['machine_code'])) {
            $machineOperationLog->where('machine_code', 'like', '%' . $request['ex_admin_filter']['machine_code'] . '%');
        }
        if (!empty($request['ex_admin_filter']['machine_action'])) {
            if($request['ex_admin_filter']['machine_action'] == 1) {
                $action = [Jackpot::OPEN_ONE,Jackpot::OPEN_TEN,Jackpot::OPEN_ANY_POINT];
            }else{
                $action = [Jackpot::WASH_ZERO,Jackpot::WASH_ZERO_REMAINDER,Jackpot::MACHINE_POINT];
            }
            $machineOperationLog->whereIn('action',$action);
        } else {
            $machineOperationLog->whereIn('action', [Jackpot::OPEN_ONE,Jackpot::OPEN_TEN,Jackpot::OPEN_ANY_POINT,Jackpot::WASH_ZERO,Jackpot::WASH_ZERO_REMAINDER,Jackpot::MACHINE_POINT]);
        }
        if (!empty($request['ex_admin_filter']['user_id'])) {
            $machineOperationLog->where('user_id', $request['ex_admin_filter']['user_id']);
        }
        if (!empty($request['ex_admin_filter']['date_type'])) {
            if($request['ex_admin_filter']['date_type'] == 1) {
                $today = new DateTime('now');
                $todayStart = clone $today;
                $todayEnd = clone $today;
                $date = [$todayStart->setTime(0, 0, 0), $todayEnd->setTime(23, 59, 59, 999999)];
            } elseif ($request['ex_admin_filter']['date_type'] == 2) {
                $yesterday = (new DateTime('now'))->modify('-1 day');
                $yesterdayStart = clone $yesterday;
                $yesterdayEnd = clone $yesterday;
                $date = [$yesterdayStart->setTime(0, 0, 0, 0),$yesterdayEnd->setTime(23, 59, 59, 999999)];
            } elseif ($request['ex_admin_filter']['date_type'] == 3) {
                $thisWeekStart = (new DateTime('now'))->modify('this week');
                $thisWeekEnd = clone $thisWeekStart;
                $date = [$thisWeekStart->setTime(0, 0, 0, 0), $thisWeekEnd->modify('+6 days')->setTime(23, 59, 59, 999999)];
            } elseif ($request['ex_admin_filter']['date_type'] == 4) {
                $lastWeekStart = (new DateTime('now'))->modify('last week');
                $lastWeekEnd = clone $lastWeekStart;
                $date = [$lastWeekStart->setTime(0, 0, 0, 0), $lastWeekEnd->modify('+6 days')->setTime(23, 59, 59, 999999)];
            } elseif ($request['ex_admin_filter']['date_type'] == 5) {
                $thisMonthStart = (new DateTime('now'))->modify('first day of this month');
                $thisMonthEnd = new DateTime('last day of this month');
                $date = [$thisMonthStart->setTime(0, 0, 0, 0), $thisMonthEnd->setTime(23, 59, 59, 999999)];
            } elseif ($request['ex_admin_filter']['date_type'] == 6) {
                $lastMonthStart = (new DateTime('now'))->modify('first day of last month');
                $lastMonthEnd = new DateTime('last day of last month');
                $date = [$lastMonthStart->setTime(0, 0, 0, 0), $lastMonthEnd->setTime(23, 59, 59, 999999)];
            }
            $machineOperationLog->whereBetween('created_at', $date);
        }
        if (!empty($request['ex_admin_filter']['created_at_start'])) {
            $machineOperationLog->where('created_at', '>=', new DateTime($request['ex_admin_filter']['created_at_start']));
        }
        if (!empty($request['ex_admin_filter']['created_at_end'])) {
            $machineOperationLog->where('created_at', '<=', new DateTime($request['ex_admin_filter']['created_at_end']));
        }

        if (!empty($request['ex_admin_filter']['cate_id'])) {
            $machineOperationLog->whereIn('machine_cate', $request['ex_admin_filter']['cate_id']);
        }
        if (!empty($request['ex_admin_filter']['producer_id'])) {
            $machineOperationLog->where('producer_id', $request['ex_admin_filter']['producer_id']);
        }
        $countModel = clone $machineOperationLog;
        $total = $countModel->count('*');
        $list = $machineOperationLog
            ->forPage($request['ex_admin_page'] ?? 1, $request['ex_admin_size'] ?? 20)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
        return Grid::create($list, function (Grid $grid) use ($total, $list) {
            $grid->title(admin_trans('machine_operation_log.admin_actions'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            $grid->column('machine_name', admin_trans('machine.fields.name'))->align('center');
            $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function($val,$data){
                if(!isset($data['producer_id'])){
                    if(!Cache::has('machine_info:'.$data['machine_id'])){
                        /** @var Machine $machine */
                        $machine = Machine::query()->where('id',$data['machine_id'])->first();
                        Cache::set('machine_info:'.$data['machine_id'],$machine,60);
                    }else{
                        /** @var Machine $machine */
                        $machine = Cache::get('machine_info:'.$data['machine_id']);
                    }

                    return MachineProducer::query()->where('id',$machine['producer_id'])->value('name');
                }else{
                    return MachineProducer::query()->where('id',$data['producer_id'])->value('name');
                }

            })->align('center');
            $grid->column('machine_cate', admin_trans('machine.fields.cate_id'))->display(function($val,$data){
                if(!isset($data['machine_cate'])){
                    if(!Cache::has('machine_info:'.$data['machine_id'])){
                        /** @var Machine $machine */
                        $machine = Machine::query()->where('id',$data['machine_id'])->first();
                        Cache::set('machine_info:'.$data['machine_id'],$machine,60);
                    }else{
                        /** @var Machine $machine */
                        $machine = Cache::get('machine_info:'.$data['machine_id']);
                    }

                    return $machine->machineCategory()->value('name');
                }else{
                    return MachineCategory::query()->where('id',$data['machine_cate'])->value('name');
                }

            })->align('center');
            $grid->column('action', admin_trans('machine_operation_log.fields.action'))->display(function ($val, $data) {
                $point = '';
                if (!empty($data['point'])) {
                    $point = $data['point'];
                }
                if ($val == Jackpot::WASH_ZERO || $val == Jackpot::WASH_ZERO_REMAINDER) {
                    $content = json_decode($data['content'], true);
                    $point = $content['machine_tcp_data_cache_'.$data['machine_id'].'_point'];
                }
                return Tag::create(admin_trans('machine_operation_log.machine_action.' . $val).' '.$point);
            })->align('center');
            $grid->column('user_name', admin_trans('admin.admin_user'))->align('center');
            $grid->column('remark', admin_trans('machine.fields.remark'))->align('center');
            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine_code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machine_name')->placeholder(admin_trans('machine.fields.name'));
                $options = plugin()->webman->config('database.machine_producer_model')::select(['id', 'name'])->pluck('name', 'id')->all();
                $filter->eq()->select('producer_id')
                    ->placeholder(admin_trans('machine.fields.producer_id'))
                    ->showSearch()
                    ->style(['width' => '150px'])
                    ->dropdownMatchSelectWidth()
                    ->options($options);
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $filter->eq()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.admin_user'))
                    ->options(getAdminUserListOptions());
                $filter->eq()->select('machine_action')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine_operation_log.fields.action'))
                    ->options([
                        1 => admin_trans('machine_operation_log.action_type.1'),
                        2 => admin_trans('machine_operation_log.action_type.2'),
                    ]);
                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_operation_log.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('machine_operation_log.created_at_start'), admin_trans('machine_operation_log.created_at_end')]);
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

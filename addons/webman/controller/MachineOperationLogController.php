<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineOperationLog;
use addons\webman\model\MachineProducer;
use app\service\machine\Jackpot;
use app\service\machine\MachineServices;
use DateTime;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\Popover;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
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
     * 机台操作日志
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('machine_operation_log.log_type.0'), $this->getList('admin'))
            ->pane(admin_trans('machine_operation_log.log_type.1'), $this->getList('player'))
            ->pane(admin_trans('machine_operation_log.log_type.2'), $this->getList('system'))
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 机台操作日志
     * @param string $logType admin|player|system
     * @return Grid
     */
    public function getList($logType): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($logType) {
            switch ($logType) {
                case 'admin':
                    $grid->model()->where('user_id', '!=', 0)->where('is_system', 0);
                    break;
                case 'player':
                    $grid->model()->where('player_id', '!=', 0)->where('is_system', 0);
                    break;
                case 'system':
                    $grid->model()->where('is_system', 1);
                    break;
            }
            $grid->model()->orderBy('created_at', 'desc');

            $this->applyDepartmentScope($grid);
            $this->applyCommonFilters($grid);

            $grid->title(admin_trans('machine_operation_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            if ($logType === 'player') {
                $grid->column('player_phone', admin_trans('player.fields.phone'))->align('center');
                $grid->column('player_name', admin_trans('player.fields.name'))->align('center');
                $grid->column('uuid', admin_trans('player.fields.uuid'))->align('center');
            }
            $grid->column('machine_name', admin_trans('machine.fields.name'))->align('center');
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            if ($logType === 'admin') {
                $grid->column('user_name', admin_trans('admin.admin_user'))->align('center');
            }
            $grid->column('status', admin_trans('machine_operation_log.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('machine_operation_log.' . ($val == 1 ? 'action_success' : 'action_error')))
                    ->color(($val == 1 ? '#55acee' : '#cd201f'));
            })->align('center');
            $grid->column('action', admin_trans('machine_operation_log.fields.action'))->display(function (
                $val,
                MachineOperationLog $data
            ) {
                return getMachineOperationActionLabel((string)$val, (int)$data->machine_type);
            })->align('center');
            $grid->column('remark', admin_trans('machine_operation_log.fields.remark'))->align('center');
            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))->align('center');
            $grid->column('content', admin_trans('machine_operation_log.fields.content'))->display(function (
                $val,
                MachineOperationLog $data
            ) {
                return Popover::create(Button::create(admin_trans('machine_operation_log.view'))->size('small'))
                    ->content($this->renderContent($val, $data))
                    ->width('500px');
            })->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) use ($logType) {
                $filter->like()->text('machine_name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('machine.fields.code'));
                if ($logType === 'player') {
                    $filter->like()->text('player_name')->placeholder(admin_trans('player.fields.name'));
                    $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                }
                $filter->in()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.admin_user'))
                    ->options(getAdminUserListOptions())
                    ->multiple();
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('machine_operation_log.created_at_start'),
                    admin_trans('machine_operation_log.created_at_end'),
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 管理员操作（上分/下分）
     *
     * 菜单「日志中心 → 管理员操作」入口。只查管理员发起的上/下分。
     * @return Grid
     */
    public function actionsList(): Grid
    {
        $upActions = [Jackpot::OPEN_ONE, Jackpot::OPEN_TEN, Jackpot::OPEN_ANY_POINT];
        $downActions = [Jackpot::WASH_ZERO, Jackpot::WASH_ZERO_REMAINDER, Jackpot::MACHINE_POINT];

        return Grid::create(new $this->model(), function (Grid $grid) use ($upActions, $downActions) {
            $grid->model()
                ->where('user_id', '!=', 0)
                ->where('is_system', 0)
                ->whereIn('action', array_merge($upActions, $downActions))
                ->orderBy('created_at', 'desc');

            $requestFilter = Request::input('ex_admin_filter', []);

            if (!empty($requestFilter['machine_action'])) {
                $grid->model()->whereIn('action', $requestFilter['machine_action'] == 1 ? $upActions : $downActions);
            }
            if (!empty($requestFilter['date_type'])) {
                $date = $this->resolveDateRange((int)$requestFilter['date_type']);
                if ($date) {
                    $grid->model()->whereBetween('created_at', $date);
                }
            }
            if (!empty($requestFilter['cate_id'])) {
                $grid->model()->whereIn('machine_cate', (array)$requestFilter['cate_id']);
            }

            $this->applyDepartmentScope($grid);
            $this->applyCommonFilters($grid);

            $grid->title(admin_trans('machine_operation_log.admin_actions'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('machine_code', admin_trans('machine.fields.code'))->align('center');
            $grid->column('machine_name', admin_trans('machine.fields.name'))->align('center');
            $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function (
                $val,
                MachineOperationLog $data
            ) {
                $producerId = $this->resolveProducerId($data);

                return MachineProducer::query()->where('id', $producerId)->value('name') ?? '';
            })->align('center');
            $grid->column('machine_cate', admin_trans('machine.fields.cate_id'))->display(function (
                $val,
                MachineOperationLog $data
            ) {
                $cateId = $this->resolveMachineCate($data);

                return MachineCategory::query()->where('id', $cateId)->value('name') ?? '';
            })->align('center');
            $grid->column('action', admin_trans('machine_operation_log.fields.action'))->display(function (
                $val,
                MachineOperationLog $data
            ) use ($downActions) {
                $point = (int)$data->point;
                // 洗分历史数据点数可能只存在 content 快照里
                if ($point === 0 && in_array((string)$val, $downActions, true)) {
                    $point = (int)$this->resolveWashPoint($data);
                }

                return Tag::create(
                    getMachineOperationActionLabel((string)$val, (int)$data->machine_type) . ' ' . $point
                );
            })->align('center');
            $grid->column('user_name', admin_trans('admin.admin_user'))->align('center');
            $grid->column('remark', admin_trans('machine.fields.remark'))->align('center');
            $grid->column('created_at', admin_trans('machine_operation_log.fields.create_at'))->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) use ($requestFilter) {
                $filter->like()->text('machine_code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machine_name')->placeholder(admin_trans('machine.fields.name'));
                $producer = plugin()->webman->config('database.machine_producer_model');
                $producerOptions = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
                $filter->eq()->select('producer_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine.fields.producer_id'))
                    ->options($producerOptions);
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
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
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine_operation_log.fields.action'))
                    ->options([
                        1 => admin_trans('machine_operation_log.action_type.1'),
                        2 => admin_trans('machine_operation_log.action_type.2'),
                    ]);
                $filter->eq()->select('date_type')
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine_operation_log.date_type'))
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('machine_operation_log.created_at_start'),
                    admin_trans('machine_operation_log.created_at_end'),
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 渠道数据隔离。总后台不做过滤，渠道后台子类按登录人 department_id 收窄。
     */
    protected function applyDepartmentScope(Grid $grid): void
    {
    }

    /**
     * 两个列表共用的基础筛选
     */
    private function applyCommonFilters(Grid $grid): void
    {
        $requestFilter = Request::input('ex_admin_filter', []);

        if (!empty($requestFilter['machine_name'])) {
            $grid->model()->where('machine_name', 'like', '%' . $requestFilter['machine_name'] . '%');
        }
        if (!empty($requestFilter['machine_code'])) {
            $grid->model()->where('machine_code', 'like', '%' . $requestFilter['machine_code'] . '%');
        }
        if (!empty($requestFilter['player_name'])) {
            $grid->model()->where('player_name', 'like', '%' . $requestFilter['player_name'] . '%');
        }
        if (!empty($requestFilter['uuid'])) {
            $grid->model()->where('uuid', 'like', '%' . $requestFilter['uuid'] . '%');
        }
        if (!empty($requestFilter['producer_id'])) {
            $grid->model()->where('producer_id', (int)$requestFilter['producer_id']);
        }
        if (!empty($requestFilter['user_id'])) {
            $userIds = is_array($requestFilter['user_id'])
                ? $requestFilter['user_id']
                : [$requestFilter['user_id']];
            $grid->model()->whereIn('user_id', $userIds);
        }
        if (!empty($requestFilter['department_id'])) {
            $grid->model()->where('department_id', (int)$requestFilter['department_id']);
        }
        if (!empty($requestFilter['created_at_start'])) {
            $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
        }
        if (!empty($requestFilter['created_at_end'])) {
            $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
        }
    }

    /**
     * 操作周期 1今天 2昨天 3本周 4上周 5本月 6上月
     * @return array|null [start, end]
     */
    private function resolveDateRange(int $dateType): ?array
    {
        switch ($dateType) {
            case 1:
                $start = (new DateTime('now'))->setTime(0, 0, 0);
                $end = (new DateTime('now'))->setTime(23, 59, 59, 999999);

                return [$start, $end];
            case 2:
                $yesterday = (new DateTime('now'))->modify('-1 day');

                return [
                    (clone $yesterday)->setTime(0, 0, 0),
                    (clone $yesterday)->setTime(23, 59, 59, 999999),
                ];
            case 3:
                $weekStart = (new DateTime('now'))->modify('this week');

                return [
                    (clone $weekStart)->setTime(0, 0, 0),
                    (clone $weekStart)->modify('+6 days')->setTime(23, 59, 59, 999999),
                ];
            case 4:
                $lastWeekStart = (new DateTime('now'))->modify('last week');

                return [
                    (clone $lastWeekStart)->setTime(0, 0, 0),
                    (clone $lastWeekStart)->modify('+6 days')->setTime(23, 59, 59, 999999),
                ];
            case 5:
                $monthStart = (new DateTime('now'))->modify('first day of this month');

                return [
                    $monthStart->setTime(0, 0, 0),
                    (new DateTime('last day of this month'))->setTime(23, 59, 59, 999999),
                ];
            case 6:
                $lastMonthStart = (new DateTime('now'))->modify('first day of last month');

                return [
                    $lastMonthStart->setTime(0, 0, 0),
                    (new DateTime('last day of last month'))->setTime(23, 59, 59, 999999),
                ];
            default:
                return null;
        }
    }

    /**
     * 机台厂商id：日志快照缺失时回查机台（带缓存）
     */
    private function resolveProducerId(MachineOperationLog $data): int
    {
        if (!empty($data->producer_id)) {
            return (int)$data->producer_id;
        }

        $machine = $this->resolveMachine($data);

        return (int)($machine->producer_id ?? 0);
    }

    /**
     * 机台类别id：日志快照缺失时回查机台（带缓存）
     */
    private function resolveMachineCate(MachineOperationLog $data): int
    {
        if (!empty($data->machine_cate)) {
            return (int)$data->machine_cate;
        }

        $machine = $this->resolveMachine($data);

        return (int)($machine->cate_id ?? 0);
    }

    /**
     * 洗分点数：优先 point 列，回退 content 里的机台快照
     */
    private function resolveWashPoint(MachineOperationLog $data): int
    {
        if (!empty($data->point)) {
            return (int)$data->point;
        }

        $content = json_decode((string)$data->content, true);
        if (!is_array($content)) {
            return 0;
        }

        $key = 'machine_tcp_data_cache_' . $data->machine_id . '_point';

        return (int)($content[$key] ?? 0);
    }

    /**
     * 机台信息（60 秒缓存，避免列表 N+1）
     */
    private function resolveMachine(MachineOperationLog $data): ?Machine
    {
        $cacheKey = 'machine_info:' . $data->machine_id;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        /** @var Machine $machine */
        $machine = Machine::query()->find($data->machine_id);
        if ($machine) {
            Cache::set($cacheKey, $machine, 60);
        }

        return $machine;
    }

    /**
     * content 字段逐项转可读文案
     */
    private function renderContent($val, MachineOperationLog $data): string
    {
        $content = json_decode((string)$val, true);
        if (!is_array($content)) {
            return (string)$val;
        }

        $lines = [];
        foreach ($content as $key => $item) {
            $lines[] = MachineServices::getAttributeDes($key, $item, $data->machine_id);
        }

        return implode("\n", $lines);
    }
}

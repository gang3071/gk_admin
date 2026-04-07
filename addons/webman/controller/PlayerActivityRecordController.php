<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\ActivityContent;
use addons\webman\model\GameType;
use addons\webman\model\Notice;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerActivityRecord;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\service\WalletService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 活动管理
 */
class PlayerActivityRecordController
{
    protected $model;
    protected $playerActivityPhaseRecordModel;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_activity_record_model');
        $this->playerActivityPhaseRecordModel = plugin()->webman->config('database.player_activity_phase_record_model');

    }

    /**
     * 活动参与
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $lang = Container::getInstance()->translator->getLocale();
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->title(admin_trans('player_activity_record.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->model()->with(['player', 'machine', 'activity.activity_content'])->orderBy('created_at', 'desc');
            $grid->column('id', admin_trans('player_activity_record.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerActivityRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->align('center');
            $grid->column('type', admin_trans('game_type.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($val)),
                ]);
            })->align('center');
            $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function ($val, PlayerActivityRecord $data) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal(['addons-webman-controller-PlayerDeliveryRecordController', 'machineInfo'], ['data' => $data->machine->toArray()])->width('60%');
                }
                return '';
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->align('center');
            $grid->column('name', admin_trans('activity_content.fields.name'))->display(function ($val, PlayerActivityRecord $data) use ($lang) {
                /** @var ActivityContent $activityContent */
                $activityContent = $data->activity->activity_content->where('lang', $lang)->first();
                return Html::create($activityContent->name)->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])->modal(['addons-webman-controller-ActivityController', 'details'], ['id' => $data->activity_id])->width('60%');
            })->align('center');
            $grid->column('score', admin_trans('player_activity_record.fields.score'))->align('center');
            $grid->column('status', admin_trans('player_activity_record.fields.status'))->display(function ($val, PlayerActivityRecord $data) {
                switch ($data->status) {
                    case PlayerActivityRecord::STATUS_BEGIN:
                        return Tag::create(admin_trans('player_activity_record.status.' . $val))->color('green');
                    case PlayerActivityRecord::STATUS_FINISH:
                        return Tag::create(admin_trans('player_activity_record.status.' . $val))->color('red');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('created_at', admin_trans('player_activity_record.fields.created_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('activity.activity_content.name')->placeholder(admin_trans('activity_content.fields.name'));
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_type.fields.type'))
                    ->options([
                        GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                        GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL)
                    ]);
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_activity_record.fields.status'))
                    ->options([
                        PlayerActivityRecord::STATUS_BEGIN => admin_trans('player_activity_record.status.' . PlayerActivityRecord::STATUS_BEGIN),
                        PlayerActivityRecord::STATUS_FINISH => admin_trans('player_activity_record.status.' . PlayerActivityRecord::STATUS_FINISH),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('player_activity_record.created_at_start'), admin_trans('player_activity_record.created_at_end')]);
            });
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions, PlayerActivityRecord $data) {
                $actions->hideDel();
                $actions->prepend([
                    Button::create(admin_trans('player_activity_record.player_activity_phase_record'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal([$this, 'playerActivityPhaseRecord'], ['id' => $data->id])
                        ->width('70%')
                ]);
            });
        });
    }


    /**
     * 活动领取
     * @auth true
     */
    public function playerActivityPhaseRecord($id): Grid
    {
        return Grid::create(new $this->playerActivityPhaseRecordModel, function (Grid $grid) use ($id) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->bordered();
            $grid->autoHeight();
            $grid->model()->where('player_activity_record_id', $id);
            $this->gridColumn($grid, $lang);
            $grid->column('created_at', admin_trans('player_activity_phase_record.fields.created_at'))->align('center');
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 奖励领取审核
     * @auth true
     */
    public function examine(): Grid
    {
        return Grid::create(new $this->playerActivityPhaseRecordModel(), function (Grid $grid) {
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            $grid->bordered(true);
            $grid->autoHeight();
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('player_activity_phase_record.examine_title'));
            $grid->model()->with(['player', 'machine', 'activity.activity_content', 'machine.machineCategory'])->where('status', PlayerActivityPhaseRecord::STATUS_RECEIVED)->orderBy('created_at', 'desc');
            $this->exmaineGrid($grid, $lang);
            $grid->column('status', admin_trans('player_activity_phase_record.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('player_activity_phase_record.status.' . $val))->color('red');
            })->align('center');
            $grid->column('user_name', admin_trans('player_activity_phase_record.fields.user_name'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                return Html::create()->content([
                    Html::div()->content($data->user_name ?? ''),
                ]);
            })->align('center');
            $grid->column('created_at', admin_trans('player_activity_phase_record.fields.created_at'))->align('center');
            $this->listFilter($grid);
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $dropdown = Dropdown::create(
                Button::create(
                    [
                        admin_trans('player_activity_phase_record.bath_action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px']),
                    ]
                )
            )->trigger(['click']);
            $dropdown->item(admin_trans('player_activity_phase_record.btn.examine_reject'), 'far fa-question-circle')
                ->modal([$this, 'bathReject'])
                ->gridBatch();
            $dropdown->item(admin_trans('player_activity_phase_record.btn.examine_pass'), 'far fa-check-circle')
                ->confirm(admin_trans('player_activity_phase_record.btn.examine_pass_confirm'), [$this, 'bathPass'])
                ->gridBatch();
            $grid->tools(
                $dropdown
            );
            $grid->actions(function (Actions $actions, PlayerActivityPhaseRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('player_activity_phase_record.btn.action'), Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);

                $dropdown->item(admin_trans('player_activity_phase_record.btn.examine_pass'), 'SafetyCertificateOutlined')
                    ->confirm(admin_trans('player_activity_phase_record.btn.examine_pass_confirm'), [$this, 'pass'], ['id' => $data->id])
                    ->gridRefresh();

                $dropdown->item(admin_trans('player_activity_phase_record.btn.examine_reject'), 'WarningFilled')
                    ->modal([$this, 'reject'], ['id' => $data->id])
                    ->gridRefresh();
                $actions->prepend(
                    $dropdown
                );
            });
        });
    }

    /**
     * 批量审核通过
     * @return Msg
     * @auth true
     */
    public function bathPass(): Msg
    {
        $data = Request::input();
        $selected = $data['selected'] ?? [];
        if (!empty($selected)) {
            $playerActivityPhaseRecords = $this->playerActivityPhaseRecordModel::with('player.machine_wallet')->whereIn('id', $selected)->orderBy('id', 'desc')->get();
            /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
            foreach ($playerActivityPhaseRecords as $playerActivityPhaseRecord) {
                switch ($playerActivityPhaseRecord->status) {
                    case PlayerActivityPhaseRecord::STATUS_UNRECEIVED:
                        return message_warning(admin_trans('player_activity_phase_record.record_unreceived'));
                    case PlayerActivityPhaseRecord::STATUS_COMPLETE:
                        return message_warning(admin_trans('player_activity_phase_record.record_complete'));
                    case PlayerActivityPhaseRecord::STATUS_REJECT:
                        return message_warning(admin_trans('player_activity_phase_record.record_reject'));
                }
            }
            $playerDeliveryRecords = [];
            $adminId = Admin::id() ?? 0;
            $adminUsername = !empty(Admin::user()) ? Admin::user()->username : '';

            DB::beginTransaction();
            try {
                foreach ($playerActivityPhaseRecords as $playerActivityPhaseRecord) {
                    // ✅ 步骤 1: 获取 Redis 分布式锁
                    $lockKey = "player:balance:lock:{$playerActivityPhaseRecord->player_id}";
                    $lock = \support\Redis::set($lockKey, 1, ['NX', 'EX' => 10]);
                    if (!$lock) {
                        throw new \Exception('玩家 ' . $playerActivityPhaseRecord->player_id . ' 操作繁忙，请稍后重试');
                    }

                    try {
                        // ✅ 步骤 2: 从 Redis 读取当前余额（唯一可信源）
                        $beforeGameAmount = WalletService::getBalance($playerActivityPhaseRecord->player_id);

                        // ✅ 步骤 3: 使用 WalletService 原子性增加余额（自动同步数据库）
                        $newBalance = WalletService::atomicIncrement($playerActivityPhaseRecord->player_id, $playerActivityPhaseRecord->bonus);

                        $playerActivityPhaseRecord->status = PlayerActivityPhaseRecord::STATUS_COMPLETE;
                        $playerActivityPhaseRecord->user_id = $adminId;
                        $playerActivityPhaseRecord->user_name = $adminUsername;
                        $playerActivityPhaseRecord->push();

                        //寫入金流明細
                        $playerDeliveryRecords[] = [
                            'player_id' => $playerActivityPhaseRecord->player_id,
                            'department_id' => $playerActivityPhaseRecord->player->department_id,
                            'target' => $playerActivityPhaseRecord->getTable(),
                            'target_id' => $playerActivityPhaseRecord->id,
                            'type' => PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
                            'source' => 'activity',
                            'amount' => $playerActivityPhaseRecord->bonus,
                            'amount_before' => $beforeGameAmount,
                            'amount_after' => $newBalance,  // ✅ 使用 Redis 计算的新值
                            'tradeno' => '',
                            'remark' => '',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                    } finally {
                        // ✅ 释放 Redis 锁
                        \support\Redis::del($lockKey);
                    }
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerActivityPhaseRecord->player_id;
                    $notice->source_id = $playerActivityPhaseRecord->id;
                    $notice->type = Notice::TYPE_ACTIVITY_PASS;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = admin_trans('player_activity_record.notice.activity_pass_title');
                    $notice->content = admin_trans('player_activity_record.notice.activity_pass_content', null, [
                        '{bonus}' => $playerActivityPhaseRecord->bonus
                    ]);
                    $notice->save();
                }

                PlayerDeliveryRecord::insert(array_reverse($playerDeliveryRecords));
                DB::commit();
                return message_success(admin_trans('player_activity_phase_record.action_success'))->refresh();
            } catch (\Exception $e) {
                DB::rollBack();
                return message_error($e->getMessage());
            }
        }
        return message_error(admin_trans('player_activity_phase_record.bath_not_found'));
    }

    /**
     * 批量审核拒绝
     * @auth true
     * @return Form
     */
    public function bathReject(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $data = Request::input();
            $selected = $data['selected'] ?? [];
            $form->textarea('reject_reason')->rows(5)->required();
            $form->hidden('selected')->value($selected);
            $form->saving(function (Form $form) {
                $selected = $form->input('selected');
                $playerActivityPhaseRecords = $this->playerActivityPhaseRecordModel::whereIn('id', $selected)->get();
                if (empty($playerActivityPhaseRecords)) {
                    return message_error(admin_trans('player_activity_phase_record.not_fount'));
                }
                /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                foreach ($playerActivityPhaseRecords as $playerActivityPhaseRecord) {
                    switch ($playerActivityPhaseRecord->status) {
                        case PlayerActivityPhaseRecord::STATUS_UNRECEIVED:
                            return message_warning(admin_trans('player_activity_phase_record.record_unreceived'));
                        case PlayerActivityPhaseRecord::STATUS_COMPLETE:
                            return message_warning(admin_trans('player_activity_phase_record.record_complete'));
                        case PlayerActivityPhaseRecord::STATUS_REJECT:
                            return message_warning(admin_trans('player_activity_phase_record.record_reject'));
                    }
                }
                try {
                    $this->playerActivityPhaseRecordModel::whereIn('id', $selected)->update([
                        'status' => PlayerActivityPhaseRecord::STATUS_REJECT,
                        'reject_reason' => $form->input('reject_reason'),
                        'user_id' => Admin::id() ?? 0,
                        'user_name' => !empty(Admin::user()) ? Admin::user()->username : '',
                    ]);
                } catch (\Exception $e) {
                    return message_error(admin_trans('player_activity_phase_record.action_error'));
                }
                /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                foreach ($playerActivityPhaseRecords as $playerActivityPhaseRecord) {
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerActivityPhaseRecord->player_id;
                    $notice->source_id = $playerActivityPhaseRecord->id;
                    $notice->type = Notice::TYPE_ACTIVITY_REJECT;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = admin_trans('player_activity_record.notice.activity_reject_title');
                    $notice->content = admin_trans('player_activity_record.notice.activity_reject_content', null, [
                        '{reason}' => $form->input('reject_reason') ?? ''
                    ]);
                    $notice->save();
                }


                return message_success(admin_trans('player_activity_phase_record.action_success'));
            });
        });
    }

    /**
     * 活动领取记录
     * @auth true
     */
    public function receiveList(): Grid
    {
        return Grid::create(new $this->playerActivityPhaseRecordModel(), function (Grid $grid) {
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            $grid->bordered(true);
            $grid->autoHeight();
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('player_activity_phase_record.receive_title'));
            $grid->model()->with([
                'player',
                'machine',
                'activity.activity_content',
                'player.channel'
            ])->orderBy('created_at', 'desc');
            $query = clone $grid->model();
            $totalData = $query->selectRaw(
                'SUM(bonus) AS total_bonus,
                sum(IF(status = ' . PlayerActivityPhaseRecord::STATUS_UNRECEIVED . ', bonus,0)) as total_unreviewed_amount, 
                sum(IF(status = ' . PlayerActivityPhaseRecord::STATUS_REJECT . ', bonus,0)) as total_reject_amount, 
                sum(IF(status = ' . PlayerActivityPhaseRecord::STATUS_RECEIVED . ', bonus,0)) as total_pass_amount, 
                sum(IF(status = ' . PlayerActivityPhaseRecord::STATUS_COMPLETE . ', bonus,0)) as total_complete_amount')
                ->where(function ($query) use($exAdminFilter) {
                    if (!empty($exAdminFilter['player']['phone'])){
                        $query->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('phone', 'like', "%{$exAdminFilter['player']['phone']}%");
                        });
                    }
                    if (!empty($exAdminFilter['player']['uuid'])) {
                        $query->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('uuid', '=', $exAdminFilter['player']['uuid']);
                        });
                    }
                    if (!empty($exAdminFilter['machine']['code'])) {
                        $query->whereHas('machine', function ($query) use ($exAdminFilter) {
                            $query->where('code', '=', $exAdminFilter['machine']['code']);
                        });
                    }
                    if (!empty($exAdminFilter['machine']['name'])) {
                        $query->whereHas('machine.machineLabel', function ($query) use ($exAdminFilter) {
                            $query->where('name', 'like', "%{$exAdminFilter['machine']['name']}%");
                        });
                    }
                    if (!empty($exAdminFilter['activity']['activity_content']['name'])) {
                        $query->whereHas('activity.activity_content', function ($query) use ($exAdminFilter) {
                            $query->where('name', 'like', "%{$exAdminFilter['activity']['activity_content']['name']}%");
                        });
                    }
                    if (!empty($exAdminFilter['status'])) {
                        $query->where('status', '=', $exAdminFilter['status']);
                    }
                    if (!empty($exAdminFilter['department_id'])) {
                        $query->where('department_id', '=', $exAdminFilter['department_id']);
                    }

                })
                ->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_bonus']) ? floatval($totalData['total_bonus']) : 0)
                            ->prefix(admin_trans('promoter_profit_game_record.fields.total_reward'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_complete_amount']) ? floatval($totalData['total_complete_amount']) : 0)
                            ->prefix(admin_trans('player_activity_phase_record.status.'. PlayerActivityPhaseRecord::STATUS_COMPLETE))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_pass_amount']) ? floatval($totalData['total_pass_amount']) : 0)
                            ->prefix(admin_trans('player_activity_phase_record.status.'. PlayerActivityPhaseRecord::STATUS_RECEIVED))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_unreviewed_amount']) ? floatval($totalData['total_unreviewed_amount']) : 0)
                            ->prefix(admin_trans('player_activity_phase_record.status.'. PlayerActivityPhaseRecord::STATUS_UNRECEIVED))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_reject_amount']) ? floatval($totalData['total_reject_amount']) : 0)
                            ->prefix(admin_trans('player_activity_phase_record.status.'. PlayerActivityPhaseRecord::STATUS_REJECT))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
            })->style(['background' => '#fff']);
            $grid->tools([
                $layout
            ]);
            $this->gridColumn($grid, $lang);
            $grid->column('user_name',
                admin_trans('player_activity_phase_record.fields.user_name'))->display(function (
                $val,
                PlayerActivityPhaseRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->user_name ?? ''),
                ]);
            })->align('center');
            $grid->hideTrashed();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->column('created_at',
                admin_trans('player_activity_phase_record.fields.created_at'))->sortable()->align('center');
            $grid->column('updated_at',
                admin_trans('player_activity_phase_record.fields.updated_at'))->sortable()->align('center');
            $grid->actions(function (Actions $actions, PlayerActivityPhaseRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $this->listFilter($grid);
        });
    }

    /**
     * 审核拒绝
     * @auth true
     * @param $id
     * @return Form
     */
    public function reject($id): Form
    {
        return Form::create(new $this->playerActivityPhaseRecordModel(), function (Form $form) use ($id) {
            $form->textarea('reject_reason')->rows(5)->required();
            $form->saving(function (Form $form) use ($id) {
                /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                $playerActivityPhaseRecord = $this->playerActivityPhaseRecordModel::find($id);
                if (empty($playerActivityPhaseRecord)) {
                    return message_error(admin_trans('player_activity_phase_record.not_fount'));
                }
                switch ($playerActivityPhaseRecord->status) {
                    case PlayerActivityPhaseRecord::STATUS_UNRECEIVED:
                        return message_warning(admin_trans('player_activity_phase_record.record_unreceived'));
                    case PlayerActivityPhaseRecord::STATUS_COMPLETE:
                        return message_warning(admin_trans('player_activity_phase_record.record_complete'));
                    case PlayerActivityPhaseRecord::STATUS_REJECT:
                        return message_warning(admin_trans('player_activity_phase_record.record_reject'));
                }
                try {
                    // 生成订单
                    $playerActivityPhaseRecord->status = PlayerActivityPhaseRecord::STATUS_REJECT;
                    $playerActivityPhaseRecord->reject_reason = $form->input('reject_reason');
                    $playerActivityPhaseRecord->user_id = Admin::id() ?? 0;
                    $playerActivityPhaseRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                    $playerActivityPhaseRecord->save();
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerActivityPhaseRecord->player_id;
                    $notice->source_id = $playerActivityPhaseRecord->id;
                    $notice->type = Notice::TYPE_ACTIVITY_REJECT;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = admin_trans('player_activity_record.notice.activity_reject_title');
                    $notice->content = admin_trans('player_activity_record.notice.activity_reject_content', null, [
                        '{reason}' => $playerActivityPhaseRecord->reject_reason
                    ]);
                    $notice->save();
                } catch (\Exception $e) {
                    return message_error(admin_trans('player_activity_phase_record.action_error'));
                }
                return message_success(admin_trans('player_activity_phase_record.action_success'));
            });
        });
    }

    /**
     * 审核通过
     * @param $id
     * @auth true
     * @return Msg
     */
    public function pass($id): Msg
    {
        /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
        $playerActivityPhaseRecord = $this->playerActivityPhaseRecordModel::find($id);
        if (empty($playerActivityPhaseRecord)) {
            return message_error(admin_trans('player_activity_phase_record.not_fount'));
        }
        switch ($playerActivityPhaseRecord->status) {
            case PlayerActivityPhaseRecord::STATUS_UNRECEIVED:
                return message_warning(admin_trans('player_activity_phase_record.record_unreceived'));
            case PlayerActivityPhaseRecord::STATUS_COMPLETE:
                return message_warning(admin_trans('player_activity_phase_record.record_complete'));
            case PlayerActivityPhaseRecord::STATUS_REJECT:
                return message_warning(admin_trans('player_activity_phase_record.record_reject'));
        }
        DB::beginTransaction();
        try {
            // ✅ 步骤 1: 获取 Redis 分布式锁
            $lockKey = "player:balance:lock:{$playerActivityPhaseRecord->player_id}";
            $lock = \support\Redis::set($lockKey, 1, ['NX', 'EX' => 10]);
            if (!$lock) {
                return message_error('操作繁忙，请稍后重试');
            }

            try {
                // ✅ 步骤 2: 从 Redis 读取当前余额（唯一可信源）
                $beforeGameAmount = WalletService::getBalance($playerActivityPhaseRecord->player_id);

                // ✅ 步骤 3: 使用 WalletService 原子性增加余额（自动同步数据库）
                $newBalance = WalletService::atomicIncrement($playerActivityPhaseRecord->player_id, $playerActivityPhaseRecord->bonus);

                $playerActivityPhaseRecord->status = PlayerActivityPhaseRecord::STATUS_COMPLETE;
                $playerActivityPhaseRecord->user_id = Admin::id() ?? 0;
                $playerActivityPhaseRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                $playerActivityPhaseRecord->push();

                //寫入金流明細
                $playerDeliveryRecord = new PlayerDeliveryRecord;
                $playerDeliveryRecord->player_id = $playerActivityPhaseRecord->player_id;
                $playerDeliveryRecord->department_id = $playerActivityPhaseRecord->player->department_id;
                $playerDeliveryRecord->target = $playerActivityPhaseRecord->getTable();
                $playerDeliveryRecord->target_id = $playerActivityPhaseRecord->id;
                $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS;
                $playerDeliveryRecord->source = 'activity';
                $playerDeliveryRecord->amount = $playerActivityPhaseRecord->bonus;
                $playerDeliveryRecord->amount_before = $beforeGameAmount;
                $playerDeliveryRecord->amount_after = $newBalance;  // ✅ 使用 Redis 计算的新值
                $playerDeliveryRecord->tradeno = $playerActivityPhaseRecord->tradeno ?? '';
                $playerDeliveryRecord->remark = $playerActivityPhaseRecord->remark ?? '';
                $playerDeliveryRecord->save();
            } finally {
                // ✅ 释放 Redis 锁
                \support\Redis::del($lockKey);
            }
            // 发送站内信
            $notice = new Notice();
            $notice->department_id = Admin::user()->department_id;
            $notice->player_id = $playerActivityPhaseRecord->player_id;
            $notice->source_id = $playerActivityPhaseRecord->id;
            $notice->type = Notice::TYPE_ACTIVITY_PASS;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $notice->title = admin_trans('player_activity_record.notice.activity_pass_title');
            $notice->content = admin_trans('player_activity_record.notice.activity_pass_content', null, [
                '{bonus}' => $playerActivityPhaseRecord->bonus
            ]);
            $notice->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return message_error(admin_trans('player_activity_phase_record.action_error'));
        }
        return message_success(admin_trans('player_activity_phase_record.action_success'));
    }

    /**
     * @param Grid $grid
     * @return void
     */
    protected function listFilter(Grid $grid): void
    {
        $grid->filter(function (Filter $filter) {
            $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
            $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
            $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
            $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
            $filter->like()->text('activity.activity_content.name')->placeholder(admin_trans('activity_content.fields.name'));
            $filter->eq()->select('department_id')
                ->showSearch()
                ->style(['width' => '200px'])
                ->dropdownMatchSelectWidth()
                ->placeholder(admin_trans('announcement.fields.department_id'))
                ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
            $filter->eq()->select('status')
                ->placeholder(admin_trans('player_activity_phase_record.fields.status'))
                ->showSearch()
                ->style(['width' => '200px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    PlayerActivityPhaseRecord::STATUS_UNRECEIVED => admin_trans('player_activity_phase_record.status.' . PlayerActivityPhaseRecord::STATUS_UNRECEIVED),
                    PlayerActivityPhaseRecord::STATUS_RECEIVED => admin_trans('player_activity_phase_record.status.' . PlayerActivityPhaseRecord::STATUS_RECEIVED),
                    PlayerActivityPhaseRecord::STATUS_COMPLETE => admin_trans('player_activity_phase_record.status.' . PlayerActivityPhaseRecord::STATUS_COMPLETE),
                    PlayerActivityPhaseRecord::STATUS_REJECT => admin_trans('player_activity_phase_record.status.' . PlayerActivityPhaseRecord::STATUS_REJECT),
                ]);
            $filter->select('date_type')
                ->placeholder(admin_trans('machine_report.fields.date_type'))
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
            $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
        });
    }

    /**
     * @param Grid $grid
     * @param string $lang
     * @return void
     */
    protected function gridColumn(Grid $grid, string $lang): void
    {
        $this->exmaineGrid($grid, $lang);
        $grid->column('status', admin_trans('player_activity_phase_record.fields.status'))->display(function ($val, PlayerActivityPhaseRecord $data) {
            switch ($data->status) {
                case PlayerActivityPhaseRecord::STATUS_UNRECEIVED:
                    return Tag::create(admin_trans('player_activity_phase_record.status.' . $val))->color('green');
                case PlayerActivityPhaseRecord::STATUS_RECEIVED:
                    return Tag::create(admin_trans('player_activity_phase_record.status.' . $val))->color('red');
                case PlayerActivityPhaseRecord::STATUS_COMPLETE:
                    return Tag::create(admin_trans('player_activity_phase_record.status.' . $val))->color('orange');
                case PlayerActivityPhaseRecord::STATUS_REJECT:
                    return ToolTip::create(Tag::create(admin_trans('player_activity_phase_record.status.' . $val))->color('blue'))->title($data->reject_reason ?? '')->color('black');
                default:
                    return '';
            }
        })->align('center');
    }

    /**
     * @param Grid $grid
     * @param string $lang
     * @return void
     */
    protected function exmaineGrid(Grid $grid, string $lang): void
    {
        $grid->column('id', admin_trans('player_activity_phase_record.fields.id'))->align('center');
        $grid->column('name', admin_trans('activity_content.fields.name'))->display(function ($val, PlayerActivityPhaseRecord $data) use ($lang) {
            /** @var ActivityContent $activityContent */
            $activityContent = $data->activity->activity_content->where('lang', $lang)->first();
            return Html::create($activityContent->name)->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])->modal(['addons-webman-controller-ActivityController', 'details'], ['id' => $data->activity_id])->width('60%');
        })->align('center');
        $grid->column(function (Grid $grid) {
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerActivityPhaseRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($val),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                return $data->player->phone;
            })->align('center');
            $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                return $data->player->channel->name;
            })->width('150px')->align('center');
        }, admin_trans('player_game_log.player_info'));
        $grid->column(function (Grid $grid) {
            $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal(['addons-webman-controller-PlayerDeliveryRecordController', 'machineInfo'], ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                }
                return '';
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                return $data->machine->code ?? '';
            })->align('center');
            $grid->column('odds', admin_trans('player_game_log.fields.odds'))->display(function ($val, PlayerActivityPhaseRecord $data) {
                if (!empty($data->machine)) {
                    $odds = $data->machine->odds_x . ':' . $data->machine->odds_y;
                    if ($data->machine->type == GameType::TYPE_STEEL_BALL) {
                        $odds = $data->machine->machineCategory->name;
                    }
                    return $odds;
                }
                return '';
            })->align('center');
        }, admin_trans('player_game_log.machine_info'));
        $grid->column('condition', admin_trans('player_activity_phase_record.fields.condition'))->align('center');
        $grid->column('bonus', admin_trans('player_activity_phase_record.fields.bonus'))->align('center');
        $grid->column('player_score', admin_trans('player_activity_phase_record.fields.player_score'))->align('center');
        $grid->expandFilter();
    }
}

<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Activity;
use addons\webman\model\GameType;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerWalletTransfer;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\echart\LineChart;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Carbon;
use support\Db;

/**
 * 账变记录
 * @group channel
 */
class ChannelPlayerDeliveryRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_delivery_record_model');
    }

    /**
     * 玩家账变
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_delivery_record.title'));
            $grid->model()->with(['player', 'machine'])->orderBy('created_at', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $exAdminFilter = Request::input('ex_admin_filter', []);

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();
            if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                // 代理：查询所有下级店家的玩家记录
                $storeIds = $admin->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->pluck('id');
                $grid->model()->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds);
                });
            } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_STORE) {
                // 店家：查询自己的玩家记录
                $grid->model()->whereHas('player', function ($query) use ($admin) {
                    $query->where('store_admin_id', $admin->id);
                });
            }
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', $exAdminFilter['player']['phone']);
                });
            }
            if (!empty($exAdminFilter['player']['recommend_player']['uuid'])) {
                $grid->model()->whereHas('player.recommend_player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['recommend_player']['uuid']);
                });
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }
            if (!empty($exAdminFilter['search_source'])) {
                $searchSource = $exAdminFilter['search_source'];
                $grid->model()->where(function ($query) use ($searchSource) {
                    $query->where([
                        ['code', 'like', '%' . $searchSource . '%', 'or'],
                        ['machine_name', 'like', '%' . $searchSource . '%', 'or']
                    ])->orWhere(function ($query) use ($searchSource) {
                        $query->where([
                            ['source', 'like', '%' . $searchSource . '%', 'and'],
                        ])->whereIn('type',
                            [PlayerDeliveryRecord::TYPE_PRESENT_IN, PlayerDeliveryRecord::TYPE_PRESENT_OUT]);
                    })->orWhere(function ($query) use ($searchSource) {
                        $query->whereHas('gamePlatform', function ($query) use ($searchSource) {
                            $query->where([
                                ['name', 'like', '%' . $searchSource . '%', 'or'],
                            ]);
                        })->whereIn('type',
                            [
                                PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT,
                                PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN
                            ]);
                    });
                });
            }
            if (!empty($exAdminFilter['activity'])) {
                $target_id = PlayerMoneyEditLog::query()
                    ->leftJoin('activity_content','activity_content.id','player_money_edit_log.activity')
                    ->where('activity_id', $exAdminFilter['activity'])
                    ->pluck('player_money_edit_log.id')->toArray();
                $grid->model()->where('target', 'player_money_edit_log')->whereIn('target_id',$target_id);
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(if(`type`=' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ',`amount`,0)) as total_in_amount, sum(if(`type`= ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ',`amount`,0)) as total_out_amount, sum(if(`type`= ' . PlayerDeliveryRecord::TYPE_MACHINE . ',`amount`,0)) as total_machine_amount')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_in_amount']) ? floatval($totalData['total_in_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_in_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_out_amount']) ? floatval($totalData['total_out_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_out_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_machine_amount']) ? floatval($totalData['total_machine_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_machine_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(bcsub(bcadd(($totalData['total_machine_amount'] ?? 0),
                            ($totalData['total_in_amount'] ?? 0), 2), ($totalData['total_out_amount'] ?? 0),
                            2))->prefix(admin_trans('common.total'))->valueStyle([
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
            $grid->tools([$layout]);
            $grid->bordered();
            $grid->autoHeight();
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone)
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('player.phone')
            );
            $grid->column('name', admin_trans('player.fields.device_name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                if ($data->machine) {
                    return Html::create()->content([
                        Html::div()->content($data->machine->name),
                    ]);
                }
                return '-';
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerDeliveryRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('source', admin_trans('player_delivery_record.fields.source'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                // 优先使用翻译，如果翻译不存在则使用原始值
                $transKey = 'message.target.' . $val;
                $translatedText = admin_trans($transKey);
                // 如果翻译不存在（返回的是翻译键本身），则使用原始值
                if ($translatedText === $transKey) {
                    $translatedText = $val;
                }

                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_RECHARGE:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK:
                    case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                    case PlayerDeliveryRecord::COIN_ADD:
                    case PlayerDeliveryRecord::COIN_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_SPECIAL:
                    case PlayerDeliveryRecord::TYPE_MACHINE:
                        return Tag::create($translatedText)->color('red');
                    case PlayerDeliveryRecord::TYPE_PRESENT_IN:
                    case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                        return Tag::create($translatedText)->color('green');
                    case PlayerDeliveryRecord::TYPE_BET:
                    case PlayerDeliveryRecord::TYPE_CANCEL_BET:
                    case PlayerDeliveryRecord::TYPE_SETTLEMENT:
                    case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS:
                    case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                        return Tag::create($translatedText)->color('blue');
                    case PlayerDeliveryRecord::TYPE_GIFT:
                        return Tag::create($translatedText)->color('pink');
                    case PlayerDeliveryRecord::TYPE_PREPAY:
                    case PlayerDeliveryRecord::TYPE_REFUND:
                        return Tag::create($translatedText)->color('cyan');
                    case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                    case PlayerDeliveryRecord::TYPE_MACHINE_DOWN:
                        if ($data->machine) {
                            return Tag::create($data->machine->code)->color('orange')->style(['cursor' => 'pointer'])->modal([
                                $this,
                                'machineInfo'
                            ],
                                ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                        }
                        break;
                    case PlayerDeliveryRecord::TYPE_PROFIT:
                    case PlayerDeliveryRecord::TYPE_REVERSE_WATER:
                        return Tag::create($translatedText)->color('purple');
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN:
                        /** @var PlayerWalletTransfer $playerWalletTransfer */
                        $playerWalletTransfer = PlayerWalletTransfer::query()->where('id', $data->target_id)->first();
                        return Tag::create($playerWalletTransfer->gamePlatform->name)->color('purple');
                    case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                    case PlayerDeliveryRecord::TYPE_RECHARGE_REWARD:
                    case PlayerDeliveryRecord::TYPE_DAMAGE_REBATE:
                    case PlayerDeliveryRecord::TYPE_LOTTERY:
                        return Tag::create($translatedText)->color('orange');
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                        /** @var PlayerWalletTransfer $playerWalletTransfer */
                        $playerWalletTransfer = PlayerWalletTransfer::query()->where('id', $data->target_id)->first();
                        return Tag::create($playerWalletTransfer->gamePlatform->name)->color('orange');
                    case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                    case PlayerDeliveryRecord::TYPE_AGENT_IN:
                        return Tag::create($data->player->channel->name)->color('orange');
                    default:
                        return $translatedText ? Tag::create($translatedText)->color('gray') : '';
                }
            })->align('center');
            $grid->column('type', admin_trans('player_delivery_record.fields.type'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD))->color('#2db7f5');
                            break;
                        case PlayerDeliveryRecord::TYPE_PRESENT_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_IN))->color('#8D3514');
                            break;
                        case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_OUT))->color('#f50');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_UP))->color('#9FCC84');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE_DOWN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_DOWN))->color('#668A50');
                            break;
                        case PlayerDeliveryRecord::TYPE_RECHARGE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE))->color('#3C87C9');
                            break;
                        case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL))->color('#C98341');
                            break;
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_PROFIT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PROFIT))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_LOTTERY:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_LOTTERY))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_RECHARGE_REWARD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_DAMAGE_REBATE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_REVERSE_WATER:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REVERSE_WATER))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_SPECIAL:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SPECIAL))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_OUT))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_AGENT_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_IN))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_BET:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_BET))->color('#1890ff');
                            break;
                        case PlayerDeliveryRecord::TYPE_CANCEL_BET:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_CANCEL_BET))->color('#52c41a');
                            break;
                        case PlayerDeliveryRecord::TYPE_GIFT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GIFT))->color('#eb2f96');
                            break;
                        case PlayerDeliveryRecord::TYPE_SETTLEMENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SETTLEMENT))->color('#13c2c2');
                            break;
                        case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RE_SETTLEMENT))->color('#722ed1');
                            break;
                        case PlayerDeliveryRecord::TYPE_PREPAY:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PREPAY))->color('#fa8c16');
                            break;
                        case PlayerDeliveryRecord::TYPE_REFUND:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REFUND))->color('#a0d911');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center')->sortable();
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Html::create()->content([
                    Html::div()->content($value),
                ]);
            })->width('150px')->align('center');
            $grid->column('activity',admin_trans('activity.title'))->display(function ($activity,PlayerDeliveryRecord $data) {
                if($data->target == 'player_money_edit_log') {
                    $activityContent = PlayerMoneyEditLog::query()
                        ->leftJoin('activity_content', 'activity_content.id', '=', 'player_money_edit_log.activity')
                        ->select('activity_content.name','activity_content.activity_id')
                        ->where('player_money_edit_log.id', '=', $data->target_id)
                        ->first();
                    return Html::create()->content([
                        Html::div()->content($activity),
                        Html::create($activityContent->name)->style([
                            'cursor' => 'pointer',
                            'color' => 'rgb(24, 144, 255)'
                        ])->drawer(['addons-webman-controller-ActivityController', 'details'], ['id' => $activityContent->activity_id]),
                    ]);
                }
            });
            $grid->column('amount', admin_trans('player_delivery_record.fields.amount'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                    case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                    case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                    case PlayerDeliveryRecord::TYPE_BET:
                    case PlayerDeliveryRecord::TYPE_GIFT:
                    case PlayerDeliveryRecord::TYPE_PREPAY:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                    case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                        if ($val < 0) {
                            return Html::create()->content([$val])->style(['color' => '#cd201f']);
                        }
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                    default:
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                }
            })->align('center');
            $grid->column('amount_after', admin_trans('player_delivery_record.fields.amount_after'))->align('center');
            $grid->column('amount_before', admin_trans('player_delivery_record.fields.amount_before'))->align('center');
            $grid->column('user_name', admin_trans('player_delivery_record.fields.user_name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $name = admin_trans('player.player');
                if (in_array($data->type, [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                ])) {
                    $name = $data->user_name ?? admin_trans('common.default.admin');
                }
                if ($data->type == PlayerDeliveryRecord::TYPE_MACHINE_DOWN && !empty($data->user_id)) {
                    $name = $data->user_name ?? admin_trans('common.default.admin');
                }
                return Html::create()->content([
                    Html::div()->content($name),
                ]);
            });
            $grid->column('created_at',
                admin_trans('player_delivery_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('search_source')->placeholder(admin_trans('player_delivery_record.fields.source'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_wallet_transfer.fields.platform_name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->in()->select('type')
                    ->placeholder(admin_trans('player_delivery_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->multiple()
                    ->options([
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD),
                        PlayerDeliveryRecord::TYPE_PRESENT_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_IN),
                        PlayerDeliveryRecord::TYPE_PRESENT_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_OUT),
                        PlayerDeliveryRecord::TYPE_MACHINE_UP => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_UP),
                        PlayerDeliveryRecord::TYPE_MACHINE_DOWN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_DOWN),
                        PlayerDeliveryRecord::TYPE_RECHARGE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE),
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL),
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT),
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK),
                        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT),
                        PlayerDeliveryRecord::TYPE_PROFIT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PROFIT),
                        PlayerDeliveryRecord::TYPE_LOTTERY => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_LOTTERY),
                        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT),
                        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN),
                        PlayerDeliveryRecord::TYPE_NATIONAL_INVITE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE),
                        PlayerDeliveryRecord::TYPE_RECHARGE_REWARD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD),
                        PlayerDeliveryRecord::TYPE_DAMAGE_REBATE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE),
                        PlayerDeliveryRecord::TYPE_REVERSE_WATER => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REVERSE_WATER),
                        PlayerDeliveryRecord::COIN_ADD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::COIN_ADD),
                        PlayerDeliveryRecord::COIN_DEDUCT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::COIN_DEDUCT),
                        PlayerDeliveryRecord::TYPE_SPECIAL => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SPECIAL),
                        PlayerDeliveryRecord::TYPE_MACHINE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE),
                        PlayerDeliveryRecord::TYPE_AGENT_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_OUT),
                        PlayerDeliveryRecord::TYPE_AGENT_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_IN),
                        PlayerDeliveryRecord::TYPE_BET => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_BET),
                        PlayerDeliveryRecord::TYPE_CANCEL_BET => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_CANCEL_BET),
                        PlayerDeliveryRecord::TYPE_GIFT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GIFT),
                        PlayerDeliveryRecord::TYPE_SETTLEMENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SETTLEMENT),
                        PlayerDeliveryRecord::TYPE_RE_SETTLEMENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RE_SETTLEMENT),
                        PlayerDeliveryRecord::TYPE_PREPAY => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PREPAY),
                        PlayerDeliveryRecord::TYPE_REFUND => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REFUND),
                    ])->when([
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                    ], function ($filter) {
                        $filter->eq()->select('activity')
                            ->showSearch()
                            ->style(['width' => '200px'])
                            ->dropdownMatchSelectWidth()
                            ->placeholder(admin_trans('activity_content.fields.name'))
                            ->remoteOptions(admin_url([$this, 'getActivity']));
                    });
            });
            $grid->expandFilter();
        });
    }

    /**
     * 机台信息
     * @param $data
     * @return Card
     */
    public function machineInfo($data): Card
    {
        $tabs = Tabs::create()
            ->pane(admin_trans('common.detail'), $this->detail($data))
            ->pane(admin_trans('common.chart'), $this->chart($data));
        return Card::create($tabs);
    }

    /**
     * 机台信息
     * @param $machine
     * @return Detail
     */
    public function detail($machine): Detail
    {
        return Detail::create($machine, function (Detail $detail) {
            $detail->item('id', admin_trans('machine.fields.id'));
            $detail->item('name', admin_trans('machine.fields.name'));
            $detail->item('code', admin_trans('machine.fields.code'))->span(2);
            $detail->item('picture_url', admin_trans('machine.fields.picture_url'))->image();
            $detail->item('odds_x', admin_trans('machine.fields.odds_x'));
            $detail->item('odds_y', admin_trans('machine.fields.odds_y'))->span(2);
            $detail->item('type', admin_trans('machine.fields.type'))->display(function ($val) {
                $color = 'orange';
                if ($val == GameType::TYPE_SLOT) {
                    $color = 'green';
                }
                return Tag::create(admin_trans('game_type.game_type.' . $val))->color($color);
            });
            $detail->item('created_at', admin_trans('machine.fields.created_at'));
            $detail->item('remark', admin_trans('machine.fields.remark'));
        });
    }

    /**
     * 机台趋势图
     * @param $data
     * @return LineChart
     */
    public function chart($data): LineChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        $openPoint = PlayerGameLog::whereDate('created_at', '>=', $range)
            ->where('machine_id', $data['id'])
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get([
                DB::raw('Date(`created_at`) as date'),
                DB::raw('SUM(`open_point`) as value')
            ])
            ->toArray();
        $washPoint = PlayerGameLog::whereDate('created_at', '>=', $range)
            ->where('machine_id', $data['id'])
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get([
                DB::raw('Date(`created_at`) as date'),
                DB::raw('SUM(`wash_point`) as value')
            ])
            ->toArray();
        $openPoint = $openPoint ? array_column($openPoint, 'value', 'date') : [];
        $washPoint = $washPoint ? array_column($washPoint, 'value', 'date') : [];
        $xAxis = [];
        $openPointAxis = [];
        $washPointAxis = [];

        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $openPointAxis[] = $openPoint[$date] ?? 0;
            $washPointAxis[] = $washPoint[$date] ?? 0;
        }

        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('machine.point_chart'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('machine.open_point_chart'), $openPointAxis)
            ->data(admin_trans('machine.wash_point_chart'), $washPointAxis);
    }
    public function getActivity()
    {
        $lang = Container::getInstance()->translator->getLocale();
        $list = Activity::query()->with(['activity_content' => function ($query) use ($lang) {
            $query->where('lang', $lang);
        }])->whereJsonContains('department_id', Admin::user()->department_id)
            ->get()->toArray();
        $data = [];
        /** @var Activity $activity */
        foreach ($list as $activity) {
            $data[] = [
                'value' => $activity['id'],
                'label' => isset($activity['activity_content'][0]['name']) ? $activity['activity_content'][0]['name'] : '',
            ];
        }

        return Response::success($data);
    }
}

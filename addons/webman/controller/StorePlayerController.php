<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\LevelList;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerRegisterRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\VipLevel;
use addons\webman\service\WalletService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 店机后台 - 设备列表
 * @group store
 */
class StorePlayerController
{
    /**
     * 设备列表
     * @auth true
     * @group store
     */
    public function index(): Grid
    {
        $admin = Admin::user();
        $storeAdminId = $admin->id;
        $departmentId = $admin->department_id;

        // 预加载渠道的 VIP 等级列表
        $channelVipLevels = VipLevel::query()
            ->where('department_id', $departmentId)
            ->where('status', VipLevel::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->get(['id', 'name', 'sort', 'upgrade_bet_amount'])
            ->keyBy('sort');

        // 调试信息（显示查询条件）
        if (request()->input('debug') == 1) {
            $count = Player::query()
                ->where('department_id', $departmentId)
                ->where('store_admin_id', $storeAdminId)
                ->where('is_promoter', 0)
                ->count();

            \support\Log::info(admin_trans('player.device_list_query_log'), [
                'admin_id' => $admin->id,
                'department_id' => $departmentId,
                'store_admin_id' => $storeAdminId,
                'player_count' => $count
            ]);
        }

        // 获取筛选条件
        $requestFilter = Request::input('ex_admin_filter', []);

        // 查询最后一次交班记录时间
        $lastShiftRecord = StoreAgentShiftHandoverRecord::query()
            ->where('bind_admin_user_id', $storeAdminId)
            ->orderBy('id', 'desc')
            ->first();
        $lastShiftTime = $lastShiftRecord ? $lastShiftRecord->end_time : null;

        // 查询条件：店家管理的玩家（设备）
        // 关闭数据权限，因为我们手动控制了权限（department_id + store_admin_id）
        $query = Player::query()
            ->leftJoin('player_extend', 'player.id', '=', 'player_extend.player_id')
            ->where('player.department_id', $departmentId)
            ->where('player.store_admin_id', $storeAdminId)
            ->where('player.is_promoter', 0);

        // 应用筛选条件
        if (!empty($requestFilter)) {
            if (isset($requestFilter['player_id']) && $requestFilter['player_id'] !== '') {
                $query->where('player.id', $requestFilter['player_id']);
            }
            // VIP等级筛选
            if (isset($requestFilter['vip_level_id']) && $requestFilter['vip_level_id'] !== '') {
                $query->where('player.vip_level_id', $requestFilter['vip_level_id']);
            }
            if (isset($requestFilter['status'])) {
                $query->where('player.status', $requestFilter['status']);
            }
            if (!empty($requestFilter['phone'])) {
                $query->where('player.phone', 'like', '%' . $requestFilter['phone'] . '%');
            }
            if (!empty($requestFilter['name'])) {
                $query->where('player.name', 'like', '%' . $requestFilter['name'] . '%');
            }
            if (!empty($requestFilter['created_at_start'])) {
                $query->where('player.created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $query->where('player.created_at', '<=', $requestFilter['created_at_end']);
            }
        }

        // 计算筛选后的总数
        $totalQuery = clone $query;
        $playerCount = $totalQuery->count();

        $list = $query->select([
            'player.*',
            'player_extend.recharge_amount',
            'player_extend.withdraw_amount',
            'player_extend.machine_put_point',
            // VIP等级字段
            'vip_level.name as vip_level_name',
            'vip_level.sort as vip_level_sort',
            'vip_level.upgrade_bet_amount as current_upgrade_bet_amount',
            'vip_retain_period.period_bet_amount as period_bet_amount',
        ])
            // VIP等级关联
            ->leftjoin('vip_level', 'player.vip_level_id', '=', 'vip_level.id')
            // LEFT JOIN 保级周期（获取当前周期内打码量）
            ->leftJoin('player_vip_period as vip_retain_period', function ($join) {
                $join->on('player.id', '=', 'vip_retain_period.player_id')
                    ->on('player.vip_level_id', '=', 'vip_retain_period.vip_level_id')
                    ->where('vip_retain_period.period_type', '=', 'retain')
                    ->where('vip_retain_period.status', '=', 1);
            })
            ->orderBy('player.id', 'desc')
            ->get()
            ->toArray();

        // 🚀 批量从 Redis 获取余额和爆机状态（优化性能）
        if (!empty($list)) {
            $playerIds = array_column($list, 'id');
            $balances = WalletService::getBatchBalance($playerIds, PlayerPlatformCash::PLATFORM_SELF);
            $crashStatuses = WalletService::getBatchCrashStatus($playerIds, PlayerPlatformCash::PLATFORM_SELF);

            // 将 Redis 缓存数据合并到列表中，并修正精度
            foreach ($list as &$item) {
                $item['wallet_money'] = number_format($balances[$item['id']] ?? 0.0, 2, '.', '');
                $item['is_crashed'] = $crashStatuses[$item['id']] ?? 0;
            }
            unset($item);
        }

        // ✅ 性能优化：批量查询替代循环查询（解决N+1问题）
        if (!empty($list)) {
            $playerIds = array_column($list, 'id');
            $hasStatsTimeFilter = !empty($requestFilter['stats_start_time']) || !empty($requestFilter['stats_end_time']);

            // === 批量查询累计数据 ===
            if ($hasStatsTimeFilter) {
                // 批量查询筛选时间段内的财务记录
                $deliveryStatsQuery = PlayerDeliveryRecord::query()
                    ->whereIn('player_id', $playerIds);

                if (!empty($requestFilter['stats_start_time'])) {
                    $deliveryStatsQuery->where('created_at', '>=', $requestFilter['stats_start_time']);
                }
                if (!empty($requestFilter['stats_end_time'])) {
                    $deliveryStatsQuery->where('created_at', '<=', $requestFilter['stats_end_time']);
                }

                $deliveryStatsByPlayer = $deliveryStatsQuery->selectRaw("
                    player_id,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . " THEN `amount` ELSE 0 END) AS lottery_ticket_reward_amount
                ")->groupBy('player_id')->get()->mapWithKeys(function ($item) {
                    return [$item->player_id => $item];
                })->toArray();

                // 批量查询筛选时间段内的彩金
                $lotteryQuery = PlayerLotteryRecord::query()
                    ->whereIn('player_id', $playerIds)
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

                if (!empty($requestFilter['stats_start_time'])) {
                    $lotteryQuery->where('created_at', '>=', $requestFilter['stats_start_time']);
                }
                if (!empty($requestFilter['stats_end_time'])) {
                    $lotteryQuery->where('created_at', '<=', $requestFilter['stats_end_time']);
                }

                $lotteryByPlayer = $lotteryQuery->selectRaw('player_id, SUM(amount) as total_amount')
                    ->groupBy('player_id')->pluck('total_amount', 'player_id')->toArray();
            } else {
                // 没有时间筛选，批量查询累计彩金
                $lotteryByPlayer = PlayerLotteryRecord::query()
                    ->whereIn('player_id', $playerIds)
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->selectRaw('player_id, SUM(amount) as total_amount')
                    ->groupBy('player_id')->pluck('total_amount', 'player_id')->toArray();

                // 批量查询累计摸奖券
                $lotteryTicketRewardByPlayer = PlayerDeliveryRecord::query()
                    ->whereIn('player_id', $playerIds)
                    ->where('type', PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD)
                    ->selectRaw('player_id, SUM(amount) as total_amount')
                    ->groupBy('player_id')->pluck('total_amount', 'player_id')->toArray();

                $deliveryStatsByPlayer = [];
            }

            // === 批量查询当前未交班数据 ===
            // 批量查询当前班次财务数据
            $currentShiftDeliveryQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds);

            if ($lastShiftTime) {
                $currentShiftDeliveryQuery->where('created_at', '>', $lastShiftTime);
            }

            $currentShiftDeliveryByPlayer = $currentShiftDeliveryQuery->selectRaw("
                player_id,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS current_machine_put_point,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS current_total_income,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS current_total_outcome
            ")->groupBy('player_id')->get()->mapWithKeys(function ($item) {
                return [$item->player_id => $item];
            })->toArray();

            // 批量查询当前班次彩金
            $currentShiftLotteryQuery = PlayerLotteryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

            if ($lastShiftTime) {
                $currentShiftLotteryQuery->where('created_at', '>', $lastShiftTime);
            }

            $currentShiftLotteryByPlayer = $currentShiftLotteryQuery->selectRaw('player_id, SUM(amount) as total_amount')
                ->groupBy('player_id')->pluck('total_amount', 'player_id')->toArray();

            // 批量查询当前班次电子游戏打码量
            $currentElectronicGameBetQuery = \addons\webman\model\PlayGameRecord::query()
                ->whereIn('player_id', $playerIds)
                ->when($lastShiftTime, function ($query) use ($lastShiftTime) {
                    $query->where('created_at', '>', $lastShiftTime);
                });

            $currentElectronicGameBetByPlayer = $currentElectronicGameBetQuery->selectRaw('player_id, SUM(bet) as total_bet')
                ->groupBy('player_id')->pluck('total_bet', 'player_id')->toArray();

            // 批量查询当前班次机器打码量
            $currentMachineBetQuery = \addons\webman\model\PlayerGameLog::query()
                ->whereIn('player_id', $playerIds)
                ->when($lastShiftTime, function ($query) use ($lastShiftTime) {
                    $query->where('created_at', '>', $lastShiftTime);
                });

            $currentMachineBetByPlayer = $currentMachineBetQuery->selectRaw('player_id, SUM(chip_amount) as total_chip')
                ->groupBy('player_id')->pluck('total_chip', 'player_id')->toArray();

            // 批量查询当前班次摸奖券
            $currentLotteryTicketRewardQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->where('type', PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD)
                ->when($lastShiftTime, function ($query) use ($lastShiftTime) {
                    $query->where('created_at', '>', $lastShiftTime);
                });

            $currentLotteryTicketRewardByPlayer = $currentLotteryTicketRewardQuery->selectRaw('player_id, SUM(amount) as total_amount')
                ->groupBy('player_id')->pluck('total_amount', 'player_id')->toArray();

            // === 合并数据到列表 ===
            foreach ($list as &$item) {
                $playerId = $item['id'];

                // 累计数据
                if ($hasStatsTimeFilter) {
                    $stats = $deliveryStatsByPlayer[$playerId] ?? null;
                    $item['machine_put_point'] = floatval($stats->machine_put_point ?? 0);
                    $item['recharge_amount'] = floatval($stats->recharge_amount ?? 0);
                    $item['withdraw_amount'] = floatval($stats->withdraw_amount ?? 0);
                    $item['lottery_ticket_reward_amount'] = floatval($stats->lottery_ticket_reward_amount ?? 0);
                } else {
                    $item['lottery_ticket_reward_amount'] = floatval($lotteryTicketRewardByPlayer[$playerId] ?? 0);
                }
                $item['lottery_amount'] = floatval($lotteryByPlayer[$playerId] ?? 0);

                // 计算累计小计
                $rechargeAmount = floatval($item['recharge_amount'] ?? 0);
                $machinePutPoint = floatval($item['machine_put_point'] ?? 0);
                $withdrawAmount = floatval($item['withdraw_amount'] ?? 0);
                $lotteryAmount = floatval($item['lottery_amount'] ?? 0);

                $incomeTotal = bcadd($rechargeAmount, $machinePutPoint, 2);

                if ($hasStatsTimeFilter) {
                    // 有时间筛选：从账变记录统计，TYPE_RECHARGE和TYPE_MACHINE是分开的
                    // 小计 = (开分 + 投钞) - 洗分
                    $item['subtotal'] = bcsub($incomeTotal, $withdrawAmount, 2);
                    $item['pure_recharge_amount'] = $rechargeAmount;
                } else {
                    // 无时间筛选：使用player_extend累计数据，recharge_amount已包含投钞
                    // 小计 = 开分 - 洗分
                    $item['subtotal'] = bcsub($rechargeAmount, $withdrawAmount, 2);
                    $item['pure_recharge_amount'] = bcsub($rechargeAmount, $machinePutPoint, 2);
                }

                // 当前未交班数据
                $currentStats = $currentShiftDeliveryByPlayer[$playerId] ?? null;
                $item['current_machine_put_point'] = floatval($currentStats->current_machine_put_point ?? 0);
                $item['current_total_income'] = floatval($currentStats->current_total_income ?? 0);
                $item['current_total_outcome'] = floatval($currentStats->current_total_outcome ?? 0);
                $item['current_lottery_amount'] = floatval($currentShiftLotteryByPlayer[$playerId] ?? 0);
                $item['current_lottery_ticket_reward'] = floatval($currentLotteryTicketRewardByPlayer[$playerId] ?? 0);
                $item['current_electronic_game_bet'] = floatval($currentElectronicGameBetByPlayer[$playerId] ?? 0);
                $item['current_machine_bet'] = floatval($currentMachineBetByPlayer[$playerId] ?? 0);

                // 计算当前未交班总利润 = (开分 + 投钞) - 洗分
                // 注意：current_total_outcome 只包含洗分(TYPE_WITHDRAWAL)，不包含彩金
                // 彩金、摸奖券奖励只用于展示，不参与利润计算（已经发放给客户，客户洗分会洗掉）
                $item['current_total_profit'] = bcsub(
                    bcadd($item['current_total_income'], $item['current_machine_put_point'], 2),
                    $item['current_total_outcome'],
                    2
                );
            }
            unset($item);
        }

        // ✅ 优化：直接从已查询的 list 构建 playerOptions，避免重复查询
        $playerOptions = [];
        foreach ($list as $item) {
            $label = $item['name']
                ? "{$item['name']} (ID: {$item['id']})"
                : "ID: {$item['id']}";
            if (!empty($item['uuid'])) {
                $label .= " - {$item['uuid']}";
            }
            $playerOptions[$item['id']] = $label;
        }

        return Grid::create($list, function (Grid $grid) use ($storeAdminId, $departmentId, $admin, $playerCount, $list, $playerOptions, $requestFilter, $channelVipLevels) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 设置分页
            $grid->pagination()->pageSize(50);
            $grid->pagination()->showSizeChanger(true);
            $grid->pagination()->pageSizeOptions(['20', '50', '100', '200']);

            // 添加统计信息面板
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($requestFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $requestFilter,
                    'type' => 'StorePlayer',
                ]));
            })->style(['background' => '#fff']);

            $grid->header($layout);

            // 添加玩家按钮
            $grid->tools([
                Button::create(admin_trans('player.add_player'))
                    ->type('primary')
                    ->modal($this->form())
                    ->width('60%')
            ]);

            $grid->column('id', admin_trans('player.fields.id'))->display(function ($value) {
                return Html::create($value)->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500',
                    'color' => '#606266'
                ]);
            })->width(70)->sortable()->align('center')->fixed(true);

            $grid->column('name', admin_trans('player.fields.device_name'))->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])->size(32)
                    : Avatar::create()->content(mb_substr($val ?: 'U', 0, 1))->size(32);
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val ?: admin_trans('player.unnamed'))->style([
                        'marginLeft' => '8px',
                        'fontSize' => '13px',
                        'fontWeight' => '500',
                        'color' => '#303133',
                        'whiteSpace' => 'nowrap',
                        'overflow' => 'hidden',
                        'textOverflow' => 'ellipsis'
                    ])
                ])->style([
                    'display' => 'flex',
                    'alignItems' => 'center'
                ]);
            })->width(150)->fixed(true);

            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($value) {
                return Html::create($value ?: '-')->style([
                    'fontSize' => '13px'
                ]);
            })->width(110)->align('center');

            $grid->column('player_source', admin_trans('player.fields.player_source'))->display(function ($value) {
                return match ($value) {
                    Player::PLAYER_SOURCE_ONLINE => Tag::create(admin_trans('player.fields.player_source_online'))->color('blue'),
                    Player::PLAYER_SOURCE_OFFLINE => Tag::create(admin_trans('player.fields.player_source_offline'))->color('orange'),
                    default => Tag::create('-')->color('default'),
                };
            })->width(90)->align('center');

            // VIP等级列
            $grid->column('vip_level_name', admin_trans('player.fields.vip_level'))
                ->display(function ($value, $data) use ($channelVipLevels) {
                    $levelName = $value;
                    if (empty($levelName)) {
                        $minLevel = $channelVipLevels->first();
                        $levelName = $minLevel ? $minLevel->name : '-';
                    }
                    if ($levelName === '-') {
                        return '-';
                    }

                    $currentSort = $data['vip_level_sort'] ?? 0;
                    if ($currentSort == 0) {
                        $minLevel = $channelVipLevels->first();
                        $currentSort = $minLevel ? $minLevel->sort : 0;
                    }
                    $nextLevel = $channelVipLevels->where('sort', '>', $currentSort)->first();

                    // 计算打码进度（使用当前周期内打码量，非累计总打码量）
                    $periodBetAmount = floatval($data['period_bet_amount'] ?? 0);
                    $currentUpgradeBet = floatval($data['current_upgrade_bet_amount'] ?? 0);

                    $progress = 0;
                    $progressText = '';
                    if ($currentUpgradeBet > 0) {
                        // 进度 = 周期内打码量 / 当前等级升级要求 * 100
                        $progress = min(100, max(0, ($periodBetAmount / $currentUpgradeBet) * 100));
                        $progressText = number_format($periodBetAmount, 0) . ' / ' . number_format($currentUpgradeBet, 0);
                    } elseif (!$nextLevel) {
                        // 已是最高等级
                        $progress = 100;
                        $progressText = admin_trans('player.vip_max_level');
                    } else {
                        $progressText = number_format($periodBetAmount, 0);
                    }

                    $content = [
                        Tag::create($levelName)->color('purple'),
                        Html::div()->content([
                            Html::create($progressText)->style(['font-size' => '12px', 'color' => '#666']),
                        ]),
                    ];

                    if ($currentUpgradeBet > 0) {
                        $content[] = \ExAdmin\ui\component\feedback\Progress::create()
                            ->percent(round($progress, 1))
                            ->showInfo(false)
                            ->size('small')
                            ->style(['margin-top' => '4px', 'width' => '100px']);
                    }

                    return Html::create()->content($content)->style(['display' => 'flex', 'flex-direction' => 'column', 'align-items' => 'center']);
                })
                ->width(180)->align('center');

            $grid->column('wallet_money', admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function ($value) {
                return Html::create(number_format(floatval($value), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500'
                ]);
            })->width(110)->align('center');

            $grid->column('is_crashed', admin_trans('player.is_crashed'))->display(function ($val, $data) {
                if ($val == 1) {
                    return Tag::create(admin_trans('player.crashed'))->color('red');
                } else {
                    return Tag::create(admin_trans('player.normal'))->color('green');
                }
            })->width(90)->align('center');

            $grid->column('recharge_amount', admin_trans('player.total_recharge_amount'))->display(function ($value, $data) {
                // 累计开分需要扣除投钞金额（因为开分字段已包含投钞）
                $pureRecharge = $data['pure_recharge_amount'] ?? 0;
                return Html::create(number_format(floatval($pureRecharge), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500'
                ]);
            })->width(110)->align('center');

            $grid->column('machine_put_point', admin_trans('player.total_machine_put_point'))->display(function ($value) {
                return Html::create(number_format(floatval($value), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500'
                ]);
            })->width(110)->align('center');

            $grid->column('withdraw_amount', admin_trans('player.total_withdraw_amount'))->display(function ($value) {
                return Html::create(number_format(floatval($value), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500'
                ]);
            })->width(110)->align('center');


            $grid->column('lottery_amount', admin_trans('player.total_lottery_amount'))->display(function ($value) {
                return Html::create(number_format(floatval($value), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500'
                ]);
            })->width(110)->align('center');

            $grid->column('lottery_ticket_reward_amount', admin_trans('player.total_lottery_ticket_reward_amount'))->display(function ($value) {
                return Html::create(number_format(floatval($value ?? 0), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => '500',
                    'color' => '#E6A23C'
                ]);
            })->width(110)->align('center');

            $grid->column('subtotal', admin_trans('player.subtotal'))->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style([
                    'fontSize' => '13px',
                    'fontWeight' => 'bold',
                    'color' => $color
                ]);
            })->width(110)->align('center');

            // === 当前未交班数据列（合并显示） ===
            $grid->column('current_shift_stats', admin_trans('player.current_shift_stats'))->display(function ($value, $data) {
                $profitColor = $data['current_total_profit'] >= 0 ? '#3f8600' : '#cf1322';

                return Html::div()->content([
                    // 投钞点数
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.machine_put_point') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_machine_put_point']), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#303133'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 总收入
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.total_in') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_total_income']), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#67C23A'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 总支出
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.total_out') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_total_outcome']), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#F56C6C'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 彩金
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.lottery_amount') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_lottery_amount']), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#E6A23C'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 摸奖券
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.lottery_ticket_reward_amount_short') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_lottery_ticket_reward'] ?? 0), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#E6A23C'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 电子游戏打码量
                    Html::div()->content([
                        Html::create(admin_trans('data_center.electronic_game_bet_amount') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_electronic_game_bet'] ?? 0), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#E6A23C'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 机器打码量
                    Html::div()->content([
                        Html::create(admin_trans('data_center.machine_bet_amount') . ': ')->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_machine_bet'] ?? 0), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => '#F56C6C'
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center']),

                    // 总利润
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.label.total_profit'))->style([
                            'fontSize' => '11px',
                            'color' => '#666',
                            'display' => 'inline-block',
                            'width' => '60px',
                            'textAlign' => 'left'
                        ]),
                        Html::create(number_format(floatval($data['current_total_profit']), 2))->style([
                            'fontSize' => '11px',
                            'fontWeight' => '500',
                            'color' => $profitColor
                        ])
                    ])->style(['marginBottom' => '2px', 'display' => 'flex', 'alignItems' => 'center'])
                ])->style([
                    'padding' => '6px 8px',
                    'backgroundColor' => '#f0f9ff',
                    'borderRadius' => '4px',
                    'lineHeight' => '1.4',
                    'minWidth' => '150px'
                ]);
            })->width(170)->help(admin_trans('player.current_shift_help'));

            $grid->column('status', admin_trans('player.fields.status'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => Tag::create(admin_trans('admin.unknown'))->color('default'),
                };
            })->width(70)->align('center');

            $grid->column('created_at', admin_trans('player.fields.created_at'))->display(function ($value) {
                return Html::create($value)->style([
                    'fontSize' => '12px',
                    'color' => '#606266'
                ]);
            })->width(150)->align('center');

            // VIP等级选项
            $vipLevelOptions = ['' => admin_trans('public_msg.all')];
            foreach ($channelVipLevels as $level) {
                $vipLevelOptions[$level->id] = $level->name;
            }

            $grid->filter(function (Filter $filter) use ($playerOptions, $vipLevelOptions) {
                // 设备下拉选择
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player.filter.select_device'))
                    ->options(['' => admin_trans('public_msg.all')] + $playerOptions)
                    ->style(['width' => '300px']);

                // VIP等级筛选
                $filter->eq()->select('vip_level_id')
                    ->placeholder(admin_trans('player.fields.vip_level'))
                    ->options($vipLevelOptions)
                    ->style(['width' => '150px']);

                // 统计时间范围筛选（影响累计数据）
                $filter->form()->hidden('stats_start_time');
                $filter->form()->hidden('stats_end_time');
                $filter->form()->dateTimeRange('stats_start_time', 'stats_end_time', '')
                    ->placeholder([
                        admin_trans('player.stats_start_time'),
                        admin_trans('player.stats_end_time')
                    ])
                    ->help(admin_trans('player.stats_time_range_help'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('player.fields.status'))
                    ->options([
                        1 => admin_trans('admin.open'),
                        0 => admin_trans('admin.close')
                    ])
                    ->style(['width' => '150px']);

                $filter->eq()->select('is_crashed')
                    ->placeholder(admin_trans('player.is_crashed'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        0 => admin_trans('player.normal'),
                        1 => admin_trans('player.crashed')
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.device_name'));

                // 设备注册时间范围筛选
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')
                    ->placeholder([
                        admin_trans('player.device_created_start_time'),
                        admin_trans('player.device_created_end_time')
                    ]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->edit()->modal($this->form())->width('60%');
                $actions->hideDel();
                $actions->detail()->modal($this->viewForm())->width('60%');
            });

            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $playerCount);
            $grid->attr('mongo_model', $list);

            // 如果没有数据，显示提示信息
            if ($playerCount == 0) {
                $totalPlayers = Player::query()
                    ->where('department_id', $departmentId)
                    ->where('is_promoter', 0)
                    ->count();

                $grid->emptyText(admin_trans('player.no_device_data'));
            }
        });
    }

    /**
     * 添加/编辑玩家表单
     * @auth true
     * @group store
     * @return Form
     */
    public function form(): Form
    {
        $options = [];
        foreach (config('def_avatar') as $key => $item) {
            $options[$key] = Avatar::create()->style(['padding' => '1px'])->src($item)->shape('square');
        }
        return Form::create(new Player(), function (Form $form) use ($options) {
            $form->layout('vertical');
            if ($form->isEdit()) {
                // ========== 编辑模式 ==========
                $form->title(admin_trans('player.edit_player'));
                $orgData = $form->driver()->get();

                // 加载 player_extend 扩展数据
                $playerExtend = PlayerExtend::query()->where('player_id', $orgData['id'])->first();

                $form->text('phone', admin_trans('player.fields.phone'))->maxlength(50)->disabled();
                $form->radio('def_avatar', admin_trans('player.def_avatar'))
                    ->default(1)
                    ->options($options);
                $form->text('name', admin_trans('player.fields.name'))->maxlength(50)->required();
                $form->text('real_name', admin_trans('player.fields.real_name'))->maxlength(50)->required();
                $form->text('id_number', admin_trans('player_extend.fields.id_number'))
                    ->maxlength(50)->required()
                    ->default($playerExtend->id_number ?? '');
                $form->image('id_card_front', admin_trans('player_extend.fields.id_card_front'))
                    ->ext('jpg,png,jpeg')->fileSize('5m')
                    ->default($playerExtend->id_card_front ?? '');
                $form->image('id_card_back', admin_trans('player_extend.fields.id_card_back'))
                    ->ext('jpg,png,jpeg')->fileSize('5m')
                    ->default($playerExtend->id_card_back ?? '');
                $form->image('personal_photo', admin_trans('player_extend.fields.personal_photo'))
                    ->ext('jpg,png,jpeg')->fileSize('5m')
                    ->default($playerExtend->personal_photo ?? '');
                $form->text('address', admin_trans('player_extend.fields.address'))
                    ->maxlength(255)->required()
                    ->default($playerExtend->address ?? '');
                $form->date('birthday', admin_trans('player_extend.fields.birthday'))
                    ->required()
                    ->default($playerExtend->birthday ?? '');
                $form->text('line', admin_trans('player_extend.fields.line'))
                    ->maxlength(50)->required()
                    ->default($playerExtend->line ?? '');
                $form->textarea('remark', admin_trans('player_extend.fields.remark'))
                    ->maxlength(255)
                    ->default($playerExtend->remark ?? '');

                $form->saved(function () {
                    return message_success(admin_trans('player.save_player_info_success'));
                });
                $form->saving(function (Form $form) {
                    $orgData = $form->driver()->get();
                    $player = Player::find($orgData['id']);

                    if (empty($player)) {
                        return message_error(admin_trans('player.not_fount'));
                    }

                    DB::beginTransaction();
                    try {
                        // 更新 Player 主表
                        $player->name = $form->input('name');
                        $player->real_name = $form->input('real_name');
                        $player->avatar = $form->input('def_avatar') ?? config('def_avatar.1');
                        $player->save();

                        // 更新 PlayerExtend 扩展表
                        PlayerExtend::query()->updateOrCreate(
                            ['player_id' => $orgData['id']],
                            [
                                'id_number' => $form->input('id_number'),
                                'id_card_front' => $form->input('id_card_front'),
                                'id_card_back' => $form->input('id_card_back'),
                                'personal_photo' => $form->input('personal_photo'),
                                'address' => $form->input('address'),
                                'birthday' => $form->input('birthday'),
                                'email' => $form->input('email'),
                                'line' => $form->input('line'),
                                'remark' => $form->input('remark'),
                            ]
                        );

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage());
                    }

                    return message_success(admin_trans('player.save_player_info_success'));
                });
            } else {
                // ========== 新增模式 ==========
                $form->title(admin_trans('player.add_player'));
                $form->text('phone', admin_trans('player.fields.phone'))->maxlength(50)->required();
                $form->radio('def_avatar', admin_trans('player.def_avatar'))
                    ->default(1)
                    ->options($options);
                $form->text('name', admin_trans('player.fields.name'))->maxlength(50)->required();
                $form->text('real_name', admin_trans('player.fields.real_name'))->maxlength(50)->required();
                $form->text('id_number', admin_trans('player_extend.fields.id_number'))->maxlength(50)->required();
                $form->image('id_card_front', admin_trans('player_extend.fields.id_card_front'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
                $form->image('id_card_back', admin_trans('player_extend.fields.id_card_back'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
                $form->image('personal_photo', admin_trans('player_extend.fields.personal_photo'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
                $form->text('address', admin_trans('player_extend.fields.address'))->maxlength(255)->required();
                $form->date('birthday', admin_trans('player_extend.fields.birthday'))->required();
                $form->text('line', admin_trans('player_extend.fields.line'))->maxlength(50)->required();
                $form->textarea('remark', admin_trans('player_extend.fields.remark'))->maxlength(255)->required();
                $form->password('password', admin_trans('player.new_password'))
                    ->rule([
                        'confirmed' => admin_trans('player.password_confim_validate'),
                        'min:6' => admin_trans('player.password_min_number')
                    ])
                    ->value('')
                    ->required();
                $form->password('password_confirmation', admin_trans('player.confim_password'))
                    ->required();

                $form->saved(function () {
                    return message_success(admin_trans('player.save_player_info_success'));
                });
                $form->saving(function (Form $form) {
                    $admin = Admin::user();
                    $phone = $form->input('phone');
                    $password = $form->input('password');
                    $departmentId = $admin->department_id;

                    // 检查全民代理等级是否配置（同步自总后台逻辑）
                    if (!LevelList::query()->where('department_id', $departmentId)->orderBy('must_chip_amount')->exists()) {
                        return message_error(admin_trans('player.national_level_not_configure'));
                    }

                    $existingPlayer = Player::query()->where('phone', $phone)->first();
                    if (!empty($existingPlayer)) {
                        return message_error(admin_trans('player.phone_has_register'));
                    }

                    // 验证渠道是否存在（同步自总后台逻辑）
                    /** @var Channel $channel */
                    $channel = Channel::where('department_id', $departmentId)->first();
                    if (empty($channel)) {
                        return message_error(trans('channel_not_found', [], 'message'));
                    }

                    DB::beginTransaction();
                    try {
                        $player = new Player();
                        $player->phone = $phone;
                        $player->name = $form->input('name');
                        $player->real_name = $form->input('real_name');
                        $player->avatar = $form->input('def_avatar') ?? config('def_avatar.1');
                        // 店机后台固定值：国家代码86、线上来源
                        $player->country_code = '86';
                        $player->player_source = Player::PLAYER_SOURCE_ONLINE;
                        $player->type = Player::TYPE_PLAYER;
                        $player->currency = $channel->currency;
                        $player->department_id = $departmentId;
                        // 店机后台特有：绑定到当前店机管理员及其上级代理
                        $player->store_admin_id = $admin->id;
                        $player->agent_admin_id = $admin->parent_admin_id ?? 0;
                        $player->password = $password;
                        $player->uuid = generate15DigitUniqueId();
                        $player->recommend_code = createCode();
                        $player->save();

                        // 创建玩家扩展信息
                        addPlayerExtend($player);

                        // 更新扩展信息
                        PlayerExtend::query()->where('player_id', $player->id)->update([
                            'id_number' => $form->input('id_number'),
                            'id_card_front' => $form->input('id_card_front'),
                            'id_card_back' => $form->input('id_card_back'),
                            'personal_photo' => $form->input('personal_photo'),
                            'address' => $form->input('address'),
                            'birthday' => $form->input('birthday'),
                            'email' => $form->input('email'),
                            'line' => $form->input('line'),
                            'remark' => $form->input('remark'),
                        ]);

                        // 创建注册记录（同步自总后台逻辑）
                        addRegisterRecord($player->id, PlayerRegisterRecord::TYPE_ADMIN, $departmentId);

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage());
                    }
                    return message_success(admin_trans('player.save_player_info_success'));
                });
            }
        });
    }

    /**
     * 查看玩家详情（只读）
     * @auth true
     * @group store
     * @return Form
     */
    public function viewForm(): Form
    {
        return Form::create(new Player(), function (Form $form) {
            $form->title(admin_trans('player.details'));
            $form->actions(function ($action) {
                $action->hide();
            });
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->desc('id', admin_trans('player.fields.id'));
                    $form->desc('name', admin_trans('player.fields.name'));
                    $form->desc('phone', admin_trans('player.fields.phone'));
                    $form->desc('uuid', admin_trans('player.fields.uuid'));
                    $avatarVal = $form->input('avatar');
                    $nameVal = $form->input('name') ?: admin_trans('player.unnamed');
                    $src = !empty($avatarVal)
                        ? (is_numeric($avatarVal) ? config('def_avatar.' . $avatarVal) : $avatarVal)
                        : '';
                    $avatarHtml = $src
                        ? '<img src="' . $src . '" style="width:40px;height:40px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:8px" />'
                        : '<span style="display:inline-block;width:40px;height:40px;border-radius:50%;background:#ccc;text-align:center;line-height:40px;color:#fff;margin-right:8px">'
                        . mb_substr($nameVal, 0, 1) . '</span>';
                    $form->desc('avatar', admin_trans('player.fields.avatar'))
                        ->value($avatarHtml . '<span>' . e($nameVal) . '</span>');
                    $form->desc('player_source', admin_trans('player.fields.player_source'))
                        ->value(match ($form->input('player_source')) {
                            Player::PLAYER_SOURCE_ONLINE => admin_trans('player.fields.player_source_online'),
                            Player::PLAYER_SOURCE_OFFLINE => admin_trans('player.fields.player_source_offline'),
                            default => '-',
                        });
                    $form->desc('status', admin_trans('player.fields.status'))
                        ->value($form->input('status') == 1 ? admin_trans('admin.open') : admin_trans('admin.close'));
                    $form->desc('is_test', admin_trans('player.fields.is_test'))
                        ->value($form->input('is_test') == 1 ? admin_trans('player.promoter') : admin_trans('player.not_test'));
                    $form->desc('created_at', admin_trans('player.fields.created_at'))
                        ->value($form->input('created_at') ? date('Y-m-d H:i:s', strtotime($form->input('created_at'))) : '');
                })->span(12);

                $form->column(function (Form $form) {
                    $form->desc('player_extend.id_number', admin_trans('player_extend.fields.id_number'));
                    $idCardFront = $form->input('player_extend.id_card_front');
                    $idCardBack = $form->input('player_extend.id_card_back');
                    $personalPhoto = $form->input('player_extend.personal_photo');
                    $form->desc('player_extend.id_card_front', admin_trans('player_extend.fields.id_card_front'))
                        ->value($idCardFront ? '<img src="' . $idCardFront . '" style="max-width:120px;max-height:80px;border-radius:4px;object-fit:cover" />' : '-');
                    $form->desc('player_extend.id_card_back', admin_trans('player_extend.fields.id_card_back'))
                        ->value($idCardBack ? '<img src="' . $idCardBack . '" style="max-width:120px;max-height:80px;border-radius:4px;object-fit:cover" />' : '-');
                    $form->desc('player_extend.personal_photo', admin_trans('player_extend.fields.personal_photo'))
                        ->value($personalPhoto ? '<img src="' . $personalPhoto . '" style="max-width:120px;max-height:80px;border-radius:4px;object-fit:cover" />' : '-');
                    $form->desc('player_extend.address', admin_trans('player_extend.fields.address'));
                    $form->desc('player_extend.birthday', admin_trans('player_extend.fields.birthday'));
                    $form->desc('player_extend.email', admin_trans('player_extend.fields.email'));
                    $form->desc('player_extend.line', admin_trans('player_extend.fields.line'));
                    $form->desc('player_extend.remark', admin_trans('player_extend.fields.remark'));
                })->span(12);
            });
        });
    }
}

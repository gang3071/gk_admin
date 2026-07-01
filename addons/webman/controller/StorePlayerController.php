<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\service\WalletService;
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
use support\Log;

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
            'player_extend.machine_put_point'
        ])
            ->orderBy('player.id', 'asc')
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

        // 计算每个设备的统计数据
        foreach ($list as &$item) {
            $playerId = $item['id'];

            // === 1. 累计数据（受时间筛选影响） ===
            // 检查是否有统计时间范围筛选
            $hasStatsTimeFilter = !empty($requestFilter['stats_start_time']) || !empty($requestFilter['stats_end_time']);

            if ($hasStatsTimeFilter) {
                // 查询筛选时间段内的财务记录
                $deliveryQuery = PlayerDeliveryRecord::query()
                    ->where('player_id', $playerId);

                if (!empty($requestFilter['stats_start_time'])) {
                    $deliveryQuery->where('created_at', '>=', $requestFilter['stats_start_time']);
                }
                if (!empty($requestFilter['stats_end_time'])) {
                    $deliveryQuery->where('created_at', '<=', $requestFilter['stats_end_time']);
                }

                $deliveryStats = $deliveryQuery->selectRaw("
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount
                ")->first();

                // 查询筛选时间段内的彩金
                $lotteryQuery = PlayerLotteryRecord::query()
                    ->where('player_id', $playerId)
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

                if (!empty($requestFilter['stats_start_time'])) {
                    $lotteryQuery->where('created_at', '>=', $requestFilter['stats_start_time']);
                }
                if (!empty($requestFilter['stats_end_time'])) {
                    $lotteryQuery->where('created_at', '<=', $requestFilter['stats_end_time']);
                }

                $lotteryAmount = $lotteryQuery->sum('amount') ?? 0;

                // 覆盖累计数据
                $item['machine_put_point'] = floatval($deliveryStats->machine_put_point ?? 0);
                $item['recharge_amount'] = floatval($deliveryStats->recharge_amount ?? 0);
                $item['withdraw_amount'] = floatval($deliveryStats->withdraw_amount ?? 0);
                $item['lottery_amount'] = floatval($lotteryAmount);
            } else {
                // 没有时间筛选，使用 player_extend 的累计数据
                // 查询累计彩金
                $lotteryAmount = PlayerLotteryRecord::query()
                    ->where('player_id', $playerId)
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->sum('amount') ?? 0;

                $item['lottery_amount'] = floatval($lotteryAmount);
            }

            // 计算累计小计 = 开分 - 洗分
            // 注意：开分（recharge_amount）已包含投钞，洗分已包含彩金
            $rechargeAmount = floatval($item['recharge_amount'] ?? 0);
            $machinePutPoint = floatval($item['machine_put_point'] ?? 0);
            $withdrawAmount = floatval($item['withdraw_amount'] ?? 0);

            // 小计 = 开分 - 洗分
            $item['subtotal'] = bcsub($rechargeAmount, $withdrawAmount, 2);

            // 存储纯开分金额（扣除投钞后），用于展示
            $item['pure_recharge_amount'] = bcsub($rechargeAmount, $machinePutPoint, 2);

            // === 2. 当前未交班数据（不受时间筛选影响） ===
            // 查询从最后交班时间到现在的财务数据
            $currentShiftDeliveryQuery = PlayerDeliveryRecord::query()
                ->where('player_id', $playerId);

            if ($lastShiftTime) {
                $currentShiftDeliveryQuery->where('created_at', '>', $lastShiftTime);
            }

            $currentShiftDelivery = $currentShiftDeliveryQuery->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS current_machine_put_point,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS current_total_income,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS current_total_outcome
            ")->first();

            // 查询从最后交班时间到现在的彩金
            $currentShiftLotteryQuery = PlayerLotteryRecord::query()
                ->where('player_id', $playerId)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

            if ($lastShiftTime) {
                $currentShiftLotteryQuery->where('created_at', '>', $lastShiftTime);
            }

            $currentShiftLottery = $currentShiftLotteryQuery->sum('amount') ?? 0;

            // 存储当前未交班数据
            $item['current_machine_put_point'] = floatval($currentShiftDelivery->current_machine_put_point ?? 0);
            $item['current_total_income'] = floatval($currentShiftDelivery->current_total_income ?? 0);
            $item['current_total_outcome'] = floatval($currentShiftDelivery->current_total_outcome ?? 0);
            $item['current_lottery_amount'] = floatval($currentShiftLottery);

            // 计算当前未交班总利润 = (总收入 + 投钞) - 总支出
            // 注意：总支出（洗分）已包含彩金，彩金只用于展示
            $item['current_total_profit'] = bcsub(
                bcadd($item['current_total_income'], $item['current_machine_put_point'], 2),
                $item['current_total_outcome'],
                2
            );
        }
        Log::info('数据', [$list]);
        unset($item); // 清除引用，避免污染数组

        // ✅ 内存优化：使用 lazy() 惰性加载替代一次性 get()
        // 修复前：3000 台设备 = 5 MB 内存，5 个并发进程 = 25 MB
        // 修复后：惰性加载 = 稳定在 1 MB 以内
        $playerOptions = Player::query()
            ->where('department_id', $departmentId)
            ->where('store_admin_id', $storeAdminId)
            ->where('is_promoter', 0)
            ->orderBy('id', 'asc')
            ->select(['id', 'name', 'uuid'])
            ->lazy(500)
            ->mapWithKeys(function ($player) {
                $label = $player->name
                    ? "{$player->name} (ID: {$player->id})"
                    : "ID: {$player->id}";
                if ($player->uuid) {
                    $label .= " - {$player->uuid}";
                }
                return [$player->id => $label];
            })
            ->all();

        return Grid::create($list, function (Grid $grid) use ($storeAdminId, $departmentId, $admin, $playerCount, $list, $playerOptions, $requestFilter) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);

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

            $grid->filter(function (Filter $filter) use ($playerOptions) {
                // 设备下拉选择
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player.filter.select_device'))
                    ->options(['' => admin_trans('public_msg.all')] + $playerOptions)
                    ->style(['width' => '300px']);

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
                $actions->hideEdit();
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
     * 添加玩家表单
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
            $form->title(admin_trans('player.add_player'));
            $form->text('phone', admin_trans('player.fields.phone'))->maxlength(50)->required();
            $form->radio('avatar_type', admin_trans('player.avatar_type'))
                ->button()
                ->default(2)
                ->options([
                    1 => admin_trans('player.upload_avatar'),
                    2 => admin_trans('player.def_avatar')
                ])
                ->when(1, function (Form $form) {
                    $form->image('avatar',
                        admin_trans('player.fields.avatar'))->ext('jpg,png,jpeg')->fileSize('1m');
                })->when(2, function (Form $form) use ($options) {
                    $form->radio('def_avatar', admin_trans('player.def_avatar'))
                        ->default(1)
                        ->options($options);
                });
            $form->text('name', admin_trans('player.fields.name'))->maxlength(50)->required();
            $form->text('id_number', admin_trans('player_extend.fields.id_number'))->maxlength(50)->required();
            $form->image('id_card_front', admin_trans('player_extend.fields.id_card_front'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
            $form->image('id_card_back', admin_trans('player_extend.fields.id_card_back'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
            $form->image('personal_photo', admin_trans('player_extend.fields.personal_photo'))->ext('jpg,png,jpeg')->fileSize('5m')->required();
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

                $existingPlayer = Player::query()->where('phone', $phone)->first();
                if (!empty($existingPlayer)) {
                    return message_error(admin_trans('player.phone_has_register'));
                }

                DB::beginTransaction();
                try {
                    $player = new Player();
                    $player->phone = $phone;
                    $player->name = $form->input('name');
                    if ($form->input('avatar_type') == 1) {
                        $player->avatar = $form->input('avatar') ?? config('def_avatar.1');
                    }
                    if ($form->input('avatar_type') == 2) {
                        $player->avatar = $form->input('def_avatar') ?? config('def_avatar.1');
                    }
                    $player->country_code = '86';
                    $player->player_source = Player::PLAYER_SOURCE_ONLINE;
                    $player->type = Player::TYPE_PLAYER;
                    $player->currency = $admin->department->currency ?? 'CNY';
                    $player->department_id = $admin->department_id;
                    $player->store_admin_id = $admin->id;
                    $player->password = $password;
                    $player->uuid = generate15DigitUniqueId();
                    $player->recommend_code = createCode();
                    $player->save();

                    addPlayerExtend($player);

                    PlayerExtend::query()->where('player_id', $player->id)->update([
                        'id_number' => $form->input('id_number'),
                        'id_card_front' => $form->input('id_card_front'),
                        'id_card_back' => $form->input('id_card_back'),
                        'personal_photo' => $form->input('personal_photo'),
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error($e->getMessage());
                }
                return message_success(admin_trans('player.save_player_info_success'));
            });
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

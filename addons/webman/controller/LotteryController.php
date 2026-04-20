<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Lottery;
use app\service\LotteryServices;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 彩金
 */
class LotteryController
{
    protected $lottery;

    protected $lottery_pool;
    protected $game_lottery;

    public function __construct()
    {
        $this->lottery = plugin()->webman->config('database.lottery_model');
        $this->lottery_pool = plugin()->webman->config('database.lottery_pool_model');
        $this->game_lottery = plugin()->webman->config('database.game_lottery_model');
    }

    /**
     * 彩金列表
     * @auth true
     * @return Layout
     */
    public function index(): Layout
    {
        $layout = Layout::create();
        $layout->row(function (Row $row) {
            $row->gutter([10, 10]);
            $row->column(
                Card::create(Tabs::create()
                    ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->getList(GameType::TYPE_SLOT))
                    ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->getList(GameType::TYPE_STEEL_BALL))
                    ->type('card')
                ));
        });

        return $layout;
    }

    /**
     * @param $type
     * @return Grid
     */
    public function getList($type): Grid
    {
        return Grid::create(new $this->lottery, function (Grid $grid) use ($type) {
            $grid->model()->with(['player'])->where('game_type', $type)->orderBy('sort', 'desc')->orderBy('id', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
            }
            $grid->title(admin_trans('lottery.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('lottery.fields.id'))->align('center');
            $grid->column('name', admin_trans('lottery.fields.name'))->align('center')->filter(
                FilterColumn::like()->text('name')
            );

            // 彩池金额（固定和随机彩金都显示，实时显示DB+Redis总额）
            $grid->column('amount', admin_trans('lottery.fields.amount'))->display(function ($val, Lottery $data) {
                // 从Redis获取实时金额并累加
                try {
                    $redis = \support\Redis::connection()->client();
                    $redisKey = LotteryServices::REDIS_KEY_LOTTERY_AMOUNT . $data->id;
                    $redisAmount = $redis->get($redisKey);
                    if ($redisAmount !== false && $redisAmount > 0) {
                        $val = bcadd($val, $redisAmount, 4);
                    }
                } catch (\Exception) {
                    // 降级使用数据库金额
                }

                // 显示实时总金额（绿色粗体）
                return Html::create()->content([
                    Html::div()
                        ->content(number_format($val, 2))
                        ->style(['color' => '#52c41a', 'font-weight' => 'bold'])
                ]);
            })->align('center');

            // 入池比值（固定和随机彩金都显示）
            $grid->column('pool_ratio', admin_trans('lottery.fields.pool_ratio'))->display(function ($val, Lottery $data) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            })->align('center');

            // 派发比例（固定和随机彩金都显示）
            $grid->column('rate', admin_trans('lottery.fields.dispatch_ratio'))->display(function ($val, Lottery $data) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            })->align('center');

            // 中奖概率（仅随机彩金显示）
            $grid->column('win_ratio', admin_trans('lottery.fields.win_ratio'))->display(function ($val, Lottery $data) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_RANDOM) {
                    $percentage = bcmul($val, 100, 6);
                    return Html::create()->content([
                        Html::div()->content($percentage . '%')
                    ]);
                }
                return '-';
            })->align('center');

            $grid->column('max_amount', admin_trans('lottery.fields.max_amount'))->display(function ($val) {
                return number_format($val, 4);
            })->align('center');

            // 最大彩池金额（仅随机彩金显示）
            $grid->column('max_pool_amount', admin_trans('lottery.fields.max_pool_amount'))->display(function ($val, Lottery $data) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_RANDOM) {
                    return number_format($val, 4);
                }
                return '-';
            })->align('center');

            // 保底金额（仅随机彩金显示）
            $grid->column('auto_refill_amount', admin_trans('lottery.fields.auto_refill_amount'))->display(function ($val, Lottery $data) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_RANDOM) {
                    if ($data->auto_refill_status == 1 && $val > 0) {
                        return Html::create()->content([
                            Html::div()
                                ->content(number_format($val, 2))
                                ->style(['color' => '#1890ff', 'font-weight' => 'bold']),
                            Html::div()
                                ->content(admin_trans('lottery.status_enabled_parenthesis'))
                                ->style(['color' => '#52c41a', 'font-size' => '12px'])
                        ]);
                    } else {
                        return Html::create()->content([
                            Html::div()
                                ->content(admin_trans('lottery.status_disabled'))
                                ->style(['color' => '#999', 'font-size' => '12px'])
                        ]);
                    }
                }
                return '-';
            })->align('center');

            // 新增：爆彩状态（仅随机彩金显示）
            $grid->column('burst_status', admin_trans('lottery.fields.burst_status'))->display(function ($val, Lottery $data) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_RANDOM) {
                    return Switches::create(null, $val)
                        ->options([[1 => admin_trans('lottery.status_enabled')], [0 => admin_trans('common.status.0')]])
                        ->field('burst_status')
                        ->url('ex-admin/addons-webman-controller-LotteryController/changeBurstStatus')
                        ->params(['id' => $data->id]);
                }
                return '-';
            })->align('center');
            $grid->column('game_type', admin_trans('lottery.fields.game_type'))->sortable()->display(function ($val, Lottery $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->game_type))
                ]);
            })->align('center');
            $grid->column('lottery_type', admin_trans('lottery.fields.lottery_type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('lottery.lottery_type.' . $val))->color($val == Lottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                ]);
            })->align('center')->align('center');
            $grid->column('condition', admin_trans('lottery.fields.condition'))->display(function ($val, Lottery $data) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_FIXED) {
                    if ($data->game_type == GameType::TYPE_SLOT) {
                        return admin_trans('lottery.slot_condition_msg') . $val;
                    }
                    if ($data->game_type == GameType::TYPE_STEEL_BALL) {
                        return admin_trans('lottery.jac_condition_msg') . $val;
                    }
                } else {
                    // 随机彩金显示概率信息
                    $winRatioPercent = bcmul($data->win_ratio, 100, 4);
                    return Html::create()->content([
                        Html::div()->content(admin_trans('lottery.probability') . ': ' . $winRatioPercent . '%')
                    ]);
                }
            })->align('center');
            $grid->column('last_player_name', admin_trans('lottery.fields.last_player_name'))->display(function ($val, Lottery $data) {
                if (!empty($data->player)) {
                    $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->player->phone),
                    ]);
                }
                return '';
            })->align('center');
            $grid->column('last_award_amount', admin_trans('lottery.fields.last_award_amount'))->display(function ($val) {
                return $val > 0 ? $val : '';
            })->align('center');
            $grid->column('lottery_times', admin_trans('lottery.fields.lottery_times'))->align('center');
            $grid->column('status', admin_trans('lottery.fields.status'))->display(function ($val, Lottery $data) use ($type) {
                return Switches::create(null, $val)
                    ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                    ->field('status')
                    ->url('ex-admin/addons-webman-controller-LotteryController/changeStatus')
                    ->params([
                        'id' => $data->id,
                    ]);
            })->align('center');
            $grid->sortInput('sort');
            $grid->column('created_at', admin_trans('lottery.fields.created_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->setForm()->drawer($this->form($type));
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('lottery.fields.name'));
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('game_type')
                    ->placeholder(admin_trans('game_type.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                        GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                    ]);
                $filter->eq()->select('lottery_type')
                    ->placeholder(admin_trans('lottery.fields.lottery_type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        Lottery::LOTTERY_TYPE_FIXED => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_FIXED),
                        Lottery::LOTTERY_TYPE_RANDOM => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_RANDOM),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('lottery.created_at_start'), admin_trans('lottery.created_at_end')]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 编辑彩金
     * @auth true
     * @param $type
     * @return Form
     */
    public function form($type): Form
    {
        return Form::create(new $this->lottery(), function (Form $form) use ($type) {
            $form->title(admin_trans('lottery.lottery_info'));
            $gameType = $form->getBindField('game_type');

            /** @var Lottery $model */
            // 修复：在编辑模式下正确加载模型
            if ($form->isEdit()) {
                $id = $form->driver()->get('id');
                $model = Lottery::query()->find($id);
            } else {
                $model = $form->driver()->model();
            }

            $maxRatio = $model->rate ?? 100;

            // 使用模型方法获取爆彩配置（自动处理默认值和JSON解析）
            $burstMultiplierConfig = $model ? $model->getBurstMultiplierConfig() : [
                'final' => 50,
                'stage_4' => 25,
                'stage_3' => 15,
                'stage_2' => 10,
                'initial' => 5,
            ];

            $burstTriggerConfig = $model ? $model->getBurstTriggerConfig() : [
                '95' => 10, '90' => 6, '85' => 4, '80' => 2.5, '75' => 1.5, '70' => 0.8,
                '65' => 0.4, '60' => 0.2, '50' => 0.1, '40' => 0.05, '30' => 0.02, '20' => 0.01,
            ];
            $form->radio('game_type', admin_trans('game_type.fields.type'))
                ->button()
                ->disabled($form->isEdit())
                ->default($type)
                ->options([
                    GameType::TYPE_SLOT => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                    GameType::TYPE_STEEL_BALL => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                ])->required();
            $form->text('name', admin_trans('lottery.fields.name'))
                ->maxlength(50)
                ->help(admin_trans('lottery.form_help.lottery_name'))
                ->placeholder(admin_trans('lottery.form_placeholder.lottery_name'))
                ->required();
            // ===== 新增：独立彩池配置 =====
            $form->divider()->content(admin_trans('lottery.divider_independent_pool_config'));
            $form->number('amount', admin_trans('lottery.fields.amount'))
                ->style(['width' => '100%'])
                ->min(0)
                ->max(10000000000)
                ->precision(2)
                ->help(admin_trans('lottery.form_help.pool_amount'))
                ->placeholder(admin_trans('lottery.form_placeholder.pool_amount'));

            $form->number('pool_ratio', admin_trans('lottery.fields.pool_ratio'))
                ->style(['width' => '100%'])
                ->min(0)
                ->max(100)
                ->precision(2)
                ->suffix('%')
                ->help(admin_trans('lottery.form_help.pool_ratio'))
                ->placeholder(admin_trans('lottery.form_placeholder.pool_ratio'))
                ->required();
            $form->row(function (Form $form) {
                $form->number('max_amount', admin_trans('lottery.max_amount'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100000000)
                    ->precision(0)
                    ->rule([
                        'min:1' => admin_trans('lottery.max_amount_min_1'),
                        'max:100000000' => admin_trans('lottery.max_amount_max_100000000'),
                    ])
                    ->help(admin_trans('lottery.form_help_extended.max_payout_amount'))
                    ->placeholder(admin_trans('lottery.form_placeholder_extended.max_payout_amount'))->span(11);
                $form->switch('max_status', admin_trans('lottery.max_status'))->default(1)
                    ->style(['margin-left' => '10px'])
                    ->span(11);
            });
            $form->radio('lottery_type', admin_trans('lottery.fields.lottery_type'))
                ->button()
                ->disabled($form->isEdit())
                ->default(Lottery::LOTTERY_TYPE_FIXED)
                ->options([
                    Lottery::LOTTERY_TYPE_FIXED => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_FIXED),
                    Lottery::LOTTERY_TYPE_RANDOM => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_RANDOM),
                ])->required()
                ->when(Lottery::LOTTERY_TYPE_FIXED, function (Form $form) use ($gameType) {
                    $form->hidden('game_type')->bindAttr('value', $gameType)
                        ->when(GameType::TYPE_SLOT, function (Form $form) use ($gameType) {
                            // 派彩比例配置
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_payout_config'));
                            $maxRatio = 100;
                            $form->text('rate', admin_trans('lottery.fields.rate'))
                                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                                ->rule([
                                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                                ])
                                ->suffix('%')
                                ->default(100)
                                ->help(admin_trans('lottery.machine_lottery.payout_ratio_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.payout_ratio_placeholder'))
                                ->required();

                            // 触发条件配置
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_trigger_config'));
                            $form->number('condition', admin_trans('lottery.fields.condition'))
                                ->style(['width' => '100%'])
                                ->min(1)
                                ->max(100000000)
                                ->prefix(admin_trans('lottery.slot_condition_msg'))
                                ->help(admin_trans('lottery.machine_lottery.slot_condition_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.slot_condition_placeholder'))
                                ->required();
                        })->when(GameType::TYPE_STEEL_BALL, function (Form $form) use ($gameType) {
                            // 派彩比例配置
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_payout_config'));
                            $maxRatio = 100;
                            $form->text('rate', admin_trans('lottery.fields.rate'))
                                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                                ->rule([
                                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                                ])
                                ->suffix('%')
                                ->default(100)
                                ->help(admin_trans('lottery.machine_lottery.payout_ratio_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.payout_ratio_placeholder'))
                                ->required();

                            // 触发条件配置
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_trigger_config'));
                            $form->number('condition', admin_trans('lottery.fields.condition'))
                                ->style(['width' => '100%'])
                                ->min(1)
                                ->max(100000000)
                                ->prefix(admin_trans('lottery.jac_condition_msg'))
                                ->help(admin_trans('lottery.machine_lottery.steel_ball_condition_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.steel_ball_condition_placeholder'))
                                ->required();
                        });
                })->when(Lottery::LOTTERY_TYPE_RANDOM, function (Form $form) use ($gameType, $burstMultiplierConfig, $burstTriggerConfig) {
                    $form->hidden('game_type')->bindAttr('value', $gameType)
                        ->when(GameType::TYPE_SLOT, function (Form $form) use ($gameType, $burstMultiplierConfig, $burstTriggerConfig) {
                            // ===== 新增：概率派彩配置 =====
                            $maxRatio = 100;
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_probability_config'));
                            $form->text('rate', admin_trans('lottery.fields.rate'))
                                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                                ->rule([
                                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                                ])
                                ->suffix('%')
                                ->help(admin_trans('lottery.machine_lottery.payout_ratio_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.payout_ratio_placeholder'))
                                ->required();
                            $form->number('win_ratio', admin_trans('lottery.machine_lottery.win_ratio_label'))
                                ->style(['width' => '100%'])
                                ->min(0)
                                ->max(1)
                                ->default(0)
                                ->precision(6)
                                ->help(admin_trans('lottery.form_help.win_ratio'))
                                ->placeholder(admin_trans('lottery.form_placeholder.win_ratio'))
                                ->required();
                            $form->row(function (Form $form) {
                                $form->number('double_amount', admin_trans('lottery.double_amount'))->style(['width' => '100%'])
                                    ->default(0)
                                    ->min(0)
                                    ->max(100000000)
                                    ->precision(0)
                                    ->rule([
                                        'min:1' => admin_trans('lottery.max_double_amount_min_1'),
                                        'max:100000000' => admin_trans('lottery.max_double_amount_100000000'),
                                    ])
                                    ->help(admin_trans('lottery.form_help_extended.double_trigger_amount'))
                                    ->placeholder(admin_trans('lottery.form_placeholder_extended.double_trigger_amount'))->span(11);
                                $form->switch('double_status', admin_trans('lottery.double_status'))
                                    ->default(0)
                                    ->style(['margin-left' => '10px'])
                                    ->span(11);
                            });
                            // ===== 新增：爆彩功能配置 =====
                            $form->divider()->content(admin_trans('lottery.burst_config.divider_title'));

                            $form->row(function (Form $form) {
                                $form->switch('burst_status', admin_trans('lottery.burst_config.status_label'))
                                    ->default(0)
                                    ->help(admin_trans('lottery.burst_config.status_help'))
                                    ->span(12);
                                $form->number('burst_duration', admin_trans('lottery.burst_config.duration_label'))
                                    ->style(['width' => '100%'])
                                    ->min(1)->max(120)->precision(0)->suffix(admin_trans('lottery.machine_lottery.minutes_suffix'))->default(15)
                                    ->help(admin_trans('lottery.burst_config.duration_help'))
                                    ->placeholder(admin_trans('lottery.burst_config.duration_placeholder'))
                                    ->span(12);
                            });

                            $form->number('max_pool_amount', admin_trans('lottery.max_pool_amount'))
                                ->style(['width' => '100%'])
                                ->min(0)->max(10000000000)->precision(2)
                                ->help(admin_trans('lottery.form_help_extended.max_pool_cap'))
                                ->placeholder(admin_trans('lottery.form_placeholder_extended.max_pool_cap'))
                                ->required();

                            // ===== 新增：保底金额配置 =====
                            $form->divider()->content(admin_trans('lottery.auto_refill.divider_title'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #f0f5ff; border-left: 4px solid #597ef7;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>保底金额是彩池的最低维持金额，确保彩池始终有足够的资金进行派彩。<br>
                                    <strong>工作原理：</strong><br>
                                    • 派彩前：如果彩池不足派彩金额，自动补充到保底金额<br>
                                    • 派彩后：如果彩池低于保底金额，自动补充到保底金额<br>
                                    <strong>举例：</strong>保底金额设置为5000元：<br>
                                    • 彩池3000元，需派彩4000元 → 先补充到5000元，再派彩4000元，剩余1000元 → 再补充到5000元<br>
                                    • 彩池6000元，派彩2000元后剩余4000元 → 自动补充到5000元<br>
                                    <strong>建议：</strong>保底金额应设置为最大派彩金额的1-2倍，确保随时有足够资金派彩
                                </p>
                            </div>');
                            $form->row(function (Form $form) {
                                $form->switch('auto_refill_status', admin_trans('lottery.auto_refill.status_label'))
                                    ->default(0)
                                    ->help(admin_trans('lottery.auto_refill.status_help'))
                                    ->span(12);
                                $form->number('auto_refill_amount', admin_trans('lottery.auto_refill.amount_label'))
                                    ->style(['width' => '100%'])
                                    ->min(0)->max(10000000000)->precision(2)->default(0)
                                    ->help(admin_trans('lottery.auto_refill.amount_help'))
                                    ->placeholder(admin_trans('lottery.auto_refill.amount_placeholder'))
                                    ->span(12);
                            });

                            // 隐藏域用于保存JSON配置
                            $form->hidden('burst_multiplier_config');
                            $form->hidden('burst_trigger_config');

                            // 爆彩倍数配置
                            $form->divider()->content(admin_trans('lottery.burst_config.divider_multiplier'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #e6f7ff; border-left: 4px solid #1890ff;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>爆彩期间，玩家中奖概率会根据剩余时间自动提升。倍数越高，中奖越容易。<br>
                                    <strong>举例：</strong>假设正常中奖概率为 0.1%，爆彩持续15分钟：<br>
                                    • 前10.5分钟（剩余70%-100%）：中奖概率 = 0.1% × 5 = 0.5%<br>
                                    • 第10.5-12分钟（剩余30%-70%）：中奖概率逐步提升到 0.1% × 10 = 1%<br>
                                    • 最后1.5分钟（剩余≤10%）：中奖概率 = 0.1% × 50 = 5%（最高）
                                </p>
                            </div>');
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_final', admin_trans('lottery.burst_multiplier.final_label'))->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(50)->value($burstMultiplierConfig['final'])->precision(1)->help(admin_trans('lottery.burst_multiplier.final_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_4', '10%-30%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(25)->value($burstMultiplierConfig['stage_4'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_4_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_3', '30%-50%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(15)->value($burstMultiplierConfig['stage_3'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_3_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_2', '50%-70%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(10)->value($burstMultiplierConfig['stage_2'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_2_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_initial', '>70%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(5)->value($burstMultiplierConfig['initial'])->precision(1)->help(admin_trans('lottery.burst_multiplier.initial_help'))->ignore()->span(24);
                            });

                            // 爆彩触发概率配置
                            $form->divider()->content(admin_trans('lottery.burst_config.divider_trigger'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #fff7e6; border-left: 4px solid #faad14;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>当彩池金额越接近最大彩池时，触发爆彩的概率越高。每次玩家下注后都会检查是否触发。<br>
                                    <strong>举例：</strong>假设最大彩池为10000元：<br>
                                    • 彩池9500元（95%）：每次下注有10%概率触发爆彩<br>
                                    • 彩池8000元（80%）：每次下注有2.5%概率触发爆彩<br>
                                    • 彩池5000元（50%）：每次下注有0.1%概率触发爆彩<br>
                                    <strong>建议：</strong>彩池越满概率越高，确保彩池能及时派发，避免长期积累
                                </p>
                            </div>');
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_95', admin_trans('lottery.burst_trigger.95_label'))->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(10)->value($burstTriggerConfig['95'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.95_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_90', '90%-95%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(6)->value($burstTriggerConfig['90'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.90_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_85', '85%-90%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(4)->value($burstTriggerConfig['85'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.85_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_80', '80%-85%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(2.5)->value($burstTriggerConfig['80'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.80_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_75', '75%-80%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(1.5)->value($burstTriggerConfig['75'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.75_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_70', '70%-75%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.8)->value($burstTriggerConfig['70'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.70_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_65', '65%-70%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.4)->value($burstTriggerConfig['65'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.65_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_60', '60%-65%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.2)->value($burstTriggerConfig['60'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.60_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_50', '50%-60%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.1)->value($burstTriggerConfig['50'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.50_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_40', '40%-50%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.05)->value($burstTriggerConfig['40'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.40_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_30', '30%-40%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.02)->value($burstTriggerConfig['30'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.30_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_20', '20%-30%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.01)->value($burstTriggerConfig['20'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.20_help'))->ignore()->span(24);
                            });
                        })->when(GameType::TYPE_STEEL_BALL, function (Form $form) use ($gameType, $burstMultiplierConfig, $burstTriggerConfig) {
                            $form->divider()->content(admin_trans('lottery.machine_lottery.divider_probability_config'));
                            $maxRatio = 100;
                            $form->text('rate', admin_trans('lottery.fields.rate'))
                                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                                ->rule([
                                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                                ])
                                ->suffix('%')
                                ->default(100)
                                ->help(admin_trans('lottery.machine_lottery.payout_ratio_help'))
                                ->placeholder(admin_trans('lottery.machine_lottery.payout_ratio_placeholder'))
                                ->required();

                            $form->number('win_ratio', admin_trans('lottery.machine_lottery.win_ratio_label'))
                                ->style(['width' => '100%'])
                                ->min(0)
                                ->max(1)
                                ->precision(6)
                                ->help(admin_trans('lottery.form_help.win_ratio'))
                                ->placeholder(admin_trans('lottery.form_placeholder.win_ratio'))
                                ->required();

                            $form->row(function (Form $form) {
                                $form->number('double_amount', admin_trans('lottery.double_amount'))->style(['width' => '100%'])
                                    ->default(0)
                                    ->min(0)
                                    ->max(100000000)
                                    ->precision(0)
                                    ->rule([
                                        'min:1' => admin_trans('lottery.max_double_amount_min_1'),
                                        'max:100000000' => admin_trans('lottery.max_double_amount_100000000'),
                                    ])
                                    ->help(admin_trans('lottery.form_help_extended.double_trigger_amount'))
                                    ->placeholder(admin_trans('lottery.form_placeholder_extended.double_trigger_amount'))->span(11);
                                $form->switch('double_status', admin_trans('lottery.double_status'))
                                    ->default(0)
                                    ->style(['margin-left' => '10px'])
                                    ->span(11);
                            });

                            // 保底金额配置
                            $form->divider()->content(admin_trans('lottery.auto_refill.divider_title'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #f0f5ff; border-left: 4px solid #597ef7;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>保底金额是彩池的最低维持金额，确保彩池始终有足够的资金进行派彩。<br>
                                    <strong>工作原理：</strong><br>
                                    • 派彩前：如果彩池不足派彩金额，自动补充到保底金额<br>
                                    • 派彩后：如果彩池低于保底金额，自动补充到保底金额<br>
                                    <strong>举例：</strong>保底金额设置为5000元：<br>
                                    • 彩池3000元，需派彩4000元 → 先补充到5000元，再派彩4000元，剩余1000元 → 再补充到5000元<br>
                                    • 彩池6000元，派彩2000元后剩余4000元 → 自动补充到5000元<br>
                                    <strong>建议：</strong>保底金额应设置为最大派彩金额的1-2倍，确保随时有足够资金派彩
                                </p>
                            </div>');
                            $form->row(function (Form $form) {
                                $form->switch('auto_refill_status', admin_trans('lottery.auto_refill.status_label'))
                                    ->default(0)
                                    ->help(admin_trans('lottery.auto_refill.status_help'))
                                    ->span(12);
                                $form->number('auto_refill_amount', admin_trans('lottery.auto_refill.amount_label'))
                                    ->style(['width' => '100%'])
                                    ->min(0)->max(10000000000)->precision(2)->default(0)
                                    ->help(admin_trans('lottery.auto_refill.amount_help'))
                                    ->placeholder(admin_trans('lottery.auto_refill.amount_placeholder'))
                                    ->span(12);
                            });

                            $form->divider()->content(admin_trans('lottery.burst_config.divider_title'));

                            $form->row(function (Form $form) {
                                $form->switch('burst_status', admin_trans('lottery.burst_config.status_label'))
                                    ->default(0)
                                    ->help(admin_trans('lottery.burst_config.status_help'))
                                    ->span(12);
                                $form->number('burst_duration', admin_trans('lottery.burst_config.duration_label'))
                                    ->style(['width' => '100%'])
                                    ->min(1)->max(120)->precision(0)->suffix(admin_trans('lottery.machine_lottery.minutes_suffix'))->default(15)
                                    ->help(admin_trans('lottery.burst_config.duration_help'))
                                    ->placeholder(admin_trans('lottery.burst_config.duration_placeholder'))
                                    ->span(12);
                            });

                            $form->number('max_pool_amount', admin_trans('lottery.max_pool_amount'))
                                ->style(['width' => '100%'])
                                ->default(0)
                                ->min(0)->max(10000000000)->precision(2)
                                ->help(admin_trans('lottery.form_help_extended.max_pool_cap'))
                                ->placeholder(admin_trans('lottery.form_placeholder_extended.max_pool_cap'))
                                ->required();

                            // 隐藏域用于保存JSON配置
                            $form->hidden('burst_multiplier_config');
                            $form->hidden('burst_trigger_config');

                            // 爆彩倍数配置
                            $form->divider()->content(admin_trans('lottery.burst_config.divider_multiplier'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #e6f7ff; border-left: 4px solid #1890ff;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>爆彩期间，玩家中奖概率会根据剩余时间自动提升。倍数越高，中奖越容易。<br>
                                    <strong>举例：</strong>假设正常中奖概率为 0.1%，爆彩持续15分钟：<br>
                                    • 前10.5分钟（剩余70%-100%）：中奖概率 = 0.1% × 5 = 0.5%<br>
                                    • 第10.5-12分钟（剩余30%-70%）：中奖概率逐步提升到 0.1% × 10 = 1%<br>
                                    • 最后1.5分钟（剩余≤10%）：中奖概率 = 0.1% × 50 = 5%（最高）
                                </p>
                            </div>');
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_final', admin_trans('lottery.burst_multiplier.final_label'))->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(50)->value($burstMultiplierConfig['final'])->precision(1)->help(admin_trans('lottery.burst_multiplier.final_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_4', '10%-30%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(25)->value($burstMultiplierConfig['stage_4'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_4_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_3', '30%-50%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(15)->value($burstMultiplierConfig['stage_3'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_3_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_stage_2', '50%-70%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(10)->value($burstMultiplierConfig['stage_2'])->precision(1)->help(admin_trans('lottery.burst_multiplier.stage_2_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                                $form->number('burst_multiplier_initial', '>70%')->style(['width' => '100%'])
                                    ->min(1)->max(100)->default(5)->value($burstMultiplierConfig['initial'])->precision(1)->help(admin_trans('lottery.burst_multiplier.initial_help'))->ignore()->span(24);
                            });

                            // 爆彩触发概率配置
                            $form->divider()->content(admin_trans('lottery.burst_config.divider_trigger'));
                            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #fff7e6; border-left: 4px solid #faad14;">
                                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                                    <strong>说明：</strong>当彩池金额越接近最大彩池时，触发爆彩的概率越高。每次玩家下注后都会检查是否触发。<br>
                                    <strong>举例：</strong>假设最大彩池为10000元：<br>
                                    • 彩池9500元（95%）：每次下注有10%概率触发爆彩<br>
                                    • 彩池8000元（80%）：每次下注有2.5%概率触发爆彩<br>
                                    • 彩池5000元（50%）：每次下注有0.1%概率触发爆彩<br>
                                    <strong>建议：</strong>彩池越满概率越高，确保彩池能及时派发，避免长期积累
                                </p>
                            </div>');
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_95', admin_trans('lottery.burst_trigger.95_label'))->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(10)->value($burstTriggerConfig['95'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.95_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_90', '90%-95%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(6)->value($burstTriggerConfig['90'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.90_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_85', '85%-90%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(4)->value($burstTriggerConfig['85'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.85_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_80', '80%-85%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(2.5)->value($burstTriggerConfig['80'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.80_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_75', '75%-80%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(1.5)->value($burstTriggerConfig['75'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.75_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_70', '70%-75%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.8)->value($burstTriggerConfig['70'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.70_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_65', '65%-70%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.4)->value($burstTriggerConfig['65'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.65_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_60', '60%-65%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.2)->value($burstTriggerConfig['60'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.60_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_50', '50%-60%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.1)->value($burstTriggerConfig['50'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.50_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_40', '40%-50%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.05)->value($burstTriggerConfig['40'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.40_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_30', '30%-40%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.02)->value($burstTriggerConfig['30'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.30_help'))->ignore()->span(24);
                            });
                            $form->row(function (Form $form) use ($burstTriggerConfig) {
                                $form->number('burst_trigger_20', '20%-30%')->style(['width' => '100%'])
                                    ->min(0)->max(100)->default(0.01)->value($burstTriggerConfig['20'])->precision(2)->suffix('%')->help(admin_trans('lottery.burst_trigger.20_help'))->ignore()->span(24);
                            });

                        });
                });
            $form->switch('status', admin_trans('lottery.fields.status'))->default(1);
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                $lotteryType = $form->input('lottery_type');
                $gameType = $form->input('game_type');

                if (!$form->isEdit()) {
                    $count = Lottery::query()->where('game_type', $gameType)->count();
                    if ($count > 4) {
                        return message_error(admin_trans('lottery.rul.max_count_five'));
                    }
                }

                // 检查是否更新了中奖概率
                if ($form->isEdit()) {
                    $id = $form->driver()->get('id');
                    $oldModel = Lottery::query()->find($id);
                    $newWinRatio = $form->input('win_ratio');

                    // 如果中奖概率发生变化，重置开奖统计
                    if ($oldModel && $oldModel->win_ratio != $newWinRatio) {
                        try {
                            $redis = \support\Redis::connection()->client();

                            // 删除总统计
                            $redis->del('lottery_stats:total:' . $id);
                            $redis->del('lottery_stats:win:' . $id);

                            // 删除所有日期的统计（使用 SCAN 避免阻塞 Redis）
                            $pattern = 'lottery_stats:daily:*:' . $id . ':*';
                            $cursor = 0;
                            $deletedCount = 0;

                            do {
                                // 使用 SCAN 分批查找，每次最多返回 100 个键
                                // Redis::scan($cursor, $pattern, $count) - 使用位置参数
                                $result = $redis->scan($cursor, $pattern, 100);

                                if ($result === false) {
                                    break; // 扫描失败，退出循环
                                }

                                $cursor = $result[0];
                                $keys = $result[1] ?? [];

                                if (!empty($keys)) {
                                    $redis->del($keys);
                                    $deletedCount += count($keys);
                                }
                            } while ($cursor != 0);

                            if ($deletedCount > 0) {
                                \support\Log::info('清理日统计键', [
                                    'lottery_id' => $id,
                                    'game_type' => $gameType,
                                    'deleted_count' => $deletedCount,
                                ]);
                            }

                            \support\Log::info(admin_trans('lottery.log.reset_stats'), [
                                'lottery_id' => $id,
                                'game_type' => $gameType,
                                'old_win_ratio' => $oldModel->win_ratio,
                                'new_win_ratio' => $newWinRatio,
                            ]);
                        } catch (\Exception $e) {
                            \support\Log::error(admin_trans('lottery.log.reset_stats_failed') . ': ' . $e->getMessage());
                        }
                    }
                }

                if ($lotteryType == Lottery::LOTTERY_TYPE_RANDOM) {
                    // 组装爆彩倍数配置为JSON
                    $multiplierConfig = [
                        'final' => floatval($form->input('burst_multiplier_final') ?? 50),
                        'stage_4' => floatval($form->input('burst_multiplier_stage_4') ?? 25),
                        'stage_3' => floatval($form->input('burst_multiplier_stage_3') ?? 15),
                        'stage_2' => floatval($form->input('burst_multiplier_stage_2') ?? 10),
                        'initial' => floatval($form->input('burst_multiplier_initial') ?? 5),
                    ];
                    $form->input('burst_multiplier_config', json_encode($multiplierConfig));

                    // 组装爆彩触发概率配置为JSON
                    $triggerConfig = [
                        '95' => floatval($form->input('burst_trigger_95') ?? 10),
                        '90' => floatval($form->input('burst_trigger_90') ?? 6),
                        '85' => floatval($form->input('burst_trigger_85') ?? 4),
                        '80' => floatval($form->input('burst_trigger_80') ?? 2.5),
                        '75' => floatval($form->input('burst_trigger_75') ?? 1.5),
                        '70' => floatval($form->input('burst_trigger_70') ?? 0.8),
                        '65' => floatval($form->input('burst_trigger_65') ?? 0.4),
                        '60' => floatval($form->input('burst_trigger_60') ?? 0.2),
                        '50' => floatval($form->input('burst_trigger_50') ?? 0.1),
                        '40' => floatval($form->input('burst_trigger_40') ?? 0.05),
                        '30' => floatval($form->input('burst_trigger_30') ?? 0.02),
                        '20' => floatval($form->input('burst_trigger_20') ?? 0.01),
                    ];
                    $form->input('burst_trigger_config', json_encode($triggerConfig));

                    // 验证新增字段
                    $poolRatio = $form->input('pool_ratio');
                    $winRatio = $form->input('win_ratio');
                    $maxPoolAmount = $form->input('max_pool_amount');
                    $autoRefillStatus = $form->input('auto_refill_status');
                    $autoRefillAmount = $form->input('auto_refill_amount');

                    if (empty($poolRatio) || $poolRatio <= 0) {
                        return message_error(admin_trans('common.pool_ratio_must_greater_than_zero'));
                    }
                    if ($poolRatio > 100) {
                        return message_error(admin_trans('common.pool_ratio_cannot_exceed_100'));
                    }
                    if (empty($winRatio) || $winRatio <= 0) {
                        return message_error(admin_trans('common.win_probability_must_greater_than_zero'));
                    }
                    if ($winRatio > 1) {
                        return message_error(admin_trans('common.win_probability_cannot_exceed_1'));
                    }
                    if (empty($maxPoolAmount) || $maxPoolAmount <= 0) {
                        return message_error(admin_trans('common.max_pool_amount_must_greater_than_zero'));
                    }

                    // 验证保底金额
                    if ($autoRefillStatus == 1) {
                        if (empty($autoRefillAmount) || $autoRefillAmount <= 0) {
                            return message_error(admin_trans('common.minimum_amount_must_greater_than_zero'));
                        }
                        if ($autoRefillAmount > $maxPoolAmount) {
                            return message_error(admin_trans('common.minimum_amount_cannot_exceed_max'));
                        }
                    }
                } else {
                    // 固定彩金验证
                    $condition = $form->input('condition');
                    if (empty($condition)) {
                        return message_error(admin_trans('lottery.rul.condition_required'));
                    }

                    // 验证 rate（固定彩金也需要）
                    $rate = $form->input('rate');
                    if (empty($rate) && $rate !== '0') {
                        $form->input('rate', 100); // 默认100%
                    } elseif ($rate < 0 || $rate > 100) {
                        return message_error(admin_trans('common.distribution_ratio_range_error'));
                    }

                    // 固定彩金不需要随机彩金的字段，设置默认值
                    if (!$form->isEdit()) {
                        $form->input('pool_ratio', 0);
                        $form->input('win_ratio', 0);
                        $form->input('max_pool_amount', 0);
                        $form->input('burst_status', 0);
                    }
                }
            });
        });
    }

    /**
     * 同步彩金池（将Redis数据同步到数据库）
     * @auth true
     */
    public function syncLotteryPool($type): Form
    {
        return Form::create([], function (Form $form) use ($type) {
            $form->title(admin_trans('lottery.sync.title'));

            // 计算当前总金额
            $totalAmount = Lottery::query()
                ->where('game_type', $type)
                ->where('lottery_type', Lottery::LOTTERY_TYPE_RANDOM)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->sum('amount');

            // 从Redis获取待同步金额
            $redisPendingAmount = 0;
            try {
                $redis = \support\Redis::connection()->client();
                $lotteries = Lottery::query()
                    ->where('game_type', $type)
                    ->where('lottery_type', Lottery::LOTTERY_TYPE_RANDOM)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($lotteries as $lottery) {
                    $redisKey = LotteryServices::REDIS_KEY_LOTTERY_AMOUNT . $lottery->id;
                    $redisAmount = $redis->get($redisKey);
                    if ($redisAmount !== false && $redisAmount > 0) {
                        $redisPendingAmount = bcadd($redisPendingAmount, $redisAmount, 4);
                    }
                }
            } catch (\Exception $e) {
                // Redis读取失败
            }

            $form->html(admin_trans('lottery.sync.current_db_amount') . '<strong style="color:#52c41a;font-size:18px;">' . number_format($totalAmount, 2) . '</strong>');
            $form->html(admin_trans('lottery.sync.redis_pending_amount') . '<strong style="color:#ff9800;font-size:18px;">' . number_format($redisPendingAmount, 2) . '</strong>');
            $form->html(admin_trans('lottery.sync.after_sync_amount') . '<strong style="color:#1890ff;font-size:18px;">' . number_format(bcadd($totalAmount, $redisPendingAmount, 2), 2) . '</strong>');

            $form->html('<div style="margin-top:20px;padding:10px;background:#fffbe6;border:1px solid #ffe58f;border-radius:4px;">
                <strong>说明：</strong><br/>
                1. 系统会定期自动同步Redis数据到数据库<br/>
                2. 点击确定将立即强制同步所有彩金的Redis累积金额<br/>
                3. 同步后Redis累积金额将清零
            </div>');

            $form->hidden('type')->default($type);

            $form->actions()->hideResetButton();
            $form->saving(function (Form $form) {
                $type = $form->input('type');

                // 调用强制同步方法
                $result = LotteryServices::forceSyncRedisToDatabase();

                if ($result['success']) {
                    return message_success(admin_trans('lottery.sync.success', null, ['{count}' => $result['synced_count']]))->refresh();
                } else {
                    return message_error(admin_trans('lottery.sync.failed') . json_encode($result['errors']));
                }
            });
        });
    }
}

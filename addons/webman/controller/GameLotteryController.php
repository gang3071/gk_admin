<?php

namespace addons\webman\controller;

use addons\webman\model\GameLottery;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use support\Log;

/**
 * 彩金
 */
class GameLotteryController
{
    protected $game_lottery;

    public function __construct()
    {
        $this->game_lottery = plugin()->webman->config('database.game_lottery_model');
    }

    /**
     * 电子游戏彩金
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new $this->game_lottery, function (Grid $grid) {
            $grid->model()->with(['player'])->orderBy('sort', 'desc')->orderBy('id', 'desc');
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
            $grid->column('rate', admin_trans('lottery.fields.rate'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            })->align('center');
            $grid->column('pool_ratio', admin_trans('lottery.pool_ratio'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            })->align('center');
            $grid->column('win_ratio', admin_trans('lottery.fields.win_ratio'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(formatWinRatio(floatval($val))),
                ]);
            })->align('center');
            $grid->column('amount', admin_trans('lottery.game_amount'))
                ->display(function ($val) {
                    return number_format($val, 4);
                })
                ->align('center');
            $grid->column('max_amount', admin_trans('lottery.max_amount'))->align('center');
            $grid->column('max_pool_amount', admin_trans('lottery.max_pool_amount'))->align('center');
            $grid->column('double_amount', admin_trans('lottery.double_amount'))->align('center');

            // 保底金额列
            $grid->column('auto_refill_amount', admin_trans('lottery.auto_refill_amount'))->display(function ($val, GameLottery $data) {
                if ($data->auto_refill_status == 1 && $val > 0) {
                    return Html::create()->content([
                        Html::div()
                            ->content(number_format($val, 2))
                            ->style(['color' => '#1890ff', 'font-weight' => 'bold']),
                        Html::div()
                            ->content('(' . admin_trans('lottery.enabled') . ')')
                            ->style(['color' => '#52c41a', 'font-size' => '12px'])
                    ]);
                } else {
                    return Html::create()->content([
                        Html::div()
                            ->content(admin_trans('lottery.disabled'))
                            ->style(['color' => '#999', 'font-size' => '12px'])
                    ]);
                }
            })->align('center');

            $grid->column('lottery_type', admin_trans('lottery.fields.lottery_type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('lottery.lottery_type.' . $val))->color($val == GameLottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                ]);
            })->align('center')->align('center');
            $grid->column('last_player_name', admin_trans('lottery.fields.last_player_name'))->display(function ($val, GameLottery $data) {
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

            // 开奖次数和中奖次数统计
            $grid->column('lottery_stats', admin_trans('lottery.lottery_stats'))->display(function ($val, GameLottery $data) {
                try {
                    $redis = \support\Redis::connection()->client();
                    $today = date('Y-m-d');

                    // 获取总统计
                    $totalChecks = (int)$redis->get('game_lottery_stats:total:' . $data->id) ?: 0;
                    $totalWins = (int)$redis->get('game_lottery_stats:win:' . $data->id) ?: 0;

                    // 获取今日统计
                    $dailyChecks = (int)$redis->get('game_lottery_stats:daily:total:' . $data->id . ':' . $today) ?: 0;
                    $dailyWins = (int)$redis->get('game_lottery_stats:daily:win:' . $data->id . ':' . $today) ?: 0;

                    // 计算中奖率（保留8位小数以显示极低概率）
                    $totalWinRate = $totalChecks > 0 ? round(($totalWins / $totalChecks) * 100, 8) : 0;
                    $dailyWinRate = $dailyChecks > 0 ? round(($dailyWins / $dailyChecks) * 100, 8) : 0;

                    return Html::create()->content([
                        // 总统计
                        Html::div()->content(admin_trans('lottery.stats_total') . ': ' . $totalChecks . admin_trans('lottery.stats_times') . ' / ' . $totalWins . admin_trans('lottery.stats_win') . ' (' . $totalWinRate . '%)')
                            ->style(['font-size' => '12px', 'color' => '#1890ff']),
                        // 今日统计
                        Html::div()->content(admin_trans('lottery.stats_today') . ': ' . $dailyChecks . admin_trans('lottery.stats_times') . ' / ' . $dailyWins . admin_trans('lottery.stats_win') . ' (' . $dailyWinRate . '%)')
                            ->style(['font-size' => '12px', 'color' => '#52c41a', 'margin-top' => '4px']),
                    ]);
                } catch (\Exception $e) {
                    return Html::create()->content([
                        Html::div()->content(admin_trans('lottery.stats_error'))
                            ->style(['color' => '#ff4d4f', 'font-size' => '12px'])
                    ]);
                }
            })->align('center');

            $grid->column('lottery_times', admin_trans('lottery.fields.lottery_times'))->align('center');
            $grid->column('status', admin_trans('lottery.fields.status'))->switch()->align('center');
            $grid->column('double_status', admin_trans('lottery.double_status'))->switch()->align('center');
            $grid->column('max_status', admin_trans('lottery.max_status'))->switch()->align('center');
            $grid->sortInput('sort');
            $grid->column('created_at', admin_trans('lottery.fields.created_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();

            // 添加清理统计数据按钮
            $grid->tools([
                Button::create(admin_trans('lottery.clear_stats'))
                    ->icon(Icon::create('DeleteOutlined'))
                    ->type('danger')
                    ->confirm(admin_trans('lottery.clear_stats_confirm'), [$this, 'clearStats'])
                    ->gridRefresh()
            ]);

            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('lottery.fields.name'));
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('lottery.created_at_start'), admin_trans('lottery.created_at_end')]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 编辑电子游戏彩金
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->game_lottery(), function (Form $form) {
            $form->title(admin_trans('lottery.lottery_info'));
            /** @var GameLottery $model */
            if ($form->isEdit()) {
                $id = $form->driver()->get('id');
                $model = GameLottery::query()->find($id);
            } else {
                $model = $form->driver()->model();
            }
            // 使用模型方法获取配置（自动处理默认值和 JSON 解析）
            $burstMultiplierConfig = $model->getBurstMultiplierConfig();
            $burstTriggerConfig = $model->getBurstTriggerConfig();
            $maxRatio = 100;
            $form->text('name', admin_trans('lottery.fields.name'))
                ->maxlength(50)
                ->help(admin_trans('lottery.form_help.lottery_name'))
                ->placeholder(admin_trans('lottery.form_placeholder.lottery_name'))
                ->required();
            $form->number('amount', admin_trans('lottery.fields.amount'))
                ->style(['width' => '100%'])
                ->min(0)
                ->max(10000000000)
                ->precision(2)
                ->help(admin_trans('lottery.form_help.pool_amount'))
                ->placeholder(admin_trans('lottery.form_placeholder.pool_amount'));
            $form->text('pool_ratio', admin_trans('lottery.pool_ratio'))
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->suffix('%')
                ->help(admin_trans('lottery.form_help.pool_ratio'))
                ->placeholder(admin_trans('lottery.form_placeholder.pool_ratio'))
                ->required();
            $form->text('rate', admin_trans('lottery.fields.rate'))
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->suffix('%')
                ->help(admin_trans('lottery.form_help.dispatch_ratio'))
                ->placeholder(admin_trans('lottery.form_placeholder.dispatch_ratio'))
                ->required();
            $form->text('win_ratio', admin_trans('lottery.fields.win_ratio'))
                ->rulePattern('^(?:0(?:\.\d{1,9})?|1(?:\.0{1,9})?)$', admin_trans('validator.win_ratio'))
                ->help(admin_trans('lottery.form_help.win_ratio'))
                ->placeholder(admin_trans('lottery.form_placeholder.win_ratio'))
                ->required();
            // 打码量配置
            $form->divider()->content(admin_trans('lottery.divider_betting_config'));
            $form->number('base_bet_amount', admin_trans('lottery.base_bet_amount'))->style(['width' => '100%'])
                ->min(1)
                ->max(100000000)
                ->precision(2)
                ->default(100)
                ->rule([
                    'required' => admin_trans('lottery.form_validation.required_betting_amount_required'),
                    'min:1' => admin_trans('lottery.rul.base_bet_amount_0'),
                    'max:100000000' => admin_trans('lottery.rul.base_bet_amount_100000000'),
                ])
                ->help(admin_trans('lottery.form_help.required_betting_amount'))
                ->placeholder(admin_trans('lottery.form_placeholder.required_betting_amount'))
                ->required();
            $form->row(function (Form $form) {
                $form->number('amount', admin_trans('lottery.game_amount'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100000000)
                    ->precision(0)
                    ->rule([
                        'min:1' => admin_trans('lottery.game_amount_1'),
                        'max:100000000' => admin_trans('lottery.rul.game_amount_100000000'),
                    ])
                    ->help(admin_trans('lottery.form_help_extended.current_pool_amount'))
                    ->placeholder(admin_trans('lottery.form_placeholder_extended.current_pool_amount'))->span(11);
                $form->switch('status', admin_trans('lottery.fields.status'))->style(['margin-left' => '10px'])->default(1)->span(11);
            });
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
            $form->row(function (Form $form) {
                $form->number('max_pool_amount', admin_trans('lottery.max_pool_amount'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100000000)
                    ->precision(0)
                    ->rule([
                        'required' => admin_trans('lottery.form_validation.max_pool_amount_required'),
                        'min:1' => admin_trans('lottery.form_validation.max_pool_amount_min_1'),
                        'max:100000000' => admin_trans('lottery.form_validation.max_pool_amount_max_100000000'),
                    ])
                    ->help(admin_trans('lottery.form_help_extended.max_pool_cap'))
                    ->placeholder(admin_trans('lottery.form_placeholder_extended.max_pool_cap'))
                    ->required()
                    ->span(24);
            });

            // 保底金额配置
            $form->divider()->content(admin_trans('lottery.auto_refill.divider_title'));
            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #f0f5ff; border-left: 4px solid #597ef7;">
                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                    ' . admin_trans('lottery.auto_refill.description') . '
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

            // 爆彩配置区域
            $form->divider()->content(admin_trans('lottery.burst_config.divider_title'));

            // 隐藏域用于保存JSON配置
            $form->hidden('burst_multiplier_config');
            $form->hidden('burst_trigger_config');

            // 爆彩基础设置
            $form->row(function (Form $form) {
                $form->switch('burst_status', admin_trans('lottery.burst_config.status_label'))->default(1)
                    ->help(admin_trans('lottery.burst_config.status_help'))
                    ->span(8);
                $form->number('burst_duration', admin_trans('lottery.burst_config.duration_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(120)
                    ->default(15)
                    ->precision(0)
                    ->help(admin_trans('lottery.burst_config.duration_help'))
                    ->placeholder(admin_trans('lottery.burst_config.duration_placeholder'))
                    ->span(16);
            });

            // 爆彩倍数配置表格
            $form->divider()->content(admin_trans('lottery.burst_config.divider_multiplier'));
            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #e6f7ff; border-left: 4px solid #1890ff;">
                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                    ' . admin_trans('lottery.burst_multiplier.description') . '
                </p>
            </div>');

            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_final', admin_trans('lottery.burst_multiplier.final_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(50)
                    ->value($burstMultiplierConfig['final'] ?? 50)
                    ->precision(1)
                    ->help(admin_trans('lottery.burst_multiplier.final_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_4', admin_trans('lottery.burst_multiplier.stage_4_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(25)
                    ->value($burstMultiplierConfig['stage_4'] ?? 25)
                    ->precision(1)
                    ->help(admin_trans('lottery.burst_multiplier.stage_4_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_3', admin_trans('lottery.burst_multiplier.stage_3_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(15)
                    ->value($burstMultiplierConfig['stage_3'] ?? 15)
                    ->precision(1)
                    ->help(admin_trans('lottery.burst_multiplier.stage_3_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_2', admin_trans('lottery.burst_multiplier.stage_2_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(10)
                    ->value($burstMultiplierConfig['stage_2'] ?? 10)
                    ->precision(1)
                    ->help(admin_trans('lottery.burst_multiplier.stage_2_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_initial', admin_trans('lottery.burst_multiplier.initial_label'))->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(5)
                    ->value($burstMultiplierConfig['initial'] ?? 5)
                    ->precision(1)
                    ->help(admin_trans('lottery.burst_multiplier.initial_help'))
                    ->ignore()
                    ->span(24);
            });

            // 爆彩触发概率配置表格
            $form->divider()->content(admin_trans('lottery.burst_config.divider_trigger'));
            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #fff7e6; border-left: 4px solid #faad14;">
                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                    ' . admin_trans('lottery.burst_trigger.description') . '
                </p>
            </div>');

            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_95', admin_trans('lottery.burst_trigger.95_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(10)
                    ->value($burstTriggerConfig['95'] ?? 10)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.95_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_90', admin_trans('lottery.burst_trigger.90_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(6)
                    ->value($burstTriggerConfig['90'] ?? 6)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.90_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_85', admin_trans('lottery.burst_trigger.85_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['85'] ?? 4)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.85_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_80', admin_trans('lottery.burst_trigger.80_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['80'] ?? 2.5)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.80_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_75', admin_trans('lottery.burst_trigger.75_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['75'] ?? 1.5)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.75_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_70', admin_trans('lottery.burst_trigger.70_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['70'] ?? 0.8)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.70_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_65', admin_trans('lottery.burst_trigger.65_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['65'] ?? 0.4)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.65_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_60', admin_trans('lottery.burst_trigger.60_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['60'] ?? 0.2)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.60_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_50', admin_trans('lottery.burst_trigger.50_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['50'] ?? 0.1)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.50_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_40', admin_trans('lottery.burst_trigger.40_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['40'] ?? 0.05)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.40_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_30', admin_trans('lottery.burst_trigger.30_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(0.02)
                    ->value($burstTriggerConfig['30'] ?? 0.02)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.30_help'))
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_20', admin_trans('lottery.burst_trigger.20_label'))->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['20'] ?? 0.01)
                    ->precision(2)
                    ->suffix('%')
                    ->help(admin_trans('lottery.burst_trigger.20_help'))
                    ->ignore()
                    ->span(24);
            });

            $form->layout('vertical');
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    $count = GameLottery::query()->where('status', 1)->whereNull('deleted_at')->count();
                    if ($count > 5) {
                        return message_error(admin_trans('lottery.rul.max_count_five'));
                    }
                }

                // 检查是否更新了中奖概率
                if ($form->isEdit()) {
                    $id = $form->driver()->get('id');
                    $oldModel = GameLottery::query()->find($id);
                    $newWinRatio = $form->input('win_ratio');

                    // 如果中奖概率发生变化，重置开奖统计
                    if ($oldModel && $oldModel->win_ratio != $newWinRatio) {
                        try {
                            $redis = \support\Redis::connection()->client();

                            // 删除总统计
                            $redis->del('game_lottery_stats:total:' . $id);
                            $redis->del('game_lottery_stats:win:' . $id);

                            // 删除所有日期的统计（使用 SCAN 避免阻塞 Redis）
                            $pattern = 'game_lottery_stats:daily:*:' . $id . ':*';
                            $cursor = 0;
                            $deletedCount = 0;

                            do {
                                // 使用 SCAN 分批查找，每次最多返回 100 个键
                                $result = $redis->scan($cursor, 'MATCH', $pattern, 'COUNT', 100);
                                $cursor = $result[0];
                                $keys = $result[1];

                                if (!empty($keys)) {
                                    $redis->del($keys);
                                    $deletedCount += count($keys);
                                }
                            } while ($cursor != 0);

                            if ($deletedCount > 0) {
                                \support\Log::info('清理日统计键', [
                                    'lottery_id' => $id,
                                    'deleted_count' => $deletedCount,
                                ]);
                            }

                            \support\Log::info(admin_trans('lottery.log.reset_stats'), [
                                'lottery_id' => $id,
                                'old_win_ratio' => $oldModel->win_ratio,
                                'new_win_ratio' => $newWinRatio,
                            ]);
                        } catch (\Exception $e) {
                            \support\Log::error(admin_trans('lottery.log.reset_stats_failed') . ': ' . $e->getMessage());
                        }
                    }
                }

                // 验证保底金额
                $autoRefillStatus = $form->input('auto_refill_status');
                $autoRefillAmount = $form->input('auto_refill_amount');
                $maxPoolAmount = $form->input('max_pool_amount');

                if ($autoRefillStatus == 1) {
                    if (empty($autoRefillAmount) || $autoRefillAmount <= 0) {
                        return message_error(admin_trans('lottery.form_validation.auto_refill_amount_required'));
                    }
                    if ($autoRefillAmount > $maxPoolAmount) {
                        return message_error(admin_trans('lottery.form_validation.auto_refill_amount_exceed_max'));
                    }
                }

                // 保存爆彩倍数配置为JSON
                $multiplierConfig = [
                    'final' => floatval($form->input('burst_multiplier_final')),
                    'stage_4' => floatval($form->input('burst_multiplier_stage_4')),
                    'stage_3' => floatval($form->input('burst_multiplier_stage_3')),
                    'stage_2' => floatval($form->input('burst_multiplier_stage_2')),
                    'initial' => floatval($form->input('burst_multiplier_initial')),
                ];
                $form->input('burst_multiplier_config', json_encode($multiplierConfig));

                // 保存爆彩触发概率配置为JSON
                $triggerConfig = [
                    '95' => floatval($form->input('burst_trigger_95')),
                    '90' => floatval($form->input('burst_trigger_90')),
                    '85' => floatval($form->input('burst_trigger_85')),
                    '80' => floatval($form->input('burst_trigger_80')),
                    '75' => floatval($form->input('burst_trigger_75')),
                    '70' => floatval($form->input('burst_trigger_70')),
                    '65' => floatval($form->input('burst_trigger_65')),
                    '60' => floatval($form->input('burst_trigger_60')),
                    '50' => floatval($form->input('burst_trigger_50')),
                    '40' => floatval($form->input('burst_trigger_40')),
                    '30' => floatval($form->input('burst_trigger_30')),
                    '20' => floatval($form->input('burst_trigger_20')),
                ];
                $form->input('burst_trigger_config', json_encode($triggerConfig));
            });
        })->labelWidth('150');
    }

    /**
     * 清理统计数据
     * @auth true
     * @return Notification
     */
    public function clearStats(): Notification
    {
        try {
            $redis = \support\Redis::connection()->client();
            $today = date('Y-m-d');

            // 获取所有启用的彩金ID
            $lotteries = GameLottery::query()
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();

            $clearedCount = 0;
            $details = [];

            foreach ($lotteries as $id) {
                $cleared = [];

                // 清理每日统计
                $dailyTotalKey = 'game_lottery_stats:daily:total:' . $id . ':' . $today;
                $dailyWinKey = 'game_lottery_stats:daily:win:' . $id . ':' . $today;

                if ($redis->exists($dailyTotalKey)) {
                    $redis->del($dailyTotalKey);
                    $cleared[] = 'daily_total';
                }

                if ($redis->exists($dailyWinKey)) {
                    $redis->del($dailyWinKey);
                    $cleared[] = 'daily_win';
                }

                // 清理总统计
                $totalKey = 'game_lottery_stats:total:' . $id;
                $winKey = 'game_lottery_stats:win:' . $id;

                if ($redis->exists($totalKey)) {
                    $redis->del($totalKey);
                    $cleared[] = 'total';
                }

                if ($redis->exists($winKey)) {
                    $redis->del($winKey);
                    $cleared[] = 'win';
                }

                if (!empty($cleared)) {
                    $clearedCount++;
                    $details[] = [
                        'lottery_id' => $id,
                        'cleared_keys' => $cleared,
                    ];
                }
            }

            Log::info(admin_trans('lottery.log.clear_stats_success'), [
                'cleared_count' => $clearedCount,
                'details' => $details
            ]);

            return notification_success(
                admin_trans('lottery.clear_stats_success_title'),
                admin_trans('lottery.clear_stats_success_message', null, ['{count}' => $clearedCount]) . "\n\n" . admin_trans('lottery.clear_stats_details')
            );

        } catch (\Exception $e) {
            Log::error(admin_trans('lottery.log.clear_stats_error'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return notification_error(admin_trans('lottery.clear_stats_error_title'), $e->getMessage());
        }
    }
}

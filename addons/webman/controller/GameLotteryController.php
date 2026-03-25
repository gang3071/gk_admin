<?php

namespace addons\webman\controller;

use addons\webman\model\GameLottery;
use addons\webman\model\Lottery;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\result\Notification;
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
            $grid->column('max_pool_amount', '最大彩池金额')->align('center');
            $grid->column('double_amount', admin_trans('lottery.double_amount'))->align('center');

            // 保底金额列
            $grid->column('auto_refill_amount', '保底金额')->display(function ($val, GameLottery $data) {
                if ($data->auto_refill_status == 1 && $val > 0) {
                    return Html::create()->content([
                        Html::div()
                            ->content(number_format($val, 2))
                            ->style(['color' => '#1890ff', 'font-weight' => 'bold']),
                        Html::div()
                            ->content('(已启用)')
                            ->style(['color' => '#52c41a', 'font-size' => '12px'])
                    ]);
                } else {
                    return Html::create()->content([
                        Html::div()
                            ->content('未启用')
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
            $grid->column('lottery_stats', '开奖统计')->display(function ($val, GameLottery $data) {
                try {
                    $redis = \support\Redis::connection()->client();
                    $today = date('Y-m-d');

                    // 获取总统计
                    $totalChecks = (int)$redis->get('game_lottery_stats:total:' . $data->id) ?: 0;
                    $totalWins = (int)$redis->get('game_lottery_stats:win:' . $data->id) ?: 0;

                    // 获取今日统计
                    $dailyChecks = (int)$redis->get('game_lottery_stats:daily:total:' . $data->id . ':' . $today) ?: 0;
                    $dailyWins = (int)$redis->get('game_lottery_stats:daily:win:' . $data->id . ':' . $today) ?: 0;

                    // 计算中奖率
                    $totalWinRate = $totalChecks > 0 ? round(($totalWins / $totalChecks) * 100, 4) : 0;
                    $dailyWinRate = $dailyChecks > 0 ? round(($dailyWins / $dailyChecks) * 100, 4) : 0;

                    return Html::create()->content([
                        // 总统计
                        Html::div()->content('总计: ' . $totalChecks . '次 / ' . $totalWins . '中 (' . $totalWinRate . '%)')
                            ->style(['font-size' => '12px', 'color' => '#1890ff']),
                        // 今日统计
                        Html::div()->content('今日: ' . $dailyChecks . '次 / ' . $dailyWins . '中 (' . $dailyWinRate . '%)')
                            ->style(['font-size' => '12px', 'color' => '#52c41a', 'margin-top' => '4px']),
                    ]);
                } catch (\Exception $e) {
                    return Html::create()->content([
                        Html::div()->content('统计数据获取失败')
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
                Button::create('清理统计数据')
                    ->icon(Icon::create('DeleteOutlined'))
                    ->type('danger')
                    ->confirm('确定要清理所有彩金的统计数据吗？\n\n这将重置：\n• 总检查次数\n• 总中奖次数\n• 今日检查次数\n• 今日中奖次数\n\n清理后统计将从0重新开始计算。', [$this, 'clearStats'])
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
                ->help('彩金名称，用于在游戏中展示。例如：青铜彩金、白银彩金、黄金彩金、钻石彩金')
                ->placeholder('请输入彩金名称，如：青铜彩金')
                ->required();
            $form->number('amount', '彩池金额')
                ->style(['width' => '100%'])
                ->min(0)
                ->max(10000000000)
                ->precision(2)
                ->help('当前彩金的独立彩池金额，玩家中奖时从此金额中扣除。例如：彩池有5000元，玩家中奖获得5000元，彩池清零')
                ->placeholder('请输入彩池金额，如：5000');
            $form->text('pool_ratio', admin_trans('lottery.pool_ratio'))
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->suffix('%')
                ->help('玩家每次下注时，有多少比例进入此彩池。例如：设置5%，玩家下注100元，则5元进入彩池')
                ->placeholder('请输入入池比例，如：5')
                ->required();
            $form->text('rate', admin_trans('lottery.fields.rate'))
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->suffix('%')
                ->help('中奖时派发彩池的百分比。例如：设置100%，中奖时派发全部彩池；设置50%，中奖时派发一半彩池')
                ->placeholder('请输入派发比例，如：100')
                ->required();
            $form->text('win_ratio', admin_trans('lottery.fields.win_ratio'))
                ->rulePattern('^(?:0(?:\.\d{1,9})?|1(?:\.0{1,9})?)$', admin_trans('validator.win_ratio'))
                ->help('玩家每次下注的中奖概率（0-1之间）。例如：0.001表示千分之一概率，0.0001表示万分之一概率，0.01表示百分之一概率')
                ->placeholder('请输入中奖概率，如：0.001')
                ->required();
            // 打码量配置
            $form->divider()->content('打码量配置');
            $form->number('base_bet_amount', admin_trans('lottery.base_bet_amount'))->style(['width' => '100%'])
                ->min(1)
                ->max(100000000)
                ->precision(2)
                ->default(100)
                ->rule([
                    'required' => '中奖所需打码量不能为空',
                    'min:1' => admin_trans('lottery.rul.base_bet_amount_0'),
                    'max:100000000' => admin_trans('lottery.rul.base_bet_amount_100000000'),
                ])
                ->help('玩家必须累计达到此打码量才能中奖。例如：设置1000，玩家需要累计下注1000元以上才有资格中奖，防止新玩家立即中大奖')
                ->placeholder('请输入中奖所需打码量，如：1000')
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
                    ->help('当前彩池金额，玩家中奖时从此金额中扣除。例如：彩池有5000元，玩家中奖获得5000元，彩池清零')
                    ->placeholder('请输入当前彩池金额，如：5000')->span(11);
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
                    ->help('彩池达到此金额时，中奖概率翻倍。例如：设置3000元，当彩池≥3000元时，玩家中奖概率从0.001提升到0.002')
                    ->placeholder('请输入双倍开启金额，如：3000')->span(11);
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
                    ->help('单次中奖最多能获得的金额。例如：设置5000元，玩家单次中奖最多获得5000元，即使彩池有10000元')
                    ->placeholder('请输入最大派彩金额，如：5000')->span(11);
                $form->switch('max_status', admin_trans('lottery.max_status'))->default(1)
                    ->style(['margin-left' => '10px'])
                    ->span(11);
            });
            $form->row(function (Form $form) {
                $form->number('max_pool_amount', '最大彩池金额')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100000000)
                    ->precision(0)
                    ->rule([
                        'required' => '最大彩池金额不能为空',
                        'min:1' => '最大彩池金额不能小于1',
                        'max:100000000' => '最大彩池金额不能超过100000000',
                    ])
                    ->help('彩池累积的上限金额，达到后停止累积并触发爆彩。例如：设置10000元，彩池累积到10000元后不再增长，开始触发爆彩概率检查。此字段必须设置')
                    ->placeholder('请输入最大彩池金额，如：10000')
                    ->required()
                    ->span(24);
            });

            // 保底金额配置
            $form->divider()->content('保底金额配置');
            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #f0f5ff; border-left: 4px solid #597ef7;">
                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                    <strong>说明：</strong>保底金额是彩池的最低维持金额，确保彩池始终有足够的资金进行派彩。<br>
                    <strong>工作原理：</strong><br>
                    • 彩池累加：如果彩池不足派彩金额，自动补充到保底金额<br>
                    • 派彩后：如果彩池低于保底金额，自动补充到保底金额<br>
                    <strong>举例：</strong>保底金额设置为5000元：<br>
                    • 彩池3000元，需派彩4000元 → 先补充到5000元，再派彩4000元，剩余1000元 → 再补充到5000元<br>
                    • 彩池6000元，派彩2000元后剩余4000元 → 自动补充到5000元<br>
                    <strong>建议：</strong>保底金额应设置为最大派彩金额的1-2倍，确保随时有足够资金派彩
                </p>
            </div>');
            $form->row(function (Form $form) {
                $form->switch('auto_refill_status', '启用保底金额')
                    ->default(0)
                    ->help('启用后，系统会自动维持彩池在保底金额以上')
                    ->span(12);
                $form->number('auto_refill_amount', '保底金额')
                    ->style(['width' => '100%'])
                    ->min(0)->max(10000000000)->precision(2)->default(0)
                    ->help('彩池的最低维持金额。建议设置为最大派彩金额的1-2倍')
                    ->placeholder('请输入保底金额，如：5000')
                    ->span(12);
            });

            // 爆彩配置区域
            $form->divider()->content('爆彩配置');

            // 隐藏域用于保存JSON配置
            $form->hidden('burst_multiplier_config');
            $form->hidden('burst_trigger_config');

            // 爆彩基础设置
            $form->row(function (Form $form) {
                $form->switch('burst_status', '爆彩状态')->default(1)
                    ->help('启用后，当彩池达到一定比例时会概率性触发爆彩活动，大幅提升中奖概率')
                    ->span(8);
                $form->number('burst_duration', '爆彩持续时长（分钟）')->style(['width' => '100%'])
                    ->min(1)
                    ->max(120)
                    ->default(15)
                    ->precision(0)
                    ->help('爆彩触发后持续的时长。例如：设置15分钟，触发后15分钟内玩家中奖概率大幅提升，时间越少概率越高')
                    ->placeholder('请输入持续时长，如：15')
                    ->span(16);
            });

            // 爆彩倍数配置表格
            $form->divider()->content('爆彩倍数配置');
            $form->html('<div style="padding: 10px; margin-bottom: 15px; background: #e6f7ff; border-left: 4px solid #1890ff;">
                <p style="margin: 0; font-size: 13px; color: #333; line-height: 1.6;">
                    <strong>说明：</strong>爆彩期间，玩家中奖概率会根据剩余时间自动提升。倍数越高，中奖越容易。<br>
                    <strong>举例：</strong>假设正常中奖概率为 0.1%，爆彩持续15分钟：<br>
                    • 前10.5分钟（剩余70%-100%）：中奖概率 = 0.1% × 5 = 0.5%<br>
                    • 第10.5-12分钟（剩余30%-70%）：中奖概率逐步提升到 0.1% × 10 = 1%<br>
                    • 最后1.5分钟（剩余≤10%）：中奖概率 = 0.1% × 50 = 5%（最高）
                </p>
            </div>');
            $form->html('<div style="padding: 10px 0; margin-bottom: 10px;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #e8e8e8;">
                    <thead style="background-color: #fafafa;">
                        <tr>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 40%;">剩余时间百分比</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 30%;">概率倍数</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 30%;">说明</th>
                        </tr>
                    </thead>
                </table>
            </div>');

            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_final', '剩余时间 ≤ 10%')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(50)
                    ->value($burstMultiplierConfig['final'] ?? 50)
                    ->precision(1)
                    ->help('最后冲刺阶段，中奖概率最高。例：15分钟爆彩的最后1.5分钟，50倍意味着中奖概率提升50倍')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_4', '剩余时间 10% - 30%')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(25)
                    ->value($burstMultiplierConfig['stage_4'] ?? 25)
                    ->precision(1)
                    ->help('第四阶段，概率较高。例：15分钟爆彩的第12-13.5分钟，25倍提升')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_3', '剩余时间 30% - 50%')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(15)
                    ->value($burstMultiplierConfig['stage_3'] ?? 15)
                    ->precision(1)
                    ->help('第三阶段，概率中等。例：15分钟爆彩的第7.5-10.5分钟，15倍提升')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_stage_2', '剩余时间 50% - 70%')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(10)
                    ->value($burstMultiplierConfig['stage_2'] ?? 10)
                    ->precision(1)
                    ->help('第二阶段，概率适中。例：15分钟爆彩的第4.5-7.5分钟，10倍提升')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstMultiplierConfig) {
                $form->number('burst_multiplier_initial', '剩余时间 70% - 100%')->style(['width' => '100%'])
                    ->min(1)
                    ->max(100)
                    ->default(5)
                    ->value($burstMultiplierConfig['initial'] ?? 5)
                    ->precision(1)
                    ->help('初始阶段，概率较低。例：15分钟爆彩的前10.5分钟，5倍提升，让玩家感受到爆彩氛围')
                    ->ignore()
                    ->span(24);
            });

            // 爆彩触发概率配置表格
            $form->divider()->content('爆彩触发概率配置');
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
            $form->html('<div style="padding: 10px 0; margin-bottom: 10px;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #e8e8e8;">
                    <thead style="background-color: #fafafa;">
                        <tr>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 40%;">彩池占比</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 30%;">触发概率（%）</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e8e8e8; font-weight: bold; width: 30%;">说明</th>
                        </tr>
                    </thead>
                </table>
            </div>');

            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_95', '彩池占比 ≥ 95%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(10)
                    ->value($burstTriggerConfig['95'] ?? 10)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池极满状态，触发概率最高。例：最大彩池10000元，当前9500元以上，每次下注有10%概率触发爆彩')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_90', '彩池占比 90% - 95%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(6)
                    ->value($burstTriggerConfig['90'] ?? 6)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池很满，触发概率高。例：彩池9000-9500元，每次下注有6%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_85', '彩池占比 85% - 90%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['85'] ?? 4)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池较满，触发概率较高。例：彩池8500-9000元，每次下注有4%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_80', '彩池占比 80% - 85%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['80'] ?? 2.5)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池适中偏满，触发概率中等。例：彩池8000-8500元，每次下注有2.5%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_75', '彩池占比 75% - 80%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['75'] ?? 1.5)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池适中，触发概率适中。例：彩池7500-8000元，每次下注有1.5%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_70', '彩池占比 70% - 75%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['70'] ?? 0.8)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池偏满，触发概率较低。例：彩池7000-7500元，每次下注有0.8%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_65', '彩池占比 65% - 70%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['65'] ?? 0.4)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池中等，触发概率低。例：彩池6500-7000元，每次下注有0.4%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_60', '彩池占比 60% - 65%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['60'] ?? 0.2)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池中等，触发概率很低。例：彩池6000-6500元，每次下注有0.2%概率触发')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_50', '彩池占比 50% - 60%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['50'] ?? 0.1)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池半满，触发概率极低。例：彩池5000-6000元，每次下注有0.1%概率触发（1000次约1次）')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_40', '彩池占比 40% - 50%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['40'] ?? 0.05)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池偏低，触发概率极低。例：彩池4000-5000元，每次下注有0.05%概率触发（2000次约1次）')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_30', '彩池占比 30% - 40%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default(0.02)
                    ->value($burstTriggerConfig['30'] ?? 0.02)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池较低，触发概率微乎其微。例：彩池3000-4000元，每次下注有0.02%概率触发（5000次约1次）')
                    ->ignore()
                    ->span(24);
            });
            $form->row(function (Form $form) use ($burstTriggerConfig) {
                $form->number('burst_trigger_20', '彩池占比 20% - 30%')->style(['width' => '100%'])
                    ->min(0)
                    ->max(100)
                    ->default($burstTriggerConfig['20'] ?? 0.01)
                    ->precision(2)
                    ->suffix('%')
                    ->help('彩池很低，触发概率几乎为零。例：彩池2000-3000元，每次下注有0.01%概率触发（10000次约1次）。建议：低于20%不触发')
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

                            // 删除所有日期的统计（删除匹配模式的所有键）
                            $dailyKeys = $redis->keys('game_lottery_stats:daily:*:' . $id . ':*');
                            if (!empty($dailyKeys)) {
                                $redis->del($dailyKeys);
                            }

                            \support\Log::info('重置彩金开奖统计', [
                                'lottery_id' => $id,
                                'old_win_ratio' => $oldModel->win_ratio,
                                'new_win_ratio' => $newWinRatio,
                            ]);
                        } catch (\Exception $e) {
                            \support\Log::error('重置彩金开奖统计失败: ' . $e->getMessage());
                        }
                    }
                }

                // 验证保底金额
                $autoRefillStatus = $form->input('auto_refill_status');
                $autoRefillAmount = $form->input('auto_refill_amount');
                $maxPoolAmount = $form->input('max_pool_amount');

                if ($autoRefillStatus == 1) {
                    if (empty($autoRefillAmount) || $autoRefillAmount <= 0) {
                        return message_error('启用保底金额后，保底金额必须大于0');
                    }
                    if ($autoRefillAmount > $maxPoolAmount) {
                        return message_error('保底金额不能大于最大彩池金额');
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

            Log::info('清理彩金统计数据成功', [
                'cleared_count' => $clearedCount,
                'details' => $details
            ]);

            return notification_success(
                '清理成功',
                "成功清理了 {$clearedCount} 个彩金的统计数据\n\n已重置：\n• 总检查次数\n• 总中奖次数\n• 今日检查次数\n• 今日中奖次数"
            );

        } catch (\Exception $e) {
            Log::error('清理彩金统计数据异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return notification_error('清理失败', $e->getMessage());
        }
    }
}

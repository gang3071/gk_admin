<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\DepositBonusTier;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 充值满赠活动管理 - 代理后台
 * @group agent
 */
class AgentDepositBonusActivityController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_activity_model');
    }

    /**
     * 充值满赠活动列表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_activity.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前代理的活动
            $currentAdmin = Admin::user();
            $grid->model()->where('agent_id', $currentAdmin->id)
                ->with(['tiers'])
                ->orderBy('created_at', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', strtotime($exAdminFilter['created_at_start']));
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', strtotime($exAdminFilter['created_at_end']));
            }

            $grid->column('id', admin_trans('deposit_bonus_activity.fields.id'))->align('center');
            $grid->column('activity_name', admin_trans('deposit_bonus_activity.fields.activity_name'))
                ->display(function ($val, DepositBonusActivity $data) {
                    return Html::create($val)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-AgentDepositBonusActivityController', 'detail'], ['id' => $data->id]);
                })->align('center');

            $grid->column('time_range', admin_trans('deposit_bonus_activity.fields.time_range'))
                ->display(function ($val, DepositBonusActivity $data) {
                    $start = date('Y-m-d H:i', $data->start_time);
                    $end = date('Y-m-d H:i', $data->end_time);
                    return "{$start}<br/>~<br/>{$end}";
                })->align('center');

            $grid->column('bet_multiple', admin_trans('deposit_bonus_activity.fields.bet_multiple'))
                ->display(function ($val) {
                    return $val . 'x';
                })->align('center');

            $grid->column('valid_days', admin_trans('deposit_bonus_activity.fields.valid_days'))
                ->display(function ($val) {
                    return $val . admin_trans('deposit_bonus_activity.days');
                })->align('center');

            $grid->column('tier_count', admin_trans('deposit_bonus_activity.fields.tier_count'))
                ->display(function ($val, DepositBonusActivity $data) {
                    $count = $data->tiers->count();
                    return Tag::create($count)->color('blue');
                })->align('center');

            $grid->column('status', admin_trans('deposit_bonus_activity.fields.status'))
                ->display(function ($val) {
                    switch ($val) {
                        case 0:
                            return Tag::create(admin_trans('deposit_bonus_activity.status_disabled'))->color('red');
                        case 1:
                            return Tag::create(admin_trans('deposit_bonus_activity.status_enabled'))->color('green');
                        default:
                            return '';
                    }
                })->align('center');

            $grid->column('created_at', admin_trans('deposit_bonus_activity.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('activity_name')->placeholder(admin_trans('deposit_bonus_activity.fields.activity_name'));
                $filter->eq()->select('status')
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('deposit_bonus_activity.fields.status'))
                    ->options([
                        1 => admin_trans('deposit_bonus_activity.status_enabled'),
                        0 => admin_trans('deposit_bonus_activity.status_disabled')
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('deposit_bonus_activity.created_at_start'),
                    admin_trans('deposit_bonus_activity.created_at_end')
                ]);
            });

            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
        });
    }

    /**
     * 充值满赠活动表单
     * @group agent
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('deposit_bonus_activity.title'));

            $form->text('activity_name', admin_trans('deposit_bonus_activity.fields.activity_name'))
                ->required()
                ->maxlength(100)
                ->help(admin_trans('deposit_bonus_activity.help.activity_name'));

            $form->dateTimeRange('start_time', 'end_time', admin_trans('deposit_bonus_activity.fields.time_range'))
                ->required()
                ->help(admin_trans('deposit_bonus_activity.help.time_range'));

            $form->number('bet_multiple', admin_trans('deposit_bonus_activity.fields.bet_multiple'))
                ->default(5)
                ->min(0)
                ->step(0.1)
                ->required()
                ->help(admin_trans('deposit_bonus_activity.help.bet_multiple'));

            $form->number('valid_days', admin_trans('deposit_bonus_activity.fields.valid_days'))
                ->default(7)
                ->min(1)
                ->required()
                ->help(admin_trans('deposit_bonus_activity.help.valid_days'));

            $form->radio('unlock_type', admin_trans('deposit_bonus_activity.fields.unlock_type'))
                ->default(DepositBonusActivity::UNLOCK_TYPE_BET)
                ->options([
                    DepositBonusActivity::UNLOCK_TYPE_BET => admin_trans('deposit_bonus_activity.unlock_type_bet'),
                    DepositBonusActivity::UNLOCK_TYPE_NO_MACHINE => admin_trans('deposit_bonus_activity.unlock_type_no_machine'),
                ])
                ->help(admin_trans('deposit_bonus_activity.help.unlock_type'));

            $form->number('limit_per_player', admin_trans('deposit_bonus_activity.fields.limit_per_player'))
                ->default(0)
                ->min(0)
                ->help(admin_trans('deposit_bonus_activity.help.limit_per_player'));

            $form->select('limit_period', admin_trans('deposit_bonus_activity.fields.limit_period'))
                ->default('day')
                ->options([
                    'day' => admin_trans('deposit_bonus_activity.period_day'),
                    'week' => admin_trans('deposit_bonus_activity.period_week'),
                    'month' => admin_trans('deposit_bonus_activity.period_month'),
                ])
                ->help(admin_trans('deposit_bonus_activity.help.limit_period'));

            $form->textarea('description', admin_trans('deposit_bonus_activity.fields.description'))
                ->rows(4)
                ->help(admin_trans('deposit_bonus_activity.help.description'));

            // 档位配置
            $form->table('tiers', admin_trans('deposit_bonus_activity.fields.tiers'), function (Form $form) {
                $form->number('deposit_amount', admin_trans('deposit_bonus_activity.tier.deposit_amount'))
                    ->required()
                    ->min(0)
                    ->step(0.01);
                $form->number('bonus_amount', admin_trans('deposit_bonus_activity.tier.bonus_amount'))
                    ->required()
                    ->min(0)
                    ->step(0.01);
                $form->number('sort_order', admin_trans('deposit_bonus_activity.tier.sort_order'))
                    ->default(0);
            })->help(admin_trans('deposit_bonus_activity.help.tiers'));

            $form->switch('status', admin_trans('deposit_bonus_activity.fields.status'))
                ->default(DepositBonusActivity::STATUS_ENABLED);

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                try {
                    Db::beginTransaction();

                    $currentAdmin = Admin::user();

                    if (!$form->isEdit()) {
                        // 新增
                        $activity = new DepositBonusActivity();
                        $activity->created_by = $currentAdmin->id;
                        $activity->agent_id = $currentAdmin->id;
                        $activity->store_id = $currentAdmin->department_id;
                        $activity->created_at = time();
                    } else {
                        // 编辑
                        $id = $form->driver()->get('id');
                        $activity = DepositBonusActivity::where('id', $id)
                            ->where('agent_id', $currentAdmin->id)
                            ->first();
                        if (!$activity) {
                            Db::rollBack();
                            return message_error(admin_trans('deposit_bonus_activity.not_found'));
                        }
                    }

                    // 保存活动基本信息
                    $activity->activity_name = $form->input('activity_name');
                    $activity->activity_type = DepositBonusActivity::TYPE_DEPOSIT_BONUS;
                    $activity->start_time = strtotime($form->input('start_time'));
                    $activity->end_time = strtotime($form->input('end_time'));
                    $activity->bet_multiple = $form->input('bet_multiple');
                    $activity->valid_days = $form->input('valid_days');
                    $activity->unlock_type = $form->input('unlock_type') ?? DepositBonusActivity::UNLOCK_TYPE_BET;
                    $activity->limit_per_player = $form->input('limit_per_player') ?? 0;
                    $activity->limit_period = $form->input('limit_period') ?? 'day';
                    $activity->description = $form->input('description') ?? '';
                    $activity->status = $form->input('status') ?? DepositBonusActivity::STATUS_ENABLED;
                    $activity->updated_at = time();
                    $activity->save();

                    // 保存档位配置
                    $tiers = $form->input('tiers', []);
                    if (empty($tiers)) {
                        Db::rollBack();
                        return message_error(admin_trans('deposit_bonus_activity.tier_required'));
                    }

                    // 删除旧档位（编辑时）
                    if ($form->isEdit()) {
                        DepositBonusTier::where('activity_id', $activity->id)->delete();
                    }

                    // 创建新档位
                    foreach ($tiers as $tierData) {
                        $tier = new DepositBonusTier();
                        $tier->activity_id = $activity->id;
                        $tier->deposit_amount = $tierData['deposit_amount'];
                        $tier->bonus_amount = $tierData['bonus_amount'];
                        $tier->bonus_ratio = round(($tierData['bonus_amount'] / $tierData['deposit_amount']) * 100, 2);
                        $tier->sort_order = $tierData['sort_order'] ?? 0;
                        $tier->status = DepositBonusTier::STATUS_ENABLED;
                        $tier->created_at = time();
                        $tier->save();
                    }

                    Db::commit();
                    return message_success(admin_trans('form.save_success'));

                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('form.save_fail') . ': ' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 活动详情
     * @group agent
     * @auth true
     * @param int $id
     * @return Detail
     */
    public function detail(int $id): Detail
    {
        $currentAdmin = Admin::user();
        $data = DepositBonusActivity::where('id', $id)
            ->where('agent_id', $currentAdmin->id)
            ->with(['tiers'])
            ->first();

        return Detail::create($data, function (Detail $detail) use ($data) {
            $detail->item('id', admin_trans('deposit_bonus_activity.fields.id'));
            $detail->item('activity_name', admin_trans('deposit_bonus_activity.fields.activity_name'));
            $detail->item('time_range', admin_trans('deposit_bonus_activity.fields.time_range'))
                ->display(function ($val, DepositBonusActivity $data) {
                    $start = date('Y-m-d H:i:s', $data->start_time);
                    $end = date('Y-m-d H:i:s', $data->end_time);
                    return "{$start} ~ {$end}";
                });
            $detail->item('bet_multiple', admin_trans('deposit_bonus_activity.fields.bet_multiple'))
                ->display(function ($val) {
                    return $val . 'x';
                });
            $detail->item('valid_days', admin_trans('deposit_bonus_activity.fields.valid_days'))
                ->display(function ($val) {
                    return $val . admin_trans('deposit_bonus_activity.days');
                });
            $detail->item('unlock_type', admin_trans('deposit_bonus_activity.fields.unlock_type'))
                ->display(function ($val) {
                    return $val == DepositBonusActivity::UNLOCK_TYPE_BET
                        ? admin_trans('deposit_bonus_activity.unlock_type_bet')
                        : admin_trans('deposit_bonus_activity.unlock_type_no_machine');
                });
            $detail->item('limit_info', admin_trans('deposit_bonus_activity.fields.limit_info'))
                ->display(function ($val, DepositBonusActivity $data) {
                    if ($data->limit_per_player == 0) {
                        return admin_trans('deposit_bonus_activity.no_limit');
                    }
                    $period = [
                        'day' => admin_trans('deposit_bonus_activity.period_day'),
                        'week' => admin_trans('deposit_bonus_activity.period_week'),
                        'month' => admin_trans('deposit_bonus_activity.period_month'),
                    ];
                    return $data->limit_per_player . admin_trans('deposit_bonus_activity.times') . '/' . ($period[$data->limit_period] ?? '');
                });
            $detail->item('description', admin_trans('deposit_bonus_activity.fields.description'));
            $detail->item('tiers', admin_trans('deposit_bonus_activity.fields.tiers'))
                ->display(function ($val, DepositBonusActivity $data) {
                    $html = '<table style="width:100%;border-collapse:collapse;">';
                    $html .= '<tr style="background:#f5f5f5;">';
                    $html .= '<th style="border:1px solid #ddd;padding:8px;">' . admin_trans('deposit_bonus_activity.tier.deposit_amount') . '</th>';
                    $html .= '<th style="border:1px solid #ddd;padding:8px;">' . admin_trans('deposit_bonus_activity.tier.bonus_amount') . '</th>';
                    $html .= '<th style="border:1px solid #ddd;padding:8px;">' . admin_trans('deposit_bonus_activity.tier.bonus_ratio') . '</th>';
                    $html .= '</tr>';
                    foreach ($data->tiers as $tier) {
                        $html .= '<tr>';
                        $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $tier->deposit_amount . '</td>';
                        $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $tier->bonus_amount . '</td>';
                        $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $tier->bonus_ratio . '%</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</table>';
                    return Html::create()->content($html);
                });
            $detail->item('status', admin_trans('deposit_bonus_activity.fields.status'))
                ->display(function ($val) {
                    return $val == 1
                        ? Tag::create(admin_trans('deposit_bonus_activity.status_enabled'))->color('green')
                        : Tag::create(admin_trans('deposit_bonus_activity.status_disabled'))->color('red');
                });
            $detail->item('created_at', admin_trans('deposit_bonus_activity.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });
        })->bordered()->layout('vertical');
    }
}

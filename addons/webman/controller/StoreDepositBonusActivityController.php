<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\AdminUser;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 充值满赠活动管理 - 店机后台
 * @group store
 */
class StoreDepositBonusActivityController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_activity_model');
    }

    /**
     * 充值满赠活动列表（店家仅查看）
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_activity.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 店家只能查看上级代理创建的活动
            $currentAdmin = Admin::user();

            // 获取上级代理ID
            $parentAgent = AdminUser::where('id', $currentAdmin->parent_admin_id)
                ->where('type', AdminUser::TYPE_AGENT)
                ->first();

            if ($parentAgent) {
                $grid->model()->where('agent_id', $parentAgent->id)
                    ->with(['tiers'])
                    ->orderBy('created_at', 'desc');
            } else {
                // 如果没有上级代理，显示空列表
                $grid->model()->where('id', 0);
            }

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
                    ])->drawer(['addons-webman-controller-StoreDepositBonusActivityController', 'detail'], ['id' => $data->id]);
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
            $grid->hideAdd(); // 店家不能创建活动
            $grid->hideDelete();
            $grid->hideTrashed();
        });
    }

    /**
     * 活动详情
     * @group store
     * @auth true
     * @param int $id
     * @return Detail
     */
    public function detail(int $id): Detail
    {
        $currentAdmin = Admin::user();

        // 获取上级代理ID
        $parentAgent = AdminUser::where('id', $currentAdmin->parent_admin_id)
            ->where('type', AdminUser::TYPE_AGENT)
            ->first();

        $data = DepositBonusActivity::where('id', $id)
            ->where('agent_id', $parentAgent ? $parentAgent->id : 0)
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

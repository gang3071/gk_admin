<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\NationalPromoter;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use Carbon\Carbon;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\support\Request;
use support\Db;
use support\Log;

/**
 * 全民代理报表
 * @group channel
 */
class ChannelNationalPromoterReportController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.national_promoter_model');
    }

    /**
     * 全民代理报表
     * @auth true
     * @group channel
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->model()->with([
                'player',
                'last_national_profit_record',
                'level_list',
                'player.recommend_player',
                'player.player_extend'
            ])->where('invite_num', '>', 0)
                ->whereHas('player', function ($query) {
                    $query->where('department_id', Admin::user()->department_id);
                })
                ->orderBy('created_at', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('national_promoter.report_title'));
            $grid->tools([
                Button::create(admin_trans('player_promoter.bath_settlement'))->danger()
                    ->confirm(admin_trans('reverse_water.profit_settlement_confirm'), [$this, 'profitSettlement'])
            ]);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('national_promoter.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, NationalPromoter $data) {
                    return Html::create()->content([
                        Html::div()->content($val)
                    ]);
                })
                ->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, NationalPromoter $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->align('center');
            $grid->column('player.recommend_player.uuid',
                admin_trans('player.fields.recommend_promoter_name'))->align('center')->width(80)->ellipsis(true);
            $grid->column('level_list.damage_rebate_ratio',
                admin_trans('national_promoter.level_list.damage_rebate_ratio'))->append('%')->align('center')->ellipsis(true);
            $grid->column('invite_num',
                admin_trans('national_promoter.fields.invite_num'))->display(function (
                $val,
                NationalPromoter $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    ChannelPlayerController::class,
                    'index'
                ], ['id' => $data->player->id])->width('70%')->title($data->player->name . ' ' . $data->player->uuid);
            })->align('center')->sortable()->ellipsis(true);
            $grid->column('pending_amount',
                admin_trans('national_promoter.fields.pending_amount'))->align('center')->sortable()->ellipsis(true);
            $grid->column('settlement_amount',
                admin_trans('national_promoter.fields.settlement_amount'))->align('center')->sortable()->ellipsis(true);
            $grid->column('last_national_profit_record.amount',
                admin_trans('national_promoter.fields.last_national_profit_money'))->align('center')->ellipsis(true);
            $grid->column('last_national_profit_record.created_at',
                admin_trans('national_promoter.fields.last_national_profit_created_at'))->align('center')->ellipsis(true);
            $grid->column('created_at', admin_trans('national_promoter.fields.created_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, NationalPromoter $data) {
                $actions->hideDel();
                $actions->prepend(Button::create(admin_trans('national_promoter.national_profit_record'))
                    ->icon(Icon::create('UnorderedListOutlined'))
                    ->type('primary')
                    ->size('small')
                    ->modal([ChannelNationalPromoterController::class, 'record'],
                        ['id' => $data->player->id])->width('70%'));
                if ($data->pending_amount > 0) {
                    $actions->prepend(Button::create(admin_trans('national_promoter.settlement'))->danger()
                        ->confirm(admin_trans('national_promoter.settlement_confirm', null, [
                            '{uuid}' => $data->player->uuid,
                            '{amount}' => $data->pending_amount
                        ]), [$this, 'settlement'], ['id' => $data->uid]));
                }
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player.recommend_player.uuid')->placeholder(admin_trans('national_promoter.fields.recommend_promoter_uuid'));
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
            });
            $grid->expandFilter();
        });
    }

    /**
     * 全民代理批量结算
     * @group channel
     * @auth true
     */
    public function profitSettlement(): Notification
    {
        $departmentId = Admin::user()->department_id;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $list = NationalProfitRecord::query()
            ->select([
                'recommend_id',
                DB::raw('SUM(money) as money'),
                DB::raw('GROUP_CONCAT(id ORDER BY id) as record_ids'),
                DB::raw('max(id) as id'),
            ])->whereHas('player', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })->where('status', 0)
            ->where('type', 1)
            ->whereDate('created_at', '<=', $yesterday)
            ->groupBy('recommend_id')
            ->having('money', '>', 0)
            ->get();

        $date = Carbon::today()->format('Y-m-d');
        $playProfitIds = [];
        Db::beginTransaction();
        try {
            foreach ($list as $item) {
                $recordIds = explode(',', $item->record_ids ?? '');
                $validIds = array_filter($recordIds, function($id) {
                    return is_numeric($id) && $id !== '';
                });
                $playProfitIds = array_merge($playProfitIds, $validIds);
                /** @var Player $player */
                $player = Player::query()->find($item->recommend_id);
                $amountBefore = $player->machine_wallet->money;
                $player->machine_wallet->money = bcadd($player->machine_wallet->money, $item->money, 2);

                // 寫入金流明細
                $playerDeliveryRecord = new PlayerDeliveryRecord;
                $playerDeliveryRecord->player_id = $item->recommend_id;
                $playerDeliveryRecord->department_id = $player->department_id;
                $playerDeliveryRecord->target = $item->getTable();
                $playerDeliveryRecord->target_id = $item->id;
                $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_DAMAGE_REBATE;
                $playerDeliveryRecord->source = 'national_promoter';
                $playerDeliveryRecord->amount = $item->money;
                $playerDeliveryRecord->amount_before = $amountBefore;
                $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                $playerDeliveryRecord->tradeno = '';
                $playerDeliveryRecord->remark = '';
                $playerDeliveryRecord->save();
                $player->national_promoter->pending_amount = bcsub($player->national_promoter->pending_amount, $item->money, 2);
                $player->national_promoter->settlement_amount = bcadd($player->national_promoter->settlement_amount, $item->money, 2);
                $player->push();
            }
            NationalProfitRecord::query()
                ->whereIn('id', $playProfitIds)
                ->update(['status' => 1, 'settled_date' => $date]);
            Db::commit();
        } catch (\Exception $e) {
            Log::error(admin_trans('national_promoter.log.settlement_failed'), [$e->getMessage()]);
            Db::rollback();
        }
        return notification_success(admin_trans('admin.success'),
            admin_trans('promoter_profit_settlement_record.success'), ['duration' => 5])->refresh();
    }

    /**
     * 全民代理结算
     * @group channel
     * @auth true
     */
    public function settlement($id): Notification
    {
        $departmentId = Admin::user()->department_id;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $data = NationalProfitRecord::query()
            ->select([
                DB::raw('SUM(money) as money'),
                DB::raw('GROUP_CONCAT(id ORDER BY id) as record_ids'),
                DB::raw('max(id) as id'),
            ])->whereHas('player', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })->where('status', 0)
            ->where('type', 1)
            ->where('recommend_id', $id)
            ->where('money', '>', 0)
            ->whereDate('created_at', '<=', $yesterday)
            ->first();

        $date = Carbon::today()->format('Y-m-d');
        $playProfitIds = [];
        Db::beginTransaction();
        try {
            $recordIds = explode(',', $data->record_ids ?? '');
            $validIds = array_filter($recordIds, function($id) {
                return is_numeric($id) && $id !== '';
            });
            $playProfitIds = array_merge($playProfitIds, $validIds);
            $player = Player::query()->find($id);
            $amountBefore = $player->machine_wallet->money;
            $player->machine_wallet->money = bcadd($player->machine_wallet->money, $data->money, 2);

            // 寫入金流明細
            $playerDeliveryRecord = new PlayerDeliveryRecord;
            $playerDeliveryRecord->player_id = $id;
            $playerDeliveryRecord->department_id = $player->department_id;
            $playerDeliveryRecord->target = $data->getTable();
            $playerDeliveryRecord->target_id = $data->id;
            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_DAMAGE_REBATE;
            $playerDeliveryRecord->source = 'national_promoter';
            $playerDeliveryRecord->amount = $data->money;
            $playerDeliveryRecord->amount_before = $amountBefore;
            $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
            $playerDeliveryRecord->tradeno = '';
            $playerDeliveryRecord->remark = '';
            $playerDeliveryRecord->save();
            $player->national_promoter->pending_amount = bcsub($player->national_promoter->pending_amount, $data->money, 2);
            $player->national_promoter->settlement_amount = bcadd($player->national_promoter->settlement_amount, $data->money, 2);
            $player->push();

            NationalProfitRecord::query()
                ->whereIn('id', $playProfitIds)
                ->update(['status' => 1, 'settled_date' => $date]);
            Db::commit();
        } catch (\Exception $e) {
            Log::error(admin_trans('national_promoter.log.settlement_failed'), [$e->getMessage()]);
            Db::rollback();
        }
        return notification_success(admin_trans('admin.success'),
            admin_trans('promoter_profit_settlement_record.success'), ['duration' => 5])->refresh();
    }
}

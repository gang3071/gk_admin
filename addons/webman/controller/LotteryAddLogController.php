<?php

namespace addons\webman\controller;

use addons\webman\model\mongo\LotteryPoolAddLog;
use DateTime;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Request;
use Exception;

/**
 * 彩金累积日志
 */
class LotteryAddLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.lottery_pool_add_log_model');

    }

    /**
     * 彩金列表
     * @auth true
     * @return Grid
     * @throws Exception
     */
    public function index(): Grid
    {
        $request = Request::input();
        $lotteryPoolAddLog = LotteryPoolAddLog::query();
        if (!empty($request['ex_admin_filter']['machine_name'])) {
            $lotteryPoolAddLog->where('machine_name', 'like', '%' . $request['ex_admin_filter']['machine_name'] . '%');
        }
        if (!empty($request['ex_admin_filter']['machine_code'])) {
            $lotteryPoolAddLog->where('machine_code', 'like', '%' . $request['ex_admin_filter']['machine_code'] . '%');
        }
        if (!empty($request['ex_admin_filter']['player_uuid'])) {
            $lotteryPoolAddLog->where('uuid', 'like', '%' . $request['ex_admin_filter']['player_uuid'] . '%');
        }
        if (!empty($request['quickSearch'])) {
            $lotteryPoolAddLog->where([
                ['machine_name', 'like', '%' . $request['quickSearch'] . '%', 'or'],
                ['machine_code', 'like', '%' . $request['quickSearch'] . '%', 'or'],
                ['player_uuid', 'like', '%' . $request['quickSearch'] . '%', 'or'],
            ]);
        }
        if (!empty($request['ex_admin_filter']['created_at_start'])) {
            $lotteryPoolAddLog->where('created_at', '>=', new DateTime($request['ex_admin_filter']['created_at_start']));
        }
        if (!empty($request['ex_admin_filter']['created_at_end'])) {
            $lotteryPoolAddLog->where('created_at', '<=', new DateTime($request['ex_admin_filter']['created_at_end']));
        }
        $countModel = clone $lotteryPoolAddLog;
        $total = $countModel->count('*');
        $data = $lotteryPoolAddLog
            ->forPage($request['ex_admin_page'] ?? 1, $request['ex_admin_size'] ?? 20)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
        return Grid::create($data, function (Grid $grid) use ($total, $data) {
            $grid->title(admin_trans('lottery_pool_add_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('machine_name', admin_trans('lottery_pool_add_log.fields.machine_name'))->align('center');
            $grid->column('machine_code', admin_trans('lottery_pool_add_log.fields.machine_code'))->align('center');
            $grid->column('uuid', admin_trans('lottery_pool_add_log.fields.uuid'))->align('center');
            $grid->column('num', admin_trans('lottery_pool_add_log.fields.num'))->align('center');
            $grid->column('add_amount', admin_trans('lottery_pool_add_log.fields.add_amount'))->display(function ($val) {
                return (string)floatval($val);
            })->align('center');
            $grid->column('lottery_point', admin_trans('lottery_pool_add_log.fields.lottery_point'))->align('center');
            $grid->column('lottery_amount', admin_trans('lottery_pool_add_log.fields.lottery_amount'))->align('center');
            $grid->column('created_at', admin_trans('lottery_pool_add_log.fields.create_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine_name')->placeholder(admin_trans('lottery_pool_add_log.fields.machine_name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('lottery_pool_add_log.fields.machine_code'));
                $filter->like()->text('player_uuid')->placeholder(admin_trans('lottery_pool_add_log.fields.uuid'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('lottery_pool_add_log.created_at_start'), admin_trans('lottery_pool_add_log.created_at_end')]);
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $data);
        });
    }
}

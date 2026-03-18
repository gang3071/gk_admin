<?php

namespace addons\webman\controller;

use addons\webman\model\NationalPromoter;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 全民代理报表
 * @group channel
 */
class NationalPromoterReportController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.national_promoter_model');
    }
    
    /**
     * 全民代理报表
     * @auth true
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
            ])->where('invite_num', '>', 0)->orderBy('created_at', 'desc');
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
                    PlayerController::class,
                    'index'
                ], ['id' => $data->player->id])->width('70%')->title($data->player->name . ' ' . $data->player->uuid);
            })->align('center')->sortable()->ellipsis(true);
            $grid->column('pending_amount',
                admin_trans('national_promoter.fields.pending_amount'))->align('center')->sortable()->ellipsis(true);
            $grid->column('settlement_amount',
                admin_trans('national_promoter.fields.settlement_amount'))->align('center')->sortable()->ellipsis(true);
            $grid->column('last_national_profit_record.money',
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
                    ->modal([NationalPromoterController::class, 'record'], ['id' => $data->player->id])->width('70%'));
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
                $filter->eq()->select('player.department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
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
}

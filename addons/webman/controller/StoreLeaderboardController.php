<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Leaderboard;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;

/**
 * 排行榜
 */
class StoreLeaderboardController
{
    protected $model;
    protected $modelEntry;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.leaderboard_model');
        $this->modelEntry = plugin()->webman->config('database.leaderboard_entry_model');
    }

    /**
     * 主頁
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(
            Tabs::create()
                ->pane(admin_trans('leaderboard.period_type.' . Leaderboard::PERIOD_TYPE_WEEK), self::week())
                ->type('card')
                ->destroyInactiveTabPane()
        );
    }

    /**
     * 週榜 列表
     * @auth true
     * @return Grid
     */
    public function week(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('leaderboard.title'));
            $grid->hideDelete();
            $grid->hideSelection();

            $exAdminFilter = Request::input('ex_admin_filter', []);

            if (! empty($exAdminFilter['settle_at_start'])) {
                $grid->model()->where('settle_at', '>=', $exAdminFilter['settle_at_start']);
            }

            if (! empty($exAdminFilter['settle_at_end'])) {
                $grid->model()->where('settle_at', '<=', $exAdminFilter['settle_at_end']);
            }

            $grid->model()->where('period_type', 1)->where('scope_type', 3)->orderBy('period_key', 'desc');

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('period_key')->placeholder(admin_trans('leaderboard.fields.period_key'));

                $filter->form()->hidden('settle_at_start');
                $filter->form()->hidden('settle_at_end');
                $filter->form()->dateTimeRange('settle_at_start', 'settle_at_end', '')
                    ->placeholder([admin_trans('leaderboard.settle_at.start'), admin_trans('leaderboard.settle_at.end')]);
            });

            $grid->column('id', admin_trans('leaderboard.fields.id'))->align('center');
            $grid->column('period_key', admin_trans('leaderboard.fields.period_key'))->align('center');
            $grid->column('start_at', admin_trans('leaderboard.fields.start_at'))->align('center');
            $grid->column('end_at', admin_trans('leaderboard.fields.end_at'))->align('center');
            $grid->column('threshold', admin_trans('leaderboard.fields.threshold'))->align('center');
            $grid->column('settle_at', admin_trans('leaderboard.fields.settle_at'))->align('center');

            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();

                $actions->prepend(
                    Button::create(admin_trans('leaderboard_entry.title'))
                        ->type('primary')
                        ->icon(Icon::create('ProfileOutlined'))
                        ->modal([$this, 'weekDetail'], ['id' => $data['id']])
                        ->width('80%')
                );
            });
        });
    }

    /**
     * 週榜 詳情
     * @auth true
     * @param int $id
     * @return Grid
     */
    public function weekDetail(int $id): Grid
    {
        return Grid::create(new $this->modelEntry, function (Grid $grid) use ($id) {
            $grid->title(admin_trans('leaderboard_entry.title'));
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->model()->where('leaderboard_id', $id)->where('store_admin_id', Admin::user()->id);
            $grid->model()->orderBy('store_admin_id', 'asc')->orderBy('rank', 'asc');

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('leaderboard_entry.fields.player_id'));
                $filter->eq()->select('grant_status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('leaderboard_entry.fields.grant_status'))
                    ->options([
                        0 => admin_trans('leaderboard_entry.grant_status.0'),
                        1 => admin_trans('leaderboard_entry.grant_status.1')
                    ]);
            });

            $grid->column('rank', admin_trans('leaderboard_entry.fields.rank'))->align('center');
            $grid->column('player.name', admin_trans('leaderboard_entry.fields.player_id'))->align('center');
            $grid->column('score', admin_trans('leaderboard_entry.fields.score'))->align('center');
            $grid->column('prize_amount', admin_trans('leaderboard_entry.fields.prize_amount'))->align('center');
            $grid->column('grant_status', admin_trans('leaderboard_entry.fields.grant_status'))->align('center')
                ->display(function ($value) {
                    switch ($value) {
                        case 0:
                            $tag = Tag::create(admin_trans('leaderboard_entry.grant_status.0'))->color('#ff4d4f');
                            break;

                        case 1:
                            $tag = Tag::create(admin_trans('leaderboard_entry.grant_status.1'))->color('#52c41a');
                            break;
                    }

                    return Html::create()->content([$tag]);
                });
            $grid->column('granted_at', admin_trans('leaderboard_entry.fields.granted_at'))->align('center');

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }
}

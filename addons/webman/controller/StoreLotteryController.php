<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Lottery;
use addons\webman\model\PlayerLotteryRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;

/**
 * 店家彩金管理
 * @group store
 */
class StoreLotteryController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_lottery_record_model');
    }

    /**
     * 彩金领取记录
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            // 获取当前店家管理员信息
            /** @var AdminUser $currentAdmin */
            $currentAdmin = Admin::user();

            // 数据权限过滤：只显示店家下属玩家的彩金记录
            // 使用 store_admin_id 字段过滤
            $grid->model()->whereHas('player', function($query) use ($currentAdmin) {
                $query->where('store_admin_id', $currentAdmin->id);
            });

            $grid->title(admin_trans('player_lottery_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            $requestFilter = Request::input('ex_admin_filter', []);

            // 过滤条件
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            if (!empty($requestFilter['amount'])) {
                $grid->model()->where('amount', $requestFilter['amount']);
            }
            if (!empty($requestFilter['lottery_name'])) {
                $grid->model()->where('lottery_name', 'like', '%' . $requestFilter['lottery_name'] . '%');
            }
            if (!empty($requestFilter['lottery_type'])) {
                $grid->model()->where('lottery_type', $requestFilter['lottery_type']);
            }
            if (!empty($requestFilter['machine_code'])) {
                $grid->model()->where('machine_code', 'like', '%' . $requestFilter['machine_code'] . '%');
            }
            if (!empty($requestFilter['machine_name'])) {
                $grid->model()->where('machine_name', 'like', '%' . $requestFilter['machine_name'] . '%');
            }
            if (!empty($requestFilter['player_phone'])) {
                $grid->model()->where('player_phone', 'like', '%' . $requestFilter['player_phone'] . '%');
            }
            if (!empty($requestFilter['status'])) {
                $grid->model()->where('status', $requestFilter['status']);
            }
            if (!empty($requestFilter['uuid'])) {
                $grid->model()->where('uuid', $requestFilter['uuid']);
            }
            if (!empty($requestFilter['search_type'])) {
                $grid->model()->where('is_test', $requestFilter['search_type']);
            }
            if (!empty($requestFilter['search_is_promoter'])) {
                $grid->model()->where('is_promoter', $requestFilter['search_is_promoter']);
            }
            if (!empty($requestFilter['cate_id'])) {
                $cate_id = $requestFilter['cate_id'];
                $grid->model()->whereHas('machine', function ($query) use ($cate_id) {
                    $query->whereIn('cate_id', $cate_id);
                });
            }
            if (isset($requestFilter['date_type'])) {
                $grid->model()->where(getDateWhere($requestFilter['date_type'], 'created_at'));
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 统计信息
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($requestFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $requestFilter,
                    'type' => 'StoreLottery',
                ]));
            })->style(['background' => '#fff']);

            $grid->header($layout);

            // 列定义
            $grid->column('id', admin_trans('player_lottery_record.fields.id'))->align('center')->fixed(true);
            $grid->column('uuid', admin_trans('player_lottery_record.fields.uuid'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->fixed(true)->copy();

            $grid->column('player_phone', admin_trans('player_lottery_record.fields.player_phone'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    $image = isset($data->player->avatar) && !empty($data->player->avatar)
                        ? Avatar::create()->src($data->player->avatar)
                        : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($val)
                    ]);
                })->align('center')->fixed(true)->ellipsis(true);

            $grid->column('type', admin_trans('player.fields.type'))
                ->display(function ($val, $data) {
                    $tags = [];
                    if ($data['is_test'] == 1) {
                        $tags[] = Tag::create(admin_trans('player.fields.is_test'))->color('red');
                    } else {
                        $tags[] = Tag::create(admin_trans('player.player'))->color('green');
                    }
                    if ($data['is_coin'] == 1) {
                        $tags[] = Tag::create(admin_trans('player.coin_merchant'))->color('#3b5999');
                    }
                    if ($data['is_promoter'] == 1) {
                        $tags[] = Tag::create(admin_trans('player.promoter'))->color('purple');
                    }
                    return Html::create()->content($tags)->style(['display' => 'inline-flex', 'text-align' => 'center']);
                })->ellipsis(true)->width(200)->align('center');

            $grid->column('status', admin_trans('player_lottery_record.fields.status'))
                ->display(function ($val) {
                    $tag = '';
                    switch ($val) {
                        case PlayerLotteryRecord::STATUS_UNREVIEWED:
                            $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#108ee9');
                            break;
                        case PlayerLotteryRecord::STATUS_REJECT:
                            $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#f50');
                            break;
                        case PlayerLotteryRecord::STATUS_PASS:
                            $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#87d068');
                            break;
                        case PlayerLotteryRecord::STATUS_COMPLETE:
                            $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#cd201f');
                            break;
                    }
                    return Html::create()->content([$tag]);
                })->align('center');

            $grid->column('lottery_type', admin_trans('lottery.fields.lottery_type'))
                ->display(function ($val) {
                    return Html::create()->content([
                        Tag::create(admin_trans('lottery.lottery_type.' . $val))
                            ->color($val == Lottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                    ]);
                })->align('center');

            $grid->column('machine_name', admin_trans('player_lottery_record.fields.machine_name'))->align('center')->copy();
            $grid->column('machine_code', admin_trans('player_lottery_record.fields.machine_code'))->sortable()->align('center')->copy();
            $grid->column('odds', admin_trans('player_lottery_record.fields.odds'))->sortable()->align('center');
            $grid->column('lottery_name', admin_trans('player_lottery_record.fields.lottery_name'))->align('center');

            $grid->column('amount', admin_trans('player_lottery_record.fields.amount'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->lottery_multiple > 1 ? Tag::create(admin_trans('player_lottery_record.double'))
                            ->color('success')->style(['margin' => '0 auto']) : '',
                        $data->is_max == 1 ? Tag::create(admin_trans('player_lottery_record.max_amount'))
                            ->color('red')->style(['margin' => '0 auto', 'margin-left' => '3px']) : ''
                    ]);
                })->sortable()->align('center');

            $grid->column('lottery_pool_amount', admin_trans('player_lottery_record.fields.lottery_pool_amount'))
                ->sortable()->align('center');

            $grid->column('lottery_rate', admin_trans('player_lottery_record.fields.lottery_rate'))
                ->display(function ($val) {
                    return Html::create()->content([
                        Html::div()->content($val . '%')
                    ]);
                })->sortable()->align('center');

            $grid->column('cate_rate', admin_trans('player_lottery_record.fields.cate_rate'))->align('center');

            $grid->column('reject_reason', admin_trans('player_lottery_record.fields.reject_reason'))
                ->display(function ($value) {
                    return Str::of($value)->limit(20, ' (...)');
                })->tip()->width('150px')->align('center');

            $grid->column('user_name', admin_trans('player_lottery_record.fields.user_name'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($data->user_name ?? ''),
                    ]);
                })->align('center');

            $grid->column('audit_at', admin_trans('player_lottery_record.fields.audit_at'))->sortable()->align('center');
            $grid->column('created_at', admin_trans('player_lottery_record.fields.created_at'))->sortable()->align('center');

            $grid->hideDelete();
            $grid->hideSelection();

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine_name')->placeholder(admin_trans('player_lottery_record.fields.machine_name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('player_lottery_record.fields.machine_code'));
                $filter->like()->text('lottery_name')->placeholder(admin_trans('player_lottery_record.fields.lottery_name'));
                $filter->like()->text('player_phone')->placeholder(admin_trans('player_lottery_record.fields.player_phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player_lottery_record.fields.uuid'));
                $filter->eq()->number('amount')->precision(2)->style(['width' => '150px'])
                    ->placeholder(admin_trans('player_lottery_record.fields.amount'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('player_lottery_record.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerLotteryRecord::STATUS_UNREVIEWED => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_UNREVIEWED),
                        PlayerLotteryRecord::STATUS_REJECT => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_REJECT),
                        PlayerLotteryRecord::STATUS_PASS => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_PASS),
                        PlayerLotteryRecord::STATUS_COMPLETE => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_COMPLETE),
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

                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '150px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();

                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);

                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);

                $filter->select('search_is_promoter')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.is_promoter'))
                    ->options([
                        0 => admin_trans('player.not_promoter'),
                        1 => admin_trans('player.promoter'),
                    ]);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')
                    ->placeholder([
                        admin_trans('public_msg.created_at_start'),
                        admin_trans('public_msg.created_at_end')
                    ]);
            });

            $grid->expandFilter();
        });
    }
}

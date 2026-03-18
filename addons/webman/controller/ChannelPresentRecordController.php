<?php

namespace addons\webman\controller;

use addons\webman\model\PlayerPresentRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 转点记录
 * @group channel
 */
class ChannelPresentRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_present_record_model');
    }

    /**
     * 币商交易
     * @group channel
     * @auth true
     */
    public function coinList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_present_record.title'));
            $grid->model()->with(['user', 'player'])->orderBy('created_at', 'desc');
            $grid->autoHeight();
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['search_user_type']) && !empty($requestFilter['search_user_text'])) {
                    switch ($requestFilter['search_user_type']) {
                        case 'user_name':
                            $grid->model()->whereHas('user', function ($query) use ($requestFilter) {
                                $query->where('name', 'LIKE', "%" . $requestFilter['search_user_text'] . "%");
                            });
                            break;
                        case 'user_uuid':
                            $grid->model()->whereHas('user', function ($query) use ($requestFilter) {
                                $query->where('uuid', $requestFilter['search_user_text']);
                            });
                            break;
                        case 'user_phone':
                            $grid->model()->whereHas('user', function ($query) use ($requestFilter) {
                                $query->where('phone', $requestFilter['search_user_text']);
                            });
                            break;
                        default:
                            break;
                    }
                }
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
                if (!empty($requestFilter['search_player_type'])) {
                    switch ($requestFilter['search_player_type']) {
                        case 'player_name':
                            $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                                $query->where('name', 'LIKE', "%" . $requestFilter['search_player_text'] . "%");
                            });
                            break;
                        case 'player_uuid':
                            $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                                $query->where('uuid', $requestFilter['search_player_text']);
                            });
                            break;
                        case 'player_phone':
                            $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                                $query->where('phone', $requestFilter['search_player_text']);
                            });
                            break;
                        default:
                            break;
                    }
                }
            }
            if (isset($requestFilter['user_search_type'])) {
                $grid->model()->whereHas('user', function ($query) use ($requestFilter) {
                    $query->where('is_test', $requestFilter['user_search_type']);
                });
            }
            if (isset($requestFilter['player_search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('is_test', $requestFilter['player_search_type']);
                });
            }

            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(IF(type = 2, amount,0)) as total_icon_amount, sum(IF(type = 1, amount,0)) as total_player_amount')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_icon_amount']) ? floatval($totalData['total_icon_amount']) : 0)->prefix(admin_trans('player_present_record.total_data.total_icon_amount'))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_player_amount']) ? floatval($totalData['total_player_amount']) : 0)->prefix(admin_trans('player_present_record.total_data.total_player_amount'))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
            })->style(['background' => '#fff']);

            $grid->tools([
                $layout
            ]);

            $grid->column('id', admin_trans('player_present_record.fields.id'))->align('center');
            $grid->column('user.phone', admin_trans('player_present_record.fields.user_id'))->display(function (
                $val,
                PlayerPresentRecord $data
            ) {
                $image = !empty($data->user->avatar) ? Avatar::create()->src($data->user->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->user->uuid)
                ])->style(['cursor' => 'pointer'])->modal($this->playerDetail([
                    'phone' => $data->user->phone ?? '',
                    'name' => $data->user->name ?? '',
                    'uuid' => $data->user->uuid ?? '',
                    'address' => $data->user->player_extend->address ?? '',
                    'email' => $data->user->player_extend->email ?? '',
                    'line' => $data->user->player_extend->line ?? '',
                    'created_at' => !empty($data->user->created_at) ? date('Y-m-d H:i:s',
                        strtotime($data->user->created_at)) : '',
                ]));
            })->align('center');
            $grid->column('user.type', admin_trans('player.fields.type'))->display(function ($val, PlayerPresentRecord $data) {
                return Html::create()->content([
                    $data->user->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('user_origin_amount',
                admin_trans('player_present_record.fields.user_origin_amount'))->align('center');
            $grid->column('user_after_amount',
                admin_trans('player_present_record.fields.user_after_amount'))->align('center');
            $grid->column('amount', admin_trans('player_present_record.fields.amount'))->align('center');
            $grid->column('type', admin_trans('player_present_record.fields.type'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerPresentRecord::TYPE_IN:
                            $tag = Tag::create(admin_trans('player_present_record.type.' . PlayerPresentRecord::TYPE_IN))->color('#2db7f5');
                            break;
                        case PlayerPresentRecord::TYPE_OUT:
                            $tag = Tag::create(admin_trans('player_present_record.type.' . PlayerPresentRecord::TYPE_OUT))->color('#8D3514');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center')->sortable();
            $grid->column('player.phone', admin_trans('player_present_record.fields.player_id'))->display(function (
                $val,
                PlayerPresentRecord $data
            ) {
                $image = !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->uuid)
                ])->style(['cursor' => 'pointer'])->modal($this->playerDetail([
                    'phone' => $data->player->phone ?? '',
                    'name' => $data->player->name ?? '',
                    'uuid' => $data->player->uuid ?? '',
                    'address' => $data->player->player_extend->address ?? '',
                    'email' => $data->player->player_extend->email ?? '',
                    'line' => $data->player->player_extend->line ?? '',
                    'created_at' => !empty($data->player->created_at) ? date('Y-m-d H:i:s',
                        strtotime($data->player->created_at)) : '',
                ]));
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerPresentRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');

            $grid->column('player_origin_amount',
                admin_trans('player_present_record.fields.player_origin_amount'))->align('center');
            $grid->column('player_after_amount',
                admin_trans('player_present_record.fields.player_after_amount'))->align('center');
            $grid->column('created_at',
                admin_trans('player_present_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('search_user_type')
                    ->showSearch()
                    ->placeholder(admin_trans('player_present_record.search.user_placeholder'))
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        'user_uuid' => admin_trans('player_present_record.search.user_uuid'),
                        'user_name' => admin_trans('player_present_record.search.user_name'),
                        'user_phone' => admin_trans('player_present_record.search.user_phone'),
                    ]);
                $filter->eq()->text('search_user_text')->placeholder(admin_trans('player_present_record.search.placeholder'));
                $filter->eq()->select('search_player_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_present_record.search.player_placeholder'))
                    ->options([
                        'player_uuid' => admin_trans('player_present_record.search.player_uuid'),
                        'player_name' => admin_trans('player_present_record.search.player_name'),
                        'player_phone' => admin_trans('player_present_record.search.player_phone'),
                    ]);
                $filter->eq()->text('search_player_text')->placeholder(admin_trans('player_present_record.search.placeholder'));
                $filter->select('user_search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_present_record.user_type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->select('player_search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_present_record.player_type'))
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
     * 玩家详情
     * @param array $data
     * @return Detail
     */
    public function playerDetail(array $data): Detail
    {
        return Detail::create($data, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('created_at', admin_trans('player.fields.created_at'));
        })->layout('vertical');
    }
}

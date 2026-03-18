<?php

namespace addons\webman\controller;

use addons\webman\model\Player;
use addons\webman\model\PlayerPresentRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;

/**
 * 转点记录
 */
class PresentRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_present_record_model');
    }

    /**
     * 玩家转点
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_present_record.title'));
            $grid->model()->with([
                'user',
                'channel',
                'user.player_extend',
                'player',
                'player.player_extend'
            ])->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($exAdminFilter['user_search_type'])) {
                $grid->model()->whereHas('user', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['user_search_type']);
                });
            }
            if (isset($exAdminFilter['player_search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['player_search_type']);
                });
            }
            if (!empty($exAdminFilter['user_id'])) {
                $grid->model()->where('user_id', '=', $exAdminFilter['user_id']);
            }
            if (!empty($exAdminFilter['player_id'])) {
                $grid->model()->where('player_id', '=', $exAdminFilter['player_id']);
            }
            
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayerPresent',
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('id', admin_trans('player_present_record.fields.id'))->align('center');
            $grid->column('channel.name', admin_trans('player_recharge_record.fields.department_id'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('user.uuid', admin_trans('player_present_record.fields.user_id'))->display(function (
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
            }, admin_trans('player_present_record.user_info'));
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
            $grid->column(function (Grid $grid) {
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
            }, admin_trans('player_present_record.player_info'));

            $grid->column('channel.name', admin_trans('player_present_record.fields.department_id'))->align('center');
            $grid->column('created_at',
                admin_trans('player_present_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('user_id')
                    ->placeholder(admin_trans('player_present_record.fields.user_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->eq()->select('player_id')
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_present_record.fields.player_id'))
                    ->showSearch()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.department_id'))
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

    /**
     * 玩家详情
     * @param array $data
     * @return Detail
     */
    public function playerDetail(array $data): Detail
    {
        return Detail::create($data, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('created_at', admin_trans('player.fields.created_at'));
        })->layout('vertical');
    }

    /**
     * 筛选下拉
     * @return mixed
     */
    public function getPlayerOptions()
    {
        $request = Request::input();
        $player = Player::orderBy('created_at', 'desc')
            ->forPage(1, 20);
        if (!empty($request['search'])) {
            $player->where('phone', 'like', '%' . $request['search'] . '%');
        }
        $playerList = $player->get();
        $data = [];
        /** @var Player $player */
        foreach ($playerList as $player) {
            $data[] = [
                'value' => $player->id,
                'label' => $player->phone,
            ];
        }
        return Response::success($data);
    }
}

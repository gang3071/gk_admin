<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\MachineLotteryRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerPlatformCash;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;

/**
 * 机台大赏记录
 * @auth true
 */
class MachineLotteryRecordController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_lottery_record_model');
    }
    
    /**
     * 机台大赏记录
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->with([
                'player',
                'machine' => function ($query) {
                    return $query->with(['machineLabel']);
                },
                'player.channel'
            ])->where('has_rush', 0);
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['machine'])) {
                $grid->model()->whereHas('machine', function ($query) use ($exAdminFilter) {
                    if (!empty($exAdminFilter['machine']['machineLabel']['name'])) {
                        $query->whereHas('machineLabel', function ($query) use ($exAdminFilter) {
                            $query->where('name', 'like',
                                '%' . $exAdminFilter['machine']['machineLabel']['name'] . '%');
                        });
                    }
                    if (!empty($exAdminFilter['machine']['code'])) {
                        $query->where('code', $exAdminFilter['machine']['code']);
                    }
                    if (!empty($exAdminFilter['machine']['cate_id'])) {
                        $query->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                    }
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                });
            }
            
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }
            
            $grid->model()->orderBy('id', 'desc');
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('machine.machine_lottery_record_title'));
            $grid->column('id', admin_trans('player_game_log.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                if (!empty($data->player)) {
                    return Html::create()->content([
                        Html::div()->content($data->player->uuid)
                    ]);
                }
                return '';
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                if (!empty($data->player)) {
                    return Html::create()->content([
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                    ]);
                }
                return '';
            })->fixed(true)->align('center');
            $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                if (!empty($data->player)) {
                    return $data->player->channel->name;
                }
                return '';
            })->width('150px')->align('center');
            $grid->column('machine.machineLabel.name', admin_trans('machine.fields.name'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                }
                return '';
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                return $data->machine->code;
            })->align('center');
            $grid->column('use_turn', admin_trans('machine.fields.use_turn'))->display(function (
                $val,
                MachineLotteryRecord $data
            ) {
                if ($data->machine->type == GameType::TYPE_SLOT) {
                    return $val > 0 ? intval(ceil($val / 3)) : 0;
                }
                return $val;
            })->align('center');
            $grid->column('created_at', admin_trans('player_game_log.fields.create_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('machine.machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                SelectGroup::create();
                $filter->in()->cascaderSingle('machine.cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }
    
    /**
     * 玩家详情
     * @param $player_id
     * @return Detail
     */
    public function playerInfo($player_id): Detail
    {
        $player = Player::find($player_id);
        return Detail::create($player, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('is_promoter', admin_trans('player.fields.is_promoter'))->display(function (
                $value,
                Player $data
            ) {
                return Html::create()->content([
                    Tag::create($value == 1 ? admin_trans('player.promoter') : admin_trans('player.national_promoter'))->color($value == 1 ? 'red' : 'orange'),
                    $data->player_promoter->name ?? ''
                ]);
            });
            $detail->item('national_promoter.level_list.damage_rebate_ratio',
                admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function (
                $value,
                Player $data
            ) {
                return floatval($value) . ' %';
            });
            $detail->item('national_promoter.level_list.recharge_ratio',
                admin_trans('national_promoter.level_list.recharge_ratio'))->display(function ($value, Player $data) {
                return floatval($value) . ' %';
            });
            $detail->item('recommend_player.name',
                admin_trans('player_promoter.fields.recommend_promoter_name'))->display(function (
                $value,
                Player $data
            ) {
                if (isset($data->recommend_player) && !empty($data->recommend_player)) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->recommend_player->id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->recommend_player->phone);
                }
                return '';
            });
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('created_at', admin_trans('player.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            });
            $detail->item('machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF));
            
        })->bordered();
    }
}

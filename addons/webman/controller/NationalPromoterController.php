<?php

namespace addons\webman\controller;

use addons\webman\model\Channel;
use addons\webman\model\LevelList;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalLevel;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Db;
use think\Exception;

/**
 * 全民代理
 * @group channel
 */
class NationalPromoterController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.national_promoter_model');
    }

    /**
     * 全民代理
     * @auth true
     * @group channel
     * @return Card
     */
    public function index(): Card
    {
        $platform = Channel::query()->get();
        $tabs = Tabs::create();
        foreach($platform as $item){
            $tabs->pane($item['name'],$this->list($item->department_id));
        }

        return Card::create($tabs
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }
    public function list($id)
    {
        return Grid::create(new NationalLevel(), function (Grid $grid) use($id) {
            $grid->title(admin_trans('national_promoter.title'));
            $grid->model()->where('department_id',$id)->select('sort', 'name', 'id')->orderBy('sort', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('sort', admin_trans('national_promoter.level_list.sort'))->fixed(true)->align('center');
            $grid->column('name', admin_trans('national_promoter.level_list.name'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('level', admin_trans('national_promoter.level_list.level'))->display(function () {
                return 5;
            })->fixed(true)->ellipsis(true)->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, NationalLevel $data) {
                $detail = $actions->detail();
                $detail->item(admin_trans('qrcode.detail'), 'fas fa-table')
                    ->modal([$this, 'levelDetail'], ['level_id' => $data->id])
                    ->width('40%');
                $actions->hideDel();
            });
            $grid->hideAdd();
        });
    }
    /**
     * 全民代理详情
     * @param $level_id
     * @return Grid
     */
    public function levelDetail($level_id): Grid
    {
        return Grid::create(new LevelList(), function (Grid $grid) use ($level_id) {
            $grid->model()->where('level_id', $level_id)->orderBy('level', 'asc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('level', admin_trans('national_promoter.level_list.level'))->display(function (
                $value,
                $data
            ) {
                return $data->national_level->name . ' ' . $value . admin_trans('national_promoter.level_suffix');
            })->width('20%')->align('center')->fixed('right');
            $grid->column('must_chip_amount',
                admin_trans('national_promoter.level_list.must_chip_amount'))->width('20%')->align('center')->fixed('right');
            $grid->column('damage_rebate_ratio',
                admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function ($value) {
                return $value . '%';
            })->width('20%')->align('center')->fixed('right');
            $grid->column('recharge_ratio', admin_trans('national_promoter.level_list.recharge_ratio'))->width('20%')->align('center')->fixed('right');
            $grid->column('reverse_water',
                admin_trans('national_promoter.level_list.reverse_water'))->display(function ($value) {
                return $value . '%';
            })->width('20%')->align('center')->fixed('right');
            $grid->column('recharge_ratio',
                admin_trans('national_promoter.level_list.recharge_ratio'))->width('20%')->align('center')->fixed('right');
            $grid->hideDelete();
            $grid->hideAdd();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 修改打码量
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        $min_level = LevelList::orderBy('must_chip_amount', 'asc')->first();
        return Form::create(new LevelList(), function (Form $form) use ($min_level) {
            $form->title(admin_trans('national_promoter.title'));
            $form->text('national_level.name', admin_trans('national_promoter.level_list.name'))
                ->disabled(true);
            if ($form->input('id') == $min_level->id) {
                $form->number('must_chip_amount', admin_trans('national_promoter.level_list.must_chip_amount'))
                    ->style(['width' => '100%'])->disabled(true)
                    ->required();
            } else {
                $form->number('must_chip_amount', admin_trans('national_promoter.level_list.must_chip_amount'))
                    ->style(['width' => '100%'])
                    ->required();
            }
            $form->number('damage_rebate_ratio', admin_trans('national_promoter.level_list.damage_rebate_ratio'))
                ->min(0)->max(100)->precision(2)->style(['width' => '100%'])
                ->addonAfter('%')
                ->required();
            $form->number('recharge_ratio', admin_trans('national_promoter.level_list.recharge_ratio'))
                ->precision(2)->style(['width' => '100%'])
                ->required();
            $form->number('reverse_water', admin_trans('national_promoter.level_list.reverse_water'))
                ->min(0)->max(100)->step(0.01)->precision(2)->style(['width' => '100%'])
                ->addonAfter('%')
                ->required();
            $form->saving(function (Form $form) {
                DB::beginTransaction();
                try {
                    $id = $form->input('id');
                    /** @var LevelList $levelInfo */
                    $levelInfo = LevelList::query()->find($id);
                    if ($levelInfo->level == 1) {
                        $level = 5;
                        $level_id = $levelInfo->level_id + 1;
                    } else {
                        $level = $levelInfo->level - 1;
                        $level_id = $levelInfo->level_id;
                    }
                    if ($levelInfo->level == 5) {
                        $part_level = 1;
                        $part_level_id = $levelInfo->level_id - 1;
                    } else {
                        $part_level = $levelInfo->level + 1;
                        $part_level_id = $levelInfo->level_id;
                    }
                    /** @var LevelList $subLevelInfo */
                    $subLevelInfo = LevelList::query()->where('level', $level)->where('level_id', $level_id)->first();
                    /** @var LevelList $partLevelInfo */
                    $partLevelInfo = LevelList::query()->where('level', $part_level)->where('level_id',
                        $part_level_id)->first();
                    $levelInfo->must_chip_amount = $form->input('must_chip_amount');
                    $levelInfo->damage_rebate_ratio = $form->input('damage_rebate_ratio');
                    $levelInfo->recharge_ratio = $form->input('recharge_ratio');
                    $levelInfo->reverse_water = $form->input('reverse_water');
                    if ((!empty($subLevelInfo) && $subLevelInfo->must_chip_amount >= $levelInfo->must_chip_amount) || (!empty($partLevelInfo) && $partLevelInfo->must_chip_amount <= $levelInfo->must_chip_amount)) {
                        throw new Exception(admin_trans('national_promoter.must_chip_amount_error'));
                    }
                    $levelInfo->save();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error($e->getMessage());
                }
                return message_success(admin_trans('qrcode.save_success'));
            });
        });
    }

    /**
     * 邀请人数奖励
     * @group channel
     * @return Grid
     */
    public function nationalInvite(): Grid
    {
        return Grid::create(new NationalInvite(), function (Grid $grid) {
            $grid->title(admin_trans('national_promoter.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('number', admin_trans('national_promoter.invite.number'))->display(function ($value, $data) {
                return $data->min . ' ~ ' . $data->max;
            })->width('20%')->align('center')->fixed('right');
            $grid->column('interval', admin_trans('national_promoter.invite.interval'))->fixed(true)->align('center');
            $grid->column('money',
                admin_trans('national_promoter.invite.money'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('status',
                admin_trans('national_promoter.invite.status'))->switch()->ellipsis(true)->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->setForm()->drawer($this->addInviteConfig());
        });
    }

    /**
     * 修改邀请人数奖励
     * @auth true
     * @return Form
     */
    public function addInviteConfig(): Form
    {
        return Form::create(new NationalInvite(), function (Form $form) {
            $form->title(admin_trans('national_promoter.title'));
            $form->row(function (Form $form) {
                $form->number('min', admin_trans('national_promoter.invite.min'))->style(['width' => '100%']);
                $form->number('max', admin_trans('national_promoter.invite.max'))->style(['width' => '100%']);
            }, admin_trans('national_promoter.invite.number'));
            $form->number('interval', admin_trans('national_promoter.invite.interval'))
                ->style(['width' => '100%'])->min(1)
                ->required();
            $form->number('money', admin_trans('national_promoter.invite.money'))
                ->style(['width' => '100%'])->min(0)->precision(2)
                ->required();
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                DB::beginTransaction();
                try {
                    $min = $form->input('min');
                    $max = $form->input('max');
                    $id = $form->input('id');
                    $minInviteInfo = NationalInvite::query()->whereBetween('min', [$min, $max])->where(function ($query
                    ) use ($id) {
                        if (isset($id)) {
                            $query->where('id', '<>', $id);
                        }
                    })->first();
                    $maxInviteInfo = NationalInvite::query()->whereBetween('max', [$min, $max])->where(function ($query
                    ) use ($id) {
                        if (isset($id)) {
                            $query->where('id', '<>', $id);
                        }
                    })->first();
                    if (!empty($minInviteInfo) || !empty($maxInviteInfo)) {
                        throw new Exception(admin_trans('national_promoter.invite_num_error'));
                    }
                    if ($form->isEdit()) {
                        /** @var NationalInvite $nationalInvite */
                        $nationalInvite = NationalInvite::query()->find($id);
                    } else {
                        $nationalInvite = new NationalInvite();
                    }
                    $nationalInvite->min = $min;
                    $nationalInvite->max = $max;
                    $nationalInvite->interval = $form->input('interval');
                    $nationalInvite->money = $form->input('money');
                    $nationalInvite->save();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error($e->getMessage());
                }
                return message_success(admin_trans('qrcode.save_success'));
            });
        });
    }


    /**
     * 返佣记录
     * @auth true
     */
    public function record($id = 0): Grid
    {
        return Grid::create(new PlayerDeliveryRecord(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player_delivery_record.title'));
            $grid->model()->with(['player', 'machine'])
                ->when(!empty($id), function ($query) use ($id) {
                    $query->where('player_id', $id);
                })
                ->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['remark'])) {
                $grid->model()->where('remark', 'like', '%' . $exAdminFilter['remark'] . '%');
            }
            if (!empty($exAdminFilter['type'])) {
                $grid->model()->where('type', $exAdminFilter['type']);
            }
            if (isset($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                });
            }
            if (isset($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                });
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }

            //只展示三个类型的数据
            $grid->model()->whereIn('type', [
                PlayerDeliveryRecord::TYPE_NATIONAL_INVITE,
                PlayerDeliveryRecord::TYPE_RECHARGE_REWARD,
                PlayerDeliveryRecord::TYPE_DAMAGE_REBATE
            ]);

            //统计卡片
            $query = clone $grid->model();
            $totalData = $query->selectRaw("
                sum(IF(type =" . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE . ", amount,0)) as total_national_invite,
                sum(IF(type =" . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD . ", amount,0)) as total_recharge_reward,
                sum(IF(type =" . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE . ", amount,0)) as total_damage_rebate
            ")->first();

            $totalPoint = $totalData['total_national_invite'] + $totalData['total_recharge_reward'] + $totalData['total_damage_rebate'];

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData, $totalPoint) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_recharge_record.total_data.total_notional_point'))->value(!empty($totalPoint) ? round($totalPoint,
                            2) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE))->value(!empty($totalData['total_national_invite']) ? floatval($totalData['total_national_invite']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD))->value(!empty($totalData['total_recharge_reward']) ? floatval($totalData['total_recharge_reward']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE))->value(!empty($totalData['total_damage_rebate']) ? floatval($totalData['total_damage_rebate']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->autoHeight();
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('player.phone')
            );
            $grid->column('type', admin_trans('player_delivery_record.fields.type'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_RECHARGE_REWARD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_DAMAGE_REBATE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE))->color('#e8c521');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->width('150px')->align('center')->editable(
                (new Editable)
                    ->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:50' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');

            $grid->column('amount', admin_trans('player_delivery_record.fields.amount'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                    case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                    default:
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                }
            })->align('center');
            $grid->column('amount_after', admin_trans('player_delivery_record.fields.amount_after'))->align('center');
            $grid->column('amount_before', admin_trans('player_delivery_record.fields.amount_before'))->align('center');
            $grid->column('user_name', admin_trans('player_delivery_record.fields.user_name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $name = admin_trans('national_promoter.player');
                if (in_array($data->type, [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                ])) {
                    $name = $data->user_name ?? admin_trans('national_promoter.admin');
                }
                if ($data->type == PlayerDeliveryRecord::TYPE_MACHINE_DOWN && !empty($data->user_id)) {
                    $name = $data->user_name ?? admin_trans('national_promoter.admin');
                }
                return Html::create()->content([
                    Html::div()->content($name),
                ]);
            });
            $grid->column('created_at',
                admin_trans('player_delivery_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_withdraw_record.fields.remark'));
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('player_delivery_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerDeliveryRecord::TYPE_NATIONAL_INVITE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE),
                        PlayerDeliveryRecord::TYPE_RECHARGE_REWARD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD),
                        PlayerDeliveryRecord::TYPE_DAMAGE_REBATE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE),
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
     * 分润报表
     * @group channel
     * @auth true
     */
    public function reportList(): Grid
    {
        return Grid::create(new NationalProfitRecord(), function (Grid $grid) {
            $grid->title(admin_trans('promoter_profit_record.title'));
            $grid->model()->with([
                'player'
            ])->orderBy('id', 'desc');
            $this->profitRecordGrid($grid);
            $grid->export();
        });
    }

    /**
     * @param Grid $grid
     * @return void
     */
    protected function profitRecordGrid(Grid $grid): void
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);
        if (!empty($exAdminFilter['date_start'])) {
            $grid->model()->where('created_at', '>=', $exAdminFilter['date_start']);
        }
        if (!empty($exAdminFilter['date_end'])) {
            $grid->model()->where('created_at', '<=', date('Y-m-d',strtotime("{$exAdminFilter['date_end']} +1 day")));
        }
        if (!empty($exAdminFilter['settlement_time_start'])) {
            $grid->model()->where('updated_at', '>=', $exAdminFilter['settlement_time_start']);
        }
        if (!empty($exAdminFilter['settlement_time_end'])) {
            $grid->model()->where('updated_at', '<=', date('Y-m-d',strtotime("{$exAdminFilter['settlement_time_end']} +1 day")));
        }
        $grid->autoHeight();
        $grid->bordered(true);
        $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->fixed(true)->align('center');
        $grid->column('player.name', admin_trans('promoter_profit_record.player_info'))->display(function (
            $value,
            NationalProfitRecord $data
        ) {
            $value = !empty($value) ? $value : ($data->player->phone ?? '');
            return Html::create(Str::of($value)->limit(20, ' (...)'))
                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                ->modal([$this, 'playerInfo'], ['player_id' => $data->uid])
                ->width('60%')->title(admin_trans('player.fields.phone') . ':' . ($data->player->phone ?? ''));
        })->align('center')->width(120)->ellipsis(true);

        $grid->column('status', admin_trans('promoter_profit_record.fields.status'))->display(function ($val) {
            return Tag::create(admin_trans('promoter_profit_record.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
        })->align('center')->ellipsis(true);
        $grid->column('money',
            admin_trans('promoter_profit_record.fields.profit_amount'))->display(function ($val,NationalProfitRecord $data) {
                if ($data->type == 0) {
                    $active_key = 4;
                } else {
                    $active_key = 1;
                }
            return Html::create()->content([
                $val > 0 ? '+' . $val : $val,
            ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                ->modal([$this, 'NationalProfitRecordDetail'], [
                    'id' => $data->uid,
                    'date' => date('Y-m-d',strtotime($data->created_at)),
                    'active_key' => $active_key,
                    'data' => $data->toArray(),
                ])
                ->width('80%')->title(Html::create()->content([
                    admin_trans('promoter_profit_record.fields.profit_amount'),
                    ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '4px',
                        'cursor' => 'pointer'
                    ]))->title(admin_trans('promoter_profit_record.profit_amount_tip'))
                ]));
        })->align('center')->ellipsis(true)->sortable();

        $grid->column('machine_amount',
            admin_trans('national_promoter.machine_amount'))->display(function ($val,NationalProfitRecord $data) {
                $value = PlayerGameRecord::query()->whereHas('player', function ($query) use ($data) {
                    $query->where('recommend_id', $data->uid);
                })->whereDate('created_at',$data->created_at)
                    ->selectRaw('sum((open_amount - wash_amount) * national_damage_ratio) as amount')
                    ->first()->amount;
            return Html::create()->content([$value != 0 ? bcdiv($value, 100, 2) : ''])
                ->style(['color' => 'blue', 'cursor' => 'pointer'])
                ->modal([$this, 'NationalProfitRecordDetail'], [
                    'id' => $data->uid,
                    'date' => date('Y-m-d',strtotime($data->created_at)),
                    'active_key' => '1',
                    'data' => $data->toArray(),
                ])
                ->width('80%')->title(admin_trans('promoter_profit_record.fields.machine_up_amount'));

        })->align('center')->ellipsis(true);

        $grid->column('game_amount',
            admin_trans('national_promoter.game_amount'))->display(function ($val,NationalProfitRecord $data) {
                if ($data->type == 1) {
                    $value = PlayGameRecord::query()->whereHas('player', function ($query) use ($data) {
                        $query->where('recommend_id', $data->uid);
                    })->whereDate('created_at',$data->created_at)
                    ->selectRaw('sum(diff * national_damage_ratio) as amount')
                    ->first()->amount;
                } else {
                    $value = '';
                }
            return Html::create()->content([$value != 0 ? bcdiv(-$value, 100, 2) : ''])
                ->style(['color' => 'blue', 'cursor' => 'pointer'])
                ->modal([$this, 'NationalProfitRecordDetail'], [
                    'id' => $data->uid,
                    'date' => date('Y-m-d',strtotime($data->created_at)),
                    'active_key' => '8',
                    'data' => $data->toArray(),
                ])
                ->width('80%')->title(admin_trans('promoter_profit_record.fields.game_amount'));
        })->align('center')->ellipsis(true);

        $grid->column('type',admin_trans('national_promoter.fields.type'))->display(function ($val) {
            return Tag::create(admin_trans('national_promoter.type.' . $val))->color($val == 0 ? 'orange' : 'cyan');
        })->align('center')->ellipsis(true);

        $grid->column('created_at', admin_trans('promoter_profit_record.fields.date'))
            ->header(Html::create(admin_trans('promoter_profit_record.fields.date'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('promoter_profit_record.date_tip'))
            ))->width(150)->fixed('right')->align('center')->ellipsis(true);
        $grid->column('updated_at',
            admin_trans('promoter_profit_record.fields.settlement_time'))->fixed('right')->align('center')->ellipsis(true);
        $grid->filter(function (Filter $filter) {
            $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
            $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
            $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
            $filter->select('status')
                ->placeholder(admin_trans('promoter_profit_record.fields.status'))
                ->showSearch()
                ->dropdownMatchSelectWidth()
                ->style(['width' => '200px'])
                ->options([
                    0 => admin_trans('promoter_profit_record.status.' . 0),
                    1 => admin_trans('promoter_profit_record.status.' . 1),
                ]);
            $filter->select('type')
                ->placeholder(admin_trans('national_promoter.fields.type'))
                ->showSearch()
                ->dropdownMatchSelectWidth()
                ->style(['width' => '200px'])
                ->options([
                    0 => admin_trans('national_promoter.type.' . 0),
                    1 => admin_trans('national_promoter.type.' . 1),
                ]);
            $filter->form()->hidden('date_start');
            $filter->form()->hidden('date_end');
            $filter->form()->dateRange('date_start', 'date_end', '')->placeholder([
                admin_trans('public_msg.date_start'),
                admin_trans('public_msg.date_end')
            ]);
            $filter->form()->hidden('settlement_time_start');
            $filter->form()->hidden('settlement_time_end');
            $filter->form()->dateRange('settlement_time_start', 'settlement_time_end', '')->placeholder([
                admin_trans('promoter_profit_record.settlement_time_start'),
                admin_trans('promoter_profit_record.settlement_time_end')
            ]);
        });
        $grid->tools([
            ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                'margin-left' => '10px',
                'margin-top' => '4px',
                'line-height' => '28px',
                'font-size' => '15px',
                'cursor' => 'pointer'
            ]))->title(admin_trans('promoter_profit_record.date_tip'))
        ]);
        $grid->hideDelete();
        $grid->hideSelection();
        $grid->hideTrashed();
        $grid->actions(function (Actions $actions ) {
            $actions->hideDel();
        });
        $grid->expandFilter();
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
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    PlayerController::class,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            });
        })->bordered();
    }

    /**
     * 分润明细
     * @param $id
     * @param string $date
     * @param string $active_key
     * @param array $data
     * @return Card
     */
    public function NationalProfitRecordDetail($id, string $date = '', string $active_key = '1', array $data = []): Card
    {
        $tabs = Tabs::create($active_key)
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_game_record'),
                    (isset($data['machine_down_amount']) && $data['machine_down_amount'] > 0) || (isset($data['machine_up_amount']) && $data['machine_up_amount'] > 0) ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['machine_up_amount'] . ' / ' . $data['machine_down_amount']) : ''
                ]), $this->playerGameRecord($id, $date), '1')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_recharge_record'),
                    isset($data['recharge_amount']) && $data['recharge_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['recharge_amount']) : ''
                ])
                , $this->playerRechargeRecord($id, $date), '4')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.game_record'),
                    isset($data['present_amount']) && $data['present_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['game_amount']) : ''
                ])
                , $this->playGameRecord($id, $date), '8')
            ->animated(true)
            ->tabBarExtraContent(Html::create()->content([
                $data ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'margin-left' => '2px',
                    'margin-top' => '4px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.formula_tip', [], [
                    '{up}' => $data['machine_up_amount'] ?? 0,
                    '{admin_sub}' => $data['admin_deduct_amount'] ?? 0,
                    '{activity}' => $data['bonus_amount'] ?? 0,
                    '{present}' => $data['present_amount'] ?? 0,
                    '{admin_add}' => $data['admin_add_amount'] ?? 0,
                    '{down}' => $data['machine_down_amount'] ?? 0,
                    '{lottery}' => $data['lottery_amount'] ?? 0,
                    '{ratio}' => $data['actual_ratio'] ?? 0,
                    '{profit_amount}' => $data['profit_amount'] ?? 0,
                ])) : ''
            ]))
            ->type('card');

        return Card::create($tabs);
    }

    /**
     * 机台操作
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerGameRecord($id, string $date = ''): Grid
    {
        return Grid::create(new playerGameRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_game_record_title'));
            $grid->model()
                ->where('status', PlayerGameRecord::STATUS_END)
                ->where('open_point', '!=', 'wash_point')
                ->whereHas('player', function ($query) use ($id) {
                    $query->where('recommend_id', $id);
                })->whereDate('created_at', $date)
                ->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerGameRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                $val,
                PlayerGameRecord $data
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
            $grid->column('open_point', admin_trans('promoter_profit_record.fields.open_point'))->align('center');
            $grid->column('wash_point', admin_trans('promoter_profit_record.fields.wash_point'))->align('center');
            $grid->column('national_damage_ratio', admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function ($val) {
                return Html::create()->content([(float)$val . '%'])->style(['color' => 'green']);
            })->align('center');

            $grid->column('profit_amount',
                admin_trans('promoter_profit_record.fields.profit_amount'))->display(function ($val,PlayerGameRecord $data) {
                $value = bcdiv(($data->open_amount - $data->wash_amount) * $data->national_damage_ratio, 100, 2);
                return Html::create()->content([$value])->style(['color' => 'green']);
            })->align('center')->ellipsis(true);
            $grid->column('created_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 电子游戏记录
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playGameRecord($id, string $date = ''): Grid
    {
        return Grid::create(new playGameRecord(), function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->model()
                ->whereHas('player', function ($query) use ($id) {
                    $query->where('recommend_id', $id);
                })->whereDate('created_at', $date);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->uuid),
                ]);
            })->fixed(true)->align('center');
            $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->name),
                ]);
            })->align('center');
            $grid->column('order_no', admin_trans('play_game_record.fields.order_no'))->copy();
            $grid->column('game_code', admin_trans('play_game_record.fields.game_code'))->copy();
            $grid->column('bet', admin_trans('play_game_record.fields.bet'))->display(function ($val) {
                return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('diff',
                admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('national_damage_ratio', admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function ($val) {
                return Html::create()->content([(float)$val . '%'])->style(['color' => 'green']);
            })->align('center');

            $grid->column('profit_amount',
                admin_trans('promoter_profit_record.fields.profit_amount'))->display(function ($val,playGameRecord $data) {
                    $value = bcdiv($data->diff * $data->national_damage_ratio, -100, 2);
                return Html::create()->content([$value])->style(['color' => 'green']);
            })->align('center')->ellipsis(true);

            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center');
            $grid->column('updated_at', admin_trans('play_game_record.fields.action_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_platform.fields.name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
            });
        });
    }

    /**
     * 充值记录
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerRechargeRecord($id, string $date = ''): Grid
    {
        return Grid::create(new PlayerRechargeRecord(), function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_recharge_record_title'));
            $grid->model()
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->whereHas('player', function ($query) use ($id) {
                    $query->where('recommend_id', $id);
                })->whereDate('updated_at', $date)
                ->orderBy('id', 'desc');

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerRechargeRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->align('center');
            $grid->column('channel.name', admin_trans('player_recharge_record.fields.department_id'))->align('center');
            $grid->column('type', admin_trans('player_recharge_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerRechargeRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::TYPE_BUSINESS:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#cd201f');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('point', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('recharge_ratio', admin_trans('national_promoter.level_list.recharge_ratio'))->align('center');
            $grid->column('updated_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }
}

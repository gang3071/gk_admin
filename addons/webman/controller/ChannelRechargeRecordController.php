<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelFinancialRecord;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\ChannelRechargeSetting;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\NationalPromoter;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerBank;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerRechargeRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\EmptyStatus;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Db;
use support\Log;

/**
 * 充值记录
 * @group channel
 */
class ChannelRechargeRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_recharge_record_model');
    }

    /**
     * 充值记录
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_recharge_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player', 'channel_recharge_setting'])->whereIn('type', [
                PlayerRechargeRecord::TYPE_SELF,
                PlayerRechargeRecord::TYPE_ARTIFICIAL,
                PlayerRechargeRecord::TYPE_BUSINESS,
                PlayerRechargeRecord::TYPE_GB,
                PlayerRechargeRecord::TYPE_MACHINE,
                PlayerRechargeRecord::TYPE_EH,
            ])->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['remark'])) {
                    $grid->model()->where('remark', 'like', "%{$exAdminFilter['remark']}%");
                }
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['finish_time_start'])) {
                    $grid->model()->where('finish_time', '>=', $exAdminFilter['finish_time_start']);
                }
                if (!empty($exAdminFilter['finish_time_end'])) {
                    $grid->model()->where('finish_time', '<=', $exAdminFilter['finish_time_end']);
                }
                if (!empty($exAdminFilter['player_id'])) {
                    $grid->model()->where('player_id', $exAdminFilter['player_id']);
                }
                if (!empty($exAdminFilter['player']['uuid'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('uuid', $exAdminFilter['player']['uuid']);
                    });
                }
                if (!empty($exAdminFilter['channel_recharge_setting']['type'])) {
                    $grid->model()->whereHas('channel_recharge_setting', function ($query) use ($exAdminFilter) {
                        $query->where('type', $exAdminFilter['channel_recharge_setting']['type']);
                    });
                }
                if (!empty($exAdminFilter['department_id'])) {
                    $grid->model()->where('department_id', $exAdminFilter['department_id']);
                }
                if (!empty($exAdminFilter['department_id'])) {
                    $grid->model()->where('department_id', $exAdminFilter['department_id']);
                }
                if (!empty($exAdminFilter['type'])) {
                    $grid->model()->where('type', $exAdminFilter['type']);
                }
                if (isset($exAdminFilter['status']) && $exAdminFilter['status'] != null) {
                    $grid->model()->where('status', $exAdminFilter['status']);
                }
                if (!empty($exAdminFilter['talk_tradeno'])) {
                    $grid->model()->where('talk_tradeno', 'like', '%' . $exAdminFilter['talk_tradeno'] . '%');
                }
                if (!empty($exAdminFilter['tradeno'])) {
                    $grid->model()->where('tradeno', 'like', '%' . $exAdminFilter['tradeno'] . '%');
                }

                if (isset($exAdminFilter['date_type'])) {
                    $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }
                if (!empty($exAdminFilter['recommend_uuid'])) {
                    $grid->model()->whereHas('player.recommend_player', function ($query) use ($exAdminFilter) {
                        $query->where('uuid', 'like', '%' . $exAdminFilter['recommend_uuid'] . '%');
                    });
                }
                if (isset($exAdminFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['search_type']);
                    });
                }
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw(
                'sum(IF(type = 2 and status = 2, point,0)) as total_self_inmoney, 
                sum(IF(type = 3 and status = 2, point,0)) as total_business_inmoney, 
                sum(IF(type = 4 and status = 2, point,0)) as total_artificial_inmoney, 
                sum(IF(type = 5 and status = 2, point,0)) as total_gb_inmoney,
                sum(IF(type = 6 and status = 2, point,0)) as total_machine_inmoney,
                sum(IF(type = 7 and status = 2, point,0)) as total_eh_inmoney'
            )->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_self_inmoney']) ? floatval($totalData['total_self_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_self_inmoney'))->valueStyle([
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
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_artificial_inmoney']) ? floatval($totalData['total_artificial_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_artificial_inmoney'))->valueStyle([
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
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_business_inmoney']) ? floatval($totalData['total_business_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_business_inmoney'))->valueStyle([
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
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_gb_inmoney']) ? floatval($totalData['total_gb_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_gb_inmoney'))->valueStyle([
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
                    , 4)->style(['margin-top' => '5px']);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_machine_inmoney']) ? floatval($totalData['total_machine_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_machine_inmoney'))->valueStyle([
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
                    , 4)->style(['margin-top' => '5px']);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_eh_inmoney']) ? floatval($totalData['total_eh_inmoney']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_eh_inmoney'))->valueStyle([
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
                    , 4)->style(['margin-top' => '5px']);
            })->style(['background' => '#fff']);
            $grid->tools($layout);
            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center')->fixed(true);
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy()->fixed(true);
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->copy()->fixed(true);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerRechargeRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.recommend_player.uuid', admin_trans('player.recommend_uuid'))->copy()->fixed(true);
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center');
            $grid->column('channel_recharge_setting.type', admin_trans('channel_recharge_method.fields.type'))
                ->display(function ($val, PlayerRechargeRecord $data) {
                    switch ($val) {
                        case ChannelRechargeMethod::TYPE_USDT:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#55acee');
                            break;
                        case ChannelRechargeMethod::TYPE_ALI:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#3b5999');
                            break;
                        case ChannelRechargeMethod::TYPE_WECHAT:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#87d068');
                            break;
                        case ChannelRechargeMethod::TYPE_BANK:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#cd201f');
                            break;
                        case ChannelRechargeMethod::TYPE_GB:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('orange');
                            break;
                        default:
                            return '';
                    }
                    return Html::create($tag)
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'settingInfo'],
                            ['setting_id' => $data->setting_id])
                        ->width('40%')->title(admin_trans('channel_recharge_setting.recharge_setting_info'));
                })
                ->align('center');
            $grid->column('type', admin_trans('player_recharge_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerRechargeRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::TYPE_ARTIFICIAL:
                    case PlayerRechargeRecord::TYPE_BUSINESS:
                    case PlayerRechargeRecord::TYPE_MACHINE:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#cd201f');
                    case PlayerRechargeRecord::TYPE_GB:
                    case PlayerRechargeRecord::TYPE_EH:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#87d068');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('recharge_setting_info',
                admin_trans('channel_recharge_setting.recharge_setting_info'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $rechargeSetting = $data->channel_recharge_setting;
                $info = [];
                if (!empty($rechargeSetting)) {
                    switch ($rechargeSetting->type) {
                        case ChannelRechargeMethod::TYPE_USDT:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.wallet_address') . ': ' . $rechargeSetting->wallet_address);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_ALI:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.ali_account') . ': ' . $rechargeSetting->account);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_WECHAT:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.wechat_account') . ': ' . $rechargeSetting->account);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_BANK:
                        case ChannelRechargeMethod::TYPE_GB:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.name') . ': ' . $rechargeSetting->name);
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.bank_name') . ': ' . $rechargeSetting->bank_name);
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.account') . ': ' . $rechargeSetting->account);
                            break;
                    }
                }
                return Html::create()->content($info);
            })->align('left');
            $grid->column('player_bank_info', admin_trans('player_bank.title'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                /** @var PlayerBank $bank */
                $bank = $data->player->bankCard->where('status', 1)->where('type',
                    ChannelRechargeMethod::TYPE_BANK)->first();
                if (!empty($bank)) {
                    return Html::create()->content([
                        Html::markdown('- ' . admin_trans('player_bank.fields.bank_name') . ': ' . $bank->bank_name ?? ''),
                        Html::markdown('- ' . admin_trans('player_bank.fields.account') . ': ' . $bank->account ?? ''),
                        Html::markdown('- ' . admin_trans('player_bank.fields.account_name') . ': ' . $bank->account_name ?? ''),
                    ]);
                }
                return '';
            })->align('left');
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::STATUS_WAIT:
                        return Tag::create(admin_trans('player_recharge_record.status_wait'))
                            ->color('#108ee9');
                    case PlayerRechargeRecord::STATUS_RECHARGING:
                        return Tag::create(admin_trans('player_recharge_record.status_examine'))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return Tag::create(admin_trans('player_recharge_record.status_success'))
                            ->color('#87d068');
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return Tag::create(admin_trans('player_recharge_record.status_fail'))
                            ->color('#f50');
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status_cancel'))
                            ->color('#2db7f5');
                    case PlayerRechargeRecord::STATUS_RECHARGED_REJECT:
                        return Tag::create(admin_trans('player_recharge_record.status_reject'))
                            ->color('#2db7f5');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status_system_cancel'))
                            ->color('#2db7f5');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('remark', admin_trans('player_recharge_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_recharge_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('user_name', admin_trans('player_recharge_record.fields.user_name'))->align('center');
            $grid->column('finish_time',
                admin_trans('player_recharge_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_recharge_record.fields.created_at'))->sortable()->align('center')->fixed('right');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.player_id'))
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->like()->text('recommend_uuid')->placeholder(admin_trans('player.recommend_uuid'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.type'))
                    ->options([
                        PlayerRechargeRecord::TYPE_SELF => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_SELF),
                        PlayerRechargeRecord::TYPE_BUSINESS => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_BUSINESS),
                        PlayerRechargeRecord::TYPE_ARTIFICIAL => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_ARTIFICIAL),
                        PlayerRechargeRecord::TYPE_GB => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_GB),
                        PlayerRechargeRecord::TYPE_MACHINE => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_MACHINE),
                        PlayerRechargeRecord::TYPE_EH => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_EH),
                    ]);
                $filter->eq()->select('channel_recharge_setting.type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel_recharge_method.fields.type'))
                    ->options([
                        ChannelRechargeMethod::TYPE_USDT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_USDT),
                        ChannelRechargeMethod::TYPE_ALI => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_ALI),
                        ChannelRechargeMethod::TYPE_WECHAT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_WECHAT),
                        ChannelRechargeMethod::TYPE_BANK => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_BANK),
                        ChannelRechargeMethod::TYPE_GB => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_GB),
                    ]);
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.status'))
                    ->options([
                        PlayerRechargeRecord::STATUS_WAIT => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_WAIT),
                        PlayerRechargeRecord::STATUS_RECHARGING => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGING),
                        PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS),
                        PlayerRechargeRecord::STATUS_RECHARGED_FAIL => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_FAIL),
                        PlayerRechargeRecord::STATUS_RECHARGED_CANCEL => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_CANCEL),
                        PlayerRechargeRecord::STATUS_RECHARGED_REJECT => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_REJECT),
                        PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL),
                    ]);
                $filter->like()->text('remark')->placeholder(admin_trans('player_recharge_record.fields.remark'));
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_recharge_record.fields.money'));
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
                $filter->form()->hidden('finish_time_start');
                $filter->form()->hidden('finish_time_end');
                $filter->form()->dateTimeRange('finish_time_start', 'finish_time_end', '')->placeholder([
                    admin_trans('player_recharge_record.fields.finish_time'),
                    admin_trans('player_recharge_record.fields.finish_time')
                ]);

            });
            $grid->expandFilter();
        });
    }

    /**
     * 收款账号信息
     * @param $setting_id
     * @return Detail
     */
    public function settingInfo($setting_id): Detail
    {
        /** @var ChannelRechargeSetting $channelRechargeSetting */
        $channelRechargeSetting = ChannelRechargeSetting::query()->where('id', $setting_id)->withTrashed()->first();
        return Detail::create($channelRechargeSetting, function (Detail $detail) use ($channelRechargeSetting) {
            switch ($channelRechargeSetting->type) {
                case ChannelRechargeMethod::TYPE_USDT:
                    $detail->item('wallet_address', admin_trans('channel_recharge_setting.fields.wallet_address'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    $detail->item('max', admin_trans('channel_recharge_method.fields.amount_limit'))->display(function (
                        $val,
                        ChannelRechargeSetting $data
                    ) {
                        if ($data->max > 0 || $data->min > 0) {
                            return $val . $data['status'];
                        }
                        return '---';
                    });
                    break;
                case ChannelRechargeMethod::TYPE_ALI:
                    $detail->item('account', admin_trans('channel_recharge_method.ali_account'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    $detail->item('max', admin_trans('channel_recharge_method.fields.amount_limit'))->display(function (
                        $val,
                        ChannelRechargeSetting $data
                    ) {
                        if ($data->max > 0 || $data->min > 0) {
                            return $val . $data['status'];
                        }
                        return '---';
                    });
                    break;
                case ChannelRechargeMethod::TYPE_WECHAT:
                    $detail->item('account', admin_trans('channel_recharge_method.wechat_account'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    $detail->item('max', admin_trans('channel_recharge_method.fields.amount_limit'))->display(function (
                        $val,
                        ChannelRechargeSetting $data
                    ) {
                        if ($data->max > 0 || $data->min > 0) {
                            return $val . $data['status'];
                        }
                        return '---';
                    });
                    break;
                case ChannelRechargeMethod::TYPE_BANK:
                    $detail->item('name', admin_trans('channel_recharge_setting.fields.name'));
                    $detail->item('account', admin_trans('channel_recharge_setting.fields.account'));
                    $detail->item('max', admin_trans('channel_recharge_method.fields.amount_limit'))->display(function (
                        $val,
                        ChannelRechargeSetting $data
                    ) {
                        if ($data->max > 0 || $data->min > 0) {
                            return $val . $data['status'];
                        }
                        return '---';
                    });
                    break;
            }
        })->bordered()->layout('vertical');
    }

    /**
     * 充值审核
     * @group channel
     * @auth true
     */
    public function examineList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_recharge_record.examine_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $requestFilter = Request::input('ex_admin_filter', []);
            $tradeno = Request::input('tradeno', []);
            if (!empty($tradeno)) {
                $grid->model()->where('tradeno', $tradeno);
            }
            $grid->model()->with([
                'player',
                'channel_recharge_setting',
                'player.national_promoter.level_list.national_level'
            ])->where('type',
                PlayerRechargeRecord::TYPE_SELF)->whereIn('status', [
                PlayerRechargeRecord::STATUS_RECHARGING,
                PlayerRechargeRecord::STATUS_WAIT,
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS,
                PlayerRechargeRecord::STATUS_RECHARGED_REJECT,
                PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL
            ])->orderBy('created_at', 'desc');
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center');
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy();
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->copy();
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('level_list.national_level.name',
                admin_trans('national_promoter.level_list.name'))->display(function (
                $value,
                PlayerRechargeRecord $data
            ) {
                if (isset($data->player->national_promoter->level_list->national_level) && !empty($data->player->national_promoter->level_list->national_level)) {
                    return $data->player->national_promoter->level_list->national_level->name . $data->player->national_promoter->level_list->level;
                }
                return '';
            });
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . $data->currency;
            })->align('center');
            $grid->column('channel_recharge_setting.type', admin_trans('channel_recharge_method.fields.type'))
                ->display(function ($val, PlayerRechargeRecord $data) {
                    switch ($val) {
                        case ChannelRechargeMethod::TYPE_USDT:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#55acee');
                            break;
                        case ChannelRechargeMethod::TYPE_ALI:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#3b5999');
                            break;
                        case ChannelRechargeMethod::TYPE_WECHAT:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#87d068');
                            break;
                        case ChannelRechargeMethod::TYPE_BANK:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#cd201f');
                            break;
                        default:
                            return '';
                    }
                    return Html::create($tag)
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'settingInfo'],
                            ['setting_id' => $data->setting_id])
                        ->width('40%')->title(admin_trans('channel_recharge_setting.recharge_setting_info'));
                })
                ->align('center');
            $grid->column('recharge_setting_info',
                admin_trans('channel_recharge_setting.recharge_setting_info'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $rechargeSetting = $data->channel_recharge_setting;
                $info = [];
                if (!empty($rechargeSetting)) {
                    switch ($rechargeSetting->type) {
                        case ChannelRechargeMethod::TYPE_USDT:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.wallet_address') . ': ' . $rechargeSetting->wallet_address);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_ALI:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.ali_account') . ': ' . $rechargeSetting->account);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_WECHAT:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.wechat_account') . ': ' . $rechargeSetting->account);
                            $info[] = Html::div()->content(Image::create()
                                ->width(40)
                                ->src($rechargeSetting->qr_code));
                            break;
                        case ChannelRechargeMethod::TYPE_BANK:
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.name') . ': ' . $rechargeSetting->name);
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.bank_name') . ': ' . $rechargeSetting->bank_name);
                            $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.account') . ': ' . $rechargeSetting->account);
                            break;
                    }
                }
                return Html::create()->content($info);
            })->align('left');
            $grid->column('player_bank_info', admin_trans('player_bank.title'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                /** @var PlayerBank $bank */
                $bank = $data->player->bankCard->where('status', 1)->where('type',
                    ChannelRechargeMethod::TYPE_BANK)->first();
                if (!empty($bank)) {
                    return Html::create()->content([
                        Html::markdown('- ' . admin_trans('player_bank.fields.bank_name') . ': ' . $bank->bank_name ?? ''),
                        Html::markdown('- ' . admin_trans('player_bank.fields.account') . ': ' . $bank->account ?? ''),
                        Html::markdown('- ' . admin_trans('player_bank.fields.account_name') . ': ' . $bank->account_name ?? ''),
                    ]);
                }
                return '';
            })->align('left');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center');
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::STATUS_WAIT:
                        return Tag::create(admin_trans('player_recharge_record.status_wait'))
                            ->color('#108ee9');
                    case PlayerRechargeRecord::STATUS_RECHARGING:
                        return Tag::create(admin_trans('player_recharge_record.status_examine'))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return Tag::create(admin_trans('player_recharge_record.status_success'))
                            ->color('#87d068');
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return Tag::create(admin_trans('player_recharge_record.status_fail'))
                            ->color('#f50');
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status_cancel'))
                            ->color('#2db7f5');
                    case PlayerRechargeRecord::STATUS_RECHARGED_REJECT:
                        return Tag::create(admin_trans('player_recharge_record.status_reject'))
                            ->color('#2db7f5');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status_system_cancel'))
                            ->color('#2db7f5');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('remark', admin_trans('player_recharge_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_recharge_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('reject_reason',
                admin_trans('player_recharge_record.fields.reject_reason'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->tip()->width('150px')->align('center');
            $grid->column('created_at',
                admin_trans('player_recharge_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, PlayerRechargeRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('player_recharge_record.btn.action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);

                $dropdown->item(admin_trans('player_recharge_record.btn.upload_recharge_certificate'), 'far fa-file-image')
                    ->modal([$this, 'uploadCertificate'], ['id' => $data->id]);

                $dropdown->item(admin_trans('player_recharge_record.btn.view_recharge_certificate'),
                    'far fa-file-image')
                    ->modal($this->rechargeCertificate([
                        'tradeno' => $data->tradeno,
                        'certificate' => $data->certificate,
                    ]))->title(admin_trans('player_recharge_record.view_recharge_certificate_title', null,
                        ['{tradeno}' => $data->tradeno]));

                $dropdown->item(admin_trans('player_recharge_record.btn.examine_pass'), 'SafetyCertificateOutlined')
                    ->modal([$this, 'passForm'], ['id' => $data->id]);

                $dropdown->item(admin_trans('player_recharge_record.btn.examine_reject'), 'WarningFilled')
                    ->modal([$this, 'reject'], ['id' => $data->id]);
                $actions->prepend(
                    $dropdown
                );
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.player_id'))
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.status'))
                    ->options([
                        PlayerRechargeRecord::STATUS_WAIT => admin_trans('player_recharge_record.status_wait'),
                        PlayerRechargeRecord::STATUS_RECHARGING => admin_trans('player_recharge_record.status_examine'),
                        PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS => admin_trans('player_recharge_record.status_examine_pass'),
                        PlayerRechargeRecord::STATUS_RECHARGED_REJECT => admin_trans('player_recharge_record.status_examine_reject'),
                        PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL => admin_trans('player_recharge_record.status_system_cancel'),
                    ]);
                $filter->eq()->select('channel_recharge_setting.type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel_recharge_method.fields.type'))
                    ->options([
                        ChannelRechargeMethod::TYPE_USDT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_USDT),
                        ChannelRechargeMethod::TYPE_ALI => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_ALI),
                        ChannelRechargeMethod::TYPE_WECHAT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_WECHAT),
                        ChannelRechargeMethod::TYPE_BANK => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_BANK),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_recharge_record.fields.money'));
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
     * 上传付款凭证
     * @auth true
     * @group channel
     */
    public function uploadCertificate()
    {
        return Form::create(new $this->model, function (Form $form) {
            $content = admin_trans('player_recharge_record.btn.examine_pass_confirm');
            $form->push(Html::markdown(":::tip
{$content}
:::"));
            $form->image('certificate', admin_trans('player_recharge_record.fields.certificate'))
                ->required();
            $form->text('remark', admin_trans('player_recharge_record.fields.remark'))->required();

            $form->saving(function (Form $form) {
                $data = $form->input();
                return $this->pass($data['id'],$data['remark'], $data['certificate']);
            });
        });
    }
    /**
     * 查看付款凭证
     * @auth true
     * @group channel
     * @param $data
     * @return Detail
     */
    public function rechargeCertificate($data): Detail
    {
        return Detail::create($data, function (Detail $detail) {
            $detail->item('certificate')->display(function ($val) {
                if (!empty($val)) {
                    $image = Image::create()
                        ->width(100)
                        ->height(100)
                        ->style(['objectFit' => 'cover'])
                        ->src($val);
                }
                return Html::create()->content([
                    $image ?? EmptyStatus::create()->style(['margin' => '0 160px !important'])
                ])->style(['margin' => '0 auto']);
            });
        })->column(1);
    }

    /**
     * 充值订单审核拒绝
     * @auth true
     * @group channel
     * @param $id
     * @return Form
     */
    public function reject($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->textarea('reject_reason')->rows(5)->required();
            $form->saving(function (Form $form) use ($id) {
                /** @var PlayerRechargeRecord $playerRechargeRecord */
                $playerRechargeRecord = $this->model::find($id);
                if (empty($playerRechargeRecord)) {
                    return message_error(admin_trans('player_recharge_record.not_fount'));
                }
                if ($playerRechargeRecord->type != PlayerRechargeRecord::TYPE_SELF && $playerRechargeRecord->type != PlayerRechargeRecord::TYPE_GB) {
                    return message_error(admin_trans('player_recharge_record.recharge_record_error'));
                }
                switch ($playerRechargeRecord->status) {
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return message_warning(admin_trans('player_recharge_record.recharge_record_has_pass'));
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return message_warning(admin_trans('player_recharge_record.recharge_record_has_fail'));
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                        return message_warning(admin_trans('player_recharge_record.recharge_record_has_cancel'));
                    case PlayerRechargeRecord::STATUS_RECHARGED_REJECT:
                        return message_warning(admin_trans('player_recharge_record.recharge_record_has_reject'));
                    case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                        return message_warning(admin_trans('player_recharge_record.recharge_record_has_system_cancel'));
                }
                try {
                    // 生成订单
                    $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_REJECT;
                    $playerRechargeRecord->reject_reason = $form->input('reject_reason');
                    $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
                    $playerRechargeRecord->user_id = Admin::id() ?? 0;
                    $playerRechargeRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                    if ($playerRechargeRecord->save()) {
                        saveChannelFinancialRecord($playerRechargeRecord,
                            ChannelFinancialRecord::ACTION_RECHARGE_REJECT);
                    }
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerRechargeRecord->player_id;
                    $notice->source_id = $playerRechargeRecord->id;
                    $notice->type = Notice::TYPE_RECHARGE_REJECT;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = '充值稽核不通過';
                    $notice->content = '抱歉您的充值訂單稽核不通過，原因是: ' . $playerRechargeRecord->reject_reason;
                    $notice->save();
                } catch (\Exception) {
                    return message_error(admin_trans('player_recharge_record.action_error'));
                }
                return message_success(admin_trans('player_recharge_record.action_success'));
            });
        });
    }

    public function passForm()
    {
        return Form::create(new $this->model, function (Form $form) {
            $content = admin_trans('player_recharge_record.btn.examine_pass_confirm');
            $form->push(Html::markdown(":::tip
{$content}
:::"));
            $form->text('remark', admin_trans('player_recharge_record.fields.remark'))->required();

            $form->saving(function (Form $form) {
                $data = $form->input();
                return $this->pass($data['id'],$data['remark']);
            });
        });
    }

    /**
     * 充值订单审核通过
     * @param $id
     * @param $remark
     * @param string $certificate
     * @return Msg
     * @auth true
     * @group channel
     */
    public function pass($id, $remark, string $certificate = ''): Msg
    {
        /** @var PlayerRechargeRecord $playerRechargeRecord */
        $playerRechargeRecord = $this->model::find($id);
        if (empty($playerRechargeRecord)) {
            return message_error(admin_trans('player_recharge_record.not_fount'));
        }
        if ($playerRechargeRecord->type != PlayerRechargeRecord::TYPE_SELF) {
            return message_error(admin_trans('player_recharge_record.recharge_record_error'));
        }
        switch ($playerRechargeRecord->status) {
            case PlayerRechargeRecord::STATUS_WAIT:
                if(!empty($certificate)){
                    break;
                }
                return message_warning(admin_trans('player_recharge_record.recharge_record_not_complete'));
            case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                return message_warning(admin_trans('player_recharge_record.recharge_record_has_pass'));
            case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                return message_warning(admin_trans('player_recharge_record.recharge_record_has_fail'));
            case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                return message_warning(admin_trans('player_recharge_record.recharge_record_has_cancel'));
            case PlayerRechargeRecord::STATUS_RECHARGED_REJECT:
                return message_warning(admin_trans('player_recharge_record.recharge_record_has_reject'));
            case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                return message_warning(admin_trans('player_recharge_record.recharge_record_has_system_cancel'));
        }
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (empty($channel)) {
            return message_error(admin_trans('channel.not_fount'));
        }
        DB::beginTransaction();
        try {
            /** @var PlayerPlatformCash $playerWallet */
            $playerWallet = PlayerPlatformCash::query()->where('player_id',
                $playerRechargeRecord->player_id)->lockForUpdate()->first();
            $beforeGameAmount = $playerWallet->money;
            $firstRecharge = PlayerRechargeRecord::query()
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->where('player_id', $playerRechargeRecord->player_id)
                ->first();
            // 生成订单
            $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS;
            $playerRechargeRecord->remark = $remark??'';
            $playerRechargeRecord->certificate = $certificate ?? '';
            $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
            $playerRechargeRecord->user_id = Admin::id() ?? 0;
            $playerRechargeRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
            // 更新钱包
            $playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);
            $playerWallet->save();
            /** @var Player $player */
            $player = Player::query()->find($playerRechargeRecord->player_id);
            //全民代理首充返佣
            if (!isset($firstRecharge) && !empty($player->recommend_id) && $channel->national_promoter_status == 1) {
                //玩家上级推广员信息
                /** @var Player $recommendPlayer */
                $recommendPlayer = Player::query()->find($playerRechargeRecord->player->recommend_id);
                /** @var NationalPromoter $recommendNationalPromoter */
                $recommendNationalPromoter = NationalPromoter::query()->with(['level_list'])->where('uid',
                    $player->recommend_id)->first();
                //首冲成功之后激活全民代理身份
                /** @var NationalPromoter $nationalPromoter */
                $nationalPromoter = NationalPromoter::query()->where('uid',$playerRechargeRecord->player_id)->first();
                $nationalPromoter->created_at = $playerRechargeRecord->finish_time;
                $nationalPromoter->status = 1;
                $nationalPromoter->save();
                //推广员为全民代理
                if (!empty($recommendNationalPromoter) && $recommendPlayer->is_promoter < 1) {
                    //首充返佣金额
                    /** @var PlayerPlatformCash $recommendPlayerWallet */
                    $recommendPlayerWallet = PlayerPlatformCash::query()->where('player_id',
                        $recommendPlayer->id)->lockForUpdate()->first();
                    $beforeRechargeAmount = $recommendPlayerWallet->money;
                    $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money,
                        $recommendNationalPromoter->level_list->recharge_ratio, 2);
                    //寫入首充金流明細
                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $recommendPlayer->id;
                    $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                    $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
                    $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE_REWARD;
                    $playerDeliveryRecord->source = 'national_promoter';
                    $playerDeliveryRecord->amount = $recommendNationalPromoter->level_list->recharge_ratio;
                    $playerDeliveryRecord->amount_before = $beforeRechargeAmount;
                    $playerDeliveryRecord->amount_after = $recommendPlayerWallet->money;
                    $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                    $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                    $playerDeliveryRecord->save();

                    //首冲成功之后全民代理邀请奖励
                    $recommendNationalPromoter->invite_num = bcadd($recommendNationalPromoter->invite_num, 1, 0);
                    $recommendNationalPromoter->settlement_amount = bcadd($recommendNationalPromoter->invite_num,
                        $recommendNationalPromoter->level_list->recharge_ratio, 2);
                    $recommendNationalPromoter->save();
                    /** @var NationalInvite $nationalInvite */
                    $nationalInvite = NationalInvite::query()
                        ->where('min', '<=', $recommendNationalPromoter->invite_num)
                        ->where('max', '>=', $recommendNationalPromoter->invite_num)
                        ->first();
                    if (!empty($nationalInvite) && $nationalInvite->interval > 0 && $recommendNationalPromoter->invite_num % $nationalInvite->interval == 0) {
                        $money = $nationalInvite->money;
                        $amountBefore = $recommendPlayerWallet->money;
                        $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $money, 2);
                        // 寫入金流明細
                        $playerDeliveryRecord = new PlayerDeliveryRecord;
                        $playerDeliveryRecord->player_id = $recommendPlayer->id;
                        $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                        $playerDeliveryRecord->target = $nationalInvite->getTable();
                        $playerDeliveryRecord->target_id = $nationalInvite->id;
                        $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_NATIONAL_INVITE;
                        $playerDeliveryRecord->source = 'national_promoter';
                        $playerDeliveryRecord->amount = $money;
                        $playerDeliveryRecord->amount_before = $amountBefore;
                        $playerDeliveryRecord->amount_after = $recommendPlayerWallet->money;
                        $playerDeliveryRecord->tradeno = '';
                        $playerDeliveryRecord->remark = '';
                        $playerDeliveryRecord->save();
                    }

                    $nationalProfitRecord = new NationalProfitRecord();
                    $nationalProfitRecord->uid = $playerRechargeRecord->player_id;
                    $nationalProfitRecord->recommend_id = $playerRechargeRecord->player->recommend_id;
                    $nationalProfitRecord->money = $recommendNationalPromoter->level_list->recharge_ratio;
                    $nationalProfitRecord->type = 0;
                    $nationalProfitRecord->status = 1;
                    $nationalProfitRecord->save();
                    $playerRechargeRecord->recharge_ratio = $recommendNationalPromoter->level_list->recharge_ratio;
                    $recommendPlayerWallet->save();
                }
            }
            /** @var PlayerExtend $playerExtend */
            $playerExtend = PlayerExtend::query()->where('player_id', $player->id)->first();
            $playerExtend->recharge_amount = bcadd($playerExtend->recharge_amount, $playerRechargeRecord->point, 2);
            $playerExtend->save();
            $playerRechargeRecord->save();
            //寫入金流明細
            $playerDeliveryRecord = new PlayerDeliveryRecord;
            $playerDeliveryRecord->player_id = $playerRechargeRecord->player_id;
            $playerDeliveryRecord->department_id = $playerRechargeRecord->department_id;
            $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
            $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
            $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE;
            $playerDeliveryRecord->source = 'self_recharge';
            $playerDeliveryRecord->amount = $playerRechargeRecord->point;
            $playerDeliveryRecord->amount_before = $beforeGameAmount;
            $playerDeliveryRecord->amount_after = $playerWallet->money;
            $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
            $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
            $playerDeliveryRecord->save();
            // 更新渠道信息
            $channel->recharge_amount = bcadd($channel->recharge_amount, $playerRechargeRecord->point, 2);
            $channel->save();
            saveChannelFinancialRecord($playerRechargeRecord, ChannelFinancialRecord::ACTION_RECHARGE_PASS);
            // 发送站内信
            $notice = new Notice();
            $notice->department_id = Admin::user()->department_id;
            $notice->player_id = $playerRechargeRecord->player_id;
            $notice->source_id = $playerRechargeRecord->id;
            $notice->type = Notice::TYPE_RECHARGE_PASS;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $notice->title = '充值稽核通過';
            $notice->content = '本次提交已通過審核，上分 ' . $playerRechargeRecord->point . ' ，請查收。';
            $notice->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('审核通过失败', [$e->getTrace()]);
            return message_error(admin_trans('player_recharge_record.action_error'));
        }

        return message_success(admin_trans('player_recharge_record.action_success'));
    }

    /**
     * QTalk充值
     * @group channel
     * @auth true
     */
    public function talkIndex(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_recharge_record.talk_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player'])->where('type', PlayerRechargeRecord::TYPE_THIRD)->orderBy('created_at',
                'desc');
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(point) as total_point')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_point']) ? floatval($totalData['total_point']) : 0)->prefix(admin_trans('player_recharge_record.total_data.total_point'))->valueStyle([
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
            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center');
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy()->align('center');
            $grid->column('talk_tradeno',
                admin_trans('player_recharge_record.fields.talk_tradeno'))->copy()->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->copy();
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center');
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::STATUS_WAIT:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#108ee9');
                    case PlayerRechargeRecord::STATUS_RECHARGING:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#87d068');
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#f50');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SYSTEM_CANCEL:
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#2db7f5');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('remark', admin_trans('player_recharge_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_recharge_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('finish_time',
                admin_trans('player_recharge_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_recharge_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.player_id'))
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.status'))
                    ->options([
                        PlayerRechargeRecord::STATUS_WAIT => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_WAIT),
                        PlayerRechargeRecord::STATUS_RECHARGING => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGING),
                        PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS),
                        PlayerRechargeRecord::STATUS_RECHARGED_FAIL => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_FAIL),
                        PlayerRechargeRecord::STATUS_RECHARGED_CANCEL => admin_trans('player_recharge_record.status.' . PlayerRechargeRecord::STATUS_RECHARGED_CANCEL),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));
                $filter->like()->text('talk_tradeno')->placeholder(admin_trans('player_recharge_record.fields.talk_tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_recharge_record.fields.money'));
                $filter->between()->dateTimeRange('created_at')->placeholder([
                    admin_trans('player_recharge_record.fields.created_at'),
                    admin_trans('player_recharge_record.fields.created_at')
                ]);
                $filter->between()->dateTimeRange('finish_time')->placeholder([
                    admin_trans('player_recharge_record.fields.finish_time'),
                    admin_trans('player_recharge_record.fields.finish_time')
                ]);

            });
            $grid->expandFilter();
        });
    }

    /**
     * 筛选玩家下拉
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

    /**
     * 币商充值
     * @group channel
     * @auth true
     */
    public function coinList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_recharge_record.coin_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player'])->where('type', PlayerRechargeRecord::TYPE_BUSINESS)->orderBy('created_at',
                'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['finish_time_start'])) {
                    $grid->model()->where('finish_time', '>=', $exAdminFilter['finish_time_start']);
                }
                if (!empty($exAdminFilter['finish_time_end'])) {
                    $grid->model()->where('finish_time', '<=', $exAdminFilter['finish_time_end']);
                }
                if (isset($exAdminFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['search_type']);
                    });
                }
            }

            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(point) as coin_recharge_total_point')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['coin_recharge_total_point']) ? floatval($totalData['coin_recharge_total_point']) : 0)->prefix(admin_trans('player_recharge_record.total_data.coin_recharge_total_point'))->valueStyle([
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

            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center');
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, PlayerRechargeRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val)
                    ]);
                })
                ->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerRechargeRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy()->align('center');
            $grid->column('type', admin_trans('player_recharge_record.fields.type'))->display(function ($val) {
                return Tag::create(admin_trans('player_recharge_record.type.' . $val))->color('#cd201f');
            })->align('center');
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center');
            $grid->column('user_name', admin_trans('player_recharge_record.fields.user_name'))->align('center');
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::STATUS_WAIT:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#108ee9');
                    case PlayerRechargeRecord::STATUS_RECHARGING:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#87d068');
                    case PlayerRechargeRecord::STATUS_RECHARGED_FAIL:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#f50');
                    case PlayerRechargeRecord::STATUS_RECHARGED_CANCEL:
                        return Tag::create(admin_trans('player_recharge_record.status.' . $val))
                            ->color('#2db7f5');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('remark', admin_trans('player_recharge_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_recharge_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('finish_time',
                admin_trans('player_recharge_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_recharge_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.player_id'))
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_recharge_record.fields.money'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                $filter->form()->hidden('finish_time_start');
                $filter->form()->hidden('finish_time_end');
                $filter->form()->dateTimeRange('finish_time_start', 'finish_time_end', '')->placeholder([
                    admin_trans('player_recharge_record.fields.finish_time'),
                    admin_trans('player_recharge_record.fields.finish_time')
                ]);
            });
        });
    }
}

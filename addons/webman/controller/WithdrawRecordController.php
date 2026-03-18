<?php

namespace addons\webman\controller;

use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\Player;
use addons\webman\model\PlayerTag;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\badge\Badge;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Cache;

/**
 * 提现记录
 */
class WithdrawRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_withdraw_record_model');
    }

    /**
     * 提現
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.title'));
            $grid->model()->with(['player', 'channel', 'player.player_extend'])->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['currency'])) {
                    $grid->model()->whereIn('currency', $exAdminFilter['currency']);
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
                if (!empty($exAdminFilter['bank_type'])) {
                    $grid->model()->where('bank_type', $exAdminFilter['bank_type']);
                }
                if (!empty($exAdminFilter['player']['uuid'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                    });
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
                if (!empty($exAdminFilter['remark'])) {
                    $grid->model()->where('remark',  'like', '%' . $exAdminFilter['remark'] . '%');
                }
                if (isset($exAdminFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['search_type']);
                    });
                }
            }
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'WithdrawRecord',
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center')->fixed(true);
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player->uuid)
                ]);
            })->align('center')->fixed(true);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerWithdrawRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('player.recommend_player.uuid', admin_trans('player.recommend_uuid'))->copy();
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player_phone'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $image = (isset($data->player->avatar) && !empty($data->player->avatar)) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ])->style(['cursor' => 'pointer'])->modal($this->playerDetail([
                    'phone' => $data->player->phone ?? '',
                    'name' => $data->player->name ?? '',
                    'address' => $data->player->player_extend->address ?? '',
                    'email' => $data->player->player_extend->email ?? '',
                    'line' => $data->player->player_extend->line ?? '',
                    'created_at' => isset($data->player->created_at) && !empty($data->player->created_at) ? date('Y-m-d H:i:s',
                        strtotime($data->player->created_at)) : '',
                ]));
            })->align('center');
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy()->align('center');
            $grid->column('talk_tradeno',
                admin_trans('player_withdraw_record.fields.talk_tradeno'))->copy()->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('bank_type', admin_trans('player_withdraw_record.fields.bank_type'))
                ->display(function ($val, PlayerWithdrawRecord $data) {
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
                        case ChannelRechargeMethod::TYPE_COIN:
                            $tag = Tag::create(admin_trans('channel_recharge_method.type.' . $val))
                                ->color('#f50');
                            break;
                        default:
                            return '';
                    }
                    return Html::create($tag)
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'settingInfo'],
                            ['id' => $data->id])
                        ->width('40%')->title(admin_trans('player_withdraw_record.withdraw_setting_info'));
                })
                ->align('center');
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerWithdrawRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerWithdrawRecord::TYPE_ARTIFICIAL:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#cd201f');
                    case PlayerWithdrawRecord::TYPE_COIN:
                    case PlayerWithdrawRecord::TYPE_GB:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#87d068');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))
                ->display(function ($value, PlayerWithdrawRecord $data) {
                    $rejectReason = $data->reject_reason;
                    switch ($value) {
                        case PlayerWithdrawRecord::STATUS_SUCCESS:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SUCCESS))->color('#87d068');
                            break;
                        case PlayerWithdrawRecord::STATUS_WAIT:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status_wait'))->color('#108ee9');
                            break;
                        case PlayerWithdrawRecord::STATUS_FAIL:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_FAIL))->color('#f50');
                            break;
                        case PlayerWithdrawRecord::STATUS_PENDING_REJECT:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_PENDING_REJECT))->color('#cd201f');
                            break;
                        case PlayerWithdrawRecord::STATUS_PENDING_PAYMENT:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_PENDING_PAYMENT))->color('#3b5999');
                            break;
                        case PlayerWithdrawRecord::STATUS_CANCEL:
                        case PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_CANCEL))->color('#2db7f5');
                            break;
                        default:
                            $tag = '';
                    }
                    if (!empty($rejectReason)) {
                        return ToolTip::create(Badge::create(
                            $tag
                        )->count('!')->title(''))->title($rejectReason)->color('orange');
                    } else {
                        return $tag;
                    }
                })->align('center')->sortable();
            $grid->column('withdraw_setting_info',
                admin_trans('player_withdraw_record.withdraw_setting_info'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $info = [];
                switch ($data->bank_type) {
                    case ChannelRechargeMethod::TYPE_USDT:
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.account_name') . ': ' . $data->account_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.wallet_address') . ': ' . $data->wallet_address);
                        $info[] = Html::div()->content(Image::create()
                            ->width(40)
                            ->src($data->qr_code));
                        break;
                    case ChannelRechargeMethod::TYPE_ALI:
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.ali_account') . ': ' . $data->account);
                        $info[] = Html::div()->content(Image::create()
                            ->width(40)
                            ->src($data->qr_code));
                        break;
                    case ChannelRechargeMethod::TYPE_WECHAT:
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.wechat_account') . ': ' . $data->account);
                        $info[] = Html::div()->content(Image::create()
                            ->width(40)
                            ->src($data->qr_code));
                        break;
                    case ChannelRechargeMethod::TYPE_BANK:
                    case ChannelRechargeMethod::TYPE_GB:
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.bank_name') . ': ' . $data->bank_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.account') . ': ' . $data->account);
                        break;
                }
                return Html::create()->content($info);
            })->align('left');
            $grid->column('channel.name', admin_trans('player_present_record.fields.department_id'))->align('center');
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
            $grid->column('player_tag', admin_trans('player_withdraw_record.fields.player_tag'))
                ->display(function ($value) {
                    return $this->handleTagIds($value);
                })
                ->editable(
                    Editable::checkboxTag()
                        ->options($this->getPlayerTagOptionsFilter())
                )->width('150px');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $action, PlayerWithdrawRecord $data) use ($grid) {
                $action->hideDel();
                $action->hideEdit();
                $action->prepend([
                    Button::create(admin_trans('machine_report.details'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal('ex-admin/addons-webman-controller-PlayerController/playerRecord', [
                            ['id' => $data->player_id],
                        ])->width('70%')->title(admin_trans('player.fields.uuid') . ': ' . $data->player->uuid)
                ]);
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.type'))
                    ->options([
                        PlayerWithdrawRecord::TYPE_THIRD => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_THIRD),
                        PlayerWithdrawRecord::TYPE_SELF => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_SELF),
                        PlayerWithdrawRecord::TYPE_ARTIFICIAL => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_ARTIFICIAL),
                        PlayerWithdrawRecord::TYPE_GB => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_GB),
                        PlayerWithdrawRecord::TYPE_COIN => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_COIN),
                    ]);
                $filter->eq()->select('bank_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.bank_type'))
                    ->options([
                        ChannelRechargeMethod::TYPE_USDT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_USDT),
                        ChannelRechargeMethod::TYPE_ALI => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_ALI),
                        ChannelRechargeMethod::TYPE_WECHAT => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_WECHAT),
                        ChannelRechargeMethod::TYPE_BANK => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_BANK),
                        ChannelRechargeMethod::TYPE_GB => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_GB),
                        ChannelRechargeMethod::TYPE_COIN => admin_trans('channel_recharge_method.type.' . ChannelRechargeMethod::TYPE_COIN),
                    ]);
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.status'))
                    ->options([
                        PlayerWithdrawRecord::STATUS_WAIT => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_WAIT),
                        PlayerWithdrawRecord::STATUS_SUCCESS => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SUCCESS),
                        PlayerWithdrawRecord::STATUS_FAIL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_FAIL),
                        PlayerWithdrawRecord::STATUS_PENDING_PAYMENT => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_PENDING_PAYMENT),
                        PlayerWithdrawRecord::STATUS_PENDING_REJECT => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_PENDING_REJECT),
                        PlayerWithdrawRecord::STATUS_CANCEL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_CANCEL),
                        PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL),
                    ]);
                $filter->in()->select('currency')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('player_withdraw_record.fields.currency'))
                    ->options(plugin()->webman->config('currency'))
                    ->multiple();
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->like()->text('talk_tradeno')->placeholder(admin_trans('player_withdraw_record.fields.talk_tradeno'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_withdraw_record.fields.remark'));
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
                    admin_trans('player_withdraw_record.fields.finish_time'),
                    admin_trans('player_withdraw_record.fields.finish_time')
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
     * 处理标签
     * @param array $value
     * @return Html
     */
    public function handleTagIds(array $value): Html
    {
        $options = $this->getPlayerTagOptions($value);
        $html = Html::create();
        foreach ($options as $option) {
            $html->content(
                Tag::create($option)
                    ->color('success')
            );
        }
        return $html;
    }

    /**
     * 获取玩家标签选项(筛选id)
     * @param array $ids
     * @return array
     */
    public function getPlayerTagOptions(array $ids = []): array
    {
        $idsStr = json_encode($ids);
        $cacheKey = md5("player_tag_options_ids_$idsStr");
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        } else {
            if (!empty($ids)) {
                $data = (new PlayerTag())->whereIn('id', $ids)->select(['name', 'id'])->get()->toArray();
                $data = $data ? array_column($data, 'name', 'id') : [];
                Cache::set($cacheKey, $data, 24 * 60 * 60);

                return $data;
            }
            return [];
        }
    }

    /**
     * 获取玩家标签(筛选id)
     * @return array
     */
    public function getPlayerTagOptionsFilter(): array
    {
        $cacheKey = "doc_player_tag_options_filter";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        } else {
            $data = (new PlayerTag())->select(['name', 'id'])->get()->toArray();
            $data = $data ? array_column($data, 'name', 'id') : [];
            Cache::set($cacheKey, $data, 24 * 60 * 60);

            return $data;
        }
    }

    /**
     * 收款账号信息
     * @param $id
     * @return Detail
     */
    public function settingInfo($id): Detail
    {
        /** @var PlayerWithdrawRecord $playerWithdrawRecord */
        $playerWithdrawRecord = PlayerWithdrawRecord::query()->where('id', $id)->first();
        return Detail::create($playerWithdrawRecord, function (Detail $detail) use ($playerWithdrawRecord) {
            switch ($playerWithdrawRecord->bank_type) {
                case ChannelRechargeMethod::TYPE_USDT:
                    $detail->item('wallet_address', admin_trans('channel_recharge_setting.fields.wallet_address'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    break;
                case ChannelRechargeMethod::TYPE_ALI:
                    $detail->item('account', admin_trans('channel_recharge_method.ali_account'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    break;
                case ChannelRechargeMethod::TYPE_WECHAT:
                    $detail->item('account', admin_trans('channel_recharge_method.wechat_account'));
                    $detail->item('qr_code', admin_trans('channel_recharge_setting.payment_code'))->image();
                    break;
                case ChannelRechargeMethod::TYPE_BANK:
                    $detail->item('account_name', admin_trans('player_withdraw_record.fields.account_name'));
                    $detail->item('bank_name', admin_trans('channel_recharge_setting.fields.bank_name'));
                    $detail->item('account', admin_trans('channel_recharge_setting.fields.account'));
                    break;
            }
        })->bordered()->layout('vertical');
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
     * 币商提现
     * @auth true
     */
    public function coinList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.coin_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player'])->where('type', PlayerWithdrawRecord::TYPE_COIN)->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
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
            $totalData = $query->selectRaw('sum(point) as coin_withdraw_total_point')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(
                            Statistic::create()
                                ->value(!empty($totalData['coin_withdraw_total_point']) ? floatval($totalData['coin_withdraw_total_point']) : 0)
                                ->prefix(admin_trans('player_withdraw_record.total_data.total_coin_inmoney'))
                                ->valueStyle([
                                    'font-size' => '14px',
                                    'font-weight' => '500',
                                    'text-align' => 'center'
                                ])
                        ),
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

            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player_phone'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, PlayerWithdrawRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val)
                    ]);
                })
                ->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerWithdrawRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy()->align('center');
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                return Tag::create(admin_trans('player_withdraw_record.type.' . $val))->color('#cd201f');
            })->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_withdraw_record.talk_currency') : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('user_name', admin_trans('player_withdraw_record.fields.user_name'))->align('center');
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('player_withdraw_record.status.' . $val))
                    ->color('#87d068');
            })->align('center');
            $grid->column('withdraw_setting_info',
                admin_trans('player_withdraw_record.withdraw_setting_info'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
                $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.bank_name') . ': ' . $data->bank_name);
                $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.account') . ': ' . $data->account);
                return Html::create()->content($info);
            })->align('left');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->sortable()->align('center');
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
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
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
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_withdraw_record.fields.money'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->hidden('finish_time_start');
                $filter->form()->hidden('finish_time_end');
                $filter->form()->dateTimeRange('finish_time_start', 'finish_time_end', '')->placeholder([
                    admin_trans('player_withdraw_record.fields.finish_time'),
                    admin_trans('player_withdraw_record.fields.finish_time')
                ]);
            });
        });
    }
}

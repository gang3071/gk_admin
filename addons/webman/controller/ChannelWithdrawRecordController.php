<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelFinancialRecord;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\GameType;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use app\exception\PaymentException;
use app\service\payment\EHpayService;
use app\service\payment\GBpayService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
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
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Db;

/**
 * 提现记录
 * @group channel
 */
class ChannelWithdrawRecordController
{
    protected $model;
    protected $rechargeModel;
    protected $gameLogModel;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_withdraw_record_model');
        $this->rechargeModel = plugin()->webman->config('database.player_recharge_record_model');
        $this->gameLogModel = plugin()->webman->config('database.player_game_log_model');
    }

    /**
     * 提现审核
     * @group channel
     * @auth true
     */
    public function examineList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.examine_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $tradeno = Request::input('tradeno', []);
            if (!empty($tradeno)) {
                $grid->model()->where('tradeno', $tradeno);
            }
            $grid->model()->with(['player', 'player.national_promoter.level_list.national_level'])->whereIn('type',
                [PlayerWithdrawRecord::TYPE_SELF, PlayerWithdrawRecord::TYPE_GB])->orderBy('created_at',
                'desc')->orderBy('status', 'asc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player->uuid),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->copy();
            $grid->column('level_list.national_level.name',
                admin_trans('national_promoter.level_list.name'))->display(function (
                $value,
                PlayerWithdrawRecord $data
            ) {
                if (isset($data->player->national_promoter->level_list->national_level) && !empty($data->player->national_promoter->level_list->national_level)) {
                    return $data->player->national_promoter->level_list->national_level->name . $data->player->national_promoter->level_list->level;
                }
                return '';
            });
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy();
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player_name),
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('withdraw_setting_info',
                admin_trans('player_withdraw_record.withdraw_setting_info'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $info = [];
                switch ($data->bank_type) {
                    case ChannelRechargeMethod::TYPE_USDT:
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
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerWithdrawRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerWithdrawRecord::TYPE_GB:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#87d068');
                    default:
                        return '';
                }
            })->align('center');
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
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, PlayerWithdrawRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('player_withdraw_record.btn.action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);

                $dropdown->item(admin_trans('player_withdraw_record.btn.view_channel_recharge_list'),
                    'AppstoreAddOutlined')
                    ->modal($this->viewRechargeList($data->player_id))->width('70%');
                $dropdown->item(admin_trans('player_withdraw_record.btn.view_game_list'), 'far fa-file-image')
                    ->modal($this->viewGameList($data->player_id))->width('70%');

                $dropdown->item(admin_trans('player_withdraw_record.btn.examine_pass'), 'SafetyCertificateOutlined')
                    ->modal([$this, 'passForm'], ['id' => $data->id]);

                $dropdown->item(admin_trans('player_withdraw_record.btn.examine_reject'), 'WarningFilled')
                    ->modal([$this, 'reject'], ['id' => $data->id]);
                $dropdown->item(admin_trans('machine_report.details'), 'fab fa-buffer')
                    ->modal('ex-admin/addons-webman-controller-ChannelPlayerController/playerRecord', [
                        ['id' => $data->player_id],
                    ])->width('70%')->title(admin_trans('player.fields.uuid') . ': ' . $data->player->uuid);
                $actions->prepend(
                    $dropdown
                );
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
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
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.type'))
                    ->options([
                        PlayerWithdrawRecord::TYPE_SELF => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_SELF),
                        PlayerWithdrawRecord::TYPE_ARTIFICIAL => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_ARTIFICIAL),
                        PlayerWithdrawRecord::TYPE_GB => admin_trans('player_withdraw_record.type.' . PlayerWithdrawRecord::TYPE_GB),
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
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_withdraw_record.fields.remark'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_withdraw_record.fields.money'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('player_withdraw_record.fields.created_at'),
                    admin_trans('player_withdraw_record.fields.created_at')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 查看充值
     * @group channel
     * @auth true
     */
    public function viewRechargeList($playerId = 0): Grid
    {
        return Grid::create(new $this->rechargeModel(), function (Grid $grid) use ($playerId) {
            $grid->title(admin_trans('player_recharge_record.talk_title'));
            $grid->model()->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->where('player_id',
                $playerId)->orderBy('created_at', 'desc');
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
            }
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
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
            $grid->column('user_name', admin_trans('player_recharge_record.fields.user_name'))->align('center');
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
            $grid->expandFilter();
        });
    }

    /**
     * 查看游戏记录
     * @group channel
     * @auth true
     */
    public function viewGameList($playerId = 0): Grid
    {
        return Grid::create(new $this->gameLogModel(), function (Grid $grid) use ($playerId) {
            $grid->model()->where('player_id', $playerId)->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (isset($exAdminFilter['date_start']) && !empty($exAdminFilter['date_start'])) {
                    $grid->model()->whereDate('created_at', '>=', $exAdminFilter['date_start']);
                }
                if (isset($exAdminFilter['date_end']) && !empty($exAdminFilter['date_end'])) {
                    $grid->model()->whereDate('created_at', '<=', $exAdminFilter['date_end']);
                }
            }
            $grid->title(admin_trans('player_game_log.title'));
            $grid->bordered();
            $grid->autoHeight();
            $grid->column(function (Grid $grid) {
                $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->phone;
                })->align('center');
                $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->uuid;
                })->align('center');
            }, admin_trans('player_game_log.player_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->machine->name;
                })->align('center');
                $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->machine->code;
                })->align('center');
                $grid->column('odds', admin_trans('player_game_log.fields.odds'))->align('center');
            }, admin_trans('player_game_log.machine_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('game_amount', admin_trans('player_game_log.fields.game_amount'))->display(function ($val
                ) {
                    return Html::create()->content([
                        $val > 0 ? '+' . $val : $val,
                    ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
                })->align('center');
                $grid->column('before_game_amount',
                    admin_trans('player_game_log.fields.before_game_amount'))->align('center');
                $grid->column('after_game_amount',
                    admin_trans('player_game_log.fields.after_game_amount'))->align('center');
            }, admin_trans('player_game_log.player_wallet_info'));
            $grid->column(function (Grid $grid) {
                $grid->column('open_point', admin_trans('player_game_log.fields.open_point'))->align('center');
                $grid->column('wash_point', admin_trans('player_game_log.fields.wash_point'))->align('center');
                $grid->column('pressure', admin_trans('player_game_log.fields.pressure'))->align('center');
                $grid->column('score', admin_trans('player_game_log.fields.score'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        $data->type == GameType::TYPE_SLOT ? $val : 0,
                    ]);
                })->align('center');
                $grid->column('score', admin_trans('player_game_log.fields.turn_point'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return Html::create()->content([
                        $data->type == GameType::TYPE_STEEL_BALL ? $val : 0,
                    ]);
                })->align('center');
            }, admin_trans('player_game_log.machine_data'));
            $grid->column('created_at', admin_trans('player_game_log.fields.create_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->form()->hidden('date_start');
                $filter->form()->hidden('date_end');
                $filter->form()->dateRange('date_start', 'date_end', '')->placeholder([
                    admin_trans('player_game_log.fields.date_start'),
                    admin_trans('player_game_log.fields.date_end')
                ]);
                $filter->select('date_type', admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
        });
    }

    /**
     * 提现打款
     * @group channel
     * @auth true
     */
    public function paymentList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.payment_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player'])->where('type', PlayerWithdrawRecord::TYPE_SELF)->where('status',
                PlayerWithdrawRecord::STATUS_PENDING_PAYMENT)->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player->uuid),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->copy();
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy();
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player_name),
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerWithdrawRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#3b5999');
                    default:
                        return '';
                }
            })->align('center');
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
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))
                ->display(function () {
                    return Html::create()->content([
                        Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_PENDING_PAYMENT))->color('#3b5999')
                    ]);
                })->sortable();
            $grid->column('withdraw_setting_info',
                admin_trans('player_withdraw_record.withdraw_setting_info'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $info = [];
                switch ($data->bank_type) {
                    case ChannelRechargeMethod::TYPE_USDT:
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
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_method.wechat_account') . ': ' . $data->account);
                        $info[] = Html::div()->content(Image::create()
                            ->width(40)
                            ->src($data->qr_code));
                        break;
                    case ChannelRechargeMethod::TYPE_BANK:
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.bank_name') . ': ' . $data->bank_name);
                        $info[] = Html::markdown('- ' . admin_trans('channel_recharge_setting.fields.account') . ': ' . $data->account);
                        break;
                }
                return Html::create()->content($info);
            })->align('left');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->tip()->width('150px')->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, PlayerWithdrawRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $actions->prepend(
                    Button::create(admin_trans('player_withdraw_record.btn.complete_payment'))
                        ->type('danger')
                        ->modal($this->payment($data->id))
                );
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_withdraw_record.fields.money'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('player_withdraw_record.fields.created_at'),
                    admin_trans('player_withdraw_record.fields.created_at')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 提交打款凭证
     * @auth true
     * @group channel
     * @param $id
     * @return Form
     */
    public function payment($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->file('certificate')
                ->ext('jpg,png,jpeg')
                ->type('image')
                ->fileSize('2m')
                ->hideFinder()
                ->paste()
                ->style(['margin-left' => '35%', 'margin-bottom' => '16px'])
                ->help(Html::create()->content(admin_trans('player_withdraw_record.certificate_help'))->style([
                    'margin-left' => '135px',
                    'display' => 'block',
                    'width' => '235px'
                ]));
            $form->saving(function (Form $form) use ($id) {
                if (empty($form->input('certificate'))) {
                    return message_warning(admin_trans('player_withdraw_record.certificate_required'));
                }
                /** @var PlayerWithdrawRecord $playerWithdrawRecord */
                $playerWithdrawRecord = $this->model::find($id);
                if (empty($playerWithdrawRecord)) {
                    return message_error(admin_trans('player_withdraw_record.not_fount'));
                }
                if ($playerWithdrawRecord->type != PlayerWithdrawRecord::TYPE_SELF) {
                    return message_error(admin_trans('player_withdraw_record.withdraw_record_error'));
                }
                switch ($playerWithdrawRecord->status) {
                    case PlayerWithdrawRecord::STATUS_WAIT:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_not_examine'));
                    case PlayerWithdrawRecord::STATUS_SUCCESS:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_complete'));
                    case PlayerWithdrawRecord::STATUS_FAIL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_fail'));
                    case PlayerWithdrawRecord::STATUS_CANCEL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_cancel'));
                    case PlayerWithdrawRecord::STATUS_PENDING_REJECT:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_reject'));
                    case PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_system_cancel'));
                }
                if ($playerWithdrawRecord->status != PlayerWithdrawRecord::STATUS_PENDING_PAYMENT) {
                    return message_error(admin_trans('player_withdraw_record.withdraw_record_status_error'));
                }
                /** @var Channel $channel */
                $channel = Channel::where('department_id', Admin::user()->department_id)->first();
                if (empty($channel)) {
                    return message_error(admin_trans('channel.not_fount'));
                }
                try {
                    // 更新订单
                    $playerWithdrawRecord->certificate = $form->input('certificate');
                    $playerWithdrawRecord->status = PlayerWithdrawRecord::STATUS_SUCCESS;
                    $playerWithdrawRecord->finish_time = date('Y-m-d H:i:s');
                    if ($playerWithdrawRecord->save()) {
                        saveChannelFinancialRecord($playerWithdrawRecord,
                            ChannelFinancialRecord::ACTION_WITHDRAW_PAYMENT);
                        // 更新渠道数据
                        $channel->withdraw_amount = bcadd($channel->withdraw_amount, $playerWithdrawRecord->point, 2);
                        $channel->save();
                    }
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerWithdrawRecord->player_id;
                    $notice->source_id = $playerWithdrawRecord->id;
                    $notice->type = Notice::TYPE_WITHDRAW_COMPLETE;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = admin_trans('player_withdraw_record.notice.withdraw_payment_success_title');
                    $notice->content = str_replace(
                        ['{point}', '{inmoney}'],
                        [$playerWithdrawRecord->point, $playerWithdrawRecord->inmoney],
                        admin_trans('player_withdraw_record.notice.withdraw_payment_success_content')
                    );
                    $notice->save();
                } catch (\Exception $e) {
                    return message_error(admin_trans('player_recharge_record.action_error'));
                }

                return message_success(admin_trans('player_withdraw_record.action_success'));
            });
        });
    }

    /**
     * 提现订单审核拒绝
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
                /** @var PlayerWithdrawRecord $playerWithdrawRecord */
                $playerWithdrawRecord = $this->model::find($id);
                if (empty($playerWithdrawRecord)) {
                    return message_error(admin_trans('player_withdraw_record.not_fount'));
                }
                if ($playerWithdrawRecord->type != PlayerWithdrawRecord::TYPE_SELF && $playerWithdrawRecord->type != PlayerWithdrawRecord::TYPE_GB) {
                    return message_error(admin_trans('player_withdraw_record.withdraw_record_error'));
                }
                switch ($playerWithdrawRecord->status) {
                    case PlayerWithdrawRecord::STATUS_SUCCESS:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_complete'));
                    case PlayerWithdrawRecord::STATUS_FAIL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_fail'));
                    case PlayerWithdrawRecord::STATUS_CANCEL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_cancel'));
                    case PlayerWithdrawRecord::STATUS_PENDING_REJECT:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_reject'));
                    case PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_system_cancel'));
                    case PlayerWithdrawRecord::STATUS_PENDING_PAYMENT:
                        return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_pass'));
                }
                if ($playerWithdrawRecord->status != PlayerWithdrawRecord::STATUS_WAIT) {
                    return message_error(admin_trans('player_withdraw_record.withdraw_record_status_error'));
                }
                try {
                    if (withdrawBack($playerWithdrawRecord, $form->input('reject_reason'))) {
                        saveChannelFinancialRecord($playerWithdrawRecord,
                            ChannelFinancialRecord::ACTION_WITHDRAW_REJECT);
                    }
                } catch (\Exception $e) {
                    return message_error(admin_trans('player_withdraw_record.action_error'));
                }

                // 发送站内信
                $notice = new Notice();
                $notice->department_id = Admin::user()->department_id;
                $notice->player_id = $playerWithdrawRecord->player_id;
                $notice->source_id = $playerWithdrawRecord->id;
                $notice->type = Notice::TYPE_WITHDRAW_REJECT;
                $notice->receiver = Notice::RECEIVER_PLAYER;
                $notice->is_private = 1;
                $notice->title = admin_trans('player_withdraw_record.notice.withdraw_reject_title');
                $notice->content = str_replace(
                    '{reason}',
                    $playerWithdrawRecord->reject_reason,
                    admin_trans('player_withdraw_record.notice.withdraw_reject_content')
                );
                $notice->save();

                return message_success(admin_trans('player_withdraw_record.action_success'));
            });
        });
    }


    public function passForm()
    {
        return Form::create(new $this->model, function (Form $form) {
            $content = admin_trans('player_withdraw_record.btn.examine_pass_confirm');
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
     * 提现订单审核通过
     * @param $id
     * @param $remark
     * @return Msg
     * @auth true
     * @group channel
     */
    public function pass($id,$remark): Msg
    {
        /** @var PlayerWithdrawRecord $playerWithdrawRecord */
        $playerWithdrawRecord = $this->model::find($id);
        if (empty($playerWithdrawRecord)) {
            return message_error(admin_trans('player_withdraw_record.not_fount'));
        }
        if ($playerWithdrawRecord->type != PlayerWithdrawRecord::TYPE_SELF && $playerWithdrawRecord->type != PlayerWithdrawRecord::TYPE_GB) {
            return message_error(admin_trans('player_withdraw_record.withdraw_record_error'));
        }
        switch ($playerWithdrawRecord->status) {
            case PlayerWithdrawRecord::STATUS_SUCCESS:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_complete'));
            case PlayerWithdrawRecord::STATUS_FAIL:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_fail'));
            case PlayerWithdrawRecord::STATUS_CANCEL:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_cancel'));
            case PlayerWithdrawRecord::STATUS_PENDING_REJECT:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_reject'));
            case PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_system_cancel'));
            case PlayerWithdrawRecord::STATUS_PENDING_PAYMENT:
                return message_warning(admin_trans('player_withdraw_record.withdraw_record_has_pass'));
        }
        if ($playerWithdrawRecord->status != PlayerWithdrawRecord::STATUS_WAIT) {
            return message_error(admin_trans('player_withdraw_record.withdraw_record_status_error'));
        }
        DB::beginTransaction();
        try {
            $playerWithdrawRecord->status = PlayerWithdrawRecord::STATUS_PENDING_PAYMENT;
            $playerWithdrawRecord->remark = $remark??'';
            $playerWithdrawRecord->user_id = Admin::id() ?? 0;
            $playerWithdrawRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
            if ($playerWithdrawRecord->save()) {
                saveChannelFinancialRecord($playerWithdrawRecord, ChannelFinancialRecord::ACTION_WITHDRAW_PASS);
            }
            if ($playerWithdrawRecord->type == PlayerWithdrawRecord::TYPE_GB) {
                (new GBpayService($playerWithdrawRecord->player))->withdraw($playerWithdrawRecord->tradeno,
                    $playerWithdrawRecord->money);
            } else {
                if ($playerWithdrawRecord->bank_type == 2) {
                    (new EHpayService($playerWithdrawRecord->player))->withdraw($playerWithdrawRecord->tradeno,
                        $playerWithdrawRecord->money, $playerWithdrawRecord->account_name, $playerWithdrawRecord->account, admin_trans('player_withdraw_record.payment_method.alipay'));
                } else {
                    // 发送站内信
                    $notice = new Notice();
                    $notice->department_id = Admin::user()->department_id;
                    $notice->player_id = $playerWithdrawRecord->player_id;
                    $notice->source_id = $playerWithdrawRecord->id;
                    $notice->type = Notice::TYPE_WITHDRAW_PASS;
                    $notice->receiver = Notice::RECEIVER_PLAYER;
                    $notice->is_private = 1;
                    $notice->title = admin_trans('player_withdraw_record.notice.withdraw_down_success_title');
                    $notice->content = str_replace(
                        '{point}',
                        $playerWithdrawRecord->point,
                        admin_trans('player_withdraw_record.notice.withdraw_down_success_content')
                    );
                    $notice->save();
                }
            }
            DB::commit();
        } catch (PaymentException $e) {
            DB::rollBack();
            return message_error($e->getMessage());
        } catch (\Exception) {
            DB::rollBack();
            return message_error(admin_trans('player_withdraw_record.action_error'));
        }
        return message_success(admin_trans('player_withdraw_record.action_success'));
    }

    /**
     * QTalk提現
     * @group channel
     * @auth true
     */
    public function talkIndex(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.talk_title'));
            $grid->model()->with(['player'])->where('type', PlayerWithdrawRecord::TYPE_THIRD)->orderBy('created_at',
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
                if (!empty($exAdminFilter['player_id'])) {
                    $grid->model()->where('player_id', $exAdminFilter['player_id']);
                }
                if (!empty($exAdminFilter['player']['uuid'])) {
                    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('uuid', $exAdminFilter['player']['uuid']);
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
            }

            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(point) as total_point')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_point']) ? floatval($totalData['total_point']) : 0)->prefix(admin_trans('player_withdraw_record.total_data.total_point'))->valueStyle([
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

            $grid->bordered(true);
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player->uuid),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->copy();
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy();
            $grid->column('talk_tradeno', admin_trans('player_withdraw_record.fields.talk_tradeno'))->copy();
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player_name),
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerWithdrawRecord::STATUS_SUCCESS:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SUCCESS))->color('#108ee9');
                            break;
                        case PlayerWithdrawRecord::STATUS_WAIT:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_WAIT))->color('#f50');
                            break;
                        case PlayerWithdrawRecord::STATUS_FAIL:
                        default:
                            $tag = Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_FAIL))->color('#87d068');
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->sortable();
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([$this, 'getPlayerOptions']));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.status'))
                    ->options([
                        PlayerWithdrawRecord::STATUS_WAIT => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_WAIT),
                        PlayerWithdrawRecord::STATUS_SUCCESS => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SUCCESS),
                        PlayerWithdrawRecord::STATUS_FAIL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_FAIL),
                        PlayerWithdrawRecord::STATUS_CANCEL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_CANCEL),
                        PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL => admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SYSTEM_CANCEL),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->like()->text('talk_tradeno')->placeholder(admin_trans('player_withdraw_record.fields.talk_tradeno'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_withdraw_record.fields.money'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('player_withdraw_record.fields.created_at'),
                    admin_trans('player_withdraw_record.fields.created_at')
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
     * 提现
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_withdraw_record.payment_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->model()->with(['player'])->whereIn('type',
                [
                    PlayerWithdrawRecord::TYPE_SELF,
                    PlayerWithdrawRecord::TYPE_ARTIFICIAL,
                    PlayerWithdrawRecord::TYPE_GB,
                    PlayerWithdrawRecord::TYPE_COIN,
                ])->orderBy('created_at',
                'desc');
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
            $query = clone $grid->model();
            $totalData = $query->selectRaw(
                'sum(IF(type = 2, point,0)) as total_self_inmoney,
                    sum(IF(type = 3, point,0)) as total_artificial_inmoney,
                    sum(IF(type = 4, point,0)) as total_gb_point,
                    sum(IF(type = 5, point,0)) as total_coin_inmoney'
                )->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_self_inmoney']) ? floatval($totalData['total_self_inmoney']) : 0)->prefix(admin_trans('player_withdraw_record.total_data.total_self_inmoney'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_artificial_inmoney']) ? floatval($totalData['total_artificial_inmoney']) : 0)->prefix(admin_trans('player_withdraw_record.total_data.total_artificial_inmoney'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_gb_point']) ? floatval($totalData['total_gb_point']) : 0)->prefix(admin_trans('player_withdraw_record.total_data.total_gb_point'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_coin_inmoney']) ? floatval($totalData['total_coin_inmoney']) : 0)->prefix(admin_trans('player_withdraw_record.total_data.total_coin_inmoney'))->valueStyle([
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
            })->style(['background' => '#fff']);
            $grid->tools([$layout]);
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center')->fixed(true);
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player->uuid)
                ]);
            })->copy()->fixed(true);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerWithdrawRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.recommend_player.uuid', admin_trans('player.recommend_uuid'))->copy()->fixed(true);
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->copy()->fixed(true);
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->player_name),
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
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
                        $info[] = Html::markdown('- ' . admin_trans('player_withdraw_record.fields.account_name') . ': ' . $data->account_name);
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
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->tip()->width('150px')->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, PlayerWithdrawRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $actions->prepend([
                    Button::create(admin_trans('machine_report.details'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal('ex-admin/addons-webman-controller-ChannelPlayerController/playerRecord', [
                            ['id' => $data->player_id],
                        ])->width('70%')->title(admin_trans('player.fields.uuid') . ': ' . $data->player->uuid)
                ]);
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('player_id')
                    ->placeholder(admin_trans('player_withdraw_record.fields.player_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
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
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_withdraw_record.fields.type'))
                    ->options([
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
                $filter->in()->select('currency')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('player_withdraw_record.fields.currency'))
                    ->options(plugin()->webman->config('currency'))
                    ->multiple();
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_withdraw_record.fields.remark'));
                $filter->eq()->number('money')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_withdraw_record.fields.money'));
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
                    admin_trans('player_withdraw_record.fields.created_at'),
                    admin_trans('player_withdraw_record.fields.created_at')
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
                case ChannelRechargeMethod::TYPE_GB:
                    $detail->item('account_name', admin_trans('player_withdraw_record.fields.account_name'));
                    $detail->item('bank_name', admin_trans('channel_recharge_setting.fields.bank_name'));
                    $detail->item('account', admin_trans('channel_recharge_setting.fields.account'));
                    break;
            }
        })->bordered()->layout('vertical');
    }

    /**
     * 币商提现
     * @group channel
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

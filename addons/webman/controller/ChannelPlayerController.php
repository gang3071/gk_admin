<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\Currency;
use addons\webman\model\Game;
use addons\webman\model\GamePlatform;
use addons\webman\model\LevelList;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\NationalPromoter;
use addons\webman\model\OpenScoreSetting;
use addons\webman\model\PhoneSmsLog;
use addons\webman\model\Player;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerBank;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerDisabledGame;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerGamePlatform;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPresentRecord;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerRegisterRecord;
use addons\webman\model\PlayerTag;
use addons\webman\model\PlayerWalletTransfer;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\StoreAutoShiftConfig;
use addons\webman\model\StoreSetting;
use addons\webman\service\ImportService;
use app\exception\GameException;
use app\service\game\GameServiceFactory;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use support\Cache;
use support\Db;
use support\Log;
use Webman\RedisQueue\Client as queueClient;

/**
 * 渠道玩家
 * @group channel
 */
class ChannelPlayerController
{
    protected $model;

    protected $playerTag;
    protected $playerActivityPhaseRecord;
    protected $playerLotteryRecord;
    protected $playerDeliveryRecord;
    private $gameLog;
    private $playerBank;
    private $withdraw;
    private $recharge;
    private $promoter;
    private $playGameRecord;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_model');
        $this->playerTag = plugin()->webman->config('database.player_tag_model');
        $this->gameLog = plugin()->webman->config('database.player_game_log_model');
        $this->withdraw = plugin()->webman->config('database.player_withdraw_record_model');
        $this->recharge = plugin()->webman->config('database.player_recharge_record_model');
        $this->promoter = plugin()->webman->config('database.player_promoter_model');
        $this->playerActivityPhaseRecord = plugin()->webman->config('database.player_activity_phase_record_model');
        $this->playerLotteryRecord = plugin()->webman->config('database.player_lottery_record_model');
        $this->playerDeliveryRecord = plugin()->webman->config('database.player_delivery_record_model');
        $this->playerBank = plugin()->webman->config('database.player_bank_model');
        $this->playGameRecord = plugin()->webman->config('database.play_game_record_model');
    }

    /**
     * 渠道玩家
     * @auth true
     * @group channel
     * @param int $id
     * @return Grid
     */
    public function index(int $id = 0): Grid
    {
        /** @var Channel $channel */
        $channel = Channel::query()->where('department_id', Admin::user()->department_id)->first();
        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $requestFilter = Request::input('ex_admin_filter', []);
        $quickSearch = Request::input('quickSearch', '');
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');

        // 构建基础查询字段
        $selectFields = [
            'player.*',
            'player_extend.recharge_amount',
            'player_extend.withdraw_amount',
            'player_extend.machine_put_point',
            'player_extend.remark',
            'channel.name as channel_name',
            'recommend_promoter.uuid as recommend_promoter_uuid',
            'recommend_promoter.phone as recommend_promoter_phone',
            'recommend_promoter.name as recommend_promoter_name',
            'player_register_record.ip',
            'player_register_record.country_name',
            'player_register_record.city_name',
            'player_platform_cash.money',
        ];

        // 线下渠道：添加代理和店家字段
        if ($channel && $channel->is_offline == 1) {
            $selectFields = array_merge($selectFields, [
                'player.agent_admin_id',
                'player.store_admin_id',
                'agent_admin.username as agent_admin_username',
                'agent_admin.nickname as agent_admin_nickname',
                'store_admin.username as store_admin_username',
                'store_admin.nickname as store_admin_nickname',
            ]);
        }

        // 构建完整查询（带 JOIN 和字段选择）
        $query = Player::query()->with(['the_last_player_login_record'])
            ->select($selectFields)
            ->leftjoin('player_extend', 'player.id', '=', 'player_extend.player_id')
            ->leftjoin('channel', 'player.department_id', '=', 'channel.department_id')
            ->leftjoin('player as recommend_promoter', 'recommend_promoter.id', '=', 'player.recommend_id')
            ->leftjoin('player_register_record', 'player.id', '=', 'player_register_record.player_id')
            ->leftjoin('player_platform_cash', 'player.id', '=', 'player_platform_cash.player_id')
            // 线下渠道：关联代理和店家
            ->when($channel && $channel->is_offline == 1, function ($query) {
                $query->leftjoin('admin_users as agent_admin', 'player.agent_admin_id', '=', 'agent_admin.id')
                    ->leftjoin('admin_users as store_admin', 'player.store_admin_id', '=', 'store_admin.id');
            })
            // 优化 IP 筛选查询
            ->when(!empty($requestFilter['ip']), function ($query) use ($requestFilter) {
                $query->leftJoin('player_login_record as r', function($join) {
                    $join->on('player.id', '=', 'r.player_id')
                        ->whereRaw('r.id = (SELECT MAX(id) FROM player_login_record WHERE player_id = player.id)');
                });
            })
            ->where('player.type', Player::TYPE_PLAYER);

        // 应用筛选条件
        $this->applyFilters($query, $requestFilter, $quickSearch, $id, true, $channel);

        // 克隆查询用于 count，但移除 select 和 with 以优化性能
        $countQuery = clone $query;
        $countQuery->getQuery()->columns = null; // 移除 select 字段
        // 注意：我们无法移除 with()，但 count 不会加载关联数据

        // 执行 count 查询
        $total = $countQuery->count('player.id');
        // 执行分页查询
        $list = $query->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('id', 'desc');
                })
            ->get()
            ->toArray();

        // 计算每个设备的彩金和小计
        foreach ($list as &$item) {
            // 查询该设备的累计彩金
            $lotteryAmount = PlayerLotteryRecord::query()
                ->where('player_id', $item['id'])
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                ->sum('amount') ?? 0;

            $item['lottery_amount'] = $lotteryAmount;

            // 计算小计 = (开分 + 投钞) - (洗分 + 彩金)
            $rechargeAmount = floatval($item['recharge_amount'] ?? 0);
            $machinePutPoint = floatval($item['machine_put_point'] ?? 0);
            $withdrawAmount = floatval($item['withdraw_amount'] ?? 0);

            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $totalOut = bcadd($withdrawAmount, $lotteryAmount, 2);
            $item['subtotal'] = bcsub($totalIn, $totalOut, 2);
        }

        return Grid::create($list, function (Grid $grid) use ($total, $list, $channel) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player.fields.id'))->fixed(true)->align('center');
            $grid->column('name', admin_trans('player.fields.device_name'))->display(function ($val, $data) {
                $image = !empty($data['avatar']) ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar']) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                    $data['is_test'] == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->fixed(true)->align('center');
            $grid->column('uuid', admin_trans('player.fields.device_uuid'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('recommend_promoter_uuid',
                admin_trans('player.fields.recommend_promoter_name'))->display(function ($value, $data) {
                if (!empty($data['recommend_id'])) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([ChannelPlayerPromoterController::class, 'playerInfo'],
                            ['player_id' => $data['recommend_id']])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data['recommend_promoter_phone']);
                } else {
                    return Button::create(admin_trans('player.bind_promoter'))->type('dashed')->size('small')->modal([
                        $this,
                        'bindPromoter'
                    ],
                        ['id' => $data['id']])->width('20%');
                }
            })->fixed(true)->align('center')->width(80)->ellipsis(true);

            // 线下渠道：使用 player_type 字段显示玩家类型
            if ($channel && $channel->is_offline == 1) {
                $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, $data) {
                    $tags = [];

                    // 显示玩家类型
                    switch ($data['player_type'] ?? Player::PLAYER_TYPE_NORMAL) {
                        case Player::PLAYER_TYPE_AGENT:
                            $tags[] = Tag::create(admin_trans('player.fields.player_type_agent'))->color('purple');
                            break;
                        case Player::PLAYER_TYPE_STORE_MACHINE:
                            $tags[] = Tag::create(admin_trans('player.fields.player_type_store_machine'))->color('blue');
                            break;
                        case Player::PLAYER_TYPE_NORMAL:
                        default:
                            $tags[] = Tag::create(admin_trans('player.fields.player_type_normal'))->color('green');
                            break;
                    }

                    // 测试账户标签
                    if ($data['is_test'] == 1) {
                        $tags[] = Tag::create(admin_trans('player.fields.is_test'))->color('red');
                    }

                    // 币商标签
                    if ($data['is_coin'] == 1) {
                        $tags[] = Tag::create(admin_trans('player.coin_merchant'))->color('#3b5999');
                    }

                    return Html::create()->content($tags)->style(['display' => 'inline-flex', 'text-align' => 'center']);
                })->ellipsis(true)->width(200)->align('center');
            } else {
                // 线上渠道：使用原有的显示方式
                $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, $data) {
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
            }

            // 线下渠道：显示所属代理和店家
            if ($channel && $channel->is_offline == 1) {
                $grid->column('agent_admin_nickname', admin_trans('admin.agent'))->display(function ($val, $data) {
                    if (!empty($data['agent_admin_id'])) {
                        return Html::create()->content([
                            Tag::create($data['agent_admin_nickname'] ?: $data['agent_admin_username'])->color('purple')
                        ]);
                    }
                    return Html::create()->content([Tag::create(admin_trans('admin.unassigned'))->color('default')]);
                })->width(120)->align('center');

                $grid->column('store_admin_nickname', admin_trans('admin.store'))->display(function ($val, $data) {
                    if (!empty($data['store_admin_id'])) {
                        return Html::create()->content([
                            Tag::create($data['store_admin_nickname'] ?: $data['store_admin_username'])->color('blue')
                        ]);
                    }
                    return Html::create()->content([Tag::create(admin_trans('admin.unassigned'))->color('default')]);
                })->width(120)->align('center');
            }

            $grid->column('money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    $this,
                    'playerRecord'
                ], ['id' => $data['id']])->width('70%')->title($data['name'] . ' ' . $data['uuid']);
            })->ellipsis(true)->sortable()->align('center');

            $grid->column('recharge_amount', '累计开分')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('withdraw_amount', '累计洗分')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('machine_put_point', '投钞')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('lottery_amount', '彩金')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('subtotal', '小计')->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('remark', admin_trans('player_extend.fields.remark'))->display(function ($value) {
                return ToolTip::create(Str::of($value)->limit(30, ' (...)'))->title($value);
            })->editable(
                (new Editable)
                    ->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('player.fields.remark')])
            )->width('150px')->align('center');
            $grid->column('created_at', admin_trans('player.fields.created_at'))->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content(date('Y-m-d H:i:s', strtotime($val))),
                    Html::div()->content(!empty($data['ip']) ? $data['ip'] : ''),
                    Html::div()->content(!empty($data['country_name']) ? $data['country_name'] : ''),
                ]);
            })->ellipsis(true)->align('center');
            $grid->column('the_last_player_login_record.created_at',
                admin_trans('player.fields.login_at'))->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content(!empty($data['the_last_player_login_record']['ip']) ? date('Y-m-d H:i:s',
                        strtotime($data['the_last_player_login_record']['created_at'])) : ''),
                    Html::div()->content(!empty($data['the_last_player_login_record']['ip']) ? $data['the_last_player_login_record']['ip'] : ''),
                    Html::div()->content(!empty($data['the_last_player_login_record']['country_name']) ? $data['the_last_player_login_record']['country_name'] : ''),
                ]);
            })->ellipsis(true)->align('center');
            $grid->column('player_tag', admin_trans('player.fields.player_tag'))
                ->display(function ($value) {
                    return $this->handleTagIds($value);
                })
                ->editable(
                    Editable::checkboxTag()
                        ->options($this->getPlayerTagOptionsFilter())
                )->ellipsis(true)->align('center');
            $grid->column('status_national', admin_trans('player.fields.status_national'))
                ->display(function ($value,$data)use($grid) {
                    if($data['is_promoter'] == 0){
                        return Switches::create(null, $value)
                            ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                            ->url($grid->attr('url'))
                            ->field('status_national')
                            ->params([
                                'ex_admin_action' => 'update',
                                'ids' => [$data[$grid->driver()->getPk()]],
                            ]);
                    }else{
                        return '';
                    }

                })->ellipsis(true)->align('center');
            $grid->column('switch_shop',
                admin_trans('player.fields.switch_shop'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_game_platform',
                admin_trans('player.fields.status_game_platform'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_machine',
                admin_trans('player.fields.status_machine'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_reverse_water',
                admin_trans('player.fields.status_reverse_water'))->switch()->ellipsis(true)->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_transfer',
                admin_trans('player.fields.status_transfer'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_offline_open',
                admin_trans('player.fields.status_offline_open'))->switch()->ellipsis(true)->align('center');
            $grid->column('status_baccarat',
                admin_trans('player.fields.status_baccarat'))->switch()->ellipsis(true)->align('center');
            $grid->filter(function (Filter $filter) use ($channel) {
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.device_name'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.device_uuid'));
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('recommend_name')->placeholder(admin_trans('player.fields.recommend_promoter_name'));
                $filter->like()->text('ip')->placeholder(admin_trans('player.login_ip'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_extend.fields.remark'));

                // 线下渠道：代理和店家筛选
                if ($channel && $channel->is_offline == 1) {
                    // 线下渠道：代理筛选
                    $filter->eq()->select('agent_admin_id')
                        ->showSearch()
                        ->placeholder(admin_trans('admin.agent'))
                        ->remoteOptions(admin_url([ChannelPlayerController::class, 'getAgentOptions']));
                    // 线下渠道：店家筛选
                    $filter->eq()->select('store_admin_id')
                        ->showSearch()
                        ->placeholder(admin_trans('admin.store'))
                        ->remoteOptions(admin_url([ChannelPlayerController::class, 'getStoreOptions']));
                }
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->expandFilter();
            $tools = [];
            // 线下渠道：添加批量生成按钮和创建代理按钮
            if ($channel && $channel->is_offline == 1) {
                $tools[] = Button::create(admin_trans('offline_channel.create_agent'))
                    ->icon(Icon::create('fas fa-user-tie'))
                    ->type('primary')
                    ->modal([$this, 'createAgentForm'])
                    ->width('50%');
                $tools[] = Button::create(admin_trans('offline_channel.create_store_machine'))
                    ->icon(Icon::create('fas fa-store'))
                    ->type('primary')
                    ->modal([$this, 'createStoreMachineForm'])
                    ->width('50%');
                $tools[] = Button::create(admin_trans('offline_channel.batch_generate_players'))
                    ->icon(Icon::create('fas fa-users'))
                    ->modal([$this, 'batchGenerateForm'])
                    ->width('50%');
            }

            if ($channel && $channel->is_offline == 0) {
                // 工具栏按钮
                $tools = [
                    $grid->addButton()->modal($this->form()),
                    ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '10px',
                        'margin-top' => '4px',
                        'line-height' => '28px',
                        'font-size' => '15px',
                        'cursor' => 'pointer'
                    ]))->title(admin_trans('player.set_promoter_tip')),
                ];
                $tools[] = Button::create(admin_trans('player.import'))
                    ->upload([ImportService::class, 'importPlayer'], [Admin::user()->department_id])
                    ->gridRefresh()
                    ->style(['marginLeft' => '20px']);
            }

            $grid->tools($tools);
            $grid->actions(function (Actions $actions, $data) use ($channel) {
                $actions->edit()->modal($this->form())->width('60%');
                $actions->hideDel();
                $dropdown = $actions->dropdown();
                $actions->prepend(Button::create(admin_trans('offline_channel.electronic_game_disabled'))
                    ->drawer([$this, 'playerGameList'], ['player_id' => $data['id']])
                    ->type('primary'));
                $actions->prepend(Button::create(admin_trans('channel_agent.open_score'))
                    ->modal($this->presentNoPassword(['id' => $data['id']]))->width('600px'));
                // 线下渠道不显示设置币商功能
                if ($channel->coin_status == 1 && $channel->is_offline != 1) {
                    $dropdown->prepend($data['is_coin'] == 0 ? admin_trans('player.set_coin') : admin_trans('player.cancel_coin'),
                        'fas fa-key')
                        ->confirm($data['is_coin'] == 0 ? admin_trans('player.set_coin_confirm') : admin_trans('player.cancel_coin_confirm'),
                            [$this, 'setCoin'], ['id' => $data['id'], 'is_coin' => $data['is_coin'] == 0 ? 1 : 0])
                        ->gridRefresh();
                }
                if (($data['is_promoter'] == 1 || empty($data['is_promoter'])) && $channel->promotion_status == 1) {
                    $dropdown->prepend(admin_trans('player.set_promoter'), 'fas fa-key')
                        ->modal([$this, 'setPromoter'], ['id' => $data['id']])->width('25%');
                }

                if ($channel->wallet_action_status == 1) {
                    $dropdown->append(admin_trans('player.wallet.player_wallet'), 'MoneyCollectFilled')
                        ->modal($this->playerWallet([
                            'id' => $data['id'],
                            'money' => $data['money'] ?? 0,
                        ]))->width('600px');
                }

                $dropdown->append(admin_trans('player.wallet.artificial_withdrawal'), 'PayCircleOutlined')
                    ->modal($this->artificialWithdrawal([
                        'id' => $data['id'],
                        'money' => $data['money'] ?? 0,
                    ]))->width('600px')->title(Html::create(admin_trans('player.wallet.artificial_withdrawal'))->content(
                        ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                            'marginLeft' => '5px',
                            'cursor' => 'pointer'
                        ]))->title(admin_trans('player.wallet.artificial_withdrawal_tip'))
                    ));
            });
            $grid->updateing(function ($ids, $data) {
                if (isset($ids[0]) && isset($data['player_extend'])) {
                    if (PlayerExtend::updateOrCreate(
                        ['player_id' => $ids[0]],
                        $data['player_extend']
                    )) {
                        return message_success(admin_trans('player.remark_edit_success'));
                    }
                }
                if (isset($ids[0]) && isset($data['remark'])) {
                    if (PlayerExtend::query()->where('player_id', $ids[0])->update(
                        ['remark' => $data['remark']]
                    )) {
                        return message_success(admin_trans('form.save_success'));
                    }
                }
                if (isset($ids[0]) && (isset($data['name']) || isset($data['real_name']) || isset($data['switch_shop']) || isset($data['status_game_platform']) || isset($data['status_baccarat']) || isset($data['status_offline_open']) || isset($data['status']) || isset($data['status_transfer']) || isset($data['status_national']) || isset($data['status_reverse_water']) || isset($data['status_machine']))) {
                    if (Player::query()->where('id', $ids[0])->update(
                        $data
                    )) {
                        return message_success(admin_trans('form.save_success'));
                    }
                }
                if (isset($ids[0]) && isset($data['player_tag'])) {
                    $playerTag = implode(',', $data['player_tag']);
                    if (Player::query()->where('id', $ids[0])->update(
                        ['player_tag' => $playerTag]
                    )) {
                        return message_success(admin_trans('form.save_success'));
                    }
                }
            });
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }

    /**
     * 应用通用筛选条件
     * @param $query
     * @param array $requestFilter
     * @param string $quickSearch
     * @param int $id
     * @param bool $hasJoin 是否已经 JOIN 了关联表
     * @param Channel|null $channel 渠道对象
     * @return void
     */
    private function applyFilters($query, array $requestFilter, string $quickSearch, int $id, bool $hasJoin = false, $channel = null)
    {
        // 应用 ID 筛选
        if (!empty($id)) {
            $query->where('player.recommend_id', $id);
        }

        // 快速搜索
        if (!empty($quickSearch)) {
            $query->where([
                ['player.name', 'like', '%' . $quickSearch . '%', 'or'],
                ['player.phone', 'like', '%' . $quickSearch . '%', 'or'],
                ['player.uuid', 'like', '%' . $quickSearch . '%', 'or'],
            ]);
        }

        // 应用筛选条件
        if (empty($requestFilter)) {
            return;
        }

        // 线下渠道：按 player_type 筛选
        if (isset($requestFilter['search_player_type'])) {
            $query->where('player.player_type', $requestFilter['search_player_type']);
        }

        // 线下渠道：是否测试账户筛选
        if (isset($requestFilter['search_is_test'])) {
            $query->where('player.is_test', $requestFilter['search_is_test']);
        }

        // 线下渠道：是否币商筛选
        if (isset($requestFilter['search_is_coin'])) {
            $query->where('player.is_coin', $requestFilter['search_is_coin']);
        }

        // 线上渠道：玩家类型筛选（原有逻辑）
        if (isset($requestFilter['search_type'])) {
            if ($requestFilter['search_type'] == 2) {
                $query->where('player.is_test', 1);
            } elseif ($requestFilter['search_type'] == 1) {
                $query->where('player.is_coin', 1);
            } else {
                $query->where('player.is_test', 0);
            }
        }

        // 时间范围筛选
        if (!empty($requestFilter['created_at_start'])) {
            $query->where('player.created_at', '>=', $requestFilter['created_at_start']);
        }
        if (!empty($requestFilter['created_at_end'])) {
            $query->where('player.created_at', '<=', $requestFilter['created_at_end']);
        }

        // 基础字段筛选
        if (!empty($requestFilter['phone'])) {
            $query->where('player.phone', 'like', '%' . $requestFilter['phone'] . '%');
        }
        if (!empty($requestFilter['uuid'])) {
            $query->where('player.uuid', 'like', '%' . $requestFilter['uuid'] . '%');
        }
        if (!empty($requestFilter['name'])) {
            $query->where('player.name', 'like', '%' . $requestFilter['name'] . '%');
        }
        if (!empty($requestFilter['real_name'])) {
            $query->where('player.real_name', 'like', '%' . $requestFilter['real_name'] . '%');
        }
        if (!empty($requestFilter['department_id'])) {
            $query->where('player.department_id', $requestFilter['department_id']);
        }
        if (isset($requestFilter['search_is_promoter']) && in_array($requestFilter['search_is_promoter'], [0, 1])) {
            $query->where('player.is_promoter', $requestFilter['search_is_promoter']);
        }

        // 线下渠道：代理筛选
        if ($channel && $channel->is_offline == 1 && !empty($requestFilter['agent_admin_id'])) {
            $query->where('player.agent_admin_id', $requestFilter['agent_admin_id']);
        }

        // 线下渠道：店家筛选
        if ($channel && $channel->is_offline == 1 && !empty($requestFilter['store_admin_id'])) {
            $query->where('player.store_admin_id', $requestFilter['store_admin_id']);
        }

        // 只有在已经 JOIN 的情况下才应用这些筛选
        if ($hasJoin) {
            if (!empty($requestFilter['recommend_name'])) {
                $query->where([
                    ['recommend_promoter.name', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or'],
                    ['recommend_promoter.phone', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or'],
                    ['recommend_promoter.uuid', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or']
                ]);
            }
            if (!empty($requestFilter['email'])) {
                $query->where('player_extend.email', 'like', '%' . $requestFilter['email'] . '%');
            }
            if (!empty($requestFilter['line'])) {
                $query->where('player_extend.line', 'like', '%' . $requestFilter['line'] . '%');
            }
            if (!empty($requestFilter['remark'])) {
                $query->where('player_extend.remark', 'like', '%' . $requestFilter['remark'] . '%');
            }
            if (!empty($requestFilter['ip'])) {
                $query->where('r.ip', 'like', '%' . $requestFilter['ip'] . '%');
            }
        }
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
     * 渠道玩家
     * @auth true
     * @group channel
     * @return Form
     */
    public function form(): Form
    {
        $options = [];
        foreach (config('def_avatar') as $key => $item) {
            $options[$key] = Avatar::create()->style(['padding' => '1px'])->src($item)->shape('square');
        }
        return Form::create(new $this->model(), function (Form $form) use ($options) {
            if ($form->isEdit()) {
                $form->title(admin_trans('player.details'));
                $form->row(function (Form $form) use ($options) {
                    $form->column(function (Form $form) use ($options) {
                        $form->text('phone', admin_trans('player.fields.phone'))->maxlength(50)->ruleNumber()
                            ->rule([
                                (string)Rule::unique(plugin()->webman->config('database.player_model'))->ignore($form->input('id')) => admin_trans('player.phone_exist'),
                            ])
                            ->disabled(true);
                        $form->text('name', admin_trans('player.fields.name'))->maxlength(50);
                        $form->radio('avatar_type', admin_trans('player.avatar_type'))
                            ->button()
                            ->default(is_numeric($form->driver()->get('avatar')) ? 2 : 1)
                            ->options([
                                1 => admin_trans('player.upload_avatar'),
                                2 => admin_trans('player.def_avatar')
                            ])
                            ->when(1, function (Form $form) {
                                $form->file('avatar', admin_trans('player.fields.avatar'))
                                    ->value(is_numeric($form->driver()->get('avatar')) ? '' : $form->driver()->get('avatar'))
                                    ->ext('jpg,png,jpeg')
                                    ->type('image')
                                    ->fileSize('1m')
                                    ->hideFinder()
                                    ->paste();
                            })->when(2, function (Form $form) use ($options) {
                                $form->radio('def_avatar', admin_trans('player.def_avatar'))
                                    ->default(1)
                                    ->options($options);
                            });
                        $form->text('player_extend.id_number',
                            admin_trans('player_extend.fields.id_number'))->ruleAlphaNum()->maxlength(20);
                        $form->desc('the_last_player_login_record.created_at',
                            admin_trans('player.fields.login_at'))->value($form->input('the_last_player_login_record.created_at') ? date('Y-m-d H:i:s',
                            strtotime($form->input('the_last_player_login_record.created_at'))) : '');
                        $form->desc('created_at',
                            admin_trans('player.fields.created_at'))->value($form->input('created_at') ? date('Y-m-d H:i:s',
                            strtotime($form->input('created_at'))) : '');
                    })->span(12);

                    $form->column(function (Form $form) {
                        $form->text('player_extend.address',
                            admin_trans('player_extend.fields.address'))->maxlength(255);
                        $form->date('player_extend.birthday', admin_trans('player_extend.fields.birthday'));
                        $form->text('player_extend.email',
                            admin_trans('player_extend.fields.email'))->ruleEmail()->maxlength(20);
                        $form->text('player_extend.line',
                            admin_trans('player_extend.fields.line'))->ruleAlphaNum()->maxlength(20);
                        $form->select('machine_play_num', admin_trans('player.fields.machine_play_num'))->options([
                            1 => 1,
                            2 => 2,
                            3 => 3,
                            4 => 4,
                            5 => 5
                        ])->disabled();
                        $form->textarea('player_extend.remark', admin_trans('player_extend.fields.remark'))
                            ->showCount()
                            ->rule(['max:255' => admin_trans('player_extend.fields.remark')]);
                        $form->desc('player_register_record.ip', admin_trans('player.fields.register_ip'));
                        $form->desc('player_register_record.register_domain',
                            admin_trans('player.fields.register_domain'));
                    })->span(12);
                });
            } else {
                $form->title(admin_trans('player.add_player'));

                // 线下渠道：显示玩家类型选择
                /** @var Channel $channel */
                $channel = Channel::where('department_id', Admin::user()->department_id)->first();
                if ($channel && $channel->is_offline == 1) {
                    $form->radio('player_type', admin_trans('offline_channel.player_type'))
                        ->button()
                        ->default(1)
                        ->options([
                            1 => admin_trans('offline_channel.normal_player'),
                            2 => admin_trans('offline_channel.agent'),
                            3 => admin_trans('offline_channel.store_machine')
                        ])
                        ->when(2, function (Form $form) {
                            // 代理：设置上缴比例
                            $form->number('promoter_ratio', admin_trans('offline_channel.submit_ratio'))
                                ->min(0)
                                ->max(100)
                                ->precision(2)
                                ->default(60)
                                ->help(admin_trans('offline_channel.help_agent_ratio'))
                                ->required();
                        })
                        ->when(3, function (Form $form) {
                            // 店家：选择上级代理和设置上缴比例
                            $options = getPromoterTreeOptions(0, Admin::user()->department_id, false, false);
                            $form->treeSelect('recommend_id', admin_trans('offline_channel.parent_agent'))
                                ->options($options)
                                ->help(admin_trans('offline_channel.help_select_parent_agent'))
                                ->required();
                            $form->number('promoter_ratio', admin_trans('offline_channel.submit_ratio'))
                                ->min(0)
                                ->max(100)
                                ->precision(2)
                                ->default(80)
                                ->help(admin_trans('offline_channel.help_store_ratio'))
                                ->required();
                        })
                        ->required();
                }

                $form->text('phone', admin_trans('player.fields.phone'))->maxlength(50)->ruleAlphaNum()->required();
                $form->radio('avatar_type', admin_trans('player.avatar_type'))
                    ->button()
                    ->default(2)
                    ->options([
                        1 => admin_trans('player.upload_avatar'),
                        2 => admin_trans('player.def_avatar')
                    ])
                    ->when(1, function (Form $form) {
                        $form->image('avatar',
                            admin_trans('player.fields.avatar'))->ext('jpg,png,jpeg')->fileSize('1m');
                    })->when(2, function (Form $form) use ($options) {
                        $form->radio('def_avatar', admin_trans('player.def_avatar'))
                            ->default(1)
                            ->options($options);
                    });
                $form->select('country_code', admin_trans('player.fields.country_code'))->options([
                    PhoneSmsLog::COUNTRY_CODE_CH => PhoneSmsLog::COUNTRY_CODE_CH,
                    PhoneSmsLog::COUNTRY_CODE_TW => PhoneSmsLog::COUNTRY_CODE_TW,
                    PhoneSmsLog::COUNTRY_CODE_JP => PhoneSmsLog::COUNTRY_CODE_JP
                ])->required();
                $form->text('name', admin_trans('player.fields.name'))->maxlength(50)->required();
                $form->password('password', admin_trans('player.new_password'))
                    ->rule([
                        'confirmed' => admin_trans('player.password_confim_validate'),
                        'min:6' => admin_trans('player.password_min_number')
                    ])
                    ->value('')
                    ->required();
                $form->password('password_confirmation', admin_trans('player.confim_password'))
                    ->required();
            }
            $form->saved(function () {
                return message_success(admin_trans('player.save_player_info_success'));
            });
            $form->saving(function (Form $form) {
                if ($form->isEdit()) {
                    $orgData = $form->driver()->get();
                    /** @var Player $player */
                    $player = Player::find($orgData['id']);
                    if (empty($player)) {
                        return message_error(admin_trans('player.not_fount'));
                    }
                    Db::beginTransaction();
                    try {
                        $player->name = $form->input('name');
                        $player->machine_play_num = $form->input('machine_play_num');
                        $player->avatar = $form->input('avatar_type') == 1 ? $form->input('avatar') : $form->input('def_avatar');
                        $player->save();
                        PlayerExtend::query()->updateOrCreate(['player_id' => $orgData['id']], [
                            'address' => $form->input('player_extend.address'),
                            'birthday' => $form->input('player_extend.birthday'),
                            'id_number' => $form->input('player_extend.id_number'),
                            'email' => $form->input('player_extend.email'),
                            'line' => $form->input('player_extend.line'),
                            'remark' => $form->input('player_extend.remark'),
                            'player_id' => $orgData['id']
                        ]);
                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollBack();
                        return message_error($e->getMessage());
                    }
                    return message_success(admin_trans('player.save_player_info_success'));
                } else {
                    if (!LevelList::query()->where('department_id', Admin::user()->department_id)->orderBy('must_chip_amount')->exists()) {
                        return message_error(admin_trans('player.national_level_not_configure'));
                    }
                    $phone = $form->input('phone');
                    $password = $form->input('password');
                    $country_code = $form->input('country_code');
                    /** @var $player $machineCategory */
                    $player = Player::query()->where('phone', $phone)->first();
                    if (!empty($player)) {
                        return message_error(admin_trans('player.phone_has_register'));
                    }
                    /** @var Channel $channel */
                    $channel = Channel::where('department_id', Admin::user()->department_id)->first();
                    if (empty($channel)) {
                        return message_error(admin_trans('channel.not_fount'));
                    }

                    // 获取玩家类型（线下渠道）
                    $playerType = $form->input('player_type', 1);
                    $promoterRatio = $form->input('promoter_ratio', 0);
                    $recommendId = $form->input('recommend_id', 0);

                    // 店家需要验证上级代理的上缴比例
                    if ($channel->is_offline == 1 && $playerType == 3 && $recommendId > 0) {
                        /** @var PlayerPromoter $parentPromoter */
                        $parentPromoter = PlayerPromoter::query()->where('player_id', $recommendId)->first();
                        if (empty($parentPromoter)) {
                            return message_error(admin_trans('offline_channel.error_parent_agent_not_exist'));
                        }
                        if ($promoterRatio < $parentPromoter->ratio) {
                            return message_error(admin_trans('offline_channel.error_ratio_less_than_parent', null, [
                                '{ratio}' => $promoterRatio,
                                '{parent_ratio}' => $parentPromoter->ratio
                            ]));
                        }
                    }

                    Db::beginTransaction();
                    try {
                        $player = new Player();
                        $player->phone = $phone;
                        $player->name = $form->input('name');
                        if ($form->input('avatar_type') == 1) {
                            $player->avatar = $form->input('avatar') ?? config('def_avatar.1');
                        }
                        if ($form->input('avatar_type') == 2) {
                            $player->avatar = $form->input('def_avatar') ?? config('def_avatar.1');
                        }
                        $player->country_code = $country_code;
                        $player->type = Player::TYPE_PLAYER;
                        $player->currency = $channel->currency;
                        $player->password = $password;
                        $player->uuid = generate15DigitUniqueId();
                        $player->department_id = Admin::user()->department_id;
                        $player->recommend_code = createCode();

                        // 设置玩家类型（线下渠道）
                        if ($channel->is_offline == 1) {
                            $player->player_type = $playerType; // 1=普通玩家, 2=代理, 3=店家
                        } else {
                            $player->player_type = Player::PLAYER_TYPE_NORMAL; // 默认普通玩家
                        }

                        // 线下渠道：代理和店家标记为推广员
                        if ($channel->is_offline == 1 && ($playerType == 2 || $playerType == 3)) {
                            $player->is_promoter = 1;
                            if ($playerType == 3 && $recommendId > 0) {
                                $player->recommend_id = $recommendId;
                            }
                        }

                        $player->save();

                        //创建玩家全民代理身份
                        $national_promoter = new NationalPromoter;
                        $national_promoter->uid = $player->id;
                        $level_min = LevelList::where('department_id', $player->department_id)->orderBy('must_chip_amount')->first();
                        $national_promoter->level = $level_min->id;
                        $national_promoter->save();

                        addPlayerExtend($player);

                        addRegisterRecord($player->id, PlayerRegisterRecord::TYPE_ADMIN, $player->department_id);

                        // 线下渠道：创建代理/店家记录
                        if ($channel->is_offline == 1 && ($playerType == 2 || $playerType == 3)) {
                            $playerPromoter = new PlayerPromoter();
                            $playerPromoter->player_id = $player->id;
                            $playerPromoter->name = $player->name;
                            $playerPromoter->department_id = $player->department_id;
                            $playerPromoter->ratio = $promoterRatio;
                            $playerPromoter->status = 1;

                            if ($playerType == 2) {
                                // 代理：recommend_id = 0
                                $playerPromoter->recommend_id = 0;
                                $playerPromoter->path = '';
                            } else {
                                // 店家：recommend_id = 上级代理的player_id
                                $playerPromoter->recommend_id = $recommendId;
                                /** @var PlayerPromoter $parentPromoter */
                                $parentPromoter = PlayerPromoter::query()->where('player_id', $recommendId)->first();
                                $playerPromoter->path = $parentPromoter->path ? $parentPromoter->path . '-' . $recommendId : (string)$recommendId;
                            }

                            $playerPromoter->save();
                        }

                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollBack();
                        return message_error($e->getMessage());
                    }
                    return message_success(admin_trans('player.save_player_info_success'));
                }
            });
        });
    }

    /**
     * 玩家钱包
     * @auth true
     * @group channel
     * @param $data
     * @return Form
     */
    public function playerWallet($data): Form
    {
        return Form::create(new $this->model, function (Form $form) use ($data) {
            $form->hidden('id')->default($data['id']);
            $form->row(function (Form $form) {
                $type = $form->getBindField('type');
                $form->radio('type', admin_trans('player.wallet.type'))
                    ->button()
                    ->disabled($form->isEdit())
                    ->default(PlayerMoneyEditLog::TYPE_INCREASE)
                    ->options([
                        admin_trans('player.wallet.deduct'),
                        admin_trans('player.wallet.increase'),
                    ])->required()->span(7);
                $form->hidden('type')->bindAttr('value', $type)
                    ->when(PlayerMoneyEditLog::TYPE_DEDUCT, function (Form $form) {
                        $form->select('deduct_action', admin_trans('player.wallet.action'))
                            ->remoteOptions(admin_url([$this, 'getTranOptions'],
                                ['type' => PlayerMoneyEditLog::TYPE_DEDUCT]))
                            ->required()->span(16)->style(['margin-left' => '22px'])
                            ->when(PlayerMoneyEditLog::ACTIVITY, function (Form $form) {
                                $form->select('activity', admin_trans('player.wallet.action'))
                                    ->remoteOptions(admin_url([$this, 'getActivity'],
                                        ['type' => PlayerMoneyEditLog::TYPE_INCREASE]))
                                    ->required()->span(16);
                            });
                    })->when(PlayerMoneyEditLog::TYPE_INCREASE, function (Form $form) {
                        $form->select('increase_action', admin_trans('player.wallet.action'))
                            ->remoteOptions(admin_url([$this, 'getTranOptions'],
                                ['type' => PlayerMoneyEditLog::TYPE_INCREASE]))
                            ->required()->span(16)->style(['margin-left' => '22px'])
                            ->when(PlayerMoneyEditLog::ACTIVITY, function (Form $form) {
                                $form->select('activity', admin_trans('player.wallet.action'))
                                    ->remoteOptions(admin_url([$this, 'getActivity'],
                                        ['type' => PlayerMoneyEditLog::TYPE_INCREASE]))
                                    ->required()->span(16);
                            });
                    });
            });
            $form->number('money',
                admin_trans('player.wallet.money'))->min(0)->max(100000000)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player.wallet.machine_wallet') . ' ' . $data['money'] ?? 0)->required();
            $form->textarea('remark', admin_trans('player.wallet.textarea'))->maxlength(255)->bindAttr('rows',
                4)->required();
            $form->actions()->hideResetButton();
            $form->saving(function (Form $form) use ($data) {
                /** @var Channel $channel */
                $channel = Channel::where('department_id', Admin::user()->department_id)->first();
                if ($channel->wallet_action_status == 0) {
                    return message_error(admin_trans('player.wallet_action_status_has_closed'));
                }
                $deliveryType = $form->input('type') == PlayerMoneyEditLog::TYPE_INCREASE ? PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD : PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT;
                return $this->store([
                    'id' => $form->input('id'),
                    'type' => $form->input('type'),
                    'deduct_action' => $form->input('deduct_action'),
                    'increase_action' => $form->input('increase_action'),
                    'money' => $form->input('money'),
                    'remark' => $form->input('remark'),
                    'activity' => $form->input('activity'),
                    'delivery_type' => $deliveryType,
                    'source' => 'wallet_modify'
                ]);
            });
            $form->layout('vertical');
        });
    }

    /**
     * 钱包操作
     * @param $data
     * @return Msg
     */
    public function store($data): Msg
    {
        try {
            Db::beginTransaction();
            playerManualSystem($data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('player.wallet.wallet_operation_failed'));
        }
        return message_success(admin_trans('player.wallet.wallet_operation_success'));
    }

    /**
     * 人工充值
     * @auth true
     * @group channel
     * @param $data
     * @return Form
     */
    public function artificialRecharge($data): Form
    {
        return Form::create(new $this->model, function (Form $form) use ($data) {
            $form->number('point', admin_trans('player_recharge_record.fields.point'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%'])
                ->addonBefore(admin_trans('player.wallet.machine_wallet') . ' ' . $data['money'] ?? 0)
                ->required();
            $form->number('money', admin_trans('player_recharge_record.fields.money'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%']);
            $form->text('currency', admin_trans('player_recharge_record.fields.currency'))->maxlength(10);
            $form->textarea('remark',
                admin_trans('player_recharge_record.fields.remark'))->maxlength(255)->bindAttr('rows', 4);
            $form->layout('vertical');
            $form->hidden('id')->value($data['id']);
            $form->saving(function (Form $form) {
                /** @var Player $player */
                $player = Player::where('id', $form->input('id'))->whereNull('deleted_at')->first();
                if (empty($player)) {
                    return message_error(admin_trans('player.not_fount'));
                }
                if ($player->status == 0) {
                    return message_error(admin_trans('player.disable'));
                }
                Db::beginTransaction();
                try {
                    /** @var PlayerPlatformCash $playerWallet */
                    $playerWallet = PlayerPlatformCash::query()->where('player_id',
                        $player->id)->lockForUpdate()->first();
                    $beforeGameAmount = $playerWallet->money;
                    // 生成订单
                    $playerRechargeRecord = new  PlayerRechargeRecord();
                    $playerRechargeRecord->player_id = $player->id;
                    $playerRechargeRecord->talk_user_id = $player->talk_user_id;
                    $playerRechargeRecord->department_id = $player->department_id;
                    $playerRechargeRecord->tradeno = createOrderNo();
                    $playerRechargeRecord->player_name = $player->name ?? '';
                    $playerRechargeRecord->player_phone = $player->phone ?? '';
                    $playerRechargeRecord->money = $form->input('money') ?? 0;
                    $playerRechargeRecord->inmoney = $form->input('money') ?? 0;
                    $playerRechargeRecord->currency = $form->input('currency') ?? '';
                    $playerRechargeRecord->type = PlayerRechargeRecord::TYPE_ARTIFICIAL;
                    $playerRechargeRecord->point = $form->input('point');
                    $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS;
                    $playerRechargeRecord->remark = $form->input('remark');
                    $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
                    $playerRechargeRecord->user_id = Admin::id() ?? 0;
                    $playerRechargeRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
                    $playerRechargeRecord->save();

                    $playerWallet->money = bcadd($playerWallet->money, $playerRechargeRecord->point, 2);
                    $playerWallet->save();

                    $player->player_extend->recharge_amount = bcadd($player->player_extend->recharge_amount,
                        $playerRechargeRecord->point, 2);
                    if (isset($player->national_promoter->status) && $player->national_promoter->status == 0) {
                        $player->national_promoter->created_at = $playerRechargeRecord->finish_time;
                        $player->national_promoter->status = 1;
                        if (!empty($player->recommend_id) && $player->channel->national_promoter_status == 1) {
                            //玩家上级推广员信息
                            /** @var Player $recommendPlayer */
                            $recommendPlayer = Player::query()->find($player->recommend_id);
                            //推广员为全民代理
                            if(!empty($recommendPlayer->national_promoter) && $recommendPlayer->is_promoter < 1){
                                //首充返佣金额
                                /** @var PlayerPlatformCash $recommendPlayerWallet */
                                $recommendPlayerWallet = PlayerPlatformCash::query()->where('player_id',
                                    $player->recommend_id)->lockForUpdate()->first();
                                $beforeRechargeAmount = $recommendPlayerWallet->money;
                                $rechargeRebate = $recommendPlayer->national_promoter->level_list->recharge_ratio;
                                $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $rechargeRebate,
                                    2);

                                //寫入首充金流明細
                                $playerDeliveryRecord = new PlayerDeliveryRecord;
                                $playerDeliveryRecord->player_id = $recommendPlayer->id;
                                $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                                $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
                                $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
                                $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE_REWARD;
                                $playerDeliveryRecord->source = 'national_promoter';
                                $playerDeliveryRecord->amount = $rechargeRebate;
                                $playerDeliveryRecord->amount_before = $beforeRechargeAmount;
                                $playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                                $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                                $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                                $playerDeliveryRecord->save();

                                //首冲成功之后全民代理邀请奖励
                                $recommendPlayer->national_promoter->invite_num = bcadd($recommendPlayer->national_promoter->invite_num, 1, 0);
                                $recommendPlayer->national_promoter->settlement_amount = bcadd($recommendPlayer->national_promoter->settlement_amount, $rechargeRebate, 2);
                                /** @var NationalInvite $national_invite */
                                $national_invite = NationalInvite::where('min', '<=',
                                    $recommendPlayer->national_promoter->invite_num)
                                    ->where('max', '>=', $recommendPlayer->national_promoter->invite_num)->first();

                                if (!empty($national_invite) && $national_invite->interval > 0 && $recommendPlayer->national_promoter->invite_num % $national_invite->interval == 0) {
                                    $money = $national_invite->money;
                                    $amount_before = $recommendPlayerWallet->money;
                                    $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $money, 2);
                                    // 寫入金流明細
                                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                                    $playerDeliveryRecord->player_id = $recommendPlayer->id;
                                    $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                                    $playerDeliveryRecord->target = $national_invite->getTable();
                                    $playerDeliveryRecord->target_id = $national_invite->id;
                                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_NATIONAL_INVITE;
                                    $playerDeliveryRecord->source = 'national_promoter';
                                    $playerDeliveryRecord->amount = $money;
                                    $playerDeliveryRecord->amount_before = $amount_before;
                                    $playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                                    $playerDeliveryRecord->tradeno = '';
                                    $playerDeliveryRecord->remark = '';
                                    $playerDeliveryRecord->save();
                                }
                                $recommendPlayer->push();
                                $recommendPlayerWallet->save();

                                $nationalProfitRecord = new NationalProfitRecord();
                                $nationalProfitRecord->uid = $playerRechargeRecord->player_id;
                                $nationalProfitRecord->recommend_id = $playerRechargeRecord->player->recommend_id;
                                $nationalProfitRecord->money = $rechargeRebate;
                                $nationalProfitRecord->type = 0;
                                $nationalProfitRecord->status = 1;
                                $nationalProfitRecord->save();
                            }
                        }
                    }
                    $player->push();

                    //寫入金流明細
                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $playerRechargeRecord->player_id;
                    $playerDeliveryRecord->department_id = $playerRechargeRecord->department_id;
                    $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
                    $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE;
                    $playerDeliveryRecord->source = 'artificial_recharge';
                    $playerDeliveryRecord->amount = $playerRechargeRecord->point;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                    $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                    $playerDeliveryRecord->save();

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player.artificial_recharge_error'));
                }
                return message_success(admin_trans('player.artificial_recharge_success'));
            });
        });
    }

    /**
     * 人工提现
     * @auth true
     * @group channel
     * @param $data
     * @return Form
     */
    public function artificialWithdrawal($data): Form
    {
        return Form::create(new $this->model, function (Form $form) use ($data) {
            $form->number('point', admin_trans('player_withdraw_record.fields.point'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%'])
                ->addonBefore(admin_trans('player.wallet.machine_wallet') . ' ' . $data['money'] ?? 0)
                ->required();
            $form->number('money', admin_trans('player_withdraw_record.fields.money'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%']);
            $form->text('currency', admin_trans('player_withdraw_record.fields.currency'))->maxlength(10);
            $form->text('bank_name', admin_trans('player_withdraw_record.fields.bank_name'))->maxlength(50);
            $form->text('account', admin_trans('player_withdraw_record.fields.account'))->maxlength(50);
            $form->text('account_name', admin_trans('player_withdraw_record.fields.account_name'))->maxlength(50);
            $form->textarea('remark',
                admin_trans('player_withdraw_record.fields.remark'))->maxlength(255)->bindAttr('rows', 4);
            $form->layout('vertical');
            $form->hidden('id')->value($data['id']);
            $form->saving(function (Form $form) {
                /** @var Player $player */
                $player = Player::where('id', $form->input('id'))->whereNull('deleted_at')->first();
                if (empty($player)) {
                    return message_error(admin_trans('player.not_fount'));
                }
                if ($player->status == 0) {
                    return message_error(admin_trans('player.disable'));
                }
                if ($player->machine_wallet->money < $form->input('point')) {
                    return message_error(admin_trans('player.insufficient_balance'));
                }
                Db::beginTransaction();
                try {
                    // 生成订单
                    $playerWithdrawRecord = new PlayerWithdrawRecord();
                    $playerWithdrawRecord->player_id = $player->id;
                    $playerWithdrawRecord->talk_user_id = $player->talk_user_id;
                    $playerWithdrawRecord->department_id = $player->department_id;
                    $playerWithdrawRecord->tradeno = createOrderNo();
                    $playerWithdrawRecord->player_name = $player->name ?? '';
                    $playerWithdrawRecord->player_phone = $player->phone ?? '';
                    $playerWithdrawRecord->money = $form->input('money') ?? 0;
                    $playerWithdrawRecord->point = $form->input('point') ?? 0;
                    $playerWithdrawRecord->fee = 0;
                    $playerWithdrawRecord->inmoney = bcsub($playerWithdrawRecord->money, $playerWithdrawRecord->fee,
                        2); // 实际提现金额
                    $playerWithdrawRecord->currency = $form->input('currency') ?? 0;
                    $playerWithdrawRecord->bank_name = $form->input('bank_name') ?? 0;
                    $playerWithdrawRecord->account = $form->input('account') ?? 0;
                    $playerWithdrawRecord->account_name = $form->input('account_name') ?? 0;
                    $playerWithdrawRecord->remark = $form->input('remark') ?? '';
                    $playerWithdrawRecord->type = PlayerWithdrawRecord::TYPE_ARTIFICIAL;
                    $playerWithdrawRecord->status = PlayerWithdrawRecord::STATUS_SUCCESS;
                    $playerWithdrawRecord->finish_time = date('Y-m-d H:i:s');
                    $playerWithdrawRecord->save();
                    $beforeGameAmount = $player->machine_wallet->money;
                    // 玩家钱包扣减
                    $player->machine_wallet->money = bcsub($player->machine_wallet->money, $playerWithdrawRecord->point,
                        2);
                    // 更新玩家统计
                    $player->player_extend->withdraw_amount = bcadd($player->player_extend->withdraw_amount,
                        $playerWithdrawRecord->point, 2);
                    $player->push();
                    //寫入金流明細
                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $playerWithdrawRecord->player_id;
                    $playerDeliveryRecord->department_id = $playerWithdrawRecord->department_id;
                    $playerDeliveryRecord->target = $playerWithdrawRecord->getTable();
                    $playerDeliveryRecord->target_id = $playerWithdrawRecord->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_WITHDRAWAL;
                    $playerDeliveryRecord->withdraw_status = $playerWithdrawRecord->status;
                    $playerDeliveryRecord->source = 'artificial_withdrawal';
                    $playerDeliveryRecord->amount = $playerWithdrawRecord->point;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $playerWithdrawRecord->tradeno ?? '';
                    $playerDeliveryRecord->remark = $playerWithdrawRecord->remark ?? '';
                    $playerDeliveryRecord->save();
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player.artificial_withdrawal_error'));
                }
                return message_success(admin_trans('player.artificial_withdrawal_success'));
            });
        });
    }

    /**
     * 玩家银行卡
     * @param $playerId
     * @auth true
     * @group channel
     * @return Grid
     */
    public function playerBank($playerId = 0): Grid
    {
        return Grid::create(new $this->playerBank(), function (Grid $grid) use ($playerId) {
            $grid->model()->where('player_id', $playerId)->orderBy('created_at', 'desc');
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_bank.title'));
            $grid->column('bank_name', admin_trans('player_bank.fields.bank_name'))->copy()->align('center');
            $grid->column('account', admin_trans('player_bank.fields.account'))->copy()->align('center');
            $grid->column('account_name', admin_trans('player_bank.fields.account_name'))->copy()->align('center');
            $grid->column('wallet_address', admin_trans('player_bank.fields.wallet_address'))->copy()->align('center');
            $grid->column('qr_code', admin_trans('player_bank.fields.qr_code'))->image()->align('center');
            $grid->column('type', admin_trans('player_bank.fields.type'))
                ->display(function ($val) {
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
                    return $tag;
                })
                ->align('center');
            $grid->column('status', admin_trans('player_bank.fields.status'))->switch()->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('account')->placeholder(admin_trans('channel_recharge_method.fields.account'));
            });
            $grid->hideSelection();
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->setForm()->drawer($this->playerBankForm($playerId));
            $grid->tools(
                $grid->addButton()->drawer($this->playerBankForm($playerId))
            );
        });
    }

    /**
     * 银行账户
     * @auth true
     * @group channel
     * @param $playerId
     * @return Form
     */
    public function playerBankForm($playerId = 0): Form
    {
        return Form::create(new $this->playerBank(), function (Form $form) use ($playerId) {
            $form->title(admin_trans('slider.title'));
            $type = $form->getBindField('type');
            $form->select('type', admin_trans('player_bank.fields.type'))
                ->placeholder(admin_trans('player_bank.fields.type'))
                ->default(ChannelRechargeMethod::TYPE_BANK)
                ->disabled($form->isEdit())
                ->required()
                ->options([
                    ChannelRechargeMethod::TYPE_BANK => admin_trans('player_bank.type.' . ChannelRechargeMethod::TYPE_BANK),
                    ChannelRechargeMethod::TYPE_ALI => admin_trans('player_bank.type.' . ChannelRechargeMethod::TYPE_ALI),
                    ChannelRechargeMethod::TYPE_WECHAT => admin_trans('player_bank.type.' . ChannelRechargeMethod::TYPE_WECHAT),
                    ChannelRechargeMethod::TYPE_USDT => admin_trans('player_bank.type.' . ChannelRechargeMethod::TYPE_USDT),
                ]);
            $form->hidden('type')->bindAttr('value', $type)
                ->when(ChannelRechargeMethod::TYPE_BANK, function (Form $form) {
                    $form->text('bank_name', admin_trans('player_bank.fields.bank_name'))
                        ->maxlength(120)
                        ->required();
                    $form->text('account', admin_trans('player_bank.fields.account'))
                        ->maxlength(100)
                        ->required();
                    $form->text('account_name', admin_trans('player_bank.fields.account_name'))
                        ->maxlength(100)
                        ->required();
                })->when(ChannelRechargeMethod::TYPE_ALI, function (Form $form) {
                    $form->text('account', admin_trans('player_bank.fields.account'))
                        ->maxlength(100)
                        ->required();
                    $form->text('account_name', admin_trans('player_bank.fields.account_name'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('player_bank.fields.qr_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste();
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_WECHAT, function (Form $form) {
                    $form->text('account', admin_trans('player_bank.fields.account'))
                        ->maxlength(100)
                        ->required();
                    $form->text('account_name', admin_trans('player_bank.fields.account_name'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('player_bank.fields.qr_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste();
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_USDT, function (Form $form) {
                    $form->text('wallet_address',
                        admin_trans('player_bank.fields.wallet_address'))
                        ->required()
                        ->maxlength(250);
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('player_bank.fields.qr_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste()
                            ->help(admin_trans('channel_recharge_setting.wallet_code_desc'));
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                });
            $form->layout('vertical');
            $form->saving(function (Form $form) use ($playerId) {
                try {
                    if ($form->isEdit()) {
                        $id = $form->driver()->get('id');
                        /** @var PlayerBank $playerBank */
                        $playerBank = PlayerBank::find($id);
                    } else {
                        $playerBank = new PlayerBank();
                        $playerBank->player_id = $playerId;
                        $playerBank->type = $form->input('type');
                    }
                    $playerBank->bank_name = $form->input('bank_name') ?? '';
                    $playerBank->account_name = $form->input('account_name') ?? '';
                    $playerBank->wallet_address = $form->input('wallet_address');
                    $playerBank->qr_code = $form->input('qr_code');
                    $playerBank->account = $form->input('account');

                    $playerBank->save();
                } catch (\Exception $exception) {
                    return message_error(admin_trans('form.save_fail') . $exception->getMessage());
                }
                return message_success(admin_trans('form.save_success'));
            });
        });
    }

    /**
     * 绑定推广员
     * @auth true
     * @group channel
     * @param $id
     * @return Form|Msg
     */
    public function bindPromoter($id)
    {
        /** @var Player $player */
        $player = Player::query()->find($id);
        if (empty($player)) {
            return message_error(admin_trans('player.player_not_found'));
        }
        if (!empty($player->recommended_code)) {
            return message_error(admin_trans('player.player_has_bind'));
        }
        if ($player->is_promoter > 0) {
            return message_error(admin_trans('player.has_been_promoter'));
        }
        /** @var Channel $channel */
        $channel = Channel::query()->where('department_id', $player->department_id)->first();
        if (empty($channel)) {
            return message_error(admin_trans('player.channel_not_found'));
        }
        if ($channel->promotion_status != 1) {
            return message_error(admin_trans('player.channel_close_promoter'));
        }
        return Form::create($player, function (Form $form) use ($player) {
            $form->push(Html::markdown('><font size=1 color="#ff4d4f">' . admin_trans('player.bind_promoter_confirm') . '</font>'));
            $options = getPromoterTreeOptions($player->id, $player->department_id, true, $player->is_test == 1);
            $form->treeSelect('recommend_id')
                ->options($options);
            $form->saving(function (Form $form) use ($player) {
                /** @var PlayerPromoter $recommendPlayer */
                $recommendPlayer = PlayerPromoter::query()->where('player_id',
                    $form->input('recommend_id'))->where('department_id', $player->department_id)->first();
                if (empty($recommendPlayer)) {
                    return message_error(admin_trans('player.promoter_not_found'));
                }
                if ($recommendPlayer->player->status == 0) {
                    return message_error(admin_trans('player.promoter_has_disable'));
                }
                $player->recommend_id = $recommendPlayer->player->id;
                $player->recommended_code = $recommendPlayer->player->recommend_code;
                $player->save();
                $recommendPlayer->increment('player_num');
                return message_success(admin_trans('player.action_success'));
            });
        });
    }

    /**
     * 玩家游戏钱包
     * @param $id
     * @return Grid
     * @throws \Exception
     * @auth true
     * @group channel
     */
    public function playerGameWallet($id): Grid
    {
        $list = [];
        $data = PlayerGamePlatform::query()->where('player_id', $id)->get();
        /** @var PlayerGamePlatform $item */
        $lang = locale();
        $lang = Str::replace('_', '-', $lang);
        foreach ($data as $item) {
            try {
                $balance = GameServiceFactory::createService(strtoupper($item->gamePlatform->code),
                    $item->player)->getBalance([
                    'lang' => $lang
                ]);
            } catch (GameException|\Exception) {
                $balance = admin_trans('player_game_platform.game_balance_not_found');
            }
            $list[] = [
                'id' => $item->id,
                'logo' => $item->gamePlatform->logo,
                'name' => $item->gamePlatform->name,
                'code' => $item->gamePlatform->code,
                'player' => $item->player,
                'balance' => $balance,
            ];
        }
        return Grid::create($list, function (Grid $grid) use ($id) {
            $grid->tools(
                Button::create(admin_trans('player_game_platform.all_transfer_out'))
                    ->icon(Icon::create('fas fa-bars'))
                    ->confirm(admin_trans('player_game_platform.all_transfer_out_msg'), [$this, 'withdrawAmountAll'])
                    ->gridBatch()
                    ->gridRefresh()
            );
            $grid->column('logo', admin_trans('game_platform.fields.logo'))->display(function ($val, $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['logo']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('name', admin_trans('game_platform.fields.name'))->align('center');
            $grid->column('balance', admin_trans('player_game_platform.wallet_balance'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->hideDeleteSelection();
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideDetail();
                $actions->prepend(
                    Button::create(admin_trans('player_game_platform.transfer_in'))
                        ->type('primary')
                        ->modal([$this, 'depositAmount'], ['id' => $data['id']])
                        ->title(admin_trans('player_game_platform.transfer_in_title', [],
                            ['{game_platform_name}' => $data['name']]))
                );
                $actions->prepend(
                    Button::create(admin_trans('player_game_platform.transfer_out'))
                        ->type('primary')
                        ->modal([$this, 'withdrawAmount'], ['id' => $data['id']])
                        ->title(admin_trans('player_game_platform.transfer_out_title', [],
                            ['{name}' => $data['player']->name, '{game_platform_name}' => $data['name']]))
                );
            })->align('center');
        });
    }

    /**
     * 全部转出
     * @param $selected
     * @auth true
     * @group channel
     * @return Msg
     * @throws \Exception
     */
    public function withdrawAmountAll($selected): Msg
    {
        if (!isset($selected)) {
            return message_error(admin_trans('player.not_fount'));
        }
        /** @var Player $changePlayer */
        $playerGamePlatformList = PlayerGamePlatform::query()->whereIn('id', $selected)->get();
        if (!$playerGamePlatformList) {
            return message_error(admin_trans('player.not_fount'));
        }
        /** @var PlayerGamePlatform $playerGamePlatform */
        foreach ($playerGamePlatformList as $playerGamePlatform) {
            if ($playerGamePlatform->gamePlatform->status != 1) {
                return message_error(admin_trans('player_game_platform.game_platform_disable'));
            }
            $lang = locale();
            $lang = Str::replace('_', '-', $lang);
            try {
                $gameService = GameServiceFactory::createService(strtoupper($playerGamePlatform->gamePlatform->code),
                    $playerGamePlatform->player);
            } catch (\Exception $e) {
                return message_error($e->getMessage());
            }
            $amount = $gameService->getBalance(['lang' => $lang]);
            if ($amount > 0) {
                Db::beginTransaction();
                try {
                    $player = $playerGamePlatform->player;
                    $gamePlatform = $playerGamePlatform->gamePlatform;
                    $playerWalletTransfer = new PlayerWalletTransfer();
                    $playerWalletTransfer->player_id = $player->id;
                    $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
                    $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
                    $playerWalletTransfer->platform_id = $gamePlatform->id;
                    $playerWalletTransfer->department_id = $player->department_id;
                    $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_IN;
                    $playerWalletTransfer->game_amount = $amount;
                    $playerWalletTransfer->player_amount = $player->machine_wallet->money;
                    $playerWalletTransfer->tradeno = createOrderNo();
                    $result = $gameService->withdrawAmount([
                        'amount' => $amount,
                        'order_no' => $playerWalletTransfer->tradeno,
                        'lang' => $lang,
                        'take_all' => 'true',
                    ]);
                    $playerWalletTransfer->platform_no = $result['order_id'];
                    $playerWalletTransfer->amount = $result['amount'];
                    $beforeGameAmount = $player->machine_wallet->money;
                    // 更新玩家统计
                    $player->machine_wallet->money = bcadd($player->machine_wallet->money,
                        $playerWalletTransfer->amount, 2);
                    $player->push();
                    $playerWalletTransfer->save();

                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $player->id;
                    $playerDeliveryRecord->department_id = $player->department_id;
                    $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
                    $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
                    $playerDeliveryRecord->platform_id = $gamePlatform->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN;
                    $playerDeliveryRecord->source = 'wallet_transfer_in';
                    $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
                    $playerDeliveryRecord->remark = $target->remark ?? '';
                    $playerDeliveryRecord->user_id = Admin::id();
                    $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerDeliveryRecord->save();

                    Db::commit();
                } catch (\Exception|GameException $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
                }
            }
        }

        return message_success(admin_trans('admin.success'));
    }

    /**
     * 游戏钱包转出
     * @auth true
     * @group channel
     * @param $id
     * @return Form|Msg
     */
    public function withdrawAmount($id)
    {
        /** @var PlayerGamePlatform $playerGamePlatform */
        $playerGamePlatform = PlayerGamePlatform::query()->find($id);
        if (empty($playerGamePlatform)) {
            return message_error(admin_trans('player_game_platform.not_found_player_platform'));
        }
        if ($playerGamePlatform->gamePlatform->status != 1) {
            return message_error(admin_trans('player_game_platform.game_platform_disable'));
        }
        $lang = locale();
        $lang = Str::replace('_', '-', $lang);
        try {
            $gameService = GameServiceFactory::createService(strtoupper($playerGamePlatform->gamePlatform->code),
                $playerGamePlatform->player);
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }
        $balance = $gameService->getBalance(['lang' => $lang]);
        return Form::create([], function (Form $form) use ($id, $playerGamePlatform, $balance, $lang, $gameService) {
            $form->number('money',
                admin_trans('player_game_platform.current_balance') . ': ' . $balance)->min(0)->max($balance)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player_game_platform.transfer_out_amount'));
            $form->switch('take_all', admin_trans('player_game_platform.has_all_transfer_out'));
            $form->actions()->hideResetButton();
            $form->saving(function (Form $form) use ($playerGamePlatform, $balance, $lang, $gameService) {
                $amount = $form->input('money');
                $takeAll = $form->input('take_all');
                if ($takeAll == 0 && $amount > $balance) {
                    return message_error(trans('insufficient_wallet_balance', [], 'message'));
                }
                if ($takeAll == 1) {
                    if ($balance <= 0) {
                        return message_error(trans('insufficient_wallet_balance', [], 'message'));
                    }
                    $amount = $balance;
                }
                Db::beginTransaction();
                try {
                    $player = $playerGamePlatform->player;
                    $gamePlatform = $playerGamePlatform->gamePlatform;
                    $playerWalletTransfer = new PlayerWalletTransfer();
                    $playerWalletTransfer->player_id = $player->id;
                    $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
                    $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
                    $playerWalletTransfer->platform_id = $gamePlatform->id;
                    $playerWalletTransfer->department_id = $player->department_id;
                    $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_IN;
                    $playerWalletTransfer->game_amount = $balance;
                    $playerWalletTransfer->player_amount = $player->machine_wallet->money;
                    $playerWalletTransfer->tradeno = createOrderNo();
                    $result = $gameService->withdrawAmount([
                        'amount' => $amount,
                        'order_no' => $playerWalletTransfer->tradeno,
                        'lang' => $lang,
                        'take_all' => $takeAll == 1 ? 'true' : 'false',
                    ]);
                    $playerWalletTransfer->platform_no = $result['order_id'];
                    $playerWalletTransfer->amount = $result['amount'];
                    $beforeGameAmount = $player->machine_wallet->money;
                    // 更新玩家统计
                    $player->machine_wallet->money = bcadd($player->machine_wallet->money,
                        $playerWalletTransfer->amount, 2);
                    $player->push();
                    $playerWalletTransfer->save();

                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $player->id;
                    $playerDeliveryRecord->department_id = $player->department_id;
                    $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
                    $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
                    $playerDeliveryRecord->platform_id = $gamePlatform->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN;
                    $playerDeliveryRecord->source = 'wallet_transfer_in';
                    $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
                    $playerDeliveryRecord->remark = $target->remark ?? '';
                    $playerDeliveryRecord->user_id = Admin::id();
                    $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerDeliveryRecord->save();

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
                } catch (GameException $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
                }
                return message_success(admin_trans('player_game_platform.transfer_out_success'));
            });
            $form->layout('vertical');
        });
    }

    /**
     * 游戏钱包转出
     * @auth true
     * @group channel
     * @param $id
     * @return Form|Msg
     */
    public function depositAmount($id)
    {
        /** @var PlayerGamePlatform $playerGamePlatform */
        $playerGamePlatform = PlayerGamePlatform::query()->find($id);
        if (empty($playerGamePlatform)) {
            return message_error(admin_trans('player_game_platform.not_found_player_platform'));
        }
        if ($playerGamePlatform->gamePlatform->status != 1) {
            return message_error(admin_trans('player_game_platform.game_platform_disable'));
        }
        return Form::create([], function (Form $form) use ($id, $playerGamePlatform) {
            $form->number('money',
                admin_trans('player_game_platform.current_balance') . ': ' . $playerGamePlatform->player->machine_wallet->money)->min(0)->max($playerGamePlatform->player->machine_wallet->money)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player_game_platform.transfer_in_amount'));
            $form->switch('take_all', admin_trans('player_game_platform.has_all_transfer_in'));
            $form->actions()->hideResetButton();
            $form->saving(function (Form $form) use ($playerGamePlatform) {
                $amount = $form->input('money');
                $takeAll = $form->input('take_all');
                if ($takeAll == 0 && $amount > $playerGamePlatform->player->machine_wallet->money) {
                    return message_error(admin_trans('player_game_platform.insufficient_account_balance'));
                }
                if ($takeAll == 1) {
                    $amount = $playerGamePlatform->player->machine_wallet->money;
                }
                $lang = locale();
                $lang = Str::replace('_', '-', $lang);
                $player = $playerGamePlatform->player;
                $gamePlatform = $playerGamePlatform->gamePlatform;
                $gameService = GameServiceFactory::createService(strtoupper($gamePlatform->code), $player);
                $balance = $gameService->getBalance(['lang' => $lang]);
                Db::beginTransaction();
                try {
                    $playerWalletTransfer = new PlayerWalletTransfer();
                    $playerWalletTransfer->player_id = $player->id;
                    $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
                    $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
                    $playerWalletTransfer->platform_id = $gamePlatform->id;
                    $playerWalletTransfer->department_id = $player->department_id;
                    $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_OUT;
                    $playerWalletTransfer->amount = abs($amount);
                    $playerWalletTransfer->game_amount = $balance;
                    $playerWalletTransfer->player_amount = $player->machine_wallet->money;
                    $playerWalletTransfer->tradeno = createOrderNo();
                    $playerWalletTransfer->platform_no = $gameService->depositAmount([
                        'amount' => $amount,
                        'order_no' => $playerWalletTransfer->tradeno,
                        'lang' => $lang,
                    ]);
                    $playerWalletTransfer->save();
                    $beforeGameAmount = $player->machine_wallet->money;
                    $player->machine_wallet->money = bcsub($player->machine_wallet->money,
                        $playerWalletTransfer->amount, 2);
                    $player->push();

                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $player->id;
                    $playerDeliveryRecord->department_id = $player->department_id;
                    $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
                    $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
                    $playerDeliveryRecord->platform_id = $gamePlatform->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT;
                    $playerDeliveryRecord->source = 'wallet_transfer_out';
                    $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
                    $playerDeliveryRecord->remark = $target->remark ?? '';
                    $playerDeliveryRecord->user_id = Admin::id();
                    $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerDeliveryRecord->save();
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player_game_platform.transfer_in_failed') . $e->getMessage());
                } catch (GameException $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player_game_platform.transfer_in_failed') . $e->getMessage());
                }
                return message_success(admin_trans('player_game_platform.transfer_in_success'));
            });
            $form->layout('vertical');
        });
    }

    /**
     * 设置推广员
     * @auth true
     * @group channel
     * @param $id
     * @return Form
     */
    public function setPromoter($id): Form
    {
        /** @var PlayerPromoter $promoter */
        $promoter = PlayerPromoter::with(['parent_promoter'])->where('player_id', $id)->first();
        return Form::create($promoter ?? new $this->promoter(), function (Form $form) use ($id) {
            $form->push(Html::markdown('><font size=1 color="#ff4d4f">' . admin_trans('player_promoter.submit_confirm') . '</font>'));
            /** @var PlayerPromoter $model */
            $model = $form->driver()->model();
            $maxRatio = $model->parent_promoter->ratio ?? 100;
            $form->text('name')
                ->value($model->name ?? '')
                ->maxlength(30)
                ->required()->addonBefore(admin_trans('player_promoter.fields.name'));
            $form->text('ratio')
                ->value($model->ratio ?? '')
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->required()
                ->addonAfter('%')
                ->help(!empty($model->parent_promoter->ratio) ? admin_trans('player_promoter.ratio_help_parent', null,
                    ['{max_ratio}' => $maxRatio]) : admin_trans('player_promoter.ratio_help_platform', null,
                    ['{max_ratio}' => $maxRatio]))
                ->placeholder(admin_trans('player_promoter.ratio_placeholder', null, ['{max_ratio}' => $maxRatio]))
                ->addonBefore(admin_trans('player_promoter.fields.ratio'));
            $form->saving(function (Form $form) use ($id) {
                return $this->savePromoter($id, $form->input('ratio'), $form->input('name'));
            });
        });
    }

    /**
     * 保存推广员信息
     * @param $id
     * @param $ratio
     * @param string $name
     * @return Msg
     */
    public function savePromoter($id, $ratio, string $name = ''): Msg
    {
        Db::beginTransaction();
        try {
            /** @var Player $player */
            $player = Player::with(['player_promoter'])->find($id);
            if (empty($player)) {
                throw new Exception(admin_trans('player.not_fount'));
            }
            if (empty($player->player_promoter) && !empty($player->recommend_id)) {
                throw new Exception(admin_trans('player.player_must_not_attributed'));
            }
            $promoter = $player->player_promoter ?? new PlayerPromoter();

            /** @var PlayerPromoter $parentPromoter */
            $parentPromoter = PlayerPromoter::where('player_id', $player->recommend_id)->first();
            $maxRatio = $parentPromoter->ratio ?? 100;
            if ($ratio > $maxRatio) {
                throw new Exception(admin_trans('player_promoter.ratio_max_error', null, ['{max_ratio}' => $maxRatio]));
            }

            /** @var PlayerPromoter $subPromoter */
            $subPromoter = PlayerPromoter::where('recommend_id', $player->id)->orderBy('ratio', 'asc')->first();
            if (!empty($subPromoter)) {
                if ($ratio < $subPromoter->ratio) {
                    throw new Exception(admin_trans('player_promoter.ratio_min_error', null,
                        ['{min_ratio}' => $subPromoter->ratio]));
                }
            }

            $orgPromoter = $player->is_promoter;
            $path = [];
            if (!empty($parentPromoter->path)) {
                $path = explode(',', $parentPromoter->path);
            }
            $path[] = $player->id;
            $promoter->ratio = $ratio;
            $promoter->player_id = $id;
            $promoter->recommend_id = $parentPromoter->player_id ?? 0;
            $promoter->department_id = $player->department_id;
            $promoter->name = $name;
            $promoter->player_num = Player::query()->where('recommend_id', $id)->count() ?? 0;
            $promoter->path = implode(',', $path);
            $promoter->save();
            // 更新玩家信息
            $player->is_promoter = 1;
            $player->save();

            $parentPromoter && $orgPromoter == 0 && $parentPromoter->increment('team_num');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('form.save_success'));
    }

    /**
     * 钱包操作类型
     * @param $type
     * @return mixed
     */
    public function getTranOptions($type)
    {
        $options = [];
        if ($type == PlayerMoneyEditLog::TYPE_INCREASE) {
            $transactionType = [
                PlayerMoneyEditLog::ACTIVITY_GIVE,
                PlayerMoneyEditLog::TRIPLE_SEVEN_GIVE,
                PlayerMoneyEditLog::COMPOSITE_MACHINE_GIVE,
                PlayerMoneyEditLog::REAL_PERSON_GIVE,
                PlayerMoneyEditLog::ELECTRONIC_GIVE,
                PlayerMoneyEditLog::TESTING_MACHINE,
                PlayerMoneyEditLog::OTHER,
                PlayerMoneyEditLog::ACTIVITY,
                PlayerMoneyEditLog::SPECIAL,
            ];
        } else {
            $transactionType = [
                PlayerMoneyEditLog::ADMIN_DEDUCT,
                PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER,
                PlayerMoneyEditLog::ACTIVITY,
                PlayerMoneyEditLog::SPECIAL,
            ];
        }

        foreach ($transactionType as $item) {
            $options[] = [
                'value' => $item,
                'label' => admin_trans('player.wallet.wallet_type.' . $item),
            ];
        }

        return Response::success($options);
    }


    public function getActivity()
    {
        $list = Activity::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->whereJsonContains('department_id', Admin::user()->department_id)
            ->whereNull('deleted_at')
            ->get();
        $lang = Container::getInstance()->translator->getLocale();
        $options = [];
        /** @var Activity $item */
        foreach ($list as $item) {
            /** @var ActivityContent $activityContent */
            $activityContent = $item->activity_content->where('lang', $lang)->first();
            $options[] = [
                'value' => $activityContent->id,
                'label' => $activityContent->name,
            ];
        }

        return Response::success($options);
    }

    /**
     * 设置币商
     * @auth true
     * @group channel
     * @return Msg
     */
    public function setCoin(): Msg
    {
        $data = Request::input();
        /** @var Player $player */
        $player = Player::where('id', $data['id'])->whereNull('deleted_at')->first();
        if (empty($player)) {
            return message_error(admin_trans('player.not_fount'));
        }
        if ($player->status == 0) {
            return message_error(admin_trans('player.disable'));
        }
        $player->is_coin = $data['is_coin'];
        if (!$player->save()) {
            return message_error(admin_trans('player.action_error'));
        }
        return message_success(admin_trans('player.action_success'));
    }

    /**
     * 币商列表
     * @auth true
     * @group channel
     * @return Grid
     */
    public function coinList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player.coin_title'));
            $grid->model()->with(['player_extend', 'machine_wallet'])->where('is_coin', 1)->orderBy('created_at',
                'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $searchTime = [];
            $where = [];
            $whereHas = [];
            $playerPresentIn = PlayerPresentRecord::query();
            $playerRechargeRecord = PlayerRechargeRecord::query();
            if (!empty($exAdminFilter['created_at_start'])) {
                $searchTime[] = $exAdminFilter['created_at_start'];
                $where[] = ['created_at', '>=', $exAdminFilter['created_at_start']];
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $searchTime[] = $exAdminFilter['created_at_end'];
                $where[] = ['created_at', '<=', $exAdminFilter['created_at_end']];
            }
            if(!empty($exAdminFilter['phone'])){
                $whereHas[] =['player',function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%'.$exAdminFilter['phone'].'%');
                }];
                $playerPresentIn->whereHas('user',function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%'.$exAdminFilter['phone'].'%');
                });
            }
            if(!empty($exAdminFilter['uuid'])){
                $whereHas[] =['player',function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%'.$exAdminFilter['uuid'].'%');
                }];
                $playerPresentIn->whereHas('user',function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%'.$exAdminFilter['uuid'].'%');
                });
            }
            if(!empty($exAdminFilter['name'])){
                $whereHas[] =['player',function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%'.$exAdminFilter['name'].'%');
                }];
                $playerPresentIn->whereHas('user',function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%'.$exAdminFilter['name'].'%');
                });
            }
            if(!empty($exAdminFilter['department_id'])){
                $whereHas[] =['player',function ($query) use ($exAdminFilter) {
                    $query->where('department_id', $exAdminFilter['department_id']);
                }];
                $playerPresentIn->whereHas('user',function ($query) use ($exAdminFilter) {
                    $query->where('department_id', $exAdminFilter['department_id']);
                });
            }

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, Player $data) {
                $image = $data->avatar ? Avatar::create()->src(is_numeric($data->avatar) ? config('def_avatar.' . $data->avatar) : $data->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('phone')
            );


            $playerPresentOut = PlayerPresentRecord::query();

            foreach($whereHas as $has){
                $playerRechargeRecord->whereHas(...$has);
                $playerPresentOut->whereHas(...$has);
            }

            //统计卡
            $rechargeModel = $playerRechargeRecord->where($where)->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->selectRaw('
                    sum(point) as total_point,
                    sum(money) as total_money
                ')->first()->toArray();

            $totalPresentOut = $playerPresentOut->where($where)->selectRaw('
                    sum(IF(type = ' . PlayerPresentRecord::TYPE_IN . ', amount,0)) as total_in
                ')->first()->toArray();

            $totalPresentIn = $playerPresentIn->where($where)->selectRaw('
                    sum(IF(type = ' . PlayerPresentRecord::TYPE_OUT . ', amount,0)) as total_out
                ')->first()->toArray();

            $totalData = [
                'total_point' => $rechargeModel['total_point'],
                'total_in' => $totalPresentOut['total_in'],
                'total_out' => $totalPresentIn['total_out'],
                'total_money' => $rechargeModel['total_money'],
            ];

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_extend.fields.recharge_amount'))->value(!empty($totalData['total_point']) ? floatval($totalData['total_point']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])->valueStyle([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ]), 8),
                        Divider::create()->type('vertical')->style(['height' => '3em']),
                        Row::create()->column(Statistic::create()->title(admin_trans('player_extend.fields.present_out_amount'))->value(!empty($totalData['total_out']) ? floatval($totalData['total_out']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])->valueStyle([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ]), 8),
                        Divider::create()->type('vertical')->style(['height' => '3em']),
                        Row::create()->column(Statistic::create()->title(admin_trans('player_extend.fields.present_in_amount'))->value(!empty($totalData['total_in']) ? floatval($totalData['total_in']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])->valueStyle([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ]), 8),
                        Divider::create()->type('vertical')->style(['height' => '3em']),
                        Row::create()->column(Statistic::create()->title(admin_trans('player_extend.fields.total_money'))->value(!empty($totalData['total_money']) ? floatval($totalData['total_money']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])->valueStyle([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ]), 8),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->column('uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, Player $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->filter(
                    FilterColumn::like()->text('uuid')
                );
            $grid->column('name', admin_trans('player.fields.name'))->align('center')->filter(
                FilterColumn::like()->text('name')
            );
            $grid->column('player_tag', admin_trans('player.fields.player_tag'))
                ->display(function ($value) {
                    return $this->handleTagIds($value);
                })
                ->editable(
                    Editable::checkboxTag()
                        ->options($this->getPlayerTagOptionsFilter())
                )->width('10%');
            $grid->column('is_coin', admin_trans('player.fields.type'))->display(function () {
                return Tag::create(admin_trans('player.coin_merchant'))
                    ->color('#3b5999');
            })->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->switch()->align('center');
            $grid->column('status_transfer', admin_trans('player.fields.status_transfer'))->switch()->align('center');
            $grid->column('player_extend.coin_recharge_amount',
                admin_trans('player_extend.fields.recharge_amount'))->display(function ($val, Player $data) use (
                $searchTime
            ) {
                $playerRechargeRecord = $data->player_recharge_record->where('status',
                    PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS);
                if ($playerRechargeRecord->isEmpty()) {
                    return 0;
                }
                $playerRechargeRecord->toQuery();
                if (!empty($searchTime[0])) {
                    $playerRechargeRecord = $playerRechargeRecord->where('created_at', '>=', $searchTime[0]);
                }
                if (!empty($searchTime[1])) {
                    $playerRechargeRecord = $playerRechargeRecord->where('created_at', '<=', $searchTime[1]);
                }
                return $playerRechargeRecord->sum('point');
            })->align('center');
            $grid->column('player_extend.coin_money',
                admin_trans('player.wallet.money'))->display(function ($val, Player $data) use (
                $searchTime
            ) {
                $playerRechargeRecord = $data->player_recharge_record->where('status',
                    PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS);
                if ($playerRechargeRecord->isEmpty()) {
                    return 0;
                }
                $playerRechargeRecord->toQuery();
                if (!empty($searchTime[0])) {
                    $playerRechargeRecord = $playerRechargeRecord->where('created_at', '>=', $searchTime[0]);
                }
                if (!empty($searchTime[1])) {
                    $playerRechargeRecord = $playerRechargeRecord->where('created_at', '<=', $searchTime[1]);
                }
                return $playerRechargeRecord->sum('money')."({$data->channel->currency})";
            })->align('center');
            $grid->column('player_extend.present_out_amount',
                admin_trans('player_extend.fields.present_out'))->display(function ($val, Player $data) use ($searchTime
            ) {
                $presentOutRecord = $data->present_out->where('type', PlayerPresentRecord::TYPE_OUT);
                if ($presentOutRecord->isEmpty()) {
                    return 0;
                }
                $presentOutRecord = $presentOutRecord->toQuery();
                if (!empty($searchTime[0])) {
                    $presentOutRecord = $presentOutRecord->where('created_at', '>=', $searchTime[0]);
                }
                if (!empty($searchTime[1])) {
                    $presentOutRecord = $presentOutRecord->where('created_at', '<=', $searchTime[1]);
                }
                return $presentOutRecord->sum('amount');
            })->align('center');
            $grid->column('player_extend.present_in_amount',
                admin_trans('player_extend.fields.present_in'))->display(function ($val, Player $data) use ($searchTime
            ) {
                $presentInRecord = $data->present_in->where('type', PlayerPresentRecord::TYPE_IN);
                if ($presentInRecord->isEmpty()) {
                    return 0;
                }
                $presentInRecord = $presentInRecord->toQuery();
                if (!empty($searchTime[0])) {
                    $presentInRecord = $presentInRecord->where('created_at', '>=', $searchTime[0]);
                }
                if (!empty($searchTime[1])) {
                    $presentInRecord = $presentInRecord->where('created_at', '<=', $searchTime[1]);
                }
                return $presentInRecord->sum('amount');
            })->align('center');
            $grid->column('machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    $this,
                    'playerRecord'
                ], ['id' => $data->id])->width('85%')->title($data->name . ' ' . $data->uuid);
            })->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.name'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideAdd();
            $grid->expandFilter();
            $grid->tools(
                $grid->addButton()->modal($this->form())
            );
            $grid->actions(function (Actions $actions, Player $data) {
                $dropdown = $actions->dropdown();
                $dropdown->item(admin_trans('player.cancel_coin'), 'fas fa-key')
                    ->confirm(admin_trans('player.cancel_coin_confirm'), [$this, 'setCoin'],
                        ['id' => $data->id, 'is_coin' => 0])
                    ->gridRefresh();

                $dropdown->item(admin_trans('player.coin_recharge'), 'far fa-money-bill-alt')
                    ->modal($this->coinRecharge($data->id,
                        $data->currency))->title(admin_trans('player.coin_recharge_title', '',
                        ['{uuid}' => $data->uuid]));

                $dropdown->item(admin_trans('player.coin_artificial_withdraw'), 'far fa-money-bill-alt')
                    ->modal($this->coinWithdraw($data->id))->title(admin_trans('player.coin_artificial_withdraw', '',
                        ['{uuid}' => $data->uuid]));

                $actions->hideDel();
                $actions->edit()->modal($this->form())->width('60%');
            });
            $grid->updateing(function ($ids, $data) {
                if (isset($ids[0]) && isset($data['player_extend'])) {
                    if (PlayerExtend::updateOrCreate(
                        ['player_id' => $ids[0]],
                        $data['player_extend']
                    )) {
                        return message_success(admin_trans('player.remark_edit_success'));
                    }
                }
            });
        });
    }

    /**
     * 币商充值
     * @auth true
     * @group channel
     * @param $id
     * @param $currency
     * @return Form
     */
    public function coinRecharge($id, $currency): Form
    {
        return Form::create([], function (Form $form) use ($id, $currency) {
            $select = $form->select('currency')
                ->options(plugin()->webman->config('currency'))
                ->value(Admin::user()->department->channel->currency)
                ->disabled();
            $form->popItem();
            $form->row(function (Form $form) use ($select) {
                $form->number('money')
                    ->placeholder(admin_trans('player.coin_recharge_money'))
                    ->addonAfter($select)
                    ->min(1)
                    ->max(100000000)
                    ->precision(2)
                    ->required()
                    ->span(13)
                    ->style(['width' => '101%']);
                $form->number('point')
                    ->placeholder(admin_trans('player.coin_recharge_point'))
                    ->min(1)
                    ->max(100000000)
                    ->precision(2)
                    ->required()
                    ->span(11)
                    ->style(['width' => '100%']);
            })->style(['width' => '84%', 'margin' => '0px 8%']);
            $form->textarea('remark')
                ->maxlength(250)
                ->showCount()
                ->rows(4)
                ->style(['width' => '84%', 'margin' => '0 auto']);
            $form->saving(function (Form $form) use ($id) {
                /** @var Player $player */
                $player = Player::where('id', $id)->whereNull('deleted_at')->first();
                if (empty($player)) {
                    return message_error(admin_trans('player.not_fount'));
                }
                if ($player->status == 0) {
                    return message_error(admin_trans('player.disable'));
                }
                Db::beginTransaction();
                try {
                    $beforeGameAmount = $player->machine_wallet->money;
                    // 生成订单
                    $playerRechargeRecord = new  PlayerRechargeRecord();
                    $playerRechargeRecord->player_id = $id;
                    $playerRechargeRecord->talk_user_id = $player->talk_user_id;
                    $playerRechargeRecord->department_id = $player->department_id;
                    $playerRechargeRecord->tradeno = createOrderNo();
                    $playerRechargeRecord->player_name = $player->name ?? '';
                    $playerRechargeRecord->player_phone = $player->phone ?? '';
                    $playerRechargeRecord->money = $form->input('money');
                    $playerRechargeRecord->inmoney = $form->input('money');
                    $playerRechargeRecord->currency = $form->input('currency');
                    $playerRechargeRecord->type = PlayerRechargeRecord::TYPE_BUSINESS;
                    $playerRechargeRecord->point = $form->input('point');
                    $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS;
                    $playerRechargeRecord->remark = $form->input('remark');
                    $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
                    $playerRechargeRecord->user_id = Admin::id() ?? 0;
                    $playerRechargeRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
                    $playerRechargeRecord->save();
                    $player->machine_wallet->money = bcadd($player->machine_wallet->money, $playerRechargeRecord->point,
                        2);
                    $player->player_extend->recharge_amount = bcadd($player->player_extend->recharge_amount,
                        $playerRechargeRecord->point, 2);
                    $player->player_extend->coin_recharge_amount = bcadd($player->player_extend->coin_recharge_amount,
                        $playerRechargeRecord->point, 2);
                    $player->push();

                    //寫入金流明細
                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $playerRechargeRecord->player_id;
                    $playerDeliveryRecord->department_id = $playerRechargeRecord->department_id;
                    $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
                    $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE;
                    $playerDeliveryRecord->source = 'coin_recharge';
                    $playerDeliveryRecord->amount = $playerRechargeRecord->point;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                    $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                    $playerDeliveryRecord->save();

                    $tradeno = date('YmdHis') . rand(10000, 99999);
                    $playerMoneyEditLog = new PlayerMoneyEditLog;
                    $playerMoneyEditLog->player_id = $player->id;
                    $playerMoneyEditLog->department_id = $player->department_id;
                    $playerMoneyEditLog->type = PlayerMoneyEditLog::TYPE_INCREASE;
                    $playerMoneyEditLog->action = PlayerMoneyEditLog::COIN_RECHARGE;
                    $playerMoneyEditLog->tradeno = $tradeno;
                    $playerMoneyEditLog->currency = $player->currency;
                    $playerMoneyEditLog->money = $playerRechargeRecord->point;
                    $playerMoneyEditLog->inmoney = $playerRechargeRecord->inmoney;
                    $playerMoneyEditLog->remark = $form->input('remark') ?? '';
                    $playerMoneyEditLog->user_id = Admin::id() ?? 0;
                    $playerMoneyEditLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerMoneyEditLog->origin_money = $beforeGameAmount;
                    $playerMoneyEditLog->after_money = $player->machine_wallet->money;
                    $playerMoneyEditLog->save();
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    Log::error($e->getMessage());
                    return message_error(admin_trans('player.coin_recharge_error'));
                }
                return message_success(admin_trans('player.coin_recharge_success'));
            });
        });
    }

    /**
     * 币商提现
     * @auth true
     * @group channel
     * @param $id
     * @return Form
     */
    public function coinWithdraw($id): Form
    {
        return Form::create(new $this->model, function (Form $form) use ($id) {
            $form->number('point', admin_trans('player_withdraw_record.fields.point'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%'])
                ->required();
            $form->number('money', admin_trans('player_withdraw_record.fields.money'))
                ->min(0)
                ->max(100000000)
                ->precision(2)
                ->style(['width' => '100%']);
            $form->textarea('remark', admin_trans('player_withdraw_record.fields.remark'))
                ->maxlength(255)->bindAttr('rows', 4)
                ->required();
            $form->layout('vertical');
            $form->saving(function (Form $form) use ($id) {
                /** @var Player $player */
                $player = Player::where('id', $id)->whereNull('deleted_at')->first();
                if (empty($player)) {
                    return message_error(admin_trans('player.not_fount'));
                }
                if ($player->status == 0) {
                    return message_error(admin_trans('player.disable'));
                }
                if ($player->machine_wallet->money < $form->input('point')) {
                    return message_error(admin_trans('player.insufficient_balance'));
                }
                Db::beginTransaction();
                try {
                    $beforeGameAmount = $player->machine_wallet->money;
                    // 生成订单
                    $playerWithdrawRecord = new PlayerWithdrawRecord();
                    $playerWithdrawRecord->player_id = $player->id;
                    $playerWithdrawRecord->talk_user_id = $player->talk_user_id;
                    $playerWithdrawRecord->department_id = $player->department_id;
                    $playerWithdrawRecord->tradeno = createOrderNo();
                    $playerWithdrawRecord->player_name = $player->name ?? '';
                    $playerWithdrawRecord->player_phone = $player->phone ?? '';
                    $playerWithdrawRecord->money = $form->input('money') ?? 0;
                    $playerWithdrawRecord->point = $form->input('point') ?? 0;
                    $playerWithdrawRecord->fee = 0;
                    $playerWithdrawRecord->inmoney = bcsub($playerWithdrawRecord->money, $playerWithdrawRecord->fee,
                        2); // 实际提现金额
                    $playerWithdrawRecord->type = PlayerWithdrawRecord::TYPE_COIN;
                    $playerWithdrawRecord->bank_type = ChannelRechargeMethod::TYPE_COIN;
                    $playerWithdrawRecord->status = PlayerWithdrawRecord::STATUS_SUCCESS;
                    $playerWithdrawRecord->finish_time = date('Y-m-d H:i:s');
                    $playerWithdrawRecord->remark = $form->input('remark') ?? '';
                    $playerWithdrawRecord->user_id = Admin::id() ?? 0;
                    $playerWithdrawRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerWithdrawRecord->save();
                    // 玩家钱包扣减
                    $player->machine_wallet->money = bcsub($player->machine_wallet->money, $playerWithdrawRecord->point,
                        2);
                    // 更新玩家统计
                    $player->player_extend->withdraw_amount = bcadd($player->player_extend->withdraw_amount,
                        $playerWithdrawRecord->point, 2);
                    $player->push();
                    //寫入金流明細
                    $playerDeliveryRecord = new PlayerDeliveryRecord;
                    $playerDeliveryRecord->player_id = $playerWithdrawRecord->player_id;
                    $playerDeliveryRecord->department_id = $playerWithdrawRecord->department_id;
                    $playerDeliveryRecord->target = $playerWithdrawRecord->getTable();
                    $playerDeliveryRecord->target_id = $playerWithdrawRecord->id;
                    $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_WITHDRAWAL;
                    $playerDeliveryRecord->withdraw_status = $playerWithdrawRecord->status;
                    $playerDeliveryRecord->source = 'artificial_withdrawal';
                    $playerDeliveryRecord->amount = $playerWithdrawRecord->point;
                    $playerDeliveryRecord->amount_before = $beforeGameAmount;
                    $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
                    $playerDeliveryRecord->tradeno = $playerWithdrawRecord->tradeno ?? '';
                    $playerDeliveryRecord->remark = $playerWithdrawRecord->remark ?? '';
                    $playerDeliveryRecord->save();
                    $tradeno = date('YmdHis') . rand(10000, 99999);

                    $playerMoneyEditLog = new PlayerMoneyEditLog;
                    $playerMoneyEditLog->player_id = $player->id;
                    $playerMoneyEditLog->department_id = $player->department_id;
                    $playerMoneyEditLog->type = PlayerMoneyEditLog::TYPE_DEDUCT;
                    $playerMoneyEditLog->action = PlayerMoneyEditLog::COIN_WITHDRAWAL;
                    $playerMoneyEditLog->tradeno = $tradeno;
                    $playerMoneyEditLog->currency = $player->currency;
                    $playerMoneyEditLog->money = $playerWithdrawRecord->point;
                    $playerMoneyEditLog->inmoney = bcsub($playerWithdrawRecord->money, $playerWithdrawRecord->fee, 2);
                    $playerMoneyEditLog->remark = $form->input('remark') ?? '';
                    $playerMoneyEditLog->user_id = Admin::id() ?? 0;
                    $playerMoneyEditLog->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
                        [], 'message');
                    $playerMoneyEditLog->origin_money = $beforeGameAmount;
                    $playerMoneyEditLog->after_money = $player->machine_wallet->money;
                    $playerMoneyEditLog->save();
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('player.artificial_withdrawal_error'));
                }
                return message_success(admin_trans('player.artificial_withdrawal_success'));
            });
        });
    }

    /**
     * 玩家记录
     * @param $id
     * @auth true
     * @group channel
     * @return Card
     */
    public function playerRecord($id): Card
    {
        $tabs = Tabs::create()
            ->pane(admin_trans('player.player_recharge_record'), $this->rechargeRecord($id))
            ->pane(admin_trans('player.player_withdraw_record'), $this->withdrawalRecords($id))
            ->pane(admin_trans('player.player_game_record'), $this->gameRecord($id))
            ->pane(admin_trans('player.play_game_record'), $this->playGameRecord($id))
            ->pane(admin_trans('player.player_activity_phase_record'), $this->playerActivityPhaseRecord($id))
            ->pane(admin_trans('player.player_lottery_record'), $this->playerLotteryRecord($id))
            ->pane(admin_trans('player.player_delivery_record'), $this->playerDeliveryRecord($id))
            ->type('card');
        return Card::create($tabs);
    }

    /**
     * 充值记录
     * @param $id
     * @return Grid
     */
    public function rechargeRecord($id): Grid
    {
        return Grid::create(new $this->recharge(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player_recharge_record.title'));
            $grid->bordered();
            $grid->autoHeight();
            $grid->model()->with(['player'])->where('player_id', $id)->where('status',
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->orderBy('created_at', 'desc');
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
            $grid->column('id',
                admin_trans('player_recharge_record.fields.id'))->ellipsis(true)->fixed(true)->align('center');
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = (isset($data->player->avatar) && !empty($data->player->avatar)) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->ellipsis(true)->fixed(true)->align('center');
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->ellipsis(true)->copy();
            $grid->column('talk_tradeno',
                admin_trans('player_recharge_record.fields.talk_tradeno'))->ellipsis(true)->copy();
            $grid->column('channel.name',
                admin_trans('player_recharge_record.fields.department_id'))->ellipsis(true)->align('center');
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
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#cd201f');
                    default:
                        return '';
                }
            })->ellipsis(true)->align('center');
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->ellipsis(true)->align('center');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->ellipsis(true)->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('channel_recharge_setting.bank_name',
                    admin_trans('channel_recharge_setting.fields.bank_name'))->copy()->align('center');
                $grid->column('channel_recharge_setting.sub_bank',
                    admin_trans('channel_recharge_setting.fields.sub_bank'))->copy()->align('center');
                $grid->column('channel_recharge_setting.name',
                    admin_trans('channel_recharge_setting.fields.name'))->copy()->align('center');
                $grid->column('channel_recharge_setting.account',
                    admin_trans('channel_recharge_setting.fields.account'))->copy()->align('center');
            }, admin_trans('channel_recharge_setting.recharge_setting_info'))->ellipsis(true);
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function () {
                return Tag::create(admin_trans('player_recharge_record.status_success'))->color('#87d068');
            })->ellipsis(true)->align('center');
            $grid->column('created_at',
                admin_trans('player_recharge_record.fields.created_at'))->ellipsis(true)->sortable()->align('center');
            $grid->column('finish_time',
                admin_trans('player_recharge_record.fields.finish_time'))->ellipsis(true)->fixed('right')->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_recharge_record.fields.type'))
                    ->options([
                        PlayerRechargeRecord::TYPE_THIRD => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_THIRD),
                        PlayerRechargeRecord::TYPE_SELF => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_SELF),
                        PlayerRechargeRecord::TYPE_BUSINESS => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_BUSINESS),
                        PlayerRechargeRecord::TYPE_ARTIFICIAL => admin_trans('player_recharge_record.type.' . PlayerRechargeRecord::TYPE_ARTIFICIAL),
                    ]);
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_recharge_record.fields.tradeno'));
                $filter->like()->text('talk_tradeno')->placeholder(admin_trans('player_recharge_record.fields.talk_tradeno'));
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

    /**
     * 提现记录
     * @param $id
     * @return Grid
     */
    public function withdrawalRecords($id): Grid
    {
        return Grid::create(new $this->withdraw(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player_withdraw_record.title'));
            $grid->model()->with(['player'])->where('player_id', $id)->where('status',
                PlayerWithdrawRecord::STATUS_SUCCESS)->orderBy('created_at', 'desc');
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
            $grid->bordered();
            $grid->autoHeight();
            $grid->column('id',
                admin_trans('player_withdraw_record.fields.id'))->ellipsis(true)->align('center')->fixed(true);
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player_phone'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $image = (isset($data->player->avatar) && !empty($data->player->avatar)) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center')->ellipsis(true)->fixed(true);
            $grid->column('tradeno',
                admin_trans('player_withdraw_record.fields.tradeno'))->ellipsis(true)->copy()->align('center');
            $grid->column('talk_tradeno',
                admin_trans('player_withdraw_record.fields.talk_tradeno'))->ellipsis(true)->copy()->align('center');
            $grid->column('money', admin_trans('player_withdraw_record.fields.money'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? admin_trans('player_recharge_record.talk_currency') : $data->currency);
            })->ellipsis(true)->align('center');
            $grid->column('point', admin_trans('player_withdraw_record.fields.point'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('bank_name',
                    admin_trans('player_withdraw_record.fields.bank_name'))->copy()->align('center');
                $grid->column('account_name',
                    admin_trans('player_withdraw_record.fields.account_name'))->copy()->align('center');
                $grid->column('account', admin_trans('player_withdraw_record.fields.account'))->copy()->align('center');
            }, admin_trans('player_withdraw_record.player_bank'))->ellipsis(true);
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
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('status', admin_trans('player_withdraw_record.fields.status'))
                ->display(function () {
                    return Tag::create(admin_trans('player_withdraw_record.status.' . PlayerWithdrawRecord::STATUS_SUCCESS))->color('#87d068');
                })->align('center')->ellipsis(true)->sortable();
            $grid->column('channel.name',
                admin_trans('player_present_record.fields.department_id'))->ellipsis(true)->align('center');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->ellipsis(true)->sortable()->align('center');
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->ellipsis(true)->fixed('right')->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_withdraw_record.fields.tradeno'));
                $filter->like()->text('talk_tradeno')->placeholder(admin_trans('player_withdraw_record.fields.talk_tradeno'));
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
        });
    }

    /**
     * 游戏记录
     * @param $id
     * @return Grid
     */
    public function gameRecord($id): Grid
    {
        return Grid::create(new $this->gameLog(), function (Grid $grid) use ($id) {
            $grid->model()->with(['player', 'machine'])->where('player_id', $id)->orderBy('created_at', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_game_log.point_title'));
            $grid->column(function (Grid $grid) {
                $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function (
                    $val,
                    PlayerGameLog $data
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
                    PlayerGameLog $data
                ) {
                    return $data->machine->code;
                })->align('center');
                $grid->column('odds', admin_trans('player_game_log.fields.odds'))->align('center');
            }, admin_trans('player_game_log.machine_info'))->fixed(true);
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
                $grid->column('pressure', admin_trans('player_game_log.fields.pressure'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
                $grid->column('score', admin_trans('player_game_log.fields.score'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
                $grid->column('turn_point', admin_trans('player_game_log.fields.turn_point'))->display(function ($val) {
                    return floatval($val);
                })->align('center');
            }, admin_trans('player_game_log.machine_data'));
            $grid->column('created_at',
                admin_trans('player_game_log.fields.create_at'))->fixed('right')->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
        });
    }

    /**
     * 活动奖励
     * @param $id
     * @return Grid
     */
    public function playerActivityPhaseRecord($id): Grid
    {
        return Grid::create(new $this->playerActivityPhaseRecord, function (Grid $grid) use ($id) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('promoter_profit_record.player_activity_phase_record_title'));
            $grid->model()
                ->where('player_id', $id)
                ->where('status', PlayerActivityPhaseRecord::STATUS_COMPLETE)
                ->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (isset($exAdminFilter['created_at_start']) && !empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (isset($exAdminFilter['created_at_end']) && !empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('name', admin_trans('activity_content.fields.name'))->display(function (
                $val,
                PlayerActivityPhaseRecord $data
            ) use ($lang) {
                /** @var ActivityContent $activityContent */
                $activityContent = $data->activity->activity_content->where('lang', $lang)->first();
                return Html::create($activityContent->name)->style([
                    'cursor' => 'pointer',
                    'color' => 'rgb(24, 144, 255)'
                ])->modal(['addons-webman-controller-ActivityController', 'details'],
                    ['id' => $data->activity_id])->width('60%');
            })->align('center');
            $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function (
                $val,
                PlayerActivityPhaseRecord $data
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
                PlayerActivityPhaseRecord $data
            ) {
                return $data->machine->code ?? '';
            })->align('center');
            $grid->column('bonus', admin_trans('player_activity_phase_record.fields.bonus'))->display(function ($val) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('status', admin_trans('player_activity_phase_record.fields.status'))->display(function () {
                return Tag::create(admin_trans('player_activity_phase_record.status.' . PlayerActivityPhaseRecord::STATUS_COMPLETE))->color('orange');
            })->align('center');
            $grid->column('created_at',
                admin_trans('player_activity_phase_record.fields.created_at'))->align('center')->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('activity_content.name')->placeholder(admin_trans('activity_content.fields.name'));
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
        });
    }

    /**
     * 彩金奖励
     * @param $id
     * @return Grid
     */
    public function playerLotteryRecord($id): Grid
    {
        return Grid::create(new $this->playerLotteryRecord, function (Grid $grid) use ($id) {
            $grid->title(admin_trans('promoter_profit_record.player_lottery_record_title'));
            $grid->model()
                ->where('player_id', $id)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                ->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (isset($exAdminFilter['audit_at_start']) && !empty($exAdminFilter['audit_at_start'])) {
                    $grid->model()->where('audit_at', '>=', $exAdminFilter['audit_at_start']);
                }
                if (isset($exAdminFilter['audit_at_end']) && !empty($exAdminFilter['audit_at_end'])) {
                    $grid->model()->where('audit_at', '<=', $exAdminFilter['audit_at_end']);
                }
            }
            $grid->autoHeight();
            $grid->expandFilter();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->align('center');
            $grid->column('lottery_name', admin_trans('player_lottery_record.fields.lottery_name'))->align('center');
            $grid->column('machine_code', admin_trans('player_lottery_record.fields.machine_code'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Tag::create($data->machine->code)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                })
                ->align('center');

            $grid->column('amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val
            ) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('status', admin_trans('player_lottery_record.fields.status'))->display(function () {
                return Html::create()->content([
                    Tag::create(admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_COMPLETE))->color('#cd201f')
                ]);
            })->align('center');
            $grid->column('audit_at',
                admin_trans('player_lottery_record.fields.audit_at'))->fixed('right')->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('lottery_name')->placeholder(admin_trans('player_lottery_record.fields.lottery_name'));
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->form()->hidden('audit_at_start');
                $filter->form()->hidden('audit_at_end');
                $filter->form()->dateTimeRange('audit_at_start', 'audit_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
        });
    }

    /**
     * 电子游戏记录
     * @param $id
     * @return Grid
     */
    public function playGameRecord($id): Grid
    {
        return Grid::create(new $this->playGameRecord(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->model()->where('player_id',
                $id);
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->whereDate('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->whereDate('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['game_code'])) {
                $grid->model()->where('game_code', $exAdminFilter['game_code']);
            }
            if (!empty($exAdminFilter['order_no'])) {
                $grid->model()->where('order_no', $exAdminFilter['order_no']);
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(bet) as total_bet, sum(diff) as total_diff')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('play_game_record.all_bet'))->value(!empty($totalData['total_bet']) ? floatval($totalData['total_bet']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('play_game_record.all_diff'))->value(!empty($totalData['total_diff']) ? floatval($totalData['total_diff']) : 0)->style([
                            'font-size' => '15px',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '72px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->uuid),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
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
            })->sortable()->align('center');
            $grid->column('diff',
                admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->sortable()->align('center');
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . (float)$val])->style(['color' => 'green']);
            })->align('center');
            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center')->sortable();
            $grid->column('action_at', admin_trans('play_game_record.fields.action_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('admin.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayGameRecord::STATUS_UNSETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_UNSETTLED),
                        PlayGameRecord::STATUS_SETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_SETTLED)
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel.fields.name'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_platform.fields.name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 钱包操作
     * @param $id
     * @return Grid
     */
    public function playerDeliveryRecord($id): Grid
    {
        return Grid::create(new $this->playerDeliveryRecord, function (Grid $grid) use ($id) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('promoter_profit_record.player_activity_phase_record_title'));
            $grid->model()
                ->where('player_id', $id)
                ->whereIn('type', [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,
                    PlayerDeliveryRecord::TYPE_REGISTER_PRESENT,
                    PlayerDeliveryRecord::TYPE_NATIONAL_INVITE,
                ])
                ->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter)) {
                if (isset($exAdminFilter['created_at_start']) && !empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (isset($exAdminFilter['created_at_end']) && !empty($exAdminFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
            $grid->autoHeight();
            $grid->expandFilter();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('source', admin_trans('player_delivery_record.fields.source'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) use ($lang) {
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                    case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                        return Tag::create(trans($val, [], 'message', $lang))->color('red');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('type', admin_trans('player_delivery_record.fields.type'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD))->color('#2db7f5');
                            break;
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE))->color('#CC6600');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center')->sortable();
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->width('150px')->align('center');
            $grid->column('amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                if ($data->amount == 0) {
                    return Html::create()->content([$val])->style(['color' => 'green']);
                }
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                    default:
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                }
            })->align('center');
            $grid->column('user_name', admin_trans('player_delivery_record.fields.user_name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $name = '--';
                if (in_array($data->type, [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                ])) {
                    $name = $data->user_name ?? admin_trans('common.default.admin');
                }
                return Html::create()->content([
                    Html::div()->content($name),
                ]);
            });
            $grid->column('created_at',
                admin_trans('player_delivery_record.fields.created_at'))->align('center')->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('player_delivery_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD),
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT),
                        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT),
                        PlayerDeliveryRecord::TYPE_NATIONAL_INVITE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
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
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    ChannelPlayerController::class,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            });
        })->bordered();
    }

    /**
     * 玩家钱包
     * @auth true
     * @param $data
     * @return Form
     */
    public function increase($data): Form
    {
        return Form::create(new $this->model, function (Form $form) use ($data) {
            $form->hidden('id')->default($data['id']);
            $form->row(function (Form $form) {
                $type = $form->getBindField('type');
                $form->radio('type', admin_trans('player.wallet.type'))
                    ->button()
                    ->disabled($form->isEdit())
                    ->default(PlayerMoneyEditLog::TYPE_INCREASE)
                    ->options([
                        admin_trans('player.wallet.deduct'),
                        admin_trans('player.wallet.increase'),
                    ])->required()->span(7);
            });
            $form->number('money',
                admin_trans('player.wallet.money'))->min(0)->max(100000000)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player.wallet.machine_wallet') . ' ' . $data['money'] ?? 0)->required();
            $form->actions()->hideResetButton();
            $form->saving(function (Form $form) use ($data) {
                $type = $form->input('type');

                $deliveryType = $type == 1 ? PlayerDeliveryRecord::COIN_ADD : PlayerDeliveryRecord::COIN_DEDUCT;
                return $this->store([
                    'id' => $form->input('id'),
                    'type' => $form->input('type'),
                    'deduct_action' => PlayerMoneyEditLog::COIN_DEDUCT,
                    'increase_action' => PlayerMoneyEditLog::COIN_INCREASE,
                    'money' => $form->input('money'),
                    'remark' => $form->input('remark'),
                    'activity' => $form->input('activity'),
                    'delivery_type' => $deliveryType,
                    'source' => 'coin_modify'
                ]);
            });
            $form->layout('vertical');
        });
    }

    /**
     * 批量生成玩家账号表单（线下渠道）
     * @auth true
     * @group channel
     * @return Form
     */
    public function batchGenerateForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown(admin_trans('common.tips.offline_channel_only_notice')));
            });
        }

        // 获取当前渠道下的所有代理和店家（新架构）
        $currentDepartmentId = Admin::user()->department_id;

        // 1. 获取当前渠道下的所有代理账号
        $agentAdmins = AdminUser::query()
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('department_id', $currentDepartmentId)
            ->where('status', 1)
            ->get();

        $agentAdminIds = $agentAdmins->pluck('id')->toArray();

        // 2. 获取这些代理下的所有店家账号
        $storeAdmins = [];
        if (!empty($agentAdminIds)) {
            $storeAdmins = AdminUser::query()
                ->where('type', AdminUser::TYPE_STORE)
                ->whereIn('parent_admin_id', $agentAdminIds)
                ->where('status', 1)
                ->get();
        }

        // 3. 构建树形结构数据（代理 -> 店家）
        $storeOptions = [];

        // 先添加代理节点
        foreach ($agentAdmins as $agent) {
            $storeOptions[] = [
                'id' => 'agent_' . $agent->id, // 使用特殊标识，避免与店家ID冲突
                'name' => ($agent->nickname ?: $agent->username) . ' (代理)',
                'pid' => 0,
                'disabled' => true // 禁止选择代理，只能选择店家
            ];
        }

        // 再添加店家节点
        foreach ($storeAdmins as $storeAdmin) {
            $storeOptions[] = [
                'id' => $storeAdmin->id,
                'name' => $storeAdmin->nickname ?: $storeAdmin->username,
                'pid' => 'agent_' . $storeAdmin->parent_admin_id // 父节点是代理
            ];
        }

        $storeTreeOptions = Arr::tree($storeOptions);

        return Form::create([], function (Form $form) use ($storeTreeOptions) {
            $form->title(admin_trans('offline_channel.batch_generate_players_title'));

            $form->push(Html::markdown(admin_trans('common.tips.batch_generate_bind_notice')));

            $form->divider()->content(admin_trans('offline_channel.basic_config'));

            $form->row(function (Form $form) use ($storeTreeOptions) {
                $form->column(function (Form $form) use ($storeTreeOptions) {
                    // 绑定店家（必选，只能选择店家）
                    $form->treeSelect('recommend_id', admin_trans('offline_channel.bind_store_machine'))
                        ->options($storeTreeOptions)
                        ->help(admin_trans('offline_channel.help_bind_store_machine'))
                        ->required();
                })->span(12);

                $form->column(function (Form $form) {
                    // 生成数量
                    $form->number('generate_count', admin_trans('offline_channel.generate_count'))
                        ->default(10)
                        ->min(1)
                        ->max(100)
                        ->precision(0)
                        ->style(['width' => '100%'])
                        ->help(admin_trans('offline_channel.help_generate_count'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->divider()->content(admin_trans('offline_channel.account_config'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    // 账号前缀
                    $form->text('phone_prefix', admin_trans('offline_channel.account_prefix'))
                        ->default('P')
                        ->maxlength(10)
                        ->help(admin_trans('common.help.account_format'))
                        ->required();
                })->span(12);

                $form->column(function (Form $form) {
                    // 账号起始编号
                    $form->number('phone_start_number', admin_trans('offline_channel.account_start_number'))
                        ->default(1)
                        ->min(1)
                        ->max(999999)
                        ->precision(0)
                        ->style(['width' => '100%'])
                        ->help(admin_trans('common.help.number_auto_padding'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->divider()->content(admin_trans('offline_channel.nickname_config'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    // 昵称前缀
                    $form->text('name_prefix', admin_trans('offline_channel.nickname_prefix'))
                        ->default(admin_trans('offline_channel.player_default'))
                        ->maxlength(10)
                        ->help(admin_trans('common.help.nickname_format'))
                        ->required();
                })->span(12);

                $form->column(function (Form $form) {
                    // 昵称起始编号
                    $form->number('name_start_number', admin_trans('offline_channel.nickname_start_number'))
                        ->default(1)
                        ->min(1)
                        ->max(999999)
                        ->precision(0)
                        ->style(['width' => '100%'])
                        ->help(admin_trans('common.help.number_auto_padding_simple'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->divider()->content(admin_trans('offline_channel.avatar_config'));

            // 头像类型选择
            $form->radio('avatar_type', admin_trans('offline_channel.avatar_source'))
                ->button()
                ->default('default')
                ->options([
                    'default' => admin_trans('offline_channel.use_default_avatar'),
                    'upload' => admin_trans('offline_channel.upload_custom_avatar')
                ])
                ->when(['default'], function (Form $form) {
                    $avatarOptions = [];
                    foreach (config('def_avatar') as $url) {
                        // 提取文件名作为显示名称
                        $filename = basename($url);
                        $avatarOptions[$url] = $filename;
                    }
                    $form->select('default_avatar', admin_trans('offline_channel.select_default_avatar'))
                        ->default(config('def_avatar.1'))
                        ->options($avatarOptions)
                        ->help(admin_trans('common.help.all_players_use_this_avatar'))
                        ->required();
                })
                ->when(['upload'], function (Form $form) {
                    $form->image('custom_avatar', admin_trans('offline_channel.upload_avatar'))
                        ->help(admin_trans('common.help.avatar_format_recommendation'))
                        ->required();
                })
                ->required();

            $form->divider()->content(admin_trans('offline_channel.password_config'));

            // 统一密码
            $form->password('password', admin_trans('offline_channel.unified_password'))
                ->rule([
                    'min:6' => admin_trans('offline_channel.error_password_min_6')
                ])
                ->help(admin_trans('common.help.all_accounts_use_this_password'))
                ->required();

            $form->password('password_confirmation', admin_trans('offline_channel.confirm_password'))
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->batchGeneratePlayersSaving($form);
            });
        });
    }

    /**
     * 批量生成玩家账号保存逻辑
     * @param Form $form
     * @return mixed
     */
    private function batchGeneratePlayersSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error(admin_trans('offline_channel.error_offline_channel_only'));
        }

        if (!LevelList::query()->where('department_id', Admin::user()->department_id)->orderBy('must_chip_amount')->exists()) {
            return message_error(admin_trans('player.national_level_not_configure'));
        }

        $phonePrefix = $form->input('phone_prefix');
        $phoneStartNumber = (int)$form->input('phone_start_number');
        $namePrefix = $form->input('name_prefix');
        $nameStartNumber = (int)$form->input('name_start_number');
        $generateCount = (int)$form->input('generate_count');
        $password = $form->input('password');
        $recommendId = $form->input('recommend_id');

        // 获取头像
        $avatarType = $form->input('avatar_type');
        if ($avatarType === 'upload') {
            $avatar = $form->input('custom_avatar');
            if (empty($avatar)) {
                return message_error(admin_trans('offline_channel.error_please_upload_avatar'));
            }
        } else {
            $avatar = $form->input('default_avatar');
            if (empty($avatar)) {
                $avatar = config('def_avatar.1');
            }
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error(admin_trans('offline_channel.error_password_mismatch'));
        }

        // 验证绑定的店家是否存在（新架构：使用AdminUser）
        /** @var AdminUser $storeAdmin */
        $storeAdmin = AdminUser::query()
            ->where('id', $recommendId)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->first();

        if (empty($storeAdmin)) {
            return message_error(admin_trans('offline_channel.error_store_machine_not_exist'));
        }

        // 获取店家所属的代理（通过parent_admin_id）
        $agentAdmin = null;
        if ($storeAdmin->parent_admin_id > 0) {
            $agentAdmin = AdminUser::query()
                ->where('id', $storeAdmin->parent_admin_id)
                ->where('type', AdminUser::TYPE_AGENT)
                ->first();
        }

        Db::beginTransaction();
        try {
            $successCount = 0;
            $failedAccounts = [];

            for ($i = 0; $i < $generateCount; $i++) {
                $currentPhoneNumber = $phoneStartNumber + $i;
                $currentNameNumber = $nameStartNumber + $i;

                // 生成账号和昵称（补齐为4位数字）
                $phone = $phonePrefix . str_pad($currentPhoneNumber, 4, '0', STR_PAD_LEFT);
                $name = $namePrefix . str_pad($currentNameNumber, 4, '0', STR_PAD_LEFT);

                // 检查账号是否已存在
                $existingPlayer = Player::query()->where('phone', $phone)->first();
                if (!empty($existingPlayer)) {
                    $failedAccounts[] = $phone . '(' . admin_trans('common.account_exists') . ')';
                    continue;
                }

                // 创建玩家
                $player = new Player();
                $player->phone = $phone;
                $player->name = $name;
                $player->avatar = $avatar; // 使用选择的头像
                $player->country_code = PhoneSmsLog::COUNTRY_CODE_CH; // 默认中国
                $player->type = Player::TYPE_PLAYER;
                $player->player_type = Player::PLAYER_TYPE_NORMAL; // 批量生成的都是普通玩家
                $player->currency = $channel->currency;
                $player->password = $password;
                $player->uuid = generate15DigitUniqueId();
                $player->department_id = Admin::user()->department_id;
                $player->recommend_code = createCode();
                $player->recommend_id = 0; // 新架构：不使用推荐系统，设为 0

                // 新架构：关联到店家和代理
                $player->store_admin_id = $storeAdmin->id; // 归属店家
                $player->agent_admin_id = $agentAdmin ? $agentAdmin->id : 0; // 归属代理

                $player->save();

                addPlayerExtend($player);

                addRegisterRecord($player->id, PlayerRegisterRecord::TYPE_ADMIN, $player->department_id);

                $successCount++;
            }

            Db::commit();

            if (count($failedAccounts) > 0) {
                $message = admin_trans('common.batch_generate_partial_success', null, [
                    'success' => $successCount,
                    'failed' => count($failedAccounts),
                    'accounts' => implode(', ', $failedAccounts)
                ]);
            } else {
                $message = admin_trans('common.batch_generate_success', null, ['count' => $successCount]);
            }

            return message_success($message);

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('common.batch_generation_failed', null, ['message' => $e->getMessage()]));
        }
    }

    /**
     * 创建代理表单（线下渠道）
     * @auth true
     * @group channel
     * @return Form
     */
    public function createAgentForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown(admin_trans('common.tips.offline_channel_only_notice')));
            });
        }

        return Form::create([], function (Form $form) {
            $form->title(admin_trans('offline_channel.create_agent'));

            // 设置表单label宽度
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">' . admin_trans('offline_channel.tip_create_agent') . '</font>'));

            $form->divider()->content(admin_trans('offline_channel.account_info'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    // 账号（手机号，选填）
                    $form->text('phone', admin_trans('offline_channel.account_phone'))
                        ->maxlength(20)
                        ->help(admin_trans('offline_channel.help_account_phone'));
                })->span(12);

                $form->column(function (Form $form) {
                    // 后台登录账号
                    $form->text('admin_username', admin_trans('offline_channel.admin_username'))
                        ->maxlength(20)
                        ->help(admin_trans('offline_channel.help_admin_username'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', admin_trans('offline_channel.agent_name'))
                ->maxlength(50)
                ->help(admin_trans('offline_channel.help_agent_name'))
                ->required();

            $form->divider()->content(admin_trans('offline_channel.avatar_config'));

            $form->image('avatar', admin_trans('offline_channel.upload_avatar'))
                ->help(admin_trans('common.help.avatar_format'))
                ->required();

            $form->divider()->content(admin_trans('offline_channel.password_config'));

            $form->password('password', admin_trans('offline_channel.unified_password'))
                ->rule([
                    'min:6' => admin_trans('offline_channel.error_password_min_6')
                ])
                ->help(admin_trans('common.help.agent_login_password'))
                ->required();

            $form->password('password_confirmation', admin_trans('offline_channel.confirm_password'))
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->createAgentSaving($form);
            });
        });
    }

    /**
     * 创建代理保存逻辑（线下渠道）
     * 代理不创建玩家账号，只创建后台管理账号
     * @param Form $form
     * @return mixed
     */
    private function createAgentSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::query()->where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error(admin_trans('offline_channel.error_offline_channel_only'));
        }

        $phone = $form->input('phone');
        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        if (empty($avatar)) {
            return message_error(admin_trans('offline_channel.error_please_upload_avatar'));
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error(admin_trans('offline_channel.error_password_mismatch'));
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error(strtr(admin_trans('offline_channel.error_admin_username_exists'), ['{username}' => $adminUsername]));
        }

        Db::beginTransaction();
        try {
            $currentDepartmentId = Admin::user()->department_id;
            // 2. 创建后台管理员账号（代理后台超管）
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_AGENT; // 代理类型账号
            $adminUser->department_id = $currentDepartmentId;
            $adminUser->is_super = 1; // 代理后台超管
            $adminUser->save();

            // 3. 分配代理角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.agent_role'); // 代理角色ID（18）
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 4. 创建代理配置（StoreSetting绑定到admin_user_id）
            // home_notice
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $currentDepartmentId;
            $storeSetting->admin_user_id = $adminUser->id; // 绑定到后台账号
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = admin_trans('common.default.welcome_agent_system');
            $storeSetting->status = 1;
            $storeSetting->save();

            // enable_physical_machine
            $storeSettingMachine = new StoreSetting();
            $storeSetting->department_id = $currentDepartmentId;
            $storeSettingMachine->admin_user_id = $adminUser->id;
            $storeSettingMachine->feature = 'enable_physical_machine';
            $storeSettingMachine->num = 1; // 默认启用
            $storeSettingMachine->status = 1;
            $storeSettingMachine->save();

            // enable_live_baccarat
            $storeSettingBaccarat = new StoreSetting();
            $storeSetting->department_id = $currentDepartmentId;
            $storeSettingBaccarat->admin_user_id = $adminUser->id;
            $storeSettingBaccarat->feature = 'enable_live_baccarat';
            $storeSettingBaccarat->num = 1; // 默认启用
            $storeSettingBaccarat->status = 1;
            $storeSettingBaccarat->save();

            Db::commit();

            return message_success(strtr(admin_trans('offline_channel.success_create_agent'), [
                '{name}' => $name,
                '{phone}' => $phone ?: admin_trans('common.default.not_filled'),
                '{username}' => $adminUsername
            ]));

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('common.create_agent_failed', null, ['message' => $e->getMessage()]));
        }
    }

    /**
     * 创建店家表单（线下渠道）
     * @auth true
     * @group channel
     * @return Form
     */
    public function createStoreMachineForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown(admin_trans('common.tips.offline_channel_only_notice')));
            });
        }

        // 获取代理列表（从AdminUser查询，type=TYPE_AGENT）
        $agents = AdminUser::query()
            ->join('admin_department', 'admin_users.department_id', '=', 'admin_department.id')
            ->where('admin_users.type', AdminDepartment::TYPE_AGENT) // 代理类型
            ->where('admin_users.status', 1)
            ->where('admin_users.department_id', Admin::user()->department_id) // 属于当前渠道
            ->select('admin_users.id', 'admin_users.nickname as name')
            ->get();

        $agentOptions = [];
        foreach ($agents as $agent) {
            $agentOptions[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'pid' => 0
            ];
        }
        $agentTreeOptions = Arr::tree($agentOptions);

        return Form::create([], function (Form $form) use ($agentTreeOptions) {
            $form->title(admin_trans('offline_channel.create_store_machine'));

            // 设置表单label宽度
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">' . admin_trans('offline_channel.tip_create_store_machine') . '</font>'));

            $form->divider()->content(admin_trans('offline_channel.account_info'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    // 账号（手机号，选填）
                    $form->text('phone', admin_trans('offline_channel.account_phone'))
                        ->maxlength(20)
                        ->help(admin_trans('offline_channel.help_account_phone'));
                })->span(12);

                $form->column(function (Form $form) {
                    // 后台登录账号
                    $form->text('admin_username', admin_trans('offline_channel.admin_username'))
                        ->maxlength(20)
                        ->help(admin_trans('offline_channel.help_admin_username'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', admin_trans('offline_channel.store_machine_name'))
                ->maxlength(50)
                ->help(admin_trans('offline_channel.help_store_machine_name'))
                ->required();

            $form->divider()->content(admin_trans('offline_channel.parent_agent'));

            // 选择上级代理
            $form->treeSelect('recommend_id', admin_trans('offline_channel.parent_agent'))
                ->options($agentTreeOptions)
                ->help(admin_trans('offline_channel.help_select_parent_agent'))
                ->required();

            $form->divider()->content(admin_trans('common.divider.commission_settings'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    // 代理抽成
                    $form->number('agent_commission', admin_trans('offline_channel.agent_commission'))
                        ->min(0)
                        ->max(100)
                        ->step(0.01)
                        ->default(0)
                        ->help(admin_trans('common.help.agent_commission_help'))
                        ->required();
                })->span(12);

                $form->column(function (Form $form) {
                    // 渠道抽成
                    $form->number('channel_commission', admin_trans('offline_channel.channel_commission'))
                        ->min(0)
                        ->max(100)
                        ->step(0.01)
                        ->default(0)
                        ->help(admin_trans('common.help.channel_commission_help'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->divider()->content(admin_trans('offline_channel.avatar_config'));

            $form->image('avatar', admin_trans('offline_channel.upload_avatar'))
                ->help(admin_trans('common.help.avatar_format'))
                ->required();

            $form->divider()->content(admin_trans('offline_channel.password_config'));

            $form->password('password', admin_trans('offline_channel.unified_password'))
                ->rule([
                    'min:6' => admin_trans('offline_channel.error_password_min_6')
                ])
                ->help(admin_trans('common.help.store_login_password'))
                ->required();

            $form->password('password_confirmation', admin_trans('offline_channel.confirm_password'))
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->createStoreMachineSaving($form);
            });
        });
    }

    /**
     * 创建店家保存逻辑（线下渠道）
     * 店家不创建玩家账号，只创建后台管理账号 + 店家部门
     * StoreSetting绑定到admin_user_id
     * @param Form $form
     * @return mixed
     */
    private function createStoreMachineSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error(admin_trans('offline_channel.error_offline_channel_only'));
        }

        $phone = $form->input('phone');
        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $recommendId = $form->input('recommend_id');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        // 获取抽成比例，确保有效值
        $agentCommission = $form->input('agent_commission');
        $channelCommission = $form->input('channel_commission');

        // 处理空值和无效值
        $agentCommission = is_numeric($agentCommission) ? floatval($agentCommission) : 0.00;
        $channelCommission = is_numeric($channelCommission) ? floatval($channelCommission) : 0.00;

        // 验证范围
        if ($agentCommission < 0 || $agentCommission > 100) {
            return message_error(admin_trans('common.agent_commission_range_error'));
        }
        if ($channelCommission < 0 || $channelCommission > 100) {
            return message_error(admin_trans('common.channel_commission_range_error'));
        }

        if (empty($avatar)) {
            return message_error(admin_trans('offline_channel.error_please_upload_avatar'));
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error(admin_trans('offline_channel.error_password_mismatch'));
        }

        /** @var AdminUser $parentAgent */
        // 检查上级代理是否存在
        $parentAgent = AdminUser::query()
            ->where('id', $recommendId)
            ->where('type', AdminDepartment::TYPE_AGENT) // 必须是代理类型
            ->first();
        if (!$parentAgent) {
            return message_error(admin_trans('offline_channel.error_parent_agent_invalid'));
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error(strtr(admin_trans('offline_channel.error_admin_username_exists'), ['{username}' => $adminUsername]));
        }

        Db::beginTransaction();
        try {
            // 店家直接使用渠道的 department_id，不创建新的部门
            $departmentId = $parentAgent->department_id;

            // 1. 创建后台管理员账号（店家后台超管）
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_STORE; // 店家类型账号
            $adminUser->department_id = $departmentId; // 使用渠道部门ID
            $adminUser->parent_admin_id = $parentAgent->id; // 上级代理ID
            $adminUser->is_super = 0; // 店家不是超级管理员
            $adminUser->agent_commission = $agentCommission; // 代理抽成比例
            $adminUser->channel_commission = $channelCommission; // 渠道抽成比例
            $adminUser->save();

            // 2. 分配店家超管角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.store_role'); // 店家超管角色ID（19）
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 4. 创建店家配置（StoreSetting绑定到admin_user_id）
            // home_notice
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $departmentId;
            $storeSetting->admin_user_id = $adminUser->id; // 绑定到后台账号
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = admin_trans('common.default.welcome_store_system');
            $storeSetting->status = 1;
            $storeSetting->save();

            // enable_physical_machine
            $storeSettingMachine = new StoreSetting();
            $storeSettingMachine->department_id = $departmentId;
            $storeSettingMachine->admin_user_id = $adminUser->id;
            $storeSettingMachine->feature = 'enable_physical_machine';
            $storeSettingMachine->num = 1; // 默认启用
            $storeSettingMachine->status = 1;
            $storeSettingMachine->save();

            // enable_live_baccarat
            $storeSettingBaccarat = new StoreSetting();
            $storeSettingBaccarat->department_id = $departmentId;
            $storeSettingBaccarat->admin_user_id = $adminUser->id;
            $storeSettingBaccarat->feature = 'enable_live_baccarat';
            $storeSettingBaccarat->num = 1; // 默认启用
            $storeSettingBaccarat->status = 1;
            $storeSettingBaccarat->save();

            // 5. 创建默认自动交班配置（早中晚三班）
            $autoShiftConfigs = [
                [
                    'title' => admin_trans('common.shift.morning'),
                    'shift_time' => '08:00:00',
                    'description' => admin_trans('common.shift.morning_desc')
                ],
                [
                    'title' => admin_trans('common.shift.afternoon'),
                    'shift_time' => '16:00:00',
                    'description' => admin_trans('common.shift.afternoon_desc')
                ],
                [
                    'title' => admin_trans('common.shift.night'),
                    'shift_time' => '00:00:00',
                    'description' => admin_trans('common.shift.night_desc')
                ],
            ];

            foreach ($autoShiftConfigs as $configData) {
                $autoShiftConfig = new StoreAutoShiftConfig();
                $autoShiftConfig->department_id = $departmentId;
                $autoShiftConfig->bind_admin_user_id = $adminUser->id;
                $autoShiftConfig->is_enabled = 1; // 默认启用
                $autoShiftConfig->auto_settlement = 1; // 自动结算
                $autoShiftConfig->save();
            }

            Db::commit();

            return message_success(strtr(admin_trans('offline_channel.success_create_store_machine'), [
                '{name}' => $name,
                '{phone}' => $phone ?: admin_trans('common.default.not_filled'),
                '{username}' => $adminUsername,
                '{parent}' => $parentAgent->nickname
            ]));

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('common.create_store_failed', null, ['message' => $e->getMessage()]));
        }
    }

    /**
     * 获取代理选项（用于玩家列表筛选）
     * 注意：这里获取的是代理账号（AdminUser TYPE_AGENT），不是 Player
     * @return Response
     */
    public function getAgentOptions(): Response
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        // 获取当前渠道部门下的所有代理
        $agents = \addons\webman\model\AdminUser::query()
            ->where('type', \addons\webman\model\AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->where('department_id', $admin->department_id)
            ->select(['id', 'nickname', 'username'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($agent) {
                return [
                    'label' => $agent->nickname ?: $agent->username,
                    'value' => $agent->id,
                ];
            });

        return Response::success($agents);
    }

    /**
     * 获取店家选项（用于玩家列表筛选）
     * 注意：这里获取的是店家账号（AdminUser TYPE_STORE），不是 Player
     * @return Response
     */
    public function getStoreOptions(): Response
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        $query = \addons\webman\model\AdminUser::query()
            ->where('admin_users.type', \addons\webman\model\AdminUser::TYPE_STORE)
            ->where('admin_users.status', 1)
            ->select(['admin_users.id', 'admin_users.nickname', 'admin_users.username']);

        // 如果是代理账号，只能看到自己下级的店家
        if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
            $query->where('admin_users.parent_admin_id', $admin->id);
        }
        // 如果是渠道账号，可以看到本渠道所有店家
        elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
            $query->leftJoin('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
                ->where('parent_admin.department_id', $admin->department_id);
        }

        $stores = $query->orderBy('admin_users.id', 'desc')
            ->get()
            ->map(function ($store) {
                return [
                    'label' => $store->nickname ?: $store->username,
                    'value' => $store->id,
                ];
            });

        return Response::success($stores);
    }

    /**
     * 切换电子游戏状态
     * @auth true
     * @group channel
     * @param int $id
     * @param int $status
     * @return Msg
     */
    public function toggleGamePlatform(int $id, int $status): Msg
    {
        try {
            $player = Player::query()->find($id);
            if (!$player) {
                return message_error(admin_trans('player.not_fount'));
            }

            $player->status_game_platform = $status;
            $player->save();

            return message_success(admin_trans('form.save_success'));
        } catch (\Exception $e) {
            return message_error(admin_trans('player.action_error'));
        }
    }

    /**
     * 玩家游戏列表（Grid方式，仅线下渠道）
     * @auth true
     * @param int $player_id
     * @return Grid
     */
    public function playerGameList(int $player_id): Grid
    {
        /** @var Player $player */
        $player = Player::query()->with('channel')->find($player_id);

        if (empty($player)) {
            // 返回空Grid并显示错误
            return Grid::create([], function (Grid $grid) {
                $grid->title('玩家不存在');
            });
        }

        // 只有线下渠道才支持游戏级别权限管理
        if ($player->channel->is_offline != 1) {
            return Grid::create([], function (Grid $grid) {
                $grid->title('该功能仅适用于线下渠道');
            });
        }

        // 获取玩家所在渠道开启的游戏平台
        if (empty($player->channel->game_platform)) {
            return Grid::create([], function (Grid $grid) {
                $grid->title('该渠道未开启任何电子游戏平台');
            });
        }

        $channelGamePlatformIds = json_decode($player->channel->game_platform, true);
        if (empty($channelGamePlatformIds)) {
            return Grid::create([], function (Grid $grid) {
                $grid->title('该渠道未开启任何电子游戏平台');
            });
        }

        // 获取玩家已选择的游戏ID
        $selectedGameIds = PlayerDisabledGame::query()
            ->where('player_id', $player_id)
            ->where('status', 1)
            ->pluck('game_id')
            ->toArray();

        // 获取当前语言环境
        $lang = Container::getInstance()->translator->getLocale();

        return Grid::create(new Game(), function (Grid $grid) use ($selectedGameIds, $player_id, $channelGamePlatformIds, $lang, $player) {
            $grid->title('玩家游戏权限管理 - ' . $player->name);
            $grid->model()->whereIn('platform_id', $channelGamePlatformIds)
                ->where('status', 1)
                ->with(['gamePlatform', 'gameContent' => function ($query) use ($lang) {
                    $query->where('lang', $lang);
                }])
                ->orderBy('platform_id', 'asc')
                ->orderBy('sort', 'desc')
                ->orderBy('id', 'desc');

            $grid->driver()->setPk('id');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $page = Request::input('ex_admin_page', 1);
            $size = Request::input('ex_admin_size', 50);
            $param = [
                'size' => $size,
                'page' => $page,
                'ex_admin_filter' => $exAdminFilter,
                'player_id' => $player_id,
            ];

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->align('center')->width('80px');

            $grid->column('platform_id', '游戏平台')->display(function ($val, Game $data) {
                return Tag::create($data->gamePlatform->name ?? '未知平台')->color('blue');
            })->align('center')->width('120px');

            $grid->column('game_content', '游戏名称')->display(function ($val, Game $data) use ($lang) {
                $content = $data->gameContent ? $data->gameContent->where('lang', $lang)->first() : null;
                $gameName = $content->name ?? '游戏 ID: ' . $data->id;

                if ($content && $content->picture) {
                    $image = Image::create()
                        ->width(50)
                        ->height(50)
                        ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                        ->src($content->picture);
                    return Html::create()->content([
                        $image,
                        Html::div()->content($gameName)->style(['margin-left' => '8px'])
                    ])->style(['display' => 'flex', 'align-items' => 'center']);
                }
                return $gameName;
            })->align('left');

            $grid->column('cate_id', '游戏分类')->display(function ($val, Game $data) {
                return Tag::create(getGameTypeName($val))->color('green');
            })->align('center')->width('100px');

            $grid->column('is_hot', '热门')->display(function ($val) {
                return $val == 1 ? Tag::create('热门')->color('red') : '';
            })->align('center')->width('80px');

            $grid->column('is_new', '新游戏')->display(function ($val) {
                return $val == 1 ? Tag::create('新')->color('orange') : '';
            })->align('center')->width('80px');

            $grid->actions(function (Actions $actions, Game $data) use ($player_id, $selectedGameIds) {
                $actions->hideDel();
                $actions->hideEdit();

                // 判断当前游戏是否被禁用
                $isDisabled = in_array($data->id, $selectedGameIds);

                if ($isDisabled) {
                    // 已禁用，显示"取消禁用"按钮
                    $actions->prepend(
                        Button::create('取消禁用')
                            ->type('default')
                            ->size('small')
                            ->confirm('确认取消禁用该游戏？', [$this, 'toggleGameDisable'], [
                                'player_id' => $player_id,
                                'game_id' => $data->id,
                                'action' => 'enable'
                            ])
                            ->gridRefresh()
                    );
                } else {
                    // 未禁用，显示"禁用游戏"按钮
                    $actions->prepend(
                        Button::create('禁用游戏')
                            ->type('primary')
                            ->size('small')
                            ->danger()
                            ->confirm('确认禁用该游戏？', [$this, 'toggleGameDisable'], [
                                'player_id' => $player_id,
                                'game_id' => $data->id,
                                'action' => 'disable'
                            ])
                            ->gridRefresh()
                    );
                }
            })->align('center');

            $grid->pagination()->pageSize(50);
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();

            $grid->tools(
                Button::create('保存选择的游戏')
                    ->icon(Icon::create('fas fa-save'))
                    ->confirm('确认保存？',
                        [
                            $this,
                            'savePlayerGames?' . http_build_query($param)
                        ])
                    ->gridBatch()->gridRefresh()
                    ->type('primary')
            );

            $grid->filter(function (Filter $filter) use ($channelGamePlatformIds) {
                $filter->eq()->select('platform_id')
                    ->placeholder('游戏平台')
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options(GamePlatform::query()
                        ->whereIn('id', $channelGamePlatformIds)
                        ->pluck('name', 'id')
                        ->toArray());

                $filter->eq()->select('is_hot')
                    ->placeholder('是否热门')
                    ->style(['width' => '120px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => '热门游戏',
                        0 => '普通游戏'
                    ]);

                $filter->eq()->select('is_new')
                    ->placeholder('是否新游戏')
                    ->style(['width' => '120px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => '新游戏',
                        0 => '旧游戏'
                    ]);
            });

            $grid->expandFilter();
        })->selection($selectedGameIds);
    }

    /**
     * 保存玩家游戏权限
     * @auth true
     * @param $selected
     * @param $player_id
     * @param $size
     * @param $page
     * @param array $ex_admin_filter
     * @return Msg
     */
    public function savePlayerGames($selected, $player_id, $size, $page, array $ex_admin_filter = [])
    {
        if (!isset($selected)) {
            return message_error(admin_trans('common.please_select_games'));
        }

        /** @var Player $player */
        $player = Player::query()->with('channel')->find($player_id);

        if (empty($player)) {
            return message_error(admin_trans('common.player_not_exist'));
        }

        // 只有线下渠道才支持游戏级别权限管理
        if ($player->channel->is_offline != 1) {
            return message_error(admin_trans('common.offline_channel_feature_only'));
        }

        // 获取渠道允许的游戏平台
        $channelGamePlatformIds = json_decode($player->channel->game_platform, true);
        if (empty($channelGamePlatformIds)) {
            return message_error(admin_trans('common.channel_no_game_platform'));
        }

        // 验证选择的游戏
        $selectedGames = Game::query()->whereIn('id', $selected)->get();
        if ($selectedGames->isEmpty()) {
            return message_error(admin_trans('common.games_not_found'));
        }

        // 验证游戏是否都在渠道允许的范围内
        foreach ($selectedGames as $game) {
            if (!in_array($game->platform_id, $channelGamePlatformIds)) {
                return message_error(admin_trans('common.games_not_in_channel_scope'));
            }
        }

        // 处理筛选条件（如果有筛选，只删除筛选结果中未选中的）
        $filteredIds = [];
        $filterAdd = false;

        if (!empty($ex_admin_filter['platform_id']) || !empty($ex_admin_filter['cate_id']) ||
            isset($ex_admin_filter['is_hot']) || isset($ex_admin_filter['is_new'])) {

            $gameList = Game::query()
                ->whereIn('platform_id', $channelGamePlatformIds)
                ->where('status', 1)
                ->when(!empty($ex_admin_filter['platform_id']), function ($query) use ($ex_admin_filter) {
                    $query->where('platform_id', $ex_admin_filter['platform_id']);
                })
                ->when(!empty($ex_admin_filter['cate_id']), function ($query) use ($ex_admin_filter) {
                    $query->where('cate_id', $ex_admin_filter['cate_id']);
                })
                ->when(isset($ex_admin_filter['is_hot']), function ($query) use ($ex_admin_filter) {
                    $query->where('is_hot', $ex_admin_filter['is_hot']);
                })
                ->when(isset($ex_admin_filter['is_new']), function ($query) use ($ex_admin_filter) {
                    $query->where('is_new', $ex_admin_filter['is_new']);
                })
                ->orderBy('id', 'desc')
                ->forPage($page, $size)
                ->pluck('id')
                ->toArray();

            $filteredIds = array_diff($gameList, $selected);
            $filterAdd = true;
        }

        Db::beginTransaction();
        try {
            if ($filterAdd) {
                // 如果有筛选条件，只删除筛选结果中未选中的
                if (!empty($filteredIds)) {
                    PlayerDisabledGame::query()
                        ->where('player_id', $player_id)
                        ->whereIn('game_id', $filteredIds)
                        ->delete();
                }
            } else {
                // 没有筛选条件，清空该玩家所有游戏权限
                PlayerDisabledGame::query()
                    ->where('player_id', $player_id)
                    ->delete();
            }

            // 批量插入或更新选中的游戏
            $insertData = [];
            foreach ($selectedGames as $game) {
                $insertData[] = [
                    'player_id' => $player_id,
                    'game_id' => $game->id,
                    'platform_id' => $game->platform_id,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            if (!empty($insertData)) {
                PlayerDisabledGame::query()->upsert(
                    $insertData,
                    ['player_id', 'game_id'],
                    ['platform_id', 'status', 'updated_at']
                );
            }

            Db::commit();

            $count = count($selected);
            $message = $count > 0 ? "成功禁用了 {$count} 个游戏" : "已取消所有游戏禁用";
            return message_success($message)->refresh();
        } catch (Exception $e) {
            Db::rollBack();
            Log::error('save_player_games', [$e->getMessage(), $e->getTrace()]);
            return message_error($e->getMessage() ?? '保存失败');
        }
    }

    /**
     * 切换单个游戏的禁用状态
     * @auth true
     * @group channel
     * @param int $player_id
     * @param int $game_id
     * @param string $action
     * @return Msg
     */
    public function toggleGameDisable(int $player_id, int $game_id, string $action): Msg
    {
        try {
            /** @var Player $player */
            $player = Player::query()->with('channel')->find($player_id);

            if (empty($player)) {
                return message_error(admin_trans('common.player_not_exist'));
            }

            // 只有线下渠道才支持游戏级别权限管理
            if ($player->channel->is_offline != 1) {
                return message_error(admin_trans('common.offline_channel_feature_only'));
            }

            // 验证游戏是否存在
            $game = Game::query()->find($game_id);
            if (empty($game)) {
                return message_error(admin_trans('common.game_not_exist'));
            }

            // 获取渠道允许的游戏平台
            $channelGamePlatformIds = json_decode($player->channel->game_platform, true);
            if (empty($channelGamePlatformIds) || !in_array($game->platform_id, $channelGamePlatformIds)) {
                return message_error(admin_trans('common.game_not_in_channel_scope'));
            }

            Db::beginTransaction();
            try {
                if ($action === 'disable') {
                    // 禁用游戏 - 添加到 PlayerDisabledGame 表
                    PlayerDisabledGame::query()->updateOrCreate(
                        [
                            'player_id' => $player_id,
                            'game_id' => $game_id,
                        ],
                        [
                            'platform_id' => $game->platform_id,
                            'status' => 1,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                    $message = '成功禁用该游戏';
                } elseif ($action === 'enable') {
                    // 取消禁用 - 从 PlayerDisabledGame 表删除
                    PlayerDisabledGame::query()
                        ->where('player_id', $player_id)
                        ->where('game_id', $game_id)
                        ->delete();
                    $message = '成功取消禁用该游戏';
                } else {
                    return message_error(admin_trans('common.invalid_operation'));
                }

                Db::commit();
                return message_success($message)->refresh();
            } catch (Exception $e) {
                Db::rollBack();
                Log::error('toggle_game_disable', [$e->getMessage(), $e->getTrace()]);
                return message_error($e->getMessage() ?? '操作失败');
            }
        } catch (Exception $e) {
            Log::error('toggle_game_disable', [$e->getMessage(), $e->getTrace()]);
            return message_error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 玩家钱包
     * @auth true
     * @param $data
     * @return Form
     */
    public function presentNoPassword($data): Form
    {

        return Form::create([], function (Form $form) use ($data) {
            $form->hidden('id')->default($data['id']);

            // 获取当前店家账号信息（AdminUser）
            /** @var \addons\webman\model\AdminUser $store */
            $store = Admin::user();

            // 货币符号映射
            $currencySymbols = [
                'CNY' => '¥',
                'USD' => '$',
                'VND' => '₫',
                'THB' => '฿',
                'KRW' => '₩',
                'JPY' => '¥',
            ];

            // 默认值
            $currencySymbol = '¥';
            $ratio = 1;

            /** @var Channel $channel */
            $channel = Channel::query()->where('department_id', $store->department_id)->first();
            if ($channel) {
                /** @var Currency $currency */
                $currency = Currency::query()->where('identifying', $channel->currency ?? 'CNY')
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if ($currency) {
                    $currencySymbol = $currencySymbols[$currency->identifying] ?? $currency->identifying;
                    $ratio = $currency->ratio ?? 1;
                }
            }

            // 店家不再有 PlayerPlatformCash，设为 null
            $storePlatformCash = null;
            /** @var PlayerPlatformCash $machinePlatformCash */
            $machinePlatformCash = PlayerPlatformCash::query()->where('player_id', $data['id'])->first();

            $deviceMoneyAmount = bcdiv($machinePlatformCash->money ?? 0, $ratio, 2);
            $tipMessage = admin_trans('channel_agent.tip_device_balance', null, [
                    '{balance}' => $deviceMoneyAmount,
                    '{currency}' => $currencySymbol,
                    '{points}' => $machinePlatformCash->money
                ]) . ' <br>' . admin_trans('channel_agent.tip_exchange_rate', null, [
                    '{currency}' => $currencySymbol,
                    '{ratio}' => $ratio
                ]);
            $form->push(Html::markdown('><font size=1 color="#ff4d4f">' . $tipMessage . '</font>'));

            // 获取开分配置（使用 admin_user_id）
            /** @var OpenScoreSetting $openScoreSetting */
            $openScoreSetting = OpenScoreSetting::query()->where('admin_user_id', $store->id)->first();

            // 如果有开分配置，显示预设金额选项
            if ($openScoreSetting) {
                $presetOptions = ['0' => admin_trans('channel_agent.custom_amount')];

                for ($i = 1; $i <= 6; $i++) {
                    $scoreKey = 'score_' . $i;
                    if ($openScoreSetting->$scoreKey > 0) {
                        // 将游戏点数转换成货币金额显示
                        $moneyAmount = bcdiv($openScoreSetting->$scoreKey, $ratio, 2);
                        $presetOptions[$moneyAmount] = admin_trans('channel_agent.preset_amount') . " {$i}: {$currencySymbol}{$moneyAmount} ({$openScoreSetting->$scoreKey}" . admin_trans('channel_agent.points') . ")";
                    }
                }

                // 添加默认分数选项
                if ($openScoreSetting->default_scores > 0) {
                    $defaultMoney = bcdiv($openScoreSetting->default_scores, $ratio, 2);
                    $presetOptions[$defaultMoney] = admin_trans('channel_agent.default_amount') . ": {$currencySymbol}{$defaultMoney} ({$openScoreSetting->default_scores}" . admin_trans('channel_agent.points') . ")";
                }

                if (count($presetOptions) > 1) {
                    $form->select('preset_amount', admin_trans('channel_agent.quick_amount'))
                        ->options($presetOptions)
                        ->default('0')
                        ->required()
                        ->help(admin_trans('channel_agent.tip_select_preset', null, ['{currency}' => $currencySymbol, '{ratio}' => $ratio]))
                        ->when(['0'], function (Form $form) use ($deviceMoneyAmount, $currencySymbol, $ratio) {
                            // 当选择"自定义金额"时显示输入框
                            // 生成参考金额表
                            $referenceAmounts = [10, 50, 100, 500, 1000];
                            $referenceText = admin_trans('channel_agent.reference') . "：";
                            foreach ($referenceAmounts as $amount) {
                                $points = $amount * $ratio;
                                $referenceText .= " {$amount}{$currencySymbol}={$points}" . admin_trans('channel_agent.points');
                            }

                            $form->number('amount', admin_trans('channel_agent.recharge_amount'))
                                ->min(1)
                                ->max(10000000)
                                ->precision(2)
                                ->style(['width' => '100%'])
                                ->addonBefore("{$currencySymbol}")
                                ->help(admin_trans('channel_agent.tip_reference', null, [
                                    '{currency}' => $currencySymbol,
                                    '{balance}' => $deviceMoneyAmount,
                                    '{ratio}' => $ratio,
                                    '{reference}' => $referenceText
                                ]))
                                ->required();
                        });
                } else {
                    // 如果没有预设金额配置，直接显示输入框
                    $form->number('amount', admin_trans('channel_agent.recharge_amount'))
                        ->min(1)
                        ->max(10000000)
                        ->precision(2)
                        ->style(['width' => '100%'])
                        ->addonBefore("{$currencySymbol}")
                        ->help(admin_trans('channel_agent.device_balance') . ": {$currencySymbol}{$deviceMoneyAmount}，1{$currencySymbol} = {$ratio}" . admin_trans('channel_agent.points'))
                        ->required();

                    // 添加实时计算提示
                    $form->push(Html::markdown(
                        '><div style="margin-top:8px;padding:8px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:4px"><div style="font-size:14px;color:#0369a1"><strong>转换预览：</strong><span id="money-preview-2" style="color:#0c4a6e;font-weight:600">请输入金额</span><span style="margin:0 8px">→</span><span id="points-preview-2" style="color:#0ea5e9;font-weight:700;font-size:16px">0 点</span></div><div style="font-size:12px;color:#64748b;margin-top:4px">汇率：1 ' . $currencySymbol . ' = ' . $ratio . ' 游戏点数</div></div><script>(function(){const r=' . $ratio . ',s="' . $currencySymbol . '";function u(){setTimeout(function(){const i=document.querySelector("input[name=\'amount\']"),m=document.getElementById("money-preview-2"),p=document.getElementById("points-preview-2");i&&m&&p&&(i.addEventListener("input",function(){const v=parseFloat(this.value)||0,pts=Math.floor(v*r);v>0?(m.textContent=s+v.toFixed(2),p.textContent=pts.toLocaleString()+" 点",p.style.color="#0ea5e9"):(m.textContent="请输入金额",p.textContent="0 点",p.style.color="#94a3b8")}),i.value&&i.dispatchEvent(new Event("input")))},100)}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",u):u()})();</script>'
                    ));
                }
            } else {
                // 如果没有开分配置，直接显示输入框
                $form->number('amount', admin_trans('channel_agent.recharge_amount'))
                    ->min(1)
                    ->max(10000000)
                    ->precision(2)
                    ->style(['width' => '100%'])
                    ->addonBefore("{$currencySymbol}")
                    ->help(admin_trans('channel_agent.device_balance') . ": {$currencySymbol}{$deviceMoneyAmount}，1{$currencySymbol} = {$ratio}" . admin_trans('channel_agent.points'))
                    ->required();
            }
            $form->textarea('remark', admin_trans('player.wallet.textarea'))->maxlength(255)->bindAttr('rows',
                4)->required();
            $form->saving(function (Form $form) {
                $acceptId = $form->input('id');
                $presetAmount = $form->input('preset_amount');
                $customAmount = $form->input('amount');
                $remark = $form->input('remark');

                // 确定最终使用的货币金额
                // 情况1: 有预设金额字段
                if ($presetAmount !== null) {
                    if ($presetAmount === '0') {
                        // 选择了自定义金额，使用输入框的值
                        if (!$customAmount) {
                            return message_error(admin_trans('channel_agent.error_amount_required'));
                        }
                        $moneyAmount = $customAmount;
                    } else {
                        // 选择了预设金额（已经是货币金额）
                        $moneyAmount = $presetAmount;
                    }
                } else {
                    // 情况2: 没有预设金额字段（没有配置或只有一个选项）
                    if (!$customAmount) {
                        return message_error(admin_trans('channel_agent.error_amount_required'));
                    }
                    $moneyAmount = $customAmount;
                }

                // 验证货币金额
                if (!is_numeric($moneyAmount) || $moneyAmount <= 0) {
                    return message_error(admin_trans('channel_agent.error_amount_invalid'));
                }

                // 获取店家账号信息（AdminUser）
                /** @var \addons\webman\model\AdminUser $store */
                $store = Admin::user();

                // 获取设备玩家信息
                /** @var Player $devicePlayer */
                $devicePlayer = Player::where('id', $acceptId)->whereNull('deleted_at')->where('department_id',
                    $store->department_id)->first();
                if (empty($devicePlayer)) {
                    return message_error(admin_trans('channel_agent.error_device_not_found'));
                }

                // 检查设备账号状态
                if ($devicePlayer->status == 0) {
                    return message_error(trans('present_account_disabled', [], 'message'));
                }

                // 获取渠道信息
                /** @var Channel $channel */
                $channel = Channel::query()->where('department_id', $store->department_id)->first();
                if (empty($channel)) {
                    return message_error(trans('channel_not_found', [], 'message'));
                }
                if ($channel->recharge_status == 0) {
                    return message_error(trans('recharge_closed', [], 'message'));
                }

                // 获取货币配置
                /** @var Currency $currency */
                $currency = Currency::query()->where('identifying', $channel->currency)->where('status',
                    1)->whereNull('deleted_at')->select(['id', 'identifying', 'ratio'])->first();
                if (empty($currency)) {
                    return message_error(trans('currency_no_setting', [], 'message'));
                }

                // 将货币金额转换成游戏点数
                $money = $moneyAmount; // 用户输入的货币金额
                $scoreAmount = bcmul($money, $currency->ratio, 0); // 转换成游戏点数（整数）
                if ($scoreAmount <= 0) {
                    return message_error(admin_trans('common.invalid_game_points'));
                }
                // 开分逻辑
                DB::beginTransaction();
                try {
                    /** @var PlayerPlatformCash $deviceWallet */
                    $deviceWallet = PlayerPlatformCash::query()->where('player_id', $devicePlayer->id)->lockForUpdate()->first();

                    // 生成充值订单
                    $playerRechargeRecord = new PlayerRechargeRecord();
                    $playerRechargeRecord->player_id = $devicePlayer->id;
                    $playerRechargeRecord->talk_user_id = $devicePlayer->talk_user_id;
                    $playerRechargeRecord->department_id = $devicePlayer->department_id;
                    $playerRechargeRecord->tradeno = createOrderNo();
                    $playerRechargeRecord->player_name = $devicePlayer->name ?? '';
                    $playerRechargeRecord->player_phone = $devicePlayer->phone ?? '';
                    $playerRechargeRecord->money = $money;
                    $playerRechargeRecord->inmoney = $money;
                    $playerRechargeRecord->currency = $currency->identifying;
                    $playerRechargeRecord->type = PlayerRechargeRecord::TYPE_ARTIFICIAL;
                    $playerRechargeRecord->point = $scoreAmount;
                    $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS;
                    $playerRechargeRecord->remark = "店家后台开分" . ($remark ? "：{$remark}" : "");
                    $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
                    $playerRechargeRecord->user_id = Admin::user()->id;
                    $playerRechargeRecord->user_name = Admin::user()->name ?? '';
                    $playerRechargeRecord->save();

                    // 更新玩家钱包
                    $deviceWallet->money = bcadd($deviceWallet->money, $playerRechargeRecord->point, 2);
                    $deviceWallet->save();

                    // 更新玩家充值总额
                    $devicePlayer->player_extend->recharge_amount = bcadd($devicePlayer->player_extend->recharge_amount,
                        $playerRechargeRecord->point, 2);

                    // 全民代理首充返佣逻辑
                    if (isset($devicePlayer->national_promoter->status) && $devicePlayer->national_promoter->status == 0) {
                        $devicePlayer->national_promoter->created_at = $playerRechargeRecord->finish_time;
                        $devicePlayer->national_promoter->status = 1;
                        if (!empty($devicePlayer->recommend_id) && $devicePlayer->channel->national_promoter_status == 1) {
                            // 玩家上级推广员信息
                            /** @var Player $recommendPlayer */
                            $recommendPlayer = Player::query()->find($devicePlayer->recommend_id);
                            // 推广员为全民代理
                            if (!empty($recommendPlayer->national_promoter) && $recommendPlayer->is_promoter < 1) {
                                // 首充返佣金额
                                /** @var PlayerPlatformCash $recommendPlayerWallet */
                                $recommendPlayerWallet = PlayerPlatformCash::query()->where('player_id',
                                    $devicePlayer->recommend_id)->lockForUpdate()->first();
                                $beforeRechargeAmount = $recommendPlayerWallet->money;
                                $rechargeRebate = $recommendPlayer->national_promoter->level_list->recharge_ratio;
                                $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $rechargeRebate, 2);

                                // 写入首充金流明细
                                $playerDeliveryRecord = new PlayerDeliveryRecord;
                                $playerDeliveryRecord->player_id = $recommendPlayer->id;
                                $playerDeliveryRecord->department_id = $recommendPlayer->department_id;
                                $playerDeliveryRecord->target = $playerRechargeRecord->getTable();
                                $playerDeliveryRecord->target_id = $playerRechargeRecord->id;
                                $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE_REWARD;
                                $playerDeliveryRecord->source = 'national_promoter';
                                $playerDeliveryRecord->amount = $rechargeRebate;
                                $playerDeliveryRecord->amount_before = $beforeRechargeAmount;
                                $playerDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                                $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                                $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                                $playerDeliveryRecord->save();

                                // 首充成功之后全民代理邀请奖励
                                $recommendPlayer->national_promoter->invite_num = bcadd($recommendPlayer->national_promoter->invite_num, 1, 0);
                                $recommendPlayer->national_promoter->settlement_amount = bcadd($recommendPlayer->national_promoter->settlement_amount, $rechargeRebate, 2);
                                /** @var NationalInvite $national_invite */
                                $national_invite = NationalInvite::query()->where('min', '<=',
                                    $recommendPlayer->national_promoter->invite_num)
                                    ->where('max', '>=', $recommendPlayer->national_promoter->invite_num)->first();

                                if (!empty($national_invite) && $national_invite->interval > 0 && $recommendPlayer->national_promoter->invite_num % $national_invite->interval == 0) {
                                    $inviteMoney = $national_invite->money;
                                    $amount_before = $recommendPlayerWallet->money;
                                    $recommendPlayerWallet->money = bcadd($recommendPlayerWallet->money, $inviteMoney, 2);
                                    // 写入金流明细
                                    $inviteDeliveryRecord = new PlayerDeliveryRecord;
                                    $inviteDeliveryRecord->player_id = $recommendPlayer->id;
                                    $inviteDeliveryRecord->department_id = $recommendPlayer->department_id;
                                    $inviteDeliveryRecord->target = $national_invite->getTable();
                                    $inviteDeliveryRecord->target_id = $national_invite->id;
                                    $inviteDeliveryRecord->type = PlayerDeliveryRecord::TYPE_NATIONAL_INVITE;
                                    $inviteDeliveryRecord->source = 'national_promoter';
                                    $inviteDeliveryRecord->amount = $inviteMoney;
                                    $inviteDeliveryRecord->amount_before = $amount_before;
                                    $inviteDeliveryRecord->amount_after = $recommendPlayer->machine_wallet->money;
                                    $inviteDeliveryRecord->tradeno = '';
                                    $inviteDeliveryRecord->remark = '';
                                    $inviteDeliveryRecord->save();
                                }
                                $recommendPlayer->push();
                                $recommendPlayerWallet->save();

                                // 全民代理收益记录
                                $nationalProfitRecord = new NationalProfitRecord();
                                $nationalProfitRecord->uid = $playerRechargeRecord->player_id;
                                $nationalProfitRecord->recommend_id = $playerRechargeRecord->player->recommend_id;
                                $nationalProfitRecord->money = $rechargeRebate;
                                $nationalProfitRecord->type = 0;
                                $nationalProfitRecord->status = 1;
                                $nationalProfitRecord->save();
                            }
                        }
                    }

                    $devicePlayer->push();

                    // 写入充值金流明细
                    $rechargeDeliveryRecord = new PlayerDeliveryRecord;
                    $rechargeDeliveryRecord->player_id = $playerRechargeRecord->player_id;
                    $rechargeDeliveryRecord->department_id = $playerRechargeRecord->department_id;
                    $rechargeDeliveryRecord->target = $playerRechargeRecord->getTable();
                    $rechargeDeliveryRecord->target_id = $playerRechargeRecord->id;
                    $rechargeDeliveryRecord->type = PlayerDeliveryRecord::TYPE_RECHARGE;
                    $rechargeDeliveryRecord->source = 'artificial_recharge';
                    $rechargeDeliveryRecord->amount = $playerRechargeRecord->point;
                    $rechargeDeliveryRecord->amount_before = $deviceWallet->money - $playerRechargeRecord->point;
                    $rechargeDeliveryRecord->amount_after = $deviceWallet->money;
                    $rechargeDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                    $rechargeDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                    $rechargeDeliveryRecord->save();

                    // 注意：旧的推荐系统营收统计已移除
                    // 新架构中，营收统计通过 StoreAgentProfitRecord 表记录
                    // 可通过查询相关记录表实时计算，而不是在此累加

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('store_open_score', [$e->getTrace()]);
                    return message_error($e->getMessage() ?? trans('system_error', [], 'message'));
                }

                // 发送充值通知（发送游戏点数）
                queueClient::send('game-depositAmount', [
                    'player_id' => $devicePlayer->id,
                    'amount' => $scoreAmount
                ]);

                return message_success(admin_trans('channel_agent.success_open_score'));
            });
            $form->layout('vertical');
        });
    }
}

<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use addons\webman\model\Channel;
use addons\webman\model\ChannelFinancialRecord;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\GameType;
use addons\webman\model\LevelList;
use addons\webman\model\Machine;
use addons\webman\model\MachineKeepingLog;
use addons\webman\model\MachineKickLog;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\NationalPromoter;
use addons\webman\model\Notice;
use addons\webman\model\PhoneSmsLog;
use addons\webman\model\Player;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerActivityRecord;
use addons\webman\model\PlayerBank;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerGamePlatform;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPresentRecord;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerRegisterRecord;
use addons\webman\model\PlayerTag;
use addons\webman\model\PlayerWashRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\PromoterProfitGameRecord;
use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\SystemSetting;
use addons\webman\service\WalletService;
use app\exception\GameException;
use app\service\machine\MachineServices;
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
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Cache;
use support\Db;
use support\Log;
use think\Exception;
use Webman\Push\PushException;

/**
 * 玩家
 */
class PlayerController
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
    private $playGameRecord;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_model');
        $this->playerTag = plugin()->webman->config('database.player_tag_model');
        $this->gameLog = plugin()->webman->config('database.player_game_log_model');
        $this->withdraw = plugin()->webman->config('database.player_withdraw_record_model');
        $this->recharge = plugin()->webman->config('database.player_recharge_record_model');
        $this->playerActivityPhaseRecord = plugin()->webman->config('database.player_activity_phase_record_model');
        $this->playerLotteryRecord = plugin()->webman->config('database.player_lottery_record_model');
        $this->playerDeliveryRecord = plugin()->webman->config('database.player_delivery_record_model');
        $this->playerBank = plugin()->webman->config('database.player_bank_model');
        $this->playGameRecord = plugin()->webman->config('database.play_game_record_model');
    }

    /**
     * 调用 gk_work 游戏平台代理 API
     * @param string $endpoint API 端点路径
     * @param Player $player 玩家对象
     * @param array $data 请求数据
     * @param string $lang 语言
     * @return array API 响应数据
     * @throws \Exception
     */
    private function callGameProxyApi(string $endpoint, Player $player, array $data = [], string $lang = 'zh-CN'): array
    {
        $workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
        $workerPort = env('GAME_PLATFORM_PROXY_PORT', '8788');
        $proxyUrl = "http://{$workerHost}:{$workerPort}{$endpoint}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Player-Id: ' . $player->id,
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Language: ' . $lang,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \Exception(admin_trans('player.game_server_connection_failed') . ': ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \Exception(admin_trans('player.game_server_http_error') . ': HTTP ' . $httpCode);
        }

        $result = json_decode($response, true);
        if (empty($result)) {
            throw new \Exception(admin_trans('player.game_server_response_error'));
        }

        if (isset($result['code']) && $result['code'] != 200) {
            throw new GameException($result['msg'] ?? admin_trans('player.game_operation_failed'));
        }

        return $result['data'] ?? [];
    }

    /**
     * 玩家列表
     * @auth true
     * @param int $id
     * @return Grid
     */
    public function index(int $id = 0): Grid
    {
        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $requestFilter = Request::input('ex_admin_filter', []);
        $quickSearch = Request::input('quickSearch', '');
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');
        $query = Player::query()->with(['the_last_player_login_record'])
            ->select([
                'player.*',
                'player_extend.email',
                'player_extend.line',
                'player_extend.recharge_amount',
                'player_extend.withdraw_amount',
                'player_extend.remark',
                'player_extend.present_out_amount',
                'player_extend.present_in_amount',
                'player_extend.third_recharge_amount',
                'player_extend.third_withdraw_amount',
                'channel.name as channel_name',
                'recommend_promoter.uuid as recommend_promoter_uuid',
                'recommend_promoter.phone as recommend_promoter_phone',
                'recommend_promoter.name as recommend_promoter_name',
                'national_promoter.pending_amount',
                'national_promoter.settlement_amount',
                'player_register_record.ip',
                'player_register_record.country_name',
                'player_register_record.city_name',
                'national_level.name as level_name',
                'level_list.level as level',
                'national_promoter.level as level_sort',
            ])
            ->when(!empty($id), function ($query) use ($id) {
                $query->where('player.recommend_id', $id);
            })
            ->leftjoin('channel', 'player.department_id', '=', 'channel.department_id')
            ->leftjoin('player as recommend_promoter', 'recommend_promoter.id', '=', 'player.recommend_id')
            ->leftjoin('player_extend', 'player.id', '=', 'player_extend.player_id')
            ->leftjoin('national_promoter', 'player.id', '=', 'national_promoter.uid')
            ->leftjoin('level_list', 'national_promoter.level', '=', 'level_list.id')
            ->leftjoin('national_level', 'national_level.id', '=', 'level_list.level_id')
            ->leftjoin('player_register_record', 'player.id', '=', 'player_register_record.player_id')
            ->when(!empty($requestFilter['ip']), function ($query) {
                return $query->leftJoin('player_login_record as r', 'player.id', '=', 'r.player_id')
                    ->Join(DB::raw('( SELECT player_id, max( id ) AS id FROM player_login_record GROUP BY player_id) AS t'),
                        function ($join) {
                            $join->on('r.id', '=', 't.id');
                        }
                    );
            })
            ->where('player.type', Player::TYPE_PLAYER);
        if (!empty($quickSearch)) {
            $query->where([
                ['player.name', 'like', '%' . $quickSearch . '%', 'or'],
                ['player.phone', 'like', '%' . $quickSearch . '%', 'or'],
                ['player.uuid', 'like', '%' . $quickSearch . '%', 'or'],
            ]);
        }
        if (!empty($requestFilter)) {
            if (isset($requestFilter['search_type'])) {
                if ($requestFilter['search_type'] == 2) {
                    $query->where('player.is_test', 1);
                } elseif ($requestFilter['search_type'] == 1) {
                    $query->where('player.is_coin', 1);
                } else {
                    $query->where('player.is_test', 0);
                }
            }
            if (!empty($requestFilter['created_at_start'])) {
                $query->where('player.created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $query->where('player.created_at', '<=', $requestFilter['created_at_end']);
            }
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
            if (!empty($requestFilter['recommend_name'])) {
                $query->where([
                        ['recommend_promoter.name', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or'],
                        ['recommend_promoter.phone', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or'],
                        ['recommend_promoter.uuid', 'like', '%' . $requestFilter['recommend_name'] . '%', 'or']
                    ]
                );
            }
            if (isset($requestFilter['search_is_promoter']) && in_array($requestFilter['search_is_promoter'], [0, 1])) {
                $query->where('player.is_promoter', $requestFilter['search_is_promoter']);
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
            if (!empty($requestFilter['department_id'])) {
                $query->where('player.department_id', $requestFilter['department_id']);
            }
            if (!empty($requestFilter['ip'])) {
                $query->where('r.ip', 'like', '%' . $requestFilter['ip'] . '%');
            }
        }
        $totalNum = clone $query;
        $total = $totalNum->get()->count();
        Log::error('$total', [$total]);
        $list = $query->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('id', 'asc');
                })
            ->get()
            ->toArray();

        // ✅ 优化：使用 WalletService 批量从 Redis 缓存获取余额（显示实时余额）
        if (!empty($list)) {
            $playerIds = array_column($list, 'id');
            $balances = WalletService::getBatchBalance($playerIds, PlayerPlatformCash::PLATFORM_SELF);

            // 将 Redis 缓存余额合并到列表数据中（覆盖数据库余额）
            foreach ($list as &$item) {
                // 🔧 修复精度问题：格式化为保留2位小数
                $item['money'] = number_format($balances[$item['id']] ?? 0.0, 2, '.', '');
                // 移除数据库余额字段（仅用于排序）
                unset($item['db_money']);
            }
            unset($item);
        }
        return Grid::create($list, function (Grid $grid) use ($total, $list) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player.fields.id'))->fixed(true)->sortable()->align('center');
            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, $data) {
                $image = !empty($data['avatar']) ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar']) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->fixed(true)->align('center');
            $grid->column('uuid', admin_trans('player.fields.uuid'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('name', admin_trans('player.fields.name'))
                ->editable(
                    (new Editable)
                        ->textarea('name')
                        ->showCount()
                        ->rows(5)
                        ->rule(['max:50' => admin_trans('player.fields.name')])
                )->fixed(true)->ellipsis(true)->align('center');
            $grid->column('recommend_promoter_uuid',
                admin_trans('player.fields.recommend_promoter_name'))->display(function ($value, $data) {
                if (!empty($data['recommend_id'])) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'],
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
            $grid->column('real_name', admin_trans('player.fields.real_name'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)
                    ->textarea('real_name')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:50' => admin_trans('player.fields.real_name')])
            )->width('150px')->align('center');

            $grid->column('level_sort',
                admin_trans('national_promoter.level_list.name'))->display(function ($value, $data) {
                if (!empty($data['level_name'])) {
                    return $data['level_name'] . $data['level'];
                }
                return '';
            })->sortable();

            $grid->column('channel_name', admin_trans('player.fields.department_id'))->width('150px')->align('center');
            $grid->column('pending_amount',
                admin_trans('national_promoter.fields.pending_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('settlement_amount',
                admin_trans('national_promoter.fields.settlement_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    $this,
                    'playerRecord'
                ], ['id' => $data['id']])->width('70%')->title($data['name'] . ' ' . $data['uuid']);
            })->ellipsis(true)->align('center');
            $grid->column('recharge_amount',
                admin_trans('player_extend.fields.recharge_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('withdraw_amount',
                admin_trans('player_extend.fields.withdraw_amount'))->ellipsis(true)->sortable()->align('center');
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
            $grid->column('email', admin_trans('player_extend.fields.email'))->align('center')->ellipsis(true);
            $grid->column('line', admin_trans('player_extend.fields.line'))->align('center')->ellipsis(true);
            $grid->column('present_out_amount',
                admin_trans('player_extend.fields.present_out_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('present_in_amount',
                admin_trans('player_extend.fields.present_in_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('third_recharge_amount',
                admin_trans('player_extend.fields.third_recharge_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->column('third_withdraw_amount',
                admin_trans('player_extend.fields.third_withdraw_amount'))->ellipsis(true)->sortable()->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('recommend_name')->placeholder(admin_trans('player.fields.recommend_promoter_name'));
                $filter->like()->text('ip')->placeholder(admin_trans('player.login_ip'));
                $filter->like()->text('email')->placeholder(admin_trans('player_extend.fields.email'));
                $filter->like()->text('line')->placeholder(admin_trans('player_extend.fields.line'));
                $filter->like()->text('real_name')->placeholder(admin_trans('player.fields.real_name'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_extend.fields.remark'));
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.coin_merchant'),
                        2 => admin_trans('player.fields.is_test'),
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->expandFilter();
            $grid->tools([
                    $grid->addButton()->modal($this->form()),
                    Button::create(admin_trans('player.clear_test'))->style(['margin-left' => '8px'])->confirm(admin_trans('player.clear_test_msg'),
                        [$this, 'clearTest'])
                ]
            );
            $grid->actions(function (Actions $actions, $data) {
                $actions->edit()->modal($this->form())->width('60%');
                $dropdown = $actions->dropdown();
                $dropdown->prepend(admin_trans('admin.reset_password'), 'fas fa-key')
                    ->modal($this->resetPassword($data['id']));
                $dropdown->append(admin_trans('player.wallet.player_wallet'), 'MoneyCollectFilled')
                    ->modal($this->playerWallet([
                        'id' => $data['id'],
                        'money' => $data['money'] ?? 0,
                    ]))->width('600px');
                $dropdown->append(admin_trans('player.wallet.artificial_recharge'), 'TransactionOutlined')
                    ->modal($this->artificialRecharge([
                        'id' => $data['id'],
                        'money' => $data['machine_wallet']['money'] ?? 0,
                    ]))->width('600px')->title(Html::create(admin_trans('player.wallet.artificial_recharge'))->content(
                        ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                            'marginLeft' => '5px',
                            'cursor' => 'pointer'
                        ]))->title(admin_trans('player.wallet.artificial_recharge_tip'))
                    ));
                $dropdown->append(admin_trans('player.wallet.artificial_withdrawal'), 'PayCircleOutlined')
                    ->modal($this->artificialWithdrawal([
                        'id' => $data['id'],
                        'money' => $data['machine_wallet']['money'] ?? 0,
                    ]))->width('600px')->title(Html::create(admin_trans('player.wallet.artificial_withdrawal'))->content(
                        ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                            'marginLeft' => '5px',
                            'cursor' => 'pointer'
                        ]))->title(admin_trans('player.wallet.artificial_withdrawal_tip'))
                    ));
                $dropdown->append(admin_trans('player.player_bank'), 'BankFilled')
                    ->modal($this->playerBank($data['id']))
                    ->width('70%')
                    ->title(Html::create(admin_trans('player.player_bank'))
                        ->content(
                            ToolTip::create(Icon::create('BankFilled')->style([
                                'marginLeft' => '5px',
                                'cursor' => 'pointer'
                            ]))->title(admin_trans('player.player_bank'))
                        ));
                $dropdown->append(admin_trans('player.platform_accounts'), 'AppstoreFilled')
                    ->modal($this->platformAccountList($data['id']))
                    ->width('90%')
                    ->title($data['name'] . ' (' . $data['uuid'] . ') - ' . admin_trans('player.platform_accounts'));
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
     * 玩家信息
     * @auth true
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
                        $form->text('phone',
                            admin_trans('player.fields.phone'))->maxlength(50)->disabled(true);
                        $form->text('name', admin_trans('player.fields.name'))->maxlength(50);
                        $form->radio('avatar_type', admin_trans('player.avatar_type'))
                            ->button()
                            ->default(is_numeric($form->driver()->get('avatar')) ? 2 : 1)
                            ->options([
                                1 => admin_trans('player.upload_avatar'),
                                2 => admin_trans('player.def_avatar')
                            ])
                            ->when(1, function (Form $form) {
                                $form->image('avatar',
                                    admin_trans('player.fields.avatar'))->value(is_numeric($form->driver()->get('avatar')) ? '' : $form->driver()->get('avatar'))->ext('jpg,png,jpeg')->fileSize('1m');
                            })->when(2, function (Form $form) use ($options) {
                                $form->radio('def_avatar', admin_trans('player.def_avatar'))
                                    ->default(1)
                                    ->options($options);
                            });
                        $form->text('player_extend.id_number',
                            admin_trans('player_extend.fields.id_number'))->ruleAlphaNum()->maxlength(20);
                        $form->switch('is_test',
                            admin_trans('player.fields.is_test'));
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
                        ]);
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
                $form->select('department_id', admin_trans('player.fields.department_id'))->remoteOptions(admin_url([
                    'addons-webman-controller-ChannelController',
                    'getDepartmentOptions'
                ]))->required();
                $form->switch('is_test', admin_trans('player.fields.is_test'));
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
                    DB::beginTransaction();
                    try {
                        $player->name = $form->input('name');
                        $player->is_test = $form->input('is_test');
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
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage());
                    }
                    return message_success(admin_trans('player.save_player_info_success'));
                } else {
                    if (!LevelList::query()->where('department_id', $form->input('department_id'))->orderBy('must_chip_amount')->exists()) {
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
                    $channel = Channel::where('department_id', $form->input('department_id'))->first();
                    if (empty($channel)) {
                        return jsonFailResponse(trans('channel_not_found', [], 'message'));
                    }
                    DB::beginTransaction();
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
                        $player->is_test = $form->input('is_test') ?? 0;
                        $player->department_id = $channel->department_id;
                        $player->password = $password;
                        $player->uuid = generate15DigitUniqueId();
                        $player->recommend_code = createCode();
                        $player->save();

                        //创建玩家全民代理身份
                        $national_promoter = new NationalPromoter;
                        $national_promoter->uid = $player->id;
                        $level_min = LevelList::where('department_id', $player->department_id)->orderBy('must_chip_amount')->first();
                        $national_promoter->level = $level_min->id;
                        $national_promoter->save();

                        addPlayerExtend($player);

                        addRegisterRecord($player->id, PlayerRegisterRecord::TYPE_ADMIN, $player->department_id);

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return message_error($e->getMessage());
                    }
                    return message_success(admin_trans('player.save_player_info_success'));
                }
            });
        });
    }

    /**
     * 重置密码
     * @auth true
     * @param $id
     * @return Form
     */
    public function resetPassword($id): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->password('password', admin_trans('player.new_password'))
                ->rule([
                    'confirmed' => admin_trans('player.password_confim_validate'),
                    'min:6' => admin_trans('player.password_min_number')
                ])
                ->value('')
                ->required();
            $form->password('password_confirmation', admin_trans('player.confim_password'))
                ->required();
        });
    }

    /**
     * 玩家钱包
     * @auth true
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
     * 钱包操作
     * @param $data
     * @return Msg
     */
    public function store($data): Msg
    {
        try {
            DB::beginTransaction();
            playerManualSystem($data);
            DB::commit();
        } catch (\Exception $e) {
            echo $e->getMessage();
            DB::rollBack();
            return message_error(admin_trans('player.wallet.wallet_operation_failed'));
        }

        return message_success(admin_trans('player.wallet.wallet_operation_success'));
    }

    /**
     * 人工充值
     * @auth true
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
                DB::beginTransaction();
                try {
                    // ✅ 步骤 1: 获取 Redis 分布式锁（防止并发）
                    $lockKey = "player:balance:lock:{$player->id}";
                    $lock = \support\Redis::set($lockKey, 1, ['NX', 'EX' => 10]);
                    if (!$lock) {
                        return message_error('操作繁忙，请稍后重试');
                    }

                    try {
                        // ✅ 步骤 2: 从 Redis 读取当前余额（唯一可信源）
                        $beforeGameAmount = WalletService::getBalance($player->id);

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

                        $rechargeAmount = $playerRechargeRecord->point;

                        // ✅ 步骤 3: 使用 WalletService 原子性增加余额（自动同步数据库）
                        $newBalance = WalletService::atomicIncrement($player->id, $rechargeAmount);

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
                                    $rechargeRebate = $recommendPlayer->national_promoter->level_list->recharge_ratio;

                                    // ✅ 推荐人奖励：使用 WalletService 原子性增加余额
                                    $beforeRechargeAmount = WalletService::getBalance($recommendPlayer->id);
                                    $recommendNewBalance = WalletService::atomicIncrement($recommendPlayer->id, $rechargeRebate);

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
                                    $playerDeliveryRecord->amount_after = $recommendNewBalance;
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
                                        $amount_before = $recommendNewBalance;

                                        // ✅ 再次增加邀请奖励：使用 WalletService 原子性增加余额
                                        $inviteNewBalance = WalletService::atomicIncrement($recommendPlayer->id, $money);

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
                                        $playerDeliveryRecord->amount_after = $inviteNewBalance;
                                        $playerDeliveryRecord->tradeno = '';
                                        $playerDeliveryRecord->remark = '';
                                        $playerDeliveryRecord->save();
                                    }
                                    $recommendPlayer->push();

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
                        $playerDeliveryRecord->amount_after = $newBalance;  // ✅ 使用 Redis 计算的新值
                        $playerDeliveryRecord->tradeno = $playerRechargeRecord->tradeno ?? '';
                        $playerDeliveryRecord->remark = $playerRechargeRecord->remark ?? '';
                        $playerDeliveryRecord->save();

                        DB::commit();
                    } finally {
                        // ✅ 释放 Redis 锁（无论成功失败都要释放）
                        \support\Redis::del($lockKey);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    \support\Log::error('Admin recharge failed', [
                        'player_id' => $player->id ?? 0,
                        'error' => $e->getMessage(),
                    ]);
                    return message_error(admin_trans('player.artificial_recharge_error'));
                }
                return message_success(admin_trans('player.artificial_recharge_success'));
            });
        });
    }

    /**
     * 人工提现
     * @auth true
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
                // ✅ 从 Redis 读取实时余额进行检查
                if (WalletService::getBalance($player->id) < $form->input('point')) {
                    return message_error(admin_trans('player.insufficient_balance'));
                }
                DB::beginTransaction();
                try {
                    // ✅ 步骤 1: 获取 Redis 分布式锁
                    $lockKey = "player:balance:lock:{$player->id}";
                    $lock = \support\Redis::set($lockKey, 1, ['NX', 'EX' => 10]);
                    if (!$lock) {
                        return message_error('操作繁忙，请稍后重试');
                    }

                    try {
                        // ✅ 步骤 2: 从 Redis 读取当前余额（唯一可信源）
                        $beforeGameAmount = WalletService::getBalance($player->id);

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
                        $playerWithdrawRecord->type = PlayerWithdrawRecord::TYPE_ARTIFICIAL;
                        $playerWithdrawRecord->status = PlayerWithdrawRecord::STATUS_SUCCESS;
                        $playerWithdrawRecord->finish_time = date('Y-m-d H:i:s');
                        $playerWithdrawRecord->remark = $form->input('remark') ?? '';
                        $playerWithdrawRecord->save();

                        $withdrawAmount = $playerWithdrawRecord->point;

                        // ✅ 步骤 3: 使用 WalletService 原子性减少余额（带余额检查）
                        $result = \addons\webman\service\WalletService::atomicDecrement($player->id, $withdrawAmount);

                        if ($result['ok'] == 0) {
                            throw new \Exception('余额不足');
                        }

                        $newBalance = $result['balance'];

                        // ✅ WalletService 已自动同步数据库，无需手动同步
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
                        $playerDeliveryRecord->amount_after = $newBalance;  // ✅ 使用 Redis 计算的新值
                        $playerDeliveryRecord->tradeno = $playerWithdrawRecord->tradeno ?? '';
                        $playerDeliveryRecord->remark = $playerWithdrawRecord->remark ?? '';
                        $playerDeliveryRecord->save();

                        DB::commit();
                    } finally {
                        // ✅ 释放 Redis 锁
                        \support\Redis::del($lockKey);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    \support\Log::error('[Artificial Withdrawal] Failed', [
                        'player_id' => $player->id ?? 0,
                        'error' => $e->getMessage(),
                    ]);
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

                // 更新推荐人的玩家数量（使用 save() 确保事务一致性和触发模型事件）
                $recommendPlayer->player_num = ($recommendPlayer->player_num ?? 0) + 1;
                $recommendPlayer->save();

                return message_success(admin_trans('player.action_success'));
            });
        });
    }

    // 单一钱包模式下不需要钱包转账功能
    // /** @var Player $changePlayer */
    // $playerGamePlatformList = PlayerGamePlatform::query()->whereIn('id', $selected)->get();
    // if (!$playerGamePlatformList) {
    // return message_error(admin_trans('player.not_fount'));
    // }
    // /** @var PlayerGamePlatform $playerGamePlatform */
    // foreach ($playerGamePlatformList as $playerGamePlatform) {
    // if ($playerGamePlatform->gamePlatform->status != 1) {
    // return message_error(admin_trans('player_game_platform.game_platform_disable'));
    // }
    // $lang = locale();
    // $lang = Str::replace('_', '-', $lang);
    // try {
    // $balanceData = $this->callGameProxyApi(
    // '/api/v1/get-balance',
    // $playerGamePlatform->player,
    // ['game_platform_id' => $playerGamePlatform->gamePlatform->id],
    // $lang
    // );
    // $amount = $balanceData['balance'] ?? 0;
    // } catch (\Exception $e) {
    // return message_error($e->getMessage());
    // }
    // if ($amount > 0) {
    // DB::beginTransaction();
    // try {
    // $player = $playerGamePlatform->player;
    // $gamePlatform = $playerGamePlatform->gamePlatform;
    // $playerWalletTransfer = new PlayerWalletTransfer();
    // $playerWalletTransfer->player_id = $player->id;
    // $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
    // $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
    // $playerWalletTransfer->platform_id = $gamePlatform->id;
    // $playerWalletTransfer->department_id = $player->department_id;
    // $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_IN;
    // $playerWalletTransfer->game_amount = $amount;
    // $playerWalletTransfer->player_amount = $player->machine_wallet->money;
    // $playerWalletTransfer->tradeno = createOrderNo();
    // $result = $this->callGameProxyApi(
    // '/api/v1/wallet-transfer-in',
    // $player,
    // [
    // 'game_platform_id' => $gamePlatform->id,
    // 'amount' => $amount,
    // 'take_all' => 'true',
    // ],
    // $lang
    // );
    // $playerWalletTransfer->platform_no = $result['order_id'] ?? '';
    // $playerWalletTransfer->amount = $result['amount'] ?? $amount;
    // $beforeGameAmount = $player->machine_wallet->money;
    // 更新玩家统计
    // $player->machine_wallet->money = bcadd($player->machine_wallet->money,
    // $playerWalletTransfer->amount, 2);
    // $player->push();
    // $playerWalletTransfer->save();

    // $playerDeliveryRecord = new PlayerDeliveryRecord;
    // $playerDeliveryRecord->player_id = $player->id;
    // $playerDeliveryRecord->department_id = $player->department_id;
    // $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
    // $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
    // $playerDeliveryRecord->platform_id = $gamePlatform->id;
    // $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN;
    // $playerDeliveryRecord->source = 'wallet_transfer_in';
    // $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
    // $playerDeliveryRecord->amount_before = $beforeGameAmount;
    // $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
    // $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
    // $playerDeliveryRecord->remark = $target->remark ?? '';
    // $playerDeliveryRecord->user_id = Admin::id();
    // $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
    // [], 'message');
    // $playerDeliveryRecord->save();

    // DB::commit();
    // } catch (Exception|GameException $e) {
    // DB::rollBack();
    // return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
    // }
    // }
    // }

    // return message_success(admin_trans('admin.success'));
    // }

    // if ($playerGamePlatform->gamePlatform->status != 1) {
    // return message_error(admin_trans('player_game_platform.game_platform_disable'));
    // }
    // $lang = locale();
    // $lang = Str::replace('_', '-', $lang);
    // try {
    // $balanceData = $this->callGameProxyApi(
    // '/api/v1/get-balance',
    // $playerGamePlatform->player,
    // ['game_platform_id' => $playerGamePlatform->gamePlatform->id],
    // $lang
    // );
    // $balance = $balanceData['balance'] ?? 0;
    // } catch (\Exception $e) {
    // return message_error($e->getMessage());
    // }
    // return Form::create([], function (Form $form) use ($id, $playerGamePlatform, $balance, $lang) {
    // $form->number('money',
    // admin_trans('player_game_platform.current_balance') . ': ' . $balance)->min(0)->max($balance)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player_game_platform.transfer_out_amount'));
    // $form->switch('take_all', admin_trans('player_game_platform.has_all_transfer_out'));
    // $form->actions()->hideResetButton();
    // $form->saving(function (Form $form) use ($playerGamePlatform, $balance, $lang) {
    // $amount = $form->input('money');
    // $takeAll = $form->input('take_all');
    // if ($takeAll == 0 && $amount > $balance) {
    // return message_error(trans('insufficient_wallet_balance', [], 'message'));
    // }
    // if ($takeAll == 1) {
    // if ($balance <= 0) {
    // return message_error(trans('insufficient_wallet_balance', [], 'message'));
    // }
    // $amount = $balance;
    // }
    // DB::beginTransaction();
    // try {
    // $player = $playerGamePlatform->player;
    // $gamePlatform = $playerGamePlatform->gamePlatform;
    // $playerWalletTransfer = new PlayerWalletTransfer();
    // $playerWalletTransfer->player_id = $player->id;
    // $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
    // $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
    // $playerWalletTransfer->platform_id = $gamePlatform->id;
    // $playerWalletTransfer->department_id = $player->department_id;
    // $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_IN;
    // $playerWalletTransfer->game_amount = $balance;
    // $playerWalletTransfer->player_amount = $player->machine_wallet->money;
    // $playerWalletTransfer->tradeno = createOrderNo();
    // $result = $this->callGameProxyApi(
    // '/api/v1/wallet-transfer-in',
    // $player,
    // [
    // 'game_platform_id' => $gamePlatform->id,
    // 'amount' => $amount,
    // 'take_all' => $takeAll == 1 ? 'true' : 'false',
    // ],
    // $lang
    // );
    // $playerWalletTransfer->platform_no = $result['order_id'] ?? '';
    // $playerWalletTransfer->amount = $result['amount'] ?? $amount;
    // $beforeGameAmount = $player->machine_wallet->money;
    // 更新玩家统计
    // $player->machine_wallet->money = bcadd($player->machine_wallet->money,
    // $playerWalletTransfer->amount, 2);
    // $player->push();
    // $playerWalletTransfer->save();

    // $playerDeliveryRecord = new PlayerDeliveryRecord;
    // $playerDeliveryRecord->player_id = $player->id;
    // $playerDeliveryRecord->department_id = $player->department_id;
    // $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
    // $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
    // $playerDeliveryRecord->platform_id = $gamePlatform->id;
    // $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN;
    // $playerDeliveryRecord->source = 'wallet_transfer_in';
    // $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
    // $playerDeliveryRecord->amount_before = $beforeGameAmount;
    // $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
    // $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
    // $playerDeliveryRecord->remark = $target->remark ?? '';
    // $playerDeliveryRecord->user_id = Admin::id();
    // $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
    // [], 'message');
    // $playerDeliveryRecord->save();

    // DB::commit();
    // } catch (Exception $e) {
    // DB::rollBack();
    // return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
    // } catch (GameException $e) {
    // DB::rollBack();
    // return message_error(admin_trans('player_game_platform.transfer_out_failed') . $e->getMessage());
    // }
    // return message_success(admin_trans('player_game_platform.transfer_out_success'));
    // });
    // $form->layout('vertical');
    // });
    // }

    // if ($playerGamePlatform->gamePlatform->status != 1) {
    // return message_error(admin_trans('player_game_platform.game_platform_disable'));
    // }
    // return Form::create([], function (Form $form) use ($id, $playerGamePlatform) {
    // $form->number('money',
    // admin_trans('player_game_platform.current_balance') . ': ' . $playerGamePlatform->player->machine_wallet->money)->min(0)->max($playerGamePlatform->player->machine_wallet->money)->precision(2)->style(['width' => '100%'])->addonBefore(admin_trans('player_game_platform.transfer_in_amount'));
    // $form->switch('take_all', admin_trans('player_game_platform.has_all_transfer_in'));
    // $form->actions()->hideResetButton();
    // $form->saving(function (Form $form) use ($playerGamePlatform) {
    // $amount = $form->input('money');
    // $takeAll = $form->input('take_all');
    // if ($takeAll == 0 && $amount > $playerGamePlatform->player->machine_wallet->money) {
    // return message_error(admin_trans('player_game_platform.insufficient_account_balance'));
    // }
    // if ($takeAll == 1) {
    // $amount = $playerGamePlatform->player->machine_wallet->money;
    // }
    // $lang = locale();
    // $lang = Str::replace('_', '-', $lang);
    // $player = $playerGamePlatform->player;
    // $gamePlatform = $playerGamePlatform->gamePlatform;
    // $balanceData = $this->callGameProxyApi(
    // '/api/v1/get-balance',
    // $player,
    // ['game_platform_id' => $gamePlatform->id],
    // $lang
    // );
    // $balance = $balanceData['balance'] ?? 0;
    // DB::beginTransaction();
    // try {
    // $playerWalletTransfer = new PlayerWalletTransfer();
    // $playerWalletTransfer->player_id = $player->id;
    // $playerWalletTransfer->parent_player_id = $player->recommend_id ?? 0;
    // $playerWalletTransfer->agent_player_id = $player->recommend_promoter->recommend_id ?? 0;
    // $playerWalletTransfer->platform_id = $gamePlatform->id;
    // $playerWalletTransfer->department_id = $player->department_id;
    // $playerWalletTransfer->type = PlayerWalletTransfer::TYPE_OUT;
    // $playerWalletTransfer->amount = abs($amount);
    // $playerWalletTransfer->game_amount = $balance;
    // $playerWalletTransfer->player_amount = $player->machine_wallet->money;
    // $playerWalletTransfer->tradeno = createOrderNo();
    // $result = $this->callGameProxyApi(
    // '/api/v1/wallet-transfer-out',
    // $player,
    // [
    // 'game_platform_id' => $gamePlatform->id,
    // 'amount' => $amount,
    // ],
    // $lang
    // );
    // $playerWalletTransfer->platform_no = $result['order_id'] ?? '';
    // $playerWalletTransfer->save();
    // $beforeGameAmount = $player->machine_wallet->money;
    // $player->machine_wallet->money = bcsub($player->machine_wallet->money,
    // $playerWalletTransfer->amount, 2);
    // $player->push();

    // $playerDeliveryRecord = new PlayerDeliveryRecord;
    // $playerDeliveryRecord->player_id = $player->id;
    // $playerDeliveryRecord->department_id = $player->department_id;
    // $playerDeliveryRecord->target = $playerWalletTransfer->getTable();
    // $playerDeliveryRecord->target_id = $playerWalletTransfer->id;
    // $playerDeliveryRecord->platform_id = $gamePlatform->id;
    // $playerDeliveryRecord->type = PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT;
    // $playerDeliveryRecord->source = 'wallet_transfer_out';
    // $playerDeliveryRecord->amount = $playerWalletTransfer->amount;
    // $playerDeliveryRecord->amount_before = $beforeGameAmount;
    // $playerDeliveryRecord->amount_after = $player->machine_wallet->money;
    // $playerDeliveryRecord->tradeno = $target->tradeno ?? '';
    // $playerDeliveryRecord->remark = $target->remark ?? '';
    // $playerDeliveryRecord->user_id = Admin::id();
    // $playerDeliveryRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic',
    // [], 'message');
    // $playerDeliveryRecord->save();
    // DB::commit();
    // } catch (Exception $e) {
    // DB::rollBack();
    // return message_error(admin_trans('player_game_platform.transfer_in_failed') . $e->getMessage());
    // } catch (GameException $e) {
    // DB::rollBack();
    // return message_error(admin_trans('player_game_platform.transfer_in_failed') . $e->getMessage());
    // }
    // return message_success(admin_trans('player_game_platform.transfer_in_success'));
    // });
    // $form->layout('vertical');
    // });
    // }

    /**
     * 玩家标签修改保存
     * @return Form
     */
    public function playerTagForm(): Form
    {
        return Form::create(new $this->playerTag, function (Form $form) {
            $form->text('name', admin_trans('player.fields.tag_name'));
            $form->saving(function (Form $form) {
                if ($form->isEdit()) {
                    $id = $form->driver()->get('id');
                    /** @var PlayerTag $tag */
                    $tag = PlayerTag::find($id);
                    $tag->name = $form->input('name');
                    $tag->save();
                } else {
                    $tag = new PlayerTag();
                    $tag->name = $form->input('name');
                    $tag->save();
                }
                return message_success(admin_trans('form.save_success'))->refreshMenu();
            });
        });
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

    /**
     * 获取活动列表
     * @return mixed
     */
    public function getActivity()
    {
        $list = Activity::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
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
     * 切换玩家列表
     * @auth true
     */
    public function changePlayerList($machine_id): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($machine_id) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->model()->orderBy('created_at', 'desc');
            $grid->column('phone', admin_trans('player.fields.phone'))->align('center');
            $grid->column('uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('currency', admin_trans('player.fields.currency'))->align('center');
            $grid->column('email', admin_trans('player.fields.email'))->align('center');
            $grid->column('line', admin_trans('player.fields.line'))->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->pagination()->pageSize(10);
            $grid->selectionType('radio');
            $grid->hideDeleteSelection();
            $grid->tools(
                Button::create(admin_trans('player.btn.change_player'))
                    ->icon(Icon::create('fas fa-user-plus'))
                    ->confirm(admin_trans('player.confirm.change_player_confirm'),
                        [$this, 'changePlayer?machine_id=' . $machine_id])
                    ->gridBatch()
            );
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
            });
        });
    }

    /**
     * 更改玩家
     * @param $selected
     * @param $machine_id
     * @return Msg
     * @throws PushException
     * @throws Exception
     */
    public function changePlayer($selected, $machine_id): Msg
    {
        if (!isset($selected[0])) {
            return message_error(admin_trans('player.not_fount'));
        }
        /** @var Player $changePlayer */
        $changePlayer = Player::find($selected[0]);
        if (!$changePlayer) {
            return message_error(admin_trans('player.not_fount'));
        }
        /** @var Machine $machine */
        $machine = Machine::find($machine_id);
        if ($changePlayer->id == $machine->gaming_user_id) {
            return message_error(admin_trans('player.player_no_change'));
        }

        $selfMachineCount = Machine::where('gaming_user_id', $changePlayer->id)->count();
        $machinePlayNum = $changePlayer->machine_play_num ?? 1;
        if ($changePlayer->type != 4 && $machinePlayNum <= $selfMachineCount) {
            return message_error(admin_trans('player.player_machine_limit', '',
                ['{machinePlayNum}' => $machinePlayNum]));
        }
        $machine->gaming_user_id = $changePlayer->id;
        $machine->gaming = 1;
        $machine->last_game_at = date('Y-m-d H:i:s');
        $machine->save();
        $services = MachineServices::createServices($machine);
        if ($machine->gaming_user_id != $changePlayer->id) {
            //斯洛 移分off
            if ($machine->type == GameType::TYPE_SLOT) {
                if ($services->move_point == 0 && $machine->control_type == Machine::CONTROL_TYPE_MEI) {
                    $services->sendCmd($services::MOVE_POINT_ON, 0, 'player', $changePlayer->id);
                }
                $playerScore = $services->player_score;
                $playerPressure = $services->player_pressure;
                if (empty($playerPressure)) {
                    $services->player_pressure = $services->bet;
                }
                if (empty($playerScore)) {
                    $services->player_score = $services->win;
                }
            }
            if ($machine->type == GameType::TYPE_STEEL_BALL) {
                $services->player_win_number = 0;
            }
            //记录游戏局记录
            /** @var PlayerGameRecord $gameRecord */
            $gameRecord = PlayerGameRecord::where('machine_id', $machine->id)
                ->where('player_id', $changePlayer->id)
                ->where('status', PlayerGameRecord::STATUS_START)
                ->orderBy('created_at', 'desc')
                ->first();
            if (!empty($gameRecord)) {
                // 超过一天的数据更新为已完结
                if (time() - strtotime($gameRecord->updated_at) > 24 * 60 * 60 && $machine->gaming_user_id != $changePlayer->id) {
                    $gameRecord->status = PlayerGameRecord::STATUS_END;
                }
                $gameRecord->open_point = 0;
                $gameRecord->open_amount = 0;
                $gameRecord->give_amount = 0;
                $gameRecord->save();
            }
            createGameLog($gameRecord, $machine, $changePlayer, 0, 0, $changePlayer->machine_wallet->money, 0,
                $changePlayer->machine_wallet->money);
            $machine->last_game_at = date('Y-m-d H:i:s');
            $machine->gaming = 1;
            $machine->gaming_user_id = $changePlayer->id;
            $machine->last_point_at = date('Y-m-d H:i:s');
            $machine->save();

            $services->last_play_time = time();
            $services->gaming = 1;
            $services->gaming_user_id = $changePlayer->id;
            // 玩家上分后需剔除其他观看中玩家
            sendSocketMessage('group-' . $machine->id, [
                'msg_type' => 'machine_start',
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'machine_code' => $machine->code,
                'gaming_user_id' => $machine->gaming_user_id,
            ]);
            /** @var SystemSetting $setting */
            $setting = SystemSetting::where('feature', 'gift_keeping_minutes')->where('status', 1)->first();
            if (!empty($setting) && $setting->num >= 0) {
                $services->keep_seconds = bcmul($setting->num, 60);
                // 发送增加保留时长消息
                sendSocketMessage('player-' . $machine->gaming_user_id . '-' . $machine->id, [
                    'msg_type' => 'player_machine_keeping',
                    'player_id' => $machine->gaming_user_id,
                    'machine_id' => $machine->id,
                    'keep_seconds' => $services->keep_seconds,
                    'keeping' => $services->keeping
                ]);
                sendSocketMessage('player-' . $machine->gaming_user_id, [
                    'msg_type' => 'player_machine_keeping',
                    'player_id' => $machine->gaming_user_id,
                    'machine_id' => $machine->id,
                    'keep_seconds' => $services->keep_seconds,
                    'keeping' => $services->keeping
                ]);
            }
        }

        return message_success(admin_trans('player.player_change_success'));
    }

    /**
     * 玩家记录
     * @param $id
     * @auth true
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
            ->pane(admin_trans('player.player_delivery_record'), $this->playerDeliveryRecord($id));

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
            $grid->model()->with(['channel', 'channel_recharge_setting'])
                ->where('player_id', $id)
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->orderBy('created_at', 'desc');
            $grid->column('id', admin_trans('player_recharge_record.fields.id'))->align('center');
            $grid->column('player_phone', admin_trans('player_recharge_record.fields.player_phone'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                $image = (isset($data->player->avatar) && !empty($data->player->avatar)) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->copy()->align('center');
            $grid->column('talk_tradeno',
                admin_trans('player_recharge_record.fields.talk_tradeno'))->copy()->align('center');
            $grid->column('channel.name', admin_trans('player_recharge_record.fields.department_id'))->align('center');
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
            })->align('center');
            $grid->column('money', admin_trans('player_recharge_record.fields.money'))->display(function (
                $val,
                PlayerRechargeRecord $data
            ) {
                return $val . ' ' . ($data->currency == 'TALK' ? 'Q币' : $data->currency);
            })->align('center');
            $grid->column('point', admin_trans('player_recharge_record.fields.point'))->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('channel_recharge_setting.bank_name',
                    admin_trans('channel_recharge_setting.fields.bank_name'))->copy()->align('center');
                $grid->column('channel_recharge_setting.sub_bank',
                    admin_trans('channel_recharge_setting.fields.sub_bank'))->copy()->align('center');
                $grid->column('channel_recharge_setting.name',
                    admin_trans('channel_recharge_setting.fields.name'))->copy()->align('center');
                $grid->column('channel_recharge_setting.account',
                    admin_trans('channel_recharge_setting.fields.account'))->copy()->align('center');
            }, admin_trans('channel_recharge_setting.recharge_setting_info'));
            $grid->column('status', admin_trans('player_recharge_record.fields.status'))->display(function () {
                return Tag::create(admin_trans('player_recharge_record.status_success'))->color('#87d068');
            })->align('center');
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
     * 提现记录
     * @param $id
     * @return Grid
     */
    public function withdrawalRecords($id): Grid
    {
        return Grid::create(new $this->withdraw(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player_withdraw_record.title'));
            $grid->model()->with(['channel'])->where('player_id', $id)->where('status',
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
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player_phone', admin_trans('player_withdraw_record.fields.player_phone'))->display(function (
                $val,
                PlayerWithdrawRecord $data
            ) {
                $image = (isset($data->player->avatar) && !empty($data->player->avatar)) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
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
            $grid->column(function (Grid $grid) {
                $grid->column('bank_name',
                    admin_trans('player_withdraw_record.fields.bank_name'))->copy()->align('center');
                $grid->column('account_name',
                    admin_trans('player_withdraw_record.fields.account_name'))->copy()->align('center');
                $grid->column('account', admin_trans('player_withdraw_record.fields.account'))->copy()->align('center');
            }, admin_trans('player_withdraw_record.player_bank'));
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
                })->align('center')->sortable();
            $grid->column('channel.name', admin_trans('player_present_record.fields.department_id'))->align('center');
            $grid->column('finish_time',
                admin_trans('player_withdraw_record.fields.finish_time'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_withdraw_record.fields.created_at'))->sortable()->align('center');
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
            $grid->expandFilter();
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
            $grid->model()->with(['player', 'player.channel', 'machine'])->where('player_id',
                $id)->orderBy('created_at', 'desc');
            $quickSearch = Request::input('quickSearch', []);
            if (!empty($quickSearch)) {
                $grid->model()->whereHas('machine', function ($query) use ($quickSearch) {
                    $query->where([
                        ['name', 'like', '%' . $quickSearch . '%', 'or'],
                        ['code', 'like', '%' . $quickSearch . '%', 'or'],
                    ]);
                });
            }
            $grid->bordered();
            $grid->autoHeight();
            $grid->title(admin_trans('player_game_log.point_title'));
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
                $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                    $val,
                    PlayerGameLog $data
                ) {
                    return $data->player->channel->name;
                })->width('150px')->align('center');
            }, admin_trans('player_game_log.player_info'));
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
            $grid->column('created_at', admin_trans('player_game_log.fields.create_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->between()->dateTimeRange('created_at')->placeholder([
                    admin_trans('player_game_log.fields.created_at_start'),
                    admin_trans('player_game_log.fields.created_at_end')
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
                    Html::div()->content($data->player->uuid)
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayGameRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
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
                if (!empty($exAdminFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
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
                    $name = $data->user_name ?? admin_trans('common.administrator');
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
     * 币商列表
     * @auth true
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
                'total_out' => $totalPresentIn['total_out']
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
            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, Player $data) {
                $image = $data->avatar ? Avatar::create()->src(is_numeric($data->avatar) ? config('def_avatar.' . $data->avatar) : $data->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('phone')
            );
            $grid->column('name', admin_trans('player.fields.name'))->align('center')->filter(
                FilterColumn::like()->text('name')
            );
            $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->display(function (
                $val,
                Player $data
            ) {
                return $data->channel->name;
            })->width('150px')->align('center');
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
            $grid->column('status_transfer', admin_trans('player.fields.status_transfer'))->display(function ($val) {
                return Tag::create(admin_trans('admin.open'))->color($val == 1 ? 'blue' : 'red');
            })->align('center');
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });

            $grid->actions(function (Actions $actions, Player $data){
                $actions->edit()->modal($this->form())->width('60%');
                $actions->hideDel();
                $dropdown = $actions->dropdown();

                $dropdown->item(admin_trans('player.coin_recharge'), 'far fa-money-bill-alt')
                    ->modal($this->coinRecharge($data->id,
                        $data->currency))->title(admin_trans('player.coin_recharge_title', '',
                        ['{uuid}' => $data->uuid]));

                $dropdown->item(admin_trans('player.coin_artificial_withdraw'), 'far fa-money-bill-alt')
                    ->modal($this->coinWithdraw($data->id))->title(admin_trans('player.coin_artificial_withdraw', '',
                        ['{uuid}' => $data->uuid]));
                $actions->hideEdit();

            });

            $grid->hideDelete();
            $grid->expandFilter();
            $grid->hideAdd();
            $grid->hideSelection();
            $grid->hideTrashed();
        });
    }

    /**
     * 币商充值
     * @auth true
     * @param $id
     * @param $currency
     * @return Form
     */
    public function coinRecharge($id, $currency): Form
    {
        return Form::create([], function (Form $form) use ($id, $currency) {
            $select = $form->select('currency')
                ->options(plugin()->webman->config('currency'))
                ->value($currency)
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
                DB::beginTransaction();
                try {
                    // ✅ 步骤 1: 获取 Redis 分布式锁
                    $lockKey = "player:balance:lock:{$player->id}";
                    $lock = \support\Redis::set($lockKey, 1, ['NX', 'EX' => 10]);
                    if (!$lock) {
                        return message_error('操作繁忙，请稍后重试');
                    }

                    try {
                        // ✅ 步骤 2: 从 Redis 读取当前余额（唯一可信源）
                        $beforeGameAmount = WalletService::getBalance($player->id);

                        // 生成订单
                        $playerRechargeRecord = new  PlayerRechargeRecord();
                        $playerRechargeRecord->player_id = $id;
                        $playerRechargeRecord->talk_user_id = $player->talk_user_id;
                        $playerRechargeRecord->department_id = $player->department_id;
                        $playerRechargeRecord->tradeno = createOrderNo();
                        $playerRechargeRecord->player_name = $player->name ?? '';
                        $playerRechargeRecord->player_phone = $player->phone ?? '';
                        $playerRechargeRecord->money = $form->input('money')??0;
                        $playerRechargeRecord->inmoney = $form->input('money')??0;
                        $playerRechargeRecord->currency = $form->input('currency');
                        $playerRechargeRecord->type = PlayerRechargeRecord::TYPE_BUSINESS;
                        $playerRechargeRecord->point = $form->input('point');
                        $playerRechargeRecord->status = PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS;
                        $playerRechargeRecord->remark = $form->input('remark');
                        $playerRechargeRecord->finish_time = date('Y-m-d H:i:s');
                        $playerRechargeRecord->user_id = Admin::id() ?? 0;
                        $playerRechargeRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
                        $playerRechargeRecord->save();

                        $rechargeAmount = $playerRechargeRecord->point;

                        // ✅ 步骤 3: 使用 WalletService 原子性增加余额（自动同步数据库）
                        $newBalance = \addons\webman\service\WalletService::atomicIncrement($player->id, $rechargeAmount);

                        // ✅ WalletService 已自动同步数据库，无需手动同步
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
                        $playerDeliveryRecord->amount_after = $newBalance;  // ✅ 使用 Redis 计算的新值
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
                        $playerMoneyEditLog->after_money = $newBalance;  // ✅ 使用 Redis 计算的新值
                        $playerMoneyEditLog->save();

                        DB::commit();
                    } finally {
                        // ✅ 释放 Redis 锁
                        \support\Redis::del($lockKey);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('[Coin Recharge] Failed', [
                        'player_id' => $player->id ?? 0,
                        'error' => $e->getMessage(),
                    ]);
                    return message_error(admin_trans('player.coin_recharge_error'));
                }
                return message_success(admin_trans('player.coin_recharge_success'));
            });
        });
    }

    /**
     * 币商提现
     * @auth true
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
            $form->textarea('remark', admin_trans('player_withdraw_record.fields.remark'))->maxlength(255)
                ->bindAttr('rows', 4)
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
                // ✅ 从 Redis 读取实时余额进行检查
                if (WalletService::getBalance($player->id) < $form->input('point')) {
                    return message_error(admin_trans('player.insufficient_balance'));
                }
                DB::beginTransaction();
                try {
                    // ✅ 添加行锁，防止高并发冲突
                    $playerWallet = PlayerPlatformCash::query()
                        ->where('player_id', $player->id)
                        ->lockForUpdate()
                        ->first();

                    $beforeGameAmount = $playerWallet->money;
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
                    $playerWallet->money = bcsub($playerWallet->money, $playerWithdrawRecord->point, 2);
                    $playerWallet->save(); // ✅ 触发模型事件，自动同步 Redis
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
                    $playerDeliveryRecord->amount_after = $playerWallet->money;
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
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error(admin_trans('player.artificial_withdrawal_error'));
                }
                return message_success(admin_trans('player.artificial_withdrawal_success'));
            });
        });
    }

    /**
     * 清理测试数据
     * @return Msg
     */
    public function clearTest(): Msg
    {
        try {
            DB::beginTransaction();
            $testPlayerIds = Player::query()
                ->where('is_test', 1)
                ->withTrashed()
                ->pluck('id')
                ->toArray();
            if (!empty($testPlayerIdsArray)) {
                // 更新扩展数据
                PlayerExtend::query()
                    ->whereIn('id', $testPlayerIds)
                    ->update([
                        'recharge_amount' => 0,
                        'withdraw_amount' => 0,
                        'present_in_amount' => 0,
                        'present_out_amount' => 0,
                        'third_recharge_amount' => 0,
                        'third_withdraw_amount' => 0,
                        'coin_recharge_amount' => 0,
                    ]);
                // 清理玩家游戏记录
                PlayerGameRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 每局游戏记录
                PlayerGameLog::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 玩家gift数据
                PlayerGiftRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家彩金
                PlayerLotteryRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家赠点
                PlayerPresentRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家充值
                PlayerRechargeRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家充值
                PlayerWithdrawRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家推广
                PromoterProfitGameRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->orWhereIn('promoter_player_id', $testPlayerIds)
                    ->forceDelete();
                PromoterProfitRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->orWhereIn('promoter_player_id', $testPlayerIds)
                    ->orWhereIn('source_player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家下分
                PlayerWashRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家保留
                MachineKeepingLog::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家踢出
                MachineKickLog::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家消息
                Notice::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家短信
                PhoneSmsLog::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家活动领取
                PlayerActivityPhaseRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家活动记录
                PlayerActivityRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
                // 清理玩家活动账变
                PlayerDeliveryRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->whereIn('type', [
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // (管理后台)加点
                        PlayerDeliveryRecord::TYPE_PRESENT_IN,
                        PlayerDeliveryRecord::TYPE_PRESENT_OUT,
                        PlayerDeliveryRecord::TYPE_MACHINE_UP,
                        PlayerDeliveryRecord::TYPE_MACHINE_DOWN,
                        PlayerDeliveryRecord::TYPE_RECHARGE,
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL,
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK,
                        PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
                        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT,
                        PlayerDeliveryRecord::TYPE_PROFIT,
                        PlayerDeliveryRecord::TYPE_LOTTERY,
                    ])
                    ->forceDelete();
                // 清理渠道财务数据
                ChannelFinancialRecord::query()
                    ->whereIn('player_id', $testPlayerIds)
                    ->forceDelete();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return message_error(admin_trans('player.action_error'));
        }

        return message_success(admin_trans('player.action_success'));
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
                    $this,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            });
            $detail->item('id', admin_trans('player.title'))->display(function ($val) {
                //展示所属下级
                return Html::create(admin_trans('machine_operation_log.view'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'promoterPlayers'], ['id' => $val])
                    ->width('80%')->title(admin_trans('player.title'));
            });
        })->bordered();
    }

    /**
     * 直系玩家
     * @param $id
     * @return Grid
     */
    public function promoterPlayers($id): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player.title'));
            $requestFilter = Request::input('ex_admin_filter', []);
            $grid->model()->where('recommend_id', $id)->orderBy('created_at', 'desc');
            if (!empty($requestFilter['search_type'])) {
                $grid->model()->where('is_coin', $requestFilter['search_type']);
            }
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered();
            $grid->column('id', admin_trans('player.fields.id'))->ellipsis(true)->align('center')->fixed(true);
            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, Player $data) {
                $image = $data->avatar ? Avatar::create()->src(is_numeric($data->avatar) ? config('def_avatar.' . $data->avatar) : $data->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                ]);
            })->ellipsis(true)->fixed(true)->align('center')->filter(
                FilterColumn::like()->text('phone')
            );
            $grid->column('name', admin_trans('player.fields.name'))->ellipsis(true)->align('center')->fixed(true);
            $grid->column('uuid', admin_trans('player.fields.uuid'))->ellipsis(true)->align('center');
            $grid->column('player_extend.email', admin_trans('player_extend.fields.email'))->align('center');
            $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, Player $data) {
                $tags[] = Tag::create(admin_trans('player.player'))->color('green');
                if ($data->is_coin == 1) {
                    $tags[] = Tag::create(admin_trans('player.coin_merchant'))->color('#3b5999');
                }
                if ($data->is_promoter == 1) {
                    $tags[] = Tag::create(admin_trans('player.promoter'))->color('purple');
                }
                return Html::create()->content($tags)->style(['display' => 'inline-flex', 'text-align' => 'center']);
            })->ellipsis(true)->width(200)->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('public_msg.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
            })->align('center')->ellipsis(true);
            $grid->column('machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    $this,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            })->ellipsis(true)->align('center');
            $grid->column('created_at', admin_trans('player.fields.created_at'))->display(function (
                $val,
            ) {
                return Html::create()->content([
                    Html::div()->content(date('Y-m-d H:i:s', strtotime($val))),
                ]);
            })->ellipsis(true)->align('center');
            $grid->column('the_last_player_login_record.created_at',
                admin_trans('player.fields.player_login_record'))->display(function ($val, Player $data) {
                return Html::create()->content([
                    Html::div()->content($val ? date('Y-m-d H:i:s', strtotime($val)) : ''),
                ]);
            })->ellipsis(true)->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player_extend.email')->placeholder(admin_trans('player_extend.fields.email'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.coin_merchant'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 第三方游戏平台账号列表
     * @auth true
     * @param int $playerId 玩家ID
     * @return Grid
     */
    public function platformAccountList(int $playerId = 0): Grid
    {
        return Grid::create(new PlayerGamePlatform(), function (Grid $grid) use ($playerId) {
            $grid->title(admin_trans('player_platform_account.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 关联查询玩家信息和游戏平台信息
            $grid->model()->with(['player', 'gamePlatform'])->orderBy('platform_id', 'asc');

            // 如果指定了玩家ID，只显示该玩家的账号
            if ($playerId > 0) {
                $grid->model()->where('player_id', $playerId);
            }

            // 筛选处理
            $exAdminFilter = Request::input('ex_admin_filter', []);

            // 平台ID筛选
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }

            // 状态筛选
            if (isset($exAdminFilter['status']) && $exAdminFilter['status'] !== '') {
                $grid->model()->where('status', $exAdminFilter['status']);
            }

            // 定义列
            $grid->column('id', 'ID')->width(80)->align('center')->sortable();

            $grid->column('gamePlatform.name', admin_trans('player_platform_account.fields.platform_name'))
                ->display(function ($val, PlayerGamePlatform $data) {
                    $color = '#1890ff';
                    return Tag::create($val ?: admin_trans('player_platform_account.unknown_platform'))->color($color);
                })
                ->width(150)->align('center');

            $grid->column('player_code', admin_trans('player_platform_account.fields.player_code'))
                ->width(150)->align('center')->copy();

            $grid->column('player_name', admin_trans('player_platform_account.fields.player_name'))
                ->width(150)->align('center');

            $grid->column('status', admin_trans('player_platform_account.fields.status'))
                ->display(function ($val) {
                    return match ($val) {
                        0 => Tag::create(admin_trans('player_platform_account.status.locked'))->color('red'),
                        1 => Tag::create(admin_trans('player_platform_account.status.normal'))->color('green'),
                        default => Tag::create(admin_trans('player_platform_account.status.unknown'))->color('default'),
                    };
                })
                ->width(100)->align('center');

            $grid->column('created_at', admin_trans('player_platform_account.fields.created_at'))
                ->width(160)->align('center')->sortable();

            // 筛选器
            $grid->filter(function (Filter $filter) use ($playerId) {
                // 游戏平台筛选（始终显示，可以筛选特定玩家在特定平台的账号）
                $filter->eq()->select('platform_id')
                    ->placeholder(admin_trans('player_platform_account.fields.platform_name'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('player_platform_account.fields.status'))
                    ->options([
                        1 => admin_trans('player_platform_account.status.normal'),
                        0 => admin_trans('player_platform_account.status.locked'),
                    ])
                    ->style(['width' => '150px']);
            });

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
        });
    }
}

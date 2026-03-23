<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\Currency;
use addons\webman\model\Game;
use addons\webman\model\GamePlatform;
use addons\webman\model\NationalInvite;
use addons\webman\model\NationalProfitRecord;
use addons\webman\model\OpenScoreSetting;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerDisabledGame;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWalletTransfer;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Exception;
use support\Db;
use support\Log;
use Webman\RedisQueue\Client as queueClient;

/**
 * 账变记录
 * @group channel
 */
class ChannelAgentController
{
    protected $model;

    protected $playerDeliveryRecord;

    protected $storeAgentShiftHandoverRecord;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_model');
        $this->storeAgentShiftHandoverRecord = plugin()->webman->config('database.store_agent_shift_handover_record_model');
        $this->playerDeliveryRecord = plugin()->webman->config('database.player_delivery_record_model');
    }

    /**
     * 店家列表（AdminUser 表的店家账号）
     * 注意：这里查询的是管理账号（AdminUser TYPE_STORE），不是 Player
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        return Grid::create(new \addons\webman\model\AdminUser(), function (Grid $grid) use ($admin) {
            $grid->title('店家管理');
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：店家类型 + 数据权限过滤
            $grid->model()
                ->join('admin_department as dept', 'admin_users.department_id', '=', 'dept.id')
                ->leftJoin('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
                ->where('admin_users.type', \addons\webman\model\AdminUser::TYPE_STORE)
                ->select([
                    'admin_users.*',
                    'dept.name as department_name',
                    'dept.phone as department_phone',
                    'parent_admin.nickname as parent_agent_name',
                    'parent_admin.username as parent_agent_username'
                ]);

            // 根据账号类型过滤店家
            if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                // 代理：查询本代理下的店家
                $grid->model()->where('admin_users.parent_admin_id', $admin->id);
            } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
                // 渠道：查询同一部门下的所有店家
                $grid->model()->where('parent_admin.department_id', $admin->department_id);
            }

            $grid->model()->orderBy('admin_users.id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('nickname', '店家名称')->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val ?? '', 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val)->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('username', '登录账号')->width(120)->align('center');
            $grid->column('department_phone', '联系电话')->width(120)->align('center');

            $grid->column('parent_agent_name_display', admin_trans('admin.agent'))->display(function ($val, $data) {
                $agentName = $data['parent_agent_name'] ?: $data['parent_agent_username'];
                if (!empty($agentName)) {
                    return Tag::create($agentName)->color('blue');
                }
                return Tag::create(admin_trans('admin.unassigned'))->color('default');
            })->width(120)->align('center');

            $grid->column('department_name', '部门名称')->width(150)->ellipsis(true);

            // 分润比例
            $grid->column('agent_commission', '代理抽成')->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create('未设置')->color('default');
                }
                return Tag::create($value . '%')->color('orange');
            })->width(100)->align('center');

            $grid->column('channel_commission', '渠道抽成')->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create('未设置')->color('default');
                }
                return Tag::create($value . '%')->color('blue');
            })->width(100)->align('center');

            $grid->column('status', '状态')->display(function ($value) {
                return match ($value) {
                    0 => Tag::create('已禁用')->color('red'),
                    1 => Tag::create('正常')->color('green'),
                    default => '',
                };
            })->width(80)->align('center');

            $grid->column('created_at', '创建时间')->width(160)->align('center');

            $grid->filter(function (Filter $filter) use ($admin) {
                $filter->eq()->select('admin_users.status')
                    ->placeholder('状态')
                    ->options([
                        1 => '正常',
                        0 => '已禁用'
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('admin_users.username')->placeholder('登录账号');
                $filter->like()->text('admin_users.nickname')->placeholder('店家名称');
                $filter->like()->text('dept.phone')->placeholder('联系电话');

                // 代理筛选（仅渠道可用）
                if ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
                    $filter->eq()->select('admin_users.parent_admin_id')
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('admin.agent'))
                        ->remoteOptions(admin_url([
                            'addons-webman-controller-ChannelAgentController',
                            'getAgentOptionsForFilter'
                        ]));
                }

                $filter->between()->dateTimeRange('admin_users.created_at')
                    ->placeholder(['开始时间', '结束时间']);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideDelete();
            $grid->expandFilter();
        });
    }

    /**
     * 设备列表（普通玩家设备，is_promoter=0）
     * 注意：这里查询的是 Player 表中的普通玩家，不是推广员
     * @group channel
     * @auth true
     */
    public function machineList(): Grid
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $requestFilter = Request::input('ex_admin_filter', []);
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');
        // 查询设备（Player 表中 is_promoter=0 的普通玩家）
        $query = Player::query()->with(['the_last_player_login_record', 'storeAdmin'])
            ->select([
                'player.*',
                'player_extend.present_out_amount',
                'player_extend.present_in_amount',
                'player_extend.machine_put_point',
                'player_platform_cash.money',
                'player_promoter.name as promoter_name',
                'store_admin.username as store_admin_username',
                'store_admin.nickname as store_admin_nickname'
            ])
            ->leftjoin('channel', 'player.department_id', '=', 'channel.department_id')
            ->leftjoin('player_promoter', 'player.recommend_id', '=', 'player_promoter.player_id')
            ->leftjoin('admin_users as store_admin', 'player.store_admin_id', '=', 'store_admin.id')
            ->leftjoin('player_extend', 'player.id', '=', 'player_extend.player_id')
            ->leftjoin('player_platform_cash', 'player.id', '=', 'player_platform_cash.player_id')
            ->where('player.type', Player::TYPE_PLAYER)
            ->where('player.is_promoter', 0);

        // 根据账号类型过滤设备
        if ($admin->type === \addons\webman\model\AdminUser::TYPE_STORE) {
            // 店家：查询本店家下的设备
            $query->where('player.store_admin_id', $admin->id);
        } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
            // 代理：查询所有下级店家的设备
            $storeIds = $admin->childStores()
                ->where('type', \addons\webman\model\AdminUser::TYPE_STORE)
                ->pluck('id')
                ->toArray();
            if (empty($storeIds)) {
                // 代理没有下级店家，返回空结果
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('player.store_admin_id', $storeIds);
            }
        } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
            // 渠道：查询同一部门下的所有设备
            $query->where('player.department_id', $admin->department_id);
        }

        if (!empty($requestFilter)) {
            if (!empty($requestFilter['created_at_start'])) {
                $query->where('player.created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $query->where('player.created_at', '<=', $requestFilter['created_at_end']);
            }
            // 推广员筛选（独立模块，保留）
            if (!empty($requestFilter['recommend_id'])) {
                $query->where('player.recommend_id', $requestFilter['recommend_id']);
            }
            // 店家筛选
            if (!empty($requestFilter['store_admin_id'])) {
                $query->where('player.store_admin_id', $requestFilter['store_admin_id']);
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
        }
        $totalNum = clone $query;
        $total = $totalNum->count();
        $list = $query->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('id', 'desc');
                })
            ->get()
            ->toArray();

        // 获取当前账号的最后结算时间（从 AdminUser 读取）
        $lastSettlementTimestamp = $admin->last_settlement_timestamp;

        foreach ($list as &$item) {
            $totalModel = PlayerDeliveryRecord::query()->whereHas('player', function ($query) use ($item) {
                $query->where('recommend_id', $item['id']);
            })->when(!empty($lastSettlementTimestamp),
                function ($query) use ($lastSettlementTimestamp) {
                    $query->where('created_at', '>=', $lastSettlementTimestamp);
                });
            $totalData = $totalModel->selectRaw('
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', amount, 0)) as total_in,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', amount, 0)) as total_out,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', amount, 0)) as total_point
                ')->first();

            // 注意：machineList 显示的是设备（普通玩家），不计算推广员分润
            // 这里的统计数据是该设备的推荐下级玩家的数据
            $ratio = $admin->ratio ?? 0;  // 使用当前登录账号的分润比例

            $presentInAmount = bcadd(0, $totalData['total_in'] ?? 0, 2);
            $machinePutPoint = bcadd(0, $totalData['total_point'] ?? 0, 2);
            $presentOutAmount = bcadd(0, $totalData['total_out'] ?? 0, 2);
            $totalPoint = bcsub(bcadd($machinePutPoint, $presentInAmount, 2), $presentOutAmount, 2);
            $profitAmount = bcmul($totalPoint, $ratio / 100, 2);
            $item['now_present_in_amount'] = $presentInAmount;
            $item['now_present_out_amount'] = $presentOutAmount;
            $item['now_machine_put_point'] = $machinePutPoint;
            $item['now_profit_amount'] = $profitAmount;
            $item['now_total_point'] = $totalPoint;
            $item['store_ratio'] = $ratio;
        }

        return Grid::create($list, function (Grid $grid) use ($admin, $total, $list) {
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player.fields.id'))->fixed(true)->align('center');
            $grid->column('name', admin_trans('channel_agent.account'))->display(function ($val, $data) {
                $image = !empty($data['avatar']) ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar']) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->width('100px')->ellipsis(true)->fixed(true)->align('center');
            $grid->column('uuid', admin_trans('player.fields.uuid'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val
            ) {
                return Tag::create($val)->color('orange');
            })->sortable()->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('now_present_in_amount', admin_trans('channel_agent.present_in'))->width('100px')->align('center');
                $grid->column('now_present_out_amount', admin_trans('channel_agent.present_out'))->width('100px')->align('center');
                $grid->column('now_machine_put_point', admin_trans('channel_agent.machine_put'))->width('100px')->align('center');
                $grid->column('now_total_point', admin_trans('channel_agent.total_revenue'))->display(function ($value) {
                    return Html::create(number_format($value, 2))->style(['color' => $value >= 0 ? 'green' : 'red']);
                })->width('100px')->align('center');
                $grid->column('store_ratio', admin_trans('channel_agent.store_profit_rate'))->width('100px')->append('%')->align('center');
                $grid->column('now_profit_amount', admin_trans('channel_agent.store_profit_amount'))->display(function ($value) {
                    return Html::create(number_format($value, 2))->style(['color' => $value >= 0 ? 'green' : 'red']);
                })->width('100px')->align('center');
            }, admin_trans('channel_agent.current_data'))->ellipsis(true);
            $grid->column('store_admin_name', admin_trans('admin.store'))->display(function ($val, $data) {
                $storeName = $data['store_admin_nickname'] ?: $data['store_admin_username'];
                if (!empty($storeName)) {
                    return Html::create()->content([
                        Tag::create($storeName)->color('blue')
                    ]);
                }
                return Html::create()->content([
                    Tag::create(admin_trans('admin.unassigned'))->color('default')
                ]);
            })->ellipsis(true)->align('center');
            $grid->column('present_in_amount', admin_trans('channel_agent.present_in'))->width('100px')->align('center');
            $grid->column('present_out_amount', admin_trans('channel_agent.present_out'))->width('100px')->align('center');
            $grid->column('machine_put_point', admin_trans('channel_agent.machine_put'))->width('100px')->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => '',
                };
            })->ellipsis(true)->align('center');
            $grid->column('status_transfer',
                admin_trans('player.fields.status_transfer'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => '',
                };
            })->ellipsis(true)->align('center');
            $grid->column('status_offline_open', admin_trans('player.fields.status_offline_open'))->display(function (
                $value
            ) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => '',
                };
            })->ellipsis(true)->align('center');
            $grid->column('status_baccarat', admin_trans('player.fields.status_baccarat'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => '',
                };
            })->ellipsis(true)->align('center');
            $grid->column('created_at', admin_trans('player.fields.created_at'))->ellipsis(true)->align('center');
            $grid->filter(function (Filter $filter) use ($admin) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.name'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                // 店家筛选
                if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                    // 代理：显示下级店家筛选
                    $filter->eq()->select('store_admin_id')
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('admin.store'))
                        ->remoteOptions(admin_url([$this, 'getStoreOptions']));
                }
            });
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $actions->prepend(Button::create(admin_trans('offline_channel.electronic_game_disabled'))
                    ->drawer([$this, 'playerGameList'], ['player_id' => $data['id']])
                    ->type('primary'));
                $actions->prepend(Button::create(admin_trans('channel_agent.open_score'))
                    ->modal($this->presentNoPassword(['id' => $data['id']]))->width('600px'));
            });

            $grid->hideDelete();
            $grid->expandFilter();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
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

        return Grid::create(new Game(), function (Grid $grid) use ($player_id, $channelGamePlatformIds, $lang, $player) {
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

            $grid->column('sort', '排序')->align('center')->width('80px');

            $grid->hideDelete();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });

            $grid->pagination()->pageSize(50);
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
     * @return \ExAdmin\ui\response\Msg
     */
    public function savePlayerGames($selected, $player_id, $size, $page, array $ex_admin_filter = [])
    {
        if (!isset($selected)) {
            return message_error('请选择要授权的游戏');
        }

        /** @var Player $player */
        $player = Player::query()->with('channel')->find($player_id);

        if (empty($player)) {
            return message_error('玩家不存在');
        }

        // 只有线下渠道才支持游戏级别权限管理
        if ($player->channel->is_offline != 1) {
            return message_error('该功能仅适用于线下渠道');
        }

        // 获取渠道允许的游戏平台
        $channelGamePlatformIds = json_decode($player->channel->game_platform, true);
        if (empty($channelGamePlatformIds)) {
            return message_error('该渠道未开启任何电子游戏平台');
        }

        // 验证选择的游戏
        $selectedGames = Game::query()->whereIn('id', $selected)->get();
        if ($selectedGames->isEmpty()) {
            return message_error('未找到选择的游戏');
        }

        // 验证游戏是否都在渠道允许的范围内
        foreach ($selectedGames as $game) {
            if (!in_array($game->platform_id, $channelGamePlatformIds)) {
                return message_error('选择的游戏不在渠道允许的范围内');
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
            return message_success("成功设置了 {$count} 个游戏权限")->refresh();
        } catch (Exception $e) {
            Db::rollBack();
            Log::error('save_player_games', [$e->getMessage(), $e->getTrace()]);
            return message_error($e->getMessage() ?? '保存失败');
        }
    }

    /**
     * 玩家电子游戏管理（仅线下渠道）- 旧版本，保留用于兼容
     * @auth true
     * @param $id
     * @return Form
     */
    public function managePlayerElectronicGame($id): Form
    {
        /** @var Player $player */
        $player = Player::query()->with('channel')->find($id);

        if (empty($player)) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=1 color="#ff4d4f">玩家不存在</font>'));
            });
        }

        // 只有线下渠道才支持游戏级别权限管理
        if ($player->channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=1 color="#ff4d4f">该功能仅适用于线下渠道</font>'));
            });
        }

        return Form::create([], function (Form $form) use ($player) {
            $form->hidden('player_id')->default($player->id);

            // 获取玩家所在渠道开启的游戏平台
            if (empty($player->channel->game_platform)) {
                $form->push(Html::markdown('><font size=1 color="#ff4d4f">该渠道未开启任何电子游戏平台</font>'));
                return;
            }

            $channelGamePlatformIds = json_decode($player->channel->game_platform, true);
            if (empty($channelGamePlatformIds)) {
                $form->push(Html::markdown('><font size=1 color="#ff4d4f">该渠道未开启任何电子游戏平台</font>'));
                return;
            }

            // 获取渠道开启的游戏平台列表
            $gamePlatformList = GamePlatform::query()
                ->where('status', 1)
                ->whereIn('id', $channelGamePlatformIds)
                ->orderBy('sort', 'desc')
                ->get();

            if ($gamePlatformList->isEmpty()) {
                $form->push(Html::markdown('><font size=1 color="#ff4d4f">该渠道未开启任何有效的电子游戏平台</font>'));
                return;
            }

            // 获取当前语言环境
            $lang = Container::getInstance()->translator->getLocale();

            // 获取玩家已开启的游戏
            $playerDisabledGameIds = PlayerDisabledGame::query()
                ->where('player_id', $player->id)
                ->where('status', 1)
                ->pluck('game_id')
                ->toArray();

            $form->push(Html::markdown('><font size=1 color="#1890ff">' .
                "提示: 选择该玩家可以使用的电子游戏。未选择的游戏将不会在客户端展示。" .
                '</font>'));

            // 按平台分组展示游戏
            foreach ($gamePlatformList as $platform) {
                // 获取该平台下的所有游戏
                $games = Game::query()
                    ->where('platform_id', $platform->id)
                    ->where('status', 1)
                    ->orderBy('sort', 'desc')
                    ->with(['gameContent' => function ($query) use ($lang) {
                        $query->where('lang', $lang);
                    }])
                    ->get();

                if ($games->isEmpty()) {
                    continue;
                }

                // 构建该平台的游戏选项列表
                $gameOptions = [];
                // 添加全选选项（使用特殊标识）
                $gameOptions["select_all_{$platform->id}"] = "【全选该平台所有游戏】";

                foreach ($games as $game) {
                    $gameName = $game->gameContent->first()?->name ?? "游戏 ID: {$game->id}";
                    $gameOptions[$game->id] = $gameName;
                }

                // 获取该平台下玩家已选择的游戏
                $selectedGamesInPlatform = array_values(array_intersect(
                    array_keys($gameOptions),
                    $playerDisabledGameIds
                ));

                // 检查是否该平台所有游戏都已选中，如果是则自动勾选"全选"
                $allGameIds = $games->pluck('id')->toArray();
                $allSelected = !empty($allGameIds) &&
                    count(array_intersect($allGameIds, $playerDisabledGameIds)) === count($allGameIds);

                if ($allSelected) {
                    $selectedGamesInPlatform[] = "select_all_{$platform->id}";
                }

                $form->checkbox("games_{$platform->id}", $platform->name . ' - 游戏列表')
                    ->options($gameOptions)
                    ->value(array_values($selectedGamesInPlatform));
            }

            $form->saving(function (Form $form) use ($player, $channelGamePlatformIds, $gamePlatformList) {
                $playerId = $form->input('player_id');

                // 收集所有选择的游戏
                $allSelectedGames = [];
                foreach ($gamePlatformList as $platform) {
                    $selectedGames = $form->input("games_{$platform->id}");
                    if (is_array($selectedGames)) {
                        // 检查是否选中了"全选"选项
                        $selectAllKey = "select_all_{$platform->id}";
                        if (in_array($selectAllKey, $selectedGames)) {
                            // 如果选中了全选，获取该平台所有游戏
                            $allGamesInPlatform = Game::query()
                                ->where('platform_id', $platform->id)
                                ->where('status', 1)
                                ->pluck('id')
                                ->toArray();

                            // 添加该平台所有游戏到选择列表
                            foreach ($allGamesInPlatform as $gameId) {
                                $allSelectedGames[] = [
                                    'game_id' => $gameId,
                                    'platform_id' => $platform->id
                                ];
                            }
                        } else {
                            // 没有全选，只添加手动选择的游戏
                            foreach ($selectedGames as $gameId) {
                                // 过滤掉全选标识
                                if ($gameId !== $selectAllKey && is_numeric($gameId)) {
                                    $allSelectedGames[] = [
                                        'game_id' => $gameId,
                                        'platform_id' => $platform->id
                                    ];
                                }
                            }
                        }
                    }
                }

                // 验证选择的游戏是否都在允许的平台范围内
                foreach ($allSelectedGames as $gameInfo) {
                    if (!in_array($gameInfo['platform_id'], $channelGamePlatformIds)) {
                        return message_error('选择的游戏平台不在渠道允许的范围内');
                    }
                }

                Db::beginTransaction();
                try {
                    // 获取所有需要处理的游戏
                    $allGames = Game::query()
                        ->where('status', 1)
                        ->whereIn('platform_id', $channelGamePlatformIds)
                        ->get();

                    foreach ($allGames as $game) {
                        /** @var PlayerDisabledGame $playerDisabledGame */
                        $playerDisabledGame = PlayerDisabledGame::query()
                            ->where('player_id', $playerId)
                            ->where('game_id', $game->id)
                            ->first();

                        // 检查该游戏是否在选择列表中
                        $shouldBeEnabled = false;
                        foreach ($allSelectedGames as $gameInfo) {
                            if ($gameInfo['game_id'] == $game->id) {
                                $shouldBeEnabled = true;
                                break;
                            }
                        }

                        if ($playerDisabledGame) {
                            // 更新现有记录的状态
                            $playerDisabledGame->status = $shouldBeEnabled ? 1 : 0;
                            $playerDisabledGame->save();
                        } elseif ($shouldBeEnabled) {
                            // 如果选中但没有记录，创建新记录
                            $newPlayerDisabledGame = new PlayerDisabledGame();
                            $newPlayerDisabledGame->player_id = $playerId;
                            $newPlayerDisabledGame->game_id = $game->id;
                            $newPlayerDisabledGame->platform_id = $game->platform_id;
                            $newPlayerDisabledGame->status = 1;
                            $newPlayerDisabledGame->save();
                        }
                    }

                    Db::commit();

                    $enabledCount = count($allSelectedGames);
                    return message_success("成功设置了 {$enabledCount} 个电子游戏");
                } catch (Exception $e) {
                    Db::rollBack();
                    Log::error('manage_player_electronic_game', [$e->getMessage(), $e->getTrace()]);
                    return message_error($e->getMessage() ?? '操作失败');
                }
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
                    return message_error('转换后的游戏点数无效');
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

    /**
     * 获取推广员选项（用于筛选器）
     * 注意：这里获取的是玩家推广员（Player 表 is_promoter=1），不是店家/代理
     * @return mixed
     */
    public function getAgentOptions(): mixed
    {
        $request = Request::input();

        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        // 查询下级推广员（Player 表中 is_promoter=1 的记录）
        $query = Player::query()
            ->where('is_promoter', 1)
            ->orderBy('created_at', 'desc');

        // 根据账号类型过滤
        if ($admin->type === \addons\webman\model\AdminUser::TYPE_STORE) {
            // 店家：查询本店家下的推广员
            $query->where('store_admin_id', $admin->id);
        } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
            // 代理：查询所有下级店家的推广员
            $storeIds = $admin->childStores()
                ->where('type', \addons\webman\model\AdminUser::TYPE_STORE)
                ->pluck('id')
                ->toArray();
            if (empty($storeIds)) {
                // 代理没有下级店家，返回空列表
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('store_admin_id', $storeIds);
            }
        } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
            // 渠道：查询同一部门下的所有推广员
            $query->where('department_id', $admin->department_id);
        }

        if (!empty($request['search'])) {
            $query->where('name', 'like', '%' . $request['search'] . '%');
        }

        $promoterList = $query->get();
        $data = [];
        /** @var Player $promoter */
        foreach ($promoterList as $promoter) {
            $data[] = [
                'value' => $promoter->id,
                'label' => $promoter->name,
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取代理选项（用于店家列表筛选）
     * 注意：这里获取的是代理账号（AdminUser TYPE_AGENT），不是 Player
     * @return Response
     */
    public function getAgentOptionsForFilter(): Response
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        // 只有渠道账号可以调用此方法筛选代理
        if ($admin->type !== \addons\webman\model\AdminUser::TYPE_CHANNEL) {
            return Response::success([]);
        }

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
     * 账变记录
     * @group channel
     * @auth true
     */
    public function deliveryRecord(): Grid
    {
        $lang = Container::getInstance()->translator->getLocale();
        return Grid::create(new $this->playerDeliveryRecord(), function (Grid $grid) use ($lang) {
            $grid->title(admin_trans('player_delivery_record.title'));
            $grid->model()->with(['player', 'player.storeAdmin', 'machine'])->orderBy('created_at', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $exAdminFilter = Request::input('ex_admin_filter', []);

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();

            // 根据账号类型过滤账变记录
            if ($admin->type === \addons\webman\model\AdminUser::TYPE_STORE) {
                // 店家：查询本店家下的玩家账变记录
                $grid->model()->whereHas('player', function ($query) use ($admin) {
                    $query->where('store_admin_id', $admin->id);
                });
            } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                // 代理：查询所有下级店家的玩家账变记录
                $storeIds = $admin->childStores()
                    ->where('type', \addons\webman\model\AdminUser::TYPE_STORE)
                    ->pluck('id')
                    ->toArray();
                if (empty($storeIds)) {
                    // 代理没有下级店家，返回空结果
                    $grid->model()->whereRaw('1 = 0');
                } else {
                    $grid->model()->whereHas('player', function ($query) use ($storeIds) {
                        $query->whereIn('store_admin_id', $storeIds);
                    });
                }
            } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
                // 渠道：查询同一部门下的所有玩家账变记录
                $grid->model()->whereHas('player', function ($query) use ($admin) {
                    $query->where('department_id', $admin->department_id);
                });
            }
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', $exAdminFilter['player']['uuid']);
                });
            }
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', $exAdminFilter['player']['phone']);
                });
            }
            // 店家筛选
            if (!empty($exAdminFilter['player']['store_admin_id'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('store_admin_id', $exAdminFilter['player']['store_admin_id']);
                });
            }
            if (isset($exAdminFilter['search_type']) && $exAdminFilter['search_type'] != 0) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->when($exAdminFilter['search_type'] == 1, function ($query) use ($exAdminFilter) {
                        $query->where('is_promoter', 1);
                    })->when($exAdminFilter['search_type'] == 0, function ($query) use ($exAdminFilter) {
                        $query->where('is_promoter', 0);
                    });
                });
            }
            if (!empty($exAdminFilter['search_source'])) {
                $searchSource = $exAdminFilter['search_source'];
                $grid->model()->where(function ($query) use ($searchSource) {
                    $query->where([
                        ['code', 'like', '%' . $searchSource . '%', 'or'],
                        ['machine_name', 'like', '%' . $searchSource . '%', 'or']
                    ])->orWhere(function ($query) use ($searchSource) {
                        $query->where([
                            ['source', 'like', '%' . $searchSource . '%', 'and'],
                        ])->whereIn('type',
                            [PlayerDeliveryRecord::TYPE_PRESENT_IN, PlayerDeliveryRecord::TYPE_PRESENT_OUT]);
                    })->orWhere(function ($query) use ($searchSource) {
                        $query->whereHas('gamePlatform', function ($query) use ($searchSource) {
                            $query->where([
                                ['name', 'like', '%' . $searchSource . '%', 'or'],
                            ]);
                        })->whereIn('type',
                            [
                                PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT,
                                PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN
                            ]);
                    });
                });
            }
            if (!empty($exAdminFilter['activity'])) {
                $target_id = PlayerMoneyEditLog::query()
                    ->leftJoin('activity_content', 'activity_content.id', 'player_money_edit_log.activity')
                    ->where('activity_id', $exAdminFilter['activity'])
                    ->pluck('player_money_edit_log.id')->toArray();
                $grid->model()->where('target', 'player_money_edit_log')->whereIn('target_id', $target_id);
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(if(`type`=' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ',`amount`,0)) as total_in_amount, sum(if(`type`= ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ',`amount`,0)) as total_out_amount, sum(if(`type`= ' . PlayerDeliveryRecord::TYPE_MACHINE . ',`amount`,0)) as total_machine_amount')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_in_amount']) ? floatval($totalData['total_in_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_in_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_out_amount']) ? floatval($totalData['total_out_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_out_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(!empty($totalData['total_machine_amount']) ? floatval($totalData['total_machine_amount']) : 0)->prefix(admin_trans('player_delivery_record.total_machine_amount'))->valueStyle([
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
                        Row::create()->column(Statistic::create()->value(bcsub(bcadd(($totalData['total_machine_amount'] ?? 0),
                            ($totalData['total_in_amount'] ?? 0), 2), ($totalData['total_out_amount'] ?? 0),
                            2))->prefix(admin_trans('channel_agent.total'))->valueStyle([
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
            $grid->bordered();
            $grid->autoHeight();
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('player.phone', admin_trans('channel_agent.account'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->name)
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('player.phone')
            );
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : '',
                    $data->player->is_promoter == 1 ? Tag::create(admin_trans('channel_agent.player_type_store'))->color('green') : Tag::create(admin_trans('channel_agent.player_type_device'))->color('blue'),
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.storeAdmin.nickname', admin_trans('admin.store'))->display(function ($val, PlayerDeliveryRecord $data) {
                if (!empty($data->player->storeAdmin)) {
                    return Html::create()->content([
                        Tag::create($data->player->storeAdmin->nickname)->color('blue')
                    ]);
                }
                return Html::create()->content([
                    Tag::create(admin_trans('admin.unassigned'))->color('default')
                ]);
            })->align('center');
            $grid->column('source', admin_trans('player_delivery_record.fields.source'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) use ($lang) {
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                    case PlayerDeliveryRecord::TYPE_RECHARGE:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                    case PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK:
                    case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                    case PlayerDeliveryRecord::TYPE_SPECIAL:
                    case PlayerDeliveryRecord::TYPE_MACHINE:
                        return Tag::create(trans($val, [], 'message', $lang))->color('red');
                    case PlayerDeliveryRecord::TYPE_PRESENT_IN:
                    case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                        return Tag::create($val)->color('green');
                    case PlayerDeliveryRecord::TYPE_BET:
                    case PlayerDeliveryRecord::TYPE_CANCEL_BET:
                    case PlayerDeliveryRecord::TYPE_SETTLEMENT:
                    case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                        return Tag::create($val)->color('blue');
                    case PlayerDeliveryRecord::TYPE_GIFT:
                        return Tag::create($val)->color('pink');
                    case PlayerDeliveryRecord::TYPE_PREPAY:
                    case PlayerDeliveryRecord::TYPE_REFUND:
                        return Tag::create($val)->color('cyan');
                    case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                    case PlayerDeliveryRecord::TYPE_MACHINE_DOWN:
                        if ($data->machine) {
                            return Tag::create($data->machine->code)->color('orange')->style(['cursor' => 'pointer'])->modal([
                                $this,
                                'machineInfo'
                            ],
                                ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                        }
                        break;
                    case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS:
                        return Tag::create(trans($val, [], 'message', $lang))->color('blue');
                    case PlayerDeliveryRecord::TYPE_PROFIT:
                    case PlayerDeliveryRecord::TYPE_REVERSE_WATER:
                        return Tag::create(trans($val, [], 'message', $lang))->color('purple');
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN:
                        /** @var PlayerWalletTransfer $playerWalletTransfer */
                        $playerWalletTransfer = PlayerWalletTransfer::query()->where('id', $data->target_id)->first();
                        return Tag::create($playerWalletTransfer->gamePlatform->name)->color('purple');
                    case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                    case PlayerDeliveryRecord::TYPE_RECHARGE_REWARD:
                    case PlayerDeliveryRecord::TYPE_DAMAGE_REBATE:
                    case PlayerDeliveryRecord::TYPE_LOTTERY:
                        return Tag::create(trans($val, [], 'message', $lang))->color('orange');
                    case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                        /** @var PlayerWalletTransfer $playerWalletTransfer */
                        $playerWalletTransfer = PlayerWalletTransfer::query()->where('id', $data->target_id)->first();
                        return Tag::create($playerWalletTransfer->gamePlatform->name)->color('orange');
                    case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                    case PlayerDeliveryRecord::TYPE_AGENT_IN:
                        return Tag::create($data->player->channel->name)->color('orange');
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
                        case PlayerDeliveryRecord::TYPE_PRESENT_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_IN))->color('#8D3514');
                            break;
                        case PlayerDeliveryRecord::TYPE_PRESENT_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_OUT))->color('#f50');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE_UP:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_UP))->color('#9FCC84');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE_DOWN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_DOWN))->color('#668A50');
                            break;
                        case PlayerDeliveryRecord::TYPE_RECHARGE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE))->color('#3C87C9');
                            break;
                        case PlayerDeliveryRecord::TYPE_WITHDRAWAL:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL))->color('#C98341');
                            break;
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_PROFIT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PROFIT))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_LOTTERY:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_LOTTERY))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT))->color('#CC6600');
                            break;
                        case PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_NATIONAL_INVITE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_RECHARGE_REWARD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_DAMAGE_REBATE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_REVERSE_WATER:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REVERSE_WATER))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_SPECIAL:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SPECIAL))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_MACHINE:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_OUT))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_AGENT_IN:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_IN))->color('#e8c521');
                            break;
                        case PlayerDeliveryRecord::TYPE_BET:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_BET))->color('#1890ff');
                            break;
                        case PlayerDeliveryRecord::TYPE_CANCEL_BET:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_CANCEL_BET))->color('#52c41a');
                            break;
                        case PlayerDeliveryRecord::TYPE_GIFT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GIFT))->color('#eb2f96');
                            break;
                        case PlayerDeliveryRecord::TYPE_SETTLEMENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SETTLEMENT))->color('#13c2c2');
                            break;
                        case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RE_SETTLEMENT))->color('#722ed1');
                            break;
                        case PlayerDeliveryRecord::TYPE_PREPAY:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PREPAY))->color('#fa8c16');
                            break;
                        case PlayerDeliveryRecord::TYPE_REFUND:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REFUND))->color('#a0d911');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center')->sortable();
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Html::create()->content([
                    Html::div()->content($value),
                ]);
            })->width('150px')->align('center');
            $grid->column('activity', admin_trans('activity.title'))->display(function (
                $activity,
                PlayerDeliveryRecord $data
            ) {
                if ($data->target == 'player_money_edit_log') {
                    $activityContent = PlayerMoneyEditLog::query()
                        ->leftJoin('activity_content', 'activity_content.id', '=', 'player_money_edit_log.activity')
                        ->select('activity_content.name', 'activity_content.activity_id')
                        ->where('player_money_edit_log.id', '=', $data->target_id)
                        ->first();
                    return Html::create()->content([
                        Html::div()->content($activity),
                        Html::create($activityContent->name)->style([
                            'cursor' => 'pointer',
                            'color' => 'rgb(24, 144, 255)'
                        ])->drawer(['addons-webman-controller-ActivityController', 'details'],
                            ['id' => $activityContent->activity_id]),
                    ]);
                }
            });
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
                    case PlayerDeliveryRecord::TYPE_AGENT_OUT:
                    case PlayerDeliveryRecord::TYPE_BET:
                    case PlayerDeliveryRecord::TYPE_GIFT:
                    case PlayerDeliveryRecord::TYPE_PREPAY:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                    case PlayerDeliveryRecord::TYPE_RE_SETTLEMENT:
                        // 重新结算根据金额正负显示
                        if ($val < 0) {
                            return Html::create()->content([$val])->style(['color' => '#cd201f']);
                        }
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
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
                $name = admin_trans('channel_agent.player');
                if (in_array($data->type, [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                ])) {
                    $name = $data->user_name ?? admin_trans('channel_agent.admin');
                }
                if ($data->type == PlayerDeliveryRecord::TYPE_MACHINE_DOWN && !empty($data->user_id)) {
                    $name = $data->user_name ?? admin_trans('channel_agent.admin');
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
            $grid->filter(function (Filter $filter) use ($admin) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('search_source')->placeholder(admin_trans('player_delivery_record.fields.source'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel_agent.type'))
                    ->options([
                        0 => admin_trans('channel_agent.all'),
                        1 => admin_trans('channel_agent.player_type_store'),
                        2 => admin_trans('channel_agent.player_type_device'),
                    ]);

                // 店家筛选
                if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                    // 代理：显示下级店家筛选
                    $filter->eq()->select('player.store_admin_id')
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('admin.store'))
                        ->remoteOptions(admin_url([$this, 'getStoreOptions']));
                }

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_wallet_transfer.fields.platform_name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->in()->select('type')
                    ->placeholder(admin_trans('player_delivery_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->multiple()
                    ->options([
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD),
                        PlayerDeliveryRecord::TYPE_PRESENT_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_IN),
                        PlayerDeliveryRecord::TYPE_PRESENT_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PRESENT_OUT),
                        PlayerDeliveryRecord::TYPE_MACHINE_UP => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_UP),
                        PlayerDeliveryRecord::TYPE_MACHINE_DOWN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE_DOWN),
                        PlayerDeliveryRecord::TYPE_RECHARGE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE),
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL),
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT),
                        PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK),
                        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT),
                        PlayerDeliveryRecord::TYPE_PROFIT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PROFIT),
                        PlayerDeliveryRecord::TYPE_LOTTERY => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_LOTTERY),
                        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT),
                        PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN),
                        PlayerDeliveryRecord::TYPE_NATIONAL_INVITE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_NATIONAL_INVITE),
                        PlayerDeliveryRecord::TYPE_RECHARGE_REWARD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE_REWARD),
                        PlayerDeliveryRecord::TYPE_DAMAGE_REBATE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_DAMAGE_REBATE),
                        PlayerDeliveryRecord::TYPE_REVERSE_WATER => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REVERSE_WATER),
                        PlayerDeliveryRecord::COIN_ADD => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::COIN_ADD),
                        PlayerDeliveryRecord::COIN_DEDUCT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::COIN_DEDUCT),
                        PlayerDeliveryRecord::TYPE_SPECIAL => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SPECIAL),
                        PlayerDeliveryRecord::TYPE_MACHINE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MACHINE),
                        PlayerDeliveryRecord::TYPE_AGENT_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_OUT),
                        PlayerDeliveryRecord::TYPE_AGENT_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_AGENT_IN),
                        PlayerDeliveryRecord::TYPE_BET => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_BET),
                        PlayerDeliveryRecord::TYPE_CANCEL_BET => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_CANCEL_BET),
                        PlayerDeliveryRecord::TYPE_GIFT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GIFT),
                        PlayerDeliveryRecord::TYPE_SETTLEMENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_SETTLEMENT),
                        PlayerDeliveryRecord::TYPE_RE_SETTLEMENT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RE_SETTLEMENT),
                        PlayerDeliveryRecord::TYPE_PREPAY => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_PREPAY),
                        PlayerDeliveryRecord::TYPE_REFUND => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REFUND),
                    ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 交班报表
     * @group channel
     * @auth true
     */
    public function storeAgentShiftHandoverRecord(): Grid
    {
        return Grid::create(new $this->storeAgentShiftHandoverRecord(), function (Grid $grid) {
            $grid->title(admin_trans('channel_agent.shift_handover_record'));
            $grid->model()->where('bind_admin_user_id', Admin::user()->id)->orderBy('id', 'desc');
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->fixed(true)->align('center');
            $grid->column('total_in', admin_trans('channel_agent.present_in'))->sortable()->align('center');
            $grid->column('total_out', admin_trans('channel_agent.present_out'))->sortable()->align('center');
            $grid->column('machine_point', admin_trans('channel_agent.machine_put'))->sortable()->align('center');
            $grid->column('total_profit_amount', admin_trans('channel_agent.total_profit_amount'))->sortable()->align('center');
            $grid->column('start_time', admin_trans('channel_agent.start_time'))->ellipsis(true)->align('center');
            $grid->column('end_time', admin_trans('channel_agent.end_time'))->ellipsis(true)->align('center');
            $grid->column('user_name', admin_trans('channel_agent.admin'))->align('center');
            $grid->column('created_at', admin_trans('channel_agent.created_at'))->ellipsis(true)->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('user_name')->placeholder(admin_trans('channel_agent.admin'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->hideDelete();
            $grid->expandFilter();
        });
    }

    /**
     * 获取店家选项（用于筛选）
     * @auth true
     * @return Response
     */
    public function getStoreOptions(): Response
    {
        /** @var \addons\webman\model\AdminUser $admin */
        $admin = Admin::user();

        $query = \addons\webman\model\AdminUser::query()
            ->where('type', \addons\webman\model\AdminUser::TYPE_STORE)
            ->where('status', \addons\webman\model\AdminUser::STATUS_ENABLED);

        if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
            // 代理：只显示自己的下级店家
            $query->where('parent_admin_id', $admin->id);
        } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_CHANNEL) {
            // 渠道：显示所属渠道的所有店家
            $query->where('department_id', $admin->department_id);
        }

        $stores = $query->select(['id', 'username', 'nickname'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($store) {
                return [
                    'label' => $store->nickname ?: $store->username,
                    'value' => $store->id,
                ];
            });

        return Response::success($stores);
    }
}

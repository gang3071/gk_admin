<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\ChannelMachine;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\MachineCategory;
use addons\webman\model\MachineKeepingLog;
use addons\webman\model\MachineMedia;
use addons\webman\model\Player;
use addons\webman\service\WalletService;
use app\service\machine\MachineServices;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use think\Exception;
use Webman\Push\PushException;

/**
 * 机台
 * @group channel
 */
class ChannelMachineController
{
    protected $model;

    protected $media_model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_model');
        $this->media_model = plugin()->webman->config('database.machine_media_model');
    }

    /**
     * 机台列表
     * @group channel
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->steelBallList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 斯洛列表
     * @param int $machineId
     * @return Grid
     */
    public function slotList(int $machineId = 0): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($machineId) {
            $machineIds = ChannelMachine::query()->where('department_id',
                Admin::user()->department_id)->get()->pluck('machine_id');
            $grid->model()
                ->where('type', GameType::TYPE_SLOT)
                ->whereIn('id', $machineIds)
                ->orderBy('sort')
                ->orderBy('id', 'desc');
            $this->getList($grid, GameType::TYPE_SLOT);
        });
    }

    /**
     * 钢珠列表
     * @return Grid
     */
    public function steelBallList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $machineIds = ChannelMachine::query()->where('department_id',
                Admin::user()->department_id)->get()->pluck('machine_id');
            $grid->model()
                ->where('type', GameType::TYPE_STEEL_BALL)
                ->whereIn('id', $machineIds)
                ->orderBy('sort')
                ->orderBy('id', 'desc');
            $grid->title(admin_trans('machine.title'));
            $this->getList($grid, GameType::TYPE_STEEL_BALL);
        });
    }

    /**
     * 机台
     * @param Grid $grid
     * @param $type
     * @return void
     */
    protected function getList(Grid $grid, $type)
    {
        $grid->driver()->setPk('id');
        $grid->autoHeight();
        $grid->bordered(true);
        $grid->title(admin_trans('machine.title'));
        $requestFilter = Request::input('ex_admin_filter', []);
        if (!empty($requestFilter)) {
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
        }
        $grid->column('id', admin_trans('machine.fields.id'))->align('center');
        $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                Tag::create(getGameTypeName($data->type)),
                $data->machineCategory->name ?? '',
            ]);
        })->align('center');
        $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function (
            $val,
            Machine $data
        ) {
            return Html::create()->content([
                !empty($data->producer->name) ? Tag::create($data->producer->name)->color('green') : ''
            ]);
        })->align('center');
        $grid->column('control_type', admin_trans('machine.fields.control_type'))->display(function ($val) {
            return Html::create()->content([
                Tag::create(admin_trans('machine.control_type.' . $val))->color('orange')
            ]);
        })->align('center');
        $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                $data->name
            ]);
        })->align('center');
        $grid->column('code', admin_trans('machine.fields.code'))->sortable()->align('center')
            ->filter(
                FilterColumn::like()->text('code')
            );
        $grid->column('odds_x', admin_trans('machine.fields.odds_x'))->display(function ($val, Machine $data) {
            return Html::create()->content([
                $data->odds_x . ' / ' . $data->odds_y
            ]);
        })
            ->align('left');
        $grid->column('min_point', admin_trans('machine.fields.min_point'))->align('center');
        $grid->column('max_point', admin_trans('machine.fields.max_point'))->align('center');
        $grid->column('created_at', admin_trans('machine.fields.created_at'))->sortable()->align('center');
        $grid->filter(function (Filter $filter) use ($type) {
            $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
            $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
            $filter->like()->text('port')->placeholder(admin_trans('machine.fields.port'));
            $filter->eq()->select('control_type')
                ->placeholder(admin_trans('machine.fields.control_type'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    Machine::CONTROL_TYPE_MEI => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_MEI),
                    Machine::CONTROL_TYPE_SONG => admin_trans('machine.control_type.' . Machine::CONTROL_TYPE_SONG),
                ]);
            $filter->eq()->select('status')
                ->placeholder(admin_trans('machine.fields.status'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    1 => admin_trans('admin.normal'),
                    0 => admin_trans('admin.disable')
                ]);
            SelectGroup::create();
            $filter->in()->cascaderSingle('cate_id')
                ->showSearch()
                ->style(['width' => '150px'])
                ->placeholder(admin_trans('machine.fields.cate_id'))
                ->options(getCateListOptions())
                ->multiple();
            if ($type == GameType::TYPE_FISH) {
                $filter->eq()->select('seat')
                    ->showSearch()
                    ->style(['width' => '150px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine.fields.seat'))
                    ->options(getSeatOptions());
            }
            $producer = plugin()->webman->config('database.machine_producer_model');
            $options = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
            $filter->eq()->select('producer_id')
                ->placeholder(admin_trans('machine.fields.producer_id'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options($options);
            $filter->eq()->select('gaming')
                ->placeholder(admin_trans('machine.has_gaming'))
                ->showSearch()
                ->style(['width' => '150px'])
                ->dropdownMatchSelectWidth()
                ->options([
                    1 => admin_trans('machine.gaming'),
                    0 => admin_trans('machine.not_gaming')
                ]);
            $filter->form()->hidden('created_at_start');
            $filter->form()->hidden('created_at_end');
            $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                admin_trans('public_msg.created_at_start'),
                admin_trans('public_msg.created_at_end')
            ]);
        });
        $grid->actions(function (Actions $actions) {
            $actions->hideDel();
        });
        $grid->hideDelete();
        $grid->expandFilter();
    }

    /**
     * 机台资讯列表
     * @group channel
     * @auth true
     * @return Html
     */
    public function infoList(): Html
    {
        return admin_view(plugin()->webman->getPath() . '/views/info_list.vue')->attrs([
            'cate_options' => $this->getCateListOptions(),
            'department_id' => Admin::user()->department_id,
            'ws' => env('WS_URL', ''),
            'btn_text' => admin_trans('machine_media.btn_text'),
            'play_address' => admin_trans('machine_media.play_address'),
            'slot_action_list' => getChannelMachineAction(1, Machine::CONTROL_TYPE_MEI),
            'jackpot_action_list' => getChannelMachineAction(2, Machine::CONTROL_TYPE_MEI),
            'play_title' => admin_trans('machine_media.btn.media_play'),
            'lang' => Container::getInstance()->translator->getLocale(),
        ]);
    }

    /**
     * @param array $data
     * @return array
     */
    public function getCateListOptions(array $data = []): array
    {
        $optionList = [];
        $machineCategory = MachineCategory::query()->with(['gameType'])->where('status', 1)->whereNull('deleted_at');
        if (!empty($data)) {
            $machineCategory->whereHas('gameType', function ($query) use ($data) {
                $query->where('type', $data['type'])->where('status', 1)->whereNull('deleted_at');
            });
        }
        $cateList = $machineCategory->get();
        /** @var MachineCategory $item */
        foreach ($cateList as $item) {
            if ($item->gameType->type == GameType::TYPE_SLOT) {
                $optionList[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pid' => -GameType::TYPE_SLOT
                ];
            }
            if ($item->gameType->type == GameType::TYPE_STEEL_BALL) {
                $optionList[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'pid' => -GameType::TYPE_STEEL_BALL,
                ];
            }
        }
        if (empty($data) || ($data['type'] == GameType::TYPE_SLOT)) {
            $optionList[] = [
                'id' => -GameType::TYPE_SLOT,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                'pid' => 0
            ];
        }
        if (empty($data) || ($data['type'] == GameType::TYPE_STEEL_BALL)) {
            $optionList[] = [
                'id' => -GameType::TYPE_STEEL_BALL,
                'name' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                'pid' => 0
            ];
        }

        return Arr::tree($optionList);
    }

    /**
     * 鱼机机台资讯列表
     * @return Grid
     */
    public function fishInfoList(): Grid
    {
        $departmentId = Admin::user()->department_id;
        return Grid::create(new $this->model(), function (Grid $grid) use ($departmentId) {
            $requestFilter = Request::input('ex_admin_filter', []);
            $grid->model()->with(['machineCategory', 'gamingPlayer', 'gamingPlayer.machine_wallet'])->where('type',
                GameType::TYPE_FISH)
                ->where('gaming', '!=', 1)
                ->whereHas('gamingPlayer', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->orderBy('sort', 'asc');
            if (isset($requestFilter['last_point_at_start']) && !empty($requestFilter['last_point_at_start'])) {
                $grid->model()->where('last_point_at', '>=', $requestFilter['last_point_at_start']);
            }
            if (isset($requestFilter['last_point_at_end']) && !empty($requestFilter['last_point_at_end'])) {
                $grid->model()->where('last_point_at', '<=', $requestFilter['last_point_at_end']);
            }
            if (isset($requestFilter['last_game_at_start']) && !empty($requestFilter['last_game_at_start'])) {
                $grid->model()->where('last_game_at', '>=', $requestFilter['last_game_at_start']);
            }
            if (isset($requestFilter['last_game_at_end']) && !empty($requestFilter['last_game_at_end'])) {
                $grid->model()->where('last_game_at', '<=', $requestFilter['last_game_at_end']);
            }
            $grid->title(admin_trans('machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine.fields.id'))->align('center');
            $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type)),
                    $data->machineCategory->name ?? '',
                ]);
            })->align('center');
            $grid->column('name', admin_trans('machine.fields.name'))->align('center')->filter(
                FilterColumn::like()->text('name')
            );
            $grid->column('code', admin_trans('machine.fields.code'))->sortable()->align('center')->filter(
                FilterColumn::like()->text('name')
            );
            $grid->column('last_point_at', admin_trans('machine.fields.last_point_at'))->sortable()->align('center');
            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
                $filter->in()->cascaderSingle('cate_id')
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options($this->getCateListOptions())
                    ->multiple();
                $filter->form()->hidden('last_point_at_start');
                $filter->form()->hidden('last_point_at_end');
                $filter->form()->hidden('last_game_at_start');
                $filter->form()->hidden('last_game_at_end');
                $filter->form()->dateTimeRange('last_point_at_start', 'last_point_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_point_at'),
                    admin_trans('machine.fields.last_point_at')
                ]);
                $filter->form()->dateTimeRange('last_game_at_start', 'last_game_at_end', '')->placeholder([
                    admin_trans('machine.fields.last_game_at'),
                    admin_trans('machine.fields.last_game_at')
                ]);
            });
            $grid->expandFilter();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create(
                        [
                            admin_trans('machine.btn.action'),
                            Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                        ])
                )->trigger(['click']);

                $dropdown->item(admin_trans('machine.btn.kick_player'), 'StopFilled')
                    ->confirm(admin_trans('machine.btn.kick_player_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_player'])
                    ->gridRefresh();

                $dropdown->item(admin_trans('machine.btn.kick_force'), 'StopOutlined')
                    ->confirm(admin_trans('machine.btn.kick_force_confirm'), [$this, 'action'],
                        ['id' => $data->id, 'action' => 'kick_force'])
                    ->gridRefresh();

                $actions->prepend(
                    $dropdown
                );
            });
        });
    }

    /**
     * 机台资讯操作
     * @group channel
     * @auth true
     * @param $id
     * @param $action
     * @return Msg
     */
    public function action($id, $action): Msg
    {
        /** @var Machine $machine */
        $machine = $this->model::find($id);
        if (empty($machine)) {
            return message_error(admin_trans('machine.not_fount'));
        }
        /** @var Player $player */
        $player = Player::find($machine->gaming_user_id);
        try {
            switch ($action) {
                case 'kick_player': // 踢除遊戲中的玩家
                    if ($player) {
                        machineWash($player, $machine);
                    } else {
                        throw new Exception(admin_trans('machine.action.no_fount_player'));
                    }
                    break;
                case 'kick_force': // 強制踢出(分數將不會返回玩家)
                    if ($player) {
                        resetMachineTrans($machine, $player);
                    } else {
                        throw new Exception(admin_trans('machine.action.no_fount_player'));
                    }
                    break;
                default:
                    return message_error(admin_trans('machine.action.action_error'));
            }
        } catch (\Exception $e) {
            return message_error($e->getMessage());
        }

        return message_success(admin_trans('machine.action.action_success'));
    }

    /**
     * 播放视频流
     * @auth true
     * @group channel
     * @param $id
     * @return Html|Msg
     */
    public function mediaPlay($id)
    {
        /** @var MachineMedia $media */
        $media = $this->media_model::find($id);
        $mediaList = $this->media_model::where('machine_id', $media->machine_id)->where('status',
            1)->whereNull('deleted_at')->get();
        $srcList = [];
        $mediaKey = 1;
        /** @var MachineMedia $value */
        foreach ($mediaList as $value) {
            $srcList[] = [
                'src' => 'https://' . $value->pull_ip . '/' . $value->media_app . '/play.html?id=' . $value->stream_name,
                'title' => admin_trans('machine_media.media_title') . $mediaKey,
                'desc' => admin_trans('machine_media.fields.pull_ip') . ':' . $value->pull_ip . ' ' . admin_trans('machine_media.fields.stream_name') . ':' . $value->stream_name,
            ];
            $mediaKey++;
        }
        if (empty($media)) {
            return message_error(admin_trans('machine_media.not_fount'));
        }

        return admin_view(plugin()->webman->getPath() . '/views/media_play.vue')->attrs([
            'iframe_src' => 'https://' . $media->pull_ip . '/' . $media->media_app . '/play.html?id=' . $media->stream_name,
            'iframe_list' => $srcList,
            'btn_text' => admin_trans('machine_media.btn_text'),
            'play_address' => admin_trans('machine_media.play_address'),
            'action_list' => getChannelMachineAction($media->machine->type, $media->machine->control_type),
            'type' => $media->machine->type,
            'machine_id' => $media->machine_id,
        ]);
    }

    /**
     * 机台资讯列表
     * @return Response
     * @throws \Exception
     */
    public function getMachineList(): Response
    {
        $departmentId = Admin::user()->department_id;
        $name = Request::input('name', '');
        $code = Request::input('code', '');
        $quickSearch = Request::input('quickSearch', '');
        $sort = Request::input('sort', []);
        $cateId = Request::input('cate_id', []);
        $page = Request::input('page', 1);
        $pageSize = Request::input('pageSize', 10);
        $type = Request::input('type', GameType::TYPE_SLOT);
        $model = Machine::query()
            ->with([
                'machineCategory',
                'machine_media',
                'gamingPlayer' => function ($query) {
                    return $query->select(['id', 'name', 'phone', 'avatar']);
                },
                'gamingPlayer.machine_wallet'
            ])
            ->where('type', $type)
            ->where('gaming_user_id', '!=', 0)
            ->whereHas('gamingPlayer', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->whereHas('machineLabel', function ($query) use ($name) {
                $query->when(!empty($name), function ($query) use ($name) {
                    $query->where('name', 'like', '%' . $name . '%');
                });
            })
            ->when(!empty($cateId), function ($query) use ($cateId) {
                $query->whereIn('cate_id', $cateId);
            })
            ->when(!empty($code), function ($query) use ($code) {
                $query->where('code', 'like', '%' . $code . '%');
            })
            ->when(!empty($quickSearch), function ($query) use ($quickSearch) {
                $query->where(function ($query) use ($quickSearch) {
                    $query->orWhereHas('machineLabel', function ($query) use ($quickSearch) {
                        $query->where([
                            ['name', 'like', '%' . $quickSearch . '%', 'or'],
                        ]);
                    })->orWhereHas('gamingPlayer', function ($query) use ($quickSearch) {
                        $query->where([
                            ['name', 'like', '%' . $quickSearch . '%', 'or'],
                            ['phone', 'like', '%' . $quickSearch . '%', 'or'],
                            ['uuid', 'like', '%' . $quickSearch . '%', 'or'],
                        ]);
                    })->orWhere([
                        ['code', 'like', '%' . $quickSearch . '%', 'or'],
                    ]);
                });
            });
        $total = $model->count();
        $queryModel = clone $model;
        $data = $queryModel
            ->when(!empty($sort), function ($query) use ($sort) {
                foreach ($sort as $item) {
                    $query->orderBy($item[0], $item[1] == 'ascend' ? 'asc' : 'desc');
                }
            }, function ($query) use ($quickSearch) {
                $query->orderBy('last_game_at', 'desc');
            })->forPage($page, $pageSize)->get();
        $list = [];
        /** @var Machine $item */
        foreach ($data as $item) {
            $srcList = [];
            $mediaKey = 1;
            $iframeSrc = '';
            /** @var MachineMedia $value */
            foreach ($item->machine_media->where('status', 1)->where('stream_name', '!=', '-1') as $value) {
                if (empty($iframeSrc)) {
                    $iframeSrc = 'https://' . $value->pull_ip . '/' . $value->media_app . '/play.html?id=' . $value->stream_name;
                }
                $srcList[] = [
                    'src' => 'https://' . $value->pull_ip . '/' . $value->media_app . '/play.html?id=' . $value->stream_name,
                    'title' => admin_trans('machine_media.media_title') . $mediaKey,
                    'desc' => admin_trans('machine_media.fields.pull_ip') . ':' . $value->pull_ip . ' ' . admin_trans('machine_media.fields.stream_name') . ':' . $value->stream_name,
                ];
                $mediaKey++;
            }

            $services = MachineServices::createServices($item);
            $seconds = $services->keep_seconds;
            if ($seconds > 3600) {
                $hours = intval($seconds / 3600);
                $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
            } else {
                $time = gmstrftime('%H:%M:%S', $seconds);
            }
            // 赠点
            $givePoint = 0;
            $giveCache = getGivePoints($item->gaming_user_id, $item->id);
            if (!empty($giveCache)) {
                $givePoint = $giveCache['gift_point'] ?? 0;
            }
            $wash = floor((($services->point - $givePoint)) * ($item->odds_x ?? 1) / ($item->odds_y ?? 1));
            $lastPointAt = $services->last_point_at;
            $lastPlayTime = $services->last_play_time;
            $playStartTime = $services->play_start_time;
            $actionTime = $services->action_time;
            $machineData = [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'player_phone' => $item->gamingPlayer->phone ?? '',
                'player_name' => $item->gamingPlayer->name ?? '',
                'player_avatar' => $item->gamingPlayer->avatar ?? '',
                'cate_name' => $item->machineCategory->name,
                'gaming_user_id' => $item->gaming_user_id,
                'player_point' => $item->gaming_user_id ? WalletService::getBalance($item->gaming_user_id) : 0,
                'type' => $item->type,
                'keep_seconds' => $time,
                'last_game_at' => $item->last_game_at ?? '',
                'last_point_at' => !empty($lastPointAt) ? date('Y-m-d H:i:s', $lastPointAt) : '',
                'last_play_time' => !empty($lastPlayTime) ? date('Y-m-d H:i:s', $lastPlayTime) : '',
                'auto' => $services->auto,
                'reward_status' => $services->reward_status,
                'play_start_time' => !empty($playStartTime) ? date('Y-m-d H:i:s', $playStartTime) : '',
                'point' => $services->point,
                'score' => $services->score,
                'player_open_point' => $services->player_open_point,
                'player_wash_point' => $services->player_wash_point,
                'action_time' => !empty($actionTime) ? date('Y-m-d H:i:s', $actionTime) : '',
                'keeping' => $services->keeping,
                'wash' => $wash > 0 ? $wash : 0,
                'src_list' => $srcList,
                'iframe_src' => $iframeSrc,
            ];
            switch ($item->type) {
                case GameType::TYPE_SLOT:
                    $machineData['move_point'] = $services->move_point;
                    $machineData['bet'] = $services->bet;
                    $machineData['win'] = $services->win;
                    $machineData['bb'] = $services->bb;
                    $machineData['rb'] = $services->rb;
                    $machineData['player_pressure'] = $services->bet - $services->player_pressure;
                    $machineData['player_score'] = $services->win - $services->player_score;
                    break;
                case GameType::TYPE_STEEL_BALL:
                    $machineData['turn'] = $services->turn;
                    $machineData['win_number'] = $services->win_number;
                    $machineData['push_auto'] = $services->push_auto;
                    $machineData['player_win_number'] = $services->win_number - $services->player_win_number;
                    break;
            }
            $list[] = $machineData;
        }
        return Response::success([
            'data' => $list,
            'total' => $total
        ]);
    }

    /**
     * 机台资讯操作
     * @group channel
     * @auth true
     * @return Msg
     * @throws PushException
     */
    public function keepTimeChange(): Msg
    {
        $type = Request::input('type', '');
        $duration = Request::input('duration', 0);
        $actionType = Request::input('action_type', '');
        $id = Request::input('id', 0);
        /** @var Machine $machine */
        $machine = Machine::query()
            ->where('id', $id)
            ->whereHas('gamingPlayer', function ($query) {
                $query->where('department_id', Admin::user()->department_id);
            })
            ->first();
        if (empty($machine)) {
            return message_error(admin_trans('machine.has_un_gaming'));
        }
        $services = MachineServices::createServices($machine, Container::getInstance()->translator->getLocale());
        if ($machine->gaming == 0) {
            return message_error(admin_trans('machine.has_un_gaming'));
        }
        if (!empty($duration) && $duration > 0) {
            switch ($actionType) {
                case 2:
                    $duration = bcmul($duration, 60);
                    break;
                case 3:
                    $duration = bcmul(bcmul($duration, 60), 60);
                    break;
            }
            if ($type == 1) {
                $services->keep_seconds = bcadd($services->keep_seconds, $duration);
            } else {
                $services->keep_seconds = max(bcsub($services->keep_seconds, $duration), 0);
            }
        }
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

        $machineKeepingLog = new MachineKeepingLog();
        $machineKeepingLog->player_id = 0;
        $machineKeepingLog->machine_id = $machine->id;
        $machineKeepingLog->machine_name = $machine->name;
        $machineKeepingLog->keep_seconds = $services->keep_seconds;
        $machineKeepingLog->is_system = 2;
        $machineKeepingLog->user_id = Admin::id();
        $machineKeepingLog->department_id = Admin::user()->department_id;
        $machineKeepingLog->save();

        return message_success(admin_trans('machine.action.action_success'));
    }
}

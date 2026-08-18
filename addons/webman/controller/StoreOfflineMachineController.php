<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\ChannelMachine;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\service\WalletService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use support\Container;

/**
 * 店家后台 - 线下机台管理
 * @group store
 */
class StoreOfflineMachineController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_model');
    }

    /**
     * 线下机台列表
     * @auth true
     * @group store
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
     * 斯洛机台列表
     * @return Grid
     */
    private function slotList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $storeAdminId = Admin::user()->id;
            $departmentId = Admin::user()->department_id;

            // 查询绑定到当前店家的线下斯洛机台
            $grid->model()
                ->select([
                    'machine.*',
                    'machine_category.name as category_name'
                ])
                ->join('channel_machine', 'machine.id', '=', 'channel_machine.machine_id')
                ->leftJoin('machine_category', 'machine.cate_id', '=', 'machine_category.id')
                ->where('channel_machine.department_id', $departmentId)
                ->where('channel_machine.store_admin_id', $storeAdminId)
                ->where('machine.machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('machine.type', GameType::TYPE_SLOT)
                ->whereNull('machine.deleted_at')
                ->with(['gamingPlayer', 'machineLabel'])
                ->orderBy('machine.code', 'asc');

            $grid->title(admin_trans('store_offline_machine.slot_list'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->width(120)->align('center');
            $grid->column('label_id', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return $data->machineLabel->name ?? '-';
            })->width(150);
            $grid->column('category_name', admin_trans('machine.fields.cate_id'))->width(120)->align('center');

            // 游戏状态
            $grid->column('gaming', admin_trans('machine.fields.gaming'))
                ->display(function ($val, Machine $data) {
                    if ($data->gaming_user_id > 0) {
                        return Tag::create(admin_trans('machine.gaming'))->color('green');
                    }
                    return Tag::create(admin_trans('machine.not_gaming'))->color('default');
                })
                ->width(100)->align('center');

            // 游戏中设备（Player = Device）
            $grid->column('gaming_player', admin_trans('store_offline_machine.gaming_device'))
                ->display(function ($val, Machine $data) {
                    if ($data->gamingPlayer) {
                        return Html::create()->content([
                            Html::div()->content($data->gamingPlayer->name),
                            Html::small()->content('UUID: ' . $data->gamingPlayer->uuid)->style(['color' => '#999'])
                        ]);
                    }
                    return '-';
                })
                ->width(180);

            $grid->column('status', admin_trans('machine.fields.status'))
                ->display(function ($val) {
                    return $val == 1
                        ? Tag::create(admin_trans('admin.open'))->color('green')
                        : Tag::create(admin_trans('admin.close'))->color('red');
                })
                ->width(80)->align('center');

            $grid->column('created_at', admin_trans('machine.fields.created_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->eq()->select('machine.status')
                    ->placeholder(admin_trans('machine.fields.status'))
                    ->options([
                        1 => admin_trans('admin.open'),
                        0 => admin_trans('admin.close')
                    ]);
            });

            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();

            // 批量操作工具栏按钮 - 严格按照 ExAdmin 标准模式
            // 构建 URL 参数（空参数也需要显式传递）
            $param = [];
            $grid->tools(
                Button::create(admin_trans('store_offline_machine.actions.batch_qrcode'))
                    ->type('primary')
                    ->icon(Icon::create('fas fa-qrcode'))
                    ->confirm(admin_trans('store_offline_machine.confirm.batch_qrcode'),
                        [
                            $this,
                            'batchQrCode?' . http_build_query($param)  // 关键：必须有 ?
                        ])
                    ->gridBatch()
                    ->gridRefresh()
            );

            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 查看二维码
                $actions->append(
                    Button::create(admin_trans('store_offline_machine.actions.view_qrcode'))
                        ->type('primary')
                        ->size('small')
                        ->icon(Icon::create('fas fa-qrcode'))
                        ->modal([$this, 'viewQrCode'], ['machine_id' => $data['id']])
                        ->width('550px')
                );
            });
        });
    }

    /**
     * 钢珠机台列表
     * @return Grid
     */
    private function steelBallList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $storeAdminId = Admin::user()->id;
            $departmentId = Admin::user()->department_id;

            // 查询绑定到当前店家的线下钢珠机台
            $grid->model()
                ->select([
                    'machine.*',
                    'machine_category.name as category_name'
                ])
                ->join('channel_machine', 'machine.id', '=', 'channel_machine.machine_id')
                ->leftJoin('machine_category', 'machine.cate_id', '=', 'machine_category.id')
                ->where('channel_machine.department_id', $departmentId)
                ->where('channel_machine.store_admin_id', $storeAdminId)
                ->where('machine.machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('machine.type', GameType::TYPE_STEEL_BALL)
                ->whereNull('machine.deleted_at')
                ->with(['gamingPlayer', 'machineLabel'])
                ->orderBy('machine.code', 'asc');

            $grid->title(admin_trans('store_offline_machine.steel_ball_list'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->width(120)->align('center');
            $grid->column('label_id', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return $data->machineLabel->name ?? '-';
            })->width(150);
            $grid->column('category_name', admin_trans('machine.fields.cate_id'))->width(120)->align('center');

            // 游戏状态
            $grid->column('gaming', admin_trans('machine.fields.gaming'))
                ->display(function ($val, Machine $data) {
                    if ($data->gaming_user_id > 0) {
                        return Tag::create(admin_trans('machine.gaming'))->color('green');
                    }
                    return Tag::create(admin_trans('machine.not_gaming'))->color('default');
                })
                ->width(100)->align('center');

            // 游戏中设备
            $grid->column('gaming_player', admin_trans('store_offline_machine.gaming_device'))
                ->display(function ($val, Machine $data) {
                    if ($data->gamingPlayer) {
                        return Html::create()->content([
                            Html::div()->content($data->gamingPlayer->name),
                            Html::small()->content('UUID: ' . $data->gamingPlayer->uuid)->style(['color' => '#999'])
                        ]);
                    }
                    return '-';
                })
                ->width(180);

            $grid->column('status', admin_trans('machine.fields.status'))
                ->display(function ($val) {
                    return $val == 1
                        ? Tag::create(admin_trans('admin.open'))->color('green')
                        : Tag::create(admin_trans('admin.close'))->color('red');
                })
                ->width(80)->align('center');

            $grid->column('created_at', admin_trans('machine.fields.created_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->eq()->select('machine.status')
                    ->placeholder(admin_trans('machine.fields.status'))
                    ->options([
                        1 => admin_trans('admin.open'),
                        0 => admin_trans('admin.close')
                    ]);
            });

            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();

            // 批量操作工具栏按钮 - 严格按照 ExAdmin 标准模式
            // 构建 URL 参数（空参数也需要显式传递）
            $param = [];
            $grid->tools(
                Button::create(admin_trans('store_offline_machine.actions.batch_qrcode'))
                    ->type('primary')
                    ->icon(Icon::create('fas fa-qrcode'))
                    ->confirm(admin_trans('store_offline_machine.confirm.batch_qrcode'),
                        [
                            $this,
                            'batchQrCode?' . http_build_query($param)  // 关键：必须有 ?
                        ])
                    ->gridBatch()
                    ->gridRefresh()
            );

            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 查看二维码
                $actions->append(
                    Button::create(admin_trans('store_offline_machine.actions.view_qrcode'))
                        ->type('primary')
                        ->size('small')
                        ->icon(Icon::create('fas fa-qrcode'))
                        ->modal([$this, 'viewQrCode'], ['machine_id' => $data['id']])
                        ->width('550px')
                );
            });
        });
    }

    /**
     * 机台资讯（正在游戏中的机台）
     * @auth true
     * @group store
     */
    public function infoList(): Card
    {
        return Card::create(Tabs::create()
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_SLOT), $this->slotInfoList())
            ->pane(admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL), $this->steelBallInfoList())
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    /**
     * 斯洛机台资讯
     * @return Grid
     */
    private function slotInfoList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $storeAdminId = Admin::user()->id;
            $departmentId = Admin::user()->department_id;

            // 查询绑定到当前店家且正在游戏中的线下斯洛机台
            $grid->model()
                ->select([
                    'machine.*',
                    'machine_category.name as category_name'
                ])
                ->join('channel_machine', 'machine.id', '=', 'channel_machine.machine_id')
                ->leftJoin('machine_category', 'machine.cate_id', '=', 'machine_category.id')
                ->where('channel_machine.department_id', $departmentId)
                ->where('channel_machine.store_admin_id', $storeAdminId)
                ->where('machine.machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('machine.type', GameType::TYPE_SLOT)
                ->where('machine.gaming_user_id', '!=', 0)
                ->whereNull('machine.deleted_at')
                ->with(['gamingPlayer', 'gamingPlayer.machine_wallet', 'machineLabel'])
                ->orderBy('machine.code', 'asc');

            $grid->title(admin_trans('store_offline_machine.slot_info_list'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->width(120)->align('center');
            $grid->column('label_id', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return $data->machineLabel->name ?? '-';
            })->width(150);
            $grid->column('category_name', admin_trans('machine.fields.cate_id'))->width(120)->align('center');

            // 游戏中设备信息
            $grid->column('device_info', admin_trans('store_offline_machine.device_info'))
                ->display(function ($val, Machine $data) {
                    if ($data->gamingPlayer) {
                        return Html::create()->content([
                            Html::div()->content($data->gamingPlayer->name)->style(['fontWeight' => 'bold']),
                            Html::small()->content('UUID: ' . $data->gamingPlayer->uuid)->style(['color' => '#999'])
                        ]);
                    }
                    return '-';
                })
                ->width(180);

            // 设备余额
            $grid->column('balance', admin_trans('store_offline_machine.device_balance'))
                ->display(function ($val, Machine $data) {
                    if ($data->gaming_user_id) {
                        $balance = WalletService::getBalance($data->gaming_user_id);
                        return Html::create()->content([
                            Html::strong($balance)->style(['color' => '#1890ff'])
                        ]);
                    }
                    return '-';
                })
                ->width(120)->align('center');

            // 保留时间
            $grid->column('keep_seconds', admin_trans('machine.fields.keep_seconds'))
                ->display(function ($val, Machine $data) {
                    // 从机台服务获取实时保留时间
                    $services = $this->getMachineStatusViaApi($data);
                    $seconds = $services->keep_seconds ?? 0;
                    if ($seconds > 3600) {
                        $hours = intval($seconds / 3600);
                        $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                    } else {
                        $time = gmstrftime('%H:%M:%S', $seconds);
                    }
                    return Html::create()->content($time);
                })
                ->width(120)->align('center');

            // 保留状态
            $grid->column('keeping', admin_trans('machine.fields.keeping'))
                ->display(function ($val, Machine $data) {
                    $services = $this->getMachineStatusViaApi($data);
                    $keeping = $services->keeping ?? 0;
                    return $keeping == 1
                        ? Tag::create(admin_trans('machine.keeping'))->color('red')
                        : Tag::create(admin_trans('machine.un_keeping'))->color('default');
                })
                ->width(100)->align('center');

            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
            });

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });
        });
    }

    /**
     * 钢珠机台资讯
     * @return Grid
     */
    private function steelBallInfoList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $storeAdminId = Admin::user()->id;
            $departmentId = Admin::user()->department_id;

            // 查询绑定到当前店家且正在游戏中的线下钢珠机台
            $grid->model()
                ->select([
                    'machine.*',
                    'machine_category.name as category_name'
                ])
                ->join('channel_machine', 'machine.id', '=', 'channel_machine.machine_id')
                ->leftJoin('machine_category', 'machine.cate_id', '=', 'machine_category.id')
                ->where('channel_machine.department_id', $departmentId)
                ->where('channel_machine.store_admin_id', $storeAdminId)
                ->where('machine.machine_source', Machine::MACHINE_SOURCE_OFFLINE)
                ->where('machine.type', GameType::TYPE_STEEL_BALL)
                ->where('machine.gaming_user_id', '!=', 0)
                ->whereNull('machine.deleted_at')
                ->with(['gamingPlayer', 'gamingPlayer.machine_wallet', 'machineLabel'])
                ->orderBy('machine.code', 'asc');

            $grid->title(admin_trans('store_offline_machine.steel_ball_info_list'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('code', admin_trans('machine.fields.code'))->width(120)->align('center');
            $grid->column('label_id', admin_trans('machine.fields.name'))->display(function ($val, Machine $data) {
                return $data->machineLabel->name ?? '-';
            })->width(150);
            $grid->column('category_name', admin_trans('machine.fields.cate_id'))->width(120)->align('center');

            // 游戏中设备信息
            $grid->column('device_info', admin_trans('store_offline_machine.device_info'))
                ->display(function ($val, Machine $data) {
                    if ($data->gamingPlayer) {
                        return Html::create()->content([
                            Html::div()->content($data->gamingPlayer->name)->style(['fontWeight' => 'bold']),
                            Html::small()->content('UUID: ' . $data->gamingPlayer->uuid)->style(['color' => '#999'])
                        ]);
                    }
                    return '-';
                })
                ->width(180);

            // 设备余额
            $grid->column('balance', admin_trans('store_offline_machine.device_balance'))
                ->display(function ($val, Machine $data) {
                    if ($data->gaming_user_id) {
                        $balance = WalletService::getBalance($data->gaming_user_id);
                        return Html::create()->content([
                            Html::strong($balance)->style(['color' => '#1890ff'])
                        ]);
                    }
                    return '-';
                })
                ->width(120)->align('center');

            // 保留时间
            $grid->column('keep_seconds', admin_trans('machine.fields.keep_seconds'))
                ->display(function ($val) {
                    $seconds = $val;
                    if ($seconds > 3600) {
                        $hours = intval($seconds / 3600);
                        $time = $hours . ":" . gmstrftime('%M:%S', $seconds);
                    } else {
                        $time = gmstrftime('%H:%M:%S', $seconds);
                    }
                    return Html::create()->content($time);
                })
                ->width(120)->align('center');

            $grid->column('last_game_at', admin_trans('machine.fields.last_game_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
            });

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });
        });
    }

    /**
     * 从 gk_work API 获取机台实时状态
     * 注意：线下机台可能没有联网，这里尝试获取，如果失败则返回空对象
     *
     * @param Machine $machine
     * @return object
     */
    private function getMachineStatusViaApi(Machine $machine): object
    {
        try {
            $apiService = new \app\service\MachineApiService();
            return $apiService->getMachineStatus($machine->id);
        } catch (\Exception $e) {
            // 线下机台可能未联网，返回默认值
            return (object)[
                'keep_seconds' => 0,
                'keeping' => 0,
                'last_point_at' => 0,
                'last_play_time' => 0,
            ];
        }
    }

    /**
     * 查看机台二维码
     * @auth true
     * @group store
     * @return mixed
     */
    public function viewQrCode()
    {
        $machineId = Request::input('machine_id');

        // 验证机台归属
        $storeAdminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        $machine = Machine::query()
            ->with(['machineLabel'])
            ->whereHas('channelMachines', function ($query) use ($storeAdminId, $departmentId) {
                $query->where('store_admin_id', $storeAdminId)
                    ->where('department_id', $departmentId);
            })
            ->where('id', $machineId)
            ->where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)
            ->first();

        if (!$machine) {
            return message_error(admin_trans('store_offline_machine.error.machine_not_found'));
        }

        // 返回 Vue 组件展示二维码
        return admin_view(plugin()->webman->getPath() . '/views/machine_qrcode.vue')->attrs([
            'machineId' => $machine->id,
            'machineCode' => $machine->code,
            'machineName' => $machine->machineLabel->name ?? '-',
            'title' => admin_trans('store_offline_machine.qrcode_title'),
        ]);
    }

    /**
     * 批量生成机台二维码（通过 URL 访问）
     * @auth true
     * @group store
     * @return mixed
     */
    public function batchQrCodeView()
    {
        $ids = Request::input('ids', '');
        $machineIds = $ids ? explode(',', $ids) : [];
        $machineIds = array_map('intval', array_filter($machineIds));

        if (empty($machineIds)) {
            return message_error(admin_trans('store_offline_machine.error.no_machines_selected'));
        }

        // 验证机台归属
        $storeAdminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        $machines = Machine::query()
            ->with(['machineLabel'])
            ->whereHas('channelMachines', function ($query) use ($storeAdminId, $departmentId) {
                $query->where('store_admin_id', $storeAdminId)
                    ->where('department_id', $departmentId);
            })
            ->whereIn('id', $machineIds)
            ->where('machine_source', Machine::MACHINE_SOURCE_OFFLINE)
            ->orderBy('code', 'asc')
            ->get();

        if ($machines->isEmpty()) {
            return message_error(admin_trans('store_offline_machine.error.machine_not_found'));
        }

        // 准备机台数据
        $machineData = $machines->map(function (Machine $machine) {
            return [
                'id' => (int)$machine->id,
                'code' => (string)($machine->code ?? $machine->id),
                'name' => (string)($machine->machineLabel->name ?? '-'),
            ];
        })->toArray();

        // 返回 Vue 组件展示批量二维码
        return admin_view(plugin()->webman->getPath() . '/views/batch_machine_qrcode.vue')->attrs([
            'machines' => $machineData,
            'title' => admin_trans('store_offline_machine.batch_qrcode_title'),
        ]);
    }

    /**
     * 批量生成机台二维码（gridBatch 处理）
     * @auth true
     * @group store
     * @param array|null $selected 选中的机台 IDs（由 gridBatch 自动传递）
     * @return Msg
     */
    public function batchQrCode($selected = null)
    {
        // gridBatch 会将选中的 IDs 作为参数传递
        $machineIds = $selected ?? [];

        if (empty($machineIds)) {
            return message_error(admin_trans('store_offline_machine.error.no_machines_selected'));
        }

        // 限制最多一次生成30个二维码
        if (count($machineIds) > 30) {
            return message_error(admin_trans('store_offline_machine.error.too_many_machines'));
        }

        // 构建 URL
        $url = admin_url([$this, 'batchQrCodeView'], ['ids' => implode(',', $machineIds)]);

        // 返回成功消息并打开新窗口
        return message_success(admin_trans('common.success'))
            ->script("window.open('{$url}', '_blank', 'width=1200,height=800,scrollbars=yes')");
    }
}

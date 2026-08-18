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
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 查看二维码
                $actions->append(
                    Button::create(admin_trans('store_offline_machine.actions.view_qrcode'))
                        ->type('primary')
                        ->size('small')
                        ->icon(Icon::create('fas fa-qrcode'))
                        ->handler("window.open('/ex-admin/addons-webman-controller-StoreOfflineMachineController/viewQrCodePage?machine_id={$data['id']}', '_blank', 'width=700,height=900,toolbar=no,menubar=no,scrollbars=yes,resizable=yes')")
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
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 查看二维码
                $actions->append(
                    Button::create(admin_trans('store_offline_machine.actions.view_qrcode'))
                        ->type('primary')
                        ->size('small')
                        ->icon(Icon::create('fas fa-qrcode'))
                        ->handler("window.open('/ex-admin/addons-webman-controller-StoreOfflineMachineController/viewQrCodePage?machine_id={$data['id']}', '_blank', 'width=700,height=900,toolbar=no,menubar=no,scrollbars=yes,resizable=yes')")
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
     * 查看机台二维码（新窗口完整页面）
     * @auth true
     * @group store
     * @return \support\Response
     */
    public function viewQrCodePage()
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
            return response('<h3 style="text-align:center;margin-top:50px;color:#f5222d;">机台不存在或无权限访问</h3>');
        }

        $machineCode = htmlspecialchars($machine->code);
        $machineName = htmlspecialchars($machine->machineLabel->name ?? '-');
        $machineId = $machine->id;
        $title = htmlspecialchars(admin_trans('store_offline_machine.qrcode_title'));

        // 返回完整的 HTML 页面
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - {$machineCode}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1890ff;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .info-card {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e8e8e8;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #1890ff;
            font-weight: 500;
        }
        .qrcode-wrapper {
            text-align: center;
            padding: 30px;
            background: #fafafa;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        #qrcodeCanvas {
            border: 2px solid #d9d9d9;
            background: white;
            border-radius: 4px;
        }
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        .btn-primary {
            background: #1890ff;
            color: white;
        }
        .btn-primary:hover {
            background: #40a9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(24,144,255,0.4);
        }
        .btn-default {
            background: #fff;
            color: #333;
            border: 1px solid #d9d9d9;
        }
        .btn-default:hover {
            color: #1890ff;
            border-color: #1890ff;
            transform: translateY(-2px);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 20px;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$title}</h1>
        </div>

        <div class="info-card">
            <div class="info-item">
                <span class="info-label">机台编号：</span>
                <span class="info-value">{$machineCode}</span>
            </div>
            <div class="info-item">
                <span class="info-label">机台名称：</span>
                <span class="info-value">{$machineName}</span>
            </div>
            <div class="info-item">
                <span class="info-label">机台ID：</span>
                <span class="info-value">{$machineId}</span>
            </div>
        </div>

        <div class="qrcode-wrapper">
            <canvas id="qrcodeCanvas" width="300" height="300"></canvas>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="downloadQrCode()">
                📥 下载二维码
            </button>
            <button class="btn btn-default" onclick="window.print()">
                🖨️ 打印二维码
            </button>
        </div>
    </div>

    <script>
        const machineId = '{$machineId}';

        // 简化版二维码生成（与 Vue 组件相同的算法）
        function generateQRCode(text) {
            const QRCode = {
                typeNumber: 4,
                errorCorrectLevel: 'H',

                make: function(text) {
                    const typeNumber = this.getTypeNumber(text);
                    const moduleCount = typeNumber * 4 + 17;
                    const modules = new Array(moduleCount);

                    for (let row = 0; row < moduleCount; row++) {
                        modules[row] = new Array(moduleCount);
                        for (let col = 0; col < moduleCount; col++) {
                            modules[row][col] = null;
                        }
                    }

                    this.setupPositionProbePattern(modules, 0, 0);
                    this.setupPositionProbePattern(modules, moduleCount - 7, 0);
                    this.setupPositionProbePattern(modules, 0, moduleCount - 7);
                    this.setupTimingPattern(modules, moduleCount);

                    const data = this.encodeData(text);
                    this.mapData(modules, data, moduleCount, 2);

                    return modules;
                },

                getTypeNumber: function(text) {
                    const length = text.length;
                    if (length <= 14) return 1;
                    if (length <= 26) return 2;
                    if (length <= 42) return 3;
                    return 4;
                },

                setupPositionProbePattern: function(modules, row, col) {
                    for (let r = -1; r <= 7; r++) {
                        if (row + r <= -1 || modules.length <= row + r) continue;
                        for (let c = -1; c <= 7; c++) {
                            if (col + c <= -1 || modules.length <= col + c) continue;

                            if ((0 <= r && r <= 6 && (c == 0 || c == 6))
                                || (0 <= c && c <= 6 && (r == 0 || r == 6))
                                || (2 <= r && r <= 4 && 2 <= c && c <= 4)) {
                                modules[row + r][col + c] = true;
                            } else {
                                modules[row + r][col + c] = false;
                            }
                        }
                    }
                },

                setupTimingPattern: function(modules, moduleCount) {
                    for (let r = 8; r < moduleCount - 8; r++) {
                        if (modules[r][6] !== null) continue;
                        modules[r][6] = (r % 2 == 0);
                    }
                    for (let c = 8; c < moduleCount - 8; c++) {
                        if (modules[6][c] !== null) continue;
                        modules[6][c] = (c % 2 == 0);
                    }
                },

                encodeData: function(text) {
                    const bytes = [];
                    for (let i = 0; i < text.length; i++) {
                        bytes.push(text.charCodeAt(i));
                    }
                    return bytes;
                },

                mapData: function(modules, data, moduleCount) {
                    let inc = -1;
                    let row = moduleCount - 1;
                    let bitIndex = 7;
                    let byteIndex = 0;

                    for (let col = moduleCount - 1; col > 0; col -= 2) {
                        if (col == 6) col--;

                        while (true) {
                            for (let c = 0; c < 2; c++) {
                                if (modules[row][col - c] === null) {
                                    let dark = false;

                                    if (byteIndex < data.length) {
                                        dark = (((data[byteIndex] >>> bitIndex) & 1) == 1);
                                    }

                                    modules[row][col - c] = dark;
                                    bitIndex--;

                                    if (bitIndex == -1) {
                                        byteIndex++;
                                        bitIndex = 7;
                                    }
                                }
                            }

                            row += inc;

                            if (row < 0 || moduleCount <= row) {
                                row -= inc;
                                inc = -inc;
                                break;
                            }
                        }
                    }
                }
            };

            return QRCode.make(text);
        }

        // 绘制二维码
        function drawQRCode() {
            const canvas = document.getElementById('qrcodeCanvas');
            const ctx = canvas.getContext('2d');
            const canvasSize = 300;

            try {
                const qrMatrix = generateQRCode(machineId);
                const moduleCount = qrMatrix.length;
                const cellSize = Math.floor(canvasSize / moduleCount);
                const actualSize = cellSize * moduleCount;
                const offset = Math.floor((canvasSize - actualSize) / 2);

                ctx.clearRect(0, 0, canvasSize, canvasSize);
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvasSize, canvasSize);

                ctx.fillStyle = '#000000';
                for (let row = 0; row < moduleCount; row++) {
                    for (let col = 0; col < moduleCount; col++) {
                        if (qrMatrix[row][col]) {
                            ctx.fillRect(
                                offset + col * cellSize,
                                offset + row * cellSize,
                                cellSize,
                                cellSize
                            );
                        }
                    }
                }
            } catch (error) {
                console.error('QR code generation error:', error);
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvasSize, canvasSize);
                ctx.fillStyle = '#FF0000';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('二维码生成失败', canvasSize / 2, canvasSize / 2);
            }
        }

        // 下载二维码
        function downloadQrCode() {
            const canvas = document.getElementById('qrcodeCanvas');
            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = 'machine_{$machineCode}_qrcode.png';
                link.href = url;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 'image/png');
        }

        // 页面加载完成后绘制二维码
        window.onload = function() {
            drawQRCode();
        };
    </script>
</body>
</html>
HTML;

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}

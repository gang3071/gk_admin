<?php

namespace addons\webman\common;


use addons\webman\Admin;
use addons\webman\controller\AdminController;
use addons\webman\controller\ChannelPlayerActivityRecordController;
use addons\webman\controller\ChannelPlayerLotteryRecordController;
use addons\webman\controller\ChannelRechargeRecordController;
use addons\webman\controller\ChannelWithdrawRecordController;
use addons\webman\controller\MachineController;
use addons\webman\controller\PlayerActivityRecordController;
use addons\webman\controller\PlayerLotteryRecordController;
use addons\webman\exception\HttpResponseException;
use addons\webman\model\ActivityContent;
use addons\webman\model\AdminDepartment;
use addons\webman\model\Machine;
use addons\webman\model\Notice;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use app\service\machine\MachineServices;
use ExAdmin\ui\component\navigation\menu\MenuItem;
use ExAdmin\ui\contract\SystemAbstract;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Token;
use ExAdmin\ui\token\AuthException;
use Exception;
use GatewayWorker\Lib\Gateway;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class System extends SystemAbstract
{
    /**
     * 网站名称
     * @return string
     */
    public function name(): ?string
    {
        return admin_sysconf('web_name');
    }
    
    /**
     * 网站logo
     * @return string
     */
    public function logo(): ?string
    {
        return admin_sysconf('web_logo');
    }
    
    /**
     * 网站logo跳转地址
     * @return string
     */
    public function logoHref(): ?string
    {
        return plugin()->webman->config('route.prefix');
    }
    
    /**
     * 头部导航右侧
     * @return array
     */
    public function navbarRight(): array
    {
        $ws = env('WS_URL', '');
        return [
            admin_view(plugin()->webman->getPath() . '/views/socket.vue')->attrs([
                'id' => Admin::id(),
                'type' => Admin::user()->type == 1 ? 'admin' : 'channel',
                'department_id' => Admin::user()->department_id,
                'count' => 0,
                'lang' => Container::getInstance()->translator->getLocale(),
                'ws' => $ws,
                'title' => admin_trans('admin.system_messages'),
                'examine_withdraw' => Admin::check(ChannelWithdrawRecordController::class, 'reject',
                        '') || Admin::check(ChannelWithdrawRecordController::class, 'pass', ''),
                'examine_recharge' => Admin::check(ChannelRechargeRecordController::class, 'reject',
                        '') || Admin::check(ChannelRechargeRecordController::class, 'pass', ''),
                'examine_activity' => Admin::check(PlayerActivityRecordController::class, 'reject',
                        '') || Admin::check(PlayerActivityRecordController::class, 'pass',
                        '') || Admin::check(PlayerActivityRecordController::class, 'bathPass',
                        '') || Admin::check(PlayerActivityRecordController::class, 'bathReject',
                        '') || Admin::check(ChannelPlayerActivityRecordController::class, 'reject',
                        '') || Admin::check(ChannelPlayerActivityRecordController::class, 'pass',
                        '') || Admin::check(ChannelPlayerActivityRecordController::class, 'bathReject',
                        '') || Admin::check(ChannelPlayerActivityRecordController::class, 'bathPass', ''),
                'examine_lottery' => Admin::check(PlayerLotteryRecordController::class, 'reject',
                        '') || Admin::check(PlayerLotteryRecordController::class, 'pass',
                        '') || Admin::check(PlayerLotteryRecordController::class, 'bathPass',
                        '') || Admin::check(PlayerLotteryRecordController::class, 'bathReject',
                        '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'reject',
                        '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'pass',
                        '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'bathPass',
                        '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'bathReject', ''),
                'machine' => Admin::check(MachineController::class, 'form',
                        'post') || Admin::check(MachineController::class, 'form', 'put'),
            ])
        ];
    }
    
    /**
     * 头部点击用户信息下拉菜单
     * @return array
     */
    public function adminDropdown(): array
    {
        return [
            MenuItem::create()->content(admin_trans('admin.user_info'))
                ->modal([AdminController::class, 'editInfo'], ['id' => Admin::id()]),
            MenuItem::create()->content(admin_trans('admin.update_password'))
                ->modal([AdminController::class, 'updatePassword'], ['id' => Admin::id()]),
        ];
    }
    
    /**
     * 用户信息
     * @return array
     * @throws HttpResponseException
     */
    public function userInfo(): array
    {
        try {
            Token::auth();
        } catch (AuthException $exception) {
            throw new HttpResponseException(
                response(
                    json_encode(['message' => $exception->getMessage(), 'code' => $exception->getCode()]),
                    401,
                    ['Content-Type' => 'application/json'])
            );
        }
        return Admin::user()
            ->setVisible(['id', 'nickname', 'avatar'])
            ->toArray();
    }
    
    /**
     * 菜单
     * @return array
     */
    public function menu(): array
    {
        return Arr::tree(admin_menu()->all());
    }
    
    /**
     * 上传写入数据库
     * @param $data 上传入库数据
     * @return Response
     */
    public function upload($data): Response
    {
        $model = plugin()->webman->config('database.attachment_model');
        $model::firstOrCreate($data, [
            'uploader_id' => Admin::id(),
        ]);
        return Response::success();
    }
    
    
    /**
     * 验证权限
     * @param $class 类名
     * @param $function 方法
     * @param $method 请求method
     * @return bool
     */
    public function checkPermissions($class, $function, $method): bool
    {
        return Admin::check($class, $function, $method);
    }
    
    /**
     * 获取新的消息
     * @param $page
     * @param $size
     * @return Response
     */
    public function noticeList($page, $size): Response
    {
        $typeArr = [];
        if (Admin::check(ChannelWithdrawRecordController::class, 'reject',
                '') || Admin::check(ChannelWithdrawRecordController::class, 'pass', '')) {
            $typeArr[] = Notice::TYPE_EXAMINE_WITHDRAW;
        }
        if (Admin::check(ChannelRechargeRecordController::class, 'reject',
                '') || Admin::check(ChannelRechargeRecordController::class, 'pass', '')) {
            $typeArr[] = Notice::TYPE_EXAMINE_RECHARGE;
        }
        if (Admin::check(PlayerActivityRecordController::class, 'reject',
                '') || Admin::check(PlayerActivityRecordController::class, 'pass',
                '') || Admin::check(PlayerActivityRecordController::class, 'bathPass',
                '') || Admin::check(PlayerActivityRecordController::class, 'bathReject',
                '') || Admin::check(ChannelPlayerActivityRecordController::class, 'reject',
                '') || Admin::check(ChannelPlayerActivityRecordController::class, 'pass',
                '') || Admin::check(ChannelPlayerActivityRecordController::class, 'bathPass',
                '') || Admin::check(ChannelPlayerActivityRecordController::class, 'bathReject', '')) {
            $typeArr[] = Notice::TYPE_EXAMINE_ACTIVITY;
        }
        if (Admin::check(PlayerLotteryRecordController::class, 'reject',
                '') || Admin::check(PlayerLotteryRecordController::class, 'pass',
                '') || Admin::check(PlayerLotteryRecordController::class, 'bathPass',
                '') || Admin::check(PlayerLotteryRecordController::class, 'bathReject',
                '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'reject',
                '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'pass',
                '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'bathPass',
                '') || Admin::check(ChannelPlayerLotteryRecordController::class, 'bathReject', '')) {
            $typeArr[] = Notice::TYPE_EXAMINE_LOTTERY;
        }
        if (Admin::check(MachineController::class, 'form', 'post') || Admin::check(MachineController::class, 'form',
                'put')) {
            $typeArr[] = Notice::TYPE_MACHINE;
            $typeArr[] = Notice::TYPE_MACHINE_BET;
            $typeArr[] = Notice::TYPE_MACHINE_WIN;
            $typeArr[] = Notice::TYPE_MACHINE_WIN_NUMBER;
            $typeArr[] = Notice::TYPE_MACHINE_LOCK;
            $typeArr[] = Notice::TYPE_MACHINE_CRASH;
        }
        $list = [];
        if (Admin::user()->type == AdminDepartment::TYPE_DEPARTMENT && !empty($typeArr)) {
            $list = Notice::where('receiver', Notice::RECEIVER_ADMIN)->whereIN('type', $typeArr)
                ->latest()
                ->forPage($page, $size)
                ->get();
        }
        if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL && !empty($typeArr)) {
            $list = Notice::whereIN('type', $typeArr)
                ->latest()
                ->forPage($page, $size)
                ->get();
        }
        $data = [];
        /** @var Notice $item */
        foreach ($list as $item) {
            $title = admin_trans('notice.title.' . $item->type);
            $createTime = date('Y-m-d H:i:s', strtotime($item->created_at));
            switch ($item->type) {
                case Notice::TYPE_EXAMINE_RECHARGE:
                    /** @var PlayerRechargeRecord $playerRechargeRecord */
                    $playerRechargeRecord = PlayerRechargeRecord::find($item->source_id);
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{player_name}' => !empty($playerRechargeRecord->player_name) ? $playerRechargeRecord->player_name : $playerRechargeRecord->player_phone,
                        '{point}' => $playerRechargeRecord->point,
                        '{money}' => $playerRechargeRecord->money
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'status' => $playerRechargeRecord->status == PlayerRechargeRecord::STATUS_RECHARGING,
                        'url' => admin_url([ChannelRechargeRecordController::class, 'examineList'])
                    ];
                    break;
                case Notice::TYPE_EXAMINE_WITHDRAW:
                    /** @var PlayerWithdrawRecord $playerWithdrawRecord */
                    $playerWithdrawRecord = PlayerWithdrawRecord::find($item->source_id);
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{player_name}' => !empty($playerWithdrawRecord->player_name) ? $playerWithdrawRecord->player_name : $playerWithdrawRecord->player_phone,
                        '{point}' => $playerWithdrawRecord->point,
                        '{money}' => $playerWithdrawRecord->money
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'status' => $playerWithdrawRecord->status == PlayerWithdrawRecord::STATUS_WAIT,
                        'url' => admin_url([ChannelWithdrawRecordController::class, 'examineList'])
                    ];
                    break;
                case Notice::TYPE_EXAMINE_ACTIVITY:
                    /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                    $playerActivityPhaseRecord = PlayerActivityPhaseRecord::find($item->source_id);
                    /** @var ActivityContent $activityContent */
                    $activityContent = $playerActivityPhaseRecord->activity->activity_content()
                        ->where('lang', $playerActivityPhaseRecord->player->channel->lang)
                        ->first();
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{player_name}' => !empty($playerActivityPhaseRecord->player->name) ? $playerActivityPhaseRecord->player->name : $playerActivityPhaseRecord->player->phone,
                        '{machine_code}' => $playerActivityPhaseRecord->machine->code,
                        '{activity_content_name}' => $activityContent->name ?? '',
                        '{point}' => $playerActivityPhaseRecord->bonus
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'status' => $playerActivityPhaseRecord->status == PlayerActivityPhaseRecord::STATUS_RECEIVED,
                        'url' => admin_url([
                            Request()->header('App-Name') == 'agent' ? ChannelPlayerActivityRecordController::class : PlayerActivityRecordController::class,
                            'examine'
                        ])
                    ];
                    break;
                case Notice::TYPE_EXAMINE_LOTTERY:
                    /** @var PlayerLotteryRecord $playerLotteryRecord */
                    $playerLotteryRecord = PlayerLotteryRecord::find($item->source_id);
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{player_name}' => !empty($playerLotteryRecord->player_name) ? $playerLotteryRecord->player_name : (!empty($playerLotteryRecord->player_phone) ? $playerLotteryRecord->player_phone : ''),
                        '{machine_code}' => $playerLotteryRecord->machine_code,
                        '{lottery_name}' => $playerLotteryRecord->lottery_name,
                        '{point}' => $playerLotteryRecord->bonus
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'status' => $playerLotteryRecord->status == PlayerLotteryRecord::STATUS_UNREVIEWED,
                        'url' => admin_url([
                            Request()->header('App-Name') == 'agent' ? ChannelPlayerLotteryRecordController::class : PlayerLotteryRecordController::class,
                            'auditList'
                        ])
                    ];
                    break;
                case Notice::TYPE_MACHINE:
                case Notice::TYPE_MACHINE_BET:
                case Notice::TYPE_MACHINE_WIN:
                case Notice::TYPE_MACHINE_WIN_NUMBER:
                    /** @var Machine $machine */
                    $machine = Machine::find($item->source_id);
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{machine_code}' => $machine->code,
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'machine_status' => $machine->status,
                        'status' => Gateway::isUidOnline($machine->domain . ':' . $machine->port),
                        'url' => admin_url([MachineController::class, 'index'])
                    ];
                    break;
                case Notice::TYPE_MACHINE_LOCK:
                    /** @var Machine $machine */
                    $machine = Machine::find($item->source_id);
                    $services = MachineServices::createServices($machine);
                    $content = admin_trans('notice.content.' . $item->type, '', [
                        '{machine_code}' => $machine->code,
                    ]);
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'title' => $title,
                        'content' => $content,
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'machine_status' => $services->has_lock,
                        'url' => admin_url([MachineController::class, 'infoList'])
                    ];
                    break;
                case Notice::TYPE_MACHINE_CRASH:
                    // 爆机通知直接使用保存的 content，因为已经包含了所有必要信息
                    $data[] = [
                        'id' => $item->id,
                        'source_id' => $item->source_id,
                        'player_id' => $item->player_id,
                        'title' => $title,
                        'content' => $item->content, // 直接使用保存的完整内容
                        'type' => $item->type,
                        'created_at' => $createTime,
                        'status' => true, // 爆机通知始终显示为活跃状态
                        'url' => admin_url(['addons\webman\controller\ChannelPlayerController', 'index'])
                    ];
                    break;
            }
        }
        return Response::success($data);
    }
    
    /**
     * 执行机台操作
     * @param $cmd
     * @param $data
     * @param $machine_id
     * @return Response
     * @throws \Exception
     */
    public function doMachineCmd($cmd, $data, $machine_id): Response
    {
        /** @var Machine $machine */
        $machine = Machine::find($machine_id);
        if (empty($machine)) {
            return Response::success([], admin_trans('machine_action.machine_not_found'), 100);
        }
        if ($machine->status != 1) {
            return Response::success([], admin_trans('machine_action.machine_has_disabled'), 100);
        }
        if ($machine->deleted_at != null) {
            return Response::success([], admin_trans('machine_action.machine_has_delete'), 100);
        }
        
        try {
            $machineServices = MachineServices::createServices($machine,
                Container::getInstance()->translator->getLocale());
            if ($cmd == 'all') {
                sendSocketMessage('private-admin-1-' . Admin::id(), [
                    'msg_type' => 'machine_action_result',
                    'id' => $machine->id,
                    'description' => $machineServices->getDescription(),
                ]);
            } else {
                $data = $machineServices->sendCmd($cmd, $data ?? 0, 'admin', Admin::id());
            }
        } catch (Exception $e) {
            return Response::success([], $e->getMessage(), 100);
        }
        
        return Response::success($data);
    }

    /**
     * @param $file
     * @return \support\Response|BinaryFileResponse|\Webman\Http\Response
     */
    public function download($file): \support\Response|BinaryFileResponse|\Webman\Http\Response
    {
        $path = public_path() . $file;

        if (!file_exists($path)) {
            return response('文件不存在', 404);
        }

        return response()->download($path, basename($path));
    }
}

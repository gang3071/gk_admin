<?php

namespace addons\webman\controller;

use addons\webman\model\Channel;
use addons\webman\model\SystemSetting;
use DateTime;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;
use Webman\RedisQueue\Client;

/**
 * 系统配置
 */
class SystemSettingController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.system_setting_model');
    }
    
    /**
     * 配置列表
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        $tabs = Tabs::create()->destroyInactiveTabPane()
            ->type('card')
            ->pane(admin_trans('system_setting.master'), $this->settingList());
        $channelList = Channel::get();
        /** @var Channel $channel */
        foreach ($channelList as $channel) {
            $tabs->pane($channel->name, $this->settingList($channel->department_id));
        }
        return Card::create($tabs);
    }
    
    /**
     * 系统配置
     */
    public function settingList($id = 0): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('system_setting.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->model()->where('department_id', $id);
            $grid->column('feature', admin_trans('system_setting.fields.feature'))->display(function ($value) {
                return admin_trans('system_setting.fields.' . $value);
            })->align('center');
            
            $grid->column('setting', admin_trans('system_setting.fields.setting'))
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'marquee' || $data->feature === 'machine_marquee';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:500' => admin_trans('system_setting.marquee_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'register_present';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:10000' => admin_trans('validator.max', null, ['{max}' => 10000]),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])
                )->display(function ($value, SystemSetting $data) {
                    return $data->num;
                })->align('center')
                ->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'machine_maintain';
                })->display(function ($value, SystemSetting $data) {
                    $time = '';
                    !empty($data->num) && $time .= admin_trans('system_setting.week.' . $data->num);
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= '~' . $data->date_end;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editMachineMaintain'], ['data' => $data]);
                })->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'client_maintain';
                })->display(function ($value, SystemSetting $data) {
                    $time = '';
                    !empty($data->num) && $time .= admin_trans('system_setting.week.' . $data->num);
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= '~' . $data->date_end;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editClientMaintain'], ['data' => $data]);
                })->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'recharge_order_expiration';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:180' => admin_trans('validator.max', null, ['{max}' => 180]),
                            'min:15' => admin_trans('validator.min', null, ['{min}' => 15]),
                        ])->addonAfter(admin_trans('system_setting.minutes'))
                )->display(function ($val, SystemSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('system_setting.minutes');
                    }
                    return '';
                })->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'pending_minutes';
                })->editable(
                    (new Editable)->number('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:240' => admin_trans('validator.max', null, ['{max}' => 240]),
                            'min:2' => admin_trans('validator.min', null, ['{min}' => 2]),
                        ])->addonAfter(admin_trans('system_setting.minutes'))
                )->display(function ($val, SystemSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('system_setting.minutes');
                    }
                    return '';
                })->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'keeping_off';
                })->display(function ($value, SystemSetting $data) {
                    $time = '';
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= '~' . $data->date_end;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editKeepingOff'], ['data' => $data]);
                })->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'gift_keeping_minutes';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:240' => admin_trans('validator.max', null, ['{max}' => 240]),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])->addonAfter(admin_trans('system_setting.minutes'))
                )->display(function ($val, SystemSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('system_setting.minutes');
                    }
                    return '';
                })->if(function ($value, SystemSetting $data) { // 条件2
                    return $data->feature === 'max_keeping_minutes';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:6000' => admin_trans('validator.max', null, ['{max}' => 6000]),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])->addonAfter(admin_trans('system_setting.minutes'))
                )->display(function ($val, SystemSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('system_setting.minutes');
                    }
                    return '';
                })->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'settlement_date';
                })->editable(
                    (new Editable)->select('num')
                        ->options($this->getSettlementDate())
                )->display(function ($val, SystemSetting $data) {
                    $settlementDateText = admin_trans('player_promoter.settlement_date_text');
                    if (empty($data->num)) {
                        $settlementDateText .= '(' . admin_trans('player_promoter.settlement_date_text_null') . ')';
                    } else {
                        $settlementDateText .= '(' . $data->num . admin_trans('player_promoter.date') . ')';
                    }
                    return $settlementDateText;
                })->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'spectator_num';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:50' => admin_trans('validator.max', null, ['{max}' => 50]),
                            'min:1' => admin_trans('validator.min', null, ['{min}' => 1]),
                        ])->addonAfter(admin_trans('system_setting.fields.spectator_num'))
                )->display(function ($val, SystemSetting $data) {
                    return $data->num;
                })->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'client_version';
                })->editable(
                    (new Editable)->text('content', admin_trans('system_setting.client_version'))->rule([
                        'regex:/^\d+\.\d+\.\d+$/' => admin_trans('system_setting.client_version_reg')
                    ])->maxlength(50)->required()
                )->display(function ($val, SystemSetting $data) {
                    return $data->content;
                })->align('center')->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_redirect_uri';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_redirect_uri_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_key';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_key_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'line_secret';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:200' => admin_trans('system_setting.line_secret_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'commission';
                })->editable(
                    (new Editable)->text('num')
                        ->rule([
                            'integer' => admin_trans('validator.integer'),
                            'max:100' => admin_trans('validator.max', null, ['{max}' => 100]),
                            'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                        ])->addonAfter('%')
                )->display(function ($value, SystemSetting $data) {
                    return $data->num.'%';
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'jackpot_screen_domain';
                })->editable(
                    Editable::textarea('content')
                        ->showCount()
                        ->rows(3)
                        ->rule(['max:200' => admin_trans('system_setting.jackpot_screen_domain_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return Str::of($data->content)->limit(35, ' (...)');
                })->width('20%')->align('center')
                ->if(function ($value, SystemSetting $data) {
                    return $data->feature === 'turn_relay_ip';
                })->editable(
                    Editable::text('content')
                        ->showCount()
                        ->maxlength(100)
                        ->rule(['max:100' => admin_trans('system_setting.turn_relay_ip_max_len')])
                )->display(function ($value, SystemSetting $data) {
                    return $data->content;
                })->width('20%')->align('center');

            $grid->column('status', admin_trans('system_setting.fields.status'))->switch()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });

            // 状态修改后立即推送（针对客户端维护配置）
            $grid->editing(function ($id, $data) {
                // 检查是否修改了 status 字段
                if (isset($data['status'])) {
                    // 查询完整记录以获取 feature 字段
                    $config = SystemSetting::query()->find($id);
                    if ($config && $config->feature === 'client_maintain') {
                        $this->triggerClientMaintainNotify($id, 'status_switch');
                    }
                }
            });
        });
    }
    
    /**
     * 结算日期
     * @return array
     */
    public function getSettlementDate(): array
    {
        for ($i = 1; $i <= 28; $i++) {
            $data[$i] = $i . admin_trans('player_promoter.date');
        }
        
        return $data;
    }
    
    /**
     * 机台维护时间
     * @auth true
     * @param SystemSetting $data
     * @return Form
     */
    public function editMachineMaintain(SystemSetting $data): Form
    {
        /** @var SystemSetting $data */
        $data = $data->where('feature', 'machine_maintain')->first();
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('system_setting.title'));
            $form->select('num', admin_trans('system_setting.week_str'))
                ->value($data->num)
                ->options([
                    1 => admin_trans('system_setting.week.1'),
                    2 => admin_trans('system_setting.week.2'),
                    3 => admin_trans('system_setting.week.3'),
                    4 => admin_trans('system_setting.week.4'),
                    5 => admin_trans('system_setting.week.5'),
                    6 => admin_trans('system_setting.week.6'),
                    7 => admin_trans('system_setting.week.7'),
                ])->required();
            $form->timeRange('date_start', 'date_end', admin_trans('system_setting.time_range'))
                ->value([$data->date_start, $data->date_end])
                ->required();
            $form->saved(function (Form $form) {
                $num = $form->input('num');
                $dateStart = $form->input('date_start');
                $now = new DateTime();
                $weekday = (new DateTime())->modify(getWeekdayName($num));
                $nextWeekday = (new DateTime())->modify("next " . getWeekdayName($num));
                $startDateTime = new DateTime($weekday->format('Y-m-d') . ' ' . $dateStart);
                if (time() > $startDateTime->getTimestamp() - (3 * 60)) {
                    $startDateTime = new DateTime($nextWeekday->format('Y-m-d') . ' ' . $dateStart);
                }
                $secondsUntilStart = $now < $startDateTime ? $startDateTime->getTimestamp() - $now->getTimestamp() : 0;
                /** @var SystemSetting $systemSetting */
                $systemSetting = SystemSetting::query()->where('feature', 'machine_maintain')->first();
                Client::send('machine-maintain', [
                    'setting_time' => strtotime($systemSetting->updated_at),
                    'machine_maintain' => $startDateTime->format('Y-m-d H:i:s'),
                ], max($secondsUntilStart - 3 * 60, 0));
            });
        });
    }
    
    /**
     * 客户端维护时间
     * @auth true
     * @param SystemSetting $data
     * @return Form
     */
    public function editClientMaintain(SystemSetting $data): Form
    {
        /** @var SystemSetting $data */
        $data = $data->where('feature', 'client_maintain')->first();
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('system_setting.title'));
            $form->select('num', admin_trans('system_setting.week_str'))
                ->value($data->num)
                ->options([
                    1 => admin_trans('system_setting.week.1'),
                    2 => admin_trans('system_setting.week.2'),
                    3 => admin_trans('system_setting.week.3'),
                    4 => admin_trans('system_setting.week.4'),
                    5 => admin_trans('system_setting.week.5'),
                    6 => admin_trans('system_setting.week.6'),
                    7 => admin_trans('system_setting.week.7'),
                ])->required();
            $form->timeRange('date_start', 'date_end', admin_trans('system_setting.time_range'))
                ->value([$data->date_start, $data->date_end])
                ->required();

            // 保存后立即推送通知（时间、星期修改）
            $form->saved(function (Form $form) use ($data) {
                $this->triggerClientMaintainNotify($data->id, 'time_or_week_edit');
            });
        });
    }

    /**
     * 触发客户端维护通知推送
     */
    private function triggerClientMaintainNotify(int $configId, string $trigger = 'unknown'): void
    {
        try {
            $config = SystemSetting::query()->find($configId);
            if (!$config) {
                return;
            }

            $service = new \app\service\ClientMaintainService();

            // 清除推送缓存和状态缓存，强制立即推送和更新
            $departmentId = $config->department_id ?? 0;
            $configKey = $departmentId . ':' . $config->id;
            $redis = \support\Redis::connection();

            // 清除推送缓存
            $redis->del('client_maintain:notified:' . $configKey . ':start');
            $redis->del('client_maintain:notified:' . $configKey . ':end');

            // 清除状态缓存（确保跨项目同步）
            $redis->del('client_maintain:status:' . $departmentId);

            // 立即检查并推送（同时会更新状态缓存）
            $service->checkAndNotify();

            \support\Log::info('客户端维护配置修改，触发立即推送和缓存更新', [
                'trigger' => $trigger,
                'config_id' => $configId,
                'department_id' => $departmentId,
                'status' => $config->status,
                'week' => $config->num,
                'time_range' => $config->date_start . ' ~ ' . $config->date_end,
            ]);
        } catch (\Throwable $e) {
            \support\Log::error('触发客户端维护推送失败', [
                'error' => $e->getMessage(),
                'config_id' => $configId,
            ]);
        }
    }

    /**
     * 机台保留时间
     * @auth true
     * @param SystemSetting $data
     * @return Form
     */
    public function editKeepingOff(SystemSetting $data): Form
    {
        /** @var SystemSetting $data */
        $data = $data->where('feature', 'keeping_off')->first();
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('system_setting.title'));
            $form->timeRange('date_start', 'date_end', admin_trans('system_setting.time_range'))
                ->value([$data->date_start, $data->date_end])
                ->required();
        });
    }
}

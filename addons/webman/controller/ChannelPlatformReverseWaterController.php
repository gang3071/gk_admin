<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelPlatformReverseWater;
use addons\webman\model\GamePlatform;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerReverseWaterDetail;
use addons\webman\model\PlayGameRecord;
use Carbon\Carbon;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Notification;
use Illuminate\Support\Str;
use support\Db;
use support\Log;

/**
 * 渠道平台电子游戏反水设置
 * @group channel
 */
class ChannelPlatformReverseWaterController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_platform_reverse_water_model');
    }

    /**
     * 电子游戏反水设置列表
     * @group channel
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        $platform = GamePlatform::query()->get();
        $tabs = Tabs::create();
        foreach($platform as $item){
            $tabs->pane($item['name'],$this->list($item->id));
        }

        return Card::create($tabs
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    public function list($id)
    {
        return Grid::create((new (plugin()->webman->config('database.channel_platform_reverse_water_setting_model'))), function (Grid $grid) use($id) {
            /** @var ChannelPlatformReverseWater $water */
            if(!$water = ChannelPlatformReverseWater::query()->where('platform_id',$id)->where('department_id',Admin::user()->department_id)->first()){
                $water = ChannelPlatformReverseWater::query()->create([
                    'platform_id' => $id,
                    'department_id' => Admin::user()->department_id,
                    'checkout_time' => '01:00:00',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            $grid->tools(
                [
                    Switches::create(null, $water->status??1)
                    ->style(['marginLeft' => '20px','marginTop' => '5px'])
                        ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                    ->ajax([$this,'setStatus'],['id' => $water->id]),
                    Html::create()->content([
                        admin_trans('reverse_water.checkout_time')
                    ])->style(['marginLeft' => '20px','marginTop' => '5px','color'=>'red','font-weight'=>'bold'])
                ]
            );

            $grid->model()->where('water_id',$water->id)->orderBy('point');
            $grid->title(admin_trans('machine_category.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_category.fields.id'))->align('center');
            $grid->column('point', admin_trans('activity.point'))->sortable();
            $grid->column('ratio', admin_trans('activity.ratio'))->display(function($value){
                return $value.'%';
            })->sortable();
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->setForm()->modal([$this, 'form?water_id='.$water->id])->style(['width'=>'30%']);
        });
    }

    /**
     * 反水开关
     * @param $id
     * @return \ExAdmin\ui\response\Msg
     */
    private function setStatus($id)
    {
        /** @var ChannelPlatformReverseWater $water */
        $water = ChannelPlatformReverseWater::query()->where('id',$id)->first();
        $water->status = $water->status == 0 ?1:0;
        $water->save();
        return message_success(admin_trans('admin.success'));
    }

    public function test()
    {
        //获取所有开启电子游戏反水的渠道
        $departmentIds = Channel::query()->where('reverse_water_status', 1)->pluck('department_id');
        $startTime = Carbon::today()->startOfDay()->format('Y-m-d H:i:s');
        $endTime = Carbon::today()->endOfDay()->format('Y-m-d H:i:s');
        $setting = ChannelPlatformReverseWater::query()->whereIn('department_id', $departmentIds)->get()->toArray();
        if (empty($setting)) {
            return;
        }
        $now = Carbon::now()->format('H:i:s');
        $insertData = [];
        $playGameIds = [];
        $noticeData = [];
        $time = Carbon::now();
        $date = Carbon::now()->format('Y-m-d');
        foreach ($setting as $item) {
            //判断是否开启结算
            if ($item['status'] == 0) {
                continue;
            }
            //判断是否在结算时间内
            if ($item['checkout_time'] > $now) {
                continue;
            }
            //获取游玩用户
            $playerList = PlayGameRecord::query()
                ->whereHas('player', function ($query) use ($item) {
                    //用户打开反水开关
                    $query->where('department_id', $item['department_id'])->where('status_reverse_water', 1);
                })
                ->where('platform_id', $item['platform_id'])
                ->whereBetween('created_at', [$startTime, $endTime])
                ->where('is_reverse', 0)
                ->pluck('player_id', 'id')
                ->toArray();

            $playGameIds = array_merge($playGameIds, array_keys($playerList));
            $playerList = array_unique(array_values($playerList));

            if (empty($playerList)) {
                continue;
            }

            foreach ($playerList as $player) {
                //获取用户电子游戏游玩记录
                $totalData = PlayGameRecord::query()->selectRaw('sum(bet) as all_bet, sum(diff) as all_diff')
                    ->whereBetween('created_at', [$startTime, $endTime])
                    ->where('player_id', $player)
                    ->where('platform_id', $item['platform_id'])
                    ->where('is_reverse', 0)
                    ->first()
                    ->toArray();
                /** @var Player $playerInfo */
                $playerInfo = Player::query()->where('id', $player)->first();
                $level = $playerInfo->national_promoter()->first()?->level_list()->first();
                $levelRatio = $level->reverse_water ?? 0;

                $waterRatio = ChannelPlatformReverseWater::query()
                    ->where('platform_id', $item['platform_id'])
                    ->where('department_id', $playerInfo->department_id)
                    ->first()?->setting()
                    ->where('point', '<=', $totalData['all_bet'])
                    ->orderBy('point', 'desc')
                    ->value('ratio') ?? 0;

                $reverse_water = (float)bcmul($totalData['all_bet'], ($levelRatio + $waterRatio) / 100, 2);
                $insertData[] = [
                    'admin_id' => 0,
                    'player_id' => $player,
                    'platform_id' => $item['platform_id'],
                    'point' => $totalData['all_bet'],
                    'all_diff' => $totalData['all_diff'],
                    'date' => $date,
                    'reverse_water' => $reverse_water,
                    'level_ratio' => $levelRatio,
                    'created_at' => $time,
                    'platform_ratio' => $waterRatio,
                    'status' => 0
                ];

                if (!isset($noticeData[$player])) {
                    $noticeData[$player]['reverse_water'] = 0;
                    $noticeData[$player]['department_id'] = $playerInfo->department_id;
                    $noticeData[$player]['date'] = $playerInfo->department_id;
                }

                $noticeData[$player]['reverse_water'] += $reverse_water;

            }
        }

        //批量添加反水詳情
        PlayerReverseWaterDetail::query()->insert($insertData);
        $detailIds = PlayerReverseWaterDetail::query()->where('date', $date)->pluck('id', 'player_id')->toArray();
        foreach ($noticeData as $id => $item) {
            // 发送站内信
            $notice = new Notice();
            $notice->department_id = $item['department_id'];
            $notice->player_id = $id;
            $notice->source_id = $detailIds[$id];
            $notice->type = Notice::TYPE_REVERSE_WATER;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $notice->title = admin_trans('platform_reverse_water.notice.reverse_water_reward_title');
            $notice->content = str_replace(
                '{reverse_water}',
                $item['reverse_water'],
                admin_trans('platform_reverse_water.notice.reverse_water_reward_content_yesterday')
            );
            $notice->save();
        }
        PlayGameRecord::query()->whereIn('id', $playGameIds)->update(['is_reverse' => 1]);

        return message_success(admin_trans('lottery.action_success'));
    }

    /**
     * 添加/编辑反水配置
     * @auth
     * @group channel
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new (plugin()->webman->config('database.channel_platform_reverse_water_setting_model')), function (Form $form){
            $form->title(admin_trans('game_type.title'));
            $form->number('point', admin_trans('activity.point'))->min(0)->style(['width'=>'100%'])->required();
            $form->number('ratio', admin_trans('activity.ratio'))->min(0)->max(100)->precision(2)->step(0.01)->addonAfter('%')->style(['width'=>'100%'])->required();
            $form->hidden('water_id')->default(request()->get('water_id'));
            $form->hidden('admin_id')->default(Admin::id());
        });
    }

    /**
     * 反水详情
     * @auth true
     * @group channel
     * @return Grid
     */
    public function waterDetail(): Grid
    {
        return Grid::create(new (plugin()->webman->config('database.player_reverse_water_detail_model')), function (Grid $grid) {
            $grid->title(admin_trans('reverse_water.title'));
            $exAdminFilter = \ExAdmin\ui\support\Request::input('ex_admin_filter', []);
            $grid->model()->with(['player','platform'])->orderBy('switch')->orderBy('id', 'desc');
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                });
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }

            if (isset($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id',$exAdminFilter['platform_id']);
            }

            if (!empty($exAdminFilter['remark'])) {
                $grid->model()->where('remark','like','%'.$exAdminFilter['remark'].'%');
            }

            if (!empty($exAdminFilter['created_at_start']) && !empty($exAdminFilter['created_at_end'])) {
                $grid->model()->whereBetween('date', [$exAdminFilter['created_at_start'],$exAdminFilter['created_at_end']]);
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(point) as all_point, sum(all_diff) as all_diff,sum(reverse_water) as all_reverse_water')->first();
            $layout = Layout::create();


            $layout->row(function (Row $row) use ($totalData,) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_point']) ? floatval($totalData['all_point']) : 0)->prefix(admin_trans('reverse_water.fields.point'))->valueStyle([
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
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_diff']) ? floatval($totalData['all_diff']) : 0)->prefix(admin_trans('reverse_water.fields.all_diff'))->valueStyle([
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
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_reverse_water']) ? floatval($totalData['all_reverse_water']) : 0)->prefix(admin_trans('reverse_water.fields.reverse_water'))->valueStyle([
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
                    , 6);
            })->style(['background' => '#fff']);
            $grid->tools([
                $layout,
                Button::create(admin_trans('player_promoter.bath_settlement'))->danger()
                    ->confirm(admin_trans('reverse_water.profit_settlement_confirm'), [$this, 'profitSettlement'])
            ]);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('player_wallet_transfer.fields.id'))->align('center');
            $grid->column('date', admin_trans('reverse_water.fields.date'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->align('center');
            $grid->column('player.real_name', admin_trans('player.fields.real_name'))->align('center');
            $grid->column('platform.name', admin_trans('game_platform.fields.name'))->align('center');
            $grid->column('point', admin_trans('reverse_water.fields.point'))->align('center');
            $grid->column('all_diff', admin_trans('reverse_water.fields.all_diff'))->align('center');
            $grid->column('platform_ratio', admin_trans('reverse_water.fields.platform_ratio'))->align('center');
            $grid->column('level_ratio', admin_trans('reverse_water.fields.level_ratio'))->align('center');
            $grid->column('reverse_water', admin_trans('reverse_water.fields.reverse_water'))->align('center');
            $grid->column('real_reverse_water', admin_trans('reverse_water.fields.real_reverse_water'))->align('center');
            $grid->column('switch', admin_trans('reverse_water.fields.switch'))->switch()->align('center');
            $grid->column('created_at', admin_trans('reverse_water.fields.created_at'))->align('center');
            $grid->column('receive_time', admin_trans('reverse_water.fields.receive_time'))->align('center');
            $grid->column('admin_id', admin_trans('reverse_water.fields.admin_id'))->display(function($value){
                if($value == 0){
                    return admin_trans('menu.titles.system');
                }
                return $value;
            })->align('center');
            $grid->column('remark', admin_trans('player_withdraw_record.fields.remark'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->width('150px')->align('center')->editable(
                (new Editable)
                    ->textarea('remark')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:50' => admin_trans('player_withdraw_record.fields.remark')])
            )->width('150px')->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_withdraw_record.fields.remark'));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_wallet_transfer.fields.platform_name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
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
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }
    
    /**
     * 返水批量结算
     * @group channel
     * @auth true
     */
    public function profitSettlement(): Notification
    {
        $departmentId = Admin::user()->department_id;
        $list = PlayerReverseWaterDetail::query()
            ->select([
                'player_id',
                DB::raw('SUM(reverse_water) as reverse_water'),
                DB::raw('GROUP_CONCAT(id ORDER BY id) as record_ids'),
                DB::raw('max(id) as record_id'),
                DB::raw('min(date) as start_date'),
                DB::raw('max(date) as end_date')
            ])->whereHas('player', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })->where('is_settled', 0)
            ->where('switch',1)
            ->groupBy('player_id')
            ->get();
        if (empty($list->toArray())) {
            return notification_error(admin_trans('admin.error'), admin_trans('reverse_water.settlement_reward_null'));
        }
        $time = Carbon::now();
        $date = Carbon::today()->format('Y-m-d');
        $noticeInsert = [];
        $playReverseIds = [];
        Db::beginTransaction();
        try {
            foreach ($list as $item) {
                $noticeInsert[] = [
                    'department_id' => $departmentId,
                    'player_id' => $item->player_id,
                    'source_id' => $item->record_id ?? 0,
                    'type' => Notice::TYPE_REVERSE_WATER,
                    'receiver' => Notice::RECEIVER_PLAYER,
                    'is_private' => 1,
                    'title' => admin_trans('platform_reverse_water.notice.reverse_water_reward_title'),
                    'created_at' => $time,
                    'content' => str_replace(
                        ['{start_date}', '{end_date}', '{reverse_water}'],
                        [$item->start_date, $item->end_date, sprintf('%.2f', $item->reverse_water)],
                        admin_trans('platform_reverse_water.notice.reverse_water_reward_content_daterange')
                    )
                ];
                $recordIds = explode(',', $item->record_ids ?? '');
                $validIds = array_filter($recordIds, function($id) {
                    return is_numeric($id) && $id !== '';
                });
                $playReverseIds = array_merge($playReverseIds, $validIds);
            }
            //批量插入消息通知
            Notice::query()->insert($noticeInsert);
            PlayerReverseWaterDetail::query()
                ->whereIn('id', $playReverseIds)
                ->update(['is_settled' => 1, 'settled_date' => $date]);
            Db::commit();
        } catch (\Exception $e) {
            Log::error(admin_trans('platform_reverse_water.log.settlement_failed'), [$e->getMessage()]);
            Db::rollback();
        }
        return notification_success(admin_trans('admin.success'),
            admin_trans('promoter_profit_settlement_record.success'), ['duration' => 5])->refresh();
    }
}

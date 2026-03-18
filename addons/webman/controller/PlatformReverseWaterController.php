<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelPlatformReverseWater;
use addons\webman\model\ChannelPlatformReverseWaterSetting;
use addons\webman\model\GamePlatform;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use Illuminate\Support\Str;

/**
 * 渠道平台电子游戏反水设置
 */
class PlatformReverseWaterController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_platform_reverse_water_model');
    }

    /**
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        $channel = Channel::query()->where('status', 1)->get();
        $tabs = Tabs::create();
        foreach($channel as $item){
            $tabs->pane($item['name'],$this->list($item->department_id));
        }

        return Card::create($tabs
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    public function list($id)
    {
        $platform = GamePlatform::query()->get();
        $tabs = Tabs::create();
        foreach($platform as $item){
            $water_id = ChannelPlatformReverseWater::query()->where('platform_id',$item['id'])->where('department_id', $id)->value('id');
            if (!$water_id){
                continue;
            }
            $tabs->pane($item['name'],$this->detail($water_id));
        }
        return Card::create($tabs
            ->type('card')
            ->destroyInactiveTabPane()
        );
    }

    public function detail($water_id)
    {
        return Grid::create(new ChannelPlatformReverseWaterSetting(), function (Grid $grid) use($water_id) {
            $grid->model()->where('water_id',$water_id)->orderBy('point');
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
            $grid->setForm()->modal([$this, 'form?water_id='.$water_id])->style(['width'=>'30%']);
        });
    }
    
    /**
     * 反水奖励记录
     * @auth true
     * @return Grid
     */
    public function waterDetail(): Grid
    {
        return Grid::create(new (plugin()->webman->config('database.player_reverse_water_detail_model')), function (Grid $grid) {
            $grid->title(admin_trans('reverse_water.title'));
            $exAdminFilter = \ExAdmin\ui\support\Request::input('ex_admin_filter', []);
            $grid->model()->with(['player', 'platform', 'player.channel'])->orderBy('id', 'desc');
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

            if (isset($exAdminFilter['department_id'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('department_id', '=', $exAdminFilter['department_id']);
                });
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
            $grid->tools([$layout]);
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
            $grid->column('player.channel.name', admin_trans('player.fields.department_id'))->align('center');
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
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
     * 添加/编辑反水配置
     * @auth true
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
}

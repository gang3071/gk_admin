<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRole;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\ChannelGameWeb;
use addons\webman\model\ChannelMachine;
use addons\webman\model\ExternalApp;
use addons\webman\model\GamePlatform;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
use Carbon\Carbon;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Copy;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\Popover;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Request;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use support\Db;
use support\Log;
use function DI\get;

/**
 * 渠道管理
 */
class ChannelController
{
    protected $model;

    protected $gamePlatformModel;

    protected $machineModel;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_model');
        $this->machineModel = plugin()->webman->config('database.machine_model');
        $this->gamePlatformModel = plugin()->webman->config('database.game_platform_model');
    }

    /**
     * 渠道
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('channel.title'));
            $grid->model()->with(['department'])->orderBy('created_at', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('department_id', admin_trans('channel.fields.id'))->align('center')->fixed(true);
            $grid->column('name', admin_trans('channel.fields.name'))->align('center')->fixed(true);
            $grid->column('department.leader',
                admin_trans('channel.fields.leader'))->align('center')->copy()->fixed(true);
            $grid->column('department.phone', admin_trans('channel.fields.phone'))->align('center')->copy();
            $grid->column('domain', admin_trans('channel.fields.domain'))->align('center')->copy();
            $grid->column('type', admin_trans('channel.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('channel.type.' . $val)
                ]);
            })->align('center');
            $grid->column('client_version', admin_trans('channel.fields.client_version'))->align('center');
            $grid->column('player_num', admin_trans('channel.fields.player_num'))->display(function (
                $val,
                Channel $data
            ) {
                return $data->player->count();
            })->align('center');
            $grid->column('promotion_num', admin_trans('channel.fields.promotion_num'))->display(function (
                $val,
                Channel $data
            ) {
                return $data->player->where('is_promoter', 1)->count();
            })->align('center');
            $grid->column('coin_num', admin_trans('channel.fields.coin_num'))->display(function ($val, Channel $data) {
                return $data->player->where('is_coin', 1)->count();
            })->align('center');
            $grid->column('machine_num', admin_trans('channel.fields.machine_num'))->align('center');
            $grid->column('ratio', admin_trans('channel.fields.ratio'))->display(function (
                $val,
                Channel $data
            ) use ($grid) {
                $form = Form::create();
                $form->style(['padding' => '0px', 'background' => 'none']);
                $form->layout('inline');
                $form->removeAttr('labelCol');
                $form->url($grid->attr('url'));
                $form->number('ratio')
                    ->min(1)
                    ->value($val)
                    ->max(100)
                    ->precision(2)
                    ->controls(false)
                    ->addonAfter('%')
                    ->help(admin_trans('player_promoter.promoter_max_ratio', null,
                        ['{max_ratio}' => 100]));
                $form->method('PUT');
                $form->params($grid->getCall()['params'] + [
                        'ex_form_id' => 'id',
                        'ex_admin_form_action' => 'update',
                        'ids' => [$data->id],
                    ]);
                $form->actions()->submitButton()->htmlType('submit');
                $popover = Popover::create(Html::create()->tag('i')->attr('class',
                    ['far fa-edit', 'editable-cell-icon']))
                    ->trigger('click')
                    ->destroyTooltipOnHide()
                    ->content($form);
                $visible = $popover->vModel('visible', null, false);
                return Html::create()->content([
                    floatval($val) . '%',
                    $popover
                ])->attr('class', 'ex-admin-editable-cell')
                    ->event('dblclick', [$visible => true]);
            })->align('center')->ellipsis(true);
            $grid->column('machine_media_line', admin_trans('channel.fields.machine_media_line'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('channel.machine_media_line.' . $val)
                ]);
            })->align('center');
            $grid->column('created_at', admin_trans('channel.fields.create_at'))->align('center');
            $grid->column('status', admin_trans('channel.fields.status'))->switch();
            $grid->column('is_offline', admin_trans('channel.fields.is_offline'))->switch();
            $grid->column('lang', admin_trans('channel.fields.lang'))->display(function ($val) {
                return Html::create()->content([
                    admin_config('ui.lang.list')[$val] ?? ''
                ]);
            })->align('center');
            $grid->column('currency', admin_trans('channel.fields.currency'))->align('center');
            $grid->column('channel_function', admin_trans('channel.fields.channel_function'))->display(function (
                $value,
                Channel $channel
            ) {
                $channelFunction = [];
                if ($channel->web_login_status == 1) {
                    $channelFunction[] = 'web_login_status';
                }
                if ($channel->recharge_status == 1) {
                    $channelFunction[] = 'recharge_status';
                }
                if ($channel->withdraw_status == 1) {
                    $channelFunction[] = 'withdraw_status';
                }
                if ($channel->q_talk_recharge_status == 1) {
                    $channelFunction[] = 'q_talk_recharge_status';
                }
                if ($channel->q_talk_point_status == 1) {
                    $channelFunction[] = 'q_talk_point_status';
                }
                if ($channel->q_talk_withdraw_status == 1) {
                    $channelFunction[] = 'q_talk_withdraw_status';
                }
                if ($channel->promotion_status == 1) {
                    $channelFunction[] = 'promotion_status';
                }
                if ($channel->wallet_action_status == 1) {
                    $channelFunction[] = 'wallet_action_status';
                }
                if ($channel->coin_status == 1) {
                    $channelFunction[] = 'coin_status';
                }
                if ($channel->line_login_status == 1) {
                    $channelFunction[] = 'line_login_status';
                }
                if ($channel->national_promoter_status == 1) {
                    $channelFunction[] = 'national_promoter_status';
                }
                if ($channel->reverse_water_status == 1) {
                    $channelFunction[] = 'reverse_water_status';
                }
                if ($channel->gb_payment_recharge_status == 1) {
                    $channelFunction[] = 'gb_payment_recharge_status';
                }
                if ($channel->gb_payment_withdraw_status == 1) {
                    $channelFunction[] = 'gb_payment_withdraw_status';
                }
                if ($channel->discussion_group_status == 1) {
                    $channelFunction[] = 'discussion_group_status';
                }
                if ($channel->ranking_status == 1) {
                    $channelFunction[] = 'ranking_status';
                }
                if ($channel->activity_status == 1) {
                    $channelFunction[] = 'activity_status';
                }
                if ($channel->lottery_status == 1) {
                    $channelFunction[] = 'lottery_status';
                }
                $html = Html::create();
                foreach ($channelFunction as $option) {
                    $html->content(
                        Tag::create(admin_trans('channel.fields.' . $option))
                            ->color('success')
                    );
                }
                return $html;
            })->align('center');
            $grid->column('game_platform', admin_trans('channel.fields.game_platform'))->display(function (
                $value
            ) {
                $gamePlatformArr = explode(',', trim($value, '[]'));
                $gamePlatformArr = array_map('trim', $gamePlatformArr);
                $gamePlatformList = GamePlatform::query()->where('status', 1)->whereIn('id', $gamePlatformArr)->get()->toArray();
                $html = Html::create();
                foreach ($gamePlatformList as $option) {
                    $html->content(
                        Tag::create($option['name'])
                            ->color('success')
                    );
                }
                return $html;
            })->align('center');
            $grid->column('recharge_amount', admin_trans('channel.fields.recharge_amount'))->align('center');
            $grid->column('withdraw_amount', admin_trans('channel.fields.withdraw_amount'))->align('center');
            $grid->column('present_in_amount', admin_trans('channel.fields.present_in_amount'))->align('center');
            $grid->column('present_out_amount', admin_trans('channel.fields.present_out_amount'))->align('center');
            $grid->column('third_recharge_amount',
                admin_trans('channel.fields.third_recharge_amount'))->align('center');
            $grid->column('third_withdraw_amount',
                admin_trans('channel.fields.third_withdraw_amount'))->align('center');
            $grid->column('player_total_amount', admin_trans('channel.fields.player_total_amount'))->display(function (
                $val,
                Channel $data
            ) {
                return $data->wallet()->sum('money');
            })->align('center');
            $grid->column('site_id', admin_trans('channel.fields.site_id'))->copy()->align('center');
            $grid->hideDelete();
            $grid->setForm()->drawer($this->form());
            $grid->actions(function (Actions $actions, Channel $data) {
                $actions->prepend(
                    Button::create(admin_trans('channel.add_machine'))->drawer(admin_url([$this, 'machineList']),
                        ['department_id' => $data->department_id])
                );
            });
            $grid->filter(function (Filter $filter) {
                $filter->eq()->text('id')->placeholder(admin_trans('channel.fields.id'));
                $filter->like()->text('name')->placeholder(admin_trans('channel.fields.name'));
                $filter->like()->text('phone')->placeholder(admin_trans('channel.fields.phone'));
                $filter->like()->text('leader')->placeholder(admin_trans('channel.fields.leader'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('channel.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => admin_trans('channel.normal'),
                        0 => admin_trans('channel.disable')
                    ]);
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('channel.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => admin_trans('channel.type.' . Channel::TYPE_STORE),
                        2 => admin_trans('channel.type.' . Channel::TYPE_API),
                        3 => admin_trans('channel.type.' . Channel::TYPE_AGENT),
                    ]);
            });
            $grid->expandFilter();

            $grid->deleted(function ($ids) {
                DB::beginTransaction();
                try {
                    $departmentIds = Arr::pluck(Channel::select('department_id')->whereIn('id',
                        $ids)->withTrashed()->get()->toArray(), 'department_id');
                    AdminDepartment::whereIn('id', $departmentIds)->delete();
                    AdminUser::whereIn('department_id', $departmentIds)->delete();
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                }
            });
        });
    }

    /**
     * 渠道
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('channel.title'));

            $form->row(function (Form $form) {
                $form->text('name', admin_trans('channel.fields.name'))
                    ->ruleChsDash()
                    ->rule([
                        (string)Rule::unique(plugin()->webman->config('database.channel_model'))->ignore($form->input('id')) => admin_trans('channel.name_exist'),
                    ])
                    ->required();
                $form->text('domain', admin_trans('channel.fields.domain'))
                    ->ruleUrl()
                    ->rule([
                        (string)Rule::unique(plugin()->webman->config('database.channel_model'))->ignore($form->input('id')) => admin_trans('channel.channel_exist'),
                    ])
                    ->required()->style(['margin-left' => '10px']);
                $form->text('sms_name', admin_trans('channel.fields.sms_name'))->maxlength(10);
            });

            $form->hasMany('domain_ext', admin_trans('channel.fields.domain_ext'), function (Form $form) {
                $form->text('domain', admin_trans('channel.fields.domain_ext'))
                    ->ruleUrl()
                    ->rule([
                        (string)Rule::unique(plugin()->webman->config('database.channel_model'))->ignore($form->input('id')) => admin_trans('channel.channel_exist')
                    ]);
            });
            $form->text('client_version', admin_trans('channel.fields.client_version'))->rule([
                'regex:/^\d+\.\d+\.\d+$/' => admin_trans('channel.app_version_regex')
            ])->maxlength(30)->required();
            $form->row(function (Form $form) {
                $form->text('department.phone', admin_trans('channel.fields.phone'))->ruleNumber();
                $form->text('department.leader',
                    admin_trans('channel.fields.leader'))->style(['margin-left' => '10px']);
            });
            $form->text('download_url', admin_trans('channel.fields.download_url'))->maxlength('255')->ruleUrl();

            // 线下渠道开关
            $form->switch('is_offline', admin_trans('channel.fields.is_offline'))
                ->help(admin_trans('channel.help.is_offline'))
                ->when([1], function (Form $form) {
                    // APP更新配置
                    $form->divider()->content('APP更新配置');
                    $form->number('app_version_code', admin_trans('channel.fields.app_version_code'))
                        ->help(admin_trans('channel.help.app_version_code'))
                        ->min(1)
                        ->default(1);
                    $form->text('app_update_title', admin_trans('channel.fields.app_update_title'))
                        ->help(admin_trans('channel.help.app_update_title'))
                        ->maxlength(255);
                    $form->textarea('app_update_content', admin_trans('channel.fields.app_update_content'))
                        ->help(admin_trans('channel.help.app_update_content'))
                        ->rows(5);
                    $form->switch('app_force_update', admin_trans('channel.fields.app_force_update'))
                        ->help(admin_trans('channel.help.app_force_update'));

                    $form->divider()->content('APK下载地址（请在Google Cloud Storage上传后填写）');
                    $form->text('app_download_url', admin_trans('channel.fields.app_download_url'))
                        ->help(admin_trans('channel.help.app_download_url'))
                        ->maxlength(500)
                        ->ruleUrl();
                });

            $form->textarea('externalApp.white_ip', admin_trans('channel.fields.white_ip'))
                ->showCount()
                ->rule(['max:255' => admin_trans('channel.white_ip_help')])
                ->help(admin_trans('channel.white_ip_help'));
            $form->text('externalApp.notify_url', admin_trans('channel.fields.notify_url'))->maxlength('255')->ruleUrl();
            $form->radio('currency', admin_trans('channel.fields.currency'))
                ->button()
                ->options(plugin()->webman->config('currency'))
                ->required();
            $form->radio('lang', admin_trans('channel.fields.lang'))
                ->button()
                ->options(admin_config('ui.lang.list'))
                ->required();
            $form->radio('machine_media_line', admin_trans('channel.fields.machine_media_line'))
                ->button()
                ->options([
                    1 => admin_trans('channel.machine_media_line.1'),
                    2 => admin_trans('channel.machine_media_line.2'),
                    3 => admin_trans('channel.machine_media_line.3'),
                ])
                ->required();
            $form->radio('type', admin_trans('channel.fields.type'))
                ->button()
                ->options([
                    1 => admin_trans('channel.type.' . Channel::TYPE_STORE),
                    2 => admin_trans('channel.type.' . Channel::TYPE_API),
                    3 => admin_trans('channel.type.' . Channel::TYPE_AGENT),
                ])
                ->required();
            $form->row(function (Form $form) {
                if (!$form->isEdit()) {
                    $form->text('user.username', admin_trans('channel.fields.username'))
                        ->ruleChsDash()
                        ->rule([
                            (string)Rule::unique(plugin()->webman->config('database.user_model'),
                                'username')->ignore($form->input('id')) => admin_trans('admin.username_exist'),
                        ])
                        ->required()
                        ->addonAfter(Copy::create($form->input('user.username')))
                        ->disabled($form->isEdit());
                    $form->password('user.password', admin_trans('channel.fields.password'))
                        ->default(123456)
                        ->help(admin_trans('admin.pass_help'))
                        ->required();
                } else {
                    $form->text('user.username', admin_trans('channel.fields.username'))
                        ->ruleChsDash()
                        ->addonAfter(Copy::create($form->input('user.username')))
                        ->disabled($form->isEdit());
                }
            });
            $gamePlatformList = GamePlatform::query()->where('status', 1)->get();
            $optionList = [];
            /** @var GamePlatform $item */
            foreach ($gamePlatformList as $item) {
                $optionList[$item->id] = $item->name;
            }
            if ($form->isEdit()) {
                $string = $form->driver()->get('game_platform');
                $gamePlatformArr = explode(',', trim($string, '[]'));
                $gamePlatformArr = array_map('trim', $gamePlatformArr);
                $form->checkbox('game_platform', admin_trans('channel.fields.game_platform'))
                    ->value($gamePlatformArr)
                    ->options($optionList);
            } else {
                $form->checkbox('game_platform', admin_trans('channel.fields.game_platform'))
                    ->options($optionList);
            }
            $channelFunction = [];
            if ($form->isEdit()) {
                $id = $form->driver()->get('id');
                /** @var Channel $channel */
                $channel = Channel::find($id);
                if ($channel->recharge_status == 1) {
                    $channelFunction[] = 'recharge_status';
                }
                if ($channel->q_talk_recharge_status == 1) {
                    $channelFunction[] = 'q_talk_recharge_status';
                }
                if ($channel->q_talk_point_status == 1) {
                    $channelFunction[] = 'q_talk_point_status';
                }
                if ($channel->withdraw_status == 1) {
                    $channelFunction[] = 'withdraw_status';
                }
                if ($channel->q_talk_withdraw_status == 1) {
                    $channelFunction[] = 'q_talk_withdraw_status';
                }
                if ($channel->web_login_status == 1) {
                    $channelFunction[] = 'web_login_status';
                }
                if ($channel->promotion_status == 1) {
                    $channelFunction[] = 'promotion_status';
                }
                if ($channel->wallet_action_status == 1) {
                    $channelFunction[] = 'wallet_action_status';
                }
                if ($channel->coin_status == 1) {
                    $channelFunction[] = 'coin_status';
                }
                if ($channel->national_promoter_status == 1) {
                    $channelFunction[] = 'national_promoter_status';
                }
                if ($channel->reverse_water_status == 1) {
                    $channelFunction[] = 'reverse_water_status';
                }
                if ($channel->discussion_group_status == 1) {
                    $channelFunction[] = 'discussion_group_status';
                }
                if ($channel->ranking_status == 1) {
                    $channelFunction[] = 'ranking_status';
                }
                if ($channel->line_login_status == 1) {
                    $channelFunction[] = '1';
                }
                if ($channel->gb_payment_recharge_status == 1) {
                    $channelFunction[] = 'gb_payment_recharge_status';
                }
                if ($channel->gb_payment_withdraw_status == 1) {
                    $channelFunction[] = 'gb_payment_withdraw_status';
                }
                if ($channel->status_machine == 1) {
                    $channelFunction[] = 'status_machine';
                }
                if ($channel->activity_status == 1) {
                    $channelFunction[] = 'activity_status';
                }
                if ($channel->lottery_status == 1) {
                    $channelFunction[] = 'lottery_status';
                }
                if ($channel->eh_payment_recharge_status == 1) {
                    $channelFunction[] = 'eh_payment_recharge_status';
                }
                if ($channel->eh_payment_withdraw_status == 1) {
                    $channelFunction[] = 'eh_payment_withdraw_status';
                }
            }
            $form->row(function (Form $form) use ($channelFunction) {
                $form->checkbox('channel_function', admin_trans('channel.fields.channel_function'))
                    ->value($channelFunction)
                    ->options([
                        'web_login_status' => admin_trans('channel.fields.web_login_status'),
                        'recharge_status' => admin_trans('channel.fields.recharge_status'),
                        'withdraw_status' => admin_trans('channel.fields.withdraw_status'),
                        'q_talk_recharge_status' => admin_trans('channel.fields.q_talk_recharge_status'),
                        'q_talk_point_status' => admin_trans('channel.fields.q_talk_point_status'),
                        'q_talk_withdraw_status' => admin_trans('channel.fields.q_talk_withdraw_status'),
                        'promotion_status' => admin_trans('channel.fields.promotion_status'),
                        'wallet_action_status' => admin_trans('channel.fields.wallet_action_status'),
                        'coin_status' => admin_trans('channel.fields.coin_status'),
                        1 => admin_trans('channel.fields.line_login_status'),
                        'national_promoter_status' => admin_trans('channel.fields.national_promoter_status'),
                        'reverse_water_status' => admin_trans('channel.fields.reverse_water_status'),
                        'discussion_group_status' => admin_trans('channel.fields.discussion_group_status'),
                        'ranking_status' => admin_trans('channel.fields.ranking_status'),
                        'activity_status' => admin_trans('channel.fields.activity_status'),
                        'lottery_status' => admin_trans('channel.fields.lottery_status'),
                        'gb_payment_recharge_status' => admin_trans('channel.fields.gb_payment_recharge_status'),
                        'gb_payment_withdraw_status' => admin_trans('channel.fields.gb_payment_withdraw_status'),
                        'status_machine' => admin_trans('channel.fields.status_machine'),
                        'eh_payment_recharge_status' => admin_trans('channel.fields.eh_payment_recharge_status'),
                        'eh_payment_withdraw_status' => admin_trans('channel.fields.eh_payment_withdraw_status'),
                    ])
                    ->help(admin_trans('channel.channel_function_help'))
                    ->when([1], function (Form $form) {
                        $form->text('line_client_id', admin_trans('channel.fields.line_client_id'))
                            ->maxlength('50')
                            ->help(admin_trans('channel.line_client_id_help'));
                    });
            });
            $form->layout('vertical');

            $form->saving(function (Form $form) {
                $ext = $form->input('domain_ext');
                $whiteIp = $form->input('externalApp.white_ip');
                $notifyUrl = $form->input('externalApp.notify_url');
                $domain_ext = array_column($ext, 'domain');
                if (!empty($domain_ext)) {
                    $domain = $form->input('domain');
                    $arr = array_merge([$domain], $domain_ext);
                    $countArr = array_count_values($arr);
                    foreach ($countArr as $count) {
                        if ($count > 1) {
                            return message_error(admin_trans('channel.channel_exist'));
                        }
                    }
                }

                $channelFunction = $form->input('channel_function');
                if (!empty($channelFunction)) {
                    $artificial = collect(['recharge_status', 'withdraw_status']);
                    $qTalk = collect(['q_talk_point_status', 'q_talk_withdraw_status', 'q_talk_recharge_status']);
                    $intersectArtificial = $artificial->intersect($channelFunction)->toArray();
                    $intersectQTalk = $qTalk->intersect($channelFunction)->toArray();
                    if (!empty($intersectArtificial) && !empty($intersectQTalk)) {
                        return message_error(admin_trans('channel.channel_function_help'));
                    }
                }
                if (!$form->isEdit()) {
                    DB::beginTransaction();
                    try {
                        $adminDepartment = new AdminDepartment();
                        $adminDepartment->name = $form->input('name');
                        $adminDepartment->leader = $form->input('department.leader');
                        $adminDepartment->phone = $form->input('department.phone');
                        $adminDepartment->type = AdminDepartment::TYPE_CHANNEL;
                        $adminDepartment->save();

                        $adminUser = new AdminUser();
                        $adminUser->username = $form->input('user.username');
                        $adminUser->password = $form->input('user.password');
                        $adminUser->nickname = $form->input('name');
                        $adminUser->department_id = $adminDepartment->id;
                        $adminUser->type = AdminDepartment::TYPE_CHANNEL;
                        $adminUser->is_super = 1;
                        $adminUser->save();

                        $adminRole = new AdminRoleUsers();
                        $adminRole->role_id = AdminRole::ROLE_CHANNEL;
                        $adminRole->user_id = $adminUser->id;
                        $adminRole->save();

                        $channel = new Channel();
                        $channel->name = $form->input('name');
                        $channel->type = $form->input('type');
                        $channel->sms_name = $form->input('sms_name');
                        $channel->domain = $form->input('domain');
                        $channel->domain_ext = $form->input('domain_ext') ? json_encode($form->input('domain_ext')) : null;
                        $channel->lang = $form->input('lang');
                        $channel->client_version = $form->input('client_version');
                        $channel->is_offline = $form->input('is_offline') ? 1 : 0;
                        // 只有线下渠道才保存APP更新配置
                        if ($channel->is_offline) {
                            $channel->app_version_code = $form->input('app_version_code');
                            $channel->app_update_title = $form->input('app_update_title');
                            $channel->app_update_content = $form->input('app_update_content');
                            $channel->app_force_update = $form->input('app_force_update') ? 1 : 0;
                            $channel->app_download_url = $form->input('app_download_url');
                        }
                        $channel->currency = $form->input('currency');
                        $channel->machine_media_line = $form->input('machine_media_line');
                        $channel->download_url = $form->input('download_url');
                        $channel->department_id = $adminDepartment->id;
                        $channel->user_id = $adminUser->id;
                        $channel->site_id = gen_uuid(); // 站点标识
                        $channel->recharge_status = in_array('recharge_status', $channelFunction);
                        $channel->q_talk_recharge_status = in_array('q_talk_recharge_status', $channelFunction);
                        $channel->q_talk_point_status = in_array('q_talk_point_status', $channelFunction);
                        $channel->withdraw_status = in_array('withdraw_status', $channelFunction);
                        $channel->q_talk_withdraw_status = in_array('q_talk_withdraw_status', $channelFunction);
                        $channel->web_login_status = in_array('web_login_status', $channelFunction);
                        $channel->promotion_status = in_array('promotion_status', $channelFunction);
                        $channel->wallet_action_status = in_array('wallet_action_status', $channelFunction);
                        $channel->coin_status = in_array('coin_status', $channelFunction);
                        $channel->line_login_status = in_array('line_login_status', $channelFunction);
                        $channel->line_client_id = $form->input('line_client_id');
                        $channel->game_platform = json_encode($form->input('game_platform'));
                        $channel->national_promoter_status = in_array('national_promoter_status', $channelFunction);
                        $channel->reverse_water_status = in_array('reverse_water_status', $channelFunction);
                        $channel->discussion_group_status = in_array('discussion_group_status', $channelFunction);
                        $channel->ranking_status = in_array('ranking_status', $channelFunction);
                        $channel->activity_status = in_array('activity_status', $channelFunction);
                        $channel->lottery_status = in_array('lottery_status', $channelFunction);
                        $channel->gb_payment_recharge_status = in_array('gb_payment_recharge_status', $channelFunction);
                        $channel->gb_payment_withdraw_status = in_array('gb_payment_withdraw_status', $channelFunction);
                        $channel->status_machine = in_array('status_machine', $channelFunction);
                        $channel->eh_payment_recharge_status = in_array('eh_payment_recharge_status', $channelFunction);
                        $channel->eh_payment_withdraw_status = in_array('eh_payment_withdraw_status', $channelFunction);
                        $channel->save();

                        //todo 新建电子游戏平台时同步给各渠道生成web_id(数据库手动添加的平台 后期用脚本处理)

                        //获取游戏平台列表
                        $platformList = GamePlatform::query()->pluck('id','code')->toArray();
                        $channelId = $channel->id;
                        $webIds = getWebIds(array_keys($platformList));
                        $insert = [];
                        $time = Carbon::now()->toDateTimeString();
                        foreach ($platformList as $code => $id) {
                            $insert[] = [
                                'platform_id' => $id,
                                'channel_id' => $channelId,
                                'web_id' => $webIds[$code],
                                'created_at' => $time,
                                'updated_at' => $time,
                            ];
                        }

                        //批量生成关联关系
                        if (!empty($insert)) {
                            ChannelGameWeb::query()->insert($insert);
                        }

                        /** @var ExternalApp $externalApp */
                        $externalApp = new ExternalApp();
                        $externalApp->app_name = $channel->name;
                        do {
                            $externalApp->app_id = str_pad(random_int(0, 9999999999), 10, '0',
                                STR_PAD_LEFT); // 生成一个 10 位数字
                        } while (strlen($externalApp->app_id) !== 10); // 确保是 10 位数字
                        $externalApp->app_secret = bin2hex(random_bytes(16));
                        $externalApp->user_id = Admin::user()->id;
                        $externalApp->user_name = Admin::user()->username;
                        $externalApp->department_id = $channel->department_id;
                        if (!empty($whiteIp)) {
                            $ipArray = explode(',', $whiteIp);
                            // 检查每个 IP 地址
                            foreach ($ipArray as $ip) {
                                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                                    throw new Exception(admin_trans('channel.ip_error'));
                                }
                            }
                            $externalApp->white_ip = implode(',', $ipArray);
                        }
                        if (!empty($notifyUrl)) {
                            $externalApp->notify_url = $notifyUrl;
                        }
                        $externalApp->save();

                        // 更新部门path（必须在事务内完成）
                        $adminDepartment->path = $adminDepartment->id;
                        $adminDepartment->save();

                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();
                        Log::error('渠道新增失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                        return message_error(admin_trans('channel.save_error') . ': ' . $e->getMessage());
                    }
                    return message_success(admin_trans('channel.save_success'));
                } else {
                    $orgData = $form->driver()->get();
                    /** @var Channel $channel */
                    $channel = Channel::find($orgData['id']);
                    if (empty($channel)) {
                        return message_error(admin_trans('channel.not_fount'));
                    }

                    // 提前准备游戏平台数据（事务外执行，避免长时间持有锁）
                    $existsList = ChannelGameWeb::query()->where('channel_id', $channel->id)->pluck('platform_id')->toArray();
                    $platformList = GamePlatform::query()->whereNotIn('id',$existsList)->pluck('id','code')->toArray();
                    $channelId = $channel->id;
                    $webIds = getWebIds(array_keys($platformList));
                    $insert = [];
                    $time = Carbon::now()->toDateTimeString();
                    foreach ($platformList as $code => $id) {
                        $insert[] = [
                            'platform_id' => $id,
                            'channel_id' => $channelId,
                            'web_id' => $webIds[$code],
                            'created_at' => $time,
                            'updated_at' => $time,
                        ];
                    }

                    DB::beginTransaction();
                    try {
                        $channel->name = $form->input('name');
                        $channel->type = $form->input('type');
                        $channel->sms_name = $form->input('sms_name');
                        $channel->domain = $form->input('domain');
                        $channel->domain_ext = $form->input('domain_ext') ? json_encode($form->input('domain_ext')) : null;
                        $channel->lang = $form->input('lang');
                        $channel->client_version = $form->input('client_version');
                        $channel->is_offline = $form->input('is_offline') ? 1 : 0;
                        // 只有线下渠道才保存APP更新配置
                        if ($channel->is_offline) {
                            $channel->app_version_code = $form->input('app_version_code');
                            $channel->app_update_title = $form->input('app_update_title');
                            $channel->app_update_content = $form->input('app_update_content');
                            $channel->app_force_update = $form->input('app_force_update') ? 1 : 0;
                            $channel->app_download_url = $form->input('app_download_url');
                        }
                        $channel->currency = $form->input('currency');
                        $channel->machine_media_line = $form->input('machine_media_line');
                        $channel->download_url = $form->input('download_url');
                        $channel->recharge_status = in_array('recharge_status', $channelFunction);
                        $channel->q_talk_recharge_status = in_array('q_talk_recharge_status', $channelFunction);
                        $channel->q_talk_point_status = in_array('q_talk_point_status', $channelFunction);
                        $channel->withdraw_status = in_array('withdraw_status', $channelFunction);
                        $channel->q_talk_withdraw_status = in_array('q_talk_withdraw_status', $channelFunction);
                        $channel->web_login_status = in_array('web_login_status', $channelFunction);
                        $channel->promotion_status = in_array('promotion_status', $channelFunction);
                        $channel->wallet_action_status = in_array('wallet_action_status', $channelFunction);
                        $channel->coin_status = in_array('coin_status', $channelFunction);
                        $channel->line_login_status = in_array('line_login_status', $channelFunction);
                        $channel->line_client_id = $form->input('line_client_id');
                        $channel->game_platform = $form->input('game_platform');
                        $channel->national_promoter_status = in_array('national_promoter_status', $channelFunction);
                        $channel->reverse_water_status = in_array('reverse_water_status', $channelFunction);
                        $channel->gb_payment_recharge_status = in_array('gb_payment_recharge_status', $channelFunction);
                        $channel->gb_payment_withdraw_status = in_array('gb_payment_withdraw_status', $channelFunction);
                        $channel->discussion_group_status = in_array('discussion_group_status', $channelFunction);
                        $channel->ranking_status = in_array('ranking_status', $channelFunction);
                        $channel->activity_status = in_array('activity_status', $channelFunction);
                        $channel->lottery_status = in_array('lottery_status', $channelFunction);
                        $channel->status_machine = in_array('status_machine', $channelFunction);
                        $channel->eh_payment_recharge_status = in_array('eh_payment_recharge_status', $channelFunction);
                        $channel->eh_payment_withdraw_status = in_array('eh_payment_withdraw_status', $channelFunction);
                        $channel->save();
                        /** @var AdminDepartment $adminDepartment */
                        $adminDepartment = AdminDepartment::find($channel->department_id);
                        $adminDepartment->name = $form->input('name');
                        $adminDepartment->leader = $form->input('department.leader');
                        $adminDepartment->phone = $form->input('department.phone');
                        $adminDepartment->save();
                        /** @var ExternalApp $externalApp */
                        $externalApp = ExternalApp::query()->where('department_id', $channel->department_id)->first();
                        if (empty($externalApp)) {
                            /** @var ExternalApp $externalApp */
                            $externalApp = new ExternalApp();
                            do {
                                $externalApp->app_id = str_pad(random_int(0, 9999999999), 10, '0',
                                    STR_PAD_LEFT); // 生成一个 10 位数字
                            } while (strlen($externalApp->app_id) !== 10); // 确保是 10 位数字
                            $externalApp->app_secret = bin2hex(random_bytes(16));
                            $externalApp->user_id = Admin::user()->id;
                            $externalApp->user_name = Admin::user()->username;
                            $externalApp->department_id = $channel->department_id;
                        }
                        if (!empty($whiteIp)) {
                            $ipArray = explode(',', $whiteIp);
                            // 检查每个 IP 地址
                            foreach ($ipArray as $ip) {
                                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                                    throw new Exception(admin_trans('channel.ip_error'));
                                }
                            }
                            $externalApp->white_ip = implode(',', $ipArray);
                        }
                        if (!empty($notifyUrl)) {
                            $externalApp->notify_url = $notifyUrl;
                        }
                        $externalApp->app_name = $channel->name;
                        $externalApp->save();

                        // 批量生成关联关系（数据已在事务外准备好）
                        if (!empty($insert)) {
                            ChannelGameWeb::query()->insert($insert);
                        }
                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();
                        return message_error(admin_trans('channel.save_error') . $e->getMessage());
                    }
                    return message_success(admin_trans('channel.save_success'));
                }
            });
        });
    }

    /**
     * 筛选部门/渠道
     * @return mixed
     */
    public function getDepartmentOptions()
    {
        $request = Request::input();
        $channel = Channel::orderBy('created_at', 'desc');
        if (!empty($request['search'])) {
            $channel->where('name', 'like', '%' . $request['search'] . '%');
        }
        $channelList = $channel->get();
        $data = [];
        /** @var Channel $channel */
        foreach ($channelList as $channel) {
            $data[] = [
                'value' => $channel->department_id,
                'label' => $channel->name,
            ];
        }
        return Response::success($data);
    }

    /**
     * 查看渠道机台
     * @param int $department_id
     * @return Grid
     * @auth true
     */
    public function machineList(int $department_id): Grid
    {
        $departmentId = Request::input('department_id');
        $selectedId = ChannelMachine::query()
            ->when(!empty($department_id), function ($query) use ($department_id) {
                $query->where('department_id', $department_id);
            })
            ->when(!empty($departmentId), function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->pluck('machine_id')
            ->toArray();
        return Grid::create(new $this->machineModel(), function (Grid $grid) use ($department_id) {
            $grid->title('添加机台');
            $grid->model()->whereIn('type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL])->orderBy('id',
                'desc');
            $grid->driver()->setPk('id');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $page = Request::input('ex_admin_page', 1);
            $size = Request::input('ex_admin_size', 25);
            $param = [
                'size' => $size,
                'page' => $page,
                'ex_admin_filter' => $exAdminFilter,
                'department_id' => $department_id,
            ];
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine.fields.id'))->align('center');
            $grid->column('cate_id', admin_trans('machine.fields.cate_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($data->type)),
                    $data->machineCategory->name ?? '',
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
            $grid->column('label_id', admin_trans('machine.fields.label_id'))->display(function ($val, Machine $data) {
                return Html::create()->content([
                    $data->name
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
            $grid->column('status', admin_trans('machine.fields.status'))->switch()->align('center');
            $grid->hideDelete();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->pagination()->pageSize(25);
            $grid->hideDeleteSelection();
            $grid->hideTrashed();
            $grid->tools(
                Button::create(admin_trans('channel.add_machine'))
                    ->icon(Icon::create('fas fa-chalkboard'))
                    ->confirm(admin_trans('channel.add_machine_confirm'),
                        [
                            $this,
                            'addMachine?' . http_build_query($param)
                        ])
                    ->gridBatch()->gridRefresh()
            );
            $grid->filter(function (Filter $filter) use ($department_id) {
                $filter->like()->text('machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('machine.fields.code'));
                $filter->eq()->select('label_id')
                    ->placeholder(admin_trans('machine.fields.label_id'))
                    ->style(['width' => '150px'])
                    ->dropdownMatchSelectWidth()
                    ->options(getMachineLabelOptions());
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
            });
        })->selection($selectedId);
    }

    /**
     * 执行添加机器
     * @param $selected
     * @param $department_id
     * @param $size
     * @param $page
     * @param array $ex_admin_filter
     * @return Msg
     * @auth true
     */
    public function addMachine($selected, $department_id, $size, $page, array $ex_admin_filter = []): Msg
    {
        if (!isset($selected)) {
            return message_error(admin_trans('channel.selected_machine'));
        }
        /** @var Channel $channel */
        $channel = Channel::query()->where('department_id', $department_id)->first();
        if (empty($channel)) {
            return message_error(admin_trans('channel.channel_not_found'));
        }
        if ($channel->status == 0) {
            return message_error(admin_trans('channel.channel_disable'));
        }
        $selectedMachineList = Machine::query()->whereIn('id', $selected)->get();
        if (!$selectedMachineList) {
            return message_error(admin_trans('channel.not_found_selected_machine'));
        }
        $filteredIds = [];
        $filterAdd = false;
        if (!empty($ex_admin_filter['code']) || !empty($ex_admin_filter['machineLabel']['code']) || !empty($ex_admin_filter['cate_id']) || !empty($ex_admin_filter['label_id']) || !empty($ex_admin_filter['status'])) {
            /** @var Machine $machine */
            $machineList = Machine::query()
                ->when(!empty($ex_admin_filter['code']), function (Builder $query) use ($ex_admin_filter) {
                    $query->where('code', 'like', '%' . $ex_admin_filter['code'] . '%');
                })
                ->when(!empty($ex_admin_filter['machineLabel']['code']),
                    function (Builder $query) use ($ex_admin_filter) {
                        $query->whereHas('machineLabel', function ($query) use ($ex_admin_filter) {
                            $query->where('name', 'like', '%' . $ex_admin_filter['machineLabel']['code'] . '%');
                        });
                    })
                ->when(!empty($ex_admin_filter['cate_id']), function (Builder $query) use ($ex_admin_filter) {
                    $query->whereIn('cate_id', $ex_admin_filter['cate_id']);
                })
                ->when(!empty($ex_admin_filter['label_id']), function (Builder $query) use ($ex_admin_filter) {
                    $query->where('label_id', $ex_admin_filter['label_id']);
                })
                ->when(!empty($ex_admin_filter['status']), function (Builder $query) use ($ex_admin_filter) {
                    $query->where('status', $ex_admin_filter['status']);
                })
                ->orderBy('id', 'desc')
                ->forPage($page, $size)
                ->get()
                ->pluck('id')
                ->toArray();
            $filteredIds = array_diff($machineList, $selected);
            $filterAdd = true;
        }
        $insertData = [];
        foreach ($selected as $value) {
            $insertData[] = [
                'department_id' => $department_id,
                'machine_id' => $value,
            ];
        }
        DB::beginTransaction();
        try {
            if ($filterAdd) {
                if (!empty($filteredIds)) {
                    ChannelMachine::query()->where('department_id', $department_id)->whereIn('machine_id',
                        $filteredIds)->delete();
                }
            } else {
                ChannelMachine::query()->where('department_id', $department_id)->delete();
            }
            ChannelMachine::query()->upsert($insertData, ['department_id', 'machine_id']);
            $channel->machine_num = ChannelMachine::query()->where('department_id', $department_id)->count();
            $channel->save();
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            return message_error(admin_trans('channel.add_machine_fail'));
        }

        return message_success(admin_trans('channel.add_machine_success'))->refresh();
    }
}

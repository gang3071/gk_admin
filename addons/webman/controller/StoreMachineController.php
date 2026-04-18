<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\AdminUserLimitGroup;
use addons\webman\model\Channel;
use addons\webman\model\GamePlatform;
use addons\webman\model\PlatformLimitGroup;
use addons\webman\model\PlatformLimitGroupConfig;
use addons\webman\model\StoreSetting;
use Carbon\Carbon;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Arr;
use support\Db;

/**
 * 店家管理（线下渠道）
 * @group channel
 */
class StoreMachineController
{
    /**
     * 店家列表
     * @auth true
     * @group channel
     */
    public function index(): Grid
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">' . admin_trans('store_machine.offline_only') . '</font>'));
            });
        }

        $currentDepartmentId = Admin::user()->department_id;

        // 获取店家选项列表用于筛选器下拉选择
        $storeOptions = AdminUser::query()
            ->join('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
            ->where('admin_users.type', AdminUser::TYPE_STORE)
            ->where('parent_admin.department_id', $currentDepartmentId)
            ->orderBy('admin_users.id', 'desc')
            ->get(['admin_users.id as id', 'admin_users.nickname as nickname', 'admin_users.username as username'])
            ->mapWithKeys(function ($store) {
                $label = $store->nickname ?: $store->username;
                $label .= " ({$store->username})";
                return [$store->id => $label];
            })
            ->toArray();

        return Grid::create(new AdminUser(), function (Grid $grid) use ($currentDepartmentId, $storeOptions) {
            $grid->title(admin_trans('store_machine.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：店家类型 + 数据权限过滤（通过代理的 department_id）
            $grid->model()
                ->join('admin_department as dept', 'admin_users.department_id', '=', 'dept.id')
                ->leftJoin('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
                ->where('admin_users.type', AdminDepartment::TYPE_STORE)
                ->where('parent_admin.department_id', $currentDepartmentId)  // 通过代理的 department_id 过滤
                ->select([
                    'admin_users.*',
                    'dept.name as department_name',
                    'dept.phone as department_phone',
                    'parent_admin.nickname as parent_agent_name'
                ])
                ->orderBy('admin_users.id', 'desc');

            // 手动处理筛选条件（避免字段歧义）
            $filterData = \ExAdmin\ui\support\Request::input('ex_admin_filter', []);

            // 店家 ID 筛选
            if (!empty($filterData['store_id_custom'])) {
                $grid->model()->where('admin_users.id', $filterData['store_id_custom']);
            }

            // 状态筛选
            if (isset($filterData['status_custom']) && $filterData['status_custom'] !== null && $filterData['status_custom'] !== '') {
                $grid->model()->where('admin_users.status', $filterData['status_custom']);
            }

            // 用户名筛选
            if (!empty($filterData['username_custom'])) {
                $grid->model()->where('admin_users.username', 'like', '%' . $filterData['username_custom'] . '%');
            }

            // 昵称筛选
            if (!empty($filterData['nickname_custom'])) {
                $grid->model()->where('admin_users.nickname', 'like', '%' . $filterData['nickname_custom'] . '%');
            }

            // 电话筛选
            if (!empty($filterData['department_phone_custom'])) {
                $grid->model()->where('dept.phone', 'like', '%' . $filterData['department_phone_custom'] . '%');
            }

            // 代理筛选
            if (!empty($filterData['parent_admin_id_custom'])) {
                $grid->model()->where('admin_users.parent_admin_id', $filterData['parent_admin_id_custom']);
            }

            // 创建时间筛选
            if (!empty($filterData['created_at_custom']) && is_array($filterData['created_at_custom'])) {
                if (!empty($filterData['created_at_custom'][0])) {
                    $grid->model()->where('admin_users.created_at', '>=', $filterData['created_at_custom'][0]);
                }
                if (!empty($filterData['created_at_custom'][1])) {
                    $grid->model()->where('admin_users.created_at', '<=', $filterData['created_at_custom'][1]);
                }
            }

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('nickname', admin_trans('store_machine.fields.name'))->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val, 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val)->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('username', admin_trans('store_machine.fields.username'))->width(120)->align('center');
            $grid->column('department_phone', admin_trans('store_machine.fields.phone'))->width(120)->align('center');
            $grid->column('parent_agent_name', admin_trans('admin.agent'))->width(120)->align('center');
            $grid->column('department_name', admin_trans('store_machine.fields.department_name'))->width(150)->ellipsis(true);

            // 分润比例
            $grid->column('agent_commission', admin_trans('store_machine.fields.agent_commission'))->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create(admin_trans('store_machine.status.not_set'))->color('default');
                }
                return Tag::create($value . '%')->color('orange');
            })->width(100)->align('center');

            $grid->column('channel_commission', admin_trans('store_machine.fields.channel_commission'))->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create(admin_trans('store_machine.status.not_set'))->color('default');
                }
                return Tag::create($value . '%')->color('blue');
            })->width(100)->align('center');

            $grid->column('status', admin_trans('store_machine.fields.status'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('store_machine.status.disabled'))->color('red'),
                    1 => Tag::create(admin_trans('store_machine.status.normal'))->color('green'),
                    default => '',
                };
            })->width(80)->align('center');

            $grid->column('created_at', admin_trans('store_machine.fields.created_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) use ($storeOptions) {
                // 店家下拉筛选（使用自定义字段名，手动处理）
                $filter->eq()->select('store_id_custom')
                    ->placeholder(admin_trans('store_machine.filter.select_store'))
                    ->options(['' => admin_trans('store_machine.all')] + $storeOptions)
                    ->style(['width' => '250px']);

                $filter->eq()->select('status_custom')
                    ->placeholder(admin_trans('store_machine.placeholder.status'))
                    ->options([
                        1 => admin_trans('store_machine.status.normal'),
                        0 => admin_trans('store_machine.status.disabled')
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('username_custom')->placeholder(admin_trans('store_machine.placeholder.username'));
                $filter->like()->text('nickname_custom')->placeholder(admin_trans('store_machine.placeholder.name'));
                $filter->like()->text('department_phone_custom')->placeholder(admin_trans('store_machine.placeholder.phone'));

                // 代理筛选
                $filter->eq()->select('parent_admin_id_custom')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.agent'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-StoreMachineController',
                        'getAgentOptions'
                    ]));

                $filter->between()->dateTimeRange('created_at_custom')
                    ->placeholder([admin_trans('store_machine.placeholder.start_time'), admin_trans('store_machine.placeholder.end_time')]);
            });

            $grid->actions(function (Actions $actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 添加限红组配置按钮
                $actions->append(
                    Button::create('限红组')
                        ->modal([$this, 'limitGroupForm'], ['store_id' => $data['id']])
                        ->type('primary')
                        ->size('small')
                );

                // 添加自动交班配置按钮
                $actions->append(
                    Button::create('自动交班配置')
                        ->modal([$this, 'autoShiftConfigForm'], ['store_id' => $data['id']])
                        ->type('default')
                        ->size('small')
                );

                // 添加店家系统配置按钮
                $actions->append(
                    Button::create('系统配置')
                        ->drawer([$this, 'storeSettingList'], ['store_id' => $data['id']])
                        ->type('default')
                        ->size('small')
                );

                // 添加店家开分配置按钮
                $actions->append(
                    Button::create('开分配置')
                        ->drawer([$this, 'openScoreSettingList'], ['store_id' => $data['id']])
                        ->type('default')
                        ->size('small')
                );
            });

            // 行展开 - 显示限红组配置信息
            $grid->expandRow(function ($row) {
                // 查询该店家的所有限红组配置
                $limitConfigs = AdminUserLimitGroup::query()
                    ->with(['limitGroup', 'gamePlatform'])
                    ->where('admin_user_id', $row['id'])
                    ->whereNull('deleted_at')
                    ->get();

                if ($limitConfigs->isEmpty()) {
                    return Card::create([
                        Html::div()->content([
                            Html::create('暂无限红组配置')->style(['padding' => '20px', 'color' => '#999', 'textAlign' => 'center'])
                        ])
                    ]);
                }

                $rows = [];

                foreach ($limitConfigs as $limitConfig) {
                    // 获取平台名称
                    $platformName = $limitConfig->gamePlatform ?
                        "{$limitConfig->gamePlatform->name} ({$limitConfig->platform_code})" :
                        $limitConfig->platform_code;

                    // 获取限红组名称
                    $limitGroupName = $limitConfig->limitGroup ?
                        "{$limitConfig->limitGroup->name} ({$limitConfig->limitGroup->code})" :
                        '-';

                    // 获取配置详情（从限红组配置表中获取）
                    $configDetail = '-';
                    if ($limitConfig->limitGroup) {
                        $groupConfig = PlatformLimitGroupConfig::query()
                            ->where('limit_group_id', $limitConfig->limit_group_id)
                            ->where('platform_id', $limitConfig->platform_id)
                            ->where('status', 1)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($groupConfig && $groupConfig->config_data) {
                            $configData = $groupConfig->config_data;
                            if ($limitConfig->platform_code === 'ATG' && isset($configData['operator'])) {
                                $configDetail = "营运账号: {$configData['operator']}";
                            } elseif ($limitConfig->platform_code === 'RSG') {
                                $min = $configData['min_bet_amount'] ?? 0;
                                $max = $configData['max_bet_amount'] ?? 0;
                                $configDetail = "限红范围: {$min} - {$max}";
                            } elseif ($limitConfig->platform_code === 'DG') {
                                $min = $configData['min'] ?? 0;
                                $max = $configData['max'] ?? 0;
                                $configDetail = "限红范围: {$min} - {$max}";
                            }
                        }
                    }

                    // 格式化分配时间
                    $assignedAt = $limitConfig->assigned_at ?
                        Carbon::parse($limitConfig->assigned_at)->format('Y-m-d H:i:s') :
                        '-';

                    // 添加平台配置行
                    $rows[] = Html::div()->content([
                        Html::create($platformName)->tag('h4')->style(['marginBottom' => '10px', 'marginTop' => count($rows) > 0 ? '20px' : '0', 'color' => '#1890ff']),
                        Html::create()->content([
                            // 第1行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('限红组')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($limitGroupName)
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('配置详情')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($configDetail)
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第2行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('分配时间')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($assignedAt)
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ])
                        ])
                    ]);
                }

                return Card::create([
                    Html::div()->content([
                        Html::create('限红组配置详情')->tag('h4')->style(['marginBottom' => '15px']),
                        Html::create()->content($rows)
                    ])->style(['padding' => '20px'])
                ]);
            });

            $grid->hideSelection();
            $grid->hideDelete();
            $grid->setForm()->drawer($this->createStoreMachineForm());
            $grid->expandFilter();
        });
    }

    /**
     * 创建店家表单
     * @auth true
     * @group channel
     */
    public function createStoreMachineForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">' . admin_trans('store_machine.offline_only') . '</font>'));
            });
        }

        // 获取代理列表
        $agents = AdminUser::query()
            ->join('admin_department', 'admin_users.department_id', '=', 'admin_department.id')
            ->where('admin_users.type', AdminDepartment::TYPE_AGENT)
            ->where('admin_users.status', 1)
            ->where('admin_users.department_id', Admin::user()->department_id)
            ->select('admin_users.id', 'admin_users.nickname as name')
            ->get();

        $agentOptions = [];
        foreach ($agents as $agent) {
            $agentOptions[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'pid' => 0
            ];
        }
        $agentTreeOptions = Arr::tree($agentOptions);

        return Form::create([], function (Form $form) use ($agentTreeOptions) {
            $form->title(admin_trans('store_machine.form.create_title'));
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">' . admin_trans('store_machine.form.create_hint') . '</font>'));

            $form->divider()->content(admin_trans('store_machine.form.section_account'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->text('phone', admin_trans('store_machine.fields.phone'))
                        ->maxlength(20)
                        ->help(admin_trans('store_machine.help.phone'));
                })->span(12);

                $form->column(function (Form $form) {
                    $form->text('admin_username', admin_trans('store_machine.fields.username'))
                        ->maxlength(20)
                        ->help(admin_trans('store_machine.help.username'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', admin_trans('store_machine.fields.name'))
                ->maxlength(50)
                ->help(admin_trans('store_machine.help.name'))
                ->required();

            $form->divider()->content(admin_trans('store_machine.form.section_parent_agent'));

            $form->treeSelect('recommend_id', admin_trans('store_machine.form.select_parent_agent'))
                ->options($agentTreeOptions)
                ->help(admin_trans('store_machine.help.parent_agent'))
                ->required();

            $form->divider()->content(admin_trans('store_machine.form.section_avatar'));

            $form->image('avatar', admin_trans('store_machine.fields.avatar'))
                ->help(admin_trans('store_machine.help.avatar'))
                ->required();

            $form->divider()->content(admin_trans('store_machine.form.section_password'));

            $form->password('password', admin_trans('store_machine.fields.password'))
                ->rule(['min:6' => admin_trans('store_machine.validation.password_min')])
                ->help(admin_trans('store_machine.help.password'))
                ->required();

            $form->password('password_confirmation', admin_trans('store_machine.fields.password_confirmation'))
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->createStoreMachineSaving($form);
            });
        });
    }

    /**
     * 创建店家保存逻辑
     * 店家不创建玩家账号，只创建后台管理账号
     */
    private function createStoreMachineSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error(admin_trans('store_machine.error.offline_only'));
        }

        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $recommendId = $form->input('recommend_id');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        if (empty($avatar)) {
            return message_error(admin_trans('store_machine.error.avatar_required'));
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error(admin_trans('store_machine.error.password_mismatch'));
        }

        // 检查上级代理
        $parentAgent = AdminUser::query()
            ->where('id', $recommendId)
            ->where('type', AdminDepartment::TYPE_AGENT)
            ->first();
        if (!$parentAgent) {
            return message_error(admin_trans('store_machine.error.parent_agent_not_found'));
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error(admin_trans('store_machine.error.username_exists', null, ['username' => $adminUsername]));
        }

        DB::beginTransaction();
        try {
            // 店家直接使用渠道的 department_id，不创建新的部门
            $departmentId = $parentAgent->department_id;

            // 1. 创建后台管理员账号（店家后台超管）
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_STORE;
            $adminUser->department_id = $departmentId;
            $adminUser->parent_admin_id = $parentAgent->id; // 保存上级代理ID
            $adminUser->is_super = 0; // 店家不是超级管理员
            $adminUser->save();

            // 2. 分配店家超管角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.store_role'); // 店家超管角色ID（19）
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 3. 创建店家配置
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $departmentId;
            $storeSetting->admin_user_id = $adminUser->id;
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = admin_trans('store_machine.welcome_message');
            $storeSetting->status = 1;
            $storeSetting->save();

            $storeSettingMachine = new StoreSetting();
            $storeSettingMachine->department_id = $departmentId;
            $storeSettingMachine->admin_user_id = $adminUser->id;
            $storeSettingMachine->feature = 'enable_physical_machine';
            $storeSettingMachine->num = 1;
            $storeSettingMachine->status = 1;
            $storeSettingMachine->save();

            $storeSettingBaccarat = new StoreSetting();
            $storeSettingBaccarat->department_id = $departmentId;
            $storeSettingBaccarat->admin_user_id = $adminUser->id;
            $storeSettingBaccarat->feature = 'enable_live_baccarat';
            $storeSettingBaccarat->num = 1;
            $storeSettingBaccarat->status = 1;
            $storeSettingBaccarat->save();

            // machine_crash_amount（爆机金额）
            $storeSettingCrashAmount = new StoreSetting();
            $storeSettingCrashAmount->department_id = $departmentId;
            $storeSettingCrashAmount->admin_user_id = $adminUser->id;
            $storeSettingCrashAmount->feature = 'machine_crash_amount';
            $storeSettingCrashAmount->num = 0; // 默认金额为 0
            $storeSettingCrashAmount->status = 0; // 默认不开启
            $storeSettingCrashAmount->save();

            // 4. 创建默认自动交班配置（一个店家一条记录）
            // 修复：唯一索引 uk_dept_admin (department_id, bind_admin_user_id) 限制一个店家只能有一条配置
            $autoShiftConfig = new \addons\webman\model\StoreAutoShiftConfig();
            $autoShiftConfig->department_id = $departmentId;
            $autoShiftConfig->bind_admin_user_id = $adminUser->id;
            $autoShiftConfig->is_enabled = 0; // 默认禁用，由店家自行启用
            $autoShiftConfig->shift_mode = 1; // 每日模式
            $autoShiftConfig->shift_time = '02:00:00'; // 默认凌晨2点交班
            $autoShiftConfig->auto_settlement = 1; // 自动结算
            $autoShiftConfig->save();

            DB::commit();

            return message_success(admin_trans('store_machine.create_success', null, [
                'name' => $name,
                'username' => $adminUsername,
                'agent_label' => admin_trans('admin.agent'),
                'agent_name' => $parentAgent->nickname
            ]));

        } catch (\Exception $e) {
            DB::rollBack();
            return message_error(admin_trans('store_machine.create_failed', null, ['error' => $e->getMessage()]));
        }
    }

    /**
     * 获取代理选项（用于店家筛选）
     * @auth true
     * @group channel
     */
    public function getAgentOptions(): Response
    {
        $currentDepartmentId = Admin::user()->department_id;

        $agents = AdminUser::query()
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->where('department_id', $currentDepartmentId)
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
     * 限红组配置表单
     * @auth true
     * @group channel
     */
    public function limitGroupForm()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        // 查询该店家现有的所有限红组配置（按平台区分）
        $existingConfigs = AdminUserLimitGroup::query()
            ->where('admin_user_id', $storeId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('platform_id');

        return Form::create([], function (Form $form) use ($storeId, $existingConfigs) {
            $form->title('限红组配置');

            // 显式设置提交URL
            $form->url(admin_url([
                'addons-webman-controller-StoreMachineController',
                'saveLimitGroupConfig'
            ]));

            $form->hidden('store_id')->value($storeId);

            // 获取ATG、RSG和DG平台
            $atgPlatform = GamePlatform::query()->where('code', 'ATG')->where('status', 1)->first();
            $rsgPlatform = GamePlatform::query()->where('code', 'RSG')->where('status', 1)->first();
            $dgPlatform = GamePlatform::query()->where('code', 'DG')->where('status', 1)->first();

            if (!$atgPlatform && !$rsgPlatform && !$dgPlatform) {
                $form->push(Html::markdown('><font size=2 color="#999">暂无可用的游戏平台（ATG/RSG/DG）</font>'));
            } else {
                // ATG平台配置
                if ($atgPlatform) {
                    $form->divider()->content('ATG 平台限红组配置');

                    $atgConfig = $existingConfigs->get($atgPlatform->id);
                    $atgOptions = ['' => '不配置限红组'] + $this->getLimitGroupOptionsForPlatform($atgPlatform->id);

                    $form->select('atg_limit_group_id', "限红组 ({$atgPlatform->name})")
                        ->options($atgOptions)
                        ->value($atgConfig ? $atgConfig->limit_group_id : null)
                        ->help('选择ATG平台的限红组，选择「不配置限红组」将清除该平台的限红配置');
                }

                // RSG平台配置
                if ($rsgPlatform) {
                    $form->divider()->content('RSG 平台限红组配置');

                    $rsgConfig = $existingConfigs->get($rsgPlatform->id);
                    $rsgOptions = ['' => '不配置限红组'] + $this->getLimitGroupOptionsForPlatform($rsgPlatform->id);

                    $form->select('rsg_limit_group_id', "限红组 ({$rsgPlatform->name})")
                        ->options($rsgOptions)
                        ->value($rsgConfig ? $rsgConfig->limit_group_id : null)
                        ->help('选择RSG平台的限红组，选择「不配置限红组」将清除该平台的限红配置');
                }

                // DG平台配置
                if ($dgPlatform) {
                    $form->divider()->content('DG 平台限红组配置');

                    $dgConfig = $existingConfigs->get($dgPlatform->id);
                    $dgOptions = ['' => '不配置限红组'] + $this->getLimitGroupOptionsForPlatform($dgPlatform->id);

                    $form->select('dg_limit_group_id', "限红组 ({$dgPlatform->name})")
                        ->options($dgOptions)
                        ->value($dgConfig ? $dgConfig->limit_group_id : null)
                        ->help('选择DG平台的限红组，选择「不配置限红组」将清除该平台的限红配置');
                }
            }
        });
    }

    /**
     * 保存限红组配置
     * @auth true
     * @group channel
     */
    public function saveLimitGroupConfig()
    {
        // 从请求中获取数据
        $request = request();
        $postData = $request->post();
        $data = $postData['data'] ?? $postData;

        $storeId = $data['store_id'] ?? null;

        if (!$storeId) {
            return message_error('店家ID不能为空');
        }

        // 获取ATG、RSG和DG平台
        $atgPlatform = GamePlatform::query()->where('code', 'ATG')->where('status', 1)->first();
        $rsgPlatform = GamePlatform::query()->where('code', 'RSG')->where('status', 1)->first();
        $dgPlatform = GamePlatform::query()->where('code', 'DG')->where('status', 1)->first();

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            $errors = [];

            // 处理ATG平台配置
            if ($atgPlatform) {
                $result = $this->savePlatformLimitGroup(
                    $storeId,
                    $atgPlatform->id,
                    $atgPlatform->code,
                    $data['atg_limit_group_id'] ?? null
                );

                if ($result['success']) {
                    $updatedCount++;
                } else {
                    $errors[] = "ATG: {$result['message']}";
                }
            }

            // 处理RSG平台配置
            if ($rsgPlatform) {
                $result = $this->savePlatformLimitGroup(
                    $storeId,
                    $rsgPlatform->id,
                    $rsgPlatform->code,
                    $data['rsg_limit_group_id'] ?? null
                );

                if ($result['success']) {
                    $updatedCount++;
                } else {
                    $errors[] = "RSG: {$result['message']}";
                }
            }

            // 处理DG平台配置
            if ($dgPlatform) {
                $result = $this->savePlatformLimitGroup(
                    $storeId,
                    $dgPlatform->id,
                    $dgPlatform->code,
                    $data['dg_limit_group_id'] ?? null
                );

                if ($result['success']) {
                    $updatedCount++;
                } else {
                    $errors[] = "DG: {$result['message']}";
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return message_error('配置失败：' . implode('；', $errors));
            }

            DB::commit();

            if ($updatedCount > 0) {
                return message_success("限红组配置成功，已更新 {$updatedCount} 个平台的配置");
            } else {
                return message_success('操作成功');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return message_error('配置失败：' . $e->getMessage());
        }
    }

    /**
     * 保存单个平台的限红组配置
     */
    private function savePlatformLimitGroup($storeId, $platformId, $platformCode, $limitGroupId)
    {
        // 查找该店机在该平台的现有配置
        $config = AdminUserLimitGroup::query()
            ->where('admin_user_id', $storeId)
            ->where('platform_id', $platformId)
            ->whereNull('deleted_at')
            ->first();

        // 如果不选择限红组（清空配置）
        if (empty($limitGroupId)) {
            if ($config) {
                // 软删除现有配置
                $config->delete();
                return ['success' => true, 'message' => '已清除限红组配置'];
            }
            return ['success' => true, 'message' => '无需操作'];
        }

        // 验证限红组
        $limitGroup = PlatformLimitGroup::find($limitGroupId);
        if (!$limitGroup) {
            return ['success' => false, 'message' => '限红组不存在'];
        }

        // 验证限红组是否配置了该平台
        $limitGroupConfig = PlatformLimitGroupConfig::query()
            ->where('limit_group_id', $limitGroupId)
            ->where('platform_id', $platformId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$limitGroupConfig) {
            return ['success' => false, 'message' => '该限红组未配置此平台'];
        }

        // 更新或创建配置
        if ($config) {
            // 更新现有配置
            $config->limit_group_id = $limitGroupId;
            $config->assigned_by = Admin::user()->id;
            $config->assigned_at = Carbon::now();
            $config->status = 1;
            $config->save();
        } else {
            // 创建新配置
            $config = new AdminUserLimitGroup();
            $config->admin_user_id = $storeId;
            $config->limit_group_id = $limitGroupId;
            $config->platform_id = $platformId;
            $config->platform_code = $platformCode;
            $config->assigned_by = Admin::user()->id;
            $config->assigned_at = Carbon::now();
            $config->status = 1;
            $config->save();
        }

        return ['success' => true, 'message' => '配置成功'];
    }

    /**
     * 获取指定平台的限红组选项
     * @param int $platformId 游戏平台ID
     * @return array
     */
    private function getLimitGroupOptionsForPlatform($platformId): array
    {
        $data = [];

        // 只返回该平台已配置的限红组
        $list = PlatformLimitGroup::query()
            ->where('status', 1)
            ->whereHas('configs', function ($query) use ($platformId) {
                $query->where('platform_id', $platformId)
                    ->where('status', 1)
                    ->whereNull('deleted_at');
            })
            ->orderBy('sort', 'asc')
            ->get();

        foreach ($list as $item) {
            $data[$item->id] = "{$item->name} ({$item->code})";
        }

        return $data;
    }

    /**
     * 自动交班配置表单（渠道查看店家配置）
     * @auth true
     * @group channel
     */
    public function autoShiftConfigForm()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        // 获取自动交班配置（转换为数组，避免 ExAdmin 使用默认 RESTful 逻辑）
        $config = \addons\webman\model\StoreAutoShiftConfig::query()
            ->where('department_id', $store->department_id)
            ->where('bind_admin_user_id', $storeId)
            ->first();

        // 准备表单初始值
        $formData = [];
        if ($config) {
            $formData = [
                'is_enabled' => $config->is_enabled,
                'shift_time_1' => $config->shift_time_1,
                'shift_time_2' => $config->shift_time_2,
                'shift_time_3' => $config->shift_time_3,
            ];
        } else {
            $formData = [
                'is_enabled' => 0,
                'shift_time_1' => '08:00:00',
                'shift_time_2' => '16:00:00',
                'shift_time_3' => '00:00:00',
            ];
        }

        return Form::create($formData, function (Form $form) use ($store, $storeId, $config, $formData) {
            $form->title('自动交班配置 - ' . ($store->nickname ?: $store->username));

            // 显式设置提交URL
            $form->url(admin_url([
                'addons-webman-controller-StoreMachineController',
                'saveAutoShiftConfig'
            ]));

            // 显示执行统计
            if ($config && $config->id && $config->is_enabled) {
                $service = new \app\service\store\AutoShiftService();
                $stats = $service->getExecutionStats($store->department_id, $storeId, 7);

                $form->divider()->content(admin_trans('shift_handover.auto.stats_title'));

                $form->row(function (Form $form) use ($stats) {
                    // 总执行次数
                    $form->column(function (Form $form) use ($stats) {
                        $form->push(Html::div()->content(
                            Html::create()->content([
                                Html::div()->content(admin_trans('shift_handover.auto.stats_total'))
                                    ->style(['color' => 'rgba(0,0,0,0.45)', 'font-size' => '14px', 'margin-bottom' => '4px']),
                                Html::div()->content(($stats['total'] ?? 0) . ' ' . admin_trans('shift_handover.auto.stats_times'))
                                    ->style(['font-size' => '24px', 'font-weight' => '500'])
                            ])
                        )->style(['text-align' => 'center', 'padding' => '16px', 'border' => '1px solid #f0f0f0', 'border-radius' => '4px']));
                    })->span(6);

                    // 成功次数
                    $form->column(function (Form $form) use ($stats) {
                        $form->push(Html::div()->content(
                            Html::create()->content([
                                Html::div()->content(admin_trans('shift_handover.auto.stats_success'))
                                    ->style(['color' => 'rgba(0,0,0,0.45)', 'font-size' => '14px', 'margin-bottom' => '4px']),
                                Html::div()->content(($stats['success'] ?? 0) . ' ' . admin_trans('shift_handover.auto.stats_times'))
                                    ->style(['font-size' => '24px', 'font-weight' => '500', 'color' => '#3f8600'])
                            ])
                        )->style(['text-align' => 'center', 'padding' => '16px', 'border' => '1px solid #f0f0f0', 'border-radius' => '4px']));
                    })->span(6);

                    // 失败次数
                    $form->column(function (Form $form) use ($stats) {
                        $form->push(Html::div()->content(
                            Html::create()->content([
                                Html::div()->content(admin_trans('shift_handover.auto.stats_failed'))
                                    ->style(['color' => 'rgba(0,0,0,0.45)', 'font-size' => '14px', 'margin-bottom' => '4px']),
                                Html::div()->content(($stats['failed'] ?? 0) . ' ' . admin_trans('shift_handover.auto.stats_times'))
                                    ->style(['font-size' => '24px', 'font-weight' => '500', 'color' => '#cf1322'])
                            ])
                        )->style(['text-align' => 'center', 'padding' => '16px', 'border' => '1px solid #f0f0f0', 'border-radius' => '4px']));
                    })->span(6);

                    // 成功率
                    $form->column(function (Form $form) use ($stats) {
                        $form->push(Html::div()->content(
                            Html::create()->content([
                                Html::div()->content(admin_trans('shift_handover.auto.stats_success_rate'))
                                    ->style(['color' => 'rgba(0,0,0,0.45)', 'font-size' => '14px', 'margin-bottom' => '4px']),
                                Html::div()->content(($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 2) : 0) . '%')
                                    ->style(['font-size' => '24px', 'font-weight' => '500'])
                            ])
                        )->style(['text-align' => 'center', 'padding' => '16px', 'border' => '1px solid #f0f0f0', 'border-radius' => '4px']));
                    })->span(6);
                })->gutter(16);
            }

            // 基础配置
            $form->push(Html::div()->style(['margin-top' => '24px']));
            $form->divider()->content(admin_trans('shift_handover.auto.config_title'));

            $form->switch('is_enabled', admin_trans('shift_handover.auto.enable'))
                ->checkedValue(1)
                ->unCheckedValue(0)
                ->value($formData['is_enabled'] ?? 0)
                ->help(admin_trans('shift_handover.auto.enable_help'));

            // 三个时间字段（横向排列）
            $form->row(function (Form $form) use ($formData) {
                $form->time('shift_time_1', admin_trans('shift_handover.auto.shift_time_1'))
                    ->value($formData['shift_time_1'] ?? '08:00:00')
                    ->help(admin_trans('shift_handover.auto.shift_time_1_help'))
                    ->span(8);

                $form->time('shift_time_2', admin_trans('shift_handover.auto.shift_time_2'))
                    ->value($formData['shift_time_2'] ?? '16:00:00')
                    ->help(admin_trans('shift_handover.auto.shift_time_2_help'))
                    ->span(8);

                $form->time('shift_time_3', admin_trans('shift_handover.auto.shift_time_3'))
                    ->value($formData['shift_time_3'] ?? '00:00:00')
                    ->help(admin_trans('shift_handover.auto.shift_time_3_help'))
                    ->span(8);
            });

            // 显示下次交班时间
            $form->push(Html::div()->style(['margin-top' => '24px']));
            $form->divider()->content(admin_trans('shift_handover.auto.exec_info'));
            if ($config && $config->id && $config->next_shift_time) {
                $form->push(Html::div()->content(
                    Html::create()->content([
                        Html::create()->tag('strong')->content(admin_trans('shift_handover.auto.next_shift_time') . '：'),
                        $config->next_shift_time
                    ])
                )->style(['padding' => '16px', 'background' => '#f0f9ff', 'border' => '1px solid #bae7ff', 'border-radius' => '4px', 'margin-bottom' => '24px']));
            } else {
                $form->push(Html::div()->content(admin_trans('shift_handover.auto.config_save_hint'))
                    ->style(['padding' => '16px', 'background' => '#f5f5f5', 'border' => '1px solid #d9d9d9', 'border-radius' => '4px', 'color' => '#999', 'margin-bottom' => '24px']));
            }

            $form->hidden('store_id')->value($storeId);
        });
    }

    /**
     * 保存自动交班配置
     * @auth true
     * @group channel
     */
    public function saveAutoShiftConfig()
    {
        $request = request();
        $postData = $request->post();
        $data = $postData['data'] ?? $postData;

        $storeId = $data['store_id'] ?? null;

        if (!$storeId) {
            return message_error('店家ID不能为空');
        }

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return message_error('店家不存在');
        }

        $configData = [
            'department_id' => $store->department_id,
            'bind_admin_user_id' => $storeId,
            'is_enabled' => isset($data['is_enabled']) ? (int)$data['is_enabled'] : 0,
            'shift_time_1' => $data['shift_time_1'] ?? '08:00:00',
            'shift_time_2' => $data['shift_time_2'] ?? '16:00:00',
            'shift_time_3' => $data['shift_time_3'] ?? '00:00:00',
            'auto_settlement' => 1,
        ];

        $service = new \app\service\store\AutoShiftService();
        $result = $service->saveConfig($configData);

        if ($result['code'] === 0) {
            return message_success($result['msg'] ?? admin_trans('shift_handover.auto.save_success'));
        } else {
            return message_error($result['msg'] ?? admin_trans('shift_handover.auto.save_failed'));
        }
    }

    /**
     * 店家系统配置列表（渠道查看店家配置）
     * @auth true
     * @group channel
     */
    public function storeSettingList()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        return Grid::create(new StoreSetting(), function (Grid $grid) use ($store, $storeId) {
            $grid->title('系统配置 - ' . ($store->nickname ?: $store->username));
            $grid->autoHeight();
            $grid->bordered(true);

            // 设置更新 API 路径（Drawer 中的 Grid 需要显式指定）
            $grid->api(admin_url([
                'addons-webman-controller-StoreMachineController',
                'updateStoreSetting'
            ]));

            // 查询该店家的专属配置（关闭数据权限，避免影响更新）
            $grid->model()
                ->offDataAuth()
                ->where('department_id', $store->department_id)
                ->where('admin_user_id', $storeId);

            // 功能列
            $grid->column('feature', admin_trans('store_setting.fields.feature'))
                ->display(function ($value, StoreSetting $data) {
                    return admin_trans('store_setting.fields.' . $data->feature);
                })->align('center');

            // 配置列（复用 StoreSettingController 的逻辑）
            $grid->column('setting', admin_trans('store_setting.fields.setting'))
                // 首页提醒消息
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'home_notice';
                })->editable(
                    \ExAdmin\ui\component\grid\grid\Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:500' => admin_trans('store_setting.home_notice_max_len')])
                )->display(function ($value, StoreSetting $data) {
                    return \Illuminate\Support\Str::of($data->content)->limit(50, ' (...)');
                })->width('30%')->align('center')
                // 店家跑马灯
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'store_marquee';
                })->editable(
                    \ExAdmin\ui\component\grid\grid\Editable::textarea('content')
                        ->showCount()
                        ->rows(6)
                        ->rule(['max:500' => admin_trans('store_setting.store_marquee_max_len')])
                )->display(function ($value, StoreSetting $data) {
                    return \Illuminate\Support\Str::of($data->content)->limit(50, ' (...)');
                })->width('30%')->align('center')
                // 订单过期时间
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'order_expiration';
                })->editable(
                    (new \ExAdmin\ui\component\grid\grid\Editable)->number('num')
                        ->rule([
                            'integer' => admin_trans('store_setting.validation.integer'),
                            'max:180' => admin_trans('store_setting.validation.max', null, ['{max}' => 180]),
                            'min:5' => admin_trans('store_setting.validation.min', null, ['{min}' => 5]),
                        ])->addonAfter(admin_trans('store_setting.minutes'))
                )->display(function ($val, StoreSetting $data) {
                    if (!empty($data->num)) {
                        return $data->num . ' ' . admin_trans('store_setting.minutes');
                    }
                    return '';
                })->align('center')
                // 营业时间
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'business_hours';
                })->display(function ($value, StoreSetting $data) {
                    $time = '';
                    !empty($data->date_start) && $time .= $data->date_start;
                    !empty($data->date_end) && $time .= ' ~ ' . $data->date_end;
                    return Tag::create($time)->color('blue')->modal([$this, 'editBusinessHours'], ['data' => $data]);
                })->align('center')
                // 是否开启实体机台
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'enable_physical_machine';
                })->editable(
                    (new \ExAdmin\ui\component\grid\grid\Editable)->select('num')
                        ->options([
                            1 => admin_trans('store_setting.enable'),
                            0 => admin_trans('store_setting.disable'),
                        ])
                )->display(function ($val, StoreSetting $data) {
                    if ($data->num == 1) {
                        return Tag::create(admin_trans('store_setting.enable'))->color('green');
                    } else {
                        return Tag::create(admin_trans('store_setting.disable'))->color('red');
                    }
                })->align('center')
                // 是否开启真人百家
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'enable_live_baccarat';
                })->editable(
                    (new \ExAdmin\ui\component\grid\grid\Editable)->select('num')
                        ->options([
                            1 => admin_trans('store_setting.enable'),
                            0 => admin_trans('store_setting.disable'),
                        ])
                )->display(function ($val, StoreSetting $data) {
                    if ($data->num == 1) {
                        return Tag::create(admin_trans('store_setting.enable'))->color('green');
                    } else {
                        return Tag::create(admin_trans('store_setting.disable'))->color('red');
                    }
                })->align('center')
                // 爆机金额
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'machine_crash_amount';
                })->editable(
                    (new \ExAdmin\ui\component\grid\grid\Editable)->number('num')
                        ->rule([
                            'numeric' => admin_trans('store_setting.validation.numeric'),
                            'min:0' => admin_trans('store_setting.validation.min', null, ['{min}' => 0]),
                        ])
                        ->precision(2)
                )->display(function ($val, StoreSetting $data) {
                    if (!empty($data->num)) {
                        return number_format(floatval($data->num), 2);
                    }
                    return '0.00';
                })->align('center');

            // 状态列
            $grid->column('status', admin_trans('store_setting.fields.status'))
                ->switch()->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
        });
    }

    /**
     * 更新店家系统配置
     * @auth true
     * @group channel
     */
    public function updateStoreSetting()
    {
        $request = request();
        $id = $request->input('id');
        $field = $request->input('field');
        $value = $request->input('value');

        \support\Log::info('[临时] 收到系统配置更新请求', [
            'id' => $id,
            'field' => $field,
            'value' => $value,
            'all_input' => $request->all(),
        ]);

        if (!$id) {
            return message_error('配置ID不能为空');
        }

        // 查询配置（关闭数据权限）
        $setting = StoreSetting::query()
            ->offDataAuth()
            ->find($id);

        if (!$setting) {
            return message_error('配置不存在');
        }

        \support\Log::info('[临时] 找到配置记录', [
            'setting' => $setting->toArray(),
        ]);

        // 更新字段
        if ($field && $value !== null) {
            $setting->$field = $value;
        } else {
            // 批量更新
            foreach ($request->except(['id', '_method', '_token']) as $key => $val) {
                if (in_array($key, $setting->getFillable())) {
                    $setting->$key = $val;
                }
            }
        }

        \support\Log::info('[临时] 准备保存', [
            'dirty' => $setting->getDirty(),
        ]);

        $saved = $setting->save();

        \support\Log::info('[临时] 保存结果', [
            'saved' => $saved,
            'after_save' => $setting->fresh()->toArray(),
        ]);

        if ($saved) {
            return message_success('保存成功');
        } else {
            return message_error('保存失败');
        }
    }

    /**
     * 编辑营业时间（用于店家系统配置）
     * @auth true
     * @group channel
     */
    public function editBusinessHours(StoreSetting $data): Form
    {
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('store_setting.edit_business_hours'));
            $form->timeRange('date_start', 'date_end', admin_trans('store_setting.time_range'))
                ->value([$data->date_start, $data->date_end])
                ->required();
        });
    }

    /**
     * 店家开分配置列表（渠道查看店家配置）
     * @auth true
     * @group channel
     */
    public function openScoreSettingList()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        $model = plugin()->webman->config('database.open_score_setting_model');

        return Grid::create(new $model(), function (Grid $grid) use ($store, $storeId, $model) {
            $grid->title('开分配置 - ' . ($store->nickname ?: $store->username));
            $grid->model()->where('admin_user_id', $storeId)->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('scores', admin_trans('open_score_setting.fields.scores'))
                ->display(function ($val, $data) {
                    $scores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $key = 'score_' . $i;
                        if ($data->$key > 0) {
                            $scores[] = Tag::create($data->$key)->color('cyan');
                        }
                    }
                    return Html::create()->content($scores)->style([
                        'display' => 'flex',
                        'gap' => '5px',
                        'flex-wrap' => 'wrap'
                    ]);
                })->align('center')->width('30%');

            $grid->column('default_scores', admin_trans('open_score_setting.fields.default_scores'))
                ->display(function ($val) {
                    if ($val > 0) {
                        return Tag::create($val)->color('orange');
                    }
                    return Tag::create(admin_trans('open_score_setting.not_set'))->color('default');
                })->align('center');

            $grid->column('created_at', admin_trans('open_score_setting.fields.created_at'))->align('center');
            $grid->column('updated_at', admin_trans('open_score_setting.fields.updated_at'))->align('center');

            $grid->setForm()->drawer($this->openScoreSettingForm($storeId));
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
            })->align('center');
        });
    }

    /**
     * 店家开分配置表单（用于渠道管理）
     * @auth true
     * @group channel
     * @param int $storeId
     * @return Form
     */
    public function openScoreSettingForm(int $storeId): Form
    {
        $model = plugin()->webman->config('database.open_score_setting_model');

        return Form::create(new $model(), function (Form $form) use ($storeId, $model) {
            $form->title(admin_trans('open_score_setting.title'));

            $form->number('default_scores', admin_trans('open_score_setting.fields.default_scores'))
                ->default(0)
                ->min(0)
                ->max(1000000)
                ->style(['width' => '100%'])
                ->help(admin_trans('open_score_setting.help.default_scores'));

            $form->divider()->content(admin_trans('open_score_setting.fields.default_scores'));

            // 6个开分选项
            for ($i = 1; $i <= 6; $i++) {
                $form->number('score_' . $i, admin_trans('open_score_setting.fields.score_' . $i))
                    ->default($this->getDefaultScore($i))
                    ->min(0)
                    ->max(1000000)
                    ->style(['width' => '100%'])
                    ->help(admin_trans('open_score_setting.help.score'));
            }

            $form->layout('vertical');

            // 保存时验证
            $form->saving(function (Form $form) use ($storeId, $model) {
                $form->input('admin_user_id', $storeId);

                // 检查是否已存在配置（编辑时排除当前记录）
                $exists = $model::query()->where('admin_user_id', $storeId);

                if ($form->isEdit()) {
                    $exists->where('id', '!=', $form->driver()->get('id'));
                }

                if ($exists->exists()) {
                    return message_error(admin_trans('open_score_setting.player_exists'));
                }

                // 验证至少配置一个开分选项
                $hasScore = false;
                for ($i = 1; $i <= 6; $i++) {
                    $score = $form->input('score_' . $i);
                    if (!empty($score) && $score > 0) {
                        $hasScore = true;
                        break;
                    }
                }

                if (!$hasScore) {
                    return message_error(admin_trans('open_score_setting.at_least_one_score'));
                }
            });
        });
    }

    /**
     * 获取默认开分值
     * @param int $index
     * @return int
     */
    private function getDefaultScore(int $index): int
    {
        $defaults = [100, 500, 1000, 5000, 10000, 20000];
        return $defaults[$index - 1] ?? 0;
    }
}
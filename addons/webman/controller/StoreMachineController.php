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
use ExAdmin\ui\component\grid\grid\Editable;
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

            // 分润比例（可编辑）
            $grid->column('agent_commission', admin_trans('store_machine.fields.agent_commission'))->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create(admin_trans('store_machine.status.not_set'))->color('default');
                }
                return Tag::create($value . '%')->color('orange');
            })->editable(
                (new Editable)->number('agent_commission')
                    ->min(0)
                    ->max(100)
                    ->precision(2)
            )->width(100)->align('center');

            $grid->column('channel_commission', admin_trans('store_machine.fields.channel_commission'))->display(function ($value) {
                if (is_null($value) || $value === '') {
                    return Tag::create(admin_trans('store_machine.status.not_set'))->color('default');
                }
                return Tag::create($value . '%')->color('blue');
            })->editable(
                (new Editable)->number('channel_commission')
                    ->min(0)
                    ->max(100)
                    ->precision(2)
            )->width(100)->align('center');

            // 洗分配置（从配置表读取）
            $grid->column('wash_point_config', admin_trans('store_machine.fields.wash_point_config'))
                ->display(function ($value, $data) {
                    // 从洗分配置表获取
                    $washSetting = \addons\webman\model\WashPointSetting::query()
                        ->where('admin_user_id', $data['id'])
                        ->first();

                    if ($washSetting) {
                        $effectiveWashPoint = $washSetting->getEffectiveWashPoint();
                        return Tag::create(number_format($effectiveWashPoint, 2))->color('green');
                    }

                    // 兜底：显示admin_users表中的旧值（如果存在）
                    if ($value > 0) {
                        return Tag::create(number_format($value, 2))->color('orange');
                    }

                    return Tag::create('未配置')->color('default');
                })
                ->width(120)->align('center');

            // 体验券打码判定开关
            $grid->column('experience_bet_check_enabled', admin_trans('store_machine.fields.experience_bet_check_enabled'))
                ->display(function ($value) {
                    return $value
                        ? Tag::create(admin_trans('store_machine.status.enabled'))->color('green')
                        : Tag::create(admin_trans('store_machine.status.disabled'))->color('red');
                })->width(120)->align('center');

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

                $dropdown = $actions->dropdown();

                // 限红组配置
                $dropdown->prepend(admin_trans('store_machine.actions.limit_group'), 'fas fa-shield-alt')
                    ->modal([$this, 'limitGroupForm'], ['store_id' => $data['id']])
                    ->width('60%');

                // 自动交班配置
                $dropdown->prepend(admin_trans('store_machine.actions.auto_shift_config'), 'fas fa-clock')
                    ->modal([$this, 'autoShiftConfigForm'], ['store_id' => $data['id']])
                    ->width('60%');

                // 系统配置
                $dropdown->prepend(admin_trans('store_machine.actions.system_setting'), 'fas fa-cog')
                    ->modal([$this, 'storeSettingList'], ['store_id' => $data['id']])
                    ->width('90%');

                // 开分配置
                $dropdown->prepend(admin_trans('store_machine.actions.open_score_setting'), 'fas fa-arrow-up')
                    ->modal([$this, 'openScoreSettingList'], ['store_id' => $data['id']])
                    ->width('90%');

                // 洗分配置
                $dropdown->prepend(admin_trans('store_machine.actions.wash_point_setting'), 'fas fa-arrow-down')
                    ->modal([$this, 'washPointSettingList'], ['store_id' => $data['id']])
                    ->width('90%');

                // 活动配置
                $dropdown->prepend(admin_trans('store_machine.actions.activity_config'), 'fas fa-gift')
                    ->modal([$this, 'activityConfigList'], ['store_id' => $data['id']])
                    ->width('90%');
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
            $grid->hideAdd(); // 屏蔽添加按钮
            $grid->setForm()->drawer($this->createStoreMachineForm());
            $grid->expandFilter();

            // 处理可编辑列的保存
            $grid->updateing(function ($ids, $data) use ($currentDepartmentId) {
                try {
                    if (isset($ids[0])) {
                        // 查找店家账号（需要验证权限：通过代理的 department_id）
                        $storeUser = AdminUser::query()
                            ->join('admin_users as parent_admin', 'admin_users.parent_admin_id', '=', 'parent_admin.id')
                            ->where('admin_users.id', $ids[0])
                            ->where('admin_users.type', AdminUser::TYPE_STORE)
                            ->where('parent_admin.department_id', $currentDepartmentId)
                            ->select('admin_users.*')
                            ->first();

                        if (!$storeUser) {
                            return message_error(admin_trans('admin.not_found'));
                        }

                        // 更新可编辑字段（wash_point_config已改为使用配置表）
                        $updateableFields = ['agent_commission', 'channel_commission'];
                        $updated = false;
                        foreach ($updateableFields as $field) {
                            if (array_key_exists($field, $data)) {
                                $storeUser->$field = $data[$field];
                                $updated = true;
                            }
                        }
                        if ($updated) {
                            $storeUser->save();
                            return message_success(admin_trans('form.save_success'));
                        }
                    }
                    return message_error(admin_trans('form.save_fail'));
                } catch (\Exception $e) {
                    return message_error(admin_trans('form.save_fail') . ': ' . $e->getMessage());
                }
            });
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

            // 5. 创建默认开分配置
            $openScoreSetting = new \addons\webman\model\OpenScoreSetting();
            $openScoreSetting->admin_user_id = $adminUser->id;
            $openScoreSetting->score_1 = 500;
            $openScoreSetting->score_2 = 1000;
            $openScoreSetting->score_3 = 3000;
            $openScoreSetting->score_4 = 5000;
            $openScoreSetting->score_5 = 10000;
            $openScoreSetting->score_6 = 20000;
            $openScoreSetting->default_scores = 500;
            $openScoreSetting->save();

            // 6. 创建默认洗分配置
            $washPointSetting = new \addons\webman\model\WashPointSetting();
            $washPointSetting->admin_user_id = $adminUser->id;
            $washPointSetting->wash_1 = 1000;
            $washPointSetting->wash_2 = 3000;
            $washPointSetting->wash_3 = 5000;
            $washPointSetting->wash_4 = 10000;
            $washPointSetting->wash_5 = 30000;
            $washPointSetting->wash_6 = 50000;
            $washPointSetting->default_wash_point = 1000;
            $washPointSetting->save();

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
        // 处理 PUT 请求（editable 更新）
        if (request()->method() === 'PUT') {
            return $this->updateStoreSetting();
        }

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

            // 配置列
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
                })->display(function ($val, StoreSetting $data) {
                    if ($data->status == 1) {
                        return Tag::create(admin_trans('store_setting.enable'))->color('green');
                    } else {
                        return Tag::create(admin_trans('store_setting.disable'))->color('red');
                    }
                })->align('center')
                // 是否开启真人百家
                ->if(function ($value, StoreSetting $data) {
                    return $data->feature === 'enable_live_baccarat';
                })->display(function ($val, StoreSetting $data) {
                    if ($data->status == 1) {
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
                ->switch()
                
                ->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed(); // 去掉回收站
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

        // 解析请求数据
        $body = $request->rawBody();
        $requestData = json_decode($body, true);

        \support\Log::info('[临时] 收到系统配置更新请求', [
            'method' => $request->method(),
            'parsed_data' => $requestData,
        ]);

        // ExAdmin 格式：{"ex_admin_action":"update","ids":[13],"data":{"status":0}}
        $ids = $requestData['ids'] ?? [];
        $updateData = $requestData['data'] ?? [];

        if (empty($ids)) {
            return json(['status' => false, 'msg' => '配置ID不能为空']);
        }

        $id = $ids[0]; // 取第一个ID

        // 查询配置（关闭数据权限）
        $setting = StoreSetting::query()->offDataAuth()->find($id);

        if (!$setting) {
            return json(['status' => false, 'msg' => '配置不存在']);
        }

        \support\Log::info('[临时] 找到配置记录', [
            'setting_id' => $setting->id,
            'feature' => $setting->feature,
            'update_data' => $updateData,
        ]);

        // 批量更新字段
        foreach ($updateData as $field => $value) {
            $setting->$field = $value;
        }

        \support\Log::info('[临时] 准备保存', [
            'dirty' => $setting->getDirty(),
        ]);

        try {
            if ($setting->isDirty()) {
                $saved = $setting->save();
                \support\Log::info('[临时] 保存完成', [
                    'saved' => $saved,
                    'changes' => $setting->getChanges(),
                ]);

                return json(['status' => true, 'msg' => '保存成功']);
            } else {
                \support\Log::info('[临时] 无需保存（数据未变化）');
                return json(['status' => true, 'msg' => '数据未变化']);
            }
        } catch (\Exception $e) {
            \support\Log::error('[临时] 保存异常', [
                'error' => $e->getMessage(),
            ]);
            return json(['status' => false, 'msg' => '保存失败：' . $e->getMessage()]);
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

            $grid->hideCreate(); // 去掉添加
            $grid->hideDelete(); // 去掉删除
            $grid->hideTrashed(); // 去掉回收站
            $grid->setForm()->drawer($this->openScoreSettingForm($storeId));
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
                $actions->hideDel(); // 去掉删除按钮
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
                ->step(1)  // ⭐ 只允许整数
                ->style(['width' => '100%'])
                ->help(admin_trans('open_score_setting.help.default_scores'));

            $form->divider()->content(admin_trans('open_score_setting.fields.default_scores'));

            // 6个开分选项
            for ($i = 1; $i <= 6; $i++) {
                $form->number('score_' . $i, admin_trans('open_score_setting.fields.score_' . $i))
                    ->default($this->getDefaultScore($i))
                    ->min(0)
                    ->max(1000000)
                    ->step(1)  // ⭐ 只允许整数
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

                // ⭐ 验证所有开分配置必须是正整数
                $defaultScores = $form->input('default_scores');
                if ($defaultScores !== null && $defaultScores !== '' && $defaultScores != 0) {
                    if ($defaultScores != floor($defaultScores) || $defaultScores < 0) {
                        return message_error(admin_trans('open_score_setting.error.must_be_positive_integer', null, ['field' => admin_trans('open_score_setting.fields.default_scores')]));
                    }
                }

                for ($i = 1; $i <= 6; $i++) {
                    $score = $form->input('score_' . $i);
                    if ($score !== null && $score !== '' && $score != 0) {
                        if ($score != floor($score) || $score < 0) {
                            return message_error(admin_trans('open_score_setting.error.must_be_positive_integer', null, ['field' => admin_trans('open_score_setting.fields.score_' . $i)]));
                        }
                    }
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
        $defaults = [500, 1000, 3000, 5000, 10000, 20000];
        return $defaults[$index - 1] ?? 0;
    }

    /**
     * 店家洗分配置列表
     * @auth true
     * @group channel
     */
    public function washPointSettingList()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        return Grid::create(new \addons\webman\model\WashPointSetting(), function (Grid $grid) use ($store, $storeId) {
            $grid->title('洗分配置 - ' . ($store->nickname ?: $store->username));
            $grid->model()->where('admin_user_id', $storeId)->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('wash_points', '洗分选项')
                ->display(function ($val, $data) {
                    $washPoints = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $key = 'wash_' . $i;
                        if ($data->$key > 0) {
                            $washPoints[] = Tag::create(number_format($data->$key, 2))->color('cyan');
                        }
                    }
                    return Html::create()->content($washPoints)->style([
                        'display' => 'flex',
                        'gap' => '5px',
                        'flex-wrap' => 'wrap'
                    ]);
                })->align('center')->width('30%');

            $grid->column('default_wash_point', '默认洗分基数')
                ->display(function ($val) {
                    if ($val > 0) {
                        return Tag::create(number_format($val, 2))->color('orange');
                    }
                    return Tag::create('未设置')->color('default');
                })->align('center');

            $grid->column('created_at', '创建时间')->align('center');
            $grid->column('updated_at', '更新时间')->align('center');

            $grid->hideCreate(); // 去掉添加
            $grid->hideDelete(); // 去掉删除
            $grid->hideTrashed(); // 去掉回收站
            $grid->setForm()->drawer($this->washPointSettingForm($storeId));
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
                $actions->hideDel(); // 去掉删除按钮
            })->align('center');
        });
    }

    /**
     * 店家洗分配置表单
     * @auth true
     * @group channel
     * @param int $storeId
     * @return Form
     */
    public function washPointSettingForm(int $storeId): Form
    {
        return Form::create(new \addons\webman\model\WashPointSetting(), function (Form $form) use ($storeId) {
            $form->title('洗分配置');

            $form->number('default_wash_point', '默认洗分基数')
                ->default(100)
                ->min(0)
                ->max(999999)
                ->step(1)  // ⭐ 只允许整数
                ->style(['width' => '100%'])
                ->help('默认的洗分基数（整数），玩家洗分时按此基数计算。特殊值：0 表示只洗整数部分，小数保留');

            $form->divider()->content('洗分选项（可选）');

            // 6个洗分选项
            for ($i = 1; $i <= 6; $i++) {
                $form->number('wash_' . $i, '洗分选项' . $i)
                    ->default($this->getDefaultWashPoint($i))
                    ->min(0)
                    ->max(999999)
                    ->step(1)  // ⭐ 只允许整数
                    ->style(['width' => '100%'])
                    ->help('玩家可选择的洗分基数（整数），留空表示不启用该选项');
            }

            $form->layout('vertical');

            // 保存时验证
            $form->saving(function (Form $form) use ($storeId) {
                $form->input('admin_user_id', $storeId);

                // 检查是否已存在配置（编辑时排除当前记录）
                $exists = \addons\webman\model\WashPointSetting::query()->where('admin_user_id', $storeId);

                if ($form->isEdit()) {
                    $exists->where('id', '!=', $form->driver()->get('id'));
                }

                if ($exists->exists()) {
                    return message_error('该店家已存在洗分配置，请直接编辑');
                }

                // ⭐ 验证所有洗分配置必须是非负整数（允许0）
                $defaultWashPoint = $form->input('default_wash_point');
                if ($defaultWashPoint !== null && $defaultWashPoint !== '') {
                    if ($defaultWashPoint != floor($defaultWashPoint) || $defaultWashPoint < 0) {
                        return message_error(admin_trans('wash_point_setting.error.must_be_non_negative_integer', null, ['field' => admin_trans('wash_point_setting.fields.default_wash_point')]));
                    }
                }

                for ($i = 1; $i <= 6; $i++) {
                    $wash = $form->input('wash_' . $i);
                    if ($wash !== null && $wash !== '' && $wash != 0) {
                        if ($wash != floor($wash) || $wash < 0) {
                            return message_error(admin_trans('wash_point_setting.error.must_be_non_negative_integer', null, ['field' => admin_trans('wash_point_setting.fields.wash_' . $i)]));
                        }
                    }
                }

                // 验证至少配置默认洗分基数或一个洗分选项
                $hasWashOption = false;

                for ($i = 1; $i <= 6; $i++) {
                    $wash = $form->input('wash_' . $i);
                    if (!empty($wash) && $wash > 0) {
                        $hasWashOption = true;
                        break;
                    }
                }

                // 允许 default_wash_point = 0（洗整数部分），或 > 0，或至少有一个洗分选项
                if ($defaultWashPoint === null || $defaultWashPoint === '') {
                    if (!$hasWashOption) {
                        return message_error('请至少配置默认洗分基数或一个洗分选项');
                    }
                }
            });
        });
    }

    /**
     * 获取默认洗分基数
     * @param int $index
     * @return float
     */
    private function getDefaultWashPoint(int $index): float
    {
        $defaults = [1000, 3000, 5000, 10000, 30000, 50000];
        return $defaults[$index - 1] ?? 0;
    }

    // =========================================================================
    // 活动配置（福利券/体验券）
    // =========================================================================

    /**
     * 店家活动配置列表
     * @auth true
     * @group channel
     */
    public function activityConfigList()
    {
        $storeId = request()->input('store_id');

        // 获取店家信息
        $store = AdminUser::find($storeId);
        if (!$store || $store->type != AdminUser::TYPE_STORE) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">店家不存在</font>'));
            });
        }

        // 检查是否已有配置
        $existingConfig = \addons\webman\model\StoreActivityConfig::query()
            ->where('admin_user_id', $storeId)
            ->whereNull('deleted_at')
            ->first();

        return Grid::create(new \addons\webman\model\StoreActivityConfig(), function (Grid $grid) use ($store, $storeId, $existingConfig) {
            $grid->title(admin_trans('store_machine.activity_config.list_title') . ' - ' . ($store->nickname ?: $store->username));
            $grid->model()->where('admin_user_id', $storeId)->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);

            // 基础字段
            $grid->column('id', 'ID')->width(60)->align('center');

            // 时间范围
            $grid->column('time_range', admin_trans('store_machine.activity_config.fields.start_time'))
                ->display(function ($val, $data) {
                    $start = $data->start_time ?: '-';
                    $end = $data->end_time ?: '-';
                    return "$start ~ $end";
                })->width(200)->align('center');

            // 状态
            $grid->column('status', admin_trans('store_machine.activity_config.fields.status'))
                ->switch()
                ->align('center');

            // 体验券状态
            $grid->column('experience_enabled', admin_trans('store_machine.activity_config.label.experience_enabled'))
                ->display(function ($val) {
                    return $val
                        ? Tag::create(admin_trans('store_machine.activity_config.status.1'))->color('green')
                        : Tag::create(admin_trans('store_machine.activity_config.status.0'))->color('red');
                })->align('center');

            // 福利券状态
            $grid->column('welfare_enabled', admin_trans('store_machine.activity_config.label.welfare_enabled'))
                ->display(function ($val) {
                    return $val
                        ? Tag::create(admin_trans('store_machine.activity_config.status.1'))->color('green')
                        : Tag::create(admin_trans('store_machine.activity_config.status.0'))->color('red');
                })->align('center');

            // 创建时间
            $grid->column('created_at', admin_trans('store_machine.activity_config.fields.created_at'))->width(160)->align('center');

            // 使用 drawer 方式处理表单（避免嵌套 modal）
            $grid->setForm()->drawer($this->activityConfigForm($storeId))->width('50%');

            // 操作
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            })->align('center');

            // 工具栏
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();

            // 如果已有配置，隐藏新增按钮（一个店铺只能有一个配置）
            if ($existingConfig) {
                $grid->hideCreate();
            }
        });
    }

    /**
     * 活动配置表单（新增/编辑）
     * @auth true
     * @group channel
     * @param int $storeId
     * @return Form
     */
    public function activityConfigForm(int $storeId = 0): Form
    {
        $model = \addons\webman\model\StoreActivityConfig::class;

        return Form::create(new $model(), function (Form $form) use ($storeId) {
            $form->title(admin_trans('store_machine.activity_config.edit_title'));

            $form->hidden('admin_user_id')->value($storeId);

            // 获取店家的 department_id
            $store = AdminUser::find($storeId);
            if ($store) {
                $form->hidden('department_id')->value($store->department_id);
            }

            // ========== 基本信息 ==========
            $form->divider()->content(admin_trans('store_machine.activity_config.section.basic'));

            $form->row(function (Form $form) {
                $form->dateTime('start_time', admin_trans('store_machine.activity_config.label.start_time'))
                    ->help(admin_trans('store_machine.activity_config.help.start_time'))
                    ->span(12);

                $form->dateTime('end_time', admin_trans('store_machine.activity_config.label.end_time'))
                    ->help(admin_trans('store_machine.activity_config.help.end_time'))
                    ->span(12);
            });

            $form->row(function (Form $form) {
                $form->switch('status', admin_trans('store_machine.activity_config.fields.status'))
                    ->checkedValue(1)
                    ->unCheckedValue(0)
                    ->value(1)
                    ->span(12);

                $form->dateTime('activity_end_time', admin_trans('store_machine.activity_config.label.activity_end_time'))
                    ->help(admin_trans('store_machine.activity_config.help.activity_end_time'))
                    ->span(12);
            });

            // ========== 体验券配置 ==========
            $form->divider()->content(admin_trans('store_machine.activity_config.section.experience'));

            $form->switch('experience_enabled', admin_trans('store_machine.activity_config.label.experience_enabled'))
                ->checkedValue(1)
                ->unCheckedValue(0)
                ->value(1);

            $form->row(function (Form $form) {
                $form->dateTime('experience_register_after', admin_trans('store_machine.activity_config.label.experience_register_after'))
                    ->help(admin_trans('store_machine.activity_config.help.experience_register_after'))
                    ->span(12);

                $form->number('experience_score', admin_trans('store_machine.activity_config.label.experience_score'))
                    ->min(0)
                    ->max(999999)
                    ->value(1000)
                    ->help(admin_trans('store_machine.activity_config.help.experience_score'))
                    ->span(12);
            });

            $form->row(function (Form $form) {
                $form->number('experience_daily_limit', admin_trans('store_machine.activity_config.label.experience_daily_limit'))
                    ->min(0)
                    ->max(999)
                    ->value(1)
                    ->help(admin_trans('store_machine.activity_config.help.experience_daily_limit'))
                    ->span(12);

                $form->number('experience_total_limit', admin_trans('store_machine.activity_config.label.experience_total_limit'))
                    ->min(0)
                    ->max(9999)
                    ->value(6)
                    ->help(admin_trans('store_machine.activity_config.help.experience_total_limit'))
                    ->span(12);
            });

            $form->number('experience_expire_hours', admin_trans('store_machine.activity_config.label.experience_expire_hours'))
                ->min(1)
                ->max(720)
                ->value(24)
                ->help(admin_trans('store_machine.activity_config.help.experience_expire_hours'));

            // ========== 福利券配置 ==========
            $form->divider()->content(admin_trans('store_machine.activity_config.section.welfare'));

            $form->switch('welfare_enabled', admin_trans('store_machine.activity_config.label.welfare_enabled'))
                ->checkedValue(1)
                ->unCheckedValue(0)
                ->value(1);

            $form->row(function (Form $form) {
                $form->number('welfare_daily_limit', admin_trans('store_machine.activity_config.label.welfare_daily_limit'))
                    ->min(0)
                    ->max(999)
                    ->value(1)
                    ->help(admin_trans('store_machine.activity_config.help.welfare_daily_limit'))
                    ->span(12);

                $form->number('welfare_expire_hours', admin_trans('store_machine.activity_config.label.welfare_expire_hours'))
                    ->min(1)
                    ->max(720)
                    ->value(24)
                    ->help(admin_trans('store_machine.activity_config.help.welfare_expire_hours'))
                    ->span(12);
            });

            // 福利券档位规则（使用 hasMany 实现动态表格）
            $form->divider()->content(admin_trans('store_machine.activity_config.label.welfare_rules'));
            $form->help(admin_trans('store_machine.activity_config.help.welfare_rules'));

            $form->hasMany('welfare_rules', admin_trans('store_machine.activity_config.rules.add_rule'), function (Form $form) {
                $form->select('day_type', admin_trans('store_machine.activity_config.rules.day_type'))
                    ->options([
                        'yesterday' => admin_trans('store_machine.activity_config.rules.yesterday'),
                        'today' => admin_trans('store_machine.activity_config.rules.today'),
                    ])
                    ->default('yesterday')
                    ->required();
                $form->number('bet_amount', admin_trans('store_machine.activity_config.rules.bet_amount'))
                    ->min(0)
                    ->max(99999999)
                    ->required();
                $form->number('score', admin_trans('store_machine.activity_config.rules.score'))
                    ->min(0)
                    ->max(999999)
                    ->required();
            });

            // ========== 订单前缀配置 ==========
            $form->divider()->content(admin_trans('store_machine.activity_config.section.order_prefix'));

            $form->row(function (Form $form) {
                $form->text('order_prefix_experience', admin_trans('store_machine.activity_config.label.order_prefix_experience'))
                    ->maxlength(10)
                    ->value('TY')
                    ->span(6);

                $form->text('order_prefix_welfare', admin_trans('store_machine.activity_config.label.order_prefix_welfare'))
                    ->maxlength(10)
                    ->value('FL')
                    ->span(6);

                $form->text('order_prefix_recharge', admin_trans('store_machine.activity_config.label.order_prefix_recharge'))
                    ->maxlength(10)
                    ->value('TK')
                    ->span(6);

                $form->text('order_prefix_withdraw', admin_trans('store_machine.activity_config.label.order_prefix_withdraw'))
                    ->maxlength(10)
                    ->value('TK')
                    ->span(6);
            });

            $form->layout('vertical');

            // 保存时设置 admin_user_id 和验证
            $form->saving(function (Form $form) use ($storeId) {
                $form->input('admin_user_id', $storeId);
                // 设置 department_id
                $store = AdminUser::find($storeId);
                if ($store) {
                    $form->input('department_id', $store->department_id);
                }

                // 新增时检查是否已有配置
                if (!$form->isEdit()) {
                    $exists = \addons\webman\model\StoreActivityConfig::query()
                        ->where('admin_user_id', $storeId)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return message_error(admin_trans('store_machine.activity_config.error.already_exists'));
                    }
                }
            });

            // 保存成功后提示
            $form->saved(function () {
                return message_success(admin_trans('store_machine.activity_config.message.update_success'));
            });
        });
    }

}
<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\StoreSetting;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
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
                    'admin_users.id',
                    'admin_users.username',
                    'admin_users.nickname',
                    'admin_users.avatar',
                    'admin_users.status',
                    'admin_users.type',
                    'admin_users.department_id',
                    'admin_users.parent_admin_id',
                    'admin_users.agent_commission',
                    'admin_users.channel_commission',
                    'admin_users.is_super',
                    'admin_users.created_at',
                    'dept.name as department_name',
                    'dept.phone as department_phone',
                    'parent_admin.nickname as parent_agent_name'
                ])
                ->orderBy('admin_users.id', 'desc');

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
                // 店家下拉筛选
                $filter->eq()->select('id')
                    ->placeholder(admin_trans('store_machine.filter.select_store'))
                    ->options(['' => admin_trans('store_machine.all')] + $storeOptions)
                    ->style(['width' => '250px']);

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('store_machine.placeholder.status'))
                    ->options([
                        1 => admin_trans('store_machine.status.normal'),
                        0 => admin_trans('store_machine.status.disabled')
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('username')->placeholder(admin_trans('store_machine.placeholder.username'));
                $filter->like()->text('nickname')->placeholder(admin_trans('store_machine.placeholder.name'));

                // 代理筛选
                $filter->eq()->select('parent_admin_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.agent'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-StoreMachineController',
                        'getAgentOptions'
                    ]));

                $filter->between()->dateTimeRange('admin_users.created_at')
                    ->placeholder([admin_trans('store_machine.placeholder.start_time'), admin_trans('store_machine.placeholder.end_time')]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
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

            // 4. 创建默认自动交班配置（早中晚三班）
            $autoShiftConfigs = [
                [
                    'title' => admin_trans('store_machine.auto_shift.morning_title'),
                    'shift_time' => '08:00:00',
                    'description' => admin_trans('store_machine.auto_shift.morning_desc')
                ],
                [
                    'title' => admin_trans('store_machine.auto_shift.afternoon_title'),
                    'shift_time' => '16:00:00',
                    'description' => admin_trans('store_machine.auto_shift.afternoon_desc')
                ],
                [
                    'title' => admin_trans('store_machine.auto_shift.night_title'),
                    'shift_time' => '00:00:00',
                    'description' => admin_trans('store_machine.auto_shift.night_desc')
                ],
            ];

            foreach ($autoShiftConfigs as $configData) {
                $autoShiftConfig = new \addons\webman\model\StoreAutoShiftConfig();
                $autoShiftConfig->department_id = $departmentId;
                $autoShiftConfig->bind_admin_user_id = $adminUser->id;
                $autoShiftConfig->is_enabled = 0; // 默认不启用
                $autoShiftConfig->auto_settlement = 1;
                $autoShiftConfig->save();
            }

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
}
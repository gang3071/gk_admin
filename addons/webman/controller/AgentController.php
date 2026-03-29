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
use support\Db;

/**
 * 代理管理（线下渠道）
 * @group channel
 */
class AgentController
{
    /**
     * 代理列表
     * @auth true
     * @group channel
     */
    public function index(): Grid
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Grid::create([], function (Grid $grid) {
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">' . admin_trans('agent.offline_only') . '</font>'));
            });
        }

        $currentDepartmentId = Admin::user()->department_id;

        // 获取代理选项列表用于筛选器下拉选择
        $agentOptions = AdminUser::query()
            ->where('department_id', $currentDepartmentId)
            ->where('type', AdminUser::TYPE_AGENT)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($agent) {
                $label = $agent->nickname ?: $agent->username;
                $label .= " ({$agent->username})";
                return [$agent->id => $label];
            })
            ->toArray();

        return Grid::create(new AdminUser(), function (Grid $grid) use ($currentDepartmentId, $agentOptions) {
            $grid->title(admin_trans('agent.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：代理类型 + 部门的父级是当前渠道
            $grid->model()
                ->join('admin_department', 'admin_users.department_id', '=', 'admin_department.id')
                ->where('admin_users.type', AdminDepartment::TYPE_AGENT)
                ->where('admin_users.department_id', $currentDepartmentId)
                ->select([
                    'admin_users.*',
                    'admin_department.name as department_name',
                    'admin_department.phone as department_phone'
                ])
                ->orderBy('admin_users.id', 'desc');

            // 手动处理筛选条件（避免字段歧义）
            $filterData = \ExAdmin\ui\support\Request::input('ex_admin_filter', []);

            // 代理 ID 筛选
            if (!empty($filterData['agent_id_custom'])) {
                $grid->model()->where('admin_users.id', $filterData['agent_id_custom']);
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
            if (!empty($filterData['phone_custom'])) {
                $grid->model()->where('admin_department.phone', 'like', '%' . $filterData['phone_custom'] . '%');
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

            $grid->column('nickname', admin_trans('agent.fields.name'))->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val, 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val)->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('username', admin_trans('agent.fields.username'))->width(120)->align('center');
            $grid->column('department_phone', admin_trans('agent.fields.phone'))->width(120)->align('center');
            $grid->column('department_name', admin_trans('agent.fields.department_name'))->width(150)->ellipsis(true);

            $grid->column('status', admin_trans('agent.fields.status'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('agent.status.disabled'))->color('red'),
                    1 => Tag::create(admin_trans('agent.status.normal'))->color('green'),
                    default => '',
                };
            })->width(80)->align('center');

            $grid->column('is_super', admin_trans('agent.fields.is_super'))->display(function ($value) {
                return $value == 1
                    ? Tag::create(admin_trans('agent.is_super.yes'))->color('blue')
                    : Tag::create(admin_trans('agent.is_super.no'))->color('default');
            })->width(100)->align('center');

            $grid->column('created_at', admin_trans('agent.fields.created_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) use ($agentOptions) {
                // 代理下拉筛选（使用自定义字段名，手动处理）
                $filter->eq()->select('agent_id_custom')
                    ->placeholder(admin_trans('channel_agent.filter.select_agent'))
                    ->options(['' => admin_trans('channel_agent.all')] + $agentOptions)
                    ->style(['width' => '250px']);

                $filter->eq()->select('status_custom')
                    ->placeholder(admin_trans('agent.placeholder.status'))
                    ->options([
                        1 => admin_trans('agent.status.normal'),
                        0 => admin_trans('agent.status.disabled')
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('username_custom')->placeholder(admin_trans('agent.placeholder.username'));
                $filter->like()->text('nickname_custom')->placeholder(admin_trans('agent.placeholder.name'));
                $filter->like()->text('phone_custom')->placeholder(admin_trans('agent.placeholder.phone'));

                $filter->between()->dateTimeRange('created_at_custom', admin_trans('agent.placeholder.created_at'))
                    ->placeholder([admin_trans('agent.placeholder.start_time'), admin_trans('agent.placeholder.end_time')]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->setForm()->drawer($this->createAgentForm());
            $grid->expandFilter();
        });
    }

    /**
     * 创建代理表单
     * @auth true
     * @group channel
     */
    public function createAgentForm(): Form
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return Form::create([], function (Form $form) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">' . admin_trans('agent.offline_only') . '</font>'));
            });
        }

        return Form::create([], function (Form $form) {
            $form->title(admin_trans('agent.form.create_title'));
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">' . admin_trans('agent.form.create_hint') . '</font>'));

            $form->divider()->content(admin_trans('agent.form.section_account'));

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->text('phone', admin_trans('agent.fields.phone'))
                        ->maxlength(20)
                        ->help(admin_trans('agent.help.phone'));
                })->span(12);

                $form->column(function (Form $form) {
                    $form->text('admin_username', admin_trans('agent.fields.username'))
                        ->maxlength(20)
                        ->help(admin_trans('agent.help.username'))
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', admin_trans('agent.fields.name'))
                ->maxlength(50)
                ->help(admin_trans('agent.help.name'))
                ->required();

            $form->divider()->content(admin_trans('agent.form.section_avatar'));

            $form->image('avatar', admin_trans('agent.fields.avatar'))
                ->help(admin_trans('agent.help.avatar'))
                ->required();

            $form->divider()->content(admin_trans('agent.form.section_password'));

            $form->password('password', admin_trans('agent.fields.password'))
                ->rule(['min:6' => admin_trans('agent.validation.password_min')])
                ->help(admin_trans('agent.help.password'))
                ->required();

            $form->password('password_confirmation', admin_trans('agent.fields.password_confirmation'))
                ->required();

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                return $this->createAgentSaving($form);
            });
        });
    }

    /**
     * 创建代理保存逻辑
     */
    private function createAgentSaving(Form $form)
    {
        /** @var Channel $channel */
        $channel = Channel::where('department_id', Admin::user()->department_id)->first();
        if (!$channel || $channel->is_offline != 1) {
            return message_error(admin_trans('common.offline_channel_only'));
        }

        $phone = $form->input('phone');
        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        if (empty($avatar)) {
            return message_error(admin_trans('common.please_upload_avatar'));
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error(admin_trans('common.password_mismatch'));
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error(admin_trans('common.username_exists', null, ['username' => $adminUsername]));
        }

        Db::beginTransaction();
        try {
            // 代理直接使用渠道的 department_id，不创建新的部门
            $departmentId = Admin::user()->department_id;

            // 1. 创建后台管理员账号
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_AGENT;
            $adminUser->department_id = $departmentId;
            $adminUser->is_super = 0;  // 代理不是超级管理员
            $adminUser->save();

            // 2. 分配代理角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.agent_role');
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 3. 创建代理配置（可选）
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $departmentId;
            $storeSetting->admin_user_id = $adminUser->id;
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = admin_trans('common.default.welcome_agent_system');
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

            Db::commit();

            return message_success(admin_trans('common.agent_create_success', null, [
                'name' => $name,
                'username' => $adminUsername
            ]));

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('agent.error.create_failed', null, ['error' => $e->getMessage()]));
        }
    }

    /**
     * 获取代理选项（用于筛选）
     * 注意：代理后台只能看到自己，返回当前代理账号
     * @auth true
     * @return \support\Response
     */
    public function getAgentOptions(): \support\Response
    {
        /** @var AdminUser $currentAdmin */
        $currentAdmin = Admin::user();

        // 代理后台只返回当前代理自己
        $agents = [[
            'label' => $currentAdmin->nickname ?: $currentAdmin->username,
            'value' => $currentAdmin->id,
        ]];

        return json($agents);
    }
}
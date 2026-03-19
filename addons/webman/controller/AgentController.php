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
                $grid->push(Html::markdown('><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>'));
            });
        }

        $currentDepartmentId = Admin::user()->department_id;

        return Grid::create(new AdminUser(), function (Grid $grid) use ($currentDepartmentId) {
            $grid->title('代理管理');
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：代理类型 + 部门的父级是当前渠道
            $grid->model()
                ->join('admin_department', 'admin_users.department_id', '=', 'admin_department.id')
                ->where('admin_users.type', AdminDepartment::TYPE_AGENT)
                ->where('admin_department.pid', $currentDepartmentId)
                ->select([
                    'admin_users.*',
                    'admin_department.name as department_name',
                    'admin_department.phone as department_phone'
                ])
                ->orderBy('admin_users.id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('nickname', '代理名称')->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val, 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val)->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('username', '登录账号')->width(120)->align('center');
            $grid->column('department_phone', '联系电话')->width(120)->align('center');
            $grid->column('department_name', '部门名称')->width(150)->ellipsis(true);

            $grid->column('status', '状态')->display(function ($value) {
                return match ($value) {
                    0 => Tag::create('已禁用')->color('red'),
                    1 => Tag::create('正常')->color('green'),
                    default => '',
                };
            })->width(80)->align('center');

            $grid->column('is_super', '超级管理员')->display(function ($value) {
                return $value == 1
                    ? Tag::create('是')->color('blue')
                    : Tag::create('否')->color('default');
            })->width(100)->align('center');

            $grid->column('created_at', '创建时间')->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('admin_users.status')
                    ->placeholder('状态')
                    ->options([
                        1 => '正常',
                        0 => '已禁用'
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('admin_users.username')->placeholder('登录账号');
                $filter->like()->text('admin_users.nickname')->placeholder('代理名称');
                $filter->like()->text('admin_department.phone')->placeholder('联系电话');

                $filter->between()->dateTimeRange('admin_users.created_at', '创建时间')
                    ->placeholder(['开始时间', '结束时间']);
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
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>'));
            });
        }

        return Form::create([], function (Form $form) {
            $form->title('创建代理');
            $form->labelCol(['span' => 20]);

            $form->push(Html::markdown('><font size=2 color="#1890ff">创建代理后，该代理可登录代理后台，管理下级店家</font>'));

            $form->divider()->content('账号信息');

            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->text('phone', '联系电话')
                        ->maxlength(20)
                        ->help('选填，用于联系');
                })->span(12);

                $form->column(function (Form $form) {
                    $form->text('admin_username', '后台登录账号')
                        ->maxlength(20)
                        ->help('必填，用于登录代理后台')
                        ->required();
                })->span(12);
            })->gutter(16);

            $form->text('name', '代理名称')
                ->maxlength(50)
                ->help('代理的显示名称')
                ->required();

            $form->divider()->content('头像配置');

            $form->image('avatar', '上传头像')
                ->help('支持jpg、png格式，建议尺寸200x200')
                ->required();

            $form->divider()->content('密码配置');

            $form->password('password', '登录密码')
                ->rule(['min:6' => '密码至少6位'])
                ->help('代理后台登录密码')
                ->required();

            $form->password('password_confirmation', '确认密码')
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
            return message_error('此功能仅限线下渠道使用');
        }

        $phone = $form->input('phone');
        $adminUsername = $form->input('admin_username');
        $name = $form->input('name');
        $password = $form->input('password');
        $avatar = $form->input('avatar');

        if (empty($avatar)) {
            return message_error('请上传头像');
        }

        // 验证确认密码
        if ($password !== $form->input('password_confirmation')) {
            return message_error('两次密码输入不一致');
        }

        // 检查后台账号是否已存在
        $existingAdmin = AdminUser::query()->where('username', $adminUsername)->first();
        if (!empty($existingAdmin)) {
            return message_error("登录账号 {$adminUsername} 已存在");
        }

        Db::beginTransaction();
        try {
            // 1. 创建代理部门
            $agentDepartment = new AdminDepartment();
            $agentDepartment->name = $name;
            $agentDepartment->leader = $name;
            $agentDepartment->phone = $phone ?? '';
            $agentDepartment->type = AdminDepartment::TYPE_AGENT;
            $agentDepartment->pid = Admin::user()->department_id;
            $agentDepartment->save();

            $agentDepartment->path = Admin::user()->department_id . ',' . $agentDepartment->id;
            $agentDepartment->save();

            // 2. 创建后台管理员账号
            $adminUser = new AdminUser();
            $adminUser->username = $adminUsername;
            $adminUser->password = $password;
            $adminUser->nickname = $name;
            $adminUser->avatar = $avatar;
            $adminUser->status = 1;
            $adminUser->type = AdminDepartment::TYPE_AGENT;
            $adminUser->department_id = $agentDepartment->id;
            $adminUser->player_id = 0;
            $adminUser->is_super = 1;
            $adminUser->save();

            // 3. 分配代理角色
            $adminRole = new AdminRoleUsers();
            $adminRole->role_id = config('app.agent_role');
            $adminRole->user_id = $adminUser->id;
            $adminRole->save();

            // 4. 创建代理配置
            $storeSetting = new StoreSetting();
            $storeSetting->department_id = $agentDepartment->id;
            $storeSetting->player_id = 0;
            $storeSetting->admin_user_id = $adminUser->id;
            $storeSetting->feature = 'home_notice';
            $storeSetting->content = '欢迎使用代理后台系统！';
            $storeSetting->status = 1;
            $storeSetting->save();

            $storeSettingMachine = new StoreSetting();
            $storeSettingMachine->department_id = $agentDepartment->id;
            $storeSettingMachine->player_id = 0;
            $storeSettingMachine->admin_user_id = $adminUser->id;
            $storeSettingMachine->feature = 'enable_physical_machine';
            $storeSettingMachine->num = 1;
            $storeSettingMachine->status = 1;
            $storeSettingMachine->save();

            $storeSettingBaccarat = new StoreSetting();
            $storeSettingBaccarat->department_id = $agentDepartment->id;
            $storeSettingBaccarat->player_id = 0;
            $storeSettingBaccarat->admin_user_id = $adminUser->id;
            $storeSettingBaccarat->feature = 'enable_live_baccarat';
            $storeSettingBaccarat->num = 1;
            $storeSettingBaccarat->status = 1;
            $storeSettingBaccarat->save();

            Db::commit();

            return message_success("代理 {$name} 创建成功！登录账号：{$adminUsername}");

        } catch (\Exception $e) {
            Db::rollBack();
            return message_error('创建代理失败：' . $e->getMessage());
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
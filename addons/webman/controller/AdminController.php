<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRole;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\badge\Badge;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * 系统用户管理
 */
class AdminController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.user_model');

    }

    /**
     * 系统用户
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('admin.system_user'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->model()
                ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function (Builder $builder) {
                    $builder->whereKeyNot(plugin()->webman->config('admin_auth_id'));
                });
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->userInfo();
            $grid->column('username', admin_trans('admin.fields.username'))->display(function ($val, $data) {
                if ($data['id'] == plugin()->webman->config('admin_auth_id')) {
                    return Html::create()
                        ->content($val)
                        ->content(
                            Badge::create()->count(admin_trans('admin.super_admin'))->numberStyle(['backgroundColor' => '#1890ff', 'marginLeft' => '5px'])
                        );
                } else {
                    return $val;
                }
            })->copy();
            $grid->column('phone', admin_trans('admin.fields.phone'));
            $grid->column('promoter.name', admin_trans('admin.fields.bind_promoter'));
            $grid->column('player.uuid', admin_trans('admin.fields.uuid'))->copy();
            $grid->column('email', admin_trans('admin.fields.mail'));
            $grid->column('status', admin_trans('admin.fields.status'))->switch();
            $grid->column('role', admin_trans('admin.admin_role'))->display(function ($val, $data) {
                $roleIds = AdminRoleUsers::query()->where('user_id', $data['id'])->get()->pluck('role_id');
                $roleList = AdminRole::query()->whereIn('id', $roleIds)->get();
                $tags = [];
                /** @var AdminRole $role */
                foreach ($roleList as $role) {
                    $tags[] = Tag::create($role->name)->style([
                        'margin-top' => '1px'
                    ])->color('green');
                }
                return Html::create()->content($tags)->style([
                    'display' => 'inline-flex',
                    'text-align' => 'center',
                    'width' => '100px',
                    'justify-content' => 'space-around',
                    'flex-wrap' => 'wrap'
                ]);
            })->width('100px')->align('center');
            $grid->column('type', admin_trans('admin.fields.type'))
                ->display(function ($value, AdminUser $data) {
                    $tag = '';
                    switch ($value) {
                        case AdminDepartment::TYPE_DEPARTMENT:
                            $tag = Tag::create(admin_trans('department.type.' . AdminDepartment::TYPE_DEPARTMENT))->color('#108ee9');
                            break;
                        case AdminDepartment::TYPE_CHANNEL:
                            $tag = Tag::create(admin_trans('department.type.' . AdminDepartment::TYPE_CHANNEL))->color('#f50');
                            break;
                        case AdminDepartment::TYPE_AGENT:
                            $tag = Tag::create(admin_trans('department.type.' . AdminDepartment::TYPE_AGENT))->color('#87d068');
                            break;
                        case AdminDepartment::TYPE_STORE:
                            $tag = Tag::create(admin_trans('department.type.' . AdminDepartment::TYPE_STORE))->color('#2db7f5');
                            break;
                    }
                    if ($data->is_super == 1) {
                        $tag = Tag::create(admin_trans('admin.fields.is_super'))->color('#3b5999');
                    }
                    return Html::create()->content([
                        $tag,
                    ]);
                })->sortable();
            $grid->column('created_at', admin_trans('admin.fields.create_at'));
            $grid->hideDelete();
            $grid->setForm()->modal($this->form());
            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('username')->placeholder(admin_trans('admin.fields.username'));
                $filter->like()->text('phone')->placeholder(admin_trans('admin.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('admin.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => admin_trans('admin.normal'),
                        0 => admin_trans('admin.disable')
                    ]);
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('admin.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        AdminDepartment::TYPE_DEPARTMENT => admin_trans('department.type.' . AdminDepartment::TYPE_DEPARTMENT),
                        AdminDepartment::TYPE_CHANNEL => admin_trans('department.type.' . AdminDepartment::TYPE_CHANNEL),
                        AdminDepartment::TYPE_AGENT => admin_trans('department.type.' . AdminDepartment::TYPE_AGENT),
                        AdminDepartment::TYPE_STORE => admin_trans('department.type.' . AdminDepartment::TYPE_STORE),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
            });

            $department_model = plugin()->webman->config('database.department_model');
            $departmentList = (new $department_model)::where(function($query) {
                $query->where('type', AdminDepartment::TYPE_DEPARTMENT)
                    ->orWhere('type', AdminDepartment::TYPE_AGENT)
                    ->orWhere('type', AdminDepartment::TYPE_STORE)
                    ->orWhereHas('channel', function ($query) {
                        $query->whereNull('deleted_at');
                    });
            })->get();
            $departmentTree = [
                ['id' => 'department', 'name' => admin_trans('admin.department_tree'), 'pid' => 0],
                ['id' => 'channel', 'name' => admin_trans('admin.channel_tree'), 'pid' => 0],
            ];
            /** @var AdminDepartment $value */
            foreach ($departmentList as $value) {
                if ($value->type == AdminDepartment::TYPE_DEPARTMENT) {
                    $departmentTree[] = ['id' => $value->id, 'name' => $value->name, 'pid' => $value->pid == 0 ? 'department' : $value->pid];
                }
                if ($value->type == AdminDepartment::TYPE_CHANNEL) {
                    $departmentTree[] = ['id' => $value->id, 'name' => $value->name, 'pid' => $value->pid == 0 ? 'channel' : $value->pid];
                }
                if ($value->type == AdminDepartment::TYPE_AGENT) {
                    $departmentTree[] = ['id' => $value->id, 'name' => $value->name, 'pid' => $value->pid == 0 ? 'agent' : $value->pid];
                }
                if ($value->type == AdminDepartment::TYPE_STORE) {
                    $departmentTree[] = ['id' => $value->id, 'name' => $value->name, 'pid' => $value->pid == 0 ? 'store' : $value->pid];
                }
            }
            $grid->sidebar('department_id', $departmentTree)
                ->tree()
                ->hideAdd()
                ->hideDel()
                ->searchPlaceholder(admin_trans('admin.search_department'));

            $grid->actions(function (Actions $actions, $data) {
                if ($data['id'] == plugin()->webman->config('admin_auth_id')) {
                    $actions->hideDel();
                }
                $actions->dropdown()
                    ->prepend(admin_trans('admin.reset_password'), 'fas fa-key')
                    ->modal($this->resetPassword($data['id']));

            });

            $grid->deling(function ($ids) {
                if (is_array($ids) && in_array(plugin()->webman->config('admin_auth_id'), $ids)) {
                    return message_error(admin_trans('admin.super_admin_delete'));
                }
            });

            $grid->updateing(function ($ids, $data) {
                if (in_array(plugin()->webman->config('admin_auth_id'), $ids)) {
                    if (isset($data['status']) && $data['status'] == 0) {
                        return message_error(admin_trans('admin.super_admin_disabled'));
                    }
                }
            });
        });
    }

    /**
     * 系统用户
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('admin.system_user'));
            $form->text('username', admin_trans('admin.fields.username'))
                ->ruleChsDash()
                ->rule([
                    (string)Rule::unique(plugin()->webman->config('database.user_model'))->ignore($form->input('id')) => admin_trans('admin.username_exist'),
                ])
                ->required()
                ->disabled($form->isEdit());
            $form->text('nickname', admin_trans('admin.fields.nickname'))
                ->ruleChsAlphaNum()
                ->required();
            $form->image('avatar', admin_trans('admin.fields.avatar'))
                ->required();
            if (!$form->isEdit()) {
                $form->password('password', admin_trans('admin.fields.password'))
                    ->default(123456)
                    ->help(admin_trans('admin.pass_help'))
                    ->required();
            }
            $form->text('phone', admin_trans('admin.fields.phone'))
                ->rule([
                    (string)Rule::unique(plugin()->webman->config('database.user_model'))->ignore($form->input('id')) => admin_trans('admin.phone_exist'),
                ])
                ->ruleMobile();
            $form->text('email', admin_trans('admin.fields.mail'))->ruleEmail();
            if ($form->input('id') != plugin()->webman->config('admin_auth_id')) {
                $form->radio('type', admin_trans('admin.fields.type'))
                    ->default(AdminDepartment::TYPE_DEPARTMENT)
                    ->disabled($form->isEdit())
                    ->options([
                        AdminDepartment::TYPE_DEPARTMENT => admin_trans('department.type.' . AdminDepartment::TYPE_DEPARTMENT),
                        AdminDepartment::TYPE_CHANNEL => admin_trans('department.type.' . AdminDepartment::TYPE_CHANNEL),
                        AdminDepartment::TYPE_AGENT => admin_trans('department.type.' . AdminDepartment::TYPE_AGENT),
                        AdminDepartment::TYPE_STORE => admin_trans('department.type.' . AdminDepartment::TYPE_STORE),
                    ])
                    ->when('==', AdminDepartment::TYPE_DEPARTMENT, function (Form $form) {
                        $roleModel = plugin()->webman->config('database.role_model');
                        $role = $roleModel::where('type', AdminDepartment::TYPE_DEPARTMENT)->pluck('name', 'id')->toArray();
                        $form->checkbox('roles', admin_trans('admin.access_rights'))
                            ->options($role)->required();

                        $department = plugin()->webman->config('database.department_model');
                        $options = $department::where('status', 1)->where('type', AdminDepartment::TYPE_DEPARTMENT)->get()->toArray();
                        $form->treeSelect('department_id', admin_trans('admin.department'))
                            ->required()
                            ->options($options);

                    })->when('==', AdminDepartment::TYPE_CHANNEL, function (Form $form) {
                        $roleModel = plugin()->webman->config('database.role_model');
                        $role = $roleModel::where('type', AdminDepartment::TYPE_CHANNEL)->whereNotIn('id',
                            [config('app.agent_role'), config('app.store_role')])->pluck('name', 'id')->toArray();
                        $form->checkbox('roles', admin_trans('admin.access_rights'))
                            ->options($role)->required();

                        $department = plugin()->webman->config('database.department_model');
                        $options = $department::where('status', 1)->where('type', AdminDepartment::TYPE_CHANNEL)->whereHas('channel', function ($query) {
                            $query->whereNull('deleted_at');
                        })
                            ->get()->toArray();
                        $form->treeSelect('department_id', admin_trans('admin.channel'))
                            ->required()
                            ->options($options);
                    })->when('==', AdminDepartment::TYPE_AGENT, function (Form $form) {
                        $roleModel = plugin()->webman->config('database.role_model');
                        $role = $roleModel::where('type', AdminDepartment::TYPE_AGENT)->pluck('name', 'id')->toArray();
                        $form->checkbox('roles', admin_trans('admin.access_rights'))
                            ->options($role)->required();

                        $department = plugin()->webman->config('database.department_model');
                        $options = $department::where('status', 1)->where('type', AdminDepartment::TYPE_AGENT)->get()->toArray();
                        $form->treeSelect('department_id', admin_trans('admin.agent'))
                            ->required()
                            ->options($options);
                    })->when('==', AdminDepartment::TYPE_STORE, function (Form $form) {
                        $roleModel = plugin()->webman->config('database.role_model');
                        $role = $roleModel::where('type', AdminDepartment::TYPE_STORE)->pluck('name', 'id')->toArray();
                        $form->checkbox('roles', admin_trans('admin.access_rights'))
                            ->options($role)->required();

                        $department = plugin()->webman->config('database.department_model');
                        $options = $department::where('status', 1)->where('type', AdminDepartment::TYPE_STORE)->get()->toArray();
                        $form->treeSelect('department_id', admin_trans('admin.store'))
                            ->required()
                            ->options($options);
                    });
                $department = plugin()->webman->config('database.post_model');
                $options = $department::where('status', 1)->pluck('name', 'id')->toArray();
                $form->select('post', admin_trans('admin.post'))
                    ->options($options)
                    ->multiple();
            }
        });
    }

    /**
     * 修改密码
     * @auth true
     * @group all
     * @return Form
     */
    public function updatePassword(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->password('old_password', admin_trans('admin.old_password'))->required();
            $form->password('password', admin_trans('admin.new_password'))
                ->rule([
                    'confirmed' => admin_trans('admin.password_confim_validate'),
                    'min:6' => admin_trans('admin.password_min_number')
                ])
                ->value('')
                ->required();
            $form->password('password_confirmation', admin_trans('admin.confim_password'))
                ->required();
            $form->saving(function (Form $form) {
                if (!password_verify($form->input('old_password'), Admin::user()->password)) {
                    return message_error(admin_trans('admin.old_password_error'));
                }
            });
        });
    }

    /**
     * 个人信息
     * @auth true
     * @group all
     * @return Form
     */
    public function editInfo(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->text('username', admin_trans('admin.fields.username'))
                ->ruleChsDash()->disabled();
            $form->text('nickname', admin_trans('admin.fields.nickname'))
                ->ruleChsAlphaNum();
            $form->image('avatar', admin_trans('admin.fields.avatar'));
            $form->text('phone', admin_trans('admin.fields.phone'))
                ->rule([
                    (string)Rule::unique(plugin()->webman->config('database.user_model'))->ignore($form->input('id')) => admin_trans('admin.phone_exist'),
                ])
                ->ruleMobile();
            $form->text('email', admin_trans('admin.fields.mail'))->ruleEmail();
        });
    }

    /**
     * 重置密码
     * @auth true
     * @group all
     * @param $id
     * @return Form
     */
    public function resetPassword($id): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->password('password', admin_trans('admin.new_password'))
                ->rule([
                    'confirmed' => admin_trans('admin.password_confim_validate'),
                    'min:6' => admin_trans('admin.password_min_number')
                ])
                ->value('')
                ->required();
            $form->password('password_confirmation', admin_trans('admin.confim_password'))
                ->required();
        });
    }
}

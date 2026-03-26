<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRole;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;


/**
 * 系统角色
 */
class RoleController
{
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.role_model');
    }

    /**
     * 系统角色
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->destroyInactiveTabPane()
            ->pane(admin_trans('auth.type.' . AdminDepartment::TYPE_DEPARTMENT), $this->roleList(AdminDepartment::TYPE_DEPARTMENT))
            ->pane(admin_trans('auth.type.' . AdminDepartment::TYPE_CHANNEL), $this->roleList(AdminDepartment::TYPE_CHANNEL))
            ->pane(admin_trans('auth.type.' . AdminDepartment::TYPE_AGENT), $this->roleList(AdminDepartment::TYPE_AGENT))
            ->pane(admin_trans('auth.type.' . AdminDepartment::TYPE_STORE), $this->roleList(AdminDepartment::TYPE_STORE))
            ->type('card')
        );
    }

    /**
     * 角色列表
     * @param int $type 角色类型
     * @return Grid
     */
    public function roleList(int $type = AdminDepartment::TYPE_DEPARTMENT): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) use ($type) {
            $grid->title(admin_trans('auth.title'));
            $grid->model()->where('type', $type)->orderBy('sort');
            $grid->autoHeight();
            $grid->column('name', admin_trans('auth.fields.name'));
            $grid->hideSelection();
            $grid->column('desc', admin_trans('auth.fields.desc'));
            $grid->column('is_protected', admin_trans('auth.fields.is_protected'))->display(function ($value) {
                return $value == 1
                    ? Tag::create(admin_trans('auth.tag.built_in_role'))->color('blue')
                    : Tag::create(admin_trans('auth.tag.custom_role'))->color('default');
            })->width(100)->align('center');
            $grid->column('data_type', admin_trans('auth.fields.data_type'))
                ->display(function ($value, AdminRole $data) use ($type) {
                    $tag = '';
                    switch ($value) {
                        case AdminRole::DATA_TYPE_ALL:
                            $tag = Tag::create(admin_trans('auth.options.data_type.full_data_rights'))->color('#f50');
                            break;
                        case AdminRole::DATA_TYPE_CUSTOM:
                            $tag = Tag::create(admin_trans('auth.options.data_type.custom_data_permissions'))->color('#2db7f5');
                            break;
                        case AdminRole::DATA_TYPE_DEPARTMENT_BELOW:
                            if ($type == AdminDepartment::TYPE_CHANNEL) {
                                $tag = Tag::create(admin_trans('auth.options.data_type.channel_and_the_following_data_permissions'))->color('#87d068');
                            } elseif ($type == AdminDepartment::TYPE_AGENT) {
                                $tag = Tag::create(admin_trans('auth.options.data_type.agent_and_the_following_data_permissions'))->color('#87d068');
                            } else {
                                $tag = Tag::create(admin_trans('auth.options.data_type.this_department_and_the_following_data_permissions'))->color('#87d068');
                            }
                            break;
                        case AdminRole::DATA_TYPE_DEPARTMENT:
                            $tag = Tag::create(admin_trans('auth.options.data_type.data_permissions_for_this_department'))->color('#108ee9');
                            break;
                        case AdminRole::DATA_TYPE_SELF:
                            $tag = Tag::create(admin_trans('auth.options.data_type.personal_data_rights'))->color('#108ee9');
                            break;
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->sortable();
            $grid->setForm()->modal($this->form());
            $grid->expandFilter();
            $grid->actions(function (Actions $actions, AdminRole $data) {
                $dropdown = $actions->dropdown();
                $dropdown->prepend(admin_trans('auth.auth_grant'), 'safety-certificate-filled')
                    ->modal($this->auth($data['id'], $data['type']));
                $dropdown->prepend(admin_trans('auth.menu_grant'), 'appstore-filled')
                    ->modal($this->menu($data['id'], $data['type']));
                $dropdown->prepend(admin_trans('auth.data_grant'), 'fas fa-database')
                    ->modal($this->data($data['id'], $data['type']));

                // 保护系统内置角色：渠道管理员、代理超管、店家超管
                if ($data->id == AdminRole::ROLE_CHANNEL ||
                    $data->id == AdminRole::ROLE_AGENT ||
                    $data->id == AdminRole::ROLE_STORE ||
                    ($data->is_protected ?? 0) == 1) {
                    $actions->hideDel();
                }
            });
        });
    }

    /**
     * 系统角色
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('auth.title'));

            // 检查是否为受保护的角色
            $isProtected = false;
            if ($form->isEdit()) {
                $role = $form->model()->find($form->input('id'));
                $isProtected = ($role->is_protected ?? 0) == 1 ||
                               in_array($role->id, [AdminRole::ROLE_CHANNEL, AdminRole::ROLE_AGENT, AdminRole::ROLE_STORE]);
            }

            $form->text('name', admin_trans('auth.fields.name'))->required()->disabled($isProtected);
            $form->textarea('desc', admin_trans('auth.fields.desc'))->rows(5)->required();
            $form->radio('type', admin_trans('auth.fields.type'))
                ->default(AdminDepartment::TYPE_DEPARTMENT)
                ->options([
                    AdminDepartment::TYPE_DEPARTMENT => admin_trans('auth.type.' . AdminDepartment::TYPE_DEPARTMENT),
                    AdminDepartment::TYPE_CHANNEL => admin_trans('auth.type.' . AdminDepartment::TYPE_CHANNEL),
                    AdminDepartment::TYPE_AGENT => admin_trans('auth.type.' . AdminDepartment::TYPE_AGENT),
                    AdminDepartment::TYPE_STORE => admin_trans('auth.type.' . AdminDepartment::TYPE_STORE),
                ])->disabled($form->isEdit());
            $form->number('sort', admin_trans('auth.fields.sort'))->default($this->model::max('sort') + 1);
            $form->saving(function (Form $form) {
                // 编辑时检查是否为受保护的角色
                if ($form->isEdit()) {
                    $role = $form->model()->find($form->input('id'));
                    $isProtected = ($role->is_protected ?? 0) == 1 ||
                                   in_array($role->id, [AdminRole::ROLE_CHANNEL, AdminRole::ROLE_AGENT, AdminRole::ROLE_STORE]);

                    if ($isProtected) {
                        // 受保护角色不允许修改名称和类型
                        if ($form->input('name') != $role->name) {
                            return message_error(admin_trans('common.builtin_role_cannot_modify_name'));
                        }
                        if ($form->input('type') != $role->type) {
                            return message_error(admin_trans('common.builtin_role_cannot_modify_type'));
                        }
                    }
                } else {
                    // 新建角色时，根据类型设置数据权限
                    $type = $form->input('type');
                    switch ($type) {
                        case AdminDepartment::TYPE_DEPARTMENT:
                            $form->input('data_type', AdminRole::DATA_TYPE_ALL);
                            break;
                        case AdminDepartment::TYPE_CHANNEL:
                            $form->input('data_type', AdminRole::DATA_TYPE_DEPARTMENT_BELOW);
                            break;
                        case AdminDepartment::TYPE_AGENT:
                            $form->input('data_type', AdminRole::DATA_TYPE_DEPARTMENT_BELOW);
                            break;
                        case AdminDepartment::TYPE_STORE:
                            $form->input('data_type', AdminRole::DATA_TYPE_SELF);
                            break;
                        default:
                            return message_error(admin_trans('auth.role_type_error'));
                    }
                }
            });

            // 删除前检查：防止删除受保护的角色
            $form->deleting(function (Form $form) {
                $role = $form->model()->find($form->input('id'));

                if (!$role) {
                    return message_error(admin_trans('common.role_not_exist'));
                }

                // 检查是否为受保护的角色
                if (($role->is_protected ?? 0) == 1) {
                    return message_error(admin_trans('common.builtin_role_cannot_delete'));
                }

                // 检查特定角色ID（渠道、代理、店家超管）
                if (in_array($role->id, [AdminRole::ROLE_CHANNEL, AdminRole::ROLE_AGENT, AdminRole::ROLE_STORE])) {
                    return message_error(admin_trans('common.builtin_role_cannot_delete'));
                }
            });
        });

    }

    /**
     * 数据权限
     * @auth true
     * @return Form
     */
    public function data($id, $type)
    {
        return Form::create(new $this->model(), function (Form $form) use ($type) {
            switch ($type) {
                case AdminDepartment::TYPE_DEPARTMENT:
                    $options = [
                        0 => admin_trans('auth.options.data_type.full_data_rights'),
                        1 => admin_trans('auth.options.data_type.custom_data_permissions'),
                        2 => admin_trans('auth.options.data_type.this_department_and_the_following_data_permissions'),
                        3 => admin_trans('auth.options.data_type.data_permissions_for_this_department'),
                        4 => admin_trans('auth.options.data_type.personal_data_rights'),

                    ];
                    break;
                case AdminDepartment::TYPE_CHANNEL:
                    $options = [
                        2 => admin_trans('auth.options.data_type.channel_and_the_following_data_permissions'),
                        3 => admin_trans('auth.options.data_type.data_permissions_for_this_department'),
                        4 => admin_trans('auth.options.data_type.personal_data_rights'),
                    ];
                    break;
                case AdminDepartment::TYPE_AGENT:
                    $options = [
                        2 => admin_trans('auth.options.data_type.agent_and_the_following_data_permissions'),
                        3 => admin_trans('auth.options.data_type.data_permissions_for_this_department'),
                        4 => admin_trans('auth.options.data_type.personal_data_rights'),
                    ];
                    break;
                case AdminDepartment::TYPE_STORE:
                    $options = [
                        4 => admin_trans('auth.options.data_type.personal_data_rights'),
                    ];
                    break;
                default:
                    $options = [];
            }
            $form->title(admin_trans('auth.title'));
            $form->desc('name', admin_trans('auth.fields.name'));
            $form->desc('desc', admin_trans('auth.fields.desc'));
            $form->select('data_type', admin_trans('auth.fields.data_type'))
                ->required()
                ->options($options)
                ->when(1, function (Form $form) {
                    $department = plugin()->webman->config('database.department_model');
                    $options = $department::where('status', 1)
                        ->where('type', AdminDepartment::TYPE_DEPARTMENT)
                        ->get()->toArray();
                    $tree = $form->tree('department')
                        ->showIcon()
                        ->content(Icon::create('FolderOutlined'), 'groupIcon')
                        ->multiple()
                        ->checkable()
                        ->bindAttr('checkStrictly', $form->getModel() . '.check_strictly')
                        ->options($options);
                    $form->popItem();
                    $form->switch('check_strictly', admin_trans('auth.fields.department'))
                        ->default(false)
                        ->checkedChildren(admin_trans('auth.father_son_linkage'))
                        ->unCheckedChildren(admin_trans('auth.father_son_linkage'))
                        ->checkedValue(false)
                        ->unCheckedValue(true)
                        ->getFormItem()->content($tree);

                });
        });
    }

    /**
     * 菜单权限
     * @auth true
     * @param $id
     * @param $type
     * @return Form
     */
    public function menu($id, $type): Form
    {
        $menuModel = plugin()->webman->config('database.menu_model');
        $tree = $menuModel::select('id', 'pid', 'name')->where('type', $type)->get()->toArray();
        $model = plugin()->webman->config('database.role_menu_model');
        $field = 'menu_id';
        $label = 'name';
        $nodeTypeList = [];
        // 根据角色类型确定 group 值
        $groupMap = [
            AdminDepartment::TYPE_DEPARTMENT => 'department',
            AdminDepartment::TYPE_CHANNEL => 'channel',
            AdminDepartment::TYPE_AGENT => 'agent',
            AdminDepartment::TYPE_STORE => 'store',
        ];
        $targetGroup = $groupMap[$type] ?? 'department';

        foreach ($tree as $value) {
            if (!empty($value['group'])) {
                /** 全部菜单 */
                if ($value['group'] == 'all') {
                    $nodeTypeList[] = $value;
                }
                /** 对应类型的菜单 */
                if ($value['group'] == $targetGroup) {
                    $nodeTypeList[] = $value;
                }
            } else {
                $nodeTypeList[] = $value;
            }
        }
        array_unshift($nodeTypeList, ['id' => 0, $label => admin_trans('auth.all'), 'pid' => -1]);
        $auths = $model::where('role_id', $id)->pluck($field);
        return Form::create(new $this->model(), function (Form $form) use ($id, $model, $nodeTypeList, $field, $auths, $label) {
            $form->tree('auth')
                ->options($nodeTypeList, $label)
                ->default($auths)
                ->checkable();
            $form->saving(function (Form $form) use ($id, $model, $field) {
                $auths = $form->input('auth');
                $form->removeInput('auth');
                $auths = array_filter($auths);
                $auths = array_map(function ($item) use ($id, $field) {
                    return ['role_id' => $id, $field => $item];
                }, $auths);
                $model::where('role_id', $id)->delete();
                if ($auths) {
                    $authsArr = array_chunk($auths, 10, true);
                    foreach ($authsArr as $value) {
                        $model::insert($value);
                    }
                }
            });
        });
    }

    /**
     * 功能权限
     * @auth true
     * @param $id
     * @param string $type
     * @return Form
     */
    public function auth($id, string $type = ''): Form
    {
        $tree = Admin::node()->all();
        $model = plugin()->webman->config('database.role_permission_model');
        $field = 'node_id';
        $label = 'title';
        $nodeTypeList = [];
        // 根据角色类型确定 group 值
        $groupMap = [
            AdminDepartment::TYPE_DEPARTMENT => 'department',
            AdminDepartment::TYPE_CHANNEL => 'channel',
            AdminDepartment::TYPE_AGENT => 'agent',
            AdminDepartment::TYPE_STORE => 'store',
        ];
        $targetGroup = $groupMap[$type] ?? 'department';

        foreach ($tree as $value) {
            if (!empty($value['group'])) {
                /** 全部菜单 */
                if ($value['group'] == 'all') {
                    $nodeTypeList[] = $value;
                }
                /** 对应类型的菜单 */
                if ($value['group'] == $targetGroup) {
                    $nodeTypeList[] = $value;
                }
            } else {
                $nodeTypeList[] = $value;
            }
        }
        array_unshift($nodeTypeList, ['id' => 0, $label => admin_trans('auth.all'), 'pid' => -1]);
        $auths = $model::where('role_id', $id)->pluck($field);
        return Form::create(new $this->model(), function (Form $form) use ($id, $model, $nodeTypeList, $field, $auths, $label) {
            $form->tree('auth')
                ->options($nodeTypeList, $label)
                ->default($auths)
                ->checkable();
            $form->saving(function (Form $form) use ($id, $model, $field) {
                $auths = $form->input('auth');
                $form->removeInput('auth');
                $auths = array_filter($auths);
                $auths = array_map(function ($item) use ($id, $field) {
                    return ['role_id' => $id, $field => $item];
                }, $auths);
                $model::where('role_id', $id)->delete();
                if ($auths) {
                    $authsArr = array_chunk($auths, 10, true);
                    foreach ($authsArr as $value) {
                        $model::insert($value);
                    }
                }
            });
        });
    }

    public function commonAuthForm($id, $model, $tree, $type, $field, $label): Form
    {
        $nodeTypeList = [];
        // 根据角色类型确定 group 值
        $groupMap = [
            AdminDepartment::TYPE_DEPARTMENT => 'department',
            AdminDepartment::TYPE_CHANNEL => 'channel',
            AdminDepartment::TYPE_AGENT => 'agent',
            AdminDepartment::TYPE_STORE => 'store',
        ];
        $targetGroup = $groupMap[$type] ?? 'department';

        foreach ($tree as $value) {
            if (!empty($value['group'])) {
                /** 全部菜单 */
                if ($value['group'] == 'all') {
                    $nodeTypeList[] = $value;
                }
                /** 对应类型的菜单 */
                if ($value['group'] == $targetGroup) {
                    $nodeTypeList[] = $value;
                }
            } else {
                $nodeTypeList[] = $value;
            }
        }
        array_unshift($nodeTypeList, ['id' => 0, $label => admin_trans('auth.all'), 'pid' => -1]);
        $auths = $model::where('role_id', $id)->pluck($field);
        return Form::create(new $this->model(), function (Form $form) use ($id, $model, $nodeTypeList, $field, $auths, $label) {
            $form->tree('auth')
                ->options($nodeTypeList, $label)
                ->default($auths)
                ->checkable();
            $form->saving(function (Form $form) use ($id, $model, $field) {
                $auths = $form->input('auth');
                $form->removeInput('auth');
                $auths = array_filter($auths);
                $auths = array_map(function ($item) use ($id, $field) {
                    return ['role_id' => $id, $field => $item];
                }, $auths);
                $model::where('role_id', $id)->delete();
                if ($auths) {
                    $authsArr = array_chunk($auths,10,true);
                    foreach ($authsArr as $value) {
                        $model::insert($value);
                    }
                }
            });
        });
    }
}

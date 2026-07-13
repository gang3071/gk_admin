<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\constant\MenuConstant;
use addons\webman\model\AdminDepartment;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\support\Arr;

/**
 * 菜单管理
 */
class MenuController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.menu_model');
    }

    /**
     * 系统菜单
     * @auth true
     * @return Card
     */
    public function index(): Card
    {
        return Card::create(Tabs::create()
            ->destroyInactiveTabPane()
            ->pane(admin_trans('menu.type.' . AdminDepartment::TYPE_DEPARTMENT), $this->menuList())
            ->pane(admin_trans('menu.type.' . AdminDepartment::TYPE_CHANNEL), $this->menuList(AdminDepartment::TYPE_CHANNEL))
            ->pane(admin_trans('menu.type.' . AdminDepartment::TYPE_AGENT), $this->menuList(AdminDepartment::TYPE_AGENT))
            ->pane(admin_trans('menu.type.' . AdminDepartment::TYPE_STORE), $this->menuList(AdminDepartment::TYPE_STORE))
            ->type('card')
        );
    }

    /**
     * 系统菜单
     * @param int $type 菜单类型
     * @return Grid
     */
    public function menuList(int $type = AdminDepartment::TYPE_DEPARTMENT):Grid
    {
        return Grid::create(new $this->model(),function (Grid $grid) use($type){
            $grid->title(admin_trans('menu.title'));
            $grid->model()->where('type', $type)->orderBy('sort');
            $grid->autoHeight();
            $grid->tree();

            $grid->column('name', admin_trans('menu.fields.name'))->display(function ($value, $data) {
                $icon = Icon::create($data['icon']);

                // 如果是受控菜单，显示标识
                if (MenuConstant::isControlledMenu($value)) {
                    return Html::create([
                        $icon,
                        ' ',
                        $value,
                        ' ',
                        Html::create('🔒')->style(['color' => '#ff9800', 'font-size' => '12px'])
                            ->attr('title', '此菜单受功能开关控制')
                    ]);
                }

                return Html::create([$icon, ' ', $value]);
            });

            $grid->column('url', admin_trans('menu.fields.url'))->display(function ($value) {
                if (empty($value) || $value == '#') {
                    return $value;
                }
                return Html::create($value)->tag('a')->redirect($value);
            });

            $grid->column('status', admin_trans('menu.fields.status'))->switch();
            $grid->column('open', admin_trans('menu.fields.open'))->switch();
            $grid->sortInput();
            $grid->expandFilter();

            // ✅ 只允许编辑，禁用创建和删除
            $grid->setForm()->modal($this->form());

            // ❌ 禁用创建功能（菜单应通过迁移文件创建）
            $grid->hideCreateButton();

            // ❌ 禁用删除功能（隐藏操作列中的删除按钮）
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();  // 隐藏每行的删除按钮
            });

            // ❌ 禁用批量操作（隐藏复选框和批量删除按钮）
            $grid->hideSelection();

            // ❌ 禁用回收站功能
            $grid->hideTrashed();

            $grid->updated(function (){
                return message_success(admin_trans('grid.update_success'))->refreshMenu();
            });
        });
    }

    /**
     * 系统菜单
     * @auth true
     * @param int $pid
     * @return Form
     */
    public function form(int $pid = 0): Form
    {
        return Form::create(new $this->model,function (Form $form) use($pid){
            $form->title(admin_trans('menu.title'));

            // ⚠️ name字段完全不可编辑（所有菜单都应通过迁移文件管理）
            if ($form->isEdit()) {
                $menuName = $form->input('name');
                $isControlled = MenuConstant::isControlledMenu($menuName);

                // ✅ 只读显示name，使用hidden字段保存原始值
                $form->desc('name_display', admin_trans('menu.fields.name'))
                    ->value($menuName)
                    ->help($isControlled ? '🔒 ' . admin_trans('menu.help.controlled_menu') : admin_trans('menu.help.name_readonly'));

                // 隐藏字段保存原始name值
                $form->hidden('name')->default($menuName);
            } else {
                // 新增菜单时也应该只读，提示通过迁移文件创建
                $form->desc('name_display', admin_trans('menu.fields.name'))
                    ->value(admin_trans('menu.help.use_migration'))
                    ->help(admin_trans('menu.help.create_by_migration'));

                // 隐藏字段，新增时不保存name（或保存空值，取决于业务逻辑）
                $form->hidden('name')->default('');
            }
            $form->radio('type', admin_trans('menu.fields.type'))
                ->default(AdminDepartment::TYPE_DEPARTMENT)
                ->disabled($form->isEdit())
                ->options([
                    AdminDepartment::TYPE_DEPARTMENT => admin_trans('menu.type.' . AdminDepartment::TYPE_DEPARTMENT),
                    AdminDepartment::TYPE_CHANNEL => admin_trans('menu.type.' . AdminDepartment::TYPE_CHANNEL),
                    AdminDepartment::TYPE_AGENT => admin_trans('menu.type.' . AdminDepartment::TYPE_AGENT),
                    AdminDepartment::TYPE_STORE => admin_trans('menu.type.' . AdminDepartment::TYPE_STORE),
                ])->when('==', AdminDepartment::TYPE_DEPARTMENT, function (Form $form) use($pid){
                    $menus = $this->model::where('type', AdminDepartment::TYPE_DEPARTMENT)->get()->toArray();
                    array_unshift($menus, ['id' => 0, 'name' => admin_trans('menu.fields.top'), 'pid' => -1]);
                    $form->treeSelect('pid', admin_trans('menu.fields.pid'))
                        ->default($pid)
                        ->options($menus)
                        ->required();

                })->when('==', AdminDepartment::TYPE_CHANNEL, function (Form $form) use($pid){
                    $menus = $this->model::where('type', AdminDepartment::TYPE_CHANNEL)->get()->toArray();
                    array_unshift($menus, ['id' => 0, 'name' => admin_trans('menu.fields.top'), 'pid' => -1]);
                    $form->treeSelect('pid', admin_trans('menu.fields.pid'))
                        ->default($pid)
                        ->options($menus)
                        ->required();
                })->when('==', AdminDepartment::TYPE_AGENT, function (Form $form) use($pid){
                    $menus = $this->model::where('type', AdminDepartment::TYPE_AGENT)->get()->toArray();
                    array_unshift($menus, ['id' => 0, 'name' => admin_trans('menu.fields.top'), 'pid' => -1]);
                    $form->treeSelect('pid', admin_trans('menu.fields.pid'))
                        ->default($pid)
                        ->options($menus)
                        ->required();
                })->when('==', AdminDepartment::TYPE_STORE, function (Form $form) use($pid){
                    $menus = $this->model::where('type', AdminDepartment::TYPE_STORE)->get()->toArray();
                    array_unshift($menus, ['id' => 0, 'name' => admin_trans('menu.fields.top'), 'pid' => -1]);
                    $form->treeSelect('pid', admin_trans('menu.fields.pid'))
                        ->default($pid)
                        ->options($menus)
                        ->required();
                });
            $form->autoComplete('url', admin_trans('menu.fields.url'))
                ->groupOptions(Arr::tree(Admin::node()->all()),'children','title','url');
            $form->icon('icon', admin_trans('menu.fields.icon'))
                ->default('far fa-circle')
                ->required();
            $form->number('sort', admin_trans('menu.fields.sort'))
                ->default($this->model::where('pid', $pid)->max('sort') + 1);

            // ✅ 保存前验证（所有菜单通过迁移文件管理）
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    // 新增时：禁止通过界面创建菜单，返回错误
                    return message_error(admin_trans('menu.error.create_disabled'));
                }

                // 编辑时：从提交数据中移除name字段，防止被修改
                unset($_POST['name'], $_REQUEST['name']);
            });

            $form->saved(function(){
                return message_success(admin_trans('form.save_success'))->refreshMenu();
            });
        });
    }
}

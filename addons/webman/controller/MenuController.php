<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
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
                return Html::create([
                    Icon::create($data['icon']),
                    ' ',
                    $value
                ]);
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
            $grid->setForm()->modal($this->form());
            $grid->updated(function (){
                return message_success(admin_trans('grid.update_success'))->refreshMenu();
            });
            $grid->deleted(function (){
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
            $form->text('name', admin_trans('menu.fields.name'))->required();
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
            $form->saved(function(){
                return message_success(admin_trans('form.save_success'))->refreshMenu();
            });
        });
    }
}

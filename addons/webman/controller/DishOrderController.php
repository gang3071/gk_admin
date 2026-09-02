<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminUser;
use addons\webman\model\DishOrder;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;

/**
 * 餐點訂單
 */
class DishOrderController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.dish_order_model');
    }

    /**
     * 列表
     * @auth true
     */
    public function index(): Grid
    {
        $adminUser = Admin::user();
        $stores = [];

        // 非門店角色可以看到對應的門店清單
        if ($adminUser->type != AdminDepartment::TYPE_STORE) {
            $stores = self::getStores($adminUser->type);
        }

        return Grid::create(new $this->model, function (Grid $grid) use ($adminUser, $stores)  {
            $grid->title(admin_trans('dish_order.title'));
            $grid->hideAdd();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->model()->with('items')->orderBy('id', 'desc');

            if ($adminUser->type != AdminDepartment::TYPE_STORE) {
                $grid->model()->whereIn('admin_user_id', array_keys($stores));
            } else {
                $grid->model()->where('admin_user_id', $adminUser->id);
            }

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($adminUser, $stores)  {
                $filter->like()->text('order_no')->placeholder(admin_trans('dish_order.fields.order_no'));
                $filter->like()->text('player.name')->placeholder(admin_trans('dish_order.fields.player_id'));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish_order.fields.status'))
                    ->options([
                        DishOrder::STATUS_PENDING => admin_trans('dish_order.status.' . DishOrder::STATUS_PENDING),
                        DishOrder::STATUS_CONFIRMED => admin_trans('dish_order.status.' . DishOrder::STATUS_CONFIRMED),
                        DishOrder::STATUS_COOKING => admin_trans('dish_order.status.' . DishOrder::STATUS_COOKING),
                        DishOrder::STATUS_COMPLETED => admin_trans('dish_order.status.' . DishOrder::STATUS_COMPLETED),
                        DishOrder::STATUS_CANCELLED => admin_trans('dish_order.status.' . DishOrder::STATUS_CANCELLED)
                    ]);

                if ($adminUser->type != AdminDepartment::TYPE_STORE) {
                    $filter->eq()->select('admin_user_id')
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('dish.fields.admin_user_id'))
                        ->options($stores);
                }
            });

            $grid->column('id', admin_trans('dish_order.fields.id'))->align('center');
            $grid->column('order_no', admin_trans('dish_order.fields.order_no'))->align('center');
            $grid->column('player.name', admin_trans('dish_order.fields.player_id'))->align('center');

            $grid->column('items', admin_trans('dish_order_item.title'))->align('center')
                ->display(function ($items) {
                    $html = self::htmlItems($items);
                    return Html::raw($html);
                });

            $grid->column('total_amount', admin_trans('dish_order.fields.total_amount'))->align('center');
            $grid->column('status', admin_trans('dish_order.fields.status'))->align('center')
                ->display(function ($value) {
                    switch ($value) {
                        case DishOrder::STATUS_PENDING:
                            $tag = Tag::create(admin_trans('dish_order.status.' . DishOrder::STATUS_PENDING))->color('#108ee9');
                            break;

                        case DishOrder::STATUS_CONFIRMED:
                            $tag = Tag::create(admin_trans('dish_order.status.' . DishOrder::STATUS_CONFIRMED))->color('#f50');
                            break;

                        case DishOrder::STATUS_COOKING:
                            $tag = Tag::create(admin_trans('dish_order.status.' . DishOrder::STATUS_COOKING))->color('#fa8c16');
                            break;

                        case DishOrder::STATUS_COMPLETED:
                            $tag = Tag::create(admin_trans('dish_order.status.' . DishOrder::STATUS_COMPLETED))->color('#52c41a');
                            break;

                        case DishOrder::STATUS_CANCELLED:
                            $tag = Tag::create(admin_trans('dish_order.status.' . DishOrder::STATUS_CANCELLED))->color('#ff4d4f');
                            break;
                    }

                    return Html::create()->content([$tag]);
                });
            $grid->column('created_at', admin_trans('dish_order.fields.created_at'))->align('center');
            $grid->column('updated_at', admin_trans('dish_order.fields.updated_at'))->align('center');

            if ($adminUser->type != AdminDepartment::TYPE_STORE) {
                $grid->column('admin_user_id', admin_trans('dish_order.fields.admin_user_id'))->align('center')
                    ->display(function ($value) use ($stores) {
                        return $stores[$value] ?? '門店遺失';
                    });
            }

            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
            })->align('center');

            $grid->setForm()->drawer($this->form());
        });
    }

    /**
     * 修改
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('dish_order.title'));

            $form->text('order_no', admin_trans('dish_order.fields.order_no'))->disabled();

            $form->hasMany('items', admin_trans('dish_order_item.title'), function ($items) {
                $items->text('dish_title', admin_trans('dish_order_item.fields.dish_title'))->disabled();
                $items->text('price', admin_trans('dish_order_item.fields.price'))->disabled();
                $items->text('quantity', admin_trans('dish_order_item.fields.quantity'))->disabled();
                $items->text('subtotal', admin_trans('dish_order_item.fields.subtotal'))->disabled();
                $items->text('remark', admin_trans('dish_order_item.fields.remark'))->disabled()->placeholder('');
            })
            ->disabled();

            $form->radio('status', admin_trans('dish_order.fields.status'))
                ->button()
                ->required()
                ->options([
                        DishOrder::STATUS_PENDING => admin_trans('dish_order.status.' . DishOrder::STATUS_PENDING),
                        DishOrder::STATUS_CONFIRMED => admin_trans('dish_order.status.' . DishOrder::STATUS_CONFIRMED),
                        DishOrder::STATUS_COOKING => admin_trans('dish_order.status.' . DishOrder::STATUS_COOKING),
                        DishOrder::STATUS_COMPLETED => admin_trans('dish_order.status.' . DishOrder::STATUS_COMPLETED),
                        DishOrder::STATUS_CANCELLED => admin_trans('dish_order.status.' . DishOrder::STATUS_CANCELLED)
                ]);
        });
    }

    /**
     * 門店清單
     * @return array
     */
    public function getStores(int $type): array
    {
        $adminUser = AdminUser::query()->where('type', 4);

        switch ($type) {
            case '2':  // 渠道/部門
                $adminUser->where('department_id', Admin::user()->department_id);
                break;

            case '3':  // 代理
                $adminUser->where('parent_admin_id', Admin::user()->id);
                break;
        }

        $adminUser = $adminUser->pluck('nickname','id')->toArray();

        return $adminUser;
    }

    /**
     * 組裝訂單明細
     * @return array
     */
    public function htmlItems($items)
    {
        $html = '<table>';
        $html .= '<tr>';
        $html .= '<th></th>';
        $html .= '<th></th>';
        $html .= '</tr>';

        foreach ($items as $value) {
            $html .= '<tr>';
            $html .= '<td>' . $value->dish_title . '</td>';
            $html .= '<td>x ' . $value->quantity . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

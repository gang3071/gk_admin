<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\DishCategory;
use addons\webman\model\DishOrder;
use addons\webman\model\DishOrderItem;
use addons\webman\model\PlayerPointsRecord;
use addons\webman\service\PlayerPointsService;
use ExAdmin\ui\component\common\Copy;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use support\Db;

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
        $stores = self::getStores();

        return Grid::create(new $this->model, function (Grid $grid) use ($stores)  {
            $grid->title(admin_trans('dish_order.title'));
            $grid->hideAdd();
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->model()->whereIn('admin_user_id', array_keys($stores));
            $grid->model()->with('items')->orderBy('id', 'desc');

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($stores)  {
                $filter->like()->text('order_no')->placeholder(admin_trans('dish_order.fields.order_no'));
                $filter->like()->text('player.name')->placeholder(admin_trans('dish_order.fields.player_id'));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish_order.fields.status'))
                    ->options(DishOrder::getStatusDescription());

                $filter->eq()->select('admin_user_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish.fields.admin_user_id'))
                    ->options($stores);
            });

            $grid->column('id', admin_trans('dish_order.fields.id'))->align('center');
            $grid->column('order_no', admin_trans('dish_order.fields.order_no'))->align('center');
            $grid->column('device.device_name', admin_trans('dish_order.fields.device_id'))->align('center');
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
            $grid->column('admin_user_id', admin_trans('dish_order.fields.admin_user_id'))->align('center')
                ->display(function ($value) use ($stores) {
                    return $stores[$value] ?? '門店遺失';
                });

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });

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

            $form->text('order_no', admin_trans('dish_order.fields.order_no'))->attr('readonly', true)->addonAfter(Copy::create($form->input('order_no')));
            $form->text('device.device_name', admin_trans('dish_order.fields.device_id'))->attr('readonly', true)->placeholder('');
            $form->text('player.name', admin_trans('dish_order.fields.player_id'))->attr('readonly', true)->placeholder('');

            $form->hasMany('items', admin_trans('dish_order_item.title'), function ($items) {
                $string = $items->form()->input('dish_title');
                $remark = $items->form()->input('remark');

                if (! empty($remark)) {
                    $string .= ' (' . $remark . ')';
                }

                $items->form()->input('string', $string);
                $items->desc('string', admin_trans('dish_order_item.fields.dish_title'));
                $items->desc('price', admin_trans('dish_order_item.fields.price'))->style(['width' => '80px']);
                $items->desc('quantity', admin_trans('dish_order_item.fields.quantity'))->style(['width' => '60px']);
                $items->desc('subtotal', admin_trans('dish_order_item.fields.subtotal'))->style(['width' => '80px']);
            })
            ->table()
            ->disabled();

            $form->divider();

            if (in_array($form->input('status'), [DishOrder::STATUS_COMPLETED, DishOrder::STATUS_CANCELLED])) {
                $form->radio('status', admin_trans('dish_order.fields.status'))
                    ->disabled()
                    ->button()
                    ->required()
                    ->options(DishOrder::getStatusDescription());

                $form->actions()->hideSubmitButton();
                $form->actions()->hideResetButton();
            } else {
                $form->radio('status', admin_trans('dish_order.fields.status'))
                    ->button()
                    ->required()
                    ->options(DishOrder::getStatusDescription());
            }

            // 取消訂單時退積分
            $form->saving(function (Form $form) {
                $newStatus = $form->input('status');
                $orderId = (int)$form->input('id');

                if ($newStatus != DishOrder::STATUS_CANCELLED || empty($orderId)) {
                    return;
                }

                Db::beginTransaction();
                try {
                    // 使用悲觀鎖（FOR UPDATE）防止並發取消，鎖內再檢查狀態避免競態
                    $order = DishOrder::query()
                        ->where('id', $orderId)
                        ->lockForUpdate()
                        ->first();

                    if (!$order || $order->status == DishOrder::STATUS_CANCELLED) {
                        Db::rollBack();
                        return;
                    }

                    // 在鎖內更新狀態為已取消
                    $order->status = DishOrder::STATUS_CANCELLED;
                    $order->save();

                    // 退款總額大於 0 才退積分
                    $pointsToReturn = (int)$order->total_amount;
                    if ($pointsToReturn > 0) {
                        $admin = Admin::user();
                        $adminInfo = [
                            'admin_id' => $admin['id'] ?? 0,
                            'admin_name' => $admin['nickname'] ?? admin_trans('admin.system'),
                            'admin_ip' => request()->getRealIp(),
                        ];

                        PlayerPointsService::addPoints(
                            (int)$order->player_id,
                            $pointsToReturn,
                            admin_trans('dish_order.cancel_refund') . ' ' . $order->order_no,
                            $adminInfo,
                            PlayerPointsRecord::TYPE_REFUND,
                            PlayerPointsRecord::SOURCE_REFUND
                        );
                    }

                    Db::commit();
                } catch (\Throwable $e) {
                    Db::rollBack();
                    throw $e;
                }
            });
        });
    }

    /**
     * 餐點明細報表
     * @auth true
     */
    public function reportItem(): Grid
    {
        $categories = self::getCategories();
        $stores = self::getStores();

        return Grid::create(new DishOrderItem(), function (Grid $grid) use ($categories, $stores) {
            $grid->title(admin_trans('dish_order.reportItem.title'));
            $grid->hideAdd();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->export('DishOrderItemReport' . date('ymdHis'));

            $grid->model()
                ->join('dish_order', 'dish_order.id', '=', 'dish_order_item.order_id')
                ->selectRaw(implode(', ', [
                    'MIN(dish_order_item.id) AS id',
                    'dish_order_item.dish_id',
                    'MIN(dish_order_item.dish_title) AS dish_title',
                    'dish_order_item.price AS price',
                    'SUM(dish_order_item.quantity) AS quantity',
                    'SUM(dish_order_item.subtotal) AS subtotal',
                    'dish_order.admin_user_id',
                ]))
                ->groupBy('dish_order_item.dish_id', 'dish_order_item.price', 'dish_order.admin_user_id')
                ->orderBy('dish_order.admin_user_id', 'asc')
                ->orderBy('dish_order_item.dish_id', 'asc');

            $grid->model()
                ->whereIn('dish_order.admin_user_id', array_keys($stores))
                ->where('dish_order.status', DishOrder::STATUS_COMPLETED);

            $exAdminFilter = Request::input('ex_admin_filter', []);

            if (! empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('dish_order.created_at', '>=', $exAdminFilter['created_at_start']);
            }

            if (! empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('dish_order.created_at', '<=', $exAdminFilter['created_at_end']);
            }

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($categories, $stores)  {
                $filter->like()->text('dish_title')->placeholder(admin_trans('dish_order_item.fields.dish_title'));

                $filter->eq()->select('dish.category_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish.fields.category_id'))
                    ->options($categories);

                $filter->eq()->select('order.admin_user_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('dish_order.fields.admin_user_id'))
                    ->options($stores);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')
                    ->placeholder([admin_trans('public_msg.created_at_start'), admin_trans('public_msg.created_at_end')]);
            });

            $grid->column('dish_title', admin_trans('dish_order_item.fields.dish_title'))->align('center');
            $grid->column('dish.category_id', admin_trans('dish.fields.category_id'))->align('center')
                ->display(function ($value) use ($categories) {
                        return $categories[$value] ?? '類別遺失';
                    });
            $grid->column('price', admin_trans('dish_order_item.fields.price'))->align('center');
            $grid->column('quantity', admin_trans('dish_order.reportItem.quantity'))->align('center');
            $grid->column('subtotal', admin_trans('dish_order.reportItem.subtotal'))->align('center');
            $grid->column('admin_user_id', admin_trans('dish_order.fields.admin_user_id'))->align('center')
                ->display(function ($value) use ($stores) {
                    return $stores[$value] ?? '門店遺失';
                });

            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 門店清單
     * @return array
     */
    public function getStores(): array
    {
        $stores = AdminUser::query()
            ->where('type', 4)
            ->pluck('nickname','id')
            ->toArray();

        return $stores;
    }

    /**
     * 類別清單
     * @return array
     */
    public function getCategories(): array
    {
        $dishCategory = DishCategory::query()
            ->orderBy('top', 'desc')
            ->orderBy('sort', 'desc')
            ->pluck('title','id')
            ->toArray();

        return $dishCategory;
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
            $html .= '<td>' . $value->dish_title;

            if (! empty($value->remark)) {
                $html .= ' (' . $value->remark . ')';
            }

            $html .= '</td>';
            $html .= '<td>x ' . $value->quantity . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

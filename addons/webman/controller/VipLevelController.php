<?php

namespace addons\webman\controller;

use addons\webman\model\GamePlatform;
use addons\webman\model\VipLevel;
use addons\webman\model\VipLevelCashback;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use support\Db;
use support\Log;

/**
 * VIP等级管理
 */
class VipLevelController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.vip_level_model');
        Log::info('VipLevelController __construct model: ' . ($this->model ?: 'null'));
    }

    /**
     * VIP等级列表
     * @auth true
     */
    public function index(): Grid
    {
        Log::info('VipLevelController index start');
        try {
            $model = new $this->model();
            Log::info('VipLevelController index model created: ' . get_class($model));
            $grid = Grid::create($model, function (Grid $grid) {
                Log::info('VipLevelController index grid callback');
                $grid->title(admin_trans('vip_level.title'));
                $grid->autoHeight();
                $grid->bordered(true);
                $grid->model()->orderBy('sort', 'asc')->orderBy('id', 'asc');
                $grid->column('id', admin_trans('vip_level.fields.id'))->width(80)->align('center');
                $grid->column('name', admin_trans('vip_level.fields.name'))->align('center');
                $grid->column('upgrade_limit_days', admin_trans('vip_level.fields.upgrade_limit_days'))->align('center');
                $grid->column('retain_level_days', admin_trans('vip_level.fields.retain_level_days'))->align('center');
                $grid->column('retain_level_bet_amount', admin_trans('vip_level.fields.retain_level_bet_amount'))->align('center');
                $grid->column('upgrade_bet_amount', admin_trans('vip_level.fields.upgrade_bet_amount'))->align('center');
                $grid->column('min_claim_amount', admin_trans('vip_level.fields.min_claim_amount'))->align('center');
                $grid->column('birthday_bonus', admin_trans('vip_level.fields.birthday_bonus'))->align('center');
                $grid->column('sort', admin_trans('vip_level.fields.sort'))->sortable()->width(80)->align('center');
                $grid->column('status', admin_trans('vip_level.fields.status'))
                    ->display(function ($value) {
                        return Tag::create($value ? admin_trans('vip_level.status.1') : admin_trans('vip_level.status.0'))
                            ->color($value ? '#87d068' : '#f50');
                    })->align('center');
                $grid->setForm()->drawer($this->form());
                $grid->actions(function (Actions $actions, $data) {
                    try {
                        Log::info('VipLevelController actions for id: ' . $data['id']);
                        $cashbackForm = $this->cashback($data['id']);
                        Log::info('VipLevelController actions cashbackForm type: ' . get_class($cashbackForm));
                        $actions->dropdown()
                            ->prepend(admin_trans('vip_level.cashback'), 'fas fa-percentage')
                            ->modal($cashbackForm)
                            ->title(admin_trans('vip_level.cashback_title', ['name' => $data['name']]));
                        Log::info('VipLevelController actions dropdown set success');
                    } catch (\Exception $e) {
                        Log::error('VipLevelController actions cashback error: ' . $e->getMessage());
                        Log::error('VipLevelController actions cashback trace: ' . $e->getTraceAsString());
                    }
                });
                $grid->filter(function (Filter $filter) {
                    $filter->like('name', admin_trans('vip_level.fields.name'));
                });
                $grid->expandFilter();
            });
            Log::info('VipLevelController index grid created, type: ' . get_class($grid));
            return $grid;
        } catch (\Exception $e) {
            Log::error('VipLevelController index error: ' . $e->getMessage());
            Log::error('VipLevelController index trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * VIP等级表单
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Log::info('VipLevelController form start');
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('vip_level.title'));
            $form->labelWidth(180);
            $form->text('name', admin_trans('vip_level.fields.name'))->required()->maxlength(50);
            $form->number('upgrade_limit_days', admin_trans('vip_level.fields.upgrade_limit_days'))
                ->min(0)
                ->help(admin_trans('vip_level.help.upgrade_limit_days'));
            $form->number('retain_level_days', admin_trans('vip_level.fields.retain_level_days'))
                ->min(0)
                ->help(admin_trans('vip_level.help.retain_level_days'));
            $form->number('retain_level_bet_amount', admin_trans('vip_level.fields.retain_level_bet_amount'))
                ->min(0)
                ->step(0.01)
                ->help(admin_trans('vip_level.help.retain_level_bet_amount'));
            $form->number('upgrade_bet_amount', admin_trans('vip_level.fields.upgrade_bet_amount'))
                ->min(0)
                ->step(0.01)
                ->help(admin_trans('vip_level.help.upgrade_bet_amount'));
            $form->number('min_claim_amount', admin_trans('vip_level.fields.min_claim_amount'))
                ->min(0)
                ->step(0.01)
                ->help(admin_trans('vip_level.help.min_claim_amount'));
            $form->number('birthday_bonus', admin_trans('vip_level.fields.birthday_bonus'))
                ->min(0)
                ->step(0.01)
                ->help(admin_trans('vip_level.help.birthday_bonus'));
            $form->number('sort', admin_trans('vip_level.fields.sort'))
                ->min(0)
                ->default(0)
                ->help(admin_trans('vip_level.help.sort'));
            $form->switch('status', admin_trans('vip_level.fields.status'))
                ->activeValue(1)
                ->inactiveValue(0)
                ->default(1);
        });
    }

    /**
     * 反水比例设置
     * @auth true
     * @param int $id
     * @return Form
     */
    public function cashback(int $id): Form
    {
        Log::info('VipLevelController cashback start, id: ' . $id);
        try {
            $vipLevel = VipLevel::query()->find($id);
            Log::info('VipLevelController cashback vipLevel: ' . ($vipLevel ? $vipLevel->name : 'null'));

            // 获取所有启用的游戏平台
            $platforms = GamePlatform::query()
                ->where('status', 1)
                ->orderBy('id', 'asc')
                ->get();
            Log::info('VipLevelController cashback platforms count: ' . $platforms->count());

            // 获取当前VIP等级已有的反水配置
            $existingCashbacks = VipLevelCashback::query()
                ->where('vip_level_id', $id)
                ->pluck('cashback_ratio', 'platform_id')
                ->toArray();
            Log::info('VipLevelController cashback existingCashbacks: ' . json_encode($existingCashbacks));

            Log::info('VipLevelController cashback about to create Form');
            $result = Form::create(new VipLevelCashback(), function (Form $form) use ($id, $vipLevel, $platforms, $existingCashbacks) {
                Log::info('VipLevelController cashback form callback executed');
                $form->title(admin_trans('vip_level.cashback_title', ['name' => $vipLevel->name]));
                $form->labelWidth(180);

                // 为每个平台创建一个输入框
                foreach ($platforms as $platform) {
                    $default = isset($existingCashbacks[$platform->id]) ? $existingCashbacks[$platform->id] * 100 : 0;
                    $form->number("cashback_{$platform->id}", $platform->name)
                        ->default($default)
                        ->min(0)
                        ->max(100)
                        ->step(0.01)
                        ->help(admin_trans('vip_level.help.cashback_ratio'));
                }

                // 保存逻辑
                $form->saving(function (Form $form) use ($id) {
                    $data = $form->input();
                    $cashbacks = [];

                    foreach ($data as $key => $value) {
                        if (strpos($key, 'cashback_') === 0) {
                            $platformId = str_replace('cashback_', '', $key);
                            $cashbacks[$platformId] = $value;
                        }
                    }

                    Db::transaction(function () use ($id, $cashbacks) {
                        // 删除原有的反水配置
                        VipLevelCashback::query()
                            ->where('vip_level_id', $id)
                            ->delete();

                        // 保存新的反水配置
                        foreach ($cashbacks as $platformId => $ratio) {
                            if ($ratio > 0) {
                                VipLevelCashback::query()->create([
                                    'vip_level_id' => $id,
                                    'platform_id' => $platformId,
                                    'cashback_ratio' => $ratio / 100, // 转换为小数
                                    'status' => 1,
                                ]);
                            }
                        }
                    });

                    return $data;
                });
            });
            Log::info('VipLevelController cashback Form created successfully');
            return $result;
        } catch (\Exception $e) {
            Log::error('VipLevelController cashback error: ' . $e->getMessage());
            Log::error('VipLevelController cashback trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}

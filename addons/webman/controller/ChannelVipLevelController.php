<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GamePlatform;
use addons\webman\model\VipLevel;
use addons\webman\model\VipLevelCashback;
use addons\webman\service\VipLevelService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use support\Log;

/**
 * 渠道后台 - VIP等级管理
 */
class ChannelVipLevelController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.vip_level_model');
    }

    /**
     * VIP等级列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('vip_level.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 隐藏添加按钮和清空数据按钮
            $grid->hideCreateButton();
            $grid->hideClearButton();

            // 只显示当前渠道的VIP等级
            $departmentId = Admin::user()->department_id;

            // 添加"一键导入模板"按钮
            $vipCount = VipLevel::query()
                ->where('department_id', $departmentId)
                ->count();

            if ($vipCount === 0) {
                // 如果没有VIP等级，显示导入按钮
                $grid->addTopButton(
                    Button::create(admin_trans('vip_level.import_template'))
                        ->icon(Icon::create('DownloadOutlined'))
                        ->type('primary')
                        ->api(admin_url([$this, 'importTemplate']))
                        ->confirm(admin_trans('vip_level.import_confirm'))
                );
            }
            $grid->model()
                ->where('department_id', $departmentId)
                ->orderBy('sort', 'asc')
                ->orderBy('id', 'asc');

            $grid->column('id', admin_trans('vip_level.fields.id'))->width(80)->align('center');
            $grid->column('name', admin_trans('vip_level.fields.name'))->align('center');
            $grid->column('retain_level_days', admin_trans('vip_level.fields.retain_level_days'))->align('center');
            $grid->column('retain_level_bet_amount', admin_trans('vip_level.fields.retain_level_bet_amount'))->align('center');
            $grid->column('upgrade_bet_amount', admin_trans('vip_level.fields.upgrade_bet_amount'))->align('center');
            $grid->column('min_claim_amount', admin_trans('vip_level.fields.min_claim_amount'))->align('center');
            $grid->column('birthday_bonus', admin_trans('vip_level.fields.birthday_bonus'))->align('center');
            $grid->column('sort', admin_trans('vip_level.fields.sort'))->sortable()->width(80)->align('center');

            $grid->actions(function (Actions $actions, $data) {
                $actions->prepend(
                    Button::create(admin_trans('vip_level.cashback'))
                        ->icon(Icon::create('MoneyCollectOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->drawer([$this, 'cashback'], ['vip_level_id' => $data['id']])
                        ->width('60%')
                        ->title($data['name'] . ' - ' . admin_trans('vip_level.cashback'))
                );
            });

            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->like('name', admin_trans('vip_level.fields.name'));
            });
            $grid->expandFilter();
        });
    }

    /**
     * VIP等级表单
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('vip_level.title'));
            $form->labelWidth(180);

            // 隐藏字段：自动设置当前渠道department_id
            $form->hidden('department_id')->default(Admin::user()->department_id);

            $form->text('name', admin_trans('vip_level.fields.name'))->required()->maxlength(50);
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
        });
    }

    /**
     * VIP等级反水比例设置
     * @auth true
     * @param int $vip_level_id
     * @return Form
     */
    public function cashback(int $vip_level_id): Form
    {
        $vipLevel = VipLevel::query()->find($vip_level_id);
        $platforms = GamePlatform::query()->where('status', 1)->orderBy('sort', 'desc')->orderBy('id', 'asc')->get();
        $existingCashbacks = VipLevelCashback::query()->where('vip_level_id', $vip_level_id)->pluck('cashback_ratio', 'platform_id')->toArray();

        return Form::create([], function (Form $form) use ($vipLevel, $platforms, $existingCashbacks) {
            $form->title(($vipLevel->name ?? '') . ' - ' . admin_trans('vip_level.cashback'));
            $form->labelWidth(180);

            foreach ($platforms as $platform) {
                $form->number('cashback_' . $platform->id, $platform->name)
                    ->min(0)
                    ->max(100)
                    ->step(0.01)
                    ->value($existingCashbacks[$platform->id] ?? 0)
                    ->help(admin_trans('vip_level.help.cashback_ratio'));
            }

            $form->saved(function (Form $form) {
                try {
                    $vipLevelId = request()->post('vip_level_id', 0);
                    $data = request()->post('data', []);
                    $platforms = GamePlatform::query()->where('status', 1)->pluck('id');

                    foreach ($platforms as $platformId) {
                        $key = 'cashback_' . $platformId;
                        if (!isset($data[$key])) {
                            continue;
                        }

                        $ratio = floatval($data[$key]);
                        VipLevelCashback::updateOrCreate(
                            ['vip_level_id' => $vipLevelId, 'platform_id' => $platformId],
                            ['cashback_ratio' => $ratio, 'status' => 1]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('VIP反水比例保存失败: ' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 一键导入VIP默认模板
     * @auth true
     * @return mixed
     */
    public function importTemplate()
    {
        $departmentId = Admin::user()->department_id;

        // 检查是否已有VIP等级
        $existingCount = VipLevel::query()
            ->where('department_id', $departmentId)
            ->count();

        if ($existingCount > 0) {
            return jsonFailResponse(
                admin_trans('vip_level.import_error_exists', null, ['count' => $existingCount]),
                [],
                400
            );
        }

        // 调用服务创建默认VIP等级
        $result = VipLevelService::createDefaultLevelsForChannel($departmentId);

        if ($result['success']) {
            Log::info('渠道管理员手动导入VIP模板成功', [
                'department_id' => $departmentId,
                'admin_id' => Admin::id(),
                'count' => $result['count']
            ]);

            return jsonSuccessResponse($result['message'], [
                'count' => $result['count']
            ]);
        } else {
            return jsonFailResponse($result['message'], [], 500);
        }
    }
}

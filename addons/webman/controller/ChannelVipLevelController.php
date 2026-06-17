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
 * @group channel
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

            // 只显示当前渠道的VIP等级
            $departmentId = Admin::user()->department_id;

            // 检查VIP等级数量
            $vipCount = VipLevel::query()
                ->where('department_id', $departmentId)
                ->count();

            // 调试信息
            Log::info('VIP等级按钮显示调试', [
                'department_id' => $departmentId,
                'vip_count' => $vipCount,
                'admin_user_id' => Admin::id(),
            ]);

            // 隐藏添加按钮和清空数据按钮
            $grid->hideAdd();
            $grid->hideDelete();

            // 导入VIP等级按钮（始终显示，方便重新导入）
            if ($vipCount === 0) {
                $buttonText = admin_trans('vip_level.import_template');
                $confirmText = admin_trans('vip_level.import_confirm');
                $buttonType = 'primary';
            } else {
                $buttonText = admin_trans('vip_level.import_template') . ' ' . admin_trans('vip_level.already_exists_count', null, ['count' => $vipCount]);
                $confirmText = admin_trans('vip_level.import_error_exists', null, ['count' => $vipCount]);
                $buttonType = 'default';
            }

            $importButton = Button::create($buttonText)
                ->icon(Icon::create('DownloadOutlined'))
                ->type($buttonType)
                ->confirm(
                    $confirmText,
                    [$this, 'importTemplate'],
                    [],
                    'POST'
                );

            $grid->tools($importButton);

            // 同步玩家VIP等级按钮（只在有VIP等级且有未同步玩家时显示）
            if ($vipCount > 0) {
                // 检查是否有需要同步的玩家（vip_level_id为NULL或0的玩家）
                $playerModel = plugin()->webman->config('database.player_model');
                $playersNeedSyncCount = $playerModel::query()
                    ->where('department_id', $departmentId)
                    ->where(function ($query) {
                        $query->whereNull('vip_level_id')
                              ->orWhere('vip_level_id', 0);
                    })
                    ->count();

                // 调试信息
                Log::info('同步玩家等级按钮调试', [
                    'department_id' => $departmentId,
                    'players_need_sync_count' => $playersNeedSyncCount,
                ]);

                if ($playersNeedSyncCount > 0) {
                    $syncButton = Button::create(admin_trans('vip_level.sync_players'))
                        ->icon(Icon::create('SyncOutlined'))
                        ->type('default')
                        ->confirm(
                            admin_trans('vip_level.sync_players_confirm', null, ['count' => $playersNeedSyncCount]),
                            [$this, 'syncPlayers'],
                            [],
                            'POST'
                        );

                    $grid->tools($syncButton);
                }
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
                        ->width(500)
                        ->title($data['name'] . ' - ' . admin_trans('vip_level.cashback') . '（100=100%，0.1=0.1%）')
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

            $form->text('name', admin_trans('vip_level.fields.name'))
                ->required()
                ->maxlength(50)
                ->placeholder(admin_trans('vip_level.placeholder.name'))
                ->prefix(Icon::create('CrownOutlined'))
                ->style(['width' => '120px'])
                ->help(admin_trans('vip_level.help.name'));
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
            $form->title(($vipLevel->name ?? '') . ' - ' . admin_trans('vip_level.cashback') . '（100=100%，0.1=0.1%）');
            $form->labelWidth(180);

            foreach ($platforms as $platform) {
                $form->number('cashback_' . $platform->id, $platform->name)
                    ->min(0)
                    ->max(100)
                    ->step(0.01)
                    ->value($existingCashbacks[$platform->id] ?? 0);
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
                    Log::error('VIP cashback save failed: ' . $e->getMessage(), [
                        'vip_level_id' => $vipLevelId ?? 0,
                        'error' => $e->getMessage(),
                    ]);
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
        try {
            $departmentId = Admin::user()->department_id;

            Log::info('Start importing VIP template', [
                'department_id' => $departmentId,
                'admin_id' => Admin::id(),
            ]);

            // 检查是否已有VIP等级
            $existingCount = VipLevel::query()
                ->where('department_id', $departmentId)
                ->count();

            if ($existingCount > 0) {
                Log::warning('VIP levels already exist, cannot import', [
                    'department_id' => $departmentId,
                    'existing_count' => $existingCount,
                ]);

                return jsonFailResponse(
                    admin_trans('vip_level.import_error_exists', null, ['count' => $existingCount]),
                    [],
                    400
                );
            }

            // 调用服务创建默认VIP等级
            $result = VipLevelService::createDefaultLevelsForChannel($departmentId);

            if ($result['success']) {
                Log::info('Channel admin manually imported VIP template successfully', [
                    'department_id' => $departmentId,
                    'admin_id' => Admin::id(),
                    'count' => $result['count']
                ]);

                return jsonSuccessResponse($result['message'], [
                    'count' => $result['count']
                ]);
            } else {
                Log::error('VIP template import failed', [
                    'department_id' => $departmentId,
                    'error' => $result['message']
                ]);

                return jsonFailResponse($result['message'], [], 500);
            }
        } catch (\Throwable $e) {
            Log::error('VIP template import exception', [
                'department_id' => $departmentId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return jsonFailResponse(admin_trans('vip_level.import_failed') . $e->getMessage(), [], 500);
        }
    }

    /**
     * 同步玩家VIP等级
     * @auth true
     * @return mixed
     */
    public function syncPlayers()
    {
        try {
            $departmentId = Admin::user()->department_id;

            Log::info('Start syncing players VIP level', [
                'department_id' => $departmentId,
                'admin_id' => Admin::id(),
            ]);

            // 调用服务同步玩家VIP等级
            $result = VipLevelService::syncPlayersVipLevel($departmentId);

            if ($result['success']) {
                Log::info('Channel admin synced players VIP level successfully', [
                    'department_id' => $departmentId,
                    'admin_id' => Admin::id(),
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped']
                ]);

                return jsonSuccessResponse($result['message'], [
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped']
                ]);
            } else {
                Log::error('Players VIP level sync failed', [
                    'department_id' => $departmentId,
                    'error' => $result['message']
                ]);

                return jsonFailResponse($result['message'], [], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Players VIP level sync exception', [
                'department_id' => $departmentId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return jsonFailResponse(admin_trans('vip_level.sync_failed') . $e->getMessage(), [], 500);
        }
    }
}

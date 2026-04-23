<?php

namespace addons\webman\controller;

use addons\webman\model\GamePlatform;
use addons\webman\model\PlatformLimitGroup;
use addons\webman\model\PlatformLimitGroupConfig;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;

/**
 * 限红组平台配置管理（总后台）
 */
class PlatformLimitGroupConfigController
{
    protected $model;

    public function __construct()
    {
        $this->model = PlatformLimitGroupConfig::class;
    }

    /**
     * 限红组平台配置列表
     * @auth true
     */
    public function index(): Grid
    {
        $limitGroupId = Request::input('limit_group_id');

        return Grid::create(new $this->model(), function (Grid $grid) use ($limitGroupId) {
            if ($limitGroupId) {
                $grid->model()->where('limit_group_id', $limitGroupId);
            }
            $grid->model()->with(['limitGroup', 'gamePlatform'])->orderBy('id', 'desc');

            $grid->title('限红组平台配置');
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->column('id', 'ID')->align('center')->width(80);

            $grid->column('limit_group_id', '限红组')->display(function ($value, $data) {
                if (isset($data['limitGroup']) && $data['limitGroup']) {
                    return $data['limitGroup']['name'];
                }
                if (isset($data['limit_group']) && $data['limit_group']) {
                    return $data['limit_group']['name'];
                }
                return '-';
            })->align('center');

            $grid->column('platform_id', '游戏平台')->display(function ($value, $data) {
                if (isset($data['gamePlatform']) && $data['gamePlatform']) {
                    return $data['gamePlatform']['name'];
                }
                if (isset($data['game_platform']) && $data['game_platform']) {
                    return $data['game_platform']['name'];
                }
                return '-';
            })->align('center');

            $grid->column('platform_code', '平台代码')->align('center');

            $grid->column('config_data', '配置数据')->display(function ($value) {
                if (empty($value)) {
                    return '-';
                }
                $configText = [];
                // ATG平台显示营运账号
                if (isset($value['operator'])) {
                    $configText[] = "营运账号: {$value['operator']}";
                }
                // RSG平台显示限红范围
                if (isset($value['min_bet_amount']) || isset($value['max_bet_amount'])) {
                    $min = $value['min_bet_amount'] ?? 0;
                    $max = $value['max_bet_amount'] ?? 0;
                    $configText[] = "限红范围: {$min} - {$max}";
                }
                // DG平台显示限红范围
                if (isset($value['min']) || isset($value['max'])) {
                    $min = $value['min'] ?? 0;
                    $max = $value['max'] ?? 0;
                    $configText[] = "限红范围: {$min} - {$max}";
                }
                return implode('<br>', $configText);
            })->align('center');

            $grid->column('status', '状态')->display(function ($value) {
                return $value == 1
                    ? Tag::create('启用')->color('success')
                    : Tag::create('禁用')->color('default');
            })->align('center');

            $grid->column('created_at', '创建时间')->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('limit_group_id', '限红组')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-PlatformLimitGroupConfigController',
                        'getLimitGroupOptions'
                    ]));
                $filter->eq()->select('platform_id', '游戏平台')
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->eq()->select('status')->placeholder('状态')->options([
                    1 => '启用',
                    0 => '禁用'
                ]);
            });
            $grid->expandFilter();

            $grid->setForm()->drawer($this->form());

            $grid->actions(function (Actions $actions) {
                // 可以添加其他操作
            })->align('center');
        });
    }

    /**
     * 限红组平台配置表单
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title($form->isEdit() ? '编辑平台配置' : '新建平台配置');

            $form->select('limit_group_id', '限红组')
                ->options($this->getLimitGroupOptionsArray())
                ->required()
                ->disabled($form->isEdit());

            // 获取ATG、RSG和DG平台ID
            $atgPlatform = GamePlatform::query()->where('code', 'ATG')->where('status', 1)->first();
            $rsgPlatform = GamePlatform::query()->where('code', 'RSG')->where('status', 1)->first();
            $dgPlatform = GamePlatform::query()->where('code', 'DG')->where('status', 1)->first();

            // 编辑时获取config_data（已经是数组类型，无需json_decode）
            $configData = [];
            if ($form->isEdit()) {
                $configData = $form->driver()->get('config_data') ?: [];
            }

            // 游戏平台选择，使用 when 实现条件显示
            $platformSelect = $form->select('platform_id', '游戏平台')
                ->options($this->getGamePlatformOptionsArray())
                ->required()
                ->disabled($form->isEdit())
                ->help('请选择游戏平台，选择后将显示对应平台的配置项');

            // ATG平台配置（当选择ATG时显示）
            if ($atgPlatform) {
                $platformSelect->when($atgPlatform->id, function (Form $form) use ($configData) {
                    $form->divider('ATG 平台配置');

                    $form->text('config_data.operator', 'X-Operator')
                        ->required()
                        ->value($configData['operator'] ?? '')
                        ->help('ATG平台营运账号的Operator值')
                        ->placeholder('请输入Operator');

                    $form->password('config_data.operator_key', 'X-Key')
                        ->required()
                        ->value($configData['operator_key'] ?? '')
                        ->help('ATG平台营运账号的Key值（加密存储）')
                        ->placeholder('请输入Key');

                    $form->text('config_data.provider_id', 'Provider ID')
                        ->default('4')
                        ->value($configData['provider_id'] ?? '4')
                        ->help('ATG提供商ID，默认为4')
                        ->placeholder('4');

                    $form->text('config_data.api_domain', 'API域名')
                        ->value($configData['api_domain'] ?? '')
                        ->help('可选，留空使用默认配置')
                        ->placeholder('https://api.atg.com');

                    $form->textarea('config_data.limit_description', '限红说明')
                        ->rows(3)
                        ->value($configData['limit_description'] ?? '')
                        ->help('描述该营运账号的限红设置')
                        ->placeholder('例如：单注：100-10000，单日限额：100000');
                });
            }

            // RSG平台配置（当选择RSG时显示）
            if ($rsgPlatform) {
                $platformSelect->when($rsgPlatform->id, function (Form $form) use ($configData) {
                    $form->divider('RSG 平台配置');

                    $form->number('config_data.min_bet_amount', '最小下注金额')
                        ->required()
                        ->precision(2)
                        ->min(0)
                        ->value($configData['min_bet_amount'] ?? '')
                        ->help('RSG平台最小下注金额')
                        ->placeholder('例如：1.00');

                    $form->number('config_data.max_bet_amount', '最大下注金额')
                        ->required()
                        ->precision(2)
                        ->min(0)
                        ->value($configData['max_bet_amount'] ?? '')
                        ->help('RSG平台最大下注金额')
                        ->placeholder('例如：10000.00');
                });
            }

            // DG平台配置（当选择DG时显示）
            if ($dgPlatform) {
                $platformSelect->when($dgPlatform->id, function (Form $form) use ($configData) {
                    $form->divider('DG 平台配置');

                    $form->number('config_data.min', '最小下注金额')
                        ->required()
                        ->precision(2)
                        ->min(10)
                        ->value($configData['min'] ?? '')
                        ->help('DG平台最小下注金额，不能低于10')
                        ->placeholder('例如：10.00');

                    $form->number('config_data.max', '最大下注金额')
                        ->required()
                        ->precision(2)
                        ->min(10)
                        ->value($configData['max'] ?? '')
                        ->help('DG平台最大下注金额')
                        ->placeholder('例如：10000.00');
                });
            }

            $form->radio('status', '状态')
                ->options([
                    1 => '启用',
                    0 => '禁用',
                ])
                ->default(1)
                ->required();

            // 自动设置platform_code并验证字段
            $form->saving(function (Form $form) {
                $limitGroupId = $form->input('limit_group_id');
                $platformId = $form->input('platform_id');
                $platform = GamePlatform::find($platformId);

                if (!$platform) {
                    return message_error('游戏平台不存在');
                }

                // 新建时验证限红组唯一性（每个限红组只能绑定一个配置）
                if (!$form->isEdit()) {
                    $exists = PlatformLimitGroupConfig::query()
                        ->where('limit_group_id', $limitGroupId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        return message_error('该限红组已绑定配置，每个限红组只能绑定一个平台配置');
                    }
                }

                // 设置platform_code
                $form->input('platform_code', $platform->code);

                // 根据平台类型验证必填字段并清理无关字段
                $configData = $form->input('config_data') ?: [];

                if ($platform->code === 'ATG') {
                    // 验证ATG平台必填字段
                    if (empty($configData['operator'])) {
                        return message_error('ATG平台必须填写 X-Operator');
                    }
                    if (empty($configData['operator_key'])) {
                        return message_error('ATG平台必须填写 X-Key');
                    }

                    // 清空RSG字段
                    unset($configData['min_bet_amount']);
                    unset($configData['max_bet_amount']);

                } elseif ($platform->code === 'RSG') {
                    // 验证RSG平台必填字段
                    if (!isset($configData['min_bet_amount']) || $configData['min_bet_amount'] === '') {
                        return message_error('RSG平台必须填写最小下注金额');
                    }
                    if (!isset($configData['max_bet_amount']) || $configData['max_bet_amount'] === '') {
                        return message_error('RSG平台必须填写最大下注金额');
                    }
                    if ($configData['max_bet_amount'] <= $configData['min_bet_amount']) {
                        return message_error('最大下注金额必须大于最小下注金额');
                    }

                    // 清空ATG和DG字段
                    unset($configData['operator']);
                    unset($configData['operator_key']);
                    unset($configData['provider_id']);
                    unset($configData['api_domain']);
                    unset($configData['limit_description']);
                    unset($configData['min']);
                    unset($configData['max']);

                } elseif ($platform->code === 'DG') {
                    // 验证DG平台必填字段
                    if (!isset($configData['min']) || $configData['min'] === '') {
                        return message_error('DG平台必须填写最小下注金额');
                    }
                    if (!isset($configData['max']) || $configData['max'] === '') {
                        return message_error('DG平台必须填写最大下注金额');
                    }
                    // 验证DG平台最小下注金额不能低于10
                    if ($configData['min'] < 10) {
                        return message_error('DG平台最小下注金额不能低于10');
                    }
                    if ($configData['max'] <= $configData['min']) {
                        return message_error('最大下注金额必须大于最小下注金额');
                    }

                    // 清空ATG和RSG字段
                    unset($configData['operator']);
                    unset($configData['operator_key']);
                    unset($configData['provider_id']);
                    unset($configData['api_domain']);
                    unset($configData['limit_description']);
                    unset($configData['min_bet_amount']);
                    unset($configData['max_bet_amount']);
                }

                $form->input('config_data', $configData);
            });

            // 保存/更新后清理缓存
            $form->saved(function (Form $form) {
                $this->clearLimitGroupCache($form);
            });

            // 删除后清理缓存
            $form->deleting(function (Form $form) {
                $this->clearLimitGroupCache($form);
            });
        });
    }

    /**
     * 清理限红组相关缓存
     * @param Form $form
     */
    private function clearLimitGroupCache(Form $form)
    {
        try {
            $redis = \support\Redis::connection('default')->client();

            // 获取平台ID
            $platformId = $form->model()->platform_id ?? $form->input('platform_id');

            if ($platformId) {
                // 1. 清理平台限红组配置缓存
                $platformLimitConfigKey = "platform_limit_configs:{$platformId}";
                $redis->del($platformLimitConfigKey);

                // 2. 清理所有玩家的限红组配置缓存（使用SCAN避免阻塞）
                $pattern = "limit_group_config:{$platformId}:*";
                $iterator = null;
                while (false !== ($keys = $redis->scan($iterator, $pattern, 100))) {
                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                }

                // 3. 清理游戏平台缓存
                $platformCacheKey = "game_platform:*";
                $iterator = null;
                while (false !== ($keys = $redis->scan($iterator, $platformCacheKey, 100))) {
                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                }

                \support\Log::info('限红组配置缓存已清理', [
                    'platform_id' => $platformId,
                    'limit_group_id' => $form->model()->limit_group_id ?? $form->input('limit_group_id'),
                ]);
            }
        } catch (\Exception $e) {
            \support\Log::error('清理限红组缓存失败', [
                'error' => $e->getMessage(),
                'platform_id' => $platformId ?? null,
            ]);
        }
    }

    /**
     * 获取限红组选项（用于筛选器，显示所有限红组）
     * @auth true
     */
    public function getLimitGroupOptions()
    {
        $request = Request::input();
        $query = PlatformLimitGroup::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc');

        if (!empty($request['search'])) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request['search'] . '%')
                  ->orWhere('code', 'like', '%' . $request['search'] . '%');
            });
        }

        $list = $query->get();
        $data = [];

        foreach ($list as $item) {
            $data[] = [
                'value' => $item->id,
                'label' => "{$item->name} ({$item->code})",
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取游戏平台选项（ajax用）
     * 只返回 ATG、RSG 和 DG 平台
     * @auth true
     */
    public function getGamePlatformOptions()
    {
        $request = Request::input();
        $query = GamePlatform::query()
            ->where('status', 1)
            ->whereIn('code', ['ATG', 'RSG', 'DG']) // 只显示 ATG、RSG 和 DG
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($request['search'])) {
            $query->where('name', 'like', '%' . $request['search'] . '%');
        }

        $list = $query->get();
        $data = [];

        foreach ($list as $item) {
            $data[] = [
                'value' => $item->id,
                'label' => "{$item->name} ({$item->code})",
            ];
        }

        return Response::success($data);
    }

    /**
     * 获取游戏平台选项（Form用，返回数组）
     * 只返回 ATG、RSG 和 DG 平台
     */
    private function getGamePlatformOptionsArray(): array
    {
        $data = [];
        $list = GamePlatform::query()
            ->where('status', 1)
            ->whereIn('code', ['ATG', 'RSG', 'DG']) // 只显示 ATG、RSG 和 DG
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($list as $item) {
            $data[$item->id] = "{$item->name} ({$item->code})";
        }

        return $data;
    }

    /**
     * 获取限红组选项（Form用，返回数组）
     */
    private function getLimitGroupOptionsArray(): array
    {
        // 获取已经绑定配置的限红组ID列表
        $usedLimitGroupIds = PlatformLimitGroupConfig::query()
            ->whereNull('deleted_at')
            ->pluck('limit_group_id')
            ->toArray();

        $data = [];
        $list = PlatformLimitGroup::query()
            ->where('status', 1)
            ->whereNotIn('id', $usedLimitGroupIds) // 排除已绑定的限红组
            ->orderBy('sort', 'asc')
            ->get();

        foreach ($list as $item) {
            $data[$item->id] = "{$item->name} ({$item->code})";
        }

        return $data;
    }
}

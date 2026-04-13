<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlatformLimitGroup;
use addons\webman\model\PlatformLimitGroupConfig;
use addons\webman\model\GamePlatform;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use support\Request;

/**
 * 限红组管理（总后台）
 */
class PlatformLimitGroupController
{
    protected $model;

    public function __construct()
    {
        $this->model = PlatformLimitGroup::class;
    }

    /**
     * 限红组列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title('限红组管理');

            $exAdminFilter = request()->input('ex_admin_filter', []);

            // 游戏平台筛选
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->whereHas('configs', function ($query) use ($exAdminFilter) {
                    $query->where('platform_id', $exAdminFilter['platform_id'])
                          ->where('status', 1)
                          ->whereNull('deleted_at');
                });
            }

            $grid->model()->with(['configs.gamePlatform'])->orderBy('sort', 'asc')->orderBy('id', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->column('id', 'ID')->align('center')->width(80);
            $grid->column('code', '限红组编码')->align('center');
            $grid->column('name', '限红组名称')->align('center');

            // 显示平台名称
            $grid->column('platform_name', '游戏平台')->display(function ($value, $data) {
                if (empty($data['configs'])) {
                    return '-';
                }

                $platforms = [];
                foreach ($data['configs'] as $config) {
                    if ($config['status'] != 1) continue;

                    // 优先使用关联的平台名称
                    if (isset($config['game_platform']['name'])) {
                        $platforms[] = $config['game_platform']['name'];
                    } elseif (isset($config['platform_code'])) {
                        $platforms[] = $config['platform_code'];
                    }
                }

                return $platforms ? implode(', ', $platforms) : '-';
            })->align('center');

            // 显示平台配置信息
            $grid->column('platform_config', '配置详情')->display(function ($value, $data) {
                if (empty($data['configs'])) {
                    return '未配置';
                }

                $configInfo = [];
                foreach ($data['configs'] as $config) {
                    if ($config['status'] != 1) continue;

                    $configData = $config['config_data'] ?? [];

                    // ATG平台显示营运账号
                    if ($config['platform_code'] === 'ATG' && isset($configData['operator'])) {
                        $configInfo[] = "营运账号: {$configData['operator']}";
                    }
                    // RSG平台显示限红范围
                    elseif ($config['platform_code'] === 'RSG') {
                        if (isset($configData['min_bet_amount']) && isset($configData['max_bet_amount'])) {
                            $min = $configData['min_bet_amount'];
                            $max = $configData['max_bet_amount'];
                            $configInfo[] = "限红: {$min} - {$max}";
                        }
                    }
                    // DG平台显示限红范围
                    elseif ($config['platform_code'] === 'DG') {
                        if (isset($configData['min']) && isset($configData['max'])) {
                            $min = $configData['min'];
                            $max = $configData['max'];
                            $configInfo[] = "限红: {$min} - {$max}";
                        }
                    }
                    // TNINE平台显示限红范围
                    elseif ($config['platform_code'] === 'TNINE') {
                        if (isset($configData['min_bet_limit']) && isset($configData['max_bet_limit'])) {
                            $min = $configData['min_bet_limit'];
                            $max = $configData['max_bet_limit'];
                            $configInfo[] = "限红: {$min} - {$max}";
                        }
                    }
                }

                return $configInfo ? implode("\n", $configInfo) : '未配置';
            })->align('center')->escape(false);

            $grid->column('status', '状态')->display(function ($value) {
                return $value == 1
                    ? Tag::create('启用')->color('success')
                    : Tag::create('禁用')->color('default');
            })->align('center');

            $grid->sortInput('sort');

            $grid->column('created_at', '创建时间')->display(function ($value) {
                if (empty($value)) {
                    return '-';
                }
                // 格式化为 Y-m-d H:i:s 格式
                return date('Y-m-d H:i:s', strtotime($value));
            })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('code', '限红组编码')
                    ->style(['width' => '200px']);
                $filter->like()->text('name', '限红组名称')
                    ->style(['width' => '200px']);

                // 游戏平台筛选
                $filter->eq()->select('platform_id', '游戏平台')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($this->getGamePlatformOptions());

                $filter->eq()->select('status', '状态')
                    ->style(['width' => '200px'])
                    ->options([
                        1 => '启用',
                        0 => '禁用'
                    ]);
            });
            $grid->expandFilter();

            $grid->setForm()->drawer($this->form());
        });
    }

    /**
     * 获取游戏平台选项（用于筛选）
     */
    private function getGamePlatformOptions(): array
    {
        $platforms = GamePlatform::query()
            ->where('status', 1)
            ->whereIn('code', ['ATG', 'RSG', 'DG', 'TNINE'])
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $options = [];
        foreach ($platforms as $platform) {
            $options[$platform->id] = "{$platform->name} ({$platform->code})";
        }

        return $options;
    }

    /**
     * 限红组表单
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title($form->isEdit() ? '编辑限红组' : '新建限红组');

            $form->text('code', '限红组编码')
                ->maxlength(50)
                ->required()
                ->disabled($form->isEdit())
                ->help('限红组唯一标识，如：A、B、C');

            $form->text('name', '限红组名称')
                ->maxlength(100)
                ->required()
                ->help('如：高额组、中额组、低额组');


            $form->radio('status', '状态')
                ->options([
                    1 => '启用',
                    0 => '禁用',
                ])
                ->default(1)
                ->required();

            $form->number('sort', '排序')
                ->default(0)
                ->help('数值越小越靠前');

            // 平台配置部分
            $form->divider('平台配置');

            // 获取ATG、RSG、DG和TNINE平台
            $atgPlatform = GamePlatform::query()->where('code', 'ATG')->where('status', 1)->first();
            $rsgPlatform = GamePlatform::query()->where('code', 'RSG')->where('status', 1)->first();
            $dgPlatform = GamePlatform::query()->where('code', 'DG')->where('status', 1)->first();
            $tninePlatform = GamePlatform::query()->where('code', 'TNINE')->where('status', 1)->first();

            // 编辑时获取现有配置
            $existingConfig = null;
            $existingConfigData = [];
            if ($form->isEdit()) {
                $limitGroupId = $form->driver()->get('id');
                $existingConfig = PlatformLimitGroupConfig::query()
                    ->where('limit_group_id', $limitGroupId)
                    ->whereNull('deleted_at')
                    ->first();
                if ($existingConfig) {
                    $existingConfigData = $existingConfig->config_data ?? [];
                }
            }

            // 游戏平台选择
            $platformOptions = [];
            if ($atgPlatform) {
                $platformOptions[$atgPlatform->id] = "{$atgPlatform->name} ({$atgPlatform->code})";
            }
            if ($rsgPlatform) {
                $platformOptions[$rsgPlatform->id] = "{$rsgPlatform->name} ({$rsgPlatform->code})";
            }
            if ($dgPlatform) {
                $platformOptions[$dgPlatform->id] = "{$dgPlatform->name} ({$dgPlatform->code})";
            }
            if ($tninePlatform) {
                $platformOptions[$tninePlatform->id] = "{$tninePlatform->name} ({$tninePlatform->code})";
            }

            if (empty($platformOptions)) {
                $form->html('<div style="padding: 10px; color: #999;">暂无可用的游戏平台（ATG/RSG/DG/TNINE）</div>');
            } else {
                // 平台选择（新建必填，编辑显示）
                if ($form->isEdit() && $existingConfig) {
                    $form->display('_platform_display', '游戏平台')
                        ->value($platformOptions[$existingConfig->platform_id] ?? '');
                    $form->hidden('platform_id')->value($existingConfig->platform_id);

                    // 编辑模式直接显示对应平台的配置字段
                    if ($existingConfig->platform_code === 'ATG' && $atgPlatform) {
                        $form->divider('ATG 平台配置');
                        $form->text('atg_operator', 'X-Operator')
                            ->value($existingConfigData['operator'] ?? '')
                            ->required()
                            ->help('ATG平台营运账号的Operator值')
                            ->placeholder('请输入Operator');

                        $form->password('atg_operator_key', 'X-Key')
                            ->value($existingConfigData['operator_key'] ?? '')
                            ->required()
                            ->help('ATG平台营运账号的Key值')
                            ->placeholder('请输入Key');

                        $form->text('atg_provider_id', 'Provider ID')
                            ->value($existingConfigData['provider_id'] ?? '4')
                            ->default('4')
                            ->help('ATG提供商ID，默认为4');

                        $form->text('atg_api_domain', 'API域名')
                            ->value($existingConfigData['api_domain'] ?? '')
                            ->help('可选，留空使用默认配置');

                        $form->textarea('atg_limit_description', '限红说明')
                            ->rows(3)
                            ->value($existingConfigData['limit_description'] ?? '')
                            ->help('描述该营运账号的限红设置');
                    } elseif ($existingConfig->platform_code === 'RSG' && $rsgPlatform) {
                        $form->divider('RSG 平台配置');
                        $form->number('rsg_min_bet_amount', '最小下注金额')
                            ->precision(2)
                            ->min(0)
                            ->value($existingConfigData['min_bet_amount'] ?? '')
                            ->required()
                            ->help('RSG平台最小下注金额');

                        $form->number('rsg_max_bet_amount', '最大下注金额')
                            ->precision(2)
                            ->min(0)
                            ->value($existingConfigData['max_bet_amount'] ?? '')
                            ->required()
                            ->help('RSG平台最大下注金额');
                    } elseif ($existingConfig->platform_code === 'DG' && $dgPlatform) {
                        $form->divider('DG 平台配置');
                        $form->number('dg_min', '最小下注金额')
                            ->precision(2)
                            ->min(10)
                            ->value($existingConfigData['min'] ?? '')
                            ->required()
                            ->help('DG平台最小下注金额，不能低于10');

                        $form->number('dg_max', '最大下注金额')
                            ->precision(2)
                            ->min(10)
                            ->value($existingConfigData['max'] ?? '')
                            ->required()
                            ->help('DG平台最大下注金额');
                    } elseif ($existingConfig->platform_code === 'TNINE' && $tninePlatform) {
                        $form->divider('TNINE 平台配置');
                        $form->number('tnine_min_bet_limit', '最小下注限额')
                            ->precision(2)
                            ->min(1)
                            ->value($existingConfigData['min_bet_limit'] ?? '')
                            ->required()
                            ->help('TNINE平台最小下注限额（1~1,000,000）');

                        $form->number('tnine_max_bet_limit', '最大下注限额')
                            ->precision(2)
                            ->min(2)
                            ->value($existingConfigData['max_bet_limit'] ?? '')
                            ->required()
                            ->help('TNINE平台最大下注限额（2~1,000,000）');
                    }
                } else {
                    // 新建模式使用 when 方法
                    $platformSelect = $form->select('platform_id', '游戏平台')
                        ->options($platformOptions)
                        ->required()
                        ->help('选择要配置的游戏平台');

                    // ATG平台配置（使用when实现条件显示）
                    if ($atgPlatform) {
                        $platformSelect->when($atgPlatform->id, function (Form $form) {
                            $form->divider('ATG 平台配置');

                            $form->text('atg_operator', 'X-Operator')
                                ->required()
                                ->help('ATG平台营运账号的Operator值')
                                ->placeholder('请输入Operator');

                            $form->password('atg_operator_key', 'X-Key')
                                ->required()
                                ->help('ATG平台营运账号的Key值')
                                ->placeholder('请输入Key');

                            $form->text('atg_provider_id', 'Provider ID')
                                ->default('4')
                                ->help('ATG提供商ID，默认为4');

                            $form->text('atg_api_domain', 'API域名')
                                ->help('可选，留空使用默认配置')
                                ->placeholder('https://api.atg.com');

                            $form->textarea('atg_limit_description', '限红说明')
                                ->rows(3)
                                ->help('描述该营运账号的限红设置')
                                ->placeholder('例如：单注：100-10000，单日限额：100000');
                        });
                    }

                    // RSG平台配置（使用when实现条件显示）
                    if ($rsgPlatform) {
                        $platformSelect->when($rsgPlatform->id, function (Form $form) {
                            $form->divider('RSG 平台配置');

                            $form->number('rsg_min_bet_amount', '最小下注金额')
                                ->required()
                                ->precision(2)
                                ->min(0)
                                ->help('RSG平台最小下注金额')
                                ->placeholder('例如：1.00');

                            $form->number('rsg_max_bet_amount', '最大下注金额')
                                ->required()
                                ->precision(2)
                                ->min(0)
                                ->help('RSG平台最大下注金额')
                                ->placeholder('例如：10000.00');
                        });
                    }

                    // DG平台配置（使用when实现条件显示）
                    if ($dgPlatform) {
                        $platformSelect->when($dgPlatform->id, function (Form $form) {
                            $form->divider('DG 平台配置');

                            $form->number('dg_min', '最小下注金额')
                                ->required()
                                ->precision(2)
                                ->min(10)
                                ->help('DG平台最小下注金额，不能低于10')
                                ->placeholder('例如：10.00');

                            $form->number('dg_max', '最大下注金额')
                                ->required()
                                ->precision(2)
                                ->min(10)
                                ->help('DG平台最大下注金额')
                                ->placeholder('例如：10000.00');
                        });
                    }

                    // TNINE平台配置（使用when实现条件显示）
                    if ($tninePlatform) {
                        $platformSelect->when($tninePlatform->id, function (Form $form) {
                            $form->divider('TNINE 平台配置');

                            $form->number('tnine_min_bet_limit', '最小下注限额')
                                ->required()
                                ->precision(2)
                                ->min(1)
                                ->help('TNINE平台最小下注限额（1~1,000,000）')
                                ->placeholder('例如：100.00');

                            $form->number('tnine_max_bet_limit', '最大下注限额')
                                ->required()
                                ->precision(2)
                                ->min(2)
                                ->help('TNINE平台最大下注限额（2~1,000,000）')
                                ->placeholder('例如：20000.00');
                        });
                    }
                }
            }

            // 自动设置部门ID
            if (!$form->isEdit()) {
                $form->saving(function (Form $form) {
                    $form->input('department_id', Admin::user()->department_id);
                });
            }

            // 保存后处理平台配置
            $form->saved(function (Form $form) {
                // 直接从全局 request 获取数据
                $request = request();
                $postData = $request->post();

                // 数据在 data 字段里
                $data = $postData['data'] ?? [];

                // 如果是编辑，从请求中获取ID；如果是新建，通过code查询刚保存的记录
                $limitGroupId = $postData['id'] ?? null;

                if (!$limitGroupId && isset($data['code'])) {
                    // 新建时，通过code查询刚保存的记录
                    $limitGroup = PlatformLimitGroup::query()
                        ->where('code', $data['code'])
                        ->where('department_id', Admin::user()->department_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($limitGroup) {
                        $limitGroupId = $limitGroup->id;
                    }
                }

                if (!$limitGroupId) {
                    return;
                }

                // 获取平台ID（可能在data中，也可能直接在postData中）
                $platformId = $data['platform_id'] ?? $postData['platform_id'] ?? null;
                if (!$platformId) {
                    return;
                }

                $platform = GamePlatform::query()->where('id', $platformId)->first();
                if (!$platform) {
                    return;
                }

                // 根据平台类型组织config_data
                $configData = [];
                if ($platform->code === 'ATG') {
                    $configData = [
                        'operator' => $data['atg_operator'] ?? '',
                        'operator_key' => $data['atg_operator_key'] ?? '',
                        'provider_id' => $data['atg_provider_id'] ?? '4',
                        'api_domain' => $data['atg_api_domain'] ?? '',
                        'limit_description' => $data['atg_limit_description'] ?? '',
                    ];
                } elseif ($platform->code === 'RSG') {
                    $configData = [
                        'min_bet_amount' => isset($data['rsg_min_bet_amount']) ? floatval($data['rsg_min_bet_amount']) : 0,
                        'max_bet_amount' => isset($data['rsg_max_bet_amount']) ? floatval($data['rsg_max_bet_amount']) : 0,
                    ];
                } elseif ($platform->code === 'DG') {
                    $minAmount = isset($data['dg_min']) ? floatval($data['dg_min']) : 0;
                    $maxAmount = isset($data['dg_max']) ? floatval($data['dg_max']) : 0;

                    // 验证DG平台最小下注金额不能低于10
                    if ($minAmount < 10) {
                        return;
                    }

                    $configData = [
                        'min' => $minAmount,
                        'max' => $maxAmount,
                    ];
                } elseif ($platform->code === 'TNINE') {
                    $configData = [
                        'min_bet_limit' => isset($data['tnine_min_bet_limit']) ? floatval($data['tnine_min_bet_limit']) : 0,
                        'max_bet_limit' => isset($data['tnine_max_bet_limit']) ? floatval($data['tnine_max_bet_limit']) : 0,
                    ];
                }

                // 查找或创建配置
                $config = PlatformLimitGroupConfig::query()
                    ->where('limit_group_id', $limitGroupId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($config) {
                    // 更新现有配置
                    $config->platform_id = $platformId;
                    $config->platform_code = $platform->code;
                    $config->config_data = $configData;
                    $config->status = 1;
                    $config->save();
                } else {
                    // 创建新配置
                    $config = new PlatformLimitGroupConfig();
                    $config->limit_group_id = $limitGroupId;
                    $config->platform_id = $platformId;
                    $config->platform_code = $platform->code;
                    $config->config_data = $configData;
                    $config->status = 1;
                    $config->save();
                }
            });
        });
    }
}

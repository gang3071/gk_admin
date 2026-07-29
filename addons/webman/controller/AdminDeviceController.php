<?php

namespace addons\webman\controller;

use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\AdminDevice;
use addons\webman\service\GoogleTtsHttpService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 总后台设备管理控制器
 */
class AdminDeviceController
{
    /**
     * 设备列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new AdminDevice(), function (Grid $grid) {
            $grid->title(admin_trans('device.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->model()->with(['channel', 'agent', 'store'])->orderBy('id', 'desc');

            // 默认展开筛选
            $grid->expandFilter();

            // 列配置
            $grid->column('id', 'ID')->width(80)->sortable()->fixed(true);

            $grid->column('device_name', admin_trans('device.fields.device_name'))
                ->align('center')
                ->fixed(true);

            $grid->column('device_no', admin_trans('device.fields.device_no'))
                ->align('center')
                ->copyable();

            $grid->column('voice_url', admin_trans('device.fields.voice_url'))
                ->display(function ($value, $data) {
                    if (empty($value)) {
                        return Tag::create(admin_trans('device.voice.not_generated'))->color('default');
                    }
                    return Html::div()->content([
                        Html::create('<audio controls style="height:32px;"><source src="' . $value . '" type="audio/mpeg">您的浏览器不支持音频播放</audio>'),
                    ]);
                })
                ->align('center');

            $grid->column('channel.name', admin_trans('device.fields.channel_name'))
                ->display(function ($value, $data) {
                    return $value ?: '-';
                })
                ->align('center');

            $grid->column('agent.nickname', admin_trans('device.fields.agent_name'))
                ->display(function ($value, $data) {
                    return $value ?: admin_trans('device.no_agent');
                })
                ->align('center');

            $grid->column('store.nickname', admin_trans('device.fields.store_name'))
                ->display(function ($value, $data) {
                    return $value ?: admin_trans('device.no_store');
                })
                ->align('center');

            $grid->column('created_at', admin_trans('device.fields.created_at'))
                ->align('center')
                ->sortable();

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDetail();
                $actions->edit()->modal($this->form())->width('60%');

                // 重新生成语音按钮
                $actions->prepend(
                    Button::create(admin_trans('device.voice.regenerate'))
                        ->icon(Icon::create('SoundOutlined'))
                        ->type('link')
                        ->size('small')
                        ->handler('request', admin_url(['addons-webman-controller-AdminDeviceController', 'regenerateVoice']), ['id' => $data['id']])
                        ->confirm(admin_trans('device.voice.regenerate_confirm'))
                );
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_name')->placeholder(admin_trans('device.fields.device_name'));
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));

                // 渠道筛选
                $channelOptions = [];
                $channels = Channel::orderBy('created_at', 'desc')->get();
                foreach ($channels as $channel) {
                    $channelOptions[$channel->department_id] = $channel->name;
                }
                $channelFilter = $filter->eq()->select('department_id')
                    ->placeholder(admin_trans('device.fields.channel_name'))
                    ->showSearch()
                    ->options($channelOptions);

                // 代理筛选
                $agentFilter = $filter->eq()->select('agent_admin_id')
                    ->placeholder(admin_trans('device.fields.agent_name'))
                    ->showSearch();

                // 店家筛选
                $storeFilter = $filter->eq()->select('store_admin_id')
                    ->placeholder(admin_trans('device.fields.store_name'))
                    ->showSearch();

                // 设置级联关系
                $channelFilter->load($agentFilter, admin_url(['addons-webman-controller-AdminDeviceController', 'getAgentOptions']));
                $agentFilter->load($storeFilter, admin_url(['addons-webman-controller-AdminDeviceController', 'getStoreOptions']));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('device.fields.status'))
                    ->options(AdminDevice::getStatusList());
            });

            // 隐藏清空数据按钮
            $grid->hideDelete();

            // 工具栏按钮
            $grid->tools([
                Button::create(admin_trans('device.add_device'))
                    ->icon(Icon::create('PlusOutlined'))
                    ->type('primary')
                    ->modal($this->form())
                    ->width('60%'),
            ]);
        });
    }

    /**
     * 表单
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new AdminDevice(), function (Form $form) {
            if ($form->isEdit()) {
                $form->title(admin_trans('device.edit_device'));
            } else {
                $form->title(admin_trans('device.add_device'));
            }

            $form->text('device_name', admin_trans('device.fields.device_name'))
                ->required()
                ->maxlength(100);

            $form->text('device_no', admin_trans('device.fields.device_no'))
                ->required()
                ->maxlength(100)
                ->help(admin_trans('device.device_no_help'));

            // 渠道选择
            $channelOptions = [];
            $channels = Channel::orderBy('created_at', 'desc')->get();
            foreach ($channels as $channel) {
                $channelOptions[$channel->department_id] = $channel->name;
            }

            $departmentField = $form->select('department_id', admin_trans('device.fields.channel_name'))
                ->required()
                ->showSearch()
                ->options($channelOptions)
                ->help(admin_trans('device.select_channel_first'));

            // 代理选择（根据渠道动态加载）
            $agentField = $form->select('agent_admin_id', admin_trans('device.fields.agent_name'))
                ->showSearch()
                ->help(admin_trans('device.agent_help'));

            // 店家选择（根据代理动态加载）
            $storeField = $form->select('store_admin_id', admin_trans('device.fields.store_name'))
                ->showSearch()
                ->help(admin_trans('device.store_help'));

            // 设置级联关系
            $departmentField->load($agentField, admin_url(['addons-webman-controller-AdminDeviceController', 'getAgentOptions']));
            $agentField->load($storeField, admin_url(['addons-webman-controller-AdminDeviceController', 'getStoreOptions']));

            // 切换渠道时清空代理和店家
            $departmentField->event('change', ['agent_admin_id' => null, 'store_admin_id' => null], 'variable');

            $form->text('device_model', admin_trans('device.fields.device_model'))
                ->maxlength(100);

            $form->radio('status', admin_trans('device.fields.status'))
                ->options(AdminDevice::getStatusList())
                ->default(AdminDevice::STATUS_ENABLED)
                ->required();

            $form->textarea('remark', admin_trans('device.fields.remark'))
                ->maxlength(500)
                ->showCount()
                ->rows(3);

            $form->saving(function (Form $form) {
                // 检查设备号唯一性
                $deviceNo = $form->input('device_no');
                $id = $form->input('id');

                $exists = AdminDevice::where('device_no', $deviceNo)
                    ->when($id, function ($query) use ($id) {
                        $query->where('id', '!=', $id);
                    })
                    ->exists();

                if ($exists) {
                    return message_error(admin_trans('device.device_no_exists'));
                }

                // 根据部门ID获取渠道ID
                $departmentId = $form->input('department_id');
                $channel = Channel::where('department_id', $departmentId)->first();
                if ($channel) {
                    $form->input('channel_id', $channel->id);
                }

                // 如果是线上渠道，清空代理和店家
                if ($channel && $channel->is_offline == 0) {
                    $form->input('agent_admin_id', 0);
                    $form->input('store_admin_id', 0);
                }
            });

            $form->saved(function (Form $form) {
                $id = $form->input('id');
                $deviceName = $form->input('device_name');

                // 检查是否需要生成语音
                $needGenerateVoice = false;

                if (!empty($deviceName)) {
                    if ($form->isEdit()) {
                        // 编辑设备：检查设备名称是否有变化
                        $oldDevice = AdminDevice::find($id);
                        if ($oldDevice && $oldDevice->device_name !== $deviceName) {
                            $needGenerateVoice = true;
                        }
                    } else {
                        // 新增设备：有设备名称就生成
                        $needGenerateVoice = true;
                    }
                }

                // 自动生成语音播报文件
                if ($needGenerateVoice && !empty($id)) {
                    try {
                        $result = GoogleTtsHttpService::generateDeviceCallServiceVoice($deviceName, $id);

                        if ($result['success']) {
                            // 更新语音URL到数据库
                            AdminDevice::where('id', $id)->update([
                                'voice_url' => $result['url']
                            ]);

                            return message_success(admin_trans('device.save_success') . '，' . admin_trans('device.voice.generated'));
                        } else {
                            // 语音生成失败，但设备保存成功
                            return message_warning(admin_trans('device.save_success') . '，' . admin_trans('device.voice.generate_failed') . '：' . $result['error']);
                        }
                    } catch (\Exception $e) {
                        // 异常处理，不影响设备保存
                        return message_warning(admin_trans('device.save_success') . '，' . admin_trans('device.voice.generate_error') . '：' . $e->getMessage());
                    }
                }

                return message_success(admin_trans('device.save_success'));
            });
        });
    }

    /**
     * 根据渠道获取代理选项
     * @auth true
     */
    public function getAgentOptions(): Response
    {
        $request = Request::input();
        $data = $request['data'] ?? [];
        $departmentId = $request['value'] ?? $data['department_id'] ?? $request['q'] ?? $request['department_id'] ?? '';
        $optionsField = $request['optionsField'] ?? 'agent_admin_id';

        if (empty($departmentId)) {
            return Response::success([$optionsField => [
                ['value' => 0, 'label' => admin_trans('device.no_agent')]
            ]]);
        }

        // 检查是否为线下渠道
        $channel = Channel::where('department_id', $departmentId)->first();
        if (!$channel || $channel->is_offline == 0) {
            return Response::success([$optionsField => [
                ['value' => 0, 'label' => admin_trans('device.no_agent')]
            ]]);
        }

        // 获取该渠道下的代理
        $agents = AdminUser::where('type', AdminUser::TYPE_AGENT)
            ->where('department_id', $departmentId)
            ->where('status', AdminUser::STATUS_ENABLED)
            ->get();

        $options = [['value' => 0, 'label' => admin_trans('device.no_agent')]];
        foreach ($agents as $agent) {
            $options[] = [
                'value' => $agent->id,
                'label' => $agent->nickname,
            ];
        }

        return Response::success([$optionsField => $options]);
    }

    /**
     * 根据代理获取店家选项
     * @auth true
     */
    public function getStoreOptions(): Response
    {
        $request = Request::input();
        $data = $request['data'] ?? [];
        $agentAdminId = $request['value'] ?? $data['agent_admin_id'] ?? $request['q'] ?? $request['agent_admin_id'] ?? '';
        $optionsField = $request['optionsField'] ?? 'store_admin_id';

        if (empty($agentAdminId)) {
            return Response::success([$optionsField => [
                ['value' => 0, 'label' => admin_trans('device.no_store')]
            ]]);
        }

        // 获取该代理下的店家
        $stores = AdminUser::where('type', AdminUser::TYPE_STORE)
            ->where('parent_admin_id', $agentAdminId)
            ->where('status', AdminUser::STATUS_ENABLED)
            ->get();

        $options = [['value' => 0, 'label' => admin_trans('device.no_store')]];
        foreach ($stores as $store) {
            $options[] = [
                'value' => $store->id,
                'label' => $store->nickname,
            ];
        }

        return Response::success([$optionsField => $options]);
    }

    /**
     * 重新生成语音文件
     * @auth true
     */
    public function regenerateVoice(): Response
    {
        $id = Request::input('id');

        if (empty($id)) {
            return message_error(admin_trans('device.device_not_found'));
        }

        $device = AdminDevice::find($id);
        if (!$device) {
            return message_error(admin_trans('device.device_not_found'));
        }

        try {
            $result = GoogleTtsHttpService::generateDeviceCallServiceVoice($device->device_name, $device->id);

            if ($result['success']) {
                // 更新语音URL
                $device->voice_url = $result['url'];
                $device->save();

                return message_success(admin_trans('device.voice.regenerate_success'));
            } else {
                return message_error(admin_trans('device.voice.generate_failed') . '：' . $result['error']);
            }
        } catch (\Exception $e) {
            return message_error(admin_trans('device.voice.generate_error') . '：' . $e->getMessage());
        }
    }
}

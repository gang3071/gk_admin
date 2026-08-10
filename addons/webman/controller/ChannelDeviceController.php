<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDevice;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\service\GoogleTtsHttpService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use support\Log;

/**
 * 渠道后台设备管理控制器
 * @group channel
 */
class ChannelDeviceController
{
    /**
     * 获取当前渠道
     * @return Channel|null
     */
    protected function getChannel(): ?Channel
    {
        return Channel::query()
            ->where('department_id', Admin::user()->department_id)
            ->first();
    }

    /**
     * 设备列表
     * @auth true
     */
    public function index(): Grid
    {
        $channel = $this->getChannel();
        $departmentId = Admin::user()->department_id;

        return Grid::create(new AdminDevice(), function (Grid $grid) use ($channel, $departmentId) {
            $grid->title(admin_trans('device.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 只显示当前渠道的设备
            $grid->model()
                ->with(['agent', 'store'])
                ->where('admin_device.department_id', $departmentId)
                ->orderBy('id', 'desc');

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

            // 线下渠道：显示代理和店家
            if ($channel && $channel->is_offline == 1) {
                $grid->column('agent.nickname', admin_trans('device.fields.agent_name'))
                    ->display(function ($value) {
                        return $value ?: admin_trans('device.no_agent');
                    })
                    ->align('center');

                $grid->column('store.nickname', admin_trans('device.fields.store_name'))
                    ->display(function ($value) {
                        return $value ?: admin_trans('device.no_store');
                    })
                    ->align('center');
            }

            $grid->column('device_model', admin_trans('device.fields.device_model'))
                ->align('center');

            $grid->column('status', admin_trans('device.fields.status'))
                ->display(function ($val) {
                    return $val == AdminDevice::STATUS_ENABLED
                        ? Tag::create(admin_trans('device.status.enabled'))->color('green')
                        : Tag::create(admin_trans('device.status.disabled'))->color('red');
                })
                ->align('center');

            $grid->column('remark', admin_trans('device.fields.remark'))
                ->ellipsis(true);

            $grid->column('created_at', admin_trans('device.fields.created_at'))
                ->align('center')
                ->sortable();

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDetail();
                $actions->edit()->modal($this->form())->width('60%');

                // 重新生成语音按钮
                if (!empty($data['device_name'])) {
                    $actions->prepend(
                        Button::create(admin_trans('device.voice.regenerate'))
                            ->icon(Icon::create('SoundOutlined'))
                            ->confirm(admin_trans('device.voice.regenerate_confirm'), [$this, 'regenerateVoice'], ['id' => $data['id']])
                            ->gridRefresh()
                    );
                }
            });

            // 筛选
            $grid->filter(function (Filter $filter) use ($departmentId) {
                $filter->like()->text('device_name')->placeholder(admin_trans('device.fields.device_name'));
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));

                // 代理筛选（静态加载选项，与 AdminDeviceController 一致）
                $agentOptions = [0 => admin_trans('device.no_agent')];
                $agents = AdminUser::query()
                    ->where('type', AdminUser::TYPE_AGENT)
                    ->where('status', AdminUser::STATUS_ENABLED)
                    ->where('department_id', $departmentId)
                    ->get();
                foreach ($agents as $agent) {
                    $agentOptions[$agent->id] = $agent->nickname ?: $agent->username;
                }

                $agentFilter = $filter->eq()->select('agent_admin_id')
                    ->placeholder(admin_trans('device.fields.agent_name'))
                    ->showSearch()
                    ->options($agentOptions);

                // 店家筛选（根据代理动态加载）
                $storeFilter = $filter->eq()->select('store_admin_id')
                    ->placeholder(admin_trans('device.fields.store_name'))
                    ->showSearch();

                // 设置级联关系：代理 -> 店家
                $agentFilter->load($storeFilter, admin_url(['addons-webman-controller-ChannelDeviceController', 'getStoreOptions']));

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
        $channel = $this->getChannel();
        $departmentId = Admin::user()->department_id;

        return Form::create(new AdminDevice(), function (Form $form) use ($channel, $departmentId) {
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

            // 渠道选择（当前渠道，只读）
            $departmentField = $form->select('department_id', admin_trans('device.fields.channel_name'))
                ->options([$departmentId => $channel->name ?? ''])
                ->default($departmentId)
                ->disabled()
                ->help(admin_trans('device.select_channel_first'));

            // 线下渠道：显示代理和店家选择
            if ($channel && $channel->is_offline == 1) {
                // 代理选项（静态加载，因为渠道固定且 department_id 是 disabled）
                $agentOptions = [0 => admin_trans('device.no_agent')];
                $agents = AdminUser::query()
                    ->where('type', AdminUser::TYPE_AGENT)
                    ->where('status', AdminUser::STATUS_ENABLED)
                    ->where('department_id', $departmentId)
                    ->get();
                foreach ($agents as $agent) {
                    $agentOptions[$agent->id] = $agent->nickname ?: $agent->username;
                }

                // 代理选择
                $agentField = $form->select('agent_admin_id', admin_trans('device.fields.agent_name'))
                    ->showSearch()
                    ->options($agentOptions)
                    ->help(admin_trans('device.fields.agent_help'));

                // 店家选择（根据代理动态加载）
                $storeField = $form->select('store_admin_id', admin_trans('device.fields.store_name'))
                    ->showSearch()
                    ->help(admin_trans('device.fields.store_help'));

                // 设置级联关系：代理 -> 店家
                $agentField->load($storeField, admin_url(['addons-webman-controller-ChannelDeviceController', 'getStoreOptions']));
            }

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

            $form->saving(function (Form $form) use ($channel, $departmentId) {
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

                // 自动设置渠道和部门信息（强制当前渠道，不可选择其他渠道）
                $form->input('department_id', $departmentId);
                if ($channel) {
                    $form->input('channel_id', $channel->id);
                }

                // 线上渠道：清空代理和店家
                if ($channel && $channel->is_offline == 0) {
                    $form->input('agent_admin_id', 0);
                    $form->input('store_admin_id', 0);
                }
            });

            $form->saved(function (Form $form) {
                // 自动生成服务铃语音文件
                $deviceId = $form->input('id');
                $deviceName = $form->input('device_name');

                if (!empty($deviceId) && !empty($deviceName)) {
                    try {
                        $result = GoogleTtsHttpService::generateDeviceCallServiceVoice($deviceName, $deviceId);

                        if ($result['success']) {
                            // 更新语音URL
                            $device = AdminDevice::find($deviceId);
                            if ($device) {
                                $device->voice_url = $result['url'];
                                $device->save();

                                Log::info('渠道后台设备语音自动生成成功', [
                                    'device_id' => $deviceId,
                                    'device_name' => $deviceName,
                                    'voice_url' => $result['url'],
                                    'admin_id' => Admin::id()
                                ]);
                            }
                        } else {
                            Log::warning('渠道后台设备语音生成失败', [
                                'device_id' => $deviceId,
                                'device_name' => $deviceName,
                                'error' => $result['error'] ?? 'Unknown error',
                                'admin_id' => Admin::id()
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('渠道后台设备语音生成异常', [
                            'device_id' => $deviceId,
                            'device_name' => $deviceName,
                            'exception' => $e->getMessage(),
                            'admin_id' => Admin::id()
                        ]);
                    }
                }

                return message_success(admin_trans('device.save_success'));
            });
        });
    }

    /**
     * 获取当前渠道的代理选项
     * @auth true
     */
    public function getAgentOptions(): Response
    {
        $admin = Admin::user();
        $request = Request::input();
        $optionsField = $request['optionsField'] ?? 'agent_admin_id';

        $agents = AdminUser::query()
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', AdminUser::STATUS_ENABLED)
            ->where('department_id', $admin->department_id)
            ->get();

        $options = [['value' => 0, 'label' => admin_trans('device.no_agent')]];
        foreach ($agents as $agent) {
            $options[] = [
                'value' => $agent->id,
                'label' => $agent->nickname ?: $agent->username,
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

        $stores = AdminUser::where('type', AdminUser::TYPE_STORE)
            ->where('parent_admin_id', $agentAdminId)
            ->where('status', AdminUser::STATUS_ENABLED)
            ->get();

        $options = [['value' => 0, 'label' => admin_trans('device.no_store')]];
        foreach ($stores as $store) {
            $options[] = [
                'value' => $store->id,
                'label' => $store->nickname ?: $store->username,
            ];
        }

        return Response::success([$optionsField => $options]);
    }

    /**
     * 重新生成语音文件
     * @auth true
     */
    public function regenerateVoice(): Msg
    {
        $id = Request::input('id');

        if (empty($id)) {
            return message_error(admin_trans('device.device_not_found'));
        }

        $device = AdminDevice::find($id);
        if (!$device) {
            return message_error(admin_trans('device.device_not_found'));
        }

        // 验证权限：只能操作当前渠道的设备
        if ($device->department_id != Admin::user()->department_id) {
            return message_error(admin_trans('common.no_permission'));
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

<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDevice;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
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
            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
                $actions->edit()->modal($this->form())->width('60%');
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_name')->placeholder(admin_trans('device.fields.device_name'));
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));

                // 代理筛选（仅当前渠道的代理）
                $agentFilter = $filter->eq()->select('agent_admin_id')
                    ->placeholder(admin_trans('device.fields.agent_name'))
                    ->showSearch()
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelDeviceController', 'getAgentOptions']));

                // 店家筛选（根据代理动态加载）
                $storeFilter = $filter->eq()->select('store_admin_id')
                    ->placeholder(admin_trans('device.fields.store_name'))
                    ->showSearch()
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelDeviceController', 'getStoreOptions']));

                // 设置级联关系
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

            // 线下渠道：显示代理和店家选择
            if ($channel && $channel->is_offline == 1) {
                // 代理选择（仅当前渠道的代理）
                $agentField = $form->select('agent_admin_id', admin_trans('device.fields.agent_name'))
                    ->showSearch()
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelDeviceController', 'getAgentOptions']))
                    ->help(admin_trans('device.fields.agent_help'));

                // 店家选择（根据代理动态加载）
                $storeField = $form->select('store_admin_id', admin_trans('device.fields.store_name'))
                    ->showSearch()
                    ->help(admin_trans('device.fields.store_help'));

                // 设置级联关系
                $agentField->load($storeField, admin_url(['addons-webman-controller-ChannelDeviceController', 'getStoreOptions']));

                // 切换代理时清空店家
                $agentField->event('change', ['store_admin_id' => null], 'variable');
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

            $form->saved(function () {
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
        $departmentId = Admin::user()->department_id;
        $request = Request::input();
        $optionsField = $request['optionsField'] ?? 'agent_admin_id';

        // 检查是否为线下渠道
        $channel = Channel::where('department_id', $departmentId)->first();
        if (!$channel || $channel->is_offline == 0) {
            return Response::success([$optionsField => [
                ['value' => 0, 'label' => admin_trans('device.no_agent')]
            ]]);
        }

        // 获取当前渠道下的代理
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
}

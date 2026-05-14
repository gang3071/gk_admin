<?php

namespace addons\webman\controller;

use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\Device;
use addons\webman\model\DeviceIp;
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
class DeviceController
{
    /**
     * 设备列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new Device(), function (Grid $grid) {
            $grid->title(admin_trans('device.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->model()->with(['channel', 'agent', 'store'])->orderBy('id', 'desc');

            // 列配置
            $grid->column('id', 'ID')->width(80)->sortable()->fixed(true);

            $grid->column('device_name', admin_trans('device.fields.device_name'))
                ->align('center')
                ->fixed(true);

            $grid->column('device_no', admin_trans('device.fields.device_no'))
                ->align('center')
                ->copyable();

            $grid->column('channel.name', admin_trans('device.fields.channel_name'))
                ->display(function ($value, $data) {
                    return $value ?: '-';
                })
                ->align('center');

            $grid->column('agent.name', admin_trans('device.fields.agent_name'))
                ->display(function ($value, $data) {
                    return $value ?: admin_trans('device.no_agent');
                })
                ->align('center');

            $grid->column('store.name', admin_trans('device.fields.store_name'))
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
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_name')->placeholder(admin_trans('device.fields.device_name'));
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));
                $filter->eq()->select('channel_id')
                    ->placeholder(admin_trans('device.fields.channel_name'))
                    ->showSearch()
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('device.fields.status'))
                    ->options(Device::getStatusList());
            });

            // 工具栏按钮
            $grid->tools([
                Button::create(admin_trans('device.add_device'))
                    ->icon(Icon::create('PlusOutlined'))
                    ->type('primary')
                    ->modal($this->form())
                    ->width('60%'),
            ]);

            $grid->export();
        });
    }

    /**
     * 表单
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new Device(), function (Form $form) {
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
            $form->select('department_id', admin_trans('device.fields.channel_name'))
                ->required()
                ->showSearch()
                ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']))
                ->load('agent_admin_id', admin_url(['addons-webman-controller-DeviceController', 'getAgentOptions']))
                ->help(admin_trans('device.select_channel_first'));

            // 代理选择（根据渠道动态加载）
            $form->select('agent_admin_id', admin_trans('device.fields.agent_name'))
                ->showSearch()
                ->options([0 => admin_trans('device.no_agent')])
                ->load('store_admin_id', admin_url(['addons-webman-controller-DeviceController', 'getStoreOptions']))
                ->help(admin_trans('device.agent_help'));

            // 店家选择（根据代理动态加载）
            $form->select('store_admin_id', admin_trans('device.fields.store_name'))
                ->showSearch()
                ->options([0 => admin_trans('device.no_store')])
                ->help(admin_trans('device.store_help'));

            $form->text('device_model', admin_trans('device.fields.device_model'))
                ->maxlength(100);

            $form->radio('status', admin_trans('device.fields.status'))
                ->options(Device::getStatusList())
                ->default(Device::STATUS_ENABLED)
                ->required();

            $form->textarea('remark', admin_trans('device.fields.remark'))
                ->maxlength(500)
                ->showCount()
                ->rows(3);

            $form->saving(function (Form $form) {
                // 检查设备号唯一性
                $deviceNo = $form->input('device_no');
                $id = $form->input('id');

                $exists = Device::where('device_no', $deviceNo)
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
                    $form->driver()->set('channel_id', $channel->id);
                }

                // 如果是线上渠道，清空代理和店家
                if ($channel && $channel->is_offline == 0) {
                    $form->driver()->set('agent_admin_id', 0);
                    $form->driver()->set('store_admin_id', 0);
                }
            });

            $form->saved(function () {
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
        $departmentId = Request::input('q');

        if (empty($departmentId)) {
            return jsonResponse([
                0 => admin_trans('device.no_agent')
            ]);
        }

        // 检查是否为线下渠道
        $channel = Channel::where('department_id', $departmentId)->first();
        if (!$channel || $channel->is_offline == 0) {
            return jsonResponse([
                0 => admin_trans('device.no_agent')
            ]);
        }

        // 获取该渠道下的代理
        $agents = AdminUser::where('type', AdminUser::TYPE_AGENT)
            ->where('department_id', $departmentId)
            ->where('status', AdminUser::STATUS_ENABLE)
            ->pluck('name', 'id')
            ->toArray();

        $options = [0 => admin_trans('device.no_agent')] + $agents;

        return jsonResponse($options);
    }

    /**
     * 根据代理获取店家选项
     * @auth true
     */
    public function getStoreOptions(): Response
    {
        $agentAdminId = Request::input('q');

        if (empty($agentAdminId)) {
            return jsonResponse([
                0 => admin_trans('device.no_store')
            ]);
        }

        // 获取该代理下的店家
        $stores = AdminUser::where('type', AdminUser::TYPE_STORE)
            ->where('parent_id', $agentAdminId)
            ->where('status', AdminUser::STATUS_ENABLE)
            ->pluck('name', 'id')
            ->toArray();

        $options = [0 => admin_trans('device.no_store')] + $stores;

        return jsonResponse($options);
    }
}

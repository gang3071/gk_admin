<?php

namespace addons\webman\controller;

use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use addons\webman\model\Device;
use addons\webman\model\DeviceIp;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\form\Form;
use ExAdmin\ui\component\form\InputNumber;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 渠道设备管理控制器
 */
class ChannelDeviceController
{
    /**
     * 设备列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::make(new Device(), function (Grid $grid) {
            $grid->title(admin_trans('device.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 数据权限过滤
            $grid->model()->where(function ($query) {
                $adminUser = admin();
                if ($adminUser->isChannelAdmin()) {
                    $query->where('channel_id', $adminUser->getChannelId());
                } elseif ($adminUser->isAgentAdmin()) {
                    $query->where('agent_admin_id', $adminUser->id);
                } elseif ($adminUser->isStoreAdmin()) {
                    $query->where('store_admin_id', $adminUser->id);
                }
            });

            $grid->model()->with(['channel', 'department', 'agent', 'store'])->orderBy('id', 'desc');

            // 列配置
            $grid->column('id', 'ID')->width(80)->sortable();

            $grid->column('device_name', admin_trans('device.fields.device_name'))
                ->align('center');

            $grid->column('device_no', admin_trans('device.fields.device_no'))
                ->align('center')
                ->copyable();

            $grid->column('device_model', admin_trans('device.fields.device_model'))
                ->align('center');

            $grid->column('channel.name', admin_trans('device.fields.channel_name'))
                ->align('center');

            $grid->column('department.title', admin_trans('device.fields.department_name'))
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

            $grid->column('ip_count', admin_trans('device.fields.ip_count'))
                ->display(function ($value, $data) {
                    $count = DeviceIp::where('device_id', $data['id'])
                        ->where('status', DeviceIp::STATUS_ENABLED)
                        ->count();
                    return Html::create($count)
                        ->style(['color' => $count > 0 ? 'green' : 'red', 'cursor' => 'pointer'])
                        ->modal([$this, 'ipList'], ['device_id' => $data['id']])
                        ->width('70%')
                        ->title(admin_trans('device.ip_management') . ' - ' . $data['device_name']);
                })
                ->align('center');

            $grid->column('status', admin_trans('device.fields.status'))
                ->display(function ($value) {
                    return Tag::create(Device::getStatusList()[$value] ?? '')
                        ->color($value == Device::STATUS_ENABLED ? 'green' : 'red');
                })
                ->align('center');

            $grid->column('remark', admin_trans('device.fields.remark'))
                ->limit(50)
                ->align('center');

            $grid->column('created_at', admin_trans('device.fields.created_at'))
                ->align('center')
                ->sortable();

            // 操作按钮
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDetail();
                $actions->prepend([
                    Button::create(admin_trans('device.manage_ip'))
                        ->icon(Icon::create('SettingOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal([$this, 'ipList'], ['device_id' => $data['id']])
                        ->width('70%')
                        ->title(admin_trans('device.ip_management') . ' - ' . $data['device_name']),
                ]);
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_name')->placeholder(admin_trans('device.fields.device_name'));
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));
                $filter->eq()->select('channel_id')
                    ->placeholder(admin_trans('device.fields.channel_name'))
                    ->showSearch()
                    ->options($this->getChannelOptions());
                $filter->eq()->select('agent_admin_id')
                    ->placeholder(admin_trans('device.fields.agent_name'))
                    ->showSearch()
                    ->options($this->getAgentOptions());
                $filter->eq()->select('store_admin_id')
                    ->placeholder(admin_trans('device.fields.store_name'))
                    ->showSearch()
                    ->options($this->getStoreOptions());
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('device.fields.status'))
                    ->options(Device::getStatusList());
            });

            // 工具栏按钮
            $grid->tools([
                Button::create(admin_trans('device.batch_disable'))
                    ->icon(Icon::create('StopOutlined'))
                    ->type('danger')
                    ->confirm(admin_trans('device.batch_disable_confirm'), [$this, 'batchDisable'])
                    ->gridBatch()
                    ->gridRefresh(),
            ]);

            $grid->export();
        });
    }

    /**
     * 获取渠道选项
     * @return array
     */
    private function getChannelOptions(): array
    {
        $adminUser = admin();

        $query = Channel::query();

        if ($adminUser->isChannelAdmin()) {
            $query->where('id', $adminUser->getChannelId());
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * 获取代理选项（仅线下渠道的代理）
     * @return array
     */
    private function getAgentOptions(): array
    {
        $adminUser = admin();

        $query = AdminUser::query()->where('type', AdminUser::TYPE_AGENT);

        // 数据权限过滤
        if ($adminUser->isChannelAdmin()) {
            $channelId = $adminUser->getChannelId();
            $channel = Channel::find($channelId);
            if ($channel && $channel->is_offline == 1) {
                // 仅线下渠道的代理
                $query->where('department_id', $channel->department_id);
            } else {
                // 线上渠道没有代理
                return [0 => admin_trans('device.no_agent')];
            }
        } elseif ($adminUser->isAgentAdmin()) {
            $query->where('id', $adminUser->id);
        } elseif ($adminUser->isStoreAdmin()) {
            // 店家查看自己所属的代理
            $query->where('id', $adminUser->parent_admin_id);
        }

        $options = $query->pluck('name', 'id')->toArray();
        return [0 => admin_trans('device.no_agent')] + $options;
    }

    /**
     * 获取店家选项（仅线下渠道的店家）
     * @return array
     */
    private function getStoreOptions(): array
    {
        $adminUser = admin();

        $query = AdminUser::query()->where('type', AdminUser::TYPE_STORE);

        // 数据权限过滤
        if ($adminUser->isChannelAdmin()) {
            $channelId = $adminUser->getChannelId();
            $channel = Channel::find($channelId);
            if ($channel && $channel->is_offline == 1) {
                // 仅线下渠道的店家
                $agentIds = AdminUser::where('department_id', $channel->department_id)
                    ->where('type', AdminUser::TYPE_AGENT)
                    ->pluck('id');
                $query->whereIn('parent_admin_id', $agentIds);
            } else {
                // 线上渠道没有店家
                return [0 => admin_trans('device.no_store')];
            }
        } elseif ($adminUser->isAgentAdmin()) {
            // 代理查看下级店家
            $query->where('parent_admin_id', $adminUser->id);
        } elseif ($adminUser->isStoreAdmin()) {
            // 店家只能看自己
            $query->where('id', $adminUser->id);
        }

        $options = $query->pluck('name', 'id')->toArray();
        return [0 => admin_trans('device.no_store')] + $options;
    }

    /**
     * 删除设备
     * @auth true
     */
    public function destroy($id)
    {
        try {
            Db::beginTransaction();

            $device = Device::findOrFail($id);

            // 删除关联的IP
            DeviceIp::where('device_id', $id)->delete();

            // 删除设备
            $device->delete();

            Db::commit();

            return message_success(admin_trans('admin.delete_success'));
        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('admin.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * IP管理列表
     * @auth true
     */
    public function ipList(): Grid
    {
        $deviceId = Request::input('device_id');

        return Grid::make(DeviceIp::where('device_id', $deviceId), function (Grid $grid) use ($deviceId) {
            $grid->title(admin_trans('device.ip_list'));
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->model()->orderBy('id', 'desc');

            // 列配置
            $grid->column('id', 'ID')->width(80);

            $grid->column('ip_address', admin_trans('device.fields.ip_address'))
                ->align('center')
                ->copyable();

            $grid->column('ip_type', admin_trans('device.fields.ip_type'))
                ->display(function ($value) {
                    $typeList = DeviceIp::getIpTypeList();
                    return Tag::create($typeList[$value] ?? '')
                        ->color($value == DeviceIp::IP_TYPE_IPV4 ? 'blue' : 'purple');
                })
                ->align('center');

            $grid->column('status', admin_trans('device.fields.status'))
                ->display(function ($value) {
                    return Tag::create(DeviceIp::getStatusList()[$value] ?? '')
                        ->color($value == DeviceIp::STATUS_ENABLED ? 'green' : 'red');
                })
                ->align('center');

            $grid->column('last_used_at', admin_trans('device.fields.last_used_at'))
                ->align('center');

            $grid->column('remark', admin_trans('device.fields.remark'))
                ->limit(50)
                ->align('center');

            $grid->column('created_at', admin_trans('device.fields.created_at'))
                ->align('center');

            // 工具栏添加按钮
            $grid->tools([
                Button::create(admin_trans('device.add_ip'))
                    ->icon(Icon::create('PlusOutlined'))
                    ->type('primary')
                    ->modal([$this, 'addIp'], ['device_id' => $deviceId])
                    ->width('50%')
                    ->gridRefresh()
                    ->title(admin_trans('device.add_ip'))
            ]);

            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
            });
        });
    }

    /**
     * 编辑IP
     * @auth true
     */
    public function updateIp($id): Form
    {
        $deviceIp = DeviceIp::findOrFail($id);
        return $this->addIp();
    }

    /**
     * 添加IP
     * @auth true
     */
    public function addIp(): Form
    {
        $deviceId = Request::input('device_id');

        return Form::make(new DeviceIp(), function (Form $form) use ($deviceId) {
            $form->title(admin_trans('device.add_ip'));

            $form->hidden('device_id')->value($deviceId);

            $form->text('ip_address', admin_trans('device.fields.ip_address'))
                ->required()
                ->help(admin_trans('device.fields.ip_address_help'));

            $form->radio('status', admin_trans('device.fields.status'))
                ->options(DeviceIp::getStatusList())
                ->default(DeviceIp::STATUS_ENABLED);

            $form->textarea('remark', admin_trans('device.fields.remark'))
                ->maxLength(500);

            // 保存前验证
            $form->saving(function (Form $form) {
                // 验证IP格式
                if (!DeviceIp::validateIpAddress($form->model()->ip_address)) {
                    return message_error(admin_trans('device.invalid_ip_address'));
                }

                // 验证IP唯一性
                $exists = DeviceIp::where('device_id', $form->model()->device_id)
                    ->where('ip_address', $form->model()->ip_address)
                    ->when($form->model()->id, function ($q) use ($form) {
                        $q->where('id', '!=', $form->model()->id);
                    })
                    ->exists();

                if ($exists) {
                    return message_error(admin_trans('device.ip_already_exists'));
                }
            });
        });
    }

    /**
     * 删除IP
     * @auth true
     */
    public function destroyIp($id)
    {
        try {
            $deviceIp = DeviceIp::findOrFail($id);
            $deviceIp->delete();

            return message_success(admin_trans('admin.delete_success'));
        } catch (\Exception $e) {
            return message_error(admin_trans('admin.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量禁用设备
     * @auth true
     */
    public function batchDisable()
    {
        $ids = Request::input('ids', []);

        if (empty($ids)) {
            return message_error(admin_trans('device.no_device_selected'));
        }

        try {
            Db::beginTransaction();

            $count = Device::whereIn('id', $ids)->update(['status' => Device::STATUS_DISABLED]);

            Db::commit();

            return message_success(admin_trans('device.batch_disable_success', [], [
                '{count}' => $count
            ]));
        } catch (\Exception $e) {
            Db::rollBack();
            return message_error(admin_trans('device.batch_disable_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 编辑设备
     * @auth true
     */
    public function update($id): Form
    {
        return $this->store();
    }

    /**
     * 新增设备
     * @auth true
     */
    public function store(): Form
    {
        return Form::make(new Device(), function (Form $form) {
            $form->title(admin_trans('device.create'));

            $adminUser = admin();

            if ($adminUser->isChannelAdmin()) {
                $form->hidden('channel_id')->value($adminUser->getChannelId());
                $form->hidden('department_id')->value($adminUser->department_id);
            } else {
                $form->select('channel_id', admin_trans('device.fields.channel_name'))
                    ->options($this->getChannelOptions())
                    ->required();
                $form->hidden('department_id')->value(fn($form) => $form->model()->channel_id ? Channel::find($form->model()->channel_id)->department_id : 0);
            }

            $form->text('device_name', admin_trans('device.fields.device_name'))
                ->required()
                ->maxLength(100);

            $form->text('device_no', admin_trans('device.fields.device_no'))
                ->required()
                ->maxLength(100)
                ->help(admin_trans('device.fields.device_no_help'));

            $form->text('device_model', admin_trans('device.fields.device_model'))
                ->maxLength(100);

            // 代理选择（仅线下渠道）
            $form->select('agent_admin_id', admin_trans('device.fields.agent_name'))
                ->options($this->getAgentOptions())
                ->help(admin_trans('device.fields.agent_help'));

            // 店家选择（仅线下渠道）
            $form->select('store_admin_id', admin_trans('device.fields.store_name'))
                ->options($this->getStoreOptions())
                ->help(admin_trans('device.fields.store_help'));

            $form->radio('status', admin_trans('device.fields.status'))
                ->options(Device::getStatusList())
                ->default(Device::STATUS_ENABLED);

            $form->textarea('remark', admin_trans('device.fields.remark'))
                ->maxLength(500);

            // 保存前验证
            $form->saving(function (Form $form) {
                // 验证设备号唯一性
                $deviceNo = $form->model()->device_no;
                $exists = Device::where('device_no', $deviceNo)
                    ->when($form->model()->id, function ($q) use ($form) {
                        $q->where('id', '!=', $form->model()->id);
                    })
                    ->exists();

                if ($exists) {
                    return message_error(admin_trans('device.device_no_exists'));
                }

                // 自动设置部门ID
                if (empty($form->model()->department_id) && $form->model()->channel_id) {
                    $channel = Channel::find($form->model()->channel_id);
                    $form->model()->department_id = $channel->department_id ?? 0;
                }
            });
        });
    }
}

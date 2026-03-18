<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelRechargeMethod;
use addons\webman\model\ChannelRechargeSetting;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\support\Arr;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 充值渠道
 * @group channel
 */
class ChannelRechargeController
{
    protected $model;
    protected $method;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_recharge_setting_model');
        $this->method = plugin()->webman->config('database.channel_recharge_method_model');
    }

    /**
     * 充值账号
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->sidebar('method_id', new $this->method)
                ->setForm($this->methodForm())->hideDel()->hideAdd();
            $grid->title(admin_trans('channel_recharge_setting.title'));
            $grid->model()->with(['channel_recharge_method'])->whereHas('channel_recharge_method', function ($query) {
                $query->whereNull('deleted_at');
            });
            $grid->autoHeight();
            $grid->bordered(true);
            $quickSearch = Request::input('quickSearch', []);
            if (!empty($quickSearch)) {
                $grid->model()->where([
                    ['name', 'like', '%' . $quickSearch . '%', 'or'],
                    ['account', 'like', '%' . $quickSearch . '%', 'or'],
                    ['bank_name', 'like', '%' . $quickSearch . '%', 'or'],
                ]);
            }
            $grid->column('id', admin_trans('channel_recharge_setting.fields.id'))->align('center');
            $grid->column('channel_recharge_method.name',
                admin_trans('channel_recharge_setting.fields.method_name'))->align('center')->ellipsis(true);
            $grid->column('min', admin_trans('channel_recharge_setting.fields.min'))->display(function (
                $val,
                ChannelRechargeSetting $data
            ) {
                return $val ? $val . ' ' . $data->currency : '--';
            })->align('center')->ellipsis(true);
            $grid->column('max', admin_trans('channel_recharge_setting.fields.max'))->display(function (
                $val,
                ChannelRechargeSetting $data
            ) {
                return $val ? $val . ' ' . $data->currency : '--';
            })->align('center')->ellipsis(true);
            $grid->column('name', admin_trans('channel_recharge_setting.fields.name'))->align('center');
            $grid->column('account', admin_trans('channel_recharge_setting.fields.account'))->copy()->align('center');
            $grid->column('wallet_address',
                admin_trans('channel_recharge_setting.fields.wallet_address'))->copy()->align('center');
            $grid->column('qr_code', admin_trans('channel_recharge_setting.fields.qr_code'))->image()->align('center');
            $grid->column('user_name', admin_trans('channel_recharge_setting.fields.user_name'))->align('center');
            $grid->column('status', admin_trans('channel_recharge_setting.fields.status'))->display(function ($val,$data) {
                return Switches::create(null, $val)
                    ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                    ->field('status')
                    ->url('ex-admin/addons-webman-controller-ChannelRechargeController/changeStatus')
                    ->params([
                        'id' => $data->id,
                    ]);
            })->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('channel_recharge_setting.fields.name'));
                $filter->like()->text('bank_name')->placeholder(admin_trans('channel_recharge_setting.fields.bank_name'));
                $filter->like()->text('account')->placeholder(admin_trans('channel_recharge_setting.fields.account'));
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();
            $grid->addButton()->drawer($this->form());
            $grid->setForm()->drawer($this->form());
            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
            });
        });
    }

    public function changeStatus($id,$data)
    {
        $status = $data['status'];
        /** @var ChannelRechargeSetting $setting */
        $setting = ChannelRechargeSetting::query()->where('id',$id)->first();
        if($status == 1 && $setting->type != ChannelRechargeMethod::TYPE_USDT){
            //判断当前的阈值是否满足开关
            /** @var ChannelRechargeMethod $channelRecharge */
            $channelRecharge = ChannelRechargeMethod::query()->where('id',$setting->method_id)->first();
            if($channelRecharge->amount_limit == 1){
                if($channelRecharge->min > $setting->min || $channelRecharge->max < $setting->max) {
                    return message_error(admin_trans('channel_recharge_setting.change_status_error'));
                }
            }
        }

        $setting->status = $status;
        $setting->save();
        return message_success(admin_trans('lottery.action_success'));
    }

    /**
     * 充值方式
     * @auth true
     * @group channel
     * @return Form
     */
    public function methodForm(): Form
    {
        return Form::create(new $this->method, function (Form $form) {
            $form->row(function (Form $form) {
                $form->text('name',
                    admin_trans('channel_recharge_method.fields.name'))->disabled()->maxlength(120)->placeholder(admin_trans('channel_recharge_method.placeholder_name'))->required()->span(11);
                $form->push(Divider::create()->content(' ')->style(['margin-left' => '11px']));
                $form->switch('status', admin_trans('channel_recharge_method.fields.status'))->required()->span(11);
            });
            $form->radio('amount_limit', admin_trans('channel_recharge_method.fields.amount_limit'))
                ->button()
                ->default(0)
                ->options([
                    1 => admin_trans('channel_recharge_method.limitation'),
                    2 => admin_trans('channel_recharge_method.no_limit')
                ])
                ->when(1, function (Form $form) {
                    $form->row(function (Form $form) {
                        $form->number('min', admin_trans('channel_recharge_method.fields.min'))
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->rule([
                                'required' => admin_trans('channel_recharge_method.rul.min_required'),
                                'min:0' => admin_trans('channel_recharge_method.rul.min_min_1'),
                                'max:100000000' => admin_trans('channel_recharge_method.rul.min_max_100000000'),
                            ])
                            ->placeholder(admin_trans('channel_recharge_method.placeholder_min'))->span(11);
                        $form->push(Divider::create()->content('~')->style(['margin-top' => '31px']));
                        $form->number('max', admin_trans('channel_recharge_method.fields.max'))
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->rule([
                                'required' => admin_trans('channel_recharge_method.rul.max_required'),
                                'min:0' => admin_trans('channel_recharge_method.rul.max_min_1'),
                                'max:100000000' => admin_trans('channel_recharge_method.rul.max_max_100000000'),
                            ])
                            ->placeholder(admin_trans('channel_recharge_method.placeholder_max'))->span(11);
                    });
                })->style(['width' => '100 % ', 'margin-left' => '1px']);
            $form->colon(false);
            $form->removeAttr('labelCol');
            $form->actions()->hideResetButton();
            $form->actions()->submitButton()->content(admin_trans('form.submit'));
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                try {
                    DB::beginTransaction();
                    $amountLimit = $form->input('amount_limit');
                    if ($form->isEdit()) {
                        $id = $form->driver()->get('id');
                        /** @var ChannelRechargeMethod $method */
                        $method = ChannelRechargeMethod::find($id);
                        $method->name = $form->input('name');
                    } else {
                        $method = new ChannelRechargeMethod();
                        $method->name = $form->input('name');
                        $method->department_id = Admin::user()->department_id;
                        $method->user_id = Admin::id();
                        $method->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                    }
                    if ($amountLimit == 1) {
                        if ($form->input('min') >= $form->input('max')) {
                            return message_success(admin_trans('channel_recharge_method.rul.max_gt_min'));
                        }
                        $min = $form->input('min');
                        $max = $form->input('max');

                        $method->min = $min;
                        $method->max = $max;

                        if($form->isEdit()){
                            //获取所有充值配置
                            $rechargeSetting = ChannelRechargeSetting::query()->where('method_id',$id)->get();
                            //配置超过外部的值时关闭配置
                            //外面的最小值小于里面的最小值，外面的最大值大于里面的最大值
                            /** @var ChannelRechargeSetting $item */
                            foreach($rechargeSetting as $item){
                                if($min > $item->min || $max < $item->max) {
                                    $item->status = 0;
                                    $item->save();
                                }
                            }
                        }
                    } else {
                        $method->min = 0;
                        $method->max = 0;
                    }
                    $method->status = $form->input('status');
                    $method->amount_limit = $amountLimit;

                    $method->save();
                    Db::commit();
                } catch (\Exception $exception) {
                    Db::rollBack();
                    return message_error(admin_trans('form.save_error'));
                }
                return message_success(admin_trans('form.save_success'));
            });
        });
    }

    /**
     * 充值账号配置
     * @auth true
     * @group channel
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $disabledValue = Arr::pluck(ChannelRechargeMethod::where('status', 0)->get(['type'])->toArray(), 'type');
            /** @var Channel $channel */
            $channel = Channel::query()->where('department_id', Admin::user()->department_id)->first();
            if ($channel->gb_payment_recharge_status == 0 || ChannelRechargeSetting::query()->where('department_id',
                    Admin::user()->department_id)->where('type', ChannelRechargeMethod::TYPE_GB)->exists()) {
                $gbDsiabled = [ChannelRechargeMethod::TYPE_GB];
                $disabledValue = array_unique(array_merge($disabledValue, $gbDsiabled));
            }
            $form->title(admin_trans('slider.title'));
            $type = $form->getBindField('type');
            $form->select('type', admin_trans('channel_recharge_setting.fields.method_id'))
                ->placeholder(admin_trans('channel_recharge_setting.placeholder_method'))
                ->disabledValue($disabledValue ?? [])
                ->default(ChannelRechargeMethod::TYPE_BANK)
                ->required()
                ->options($this->getRechargeMethod());
            $form->hidden('type')->bindAttr('value', $type)
                ->when(ChannelRechargeMethod::TYPE_BANK, function (Form $form) {
                    $form->text('name', admin_trans('channel_recharge_setting.fields.name'))
                        ->maxlength(120)
                        ->required();
                    $form->text('bank_name', admin_trans('channel_recharge_setting.fields.bank_name'))
                        ->maxlength(100)
                        ->required();
                    $form->text('account', admin_trans('channel_recharge_setting.fields.account'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        //银行卡的范围
                        /** @var ChannelRechargeMethod $channelRecharge */
                        $channelRecharge = ChannelRechargeMethod::query()->where('type',ChannelRechargeMethod::TYPE_BANK)->first();
                        $minContent = admin_trans('channel_recharge_setting.fields.min');
                        $maxContent = admin_trans('channel_recharge_setting.fields.max');
                        if($channelRecharge->amount_limit == 1){
                            $minContent .="($channelRecharge->min)";
                            $maxContent .="($channelRecharge->max)";
                        }
                        $form->number('min', $minContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_min'))->span(10);
                        $form->push(Divider::create()->content('~')->style(['margin-top' => '31px']));
                        $form->number('max', $maxContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_max'))->span(10);
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_ALI, function (Form $form) {
                    $form->text('account', admin_trans('channel_recharge_method.ali_account'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        /** @var ChannelRechargeMethod $channelRecharge */
                        $channelRecharge = ChannelRechargeMethod::query()->where('type',ChannelRechargeMethod::TYPE_ALI)->first();
                        $minContent = admin_trans('channel_recharge_setting.fields.min');
                        $maxContent = admin_trans('channel_recharge_setting.fields.max');
                        if($channelRecharge->amount_limit == 1){
                            $minContent .="($channelRecharge->min)";
                            $maxContent .="($channelRecharge->max)";
                        }
                        $form->number('min', $minContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_min'))->span(10);
                        $form->push(Divider::create()->content('~')->style(['margin-top' => '31px']));
                        $form->number('max', $maxContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_max'))->span(10);
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('channel_recharge_setting.payment_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste();
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_WECHAT, function (Form $form) {
                    $form->text('account', admin_trans('channel_recharge_method.wechat_account'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        /** @var ChannelRechargeMethod $channelRecharge */
                        $channelRecharge = ChannelRechargeMethod::query()->where('type',ChannelRechargeMethod::TYPE_WECHAT)->first();
                        $minContent = admin_trans('channel_recharge_setting.fields.min');
                        $maxContent = admin_trans('channel_recharge_setting.fields.max');
                        if($channelRecharge->amount_limit == 1){
                            $minContent .="($channelRecharge->min)";
                            $maxContent .="($channelRecharge->max)";
                        }
                        $form->number('min', $minContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_min'))->span(10);
                        $form->push(Divider::create()->content('~')->style(['margin-top' => '31px']));
                        $form->number('max', $maxContent)
                            ->style(['width' => '100%'])
                            ->min(1)
                            ->max(100000000)
                            ->precision(2)
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_max'))->span(10);
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('channel_recharge_setting.payment_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste();
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_USDT, function (Form $form) {
                    $form->text('wallet_address',
                        admin_trans('channel_recharge_setting.fields.wallet_address'))
                        ->required()
                        ->maxlength(250);
                    $form->row(function (Form $form) {
                        $form->file('qr_code', admin_trans('channel_recharge_setting.wallet_code'))
                            ->ext('jpg,png,jpeg')
                            ->type('image')
                            ->fileSize('1m')
                            ->required()
                            ->hideFinder()
                            ->paste()
                            ->help(admin_trans('channel_recharge_setting.wallet_code_desc'));
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                })->when(ChannelRechargeMethod::TYPE_GB, function (Form $form) {
                    $form->text('account', admin_trans('channel_recharge_setting.fields.gb_account'))
                        ->maxlength(100)
                        ->required();
                    $form->text('gb_token', admin_trans('channel_recharge_setting.fields.gb_token'))
                        ->maxlength(100)
                        ->required();
                    $form->text('gb_secret', admin_trans('channel_recharge_setting.fields.gb_secret'))
                        ->maxlength(100)
                        ->required();
                    $form->row(function (Form $form) {
                        //银行卡的范围
                        /** @var ChannelRechargeMethod $channelRecharge */
                        $channelRecharge = ChannelRechargeMethod::query()->where('type',
                            ChannelRechargeMethod::TYPE_GB)->first();
                        $minContent = admin_trans('channel_recharge_setting.fields.min');
                        $maxContent = admin_trans('channel_recharge_setting.fields.max');
                        if ($channelRecharge->amount_limit == 1) {
                            $minContent .= "($channelRecharge->min)";
                            $maxContent .= "($channelRecharge->max)";
                        }
                        $form->number('min', $minContent)
                            ->style(['width' => '100%'])
                            ->min(10)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_min'))->span(10);
                        $form->push(Divider::create()->content('~')->style(['margin-top' => '31px']));
                        $form->number('max', $maxContent)
                            ->style(['width' => '100%'])
                            ->min(10)
                            ->max(100000000)
                            ->precision(2)
                            ->required()
                            ->placeholder(admin_trans('channel_recharge_setting.placeholder_max'))->span(10);
                    })->style(['width' => '100%', 'margin-left' => '1px']);
                });
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                /** @var Channel $channel */
                $channel = Channel::query()->where('department_id', Admin::user()->department_id)->first();
                $type = $form->input('type');
                if ($channel->gb_payment_recharge_status == 0 && $type == ChannelRechargeMethod::TYPE_GB) {
                    return message_error(admin_trans('channel_recharge_setting.gb_payment_disabled'));
                }
                try {
                    if ($form->isEdit()) {
                        $id = $form->driver()->get('id');
                        /** @var ChannelRechargeSetting $setting */
                        $setting = ChannelRechargeSetting::find($id);
                    } else {
                        $setting = new ChannelRechargeSetting();
                        $setting->currency = Admin::user()->department->channel->currency;
                        $setting->department_id = Admin::user()->department_id;
                        $setting->user_id = Admin::id();
                        $setting->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                        if ($type == ChannelRechargeMethod::TYPE_GB && ChannelRechargeSetting::query()->where('department_id',
                                Admin::user()->department_id)->where('type',
                                ChannelRechargeMethod::TYPE_GB)->exists()) {
                            return message_error(admin_trans('channel_recharge_setting.gb_payment_exist'));
                        }
                    }
                    /** @var ChannelRechargeMethod $method */
                    $method = ChannelRechargeMethod::query()->where('type',
                        $form->input('type'))->where('department_id', Admin::user()->department_id)->first();
                    if (empty($method)) {
                        throw new \Exception(admin_trans('channel_recharge_setting.recharge_method_not_found'));
                    }


                    if (in_array($method->type, [
                        ChannelRechargeMethod::TYPE_ALI,
                        ChannelRechargeMethod::TYPE_BANK,
                        ChannelRechargeMethod::TYPE_WECHAT
                    ])) {
                        if (!empty($form->input('max')) && !empty($form->input('min')) && $form->input('max') <= $form->input('min')) {
                            throw new \Exception(admin_trans('channel_recharge_method.rul.max_gt_min'));
                        }
                    }

                    $setting->method_id = $method->id;
                    $setting->name = $form->input('name');
                    $setting->bank_name = $form->input('bank_name') ?? '';
                    $setting->wallet_address = $form->input('wallet_address');
                    $setting->qr_code = $form->input('qr_code');
                    $setting->account = $form->input('account');
                    $setting->max = $form->input('max');
                    $setting->min = $form->input('min');
                    $setting->type = $form->input('type');
                    $setting->gb_secret = $form->input('gb_secret');
                    $setting->gb_token = $form->input('gb_token');
                    
                    if($method->amount_limit == 1 && ($method->min > $setting->min || $method->max < $setting->max)) {
                        return message_error(admin_trans('channel_recharge_setting.create_error'));
                    }


                    $setting->save();
                } catch (\Exception $exception) {
                    return message_error(admin_trans('form.save_fail') . $exception->getMessage());
                }
                return message_success(admin_trans('form.save_success'));
            });
        });
    }

    /**
     * 获取充值方式
     * @return array
     */
    public function getRechargeMethod(): array
    {
        $options = [];
        $methodList = ChannelRechargeMethod::query()->get(['type', 'name'])->toArray();
        /** @var ChannelRechargeMethod $item */
        foreach ($methodList as $item) {
            $options[$item['type']] = $item['name'];
        }

        return $options;
    }
}

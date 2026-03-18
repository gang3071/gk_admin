<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Bank;
use addons\webman\model\BankContent;
use addons\webman\model\Channel;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\support\Container;
use Exception;

/**
 * 公告
 */
class BankController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.bank_model');
    }
    
    /**
     * 银行列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('bank.title'));
            $grid->model()->orderBy('id','desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('name', admin_trans('bank.fields.name'))->display(function ($val, Bank $data) use ($lang) {
                /** @var BankContent $bankContent */
                $bankContent = $data->BankContent->where('lang', $lang)->first();
                return Html::create()->content([
                    $bankContent->name ?? '',
                ]);
            })->align('center');
            $grid->column('pic', admin_trans('bank.fields.pic'))->display(function ($val, Bank $data) use ($lang
            ) {
                /** @var BankContent $bankContent */
                $bankContent = $data->BankContent->where('lang', $lang)->first();
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($bankContent->pic ?? '');
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('department_id', admin_trans('bank.fields.department_id'))->display(function ($val){
                return Channel::query()->where('department_id', $val)->value('name');
            })->ellipsis(true)->align('center');
            $grid->column('status', admin_trans('bank.fields.status'))->switch()->align('center');
            $grid->column('creator_id', admin_trans('bank.fields.creator_id'))->display(function ($val){
                return AdminUser::query()->where('id', $val)->value('username');
            })->ellipsis(true)->align('center');
            $grid->column('created_at', admin_trans('bank.fields.created_at'))->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('bankContent.name')->placeholder(admin_trans('bank.fields.name'));
                $filter->like()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('bank.fields.department_id'))
                    ->options($this->getChannelOptions());
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideDetail();
            })->align('center');
        });
    }
    
    /**
     * 添加游戏
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('bank.title'));
            $form->select('department_id', admin_trans('slider.fields.department_id'))->options($this->getChannelOptions())->required();
            $langList = plugin()->webman->config('ui.lang.list');
            $tabs = $form->tabs()->destroyInactiveTabPane();
            $contents = [];
            if ($form->isEdit()) {
                $contents = $form->driver()->get('bankContent')->mapWithKeys(function (bankContent $content) {
                    return [
                        $content->lang => [
                            'name' => $content->name,
                            'pic' => $content->pic,
                            'id' => $content->id,
                        ]
                    ];
                });
            }
            foreach ($langList as $k => $v) {
                $tabs->pane($v, function (Form $form) use ($k, $contents) {
                    $form->text("content." . $k . ".name", admin_trans('bank.fields.name'))
                        ->value($contents[$k]['name'] ?? '')
                        ->required()->maxlength(200)
                        ->help(admin_trans('bank.help.name'));
                    $form->image("content." . $k . ".pic", admin_trans('bank.fields.pic'))
                        ->ext('jpg,png,jpeg')
                        ->value($contents[$k]['pic'] ?? '')
                        ->fileSize('3m')
                        ->help(admin_trans('bank.help.picture_size'))
                        ->required();
                    $form->hidden('content_id')->default($contents[$k]['id'] ?? '');
                });
            }
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                try {
                    if (!$form->isEdit()) {
                        $bank = new Bank();
                        $bank->creator_id = Admin::id();
                    } else {
                        $id = $form->driver()->get('id');
                        $bank = Bank::query()->find($id);
                    }
                    $bank->department_id = $form->input('department_id');
                    $bank->save();
                    $contents = $form->input('content');
                    foreach ($contents as $key => $content) {
                        BankContent::query()->updateOrCreate(
                            [
                                'lang' => $key,
                                'bank_id' => $bank->id,
                            ],
                            [
                                'name' => $content['name'] ?? '',
                                'pic' => $content['pic'] ?? '',
                            ]
                        );
                    }
                } catch (Exception $e) {
                    return message_error(admin_trans('form.save_fail') . $e->getMessage());
                }
                return message_success(admin_trans('form.save_success'));
            });
        });
    }

    /**
     * 筛选部门/渠道
     * @return array
     */
    public function getChannelOptions(): array
    {
        $channelList = Channel::orderBy('created_at', 'desc')->get();
        $data = [];
        /** @var Channel $channel */
        foreach ($channelList as $channel) {
            $data[$channel->department_id] = $channel->name;
        }
        return $data;
    }
}

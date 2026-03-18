<?php

namespace addons\webman\controller;

use addons\webman\form\MyEditor;
use addons\webman\model\Channel;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;

/**
 * 轮播图
 */
class SliderController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.slider_model');
    }

    /**
     * 轮播图
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('slider.title'));
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->sortDrag();
            $grid->column('picture_url', admin_trans('slider.fields.picture_url'))->display(function ($val, $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['picture_url']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('url', admin_trans('slider.fields.url'))->align('center')->editable(
                Editable::text()
                    ->showCount()
                    ->ruleUrl()
                    ->rule(['max:200' => admin_trans('slider.url_max_length')])
            );
            $grid->column('channel.name', admin_trans('slider.fields.department_id'))->align('center');
            $grid->column('status', admin_trans('slider.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
            });
            $grid->hideDelete();
            $grid->expandFilter();
        });
    }

    /**
     * 轮播图
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Form::extend('myEditor', MyEditor::class);

        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('slider.title'));
            $form->image('picture_url', admin_trans('slider.fields.picture_url'))
                ->ext('jpg,png,jpeg')
                ->fileSize('5m')
                ->help(admin_trans('slider.help.picture_url_size'))
                ->required();
            $form->select('department_id', admin_trans('slider.fields.department_id'))->options($this->getChannelOptions())->required();
            $form->text('url', admin_trans('slider.fields.url'))->ruleUrl()->maxlength(200);
            $form->myEditor('content', admin_trans('slider.fields.content'));
            $form->number('sort', admin_trans('slider.fields.sort'))->default($this->model::max('sort') + 1);
            $form->layout('vertical');
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

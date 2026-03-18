<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\form\MyEditor;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\support\Container;

/**
 * 轮播图
 * @group channel
 */
class ChannelSliderController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.slider_model');
    }

    /**
     * 轮播图
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('slider.title'));
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
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
            $lang = Container::getInstance()->translator->getLocale();
            $grid->column('activity_id', admin_trans('activity_content.fields.name'))->display(function ($val) use ($lang) {
                /** @var ActivityContent $activityContent */
                if(!empty($val)) {
                    $activityContent = ActivityContent::query()->where('activity_id', $val)->where('lang', $lang)->first();
                    return Html::create($activityContent->name)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-ChannelActivityController', 'details'], ['id' => $val]);
                }
                return '';
            })->align('center');
            $grid->sortInput('sort', admin_trans('slider.fields.sort'))->align('center');
            $grid->column('status', admin_trans('slider.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
        });
    }

    /**
     * 轮播图
     * @group channel
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Form::extend('myEditor', MyEditor::class);

        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('slider.title'));
            $form->file('picture_url', admin_trans('slider.fields.picture_url'))
                ->ext('jpg,png,jpeg')
                ->type('image')
                ->fileSize('1m')
                ->required()
                ->hideFinder()
                ->paste();
            $form->text('url', admin_trans('slider.fields.url'))->ruleUrl()->maxlength(200);
            $lang = Container::getInstance()->translator->getLocale();
            //获取待绑定活动
            $activity_ids = Activity::query()->whereJsonContains('department_id',
                Admin::user()->department_id)->pluck('id');
            $options = ActivityContent::query()->where('lang', $lang)
                ->whereIn('activity_id',$activity_ids)->pluck('name', 'activity_id')->toArray();
            $form->select('activity_id',admin_trans('activity_content.fields.name'))
                ->options($options);
            $form->myEditor('content', admin_trans('slider.fields.content'));
            $form->number('sort', admin_trans('slider.fields.sort'))->default($this->model::max('sort') + 1);
            $form->hidden('department_id')->default(Admin::user()->department_id);
            $form->layout('vertical');
        });
    }
}

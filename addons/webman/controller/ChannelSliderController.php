<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\form\MyEditor;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use addons\webman\model\Slider;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\support\Container;
use Illuminate\Support\Str;

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
                // 视频素材用原生 video 预览，图片保持缩略图（视频放 <img> 会显示成乱码/破图）
                if ((int)($data['type'] ?? Slider::TYPE_IMAGE) === Slider::TYPE_VIDEO) {
                    // 原生 video 不带 xgplayer 那个 70px 圆形播放按钮背景；preload=metadata 取首帧当缩略图，controls 可直接播放
                    // video 是块级元素，套一层 flex 让它和图片一样居中；尺寸收窄避免撑宽列
                    $video = Html::create()->tag('video')
                        ->attr('src', $val)
                        ->attr('preload', 'metadata')
                        ->attr('controls', true)
                        ->attr('playsinline', true)
                        ->style(['width' => '80px', 'height' => '45px', 'objectFit' => 'contain', 'background' => '#000000']);
                    return Html::create()->content([
                        $video,
                    ])->style(['display' => 'flex', 'justifyContent' => 'center']);
                }
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
                    // 语种码统一成 zh-TW 形式（cookie ex_admin_lang 可能是 zh_TW 等写法）；查不到内容直接返回空，避免取 ->name 空指针崩掉整个列表
                    $activityContent = ActivityContent::query()
                        ->where('activity_id', $val)
                        ->where('lang', Str::replace('_', '-', $lang))
                        ->first();
                    if (!$activityContent) {
                        return '';
                    }
                    return Html::create($activityContent->name)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-ChannelActivityController', 'details'], ['id' => $val]);
                }
                return '';
            })->align('center');
            $grid->column('ad_position', admin_trans('slider.fields.ad_position'))->display(function ($val) {
                return admin_trans('slider.ad_position.' . $val);
            })->align('center');
            $grid->sortInput('sort', admin_trans('slider.fields.sort'))->align('center');
            $grid->column('status', admin_trans('slider.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('ad_position')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('slider.fields.ad_position'))
                    ->options([
                        Slider::AD_POSITION_GAME_LOBBY => admin_trans('slider.ad_position.' . Slider::AD_POSITION_GAME_LOBBY),
                        Slider::AD_POSITION_PHYSICAL_LOBBY => admin_trans('slider.ad_position.' . Slider::AD_POSITION_PHYSICAL_LOBBY),
                        Slider::AD_POSITION_STANDBY => admin_trans('slider.ad_position.' . Slider::AD_POSITION_STANDBY),
                        Slider::AD_POSITION_LANDSCAPE => admin_trans('slider.ad_position.' . Slider::AD_POSITION_LANDSCAPE),
                    ]);
            });
            $grid->hideDelete();
            $grid->expandFilter();
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
            $form->select('type', admin_trans('slider.fields.type'))
                ->options([
                    Slider::TYPE_IMAGE => admin_trans('slider.type.' . Slider::TYPE_IMAGE),
                    Slider::TYPE_VIDEO => admin_trans('slider.type.' . Slider::TYPE_VIDEO),
                ])
                ->default(Slider::TYPE_IMAGE)
                ->required()
                ->when(Slider::TYPE_VIDEO, function (Form $form) {
                    $this->videoField($form);
                });
            $form->select('ad_position', admin_trans('slider.fields.ad_position'))
                ->options([
                    1 => admin_trans('slider.ad_position.1'),
                    2 => admin_trans('slider.ad_position.2'),
                    3 => admin_trans('slider.ad_position.3'),
                    4 => admin_trans('slider.ad_position.4'),
                ])->required()
                ->when(1, function (Form $form) {
                    $this->pictureField($form, 'slider.help.picture_size_1');
                })
                ->when(2, function (Form $form) {
                    $this->pictureField($form, 'slider.help.picture_size_2');
                })
                ->when(3, function (Form $form) {
                    $this->pictureField($form, 'slider.help.picture_size_3');
                })
                ->when(4, function (Form $form) {
                    $this->pictureField($form);
                });
            $form->text('url', admin_trans('slider.fields.url'))->ruleUrl()->maxlength(200);
            $lang = Container::getInstance()->translator->getLocale();
            //获取待绑定活动
            $activity_ids = Activity::query()->whereJsonContains('department_id',
                Admin::user()->department_id)->pluck('id');
            $options = ActivityContent::query()->where('lang', Str::replace('_', '-', $lang))
                ->whereIn('activity_id',$activity_ids)->pluck('name', 'activity_id')->toArray();
            $form->select('activity_id',admin_trans('activity_content.fields.name'))
                ->options($options);
            $form->myEditor('content', admin_trans('slider.fields.content'));
            $form->number('sort', admin_trans('slider.fields.sort'))->default($this->model::max('sort') + 1);
            $form->hidden('department_id')->default(Admin::user()->department_id);
            $form->layout('vertical');
        });
    }

    /**
     * 图片上传字段（仅类型=图片时显示，按广告位带出对应尺寸建议）
     *
     * @param Form $form
     * @param string|null $helpKey slider.help.* 尺寸建议语言键，null 表示不提示
     * @return void
     */
    private function pictureField(Form $form, ?string $helpKey = null): void
    {
        $field = $form->file('picture_url', admin_trans('slider.fields.picture_url'))
            ->ext('jpg,png,jpeg')
            ->type('image')
            ->fileSize('1m')
            ->required()
            ->hideFinder()
            ->paste();
        if ($helpKey !== null) {
            $field->help(admin_trans($helpKey));
        }
        // 与视频上传互斥：类型=视频时隐藏
        // 条件字段必须用 getBindField() 的绑定名（与 when() 内部一致），写裸 'type' 会永远不匹配
        $field->where($form->getBindField('type'), Slider::TYPE_IMAGE);
    }

    /**
     * 视频上传字段（仅类型=视频时显示）
     *
     * @param Form $form
     * @return void
     */
    private function videoField(Form $form): void
    {
        $form->file('picture_url', admin_trans('slider.fields.video'))
            ->ext('mp4,webm,mov')
            ->type('file')
            ->fileSize('60m')
            ->help(admin_trans('slider.help.video_format'))
            ->required()
            ->hideFinder()
            ->paste();
    }
}

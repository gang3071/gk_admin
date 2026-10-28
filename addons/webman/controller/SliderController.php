<?php

namespace addons\webman\controller;

use addons\webman\form\MyEditor;
use addons\webman\model\Channel;
use addons\webman\model\Slider;
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
            // 拖拽排序是原生 SQL 更新，不触发模型事件，需单独推送
            $grid->sorting(function ($id) {
                $slider = $this->model::find($id);
                if ($slider) {
                    \addons\webman\service\SliderPushService::notify($slider);
                }
            });
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
            $grid->column('channel.name', admin_trans('slider.fields.department_id'))->align('center');
            $grid->column('ad_position', admin_trans('slider.fields.ad_position'))->display(function ($val) {
                return admin_trans('slider.ad_position.' . $val);
            })->align('center');
            $grid->column('status', admin_trans('slider.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->eq()->select('ad_position')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('slider.fields.ad_position'))
                    ->options([
                        1 => admin_trans('slider.ad_position.1'),
                        2 => admin_trans('slider.ad_position.2'),
                        3 => admin_trans('slider.ad_position.3'),
                        4 => admin_trans('slider.ad_position.4'),
                    ]);
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
            $form->select('department_id', admin_trans('slider.fields.department_id'))->options($this->getChannelOptions())->required();
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
                });
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

    /**
     * 图片上传字段（仅类型=图片时显示，按广告位带出尺寸建议）
     *
     * @param Form $form
     * @param string|null $helpKey slider.help.* 尺寸建议语言键
     * @return void
     */
    private function pictureField(Form $form, ?string $helpKey = null): void
    {
        $field = $form->image('picture_url', admin_trans('slider.fields.picture_url'))
            ->ext('jpg,png,jpeg,webp')
            ->fileSize('60m')
            ->required();
        if ($helpKey !== null) {
            $field->help(admin_trans($helpKey));
        }
        // 与视频上传互斥：类型=视频时隐藏（此前图片字段放行 mp4，会把视频塞进图片预览导致显示乱码）
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

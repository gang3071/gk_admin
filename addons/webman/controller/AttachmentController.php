<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\DownloadFile;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\field\upload\Upload;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\ToolTip;
use Illuminate\Database\Eloquent\Builder;


/**
 * 附件管理
 */
class AttachmentController
{
    protected $attachmentModel;
    protected $attachmentCateModel;

    public function __construct()
    {
        $this->attachmentModel = plugin()->webman->config('database.attachment_model');
        $this->attachmentCateModel = plugin()->webman->config('database.attachment_cate_model');
    }

    /**
     * 附件
     * @auth true
     * @param string $type image图片 file文件
     * @param int $size 文件大小
     * @param array $ext 文件后缀
     * @param string $customStyle
     * @param string $selectionField
     * @return Grid
     */
    public function index($type = '', $size = 0, $ext = [],$customStyle='card',$selectionField=''): Grid
    {
        $grid = Grid::create(new $this->attachmentModel);
        if($selectionField){
            $grid->selectionField($selectionField);
        }
        $grid->title(admin_trans('attachment.title'));
        $grid->model()->when($type, function (Builder $q, $value) {
            $q->where('type', $value);
        })->when($ext, function (Builder $q, $value) {
            $q->whereIn('ext', $value);
        })->when($size, function (Builder $q, $value) {
            $q->where('size', '<=', $value);
        });
        $grid->hideTrashed();
        $grid->autoHeight();
        $grid->sidebar('cate_id', new $this->attachmentCateModel)
            ->model(function (Builder $builder) {
                $builder->where(function (Builder $q) {
                    $q->orWhere('admin_id', Admin::id())->orWhere('permission_type', 0);
                });

            })
            ->setForm($this->cate())
            ->tree();
        $grid->custom(function ($data) {
            return Html::create([
                Image::create()
                    ->src($data['url'])
                    ->style(['object-fit' => 'contain'])
                    ->width(80)
                    ->height(80)->whenShow($data['type'] == 'image'),
                DownloadFile::create()
                    ->onlyImage()
                    ->style(['object-fit' => 'contain'])
                    ->width(80)
                    ->height(80)
                    ->url($data['url'])->whenShow($data['type'] == 'file'),
                ToolTip::create()->title($data['real_name'])
                    ->placement('bottom')
                    ->content(
                        Html::create($data['real_name'])
                            ->style(['white-space' => 'nowrap', 'text-overflow' => 'ellipsis', 'overflow' => 'hidden', 'width' => '100%'])
                    ),
            ])->style(['display' => 'flex', 'align-items' => 'center', 'flex-direction' => 'column', 'text-align' => 'center']);
        }, 'ACard',$customStyle)->grid(10, 6)
            ->when($customStyle=='card',function ($list){
                $list->class('ant-card')->style(['padding'=>'0 10px']);
            });

        $grid->pagination()->pageSize(24);
        $grid->actions(function (Actions $actions, $data) {
            $actions->icon();
            $actions->prepend(
                Button::create()
                    ->icon('<cloud-download-outlined />')
                    ->size('small')
                    ->shape('circle')
                    ->redirect($data['url'])
            );
        });
        $grid->expandFilter();

        $grid->vModel('selectedSidebar');

        $grid->tools(
            Upload::create()
                ->multiple()
                ->action('ex-admin/addons-webman-controller-AttachmentController/upload')
                ->bindAttr('params', ['cate_id' => $grid->bindAttr('selectedSidebar')])
                ->style(['marginLeft' => '8px'])
                ->eventCustom('success', 'GridRefresh')
        ,false);
        return $grid;
    }

    /**
     * 上传
     * @return mixed
     */
    public function upload()
    {
        $class = plugin()->webman->config('form.uploader');
        $simpleUploader = new $class;
        return $simpleUploader->upload();
    }

    /**
     * 附件分类
     * @auth true
     */
    public function cate()
    {
        return Form::create(new $this->attachmentCateModel(),function (Form $form){
            $options = $this->attachmentCateModel::where('admin_id', Admin::id())->get()->toArray();
            array_unshift($options, ['id' => 0, 'name' => admin_trans('attachment.cate.parent'), 'pid' => -1]);
            $form->treeSelect('pid', admin_trans('attachment.cate.fields.pid'))
                ->default(0)
                ->required()
                ->options($options);
            $form->text('name', admin_trans('attachment.cate.fields.name'))->required();
            $form->radio('permission_type', admin_trans('attachment.cate.fields.permission_type'))
                ->options([
                    0 => admin_trans('attachment.cate.public'),
                    1 => admin_trans('attachment.cate.private'),
                ])
                ->default(0);
            $form->number('sort', admin_trans('attachment.cate.fields.sort'))->default($this->attachmentCateModel::max('sort') + 1);
            $form->input('admin_id', Admin::id());
        });
    }
}

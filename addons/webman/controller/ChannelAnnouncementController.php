<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\form\MyEditor;
use addons\webman\model\Announcement;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;

/**
 * 公告
 * @group channel
 */
class ChannelAnnouncementController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.announcement_model');
    }

    /**
     * 公告
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('announcement.title'));
            $grid->model()->orderBy('status', 'desc')->orderBy('priority', 'desc')->orderBy('push_time', 'asc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('admin_name', admin_trans('announcement.fields.admin_name'))->display(function ($val, $data) {
                $avatarValue = $data->adminUser->avatar ?? '';
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($avatarValue);
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('title', admin_trans('announcement.fields.title'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->editable(
                (new Editable)->textarea('title')
                    ->showCount()
                    ->rows(5)
                    ->rule(['max:255' => admin_trans('announcement.fields.remark')])
            )->width('15%');
            $grid->column('valid_time', admin_trans('announcement.fields.valid_time'))
                ->editable(
                    Editable::dateTime()->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day + 1 < 10 ? '0' + Day : Day;
                    return Y + M + D < '" . date('Y-m-d') . "';", ['time'])
                )->sortable();
            $grid->column('push_time', admin_trans('announcement.fields.push_time'))
                ->editable(
                    Editable::dateTime()->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day + 1 < 10 ? '0' + Day : Day;
                    return Y + M + D < '" . date('Y-m-d') . "';", ['time'])
                )->sortable();
            $grid->column('priority', admin_trans('announcement.fields.priority'))
                ->display(function ($value) {
                    switch ($value) {
                        case Announcement::PRIORITY_SENIOR:
                            $tag = Tag::create(admin_trans('announcement.priority.' . Announcement::PRIORITY_SENIOR))->color('#108ee9');
                            break;
                        case Announcement::PRIORITY_EMERGENT:
                            $tag = Tag::create(admin_trans('announcement.priority.' . Announcement::PRIORITY_EMERGENT))->color('#f50');
                            break;
                        case Announcement::PRIORITY_ORDINARY:
                        default:
                            $tag = Tag::create(admin_trans('announcement.priority.' . Announcement::PRIORITY_ORDINARY))->color('#87d068');
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->sortable();
            $grid->expandFilter();
            $grid->column('status', admin_trans('announcement.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
        });
    }

    /**
     * 公告
     * @group channel
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Form::extend('myEditor', MyEditor::class);

        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('announcement.title'));
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->text('title', admin_trans('announcement.fields.title'))->required()->maxlength(200)->help(admin_trans('announcement.help.title'));
                    $form->radio('priority', admin_trans('announcement.fields.priority'))
                        ->default(Announcement::PRIORITY_ORDINARY)
                        ->button()
                        ->options([
                            Announcement::PRIORITY_ORDINARY => admin_trans('announcement.priority.' . Announcement::PRIORITY_ORDINARY),
                            Announcement::PRIORITY_SENIOR => admin_trans('announcement.priority.' . Announcement::PRIORITY_SENIOR),
                            Announcement::PRIORITY_EMERGENT => admin_trans('announcement.priority.' . Announcement::PRIORITY_EMERGENT),
                        ]);
                })->span(12);
                $form->column(function (Form $form) {
                    $form->date('push_time', admin_trans('announcement.fields.push_time'))->required()->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day + 1 < 10 ? '0' + Day : Day;
                    return Y + M + D < '" . date('Y-m-d') . "';", ['time'])
                        ->valueFormat('YYYY-MM-DD HH:mm:ss')
                        ->showTime(true)->help(admin_trans('announcement.help.push_time'));
                    $form->date('valid_time', admin_trans('announcement.fields.valid_time'))->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day + 1 < 10 ? '0' + Day : Day;
                    return Y + M + D < '" . date('Y-m-d') . "';", ['time'])
                        ->valueFormat('YYYY-MM-DD HH:mm:ss')
                        ->showTime(true)->help(admin_trans('announcement.help.valid_time'));
                })->span(12);
            });
            $form->myEditor('content', admin_trans('announcement.fields.content'))->required();
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    $form->input('admin_id', Admin::id());
                    $form->input('department_id', Admin::user()->department_id);
                    $form->input('admin_name', !empty(Admin::user()) ? Admin::user()->toArray()['username'] : trans('system_automatic', [], 'message'));
                }
            });
            $form->layout('vertical');
        });
    }
}

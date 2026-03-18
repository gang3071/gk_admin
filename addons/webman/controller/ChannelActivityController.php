<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use addons\webman\model\ActivityPhase;
use addons\webman\model\MachineCategory;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 活动管理
 * @group channel
 */
class ChannelActivityController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.activity_model');

    }

    /**
     * 活动
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $grid->model()->whereJsonContains('department_id',
                Admin::user()->department_id)->with(['activity_content']);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->title(admin_trans('activity.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $lang = Container::getInstance()->translator->getLocale();
            $grid->column('id', admin_trans('activity.fields.id'))->align('center');
            $grid->column('name', admin_trans('activity_content.fields.name'))->display(function (
                $val,
                Activity $data
            ) use ($lang) {
                /** @var ActivityContent $activityContent */
                $activityContent = $data->activity_content->where('lang', $lang)->first();
                return Html::create($activityContent->name)->style([
                    'cursor' => 'pointer',
                    'color' => 'rgb(24, 144, 255)'
                ])->drawer(['addons-webman-controller-ActivityController', 'details'], ['id' => $data->id]);
            })->align('center');
            $grid->column('type', admin_trans('activity.fields.type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(getGameTypeName($val)),
                ]);
            })->align('center');
            $grid->column('get_way', admin_trans('activity_content.fields.get_way'))->display(function (
                $val,
                Activity $data
            ) use ($lang) {
                /** @var ActivityContent $activityContent */
                $activityContent = $data->activity_content->where('lang', $lang)->first();
                return $activityContent->get_way ?? '';
            })->align('center');
            $grid->column('picture', admin_trans('activity_content.fields.picture'))
                ->display(function ($val, Activity $data) use ($lang) {
                    /** @var ActivityContent $activityContent */
                    $activityContent = $data->activity_content->where('lang', $lang)->first();
                    $image = Image::create()
                        ->width(50)
                        ->height(50)
                        ->style(['objectFit' => 'cover'])
                        ->src($activityContent->picture ?? '');
                    return Html::create()->content([
                        $image,
                    ]);
                })->align('center');
            $grid->column('status', admin_trans('activity.fields.status'))->display(function ($val, Activity $data) use
            (
                $lang
            ) {
                switch ($data->status) {
                    case 0:
                        return Tag::create(admin_trans('admin.close'))->color('red');
                    case 1:
                        return Tag::create(admin_trans('admin.open'))->color('green');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('time_frame', admin_trans('activity.fields.time_frame'))
                ->display(function ($val, Activity $data) {
                    $time = '';
                    !empty($data->start_time) && $time .= $data->start_time;
                    !empty($data->end_time) && $time .= '~' . $data->end_time;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan');
                })
                ->align('center');
            $grid->column('created_at', admin_trans('activity.fields.created_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('activity_content.name')->placeholder(admin_trans('activity_content.fields.name'));
                $filter->eq()->select('status')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('activity.fields.status'))
                    ->options([
                        1 => admin_trans('post.normal'),
                        0 => admin_trans('post.disable')
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('activity.created_at_start'),
                    admin_trans('activity.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 活动详情
     * @param $id
     * @return Card
     */
    public function details($id): Card
    {
        $tabs = Tabs::create()
            ->pane(admin_trans('activity.detail'), $this->detail($id))
            ->pane(admin_trans('activity.phase'), $this->phase($id));
        return Card::create($tabs);
    }

    /**
     * 活动详情
     * @param $id
     * @return Detail
     */
    public function detail($id): Detail
    {
        $data = (new $this->model)->find($id);
        return Detail::create($data, function (Detail $detail) {
            $detail->item('type', admin_trans('activity.fields.type'))->display(function ($val, Activity $data) {
                return Tag::create(getGameTypeName($data->type));
            });
            $detail->item('rang_time', admin_trans('activity.rang_time'))->display(function ($val, Activity $data) {
                return $data->start_time . ' ~ ' . $data->end_time;
            });
            $detail->item('cate_id', admin_trans('activity.fields.cate_id'))->display(function (
                $val,
                Activity $data
            ) {
                $cateList = MachineCategory::whereIn('id', explode(',', $data->cate_id))->get();
                $tag = [];
                /** @var MachineCategory $cate */
                foreach ($cateList as $cate) {
                    $tag[] = Tag::create($cate->name);
                }
                return Html::create($tag);
            });
            $detail->item('activity_content', admin_trans('activity.activity_content'))->display(function (
                $val,
                Activity $data
            ) {
                $langList = plugin()->webman->config('ui.lang.list');
                $tabs = Tabs::create()->destroyInactiveTabPane();
                /** @var ActivityContent $item */
                foreach ($data->activity_content as $item) {
                    $tabs->pane($langList[$item->lang], Detail::create($item, function (Detail $detail) {
                        $detail->item('name', admin_trans('activity_content.fields.name'));
                        $detail->item('description', admin_trans('activity_content.fields.description'));
                        $detail->item('join_condition', admin_trans('activity_content.fields.join_condition'));
                        $detail->item('get_way', admin_trans('activity_content.fields.get_way'));
                        $detail->item('picture', admin_trans('activity_content.fields.picture'))->image();
                    })->bordered()->layout('vertical'));
                }
                return $tabs;
            });
        })->bordered()->layout('vertical');
    }

    /**
     * $id
     * @param $id
     * @return Tabs
     */
    public function phase($id): Tabs
    {
        $key = 0;
        $activityPhase = [];
        /** @var Activity $data */
        $data = (new $this->model)->find($id);
        /** @var ActivityPhase $item */
        foreach ($data->activity_phase as $item) {
            if (!isset($activityPhase[$item->cate_id]) && empty($activityPhase[$item->cate_id])) {
                $activityPhase[$item->cate_id] = [
                    'key' => $key,
                    'cate_id' => $item->cate_id,
                    'cate_name' => $item->machineCategory->name,
                    'phase_list' => []
                ];
                $key++;
            }
            $activityPhase[$item->cate_id]['phase_list'][] = [
                'id' => $item->id,
                'condition' => $item->condition,
                'bonus' => $item->bonus,
                'notice' => json_decode($item->notice, true)
            ];
        }
        $phaseTabs = Tabs::create()->destroyInactiveTabPane();
        foreach ($activityPhase as $item) {
            $phaseDetail = Detail::create($item, function (Detail $detail) use ($item) {
                foreach ($item['phase_list'] as $value) {
                    $detail->item('condition', admin_trans('activity_phase.fields.condition'))->display(function () use
                    (
                        $value
                    ) {
                        return $value['condition'];
                    });
                    $detail->item('bonus', admin_trans('activity_phase.fields.bonus'))->display(function () use (
                        $value
                    ) {
                        return $value['bonus'];
                    });
                    $tabs = Tabs::create()->destroyInactiveTabPane();
                    /** @var ActivityContent $item */
                    $langList = plugin()->webman->config('ui.lang.list');
                    foreach ($value['notice'] as $key => $v) {
                        $tabs->pane($langList[$key], $v);
                    }
                    $detail->item('notice', admin_trans('activity_phase.fields.notice'))->display(function () use (
                        $tabs
                    ) {
                        return $tabs;
                    });
                }
            })->bordered()->layout('vertical');

            $phaseTabs->pane($item['cate_name'], $phaseDetail);
        }

        return $phaseTabs;
    }
}

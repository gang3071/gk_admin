<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Activity;
use addons\webman\model\ActivityContent;
use addons\webman\model\ActivityPhase;
use addons\webman\model\Channel;
use addons\webman\model\GameType;
use addons\webman\model\MachineCategory;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\Component;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Space;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use support\Db;
use think\Exception;

/**
 * 活动管理
 */
class ActivityController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.activity_model');

    }

    /**
     * 活动
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $grid->model()->with(['activity_content']);
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
            $grid->column('department_id', admin_trans('activity.fields.department_id'))->display(function (
                $val,
                Activity $data
            ) {
                if (!empty($data->department_id)) {
                    $channel = Channel::query()->whereIn('department_id',
                        json_decode($data->department_id, true))->get();
                    $tag = [];
                    if (!empty($channel)) {
                        /** @var Channel $item */
                        foreach ($channel as $item) {
                            $tag[] = Tag::create($item->name);
                        }
                        return Html::create()->content($tag)->style(['line-height' => '25px']);
                    }
                }
                return '';
            })->width('150px')->align('center');
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
            $grid->column('status', admin_trans('activity.fields.status'))->switch([
                [1 => ''],
                [0 => '']
            ])->align('center');
            $grid->sortInput('sort', admin_trans('activity.fields.sort'))->align('center');
            $grid->column('time_frame', admin_trans('activity.fields.time_frame'))
                ->display(function ($val, Activity $data) {
                    $time = '';
                    !empty($data->start_time) && $time .= $data->start_time;
                    !empty($data->end_time) && $time .= '~' . $data->end_time;
                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editTimeFrame'],
                        ['id' => $data->id])->title(admin_trans('activity.fields.time_frame'));
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
                $filter->like()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
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
            $grid->setForm()->drawer($this->activity());
        });
    }

    /**
     * 活动
     * @auth true
     * @return Component|Msg
     */
    public function activity()
    {
        $data = Request::input();
        $activity = [];
        if (!empty($data['id'])) {
            /** @var Activity $activityModel */
            $activityModel = Activity::find($data['id']);
            if (empty($activityModel)) {
                return message_error(admin_trans('activity.not_fount'));
            }
            $activity['id'] = $activityModel->id;
            $activity['type'] = $activityModel->type;
            $activity['department_id'] = json_decode($activityModel->department_id, true);
            $activity['range_time'] = [
                $activityModel->start_time,
                $activityModel->end_time,
            ];
            /** @var ActivityContent $item */
            foreach ($activityModel->activity_content as $item) {
                $activity['activity_content'][$item->lang] = [
                    'name' => $item->name,
                    'lang' => $item->lang,
                    'description' => $item->description,
                    'join_condition' => $item->join_condition,
                    'get_way' => $item->get_way,
                    'picture' => [
                        [
                            'url' => $item->picture,
                            'status' => 'done',
                        ]
                    ],
                    'id' => $item->id,
                ];
            }
            $key = 0;
            /** @var ActivityPhase $item */
            foreach ($activityModel->activity_phase as $item) {
                if (!isset($activity['activity_phase'][$item->cate_id]) && empty($activity['activity_phase'][$item->cate_id])) {
                    $activity['activity_phase'][$item->cate_id] = [
                        'key' => $key,
                        'cate_id' => $item->cate_id,
                        'cate_name' => $item->machineCategory->name,
                        'phase_list' => []
                    ];
                    $key++;
                }
                $activity['activity_phase'][$item->cate_id]['phase_list'][] = [
                    'id' => $item->id,
                    'condition' => $item->condition,
                    'bonus' => $item->bonus,
                    'notice' => json_decode($item->notice, true)
                ];
            }
            $activity['activity_phase'] = array_values($activity['activity_phase']);
        }
        $machineCategoryOptions = [
            [
                'label' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                'options' => []
            ],
            [
                'label' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                'options' => []
            ]
        ];
        $machineCategoryList = MachineCategory::with(['gameType'])->where('status', 1)->whereNull('deleted_at')->get();
        /** @var MachineCategory $item */
        foreach ($machineCategoryList as $item) {
            if ($item->gameType->type == GameType::TYPE_SLOT) {
                $machineCategoryOptions[0]['options'][] = [
                    'value' => $item->id,
                    'label' => $item->name,
                    'type' => GameType::TYPE_SLOT,
                ];
            }
            if ($item->gameType->type == GameType::TYPE_STEEL_BALL) {
                $machineCategoryOptions[1]['options'][] = [
                    'value' => $item->id,
                    'label' => $item->name,
                    'type' => GameType::TYPE_STEEL_BALL,
                ];
            }
        }
        $langs = [];
        $langList = plugin()->webman->config('ui.lang.list');
        foreach ($langList as $k => $v) {
            $langs[] = [
                'key' => $k,
                'value' => $v,
            ];
        }
        return Space::create()
            ->content(admin_view(plugin()->webman->getPath() . '/views/activity_tabs.vue')->attrs([
                'departmentOptions' => $this->getChannelOptions(),
                'activityModel' => $activity ?? [],
                'categoryOptions' => $machineCategoryOptions,
                'gameType' => [
                    [
                        'label' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT),
                        'value' => GameType::TYPE_SLOT,
                    ],
                    [
                        'label' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL),
                        'value' => GameType::TYPE_STEEL_BALL,
                    ],
                ],
                'langs' => $langs,
                'langLocale' => Container::getInstance()->translator->getLocale()
            ]))
            ->style(['width' => '100%', 'display' => 'block']);
    }

    /**
     * 筛选部门/渠道
     * @return array
     */
    public function getChannelOptions(): array
    {
        $channelList = Channel::query()->orderBy('created_at', 'desc')->get();
        $data = [];
        /** @var Channel $channel */
        foreach ($channelList as $channel) {
            $data[] = [
                'value' => $channel->department_id,
                'label' => $channel->name
            ];
        }
        return $data;
    }

    /**
     * 编辑活动开放时间
     * @auth true
     * @param int $id
     * @return Form
     */
    public function editTimeFrame(int $id): Form
    {
        /** @var Activity $data */
        $data = Activity::find($id);
        return Form::create($data, function (Form $form) use ($data) {
            $form->title(admin_trans('activity.fields.time_frame'));
            $form->dateTimeRange('start_time', 'end_time', admin_trans('activity.fields.time_frame'))
                ->value([$data->start_time, $data->end_time])
                ->required();
        });
    }

    /**
     * 编辑添加活动
     * @auth true
     * @return Msg
     */
    public function activityOperate(): Msg
    {
        $data = Request::input();
        if (empty($data['type'])) {
            return message_error(admin_trans('activity.game_type_fail'));
        }
        if (!empty($data['id'])) {
            /** @var Activity $activity */
            $activity = Activity::with(['activity_content'])->find($data['id']);
            if (empty($activity)) {
                return message_error(admin_trans('activity.not_fount'));
            }
            DB::beginTransaction();
            try {
                $departmentId = json_encode($data['department_id']);
                $activity->start_time = $data['range_time'][0];
                $activity->end_time = $data['range_time'][1];
                $activity->type = $data['type'];
                $activity->department_id = ($departmentId == 'null' || empty($departmentId)) ? null : $departmentId;
                if (!empty($data['activity_content'])) {
                    foreach ($data['activity_content'] as $key => $item) {
                        if (!empty($item['id'])) {
                            $activityContent = ActivityContent::find($item['id']);
                        } else {
                            $activityContent = new ActivityContent();
                        }
                        $this->addActivityContent($item, $activityContent, $activity, $key);
                    }
                } else {
                    throw new Exception(admin_trans('activity.activity_content_must'));
                }
                if (!empty($data['activity_phase'])) {
                    $activityPhaseArr = [];
                    foreach ($data['activity_phase'] as $item) {
                        $item = $this->checkCate($item, $activity);
                        if (!empty($item['phase_list'])) {
                            foreach ($item['phase_list'] as $phase) {
                                if (!empty($phase['id'])) {
                                    $activityPhaseArr[] = $phase['id'];
                                }
                            }
                        } else {
                            throw new Exception(admin_trans('activity.activity_phase_must'));
                        }
                    }
                    if (empty($activityPhaseArr)) {
                        ActivityPhase::where('activity_id', $activity->id)->delete();
                    } else {
                        ActivityPhase::where('activity_id', $activity->id)->whereNotIn('id',
                            $activityPhaseArr)->delete();
                    }
                    $catId = [];
                    foreach ($data['activity_phase'] as $item) {
                        $catId[] = $item['cate_id'];
                        if (!empty($item['phase_list'])) {
                            foreach ($item['phase_list'] as $phase) {
                                if (!empty($phase['id'])) {
                                    /** @var ActivityPhase $activityPhase */
                                    $activityPhase = ActivityPhase::find($phase['id']);
                                } else {
                                    $activityPhase = new ActivityPhase();
                                }
                                $this->activityPhase($activity, $activityPhase, $phase, $item['cate_id']);
                            }
                        } else {
                            throw new Exception(admin_trans('activity.activity_phase_must'));
                        }
                    }
                } else {
                    throw new Exception(admin_trans('activity.activity_phase_must'));
                }
                if (!empty($catId)) {
                    $activity->cate_id = implode(',', array_unique($catId));
                }
                $activity->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return message_error($e->getMessage());
            }
        } else {
            DB::beginTransaction();
            try {
                $departmentId = json_encode($data['department_id']);
                $activity = new Activity();
                $activity->start_time = $data['range_time'][0];
                $activity->end_time = $data['range_time'][1];
                $activity->type = $data['type'];
                $activity->department_id = ($departmentId == 'null' || empty($departmentId)) ? null : $departmentId;
                $activity->user_id = Admin::id() ?? 0;
                $activity->cate_id = '';
                $activity->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
                $activity->save();
                if (!empty($data['activity_content'])) {
                    foreach ($data['activity_content'] as $key => $item) {
                        $activityContent = new ActivityContent();
                        $this->addActivityContent($item, $activityContent, $activity, $key);
                    }
                } else {
                    throw new Exception(admin_trans('activity.activity_content_must'));
                }
                if (!empty($data['activity_phase'])) {
                    $catId = [];
                    foreach ($data['activity_phase'] as $item) {
                        $catId[] = $item['cate_id'];
                        if (!empty($item['phase_list'])) {
                            $this->checkCate($item, $activity);
                            foreach ($item['phase_list'] as $phase) {
                                $activityPhase = new ActivityPhase();
                                $this->activityPhase($activity, $activityPhase, $phase, $item['cate_id']);
                            }
                        } else {
                            throw new Exception(admin_trans('activity.activity_phase_must'));
                        }
                    }
                } else {
                    throw new Exception(admin_trans('activity.activity_phase_must'));
                }
                if (!empty($catId)) {
                    $activity->cate_id = implode(',', array_unique($catId));
                }
                $activity->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return message_error($e->getMessage());
            }
        }
        return message_success(admin_trans('form.save_success'));
    }

    /**
     * @param $item
     * @param ActivityContent $activityContent
     * @param Activity $activity
     * @param $lang
     * @return void
     */
    protected function addActivityContent($item, ActivityContent $activityContent, Activity $activity, $lang): void
    {
        $activityContent->name = $item['name'];
        $activityContent->picture = isset($item['picture'][0]['response']['data'][0]) && !empty($item['picture'][0]['response']['data'][0]) ? $item['picture'][0]['response']['data'][0] : ((isset($item['picture'][0]['url']) && !empty($item['picture'][0]['url'])) ? $item['picture'][0]['url'] : '');
        $activityContent->lang = $lang;
        $activityContent->get_way = $item['get_way'];
        $activityContent->description = $item['description'];
        $activityContent->join_condition = $item['join_condition'];
        $activityContent->activity_id = $activity->id;
        $activityContent->save();
    }

    /**
     * 验证机台类别
     * @param $item
     * @param Activity $activity
     * @return mixed
     * @throws Exception
     */
    protected function checkCate($item, Activity $activity)
    {
        if (empty($item['cate_id'])) {
            throw new Exception(admin_trans('activity.game_type_fail'));
        }
        /** @var MachineCategory $machineCategory */
        $machineCategory = MachineCategory::find($item['cate_id']);
        if (empty($machineCategory) || !empty($machineCategory->deleted_at) || !empty($machineCategory->gameType->deleted_at)) {
            throw new Exception(admin_trans('activity.game_type_fail'));

        }
        if ($machineCategory->gameType->type != $activity->type) {
            throw new Exception(admin_trans('activity.game_type_must_diff'));
        }
        return $item;
    }

    /**
     * @param Activity $activity
     * @param ActivityPhase $activityPhase
     * @param $item
     * @param $cateId
     * @return void
     */
    protected function activityPhase(Activity $activity, ActivityPhase $activityPhase, $item, $cateId): void
    {
        $activityPhase->activity_id = $activity->id;
        $activityPhase->cate_id = $cateId;
        $activityPhase->condition = $item['condition'];
        $activityPhase->bonus = $item['bonus'];
        $activityPhase->sort = $item['sort'] ?? 0;
        $activityPhase->notice = json_encode($item['notice'] ?? []);
        $activityPhase->save();
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
            $detail->item('cate_id', admin_trans('activity.fields.cate_id'))->display(function ($val, Activity $data) {
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
                    $detail->item('bonus', admin_trans('activity_phase.fields.bonus'))->display(function () use ($value
                    ) {
                        return $value['bonus'];
                    });
                    $tabs = Tabs::create()->destroyInactiveTabPane();
                    /** @var ActivityContent $item */
                    $langList = plugin()->webman->config('ui.lang.list');
                    foreach ($value['notice'] as $key => $v) {
                        $tabs->pane($langList[$key], $v);
                    }
                    $detail->item('notice', admin_trans('activity_phase.fields.notice'))->display(function () use ($tabs
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

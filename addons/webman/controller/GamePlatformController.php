<?php

namespace addons\webman\controller;

use addons\webman\model\GameExtend;
use addons\webman\model\GamePlatform;
use addons\webman\model\GameType;
use addons\webman\service\GamePlatformService;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Exception;
use Illuminate\Support\Str;
use support\Db;

/**
 * 电子游戏平台
 */
class GamePlatformController
{

    protected $model;

    protected $gameExtend;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.game_platform_model');
        $this->gameExtend = plugin()->webman->config('database.game_extend_model');
    }

    /**
     * 获取当前语言
     * @return string
     */
    private function getCurrentLang(): string
    {
        try {
            // 优先从 ExAdmin Container 获取
            $locale = Container::getInstance()->translator->getLocale();
            if ($locale) {
                return Str::replace('_', '-', $locale);
            }
        } catch (Exception $e) {
            // 忽略错误，继续尝试其他方式
        }

        // 从 cookie 获取
        $lang = request()->cookie('ex_admin_lang');
        if ($lang) {
            return Str::replace('_', '-', $lang);
        }

        // 默认返回中文
        return 'zh-CN';
    }

    /**
     * 游戏平台列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('game_platform.title'));
            $grid->model()->with(['defaultLimitGroup'])->orderBy('sort', 'desc')->orderBy('id', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->column('id', admin_trans('game_platform.fields.id'))->align('center');
            $grid->column('logo', admin_trans('game_platform.fields.logo'))->display(function ($val, $data) {
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data['logo']);
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('name', admin_trans('game_platform.fields.name'))->align('center');
            $grid->column('ratio', admin_trans('game_platform.fields.ratio'))->append('%')->align('center');
            $grid->column('cate_id', admin_trans('game_platform.fields.cate_id'))->display(function (
                $value,
                GamePlatform $data
            ) {
                $html = Html::create();
                if (!empty($data->cate_id)) {
                    foreach (json_decode($data->cate_id, true) as $item) {
                        $html->content(
                            Tag::create(admin_trans('game_type.game_type_cate.' . $item))
                                ->color('success')
                        );
                    }
                }
                return $html;
            })->align('center');

            // 默认限红组列
            $grid->column('default_limit_group', '默认限红组')->display(function ($value, GamePlatform $data) {
                // 只有ATG、RSG和DG平台显示默认限红组
                if (!in_array($data->code, ['ATG', 'RSG', 'DG'])) {
                    return '-';
                }

                if ($data->defaultLimitGroup) {
                    return $data->defaultLimitGroup->name . ' (' . $data->defaultLimitGroup->code . ')';
                }

                return '未设置';
            })->align('center');

            // 维护时间列
            $grid->column('maintenance_time', admin_trans('game_platform.fields.maintenance_time'))
                ->display(function ($value, GamePlatform $data) {
                    $time = '';
                    !empty($data->maintenance_week) && $time .= admin_trans('system_setting.week.' . $data->maintenance_week) . ' ';
                    !empty($data->maintenance_start_time) && $time .= $data->maintenance_start_time;
                    !empty($data->maintenance_end_time) && $time .= '~' . $data->maintenance_end_time;

                    if (empty($time)) {
                        $time = admin_trans('game_platform.no_maintenance');
                    }

                    $html = Html::create()->content([
                        Icon::create('FieldTimeOutlined'),
                        $time
                    ])->style(['cursor' => 'pointer']);
                    return Tag::create($html)->color('cyan')->modal([$this, 'editPlatformMaintain'], ['data' => $data]);
                })->align('center');

            $grid->sortInput('sort');
            $grid->column('status', admin_trans('game_platform.fields.status'))->switch()->align('center');
            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                // has_lobby = 1 只显示进入游戏大厅
                if (!empty($data['has_lobby'])) {
                    $actions->prepend(
                        Button::create(admin_trans('game_platform.enter_game'))->ajax([$this, 'enterGame'],
                            ['id' => $data['id']])
                    );
                } else {
                    // has_lobby = 0 只显示查看游戏
                    $actions->prepend(
                        Button::create(admin_trans('game_platform.view_game'))->modal([$this, 'getGameList'],
                            ['id' => $data['id']])->width('70%')
                    );
                }
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->hideTrashed();
        });
    }

    /**
     * 游戏类型
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('game_type.title'));
            $maxRatio = 100;
            $form->text('name', admin_trans('game_platform.fields.name'))->maxlength(50)->required();
            $form->text('code',
                admin_trans('game_platform.fields.code'))->maxlength(50)->disabled($form->isEdit())->required();
            $form->text('ratio')
                ->rulePattern('^[0-9]+(.[0-9]{1,2})?$', admin_trans('validator.twoDecimal'))
                ->rule([
                    'max:' . $maxRatio => admin_trans('validator.max', null, ['{max}' => $maxRatio]),
                    'min:0' => admin_trans('validator.min', null, ['{min}' => 0]),
                    'regex:/^[0-9]+(.[0-9]{1,2})?$/' => admin_trans('validator.twoDecimal'),
                ])
                ->required()
                ->addonAfter('%')
                ->help(admin_trans('game_platform.ratio_help'))
                ->placeholder(admin_trans('game_platform.ratio_placeholder'))
                ->addonBefore(admin_trans('game_platform.fields.ratio'));

            if ($form->isEdit()) {
                $string = $form->driver()->get('cate_id');
                $cateArr = explode(',', trim($string, '[]'));
                $cateArr = array_map('trim', $cateArr);
                $form->checkbox('cate_id', admin_trans('game_platform.fields.cate_id'))
                    ->value($cateArr)
                    ->options(getPlatformGameTypeOptions())
                    ->required();
            } else {
                $form->checkbox('cate_id', admin_trans('game_platform.fields.cate_id'))
                    ->options(getPlatformGameTypeOptions())
                    ->required();
            }

            // 游戏模式（仅在电子游戏分类时显示）
            $form->radio('display_mode', admin_trans('game_platform.fields.display_mode'))
                ->default(GamePlatform::DISPLAY_MODE_ALL)
                ->options([
                    GamePlatform::DISPLAY_MODE_LANDSCAPE => admin_trans('game_platform.display_mode.' . GamePlatform::DISPLAY_MODE_LANDSCAPE),
                    GamePlatform::DISPLAY_MODE_PORTRAIT => admin_trans('game_platform.display_mode.' . GamePlatform::DISPLAY_MODE_PORTRAIT),
                    GamePlatform::DISPLAY_MODE_ALL => admin_trans('game_platform.display_mode.' . GamePlatform::DISPLAY_MODE_ALL),
                ])
                ->help(admin_trans('game_platform.display_mode_help'));

            $form->image('logo', admin_trans('game_platform.fields.logo'))
                ->ext('jpg,png,jpeg')
                ->fileSize('1m')
                ->required();

            // 默认限红组（只在ATG/RSG/DG平台显示）
            if ($form->isEdit()) {
                $platformId = $form->driver()->get('id');
                $platformCode = $form->driver()->get('code');
                if (in_array($platformCode, ['ATG', 'RSG', 'DG'])) {
                    $form->select('default_limit_group_id', '默认限红组')
                        ->options($this->getLimitGroupOptionsForPlatform($platformId))
                        ->help('为该平台设置默认限红组，当店家未配置限红时使用');
                }
            }

            $form->switch('has_lobby', admin_trans('game_platform.fields.has_lobby'))->default(0)
                ->when(1, function (Form $form) {
                    $langList = plugin()->webman->config('ui.lang.list');
                    $tabs = $form->tabs()->destroyInactiveTabPane();
                    $contents = [];
                    if ($form->isEdit()) {
                        $contents = json_decode($form->driver()->get('picture'), true);
                    }
                    foreach ($langList as $k => $v) {
                        $tabs->pane($v, function (Form $form) use ($k, $contents) {
                            $form->image("picture." . $k . ".picture", admin_trans('game_platform.fields.picture'))
                                ->ext('jpg,png,jpeg')
                                ->value($contents[$k]['picture'] ?? '')
                                ->fileSize('3m')
                                ->help(admin_trans('game.help.picture_size'))
                                ->required();
                        });
                    }
                });
            $form->layout('vertical');
        });
    }

    /**
     * 进入游戏大厅
     * @param $id
     * @auth true
     * @return Notification
     */
    public function enterGame($id): Notification
    {
        try {
            $service = new GamePlatformService($this->getCurrentLang());
            $url = $service->enterLobby($id);

            return notification_success(
                admin_trans('admin.success'),
                admin_trans('game_platform.action_success')
            )->redirect($url);
        } catch (Exception $e) {
            return notification_error(
                admin_trans('admin.error'),
                $e->getMessage()
            );
        }
    }

    /**
     * 筛选游戏平台
     * @auth true
     * @return mixed
     */
    public function getGamePlatformOptions()
    {
        $request = Request::input();
        $gamePlatformList = GamePlatform::query()->orderBy('created_at', 'desc');
        if (!empty($request['search'])) {
            $gamePlatformList->where('name', 'like', '%' . $request['search'] . '%');
        }
        $list = $gamePlatformList->get();
        $data = [];
        /** @var GamePlatform $gamePlatform */
        foreach ($list as $gamePlatform) {
            $data[] = [
                'value' => $gamePlatform->id,
                'label' => $gamePlatform->name,
            ];
        }

        return Response::success($data);
    }

    /**
     * 筛选游戏分类
     * @auth true
     * @return mixed
     */
    public function getGameCateOptions()
    {
        return Response::success([
            [
                'value' => GameType::CATE_COMPUTER_GAME,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_COMPUTER_GAME),
            ],
            [
                'value' => GameType::CATE_SLO,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_SLO),
            ],
            [
                'value' => GameType::CATE_FISH,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_FISH),
            ],
            [
                'value' => GameType::CATE_P2P,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_P2P),
            ],
            [
                'value' => GameType::CATE_SPORT,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_SPORT),
            ],
            [
                'value' => GameType::CATE_ARCADE,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_ARCADE),
            ],
            [
                'value' => GameType::CATE_LOTTERY,
                'label' => admin_trans('game_type.game_type_cate.' . GameType::CATE_LOTTERY),
            ],
        ]);
    }

    /**
     * 查看游戏
     * @param $id
     * @auth true
     * @return
     */
    public function getGameList($id)
    {
        /** @var GamePlatform $gamePlatform */
        $gamePlatform = GamePlatform::query()
            ->where('id', $id)
            ->select(['id', 'code', 'name'])
            ->first();
        if (empty($gamePlatform)) {
            return notification_error(admin_trans('admin.error'), admin_trans('game_platform.not_fount'));
        }
        try {
            // 调用服务获取游戏列表
            $service = new GamePlatformService($this->getCurrentLang());
            $service->getGameList($gamePlatform);
        } catch (Exception $e) {
            return notification_error(
                admin_trans('admin.error'),
                $e->getMessage()
            );
        }

        return Grid::create(new $this->gameExtend(), function (Grid $grid) use ($gamePlatform) {
            $grid->model()->where('platform_id', $gamePlatform->id)->orderBy('id', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            switch ($gamePlatform->code) {
                case GamePlatform::TYPE_ATG:
                    $grid->column('logo', 'Logo')->display(function ($val, GameExtend $data) {
                        $image = Image::create()
                            ->width(50)
                            ->height(50)
                            ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                            ->src($data['logo']);
                        return Html::create()->content([
                            $image,
                        ]);
                    })->align('center');
                    $grid->column('name', admin_trans('game_extend.fields.name'))->display(function (
                        $value,
                        GameExtend $data
                    ) {
                        if ($data->is_new == 1) {
                            $tagNew = Tag::create(admin_trans('game_extend.new'))->color('#108ee9');
                        }
                        if ($data->is_hot == 1) {
                            $hotNew = Tag::create(admin_trans('game_extend.hot'))->color('#f50');
                        }

                        return Html::create()->content([
                            $value,
                            $tagNew ?? '',
                            $hotNew ?? '',
                        ]);
                    })->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->copy()->align('center');
                    $grid->column('status', admin_trans('game_extend.fields.status'))->switch()->align('center');
                    break;
                case GamePlatform::TYPE_BTG:
                case GamePlatform::TYPE_WM:
                case GamePlatform::TYPE_RSG:
                case GamePlatform::TYPE_JDB:
                case GamePlatform::TYPE_SP:
                case GamePlatform::TYPE_SA:
                case GamePlatform::TYPE_O8:
                case GamePlatform::TYPE_O8_STM:
                case GamePlatform::TYPE_O8_HS:
                case GamePlatform::TYPE_KT:
                case GamePlatform::TYPE_QT:
                case GamePlatform::TYPE_TNINE_SLOT:
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->copy()->align('center');
                    break;
                case GamePlatform::TYPE_DG:
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->copy()->align('center');
                    $grid->column('table_name', admin_trans('game_extend.fields.table_name'))->copy()->align('center');
                    break;
                case GamePlatform::TYPE_KY:
                    $grid->column('logo', 'Logo')->display(function ($val, GameExtend $data) {
                        $image = Image::create()
                            ->width(50)
                            ->height(50)
                            ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                            ->src($data['logo']);
                        return Html::create()->content([
                            $image,
                        ]);
                    })->align('center');
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->align('center');
                    $grid->setForm()->modal([$this, 'addKYGame'])->title(admin_trans('game_extend.fields.name'));
                    break;
                case GamePlatform::TYPE_YZG:
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->align('center');
                    $grid->setForm()->drawer($this->addKYGame());
                    break;
                default:
                    break;
            }
            $grid->column('status', admin_trans('game_extend.fields.status'))->display(function ($value) {
                return Html::create()->content([
                    $value == 1 ? Tag::create(admin_trans('game_extend.normal'))->color('#108ee9') : Tag::create(admin_trans('game_extend.maintain'))->color('#f50'),
                ]);
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('game_extend.fields.name'));
                $filter->like()->text('code')->placeholder(admin_trans('game_extend.fields.code'));
                $filter->like()->text('table_name')->placeholder(admin_trans('game_extend.fields.table_name'));
            });
            $grid->expandFilter();
        });
    }

    /**
     * 添加KY 游戏
     * @return Form
     */
    public function addKYGame() : Form
    {
        return Form::create(new $this->gameExtend(), function (Form $form) {
            $form->title(admin_trans('game_type.title'));
            $form->hidden('platform_id')->value(37);
            $form->hidden('cate_id')->value(GameType::TYPE_FISH);
            $form->text('name', admin_trans('game_extend.fields.name'))->required();
            $form->text('code', admin_trans('game_extend.fields.code'))->required();
            $form->image('logo', admin_trans('game_extend.fields.logo'))->required();
            $form->switch('is_new', admin_trans('game_extend.fields.is_new'))->default(0);
            $form->switch('is_hot', admin_trans('game_extend.fields.is_hot'))->default(0);
            $form->switch('status', admin_trans('game_extend.fields.status'))->default(0);
            $form->saving(function (Form $form) {
                if (!$form->isEdit()) {
                    $gameExtend = new GameExtend();
                } else {
                    $id = $form->driver()->get('id');
                    $gameExtend = GameExtend::query()->find($id);
                }
                DB::beginTransaction();
                try {
                    $gameExtend->platform_id = $form->input('platform_id');
                    $gameExtend->cate_id = $form->input('cate_id');
                    $gameExtend->name = $form->input('name');
                    $gameExtend->code = $form->input('code');
                    $gameExtend->logo = $form->input('logo');
                    $gameExtend->is_new = $form->input('is_new');
                    $gameExtend->is_hot = $form->input('is_hot');
                    $gameExtend->status = $form->input('status');
                    $gameExtend->org_data = $form->input('org_data') ?? 'NULL'; //自行设置没有拿到源数据
                    $gameExtend->save();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error(admin_trans('form.save_fail') . $e->getMessage());
                }
                return message_success(admin_trans('game_extend.save_success'));
            });
        });
    }

    /**
     * 获取限红组选项（用于游戏平台表单）
     * 只返回该平台已配置的限红组
     * @param int|null $platformId 游戏平台ID
     * @return array
     */
    private function getLimitGroupOptionsForPlatform($platformId = null): array
    {
        $data = [];
        $limitGroupModel = 'addons\webman\model\PlatformLimitGroup';
        $configModel = 'addons\webman\model\PlatformLimitGroupConfig';

        if (!class_exists($limitGroupModel) || !class_exists($configModel)) {
            return $data;
        }

        // 只返回该平台已配置的限红组
        if ($platformId) {
            $list = $limitGroupModel::query()
                ->where('status', 1)
                ->whereHas('configs', function ($query) use ($platformId) {
                    $query->where('platform_id', $platformId)
                          ->where('status', 1)
                          ->whereNull('deleted_at');
                })
                ->orderBy('sort', 'asc')
                ->get();

            foreach ($list as $item) {
                $data[$item->id] = "{$item->name} ({$item->code})";
            }
        }

        return $data;
    }

    /**
     * 游戏平台维护时间编辑
     * @auth true
     * @param GamePlatform $data
     * @return Form
     */
    public function editPlatformMaintain(GamePlatform $data): Form
    {
        /** @var GamePlatform $platform */
        $platform = GamePlatform::query()->where('id', $data->id)->first();

        return Form::create($platform, function (Form $form) use ($platform) {
            $form->title(admin_trans('game_platform.maintenance_title'));

            // 维护功能开关
            $form->switch('maintenance_status', admin_trans('game_platform.fields.maintenance_status'))
                ->value($platform->maintenance_status ?? 0)
                ->help(admin_trans('game_platform.maintenance_status_help'));

            // 星期选择
            $form->select('maintenance_week', admin_trans('system_setting.week_str'))
                ->value($platform->maintenance_week)
                ->options([
                    1 => admin_trans('system_setting.week.1'),
                    2 => admin_trans('system_setting.week.2'),
                    3 => admin_trans('system_setting.week.3'),
                    4 => admin_trans('system_setting.week.4'),
                    5 => admin_trans('system_setting.week.5'),
                    6 => admin_trans('system_setting.week.6'),
                    7 => admin_trans('system_setting.week.7'),
                ]);

            // 时间范围
            $form->timeRange('maintenance_start_time', 'maintenance_end_time', admin_trans('system_setting.time_range'))
                ->value([$platform->maintenance_start_time, $platform->maintenance_end_time]);
        });
    }
}

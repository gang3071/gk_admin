<?php

namespace addons\webman\controller;

use addons\webman\model\GameExtend;
use addons\webman\model\GamePlatform;
use addons\webman\model\GameType;
use addons\webman\model\Player;
use app\service\game\GameServiceFactory;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use ExAdmin\ui\support\Container;
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
            $grid->model()->orderBy('sort', 'desc')->orderBy('id', 'desc');
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
        /** @var GamePlatform $gamePlatform */
        $gamePlatform = GamePlatform::query()
            ->where('id', $id)
            ->select(['id', 'code', 'name'])
            ->first();
        if (empty($gamePlatform)) {
            return notification_error(admin_trans('admin.error'), admin_trans('game_platform.not_fount'));
        }
        $player = Player::query()->where('is_admin', 1)->first();
        if (empty($player)) {
            return notification_error(admin_trans('admin.error'),
                admin_trans('game_platform.player_not_fount'));
        }
        $lang = $this->getCurrentLang();

        try {
            // 调用 gk_work 管理后台专用 API
            $workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
            $workerPort = env('GAME_PLATFORM_PROXY_PORT', '8788');
            $endpoint = '/api/admin/lobby-login';
            $proxyUrl = "http://{$workerHost}:{$workerPort}{$endpoint}";

            // 使用 curl 发送请求
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'game_platform_id' => $gamePlatform->id,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Player-Id: ' . $player->id,
                'Accept: application/json',
                'Content-Type: application/json',
                'Accept-Language: ' . $lang,
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // 检查 curl 错误
            if ($curlError) {
                throw new Exception(admin_trans('message.system_busy') . ': ' . $curlError);
            }

            // 检查 HTTP 状态码
            if ($httpCode !== 200) {
                $errorMsg = admin_trans('message.system_busy') . ' (HTTP ' . $httpCode . ')';

                // 尝试解析响应中的错误信息
                if ($response) {
                    $errorData = json_decode($response, true);
                    if (!empty($errorData['msg'])) {
                        $errorMsg = $errorData['msg'] . ' (HTTP ' . $httpCode . ')';
                    }
                }

                throw new Exception($errorMsg);
            }

            $data = json_decode($response, true);
            if (empty($data) || $data['code'] != 200) {
                throw new Exception($data['msg'] ?? admin_trans('game_platform.action_error'));
            }

            $res = $data['data']['url'] ?? $data['data']['lobby_url'] ?? '';
        } catch (Exception $e) {
            return notification_error(admin_trans('admin.error'),
                $e->getMessage() ?? admin_trans('game_platform.action_error'));
        }
        return notification_success(admin_trans('admin.success'),
            admin_trans('game_platform.action_success'))->redirect($res);
    }

    /**
     * 筛选游戏平台
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
     * 筛选游戏平台
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
        $player = Player::query()->where('is_admin', 1)->first();
        if (empty($player)) {
            return notification_error(admin_trans('admin.error'),
                admin_trans('game_platform.player_not_fount'));
        }
        $lang = $this->getCurrentLang();

        try {
            // 调用 gk_work 管理后台专用 API 获取并保存游戏列表
            $workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
            $workerPort = env('GAME_PLATFORM_PROXY_PORT', '8788');
            $endpoint = '/api/admin/get-game-list';
            $proxyUrl = "http://{$workerHost}:{$workerPort}{$endpoint}";

            // 使用 curl 发送请求
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'game_platform_id' => $gamePlatform->id,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Player-Id: ' . $player->id,
                'Accept: application/json',
                'Content-Type: application/json',
                'Accept-Language: ' . $lang,
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // 检查 curl 错误
            if ($curlError) {
                throw new Exception(admin_trans('message.system_busy') . ': ' . $curlError);
            }

            // 检查 HTTP 状态码
            if ($httpCode !== 200) {
                $errorMsg = admin_trans('message.system_busy') . ' (HTTP ' . $httpCode . ')';

                // 尝试解析响应中的错误信息
                if ($response) {
                    $errorData = json_decode($response, true);
                    if (!empty($errorData['msg'])) {
                        $errorMsg = $errorData['msg'] . ' (HTTP ' . $httpCode . ')';
                    }
                }

                throw new Exception($errorMsg);
            }

            $data = json_decode($response, true);
            if (empty($data) || $data['code'] != 200) {
                throw new Exception($data['msg'] ?? admin_trans('game_platform.action_error'));
            }
        } catch (Exception $e) {
            return notification_error(admin_trans('admin.error'),
                $e->getMessage() ?? admin_trans('game_platform.action_error'));
        }

        return Grid::create(new $this->gameExtend(), function (Grid $grid) use ($gamePlatform) {
            $grid->model()->where('platform_id', $gamePlatform->id)->orderBy('id', 'desc');
            $grid->bordered(true);
            $grid->autoHeight();
            switch ($gamePlatform->code) {
                case GameServiceFactory::TYPE_ATG:
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
                case GameServiceFactory::TYPE_BTG:
                case GameServiceFactory::TYPE_WM:
                case GameServiceFactory::TYPE_RSG:
                case GameServiceFactory::TYPE_JDB:
                case GameServiceFactory::TYPE_SP:
                case GameServiceFactory::TYPE_SA:
                case GameServiceFactory::TYPE_O8:
                case GameServiceFactory::TYPE_O8_STM:
                case GameServiceFactory::TYPE_O8_HS:
                case GameServiceFactory::TYPE_KT:
                case GameServiceFactory::TYPE_TNINE_SLOT:
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->copy()->align('center');
                    break;
                case GameServiceFactory::TYPE_DG:
                    $grid->column('name', admin_trans('game_extend.fields.name'))->align('center');
                    $grid->column('code', admin_trans('game_extend.fields.code'))->copy()->align('center');
                    $grid->column('table_name', admin_trans('game_extend.fields.table_name'))->copy()->align('center');
                    break;
                case GameServiceFactory::TYPE_KY:
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
                case GameServiceFactory::TYPE_YZG:
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
}

<?php

namespace addons\webman\controller;

use addons\webman\form\MyEditor;
use addons\webman\model\Channel;
use addons\webman\model\Game;
use addons\webman\model\GameContent;
use addons\webman\model\GameExtend;
use addons\webman\model\GamePlatform;
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
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Exception;
use Illuminate\Support\Str;

/**
 * 公告
 */
class GameController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.game_model');
    }
    
    /**
     * 游戏列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('game.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->model()->orderBy('status', 'desc')->orderBy('sort', 'desc');
            $grid->column('picture', admin_trans('game.fields.picture'))->display(function ($val, Game $data) use ($lang
            ) {
                /** @var GameContent $gameContent */
                $gameContent = $data->gameContent->where('lang', $lang)->first();
                $image = Image::create()
                    ->width(50)
                    ->height(50)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($gameContent->picture ?? '');
                return Html::create()->content([
                    $image,
                ]);
            })->align('center');
            $grid->column('name', admin_trans('game.fields.name'))->display(function ($val, Game $data) use ($lang) {
                /** @var GameContent $gameContent */
                $gameContent = $data->gameContent->where('lang', $lang)->first();
                return Html::create()->content([
                    $gameContent->name ?? '',
                ]);
            })->align('center');
            $grid->column('gamePlatform.name', admin_trans('game_platform.fields.name'))->display(function (
                $value,
                Game $data
            ) {
                return Html::create()->content(
                    $data->gamePlatform->name
                );
            })->align('center');
            $grid->column('game_extend.code', admin_trans('game.fields.code'))->align('center');
            $grid->column('cate_id', admin_trans('game.fields.cate_id'))->display(function ($value, Game $data) {
                return Html::create()->content(
                    Tag::create(admin_trans('game_type.game_type_cate.' . $data->cate_id))
                        ->color('success')
                );
            })->align('center');
            $grid->column('display_mode', admin_trans('game.fields.display_mode'))->display(function ($val) {
                return Html::create()->content([
                    admin_trans('game.display_mode.' . $val)
                ]);
            })->align('center');
            $grid->sortInput('sort', admin_trans('game.fields.sort'))->align('center');
            $grid->column('is_new', admin_trans('game.fields.is_new'))->switch()->align('center');
            $grid->column('is_hot', admin_trans('game.fields.is_hot'))->switch()->align('center');
            $grid->column('is_ios', admin_trans('game.fields.is_ios'))->switch()->align('center');
            $grid->column('status', admin_trans('game.fields.status'))->switch()->align('center');
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('game_extend.code')->placeholder(admin_trans('game.fields.code'));
                $filter->like()->text('gameContent.name')->placeholder(admin_trans('game.fields.name'));
                $filter->eq()->select('cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game.fields.cate_id'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGameCateOptions'
                    ]));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_platform.fields.name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
            });
            $grid->expandFilter();
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $actions->hideDetail();
                $actions->prepend(
                    Button::create(admin_trans('game.enter_game'))->ajax([$this, 'enterGame'],
                        ['id' => $data['id']])
                );
            })->align('center');
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
        /** @var Game $game */
        $game = Game::query()
            ->where('id', $id)
            ->first();
        if (empty($game)) {
            return notification_error(admin_trans('admin.error'), admin_trans('game.not_fount'));
        }
        if (empty($game->gamePlatform)) {
            return notification_error(admin_trans('admin.error'), admin_trans('game_platform.not_fount'));
        }
        if ($game->gamePlatform->status == 0) {
            return notification_error(admin_trans('admin.error'), admin_trans('game_platform.disable'));
        }
        $player = Player::query()->where('is_admin', 1)->first();
        if (empty($player)) {
            return notification_error(admin_trans('admin.error'),
                admin_trans('game_platform.player_not_fount'));
        }
        $lang = locale();
        $lang = Str::replace('_', '-', $lang);
        try {
            $res = GameServiceFactory::createService(strtoupper($game->gamePlatform->code), $player)->gameLogin($game,
                $lang);
        } catch (Exception $e) {
            return notification_error(admin_trans('admin.error'),
                $e->getMessage() ?? admin_trans('game_platform.action_error'))->redirect('');
        }
        return notification_success(admin_trans('admin.success'),
            admin_trans('game_platform.action_success'))->redirect($res);
    }
    
    /**
     * 添加游戏
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        Form::extend('myEditor', MyEditor::class);
        
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('game.title'));
            $form->row(function (Form $form) {
                $form->select('platform_id', admin_trans('game.fields.platform_id'))
                    ->options($this->getGamePlatform())
                    ->load(
                        $form->select('game_extend_id', admin_trans('game.fields.game_extend_id'))
                            ->style(['width' => '200px', 'margin-left' => '10px'])->required(),
                        admin_url([$this, 'getGame'])
                    )->style(['width' => '200px'])->required();
            });
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->select('cate_id', admin_trans('game.fields.cate_id'))
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->placeholder(admin_trans('game.fields.cate_id'))
                        ->remoteOptions(admin_url([
                            'addons-webman-controller-GamePlatformController',
                            'getGameCateOptions'
                        ]))->required();
                })->span(10);
                $form->push(Divider::create()->content(' ')->style(['margin-left' => '8px']));
                $form->column(function (Form $form) {
                    $form->switch('status', admin_trans('game.fields.status'))->default(1);
                })->span(11);
            });
            $form->row(function (Form $form) {
                $form->column(function (Form $form) {
                    $form->switch('is_new', admin_trans('game.fields.is_new'))->default(1);
                })->span(10);
                $form->push(Divider::create()->content(' ')->style(['margin-left' => '8px']));
                $form->column(function (Form $form) {
                    $form->switch('is_hot', admin_trans('game.fields.is_hot'))->default(1);
                })->span(11);
            });

            $ChannelList = Channel::query()->select('department_id', 'name')->where('status', 1)->get();
            $optionList = [];
            /** @var Channel $item */
            foreach ($ChannelList as $item) {
                $optionList[$item->department_id] = $item->name;
            }
            if ($form->isEdit()) {
                $string = $form->driver()->get('channel_hidden');
                $channelArr = explode(',', trim($string, '[]'));
                $channelArr = array_map('trim', $channelArr);
                $form->checkbox('channel_hidden', admin_trans('game.fields.channel_hidden'))
                    ->value($channelArr)
                    ->options($optionList);
            } else {
                $form->checkbox('channel_hidden', admin_trans('game.fields.channel_hidden'))
                    ->options($optionList);
            }
            $form->radio('display_mode', admin_trans('game.fields.display_mode'))
                ->button()
                ->options([
                    1 => admin_trans('game.display_mode.1'),
                    2 => admin_trans('game.display_mode.2'),
                    3 => admin_trans('game.display_mode.3'),
                ])
                ->required();
            $langList = plugin()->webman->config('ui.lang.list');
            $tabs = $form->tabs()->destroyInactiveTabPane();
            $contents = [];
            if ($form->isEdit()) {
                $contents = $form->driver()->get('gameContent')->mapWithKeys(function (gameContent $content) {
                    return [
                        $content->lang => [
                            'name' => $content->name,
                            'description' => $content->description,
                            'picture' => $content->picture,
                            'id' => $content->id,
                        ]
                    ];
                });
            }
            foreach ($langList as $k => $v) {
                $tabs->pane($v, function (Form $form) use ($k, $contents) {
                    $form->text("content." . $k . ".name", admin_trans('game.fields.name'))
                        ->value($contents[$k]['name'] ?? '')
                        ->required()->maxlength(200)
                        ->help(admin_trans('game.help.name'));
                    $form->image("content." . $k . ".picture", admin_trans('game.fields.picture'))
                        ->ext('jpg,png,jpeg')
                        ->value($contents[$k]['picture'] ?? '')
                        ->fileSize('3m')
                        ->help(admin_trans('game.help.picture_size'))
                        ->required();
                    $form->myEditor("content." . $k . ".description", admin_trans('game.fields.description'))
                        ->value(isset($contents[$k]['description']) ? (string)$contents[$k]['description'] : '');
                    $form->hidden('content_id')->default($contents[$k]['id'] ?? '');
                });
            }
            $form->layout('vertical');
            $form->saving(function (Form $form) {
                try {
                    if (!$form->isEdit()) {
                        $game = new Game();
                    } else {
                        $id = $form->driver()->get('id');
                        $game = Game::query()->find($id);
                    }
                    $game->status = $form->input('status');
                    $game->is_hot = $form->input('is_hot');
                    $game->is_new = $form->input('is_new');
                    $game->display_mode = $form->input('display_mode');
                    $game->platform_id = $form->input('platform_id');
                    $game->game_extend_id = $form->input('game_extend_id');
                    $game->cate_id = $form->input('cate_id');
                    $game->channel_hidden = json_encode($form->input('channel_hidden'));
                    $game->save();
                    $contents = $form->input('content');
                    foreach ($contents as $key => $content) {
                        GameContent::query()->updateOrCreate(
                            [
                                'lang' => $key,
                                'game_id' => $game->id,
                                'platform_id' => $game->platform_id,
                            ],
                            [
                                'name' => $content['name'] ?? '',
                                'description' => $content['description'] ?? '',
                                'picture' => $content['picture'] ?? '',
                            ]
                        );
                    }
                } catch (Exception $e) {
                    return message_error(admin_trans('form.save_fail') . $e->getMessage());
                }
                return message_success(admin_trans('form.save_success'));
            });
        });
    }
    
    /**
     * 筛选游戏平台
     * @return array
     */
    public function getGamePlatform(): array
    {
        $data = [];
        $gamePlatformList = GamePlatform::query()->where('has_lobby', 0)->orderBy('created_at', 'desc')->get();
        /** @var GamePlatform $gamePlatform */
        foreach ($gamePlatformList as $gamePlatform) {
            $data[$gamePlatform->id] = $gamePlatform->name;
        }
        
        return $data;
    }
    
    /**
     * 筛选游戏
     * @return mixed
     */
    public function getGame()
    {
        $request = Request::input();
        $data = [];
        $gameExtendList = GameExtend::query()->where('platform_id', $request['value'])->get();
        /** @var GameExtend $game */
        foreach ($gameExtendList as $game) {
            $data[] = [
                'value' => $game->id,
                'label' => $game->name . $game->table_name,
            ];
        }
        
        return Response::success([$request['optionsField'] => $data]);
    }
}

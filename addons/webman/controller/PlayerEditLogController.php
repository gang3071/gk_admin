<?php

namespace addons\webman\controller;

use addons\webman\model\PlayerEditLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;

/**
 * 玩家资料修改日志
 */
class PlayerEditLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_edit_log_model');

    }

    /**
     * 玩家资料修改日志
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->model()->with(['player', 'user'])->orderBy('created_at', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['department_id'])) {
                    $grid->model()->where('department_id', $requestFilter['department_id']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
                if (isset($requestFilter['search_type'])) {
                    $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                        $query->where('is_test', $requestFilter['search_type']);
                    });
                }
            }
            $grid->title(admin_trans('player_edit_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_edit_log.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, PlayerEditLog $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                    ]);
                })
                ->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerEditLog $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function ($val, PlayerEditLog $data) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone),
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('player.phone')
            );
            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function ($val, PlayerEditLog $data) {
                $image = Image::create()
                    ->width(30)
                    ->height(30)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data->user->avatar);
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->user->nickname)
                ]);
            })->align('center');
            $grid->column('new_data', admin_trans('player_edit_log.action_info'))->display(function ($val, PlayerEditLog $data) {
                $content = [];
                $newData = json_decode($val, true);
                foreach ($newData as $key => $item) {
                    switch ($key) {
                        case 'is_coin':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.set_coin'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.cancel_coin'));
                            }
                            break;
                        case 'is_promoter':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.set_promoter'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.cancel_promoter'));
                            }
                            break;
                        case 'status':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_stop'));
                            }
                            break;
                        case 'status_withdraw':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_withdraw_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_withdraw_close'));
                            }
                            break;
                        case 'status_open_point':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_open_point_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_open_point_close'));
                            }
                            break;
                        case 'status_reverse_water':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_reverse_water_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_reverse_water_close'));
                            }
                            break;
                        case 'status_national':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_national_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_national_close'));
                            }
                            break;
                        case 'status_machine':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_machine_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_machine_close'));
                            }
                            break;
                        case 'status_offline_open':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_offline_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_offline_close'));
                            }
                            break;
                        case 'status_baccarat':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_baccarat_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_baccarat_close'));
                            }
                            break;
                        case 'status_game_platform':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_game_platform_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.status_game_platform_close'));
                            }
                            break;
                        case 'is_test':
                            if ($item == 1) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.is_test_open'));
                            }
                            if ($item == 0) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.is_test_close'));
                            }
                            break;
                        case 'machine_play_num':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.machine_play_num') . $item);
                            break;
                        case 'name':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.name') . $item);
                            break;
                        case 'phone':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.phone') . $item);
                            break;
                        case 'country_code':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.country_code') . $item);
                            break;
                        case 'play_password':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.play_password') . '******');
                            break;
                        case 'password':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.password') . '******');
                            break;
                        case 'avatar':
                            if (!empty($item)) {
                                $content[] = Html::div()->content(admin_trans('player_edit_log.action.avatar'));
                                $content[] = Avatar::create()->src(is_numeric($item) ? config('def_avatar.' . $item) : $item);
                            }
                            break;
                        case 'sex':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.sex') . ($item == 1 ? 'name' : ($item == 2 ? '女' : '未知')));
                            break;
                        case 'email':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.email') . $item);
                            break;
                        case 'qq':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.qq') . $item);
                            break;
                        case 'telegram':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.telegram') . $item);
                            break;
                        case 'birthday':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.birthday') . $item);
                            break;
                        case 'id_number':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.id_number') . $item);
                            break;
                        case 'address':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.address') . $item);
                            break;
                        case 'wechat':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.wechat') . $item);
                            break;
                        case 'whatsapp':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.whatsapp') . $item);
                            break;
                        case 'facebook':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.facebook') . $item);
                            break;
                        case 'line':
                            $content[] = Html::div()->content(admin_trans('player_edit_log.action.line') . $item);
                            break;
                        case 'remark':
                            $content[] = ToolTip::create(admin_trans('player_edit_log.action.remark') . Str::of($item)->limit(20, ' (...)'))->title($item);
                            break;
                    }
                }
                return Html::create()->content($content);
            })->align('left');
            $grid->column('created_at', admin_trans('player_edit_log.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->in()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.admin_user'))
                    ->options(getAdminUserListOptions())
                    ->multiple();
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('player_edit_log.created_at_start'), admin_trans('player_edit_log.created_at_end')]);
            });
            $grid->expandFilter();
        });
    }
}

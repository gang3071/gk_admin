<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\MachineEditLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
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
 * 机台异动日志
 */
class MachineEditLogController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_edit_log_model');

    }

    /**
     * 机台异动日志
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->model()->with(['machine'])->orderBy('id', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
            }
            $grid->title(admin_trans('machine_edit_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('machine_edit_log.fields.id'))->align('center');
            $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function ($val, MachineEditLog $data) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal(['addons-webman-controller-PlayerDeliveryRecordController', 'machineInfo'], ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);;
                }
                return '';
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->align('center')
                ->filter(
                    FilterColumn::like()->text('machine.code')
                );
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val, MachineEditLog $data) {
                $tag = '';
                switch ($data->machine->type) {
                    case GameType::TYPE_STEEL_BALL:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data->machine->type))->color('#f50');
                        break;
                    case GameType::TYPE_SLOT:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data->machine->type))->color('#2db7f5');
                        break;
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center');
            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function ($val, MachineEditLog $data) {
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
            $grid->column('new_data', admin_trans('machine_edit_log.action_info'))->display(function ($val) {
                $content = [];
                $newData = json_decode($val, true);
                foreach ($newData as $key => $item) {
                    switch ($key) {
                        case 'cate_id':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.cate_id') . $item);
                            break;
                        case 'name':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.name') . $item);
                            break;
                        case 'code':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.code') . $item);
                            break;
                        case 'picture_url':
                            if (!empty($item)) {
                                $content[] = Html::div()->content(admin_trans('machine_edit_log.action.picture_url'));
                                $content[] = Avatar::create()->src($item);
                            }
                            break;
                        case 'type':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.type') . admin_trans('game_type.game_type.' . $item));
                            break;
                        case 'domain':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.domain') . $item);
                            break;
                        case 'ip':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.ip') . $item);
                            break;
                        case 'port':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.port') . $item);
                            break;
                        case 'currency':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.currency') . $item);
                            break;
                        case 'odds_x':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.odds_x') . $item);
                            break;
                        case 'odds_y':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.odds_y') . $item);
                            break;
                        case 'min_point':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.min_point') . $item);
                            break;
                        case 'max_point':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.max_point') . $item);
                            break;
                        case 'control_open_point':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.control_open_point') . $item);
                            break;
                        case 'auto_up_turn':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.auto_up_turn') . ($item == 1 ? admin_trans('admin.open') : admin_trans('admin.close')));
                            break;
                        case 'push_auto':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.push_auto') . ($item == 1 ? admin_trans('admin.open') : admin_trans('admin.close')));
                            break;
                        case 'keep_seconds':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.keep_seconds') . $item);
                            break;
                        case 'wash_limit':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.wash_limit') . $item);
                            break;
                        case 'remark':
                            $content[] = ToolTip::create(admin_trans('machine_edit_log.action.remark') . Str::of($item)->limit(20, ' (...)'))->title($item);
                            break;
                        case 'correct_rate':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.correct_rate') . $item);
                            break;
                        case 'status':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.status') . ($item == 1 ? admin_trans('admin.open') : admin_trans('admin.close')));
                            break;
                        case 'gaming_user_id':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.gaming_user_id') . ($item == 0 ? admin_trans('machine_edit_log.action.out_player') : admin_trans('machine_edit_log.action.change_player')));
                            break;
                        case 'sort':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.sort') . $item);
                            break;
                        case 'strategy_id':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.strategy_id') . $item);
                            break;
                        case 'is_use':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.is_use') . ($item == 0 ? admin_trans('machine_edit_log.action.is_use_close') : admin_trans('machine_edit_log.action.is_use_open')));
                            break;
                        case 'maintaining':
                            $content[] = Html::div()->content(admin_trans('machine_edit_log.action.maintaining') . ($item == 0 ? admin_trans('machine_edit_log.action.maintaining_closed') : admin_trans('machine_edit_log.action.maintaining_open')));
                            break;
                    }
                }
                return Html::create()->content($content);
            })->align('left');
            $grid->column('created_at', admin_trans('machine_edit_log.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions, MachineEditLog $data) {
                $actions->hideDel();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.machineLabel.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->in()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('machine_edit_log.admin_user'))
                    ->options(getAdminUserListOptions())
                    ->multiple();
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([admin_trans('machine_edit_log.created_at_start'), admin_trans('machine_edit_log.created_at_end')]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 机台异动日志详情
     * @param $machineEditLog
     * @auth true
     * @return Detail
     */
    public function detail($machineEditLog): Detail
    {
        return Detail::create($machineEditLog, function (Detail $detail) use ($machineEditLog) {
            $source = $machineEditLog['source'];
            $detail->item('new_data')->display(function ($val) use ($source) {
                if (!empty($val)) {
                    $newData = json_decode($val, true);
                    return Detail::create($newData, function (Detail $detail) use ($newData, $source) {
                        foreach ($newData as $key => $item) {
                            switch ($key) {
                                case 'remark':
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key))->tip();
                                    break;
                                case 'picture_url':
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key))->image();
                                    break;
                                default:
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key));
                            }
                        }
                    })->bordered()->column(2);
                }
                return '';
            })->label(Icon::create('PaperClipOutlined'))->label(admin_trans('machine_edit_log.fields.new_data'));
            $detail->item('origin_data')->display(function ($val) use ($machineEditLog) {
                $source = $machineEditLog['source'];
                if (!empty($val)) {
                    $originData = json_decode($val, true);
                    return Detail::create($originData, function (Detail $detail) use ($originData, $source) {
                        foreach ($originData as $key => $item) {
                            switch ($key) {
                                case 'remark':
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key))->tip();
                                    break;
                                case 'picture_url':
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key))->image();
                                    break;
                                default:
                                    $detail->item($key, admin_trans(($source == MachineEditLog::SOURCE_MACHINE ? 'machine' : 'machine_media') . '.fields.' . $key));
                            }
                        }
                    })->bordered()->column(2);
                }
                return '';
            })->label(Icon::create('FileDoneOutlined'))->label(admin_trans('machine_edit_log.fields.origin_data'));
        })->bordered()->layout('vertical');
    }
}

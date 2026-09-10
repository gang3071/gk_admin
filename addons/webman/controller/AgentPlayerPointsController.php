<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerPointsRecord;
use addons\webman\service\PlayerPointsService;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;
use Exception;

/**
 * 代理 - 玩家积分管理控制器
 *
 * @author Claude Code
 * @date 2026-09-08
 * @group agent
 */
class AgentPlayerPointsController
{
    /**
     * 验证玩家权限（代理：只能访问绑定到该代理的玩家）
     */
    private function checkPlayerPermission(int $playerId): array|false
    {
        $admin = Admin::user();
        $player = Player::find($playerId);

        if (!$player) {
            return false;
        }

        // 代理：只能访问绑定到该代理的玩家
        if ($player->department_id != $admin->department_id || $player->agent_admin_id != $admin->id) {
            return false;
        }

        return [
            'department_id' => $admin->department_id,
            'agent_admin_id' => $admin->id,
        ];
    }

    /**
     * 积分变动记录列表
     * @auth true
     */
    public function index(array $params = []): Grid
    {
        $playerId = $params['player_id'] ?? 0;

        // 验证玩家ID
        if (!$playerId) {
            return Grid::create([], function (Grid $grid) {
                $grid->title(admin_trans('player_points.records_title'));
                $grid->quickSearch(admin_trans('player_points.message.player_not_found'));
            });
        }

        // 验证权限
        $permissionFilter = $this->checkPlayerPermission($playerId);
        if ($permissionFilter === false) {
            return Grid::create([], function (Grid $grid) {
                $grid->title(admin_trans('player_points.records_title'));
                $grid->quickSearch(admin_trans('player_points.message.permission_denied'));
            });
        }

        // 获取玩家信息
        $player = Player::find($playerId);
        if (!$player) {
            return Grid::create([], function (Grid $grid) {
                $grid->title(admin_trans('player_points.records_title'));
                $grid->quickSearch(admin_trans('player_points.message.player_not_found'));
            });
        }

        // 获取参数
        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $type = Request::input('ex_admin_filter.type');

        // 获取积分信息
        $pointsData = PlayerPointsService::getPlayerPoints($playerId);

        // 查询数据（应用权限过滤）
        $result = PlayerPointsService::getRecords($playerId, $page, $size, $type !== '' ? $type : null, $permissionFilter);
        $list = $result['list'] ?? [];
        $total = $result['total'] ?? 0;

        return Grid::create($list, function (Grid $grid) use ($player, $pointsData, $total, $list) {
            $grid->title(admin_trans('player_points.records_title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 设置分页数据
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);

            // 添加顶部统计卡片
            $grid->header(function () use ($player, $pointsData) {
                return [
                    ['label' => admin_trans('player_points.statistics.player_account'), 'value' => $player->username ?? $player->name],
                    ['label' => admin_trans('player_points.statistics.available_points'), 'value' => $pointsData['available_points'], 'type' => 'success'],
                    ['label' => admin_trans('player_points.statistics.frozen_points'), 'value' => $pointsData['frozen_points'], 'type' => 'warning'],
                    ['label' => admin_trans('player_points.statistics.total_points'), 'value' => $pointsData['total_points'], 'type' => 'primary'],
                ];
            });

            // 列定义
            $grid->column('id', admin_trans('player_points.fields.id'))->width(80)->align('center');
            $grid->column('type_desc', admin_trans('player_points.fields.type'))->width(120)->align('center');
            $grid->column('points', admin_trans('player_points.fields.points'))
                ->width(120)
                ->align('center')
                ->display(function ($value) {
                    if ($value > 0) {
                        return "<span style='color: #52c41a; font-weight: bold'>+{$value}</span>";
                    } elseif ($value < 0) {
                        return "<span style='color: #ff4d4f; font-weight: bold'>{$value}</span>";
                    } else {
                        return "<span>{$value}</span>";
                    }
                });
            $grid->column('points_before', admin_trans('player_points.fields.points_before'))->width(120)->align('center');
            $grid->column('points_after', admin_trans('player_points.fields.points_after'))->width(120)->align('center');
            $grid->column('remark', admin_trans('player_points.fields.remark'))->width(250);
            $grid->column('admin_name', admin_trans('player_points.fields.admin_name'))->width(120)->align('center');
            $grid->column('admin_ip', admin_trans('player_points.fields.admin_ip'))->width(150)->align('center');
            $grid->column('created_at', admin_trans('player_points.fields.created_at'))->width(180)->align('center');

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->equal('type', admin_trans('player_points.fields.type'))->select([
                    '' => admin_trans('player_points.filter.all_types'),
                    PlayerPointsRecord::TYPE_BETTING_SUMMARY => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_BETTING_SUMMARY),
                    PlayerPointsRecord::TYPE_EXCHANGE => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_EXCHANGE),
                    PlayerPointsRecord::TYPE_EXPIRE => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_EXPIRE),
                    PlayerPointsRecord::TYPE_ADMIN_ADJUST => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_ADMIN_ADJUST),
                    PlayerPointsRecord::TYPE_ACTIVITY => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_ACTIVITY),
                    PlayerPointsRecord::TYPE_REFUND => admin_trans('player_points.type.' . PlayerPointsRecord::TYPE_REFUND),
                ]);
            });
        });
    }

    /**
     * 增加积分表单
     * @auth true
     */
    public function addPoints(): Form
    {
        $data = Request::input();

        // 验证权限
        if ($this->checkPlayerPermission($data['player_id']) === false) {
            return message_error(admin_trans('player_points.message.permission_denied'));
        }

        $player = Player::find($data['player_id']);
        if (!$player) {
            return message_error(admin_trans('player_points.message.player_not_found'));
        }

        return Form::create(new Player(), function (Form $form) use ($data, $player) {
            $form->labelWidth('120px');
            $form->hidden('player_id')->value($data['player_id']);
            $form->text('username', admin_trans('player_points.fields.player_name'))
                ->value($player->username ?? $player->name ?? '')
                ->disabled();
            $form->number('points', admin_trans('player_points.form.points_amount'))
                ->min(1)
                ->required();
            $form->textarea('remark', admin_trans('player_points.fields.remark'))
                ->required()
                ->placeholder(admin_trans('player_points.form.remark_placeholder'));

            $form->saving(function (Form $form) {
                try {
                    $admin = Admin::user();
                    $adminInfo = [
                        'admin_id' => $admin['id'] ?? 0,
                        'admin_name' => $admin['nickname'] ?? admin_trans('admin.system'),
                        'admin_ip' => Request::getRealIp(),
                    ];

                    PlayerPointsService::addPoints(
                        (int)$form->input('player_id'),
                        abs((int)$form->input('points')),
                        $form->input('remark') ?? admin_trans('player_points.action.add_points'),
                        $adminInfo
                    );
                } catch (Exception $e) {
                    return message_error(admin_trans('player_points.message.add_failed') . '：' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 扣除积分表单
     * @auth true
     */
    public function deductPoints(): Form
    {
        $data = Request::input();

        // 验证权限
        if ($this->checkPlayerPermission($data['player_id']) === false) {
            return message_error(admin_trans('player_points.message.permission_denied'));
        }

        $player = Player::find($data['player_id']);
        if (!$player) {
            return message_error(admin_trans('player_points.message.player_not_found'));
        }

        // 获取当前积分
        $pointsData = PlayerPointsService::getPlayerPoints($data['player_id']);
        $availablePoints = $pointsData['available_points'];

        return Form::create(new Player(), function (Form $form) use ($data, $player, $availablePoints) {
            $form->labelWidth('120px');
            $form->hidden('player_id')->value($data['player_id']);
            $form->text('username', admin_trans('player_points.fields.player_name'))
                ->value($player->username ?? $player->name ?? '')
                ->disabled();
            $form->text('current_points', admin_trans('player_points.form.current_points'))
                ->value($availablePoints)
                ->disabled();
            $form->number('points', admin_trans('player_points.form.points_amount'))
                ->min(1)
                ->max($availablePoints)
                ->required();
            $form->textarea('remark', admin_trans('player_points.fields.remark'))
                ->required()
                ->placeholder(admin_trans('player_points.form.remark_placeholder'));

            $form->saving(function (Form $form) {
                try {
                    $admin = Admin::user();
                    $adminInfo = [
                        'admin_id' => $admin['id'] ?? 0,
                        'admin_name' => $admin['nickname'] ?? admin_trans('admin.system'),
                        'admin_ip' => Request::getRealIp(),
                    ];

                    PlayerPointsService::deductPoints(
                        (int)$form->input('player_id'),
                        abs((int)$form->input('points')),
                        $form->input('remark') ?? admin_trans('player_points.action.deduct_points'),
                        $adminInfo
                    );
                } catch (Exception $e) {
                    return message_error(admin_trans('player_points.message.deduct_failed') . '：' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 冻结积分表单
     * @auth true
     */
    public function freezePoints(): Form
    {
        $data = Request::input();

        // 验证权限
        if ($this->checkPlayerPermission($data['player_id']) === false) {
            return message_error(admin_trans('player_points.message.permission_denied'));
        }

        $player = Player::find($data['player_id']);
        if (!$player) {
            return message_error(admin_trans('player_points.message.player_not_found'));
        }

        // 获取当前积分
        $pointsData = PlayerPointsService::getPlayerPoints($data['player_id']);
        $availablePoints = $pointsData['available_points'];

        return Form::create(new Player(), function (Form $form) use ($data, $player, $availablePoints) {
            $form->labelWidth('120px');
            $form->hidden('player_id')->value($data['player_id']);
            $form->text('username', admin_trans('player_points.fields.player_name'))
                ->value($player->username ?? $player->name ?? '')
                ->disabled();
            $form->text('current_points', admin_trans('player_points.form.current_points'))
                ->value($availablePoints)
                ->disabled();
            $form->number('points', admin_trans('player_points.form.points_amount'))
                ->min(1)
                ->max($availablePoints)
                ->required();
            $form->textarea('remark', admin_trans('player_points.fields.remark'))
                ->required()
                ->placeholder(admin_trans('player_points.form.remark_placeholder'));

            $form->saving(function (Form $form) {
                try {
                    PlayerPointsService::freezePoints(
                        (int)$form->input('player_id'),
                        abs((int)$form->input('points')),
                        $form->input('remark') ?? admin_trans('player_points.action.freeze_points')
                    );
                } catch (Exception $e) {
                    return message_error(admin_trans('player_points.message.freeze_failed') . '：' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 解冻积分表单
     * @auth true
     */
    public function unfreezePoints(): Form
    {
        $data = Request::input();

        // 验证权限
        if ($this->checkPlayerPermission($data['player_id']) === false) {
            return message_error(admin_trans('player_points.message.permission_denied'));
        }

        $player = Player::find($data['player_id']);
        if (!$player) {
            return message_error(admin_trans('player_points.message.player_not_found'));
        }

        // 获取当前积分
        $pointsData = PlayerPointsService::getPlayerPoints($data['player_id']);
        $frozenPoints = $pointsData['frozen_points'];

        return Form::create(new Player(), function (Form $form) use ($data, $player, $frozenPoints) {
            $form->labelWidth('120px');
            $form->hidden('player_id')->value($data['player_id']);
            $form->text('username', admin_trans('player_points.fields.player_name'))
                ->value($player->username ?? $player->name ?? '')
                ->disabled();
            $form->text('frozen_points', admin_trans('player_points.form.current_frozen_points'))
                ->value($frozenPoints)
                ->disabled();
            $form->number('points', admin_trans('player_points.form.points_amount'))
                ->min(1)
                ->max($frozenPoints)
                ->required();
            $form->textarea('remark', admin_trans('player_points.fields.remark'))
                ->required()
                ->placeholder(admin_trans('player_points.form.remark_placeholder'));

            $form->saving(function (Form $form) {
                try {
                    PlayerPointsService::unfreezePoints(
                        (int)$form->input('player_id'),
                        abs((int)$form->input('points')),
                        $form->input('remark') ?? admin_trans('player_points.action.unfreeze_points')
                    );
                } catch (Exception $e) {
                    return message_error(admin_trans('player_points.message.unfreeze_failed') . '：' . $e->getMessage());
                }
            });
        });
    }
}

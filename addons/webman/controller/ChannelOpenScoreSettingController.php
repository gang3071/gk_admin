<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;

/**
 * 店家开分配置
 * @group channel
 */
class ChannelOpenScoreSettingController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.open_score_setting_model');
    }

    /**
     * 店家开分配置列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('open_score_setting.title'));
            $grid->model()->where('admin_user_id', Admin::user()->id)->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('scores', admin_trans('open_score_setting.fields.scores'))
                ->display(function ($val, $data) {
                    $scores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $key = 'score_' . $i;
                        if ($data->$key > 0) {
                            $scores[] = Tag::create($data->$key)->color('cyan');
                        }
                    }
                    return Html::create()->content($scores)->style([
                        'display' => 'flex',
                        'gap' => '5px',
                        'flex-wrap' => 'wrap'
                    ]);
                })->align('center')->width('30%');

            $grid->column('default_scores', admin_trans('open_score_setting.fields.default_scores'))
                ->display(function ($val) {
                    if ($val > 0) {
                        return Tag::create($val)->color('orange');
                    }
                    return Tag::create(admin_trans('open_score_setting.not_set'))->color('default');
                })->align('center');

            $grid->column('created_at', admin_trans('open_score_setting.fields.created_at'))->align('center');
            $grid->column('updated_at', admin_trans('open_score_setting.fields.updated_at'))->align('center');

            $grid->setForm()->drawer($this->form());
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDetail();
            })->align('center');
        });
    }

    /**
     * 店家开分配置表单
     * @group channel
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('open_score_setting.title'));

            $form->number('default_scores', admin_trans('open_score_setting.fields.default_scores'))
                ->default(0)
                ->min(0)
                ->max(1000000)
                ->style(['width' => '100%'])
                ->help(admin_trans('open_score_setting.help.default_scores'));

            $form->divider()->content(admin_trans('open_score_setting.fields.default_scores'));

            // 6个开分选项
            for ($i = 1; $i <= 6; $i++) {
                $form->number('score_' . $i, admin_trans('open_score_setting.fields.score_' . $i))
                    ->default($this->getDefaultScore($i))
                    ->min(0)
                    ->max(1000000)
                    ->style([
                        'width' => '100%',
                    ])
                    ->help(admin_trans('open_score_setting.help.score'));
            }

            $form->layout('vertical');

            // 保存时验证
            $form->saving(function (Form $form) {
                $adminUserId = Admin::user()->id;
                $form->input('admin_user_id', $adminUserId);

                // 检查是否已存在配置（编辑时排除当前记录）
                $exists = $this->model::query()->where('admin_user_id', $adminUserId);

                if ($form->isEdit()) {
                    $exists->where('id', '!=', $form->driver()->get('id'));
                }

                if ($exists->exists()) {
                    return message_error(admin_trans('open_score_setting.player_exists'));
                }

                // 验证至少配置一个开分选项
                $hasScore = false;
                for ($i = 1; $i <= 6; $i++) {
                    $score = $form->input('score_' . $i);
                    if (!empty($score) && $score > 0) {
                        $hasScore = true;
                        break;
                    }
                }

                if (!$hasScore) {
                    return message_error(admin_trans('open_score_setting.at_least_one_score'));
                }
            });
        });
    }

    /**
     * 获取默认开分值
     * @param int $index
     * @return int
     */
    protected function getDefaultScore(int $index): int
    {
        $defaults = [100, 500, 1000, 5000, 10000, 20000];
        return $defaults[$index - 1] ?? 0;
    }
}

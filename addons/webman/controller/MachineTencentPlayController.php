<?php

namespace addons\webman\controller;

use addons\webman\model\MachineTencentPlay;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Grid;

/**
 * 腾讯云播放配置
 */
class MachineTencentPlayController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_tencent_play_model');
    }
    
    /**
     * 腾讯云播放配置
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');
            $grid->title(admin_trans('machine_tencent_play.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player.fields.id'))->align('center');
            $grid->column('title', admin_trans('machine_tencent_play.fields.title'))->align('center');
            $grid->column('push_domain',
                admin_trans('machine_tencent_play.fields.push_domain'))->copy()->align('center');
            $grid->column('push_key', admin_trans('machine_tencent_play.fields.push_key'))->copy()->align('center');
            $grid->column('pull_domain', '播放域名(国际区)')->copy()->align('center');
            $grid->column('pull_key', '播放域名Key(国际区)')->copy()->align('center');
            
            $grid->column('pull_domain_cn', '播放域名(大陆地区)')->copy()->align('center');
            $grid->column('pull_key_cn', '播放域名Key(大陆地区)')->copy()->align('center');
            
            $grid->column('license', admin_trans('machine_tencent_play.fields.license'))->copy()->align('center');
            $grid->column('license_key',
                admin_trans('machine_tencent_play.fields.license_key'))->copy()->align('center');
            $grid->column('api_key', 'SecretId')->copy()->align('center');
            $grid->column('api_appid', 'SecretKey')->copy()->align('center');
            $grid->column('created_at',
                admin_trans('machine_tencent_play.fields.created_at'))->sortable()->align('center');
            $grid->column('updated_at',
                admin_trans('machine_tencent_play.fields.updated_at'))->sortable()->align('center');
            $grid->expandFilter();
            $grid->hideDeleteSelection();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->setForm()->drawer($this->form());
            $grid->actions(function (Actions $actions, MachineTencentPlay $data) {
                $actions->prepend([
                    Button::create(admin_trans('machine_tencent_play.media_reset'))->modal($this->machineMedia($data['id'])),
                ]);
            })->align('center');
        });
    }
    
    /**
     * 重设推流
     * @auth true
     */
    public function machineMedia($id): Form
    {
        return Form::create([], function (Form $form) use ($id) {
            $form->checkbox('videos', '请选择重设的视讯直接')
                ->options([
                    '60.249.10.215:5080' => 'video1',
                    '118.163.197.107:5080' => 'video2',
                    '118.163.197.108:5080' => 'video3',
                    '118.163.177.107:5080' => 'video4',
                    '118.163.177.108:5080' => 'video5',
                ]);
            $form->saving(function (Form $form) use ($id) {
                if (empty($form->input('videos'))) {
                    return message_error('请选择重设的视讯主机');
                }
                /** @var MachineTencentPlay $machineTencentPlay */
                $machineTencentPlay = MachineTencentPlay::query()->where('id', $id)->first();
                if (empty($machineTencentPlay)) {
                    return message_error(admin_trans('machine_tencent_play.not_fount'));
                }
                if (MachineTencentPlay::query()->where('id', '!=', $id)->where('status', 1)->exists()) {
                    return message_error(admin_trans('machine_tencent_play.tencent_play_has_open'));
                }
                try {
                    machineMedia($machineTencentPlay->id, $form->input('videos'));
                } catch (\Exception) {
                    return message_error(admin_trans('player.action_error'));
                }
                
                return message_success(admin_trans('player.action_success'));
            });
            $form->layout('vertical');
        });
    }
    
    /**
     * 添加/修改腾讯云配置
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('machine_tencent_play.title'));
            $form->text('title', admin_trans('machine_tencent_play.fields.title'))->required();
            $form->text('push_domain', admin_trans('machine_tencent_play.fields.push_domain'))->required();
            $form->text('push_key', admin_trans('machine_tencent_play.fields.push_key'))->required();
            $form->text('pull_domain', '播放域名(国际区)')->required();
            $form->text('pull_key', '播放域名Key(国际区)')->required();
            $form->text('pull_domain_cn', '播放域名(大陆地区)')->required();
            $form->text('pull_key_cn', '播放域名Key(大陆地区)')->required();
            $form->text('license', admin_trans('machine_tencent_play.fields.license'))->required();
            $form->text('license_key', admin_trans('machine_tencent_play.fields.license_key'))->required();
            $form->text('api_appid', 'SecretId')->required();
            $form->text('api_key', 'SecretKey')->required();
            $form->layout('vertical');
        });
    }
}

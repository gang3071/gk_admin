<?php

namespace addons\webman\controller;



use addons\webman\form\Driver\Config;
use ExAdmin\ui\component\form\Form;


/**
 * 配置管理
 */
class ConfigController
{

    /**
     * 系统配置
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new Config(), function (Form $form) {
            $form->title(admin_trans('config.title'));
            $form->layout('vertical');
            $form->image('web_logo', admin_trans('config.logo'))->size(80, 80);
            $form->text('web_name', admin_trans('config.name'));
            $form->text('web_miitbeian', admin_trans('config.miitbeian'));
            $form->text('web_copyright', admin_trans('config.copyright'));
        });
    }
}

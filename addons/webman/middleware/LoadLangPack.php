<?php

namespace addons\webman\middleware;


use ExAdmin\ui\support\Container;
use Illuminate\Support\Arr;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;


class LoadLangPack implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $lang = plugin()->webman->config('ui.lang');
        Arr::set($lang,'default',$request->cookie('ex_admin_lang',$lang['default']));
        admin_config(['lang'=>$lang], 'ui');
        Container::getInstance()->translator->setLocale($lang['default']);
        Container::getInstance()->translator->load(plugin()->webman->getPath() . DIRECTORY_SEPARATOR . 'lang', 'ex_admin_ui');
        return $handler($request);
    }
}

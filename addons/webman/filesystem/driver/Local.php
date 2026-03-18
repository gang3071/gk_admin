<?php


namespace addons\webman\filesystem\driver;


use addons\webman\filesystem\AdapterFactoryInterface;

class Local implements AdapterFactoryInterface
{
    public function make(array $options)
    {
        return new \League\Flysystem\Adapter\Local($options['root'], $options['lock'] ?? LOCK_EX);
    }
}
<?php


namespace addons\webman\filesystem;


use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;

interface AdapterFactoryInterface
{
    /**
     * @return AdapterInterface|Filesystem
     */
    public function make(array $options);
}
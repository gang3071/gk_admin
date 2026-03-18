<?php

namespace addons\webman;


use addons\webman\controller\AttachmentController;
use addons\webman\filesystem\Filesystem;
use ExAdmin\ui\component\form\field\Editor;
use ExAdmin\ui\component\form\field\upload\File;
use ExAdmin\ui\component\form\field\upload\Image;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Token;
use Iidestiny\Flysystem\Oss\OssAdapter;
use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use support\Cache;

class Admin
{
    protected static $permissions = [];

    /**
     * 方法是否存在
     * @param $class 类
     * @param $method 方法
     * @return bool
     * @throws \ReflectionException
     */
    public static function methodExists($class, $method)
    {
        $constructor = new \ReflectionClass($class);
        if ($constructor->hasMethod($method)) {
            $method = $constructor->getMethod($method);
            if ($constructor->name == $method->class) {
                return true;
            }
        }
        return false;
    }

    /**
     * 权限节点
     * @return \ExAdmin\ui\auth\Node
     */
    public static function node()
    {
        return \ExAdmin\ui\support\Container::getInstance()->node;
    }

    /**
     * 权限
     * @return array
     */
    public static function permission()
    {
        $permissionKey = 'ADMIN_PERMISSIONS_' . Admin::id();
        $adminPermissions = Cache::get($permissionKey);
        if(empty($adminPermissions)){
            $adminPermissions = Admin::user()->permission->pluck('node_id')->toArray();
            Cache::set($permissionKey, $adminPermissions);
        }
        return $adminPermissions;
    }

    /**
     * 角色
     * @return array
     */
    public static function role()
    {
        return Admin::user()->roles->pluck('id')->toArray();
    }

    /**
     * 用户
     * @return mixed
     */
    public static function user()
    {

        return Token::user();
    }

    /**
     * 用户id
     * @return int|string|null
     */
    public static function id()
    {
        return Token::id();
    }

    public static function check($class, $function, $method)
    {
        $node = Admin::node()->all();
        $node = array_column($node, 'id');
        $actions[] = str_replace('-', '\\', $class) . '\\' . $function;
        $actions[] = str_replace('-', '\\', $class) . '\\' . $function . '-' . strtolower($method);
        foreach ($actions as $action) {
            if (in_array($action, $node)) {
                if (Admin::id() == plugin()->webman->config('admin_auth_id')) {
                    return true;
                }
                if (!in_array($action, Admin::permission())) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function getDispatch()
    {
        $class = null;
        $function = null;
        if (request()->route->getPath() == '/ex-admin/{class}/{function}') {
            $class = request()->route->param('class');
            $function = request()->route->param('function');
        }
        return [$class, $function];
    }

    /**
     * 上传初始化配置
     */
    public static function uploadInit()
    {
        $uploadDiskConfig = function ($disk) {
            $config = config("plugin.rockys.ex-admin-webman.filesystems.disks.$disk");
            //上传初始化
            $uploadConfig['driver'] = 'local';
            $adapter = Filesystem::disk($disk)->getAdapter();
            if ($config['driver'] == QiniuAdapter::class) {
                $uploadConfig['domain'] = $config['domain'];
                $uploadConfig['uploadToken'] = $adapter->getUploadToken(null, 3600 * 3);
                $uploadConfig['driver'] = 'qiniu';
            } elseif ($config['driver'] == OssAdapter::class) {
                $adapter->setCdnUrl($config['domain']);
                $uploadConfig['domain'] = $config['domain'];
                $uploadConfig['accessKey'] = $config['access_key'];
                $uploadConfig['secretKey'] = $config['secret_key'];
                $uploadConfig['region'] = $config['region'];
                $uploadConfig['bucket'] = $config['bucket'];
                $uploadConfig['driver'] = 'oss';
            }
            return $uploadConfig;

        };
        $uploadDisk = function ($disk) use ($uploadDiskConfig) {
            $uploadConfig = $uploadDiskConfig($disk);
            foreach ($uploadConfig as $key => $value) {
                $this->$key($value);
            }
            $this->attr('disk', $disk);
            return $this;
        };
        Image::addMethod('disk', $uploadDisk);
        File::addMethod('disk', $uploadDisk);
        Editor::addMethod('disk', function ($disk) use ($uploadDiskConfig) {
            $uploadConfig = $uploadDiskConfig($disk);
            $uploadConfig['disk'] = $disk;
            $this->upload($uploadConfig + ['progress' => true]);
        });

        $finder = function ($upload, $type = '') {
            $grid = Container::getInstance()
                ->make(\ExAdmin\ui\Route::class)
                ->invokeMethod(AttachmentController::class, 'index', [
                        'size' => $upload->attr('fileSize'),
                        'ext' => $upload->attr('ext'),
                        'type' => $type,
                        'customStyle' => null
                    ]
                );
            $grid->selectionField('url');
            $grid->params(['selectionField' => 'url']);
            $attrs = $upload->getAttrs();
            unset($attrs['progress'], $attrs['onlyShow'], $attrs['type']);
            $grid->attr('tools')[0]->attrs($attrs);
            $upload->attr('finder', $grid);
        };
        Image::beforeEnd(function ($image) use ($finder) {
            $finder($image, 'image');
        });
        File::beforeEnd(function ($file) use ($finder) {
            $finder($file);
        });
    }
}

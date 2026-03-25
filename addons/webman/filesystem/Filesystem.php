<?php


namespace addons\webman\filesystem;


use Illuminate\Filesystem\FilesystemAdapter;

class Filesystem
{


    /**
     * @param string|null $disk
     * @return FilesystemAdapter
     */
    public function driver(string $disk = null): FilesystemAdapter
    {
        $disk = $disk ?: config('plugin.rockys.ex-admin-webman.filesystems.default');
        $config = config('plugin.rockys.ex-admin-webman.filesystems.disks.'.$disk);

        // 动态获取 URL（从浏览器请求）
        if (isset($config['url']) && $config['url'] === 'dynamic') {
            $request = request();
            if ($request) {
                $host = $request->header('x-forwarded-host') ?: $request->header('host');
                $config['url'] = 'https://' . $host . '/storage';
            } else {
                $config['url'] = env('APP_URL', 'https://zhu.supergames9.com') . '/storage';
            }
        }

        $driver = (new $config['driver'])->make($config);
        if($driver instanceof \League\Flysystem\Filesystem){
           $filesystem = $driver;
        }else{
           $filesystem  = new \League\Flysystem\Filesystem($driver,$config);
        }

        $adapter = new FilesystemAdapter($filesystem, $driver, $config);

        return $adapter;
    }
    public static function __callStatic($name, $arguments)
    {
        $self = new static();
        if($name == 'disk'){
            return $self->driver(...$arguments);
        }else{
            return $self->driver()->$name(...$arguments);
        }
    }
}
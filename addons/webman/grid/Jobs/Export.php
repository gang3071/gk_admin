<?php

namespace addons\webman\grid\Jobs;

use ExAdmin\ui\Route;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Webman\RedisQueue\Consumer;

class Export implements Consumer
{

    // 要消费的队列名
    public $queue = 'ex-admin-grid-export';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';



    public function consume($data)
    {
        try {
            $data['ex_admin_queue'] = false;
            Request::init(function (\Symfony\Component\HttpFoundation\Request $q) use($data){
                $q->initialize($data,$data,[],[],[],$data['ex_admin_request']['server']);
                $q->headers = new HeaderBag($data['ex_admin_request']['header']);
                $q->setMethod($data['ex_admin_request']['method']);
            });
            $class = str_replace('-', '\\', $data['ex_admin_class']);
            Container::getInstance()
                ->make(Route::class)
                ->invokeMethod($class, $data['ex_admin_function'], $data)
                ->jsonSerialize();

        } finally {
            // ✅ 显式清理大对象
            $data = null;
            unset($data);

            // ✅ 手动触发垃圾回收
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // ✅ 记录内存使用（用于监控）
            \support\Log::info('Export Job Completed', [
                'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
            ]);
        }
    }
}

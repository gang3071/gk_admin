<?php

namespace addons\webman\grid;

use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;

class AgentStoreFinanceExporter extends Excel
{
    public function write(array $data, \Closure $finish = null)
    {
        $directory = public_path('storage');

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('無法創建文件目錄');
        }

        parent::write($data, function ($export) use ($directory) {
            $savedPath = $export->save($directory);
            $file = '/storage/' . basename($savedPath);

            return Request::getSchemeAndHttpHost() . $file;
        });
    }
}

<?php

namespace addons\webman;

use ExAdmin\ui\auth\Node;
use ExAdmin\ui\support\Arr;

/**
 * 功能許可權節點
 *
 * 優先讀取靜態節點設定（config/admin_node.php、channel_node.php、agent_node.php、store_node.php），
 * 以保留 group 分類；靜態設定為空時才退回框架的控制器掃描。
 */
class AdminNode extends Node
{
    /**
     * 單例
     * @var AdminNode|null
     */
    private static $instance = null;

    /**
     * 取得單例
     * @return AdminNode
     */
    public static function instance(): AdminNode
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 取得節點
     * @param bool $tree 是否樹狀
     * @return array
     */
    public function all(bool $tree = false)
    {
        $flat = [];
        foreach (['admin_node', 'channel_node', 'agent_node', 'store_node'] as $key) {
            $list = admin_config('admin.' . $key, []);
            // 保險：admin_config 尚未載入時，改讀 webman config() 或直接 include 檔案
            if (empty($list) && function_exists('config')) {
                $list = config($key, []);
            }
            if (empty($list) && function_exists('base_path')) {
                $file = base_path('config/' . $key . '.php');
                if (is_file($file)) {
                    $list = include $file;
                }
            }
            if (is_array($list)) {
                $this->flatten($list, $flat);
            }
        }
        // 不同 *_node.php 會有重複節點（例如 AdminController），以 id 去重（保留先出現的）
        $nodes = [];
        $seen = [];
        foreach ($flat as $item) {
            $id = $item['id'] ?? '';
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            if ($id !== '') {
                $seen[$id] = true;
            }
            $nodes[] = $item;
        }
        if (!empty($nodes)) {
            return $tree ? Arr::tree($nodes) : $nodes;
        }
        return parent::all($tree);
    }

    /**
     * 攤平巢狀節點（靜態設定用 children 巢狀表示）
     * @param array $list 節點清單
     * @param array $out 攤平後的節點
     * @return void
     */
    private function flatten(array $list, array &$out): void
    {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $children = $item['children'] ?? [];
            unset($item['children']);
            $out[] = $item;
            if (!empty($children)) {
                $this->flatten($children, $out);
            }
        }
    }
}

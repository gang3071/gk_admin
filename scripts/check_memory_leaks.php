<?php
/**
 * ORM 查询内存泄漏检查脚本
 *
 * 检查控制器中可能导致内存泄漏的ORM查询模式
 *
 * 运行：php scripts/check_memory_leaks.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

class MemoryLeakChecker
{
    private $suspiciousPatterns = [
        // 过度嵌套的with()
        '/->with\(\[.*\..*\..*\]/' => '检测到3层或更多嵌套关联（如 player.channel.admin）',

        // 在循环中使用with()
        '/foreach.*->with\(/' => '检测到在循环中使用with()，可能导致N+1',

        // 没有limit的大量数据查询
        '/->get\(\).*\/\/.*(?!limit)/' => '检测到没有limit的全量查询',

        // 在Grid中使用过多with()
        '/Grid::create.*->with\(\[(.*,.*,.*,.*,)\]/' => '检测到Grid加载超过4个关联',
    ];

    private $controllerPath = __DIR__ . '/../addons/webman/controller/';
    private $issues = [];

    public function check()
    {
        echo "🔍 开始检查 ORM 查询内存泄漏...\n\n";

        $files = $this->getControllerFiles();
        $totalIssues = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $fileIssues = $this->checkFile($file, $content);

            if (!empty($fileIssues)) {
                $totalIssues += count($fileIssues);
                echo "📁 " . basename($file) . "\n";
                foreach ($fileIssues as $issue) {
                    echo "   ⚠️  第 {$issue['line']} 行: {$issue['description']}\n";
                    echo "      代码: " . trim($issue['code']) . "\n\n";
                }
            }
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "✅ 检查完成！共发现 {$totalIssues} 个潜在问题\n";
        echo str_repeat('=', 60) . "\n";

        if ($totalIssues > 0) {
            echo "\n💡 修复建议：\n";
            echo "1. 减少with()的嵌套层级（最多2层）\n";
            echo "2. 只加载真正需要的关联数据\n";
            echo "3. 在Grid列表中，避免加载超过3个关联\n";
            echo "4. 使用select()只查询需要的字段\n";
            echo "5. 对大数据集使用chunk()或cursor()分批处理\n\n";
        }

        return $totalIssues;
    }

    private function getControllerFiles()
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->controllerPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function checkFile($filename, $content)
    {
        $issues = [];
        $lines = explode("\n", $content);

        // 检查1：过度嵌套的with()
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/->with\(\[/', $line)) {
                // 获取完整的with()调用
                $withContent = $this->extractWithContent($lines, $lineNum);

                // 检查是否有3层以上嵌套（如 'player.channel.admin'）
                if (preg_match_all('/["\'][\w\.]+\.[\w\.]+\.[\w\.]+["\']/', $withContent, $matches)) {
                    $issues[] = [
                        'line' => $lineNum + 1,
                        'description' => '过度嵌套的关联加载（3层+），会加载大量数据',
                        'code' => trim($line)
                    ];
                }

                // 检查是否加载了超过5个关联
                $relationCount = substr_count($withContent, "'") / 2;
                if ($relationCount > 5) {
                    $issues[] = [
                        'line' => $lineNum + 1,
                        'description' => "一次加载了 {$relationCount} 个关联，建议减少到3个以内",
                        'code' => trim($line)
                    ];
                }

                // 检查是否有嵌套闭包的with()
                if (preg_match('/function\s*\(\$query\).*->with\(/', $withContent)) {
                    $issues[] = [
                        'line' => $lineNum + 1,
                        'description' => '嵌套闭包的关联加载，可能导致N+1问题',
                        'code' => trim($line)
                    ];
                }
            }

            // 检查2：在循环中查询数据库
            if (preg_match('/foreach.*as/', $line) && isset($lines[$lineNum + 1])) {
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($lines[$lineNum + $i])) {
                        $nextLine = $lines[$lineNum + $i];
                        if (preg_match('/->where\(|->find\(|->get\(/', $nextLine)) {
                            $issues[] = [
                                'line' => $lineNum + $i + 1,
                                'description' => '在循环中执行数据库查询，可能导致N+1问题',
                                'code' => trim($nextLine)
                            ];
                            break;
                        }
                    }
                }
            }

            // 检查3：没有分页的大量数据查询
            if (preg_match('/->get\(\)/', $line) && !preg_match('/limit|take|forPage/', $line)) {
                // 检查前后3行是否有limit
                $hasLimit = false;
                for ($i = -3; $i <= 3; $i++) {
                    if (isset($lines[$lineNum + $i]) && preg_match('/limit|take|forPage/', $lines[$lineNum + $i])) {
                        $hasLimit = true;
                        break;
                    }
                }

                if (!$hasLimit && strpos($line, '// ') === false) { // 排除注释
                    $issues[] = [
                        'line' => $lineNum + 1,
                        'description' => '全量查询（没有limit），可能加载大量数据',
                        'code' => trim($line)
                    ];
                }
            }
        }

        return $issues;
    }

    private function extractWithContent($lines, $startLine)
    {
        $content = '';
        $bracketCount = 0;
        $started = false;

        for ($i = $startLine; $i < count($lines); $i++) {
            $line = $lines[$i];
            $content .= $line . "\n";

            if (strpos($line, '->with([') !== false || strpos($line, '->with( [') !== false) {
                $started = true;
            }

            if ($started) {
                $bracketCount += substr_count($line, '[');
                $bracketCount -= substr_count($line, ']');

                if ($bracketCount === 0) {
                    break;
                }
            }
        }

        return $content;
    }
}

// 运行检查
$checker = new MemoryLeakChecker();
$issues = $checker->check();

exit($issues > 0 ? 1 : 0);

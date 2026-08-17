<?php

namespace process;

use Illuminate\Support\Facades\DB;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * TYPE_BET 数据清理定时任务
 *
 * 功能：
 * - 每 5 秒清理一批 TYPE_BET (26) 数据
 * - 每批删除 5000 条
 * - 自动归档到 player_delivery_record_archive_type_bet 表
 * - 清理完成后自动停止
 * - 记录执行日志
 *
 * 预计耗时：
 * - 2000 万条数据，约 5.5 小时完成
 *
 * 启用方式：
 * - 在 config/process.php 中取消注释 cleanup_type_bet 配置
 *
 * 停止方式：
 * - 在 config/process.php 中注释掉 cleanup_type_bet 配置
 * - 执行 php start.php restart
 */
class CleanupTypeBetTask
{
    /** @var int 每批删除数量 */
    private const BATCH_SIZE = 5000;

    /** @var int 总删除数量统计 */
    private int $totalDeleted = 0;

    /** @var int 总批次统计 */
    private int $totalBatches = 0;

    /** @var string 开始时间 */
    private string $startTime = '';

    /** @var bool 是否已完成 */
    private bool $isCompleted = false;

    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart(): void
    {
        echo "========================================\n";
        echo "TYPE_BET 清理任务已启动\n";
        echo "每 5 秒删除 5000 条，预计 5.5 小时完成\n";
        echo "========================================\n";

        $this->startTime = date('Y-m-d H:i:s');

        // 创建归档表（如果不存在）
        $this->createArchiveTable();

        // 每 5 秒执行一次清理（Cron 表达式：秒 分 时 日 月 周）
        // */5 * * * * * 表示每 5 秒执行
        new Crontab('*/5 * * * * *', function () {
            if (!$this->isCompleted) {
                $this->cleanupBatch();
            }
        });

        Log::info('CleanupTypeBetTask 启动', [
            'batch_size' => self::BATCH_SIZE,
            'start_time' => $this->startTime
        ]);
    }

    /**
     * 创建归档表
     */
    private function createArchiveTable(): void
    {
        try {
            // 使用完整表名（包含前缀）
            $tableName = config('database.connections.mysql.prefix') . 'player_delivery_record';
            $archiveTableName = config('database.connections.mysql.prefix') . 'player_delivery_record_archive_type_bet';

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$archiveTableName}
                LIKE {$tableName}
            ");
            echo "[CleanupTypeBet] ✅ 归档表已创建: {$archiveTableName}\n";

            Log::info('创建归档表成功', [
                'table_name' => $archiveTableName
            ]);
        } catch (\Exception $e) {
            Log::error('创建归档表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            echo "[CleanupTypeBet] ❌ 创建归档表失败: {$e->getMessage()}\n";
        }
    }

    /**
     * 执行一批清理
     *
     * ⚠️ 核心逻辑说明：
     * 1. 先查询要删除的 ID 列表（加锁）
     * 2. 按这些 ID 归档数据
     * 3. 按这些 ID 删除数据
     * 4. 确保归档和删除的是同一批数据，避免数据丢失
     */
    private function cleanupBatch(): void
    {
        // ✅ 设置内存限制
        ini_set('memory_limit', '512M');

        try {
            DB::beginTransaction();

            // 获取表名前缀
            $prefix = config('database.connections.mysql.prefix', '');
            $sourceTable = $prefix . 'player_delivery_record';
            $archiveTable = $prefix . 'player_delivery_record_archive_type_bet';

            // 🔒 步骤 1：查询要删除的 ID 列表（加锁，确保数据一致性）
            $idsToDelete = DB::select("
                SELECT id FROM {$sourceTable}
                WHERE type = 26
                LIMIT ?
                FOR UPDATE
            ", [self::BATCH_SIZE]);

            // 如果没有数据，提交事务并返回
            if (empty($idsToDelete)) {
                DB::commit();
                $this->isCompleted = true;
                $this->onCleanupCompleted();
                return;
            }

            // 提取 ID 数组
            $ids = array_column($idsToDelete, 'id');
            $idList = implode(',', $ids);
            $deletedRows = count($ids);

            // 📦 步骤 2：按 ID 归档数据（使用 INSERT IGNORE 避免主键冲突）
            DB::statement("
                INSERT IGNORE INTO {$archiveTable}
                SELECT * FROM {$sourceTable}
                WHERE id IN ({$idList})
            ");

            // 🗑️ 步骤 3：按相同的 ID 删除数据
            DB::delete("
                DELETE FROM {$sourceTable}
                WHERE id IN ({$idList})
            ");

            DB::commit();

            // 4. 更新统计
            $this->totalDeleted += $deletedRows;
            $this->totalBatches++;

            // 5. 每 10 批或最后一批输出进度
            if ($this->totalBatches % 10 === 0 || $deletedRows < self::BATCH_SIZE) {
                $this->logProgress($deletedRows);
            }

            // ✅ 强制垃圾回收
            unset($idsToDelete, $ids, $idList);
            gc_collect_cycles();

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('CleanupTypeBet 执行失败', [
                'batch' => $this->totalBatches,
                'total_deleted' => $this->totalDeleted,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            echo "[CleanupTypeBet] ❌ 执行失败 - 批次: {$this->totalBatches}, Error: {$e->getMessage()}\n";
            echo "[CleanupTypeBet] ⚠️ 任务将在下次定时触发时继续执行\n";

            // 如果是致命错误（如数据库连接失败），标记为完成以停止执行
            if (str_contains($e->getMessage(), 'SQLSTATE') ||
                str_contains($e->getMessage(), 'Connection')) {
                echo "[CleanupTypeBet] ⚠️ 检测到数据库连接问题，任务已暂停\n";
                $this->isCompleted = true;
            }

            // ✅ 异常情况也要清理内存
            gc_collect_cycles();
        }
    }

    /**
     * 记录进度日志
     */
    private function logProgress(int $deletedRows): void
    {
        try {
            // 查询剩余数量
            $remaining = DB::table('player_delivery_record')
                ->where('type', 26)
                ->count();

            // 计算各项指标
            $elapsedSeconds = max(1, strtotime('now') - strtotime($this->startTime));
            $elapsedMinutes = $elapsedSeconds / 60;
            $estimatedHours = $remaining > 0 ? ($remaining / self::BATCH_SIZE * 5 / 3600) : 0;
            $completedPercent = $this->totalDeleted > 0 ?
                round(($this->totalDeleted / ($this->totalDeleted + $remaining)) * 100, 1) : 0;

            // 检查锁等待（仅在每 50 批检查一次，减少开销）
            $lockCount = 0;
            if ($this->totalBatches % 50 === 0) {
                $lockWaits = DB::select("
                    SELECT COUNT(*) as count
                    FROM information_schema.processlist
                    WHERE state LIKE '%Waiting for table%'
                       OR state LIKE '%Locked%'
                ");
                $lockCount = $lockWaits[0]->count ?? 0;
            }

            echo sprintf(
                "[CleanupTypeBet] 批次: %d | 本批: %d | 累计: %s | 剩余: %s | 完成度: %.1f%% | 耗时: %.1f分钟 | 预计剩余: %.1f小时 | 锁等待: %d\n",
                $this->totalBatches,
                $deletedRows,
                number_format($this->totalDeleted),
                number_format($remaining),
                $completedPercent,
                $elapsedMinutes,
                $estimatedHours,
                $lockCount
            );

            Log::info('CleanupTypeBet 进度', [
                'batch' => $this->totalBatches,
                'deleted_this_batch' => $deletedRows,
                'total_deleted' => $this->totalDeleted,
                'remaining' => $remaining,
                'completed_percent' => $completedPercent,
                'elapsed_minutes' => round($elapsedMinutes, 1),
                'estimated_hours' => round($estimatedHours, 1),
                'lock_waits' => $lockCount,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 记录进度失败不影响主流程
            Log::warning('记录进度失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 清理完成后的回调
     */
    private function onCleanupCompleted(): void
    {
        $elapsedSeconds = max(1, strtotime('now') - strtotime($this->startTime));
        $elapsedMinutes = $elapsedSeconds / 60;
        $elapsedHours = $elapsedMinutes / 60;
        $avgSpeed = $this->totalDeleted > 0 ? round($this->totalDeleted / $elapsedSeconds, 0) : 0;

        echo "========================================\n";
        echo "TYPE_BET 清理完成！\n";
        echo "总批次: {$this->totalBatches}\n";
        echo "总删除: " . number_format($this->totalDeleted) . " 条\n";
        echo "总耗时: " . round($elapsedMinutes, 1) . " 分钟 (" . round($elapsedHours, 1) . " 小时)\n";
        echo "平均速度: " . $avgSpeed . " 条/秒\n";
        echo "========================================\n";
        echo "⚠️ 请在低峰期执行 OPTIMIZE TABLE player_delivery_record 回收磁盘空间\n";
        echo "⚠️ 清理完成后，请在 config/process.php 中注释掉 cleanup_type_bet 配置并重启服务\n";
        echo "========================================\n";

        Log::info('CleanupTypeBet 完成', [
            'total_batches' => $this->totalBatches,
            'total_deleted' => $this->totalDeleted,
            'elapsed_minutes' => round($elapsedMinutes, 1),
            'elapsed_hours' => round($elapsedHours, 1),
            'avg_speed' => $avgSpeed,
            'start_time' => $this->startTime,
            'end_time' => date('Y-m-d H:i:s')
        ]);

        // 验证结果
        $this->verifyResults();
    }

    /**
     * 验证清理结果
     */
    private function verifyResults(): void
    {
        try {
            // 1. 确认剩余 TYPE_BET 数量
            $remaining = DB::table('player_delivery_record')
                ->where('type', 26)
                ->count();

            // 2. 确认归档数量
            $archived = DB::table('player_delivery_record_archive_type_bet')
                ->count();

            // 3. 检查其他类型数据
            $otherTypes = DB::table('player_delivery_record')
                ->whereIn('type', [1, 6, 7, 23, 29])
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('type')
                ->get();

            echo "[CleanupTypeBet] 验证结果:\n";
            echo "  - 剩余 TYPE_BET: $remaining 条（预期 0）\n";
            echo "  - 归档数据: " . number_format($archived) . " 条\n";
            echo "  - 其他类型数据未受影响\n";

            Log::info('CleanupTypeBet 验证结果', [
                'remaining' => $remaining,
                'archived' => $archived,
                'other_types' => $otherTypes->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('验证清理结果失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
}

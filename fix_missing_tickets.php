<?php
/**
 * 补发遗漏的奖券
 * 扫描所有打码超标但未发券的进度记录，补发奖券
 */

require_once __DIR__ . '/vendor/autoload.php';

use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\service\LotteryTicketIssueService;
use support\Db;

// 绕过 Composer 平台检查
define('COMPOSER_BINARY', 'skip');

$activityId = $argv[1] ?? null;

echo "\n========================================\n";
echo "补发遗漏的奖券\n";
if ($activityId) {
    echo "活动ID: {$activityId}\n";
} else {
    echo "扫描所有活动\n";
}
echo "========================================\n\n";

try {
    // 查询所有异常进度记录（打码超标但未发券）
    $query = LotteryTicketBetProgress::query()
        ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
        ->whereRaw('current_bet_amount >= bet_amount_required')
        ->whereRaw('total_tickets_issued < FLOOR(current_bet_amount / bet_amount_required) * ticket_count_per_cycle');

    if ($activityId) {
        $query->where('activity_id', $activityId);
    }

    $abnormalRecords = $query->get();

    if ($abnormalRecords->isEmpty()) {
        echo "✅ 没有发现需要补发的记录\n\n";
        exit(0);
    }

    echo "发现 {$abnormalRecords->count()} 条需要补发的记录\n\n";

    $issueService = new LotteryTicketIssueService();
    $totalIssued = 0;
    $successCount = 0;
    $errors = [];

    foreach ($abnormalRecords as $progress) {
        try {
            // 计算应发券数
            $shouldCycles = floor($progress->current_bet_amount / $progress->bet_amount_required);
            $shouldTickets = $shouldCycles * $progress->ticket_count_per_cycle;
            $missingTickets = $shouldTickets - $progress->total_tickets_issued;

            if ($missingTickets <= 0) {
                continue;
            }

            echo "进度ID {$progress->id}, 玩家ID {$progress->player_id}:\n";
            echo "  打码: {$progress->current_bet_amount} / {$progress->bet_amount_required}\n";
            echo "  应发券: {$shouldTickets}, 已发: {$progress->total_tickets_issued}, 缺少: {$missingTickets}\n";

            // 开始补发
            Db::beginTransaction();
            try {
                $tickets = $issueService->issueTicketsBatch(
                    $progress->activity_id,
                    $progress->player_id,
                    $missingTickets,
                    'betting'  // 来源：打码
                );

                $issuedCount = count($tickets);

                // 更新进度记录
                $progress->cycles_completed = $shouldCycles;
                $progress->total_tickets_issued += $issuedCount;
                $progress->last_issued_at = date('Y-m-d H:i:s');
                $progress->save();

                Db::commit();

                echo "  ✅ 成功补发 {$issuedCount} 张券\n\n";

                $totalIssued += $issuedCount;
                $successCount++;

            } catch (\Exception $e) {
                Db::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $errors[] = "进度ID {$progress->id}: " . $e->getMessage();
            echo "  ❌ 补发失败: {$e->getMessage()}\n\n";
        }
    }

    echo "========================================\n";
    echo "补发完成\n";
    echo "========================================\n\n";

    echo "处理记录数: {$abnormalRecords->count()}\n";
    echo "成功: {$successCount}\n";
    echo "失败: " . count($errors) . "\n";
    echo "总补发券数: {$totalIssued}\n";

    if (!empty($errors)) {
        echo "\n失败详情:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n";

} catch (\Exception $e) {
    echo "❌ 补发失败: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}

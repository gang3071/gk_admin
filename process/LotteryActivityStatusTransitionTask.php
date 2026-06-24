<?php

namespace process;

use addons\webman\model\LotteryTicketActivity;
use addons\webman\service\LotteryTicketBetProgressService;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Worker;

/**
 * 摸奖券活动状态自动流转任务
 * 定时检查活动状态并自动更新
 *
 * 执行频率: 每5秒一次
 * 处理逻辑: 检查活动时间节点，自动更新状态
 *
 * 状态流转规则（新增"待开奖"状态）:
 * 1. 活动开始 → STATUS_ONGOING (start_time 到达，自动)
 * 2. 活动结束 → STATUS_PENDING_DRAW (end_time 到达，自动进入待开奖，停止发券)
 * 3. 开始开奖 → STATUS_DRAWING (管理员手动点击"开奖"按钮)
 * 4. 完全结束 → STATUS_ENDED (管理员手动点击"停止开奖"按钮)
 *
 * 注意：
 * - end_time 到达后自动进入待开奖状态（STATUS_PENDING_DRAW），停止发券
 * - 待开奖状态需要管理员手动触发开奖
 * - 开奖期可由管理员手动结束
 * - 预热期、打码中、已开奖待发放等状态已废弃
 * - draw_time 字段已删除
 */
class LotteryActivityStatusTransitionTask
{
    public function onWorkerStart(Worker $worker)
    {
        // 每5秒执行一次（更及时的状态流转）
        new Crontab('*/5 * * * * *', function () {
            $this->checkAndTransitionStatus();
        });

        Log::info('摸奖券活动状态流转任务已启动（每5秒执行）');
    }

    /**
     * 检查并流转活动状态
     */
    protected function checkAndTransitionStatus()
    {
        try {
            $now = date('Y-m-d H:i:s');
            $transitionCount = 0;

            // 获取所有未结束的活动（包含待开奖状态）
            $activities = LotteryTicketActivity::whereIn('status', [
                LotteryTicketActivity::STATUS_NOT_STARTED,
                LotteryTicketActivity::STATUS_ONGOING,
                LotteryTicketActivity::STATUS_PENDING_DRAW,
            ])->get();

            foreach ($activities as $activity) {
                $oldStatus = $activity->status;
                $newStatus = $this->determineNewStatus($activity, $now);

                if ($newStatus !== $oldStatus) {
                    $this->performTransition($activity, $newStatus);
                    $transitionCount++;

                    Log::info('摸奖券活动状态自动流转', [
                        'activity_id' => $activity->id,
                        'activity_name' => $activity->name,
                        'old_status' => LotteryTicketActivity::getStatusText($oldStatus),
                        'new_status' => LotteryTicketActivity::getStatusText($newStatus),
                        'time' => $now,
                    ]);
                }
            }

            if ($transitionCount > 0) {
                Log::info('摸奖券活动状态流转完成', [
                    'total_transitions' => $transitionCount,
                    'time' => $now,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('摸奖券活动状态流转失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * 判断活动应该处于的状态
     *
     * @param LotteryTicketActivity $activity
     * @param string $now
     * @return int
     */
    protected function determineNewStatus(LotteryTicketActivity $activity, string $now): int
    {
        // 已结束/已关闭/开奖中的活动不再自动流转
        if (in_array($activity->status, [
            LotteryTicketActivity::STATUS_ENDED,
            LotteryTicketActivity::STATUS_CLOSED,
            LotteryTicketActivity::STATUS_DRAWING,
        ])) {
            return $activity->status;
        }

        // 1. 检查是否超过结束时间 → 进入待开奖
        if ($now >= $activity->end_time) {
            return LotteryTicketActivity::STATUS_PENDING_DRAW;
        }

        // 2. 检查是否应该进入进行中
        if ($now >= $activity->start_time && $now < $activity->end_time) {
            return LotteryTicketActivity::STATUS_ONGOING;
        }

        // 3. 默认保持未开始状态
        return LotteryTicketActivity::STATUS_NOT_STARTED;
    }

    /**
     * 执行状态流转
     *
     * @param LotteryTicketActivity $activity
     * @param int $newStatus
     */
    protected function performTransition(LotteryTicketActivity $activity, int $newStatus)
    {
        $oldStatus = $activity->status;

        // 记录状态变更历史
        $activity->recordStatusChange($newStatus, '系统自动流转');
        $activity->save();

        // 根据状态变化执行特定操作
        switch ($newStatus) {
            case LotteryTicketActivity::STATUS_ONGOING:
                // 活动开始（进行中）
                $this->onActivityStart($activity);
                break;

            case LotteryTicketActivity::STATUS_PENDING_DRAW:
                // 进入待开奖，停止发券
                $this->onPendingDraw($activity);
                break;

            case LotteryTicketActivity::STATUS_DRAWING:
                // 开奖期开始（由管理员手动触发）
                $this->onDrawingStart($activity);
                break;

            case LotteryTicketActivity::STATUS_ENDED:
                // 活动结束，清理资源
                $this->onActivityEnd($activity);
                break;
        }
    }

    /**
     * 活动开始（进行中）
     * @param LotteryTicketActivity $activity
     */
    protected function onActivityStart(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);

        // 发送活动开始通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'activity_start');
    }

    /**
     * 进入待开奖状态
     * @param LotteryTicketActivity $activity
     */
    protected function onPendingDraw(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动进入待开奖状态', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'end_time' => $activity->end_time,
        ]);

        // 停止所有玩家的打码进度（不再发券）
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送待开奖通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'pending_draw');

        // ⭐ 推送券数更新（活动结束后券失效，玩家有效券数减少）
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」已結束', $activity->name)
        );
    }

    /**
     * 开奖期开始（管理员手动触发）
     * @param LotteryTicketActivity $activity
     */
    protected function onDrawingStart(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动开奖期开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);

        // 停止所有玩家的打码进度（不再发券）
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送开奖通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'drawing_start');

        // ⭐ 推送券数更新
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」開始開獎', $activity->name)
        );
    }

    /**
     * 活动结束
     * @param LotteryTicketActivity $activity
     */
    protected function onActivityEnd(LotteryTicketActivity $activity)
    {
        Log::info('摸奖券活动结束', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'total_tickets' => $activity->total_tickets,
            'used_tickets' => $activity->used_tickets,
        ]);

        // 确保所有打码进度已结束
        LotteryTicketBetProgressService::endActivityProgress($activity->id);

        // 发送活动结束通知
        \addons\webman\service\LotteryTicketPushService::pushActivityStatusChange($activity, 'ended');

        // ⭐ 推送券数更新
        \addons\webman\service\LotteryTicketPushService::pushActivityPlayersTicketsUpdate(
            $activity->id,
            sprintf('活動「%s」已完全結束', $activity->name)
        );
    }
}

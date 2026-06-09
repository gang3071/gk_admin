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
 * 执行频率: 每分钟一次
 * 处理逻辑: 检查活动时间节点，自动更新状态
 *
 * 状态流转规则:
 * 1. 预热期开始 → STATUS_PREHEATING (preheat_start_time 到达)
 * 2. 活动开始 → STATUS_BETTING (start_time 到达)
 * 3. 开奖时间 → STATUS_DRAWING (draw_time 到达)
 * 4. 活动结束 → STATUS_ENDED (end_time 到达)
 */
class LotteryActivityStatusTransitionTask
{
    public function onWorkerStart(Worker $worker)
    {
        // 每分钟执行一次（错开扫描任务）
        new Crontab('43 * * * * *', function () {
            $this->checkAndTransitionStatus();
        });

        Log::info('摸奖券活动状态流转任务已启动');
    }

    /**
     * 检查并流转活动状态
     */
    protected function checkAndTransitionStatus()
    {
        try {
            $now = date('Y-m-d H:i:s');
            $transitionCount = 0;

            // 获取所有未结束的活动
            $activities = LotteryTicketActivity::whereIn('status', [
                LotteryTicketActivity::STATUS_NOT_STARTED,
                LotteryTicketActivity::STATUS_PREHEATING,
                LotteryTicketActivity::STATUS_BETTING,
                LotteryTicketActivity::STATUS_ONGOING,
                LotteryTicketActivity::STATUS_DRAWING,
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
        // 已结束的活动不再流转
        if ($activity->status === LotteryTicketActivity::STATUS_ENDED) {
            return $activity->status;
        }

        // 1. 检查是否应该结束
        if ($now >= $activity->end_time) {
            return LotteryTicketActivity::STATUS_ENDED;
        }

        // 2. 检查是否应该进入开奖中
        if ($activity->draw_time && $now >= $activity->draw_time) {
            return LotteryTicketActivity::STATUS_DRAWING;
        }

        // 3. 检查是否应该进入打码中
        if ($now >= $activity->start_time) {
            return LotteryTicketActivity::STATUS_BETTING;
        }

        // 4. 检查是否应该进入预热期
        if ($activity->preheat_start_time && $now >= $activity->preheat_start_time) {
            return LotteryTicketActivity::STATUS_PREHEATING;
        }

        // 5. 默认保持未开始状态
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
            case LotteryTicketActivity::STATUS_PREHEATING:
                // 预热期开始，可以发送推送通知
                $this->onPreheatingStart($activity);
                break;

            case LotteryTicketActivity::STATUS_BETTING:
                // 打码期开始，初始化玩家打码进度
                $this->onBettingStart($activity);
                break;

            case LotteryTicketActivity::STATUS_DRAWING:
                // 开奖期开始，停止发券
                $this->onDrawingStart($activity);
                break;

            case LotteryTicketActivity::STATUS_ENDED:
                // 活动结束，清理资源
                $this->onActivityEnd($activity);
                break;
        }
    }

    /**
     * 预热期开始
     * @param LotteryTicketActivity $activity
     */
    protected function onPreheatingStart(LotteryTicketActivity $activity)
    {
        // TODO: 发送预热通知
        // - WebSocket推送给在线玩家
        // - 创建系统公告
        Log::info('摸奖券活动预热期开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);
    }

    /**
     * 打码期开始
     * @param LotteryTicketActivity $activity
     */
    protected function onBettingStart(LotteryTicketActivity $activity)
    {
        // 活动正式开始，发送通知
        Log::info('摸奖券活动打码期开始', [
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
        ]);

        // TODO: 发送活动开始通知
        // - WebSocket推送
        // - 短信通知（可选）
    }

    /**
     * 开奖期开始
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

        // TODO: 发送开奖通知
        // - 开启直播推流（如果有）
        // - WebSocket推送开奖中状态
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

        // TODO: 发送活动结束通知
        // - 统计数据汇总
        // - 生成活动报告
        // - WebSocket推送活动结束
    }
}

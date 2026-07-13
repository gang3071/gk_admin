<?php

namespace process;

use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\VipLevel;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * VIP生日礼金定时任务
 *
 * 功能：
 * - 每天检查一次玩家生日
 * - 根据VIP等级发放生日礼金
 * - 记录到账单表并推送通知
 */
class VipBirthdayBonusTask
{
    /**
     * @var \Monolog\Logger|null
     */
    private $log = null;

    /**
     * @var bool 进程执行中标志，防止重叠执行
     */
    private static bool $running = false;

    /**
     * Worker 启动时的回调
     */
    public function onWorkerStart()
    {
        $this->log = Log::channel('vip');

        $this->log->info('VipBirthdayBonusTask 进程已启动', [
            'schedule' => '0 0 1 * * *',
            'pid' => getmypid(),
        ]);

        echo "VipBirthdayBonusTask: VIP生日礼金任务已启动，每天凌晨1点执行\n";

        // 每天凌晨1点执行（Cron 表达式：秒 分 时 日 月 周）
        new Crontab('0 0 1 * * *', function () {
            $this->doWork();
        });
    }

    /**
     * 执行生日礼金发放
     */
    private function doWork(): void
    {
        if (self::$running) {
            $this->log->warning('VipBirthdayBonusTask 跳过：上一次仍在执行');
            echo "[VipBirthday] 跳过：上一次仍在执行\n";
            return;
        }
        self::$running = true;

        ini_set('memory_limit', '512M');

        $startTime = microtime(true);
        $today = date('m-d'); // 月-日格式，用于匹配生日

        try {
            $this->log->info('VipBirthdayBonusTask 开始执行', [
                'today' => $today,
                'memory' => memory_get_usage(true),
            ]);

            // 获取所有启用的VIP等级（带生日礼金配置）
            $vipLevels = VipLevel::query()
                ->where('status', VipLevel::STATUS_ENABLED)
                ->where('birthday_bonus', '>', 0)
                ->get()
                ->keyBy('id');

            if ($vipLevels->isEmpty()) {
                $this->log->info('VipBirthdayBonusTask 无VIP等级配置生日礼金');
                return;
            }

            // 查询今天生日且有VIP等级的玩家
            $players = Player::query()
                ->join('player_extend', 'player.id', '=', 'player_extend.player_id')
                ->whereNotNull('player.vip_level_id')
                ->where('player.vip_level_id', '>', 0)
                ->where('player.status', Player::STATUS_ENABLE)
                ->whereNull('player.deleted_at') // 排除软删除
                ->whereRaw("DATE_FORMAT(player_extend.birthday, '%m-%d') = ?", [$today])
                ->select('player.*')
                ->get();

            if ($players->isEmpty()) {
                $this->log->info('VipBirthdayBonusTask 今天无玩家生日', ['today' => $today]);
                return;
            }

            // 批量预加载当年已发放生日礼金的玩家ID（避免N+1查询）
            $playerIds = $players->pluck('id')->toArray();
            $sentPlayerIds = [];

            if (!empty($playerIds)) {
                $sentPlayerIds = PlayerDeliveryRecord::query()
                    ->whereIn('player_id', $playerIds)
                    ->where('type', PlayerDeliveryRecord::TYPE_BIRTHDAY_BONUS)
                    ->whereYear('created_at', date('Y'))
                    ->pluck('player_id')
                    ->toArray();
                $sentPlayerIds = array_flip($sentPlayerIds);
            }

            $result = [
                'total' => $players->count(),
                'success' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];

            foreach ($players as $player) {
                try {
                    $vipLevel = $vipLevels->get($player->vip_level_id);
                    if (!$vipLevel) {
                        $result['skipped']++;
                        continue;
                    }

                    $bonusAmount = (float)$vipLevel->birthday_bonus;
                    if ($bonusAmount <= 0) {
                        $result['skipped']++;
                        continue;
                    }

                    // 检查当年是否已经发放过（从预加载数据中判断）
                    if (isset($sentPlayerIds[$player->id])) {
                        $result['skipped']++;
                        continue;
                    }

                    // 发放生日礼金
                    $this->sendBirthdayBonus($player, $vipLevel, $bonusAmount);
                    $result['success']++;

                } catch (\Throwable $e) {
                    $result['errors']++;
                    $this->log->error('VipBirthdayBonusTask 单个玩家处理失败', [
                        'player_id' => $player->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $elapsed = round(microtime(true) - $startTime, 3);

            $this->log->info('VipBirthdayBonusTask 执行完成', [
                'today' => $today,
                'total' => $result['total'],
                'success' => $result['success'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'elapsed_seconds' => $elapsed,
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            if ($result['success'] > 0) {
                echo "[VipBirthday] 执行完成 - total: {$result['total']}, success: {$result['success']}, skipped: {$result['skipped']}, errors: {$result['errors']}, elapsed: {$elapsed}s\n";
            }

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $startTime, 3);

            $this->log->error('VipBirthdayBonusTask 执行异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'elapsed_seconds' => $elapsed,
            ]);

            echo "[VipBirthday] 执行异常 - Error: {$e->getMessage()}\n";
        } finally {
            self::$running = false;
        }
    }

    /**
     * 生成生日礼金通知（只创建notice和推送，不发放）
     *
     * @param Player $player
     * @param VipLevel $vipLevel
     * @param float $bonusAmount
     */
    private function sendBirthdayBonus(Player $player, VipLevel $vipLevel, float $bonusAmount): void
    {
        // 构建推送消息
        $title = '生日禮金';
        $content = sprintf('祝您生日快樂！您的VIP%s生日禮金 %s 可領取', $vipLevel->name, number_format($bonusAmount, 2));
        $pushMessage = [
            'msg_type' => 'vip_birthday_bonus',
            'player_id' => $player->id,
            'title' => $title,
            'content' => $content,
            'amount' => $bonusAmount,
            'vip_level_name' => $vipLevel->name,
        ];

        // 入库保存完整消息内容
        $notice = Notice::query()->create([
            'department_id' => $player->department_id,
            'player_id' => $player->id,
            'type' => Notice::TYPE_VIP_BIRTHDAY_BONUS,
            'title' => $title,
            'content' => json_encode($pushMessage, JSON_UNESCAPED_UNICODE),
            'status' => 0,
            'receiver' => Notice::RECEIVER_PLAYER,
            'is_private' => 1,
        ]);

        // 推送时补充 notice_id
        $pushMessage['notice_id'] = $notice->id;
        sendSocketMessage('player-' . $player->id, $pushMessage);

        $this->log->info('VipBirthdayBonusTask 通知已发送', [
            'player_id' => $player->id,
            'vip_level' => $vipLevel->name,
            'amount' => $bonusAmount,
            'notice_id' => $notice->id,
        ]);
    }
}

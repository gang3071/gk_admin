<?php

namespace app\queue\redis;

use addons\webman\model\PlayerPointsRecord;
use addons\webman\model\StoreSetting;
use addons\webman\service\PlayerPointsService;
use support\Log;
use Webman\RedisQueue\Consumer;

class SteelBallPointsGiftQueue implements Consumer
{
    public $queue = 'steel-ball-points-gift';
    public $connection = 'default';

    public function consume($data): void
    {
        $playerId     = (int)($data['player_id'] ?? 0);
        $departmentId = (int)($data['department_id'] ?? 0);
        $storeAdminId = (int)($data['store_admin_id'] ?? 0);
        $score        = (int)($data['score'] ?? 0);
        $machineId    = (int)($data['machine_id'] ?? 0);

        if ($playerId <= 0 || $storeAdminId <= 0 || $score <= 0) {
            Log::warning('SteelBallPointsGiftQueue: 参数无效', $data);
            return;
        }

        $setting = StoreSetting::query()
            ->where('admin_user_id', $storeAdminId)
            ->where('feature', 'steel_ball_points_gift')
            ->first();

        if (!$setting || $setting->status != 1) {
            return;
        }

        $ratio = max(1, (int)$setting->num);
        $points = (int)floor($score * $ratio);

        if ($points <= 0) {
            return;
        }

        try {
            PlayerPointsService::addPoints(
                $playerId,
                $points,
                '鋼珠下珠贈送積分',
                ['admin_id' => 0, 'admin_name' => 'system', 'admin_ip' => ''],
                PlayerPointsRecord::TYPE_STEEL_BALL_JACKPOT,
                PlayerPointsRecord::SOURCE_STEEL_BALL
            );
            Log::info('SteelBallPointsGiftQueue: 积分赠送成功', [
                'player_id'     => $playerId,
                'machine_id'    => $machineId,
                'department_id' => $departmentId,
                'score'         => $score,
                'points'        => $points,
                'ratio'         => $ratio,
            ]);
        } catch (\Throwable $e) {
            Log::error('SteelBallPointsGiftQueue: 积分赠送失败', [
                'player_id' => $playerId,
                'score'     => $score,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}

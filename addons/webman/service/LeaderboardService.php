<?php

namespace addons\webman\service;

use addons\webman\model\GamePlatform;
use addons\webman\model\Leaderboard;
use addons\webman\model\LeaderboardEntry;
use addons\webman\model\Notice;
use addons\webman\model\Player;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\SystemSetting;
use Carbon\Carbon;
use support\Db;
use support\Log;

/**
 * 排行榜服務
 *
 * 職責：
 * - 結算週榜 / 月榜（即時刷新當期 + 定案已結束的期；只算，不發錢）
 * - 發放獎勵（發到錢包 + 寫錢包異動紀錄 + 寫玩家通知）
 *
 * 資料表：leaderboard、leaderboard_entry
 * 設定來源：system_setting（feature 以 leaderboard_ 開頭，content 為 JSON）
 *
 * 計分口徑：play_game_record 即時加總（只算電子平台、已結算的下注單）
 *
 * 期間口徑：當期進行中（本週一08:00~下週一08:00 / 本月1號08:00~下月1號08:00）
 * - 每 30 分鐘刷新當期名次（settle_at 保持 NULL = 未定案）
 * - 期結束後定案（寫入 settle_at），發獎只處理已定案的期
 */
class LeaderboardService
{
    /** 榜單取前 N 名 */
    const SETTLE_TOP = 8;

    /** 結算週榜（period_type=1） */
    public static function settleWeekly(): array
    {
        return self::settle(1);
    }

    /** 結算月榜（period_type=2） */
    public static function settleMonthly(): array
    {
        return self::settle(2);
    }

    /**
     * 結算指定週期的榜單（即時刷新當期 + 定案已結束的期；只算，不發錢）
     *
     * 每 30 分鐘執行：
     * - 刷新「進行中當期」的名次（settle_at 保持 NULL，未定案）
     * - 將「已結束且未定案」的期定案（寫入 settle_at），供發獎使用
     *
     * @param int $periodType 1=週榜 2=月榜
     * @return array ['refreshed'=>int, 'finalized'=>int, 'skipped'=>int, 'entries'=>int]
     */
    public static function settle(int $periodType): array
    {
        $now = Carbon::now('Asia/Taipei');
        [$start, $end, $periodKey] = self::currentPeriod($periodType, $now);

        $result = ['refreshed' => 0, 'finalized' => 0, 'skipped' => 0, 'entries' => 0];

        foreach (self::getSettings($periodType) as $setting) {
            $code = $setting['code'];

            // 1) 即時刷新「當期進行中」榜單（不改 settle_at）
            $exist = Leaderboard::query()
                ->where('code', $code)
                ->where('period_key', $periodKey)
                ->first();

            if ($exist && $exist->settle_at !== null) {
                // 已定案，不再覆蓋
                $result['skipped']++;
            } else {
                $result['entries'] += self::refreshBoard($exist, $setting, $periodType, $periodKey, $start, $end, $now);
                $result['refreshed']++;
            }

            // 2) 定案所有「已結束但未定案」的期
            $result['finalized'] += self::finalizeEnded($code, $now);
        }

        return $result;
    }

    /**
     * 取得「當期進行中」的期間
     *
     * 週榜：本週一 08:00 ~ 下週一 08:00（未到本週一 08:00 則仍屬上一期）
     * 月榜：本月 1 號 08:00 ~ 下月 1 號 08:00（未到本月 1 號 08:00 則仍屬上一期）
     *
     * @param int $periodType
     * @param Carbon $now
     * @return array [Carbon $start, Carbon $end, string $periodKey]
     */
    private static function currentPeriod(int $periodType, Carbon $now): array
    {
        if ($periodType === 1) {
            $start = $now->copy()->startOfWeek(Carbon::MONDAY)->setTime(8, 0, 0);
            if ($now->lt($start)) {
                $start = $start->subWeek();
            }
            $end = $start->copy()->addWeek();
            $periodKey = $start->format('Y-m-d');
        } else {
            $start = $now->copy()->startOfMonth()->setTime(8, 0, 0);
            if ($now->lt($start)) {
                $start = $start->subMonthNoOverflow();
            }
            $end = $start->copy()->addMonthNoOverflow();
            $periodKey = $start->format('Y-m');
        }

        return [$start, $end, $periodKey];
    }

    /**
     * 刷新（upsert）單一「進行中」榜單，覆蓋其名次，回傳名次筆數
     *
     * @param Leaderboard|null $exist 已存在的當期榜單（null=尚未建立）
     * @param array $setting
     * @param int $periodType
     * @param string $periodKey
     * @param Carbon $start
     * @param Carbon $end
     * @param Carbon $now
     * @return int
     */
    private static function refreshBoard($exist, array $setting, int $periodType, string $periodKey, Carbon $start, Carbon $end, Carbon $now): int
    {
        $ts = $now->format('Y-m-d H:i:s');

        if ($exist) {
            $leaderboardId = (int) $exist->id;
            Leaderboard::query()->where('id', $leaderboardId)->update([
                'name'         => $setting['name'],
                'period_type'  => $periodType,
                'scope_type'   => $setting['scope_type'],
                'start_at'     => $start->format('Y-m-d H:i:s'),
                'end_at'       => $end->format('Y-m-d H:i:s'),
                'threshold'    => sprintf('%.2f', $setting['threshold']),
                'prize_config' => json_encode($setting['prizes'], JSON_UNESCAPED_UNICODE),
                'updated_at'   => $ts,
            ]);
        } else {
            // settle_at 保持 NULL（未定案）
            $leaderboardId = Leaderboard::query()->insertGetId([
                'code'         => $setting['code'],
                'name'         => $setting['name'],
                'period_type'  => $periodType,
                'scope_type'   => $setting['scope_type'],
                'period_key'   => $periodKey,
                'start_at'     => $start->format('Y-m-d H:i:s'),
                'end_at'       => $end->format('Y-m-d H:i:s'),
                'threshold'    => sprintf('%.2f', $setting['threshold']),
                'prize_config' => json_encode($setting['prizes'], JSON_UNESCAPED_UNICODE),
                'entry_count'  => 0,
                'created_at'   => $ts,
                'updated_at'   => $ts,
            ]);
        }

        // 覆蓋名次：先刪同榜舊名次再重塞（同一 leaderboard_id，unique 不衝突）
        LeaderboardEntry::query()->where('leaderboard_id', $leaderboardId)->delete();
        $rows = self::buildEntries($leaderboardId, $setting, $start, $end);
        if (!empty($rows)) {
            LeaderboardEntry::query()->insert($rows);
        }

        Leaderboard::query()->where('id', $leaderboardId)->update([
            'entry_count' => count($rows),
            'updated_at'  => $ts,
        ]);

        return count($rows);
    }

    /**
     * 定案「已結束但未定案」的期：以該期期間重算最終名次並寫入 settle_at
     *
     * @param string $code
     * @param Carbon $now
     * @return int 定案筆數
     */
    private static function finalizeEnded(string $code, Carbon $now): int
    {
        $ts = $now->format('Y-m-d H:i:s');

        $boards = Leaderboard::query()
            ->where('code', $code)
            ->whereNull('settle_at')
            ->where('end_at', '<=', $ts)
            ->get();

        $count = 0;
        foreach ($boards as $lb) {
            $setting = [
                'code'       => (string) $lb->code,
                'name'       => (string) $lb->name,
                'scope_type' => (int) $lb->scope_type,
                'threshold'  => (float) $lb->threshold,
                'prizes'     => json_decode((string) $lb->prize_config, true) ?: [],
            ];

            $start = Carbon::parse($lb->start_at);
            $end = Carbon::parse($lb->end_at);

            // 以定案的期間重算最終名次
            LeaderboardEntry::query()->where('leaderboard_id', $lb->id)->delete();
            $rows = self::buildEntries((int) $lb->id, $setting, $start, $end);
            if (!empty($rows)) {
                LeaderboardEntry::query()->insert($rows);
            }

            Leaderboard::query()->where('id', $lb->id)->update([
                'entry_count' => count($rows),
                'settle_at'   => $ts,
                'updated_at'  => $ts,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 發放獎勵（只發，不算）
     *
     * 撈「已結算、未發、有獎金」的名次，逐一發到錢包。
     *
     * @return array ['granted'=>int, 'failed'=>int]
     */
    public static function grant(): array
    {
        $now = Carbon::now('Asia/Taipei');
        $result = ['granted' => 0, 'failed' => 0];

        $entries = LeaderboardEntry::query()
            ->with(['leaderboard' => function ($q) {
                $q->select('id', 'code', 'name', 'period_key', 'settle_at');
            }])
            ->where('grant_status', LeaderboardEntry::GRANT_STATUS_UNISSUED)
            ->where('prize_amount', '>', 0)
            ->whereHas('leaderboard', function ($q) {
                $q->whereNotNull('settle_at');
            })
            ->orderBy('id')
            ->limit(500)
            ->get();

        foreach ($entries as $entry) {
            try {
                if (self::grantOne($entry, $now)) {
                    $result['granted']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::channel('leaderboard')->error('[排行榜發獎] 單筆發放失敗', [
                    'entry_id'  => $entry->id,
                    'player_id' => $entry->player_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    // ========================================
    // 內部方法
    // ========================================

    /**
     * 讀取榜單設定（system_setting，feature 以 leaderboard_ 開頭）
     */
    private static function getSettings(int $periodType): array
    {
        $rows = SystemSetting::query()
            ->offDataAuth()
            ->where('feature', 'like', 'leaderboard_%')
            ->where('department_id', 0)
            ->where('status', 1)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $content = json_decode($row->content, true);
            if (!is_array($content)) {
                continue;
            }
            if ((int)($content['period_type'] ?? 0) !== $periodType) {
                continue;
            }
            $result[] = [
                'code'       => (string)($content['code'] ?? $row->feature),
                'name'       => (string)($content['name'] ?? ($content['code'] ?? $row->feature)),
                'scope_type' => (int)($content['scope_type'] ?? 1),
                'threshold'  => (float)($content['threshold'] ?? 0),
                'prizes'     => $content['prizes'] ?? [],
            ];
        }
        return $result;
    }

    /**
     * 依範圍組出名次資料
     */
    private static function buildEntries(int $leaderboardId, array $setting, Carbon $start, Carbon $end): array
    {
        $platformIds = self::electronicPlatformIds();
        if (empty($platformIds)) {
            return [];
        }

        $rows = [];

        if ((int)$setting['scope_type'] === Leaderboard::SCOPE_TYPE_STORE) {
            // 店內：每間店各自取前 N
            foreach (self::queryByStore($platformIds, $start, $end) as $r) {
                $rows[] = self::entryRow($leaderboardId, (int)$r->player_id, (int)$r->rn, (float)$r->score, $setting, (int)$r->store_admin_id);
            }
        } else {
            // 全站：全體取前 N
            $rank = 0;
            foreach (self::queryOverall($platformIds, $start, $end) as $r) {
                $rank++;
                $rows[] = self::entryRow($leaderboardId, (int)$r->player_id, $rank, (float)$r->score, $setting, (int)$r->store_admin_id);
            }
        }

        return $rows;
    }

    /**
     * 組出名次單筆資料（含門檻判斷與獎金）
     */
    private static function entryRow(int $leaderboardId, int $playerId, int $rank, float $score, array $setting, int $storeAdminId): array
    {
        $prize = 0;
        if ($score >= (float)$setting['threshold']) {
            foreach ($setting['prizes'] as $p) {
                if ((int)($p['rank'] ?? 0) === $rank) {
                    $prize = (float)($p['amount'] ?? 0);
                    break;
                }
            }
        }

        $now = date('Y-m-d H:i:s');

        return [
            'leaderboard_id' => $leaderboardId,
            'player_id'      => $playerId,
            'store_admin_id' => $storeAdminId,
            'rank'           => $rank,
            'score'          => sprintf('%.2f', $score),
            'prize_amount'   => sprintf('%.2f', $prize),
            'grant_status'   => LeaderboardEntry::GRANT_STATUS_UNISSUED,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }

    /**
     * 全站排行（取前 N）
     */
    private static function queryOverall(array $platformIds, Carbon $start, Carbon $end): array
    {
        $in = implode(',', array_fill(0, count($platformIds), '?'));

        $sql = "SELECT r.player_id, pl.store_admin_id, SUM(r.bet) AS score
                FROM play_game_record r
                JOIN player pl ON pl.id = r.player_id
                WHERE r.created_at >= ? AND r.created_at < ?
                  AND r.settlement_status = 1 AND r.type = 1 AND r.bet > 0
                  AND r.platform_id IN ($in)
                  GROUP BY r.player_id, pl.store_admin_id
                ORDER BY score DESC, r.player_id ASC
                LIMIT " . self::SETTLE_TOP;

        $bindings = array_merge(
            [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
            $platformIds
        );

        return Db::select($sql, $bindings);
    }

    /**
     * 店內排行（每間店各自取前 N）
     */
    private static function queryByStore(array $platformIds, Carbon $start, Carbon $end): array
    {
        $in = implode(',', array_fill(0, count($platformIds), '?'));

        $sql = "SELECT * FROM (
                    SELECT r.player_id, pl.store_admin_id, SUM(r.bet) AS score,
                           ROW_NUMBER() OVER (PARTITION BY pl.store_admin_id ORDER BY SUM(r.bet) DESC, r.player_id ASC) AS rn
                    FROM play_game_record r
                    JOIN player pl ON pl.id = r.player_id
                    WHERE r.created_at >= ? AND r.created_at < ?
                      AND r.settlement_status = 1 AND r.type = 1 AND r.bet > 0
                      AND r.platform_id IN ($in)
                    GROUP BY r.player_id, pl.store_admin_id
                ) t
                WHERE t.rn <= " . self::SETTLE_TOP . "
                ORDER BY t.store_admin_id ASC, t.rn ASC";

        $bindings = array_merge(
            [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
            $platformIds
        );

        return Db::select($sql, $bindings);
    }

    /**
     * 電子平台 ID 清單（來源：platform_filter.included_platforms）
     */
    private static function electronicPlatformIds(): array
    {
        $codes = config('platform_filter.included_platforms', []);
        if (empty($codes)) {
            return [];
        }

        return GamePlatform::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(function ($v) {
                return (int)$v;
            })
            ->all();
    }

    /**
     * 發放單筆獎勵
     */
    private static function grantOne($entry, Carbon $now): bool
    {
        $playerId = (int)$entry->player_id;
        $amount = (float)$entry->prize_amount;
        $ts = $now->format('Y-m-d H:i:s');

        // 若錢包異動紀錄已存在，代表前次流程已完成入帳，直接補回名次狀態。
        $existingLog = PlayerMoneyEditLog::query()
            ->where('tradeno', 'LBR' . $entry->id)
            ->withTrashed()
            ->exists();
        if ($existingLog) {
            LeaderboardEntry::query()->where('id', $entry->id)->update([
                'grant_status' => LeaderboardEntry::GRANT_STATUS_ISSUED,
                'granted_at'   => $ts,
                'updated_at'   => $ts,
            ]);
            return false;
        }

        // 1) 原子搶單：只有把 grant_status 從 0 改成 1 成功才發，避免重複發放
        $claimed = LeaderboardEntry::query()
            ->where('id', $entry->id)
            ->where('grant_status', LeaderboardEntry::GRANT_STATUS_UNISSUED)
            ->update([
                'grant_status' => LeaderboardEntry::GRANT_STATUS_ISSUED,
                'granted_at'   => $ts,
                'updated_at'   => $ts,
            ]);
        if ($claimed === 0) {
            return false; // 已被處理
        }

        $player = Player::query()->where('id', $playerId)->first(['id', 'department_id']);
        $departmentId = (int)($player->department_id ?? 0);

        // 2) 發到錢包（Redis + DB，Redis 為即時權威）
        try {
            $before = WalletService::getBalance($playerId);
            $after = WalletService::atomicIncrement($playerId, $amount);
        } catch (\Throwable $e) {
            // 發錢失敗 → 還原標記，讓下次重試
            LeaderboardEntry::query()->where('id', $entry->id)->update([
                'grant_status' => LeaderboardEntry::GRANT_STATUS_UNISSUED,
                'granted_at'   => null,
                'updated_at'   => $ts,
            ]);
            throw $e;
        }

        // 3) 錢包異動紀錄（tradeno 欄位上限 20 字，用短代碼）
        //    寫入失敗代表「錢已加但無帳務紀錄」，必須把剛加的金額退回後再重試
        try {
            $log = new PlayerMoneyEditLog();
            $log->player_id = $playerId;
            $log->department_id = $departmentId;
            $log->type = PlayerMoneyEditLog::TYPE_INCREASE;
            $log->action = PlayerMoneyEditLog::LEADERBOARD_GIVE;
            $log->tradeno = 'LBR' . $entry->id;
            $log->currency = 'TWD';
            $log->money = $amount;
            $log->origin_money = $before;
            $log->after_money = $after;
            $log->inmoney = $amount;
            $log->subsidy_money = 0;
            $log->bet_multiple = 0;
            $log->bet_num = 0;
            $log->remark = sprintf('%s 第%d名', $entry->leaderboard->name ?? '', $entry->rank);
            $log->created_at = $ts;
            $log->updated_at = $ts;
            $log->save();
        } catch (\Throwable $e) {
            // 先把步驟 2 加的金額扣回，避免帳務與紀錄不一致
            $rolledBack = false;
            try {
                $rollbackResult = WalletService::atomicDecrement($playerId, $amount);
                $rolledBack = (bool)($rollbackResult['ok'] ?? false);
            } catch (\Throwable $rollbackError) {
                // 退款失敗（例：餘額已被花掉）→ 保留已發狀態，避免下輪重複發錢，留待人工核對
                Log::channel('leaderboard')->error('[排行榜發獎] 退款失敗，需人工核對', [
                    'entry_id'  => $entry->id,
                    'player_id' => $playerId,
                    'amount'    => $amount,
                    'error'     => $rollbackError->getMessage(),
                ]);
            }

            // 只有在錢確實退回時，才把名次還原為未發，讓下輪安全重試
            if ($rolledBack) {
                LeaderboardEntry::query()->where('id', $entry->id)->update([
                    'grant_status' => LeaderboardEntry::GRANT_STATUS_UNISSUED,
                    'granted_at'   => null,
                    'updated_at'   => $ts,
                ]);
            }

            Log::channel('leaderboard')->error('[排行榜發獎] 寫入錢包異動紀錄失敗', [
                'entry_id'    => $entry->id,
                'rolled_back' => $rolledBack,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }

        // 4) 玩家通知（登入後跳得名訊息）
        try {
            $notice = new Notice();
            $notice->department_id = $departmentId;
            $notice->player_id = $playerId;
            $notice->source_id = (int)$entry->id;
            $notice->type = Notice::TYPE_LEADERBOARD_REWARD;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $notice->status = 0;
            $notice->title = '排行榜獎勵';
            $notice->content = sprintf(
                '恭喜獲得「%s」第%d名，獎勵 %s 已入帳',
                $entry->leaderboard->name ?? '',
                $entry->rank,
                number_format($amount, 2, '.', '')
            );
            $notice->created_at = $ts;
            $notice->updated_at = $ts;
            $notice->save();
        } catch (\Throwable $e) {
            Log::channel('leaderboard')->error('[排行榜發獎] 寫入通知失敗', [
                'entry_id' => $entry->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return true;
    }
}

<?php

namespace addons\webman\service;

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
 * - 結算 VIP 總榜（依 VIP 等級、同等級比分數；不發獎）
 * - 發放獎勵（發到錢包 + 寫錢包異動紀錄 + 寫玩家通知）
 *
 * 資料表：leaderboard、leaderboard_entry
 * 設定來源：system_setting（feature 以 leaderboard_ 開頭，content 為 JSON）
 *
 * 計分口徑（與後台一致）：
 * - 遊戲記錄：play_game_record，已結算投注（settlement_status=1）加總 bet
 * - 上下分報表：player_game_log，加總 chip_amount（打碼量，斯洛／鋼珠／漁機）
 *
 * 期間口徑：當期進行中（本週一08:00~下週一08:00 / 本月1號08:00~下月1號08:00）
 * - 每 30 分鐘刷新當期名次（settle_at 保持 NULL = 未定案）
 * - 期結束後定案（寫入 settle_at），發獎只處理已定案的期
 */
class LeaderboardService
{
    /** 榜單取前 N 名 */
    const SETTLE_TOP = 10;

    /** VIP 總榜：最低 VIP 等級（vip_level.sort） */
    const VIP_MIN_LEVEL = 5;

    /** VIP 總榜：取前 N 名 */
    const VIP_TOP = 10;

    /** VIP 總榜代碼 */
    const VIP_CODE_WEEKLY = 'vip_weekly';
    const VIP_CODE_MONTHLY = 'vip_monthly';

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

        // 3) VIP 總榜（不依 system_setting，固定結算）
        self::settleVip($periodType, $start, $end, $periodKey, $now);

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
                'period_type' => (int) $lb->period_type,
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
     * 結算 VIP 總榜（週/月）
     *
     * @param int $periodType
     * @param Carbon $start
     * @param Carbon $end
     * @param string $periodKey
     * @param Carbon $now
     * @return void
     */
    private static function settleVip(int $periodType, Carbon $start, Carbon $end, string $periodKey, Carbon $now): void
    {
        $code = $periodType === Leaderboard::PERIOD_TYPE_WEEK ? self::VIP_CODE_WEEKLY : self::VIP_CODE_MONTHLY;

        $exist = Leaderboard::query()
            ->where('code', $code)
            ->where('period_key', $periodKey)
            ->first();

        if (!$exist || $exist->settle_at === null) {
            self::refreshVipBoard($exist, $code, $periodType, $periodKey, $start, $end, $now);
        }

        self::finalizeVipEnded($code, $now);
    }

    /**
     * 刷新 VIP 總榜當期名次
     *
     * @param Leaderboard|null $exist
     * @param string $code
     * @param int $periodType
     * @param string $periodKey
     * @param Carbon $start
     * @param Carbon $end
     * @param Carbon $now
     * @return int
     */
    private static function refreshVipBoard($exist, string $code, int $periodType, string $periodKey, Carbon $start, Carbon $end, Carbon $now): int
    {
        $ts = $now->format('Y-m-d H:i:s');

        if ($exist) {
            $leaderboardId = (int) $exist->id;
            Leaderboard::query()->where('id', $leaderboardId)->update([
                'name'         => 'VIP總榜',
                'period_type'  => $periodType,
                'scope_type'   => Leaderboard::SCOPE_TYPE_ALL,
                'start_at'     => $start->format('Y-m-d H:i:s'),
                'end_at'       => $end->format('Y-m-d H:i:s'),
                'threshold'    => '0.00',
                'prize_config' => '[]',
                'updated_at'   => $ts,
            ]);
        } else {
            $leaderboardId = Leaderboard::query()->insertGetId([
                'code'         => $code,
                'name'         => 'VIP總榜',
                'period_type'  => $periodType,
                'scope_type'   => Leaderboard::SCOPE_TYPE_ALL,
                'period_key'   => $periodKey,
                'start_at'     => $start->format('Y-m-d H:i:s'),
                'end_at'       => $end->format('Y-m-d H:i:s'),
                'threshold'    => '0.00',
                'prize_config' => '[]',
                'entry_count'  => 0,
                'created_at'   => $ts,
                'updated_at'   => $ts,
            ]);
        }

        LeaderboardEntry::query()->where('leaderboard_id', $leaderboardId)->delete();
        $rows = self::buildVipEntries($leaderboardId, $start, $end);
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
     * 組出 VIP 總榜名次（不發獎）
     *
     * @param int $leaderboardId
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    private static function buildVipEntries(int $leaderboardId, Carbon $start, Carbon $end): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = [];
        $rank = 0;

        foreach (self::queryVip($start, $end) as $r) {
            $rank++;
            $rows[] = [
                'leaderboard_id' => $leaderboardId,
                'player_id'      => (int) $r->player_id,
                'store_admin_id' => (int) $r->store_admin_id,
                'rank'           => $rank,
                'score'          => sprintf('%.2f', (float) $r->score),
                'prize_amount'   => '0.00',
                'grant_status'   => LeaderboardEntry::GRANT_STATUS_UNISSUED,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        return $rows;
    }

    /**
     * 定案已結束的 VIP 總榜
     *
     * @param string $code
     * @param Carbon $now
     * @return int
     */
    private static function finalizeVipEnded(string $code, Carbon $now): int
    {
        $ts = $now->format('Y-m-d H:i:s');

        $boards = Leaderboard::query()
            ->where('code', $code)
            ->whereNull('settle_at')
            ->where('end_at', '<=', $ts)
            ->get();

        $count = 0;
        foreach ($boards as $lb) {
            $start = Carbon::parse($lb->start_at);
            $end = Carbon::parse($lb->end_at);

            LeaderboardEntry::query()->where('leaderboard_id', $lb->id)->delete();
            $rows = self::buildVipEntries((int) $lb->id, $start, $end);
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
                'period_type' => $periodType,
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
        $rows = [];

        if ((int)$setting['scope_type'] === Leaderboard::SCOPE_TYPE_STORE) {
            // 店內：每間店各自取前 N
            foreach (self::queryByStore($start, $end) as $r) {
                $rows[] = self::entryRow($leaderboardId, (int)$r->player_id, (int)$r->rn, (float)$r->score, $setting, (int)$r->store_admin_id);
            }
        } else {
            // 全站：全體取前 N
            $rank = 0;
            foreach (self::queryOverall($start, $end) as $r) {
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
     * 排行榜計分子查詢（遊戲記錄已結算投注 + 上下分報表打碼量）
     *
     * @return string
     */
    private static function scoreSubquery(): string
    {
        return "SELECT r.player_id, SUM(r.bet) AS score
                FROM play_game_record r
                WHERE r.created_at >= ? AND r.created_at < ?
                  AND r.settlement_status = 1
                GROUP BY r.player_id
                UNION ALL
                SELECT g.player_id, SUM(g.chip_amount) AS score
                FROM player_game_log g
                WHERE g.created_at >= ? AND g.created_at < ?
                GROUP BY g.player_id";
    }

    /**
     * 全站排行（取前 N）
     */
    private static function queryOverall(Carbon $start, Carbon $end): array
    {
        $sql = "SELECT t.player_id, p.store_admin_id, SUM(t.score) AS score
                FROM player p
                JOIN (" . self::scoreSubquery() . ") t ON t.player_id = p.id
                GROUP BY t.player_id, p.store_admin_id
                ORDER BY score DESC, t.player_id ASC
                LIMIT " . self::SETTLE_TOP;

        return Db::select($sql, self::scoreBindings($start, $end));
    }

    /**
     * 店內排行（每間店各自取前 N）
     */
    private static function queryByStore(Carbon $start, Carbon $end): array
    {
        $sql = "SELECT * FROM (
                    SELECT p.store_admin_id, t.player_id, SUM(t.score) AS score,
                           ROW_NUMBER() OVER (PARTITION BY p.store_admin_id ORDER BY SUM(t.score) DESC, t.player_id ASC) AS rn
                    FROM player p
                    JOIN (" . self::scoreSubquery() . ") t ON t.player_id = p.id
                    GROUP BY p.id, t.player_id
                ) t2
                WHERE t2.rn <= " . self::SETTLE_TOP . "
                ORDER BY t2.store_admin_id ASC, t2.rn ASC";

        return Db::select($sql, self::scoreBindings($start, $end));
    }

    /**
     * 計分子查詢的綁定參數
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    private static function scoreBindings(Carbon $start, Carbon $end): array
    {
        $startAt = $start->format('Y-m-d H:i:s');
        $endAt = $end->format('Y-m-d H:i:s');

        return [$startAt, $endAt, $startAt, $endAt];
    }

    /**
     * VIP 總榜排行（依 VIP 等級、同等級比分數，取前 N）
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return array
     */
    private static function queryVip(Carbon $start, Carbon $end): array
    {
        $sql = "SELECT p.id AS player_id, p.store_admin_id, SUM(t.score) AS score
                FROM player p
                JOIN vip_level vl ON vl.id = p.vip_level_id
                JOIN (" . self::scoreSubquery() . ") t ON t.player_id = p.id
                WHERE vl.sort >= ?
                  AND p.status = 1 AND p.deleted_at IS NULL AND p.store_admin_id > 0
                GROUP BY p.id
                ORDER BY MAX(vl.sort) DESC, score DESC, p.id ASC
                LIMIT " . self::VIP_TOP;

        $bindings = array_merge(self::scoreBindings($start, $end), [self::VIP_MIN_LEVEL]);

        return Db::select($sql, $bindings);
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

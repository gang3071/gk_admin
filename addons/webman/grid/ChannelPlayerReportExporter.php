<?php

namespace addons\webman\grid;

use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * 渠道玩家报表导出器
 */
class ChannelPlayerReportExporter extends Excel
{
    /**
     * 导出数据
     */
    public function write(array $data, \Closure $finish = null)
    {
        try {
            $exAdminFilter = Request::input('ex_admin_filter', []);

            // 构建查询（复用控制器逻辑）
            $baseQuery = Player::query()->withTrashed();
            $playGameRecordBaseQuery = PlayGameRecord::query()
                ->when(!empty($exAdminFilter['uuid']) || !empty($exAdminFilter['real_name']) || !empty($exAdminFilter['phone']) || !empty($exAdminFilter['recommend_promoter']['name']) || (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'], [0, 1])) || !empty($exAdminFilter['search_type']), function (Builder $q) use ($exAdminFilter) {
                    $q->leftjoin('player', 'play_game_record.player_id', '=', 'player.id');
                });

            // 应用筛选条件
            $this->applyFilters($baseQuery, $playGameRecordBaseQuery, $exAdminFilter);

            // 构建查询
            $baseQuery->leftJoin('player_delivery_record', function ($join) use ($exAdminFilter) {
                $join->on('player.id', '=', 'player_delivery_record.player_id')
                    ->when(!empty($exAdminFilter['created_at_start']), function ($q) use ($exAdminFilter) {
                        $q->where('player_delivery_record.created_at', '>=', $exAdminFilter['created_at_start']);
                    })
                    ->when(!empty($exAdminFilter['created_at_end']), function ($q) use ($exAdminFilter) {
                        $q->where('player_delivery_record.created_at', '<=', $exAdminFilter['created_at_end']);
                    })
                    ->when(!empty($exAdminFilter['date_type']), function ($q) use ($exAdminFilter) {
                        $q->where(getDateWhere($exAdminFilter['date_type'], 'player_delivery_record.created_at'));
                    })
                    ->when(!empty($exAdminFilter['type']), function ($q) use ($exAdminFilter) {
                        $q->where('player_delivery_record.type', $exAdminFilter['type']);
                    });
            });

            $baseQuery->selectRaw("
                player.*,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD . " THEN player_delivery_record.amount ELSE 0 END) AS modified_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN player_delivery_record.amount ELSE 0 END) AS recharge_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS activity_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) AS lottery_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_UP . " THEN player_delivery_record.amount ELSE 0 END) AS machine_up_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_DOWN . " THEN player_delivery_record.amount ELSE 0 END) AS machine_down_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN player_delivery_record.amount ELSE 0 END) AS coin_withdraw,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN player_delivery_record.amount ELSE 0 END) AS coin_transfer,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN player_delivery_record.amount ELSE 0 END) AS machine_chip_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_DOWN . " THEN player_delivery_record.amount ELSE 0 END) -
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_UP . " THEN player_delivery_record.amount ELSE 0 END) -
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS winn_los_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS withdrawal_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source in ('self_recharge','gb_recharge') THEN player_delivery_record.amount ELSE 0 END) AS self_recharge_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source = 'artificial_recharge' THEN player_delivery_record.amount ELSE 0 END) AS artificial_recharge_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source in ('channel_withdrawal', 'gb_withdrawal') and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS channel_withdrawal_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source = 'artificial_withdrawal' and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS artificial_withdrawal_total,
                SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN player_delivery_record.amount ELSE 0 END) + SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS total_amount
            ");

            $list = $baseQuery->with([
                'recommend_promoter.player',
                'national_promoter.level_list',
                'national_promoter.level_list.national_level'
            ])->groupBy('player.id')
                ->orderBy('player.id', 'desc')
                ->get()
                ->toArray();

            // 获取电子游戏数据
            $formattedRecords = $playGameRecordBaseQuery
                ->whereIn('player_id', array_column($list, 'id'))
                ->selectRaw('player_id,SUM(bet) AS bet_total,SUM(diff) AS diff_total')
                ->groupBy('play_game_record.player_id')
                ->get()
                ->toArray();

            $playGameRecord = [];
            foreach ($formattedRecords as $record) {
                $playGameRecord[$record['player_id']] = $record;
            }

            // 写入表头
            $headers = [
                admin_trans('player.fields.uuid'),
                admin_trans('player.fields.name'),
                admin_trans('player.fields.phone'),
                admin_trans('player.fields.type'),
                admin_trans('player.fields.real_name'),
                admin_trans('national_promoter.level_list.name'),
                admin_trans('player.fields.recommend_promoter_name'),
                admin_trans('player.recharge_total'),
                admin_trans('player_wallet_transfer.fields.player_amount'),
                admin_trans('player.self_recharge_total'),
                admin_trans('player.artificial_recharge_total'),
                admin_trans('player.withdrawal_total'),
                admin_trans('player.channel_withdrawal_total'),
                admin_trans('player.artificial_withdrawal_total'),
                admin_trans('player.modified_total'),
                admin_trans('player.coin_transfer'),
                admin_trans('player.coin_withdraw'),
                admin_trans('player.machine_up_total'),
                admin_trans('player.machine_down_total'),
                admin_trans('player.machine_chip_total'),
                admin_trans('player.winn_los_total'),
                admin_trans('player.lottery_total'),
                admin_trans('player.activity_total'),
                admin_trans('player.bet_total'),
                admin_trans('player.diff_total'),
                admin_trans('player.total_amount'),
            ];

            $row = 1;
            $col = 'A';
            foreach ($headers as $header) {
                $this->sheet->setCellValue($col . $row, $header);
                $col++;
            }
            $row++;

            // 写入数据
            foreach ($list as $item) {
                // 获取推广员名称
                $promoterName = admin_trans('player.no_promoter');
                if (isset($item['recommend_promoter'])) {
                    $promoterName = $item['recommend_promoter']['player']['uuid'] ?? '';
                }

                // 获取等级
                $levelName = '';
                if (!empty($item['national_promoter']['level_list']['national_level'])) {
                    $levelName = $item['national_promoter']['level_list']['national_level']['name'] . $item['national_promoter']['level_list']['level'];
                }

                // 获取类型
                $typeName = $item['is_promoter'] == 1
                    ? admin_trans('player.promoter')
                    : admin_trans('player.not_promoter');
                if ($item['is_test'] == 1) {
                    $typeName = admin_trans('player.fields.is_test');
                }

                // 电子游戏数据
                $betTotal = 0;
                $diffTotal = 0;
                if (!empty($playGameRecord[$item['id']])) {
                    $betTotal = $playGameRecord[$item['id']]['bet_total'];
                    $diffTotal = $playGameRecord[$item['id']]['diff_total'] * -1;
                }

                $rowData = [
                    $item['uuid'],
                    $item['name'] ?? '',
                    $item['phone'] ?? '',
                    $typeName,
                    $item['real_name'] ?? '',
                    $levelName,
                    $promoterName,
                    number_format($item['recharge_total'] ?? 0, 2, '.', ''),
                    number_format($item['player_money'] ?? 0, 2, '.', ''),
                    number_format($item['self_recharge_total'] ?? 0, 2, '.', ''),
                    number_format($item['artificial_recharge_total'] ?? 0, 2, '.', ''),
                    number_format($item['withdrawal_total'] ?? 0, 2, '.', ''),
                    number_format($item['channel_withdrawal_total'] ?? 0, 2, '.', ''),
                    number_format($item['artificial_withdrawal_total'] ?? 0, 2, '.', ''),
                    number_format($item['modified_total'] ?? 0, 2, '.', ''),
                    number_format($item['coin_transfer'] ?? 0, 2, '.', ''),
                    number_format($item['coin_withdraw'] ?? 0, 2, '.', ''),
                    number_format($item['machine_up_total'] ?? 0, 2, '.', ''),
                    number_format($item['machine_down_total'] ?? 0, 2, '.', ''),
                    number_format($item['machine_chip_total'] ?? 0, 2, '.', ''),
                    number_format($item['winn_los_total'] ?? 0, 2, '.', ''),
                    number_format($item['lottery_total'] ?? 0, 2, '.', ''),
                    number_format($item['activity_total'] ?? 0, 2, '.', ''),
                    number_format($betTotal, 2, '.', ''),
                    number_format($diffTotal, 2, '.', ''),
                    number_format($item['total_amount'] ?? 0, 2, '.', ''),
                ];

                $col = 'A';
                foreach ($rowData as $value) {
                    $this->sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // 完成回调
            if ($finish) {
                $result = call_user_func($finish, $this);
                $this->cache->set(['status' => 1, 'url' => $result]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);
            }

        } catch (\Throwable $e) {
            \support\Log::error('ChannelPlayerReportExporter 导出失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->cache->set([
                'status' => 2,
                'error' => $e->getMessage(),
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);
        }
    }

    /**
     * 保存文件到 public/storage 目录
     */
    public function save(string $path)
    {
        // 强制使用 public/storage 目录
        $storageDir = public_path('storage');

        // 确保目录存在
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // 调用父类保存文件
        $fullFilePath = parent::save($storageDir);

        // 获取文件名
        $fileName = basename($fullFilePath);

        // 构建相对路径
        $publicRelativePath = '/storage/' . $fileName;

        \support\Log::info('ChannelPlayerReportExporter: 文件保存完成', [
            'filesystem_path' => $fullFilePath,
            'relative_path' => $publicRelativePath,
        ]);

        return $publicRelativePath;
    }

    /**
     * 应用筛选条件
     */
    private function applyFilters($baseQuery, $playGameRecordBaseQuery, $exAdminFilter)
    {
        if (empty($exAdminFilter)) {
            return;
        }

        if (!empty($exAdminFilter['uuid'])) {
            $baseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
            $playGameRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
        }

        if (!empty($exAdminFilter['real_name'])) {
            $baseQuery->where('player.real_name', 'like', '%' . $exAdminFilter['real_name'] . '%');
            $playGameRecordBaseQuery->where('player.real_name', 'like', '%' . $exAdminFilter['real_name'] . '%');
        }

        if (!empty($exAdminFilter['phone'])) {
            $baseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
            $playGameRecordBaseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
        }

        if (!empty($exAdminFilter['recommend_promoter']['name'])) {
            $baseQuery->leftjoin('player as rp', 'player.recommend_id', '=', 'rp.id')
                ->where(function ($q) use ($exAdminFilter) {
                    $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                        ->orWhere('rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                });
            $playGameRecordBaseQuery->leftjoin('player as rp', 'play_game_record.parent_player_id', '=', 'rp.id')
                ->where(function ($q) use ($exAdminFilter) {
                    $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                        ->orWhere('rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                });
        }

        if (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'], [0, 1])) {
            $baseQuery->where('player.is_promoter', $exAdminFilter['search_is_promoter']);
            $playGameRecordBaseQuery->whereHas('player', function ($q) use ($exAdminFilter) {
                $q->where('is_promoter', $exAdminFilter['search_is_promoter']);
            });
        }

        if (!empty($exAdminFilter['search_type'])) {
            $baseQuery->where('player.is_test', $exAdminFilter['search_type']);
            $playGameRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
        }

        if (!empty($exAdminFilter['created_at_start'])) {
            $playGameRecordBaseQuery->where('play_game_record.created_at', '>=', $exAdminFilter['created_at_start']);
        }

        if (!empty($exAdminFilter['created_at_end'])) {
            $playGameRecordBaseQuery->where('play_game_record.created_at', '<=', $exAdminFilter['created_at_end']);
        }

        if (isset($exAdminFilter['date_type'])) {
            $playGameRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'], 'play_game_record.created_at'));
        }
    }
}

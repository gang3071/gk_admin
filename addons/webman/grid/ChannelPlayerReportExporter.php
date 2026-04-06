<?php

namespace addons\webman\grid;

use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 渠道玩家报表导出器
 */
class ChannelPlayerReportExporter extends Excel
{
    protected $currentRow = 1;
    protected $processedRows = 0;
    protected $isInitialized = false;
    protected $reportData = [];

    /**
     * 导出数据
     */
    public function write(array $data, \Closure $finish = null)
    {
        try {
            // 第一次调用时初始化
            if (!$this->isInitialized) {
                \support\Log::info('=== ChannelPlayerReportExporter 开始导出 ===');

                $exAdminFilter = Request::input('ex_admin_filter', []);
                \support\Log::info('步骤1: 获取筛选参数', ['filter' => $exAdminFilter]);

                // 构建查询（复用控制器逻辑）
                \support\Log::info('步骤2: 开始构建查询');
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

                \support\Log::info('步骤3: 查询玩家数据');
                $list = $baseQuery->with([
                    'recommend_promoter.player',
                    'national_promoter.level_list',
                    'national_promoter.level_list.national_level'
                ])->groupBy('player.id')
                    ->orderBy('player.id', 'desc')
                    ->get()
                    ->toArray();

                \support\Log::info('步骤4: 玩家数据查询完成', ['count' => count($list)]);

                // 验证数据
                if (empty($list)) {
                    \support\Log::warning('步骤5: 数据为空，无法导出');
                    throw new \Exception('没有可导出的数据，请检查筛选条件');
                }

                // 获取电子游戏数据
                \support\Log::info('步骤5: 查询电子游戏数据');
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

                // 准备导出数据
                $this->prepareExportData($list, $playGameRecord);

                \support\Log::info('步骤6: 数据准备完成，开始写入Excel');
                $this->writeHeaders();
                $this->isInitialized = true;
            }

            // 分批处理数据行（带进度更新）
            $totalCount = count($this->reportData);
            $lastProgress = -1;

            foreach ($this->reportData as $index => $rowData) {
                $this->writeDataRow($rowData, $index);
                $this->processedRows++;

                // 计算进度（每5%或每10条记录更新一次）
                $progress = floor($this->processedRows / $totalCount * 100);
                $shouldUpdateCache = (
                    $progress != $lastProgress && $progress % 5 == 0
                    || $this->processedRows % 10 == 0
                    || $this->processedRows == $totalCount
                );

                if ($shouldUpdateCache) {
                    $this->cache->set([
                        'status' => 0,
                        'progress' => $progress
                    ]);
                    $this->cache->expiresAfter(60);
                    $this->filesystemAdapter->save($this->cache);

                    \support\Log::info('步骤7: 进度更新', [
                        'processed' => $this->processedRows,
                        'total' => $totalCount,
                        'progress' => $progress . '%'
                    ]);

                    $lastProgress = $progress;
                }
            }

            // 所有数据处理完成
            if ($this->processedRows >= count($this->reportData)) {
                \support\Log::info('步骤8: 设置列宽');
                $this->setColumnWidths();

                // 完成回调
                if ($finish) {
                    \support\Log::info('步骤9: 调用完成回调');
                    $result = call_user_func($finish, $this);
                    \support\Log::info('步骤10: 生成文件路径', ['file_url' => $result]);

                    $this->cache->set(['status' => 1, 'url' => $result]);
                    $this->cache->expiresAfter(60);
                    $this->filesystemAdapter->save($this->cache);

                    \support\Log::info('步骤11: 缓存保存成功');
                }

                \support\Log::info('=== ChannelPlayerReportExporter 导出成功 ===');
            }

        } catch (\Throwable $e) {
            \support\Log::error('=== ChannelPlayerReportExporter 导出失败 ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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
     * 准备导出数据
     */
    private function prepareExportData($list, $playGameRecord)
    {
        $this->reportData = [];

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

            $this->reportData[] = [
                $item['uuid'],
                $item['name'] ?? '',
                $item['phone'] ?? '',
                $typeName,
                $item['real_name'] ?? '',
                $levelName,
                $promoterName,
                $item['recharge_total'] ?? 0,
                $item['player_money'] ?? 0,
                $item['self_recharge_total'] ?? 0,
                $item['artificial_recharge_total'] ?? 0,
                $item['withdrawal_total'] ?? 0,
                $item['channel_withdrawal_total'] ?? 0,
                $item['artificial_withdrawal_total'] ?? 0,
                $item['modified_total'] ?? 0,
                $item['coin_transfer'] ?? 0,
                $item['coin_withdraw'] ?? 0,
                $item['machine_up_total'] ?? 0,
                $item['machine_down_total'] ?? 0,
                $item['machine_chip_total'] ?? 0,
                $item['winn_los_total'] ?? 0,
                $item['lottery_total'] ?? 0,
                $item['activity_total'] ?? 0,
                $betTotal,
                $diffTotal,
                $item['total_amount'] ?? 0,
            ];
        }
    }

    /**
     * 写入表头
     */
    private function writeHeaders()
    {

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

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // 表头样式
        $lastCol = chr(65 + count($headers) - 1); // A + 25 = Z
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastCol . $this->currentRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1890FF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9']
                ]
            ]
        ]);

        $this->currentRow++;
    }

    /**
     * 写入单行数据
     */
    private function writeDataRow(array $rowData, int $index)
    {
        $col = 'A';
        foreach ($rowData as $colIndex => $value) {
            // 数字列格式化
            if ($colIndex >= 7) { // 从第8列开始是数字列
                $value = number_format(floatval($value), 2, '.', '');
            }
            $this->sheet->setCellValue($col . $this->currentRow, $value);
            $col++;
        }

        // 交替行背景色
        $bgColor = $index % 2 == 0 ? 'FFFFFF' : 'F5F5F5';
        $lastCol = chr(65 + count($rowData) - 1);
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastCol . $this->currentRow)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $bgColor]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // 数字列右对齐
        $this->sheet->getStyle('H' . $this->currentRow . ':' . $lastCol . $this->currentRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $this->currentRow++;
    }

    /**
     * 设置列宽
     */
    private function setColumnWidths()
    {
        $this->sheet->getColumnDimension('A')->setWidth(15); // UUID
        $this->sheet->getColumnDimension('B')->setWidth(15); // 姓名
        $this->sheet->getColumnDimension('C')->setWidth(15); // 手机号
        $this->sheet->getColumnDimension('D')->setWidth(12); // 类型
        $this->sheet->getColumnDimension('E')->setWidth(15); // 真实姓名
        $this->sheet->getColumnDimension('F')->setWidth(12); // 等级
        $this->sheet->getColumnDimension('G')->setWidth(15); // 推广员
        // H-Z 列（数字列）统一宽度
        foreach (range('H', 'Z') as $col) {
            $this->sheet->getColumnDimension($col)->setWidth(15);
        }
    }

    /**
     * 保存文件
     * @param string $path 保存目录（Arrays driver 传入 sys_get_temp_dir()，我们忽略并使用 public/storage）
     * @return string 返回相对路径，让 Arrays driver 包装成 download URL
     */
    public function save(string $path)
    {
        // 忽略传入的 $path 参数（sys_get_temp_dir()），强制使用 public/storage 目录
        $storageDir = public_path('storage');

        // 确保目录存在
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // 调用父类保存文件到 public/storage 目录
        $fullFilePath = parent::save($storageDir);

        // 获取文件名
        $fileName = basename($fullFilePath);

        // 返回相对路径（以 / 开头），Arrays driver 会把它作为 file 参数
        $relativePath = '/storage/' . $fileName;

        \support\Log::info('ChannelPlayerReportExporter: 文件保存完成', [
            'filesystem_path' => $fullFilePath,
            'relative_path' => $relativePath,
        ]);

        // 返回相对路径，Arrays driver 会包装成：/ex-admin/system/download?file=/storage/xxx.xlsx
        return $relativePath;
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

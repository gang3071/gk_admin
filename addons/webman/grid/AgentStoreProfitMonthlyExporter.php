<?php

namespace addons\webman\grid;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 店家月度营业状况导出器
 */
class AgentStoreProfitMonthlyExporter extends Excel
{
    protected $currentRow = 1;
    protected $isInitialized = false;
    protected $processedStores = 0;

    protected $reportData = [];
    protected $totalDeviceCount = 0;
    protected $totalStoreCount = 0;
    protected $admin = null;

    /**
     * 导出数据
     */
    public function write(array $data, \Closure $finish = null)
    {
        try {
            if (!$this->isInitialized) {
                \support\Log::info('=== AgentStoreProfitMonthlyExporter 开始导出 ===');

                /** @var AdminUser $admin */
                $admin = Admin::user();

                // 计算时间范围（以每天08:00:00作为分界点）
                // 月度起始时间：当月1号 08:00:00
                $monthStart = date('Y-m-01 08:00:00');

                // 当前截止时间：当前实时时间
                $currentTime = date('Y-m-d H:i:s');

                // 昨日截止时间：昨天 08:00:00
                $yesterdayEnd = date('Y-m-d 08:00:00', strtotime('-1 day'));

                // 昨日小计的统计范围：1号08:00 到 昨日08:00
                // 如果昨日08:00早于月度起始（即昨天是上个月），则昨日小计为0
                $yesterdayStart = $monthStart;

                if (strtotime($yesterdayEnd) < strtotime($monthStart)) {
                    // 昨天08:00早于1号08:00，说明跨月了，昨日小计为0
                    $yesterdayEnd = $monthStart; // 起止时间相同，查询结果为0
                }

                \support\Log::info('步骤1: 计算时间范围', [
                    'month_start' => $monthStart,
                    'current_time' => $currentTime,
                    'yesterday_start' => $yesterdayStart,
                    'yesterday_end' => $yesterdayEnd
                ]);

                // 查询月度数据
                $this->queryMonthlyData($admin, $monthStart, $currentTime, $yesterdayEnd, $yesterdayStart);

                if (empty($this->reportData)) {
                    \support\Log::warning('步骤2: 数据为空，无法导出');
                    throw new \Exception('没有可导出的数据');
                }

                $this->admin = $admin;

                // 设置总数（用于进度计算）
                $this->count = count($this->reportData);

                // 写入第一行（时间、标题、统计信息）
                $this->writeFirstRow($monthStart, $currentTime);

                // 写入第二行（表头）
                $this->writeHeaders();

                $this->isInitialized = true;
            }

            // 分批处理数据行
            $totalCount = $this->count;
            $lastProgress = -1;

            foreach ($this->reportData as $index => $item) {
                $this->writeDataRow($item, $index);
                $this->processedStores++;

                $progress = floor($this->processedStores / $totalCount * 100);
                $shouldUpdateCache = (
                    $progress != $lastProgress && $progress % 5 == 0
                    || $this->processedStores % 10 == 0
                    || $this->processedStores == $totalCount
                );

                if ($shouldUpdateCache) {
                    $this->cache->set([
                        'status' => 0,
                        'progress' => $progress
                    ]);
                    $this->cache->expiresAfter(60);
                    $this->filesystemAdapter->save($this->cache);

                    \support\Log::info('步骤3: 进度更新', [
                        'processed' => $this->processedStores,
                        'total' => $totalCount,
                        'progress' => $progress . '%'
                    ]);

                    $lastProgress = $progress;
                }
            }

            // 所有数据处理完成后保存文件
            if ($this->processedStores >= $this->count) {
                \support\Log::info('步骤4: 设置列宽并保存文件');

                // 清除 ExAdmin 自动添加的多余列（I、J、K、L）
                // 这些列是 Grid 中定义的 agent_commission、agent_profit、channel_commission、channel_profit
                // 我们只需要 A-H 共8列
                $this->removeExtraColumns();

                $this->setColumnWidths();

                parent::save(\addons\webman\filesystem\Filesystem::path(''));
                $downloadUrl = \addons\webman\filesystem\Filesystem::url($this->getFilename() . '.' . $this->getExtension());

                \support\Log::info('步骤5: 生成下载 URL', [
                    'download_url' => $downloadUrl
                ]);

                $this->cache->set(['status' => 1, 'url' => $downloadUrl]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);

                \support\Log::info('=== AgentStoreProfitMonthlyExporter 导出成功 ===');
            }

        } catch (\Throwable $e) {
            \support\Log::error('=== AgentStoreProfitMonthlyExporter 导出失败 ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->cache->set([
                'status' => 2,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);
        }
    }

    /**
     * 写入第一行（时间、标题、统计信息）
     */
    private function writeFirstRow($monthStart, $currentTime)
    {
        // 合并单元格 A1:I1
        $this->sheet->mergeCells('A1:I1');

        $timeRange = date('Y-m-d H:i', strtotime($monthStart)) . ' 至 ' . date('Y-m-d H:i', strtotime($currentTime));
        $title = $timeRange . ' | 营业状况 | 总设备数：' . $this->totalDeviceCount . ' | 店家数：' . $this->totalStoreCount;

        $this->sheet->setCellValue('A1', $title);
        $this->sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1890FF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $this->sheet->getRowDimension(1)->setRowHeight(30);

        $this->currentRow = 2;
    }

    /**
     * 写入表头（第二行）
     */
    private function writeHeaders()
    {
        $headers = [
            '店家名称',
            '设备数量',
            '开分',
            '投钞',
            '洗分',
            '彩金',
            '小计',
            '昨日小计',
            '今日变化'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // 表头样式
        $this->sheet->getStyle('A' . $this->currentRow . ':I' . $this->currentRow)->applyFromArray([
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
    private function writeDataRow(array $item, int $index)
    {
        $this->sheet->setCellValue('A' . $this->currentRow, $item['store_name']);
        $this->sheet->setCellValue('B' . $this->currentRow, $item['device_count']);
        $this->sheet->setCellValue('C' . $this->currentRow, number_format(floatval($item['recharge_amount']), 2));
        $this->sheet->setCellValue('D' . $this->currentRow, number_format(floatval($item['machine_put_point']), 2));
        $this->sheet->setCellValue('E' . $this->currentRow, number_format(floatval($item['withdraw_amount']), 2));
        $this->sheet->setCellValue('F' . $this->currentRow, number_format(floatval($item['lottery_amount']), 2));
        $this->sheet->setCellValue('G' . $this->currentRow, number_format(floatval($item['subtotal']), 2));
        $this->sheet->setCellValue('H' . $this->currentRow, number_format(floatval($item['yesterday_subtotal']), 2));
        $this->sheet->setCellValue('I' . $this->currentRow, number_format(floatval($item['today_change']), 2));

        // 交替行背景色
        $bgColor = $index % 2 == 0 ? 'FFFFFF' : 'F5F5F5';
        $this->sheet->getStyle('A' . $this->currentRow . ':I' . $this->currentRow)->applyFromArray([
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
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // 小计列颜色
        $subtotal = floatval($item['subtotal']);
        $this->sheet->getStyle('G' . $this->currentRow)->getFont()->getColor()->setRGB($subtotal >= 0 ? '52C41A' : 'FF4D4F');
        $this->sheet->getStyle('G' . $this->currentRow)->getFont()->setBold(true);

        // 昨日小计列颜色
        $yesterdaySubtotal = floatval($item['yesterday_subtotal']);
        $this->sheet->getStyle('H' . $this->currentRow)->getFont()->getColor()->setRGB($yesterdaySubtotal >= 0 ? '52C41A' : 'FF4D4F');

        // 今日变化列颜色
        $todayChange = floatval($item['today_change']);
        $this->sheet->getStyle('I' . $this->currentRow)->getFont()->getColor()->setRGB($todayChange >= 0 ? '52C41A' : 'FF4D4F');
        $this->sheet->getStyle('I' . $this->currentRow)->getFont()->setBold(true);

        $this->currentRow++;
    }

    /**
     * 设置列宽
     */
    private function setColumnWidths()
    {
        $this->sheet->getColumnDimension('A')->setWidth(20); // 店家名称
        $this->sheet->getColumnDimension('B')->setWidth(12); // 设备数量
        $this->sheet->getColumnDimension('C')->setWidth(15); // 开分
        $this->sheet->getColumnDimension('D')->setWidth(15); // 投钞
        $this->sheet->getColumnDimension('E')->setWidth(15); // 洗分
        $this->sheet->getColumnDimension('F')->setWidth(15); // 彩金
        $this->sheet->getColumnDimension('G')->setWidth(15); // 小计
        $this->sheet->getColumnDimension('H')->setWidth(15); // 昨日小计
        $this->sheet->getColumnDimension('I')->setWidth(15); // 今日变化
    }

    /**
     * 删除 ExAdmin 自动添加的多余列
     * ExAdmin 框架会自动将 Grid 中定义的所有列添加到导出中
     * 我们需要删除 J、K、L、M 列（代理抽成、代理分润、渠道抽成、渠道分润）
     */
    private function removeExtraColumns()
    {
        try {
            // 获取最大列数
            $highestColumn = $this->sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            // 如果列数超过9列（I列），说明有多余的列
            if ($highestColumnIndex > 9) {
                $columnsToRemove = $highestColumnIndex - 9;

                // 从 J 列开始删除所有多余列
                // removeColumn(列名或索引, 删除的列数)
                $this->sheet->removeColumn('J', $columnsToRemove);

                \support\Log::info('removeExtraColumns: 已删除多余列', [
                    'original_columns' => $highestColumnIndex,
                    'removed_columns' => $columnsToRemove,
                    'final_columns' => 9,
                ]);
            }
        } catch (\Exception $e) {
            \support\Log::error('removeExtraColumns: 删除多余列失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 查询月度数据
     */
    private function queryMonthlyData($admin, $monthStart, $currentTime, $yesterdayEnd, $yesterdayStart)
    {
        \support\Log::info('queryMonthlyData: 开始查询');

        // 获取代理下的所有店家
        $stores = $admin->childStores()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->get();

        $this->totalStoreCount = $stores->count();
        $this->totalDeviceCount = 0;

        $reportData = [];

        foreach ($stores as $store) {
            // 获取该店家下的所有玩家（设备）
            $playerIds = Player::query()
                ->where('store_admin_id', $store->id)
                ->where('is_promoter', 0)
                ->pluck('id')
                ->toArray();

            // 统计设备数量
            $deviceCount = count($playerIds);

            // 统计总设备数
            $this->totalDeviceCount += $deviceCount;

            if (empty($playerIds)) {
                // 没有玩家也要显示店家信息
                $reportData[] = [
                    'store_name' => $store->nickname ?: $store->username,
                    'device_count' => $deviceCount,
                    'recharge_amount' => 0,
                    'machine_put_point' => 0,
                    'withdraw_amount' => 0,
                    'lottery_amount' => 0,
                    'subtotal' => '0',
                    'yesterday_subtotal' => '0',
                    'today_change' => '0',
                ];
                continue;
            }

            // 查询月度数据（1号08:00至当前时间）
            $monthlyData = $this->queryPeriodData($playerIds, $monthStart, $currentTime);

            // 查询昨日小计（1号08:00到昨日08:00）
            $yesterdayData = $this->queryPeriodData($playerIds, $yesterdayStart, $yesterdayEnd);

            // 计算今日变化：昨日08:00到当前时间的变化
            // 今日变化 = 月度小计 - 昨日小计 = (1号08:00→现在) - (1号08:00→昨日08:00) = 昨日08:00→现在
            $todayChange = bcsub($monthlyData['subtotal'], $yesterdayData['subtotal'], 2);

            $reportData[] = [
                'store_name' => $store->nickname ?: $store->username,
                'device_count' => $deviceCount,
                'recharge_amount' => $monthlyData['recharge_amount'],
                'machine_put_point' => $monthlyData['machine_put_point'],
                'withdraw_amount' => $monthlyData['withdraw_amount'],
                'lottery_amount' => $monthlyData['lottery_amount'],
                'subtotal' => $monthlyData['subtotal'],
                'yesterday_subtotal' => $yesterdayData['subtotal'],
                'today_change' => $todayChange,
            ];
        }

        $this->reportData = $reportData;
    }

    /**
     * 查询指定时间段的数据
     */
    private function queryPeriodData(array $playerIds, $startTime, $endTime)
    {
        // 查询开分、洗分、投钞数据
        $deliveryQuery = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime);

        $deliveryData = $deliveryQuery->selectRaw("
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
        ")->first();

        // 查询拉彩数据
        $lotteryQuery = PlayerLotteryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime);

        $lotteryData = $lotteryQuery->selectRaw("
            SUM(`amount`) as lottery_amount
        ")->first();

        // 提取数据
        $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
        $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
        $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
        $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

        // 计算小计：(开分+投钞) - 洗分
        // 注意：与列表计算方式保持一致，不减彩金
        $totalIn = bcadd(strval($rechargeAmount), strval($machinePutPoint), 2);
        $subtotal = bcsub($totalIn, strval($withdrawAmount), 2);

        return [
            'recharge_amount' => $rechargeAmount,
            'withdraw_amount' => $withdrawAmount,
            'machine_put_point' => $machinePutPoint,
            'lottery_amount' => $lotteryAmount,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * 保存文件（占位方法）
     */
    public function save(string $path)
    {
        return '/storage/placeholder.xlsx';
    }
}

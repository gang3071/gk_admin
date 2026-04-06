<?php

namespace addons\webman\grid;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 代理店家分润月报导出器
 */
class AgentStoreProfitReportExporter extends Excel
{
    protected $currentRow = 1;
    protected $titleRow = 1;
    protected $processedStores = 0;  // 已处理的店家数量
    protected $isInitialized = false; // 是否已初始化

    // 保存查询到的数据
    protected $reportData = [];
    protected $totalStats = [];
    protected $admin = null;
    protected $createdAtStart = null;
    protected $createdAtEnd = null;

    /**
     * 导出数据
     * @param array $data
     * @param \Closure|null $finish
     * @return void
     */
    public function write(array $data, \Closure $finish = null)
    {
        try {
            // ✅ 第一次调用时初始化（写入标题、统计汇总、表头）
            if (!$this->isInitialized) {
                \support\Log::info('=== AgentStoreProfitReportExporter 开始导出 ===');

                // ExAdmin Grid 导出时，$data 参数通常只包含简化数据
                // 需要从 Request 获取筛选参数，重新查询完整数据
                $exAdminFilter = Request::input('ex_admin_filter', []);
                $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
                $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;
                $selectedStoreId = $exAdminFilter['store_id'] ?? null;

                \support\Log::info('步骤1: 获取筛选参数', [
                    'created_at_start' => $createdAtStart,
                    'created_at_end' => $createdAtEnd,
                    'store_id' => $selectedStoreId
                ]);

                /** @var AdminUser $admin */
                $admin = Admin::user();
                \support\Log::info('步骤2: 获取当前代理', [
                    'admin_id' => $admin->id,
                    'admin_username' => $admin->username
                ]);

                // 重新查询数据（复用控制器逻辑）
                \support\Log::info('步骤3: 开始查询数据');
                $this->queryReportData($admin, $selectedStoreId, $createdAtStart, $createdAtEnd, $reportData, $totalStats);

                \support\Log::info('步骤4: 数据查询完成', [
                    'data_count' => count($reportData),
                    'total_recharge' => $totalStats['total_recharge'] ?? 0,
                    'total_subtotal' => $totalStats['total_subtotal'] ?? 0
                ]);

                // 验证数据
                if (empty($reportData)) {
                    \support\Log::warning('步骤5: 数据为空，无法导出');
                    throw new \Exception('没有可导出的数据，请检查筛选条件或确认是否有店家数据');
                }

                // 保存数据到实例属性
                $this->reportData = $reportData;
                $this->totalStats = $totalStats;
                $this->admin = $admin;
                $this->createdAtStart = $createdAtStart;
                $this->createdAtEnd = $createdAtEnd;

                // 写入标题、统计汇总、表头
                \support\Log::info('步骤6: 写入标题和表头');
                $this->writeTitle($admin, $createdAtStart, $createdAtEnd);
                $this->writeSummary($totalStats);
                $this->writeHeaders();

                $this->isInitialized = true;
            }

            // ✅ 分批处理数据行（每次处理一批，更新进度）
            $totalCount = count($this->reportData);
            $lastProgress = -1; // 上次更新的进度值

            foreach ($this->reportData as $index => $item) {
                // 写入数据行
                $this->writeDataRow($item, $index);

                // 更新已处理数量
                $this->processedStores++;

                // ✅ 计算进度（每5%或每10条记录更新一次缓存，避免过于频繁）
                $progress = floor($this->processedStores / $totalCount * 100);
                $shouldUpdateCache = (
                    $progress != $lastProgress && $progress % 5 == 0  // 每5%更新一次
                    || $this->processedStores % 10 == 0                // 或每10条记录
                    || $this->processedStores == $totalCount           // 或最后一条
                );

                if ($shouldUpdateCache) {
                    $this->cache->set([
                        'status' => 0,         // ✅ 0 = 进行中（显示进度条）
                        'progress' => $progress // ✅ 进度百分比
                    ]);
                    $this->cache->expiresAfter(60);
                    $this->filesystemAdapter->save($this->cache);

                    \support\Log::info('步骤7: 进度更新', [
                        'processed' => $this->processedStores,
                        'total' => $totalCount,
                        'progress' => $progress . '%'
                    ]);

                    $lastProgress = $progress;
                }
            }

            // ✅ 所有数据处理完成后，写入合计行并保存文件
            if ($this->processedStores >= $this->count) {
                \support\Log::info('步骤8: 写入合计行');
                $this->writeTotalRow($this->totalStats);
                $this->setColumnWidths();

                // ✅ 参考 Eloquent driver 的做法，使用 Filesystem::path() 和 Filesystem::url()
                \support\Log::info('步骤9: 保存文件到 public/storage');
                parent::save(\addons\webman\filesystem\Filesystem::path(''));

                // ✅ 使用 Filesystem::url() 构建 HTTPS URL（与 ShiftReportExporter 一致）
                $downloadUrl = \addons\webman\filesystem\Filesystem::url($this->getFilename() . '.' . $this->getExtension());

                \support\Log::info('步骤10: 生成下载 URL', [
                    'download_url' => $downloadUrl
                ]);

                // ✅ 设置缓存（不调用 finish callback）
                $this->cache->set(['status' => 1, 'url' => $downloadUrl]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);

                \support\Log::info('步骤11: 缓存保存成功');
                \support\Log::info('=== AgentStoreProfitReportExporter 导出成功 ===');
            }

            // ✅ 注意：不调用 finish callback
            // Arrays driver 的 callback 使用 Request::getSchemeAndHttpHost() 返回 http URL
            // 我们模仿 Eloquent driver，使用 Filesystem::url() 返回 https URL

        } catch (\Throwable $e) {
            \support\Log::error('=== AgentStoreProfitReportExporter 导出失败 ===', [
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
     * 写入单行数据（用于分批处理）
     */
    private function writeDataRow(array $item, int $index)
    {
        $this->sheet->setCellValue('A' . $this->currentRow, $item['id']);
        $this->sheet->setCellValue('B' . $this->currentRow, $item['store_name']);
        $this->sheet->setCellValue('C' . $this->currentRow, $item['store_username']);
        $this->sheet->setCellValue('D' . $this->currentRow, number_format(floatval($item['recharge_amount']), 2));
        $this->sheet->setCellValue('E' . $this->currentRow, number_format(floatval($item['withdraw_amount']), 2));
        $this->sheet->setCellValue('F' . $this->currentRow, number_format(floatval($item['machine_put_point']), 2));
        $this->sheet->setCellValue('G' . $this->currentRow, number_format(floatval($item['lottery_amount']), 2));
        $this->sheet->setCellValue('H' . $this->currentRow, number_format(floatval($item['subtotal']), 2));
        $this->sheet->setCellValue('I' . $this->currentRow, $item['agent_commission'] . '%');
        $this->sheet->setCellValue('J' . $this->currentRow, number_format(floatval($item['agent_profit']), 2));
        $this->sheet->setCellValue('K' . $this->currentRow, $item['channel_commission'] . '%');
        $this->sheet->setCellValue('L' . $this->currentRow, number_format(floatval($item['channel_profit']), 2));

        // 交替行背景色
        $bgColor = $index % 2 == 0 ? 'FFFFFF' : 'F5F5F5';
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)->applyFromArray([
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

        // 小计列：根据正负值设置颜色
        $subtotal = floatval($item['subtotal']);
        $this->sheet->getStyle('H' . $this->currentRow)->getFont()->getColor()->setRGB($subtotal >= 0 ? '52C41A' : 'FF4D4F');
        $this->sheet->getStyle('H' . $this->currentRow)->getFont()->setBold(true);

        // 代理分润列：设置颜色
        $agentProfit = floatval($item['agent_profit']);
        $this->sheet->getStyle('J' . $this->currentRow)->getFont()->getColor()->setRGB($agentProfit >= 0 ? '1890FF' : 'FA8C16');
        $this->sheet->getStyle('J' . $this->currentRow)->getFont()->setBold(true);

        // 渠道分润列：设置颜色
        $channelProfit = floatval($item['channel_profit']);
        $this->sheet->getStyle('L' . $this->currentRow)->getFont()->getColor()->setRGB($channelProfit >= 0 ? '52C41A' : 'F5222D');
        $this->sheet->getStyle('L' . $this->currentRow)->getFont()->setBold(true);

        $this->currentRow++;
    }

    /**
     * 写入标题
     */
    private function writeTitle($admin, $startTime, $endTime)
    {
        // 主标题
        $this->sheet->setCellValue('A1', admin_trans('agent_store_profit.export.title'));
        $this->sheet->mergeCells('A1:M1');
        $this->sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
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
        $this->sheet->getRowDimension(1)->setRowHeight(35);

        // 报表信息
        $this->currentRow = 2;
        $this->sheet->setCellValue('A2', admin_trans('agent_store_profit.export.agent_info') . $admin->nickname . ' (' . $admin->username . ')');
        $this->sheet->mergeCells('A2:M2');

        $this->currentRow = 3;
        $timeRange = admin_trans('agent_store_profit.export.time_range');
        if ($startTime && $endTime) {
            $timeRange .= $startTime . ' ~ ' . $endTime;
        } else if ($startTime) {
            $timeRange .= admin_trans('agent_store_profit.export.start_from') . $startTime;
        } else if ($endTime) {
            $timeRange .= admin_trans('agent_store_profit.export.end_at') . $endTime;
        } else {
            $timeRange .= admin_trans('agent_store_profit.export.all_time');
        }
        $this->sheet->setCellValue('A3', $timeRange);
        $this->sheet->mergeCells('A3:M3');

        $this->currentRow = 4;
        $this->sheet->setCellValue('A4', admin_trans('agent_store_profit.export.export_time') . date('Y-m-d H:i:s'));
        $this->sheet->mergeCells('A4:M4');

        // 设置信息行样式
        foreach ([2, 3, 4] as $row) {
            $this->sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0F2F5']
                ]
            ]);
        }

        $this->currentRow = 5; // 空行
    }

    /**
     * 写入统计汇总
     */
    private function writeSummary(array $totalStats)
    {
        $this->currentRow++;
        $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('agent_store_profit.export.summary_title'));
        $this->sheet->mergeCells('A' . $this->currentRow . ':M' . $this->currentRow);
        $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E6F7FF']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $this->currentRow++;
        $summaryData = [
            ['label' => admin_trans('agent_store_profit.stats.total_recharge'), 'value' => $totalStats['total_recharge']],
            ['label' => admin_trans('agent_store_profit.stats.total_withdraw'), 'value' => $totalStats['total_withdraw']],
            ['label' => admin_trans('agent_store_profit.stats.total_machine_put'), 'value' => $totalStats['total_machine_put']],
            ['label' => admin_trans('agent_store_profit.stats.total_lottery'), 'value' => $totalStats['total_lottery']],
            ['label' => admin_trans('agent_store_profit.stats.total_subtotal'), 'value' => $totalStats['total_subtotal']],
            ['label' => admin_trans('agent_store_profit.stats.total_agent_profit'), 'value' => $totalStats['total_agent_profit']],
            ['label' => admin_trans('agent_store_profit.stats.total_channel_profit'), 'value' => $totalStats['total_channel_profit']],
        ];

        $colIndex = 0;
        foreach ($summaryData as $item) {
            $col1 = chr(65 + $colIndex * 2); // A, C, E, G...
            $col2 = chr(66 + $colIndex * 2); // B, D, F, H...

            $this->sheet->setCellValue($col1 . $this->currentRow, $item['label']);
            $this->sheet->setCellValue($col2 . $this->currentRow, number_format(floatval($item['value']), 2));

            $this->sheet->getStyle($col1 . $this->currentRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0F2F5']
                ]
            ]);

            $this->sheet->getStyle($col2 . $this->currentRow)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1890FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
            ]);

            $colIndex++;
            if ($colIndex >= 7) break; // 最多7组统计数据
        }

        $this->currentRow += 2; // 空行
    }

    /**
     * 写入表头
     */
    private function writeHeaders()
    {
        $headers = [
            'ID',
            admin_trans('agent_store_profit.fields.store_name'),
            admin_trans('agent_store_profit.fields.store_username'),
            admin_trans('agent_store_profit.fields.recharge_amount'),
            admin_trans('agent_store_profit.fields.withdraw_amount'),
            admin_trans('agent_store_profit.fields.machine_put_point'),
            admin_trans('agent_store_profit.fields.lottery_amount'),
            admin_trans('agent_store_profit.fields.subtotal'),
            admin_trans('agent_store_profit.fields.agent_commission'),
            admin_trans('agent_store_profit.fields.agent_profit'),
            admin_trans('agent_store_profit.fields.channel_commission'),
            admin_trans('agent_store_profit.fields.channel_profit'),
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // 表头样式
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)->applyFromArray([
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
     * 写入合计行
     */
    private function writeTotalRow(array $totalStats)
    {
        $this->sheet->setCellValue('A' . $this->currentRow, '');
        $this->sheet->setCellValue('B' . $this->currentRow, admin_trans('agent_store_profit.export.total'));
        $this->sheet->setCellValue('C' . $this->currentRow, '');
        $this->sheet->setCellValue('D' . $this->currentRow, number_format(floatval($totalStats['total_recharge']), 2));
        $this->sheet->setCellValue('E' . $this->currentRow, number_format(floatval($totalStats['total_withdraw']), 2));
        $this->sheet->setCellValue('F' . $this->currentRow, number_format(floatval($totalStats['total_machine_put']), 2));
        $this->sheet->setCellValue('G' . $this->currentRow, number_format(floatval($totalStats['total_lottery']), 2));
        $this->sheet->setCellValue('H' . $this->currentRow, number_format(floatval($totalStats['total_subtotal']), 2));
        $this->sheet->setCellValue('I' . $this->currentRow, '');
        $this->sheet->setCellValue('J' . $this->currentRow, number_format(floatval($totalStats['total_agent_profit']), 2));
        $this->sheet->setCellValue('K' . $this->currentRow, '');
        $this->sheet->setCellValue('L' . $this->currentRow, number_format(floatval($totalStats['total_channel_profit']), 2));

        // 合计行样式
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1890FF']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '1890FF']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    /**
     * 设置列宽
     */
    private function setColumnWidths()
    {
        $this->sheet->getColumnDimension('A')->setWidth(8);  // ID
        $this->sheet->getColumnDimension('B')->setWidth(20); // 店家名称
        $this->sheet->getColumnDimension('C')->setWidth(15); // 店家账号
        $this->sheet->getColumnDimension('D')->setWidth(15); // 累计开分
        $this->sheet->getColumnDimension('E')->setWidth(15); // 累计洗分
        $this->sheet->getColumnDimension('F')->setWidth(15); // 投钞
        $this->sheet->getColumnDimension('G')->setWidth(15); // 彩金
        $this->sheet->getColumnDimension('H')->setWidth(15); // 小计
        $this->sheet->getColumnDimension('I')->setWidth(12); // 代理抽成
        $this->sheet->getColumnDimension('J')->setWidth(15); // 代理分润
        $this->sheet->getColumnDimension('K')->setWidth(12); // 渠道抽成
        $this->sheet->getColumnDimension('L')->setWidth(15); // 渠道分润
    }

    /**
     * 保存文件
     * 注意：这个方法不会被调用，因为我们在 write() 方法中已经直接保存了文件
     * 保留这个方法只是为了满足抽象类的要求
     */
    public function save(string $path)
    {
        // 这个方法不会被 Arrays driver 的 callback 调用，因为我们不调用 finish callback
        // 如果意外被调用，返回一个占位符
        return '/storage/placeholder.xlsx';
    }

    /**
     * 查询报表数据（复用控制器逻辑）
     */
    private function queryReportData($admin, $selectedStoreId, $createdAtStart, $createdAtEnd, &$reportData, &$totalStats)
    {
        \support\Log::info('queryReportData: 开始查询', [
            'admin_id' => $admin->id,
            'selected_store_id' => $selectedStoreId
        ]);

        // 获取代理下的所有店家
        $allStoresQuery = $admin->childStores()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1);

        // 如果选择了特定店家，只查询该店家
        if (!empty($selectedStoreId)) {
            $storeIds = [$selectedStoreId];
            \support\Log::info('queryReportData: 筛选特定店家', ['store_id' => $selectedStoreId]);
        } else {
            $storeIds = $allStoresQuery->pluck('id')->toArray();
            \support\Log::info('queryReportData: 查询所有店家', ['store_count' => count($storeIds)]);
        }

        // 构建报表数据
        $reportData = [];
        $totalStats = [
            'total_recharge' => '0',
            'total_withdraw' => '0',
            'total_machine_put' => '0',
            'total_lottery' => '0',
            'total_subtotal' => '0',
            'total_agent_profit' => '0',
            'total_channel_profit' => '0',
        ];

        foreach ($storeIds as $storeId) {
            $store = AdminUser::find($storeId);
            if (!$store) {
                continue;
            }

            // 获取该店家下的所有玩家
            $playerIds = Player::query()
                ->where('store_admin_id', $storeId)
                ->where('is_promoter', 0)
                ->pluck('id')
                ->toArray();

            if (empty($playerIds)) {
                // 没有玩家也要显示店家信息
                $reportData[] = [
                    'id' => $store->id,
                    'store_name' => $store->nickname,
                    'store_username' => $store->username,
                    'agent_commission' => $store->agent_commission ?? 0,
                    'channel_commission' => $store->channel_commission ?? 0,
                    'recharge_amount' => 0,
                    'withdraw_amount' => 0,
                    'machine_put_point' => 0,
                    'lottery_amount' => 0,
                    'subtotal' => '0',
                    'agent_profit' => '0',
                    'channel_profit' => '0',
                ];
                continue;
            }

            // 查询开分、洗分、投钞数据
            $deliveryQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds);

            // 时间筛选
            if (!empty($createdAtStart)) {
                $deliveryQuery->where('created_at', '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $deliveryQuery->where('created_at', '<=', $createdAtEnd);
            }

            $deliveryData = $deliveryQuery->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")->first();

            // 查询拉彩数据
            $lotteryQuery = PlayerLotteryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

            // 时间筛选
            if (!empty($createdAtStart)) {
                $lotteryQuery->where('created_at', '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $lotteryQuery->where('created_at', '<=', $createdAtEnd);
            }

            $lotteryData = $lotteryQuery->selectRaw("
                SUM(`amount`) as lottery_amount
            ")->first();

            // 提取数据
            $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
            $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

            // 计算小计：(开分+投钞) - (洗分+彩金)
            $totalIn = bcadd(strval($rechargeAmount), strval($machinePutPoint), 2);
            $totalOut = bcadd(strval($withdrawAmount), strval($lotteryAmount), 2);
            $subtotal = bcsub($totalIn, $totalOut, 2);

            // 计算代理分润：小计 * 代理抽成比例
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($subtotal, bcdiv(strval($agentCommission), '100', 4), 2);

            // 计算渠道分润：小计 * 渠道抽成比例
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($subtotal, bcdiv(strval($channelCommission), '100', 4), 2);

            $item = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'agent_commission' => $agentCommission,
                'channel_commission' => $channelCommission,
                'recharge_amount' => $rechargeAmount,
                'withdraw_amount' => $withdrawAmount,
                'machine_put_point' => $machinePutPoint,
                'lottery_amount' => $lotteryAmount,
                'subtotal' => $subtotal,
                'agent_profit' => $agentProfit,
                'channel_profit' => $channelProfit,
            ];

            $reportData[] = $item;

            // 累加统计
            $totalStats['total_recharge'] = bcadd($totalStats['total_recharge'], strval($rechargeAmount), 2);
            $totalStats['total_withdraw'] = bcadd($totalStats['total_withdraw'], strval($withdrawAmount), 2);
            $totalStats['total_machine_put'] = bcadd($totalStats['total_machine_put'], strval($machinePutPoint), 2);
            $totalStats['total_lottery'] = bcadd($totalStats['total_lottery'], strval($lotteryAmount), 2);
            $totalStats['total_subtotal'] = bcadd($totalStats['total_subtotal'], $subtotal, 2);
            $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $agentProfit, 2);
            $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $channelProfit, 2);
        }
    }
}

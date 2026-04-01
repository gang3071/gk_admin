<?php

namespace addons\webman\grid;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
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

    /**
     * 导出数据
     * @param array $data
     * @param \Closure|null $finish
     * @return void
     */
    public function write(array $data, \Closure $finish = null)
    {
        try {
            // 直接使用传入的数据（Grid 已经查询好的数据）
            $reportData = $data;

            // 从 Request 获取筛选参数（用于显示时间范围）
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
            $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;

            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 计算统计汇总
            $totalStats = [
                'total_recharge' => 0,
                'total_withdraw' => 0,
                'total_machine_put' => 0,
                'total_lottery' => 0,
                'total_subtotal' => 0,
                'total_agent_profit' => 0,
                'total_channel_profit' => 0,
            ];

            foreach ($reportData as $item) {
                $totalStats['total_recharge'] = bcadd($totalStats['total_recharge'], $item['recharge_amount'] ?? 0, 2);
                $totalStats['total_withdraw'] = bcadd($totalStats['total_withdraw'], $item['withdraw_amount'] ?? 0, 2);
                $totalStats['total_machine_put'] = bcadd($totalStats['total_machine_put'], $item['machine_put_point'] ?? 0, 2);
                $totalStats['total_lottery'] = bcadd($totalStats['total_lottery'], $item['lottery_amount'] ?? 0, 2);
                $totalStats['total_subtotal'] = bcadd($totalStats['total_subtotal'], $item['subtotal'] ?? 0, 2);
                $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $item['agent_profit'] ?? 0, 2);
                $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $item['channel_profit'] ?? 0, 2);
            }

            // 写入 Excel
            $this->writeExcel($reportData, $totalStats, $createdAtStart, $createdAtEnd, $admin);

            // 完成回调
            if ($finish) {
                $result = call_user_func($finish, $this);
                $this->cache->set(['status' => 1, 'url' => $result]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);
            }

        } catch (\Throwable $e) {
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
     * 写入 Excel 数据
     */
    private function writeExcel(array $reportData, array $totalStats, $startTime, $endTime, $admin)
    {
        // 1. 标题区域
        $this->writeTitle($admin, $startTime, $endTime);

        // 2. 统计汇总区域
        $this->writeSummary($totalStats);

        // 3. 表头
        $this->writeHeaders();

        // 4. 数据行
        $this->writeDataRows($reportData);

        // 5. 合计行
        $this->writeTotalRow($totalStats);

        // 6. 设置列宽
        $this->setColumnWidths();
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
     * 写入数据行
     */
    private function writeDataRows(array $reportData)
    {
        foreach ($reportData as $index => $item) {
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
}

<?php

namespace addons\webman\grid;

use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ShiftReportExporter extends Excel
{
    // 跟踪已处理的记录数（用于判断是否完成）
    protected $processedRecords = 0;

    // 跟踪设备数量
    protected $totalDevices = 0;

    // 总计数据
    protected $grandTotal = [
        'machine_point' => 0,
        'recharge_amount' => 0,
        'withdrawal_amount' => 0,
        'modified_add_amount' => 0,
        'modified_deduct_amount' => 0,
        'lottery_amount' => 0,
        'total_in' => 0,
        'total_out' => 0,
        'profit' => 0
    ];

    public function columns(array $columns)
    {
        // 保存列配置，但不生成默认表头
        $this->columns = $columns;
        return $this;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
        foreach ($data as $record) {
            // 从数据库查询原始记录（因为 parseColumn 后的数据没有所有字段）
            $recordId = $record['id'] ?? null;
            if (!$recordId) {
                continue;
            }

            $originalRecord = StoreAgentShiftHandoverRecord::find($recordId);
            if (!$originalRecord) {
                continue;
            }

            // 交班记录标题行
            $this->sheet->setCellValue('A' . $this->currentRow, '交班ID: ' . $originalRecord->id);
            $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
            $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F8']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
            ]);
            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
            $this->currentRow++;

            // 交班汇总信息（改为更清晰的布局）
            $summaryData = [
                ['交班时间:', $originalRecord->start_time . ' ~ ' . $originalRecord->end_time, '交班类型:', $originalRecord->is_auto_shift == 1 ? '自动交班' : '手动交班'],
                ['投钞点数:', number_format($originalRecord->machine_point, 0), '彩金:', number_format($originalRecord->lottery_amount, 2)],
                ['总收入:', number_format($originalRecord->total_in, 2), '总支出:', number_format($originalRecord->total_out, 2)],
                ['利润:', number_format($originalRecord->total_profit_amount, 2), '', '']
            ];

            $summaryStartRow = $this->currentRow;
            foreach ($summaryData as $rowData) {
                $this->sheet->setCellValue('A' . $this->currentRow, $rowData[0]);
                $this->sheet->setCellValue('B' . $this->currentRow, $rowData[1]);
                $this->sheet->setCellValue('C' . $this->currentRow, $rowData[2]);
                $this->sheet->setCellValue('D' . $this->currentRow, $rowData[3]);

                // 标签列加粗
                $this->sheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
                $this->sheet->getStyle('C' . $this->currentRow)->getFont()->setBold(true);

                // 数值列右对齐
                $this->sheet->getStyle('B' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getStyle('D' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $this->currentRow++;
            }

            // 合并汇总区域的右侧列
            $this->sheet->mergeCells('D' . ($summaryStartRow + 3) . ':K' . ($summaryStartRow + 3));

            // 设置汇总区域样式
            $summaryRange = 'A' . $summaryStartRow . ':K' . ($this->currentRow - 1);
            $this->sheet->getStyle($summaryRange)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);

            // 利润单元格颜色
            $profitCell = 'B' . ($summaryStartRow + 3);
            $profitColor = $originalRecord->total_profit_amount >= 0 ? '3f8600' : 'cf1322';
            $this->sheet->getStyle($profitCell)->getFont()->getColor()->setRGB($profitColor);
            $this->sheet->getStyle($profitCell)->getFont()->setBold(true);

            $this->currentRow++; // 空行

            // 获取设备明细
            $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $originalRecord->id)
                ->orderBy('profit', 'desc')
                ->get();

            if ($deviceDetails->isNotEmpty()) {
                // 设备明细表头
                $headers = ['设备名称', '设备编号', '投钞点数', '开分', '洗分', '后台加点', '后台扣点', '彩金', '总收入', '总支出', '利润'];
                $headerRow = $this->currentRow;

                foreach ($headers as $index => $header) {
                    $this->sheet->setCellValueByColumnAndRow($index + 1, $this->currentRow, $header);
                }

                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D0E8F2']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                ]);
                $this->sheet->getRowDimension($this->currentRow)->setRowHeight(22);
                $this->currentRow++;

                // 小计数据
                $subtotal = [
                    'machine_point' => 0,
                    'recharge_amount' => 0,
                    'withdrawal_amount' => 0,
                    'modified_add_amount' => 0,
                    'modified_deduct_amount' => 0,
                    'lottery_amount' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'profit' => 0
                ];

                // 设备明细数据
                $detailStartRow = $this->currentRow;
                foreach ($deviceDetails as $index => $detail) {
                    $this->sheet->setCellValue('A' . $this->currentRow, $detail->player_name);
                    $this->sheet->setCellValue('B' . $this->currentRow, $detail->player_phone);
                    $this->sheet->setCellValue('C' . $this->currentRow, number_format($detail->machine_point, 0));
                    $this->sheet->setCellValue('D' . $this->currentRow, number_format($detail->recharge_amount, 2));
                    $this->sheet->setCellValue('E' . $this->currentRow, number_format($detail->withdrawal_amount, 2));
                    $this->sheet->setCellValue('F' . $this->currentRow, number_format($detail->modified_add_amount, 2));
                    $this->sheet->setCellValue('G' . $this->currentRow, number_format($detail->modified_deduct_amount, 2));
                    $this->sheet->setCellValue('H' . $this->currentRow, number_format($detail->lottery_amount, 2));
                    $this->sheet->setCellValue('I' . $this->currentRow, number_format($detail->total_in, 2));
                    $this->sheet->setCellValue('J' . $this->currentRow, number_format($detail->total_out, 2));
                    $this->sheet->setCellValue('K' . $this->currentRow, number_format($detail->profit, 2));

                    // 数字列右对齐
                    $this->sheet->getStyle('C' . $this->currentRow . ':K' . $this->currentRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // 交替行背景色
                    $rowColor = $index % 2 == 0 ? 'FFFFFF' : 'F9F9F9';
                    $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]]
                    ]);

                    // 利润颜色
                    $profitColor = $detail->profit >= 0 ? '3f8600' : 'cf1322';
                    $this->sheet->getStyle('K' . $this->currentRow)->getFont()->getColor()->setRGB($profitColor);
                    $this->sheet->getStyle('K' . $this->currentRow)->getFont()->setBold(true);

                    // 累加小计
                    $subtotal['machine_point'] += $detail->machine_point;
                    $subtotal['recharge_amount'] += $detail->recharge_amount;
                    $subtotal['withdrawal_amount'] += $detail->withdrawal_amount;
                    $subtotal['modified_add_amount'] += $detail->modified_add_amount;
                    $subtotal['modified_deduct_amount'] += $detail->modified_deduct_amount;
                    $subtotal['lottery_amount'] += $detail->lottery_amount;
                    $subtotal['total_in'] += $detail->total_in;
                    $subtotal['total_out'] += $detail->total_out;
                    $subtotal['profit'] += $detail->profit;

                    $this->currentRow++;
                }

                // 小计行
                $this->sheet->setCellValue('A' . $this->currentRow, '小计');
                $this->sheet->setCellValue('B' . $this->currentRow, '');
                $this->sheet->setCellValue('C' . $this->currentRow, number_format($subtotal['machine_point'], 0));
                $this->sheet->setCellValue('D' . $this->currentRow, number_format($subtotal['recharge_amount'], 2));
                $this->sheet->setCellValue('E' . $this->currentRow, number_format($subtotal['withdrawal_amount'], 2));
                $this->sheet->setCellValue('F' . $this->currentRow, number_format($subtotal['modified_add_amount'], 2));
                $this->sheet->setCellValue('G' . $this->currentRow, number_format($subtotal['modified_deduct_amount'], 2));
                $this->sheet->setCellValue('H' . $this->currentRow, number_format($subtotal['lottery_amount'], 2));
                $this->sheet->setCellValue('I' . $this->currentRow, number_format($subtotal['total_in'], 2));
                $this->sheet->setCellValue('J' . $this->currentRow, number_format($subtotal['total_out'], 2));
                $this->sheet->setCellValue('K' . $this->currentRow, number_format($subtotal['profit'], 2));

                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE599']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '999999']]]
                ]);
                $this->sheet->getStyle('A' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 小计利润颜色
                $subtotalProfitColor = $subtotal['profit'] >= 0 ? '3f8600' : 'cf1322';
                $this->sheet->getStyle('K' . $this->currentRow)->getFont()->getColor()->setRGB($subtotalProfitColor);

                $this->currentRow++;

                // 累加到总计
                foreach ($subtotal as $key => $value) {
                    $this->grandTotal[$key] += $value;
                }

                // 累加设备数量
                $this->totalDevices += $deviceDetails->count();
            } else {
                $this->sheet->setCellValue('A' . $this->currentRow, '暂无设备数据');
                $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                ]);
                $this->currentRow++;
            }

            // 空行分隔
            $this->currentRow += 2;

            // 递增已处理记录数
            $this->processedRecords++;

            // 更新缓存进度（基于已处理的记录数）
            $progress = $this->count > 0 ? floor($this->processedRecords / $this->count * 100) : 0;
            $this->cache->set([
                'status' => 0,
                'progress' => $progress
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);
        }

        // 在 foreach 循环外部检查是否所有记录都已处理完成
        if ($this->processedRecords >= $this->count) {
            // 添加总计行
            $this->addGrandTotalRow();

            // 设置列宽
            $this->setColumnWidths();

            // 冻结首行
            $this->sheet->freezePane('A2');

            // 完成回调 - 只在所有数据处理完成后调用
            if ($finish) {
                $result = call_user_func($finish, $this);
                $this->cache->set([
                    'status' => 1,
                    'url' => $result
                ]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);
            }
        }
        } catch (\Throwable $e) {
            // 捕获异常并保存错误信息到缓存
            $this->cache->set([
                'status' => 2,
                'error' => $e->getMessage(),
                'file' => str_replace('D:\\gk_admin\\', '', $e->getFile()),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);

            // 不要重新抛出，让错误信息保存在缓存中供前端显示
            return;
        }
    }

    /**
     * 添加总计行
     */
    protected function addGrandTotalRow()
    {
        $this->currentRow += 1;

        // 总计标题
        $this->sheet->setCellValue('A' . $this->currentRow, '═══ 总计 ═══ (共 ' . $this->processedRecords . ' 次交班, ' . $this->totalDevices . ' 台设备)');
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '2F5496']]]
        ]);
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(30);
        $this->currentRow++;

        // 总计数据行
        $this->sheet->setCellValue('A' . $this->currentRow, '全部交班记录');
        $this->sheet->setCellValue('B' . $this->currentRow, '');
        $this->sheet->setCellValue('C' . $this->currentRow, number_format($this->grandTotal['machine_point'], 0));
        $this->sheet->setCellValue('D' . $this->currentRow, number_format($this->grandTotal['recharge_amount'], 2));
        $this->sheet->setCellValue('E' . $this->currentRow, number_format($this->grandTotal['withdrawal_amount'], 2));
        $this->sheet->setCellValue('F' . $this->currentRow, number_format($this->grandTotal['modified_add_amount'], 2));
        $this->sheet->setCellValue('G' . $this->currentRow, number_format($this->grandTotal['modified_deduct_amount'], 2));
        $this->sheet->setCellValue('H' . $this->currentRow, number_format($this->grandTotal['lottery_amount'], 2));
        $this->sheet->setCellValue('I' . $this->currentRow, number_format($this->grandTotal['total_in'], 2));
        $this->sheet->setCellValue('J' . $this->currentRow, number_format($this->grandTotal['total_out'], 2));
        $this->sheet->setCellValue('K' . $this->currentRow, number_format($this->grandTotal['profit'], 2));

        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FF9900']]]
        ]);
        $this->sheet->getStyle('A' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);

        // 总计利润颜色
        $grandProfitColor = $this->grandTotal['profit'] >= 0 ? '3f8600' : 'cf1322';
        $this->sheet->getStyle('K' . $this->currentRow)->getFont()->getColor()->setRGB($grandProfitColor);
    }

    /**
     * 设置列宽
     */
    protected function setColumnWidths()
    {
        $widths = [
            'A' => 20,  // 设备名称
            'B' => 15,  // 设备编号
            'C' => 12,  // 投钞点数
            'D' => 14,  // 开分
            'E' => 14,  // 洗分
            'F' => 14,  // 后台加点
            'G' => 14,  // 后台扣点
            'H' => 14,  // 彩金
            'I' => 14,  // 总收入
            'J' => 14,  // 总支出
            'K' => 16,  // 利润
        ];

        foreach ($widths as $col => $width) {
            $this->sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    /**
     * 保存文件
     * @param string $path 保存目录
     * @return string|bool
     */
    public function save(string $path)
    {
        // 确保目录存在
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return parent::save($path);
    }

    /**
     * 导出错误（带详细信息）
     * @param \Throwable $exception
     */
    public function exportErrorWithDetails(\Throwable $exception = null)
    {
        $data = ['status' => 2];

        if ($exception) {
            $data['error'] = $exception->getMessage();
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['trace'] = substr($exception->getTraceAsString(), 0, 2000);
        }

        $this->cache->set($data);
        $this->cache->expiresAfter(60);
        $this->filesystemAdapter->save($this->cache);
    }
}

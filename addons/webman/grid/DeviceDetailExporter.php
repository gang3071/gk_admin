<?php

namespace addons\webman\grid;

use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerMoneyEditLog;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 设备明细导出器
 * 专门用于导出单条交班记录的设备明细
 */
class DeviceDetailExporter extends Excel
{
    protected ?StoreAgentShiftHandoverRecord $shiftRecord = null;

    public function columns(array $columns)
    {
        // 保存列配置
        $this->columns = $columns;
        return $this;
    }

    /**
     * 获取交班记录
     */
    protected function getShiftRecord(): ?StoreAgentShiftHandoverRecord
    {
        if ($this->shiftRecord === null) {
            // 从请求参数获取 shift_record_id
            $shiftRecordId = Request::input('shift_record_id');
            if ($shiftRecordId) {
                $this->shiftRecord = StoreAgentShiftHandoverRecord::find($shiftRecordId);
            }
        }
        return $this->shiftRecord;
    }

    /**
     * 获取设备的历史交易记录
     * @param int $playerId 设备ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    protected function getDeviceTransactionHistory(int $playerId, string $startTime, string $endTime): array
    {
        $records = [];

        // 1. 开分记录
        $recharges = PlayerRechargeRecord::where('player_id', $playerId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->where('status', 1) // 已完成
            ->orderBy('created_at')
            ->get();

        foreach ($recharges as $record) {
            $records[] = [
                'time' => $record->created_at,
                'type' => admin_trans('shift_handover.transaction.type_recharge'),
                'type_key' => 'recharge',
                'amount' => $record->money,
                'remark' => $record->remark ?? ''
            ];
        }

        // 2. 洗分记录
        $withdrawals = PlayerWithdrawRecord::where('player_id', $playerId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->where('status', 1) // 已完成
            ->orderBy('created_at')
            ->get();

        foreach ($withdrawals as $record) {
            $records[] = [
                'time' => $record->created_at,
                'type' => admin_trans('shift_handover.transaction.type_withdrawal'),
                'type_key' => 'withdrawal',
                'amount' => $record->money,
                'remark' => $record->remark ?? ''
            ];
        }

        // 3. 彩金记录
        $lotteries = PlayerLotteryRecord::where('player_id', $playerId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->orderBy('created_at')
            ->get();

        foreach ($lotteries as $record) {
            $records[] = [
                'time' => $record->created_at,
                'type' => admin_trans('shift_handover.transaction.type_lottery'),
                'type_key' => 'lottery',
                'amount' => $record->amount,
                'remark' => $record->lottery_name ?? ''
            ];
        }

        // 4. 后台加点/扣点记录
        $edits = PlayerMoneyEditLog::where('player_id', $playerId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->orderBy('created_at')
            ->get();

        foreach ($edits as $record) {
            $isAddPoint = $record->money > 0;
            $records[] = [
                'time' => $record->created_at,
                'type' => $isAddPoint ? admin_trans('shift_handover.transaction.type_add_point') : admin_trans('shift_handover.transaction.type_deduct_point'),
                'type_key' => $isAddPoint ? 'add_point' : 'deduct_point',
                'amount' => abs($record->money),
                'remark' => $record->remark ?? ''
            ];
        }

        // 按时间排序
        usort($records, function ($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });

        return $records;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
            // 获取交班记录
            $shiftRecord = $this->getShiftRecord();
            if (!$shiftRecord) {
                throw new \Exception('交班记录不存在');
            }

            // 交班记录标题行
            $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.export.title', null, ['id' => $shiftRecord->id]));
            $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
            $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 16],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '2F5496']]]
            ]);
            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(30);
            $this->currentRow++;

            // 空行
            $this->currentRow++;

            // 交班汇总信息
            $summaryData = [
                [admin_trans('shift_handover.export.start_time_label'), $shiftRecord->start_time, admin_trans('shift_handover.export.end_time_label'), $shiftRecord->end_time],
                [admin_trans('shift_handover.export.shift_type_label'), $shiftRecord->is_auto_shift == 1 ? admin_trans('shift_handover.auto_shift') : admin_trans('shift_handover.manual_shift'), admin_trans('shift_handover.export.machine_point_label'), number_format($shiftRecord->machine_point, 0)],
                [admin_trans('shift_handover.export.lottery_amount_label'), number_format($shiftRecord->lottery_amount, 2), admin_trans('shift_handover.export.total_in_label'), number_format($shiftRecord->total_in, 2)],
                [admin_trans('shift_handover.export.total_out_label'), number_format($shiftRecord->total_out, 2), admin_trans('shift_handover.export.total_profit_label'), number_format($shiftRecord->total_profit_amount, 2)]
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

            // 设置汇总区域样式
            $summaryRange = 'A' . $summaryStartRow . ':D' . ($this->currentRow - 1);
            $this->sheet->getStyle($summaryRange)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);

            // 利润单元格颜色
            $profitCell = 'D' . ($summaryStartRow + 3);
            $profitColor = $shiftRecord->total_profit_amount >= 0 ? '3f8600' : 'cf1322';
            $this->sheet->getStyle($profitCell)->getFont()->getColor()->setRGB($profitColor);
            $this->sheet->getStyle($profitCell)->getFont()->setBold(true);

            $this->currentRow++; // 空行

            // 获取设备明细
            $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $shiftRecord->id)
                ->orderBy('profit', 'desc')
                ->get();

            if ($deviceDetails->isEmpty()) {
                $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.export.no_device_data'));
                $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF4E6']]
                ]);
                $this->currentRow++;
            } else {
                // 设备明细表头
                $headers = [
                    admin_trans('shift_handover.device_name'),
                    admin_trans('shift_handover.device_number'),
                    admin_trans('shift_handover.machine_point'),
                    admin_trans('shift_handover.recharge_amount'),
                    admin_trans('shift_handover.withdrawal_amount'),
                    admin_trans('shift_handover.modified_add_amount'),
                    admin_trans('shift_handover.modified_deduct_amount'),
                    admin_trans('shift_handover.lottery_amount'),
                    admin_trans('shift_handover.total_in'),
                    admin_trans('shift_handover.total_out'),
                    admin_trans('shift_handover.profit')
                ];

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

                    // 添加该设备的历史交易记录
                    $this->writeDeviceTransactionHistory($detail->player_id, $detail->player_name, $shiftRecord->start_time, $shiftRecord->end_time);
                }

                // 小计行
                $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.export.subtotal_devices', null, ['count' => $deviceDetails->count()]));
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
            }

            // 添加说明
            $this->currentRow += 2;
            $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.export.device_detail_note', null, ['time' => date('Y-m-d H:i:s')]));
            $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
            $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ]);

            // 设置列宽
            $this->setColumnWidths();

            // 冻结首行
            $this->sheet->freezePane('A2');

            // 完成回调
            if ($finish) {
                $result = call_user_func($finish, $this);
                $this->cache->set([
                    'status' => 1,
                    'url' => $result
                ]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);
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

            return;
        }
    }

    /**
     * 输出设备的历史交易记录
     * @param int $playerId 设备ID
     * @param string $playerName 设备名称
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     */
    protected function writeDeviceTransactionHistory(int $playerId, string $playerName, string $startTime, string $endTime)
    {
        // 获取历史交易记录
        $transactions = $this->getDeviceTransactionHistory($playerId, $startTime, $endTime);

        if (empty($transactions)) {
            return;
        }

        // 添加设备历史记录标题
        $this->sheet->setCellValue('A' . $this->currentRow, '  ↳ ' . admin_trans('shift_handover.transaction.detail_title', null, ['name' => $playerName, 'count' => count($transactions)]));
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1890ff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
        ]);
        $this->currentRow++;

        // 历史记录表头
        $this->sheet->setCellValue('B' . $this->currentRow, admin_trans('shift_handover.transaction.time'));
        $this->sheet->setCellValue('C' . $this->currentRow, admin_trans('shift_handover.transaction.type'));
        $this->sheet->setCellValue('D' . $this->currentRow, admin_trans('shift_handover.transaction.amount'));
        $this->sheet->setCellValue('E' . $this->currentRow, admin_trans('shift_handover.transaction.remark'));
        $this->sheet->mergeCells('E' . $this->currentRow . ':K' . $this->currentRow);

        $this->sheet->getStyle('B' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]]
        ]);
        $this->currentRow++;

        // 历史记录数据
        foreach ($transactions as $transaction) {
            $this->sheet->setCellValue('B' . $this->currentRow, $transaction['time']);
            $this->sheet->setCellValue('C' . $this->currentRow, $transaction['type']);
            $this->sheet->setCellValue('D' . $this->currentRow, number_format($transaction['amount'], 2));
            $this->sheet->setCellValue('E' . $this->currentRow, $transaction['remark']);
            $this->sheet->mergeCells('E' . $this->currentRow . ':K' . $this->currentRow);

            // 样式
            $this->sheet->getStyle('B' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                'font' => ['size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'EEEEEE']]]
            ]);

            // 金额右对齐
            $this->sheet->getStyle('D' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // 类型颜色标记
            $typeColor = '000000';
            switch ($transaction['type_key']) {
                case 'recharge':
                    $typeColor = '52c41a'; // 绿色
                    break;
                case 'withdrawal':
                    $typeColor = 'ff4d4f'; // 红色
                    break;
                case 'lottery':
                    $typeColor = 'fa8c16'; // 橙色
                    break;
                case 'add_point':
                    $typeColor = '1890ff'; // 蓝色
                    break;
                case 'deduct_point':
                    $typeColor = 'f5222d'; // 暗红色
                    break;
            }
            $this->sheet->getStyle('C' . $this->currentRow)->getFont()->getColor()->setRGB($typeColor);
            $this->sheet->getStyle('C' . $this->currentRow)->getFont()->setBold(true);

            $this->currentRow++;
        }

        // 添加空行分隔
        $this->currentRow++;
    }

    /**
     * 设置列宽
     */
    protected function setColumnWidths()
    {
        $widths = [
            'A' => 22,  // 设备名称
            'B' => 18,  // 设备编号/时间
            'C' => 12,  // 投钞点数/类型
            'D' => 14,  // 开分/金额
            'E' => 25,  // 洗分/备注
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
}

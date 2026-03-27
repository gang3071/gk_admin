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

    // 总计数据（累加所有设备明细）
    protected $grandTotal = [
        'machine_point' => 0,
        'recharge_amount' => 0,      // 开分
        'withdrawal_amount' => 0,    // 洗分
        'modified_add_amount' => 0,  // 后台加点
        'modified_deduct_amount' => 0, // 后台扣点
        'lottery_amount' => 0,       // 彩金
        'total_in' => 0,             // 总收入
        'total_out' => 0,            // 总支出
        'profit' => 0                // 利润
    ];

    // 存储所有交班记录数据，用于先计算总计再输出明细
    protected $allRecords = [];

    // 存储每个设备的累计数据 [player_name => [...]]
    protected $deviceTotals = [];

    // 店家管理员ID（用于查询所有设备）
    protected $storeAdminId = null;

    public function columns(array $columns)
    {
        // 保存列配置，但不生成默认表头
        $this->columns = $columns;
        return $this;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
        // 如果是第一次调用，初始化店家所有设备并加载所有历史数据
        if ($this->processedRecords == 0) {
            // 获取店家管理员ID
            if (!empty($data)) {
                $firstRecordId = $data[0]['id'] ?? null;
                if ($firstRecordId) {
                    $firstRecord = StoreAgentShiftHandoverRecord::find($firstRecordId);
                    if ($firstRecord && $firstRecord->bind_admin_user_id) {
                        $this->storeAdminId = $firstRecord->bind_admin_user_id;
                    }
                }
            }

            // 初始化店家所有设备
            $this->initializeStoreDevices();

            // 加载所有历史交班记录的设备明细数据（从设备创建开始到现在）
            $this->loadAllHistoricalData();

            // 从第1行开始写明细，完成后会在前面插入总计区域
            $this->currentRow = 1;
        }

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

            // 交班记录标题行（包含交班ID和时间）
            $titleText = admin_trans('shift_handover.shift_id') . ': ' . $originalRecord->id . '    ' .
                         admin_trans('shift_handover.shift_time') . ': ' . $originalRecord->start_time . ' ~ ' . $originalRecord->end_time;
            $this->sheet->setCellValue('A' . $this->currentRow, $titleText);
            $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
            $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F8']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
            ]);
            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
            $this->currentRow++;

            // 获取设备明细
            $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $originalRecord->id)
                ->orderBy('profit', 'desc')
                ->get();

            if ($deviceDetails->isNotEmpty()) {
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

                    // 注意：当前交班记录的设备数据已经在 loadAllHistoricalData() 中累加过了
                    // 这里不需要重复累加，因为 loadAllHistoricalData() 已经包含了所有交班记录

                    $this->currentRow++;
                }

                // 小计行
                $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.subtotal') . ' (' . admin_trans('shift_handover.shift_id') . '#' . $originalRecord->id . ')');
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

                // 注意：不累加小计到总计，因为总计已经在 loadAllHistoricalData() 中计算了所有历史数据
            } else {
                // 没有设备明细数据
                $this->sheet->setCellValue('A' . $this->currentRow, admin_trans('shift_handover.no_device_data'));
                $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF4E6']]
                ]);
                $this->currentRow++;

                // 注意：不累加到总计，因为总计已经在 loadAllHistoricalData() 中计算了所有历史数据
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
            // 计算顶部需要的行数（动态计算，因为设备数量在处理完成后才知道）
            $deviceCount = count($this->deviceTotals);
            $topRowsNeeded = 13 + $deviceCount; // 1标题 + 1空 + 1表头 + N设备 + 1总计 + 2空 + 1说明 + 2空 + 1分隔 + 1空 + 1明细标题 + 2空

            // 在第1行前插入足够的行数（这会将已写入的明细数据自动下移）
            $this->sheet->insertNewRowBefore(1, $topRowsNeeded);

            // 添加总计行（写入到第1行开始的插入区域）
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
     * 初始化店家所有设备
     */
    protected function initializeStoreDevices()
    {
        if (!$this->storeAdminId) {
            return;
        }

        // 查询店家所有设备（Player表）
        $playerModel = plugin()->webman->config('database.player_model');
        $allDevices = $playerModel::query()
            ->where('store_admin_id', $this->storeAdminId)
            ->select(['id', 'name', 'phone', 'uuid'])
            ->get();

        // 初始化每个设备的累计数据结构（使用 player_id 作为唯一标识）
        foreach ($allDevices as $device) {
            $deviceKey = $device->id; // 使用 player_id 作为唯一标识
            $this->deviceTotals[$deviceKey] = [
                'player_id' => $device->id,
                'player_name' => $device->name,
                'player_phone' => $device->phone ?? $device->uuid,
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
        }
    }

    /**
     * 加载所有历史交班记录的设备明细数据（从设备创建开始到现在）
     */
    protected function loadAllHistoricalData()
    {
        if (!$this->storeAdminId) {
            return;
        }

        // 查询该店家所有的交班记录
        $allShiftRecords = StoreAgentShiftHandoverRecord::query()
            ->where('bind_admin_user_id', $this->storeAdminId)
            ->pluck('id');

        if ($allShiftRecords->isEmpty()) {
            return;
        }

        // 查询所有交班记录的设备明细
        $allDeviceDetails = StoreShiftDeviceDetail::query()
            ->whereIn('shift_record_id', $allShiftRecords)
            ->get();

        // 累加到每个设备的总计和全局总计
        foreach ($allDeviceDetails as $detail) {
            $deviceKey = $detail->player_id; // 使用 player_id 作为唯一标识

            if (isset($this->deviceTotals[$deviceKey])) {
                $this->deviceTotals[$deviceKey]['machine_point'] += $detail->machine_point;
                $this->deviceTotals[$deviceKey]['recharge_amount'] += $detail->recharge_amount;
                $this->deviceTotals[$deviceKey]['withdrawal_amount'] += $detail->withdrawal_amount;
                $this->deviceTotals[$deviceKey]['modified_add_amount'] += $detail->modified_add_amount;
                $this->deviceTotals[$deviceKey]['modified_deduct_amount'] += $detail->modified_deduct_amount;
                $this->deviceTotals[$deviceKey]['lottery_amount'] += $detail->lottery_amount;
                $this->deviceTotals[$deviceKey]['total_in'] += $detail->total_in;
                $this->deviceTotals[$deviceKey]['total_out'] += $detail->total_out;
                $this->deviceTotals[$deviceKey]['profit'] += $detail->profit;
            }

            // 同时累加到全局总计
            $this->grandTotal['machine_point'] += $detail->machine_point;
            $this->grandTotal['recharge_amount'] += $detail->recharge_amount;
            $this->grandTotal['withdrawal_amount'] += $detail->withdrawal_amount;
            $this->grandTotal['modified_add_amount'] += $detail->modified_add_amount;
            $this->grandTotal['modified_deduct_amount'] += $detail->modified_deduct_amount;
            $this->grandTotal['lottery_amount'] += $detail->lottery_amount;
            $this->grandTotal['total_in'] += $detail->total_in;
            $this->grandTotal['total_out'] += $detail->total_out;
            $this->grandTotal['profit'] += $detail->profit;
        }
    }

    /**
     * 添加总计行（在顶部显示 - 当前店家所有设备从开始到现在的累加报表）
     */
    protected function addGrandTotalRow()
    {
        $topRow = 1; // 从第1行开始

        // 设置设备总数为实际设备数量
        $this->totalDevices = count($this->deviceTotals);

        // ==================== 第1部分：累加总报表标题 ====================
        $totalTitle = sprintf(
            '【%s】 %s：%d %s | %s：%d %s',
            admin_trans('shift_handover.grand_total'),
            admin_trans('shift_handover.total_shifts'),
            $this->processedRecords,
            admin_trans('shift_handover.shifts'),
            admin_trans('shift_handover.devices'),
            $this->totalDevices,
            admin_trans('shift_handover.devices_unit')
        );
        $this->sheet->setCellValue('A' . $topRow, $totalTitle);
        $this->sheet->mergeCells('A' . $topRow . ':K' . $topRow);
        $this->sheet->getStyle('A' . $topRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '2F5496']]]
        ]);
        $this->sheet->getRowDimension($topRow)->setRowHeight(35);
        $topRow++;

        // 空行
        $topRow++;

        // ==================== 第2部分：总计表头 ====================
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
            $this->sheet->setCellValueByColumnAndRow($index + 1, $topRow, $header);
        }

        $this->sheet->getStyle('A' . $topRow . ':K' . $topRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D0E8F2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
        ]);
        $this->sheet->getRowDimension($topRow)->setRowHeight(22);
        $topRow++;

        // ==================== 第3部分：每个设备的明细行 ====================
        // 按利润倒序排列设备
        usort($this->deviceTotals, function($a, $b) {
            return $b['profit'] <=> $a['profit'];
        });

        $deviceRowStart = $topRow;
        foreach ($this->deviceTotals as $index => $device) {
            $this->sheet->setCellValue('A' . $topRow, $device['player_name']);
            $this->sheet->setCellValue('B' . $topRow, $device['player_phone']);
            $this->sheet->setCellValue('C' . $topRow, number_format($device['machine_point'], 0));
            $this->sheet->setCellValue('D' . $topRow, number_format($device['recharge_amount'], 2));
            $this->sheet->setCellValue('E' . $topRow, number_format($device['withdrawal_amount'], 2));
            $this->sheet->setCellValue('F' . $topRow, number_format($device['modified_add_amount'], 2));
            $this->sheet->setCellValue('G' . $topRow, number_format($device['modified_deduct_amount'], 2));
            $this->sheet->setCellValue('H' . $topRow, number_format($device['lottery_amount'], 2));
            $this->sheet->setCellValue('I' . $topRow, number_format($device['total_in'], 2));
            $this->sheet->setCellValue('J' . $topRow, number_format($device['total_out'], 2));
            $this->sheet->setCellValue('K' . $topRow, number_format($device['profit'], 2));

            // 数字列右对齐
            $this->sheet->getStyle('C' . $topRow . ':K' . $topRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // 交替行背景色
            $rowColor = $index % 2 == 0 ? 'FFFFFF' : 'F9F9F9';
            $this->sheet->getStyle('A' . $topRow . ':K' . $topRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]]
            ]);

            // 利润颜色
            $profitColor = $device['profit'] >= 0 ? '3f8600' : 'cf1322';
            $this->sheet->getStyle('K' . $topRow)->getFont()->getColor()->setRGB($profitColor);
            $this->sheet->getStyle('K' . $topRow)->getFont()->setBold(true);

            $this->sheet->getRowDimension($topRow)->setRowHeight(20);
            $topRow++;
        }

        // ==================== 第4部分：总计数据行 ====================
        $this->sheet->setCellValue('A' . $topRow, admin_trans('shift_handover.all_devices_summary') . ' (' . $this->totalDevices . admin_trans('shift_handover.devices_unit') . ')');
        $this->sheet->setCellValue('B' . $topRow, '-');
        $this->sheet->setCellValue('C' . $topRow, number_format($this->grandTotal['machine_point'], 0));
        $this->sheet->setCellValue('D' . $topRow, number_format($this->grandTotal['recharge_amount'], 2));
        $this->sheet->setCellValue('E' . $topRow, number_format($this->grandTotal['withdrawal_amount'], 2));
        $this->sheet->setCellValue('F' . $topRow, number_format($this->grandTotal['modified_add_amount'], 2));
        $this->sheet->setCellValue('G' . $topRow, number_format($this->grandTotal['modified_deduct_amount'], 2));
        $this->sheet->setCellValue('H' . $topRow, number_format($this->grandTotal['lottery_amount'], 2));
        $this->sheet->setCellValue('I' . $topRow, number_format($this->grandTotal['total_in'], 2));
        $this->sheet->setCellValue('J' . $topRow, number_format($this->grandTotal['total_out'], 2));
        $this->sheet->setCellValue('K' . $topRow, number_format($this->grandTotal['profit'], 2));

        $this->sheet->getStyle('A' . $topRow . ':K' . $topRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FF9900']]]
        ]);
        $this->sheet->getStyle('A' . $topRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->sheet->getStyle('B' . $topRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->sheet->getRowDimension($topRow)->setRowHeight(28);

        // 总计利润颜色
        $grandProfitColor = $this->grandTotal['profit'] >= 0 ? '3f8600' : 'cf1322';
        $this->sheet->getStyle('K' . $topRow)->getFont()->getColor()->setRGB($grandProfitColor);
        $topRow++;

        // 空行
        $topRow++;

        // ==================== 第5部分：说明文字 ====================
        $this->sheet->setCellValue('A' . $topRow, admin_trans('shift_handover.export_note'));
        $this->sheet->mergeCells('A' . $topRow . ':K' . $topRow);
        $this->sheet->getStyle('A' . $topRow)->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $this->sheet->getRowDimension($topRow)->setRowHeight(20);
        $topRow++;

        // 空行
        $topRow += 2;

        // ==================== 第6部分：分隔线 ====================
        $separator = str_repeat('═', 120);
        $this->sheet->setCellValue('A' . $topRow, $separator);
        $this->sheet->mergeCells('A' . $topRow . ':K' . $topRow);
        $this->sheet->getStyle('A' . $topRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '999999']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $this->sheet->getRowDimension($topRow)->setRowHeight(5);
        $topRow++;

        // 空行
        $topRow++;

        // ==================== 第7部分：明细标题 ====================
        $this->sheet->setCellValue('A' . $topRow, admin_trans('shift_handover.details_title'));
        $this->sheet->mergeCells('A' . $topRow . ':K' . $topRow);
        $this->sheet->getStyle('A' . $topRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '4472C4']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
        ]);
        $this->sheet->getRowDimension($topRow)->setRowHeight(30);
        $topRow++;

        // 空行（留给明细部分开始）
        $topRow++;
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

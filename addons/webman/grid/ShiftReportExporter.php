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

    // 店家管理员ID（用于查询所有设备）
    protected $storeAdminId = null;

    // 存储每个设备的累计数据 [player_name => [...]]
    protected $deviceTotals = [];

    /**
     * 固定导出的16个列定义
     */
    protected array $availableColumns = [
        'player_name' => 'shift_handover.device_name',
        'player_phone' => 'shift_handover.device_number',
        'open_score_amount' => 'shift_handover.open_score_amount',
        'channel_withdrawal_amount' => 'shift_handover.channel_withdrawal_amount',
        'incoming_ticket_amount' => 'shift_handover.incoming_ticket_amount',
        'ticket_redeem_amount' => 'shift_handover.ticket_redeem_amount',
        'ticket_open_score_amount' => 'shift_handover.ticket_open_score_amount',
        'redeem_amount' => 'shift_handover.redeem_amount',
        'redeem_machine_amount' => 'shift_handover.redeem_machine_amount',
        'ticket_unredeemed_amount' => 'shift_handover.ticket_unredeemed_amount',
        'experience_coupon_amount' => 'shift_handover.experience_coupon_amount',
        'welfare_coupon_amount' => 'shift_handover.welfare_coupon_amount',
        'electronic_game_bet_amount' => 'shift_handover.electronic_game_bet_amount',
        'machine_bet_amount' => 'shift_handover.machine_bet_amount',
        'total_in' => 'shift_handover.total_in',
        'total_out' => 'shift_handover.total_out',
        'profit' => 'shift_handover.profit',
    ];

    // 用户选择的导出列
    protected $selectedColumns = [];

    /**
     * 设置要导出的列
     * @param array $columns 列名数组
     * @return $this
     */
    public function setSelectedColumns(array $columns): static
    {
        $this->selectedColumns = $columns;
        return $this;
    }

    /**
     * 获取所有可导出的列定义
     * @return array
     */
    public function getAvailableColumns(): array
    {
        $result = [];
        foreach ($this->availableColumns as $key => $translationKey) {
            $result[$key] = admin_trans($translationKey);
        }
        return $result;
    }

    /**
     * 获取当前选中的列（如果未选择则返回全部）
     * @return array
     */
    protected function getActiveColumns(): array
    {
        if (empty($this->selectedColumns)) {
            return array_keys($this->availableColumns);
        }
        return $this->selectedColumns;
    }

    public function columns(array $columns)
    {
        // 保存列配置，但不生成默认表头
        $this->columns = $columns;
        return $this;
    }

    /**
     * 根据列索引获取 Excel 列字母（A, B, ..., Z, AA, AB, ...）
     */
    protected function getColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26) - 1;
        }
        return $letter;
    }

    /**
     * 获取列数据（支持设备信息和数值列）
     */
    protected function getColumnValue(string $column, array $deviceInfo, ?object $detail, array &$subtotal): mixed
    {
        return match ($column) {
            'player_name' => $deviceInfo['player_name'],
            'player_phone' => $deviceInfo['player_phone'],
            'open_score_amount' => $detail ? ($detail->open_score_amount ?? 0) : 0,
            'ticket_open_score_amount' => $detail ? ($detail->ticket_open_score_amount ?? 0) : 0,
            'incoming_ticket_amount' => $detail ? ($detail->incoming_ticket_amount ?? 0) : 0,
            'redeem_amount' => $detail ? ($detail->redeem_amount ?? 0) : 0,
            'redeem_machine_amount' => $detail ? ($detail->redeem_machine_amount ?? 0) : 0,
            'channel_withdrawal_amount' => $detail ? ($detail->channel_withdrawal_amount ?? 0) : 0,
            'ticket_redeem_amount' => $detail ? ($detail->ticket_redeem_amount ?? 0) : 0,
            'ticket_unredeemed_amount' => $detail ? ($detail->ticket_unredeemed_amount ?? 0) : 0,
            'experience_coupon_amount' => $detail ? ($detail->experience_coupon_amount ?? 0) : 0,
            'welfare_coupon_amount' => $detail ? ($detail->welfare_coupon_amount ?? 0) : 0,
            'electronic_game_bet_amount' => $detail ? $detail->electronic_game_bet_amount : 0,
            'machine_bet_amount' => $detail ? $detail->machine_bet_amount : 0,
            'total_in' => $detail ? $detail->total_in : 0,
            'total_out' => $detail ? $detail->total_out : 0,
            'profit' => $detail ? $detail->profit : 0,
            default => 0,
        };
    }

    /**
     * 格式化列值用于显示
     */
    protected function formatColumnValue(string $column, mixed $value): string
    {
        return match ($column) {
            'player_name', 'player_phone' => (string) $value,
            'machine_point' => number_format($value, 0),
            default => number_format($value, 2),
        };
    }

    /**
     * 检查一行数据是否全部为0（排除文本列）
     */
    protected function isAllZero(array $values, array $activeColumns): bool
    {
        foreach ($activeColumns as $column) {
            // 跳过文本列
            if (in_array($column, ['player_name', 'player_phone'])) {
                continue;
            }
            $value = $values[$column] ?? 0;
            if (bccomp($value, '0', 2) !== 0) {
                return false;
            }
        }
        return true;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
            // 使用用户选择的列（如果未选择则使用全部列）
            $activeColumns = $this->getActiveColumns();
            $columnCount = count($activeColumns);
            $lastColumnLetter = $this->getColumnLetter($columnCount - 1);

            // 如果是第一次调用，初始化店家所有设备
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

                // 从第1行开始写明细
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
                $this->sheet->mergeCells('A' . $this->currentRow . ':' . $lastColumnLetter . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                ]);
                $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
                $this->currentRow++;

                // 柜台开票金额行
                $counterTicketLabel = admin_trans('shift_handover.counter_ticket_amount') . '：';
                $counterTicketValue = number_format($originalRecord->counter_ticket_amount ?? 0, 2);
                $this->sheet->setCellValue('A' . $this->currentRow, $counterTicketLabel);
                $this->sheet->setCellValue('B' . $this->currentRow, $counterTicketValue);
                $this->sheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
                $this->sheet->getStyle('B' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
                $this->currentRow++;

                // 获取设备明细
                $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $originalRecord->id)->get();

                // 转换为以 player_id 为 key 的数组，方便查找
                $deviceDetailsMap = [];
                foreach ($deviceDetails as $detail) {
                    $deviceDetailsMap[$detail->player_id] = $detail;
                }

                // 总是显示设备明细表（即使没有数据）
                {
                    // 设备明细表头（基于选中的列）
                    $headerRow = $this->currentRow;
                    foreach ($activeColumns as $index => $column) {
                        $headerLabel = admin_trans($this->availableColumns[$column] ?? $column);
                        $this->sheet->setCellValueByColumnAndRow($index + 1, $this->currentRow, $headerLabel);
                    }

                    $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastColumnLetter . $this->currentRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D0E8F2']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                    ]);
                    $this->sheet->getRowDimension($this->currentRow)->setRowHeight(22);
                    $this->currentRow++;

                    // 小计数据（只初始化选中的列）
                    $subtotal = [];
                    foreach ($activeColumns as $column) {
                        if (!in_array($column, ['player_name', 'player_phone'])) {
                            $subtotal[$column] = 0;
                        }
                    }

                    // 设备明细数据 - 遍历所有设备（即使某些设备在本次交班中没有数据）
                    // 使用与初始化相同的排序逻辑（按 player_id 升序）
                    $sortedDevices = $this->deviceTotals;
                    ksort($sortedDevices); // 按 key (player_id) 升序排序

                    $detailStartRow = $this->currentRow;
                    $index = 0;
                    foreach ($sortedDevices as $playerId => $deviceInfo) {
                        // 检查该设备在本次交班记录中是否有数据
                        $detail = $deviceDetailsMap[$playerId] ?? null;

                        // 获取所有列的值
                        $rowValues = [];
                        foreach ($activeColumns as $column) {
                            $rowValues[$column] = $this->getColumnValue($column, $deviceInfo, $detail, $subtotal);
                        }

                        // 过滤全部为0的明细行
                        if ($this->isAllZero($rowValues, $activeColumns)) {
                            continue;
                        }

                        // 写入每列数据
                        foreach ($activeColumns as $colIndex => $column) {
                            $value = $rowValues[$column];
                            $formattedValue = $this->formatColumnValue($column, $value);
                            $this->sheet->setCellValueByColumnAndRow($colIndex + 1, $this->currentRow, $formattedValue);
                        }

                        // 数字列右对齐（跳过前两列文本）
                        if ($columnCount > 2) {
                            $this->sheet->getStyle($this->getColumnLetter(2) . $this->currentRow . ':' . $lastColumnLetter . $this->currentRow)
                                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }

                        // 交替行背景色
                        $rowColor = $index % 2 == 0 ? 'FFFFFF' : 'F9F9F9';
                        $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastColumnLetter . $this->currentRow)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]]
                        ]);

                        // 利润颜色（如果利润列被选中）
                        if (in_array('profit', $activeColumns)) {
                            $profitColIndex = array_search('profit', $activeColumns);
                            $profitLetter = $this->getColumnLetter($profitColIndex);
                            $profit = $rowValues['profit'] ?? 0;
                            $profitColor = $profit >= 0 ? '3f8600' : 'cf1322';
                            $this->sheet->getStyle($profitLetter . $this->currentRow)->getFont()->getColor()->setRGB($profitColor);
                            $this->sheet->getStyle($profitLetter . $this->currentRow)->getFont()->setBold(true);
                        }

                        // 累加小计
                        foreach ($subtotal as $column => &$value) {
                            $value += $rowValues[$column] ?? 0;
                        }
                        unset($value);

                        $this->currentRow++;
                        $index++;
                    }

                    // 小计行
                    $subtotalLabel = admin_trans('shift_handover.subtotal') . ' (' . admin_trans('shift_handover.shift_id') . '#' . $originalRecord->id . ')';
                    $this->sheet->setCellValue('A' . $this->currentRow, $subtotalLabel);

                    // 写入小计数据
                    foreach ($activeColumns as $colIndex => $column) {
                        if (in_array($column, ['player_name', 'player_phone'])) {
                            $this->sheet->setCellValueByColumnAndRow($colIndex + 1, $this->currentRow, '');
                        } else {
                            $value = $subtotal[$column] ?? 0;
                            $formattedValue = $this->formatColumnValue($column, $value);
                            $this->sheet->setCellValueByColumnAndRow($colIndex + 1, $this->currentRow, $formattedValue);
                        }
                    }

                    $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastColumnLetter . $this->currentRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE599']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '999999']]]
                    ]);
                    $this->sheet->getStyle('A' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // 小计利润颜色
                    if (in_array('profit', $activeColumns)) {
                        $profitColIndex = array_search('profit', $activeColumns);
                        $profitLetter = $this->getColumnLetter($profitColIndex);
                        $subtotalProfit = $subtotal['profit'] ?? 0;
                        $subtotalProfitColor = $subtotalProfit >= 0 ? '3f8600' : 'cf1322';
                        $this->sheet->getStyle($profitLetter . $this->currentRow)->getFont()->getColor()->setRGB($subtotalProfitColor);
                    }

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
                // 设置列宽
                $this->setColumnWidths($activeColumns);

                // 冻结首行
                $this->sheet->freezePane('A1');

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
     * 初始化店家所有设备（分批加载，避免内存溢出）
     */
    protected function initializeStoreDevices()
    {
        if (!$this->storeAdminId) {
            return;
        }

        // 使用 chunk() 分批加载设备（每次200条）
        $playerModel = plugin()->webman->config('database.player_model');
        $playerModel::query()
            ->where('store_admin_id', $this->storeAdminId)
            ->select(['id', 'name', 'phone', 'uuid'])
            ->chunk(200, function ($devices) {
                // 初始化每个设备的累计数据结构（使用 player_id 作为唯一标识）
                foreach ($devices as $device) {
                    $deviceKey = $device->id; // 使用 player_id 作为唯一标识
                    $this->deviceTotals[$deviceKey] = [
                        'player_id' => $device->id,
                        'player_name' => $device->name,
                        'player_phone' => $device->phone ?? $device->uuid,
                    ];
                }

                // 显式释放
                $devices = null;
                unset($devices);
            });
    }

    /**
     * 设置列宽（基于选中的列）
     * @param array $activeColumns 当前激活的列
     */
    protected function setColumnWidths(array $activeColumns = [])
    {
        // 列宽度定义（固定17列）
        $columnWidths = [
            'player_name' => 12,
            'player_phone' => 15,
            'open_score_amount' => 12,
            'channel_withdrawal_amount' => 12,
            'incoming_ticket_amount' => 14,
            'ticket_redeem_amount' => 12,
            'ticket_open_score_amount' => 12,
            'redeem_amount' => 12,
            'redeem_machine_amount' => 12,
            'ticket_unredeemed_amount' => 12,
            'experience_coupon_amount' => 12,
            'welfare_coupon_amount' => 12,
            'electronic_game_bet_amount' => 14,
            'machine_bet_amount' => 14,
            'total_in' => 14,
            'total_out' => 14,
            'profit' => 16,
        ];

        // 如果没有指定列，使用全部列
        if (empty($activeColumns)) {
            $activeColumns = array_keys($columnWidths);
        }

        // 根据选中的列设置宽度
        foreach ($activeColumns as $index => $column) {
            $letter = $this->getColumnLetter($index);
            $width = $columnWidths[$column] ?? 14;
            $this->sheet->getColumnDimension($letter)->setWidth($width);
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

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

    public function columns(array $columns)
    {
        // 保存列配置，但不生成默认表头
        $this->columns = $columns;
        return $this;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
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
                $this->sheet->mergeCells('A' . $this->currentRow . ':T' . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4F8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                ]);
                $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
                $this->currentRow++;

                // 出票核销汇总行
                $ticketRecordTotal = $originalRecord->ticket_record_total_score ?? 0;
                $ticketRedeemBackendUsed = $originalRecord->ticket_redeem_backend_used_score ?? 0;
                $ticketSubtotal = bcsub($ticketRecordTotal, $ticketRedeemBackendUsed, 2);

                $ticketSummaryText = sprintf(
                    '%s: %s    %s: %s    %s: %s',
                    admin_trans('shift_handover.record.ticket_record_total_score'),
                    number_format($ticketRecordTotal, 2),
                    admin_trans('shift_handover.record.ticket_redeem_backend_used_score'),
                    number_format($ticketRedeemBackendUsed, 2),
                    admin_trans('shift_handover.record.ticket_subtotal'),
                    number_format(floatval($ticketSubtotal), 2)
                );
                $this->sheet->setCellValue('A' . $this->currentRow, $ticketSummaryText);
                $this->sheet->mergeCells('A' . $this->currentRow . ':T' . $this->currentRow);
                $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '333333']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                ]);
                $this->sheet->getRowDimension($this->currentRow)->setRowHeight(22);
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
                        admin_trans('shift_handover.activity_bonus_amount'),
                        admin_trans('shift_handover.lottery_ticket_reward_amount'),
                        admin_trans('shift_handover.record.birthday_bonus_amount'),
                        admin_trans('shift_handover.record.upgrade_bonus_amount'),
                        admin_trans('shift_handover.electronic_game_bet_amount'),
                        admin_trans('shift_handover.machine_bet_amount'),
                        admin_trans('shift_handover.total_in'),
                        admin_trans('shift_handover.total_out'),
                        admin_trans('shift_handover.profit')
                    ];
                    $headerRow = $this->currentRow;

                    foreach ($headers as $index => $header) {
                        $this->sheet->setCellValueByColumnAndRow($index + 1, $this->currentRow, $header);
                    }

                    $this->sheet->getStyle('A' . $this->currentRow . ':Q' . $this->currentRow)->applyFromArray([
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
                        'activity_bonus_amount' => 0,
                        'lottery_ticket_reward_amount' => 0,
                        'birthday_bonus_amount' => 0,
                        'upgrade_bonus_amount' => 0,
                        'electronic_game_bet_amount' => 0,
                        'machine_bet_amount' => 0,
                        'total_in' => 0,
                        'total_out' => 0,
                        'profit' => 0
                    ];

                    // 设备明细数据 - 遍历所有设备（即使某些设备在本次交班中没有数据）
                    // 使用与初始化相同的排序逻辑（按 player_id 升序）
                    $sortedDevices = $this->deviceTotals;
                    ksort($sortedDevices); // 按 key (player_id) 升序排序

                    $detailStartRow = $this->currentRow;
                    $index = 0;
                    foreach ($sortedDevices as $playerId => $deviceInfo) {
                        // 检查该设备在本次交班记录中是否有数据
                        $detail = $deviceDetailsMap[$playerId] ?? null;

                        // 如果有数据，显示真实数据；如果没有数据，显示0
                        $machinePoint = $detail ? $detail->machine_point : 0;
                        $rechargeAmount = $detail ? $detail->recharge_amount : 0;
                        $withdrawalAmount = $detail ? $detail->withdrawal_amount : 0;
                        $modifiedAddAmount = $detail ? $detail->modified_add_amount : 0;
                        $modifiedDeductAmount = $detail ? $detail->modified_deduct_amount : 0;
                        $lotteryAmount = $detail ? $detail->lottery_amount : 0;
                        $activityBonusAmount = $detail ? $detail->activity_bonus_amount : 0;
                        $lotteryTicketRewardAmount = $detail ? $detail->lottery_ticket_reward_amount : 0;
                        $birthdayBonusAmount = $detail ? ($detail->birthday_bonus_amount ?? 0) : 0;
                        $upgradeBonusAmount = $detail ? ($detail->upgrade_bonus_amount ?? 0) : 0;
                        $electronicGameBetAmount = $detail ? $detail->electronic_game_bet_amount : 0;
                        $machineBetAmount = $detail ? $detail->machine_bet_amount : 0;
                        $totalIn = $detail ? $detail->total_in : 0;
                        $totalOut = $detail ? $detail->total_out : 0;
                        $profit = $detail ? $detail->profit : 0;

                        // 过滤全部为0的明细行（使用 bccomp 精确比较小数）
                        $allZero = (bccomp($machinePoint, '0', 2) === 0
                            && bccomp($rechargeAmount, '0', 2) === 0
                            && bccomp($withdrawalAmount, '0', 2) === 0
                            && bccomp($modifiedAddAmount, '0', 2) === 0
                            && bccomp($modifiedDeductAmount, '0', 2) === 0
                            && bccomp($lotteryAmount, '0', 2) === 0
                            && bccomp($activityBonusAmount, '0', 2) === 0
                            && bccomp($lotteryTicketRewardAmount, '0', 2) === 0
                            && bccomp($birthdayBonusAmount, '0', 2) === 0
                            && bccomp($upgradeBonusAmount, '0', 2) === 0
                            && bccomp($electronicGameBetAmount, '0', 2) === 0
                            && bccomp($machineBetAmount, '0', 2) === 0
                            && bccomp($totalIn, '0', 2) === 0
                            && bccomp($totalOut, '0', 2) === 0
                            && bccomp($profit, '0', 2) === 0);
                        if ($allZero) {
                            continue;
                        }

                        $this->sheet->setCellValue('A' . $this->currentRow, $deviceInfo['player_name']);
                        $this->sheet->setCellValue('B' . $this->currentRow, $deviceInfo['player_phone']);
                        $this->sheet->setCellValue('C' . $this->currentRow, number_format($machinePoint, 0));
                        $this->sheet->setCellValue('D' . $this->currentRow, number_format($rechargeAmount, 2));
                        $this->sheet->setCellValue('E' . $this->currentRow, number_format($withdrawalAmount, 2));
                        $this->sheet->setCellValue('F' . $this->currentRow, number_format($modifiedAddAmount, 2));
                        $this->sheet->setCellValue('G' . $this->currentRow, number_format($modifiedDeductAmount, 2));
                        $this->sheet->setCellValue('H' . $this->currentRow, number_format($lotteryAmount, 2));
                        $this->sheet->setCellValue('I' . $this->currentRow, number_format($activityBonusAmount, 2));
                        $this->sheet->setCellValue('J' . $this->currentRow, number_format($lotteryTicketRewardAmount, 2));
                        $this->sheet->setCellValue('K' . $this->currentRow, number_format($birthdayBonusAmount, 2));
                        $this->sheet->setCellValue('L' . $this->currentRow, number_format($upgradeBonusAmount, 2));
                        $this->sheet->setCellValue('M' . $this->currentRow, number_format($electronicGameBetAmount, 2));
                        $this->sheet->setCellValue('N' . $this->currentRow, number_format($machineBetAmount, 2));
                        $this->sheet->setCellValue('O' . $this->currentRow, number_format($totalIn, 2));
                        $this->sheet->setCellValue('P' . $this->currentRow, number_format($totalOut, 2));
                        $this->sheet->setCellValue('Q' . $this->currentRow, number_format($profit, 2));

                        // 数字列右对齐
                        $this->sheet->getStyle('C' . $this->currentRow . ':Q' . $this->currentRow)
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                        // 交替行背景色
                        $rowColor = $index % 2 == 0 ? 'FFFFFF' : 'F9F9F9';
                        $this->sheet->getStyle('A' . $this->currentRow . ':Q' . $this->currentRow)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]]
                        ]);

                        // 利润颜色
                        $profitColor = $profit >= 0 ? '3f8600' : 'cf1322';
                        $this->sheet->getStyle('Q' . $this->currentRow)->getFont()->getColor()->setRGB($profitColor);
                        $this->sheet->getStyle('Q' . $this->currentRow)->getFont()->setBold(true);

                        // 累加小计
                        $subtotal['machine_point'] += $machinePoint;
                        $subtotal['recharge_amount'] += $rechargeAmount;
                        $subtotal['withdrawal_amount'] += $withdrawalAmount;
                        $subtotal['modified_add_amount'] += $modifiedAddAmount;
                        $subtotal['modified_deduct_amount'] += $modifiedDeductAmount;
                        $subtotal['lottery_amount'] += $lotteryAmount;
                        $subtotal['activity_bonus_amount'] += $activityBonusAmount;
                        $subtotal['lottery_ticket_reward_amount'] += $lotteryTicketRewardAmount;
                        $subtotal['birthday_bonus_amount'] += $birthdayBonusAmount;
                        $subtotal['upgrade_bonus_amount'] += $upgradeBonusAmount;
                        $subtotal['electronic_game_bet_amount'] += $electronicGameBetAmount;
                        $subtotal['machine_bet_amount'] += $machineBetAmount;
                        $subtotal['total_in'] += $totalIn;
                        $subtotal['total_out'] += $totalOut;
                        $subtotal['profit'] += $profit;

                        $this->currentRow++;
                        $index++;
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
                    $this->sheet->setCellValue('I' . $this->currentRow, number_format($subtotal['activity_bonus_amount'], 2));
                    $this->sheet->setCellValue('J' . $this->currentRow, number_format($subtotal['lottery_ticket_reward_amount'], 2));
                    $this->sheet->setCellValue('K' . $this->currentRow, number_format($subtotal['birthday_bonus_amount'], 2));
                    $this->sheet->setCellValue('L' . $this->currentRow, number_format($subtotal['upgrade_bonus_amount'], 2));
                    $this->sheet->setCellValue('M' . $this->currentRow, number_format($subtotal['electronic_game_bet_amount'], 2));
                    $this->sheet->setCellValue('N' . $this->currentRow, number_format($subtotal['machine_bet_amount'], 2));
                    $this->sheet->setCellValue('O' . $this->currentRow, number_format($subtotal['total_in'], 2));
                    $this->sheet->setCellValue('P' . $this->currentRow, number_format($subtotal['total_out'], 2));
                    $this->sheet->setCellValue('Q' . $this->currentRow, number_format($subtotal['profit'], 2));

                    $this->sheet->getStyle('A' . $this->currentRow . ':Q' . $this->currentRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE599']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '999999']]]
                    ]);
                    $this->sheet->getStyle('A' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // 小计利润颜色
                    $subtotalProfitColor = $subtotal['profit'] >= 0 ? '3f8600' : 'cf1322';
                    $this->sheet->getStyle('Q' . $this->currentRow)->getFont()->getColor()->setRGB($subtotalProfitColor);

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
                $this->setColumnWidths();

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
            'I' => 14,  // 活动奖励
            'J' => 14,  // 摸奖券奖励
            'K' => 14,  // 电子游戏打码量
            'L' => 14,  // 机器打码量
            'M' => 14,  // 总收入
            'N' => 14,  // 总支出
            'O' => 16,  // 利润
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

<?php

namespace addons\webman\grid;

use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\grid\grid\excel\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ShiftReportExporter extends Excel
{
    public function columns(array $columns)
    {
        // 保存列配置，但不生成默认表头
        $this->columns = $columns;
        return $this;
    }

    public function write(array $data, \Closure $finish = null)
    {
        foreach ($data as $record) {
            // 交班记录标题行
            $this->sheet->setCellValue('A' . $this->currentRow, '交班ID: ' . $record['id']);
            $this->sheet->mergeCells('A' . $this->currentRow . ':L' . $this->currentRow);
            $this->sheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->sheet->getStyle('A' . $this->currentRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8F4F8');
            $this->currentRow++;

            // 交班汇总信息行
            $this->sheet->setCellValue('A' . $this->currentRow, '交班时间');
            $this->sheet->setCellValue('B' . $this->currentRow, $record['start_time'] . ' ~ ' . $record['end_time']);
            $this->sheet->setCellValue('C' . $this->currentRow, '类型');
            $this->sheet->setCellValue('D' . $this->currentRow, $record['is_auto_shift'] == 1 ? '自动交班' : '手动交班');
            $this->sheet->setCellValue('E' . $this->currentRow, '投钞');
            $this->sheet->setCellValue('F' . $this->currentRow, $record['machine_point']);
            $this->sheet->setCellValue('G' . $this->currentRow, '收入');
            $this->sheet->setCellValue('H' . $this->currentRow, number_format($record['total_in'], 2));
            $this->sheet->setCellValue('I' . $this->currentRow, '支出');
            $this->sheet->setCellValue('J' . $this->currentRow, number_format($record['total_out'], 2));
            $this->sheet->setCellValue('K' . $this->currentRow, '利润');
            $this->sheet->setCellValue('L' . $this->currentRow, number_format($record['total_profit_amount'], 2));

            $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)->getFont()->setBold(true);
            $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F0F0F0');
            $this->currentRow++;

            // 获取设备明细
            $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $record['id'])
                ->orderBy('profit', 'desc')
                ->get();

            if ($deviceDetails->isNotEmpty()) {
                // 设备明细表头
                $headers = ['设备名称', '设备编号', '投钞点数', '开分', '洗分', '后台加点', '后台扣点', '彩金', '总收入', '总支出', '利润'];
                $col = 0;
                foreach ($headers as $header) {
                    $this->sheet->setCellValueByColumnAndRow($col + 1, $this->currentRow, $header);
                    $col++;
                }

                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->getFont()->setBold(true);
                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D0E8F2');
                $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $this->currentRow++;

                // 设备明细数据
                foreach ($deviceDetails as $detail) {
                    $this->sheet->setCellValue('A' . $this->currentRow, $detail->player_name);
                    $this->sheet->setCellValue('B' . $this->currentRow, $detail->player_phone);
                    $this->sheet->setCellValue('C' . $this->currentRow, $detail->machine_point);
                    $this->sheet->setCellValue('D' . $this->currentRow, number_format($detail->recharge_amount, 2));
                    $this->sheet->setCellValue('E' . $this->currentRow, number_format($detail->withdrawal_amount, 2));
                    $this->sheet->setCellValue('F' . $this->currentRow, number_format($detail->modified_add_amount, 2));
                    $this->sheet->setCellValue('G' . $this->currentRow, number_format($detail->modified_deduct_amount, 2));
                    $this->sheet->setCellValue('H' . $this->currentRow, number_format($detail->lottery_amount, 2));
                    $this->sheet->setCellValue('I' . $this->currentRow, number_format($detail->total_in, 2));
                    $this->sheet->setCellValue('J' . $this->currentRow, number_format($detail->total_out, 2));
                    $this->sheet->setCellValue('K' . $this->currentRow, number_format($detail->profit, 2));

                    // 利润颜色
                    if ($detail->profit >= 0) {
                        $this->sheet->getStyle('K' . $this->currentRow)->getFont()->getColor()->setRGB('3f8600');
                    } else {
                        $this->sheet->getStyle('K' . $this->currentRow)->getFont()->getColor()->setRGB('cf1322');
                    }
                    $this->currentRow++;
                }
            } else {
                $this->sheet->setCellValue('A' . $this->currentRow, '暂无设备数据');
                $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
                $this->currentRow++;
            }

            // 空行分隔
            $this->currentRow++;

            // 更新缓存进度
            $this->cache->set([
                'status' => 0,
                'progress' => $this->progress()
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);
        }

        // 设置列宽（只需要设置一次，放在判断完成后）
        if ($this->currentRow > $this->count) {
            foreach (range('A', 'L') as $col) {
                $this->sheet->getColumnDimension($col)->setWidth(15);
            }

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
    }
}

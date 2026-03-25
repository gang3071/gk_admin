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
        $currentRow = 1;

        foreach ($data as $record) {
            // 交班记录标题行
            $this->sheet->setCellValue('A' . $currentRow, '交班ID: ' . $record['id']);
            $this->sheet->mergeCells('A' . $currentRow . ':L' . $currentRow);
            $this->sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(12);
            $this->sheet->getStyle('A' . $currentRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8F4F8');
            $currentRow++;

            // 交班汇总信息行
            $this->sheet->setCellValue('A' . $currentRow, '交班时间');
            $this->sheet->setCellValue('B' . $currentRow, $record['start_time'] . ' ~ ' . $record['end_time']);
            $this->sheet->setCellValue('C' . $currentRow, '类型');
            $this->sheet->setCellValue('D' . $currentRow, $record['is_auto_shift'] == 1 ? '自动交班' : '手动交班');
            $this->sheet->setCellValue('E' . $currentRow, '投钞');
            $this->sheet->setCellValue('F' . $currentRow, $record['machine_point']);
            $this->sheet->setCellValue('G' . $currentRow, '收入');
            $this->sheet->setCellValue('H' . $currentRow, number_format($record['total_in'], 2));
            $this->sheet->setCellValue('I' . $currentRow, '支出');
            $this->sheet->setCellValue('J' . $currentRow, number_format($record['total_out'], 2));
            $this->sheet->setCellValue('K' . $currentRow, '利润');
            $this->sheet->setCellValue('L' . $currentRow, number_format($record['total_profit_amount'], 2));

            $this->sheet->getStyle('A' . $currentRow . ':L' . $currentRow)->getFont()->setBold(true);
            $this->sheet->getStyle('A' . $currentRow . ':L' . $currentRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F0F0F0');
            $currentRow++;

            // 获取设备明细
            $deviceDetails = StoreShiftDeviceDetail::where('shift_record_id', $record['id'])
                ->orderBy('profit', 'desc')
                ->get();

            if ($deviceDetails->isNotEmpty()) {
                // 设备明细表头
                $headers = ['设备名称', '设备编号', '投钞点数', '开分', '洗分', '后台加点', '后台扣点', '彩金', '总收入', '总支出', '利润'];
                $col = 0;
                foreach ($headers as $header) {
                    $this->sheet->setCellValueByColumnAndRow($col + 1, $currentRow, $header);
                    $col++;
                }

                $this->sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->getFont()->setBold(true);
                $this->sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D0E8F2');
                $this->sheet->getStyle('A' . $currentRow . ':K' . $currentRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // 设备明细数据
                foreach ($deviceDetails as $detail) {
                    $this->sheet->setCellValue('A' . $currentRow, $detail->player_name);
                    $this->sheet->setCellValue('B' . $currentRow, $detail->player_phone);
                    $this->sheet->setCellValue('C' . $currentRow, $detail->machine_point);
                    $this->sheet->setCellValue('D' . $currentRow, number_format($detail->recharge_amount, 2));
                    $this->sheet->setCellValue('E' . $currentRow, number_format($detail->withdrawal_amount, 2));
                    $this->sheet->setCellValue('F' . $currentRow, number_format($detail->modified_add_amount, 2));
                    $this->sheet->setCellValue('G' . $currentRow, number_format($detail->modified_deduct_amount, 2));
                    $this->sheet->setCellValue('H' . $currentRow, number_format($detail->lottery_amount, 2));
                    $this->sheet->setCellValue('I' . $currentRow, number_format($detail->total_in, 2));
                    $this->sheet->setCellValue('J' . $currentRow, number_format($detail->total_out, 2));
                    $this->sheet->setCellValue('K' . $currentRow, number_format($detail->profit, 2));

                    // 利润颜色
                    if ($detail->profit >= 0) {
                        $this->sheet->getStyle('K' . $currentRow)->getFont()->getColor()->setRGB('3f8600');
                    } else {
                        $this->sheet->getStyle('K' . $currentRow)->getFont()->getColor()->setRGB('cf1322');
                    }
                    $currentRow++;
                }
            } else {
                $this->sheet->setCellValue('A' . $currentRow, '暂无设备数据');
                $this->sheet->mergeCells('A' . $currentRow . ':K' . $currentRow);
                $currentRow++;
            }

            // 空行分隔
            $currentRow++;
        }

        // 设置列宽
        foreach (range('A', 'L') as $col) {
            $this->sheet->getColumnDimension($col)->setWidth(15);
        }

        // 更新当前行数
        $this->currentRow = $currentRow;

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
    }
}

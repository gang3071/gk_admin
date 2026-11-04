<?php

namespace addons\webman\grid;

use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 交班报表 PDF 导出
 * 与 ShiftReportExporter 共用取数/写表逻辑（继承），只替换渲染格式：
 * - A4 横向（明细列多，接受较小字号）
 * - 每班一页：交班标题行前强制分页，班次块不跨页（首块除外，避免空白首页）
 * - 中文字体见 MpdfCjkWriter
 * Excel 导出仍走 ShiftReportExporter，不受本类影响
 */
class ShiftReportPdfExporter extends ShiftReportExporter
{
    /**
     * 交班标题行写完后追加分页点：每班从新一页开始
     * 明细主表/机台报表/汇总表的班次块都经过本方法，统一生效
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $row
     * @param string $titleText
     * @param string $lastColumnLetter
     */
    protected function writeShiftTitleRow($sheet, int $row, string $titleText, string $lastColumnLetter): void
    {
        parent::writeShiftTitleRow($sheet, $row, $titleText, $lastColumnLetter);

        if ($row > 1) {
            // PhpSpreadsheet 的 Html/PDF writer 内部用 0 基行号匹配分页点（generateRow 注释明确 0-based），
            // setBreak 存的却是 1 基坐标，坐标写上一行分页才落在班次标题行之前
            $sheet->setBreak('A' . ($row - 1), Worksheet::BREAK_ROW);
        }
    }

    /**
     * 无可导出记录时写一行提示（PDF 仍生成，避免点导出按钮无任何反馈）
     * @param string $text
     */
    public function writeEmptyHint(string $text): void
    {
        $this->sheet->setCellValue('A1', $text);
    }

    /**
     * 保存为 PDF（覆写父类的 xlsx 落盘；PDF 无表保护概念，不锁表）
     * @param string $path 保存目录
     * @return string 生成的 PDF 文件路径
     */
    public function save(string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        foreach ($this->spreadsheet->getAllSheets() as $sheet) {
            $sheet->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A4);
            // 明细列多，用窄页边距换横向空间
            $sheet->getPageMargins()
                ->setLeft(0.25)->setRight(0.25)
                ->setTop(0.4)->setBottom(0.4);
        }

        $writer = new MpdfCjkWriter($this->spreadsheet);
        $writer->setFont(MpdfCjkWriter::FONT_KEY);
        $writer->setTempDir(sys_get_temp_dir());
        // 三张工作表都要进 PDF：不显式开启全表模式时 writer 只渲染第一张
        $writer->writeAllSheets();

        $filename = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->getFilename() . '.pdf';
        $writer->save($filename);

        return $filename;
    }
}

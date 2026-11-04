<?php

namespace addons\webman\grid;

use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

/**
 * 带中文字体的 mPDF writer
 * PDF 引擎默认不带中文字形，这里注入项目内置的 wqy-microhei（addons/webman/fonts），
 * 不依赖系统字体，服务端迁移时随项目走
 */
class MpdfCjkWriter extends Mpdf
{
    /**
     * 字体目录（项目内置字体文件）
     */
    public const FONT_DIR = __DIR__ . '/../fonts';

    /**
     * 字体 key，供 writer->setFont() 与 mPDF default_font 引用
     */
    public const FONT_KEY = 'wqy';

    /**
     * 注入字体配置后创建 mPDF 实例
     * @param array $config 基类传入的 mPDF 配置（含 tempDir）
     * @return \Mpdf\Mpdf
     */
    protected function createExternalWriterInstance($config)
    {
        $config['mode'] = 'utf-8';
        $config['fontDir'] = array_merge((array)($config['fontDir'] ?? []), [self::FONT_DIR]);
        // wqy-microhei.ttc 是 TrueType Collection，需指定集合内字面编号（1 = 微米黑）
        $config['fontdata'] = [
            self::FONT_KEY => [
                'R' => 'wqy-microhei.ttc',
                'B' => 'wqy-microhei.ttc',
                'TTCfontID' => ['R' => 1, 'B' => 1],
            ],
        ];
        $config['default_font'] = self::FONT_KEY;

        return new \Mpdf\Mpdf($config);
    }
}

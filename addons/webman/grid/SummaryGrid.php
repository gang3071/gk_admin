<?php

namespace addons\webman\grid;

use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Grid;

/**
 * 支持在列表末尾追加汇总行的 Grid
 *
 * 汇总行作为数据行走列的展示规则渲染（与列对齐），追加在当前页最后一行；
 * 不计入分页总数，导出不含汇总行
 */
class SummaryGrid extends Grid
{
    /**
     * 汇总行原始数据
     * @var array|null
     */
    private $summaryRow = null;

    /**
     * 汇总行内不套用列展示规则的字段（比例、备注等无合计含义的列）
     * @var array
     */
    private $summaryPlainColumns = [];

    /**
     * 设置汇总行
     * @param array $row 汇总行原始数据（数值列会经过列的展示规则渲染）
     * @param array $plainColumns 不套用展示规则、直接留白的字段
     * @return $this
     */
    public function summaryRow(array $row, array $plainColumns = [])
    {
        $this->summaryRow = $row;
        $this->summaryPlainColumns = $plainColumns;
        return $this;
    }

    /**
     * 解析行数据，在当前页末尾追加汇总行
     * @param array $data 当前页数据
     * @param bool $export 是否导出（导出不含汇总行）
     * @return array
     */
    public function parseColumn($data, $export = false)
    {
        $tableData = parent::parseColumn($data, $export);

        if ($export || is_null($this->summaryRow) || empty($data)) {
            return $tableData;
        }

        $summary = parent::parseColumn([$this->summaryRow], $export)[0];
        foreach ($this->summaryPlainColumns as $field) {
            $summary[$field] = Html::create($this->summaryRow[$field] ?? '')
                ->attr('class', 'ex_admin_table_td_' . $field);
        }
        $tableData[] = $summary;

        return $tableData;
    }
}

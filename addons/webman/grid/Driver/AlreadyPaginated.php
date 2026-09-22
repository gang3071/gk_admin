<?php

namespace addons\webman\grid\Driver;

use ExAdmin\ui\component\grid\grid\driver\Arrays;
use ExAdmin\ui\component\grid\grid\Grid;

/**
 * 已分页数据源
 *
 * 调用方自行 SQL 分页（LIMIT/OFFSET）后把「当前页」数组交给 Grid。
 * 覆盖 Arrays::data() 避免 array_slice 二次切片（否则第 2 页起会得到空数据），
 * 总条数通过构造参数写入 setTotal()，供分页器使用。
 */
class AlreadyPaginated extends Arrays
{
    /**
     * @param array $rows 当前页数据
     * @param int $total 筛选后的总条数
     */
    public function __construct(array $rows = [], int $total = 0)
    {
        $this->repository = $rows;
        if ($total > 0) {
            $this->setTotal($total);
        }
    }

    /**
     * GridManager 在传入 GridAbstract 实例时会把实例自身当作 repository 传进来，
     * 这里忽略它，保留构造时写入的当前页数据。
     */
    public function initialize(Grid $grid, $repository)
    {
        $this->grid = $grid;
        if (is_array($repository)) {
            $this->repository = $repository;
        }
    }

    /**
     * 数据已是当前页，直接返回，不再按 page/size 切片
     */
    public function data(int $page, int $size, bool $hidePage)
    {
        return $this->repository;
    }
}

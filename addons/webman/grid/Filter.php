<?php

namespace addons\webman\grid;

use Illuminate\Database\Eloquent\Builder;
use support\Db;


class Filter
{
    protected $builder;
    protected function getTableFields($builder,$table){
        $tableFields = Db::connection($builder->getModel()->getConnectionName())->select('SHOW FULL COLUMNS FROM '.$table);
        $fields = [];
        foreach ($tableFields as $tableField){
            $tableField = json_decode(json_encode($tableField),true);
            $tableField = array_change_key_case($tableField);
            $fields[] = $tableField['field'];
        }
        return $fields;
    }
    public function __construct(Builder $builder,$rule)
    {
        $tableField = $this->getTableFields($builder,$builder->getModel()->getTable());
        $builder->where(function ($query) use($rule,$tableField){
            $this->builder = $query;
            foreach ($rule as $item) {
                if(is_numeric($item['value']) || !empty($item['value'])){
                    $fields = explode( '->',$item['field']);
                    $field = current($fields);
                    if($item['relation'] || in_array($field, $tableField)){
                        $this->parseFilter($item['type'],$item['relation'],$item['rule'], $item['field'], $item['value']);
                    }
                }
            }
        });
    }

    /**
     * 关联筛选

     * @param string $rule 规则类型
     * @param string $field 字段
     * @param mixed $value 筛选值
     * @param string $relation 关联方法
     */
    public function whereHas($relation, $rule, $field, $value)
    {
        $this->builder->whereHas($relation, function ($builder) use ($rule, $field, $value) {
            $this->parseFilter($rule,null, $field, $value, $builder);
        });
    }

    /**
     * 解析筛选
     * @param string $type 类型
     * @param string $relation 关联方法
     * @param string $rule 规则类型
     * @param string $field 字段
     * @param mixed $value 筛选值
     * @param Builder $builder
     */
    public function parseFilter($type,$relation,$rule, $field, $value, $builder = null)
    {
        if (is_null($builder)) {
            $builder = $this->builder;
        }
        if($relation){
            return $builder->whereHas($relation, function ($query) use ($type,$rule, $field, $value) {
                $this->parseFilter($type,null,$rule, $field, $value, $query);
            });
        }
        if($type == 'cascader'){
            return $builder->where(function ($query) use($rule,$value){
                foreach ($value as $row){
                    $query->orWhere(function ($q) use($rule,$row){
                        foreach ($row as $field=>$val){
                            $this->parseFilter('normal',null,$rule,$field,$val,$q);
                        }
                    });
                }
            });
        }
        if ($field == 'player_tag') {
            $rule = 'findIn';
        }
        switch ($rule) {
            case 'eq':
                $builder->where($field, $value);
                break;
            case 'neq':
                $builder->where($field, '!=', $value);
                break;
            case 'egt':
                $builder->where($field, '>=', $value);
                break;
            case 'elt':
                $builder->where($field, '<=', $value);
                break;
            case 'gt':
                $builder->where($field, '>', $value);
                break;
            case 'lt':
                $builder->where($field, '<', $value);
                break;
            case 'between':
                $builder->whereBetween($field, $value);
                break;
            case 'notBetween':
                $builder->whereNotBetween($field, $value);
                break;
            case 'like':
                $builder->where($field, 'LIKE', "%$value%");
                break;
            case 'json':
                list($field,$node) = explode('->',$field);
                $builder->whereRaw("JSON_EXTRACT({$field},'$.{$node}') = '{$value}'");
                break;
            case 'jsonLike':
                list($field,$node) = explode('->',$field);
                $builder->whereRaw("JSON_EXTRACT({$field},'$.{$node}') LIKE '%{$value}%'");
                break;
            case 'jsonArrLike':
                list($field,$node) = explode('->',$field);
                $builder->whereRaw("JSON_EXTRACT({$field},'$[*].{$node}') LIKE '%{$value}%'");
                break;
            case 'in':
                $builder->whereIn($field, $value);
                break;
            case 'notIn':
                $builder->whereNotIn($field, $value);
                break;
            case 'findIn':
                $builder->whereRaw("FIND_IN_SET('{$value}',{$field})");
                break;
        }
    }
}

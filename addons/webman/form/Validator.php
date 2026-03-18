<?php

namespace addons\webman\form;


use ExAdmin\ui\contract\ValidatorAbstract;
use ExAdmin\ui\response\Response;

class Validator extends ValidatorAbstract
{
    /**
     * 验证
     * @param array $data 表单数据
     * @param bool $edit true更新，false新增
     * @return mixed
     */
    function check(array $data, bool $edit)
    {
        $ruleArr = $edit ? $this->updateRule : $this->createRule;
        $rules = [];
        $messages = [];
        foreach ($ruleArr as $field => $row) {
            $rule = [];
            if($row instanceof \Closure){
                $row = call_user_func_array($row,[$data,$this->form]);
            }
            foreach ($row as $key => $item) {
                if (is_numeric($key)) {
                    $rule[] = $item;
                } else {
                    $rule[] = $key;
                    $index = strpos($key, ':');
                    if ($index !== false) {
                        $key = substr($key, 0, $index);
                    }
                    $messages["{$field}.{$key}"] = $item;
                }
            }
            $rules[$field] = $rule;
        }
        $validator = validator($data, $rules, $messages);
        if ($validator->fails()) {
            return Response::success($validator->errors()->getMessages(), '', 422);
        }
        if($this->form->getSteps()){

            if(!$this->form->isStepfinish()){
                return Response::success([], '', 201);
            }
        }
        return true;
    }
}

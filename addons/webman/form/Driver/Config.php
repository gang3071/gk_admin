<?php

namespace addons\webman\form\Driver;



use ExAdmin\ui\component\form\step\StepResult;
use ExAdmin\ui\contract\FormAbstract;
use ExAdmin\ui\response\Message;
use ExAdmin\ui\response\Response;


class Config extends FormAbstract
{


    /**
     * 数据保存
     * @param array $data
     * @param mixed $id
     * @return Message|Response
     */
    public function save(array $data, $id = null)
    {
        $result = $this->dispatchEvent('saving',[$this->form]);
        if ($result instanceof Message) {
            return $result;
        }
        foreach ($data as $field => $value) {
            admin_sysconf($field, $value);
        }

        $savedResult = $this->dispatchEvent('saved',[$this->form]);
        if ($savedResult instanceof Message) {
            return $savedResult;
        }
        if($this->form->isStepfinish()){
            $result = call_user_func($this->form->getSteps()->getFinish(),new StepResult($this->form,$data, $result, $id));
            return Response::success($result,'',202);
        }
        return message_success(admin_trans('form.save_success'));
    }

    /**
     * 返回唯一标识字段，一般数据库主键自增字段
     * @return string
     */
    public function getPk(): string
    {
        return 'id';
    }

    /**
     * 获取数据
     * @param string $field 字段
     * @return mixed
     */
    public function get(string $field = null)
    {
        return admin_sysconf($field);
    }

    /**
     * 编辑数据
     * @param mixed $id
     * @return mixed
     */
    public function edit($id)
    {
        // TODO: Implement edit() method.
    }


}

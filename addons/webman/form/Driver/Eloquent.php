<?php

namespace addons\webman\form\Driver;


use ExAdmin\ui\component\form\step\StepResult;
use ExAdmin\ui\contract\FormAbstract;
use ExAdmin\ui\response\Message;
use ExAdmin\ui\response\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use support\Db;


/**
 * @property Model $repository
 */
class Eloquent extends FormAbstract
{

    /**
     * 编辑数据
     * @param mixed $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->trashed()) {
            $this->data = $this->repository->withTrashed()->find($id);
        } else {
            $this->data = $this->repository->find($id);
        }
        return $this->data;
    }

    public function trashed(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->repository));
    }

    public function model()
    {
        return $this->repository;
    }

    /**
     * 数据保存
     * @param array $data
     * @return Message|Response
     */
    public function save(array $data, $id = null)
    {
        //验证数据
        $result = $this->form->validator()->check($data, !is_null($id));
        if ($result instanceof Response) {
            return $result;
        }
        $this->form->input($data);
        $result = $this->dispatchEvent('saving', [$this->form]);
        if ($result instanceof Message) {
            return $result;
        }
        $tableField = $this->getTableFields($this->repository->getTable());
        if (!is_null($id)) {
            $this->repository = $this->edit($id);
        }
        Db::connection($this->repository->getConnectionName())->beginTransaction();
        try {
            foreach ($this->form->input() as $field => $value) {
                if (in_array($field, $tableField)) {
                    $this->repository->setAttribute($field, $value);
                }
            }
            if (!in_array($this->repository::CREATED_AT, $tableField) || !in_array($this->repository::UPDATED_AT, $tableField)) {
                $this->repository->timestamps = false;
            }
            $result = $this->repository->save();
            foreach ($this->form->input() as $field => $value) {
                if (method_exists($this->repository, $field)) {
                    $relationMethod = $this->repository->$field();
                    if ($relationMethod instanceof BelongsToMany) {
                        $relationMethod->sync($value);
                    } elseif ($relationMethod instanceof HasOne || $relationMethod instanceof MorphOne || $relationMethod instanceof BelongsTo || $relationMethod instanceof MorphTo) {
                        $model = $this->repository->$field;
                        if (!$model) {
                            $model = $relationMethod->make();
                        }
                        $this->relationSave($model, $value);
                    } elseif ($relationMethod instanceof HasMany || $relationMethod instanceof MorphMany) {
                        $pk = $relationMethod->getModel()->getKeyName();

                        $realtionUpdateIds = array_column($value, $pk);
                        if (!empty($this->repository->$field)) {
                            $deleteIds = $this->repository->$field->pluck($pk)->toArray();
                            $deleteIds = array_diff($deleteIds, $realtionUpdateIds);
                            if (count($deleteIds) > 0) {
                                $this->repository->$field()->whereIn($pk, $deleteIds)->delete();
                            }
                        }
                        $foreignKey = $relationMethod->getForeignKeyName();
                        $parentKey = $relationMethod->getParentKey();

                        foreach ($value as $key => &$val) {
                            $model = $relationMethod->getModel()->newModelInstance();
                            if (!empty($val[$pk])) {
                                $model = $model->find($val[$pk]);
                            }
                            $val[$foreignKey] = $parentKey;

                            $this->relationSave($model, $val);
                        }
                    }
                }
            }
            Db::connection($this->repository->getConnectionName())->commit();
        } catch (\Exception $exception) {
            Db::connection($this->repository->getConnectionName())->rollBack();
            if (config('app.debug')) {
                throw $exception;
            }

        }
        $savedResult = $this->dispatchEvent('saved', [$this->form]);
        if ($savedResult instanceof Message) {
            return $savedResult;
        }
        if ($this->form->isStepfinish()) {
            $result = call_user_func($this->form->getSteps()->getFinish(), new StepResult($this->form, $data, $result, $id));
            return Response::success($result, '', 202);
        }
        if ($result) {
            return message_success(admin_trans('form.save_success'));
        }
        return message_error(admin_trans('form.save_fail'));
    }
    protected function getTableFields($table){
        $tableFields = Db::connection($this->repository->getConnectionName())->select('SHOW FULL COLUMNS FROM '.$table);
        $fields = [];
        foreach ($tableFields as $tableField){
            $tableField = json_decode(json_encode($tableField),true);
            $tableField = array_change_key_case($tableField);
            $fields[] = $tableField['field'];
        }
        return $fields;
    }
    protected function relationSave(Model $model, array $data)
    {
        $tableField = $this->getTableFields($model->getTable());

        foreach ($data as $field => $value) {
            if (in_array($field, $tableField)) {
                $model->$field = $value;
            }
        }
        if (!in_array($model::CREATED_AT, $tableField) || !in_array($model::UPDATED_AT, $tableField)) {
            $model->timestamps = false;
        }
        $model->save();
    }

    /**
     * 返回唯一标识字段，一般数据库主键自增字段
     * @return string
     */
    public function getPk(): string
    {
        return $this->repository->getKeyName();
    }

    /**
     * 获取数据
     * @param string $field 字段
     * @return mixed
     */
    public function get(string $field = null)
    {
        if (is_null($field)) {
            return $this->data->toArray();
        }
        $value = Arr::get($this->data, $field);
        if (method_exists($this->repository, $field)) {
            $relation = $this->repository->$field();
            if ($relation instanceof BelongsToMany) {
                if (empty($value)) {
                    return [];
                } else {
                    return $value->pluck($relation->getRelatedKeyName());
                }
            }
        }
        return $value;
    }


}

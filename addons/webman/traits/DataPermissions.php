<?php

namespace addons\webman\traits;

use addons\webman\Admin;
use addons\webman\model\AdminRole;
use addons\webman\model\AdminUser;
use support\Cache;
use support\Db;

/**
 * @method $this offDataAuth() 关闭数据权限
 */
trait DataPermissions
{
    //全部数据权限
    private $FULL_DATA_RIGHTS = 0;
    //自定义数据权限
    private $CUSTOM_DATA_PERMISSIONS = 1;
    //本部门及以下数据权限
    private $THIS_DEPARTMENT_AND_THE_FOLLOWING_DATA_PERMISSIONS = 2;
    //本部门数据权限
    private $DATA_PERMISSIONS_FOR_THIS_DEPARTMENT = 3;
    //本人数据权限
    private $PERSONAL_DATA_RIGHTS = 4;

    // 缓存时间（秒）
    private $CACHE_TTL = 3600; // 1小时

    /**
     * 关闭数据权限
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOffDataAuth($query)
    {
        return $query->withoutGlobalScope('dataAuth');
    }

    /**
     * 数据权限字段
     * @var array
     */
    public function initializeDataPermissions()
    {
        $adminId = Admin::id();
        if ($adminId && plugin()->webman->config('admin_auth_id') != $adminId && count($this->dataAuth) > 0) {

            static::addGlobalScope('dataAuth', function ($builder) {
                $adminId = Admin::id();
                if (request()->app != 'api' && $adminId && plugin()->webman->config('admin_auth_id') != $adminId && count($this->dataAuth) > 0) {
                    // 获取角色信息（带缓存）
                    $role = $this->getUserRole($adminId);

                    if (!$role) {
                        return;
                    }

                    $builder->where(function ($query) use ($role, $adminId) {
                        $table = $this->getTable();

                        switch ($role->data_type) {
                            case $this->CUSTOM_DATA_PERMISSIONS:
                                $this->applyCustomPermissions($query, $table, $role, $adminId);
                                break;
                            case $this->THIS_DEPARTMENT_AND_THE_FOLLOWING_DATA_PERMISSIONS:
                                $this->applyDepartmentAndBelowPermissions($query, $table, $adminId);
                                break;
                            case $this->DATA_PERMISSIONS_FOR_THIS_DEPARTMENT:
                                $this->applyDepartmentPermissions($query, $table, $adminId);
                                break;
                            case $this->PERSONAL_DATA_RIGHTS:
                                $this->applyPersonalPermissions($query, $table);
                                break;
                        }
                    });
                }
            });
        }
    }

    /**
     * 获取用户角色信息（带缓存）
     * @param int $adminId
     * @return object|null
     */
    private function getUserRole($adminId)
    {
        $cacheKey = 'data_perm:role_user:' . $adminId;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $role_user_table = plugin()->webman->config('database.role_user_table');
        $role_table = plugin()->webman->config('database.role_table');

        $role = DB::connection($this->getConnectionName())->table($role_table)
            ->selectRaw($role_table . '.id,data_type')
            ->where($role_user_table . '.user_id', $adminId)
            ->join($role_user_table, $role_user_table . '.role_id', '=', $role_table . '.id')
            ->orderBy('data_type')
            ->first();

        if ($role) {
            Cache::set($cacheKey, $role, $this->CACHE_TTL);
        }

        return $role;
    }

    /**
     * 应用自定义权限
     */
    private function applyCustomPermissions($query, $table, $role, $adminId)
    {
        $role_department_table = plugin()->webman->config('database.role_department_table');
        $user_table = plugin()->webman->config('database.user_table');

        $query->where(function ($q) use ($table, $user_table, $role_department_table, $role) {
            $this->eachDataAuth(function ($field, $adminField) use ($table, $q, $user_table, $role_department_table, $role) {
                $db = DB::connection($this->getConnectionName())->table($user_table)
                    ->selectRaw($user_table . '.' . $adminField)
                    ->whereNull($user_table . '.deleted_at')
                    ->join($role_department_table, $role_department_table . '.department_id', '=', $user_table . '.department_id')
                    ->where($role_department_table . '.role_id', $role->id);
                $q->whereRaw($table . '.' . $field . ' IN (' . $db->toSql() . ')', $db->getBindings());
            });
        })->orWhere(function ($q) use ($table) {
            $this->eachDataAuth(function ($field, $adminField) use ($table, $q) {
                $q->where($table . '.' . $field, Admin::user()->$adminField);
            });
        });
    }

    /**
     * 应用本部门及以下权限
     */
    private function applyDepartmentAndBelowPermissions($query, $table, $adminId)
    {
        $department_id = Admin::user()->department_id;
        $departmentIds = $this->getDepartmentAndBelowIds($adminId, $department_id);

        $this->eachDataAuth(function ($field) use ($table, $query, $departmentIds) {
            $query->whereIn($table . '.' . $field, $departmentIds);
        });
    }

    /**
     * 应用本部门权限
     */
    private function applyDepartmentPermissions($query, $table, $adminId)
    {
        $department_id = Admin::user()->department_id;
        $departmentIds = $this->getDepartmentIds($department_id);

        $this->eachDataAuth(function ($field) use ($table, $query, $departmentIds) {
            $query->whereIn($table . '.' . $field, $departmentIds);
        });
    }

    /**
     * 应用个人权限
     */
    private function applyPersonalPermissions($query, $table)
    {
        $this->eachDataAuth(function ($field, $adminField) use ($table, $query) {
            $query->where($table . '.' . $field, Admin::user()->$adminField);
        });
    }

    /**
     * 获取本部门及以下部门ID列表（带缓存）
     * @param int $adminId
     * @param int $department_id
     * @return array
     */
    private function getDepartmentAndBelowIds($adminId, $department_id)
    {
        $cacheKey = 'data_perm:dept_below:' . $adminId . ':' . $department_id;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 优化：使用 JOIN 替代 FIND_IN_SET，性能更好
        $data = AdminUser::query()
            ->whereNull('admin_users.deleted_at')
            ->select(['admin_users.department_id'])
            ->join('admin_department', 'admin_department.id', '=', 'admin_users.department_id')
            ->where(function($query) use ($department_id) {
                // 使用 LIKE 替代 FIND_IN_SET，配合索引性能更好
                $query->where('admin_department.path', 'like', '%,' . $department_id . ',%')
                    ->orWhere('admin_department.path', 'like', $department_id . ',%')
                    ->orWhere('admin_department.path', 'like', '%,' . $department_id)
                    ->orWhere('admin_department.id', $department_id);
            })
            ->pluck('department_id')
            ->toArray();

        Cache::set($cacheKey, $data, $this->CACHE_TTL);

        return $data;
    }

    /**
     * 获取本部门ID列表（带缓存）
     * @param int $department_id
     * @return array
     */
    private function getDepartmentIds($department_id)
    {
        $cacheKey = 'data_perm:dept:' . $department_id;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $data = AdminUser::query()
            ->whereNull('deleted_at')
            ->select(['admin_users.department_id'])
            ->where('department_id', $department_id)
            ->pluck('department_id')
            ->toArray();

        Cache::set($cacheKey, $data, $this->CACHE_TTL);

        return $data;
    }

    private function eachDataAuth(\Closure $closure)
    {
        foreach ($this->dataAuth as $key => $field) {
            if (is_numeric($key)) {
                $adminField = 'id';
            } else {
                $adminField = $key;
            }
            call_user_func_array($closure, [$field, $adminField]);
        }
    }
}

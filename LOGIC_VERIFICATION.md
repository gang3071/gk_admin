# 🔍 修复逻辑验证报告

## 当前修复方案分析

### 修复代码：

```php
// addons/webman/traits/DataPermissions.php

trait DataPermissions
{
    // 静态标记：记录哪些模型类已注册全局作用域
    private static $scopeRegistered = [];
    
    public function initializeDataPermissions()
    {
        // ✅ 检查当前模型类是否已注册
        $modelClass = static::class;
        if (isset(self::$scopeRegistered[$modelClass])) {
            return;  // 已注册，直接返回
        }
        
        $adminId = Admin::id();
        if ($adminId && plugin()->webman->config('admin_auth_id') != $adminId && count($this->dataAuth) > 0) {
            
            static::addGlobalScope('dataAuth', function ($builder) {
                $adminId = Admin::id();  // ← 关键：每次查询时重新获取用户ID
                if (request()->app != 'api' && $adminId && ...) {
                    // 应用数据权限过滤
                }
            });
            
            // ✅ 标记已注册
            self::$scopeRegistered[$modelClass] = true;
        }
    }
}
```

---

## ✅ 逻辑正确性分析

### 关键点1：闭包内部动态获取用户ID

**问题：** 如果作用域只注册一次，不同用户的数据权限会不会混乱？

**答案：** ❌ 不会！

**原因：**
```php
static::addGlobalScope('dataAuth', function ($builder) {
    $adminId = Admin::id();  // ← 每次查询时动态获取当前用户ID！
    // 不是在注册时固定，而是在查询时动态获取
});
```

**执行流程：**
```
注册阶段（只执行一次）：
└─ static::addGlobalScope('dataAuth', 闭包对象)

查询阶段（每次查询都执行）：
└─ Player::where(...)->get()
   └─ 执行全局作用域闭包
      └─ $adminId = Admin::id()  ← 动态获取当前请求的用户ID
      └─ 应用当前用户的数据权限过滤
```

**结论：** ✅ 数据权限仍然是动态的，不会失效

---

### 关键点2：各种场景下的执行路径

#### 场景1：Worker启动后第一次实例化（无用户登录）

```
时间线：
├─ Worker 启动
├─ 后台任务执行：new Player()
│  ├─ initializeDataPermissions() 被调用
│  ├─ 检查 $scopeRegistered[Player::class]：未设置
│  ├─ $adminId = Admin::id()：null
│  ├─ 不满足 if 条件，不注册作用域
│  └─ 不标记 $scopeRegistered ← 注意：不标记！
│
└─ 用户A请求：new Player()
   ├─ initializeDataPermissions() 被调用
   ├─ 检查 $scopeRegistered[Player::class]：仍未设置
   ├─ $adminId = Admin::id()：123
   ├─ 满足 if 条件，注册作用域 ✅
   └─ 标记 $scopeRegistered[Player::class] = true
```

**影响：** 
- ⚠️ 第一次实例化时会多一次条件检查
- ✅ 但不会导致内存泄漏（没有注册作用域）
- ✅ 用户登录后会正常注册

**严重性：** 低（可忽略）

---

#### 场景2：用户已登录

```
时间线：
├─ 用户A请求1：new Player()
│  ├─ $adminId = 123
│  ├─ 注册作用域 ✅
│  └─ 标记 $scopeRegistered[Player::class] = true
│
├─ 用户A请求2：new Player()
│  ├─ 检查 $scopeRegistered[Player::class]：已设置
│  └─ 直接返回 ✅ 不重复注册
│
└─ 用户B请求：new Player()
   ├─ 检查 $scopeRegistered[Player::class]：已设置
   ├─ 直接返回 ✅ 不重复注册
   └─ 查询时闭包内部获取 Admin::id() = 456 ✅ 使用用户B的权限
```

**结论：** ✅ 完美工作

---

#### 场景3：超级管理员

```
时间线：
├─ 超级管理员请求：new Player()
│  ├─ $adminId = 1
│  ├─ config('admin_auth_id') = 1
│  ├─ 不满足 if 条件（$adminId == config('admin_auth_id')）
│  ├─ 不注册作用域 ✅
│  └─ 不标记 $scopeRegistered
│
└─ 普通用户请求：new Player()
   ├─ $adminId = 123
   ├─ 检查 $scopeRegistered[Player::class]：未设置（超级管理员没标记）
   ├─ 满足 if 条件，注册作用域 ✅
   └─ 标记 $scopeRegistered[Player::class] = true
```

**问题：** ⚠️ 如果超级管理员先访问，会不会导致普通用户无权限过滤？

**答案：** ❌ 不会！

**原因：**
- 超级管理员不满足 if 条件，不会标记 `$scopeRegistered`
- 普通用户访问时，检查 `$scopeRegistered` 仍未设置
- 普通用户会正常注册作用域

**结论：** ✅ 逻辑正确

---

### 关键点3：Eloquent 全局作用域机制

**问题：** 多次调用 `addGlobalScope('dataAuth', ...)` 会发生什么？

**实际行为：**

根据 Laravel Eloquent 源码：
```php
// Illuminate\Database\Eloquent\Model
protected static $globalScopes = [];

public static function addGlobalScope($identifier, $scope)
{
    static::$globalScopes[static::class][$identifier] = $scope;
    // ← 使用相同的 $identifier 会覆盖，不会累积！
}
```

**重要发现：** 即使不做任何修复，使用相同的标识符 `'dataAuth'` 也只会覆盖，不会累积多个作用域！

**那为什么还会内存泄漏？**

让我重新检查...实际上可能不是作用域对象本身的累积，而是：

1. **闭包捕获的变量累积**（如果闭包外部有变量）
2. **每次调用 `addGlobalScope()` 的开销**
3. **或者是我之前的假设错误**

让我重新检查原始代码...

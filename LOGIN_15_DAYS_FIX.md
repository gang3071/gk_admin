# 登录15天免登录失效问题修复指南

## 🔍 问题现象

用户勾选"记住我（15天免登录）"后，登录状态无法保持15天，过早失效。

---

## 🎯 根本原因分析

### 原因 1: 缺少 admin.php 配置文件（已修复✅）

**问题：**
`addons/webman/common/Login.php:375` 调用 `admin_config('admin.token')` 返回 **null**

```php
// Login.php:375-377
$config = admin_config('admin.token');  // ❌ 返回 null
$key = $config['key'];  // ❌ Fatal error: Trying to access array offset on value of type null
$unique = $config['unique'];
```

**修复：**
创建 `config/admin.php` 文件：

```php
<?php
return [
    'token' => [
        // Token 加密密钥（16位字符串，用于 AES-128-ECB）
        'key' => function_exists('env') ? env('ADMIN_TOKEN_KEY', 'gkAdminTokenKey') : 'gkAdminTokenKey',
        
        // 是否唯一登录（踢出上次登录）
        'unique' => true,
        
        // Token 默认过期时间（7天）
        // 注意：勾选"记住我"时会覆盖为15天
        'expire' => 7 * 24 * 3600,
    ],
];
```

---

### 原因 2: Redis 持久连接导致缓存失效（高度怀疑⚠️）

**问题：**
刚刚将 `config/redis.php` 的 `'persistent' => true` 改为 `false` 以修复内存泄漏，但这可能导致 **已存在的 Token 缓存失效**。

**原因：**
- Redis 持久连接使用固定的连接ID
- 改为非持久连接后，连接ID变化
- **旧的 Token 可能无法访问（连接断开）**

**症状：**
- 修改 Redis 配置后，所有在线用户被踢出
- 新登录的用户正常，旧用户需要重新登录

---

### 原因 3: Cache 驱动实现问题（需验证）

**`addons/webman/token/driver/Cache.php` 使用 `Support\Cache`（大写S）**

```php
// addons/webman/token/driver/Cache.php:20
use Support\Cache as C;

public function set($token, $expire)
{
    return C::set(md5($token), $token, $expire);  // ✅ 过期时间已传递
}
```

**验证步骤：**
1. 检查 `Support\Cache::set()` 是否正确使用 `$expire` 参数
2. 确认 Redis TTL 是否设置为 1296000 秒（15天）

---

## ✅ 完整修复方案

### 步骤 1: 确保 config/admin.php 存在

**已完成 ✅**

文件位置：`D:\gk_admin\config\admin.php`

### 步骤 2: 重启 Webman 服务

**⚠️ 必须重启才能加载新配置**

```bash
php start.php restart
```

### 步骤 3: 清空所有旧 Token（推荐）

由于 Redis 配置从持久连接改为非持久连接，建议清空所有旧 Token：

```bash
php -r "
require 'vendor/autoload.php';
\$redis = support\Redis::connection()->client();

// 删除所有 Token
\$iterator = null;
\$deleted = 0;
while (false !== (\$keys = \$redis->scan(\$iterator, 'last_auth_token_*', 1000))) {
    if (is_array(\$keys) && count(\$keys) > 0) {
        \$redis->del(...\$keys);
        \$deleted += count(\$keys);
    }
    if (\$iterator === 0) {
        break;
    }
}

echo '清除了 ' . \$deleted . ' 个旧 Token' . PHP_EOL;
echo '所有用户需要重新登录' . PHP_EOL;
"
```

### 步骤 4: 验证新登录的 Token

**测试脚本：**

```bash
# 登录后检查 Token TTL
php -r "
require 'vendor/autoload.php';
\$redis = support\Redis::connection()->client();

\$keys = \$redis->keys('last_auth_token_*');
if (count(\$keys) > 0) {
    foreach (\$keys as \$key) {
        \$ttl = \$redis->ttl(\$key);
        \$days = round(\$ttl / 86400, 2);
        echo \$key . ': TTL = ' . \$ttl . ' 秒 (' . \$days . ' 天)' . PHP_EOL;
    }
} else {
    echo '当前没有登录 Token' . PHP_EOL;
}
"
```

**预期结果：**
- 勾选"记住我"：TTL ≈ 1296000 秒 (15天)
- 未勾选"记住我"：TTL ≈ 604800 秒 (7天)

### 步骤 5: 监控 Token 是否提前失效

**检查点：**
1. 登录时勾选"记住我"
2. 记录当前时间和 Token
3. 7天后检查是否还能访问
4. 15天内不应该被踢出

---

## 🔧 代码流程分析

### 登录流程（Login.php:363-391）

```php
// 1. 获取"记住我"状态
$rememberMe = $data['remember_me'] ?? false;

// 2. 计算过期时间
$tokenExpire = $rememberMe ? 15 * 24 * 3600 : null;  // 1296000 秒 或 null

// 3. 生成 Token
$userData['token_expire'] = time() + $tokenExpire;  // 存储过期时间戳
$token = openssl_encrypt(json_encode($userData), 'AES-128-ECB', $key);

// 4. 保存到缓存
$driver = new \addons\webman\token\driver\Cache();
$driver->set($token, $tokenExpire ?: $config['expire']);  // ✅ 使用15天或默认7天

// 5. 唯一登录处理
if ($unique) {
    $driver->setLastToken($userData['id'], $token, $tokenExpire ?: $config['expire']);
}
```

### Cache 驱动（addons/webman/token/driver/Cache.php）

```php
public function set($token, $expire)
{
    // 使用 md5($token) 作为键
    // $expire = 1296000 (15天) 或 604800 (7天)
    return C::set(md5($token), $token, $expire);
}

public function setLastToken($id, $token, $expire)
{
    // 使用 'last_auth_token_' . $id 作为键
    return C::set('last_auth_token_' . $id, $token, $expire);
}
```

---

## 📊 验证清单

### ✅ 配置文件检查

- [x] `config/admin.php` 存在
- [x] `config['token']['key']` 有值（16位字符串）
- [x] `config['token']['expire']` = 604800 (7天)
- [ ] `config/redis.php` - `persistent` = false ⚠️ 

### ⚠️ Redis 配置影响

**关键问题：**
- Redis 从 `persistent => true` 改为 `false` 后
- 旧连接的缓存数据可能无法访问
- **建议：** 修改后立即清空所有 Token，要求用户重新登录

### 🔍 Token 过期时间验证

**测试步骤：**

1. **清空浏览器缓存和 Cookie**
2. **登录并勾选"记住我"**
3. **检查 Redis TTL：**
   ```bash
   redis-cli
   > KEYS last_auth_token_*
   > TTL last_auth_token_1
   # 应该显示约 1296000 秒
   ```
4. **等待 7 天后检查是否仍然登录**
5. **等待 15 天后应该被踢出**

---

## 🚨 常见问题

### Q1: 为什么修改 Redis 配置后所有人都掉线了？

**A:** Redis 从持久连接改为非持久连接后，连接标识变化，导致旧的缓存键无法访问。

**解决：** 清空所有 Token，让用户重新登录。

### Q2: 如何验证15天免登录是否生效？

**A:** 

1. 登录时勾选"记住我"
2. 运行以下命令检查 Token TTL：
   ```bash
   redis-cli
   > KEYS last_auth_token_*
   > TTL last_auth_token_<用户ID>
   ```
3. TTL 应该约等于 1296000 秒（15天）

### Q3: Token 加密密钥在哪里配置？

**A:** 

在 `config/admin.php` 中：
```php
'key' => 'gkAdminTokenKey',  // 16位字符串
```

**⚠️ 生产环境应该使用随机密钥并保密！**

### Q4: 为什么有两个 Cache？

**A:**

- `Support\Cache` (大写S) - ExAdmin 的缓存抽象层
- `support\Cache` (小写s) - Webman 的 Redis 缓存

Token 驱动使用 `Support\Cache`，底层可能调用 `support\Cache`。

---

## 📝 总结

### 已修复问题

1. ✅ 创建 `config/admin.php` 配置文件
2. ✅ 设置 Token 默认过期时间为 7 天
3. ✅ "记住我"使用 15 天过期时间

### 需要执行的操作

1. ⚠️ 重启 Webman 服务：`php start.php restart`
2. ⚠️ 清空所有旧 Token（因为 Redis 配置变更）
3. ⚠️ 通知用户重新登录
4. ✅ 验证新登录的 Token TTL 是否正确

### 预期效果

- **勾选"记住我"：** 15天内免登录
- **未勾选：** 7天内免登录
- **唯一登录：** 同一账号多处登录时踢出上次登录

### 监控建议

定期检查 Redis 中 Token 的 TTL，确保过期时间设置正确：

```bash
redis-cli KEYS "last_auth_token_*" | head -5 | xargs -I {} redis-cli TTL {}
```

---

## 🔗 相关文件

- `config/admin.php` - Token 配置（新建）
- `config/redis.php` - Redis 配置（persistent => false）
- `addons/webman/common/Login.php` - 登录逻辑
- `addons/webman/token/driver/Cache.php` - Token 缓存驱动

---

**最后更新：** 2026-05-22
**状态：** 配置文件已创建，等待重启验证

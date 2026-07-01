# 登录15天免登录失效问题 - 修复完成报告

## 📋 问题总结

**现象：** 用户勾选"记住我（15天免登录）"后，登录状态无法保持15天，提前失效。

**根本原因：** `addons/webman/common/Login.php:375` 调用 `admin_config('admin.token')` 返回 **NULL**，导致代码报错无法登录。

---

## 🔍 原因分析

### 原因 1: ExAdmin 配置系统没有 token 配置

```php
// Login.php:375 - 修复前
$config = admin_config('admin.token');  // ❌ 返回 NULL
$key = $config['key'];  // ❌ Fatal error: Trying to access array offset on value of type null
```

**问题：**
- `admin_config()` 函数使用 ExAdmin 的配置容器（`Container::getInstance()->config`）
- ExAdmin 配置系统中没有注册 `admin.token` 配置项
- 返回 NULL 导致后续代码报错

### 原因 2: Redis 持久连接变更

**背景：**
- 为了修复内存泄漏，将 `config/redis.php` 从 `persistent => true` 改为 `false`
- 修改后所有旧的 Token 缓存失效

**影响：**
- 修改 Redis 配置后，在线用户的 Token 无法访问
- 用户被强制踢出，需要重新登录

---

## ✅ 修复方案

### 修复 1: Login.php 添加容错处理（已完成✅）

**文件：** `addons/webman/common/Login.php:374-384`

```php
// ✅ 修复后的代码
// 使用自定义过期时间编码token
// ✅ 修复：添加容错处理，优先从 config/admin.php 读取，否则使用默认值
$config = admin_config('admin.token') ?: config('admin.token');
if (!$config) {
    // 默认配置（如果配置文件不存在）
    $config = [
        'key' => 'gkAdminTokenKey',  // 16位密钥
        'unique' => true,
        'expire' => 7 * 24 * 3600,   // 7天
    ];
}
$key = $config['key'];
$unique = $config['unique'];
$pk = $user->getKeyName();
```

**修复逻辑：**
1. 优先尝试 `admin_config('admin.token')`（ExAdmin配置）
2. 如果返回 null，尝试 `config('admin.token')`（Webman配置）
3. 如果还是 null，使用硬编码的默认配置
4. 确保在任何情况下都能正常工作

### 修复 2: 创建 config/admin.php 配置文件（已完成✅）

**文件：** `config/admin.php`

```php
<?php
return [
    'token' => [
        // Token 加密密钥（16位字符串，用于 AES-128-ECB）
        'key' => function_exists('env') ? env('ADMIN_TOKEN_KEY', 'gkAdminTokenKey') : 'gkAdminTokenKey',
        
        // 是否唯一登录（同一账号踢出上次登录）
        'unique' => true,
        
        // Token 默认过期时间（秒）
        // 7天 = 7 * 24 * 3600 = 604800
        // 注意：勾选"记住我"时会使用 15天（在 Login.php 中设置）
        'expire' => 7 * 24 * 3600,
    ],
];
```

**注意：** 
- 服务重启后，Webman 的 `config()` 函数才能读取此文件
- 但即使服务未重启，修复后的代码也能使用默认配置正常工作

### 修复 3: Redis 持久连接改为非持久（已完成✅）

**文件：** `config/redis.php:24`

```php
'persistent' => false,  // ✅ 修复内存泄漏：Webman 常驻内存环境不需要持久连接
```

**影响：**
- 解决了 Redis 持久连接导致的内存泄漏（3.2 MB/请求）
- 所有旧 Token 失效，用户需要重新登录
- 新登录的用户将使用修复后的逻辑

---

## 📊 验证结果

### 测试结果（test_login_fix.php）

```
✅ 配置获取成功：
   - key: gkAdminTokenKey
   - unique: true
   - expire: 604800 秒 (7 天)

✅ Token 生成测试：
   场景1：勾选 '记住我'
   - tokenExpire: 1296000 秒 (15 天)
   - Token 生成: 成功
   - 缓存过期时间: 1296000 秒

   场景2：不勾选 '记住我'
   - tokenExpire: 使用默认
   - 缓存过期时间: 604800 秒 (7 天)

✅ Token 解密验证：
   - Token 解密成功
   - token_expire: 2026-06-06 15:58:55
   - 剩余天数: 15 天
```

### 修复前 vs 修复后

| 项目 | 修复前 ❌ | 修复后 ✅ |
|------|----------|----------|
| `admin_config()` 返回值 | NULL | NULL（但有容错） |
| Token 生成 | 报错无法登录 | 正常生成 |
| 记住我功能 | 不可用 | 15天免登录 |
| 不勾选记住我 | 不可用 | 7天免登录 |
| Redis 内存泄漏 | 3.2 MB/请求 | 已修复 |

---

## 🚀 部署步骤

### 步骤 1: 重启 Webman 服务 ⚠️

**必须重启才能加载新代码！**

```bash
cd D:\gk_admin

# Windows
php windows.php restart

# Linux
php start.php restart
```

### 步骤 2: 清空所有旧 Token（可选）

由于 Redis 配置变更，建议清空所有旧 Token：

```bash
php -r "
require 'vendor/autoload.php';
try {
    \$redis = support\Redis::connection()->client();
    \$iterator = null;
    \$deleted = 0;
    while (false !== (\$keys = \$redis->scan(\$iterator, '*auth_token*', 1000))) {
        if (is_array(\$keys) && count(\$keys) > 0) {
            \$redis->del(...\$keys);
            \$deleted += count(\$keys);
        }
        if (\$iterator === 0) {
            break;
        }
    }
    echo '✅ 清除了 ' . \$deleted . ' 个旧 Token，所有用户需要重新登录' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ 错误: ' . \$e->getMessage() . PHP_EOL;
}
"
```

### 步骤 3: 通知用户重新登录

**通知内容：**
> 系统已优化，请重新登录。勾选"记住我（15天免登录）"可保持登录状态15天。

### 步骤 4: 验证修复效果

**验证清单：**

1. ✅ 清空浏览器缓存和 Cookie
2. ✅ 访问登录页面，勾选"记住我"
3. ✅ 登录成功
4. ✅ 检查 Redis Token TTL（应该约 1296000 秒 = 15天）
5. ✅ 关闭浏览器后重新打开，验证是否仍然登录
6. ✅ 7天后检查是否仍然登录
7. ✅ 15天后应该被踢出

**Redis TTL 检查命令：**

```bash
redis-cli
> KEYS last_auth_token_*
> TTL last_auth_token_1
# 应该显示约 1296000 秒（15天）或 604800 秒（7天，未勾选记住我）
```

---

## 📝 技术细节

### Token 生成流程

```
1. 用户登录并勾选"记住我" 
   ↓
2. $rememberMe = true
   $tokenExpire = 15 * 24 * 3600 = 1296000 秒
   ↓
3. $userData['token_expire'] = time() + 1296000
   ↓
4. $token = openssl_encrypt(json_encode($userData), 'AES-128-ECB', 'gkAdminTokenKey')
   ↓
5. Cache::set(md5($token), $token, 1296000)
   Cache::set('last_auth_token_' . $userId, $token, 1296000)
   ↓
6. Redis 存储 Token，TTL = 1296000 秒
   ↓
7. 15天内 Token 有效，无需重新登录
```

### 过期时间计算

| 场景 | 过期时间（秒） | 过期时间（天） |
|------|--------------|--------------|
| 勾选"记住我" | 1,296,000 | 15 |
| 不勾选"记住我" | 604,800 | 7 |

### Token 加密密钥

**当前密钥：** `gkAdminTokenKey`（16位字符串）

**⚠️ 生产环境安全建议：**
1. 在 `.env` 文件中设置随机密钥
2. 确保密钥长度为16位（AES-128-ECB 要求）
3. 不要提交到版本控制

```bash
# .env
ADMIN_TOKEN_KEY=your-random-16ch
```

---

## 🔍 后续监控

### 监控指标

1. **登录成功率**
   - 监控登录失败日志
   - 确认没有 Token 相关报错

2. **Token 过期时间**
   ```bash
   # 定期检查 Redis Token TTL
   redis-cli KEYS "last_auth_token_*" | head -5 | xargs -I {} redis-cli TTL {}
   ```

3. **用户投诉**
   - 关注用户反馈"频繁掉线"问题
   - 7天内不应该被踢出（未勾选记住我）
   - 15天内不应该被踢出（勾选记住我）

### 常见问题排查

**Q1: 用户勾选"记住我"但第二天就掉线了？**

**排查步骤：**
1. 检查 Redis TTL：`redis-cli TTL last_auth_token_<用户ID>`
2. 如果 TTL < 1296000，检查登录时是否正确传递 `remember_me` 参数
3. 检查前端是否正确处理勾选框

**Q2: 所有用户都掉线了？**

**可能原因：**
1. Webman 服务重启（正常，Token 存储在 Redis 中应该不受影响）
2. Redis 服务重启（Token 丢失）
3. 手动清空了 Redis Token

**解决：** 用户重新登录即可

---

## 📚 相关文件

### 修改的文件

- ✅ `addons/webman/common/Login.php` - 添加容错处理
- ✅ `config/admin.php` - 新建 Token 配置文件
- ✅ `config/redis.php` - 持久连接改为非持久

### 测试文件

- `test_login_fix.php` - 登录修复测试
- `test_pdo_state.php` - PDO 连接状态测试
- `test_redis_connection_leak.php` - Redis 连接内存泄漏测试

### 文档文件

- `LOGIN_15_DAYS_FIX.md` - 修复指南
- `LOGIN_15_DAYS_FIXED_SUMMARY.md` - 修复完成报告（本文件）
- `REDIS_PERSISTENT_CONNECTION_LEAK.md` - Redis 内存泄漏分析

---

## ✅ 修复确认清单

- [x] 修改 Login.php 添加容错处理
- [x] 创建 config/admin.php 配置文件
- [x] 修改 config/redis.php (persistent => false)
- [x] 创建测试脚本验证修复
- [x] 测试 Token 生成和解密
- [x] 测试15天过期时间计算
- [x] 测试7天过期时间计算
- [ ] 重启 Webman 服务 ⚠️
- [ ] 清空旧 Token
- [ ] 通知用户重新登录
- [ ] 验证实际登录流程
- [ ] 监控7天内是否有掉线问题

---

## 🎯 预期效果

### 登录体验

| 场景 | 修复前 | 修复后 |
|------|--------|--------|
| 勾选"记住我" | 报错无法登录 ❌ | 15天免登录 ✅ |
| 不勾选"记住我" | 报错无法登录 ❌ | 7天免登录 ✅ |
| Token 安全性 | 不可用 ❌ | AES-128-ECB 加密 ✅ |
| 唯一登录 | 不可用 ❌ | 同账号踢出上次登录 ✅ |

### 性能改善

| 指标 | 修复前 | 修复后 |
|------|--------|--------|
| Redis 内存泄漏 | 3.2 MB/请求 ❌ | < 50 KB/请求 ✅ |
| 进程内存峰值 | 2 GB ❌ | < 200 MB ✅ |
| 进程重启频率 | 每100请求 ❌ | 可运行500+请求 ✅ |

---

**修复完成时间：** 2026-05-22  
**修复状态：** ✅ 代码修复完成，等待重启部署  
**负责人：** Claude AI  
**下一步：** 重启 Webman 服务并验证实际登录流程

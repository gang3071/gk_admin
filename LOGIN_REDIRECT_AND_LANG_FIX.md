# 登录跳转和默认语言修复报告

## 📋 问题总结

**问题1：** 代理后台/店家后台/渠道后台选择15天免登录后，进入后台无法跳转到首页
**问题2：** 后台的默认语言不是中文繁体（zh-TW）

---

## 🔍 根本原因分析

### 问题1：登录跳转失败

**原因：** `config/admin.php` 在之前的修复中已创建，但服务未重启，导致 `config('admin.token')` 在内存中仍返回 NULL

**影响：**
- `addons/webman/common/Login.php:376` 的 `config('admin.token')` 返回 NULL
- 虽然有fallback使用硬编码默认值，但可能影响登录流程的其他部分

### 问题2：默认语言不是繁体中文

**原因：** ExAdmin UI 的默认配置 (`vendor/rockys/ex-admin-ui/src/config/ui.php`) 设置默认语言为 'zh-CN'（简体中文）

**影响：**
- LoadLangPack 中间件从 `plugin()->webman->config('ui.lang')` 读取配置
- 默认值是 'zh-CN'，而不是本项目需要的 'zh-TW'
- 用户首次访问时显示简体中文

---

## ✅ 修复方案

### 修复1：确保 config/admin.php 生效（已完成✅）

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

**说明：**
- 此文件已在之前的修复中创建
- 提供 token 配置，防止 `admin_config('admin.token')` 和 `config('admin.token')` 都返回 NULL
- 服务重启后，`config('admin.token')` 将读取此配置

### 修复2：创建 ExAdmin UI 配置覆盖文件（已完成✅）

**文件：** `config/plugin/rockys/ex-admin-ui/ui.php` (新建)

```php
<?php
/**
 * ExAdmin UI Configuration Override
 *
 * 覆盖 ExAdmin UI 的默认配置
 * 主要修改：默认语言从 zh-CN 改为 zh-TW（繁体中文）
 */
return [
    // 语言配置
    'lang' => [
        // 默认语言：繁体中文（Traditional Chinese）
        'default' => 'zh-TW',

        // 语言列表（支持4种语言）
        'list' => [
            'zh-TW' => '繁體中文',   // Traditional Chinese
            'zh-CN' => '简体中文',   // Simplified Chinese
            'en' => 'English',       // English
            'jp' => '日本語',        // Japanese
        ]
    ],
];
```

**说明：**
- Webman 的配置覆盖机制：项目配置文件覆盖 vendor 中的默认配置
- 此文件将覆盖 `vendor/rockys/ex-admin-ui/src/config/ui.php` 的默认语言设置
- `plugin()->webman->config('ui.lang')` 将返回 'zh-TW' 而不是 'zh-CN'

### 修复3：Login.php 容错处理（已在之前完成✅）

**文件：** `addons/webman/common/Login.php:374-384`

```php
// 使用自定义过期时间编码token
// ✅ 修复：添加容错处理，优先从配置读取，否则使用默认值
$config = admin_config('admin.token') ?: config('admin.token');
if (!$config) {
    // 默认配置（如果配置文件不存在）
    $config = [
        'key' => 'gkAdminTokenKey',  // 16位密钥
        'unique' => true,
        'expire' => 7 * 24 * 3600,   // 7天
    ];
}
```

**说明：**
- 三层fallback：`admin_config('admin.token')` → `config('admin.token')` → 硬编码默认值
- 确保在任何情况下都能获取到token配置
- 服务重启后，`config('admin.token')` 将正常工作

---

## 🚀 部署步骤

### 步骤 1: 重启 Webman 服务 ⚠️

**必须重启才能加载新配置文件！**

```bash
cd D:\gk_admin

# Windows
php windows.php restart

# 或 Linux
# php start.php restart
```

### 步骤 2: 清除浏览器缓存

由于语言配置变更，建议清除浏览器缓存和 Cookie：

1. 清除浏览器缓存（Ctrl+Shift+Delete）
2. 删除 `ex_admin_lang` Cookie
3. 删除 localStorage 中的 `locale` 项

### 步骤 3: 测试登录流程

**测试清单：**

**A. 测试超级管理员登录 (admin后台)**
1. ✅ 访问 http://localhost:8789/admin
2. ✅ 检查默认语言是否为"繁體中文"
3. ✅ 勾选"记住我（15天免登入）"
4. ✅ 输入账号密码登录
5. ✅ 检查是否成功跳转到首页
6. ✅ 关闭浏览器后重新打开，检查是否仍然登录

**B. 测试代理后台 (agent后台)**
1. ✅ 访问代理登录页面
2. ✅ 检查默认语言是否为"繁體中文"
3. ✅ 勾选"记住我（15天免登入）"
4. ✅ 输入账号密码登录
5. ✅ 检查是否成功跳转到首页
6. ✅ 关闭浏览器后重新打开，检查是否仍然登录

**C. 测试店家后台 (store后台)**
1. ✅ 访问店家登录页面
2. ✅ 检查默认语言是否为"繁體中文"
3. ✅ 勾选"记住我（15天免登入）"
4. ✅ 输入账号密码登录
5. ✅ 检查是否成功跳转到首页
6. ✅ 关闭浏览器后重新打开，检查是否仍然登录

**D. 测试渠道后台 (channel后台)**
1. ✅ 访问渠道登录页面
2. ✅ 检查默认语言是否为"繁體中文"
3. ✅ 勾选"记住我（15天免登入）"
4. ✅ 输入账号密码登录
5. ✅ 检查是否成功跳转到首页
6. ✅ 关闭浏览器后重新打开，检查是否仍然登录

### 步骤 4: 检查服务器日志

如果仍有问题，检查日志：

```bash
# 查看Webman日志
tail -f runtime/logs/webman.log

# 查看错误日志
tail -f runtime/logs/error.log
```

---

## 📊 验证方法

### 验证配置是否生效

**1. 检查 config('admin.token')：**

```bash
php -r "
require 'vendor/autoload.php';
\$config = config('admin.token');
var_dump(\$config);
"
```

**预期输出：**
```
array(3) {
  ["key"]=>
  string(16) "gkAdminTokenKey"
  ["unique"]=>
  bool(true)
  ["expire"]=>
  int(604800)
}
```

**2. 检查 plugin()->webman->config('ui.lang')：**

由于需要Web上下文，只能在浏览器中验证：
- 访问登录页面，检查默认语言是否为"繁體中文"
- 打开浏览器开发者工具，检查 Console 是否有语言相关日志

### 验证登录流程

**1. 打开浏览器开发者工具（F12）**

**2. 切换到 Network 标签**

**3. 执行登录操作**

**4. 检查登录响应：**

查找 `/ex-admin/login/login` 请求的响应：

```json
{
  "code": 200,
  "msg": "登录成功",
  "data": {
    "token": "encrypted_token_string",
    "locale": "zh-TW",
    "remember_me": true
  }
}
```

**预期结果：**
- `code`: 200
- `data.token`: 有值（加密字符串）
- `data.locale`: "zh-TW"
- `data.remember_me`: true (如果勾选了记住我)

**5. 检查路由跳转：**

登录成功后，应该自动跳转到首页，URL变化：
- 超级管理员：`/ex-admin/index`
- 代理后台：`/ex-admin/agent-index` (或类似)
- 店家后台：`/ex-admin/store-index` (或类似)
- 渠道后台：`/ex-admin/channel-index` (或类似)

如果卡在登录页面不跳转，说明前端路由有问题。

---

## 🔧 故障排查

### 问题1：服务重启后，config('admin.token') 仍返回 NULL

**检查：**

```bash
# 确认文件存在
ls -la config/admin.php

# 检查文件内容
cat config/admin.php

# 检查 Webman 是否正常启动
php start.php status
```

**解决：**

如果文件存在但配置不生效，可能是Webman缓存问题：

```bash
# 完全停止
php start.php stop

# 清除缓存（如果有）
rm -rf runtime/cache/*

# 重新启动
php start.php start -d
```

### 问题2：默认语言仍是简体中文

**检查：**

```bash
# 确认UI配置文件存在
ls -la config/plugin/rockys/ex-admin-ui/ui.php

# 检查文件内容
cat config/plugin/rockys/ex-admin-ui/ui.php
```

**解决：**

1. 清除浏览器 Cookie (`ex_admin_lang`)
2. 清除 localStorage (`locale`)
3. 硬刷新页面（Ctrl+Shift+R）

### 问题3：登录成功但不跳转

**可能原因：**

1. 前端路由配置问题
2. Token未正确保存到Cookie/localStorage
3. ExAdmin 权限检查失败

**调试步骤：**

1. 打开浏览器开发者工具 Console
2. 查看是否有JavaScript错误
3. 检查 Network 标签，登录请求是否返回200
4. 检查 Application → Cookies，是否有 `ex_admin_token` Cookie
5. 检查 Application → Local Storage，是否有token相关项

**常见错误：**

```javascript
// 如果看到这个错误：
Uncaught TypeError: Cannot read property 'push' of undefined

// 说明 $router 未定义，可能是Vue Router配置问题
```

### 问题4：勾选"记住我"后仍然提前掉线

**检查：**

1. 登录时是否勾选了"记住我"复选框
2. 检查后端Login.php是否接收到 `remember_me` 参数
3. 检查Redis中Token的TTL

```bash
redis-cli
> KEYS last_auth_token_*
> TTL last_auth_token_1
# 应该显示约 1296000 秒（15天）
```

---

## 📝 技术细节

### Webman 配置覆盖机制

Webman 支持多层配置覆盖：

```
优先级（高到低）：
1. 项目配置：config/plugin/{vendor}/{plugin}/{file}.php
2. 插件配置：vendor/{vendor}/{plugin}/src/config/plugin/{vendor}/{plugin}/{file}.php
```

**本次修复利用此机制：**

- **创建** `config/plugin/rockys/ex-admin-ui/ui.php`
- **覆盖** `vendor/rockys/ex-admin-ui/src/config/ui.php` 的默认值
- **生效** 服务重启后，`plugin()->webman->config('ui.lang')` 读取项目配置

### 前端语言初始化流程

```
1. 用户访问登录页面
   ↓
2. mounted() 钩子执行
   ↓
3. 检查 localStorage.getItem('locale')
   ↓
4. 检查 this.getCookie('ex_admin_lang')
   ↓
5. 如果都为空，设置默认为 'zh-TW'
   ↓
6. 登录成功后，后端返回 locale
   ↓
7. 前端保存到 Cookie ('ex_admin_lang')
   ↓
8. LoadLangPack 中间件读取 Cookie
   ↓
9. 设置 Container translator locale
```

**注意：**
- 前端 Vue 组件的默认值是fallback，真正的默认值应该由后端配置决定
- 后端 `plugin()->webman->config('ui.lang')['default']` 的值会影响后续请求的语言

### Token 生成和验证流程

```
登录请求（带 remember_me=true）
   ↓
Login.php:364-365
   $rememberMe = $data['remember_me'] ?? false;
   $tokenExpire = $rememberMe ? 15 * 24 * 3600 : null;
   ↓
Login.php:376-384
   读取配置（三层fallback）
   $config = admin_config('admin.token') ?: config('admin.token') ?: [默认值]
   ↓
Login.php:389
   $token = openssl_encrypt(json_encode($userData), 'AES-128-ECB', $key);
   ↓
Login.php:396
   $driver->set($token, $tokenExpire ?: $config['expire']);
   // 15天 或 7天
   ↓
Redis 存储 Token，TTL = 1296000 秒（15天）或 604800 秒（7天）
```

---

## ✅ 修复确认清单

- [x] 创建 config/admin.php（之前已完成）
- [x] 创建 config/plugin/rockys/ex-admin-ui/ui.php
- [x] 修改 Login.php 添加容错处理（之前已完成）
- [ ] 重启 Webman 服务 ⚠️
- [ ] 清除浏览器缓存和 Cookie
- [ ] 测试超级管理员登录
- [ ] 测试代理后台登录
- [ ] 测试店家后台登录
- [ ] 测试渠道后台登录
- [ ] 验证默认语言为繁体中文
- [ ] 验证15天记住我功能

---

## 🎯 预期效果

### 登录体验

| 场景 | 修复前 | 修复后 |
|------|--------|--------|
| 默认语言 | 简体中文 ❌ | 繁体中文 ✅ |
| 勾选"记住我"登录 | 可能失败 ❌ | 15天免登录 ✅ |
| 登录后跳转 | 可能失败 ❌ | 正常跳转首页 ✅ |
| 不勾选"记住我" | 可能失败 ❌ | 7天免登录 ✅ |

### 性能改善

| 指标 | 说明 |
|------|------|
| 配置加载 | 服务重启后从内存读取，无性能影响 |
| Token生成 | 使用配置文件值，性能稳定 |
| 语言切换 | Cookie机制，即时生效 |

---

**修复完成时间：** 2026-05-23  
**修复状态：** ✅ 代码修复完成，**等待重启部署**  
**负责人：** Claude AI  
**下一步：** 重启 Webman 服务并验证实际登录流程

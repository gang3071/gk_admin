# 机台在线状态显示问题修复指南

## 问题描述

**现象：** 机台已经连接到 Gateway，但在后台 `/ex-admin/addons-webman-controller-MachineController/index` 页面显示"离线"

**涉及系统：**
- gk_admin (后台管理) - 端口 8789
- gk_work (机台服务) - 端口 8788

---

## 原因分析

### 机台在线状态检查流程

```
用户访问机台列表
    ↓
MachineController::index() → slotList() → getList()
    ↓
第201-215行：检查每台机台在线状态
    ↓
checkMachineOnlineViaApi($machine)
    ↓
MachineApiService::checkOnline($machineId)
    ↓
HTTP POST → http://GK_WORK_API_URL/api/admin/machine/check-online
    ↓
gk_work 返回 {"code": 200, "data": {"online": true/false}}
```

### 可能的原因

1. ✅ **GK_WORK_API_URL 配置错误**
   - 当前配置：`GK_WORK_API_URL=http://127.0.0.1:8788`
   - 问题：在生产环境中，gk_work 可能在不同服务器或不同端口

2. ⚠️ **gk_work 服务未启动**
   - gk_work 服务没有运行
   - 或运行但端口不是 8788

3. ⚠️ **网络连接问题**
   - gk_admin 无法访问 gk_work 的 API
   - 防火墙阻止

4. ⚠️ **API 接口问题**
   - gk_work 的 `/api/admin/machine/check-online` 接口不存在或有错误

---

## 诊断步骤

### 步骤1：检查 gk_work 服务状态

```bash
# SSH 到生产服务器
ssh user@admin-test.5super9.com

# 检查 gk_work 是否运行
cd /www/wwwroot/gk_work  # 或实际的 gk_work 路径
php start.php status

# 预期输出：
# Webman[/www/wwwroot/gk_work/start.php] is running
# ...
# worker[...] listening on http://0.0.0.0:8788
```

**如果服务未运行：**
```bash
cd /www/wwwroot/gk_work
php start.php start -d
```

---

### 步骤2：检查 gk_work 的 IP 和端口

```bash
# 查看 gk_work 监听的端口
netstat -tlnp | grep :8788

# 或查看进程
ps aux | grep "gk_work"

# 检查 gk_work 的配置
cat /www/wwwroot/gk_work/config/server.php | grep listen
```

**确认 gk_work 监听的地址：**
- `http://0.0.0.0:8788` - 监听所有接口 ✅
- `http://127.0.0.1:8788` - 仅本地可访问（如果 gk_admin 在不同服务器则不可访问）❌

---

### 步骤3：测试 gk_work API 连接

**从 gk_admin 服务器测试：**

```bash
# SSH 到 gk_admin 服务器
cd /www/wwwroot/admin-test.5super9.com

# 测试连接（使用当前配置）
curl -X POST http://127.0.0.1:8788/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":1}' \
  -v

# 预期成功响应：
# {"code":200,"msg":"success","data":{"online":true}}

# 或测试其他接口
curl http://127.0.0.1:8788/api/admin/machine/gateway-info
```

**如果 gk_work 在不同服务器：**

```bash
# 假设 gk_work 在 10.140.0.10 服务器
curl -X POST http://10.140.0.10:8788/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":1}'
```

---

### 步骤4：检查 gk_admin 的配置

```bash
# 查看当前配置
cd /www/wwwroot/admin-test.5super9.com
grep GK_WORK_API_URL .env

# 输出：
# GK_WORK_API_URL=http://127.0.0.1:8788
```

---

## 修复方案

### 方案A：gk_admin 和 gk_work 在同一服务器

**确认配置：**
```bash
# .env 配置
GK_WORK_API_URL=http://127.0.0.1:8788
```

**确保 gk_work 监听 0.0.0.0 或 127.0.0.1：**
```bash
# 编辑 gk_work/config/server.php
'listen' => 'http://0.0.0.0:8788',  # 或 http://127.0.0.1:8788
```

---

### 方案B：gk_admin 和 gk_work 在不同服务器（推荐架构）

#### 生产环境拓扑示例

```
gk_admin:  10.140.0.11:8789 (admin-test.5super9.com)
gk_work:   10.140.0.10:8788 (内网专用)
```

**修改 gk_admin 的 .env：**

```bash
# SSH 到 gk_admin 服务器
cd /www/wwwroot/admin-test.5super9.com

# 编辑 .env
vim .env

# 修改为 gk_work 的实际地址
GK_WORK_API_URL=http://10.140.0.10:8788
```

**测试连接：**
```bash
curl -X POST http://10.140.0.10:8788/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":1}'
```

**重启 gk_admin：**
```bash
cd /www/wwwroot/admin-test.5super9.com
php start.php restart
```

---

### 方案C：使用域名（推荐生产环境）

如果 gk_work 有内网域名：

```bash
# .env 配置
GK_WORK_API_URL=http://gk-work-internal.5super9.com:8788
```

---

## 验证修复

### 1. 检查日志

```bash
# 查看 gk_admin 日志
tail -f /www/wwwroot/admin-test.5super9.com/runtime/logs/webman.log | grep -i "MachineApiService\|check.*online"

# 成功示例：
# [INFO] MachineApiService::checkOnline success: {"machine_id":1,"online":true}

# 失败示例：
# [ERROR] MachineApiService::checkOnline failed: Connection refused
```

### 2. 浏览器测试

访问机台列表页面：
```
https://admin-test.5super9.com/ex-admin/addons-webman-controller-MachineController/index
```

**预期结果：**
- 已连接的机台显示"在线"（绿色）
- 未连接的机台显示"离线"（灰色）

### 3. 测试单台机台

```bash
# 在生产服务器测试单台机台状态
curl -X POST http://GK_WORK_IP:8788/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":机台ID}'
```

---

## 性能优化（可选）

当前实现：每台机台单独调用 API 检查在线状态，列表加载较慢。

### 优化方案：批量检查

**1. 在 MachineController 中使用批量接口：**

```php
// 修改 getList() 方法（第201-224行）

// 获取当前页所有机台ID
$machineIds = $grid->model()->pluck('id')->toArray();

// 批量检查在线状态
$onlineStatus = [];
try {
    $result = MachineApiService::batchCheckOnline($machineIds);
    $onlineStatus = $result; // ['1' => true, '2' => false, ...]
} catch (\Exception $e) {
    \support\Log::warning('Batch check machine online failed', [
        'error' => $e->getMessage()
    ]);
}

// 显示状态时从缓存数组读取
$grid->column('now_status', admin_trans('machine.fields.now_status'))
    ->display(function ($val, Machine $data) use ($onlineStatus) {
        $machineStatus = $onlineStatus[$data->id] ?? false ? 'online' : 'offline';
        
        return admin_view(plugin()->webman->getPath() . '/views/machine_status.vue')->attrs([
            'id' => $data->id,
            'type' => Admin::user()->type == 1 ? 'admin' : 'channel',
            'department_id' => Admin::user()->department_id,
            'ws' => env('WS_URL', ''),
            'machine_status' => $machineStatus,
        ]);
    })
    ->align('center');
```

**2. gk_work 实现批量接口：**

确认 gk_work 已实现 `/api/admin/machine/batch-check-online` 接口（MachineApiService 第174-191行已定义）

---

## 常见问题

### Q1: gk_work 服务在哪里？

**A:** 根据项目架构文档：
- 开发环境：通常在 `D:\gk_work` 或 `localhost:8788`
- 生产环境：通常在独立服务器或同服务器不同目录

查找方法：
```bash
# 查找 gk_work 目录
find /www/wwwroot -name "gk_work" -type d 2>/dev/null
find /data -name "gk_work" -type d 2>/dev/null

# 或检查运行的进程
ps aux | grep "start.php" | grep -v "admin"
```

---

### Q2: 如何知道 gk_work 的 IP？

**A:** 
1. 查看 gk_admin 部署文档或运维文档
2. 询问运维人员
3. 检查网络拓扑：
   ```bash
   # 查看同网段的服务器
   ip addr show
   # 或
   ifconfig
   ```

---

### Q3: 修改 .env 后需要重启吗？

**A:** 是的，必须重启 Webman 服务：
```bash
cd /www/wwwroot/admin-test.5super9.com
php start.php restart
```

---

### Q4: API 调用超时怎么办？

**A:** 增加超时时间（MachineApiService.php 第67行）：
```php
return Http::baseUrl(self::$baseUrl)
    ->timeout(30)  // 改为 60 或更大
    ->withHeaders([...]);
```

---

### Q5: 机台连接了但还是显示离线？

**A:** 可能的原因：
1. **Gateway Worker 进程未启动** - 检查 gk_work 的 Gateway Worker
2. **Redis 连接状态未同步** - 清除 Redis 缓存
3. **API 返回数据格式错误** - 检查 gk_work 日志

```bash
# 检查 gk_work 的 Gateway Worker
cd /www/wwwroot/gk_work
php start.php status | grep -i gateway

# 清除 Redis 缓存
redis-cli
> KEYS machine_tcp_*
> DEL machine_tcp_action_cache_*
```

---

## 快速检查清单

部署到生产环境后，按顺序检查：

- [ ] gk_work 服务已启动 (`php start.php status`)
- [ ] gk_work 监听在正确的 IP:PORT
- [ ] gk_admin 能 ping 通 gk_work 服务器
- [ ] gk_admin 能 curl 到 gk_work API
- [ ] GK_WORK_API_URL 配置正确
- [ ] .env 修改后已重启 gk_admin
- [ ] 防火墙允许 8788 端口
- [ ] 机台列表页面正确显示在线状态

---

## 调试命令集合

```bash
# === 在 gk_admin 服务器执行 ===

# 1. 检查配置
grep GK_WORK_API_URL .env

# 2. 测试 API 连接
curl -X POST http://$(grep GK_WORK_API_URL .env | cut -d= -f2)/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":1}' -v

# 3. 查看日志
tail -50 runtime/logs/webman.log | grep -i "machine.*api\|check.*online"

# 4. 重启服务
php start.php restart

# === 在 gk_work 服务器执行 ===

# 1. 检查服务状态
php start.php status

# 2. 检查端口监听
netstat -tlnp | grep :8788

# 3. 查看日志
tail -50 runtime/logs/webman.log | grep -i "check.*online\|machine"

# 4. 测试本地 API
curl -X POST http://127.0.0.1:8788/api/admin/machine/check-online \
  -H "Content-Type: application/json" \
  -d '{"machine_id":1}'
```

---

**创建日期：** 2026-05-19  
**适用环境：** 生产环境（admin-test.5super9.com）  
**关键配置：** GK_WORK_API_URL  
**依赖服务：** gk_work (端口 8788)

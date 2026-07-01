# MachineApiService Facade 错误修复

**修复日期：** 2026-05-19  
**问题类型：** Laravel Facade 未初始化  
**严重性：** 🔴 高 - 阻止机台状态查询功能

---

## 问题描述

### 错误日志
```
[2026-05-19 16:32:36] default.ERROR: MachineApiService::getMachineStatus failed 
{"machine_id":1271,"error":"A facade root has not been set."} []

[2026-05-19 16:32:36] default.ERROR: MachineApiService::checkOnline failed 
{"machine_id":1274,"error":"A facade root has not been set."} []
```

### 根本原因
`MachineApiService` 使用了 Laravel 的 `Illuminate\Support\Facades\Http` Facade，但 Webman 框架没有完整初始化 Laravel 的服务容器，导致 Facade 无法工作。

### 影响范围
- 机台状态查询（`getMachineStatus`）
- 机台在线检查（`checkOnline`）
- 批量在线检查（`batchCheckOnline`）
- 发送机台指令（`sendCmd`）
- 获取操作描述（`getDescription`）
- Gateway 信息查询（`getGatewayInfo`）
- 在线统计（`getAllOnlineStatus`, `getOnlineStatistics`）

---

## 修复方案

### 解决方案：将 Laravel Http Facade 替换为 Guzzle

**原因：**
- Guzzle 已安装（`guzzlehttp/guzzle 7.10.0`）
- Guzzle 不依赖 Laravel 服务容器
- 与 Webman 框架完全兼容

---

## 修复详情

### 1. 修改命名空间导入

**修复前：**
```php
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
```

**修复后：**
```php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
```

---

### 2. 修改 createClient() 方法

**修复前（Laravel Http Facade）：**
```php
private static function createClient(int $adminId = 0): PendingRequest
{
    self::init();

    return Http::baseUrl(self::$baseUrl)
        ->timeout(30)
        ->withHeaders([
            'X-Admin-Id' => $adminId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
}
```

**修复后（Guzzle）：**
```php
private static function createClient(int $adminId = 0): Client
{
    self::init();

    return new Client([
        'base_uri' => self::$baseUrl,
        'timeout' => 30,
        'headers' => [
            'X-Admin-Id' => $adminId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]);
}
```

---

### 3. 修改 handleResponse() 方法

**修复前（Laravel Response）：**
```php
private static function handleResponse(Response $response, string $action): array
{
    if (!$response->successful()) {
        throw new Exception("{$action}失败: HTTP {$response->status()}");
    }

    $data = $response->json();

    if (!isset($data['code'])) {
        throw new Exception("{$action}失败: 响应格式错误");
    }

    if ($data['code'] != 200) {
        $msg = $data['msg'] ?? '未知错误';
        throw new Exception("{$action}失败: {$msg}");
    }

    return $data['data'] ?? [];
}
```

**修复后（Guzzle Response）：**
```php
private static function handleResponse($response, string $action): array
{
    $statusCode = $response->getStatusCode();

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception("{$action}失败: HTTP {$statusCode}");
    }

    $body = (string)$response->getBody();
    $data = json_decode($body, true);

    if (!isset($data['code'])) {
        throw new Exception("{$action}失败: 响应格式错误");
    }

    if ($data['code'] != 200) {
        $msg = $data['msg'] ?? '未知错误';
        throw new Exception("{$action}失败: {$msg}");
    }

    return $data['data'] ?? [];
}
```

---

### 4. 修改所有 API 方法调用

**修复前（Laravel Http）：**
```php
public static function getMachineStatus(int $machineId, string $lang = 'zh_CN'): array
{
    try {
        $response = self::createClient()
            ->post('/api/admin/machine/status', [
                'machine_id' => $machineId,
                'lang' => $lang,
            ]);

        return self::handleResponse($response, '获取机台状态');

    } catch (Exception $e) {
        Log::error('MachineApiService::getMachineStatus failed', [
            'machine_id' => $machineId,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

**修复后（Guzzle）：**
```php
public static function getMachineStatus(int $machineId, string $lang = 'zh_CN'): array
{
    try {
        $client = self::createClient();
        $response = $client->post('/api/admin/machine/status', [
            'json' => [
                'machine_id' => $machineId,
                'lang' => $lang,
            ]
        ]);

        return self::handleResponse($response, '获取机台状态');

    } catch (GuzzleException $e) {
        Log::error('MachineApiService::getMachineStatus failed', [
            'machine_id' => $machineId,
            'error' => $e->getMessage()
        ]);
        throw new Exception($e->getMessage(), $e->getCode(), $e);
    } catch (Exception $e) {
        Log::error('MachineApiService::getMachineStatus failed', [
            'machine_id' => $machineId,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
```

**关键变化：**
1. ✅ 显式创建 `$client` 变量
2. ✅ 使用 `'json' => [...]` 包裹请求数据（Guzzle 格式）
3. ✅ 捕获 `GuzzleException` 并转换为通用 `Exception`

---

## 修复的方法列表

已修复以下 9 个方法：

| 方法 | 请求类型 | 说明 |
|------|---------|------|
| `sendCmd()` | POST | 发送机台指令 |
| `getMachineStatus()` | POST | 获取机台状态 |
| `checkOnline()` | POST | 检查机台在线 |
| `batchCheckOnline()` | POST | 批量检查在线 |
| `getDescription()` | POST | 获取操作描述 |
| `getGatewayInfo()` | GET | 获取 Gateway 信息 |
| `getAllOnlineStatus()` | POST | 获取所有在线状态 |
| `getOnlineStatistics()` | GET | 获取在线统计 |
| `testConnection()` | - | 测试连接（调用 getGatewayInfo） |

---

## Laravel Http vs Guzzle 对比

### API 调用差异

| 操作 | Laravel Http | Guzzle |
|------|-------------|--------|
| POST 请求 | `->post($url, $data)` | `->post($url, ['json' => $data])` |
| GET 请求 | `->get($url)` | `->get($url)` |
| 获取状态码 | `$response->status()` | `$response->getStatusCode()` |
| 检查成功 | `$response->successful()` | `$statusCode >= 200 && $statusCode < 300` |
| 获取 JSON | `$response->json()` | `json_decode($response->getBody(), true)` |
| 异常 | `Exception` | `GuzzleException` |

---

## 验证修复

### 语法检查
```bash
✅ php -l D:/gk_admin/app/service/MachineApiService.php
No syntax errors detected
```

### 功能测试建议

1. **测试机台状态查询**
   ```php
   use app\service\MachineApiService;
   
   $result = MachineApiService::getMachineStatus(1271);
   var_dump($result);
   ```

2. **测试在线检查**
   ```php
   $result = MachineApiService::checkOnline(1274);
   var_dump($result);
   ```

3. **测试连接**
   ```php
   $isConnected = MachineApiService::testConnection();
   echo $isConnected ? '连接成功' : '连接失败';
   ```

---

## 部署注意事项

### 无需额外依赖
- ✅ Guzzle 已安装（`guzzlehttp/guzzle 7.10.0`）
- ✅ 无需修改 composer.json
- ✅ 无需运行 `composer install`

### 重启服务
```bash
# Windows
cd D:\gk_admin
php windows.php stop
php windows.php start

# Linux
cd /path/to/gk_admin
php start.php restart
```

### 监控日志
```bash
# 检查错误是否消失
tail -f runtime/logs/webman.log | grep "MachineApiService"

# 应该看到正常的请求日志，不再有 "A facade root has not been set" 错误
```

---

## 预期效果

修复后：
- ✅ 机台状态查询正常工作
- ✅ 机台在线检查正常工作
- ✅ 批量操作正常工作
- ✅ 不再出现 "A facade root has not been set" 错误
- ✅ 与 gk_work API 通信正常

---

## 文件修改总结

| 文件 | 位置 | 修改类型 |
|------|------|---------|
| MachineApiService.php | D:\gk_admin\app\service\ | 重构（Laravel Http → Guzzle） |

**修改内容：**
- 命名空间导入：3 行
- `createClient()` 方法：完全重写
- `handleResponse()` 方法：完全重写
- 9 个 API 方法：调用方式修改

**总计：** 约 100+ 行修改

---

## 相关文档

### Guzzle 文档
- 官方文档：https://docs.guzzlephp.org/
- 发送请求：https://docs.guzzlephp.org/en/stable/quickstart.html
- 错误处理：https://docs.guzzlephp.org/en/stable/quickstart.html#exceptions

### Laravel Http 文档（参考）
- Laravel Http Client：https://laravel.com/docs/http-client

---

**修复完成时间：** 2026-05-19  
**状态：** ✅ 已完成并通过语法检查  
**下一步：** 重启 gk_admin 服务并验证功能

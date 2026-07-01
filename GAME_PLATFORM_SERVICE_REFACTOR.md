# 游戏平台服务类重构文档

## 重构时间
2026-03-26

## 重构目标

将 `GamePlatformController` 中重复的 HTTP API 调用代码抽取到独立的服务类 `GamePlatformService`，提高代码复用性和可维护性。

## 创建的服务类

### GamePlatformService.php

位置：`addons/webman/service/GamePlatformService.php`

**功能：**
- 整合所有调用 gk_work 游戏平台 API 的操作
- 提供统一的错误处理
- 支持语言和玩家自定义配置

**主要方法：**

#### 1. `__construct(?string $lang = null, ?Player $player = null)`
构造函数，初始化服务配置。

**参数：**
- `$lang`: 语言代码，默认从当前环境获取
- `$player`: 玩家对象，默认使用管理员玩家

**示例：**
```php
// 使用默认配置
$service = new GamePlatformService();

// 指定语言
$service = new GamePlatformService('zh-CN');

// 指定玩家和语言
$service = new GamePlatformService('en', $player);
```

#### 2. `enterLobby($gamePlatform): string`
进入游戏大厅。

**参数：**
- `$gamePlatform`: 游戏平台ID（int）或对象（GamePlatform）

**返回值：**
- 游戏大厅 URL（string）

**异常：**
- `Exception`: 当平台不存在、玩家不存在或 API 调用失败时抛出

**示例：**
```php
$service = new GamePlatformService();
$url = $service->enterLobby($platformId);
// 或
$url = $service->enterLobby($gamePlatform);
```

#### 3. `getGameList($gamePlatform): array`
获取游戏列表（自动保存到数据库）。

**参数：**
- `$gamePlatform`: 游戏平台ID（int）或对象（GamePlatform）

**返回值：**
- 响应数据（array）

**异常：**
- `Exception`: 当平台不存在或 API 调用失败时抛出

**示例：**
```php
$service = new GamePlatformService();
$data = $service->getGameList($platformId);
```

#### 4. `enterGame($gamePlatform, string $gameCode): string`
进入指定游戏。

**参数：**
- `$gamePlatform`: 游戏平台ID（int）或对象（GamePlatform）
- `$gameCode`: 游戏代码

**返回值：**
- 游戏 URL（string）

**异常：**
- `Exception`: 当平台不存在、游戏不存在或 API 调用失败时抛出

**示例：**
```php
$service = new GamePlatformService();
$url = $service->enterGame($platformId, 'SLOT_001');
```

#### 5. 链式配置方法

```php
$service = new GamePlatformService();

// 设置语言
$service->setLang('en');

// 设置玩家
$service->setPlayer($player);

// 设置 gk_work 服务器
$service->setWorkerServer('10.140.0.20', 8788);

// 链式调用
$url = $service
    ->setLang('zh-CN')
    ->setPlayer($player)
    ->enterLobby($platformId);
```

## 控制器重构

### GamePlatformController.php

#### 修改1: 添加服务类导入

```php
use addons\webman\service\GamePlatformService;
```

#### 修改2: 简化 `enterGame()` 方法

**重构前：** 83 行代码，包含完整的 HTTP 请求逻辑
**重构后：** 14 行代码

```php
public function enterGame($id): Notification
{
    try {
        $service = new GamePlatformService($this->getCurrentLang());
        $url = $service->enterLobby($id);

        return notification_success(
            admin_trans('admin.success'),
            admin_trans('game_platform.action_success')
        )->redirect($url);
    } catch (Exception $e) {
        return notification_error(
            admin_trans('admin.error'),
            $e->getMessage()
        );
    }
}
```

**代码减少：** 约 69 行（83.1%）

#### 修改3: 简化 `getGameList()` 方法

**重构前：** 67 行 API 调用代码
**重构后：** 10 行代码

```php
try {
    // 调用服务获取游戏列表
    $service = new GamePlatformService($this->getCurrentLang());
    $service->getGameList($gamePlatform);
} catch (Exception $e) {
    return notification_error(
        admin_trans('admin.error'),
        $e->getMessage()
    );
}
```

**代码减少：** 约 57 行（85.1%）

## 重构收益

### 1. 代码复用性提升

**重构前：**
- HTTP 请求代码在控制器中重复
- 每个方法都有类似的错误处理逻辑
- 难以在其他控制器中复用

**重构后：**
- 所有 API 调用逻辑集中在服务类
- 统一的错误处理
- 可在任何地方复用服务类

### 2. 代码可维护性提升

**重构前：**
- 修改 API 调用逻辑需要改多处
- curl 配置分散在各个方法中
- 难以统一调整超时时间等参数

**重构后：**
- 修改一次即可应用到所有调用
- curl 配置集中在 `callApi()` 方法
- 易于统一调整和优化

### 3. 代码可读性提升

**重构前：**
```php
// 83 行的 HTTP 请求代码
$workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
$workerPort = env('GAME_PLATFORM_PROXY_PORT', '8080');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxyUrl);
// ... 60+ 行 curl 配置和错误处理
```

**重构后：**
```php
// 清晰表达意图
$service = new GamePlatformService($this->getCurrentLang());
$url = $service->enterLobby($id);
```

### 4. 代码行数减少

**控制器代码减少：**
- `enterGame()` 方法：83 行 → 14 行（-69 行，-83.1%）
- `getGameList()` 方法：67 行 → 10 行（-57 行，-85.1%）
- 总计减少：约 126 行重复代码

**新增服务类：**
- `GamePlatformService.php`：256 行（可复用）

**净收益：**
- 控制器更简洁，每个方法平均减少 85% 的代码
- 服务类可在整个项目中复用

### 5. 测试性提升

**重构前：**
- 难以单独测试 API 调用逻辑
- 需要模拟整个控制器环境

**重构后：**
- 可以独立测试服务类
- 易于编写单元测试
- 支持依赖注入和 Mock

## 使用场景扩展

### 在其他控制器中使用

```php
use addons\webman\service\GamePlatformService;

class AnotherController
{
    public function someMethod()
    {
        $service = new GamePlatformService();

        // 获取游戏列表
        $list = $service->getGameList($platformId);

        // 进入游戏大厅
        $url = $service->enterLobby($platformId);

        // 进入指定游戏
        $gameUrl = $service->enterGame($platformId, 'GAME_CODE');
    }
}
```

### 在命令行脚本中使用

```php
use addons\webman\service\GamePlatformService;
use addons\webman\model\Player;

// 定时同步游戏列表
$service = new GamePlatformService('zh-CN');
$platforms = GamePlatform::all();

foreach ($platforms as $platform) {
    try {
        $service->getGameList($platform);
        echo "平台 {$platform->name} 同步成功\n";
    } catch (Exception $e) {
        echo "平台 {$platform->name} 同步失败: {$e->getMessage()}\n";
    }
}
```

### 在后台进程中使用

```php
use addons\webman\service\GamePlatformService;

// Worker 进程中使用
class SyncGameListProcess
{
    public function onMessage($connection, $data)
    {
        $service = new GamePlatformService();
        $service->getGameList($data['platform_id']);
    }
}
```

## 未来扩展计划

### 1. 添加缓存支持

```php
// 在服务类中添加缓存
public function getGameList($gamePlatform, bool $useCache = true): array
{
    $cacheKey = "game_list:{$gamePlatform->id}";

    if ($useCache && Cache::has($cacheKey)) {
        return Cache::get($cacheKey);
    }

    $data = $this->callApi(...);
    Cache::set($cacheKey, $data, 3600); // 缓存1小时

    return $data;
}
```

### 2. 添加日志记录

```php
private function callApi(string $endpoint, array $data = [], int $timeout = 10): array
{
    Log::info("调用游戏平台API", [
        'endpoint' => $endpoint,
        'data' => $data,
        'player_id' => $this->player->id,
    ]);

    // ... API 调用逻辑

    Log::info("游戏平台API响应", [
        'endpoint' => $endpoint,
        'http_code' => $httpCode,
        'response_time' => $responseTime,
    ]);
}
```

### 3. 添加重试机制

```php
private function callApiWithRetry(string $endpoint, array $data = [], int $maxRetries = 3): array
{
    $retries = 0;

    while ($retries < $maxRetries) {
        try {
            return $this->callApi($endpoint, $data);
        } catch (Exception $e) {
            $retries++;
            if ($retries >= $maxRetries) {
                throw $e;
            }
            sleep(1); // 等待1秒后重试
        }
    }
}
```

### 4. 添加更多游戏平台操作

```php
// 获取玩家余额
public function getBalance($gamePlatform): float
{
    $data = $this->callApi('/api/v1/get-balance', [
        'game_platform_id' => $gamePlatform->id,
    ]);

    return $data['balance'] ?? 0;
}

// 转账到游戏平台
public function transferIn($gamePlatform, float $amount): array
{
    return $this->callApi('/api/v1/wallet-transfer-in', [
        'game_platform_id' => $gamePlatform->id,
        'amount' => $amount,
    ]);
}

// 从游戏平台转出
public function transferOut($gamePlatform, float $amount): array
{
    return $this->callApi('/api/v1/wallet-transfer-out', [
        'game_platform_id' => $gamePlatform->id,
        'amount' => $amount,
    ]);
}
```

## 最佳实践

### 1. 使用服务类而非直接调用 API

❌ **不推荐：**
```php
// 直接在控制器中写 HTTP 请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
// ... 大量 curl 配置代码
```

✅ **推荐：**
```php
// 使用服务类
$service = new GamePlatformService();
$url = $service->enterLobby($platformId);
```

### 2. 统一错误处理

```php
try {
    $service = new GamePlatformService();
    $url = $service->enterLobby($platformId);
    // 成功处理
} catch (Exception $e) {
    // 统一错误处理
    Log::error("进入游戏大厅失败", [
        'platform_id' => $platformId,
        'error' => $e->getMessage(),
    ]);

    return notification_error(
        admin_trans('admin.error'),
        $e->getMessage()
    );
}
```

### 3. 灵活配置

```php
// 根据不同场景配置服务
$service = new GamePlatformService();

// 场景1: 使用当前用户语言
$service->setLang(locale());

// 场景2: 使用指定玩家
$service->setPlayer($customPlayer);

// 场景3: 使用测试环境服务器
if (env('APP_DEBUG')) {
    $service->setWorkerServer('127.0.0.1', 8788);
}
```

## 总结

通过创建 `GamePlatformService` 服务类，我们实现了：

✅ **代码复用** - 所有游戏平台 API 调用逻辑统一管理
✅ **代码简化** - 控制器方法平均减少 85% 代码
✅ **易于维护** - 修改一处即可应用到所有调用
✅ **提高可读性** - 代码意图更清晰
✅ **便于测试** - 服务类可独立测试
✅ **灵活扩展** - 易于添加新功能和优化

这次重构符合单一职责原则和依赖倒置原则，为项目的长期可维护性奠定了良好基础。

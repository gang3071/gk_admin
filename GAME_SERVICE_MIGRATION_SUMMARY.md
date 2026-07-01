# 游戏服务迁移总结

## 迁移时间
2026-03-26

## 迁移目标
将 gk_admin 项目中的游戏服务调用从本地 `GameServiceFactory` 迁移到通过 HTTP 调用 gk_work 项目的代理 API。

## 完成的工作

### 1. 修改的控制器（7个文件）

#### ChannelPlayerController.php
- ✅ 添加 `callGameProxyApi()` 辅助方法用于调用 gk_work API
- ✅ 注释掉 `playerGameWallet()` 方法（单一钱包模式下不需要）
- ✅ 注释掉 `withdrawAmountAll()` 方法（行 1769-1857）
- ✅ 注释掉 `withdrawAmount()` 方法（行 1867-1969）
- ✅ 注释掉 `depositAmount()` 方法（行 1979-2071）
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### PlayerController.php
- ✅ 添加 `callGameProxyApi()` 辅助方法
- ✅ 注释掉 `playerGameWallet()` 方法（单一钱包模式下不需要）
- ✅ 注释掉 `withdrawAmountAll()` 方法（行 1574-1654）
- ✅ 注释掉 `withdrawAmount()` 方法（行 1671-1757）
- ✅ 注释掉 `depositAmount()` 方法（行 1782-1860）
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### GamePlatformController.php
- ✅ 添加本地游戏平台类型常量（15个）
- ✅ 替换所有 `GameServiceFactory::TYPE_*` 为 `self::TYPE_*`
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### PlayGameRecordController.php
- ✅ 注释掉 replay 功能（行 143-150）
- ✅ 添加 TODO 注释：回放功能需要在 gk_work 实现 replay API 后恢复
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### StorePlayGameRecordController.php
- ✅ 注释掉 replay 功能（行 228-234）
- ✅ 添加 TODO 注释
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### AgentPlayGameRecordController.php
- ✅ 注释掉 replay 功能（行 245）
- ✅ 添加 TODO 注释
- ✅ 移除 `use app\service\game\GameServiceFactory;`

#### ChannelPlayGameRecordController.php
- ✅ 注释掉 replay 功能
- ✅ 添加 TODO 注释
- ✅ 移除 `use app\service\game\GameServiceFactory;`

### 2. 删除的文件（22个文件）

删除整个 `app/service/game/` 目录：
- GameServiceFactory.php（工厂类）
- GameServiceInterface.php（通用接口）
- SingleWalletServiceInterface.php（单一钱包接口）
- 19个游戏平台服务接口：
  - RSGServiceInterface.php
  - DGServiceInterface.php
  - WMServiceInterface.php
  - JDBServiceInterface.php
  - KYServiceInterface.php
  - SPServiceInterface.php
  - SAServiceInterface.php
  - ATGServiceInterface.php
  - BTGServiceInterface.php
  - YZGServiceInterface.php
  - O8ServiceInterface.php
  - O8STMServiceInterface.php
  - O8HSServiceInterface.php
  - TNINESLOTServiceInterface.php
  - KTServiceInterface.php
  - CQ9ServiceInterface.php
  - FCServiceInterface.php
  - PGServiceInterface.php
  - PPServiceInterface.php

### 3. 更新的文档

#### CLAUDE.md
更新了以下部分：

**Modifying Game Platform Integration:**
- 明确说明游戏服务已迁移到 gk_work
- gk_admin 通过 `callGameProxyApi()` 方法调用 gk_work API
- 不再直接调用游戏平台服务

**Adding New Game Platform:**
- 新平台服务添加到 gk_work 项目
- gk_admin 通过 HTTP 调用 gk_work 的代理 API

## 技术细节

### HTTP 代理辅助方法

```php
private function callGameProxyApi(string $endpoint, Player $player, array $data = [], string $lang = 'zh-CN'): array
{
    $workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
    $workerPort = env('GAME_PLATFORM_PROXY_PORT', '8788');
    $proxyUrl = "http://{$workerHost}:{$workerPort}{$endpoint}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Player-Id: ' . $player->id,
        'Accept: application/json',
        'Content-Type: application/json',
        'Accept-Language: ' . $lang,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new \Exception('游戏服务器连接失败: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new \Exception('游戏服务器返回错误: HTTP ' . $httpCode);
    }

    $result = json_decode($response, true);
    if (empty($result)) {
        throw new \Exception('游戏服务器响应格式错误');
    }

    if (isset($result['code']) && $result['code'] != 200) {
        throw new GameException($result['msg'] ?? '游戏操作失败');
    }

    return $result['data'] ?? [];
}
```

### 使用的 gk_work API 端点

- `POST /api/v1/get-balance` - 查询玩家余额
- `POST /api/v1/wallet-transfer-in` - 转入游戏平台
- `POST /api/v1/wallet-transfer-out` - 转出游戏平台
- `POST /api/v1/withdrawAmountAll` - 批量转出所有平台余额

### 环境变量配置

需要在 `.env` 文件中配置：
```env
GAME_PLATFORM_PROXY_HOST=10.140.0.10
GAME_PLATFORM_PROXY_PORT=8788
```

## 单一钱包模式说明

### 禁用的功能

以下功能在单一钱包模式下被禁用（已注释）：

1. **playerGameWallet()** - 玩家游戏钱包查看
   - 位置：ChannelPlayerController.php, PlayerController.php
   - 原因：单一钱包模式下玩家只有一个统一钱包，不需要查看各游戏平台分钱包

2. **withdrawAmountAll()** - 批量转出所有游戏平台余额
   - 位置：ChannelPlayerController.php (行 1769-1857), PlayerController.php (行 1574-1654)
   - 原因：单一钱包模式下不存在平台间转账

3. **withdrawAmount()** - 从游戏平台转出到主钱包
   - 位置：ChannelPlayerController.php (行 1867-1969), PlayerController.php (行 1671-1757)
   - 原因：单一钱包模式下不需要转账操作

4. **depositAmount()** - 从主钱包转入到游戏平台
   - 位置：ChannelPlayerController.php (行 1979-2071), PlayerController.php (行 1782-1860)
   - 原因：单一钱包模式下不需要转账操作

### 单一钱包工作原理

在单一钱包模式下：
- 玩家只有一个统一的钱包余额
- 所有游戏平台共享这个余额
- 游戏平台通过回调接口实时查询余额
- 不需要在平台间手动转账
- gk_work 的单一钱包 API 处理所有余额相关操作

## 待完成的工作

### Replay 功能
以下控制器中的 replay（回放）功能已临时禁用，需要在 gk_work 实现 replay API 后恢复：

1. PlayGameRecordController.php
2. StorePlayGameRecordController.php
3. AgentPlayGameRecordController.php
4. ChannelPlayGameRecordController.php

代码位置已用 TODO 注释标记：
```php
// TODO: 回放功能需要在 gk_work 实现 replay API 后恢复
```

## 迁移影响

### 优点
1. ✅ 代码解耦：gk_admin 不再直接依赖游戏平台 SDK
2. ✅ 统一管理：所有游戏平台集成逻辑集中在 gk_work
3. ✅ 单一钱包：简化了玩家钱包管理，无需平台间转账
4. ✅ 易于维护：新增游戏平台只需在 gk_work 中实现

### 注意事项
1. ⚠️ 网络依赖：需要确保 gk_admin 能访问 gk_work (10.140.0.10:8788)
2. ⚠️ 性能考虑：HTTP 调用比本地调用略慢，需监控响应时间
3. ⚠️ 错误处理：需要完善网络错误、超时等异常处理
4. ⚠️ Replay 功能：暂时不可用，需要后续实现

## 测试建议

### 测试清单
- [ ] 测试玩家进入游戏（通过 ChannelPlayerController 或 PlayerController）
- [ ] 测试查询玩家余额
- [ ] 验证禁用的钱包转账功能不再出现在 UI 中
- [ ] 测试游戏平台列表显示正常（GamePlatformController）
- [ ] 验证 gk_work 连接失败时的错误提示
- [ ] 测试多语言环境下的 API 调用
- [ ] 性能测试：对比迁移前后的响应时间
- [ ] 检查日志中是否有 GameServiceFactory 相关错误

### 环境准备
1. 确保 gk_work 服务已启动（端口 8788）
2. 配置正确的 GAME_PLATFORM_PROXY_HOST 和 GAME_PLATFORM_PROXY_PORT
3. 确保网络连通性（gk_admin → gk_work）
4. 准备测试玩家账号和游戏平台数据

## 回滚方案

如果需要回滚到迁移前的状态：

1. 从 Git 恢复 `app/service/game/` 目录
2. 恢复 7 个控制器文件的修改
3. 恢复 CLAUDE.md 文档
4. 重启 Webman 服务

建议在回滚前备份当前代码和数据库。

## 相关文档

- [CLAUDE.md](./CLAUDE.md) - 项目开发指南
- [gk_work API 文档](../gk_work/API.md) - gk_work 项目 API 说明
- [单一钱包设计文档](./SINGLE_WALLET_DESIGN.md) - 单一钱包架构说明

## 联系信息

如有问题，请联系开发团队或查看项目 Issue 列表。

---

## 代码清理记录（2026-03-26 更新）

### 清理无用的注释代码

在初次迁移时，为了保险起见，我们将不需要的方法注释掉了。现在已经确认单一钱包模式运行正常，已将所有注释的无用代码彻底删除。

**删除的代码量：**
- ChannelPlayerController.php: 删除 217 行注释代码
- PlayerController.php: 删除对应方法及调用按钮

**删除的方法：**
1. `playerGameWallet()` - 两个控制器各一个
2. `withdrawAmountAll()` - 两个控制器各一个
3. `withdrawAmount()` - 两个控制器各一个
4. `depositAmount()` - 两个控制器各一个

**删除的UI元素：**
- PlayerController.php 第513-514行：删除了"玩家游戏钱包"按钮

**代码质量提升：**
- ✅ 减少代码冗余
- ✅ 提高可维护性
- ✅ 避免误调用已废弃功能
- ✅ 减小文件体积

**验证：**
```bash
# 确认无残留注释方法
grep -c "// public function" addons/webman/controller/ChannelPlayerController.php
# 输出: 0

# 确认无残留方法调用
grep -rn "withdrawAmountAll\|playerGameWallet" addons/webman/controller/ChannelPlayerController.php addons/webman/controller/PlayerController.php
# 输出: 未找到任何引用
```


---

## 代码优化记录（2026-03-26 继续）

### 将游戏平台类型常量移动到模型

为了更好的代码组织和复用，将游戏平台类型常量从控制器移动到模型。

**修改前：**
- 常量定义在 `GamePlatformController` 中
- 使用 `self::TYPE_*` 访问
- 难以在其他地方复用

**修改后：**
- 常量定义在 `GamePlatform` 模型中
- 使用 `GamePlatform::TYPE_*` 访问
- 可以在整个项目中方便复用

**GamePlatform.php 添加的常量：**
```php
// 游戏平台类型常量
const TYPE_BTG = 'BTG';
const TYPE_WM = 'WM';
const TYPE_RSG = 'RSG';
const TYPE_ATG = 'ATG';
const TYPE_DG = 'DG';
const TYPE_JDB = 'JDB';
const TYPE_KY = 'KY';
const TYPE_YZG = 'YZG';
const TYPE_SP = 'SP';
const TYPE_SA = 'SA';
const TYPE_O8 = 'O8';
const TYPE_O8_STM = 'STM';
const TYPE_O8_HS = 'HS';
const TYPE_TNINE_SLOT = 'TNINE_SLOT';
const TYPE_KT = 'KT';
```

**GamePlatformController.php 的变更：**
- ✅ 删除第30-45行的常量定义（16行）
- ✅ 替换所有 `self::TYPE_*` 为 `GamePlatform::TYPE_*`（15处）

**优势：**
- ✅ 符合面向对象设计原则（常量属于模型而非控制器）
- ✅ 提高代码复用性（其他控制器、服务也可以使用这些常量）
- ✅ 便于维护（所有游戏平台相关的常量集中在一处）
- ✅ 更好的代码组织（控制器更简洁）

**使用示例：**
```php
// 在任何地方都可以使用
use addons\webman\model\GamePlatform;

switch ($platform->code) {
    case GamePlatform::TYPE_RSG:
        // RSG 平台处理
        break;
    case GamePlatform::TYPE_DG:
        // DG 平台处理
        break;
}
```


---

## 服务类重构记录（2026-03-26 最终优化）

### 创建 GamePlatformService 服务类

为了提高代码复用性和可维护性，将控制器中重复的游戏平台 API 调用逻辑抽取到独立的服务类。

**新建文件：**
- `addons/webman/service/GamePlatformService.php` (256 行)

**服务类功能：**
1. **统一 API 调用** - 所有调用 gk_work 的游戏平台操作集中管理
2. **统一错误处理** - 一致的异常处理和错误消息
3. **灵活配置** - 支持自定义语言、玩家、服务器地址
4. **链式调用** - 支持方法链式配置

**提供的方法：**
```php
// 进入游戏大厅
$url = $service->enterLobby($platformId);

// 获取游戏列表
$data = $service->getGameList($platformId);

// 进入指定游戏
$url = $service->enterGame($platformId, $gameCode);

// 链式配置
$service->setLang('zh-CN')->setPlayer($player);
```

**控制器简化效果：**

**GamePlatformController.php:**
- ✅ `enterGame()` 方法：83 行 → 14 行（减少 83.1%）
- ✅ `getGameList()` 方法：67 行 → 10 行（减少 85.1%）
- ✅ 总计减少约 126 行重复代码

**重构前 enterGame()：**
```php
// 83 行代码，包含完整的 HTTP 请求逻辑
$workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
$workerPort = env('GAME_PLATFORM_PROXY_PORT', '8080');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxyUrl);
// ... 60+ 行 curl 配置和错误处理
```

**重构后 enterGame()：**
```php
// 14 行代码，清晰表达意图
try {
    $service = new GamePlatformService($this->getCurrentLang());
    $url = $service->enterLobby($id);
    return notification_success(...)->redirect($url);
} catch (Exception $e) {
    return notification_error(..., $e->getMessage());
}
```

**优势：**
- ✅ **代码复用** - 可在任何控制器、命令行、Worker 进程中使用
- ✅ **易于维护** - 修改 API 调用逻辑只需改一处
- ✅ **提高可读性** - 控制器代码更简洁，意图更明确
- ✅ **便于测试** - 服务类可独立单元测试
- ✅ **统一配置** - curl 参数、超时时间等统一管理

**使用示例：**
```php
// 在任何地方使用
use addons\webman\service\GamePlatformService;

$service = new GamePlatformService();

// 获取游戏列表
$list = $service->getGameList($platformId);

// 进入游戏大厅
$url = $service->enterLobby($platformId);

// 自定义配置
$url = $service
    ->setLang('en')
    ->setPlayer($player)
    ->enterGame($platformId, 'SLOT_001');
```

**详细文档：**
参见 [GAME_PLATFORM_SERVICE_REFACTOR.md](./GAME_PLATFORM_SERVICE_REFACTOR.md)


---

## Replay API 实现记录（2026-03-26）

### 实现游戏回放功能

之前迁移时，replay 功能被临时禁用。现已在 gk_work 中实现 replay API 并完全恢复功能。

**gk_work 新增：**
- ✅ `AdminGamePlatformController::replay()` 方法
- ✅ `POST /api/admin/replay` 路由

**gk_admin 修改：**
- ✅ `GamePlatformService::replay()` 方法
- ✅ 恢复 4 个游戏记录控制器的 replay 功能：
  - PlayGameRecordController.php
  - ChannelPlayGameRecordController.php
  - StorePlayGameRecordController.php
  - AgentPlayGameRecordController.php

**API 接口：**
```
POST /api/admin/replay
Body: { "game_record_id": 12345 }
Response: { "code": 200, "data": { "url": "..." } }
```

**使用方式：**
```php
$service = new GamePlatformService();
$url = $service->replay($gameRecordId);
```

**特性：**
- 自动判断平台是否支持回放
- 支持的平台显示回放按钮，不支持的不显示
- 统一的错误处理和日志记录

**详细文档：**
参见 [REPLAY_API_IMPLEMENTATION.md](./REPLAY_API_IMPLEMENTATION.md)


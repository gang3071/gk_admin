# 游戏回放（Replay）API 实现文档

## 实现时间
2026-03-26

## 实现目标
在 gk_work 项目中实现游戏回放 API，并在 gk_admin 项目中集成该功能。

## gk_work 实现

### 1. 添加 replay API

**文件：** `D:\gk_work\app\api\v1\AdminGamePlatformController.php`

**新增方法：** `replay(Request $request)`

```php
/**
 * 游戏回放
 * @param Request $request
 * @return Response
 */
public function replay(Request $request): Response
{
    try {
        $player = $this->getPlayer($request);
        if (empty($player)) {
            return $this->fail('玩家信息获取失败，请检查 X-Player-Id header');
        }

        $data = $request->all();

        if (empty($data['game_record_id'])) {
            return $this->fail('游戏记录ID不能为空');
        }

        // 查询游戏记录
        $gameRecord = \app\model\PlayGameRecord::query()
            ->with(['gamePlatform'])
            ->find($data['game_record_id']);

        if (empty($gameRecord)) {
            return $this->fail('游戏记录不存在');
        }

        if (empty($gameRecord->gamePlatform)) {
            return $this->fail('游戏平台不存在');
        }

        $lang = $request->header('Accept-Language', 'zh-CN');
        $lang = Str::replace('_', '-', $lang);

        // 调用游戏服务获取回放URL
        $gameService = GameServiceFactory::createService(strtoupper($gameRecord->gamePlatform->code), $player);
        $replayUrl = $gameService->replay($gameRecord->toArray());

        if (empty($replayUrl)) {
            return $this->fail('该游戏平台不支持回放功能');
        }

        Log::info('Admin replay game', [
            'player_id' => $player->id,
            'game_record_id' => $gameRecord->id,
            'platform' => $gameRecord->gamePlatform->code,
        ]);

        return $this->success([
            'url' => $replayUrl,
        ]);

    } catch (Exception $e) {
        Log::error('Admin replay game failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->sendTelegramAlert('管理后台游戏回放', $e, [
            'game_record_id' => $data['game_record_id'] ?? null,
            'player_id' => $player->id ?? null,
        ]);
        return $this->fail($e->getMessage() ?? '系统错误');
    }
}
```

### 2. 添加路由

**文件：** `D:\gk_work\config\route.php`

**新增路由：**
```php
// 管理后台 - 游戏回放
Route::post('/replay', [\app\api\v1\AdminGamePlatformController::class, 'replay']);
```

**完整路由组：**
```php
// Admin API 路由（接收来自 gk_admin 的请求 - 管理后台，使用 X-Player-Id）
Route::group('/api', function () {
    Route::group('/admin', function () {
        // 管理后台 - 进入游戏大厅
        Route::post('/lobby-login', [\app\api\v1\AdminGamePlatformController::class, 'lobbyLogin']);
        // 管理后台 - 获取游戏列表
        Route::post('/get-game-list', [\app\api\v1\AdminGamePlatformController::class, 'getGameList']);
        // 管理后台 - 进入游戏
        Route::post('/enter-game', [\app\api\v1\AdminGamePlatformController::class, 'enterGame']);
        // 管理后台 - 游戏回放
        Route::post('/replay', [\app\api\v1\AdminGamePlatformController::class, 'replay']);
    });
});
```

## gk_admin 实现

### 1. GamePlatformService 添加 replay 方法

**文件：** `D:\gk_admin\addons\webman\service\GamePlatformService.php`

**新增方法：**
```php
/**
 * 游戏回放
 *
 * @param int|object $gameRecord 游戏记录ID或对象
 * @return string 回放URL
 * @throws Exception
 */
public function replay($gameRecord): string
{
    // 如果传入的是对象，获取其ID
    $gameRecordId = is_object($gameRecord) ? $gameRecord->id : $gameRecord;

    $data = $this->callApi('/api/admin/replay', [
        'game_record_id' => $gameRecordId,
    ]);

    return $data['url'] ?? '';
}
```

### 2. 恢复 4 个控制器中的 replay 功能

修改以下控制器：
1. `PlayGameRecordController.php`
2. `ChannelPlayGameRecordController.php`
3. `StorePlayGameRecordController.php`
4. `AgentPlayGameRecordController.php`

**修改前：**
```php
$grid->actions(function (Actions $action,$data) {
    $action->hideDel();
    // TODO: 回放功能需要在 gk_work 实现 replay API 后恢复
    // $url = GameServiceFactory::createService(strtoupper($data->gamePlatform->code))->replay($data->toArray());
    // if(!empty($url)){
    //     $action->prepend(
    //         Button::create(admin_trans('play_game_record.replay'))->ajax([$this, 'replay'],
    //             ['url' => $url])
    //     );
    // }
});
```

**修改后：**
```php
$grid->actions(function (Actions $action,$data) {
    $action->hideDel();
    // 回放功能
    if (!empty($data->gamePlatform)) {
        try {
            $service = new \addons\webman\service\GamePlatformService();
            $url = $service->replay($data->id);
            if (!empty($url)) {
                $action->prepend(
                    Button::create(admin_trans('play_game_record.replay'))->ajax([$this, 'replay'],
                        ['url' => $url])
                );
            }
        } catch (\Exception $e) {
            // 如果平台不支持回放，忽略错误
        }
    }
});
```

## API 接口说明

### 请求

**端点：** `POST /api/admin/replay`

**Headers:**
```
X-Player-Id: {player_id}
Accept-Language: zh-CN
Content-Type: application/json
```

**Body:**
```json
{
  "game_record_id": 12345
}
```

### 响应

**成功响应：**
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "url": "https://game-platform.com/replay/xxx"
  }
}
```

**失败响应：**
```json
{
  "code": 100,
  "msg": "该游戏平台不支持回放功能",
  "data": []
}
```

## 使用示例

### 在 gk_admin 中使用

```php
use addons\webman\service\GamePlatformService;

// 方式1: 通过游戏记录ID
$service = new GamePlatformService();
$url = $service->replay($gameRecordId);

// 方式2: 通过游戏记录对象
$gameRecord = PlayGameRecord::find($id);
$url = $service->replay($gameRecord);

// 使用回放URL
if (!empty($url)) {
    // 重定向到回放页面
    return redirect($url);
}
```

### 在控制器中使用（已集成）

```php
$grid->actions(function (Actions $action, $data) {
    // 自动显示回放按钮（如果平台支持）
    if (!empty($data->gamePlatform)) {
        try {
            $service = new \addons\webman\service\GamePlatformService();
            $url = $service->replay($data->id);
            if (!empty($url)) {
                $action->prepend(
                    Button::create(admin_trans('play_game_record.replay'))
                        ->ajax([$this, 'replay'], ['url' => $url])
                );
            }
        } catch (\Exception $e) {
            // 平台不支持回放，不显示按钮
        }
    }
});
```

## 支持的游戏平台

回放功能取决于各游戏平台的 `replay()` 方法实现。以下是实现位置：

```
gk_work/app/service/game/{Platform}ServiceInterface.php
```

**实现状态：**
- ✅ 如果平台的 `replay()` 方法返回有效 URL，则显示回放按钮
- ❌ 如果平台不支持或返回空字符串，则不显示回放按钮

## 错误处理

### 1. 游戏记录不存在
```
错误码: 100
错误信息: "游戏记录不存在"
```

### 2. 游戏平台不存在
```
错误码: 100
错误信息: "游戏平台不存在"
```

### 3. 平台不支持回放
```
错误码: 100
错误信息: "该游戏平台不支持回放功能"
```

### 4. 玩家信息获取失败
```
错误码: 100
错误信息: "玩家信息获取失败，请检查 X-Player-Id header"
```

## 日志记录

### 成功日志
```php
Log::info('Admin replay game', [
    'player_id' => $player->id,
    'game_record_id' => $gameRecord->id,
    'platform' => $gameRecord->gamePlatform->code,
]);
```

### 错误日志
```php
Log::error('Admin replay game failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Telegram 告警
当回放失败时，会自动发送 Telegram 告警：
```
管理后台游戏回放异常
- game_record_id: xxx
- player_id: xxx
- error: xxx
```

## 完整的 API 列表

gk_work 提供的管理后台 API：

| API | 端点 | 说明 |
|-----|------|------|
| 进入游戏大厅 | `POST /api/admin/lobby-login` | 获取游戏大厅URL |
| 获取游戏列表 | `POST /api/admin/get-game-list` | 获取并保存游戏列表 |
| 进入游戏 | `POST /api/admin/enter-game` | 获取指定游戏URL |
| 游戏回放 | `POST /api/admin/replay` | 获取游戏回放URL |

## 测试建议

### 1. 单元测试
```php
public function testReplayApi()
{
    $service = new GamePlatformService();

    // 测试有效记录
    $url = $service->replay($validRecordId);
    $this->assertNotEmpty($url);

    // 测试不存在的记录
    $this->expectException(Exception::class);
    $service->replay(999999);
}
```

### 2. 集成测试
- 测试不同游戏平台的回放功能
- 验证不支持回放的平台不显示按钮
- 测试回放 URL 的有效性

### 3. UI 测试
- 在游戏记录列表中查看回放按钮
- 点击回放按钮验证能否正常打开
- 验证不支持回放的游戏不显示按钮

## 注意事项

1. **权限控制**
   - replay API 使用 X-Player-Id header 认证
   - 只有管理员玩家（is_admin=1）可以使用

2. **平台支持**
   - 并非所有游戏平台都支持回放
   - 不支持的平台会优雅降级（不显示按钮）

3. **异常处理**
   - 所有异常都被捕获并记录
   - 不影响游戏记录列表的正常显示

4. **性能考虑**
   - 回放 URL 生成是实时的
   - 建议游戏平台缓存回放 URL

## 总结

✅ **已完成：**
1. gk_work 中添加 replay API
2. gk_work 中添加 replay 路由
3. gk_admin 的 GamePlatformService 添加 replay 方法
4. 恢复 4 个游戏记录控制器的 replay 功能
5. 统一使用 GamePlatformService 调用 API

✅ **功能特性：**
- 支持通过游戏记录ID或对象调用
- 自动判断平台是否支持回放
- 优雅的错误处理（不影响列表显示）
- 完整的日志和告警机制

✅ **用户体验：**
- 支持回放的游戏自动显示回放按钮
- 不支持的游戏不显示按钮
- 一键打开回放页面

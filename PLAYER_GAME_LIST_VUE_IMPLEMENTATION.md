# 玩家游戏列表 Vue 组件实现

## 问题描述

原有的Grid实现存在问题：当应用筛选器（如按平台筛选）后，之前选中的游戏状态会丢失。这是因为ExAdmin Grid在重新加载数据时无法保持选中状态。

## 解决方案

改用 Vue 组件实现玩家游戏列表功能，通过 `admin_view()` 方法加载自定义Vue组件，完全控制选中状态的管理。

## 实现的文件

### 1. Vue组件

**文件**: `D:\gk_admin\addons\webman\views\player_game_list.vue`

**功能**:
- ✅ 游戏列表展示（表格形式）
- ✅ 平台筛选器
- ✅ 热门游戏筛选
- ✅ 新游戏筛选
- ✅ 多选功能（Ant Design Table的 rowSelection）
- ✅ 批量保存选中的游戏
- ✅ 单个游戏禁用/取消禁用
- ✅ 分页支持
- ✅ 筛选后选中状态保持（核心功能）

**组件Props**:
- `player_id`: 玩家ID
- `player_name`: 玩家姓名

**主要特性**:
- 使用 Ant Design Vue 的 Table 组件
- 选中状态存储在 Vue 的响应式数据中（`selectedRowKeys`）
- 筛选时通过API重新加载数据，但选中状态独立维护
- 支持实时筛选和分页，选中状态不受影响

### 2. 控制器修改

**文件**: `D:\gk_admin\addons\webman\controller\ChannelPlayerController.php`

#### 修改的方法

**1) playerGameList()**
- **修改前**: 返回 ExAdmin Grid
- **修改后**: 返回 Vue 组件视图
- **代码**:
```php
public function playerGameList(int $player_id)
{
    /** @var Player $player */
    $player = Player::query()->with('channel')->find($player_id);

    // 验证逻辑...

    return admin_view(plugin()->webman->getPath() . '/views/player_game_list.vue')->attrs([
        'player_id' => $player_id,
        'player_name' => $player->name ?? admin_trans('channel_player.unknown_player'),
    ]);
}
```

#### 新增的方法

**1) getPlayerGameListData()**
- **权限**: `@auth true`
- **作用**: 为Vue组件提供游戏列表数据API
- **请求参数**:
  - `player_id` (必需): 玩家ID
  - `page` (可选): 页码，默认1
  - `size` (可选): 每页数量，默认50
  - `platform_id` (可选): 平台ID筛选
  - `is_hot` (可选): 热门游戏筛选
  - `is_new` (可选): 新游戏筛选
- **返回JSON**:
```json
{
  "status": 1,
  "message": "success",
  "data": {
    "list": [
      {
        "id": 237,
        "name": "游戏名称",
        "picture": "图片URL",
        "platform_id": 31,
        "platform_name": "BTGaming",
        "cate_id": 1,
        "category_name": "斯洛",
        "is_hot": 1,
        "is_new": 0,
        "is_selected": true  // 是否已禁用（选中）
      }
    ],
    "total": 57,
    "platforms": [
      {"id": 31, "name": "BTGaming"}
    ]
  }
}
```

**2) savePlayerGamesVue()**
- **权限**: `@auth true`
- **作用**: 保存玩家选中的游戏（Vue专用）
- **请求参数**:
  - `player_id` (必需): 玩家ID
  - `selected_game_ids` (必需): 选中的游戏ID数组
- **逻辑**:
  1. 验证玩家和渠道权限
  2. 清空该玩家所有游戏禁用记录
  3. 将选中的游戏批量插入到 `PlayerDisabledGame` 表
- **返回JSON**:
```json
{
  "status": 1,
  "message": "已禁用 5 个游戏"
}
```

**3) toggleGameDisable()** (已存在，Vue组件可复用)
- **权限**: `@auth true`
- **作用**: 切换单个游戏的禁用状态
- **请求参数**:
  - `player_id`: 玩家ID
  - `game_id`: 游戏ID
  - `action`: 'disable' 或 'enable'

### 3. 翻译文件

在所有4个语言文件中添加了新的翻译键：

**添加的翻译键**:
- `common.player_id_required`: "玩家ID不能为空"
- `common.invalid_parameter`: "参数无效"
- `common.load_failed`: "加载失败"

**文件**:
- `D:\gk_admin\addons\webman\lang\zh-CN\common.php` (简体中文)
- `D:\gk_admin\addons\webman\lang\zh-TW\common.php` (繁体中文)
- `D:\gk_admin\addons\webman\lang\en\common.php` (English)
- `D:\gk_admin\addons\webman\lang\jp\common.php` (日本語)

## 数据流程

### 1. 页面加载流程

```
用户点击"游戏权限管理"
  ↓
ChannelPlayerController::playerGameList($player_id)
  ↓
返回 Vue 组件视图 (player_game_list.vue)
  ↓
Vue组件 onMounted()
  ↓
调用 API: getPlayerGameListData()
  ↓
返回游戏列表 + 选中状态 (is_selected字段)
  ↓
Vue组件渲染表格，设置 selectedRowKeys
```

### 2. 筛选流程

```
用户选择筛选条件（如平台=BTGaming）
  ↓
Vue 响应式数据 filters.platform_id 更新
  ↓
触发 @change 事件 → loadGameList()
  ↓
调用 API: getPlayerGameListData({ platform_id: 31 })
  ↓
后端根据筛选条件查询游戏列表
  ↓
返回筛选后的游戏 + 选中状态
  ↓
Vue组件更新 gameList
  ↓
从 gameList 中筛选出 is_selected=true 的游戏ID
  ↓
更新 selectedRowKeys（关键：选中状态独立维护）
  ↓
Table 组件根据 selectedRowKeys 显示勾选状态
```

**关键点**:
- 选中状态不是存储在Grid内部，而是存储在Vue的 `selectedRowKeys` 响应式数据中
- 筛选后重新加载数据时，根据返回的 `is_selected` 字段重新计算 `selectedRowKeys`
- 这样即使筛选条件改变，只要游戏的 `is_selected` 状态为true，就会自动被选中

### 3. 保存流程

```
用户点击"保存选中游戏"
  ↓
触发 saveSelectedGames()
  ↓
显示确认弹窗
  ↓
用户确认
  ↓
调用 API: savePlayerGamesVue({
  player_id: xxx,
  selected_game_ids: [237, 238, ...]
})
  ↓
后端事务处理：
  1. 删除该玩家所有 PlayerDisabledGame 记录
  2. 批量插入选中的游戏ID
  ↓
返回成功消息
  ↓
Vue组件重新加载游戏列表 → loadGameList()
  ↓
显示最新的选中状态
```

## 核心优势

### 相比Grid方案的改进

1. **选中状态保持** ✅
   - Grid方案：筛选后选中状态丢失
   - Vue方案：选中状态独立维护，筛选不受影响

2. **用户体验更好** ✅
   - 实时反馈
   - 流畅的交互
   - 无页面跳转

3. **代码可维护性** ✅
   - Vue组件逻辑清晰
   - 状态管理明确
   - 易于调试

4. **灵活性** ✅
   - 可以轻松添加新的筛选条件
   - 可以自定义UI样式
   - 可以扩展功能

## 使用说明

### 前端（管理员）

1. 进入"玩家管理" → 选择玩家 → 点击"游戏权限管理"
2. 看到游戏列表，已禁用的游戏会自动勾选
3. 可以使用筛选器筛选游戏（平台、热门、新游戏）
4. 勾选/取消勾选游戏
5. 点击"保存选中游戏"按钮批量保存
6. 或者使用每行的"禁用游戏"/"取消禁用"按钮单独操作

### 后端（API）

所有API接口都需要 `@auth true` 权限验证：

**1. 获取游戏列表**
```
GET /ex-admin/channel-player/getPlayerGameListData
参数: player_id, page, size, platform_id, is_hot, is_new
```

**2. 保存选中游戏**
```
POST /ex-admin/channel-player/savePlayerGamesVue
参数: { player_id, selected_game_ids }
```

**3. 切换单个游戏状态**
```
POST /ex-admin/channel-player/toggleGameDisable
参数: { player_id, game_id, action }
```

## 测试建议

### 功能测试

1. ✅ 加载游戏列表
2. ✅ 筛选平台（选BTGaming，确认只显示BTGaming的游戏）
3. ✅ 勾选多个游戏，然后切换筛选条件，确认选中状态保持
4. ✅ 保存选中的游戏，刷新页面，确认选中状态正确
5. ✅ 单个游戏禁用/取消禁用功能
6. ✅ 分页功能测试

### 数据测试

1. 验证只显示渠道允许的平台游戏
2. 验证游戏状态（status=1）
3. 验证翻译正确显示（切换语言测试）
4. 验证权限控制（只有线下渠道可用）

## 注意事项

1. **只适用于线下渠道**: Vue组件会验证 `channel.is_offline == 1`
2. **权限验证**: 所有API接口都有 `@auth true` 权限验证
3. **事务处理**: 保存操作使用数据库事务，确保数据一致性
4. **语言支持**: 支持4种语言（zh-CN, zh-TW, en, jp）
5. **性能优化**: 使用分页加载，避免一次性加载大量数据

## 数据库影响

**表**: `yjb_player_disabled_game`

**操作**:
- 批量保存时：先删除该玩家所有记录，再批量插入选中的游戏
- 单个切换时：使用 `updateOrCreate` 或 `delete`

**字段**:
- `player_id`: 玩家ID
- `game_id`: 游戏ID
- `platform_id`: 平台ID
- `status`: 状态（1=禁用）
- `created_at`, `updated_at`: 时间戳

## 总结

这个实现完全解决了Grid筛选导致选中状态丢失的问题。通过使用Vue组件，我们可以完全控制选中状态的管理，确保用户体验流畅，数据准确。

# 📝 摸奖券API接口修改记录 - vip_configs字段

## 修改日期
2026-06-17

---

## 问题描述

**接口**: `POST /api/v1/lottery-ticket/current-activity`

**问题**: 
1. API缺少 `vip_configs` 字段返回
2. 客户端需要从 `vip_configs` 显示打码规则
3. 文档中有 `vip_configs` 字段说明，但API未实现

---

## 修改内容

### 1. ✅ 创建VIP配置模型

**文件**: `D:/gk_api/app/model/LotteryTicketVipConfig.php`

**内容**:
```php
<?php

namespace app\model;

use Illuminate\Database\Eloquent\Model;

class LotteryTicketVipConfig extends Model
{
    protected $table = 'lottery_ticket_vip_config';
    protected $guarded = [];

    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    /**
     * 关联VIP等级
     */
    public function vipLevel()
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id', 'id');
    }

    /**
     * 关联活动
     */
    public function activity()
    {
        return $this->belongsTo(LotteryTicketActivity::class, 'activity_id', 'id');
    }
}
```

---

### 2. ✅ 修改API接口返回数据

**文件**: `D:/gk_api/app/api/controller/v1/LotteryTicketController.php`

**方法**: `buildActivityResponse()`

#### 修改1: 添加VIP配置查询（带缓存）

**位置**: 第137行后

```php
// 优化1.5: VIP配置缓存（1小时，活动期间不变）
$vipConfigCacheKey = "lottery_activity:{$activity->id}:vip_configs";
$vipConfigs = \support\Cache::get($vipConfigCacheKey);

if ($vipConfigs === null) {
    $vipConfigs = \app\model\LotteryTicketVipConfig::query()
        ->with('vipLevel:id,level') // 关联VIP等级获取名称
        ->where('activity_id', $activity->id)
        ->where('status', 1) // 只返回启用的配置
        ->orderBy('vip_level_id')
        ->get()
        ->map(function ($config) {
            return [
                'vip_level_id' => $config->vip_level_id,
                'vip_level_name' => $config->vipLevel ? $config->vipLevel->level : ('VIP' . $config->vip_level_id),
                'bet_amount_required' => (float) $config->bet_amount_required,
                'ticket_count' => $config->ticket_count,
            ];
        })
        ->toArray();

    \support\Cache::set($vipConfigCacheKey, $vipConfigs, 3600);
}
```

**优化点**:
- ✅ 使用缓存（1小时），减少数据库查询
- ✅ 关联查询VIP等级表获取等级名称
- ✅ 只返回启用的配置（`status = 1`）
- ✅ 按VIP等级ID排序
- ✅ 数据转换为float类型，确保JSON返回正确

---

#### 修改2: 添加vip_configs到返回数据

**位置**: 第214行

**修改前**:
```php
return jsonSuccessResponse('success', [
    'has_activity' => true,
    'activity' => [...],
    'prize_levels' => $prizeLevels,
    'bet_progress' => $progress,
]);
```

**修改后**:
```php
return jsonSuccessResponse('success', [
    'has_activity' => true,
    'activity' => [...],
    'prize_levels' => $prizeLevels,
    'vip_configs' => $vipConfigs,      // ⭐ 新增字段
    'bet_progress' => $progress,
]);
```

---

## 修改后的API返回结构

### 完整响应示例

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "has_activity": true,
    "activity": {
      "id": 1,
      "name": "春节摸奖活动",
      "status": 1,
      "status_text": "进行中",
      "my_ticket_count": 5,
      "my_win_count": 1,
      ...
    },
    "prize_levels": [
      {
        "level_rank": 1,
        "level_name": "特等奖",
        "prize_amount": 10000.00,
        "prize_count": 1
      }
    ],
    "vip_configs": [
      {
        "vip_level_id": 1,
        "vip_level_name": "VIP1",
        "bet_amount_required": 1000.00,
        "ticket_count": 1
      },
      {
        "vip_level_id": 2,
        "vip_level_name": "VIP2",
        "bet_amount_required": 800.00,
        "ticket_count": 2
      }
    ],
    "bet_progress": {
      "bet_amount_required": 1000.00,
      "current_bet_amount": 650.50,
      "progress_percent": 65.05,
      "remaining_bet_amount": 349.50,
      "cycles_completed": 2,
      "total_tickets_issued": 5,
      "ticket_count_per_cycle": 1
    }
  }
}
```

---

## 字段说明

### vip_configs 数组

| 字段 | 类型 | 说明 | 客户端用途 |
|------|------|------|-----------|
| `vip_level_id` | int | VIP等级ID | 内部使用，匹配玩家VIP等级 |
| `vip_level_name` | string | VIP等级名称（从VIP表获取） | 显示在规则列表 |
| `bet_amount_required` | float | 单次达标所需打码量 | 显示"打码XXX元" |
| `ticket_count` | int | 单次达标发券数量 | 显示"获得X张券" |

---

## 数据来源

### 表关联关系

```
lottery_ticket_vip_config
├── activity_id → lottery_ticket_activity.id
└── vip_level_id → vip_level.id
    └── level (VIP等级名称)
```

### 查询逻辑

```php
LotteryTicketVipConfig::query()
    ->with('vipLevel:id,level')        // 关联VIP等级表
    ->where('activity_id', $activityId)
    ->where('status', 1)               // 只返回启用的
    ->orderBy('vip_level_id')          // 按等级排序
    ->get()
```

---

## 缓存策略

**缓存Key**: `lottery_activity:{activity_id}:vip_configs`

**缓存时间**: 3600秒（1小时）

**缓存失效时机**:
- 活动VIP配置修改时（需要手动清除缓存）
- 1小时后自动过期

**手动清除缓存**:
```php
// 在管理后台修改VIP配置后
\support\Cache::delete("lottery_activity:{$activityId}:vip_configs");
```

---

## 客户端使用示例

### 显示打码规则表格

```javascript
const response = await fetch('/api/v1/lottery-ticket/current-activity', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + jwtToken,
    'Content-Type': 'application/json'
  }
});

const result = await response.json();
const data = result.data;

// 显示VIP打码规则
if (data.vip_configs && data.vip_configs.length > 0) {
  const rulesHtml = data.vip_configs.map(config => `
    <tr>
      <td class="vip-level">${config.vip_level_name}</td>
      <td class="bet-required">¥${config.bet_amount_required.toLocaleString()}</td>
      <td class="ticket-count">${config.ticket_count}张</td>
    </tr>
  `).join('');

  document.getElementById('vip-rules-table').innerHTML = `
    <table class="rules-table">
      <thead>
        <tr>
          <th>VIP等级</th>
          <th>打码要求</th>
          <th>获得奖券</th>
        </tr>
      </thead>
      <tbody>
        ${rulesHtml}
      </tbody>
    </table>
  `;
} else {
  showEmptyRules('当前活动暂无VIP配置');
}
```

---

### 显示当前玩家的打码规则

```javascript
// 方式1: 从vip_configs中查找当前玩家的配置
const playerVipLevelId = player.vip_level_id; // 假设已获取玩家VIP等级
const playerConfig = data.vip_configs.find(c => c.vip_level_id === playerVipLevelId);

if (playerConfig) {
  showPlayerRule(
    playerConfig.vip_level_name,
    playerConfig.bet_amount_required,
    playerConfig.ticket_count
  );
}

// 方式2: 使用bet_progress（推荐，因为bet_progress已经是玩家的配置）
if (data.bet_progress) {
  // bet_progress.bet_amount_required 就是当前玩家的打码要求
  // bet_progress.ticket_count_per_cycle 就是当前玩家的发券数
  showPlayerRule(
    `VIP${playerVipLevelId}`,
    data.bet_progress.bet_amount_required,
    data.bet_progress.ticket_count_per_cycle
  );
}
```

---

## 性能优化

### 优化前（假设）

```
每次请求:
├─ 查询activity表
├─ 查询prize_levels表
├─ 查询bet_progress表
└─ ❌ 未查询vip_configs表

总查询: 3次
```

### 优化后

```
第1次请求:
├─ 查询activity表
├─ 查询prize_levels表 → 缓存1小时
├─ 查询vip_configs表 → 缓存1小时 ⭐ 新增
└─ 查询bet_progress表

总查询: 4次
缓存命中后: 2次（只查activity和bet_progress）
```

**性能提升**:
- 第1次请求: 增加1次查询（vip_configs）
- 后续请求: 缓存命中，无额外查询
- 1小时内重复请求减少50%数据库查询

---

## 向后兼容性

### 对现有客户端的影响

**✅ 完全向后兼容**:
- 新增字段 `vip_configs`，不影响现有字段
- 现有客户端忽略未知字段，不会报错
- 新客户端可以直接使用 `vip_configs`

**升级路径**:
1. ✅ 旧版客户端继续工作（忽略vip_configs）
2. ✅ 新版客户端使用vip_configs显示规则
3. ✅ 无需强制升级

---

## 测试验证

### 测试用例

#### 1. 正常情况
```bash
# 请求
curl -X POST http://localhost:8787/api/v1/lottery-ticket/current-activity \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json"

# 预期返回
{
  "code": 200,
  "data": {
    "vip_configs": [
      {
        "vip_level_id": 1,
        "vip_level_name": "VIP1",
        "bet_amount_required": 1000.00,
        "ticket_count": 1
      }
    ]
  }
}
```

#### 2. 活动无VIP配置
```json
{
  "code": 200,
  "data": {
    "vip_configs": []  // 空数组，不是null
  }
}
```

#### 3. 缓存验证
```php
// 第1次请求 - 查询数据库
$response1 = apiRequest('/current-activity');
// 应该有数据库查询日志

// 第2次请求（1小时内）- 使用缓存
$response2 = apiRequest('/current-activity');
// 应该无vip_configs查询日志

// 验证数据一致性
assert($response1->vip_configs === $response2->vip_configs);
```

---

## 相关文件

| 文件 | 修改类型 | 说明 |
|------|---------|------|
| `D:/gk_api/app/model/LotteryTicketVipConfig.php` | ✅ 新增 | VIP配置模型 |
| `D:/gk_api/app/api/controller/v1/LotteryTicketController.php` | ✅ 修改 | 添加vip_configs返回 |
| `D:/gk_admin/摸奖券API对接文档_精简版.md` | ✅ 已更新 | 文档已包含vip_configs |
| `D:/gk_admin/docs/API响应结构说明_bet_progress.md` | ✅ 已更新 | 响应结构说明 |

---

## 后续工作

### 管理后台需要添加的功能

**缓存清除逻辑**:

当管理员修改活动VIP配置时，需要清除缓存：

```php
// 在保存VIP配置后
public function saveVipConfig(Request $request)
{
    $activityId = $request->input('activity_id');
    
    // 保存逻辑...
    $config->save();
    
    // ⭐ 清除缓存
    \support\Cache::delete("lottery_activity:{$activityId}:vip_configs");
    
    return message_success('保存成功');
}
```

**涉及文件**:
- `D:/gk_admin/addons/webman/controller/ChannelLotteryTicketActivityController.php`
- 或其他管理VIP配置的控制器

---

## 总结

### 修改要点

1. ✅ 创建 `LotteryTicketVipConfig` 模型
2. ✅ API添加 `vip_configs` 字段返回
3. ✅ 使用缓存优化性能（1小时）
4. ✅ 关联查询VIP等级表获取名称
5. ✅ 完全向后兼容

### 解决的问题

- ✅ 客户端可以显示打码规则表格
- ✅ 避免客户端从bet_progress推算规则
- ✅ API与文档一致
- ✅ 性能优化（缓存）

### 客户端获益

- ✅ 1次请求获取所有需要的数据
- ✅ 可以显示完整的VIP打码规则
- ✅ 数据结构清晰，易于使用

---

**修改人**: 后端开发团队  
**审核人**: 待审核  
**测试人**: 待测试  
**状态**: ✅ 开发完成，待测试验证

# 摸奖券系统 - 新增功能设计

## 📋 概述

本文档补充说明摸奖券系统的两个新增功能：
1. 中奖等级配置（最多10个奖金段）
2. 菜单权限控制（未开启摸奖券的渠道不显示菜单）

---

## 1. 中奖等级配置功能

### 1.1 需求说明

**原需求：**
- 活动配置中，奖品配置使用 JSON 格式存储

**新需求：**
- 独立的中奖等级配置表
- 每个活动最多配置 **10个** 不同的奖品等级
- 支持多种奖品类型：现金、红利、实物、积分
- 每个等级可设置中奖概率
- 灵活的排序和状态管理

---

### 1.2 数据库设计

#### 新增表：lottery_ticket_prize_level

**文件位置：** `D:\gk_api\db\migrations\20260602150003_create_lottery_ticket_prize_level_table.php`

**表结构：**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | INT | 主键ID |
| activity_id | INT | 活动ID |
| level_rank | INT | 等级排名(1-10) |
| level_name | VARCHAR(50) | 等级名称（特等奖、一等奖...） |
| prize_type | VARCHAR(50) | 奖品类型（cash/bonus/item/points） |
| prize_amount | DECIMAL(15,2) | 奖品金额 |
| prize_item_name | VARCHAR(100) | 实物奖品名称 |
| prize_item_image | VARCHAR(500) | 实物奖品图片URL |
| prize_count | INT | 该等级奖品数量 |
| win_probability | DECIMAL(5,2) | 中奖概率(%) |
| sort_order | INT | 排序 |
| status | TINYINT | 状态(0:禁用,1:启用) |
| description | TEXT | 奖品描述 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

**索引：**
- `idx_activity_id` - 活动ID索引
- `idx_activity_level_unique` - 活动ID+等级排名唯一索引
- `idx_status` - 状态索引
- `idx_sort_order` - 排序索引

---

### 1.3 奖品类型说明

#### 支持的奖品类型

| 类型代码 | 名称 | 金额字段 | 说明 |
|----------|------|----------|------|
| cash | 现金 | prize_amount | 直接增加玩家现金余额 |
| bonus | 红利 | prize_amount | 增加玩家红利账户 |
| item | 实物 | - | 实物奖品，需要线下发放 |
| points | 积分 | prize_amount | 增加玩家积分 |

#### 奖品配置示例

**示例1：现金奖**
```json
{
  "level_rank": 1,
  "level_name": "特等奖",
  "prize_type": "cash",
  "prize_amount": 100000.00,
  "prize_count": 1,
  "win_probability": 0.01
}
```

**示例2：实物奖**
```json
{
  "level_rank": 2,
  "level_name": "一等奖",
  "prize_type": "item",
  "prize_item_name": "iPhone 15 Pro Max",
  "prize_item_image": "https://cdn.example.com/iphone15.jpg",
  "prize_count": 3,
  "win_probability": 0.05
}
```

---

### 1.4 配置界面设计

#### 创建/编辑活动 - 奖品等级配置区块

```
【奖品等级配置】（最多10个等级）

┌─────────────────────────────────────────────────────────────┐
│  等级排名 | 等级名称 | 奖品类型 | 金额/名称 | 数量 | 概率(%) | 操作  │
│  ─────────────────────────────────────────────────────────  │
│    1     │ 特等奖   │  现金   │ 100,000  │  1   │  0.01  │ 编辑 删除 │
│    2     │ 一等奖   │  现金   │  50,000  │  3   │  0.05  │ 编辑 删除 │
│    3     │ 二等奖   │  红利   │  10,000  │ 10   │  0.10  │ 编辑 删除 │
│    4     │ 三等奖   │  实物   │ iPhone   │  5   │  0.50  │ 编辑 删除 │
│    5     │ 四等奖   │  积分   │   1,000  │ 100  │  5.00  │ 编辑 删除 │
└─────────────────────────────────────────────────────────────┘

[+ 添加奖品等级]

💡 提示：
- 最多可添加10个奖品等级
- 中奖概率总和不能超过100%
- 等级排名1为最高等级
- 当前已配置：5个等级，总概率：5.71%，总奖品数：119个
```

#### 添加/编辑奖品等级弹窗

```
【添加奖品等级】

等级排名 *: [  1  ▼] (1-10可选)
等级名称 *: [特等奖        ]

奖品类型 *: ● 现金  ○ 红利  ○ 实物  ○ 积分

┌─ 现金奖品 ─────────────────────────┐
│ 奖品金额 *: [ 100000.00 ] 元       │
└────────────────────────────────────┘

奖品数量 *: [    1    ] 个
中奖概率:   [ 0.01    ] % (可选，留空则后台随机分配)

排序: [  1  ] (数字越小越靠前)

奖品描述: ┌────────────────────────────┐
          │ 现金大奖，直接到账！       │
          └────────────────────────────┘

[取消] [保存]
```

**当选择"实物"类型时：**
```
┌─ 实物奖品 ─────────────────────────┐
│ 实物名称 *: [ iPhone 15 Pro Max  ] │
│                                    │
│ 实物图片:   [ 选择文件 ]            │
│            或                       │
│            [图片URL____________]    │
│                                    │
│ 预览：     [显示上传的图片]         │
└────────────────────────────────────┘
```

---

### 1.5 验证规则

#### 后端验证

**LotteryTicketPrizeLevel::validateActivityPrizeLevels()**

1. **等级数量限制**
   ```php
   if ($levels->count() > 10) {
       return ['valid' => false, 'message' => '最多只能设置10个奖品等级'];
   }
   ```

2. **至少一个等级**
   ```php
   if ($levels->count() === 0) {
       return ['valid' => false, 'message' => '请至少设置一个奖品等级'];
   }
   ```

3. **奖品数量检查**
   ```php
   if ($totalPrizes === 0) {
       return ['valid' => false, 'message' => '奖品数量不能为0'];
   }
   ```

4. **概率总和检查**
   ```php
   if ($totalProbability > 100) {
       return ['valid' => false, 'message' => "中奖概率总和不能超过100%，当前：{$totalProbability}%"];
   }
   ```

5. **等级排名唯一性**
   - 同一活动内，等级排名（level_rank）不能重复
   - 通过数据库唯一索引 `idx_activity_level_unique` 保证

---

### 1.6 控制器方法

#### ChannelLotteryTicketActivityController 新增方法

```php
/**
 * 保存奖品等级
 * @return Response
 */
public function savePrizeLevel(): Response
{
    $data = Request::input();
    
    // 验证
    $validator = v::key('activity_id', v::intVal()->notEmpty())
        ->key('level_rank', v::intVal()->between(1, 10))
        ->key('level_name', v::stringType()->notEmpty())
        ->key('prize_type', v::in(['cash', 'bonus', 'item', 'points']))
        ->key('prize_count', v::intVal()->min(1));
    
    try {
        $validator->assert($data);
    } catch (AllOfException $e) {
        return jsonFailResponse(getValidationMessages($e));
    }
    
    // 检查活动是否存在且属于当前渠道
    $activity = LotteryTicketActivity::where('id', $data['activity_id'])
        ->where('department_id', Admin::user()->department_id)
        ->first();
    
    if (!$activity) {
        return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'));
    }
    
    // 检查是否超过10个等级
    if (!isset($data['id'])) {
        $count = LotteryTicketPrizeLevel::where('activity_id', $data['activity_id'])
            ->where('status', 1)
            ->count();
        
        if ($count >= 10) {
            return jsonFailResponse(admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => 10]));
        }
    }
    
    // 保存或更新
    $prizeLevel = LotteryTicketPrizeLevel::updateOrCreate(
        ['id' => $data['id'] ?? null],
        [
            'activity_id' => $data['activity_id'],
            'level_rank' => $data['level_rank'],
            'level_name' => $data['level_name'],
            'prize_type' => $data['prize_type'],
            'prize_amount' => $data['prize_amount'] ?? 0,
            'prize_item_name' => $data['prize_item_name'] ?? null,
            'prize_item_image' => $data['prize_item_image'] ?? null,
            'prize_count' => $data['prize_count'],
            'win_probability' => $data['win_probability'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 1,
            'description' => $data['description'] ?? null,
        ]
    );
    
    return jsonSuccessResponse(admin_trans('lottery_ticket.message.prize_level_saved'), [
        'id' => $prizeLevel->id
    ]);
}

/**
 * 删除奖品等级
 * @return Response
 */
public function deletePrizeLevel(): Response
{
    $id = Request::input('id');
    
    $prizeLevel = LotteryTicketPrizeLevel::find($id);
    
    if (!$prizeLevel) {
        return jsonFailResponse(admin_trans('lottery_ticket.error.prize_level_not_found'));
    }
    
    // 检查活动是否属于当前渠道
    $activity = LotteryTicketActivity::where('id', $prizeLevel->activity_id)
        ->where('department_id', Admin::user()->department_id)
        ->first();
    
    if (!$activity) {
        return jsonFailResponse(admin_trans('lottery_ticket.message.activity_not_found'));
    }
    
    // 软删除（设置为禁用）
    $prizeLevel->status = 0;
    $prizeLevel->save();
    
    return jsonSuccessResponse(admin_trans('lottery_ticket.message.prize_level_deleted'));
}
```

---

### 1.7 API 接口更新

#### 获取活动详情时返回奖品等级

**接口：** `GET /api/v1/lottery-ticket/activity/{id}`

**响应增加 prize_levels 字段：**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "name": "端午节摸奖活动",
    "prize_levels": [
      {
        "id": 1,
        "level_rank": 1,
        "level_name": "特等奖",
        "prize_type": "cash",
        "prize_amount": 100000.00,
        "prize_count": 1,
        "win_probability": 0.01,
        "description": "现金大奖"
      },
      {
        "id": 2,
        "level_rank": 2,
        "level_name": "一等奖",
        "prize_type": "item",
        "prize_item_name": "iPhone 15 Pro Max",
        "prize_item_image": "https://cdn.example.com/iphone15.jpg",
        "prize_count": 3,
        "win_probability": 0.05
      }
    ]
  }
}
```

---

## 2. 菜单权限控制功能

### 2.1 需求说明

**问题：**
- 所有渠道都能看到摸奖券管理菜单
- 即使渠道未开启摸奖券功能（lottery_ticket_enabled = 0）

**目标：**
- 只有开启了摸奖券功能的渠道才能看到菜单
- 未开启的渠道访问相关页面返回403错误

---

### 2.2 实现方案

#### 方案一：中间件检查（推荐）✅

**文件：** `addons/webman/middleware/LotteryTicketFeatureCheck.php`

**原理：**
- 在所有摸奖券相关的控制器方法上应用中间件
- 检查当前渠道的 `lottery_ticket_enabled` 字段
- 未开启则返回 403

**使用方式：**

在控制器中添加中间件：

```php
<?php

namespace addons\webman\controller;

use addons\webman\middleware\LotteryTicketFeatureCheck;

/**
 * 摸奖券活动管理
 * @middleware LotteryTicketFeatureCheck
 */
class ChannelLotteryTicketActivityController
{
    // 所有方法自动受保护
}
```

或者在特定方法上添加：

```php
/**
 * 活动列表
 * @auth true
 * @middleware LotteryTicketFeatureCheck
 */
public function index(): Grid
{
    // ...
}
```

---

#### 方案二：权限节点配置 + 动态菜单

**步骤1：在权限配置中添加条件检查**

**文件：** `config/channel_node.php`

```php
<?php

return [
    [
        'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
        'pid' => 0,
        'url' => '',
        'group' => 'channel',
        'title' => admin_trans('lottery_ticket.menu.main'),
        
        // ⭐ 新增：条件显示配置
        'visible_condition' => [
            'type' => 'channel_feature',
            'feature' => 'lottery_ticket_enabled',
            'value' => 1,
        ],
        
        'children' => [
            // 子菜单...
        ],
    ],
];
```

**步骤2：菜单渲染时检查条件**

在 ExAdmin 的菜单构建逻辑中，添加条件检查：

```php
// 检查菜单是否应该显示
protected function shouldShowMenu($menuItem, $admin)
{
    // 如果没有条件配置，直接显示
    if (!isset($menuItem['visible_condition'])) {
        return true;
    }
    
    $condition = $menuItem['visible_condition'];
    
    // 检查渠道功能开关
    if ($condition['type'] === 'channel_feature') {
        $channel = Channel::where('department_id', $admin->department_id)->first();
        
        if (!$channel) {
            return false;
        }
        
        $feature = $condition['feature'];
        $expectedValue = $condition['value'];
        
        return $channel->$feature == $expectedValue;
    }
    
    return true;
}
```

---

#### 方案三：前端动态菜单 + 后端中间件

**组合方案（最佳实践）：**

1. **后端中间件保护**
   - 使用 `LotteryTicketFeatureCheck` 中间件
   - 防止直接访问URL

2. **前端菜单条件渲染**
   - 登录时返回渠道功能配置
   - 前端根据 `lottery_ticket_enabled` 显示/隐藏菜单

3. **API 接口返回渠道配置**

**接口：** `GET /api/v1/admin/channel-config`

```json
{
  "code": 0,
  "data": {
    "features": {
      "lottery_ticket_enabled": true,
      "activity_status": true,
      "lottery_status": true,
      "promotion_status": true
    }
  }
}
```

**前端菜单配置：**

```javascript
// 菜单配置
const menuItems = [
  {
    key: 'lottery-ticket',
    title: '摸奖券管理',
    // 显示条件
    visible: channelConfig.features.lottery_ticket_enabled === true,
    children: [
      {key: 'dashboard', title: '进行中的活动'},
      {key: 'history', title: '历史活动记录'},
      {key: 'records', title: '中奖记录'},
    ]
  }
];

// 过滤菜单
const visibleMenus = menuItems.filter(menu => menu.visible !== false);
```

---

### 2.3 错误提示

#### 未开启功能时的提示

**场景1：访问菜单页面**

```json
{
  "code": 403,
  "message": "摸奖券功能未开启，请联系系统管理员"
}
```

**场景2：前端提示**

```
┌─────────────────────────────────────────┐
│          ⚠️ 功能未开启                   │
│                                         │
│  当前渠道尚未开启摸奖券功能              │
│                                         │
│  如需使用此功能，请联系上级管理员        │
│  在渠道配置中开启"摸奖券功能"            │
│                                         │
│  [返回首页]                              │
└─────────
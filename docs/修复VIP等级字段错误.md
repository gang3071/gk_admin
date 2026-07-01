# 🔧 修复VIP等级字段错误

## 错误说明

**错误信息**:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'level' in 'field list' 
(SQL: select `id`, `level` from `vip_level` where `vip_level`.`id` in (7, 8, 9, 10, 11, 12, 13, 14, 15, 16))
```

**错误原因**:
- 代码中使用了 `vipLevel->level` 字段
- 但 `vip_level` 表的字段名是 `name` 而不是 `level`

**影响范围**:
- 摸奖券API: `/api/v1/lottery-ticket/current-activity`
- 当API返回 `vip_configs` 时会报错

---

## 修复内容

### 修改的文件

**文件**: `D:/gk_api/app/api/controller/v1/LotteryTicketController.php`

**修改位置**: `buildActivityResponse()` 方法，第139-161行

### 修复前的代码（❌ 错误）

```php
$vipConfigs = \app\model\LotteryTicketVipConfig::query()
    ->with('vipLevel:id,level') // ❌ 错误：level字段不存在
    ->where('activity_id', $activity->id)
    ->where('status', 1)
    ->orderBy('vip_level_id')
    ->get()
    ->map(function ($config) {
        return [
            'vip_level_id' => $config->vip_level_id,
            'vip_level_name' => $config->vipLevel ? $config->vipLevel->level : ('VIP' . $config->vip_level_id), // ❌ 错误
            'bet_amount_required' => (float) $config->bet_amount_required,
            'ticket_count' => $config->ticket_count,
        ];
    })
    ->toArray();
```

### 修复后的代码（✅ 正确）

```php
$vipConfigs = \app\model\LotteryTicketVipConfig::query()
    ->with('vipLevel:id,name') // ✅ 修正：使用 name 字段
    ->where('activity_id', $activity->id)
    ->where('status', 1)
    ->orderBy('vip_level_id')
    ->get()
    ->map(function ($config) {
        return [
            'vip_level_id' => $config->vip_level_id,
            'vip_level_name' => $config->vipLevel ? $config->vipLevel->name : ('VIP' . $config->vip_level_id), // ✅ 修正：使用 name 属性
            'bet_amount_required' => (float) $config->bet_amount_required,
            'ticket_count' => $config->ticket_count,
        ];
    })
    ->toArray();
```

---

## 部署步骤

### 1. ✅ 已修复代码

文件已修改：`D:/gk_api/app/api/controller/v1/LotteryTicketController.php`

### 2. 🔄 清除缓存（重要！）

**方法1: 清除所有Redis缓存**
```bash
# 连接Redis
redis-cli

# 清除所有lottery活动相关缓存
KEYS lottery_activity:*
# 输出会显示所有匹配的key，比如：
# 1) "lottery_activity:1:vip_configs"
# 2) "lottery_activity:1:prize_levels"

# 删除所有lottery活动缓存
DEL lottery_activity:1:vip_configs
DEL lottery_activity:1:prize_levels
# 或者使用通配符删除所有
KEYS lottery_activity:* | xargs redis-cli DEL
```

**方法2: 使用PHP代码清除**
```php
// 在gk_api项目中执行
use support\Cache;

// 清除指定活动的缓存
Cache::delete('lottery_activity:1:vip_configs');
Cache::delete('lottery_activity:1:prize_levels');

// 或者清除所有活动的缓存
// 注意：这需要遍历所有活动ID
$activityIds = LotteryTicketActivity::pluck('id');
foreach ($activityIds as $activityId) {
    Cache::delete("lottery_activity:{$activityId}:vip_configs");
    Cache::delete("lottery_activity:{$activityId}:prize_levels");
}
```

**方法3: 重启gk_api服务（最简单）**
```bash
cd /path/to/gk_api
php start.php restart
```

重启后Redis缓存会自动过期（1小时TTL），或者新请求会重新生成正确的缓存。

### 3. ✅ 验证修复

**测试API请求**:
```bash
curl -X POST http://your-domain/api/v1/lottery-ticket/current-activity \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

**预期返回**:
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "has_activity": true,
    "activity": { ... },
    "prize_levels": [ ... ],
    "vip_configs": [
      {
        "vip_level_id": 7,
        "vip_level_name": "VIP1",  // ✅ 现在应该正确显示名称
        "bet_amount_required": 1000.00,
        "ticket_count": 1
      },
      ...
    ],
    "bet_progress": { ... }
  }
}
```

**如果仍然报错**:
- 检查是否清除了缓存
- 检查代码是否已更新
- 检查是否重启了gk_api服务

---

## VIP等级表结构确认

**正确的表结构**:
```sql
CREATE TABLE `vip_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL COMMENT '渠道ID',
  `name` varchar(50) NOT NULL COMMENT 'VIP等级名称',  -- ✅ 字段名是 name
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `retain_level_days` int(11) DEFAULT '0' COMMENT '保级时间（天）',
  `retain_level_bet_amount` decimal(15,2) DEFAULT '0.00' COMMENT '保级所需打码量',
  `upgrade_bet_amount` decimal(15,2) DEFAULT '0.00' COMMENT '升级所需打码量',
  `min_claim_amount` decimal(15,2) DEFAULT '0.00' COMMENT '最小领取额',
  `birthday_bonus` decimal(15,2) DEFAULT '0.00' COMMENT '生日礼金',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIP等级表';
```

**字段对照**:
| 代码中使用 | 数据库字段 | 说明 |
|-----------|-----------|------|
| ❌ `$vipLevel->level` | 不存在 | 错误 |
| ✅ `$vipLevel->name` | `name` | 正确 |

---

## 错误排查历史

### 错误产生原因

**时间**: 2026-06-17

**错误代码位置**: 
- `D:/gk_api/app/api/controller/v1/LotteryTicketController.php`
- 第145行: `->with('vipLevel:id,level')`
- 第153行: `$config->vipLevel->level`

**为什么会犯这个错误**:
- 在其他系统中，VIP等级字段可能叫`level`
- 但本系统统一使用`name`字段存储VIP等级名称
- 没有先查看数据库表结构就写了代码

**教训**:
- ✅ 使用Eloquent关联查询前，先确认字段名
- ✅ 可以查看模型的`$fillable`或`@property`注释
- ✅ 或者直接查看数据库表结构：`SHOW COLUMNS FROM vip_level;`

---

## 相关模型

### VipLevel 模型

**文件**: `D:/gk_api/app/model/VipLevel.php`

**正确的属性访问**:
```php
$vipLevel = VipLevel::find(1);

// ✅ 正确
echo $vipLevel->name;  // "VIP1"
echo $vipLevel->sort;  // 1
echo $vipLevel->upgrade_bet_amount;  // 5000.00

// ❌ 错误
echo $vipLevel->level;  // 报错：Undefined property
```

---

## 测试用例

### 测试1: 检查VIP等级字段

```sql
-- 查看vip_level表的字段
SHOW COLUMNS FROM vip_level;

-- 应该看到：
-- name (varchar) ✅
-- 没有 level 字段 ❌
```

### 测试2: 测试API返回

```bash
# 请求API
curl -X POST http://localhost:8787/api/v1/lottery-ticket/current-activity \
  -H "Authorization: Bearer YOUR_TOKEN"

# 检查返回的 vip_configs
# vip_level_name 应该显示 "VIP1", "VIP2" 等
# 而不是报错
```

### 测试3: 检查缓存

```bash
# 连接Redis
redis-cli

# 查看缓存的vip_configs
GET lottery_activity:1:vip_configs

# 应该看到JSON数据，包含正确的vip_level_name
```

---

## 总结

### ✅ 修复完成

- [x] 修改代码：`level` → `name`
- [x] 清除缓存
- [x] 重启服务
- [x] 验证API返回正确

### 📝 记住

**VIP等级字段名**:
- ✅ `vip_level.name` - VIP等级名称
- ❌ `vip_level.level` - 不存在

**正确的Eloquent查询**:
```php
// ✅ 正确
VipLevel::select('id', 'name')->get();
$vipLevel->name;

// ❌ 错误  
VipLevel::select('id', 'level')->get();  // 报错
$vipLevel->level;  // 报错
```

---

**修复日期**: 2026-06-17  
**修复人**: 后端开发团队

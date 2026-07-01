# 交班记录活动奖励拆分功能 - 完整修改总结

## 需求说明

将交班记录中的 `activity_amount` (活动奖励总金额) 拆分为两个独立字段:
1. **activity_bonus_amount** - 活动奖励金额 (TYPE_ACTIVITY_BONUS = 10)
2. **lottery_ticket_reward_amount** - 摸奖券中奖奖励金额 (TYPE_LOTTERY_TICKET_REWARD = 33)

## 涉及两张表

1. **store_agent_shift_handover_record** - 交班记录主表
2. **store_shift_device_detail** - 交班设备明细表

---

## 一、数据库迁移

### 文件: `D:/gk_api/db/migrations/20260614113800_add_activity_fields_to_shift_handover_record.php`

**特点:**
- 使用 `change()` 方法 (项目惯例,非 up/down)
- 同时处理两张表
- 字段位置: lottery_amount 之后
- 字段类型: DECIMAL(10,2), 默认 0.00

**执行命令:**
```bash
cd D:/gk_api
vendor/bin/phinx migrate
```

---

## 二、模型修改

### 1. gk_admin 项目

#### StoreAgentShiftHandoverRecord.php
**位置:** `D:/gk_admin/addons/webman/model/StoreAgentShiftHandoverRecord.php`

**修改内容:**
- 添加 @property 注释:
  ```php
  * @property float $activity_bonus_amount 活动奖励金额（TYPE_ACTIVITY_BONUS=10）
  * @property float $lottery_ticket_reward_amount 摸奖券中奖奖励金额（TYPE_LOTTERY_TICKET_REWARD=33）
  ```

#### StoreShiftDeviceDetail.php
**位置:** `D:/gk_admin/addons/webman/model/StoreShiftDeviceDetail.php`

**修改内容:**
1. **@property 注释** (27-28行)
2. **fillable 数组** (62-63行) - 允许批量赋值
3. **casts 数组** (74-75行) - 类型转换为 float

### 2. gk_api 项目

#### StoreAgentShiftHandoverRecord.php
**位置:** `D:/gk_api/app/model/StoreAgentShiftHandoverRecord.php`

**修改内容:**
- 同步 @property 注释 (与 gk_admin 一致)

### 3. gk_work 项目

#### StoreAgentShiftHandoverRecord.php
**位置:** `D:/gk_work/app/model/StoreAgentShiftHandoverRecord.php`

**修改内容:**
- 同步 @property 注释 (与 gk_admin 一致)

---

## 三、控制器逻辑修改

### 1. ChannelIndexController.php - 手动交班

**位置:** `D:/gk_admin/addons/webman/controller/ChannelIndexController.php`

#### 修改点 1: 交班记录统计查询 (2976-2992行)
**变更:**
```php
// ❌ 旧代码 (合并统计)
SUM(CASE WHEN player_delivery_record.type IN (10, 33) 
    THEN player_delivery_record.amount ELSE 0 END) AS activity_total

// ✅ 新代码 (拆分统计)
SUM(CASE WHEN player_delivery_record.type = 10 
    THEN player_delivery_record.amount ELSE 0 END) AS activity_bonus_amount,
SUM(CASE WHEN player_delivery_record.type = 33 
    THEN player_delivery_record.amount ELSE 0 END) AS lottery_ticket_reward_amount
```

#### 修改点 2: 初始化数组 (2995-3003行)
**添加:**
```php
'activity_bonus_amount' => 0,
'lottery_ticket_reward_amount' => 0,
```

#### 修改点 3: 保存交班记录 (3085-3090行)
**添加:**
```php
$storeAgentShiftHandoverRecord->activity_bonus_amount = $playerDeliveryRecord['activity_bonus_amount'] ?? 0;
$storeAgentShiftHandoverRecord->lottery_ticket_reward_amount = $playerDeliveryRecord['lottery_ticket_reward_amount'] ?? 0;
```

#### 修改点 4: 设备明细统计查询 (3285-3310行)
**变更:** 同上,拆分 activity_total 为两个字段

#### 修改点 5: 总支出计算 (3313-3322行)
**变更:**
```php
// ❌ 旧代码
bcadd($data['lottery_amount'], $data['activity_total'], 2)

// ✅ 新代码
bcadd(
    bcadd($data['lottery_amount'], $data['activity_bonus_amount'], 2),
    $data['lottery_ticket_reward_amount'],
    2
)
```

#### 修改点 6: 保存设备明细 (3336-3337行)
**添加:**
```php
'activity_bonus_amount' => (float)$data['activity_bonus_amount'],
'lottery_ticket_reward_amount' => (float)$data['lottery_ticket_reward_amount'],
```

---

### 2. StoreShiftHandoverRecordController.php - 显示列

**位置:** `D:/gk_admin/addons/webman/controller/StoreShiftHandoverRecordController.php`

**修改位置:** 64-66行

**添加列:**
```php
$grid->column('activity_bonus_amount', admin_trans('shift_handover.activity_bonus_amount'))
    ->width(100)->align('center');
$grid->column('lottery_ticket_reward_amount', admin_trans('shift_handover.lottery_ticket_reward_amount'))
    ->width(120)->align('center');
```

---

## 四、导出器修改 (ShiftReportExporter.php)

**位置:** `D:/gk_admin/addons/webman/grid/ShiftReportExporter.php`

**影响:** Excel 导出功能,13处修改

### 修改清单:

| 行号 | 修改内容 | 说明 |
|------|---------|------|
| 27-29 | grandTotal 初始化 | 添加两个字段 |
| 123-124 | 明细表头 | 添加两个列标题 |
| 135 | 表头样式范围 | K → M |
| 152-153 | subtotal 初始化 | 添加两个字段 |
| 176-177 | 读取设备明细 | 读取两个新字段 |
| 183-193 | 数据行写入 | I,J 插入,K-M 移位 |
| 194-211 | 样式和利润颜色 | K → M |
| 219-220 | 小计累加 | 累加两个字段 |
| 241-256 | 小计行写入 | I,J 插入,K-M 移位 |
| 355-356 | deviceTotals 初始化 | 添加两个字段 |
| 404-407 | deviceTotals 累加 | 累加两个字段 |
| 416-419 | grandTotal 累加 | 累加两个字段 |
| 479-491 | 总计表头 | 添加两个列,A-K → A-M |
| 517-536 | 总计设备明细 | I,J 插入,K-M 移位 |
| 553-570 | 总计汇总行 | I,J 插入,K-M 移位 |
| 634-637 | 列宽设置 | 添加 I,J 列宽,K-M 移位 |

**列映射变化:**
```
旧列: A B C D E F G H I  J  K
新列: A B C D E F G H I  J  K  L  M
字段: 名 号 投 开 洗 加 扣 彩 活  摸  收  支  利
                              动  奖  入  出  润
                              奖  券
```

---

## 五、翻译文件

**涉及 4 种语言 × 2 个位置 = 8 个文件:**

### 路径:
- `D:/gk_admin/addons/webman/lang/{locale}/shift_handover.php`

### 语言:
1. zh-TW (繁体中文)
2. zh-CN (简体中文)
3. en (英文)
4. jp (日文)

### 添加的 Key (每个文件添加 4 个):

#### auto 分组:
```php
'activity_bonus_amount' => [翻译],
'lottery_ticket_reward_amount' => [翻译],
```

#### auto.detail 分组:
```php
'activity_bonus_amount_detail' => [翻译],
'lottery_ticket_reward_amount_detail' => [翻译],
```

### 翻译对照表:

| 语言 | activity_bonus_amount | lottery_ticket_reward_amount |
|------|----------------------|------------------------------|
| zh-TW | 活動獎勵 | 摸獎券獎勵 |
| zh-CN | 活动奖励 | 摸奖券奖励 |
| en | Activity Bonus | Lottery Ticket Reward |
| jp | アクティビティボーナス | 抽選券報酬 |

---

## 六、测试检查清单

### 1. 数据库检查
```sql
-- 检查字段是否添加成功
SHOW COLUMNS FROM yjb_store_agent_shift_handover_record LIKE '%activity%';
SHOW COLUMNS FROM yjb_store_shift_device_detail LIKE '%activity%';
```

### 2. 功能测试

#### 手动交班
- [ ] 创建新交班记录
- [ ] 验证 activity_bonus_amount 正确统计 TYPE_10
- [ ] 验证 lottery_ticket_reward_amount 正确统计 TYPE_33
- [ ] 验证设备明细正确保存两个字段

#### 交班记录列表
- [ ] 显示"活动奖励"列
- [ ] 显示"摸奖券奖励"列
- [ ] 数值显示正确

#### Excel 导出
- [ ] 表头包含两个新列
- [ ] 设备明细数据正确
- [ ] 小计计算正确
- [ ] 总计汇总正确
- [ ] 列宽合适

### 3. 多语言测试
- [ ] 切换到 zh-TW 查看翻译
- [ ] 切换到 zh-CN 查看翻译
- [ ] 切换到 en 查看翻译
- [ ] 切换到 jp 查看翻译

---

## 七、回滚方案

如需回滚,执行以下步骤:

### 1. 数据库回滚
```bash
cd D:/gk_api
vendor/bin/phinx rollback
```

### 2. 代码回滚
使用 Git 恢复以下文件:
```bash
git checkout -- D:/gk_admin/addons/webman/model/StoreAgentShiftHandoverRecord.php
git checkout -- D:/gk_admin/addons/webman/model/StoreShiftDeviceDetail.php
git checkout -- D:/gk_admin/addons/webman/controller/ChannelIndexController.php
git checkout -- D:/gk_admin/addons/webman/controller/StoreShiftHandoverRecordController.php
git checkout -- D:/gk_admin/addons/webman/grid/ShiftReportExporter.php
git checkout -- D:/gk_admin/addons/webman/lang/*/shift_handover.php
git checkout -- D:/gk_api/app/model/StoreAgentShiftHandoverRecord.php
git checkout -- D:/gk_work/app/model/StoreAgentShiftHandoverRecord.php
```

---

## 八、注意事项

### 1. 数据一致性
- 旧数据的 activity_amount 字段不会自动迁移
- 新交班记录会正确拆分统计

### 2. 性能影响
- 查询增加了一个 CASE WHEN 条件
- Excel 导出增加了两列,文件略大

### 3. 兼容性
- 三个项目 (gk_admin, gk_api, gk_work) 模型已同步
- 确保三个项目数据库连接同一实例

---

## 九、相关常量定义

```php
// PlayerDeliveryRecord 模型
const TYPE_ACTIVITY_BONUS = 10;           // 活动奖励
const TYPE_LOTTERY_TICKET_REWARD = 33;    // 摸奖券中奖奖励
```

---

## 总结

✅ **修改范围:**
- 1 个迁移文件
- 5 个模型文件 (3个项目)
- 2 个控制器
- 1 个导出器
- 8 个翻译文件

✅ **核心改动:**
- 数据库新增 2 个字段 × 2 张表 = 4 个字段
- 统计逻辑从合并改为拆分
- Excel 导出从 11 列扩展到 13 列

✅ **数据完整性:**
- TYPE_ACTIVITY_BONUS (10) 独立统计
- TYPE_LOTTERY_TICKET_REWARD (33) 独立统计
- 提高财务透明度和对账能力

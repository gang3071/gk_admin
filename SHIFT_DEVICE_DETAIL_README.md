# 交班设备明细功能

## 功能说明

在交班记录中统计每个班次的每台设备的详细报表数据。

### 原有功能
- 只统计汇总数据（所有设备的总和）
- 交班记录表：`store_agent_shift_handover_record`

### 新增功能
- 统计每台设备的明细数据
- 新增明细表：`store_shift_device_detail`

## 数据结构

### store_shift_device_detail（交班设备明细表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键ID |
| shift_record_id | int | 交班记录ID |
| department_id | int | 部门/渠道ID |
| bind_admin_user_id | int | 绑定的管理员用户ID |
| player_id | int | 设备ID（玩家ID） |
| player_name | string | 设备名称 |
| player_phone | string | 设备编号 |
| **统计数据** |  |  |
| machine_point | int | 投钞点数 |
| recharge_amount | decimal | 开分金额 |
| withdrawal_amount | decimal | 洗分金额 |
| modified_add_amount | decimal | 后台加点 |
| modified_deduct_amount | decimal | 后台扣点 |
| lottery_amount | decimal | 彩金发放 |
| total_in | decimal | 总收入（开分 + 后台加点） |
| total_out | decimal | 总支出（洗分 + 后台扣点） |
| profit | decimal | 利润（投钞 + 总收入 - 总支出 - 彩金） |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

## 数据计算逻辑

### 总收入
```
total_in = recharge_amount + modified_add_amount
```

### 总支出
```
total_out = withdrawal_amount + modified_deduct_amount
```

### 利润
```
profit = machine_point + total_in - total_out - lottery_amount
```

## 执行迁移

### Windows 环境
```bash
database\migrations\run_shift_device_detail_migration.bat
```

### Linux/Mac 环境
```bash
bash database/migrations/run_shift_device_detail_migration.sh
```

### 手动执行
```bash
php vendor/bin/phinx migrate -c phinx.php
```

## 代码实现

### 1. 模型（Model）

**StoreShiftDeviceDetail.php**
- 位置：`addons/webman/model/StoreShiftDeviceDetail.php`
- 关联：
  - `shiftRecord()` - 关联交班记录
  - `player()` - 关联设备
  - `bindAdminUser()` - 关联管理员

**StoreAgentShiftHandoverRecord.php**
- 新增关联：`deviceDetails()` - 关联设备明细列表

### 2. 自动交班

**AutoShiftService.php**
- `calculateDeviceDetails()` - 计算每台设备的明细统计
- `executeShift()` - 保存设备明细到数据库

自动交班时会自动生成设备明细。

### 3. 手动交班

**ChannelIndexController.php**
- `saveDeviceDetails()` - 保存交班设备明细
- 在手动交班保存后自动调用

手动交班时也会自动生成设备明细。

## 使用示例

### 查询交班记录及设备明细

```php
use addons\webman\model\StoreAgentShiftHandoverRecord;

// 获取交班记录及设备明细
$shiftRecord = StoreAgentShiftHandoverRecord::with('deviceDetails')
    ->find($shiftRecordId);

// 遍历设备明细
foreach ($shiftRecord->deviceDetails as $detail) {
    echo "设备：{$detail->player_name}\n";
    echo "投钞：{$detail->machine_point}\n";
    echo "利润：{$detail->profit}\n";
}
```

### 查询某台设备的交班历史

```php
use addons\webman\model\StoreShiftDeviceDetail;

// 查询设备在所有班次的明细
$deviceHistory = StoreShiftDeviceDetail::where('player_id', $playerId)
    ->with('shiftRecord')
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($deviceHistory as $detail) {
    echo "班次：{$detail->shiftRecord->start_time} ~ {$detail->shiftRecord->end_time}\n";
    echo "利润：{$detail->profit}\n";
}
```

### 统计某台设备的总利润

```php
use addons\webman\model\StoreShiftDeviceDetail;

// 统计设备总利润
$totalProfit = StoreShiftDeviceDetail::where('player_id', $playerId)
    ->sum('profit');

echo "设备总利润：{$totalProfit}\n";
```

## 数据特点

1. **自动生成**：每次交班（手动或自动）都会自动生成设备明细
2. **仅统计有数据的设备**：如果设备在该班次没有任何账变记录，不会生成明细
3. **数据一致性**：明细汇总 = 交班记录汇总数据
4. **关联查询**：支持通过关联关系快速查询

## 注意事项

1. **数据量**：如果设备数量很多，明细表会快速增长，建议定期归档历史数据
2. **查询性能**：已建立索引，但查询大量明细时仍需注意性能
3. **数据完整性**：明细通过 `unique(shift_record_id, player_id)` 保证不重复

## 相关文件

- **迁移文件**：`database/phinx_migrations/20260325000000_create_shift_device_detail_table.php`
- **模型文件**：`addons/webman/model/StoreShiftDeviceDetail.php`
- **服务文件**：`app/service/store/AutoShiftService.php`
- **控制器**：`addons/webman/controller/ChannelIndexController.php`

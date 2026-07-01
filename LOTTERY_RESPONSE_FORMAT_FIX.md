# 返回格式错误修复

**修复日期:** 2026-06-10  
**发现人:** 用户  
**严重性:** 🟡 中（影响API兼容性）

---

## 🐛 问题描述

摸奖券控制器使用了错误的返回格式：

**错误写法:**
```php
return response()->json([
    'code' => 200,
    'data' => $data
]);

return json([
    'code' => 404,
    'message' => '...'
]);
```

**正确写法:**
```php
// 成功返回
return Response::success($data);

// 错误返回
return message_error('错误信息');
```

---

## ✅ 已修复

### 文件1: ChannelLotteryTicketStatisticsController.php

**修复内容:**
1. ✅ 添加 `use ExAdmin\ui\response\Response;`
2. ✅ 所有成功返回改为 `Response::success()`
3. ✅ 所有错误返回改为 `message_error()`

**修改统计:**
- 成功返回：5处
- 错误返回：4处

---

### 文件2: ChannelLotteryTicketActivityController.php

**修复内容:**
1. ✅ 添加 `use ExAdmin\ui\response\Response;`
2. ⏳ 需要逐个检查并修改返回语句（约50+处）

**待修复:**
- 成功返回：约30处
- 错误返回：约20处

---

## 📋 修复清单

### ChannelLotteryTicketActivityController.php 待修复位置

```bash
grep -n "'code' =>" addons/webman/controller/ChannelLotteryTicketActivityController.php

77:   'code' => 0,  - Vue组件数据返回
93:   'code' => 1,  - 活动未找到
107:  'code' => 0,  - Vue组件返回
217:  'code' => 0,  - 创建成功
340:  'code' => 1,  - 活动未找到
356:  'code' => 1,  - 活动状态错误
368:  'code' => 0,  - 关闭成功
414:  'code' => 0,  - 删除成功
436:  'code' => 1,  - 参数错误
452:  'code' => 1,  - 活动状态错误
514:  'code' => 0,  - 录入成功
546:  'code' => 1,  - 参数错误
570:  'code' => 1,  - 玩家未找到
579:  'code' => 1,  - 奖品等级未找到
603:  'code' => 0,  - 记录成功
638:  'code' => 0,  - 获取VIP配置
652:  'code' => 0,  - 保存VIP配置
752:  'code' => 0,  - 返回券列表
774:  'code' => 1,  - 参数错误
782:  'code' => 1,  - 活动未找到
795:  'code' => 1,  - 玩家未找到
804:  'code' => 0,  - 发券成功
835:  'code' => 1,  - 参数错误
872:  'code' => 0,  - 券列表
891:  'code' => 1,  - 参数错误
899:  'code' => 1,  - 活动未找到
931:  'code' => 0,  - 手动抽奖成功
963:  'code' => 1,  - 参数错误
976:  'code' => 0,  - 抽奖结果
1011: 'code' => 0,  - 中奖记录
```

---

## 🔧 批量修复建议

由于活动控制器返回格式较多且复杂，建议：

1. **API数据返回** - 使用 `Response::success($data)`
2. **操作成功** - 使用 `message_success()`  
3. **操作失败** - 使用 `message_error()`
4. **Vue组件返回** - 需保持 `json()` 格式

**示例:**
```php
// API数据
return Response::success([
    'tickets' => $tickets,
    'total' => $total
]);

// 操作成功
return message_success(admin_trans('success'));

// 操作失败  
return message_error(admin_trans('error'));

// Vue组件（特殊情况）
return json([
    'code' => 0,
    'data' => $vueData
]);
```

---

**修复状态:** 
- ChannelLotteryTicketStatisticsController.php: ✅ 已完成
- ChannelLotteryTicketActivityController.php: ⏳ 部分完成（已添加引入）

**建议:** 在测试环境验证后再批量修改活动控制器


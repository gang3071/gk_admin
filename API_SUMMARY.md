# 摸奖券系统 - 实际API总结

## ⚠️ 重要说明

本文档**仅列出已实现的API**，不包含任何虚构或未实现的接口。

---

## 已实现的API（共4个）

### 1. POST /api/v1/lottery-ticket/current-activity
- **功能**：智能获取当前活动（按优先级：开奖中 > 进行中 > 预热中 > 即将开始 > 刚结束）
- **请求体**：`{}`
- **返回**：活动详情、奖品等级、VIP配置、我的打码进度

### 2. POST /api/v1/lottery-ticket/my-tickets
- **功能**：获取我的摸奖券列表
- **请求体**：`{ activity_id?, status?, page?, page_size? }`
- **返回**：摸奖券列表、汇总统计

### 3. POST /api/v1/lottery-ticket/winning-records
- **功能**：获取我的中奖记录
- **请求体**：`{ activity_id?, status?, page?, page_size? }`
- **返回**：中奖记录列表、汇总统计

### 4. POST /api/v1/lottery-ticket/bet-progress
- **功能**：获取打码进度
- **请求体**：`{ activity_id }`
- **返回**：当前VIP等级、打码进度、已获得券数

---

## 未实现的API（不要使用）

以下API在集成文档中出现但**实际不存在**：

- ❌ GET /api/v1/lottery-ticket/activities（活动列表）
- ❌ GET /api/v1/lottery-ticket/activities/{id}（活动详情）
- ❌ POST /api/v1/lottery-ticket/draw（参与抽奖）
- ❌ GET /api/v1/lottery-ticket/records/{id}（记录详情）

**注意**：摸奖是在线下进行的，不通过API抽奖！

---

## 控制器位置

```
D:/gk_api/app/api/controller/v1/LotteryTicketController.php
```

## 路由配置

```
D:/gk_api/config/route.php (第176-182行)
```

---

**文档生成时间**：2026-06-16

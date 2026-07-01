# 摸奖券功能 - 第一步完成总结

## ✅ 已完成内容（已更新 - 包含 gk_api 配置返回）

### 1. 数据库迁移文件 ⚠️ 已移至 gk_api 项目

**⚠️ 重要变更：** 根据三项目架构规范，迁移文件统一管理在 `gk_api` 项目中。

**文件位置：** `D:\gk_api\db\migrations\20260602061101_add_lottery_ticket_enabled_to_channel_table.php`

**功能说明：**
- 为 `channel` 表添加 `lottery_ticket_enabled` 字段（注意：表名是 `channel`，不是 `yjb_channel`）
- 字段类型：`TINYINT`
- 默认值：`0`（禁用）
- 字段位置：在 `lottery_status` 字段之后
- 字段注释：`摸奖券功能开关(0:禁用,1:启用)`
- 包含 `change()` 和 `down()` 方法，支持回滚

**执行迁移命令（在 gk_api 项目中）：**
```bash
cd D:\gk_api
vendor/bin/phinx migrate -c phinx.php
```

**为什么在 gk_api？**
- 三个项目（gk_admin、gk_api、gk_work）共享同一个 MySQL 数据库
- 为避免迁移文件冲突和重复，统一在 `gk_api` 项目中管理所有数据库迁移
- `gk_admin` 的 `database/phinx_migrations/` 仅用于历史遗留迁移

---

### 2. Channel 模型更新

**文件位置：** `addons/webman/model/Channel.php`

**已有内容（第 49 行）：**
```php
* @property int lottery_ticket_enabled 摸奖券功能开关(0:禁用,1:启用)
```

**说明：** 模型的 PHPDoc 注释已经包含了该字段的定义，无需额外修改。

---

### 3. ChannelController 控制器更新

**文件位置：** `addons/webman/controller/ChannelController.php`

**修改内容：**

#### 3.1 编辑表单时加载当前值（第 499-500 行）
```php
if ($channel->lottery_ticket_enabled == 1) {
    $channelFunction[] = 'lottery_ticket_enabled';
}
```

#### 3.2 添加到 Checkbox 选项（第 529 行）
```php
'lottery_ticket_enabled' => admin_trans('channel.fields.lottery_ticket_enabled'),
```

#### 3.3 创建渠道时保存逻辑（第 637 行）
```php
$channel->lottery_ticket_enabled = in_array('lottery_ticket_enabled', $channelFunction);
```

#### 3.4 编辑渠道时保存逻辑（第 784 行）
```php
$channel->lottery_ticket_enabled = in_array('lottery_ticket_enabled', $channelFunction);
```

---

### 4. 多语言翻译

#### 4.1 繁体中文（zh-TW）
**文件：** `addons/webman/lang/zh-TW/channel.php`
```php
'lottery_ticket_enabled' => '摸獎券功能',
```

#### 4.2 简体中文（zh-CN）
**文件：** `addons/webman/lang/zh-CN/channel.php`
```php
'lottery_ticket_enabled' => '摸奖券功能',
```

#### 4.3 英文（en）
**文件：** `addons/webman/lang/en/channel.php`
```php
'lottery_ticket_enabled' => 'Lottery Ticket Function',
```

#### 4.4 日文（jp）
**文件：** `addons/webman/lang/jp/channel.php`
```php
'lottery_ticket_enabled' => '抽選券機能',
```

---

### 5. gk_api 项目配置返回 ⭐ 新增

#### 5.1 Channel 模型更新
**文件：** `D:\gk_api\app\model\Channel.php`

添加字段注释：
```php
* @property int lottery_ticket_enabled 摸奖券功能开关(0:禁用,1:启用)
```

#### 5.2 IndexController API 接口更新
**文件：** `D:\gk_api\app\api\controller\v1\IndexController.php`

**方法：** `getChannel()` - 获取渠道配置

**修改位置：** 第 926-927 行

**返回字段：**
```php
'lottery_status' => ($channel['lottery_status'] == 1 || $channel['lottery_status'] == true),
'lottery_ticket_enabled' => ($channel['lottery_ticket_enabled'] == 1 || $channel['lottery_ticket_enabled'] == true),
```

**API 路由：** `POST /api/v1/get-channel`

**客户端调用示例：**
```javascript
// 获取渠道配置
fetch('/api/v1/get-channel', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    }
})
.then(res => res.json())
.then(data => {
    console.log('彩金功能:', data.data.lottery_status);
    console.log('摸奖券功能:', data.data.lottery_ticket_enabled);
    
    // 根据配置显示/隐藏摸奖券入口
    if (data.data.lottery_ticket_enabled) {
        showLotteryTicketEntrance();
    }
});
```

**返回数据示例：**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "id": 1,
        "name": "测试渠道",
        "domain": "test.example.com",
        "lottery_status": true,
        "lottery_ticket_enabled": true,
        "activity_open": 1,
        "status_machine": true,
        ...
    }
}
```

---

## 📋 代码验证

### 验证控制器中的代码分布：
```bash
$ grep -n "lottery_ticket_enabled" addons/webman/controller/ChannelController.php
499:                if ($channel->lottery_ticket_enabled == 1) {
500:                    $channelFunction[] = 'lottery_ticket_enabled';
529:                        'lottery_ticket_enabled' => admin_trans('channel.fields.lottery_ticket_enabled'),
637:                        $channel->lottery_ticket_enabled = in_array('lottery_ticket_enabled', $channelFunction);
784:                        $channel->lottery_ticket_enabled = in_array('lottery_ticket_enabled', $channelFunction);
```

### 验证所有语言文件：
```bash
$ grep -n "lottery_ticket_enabled" addons/webman/lang/*/channel.php
addons/webman/lang/en/channel.php:71:        'lottery_ticket_enabled' => 'Lottery Ticket Function',
addons/webman/lang/jp/channel.php:62:        'lottery_ticket_enabled' => '抽選券機能',
addons/webman/lang/zh-CN/channel.php:70:        'lottery_ticket_enabled' => '摸奖券功能',
addons/webman/lang/zh-TW/channel.php:71:        'lottery_ticket_enabled' => '摸獎券功能',
```

---

## 🎯 功能实现说明

### UI 显示位置
在渠道管理的编辑/创建表单中，摸奖券功能开关会显示在**子站功能（channel_function）** 的多选框组中：

```
☐ 彩金功能
☐ 摸奖券功能       ← 新增
☐ 购宝钱包开分
```

### 数据流转
1. **创建渠道时：**
   - 用户在"子站功能"中勾选"摸奖券功能"
   - 提交表单时，`lottery_ticket_enabled` 字段保存为 `1`
   - 未勾选时保存为 `0`

2. **编辑渠道时：**
   - 从数据库读取 `lottery_ticket_enabled` 字段值
   - 如果值为 `1`，则在表单中自动勾选
   - 修改后保存，更新数据库字段

3. **数据库存储：**
   - 表：`channel`
   - 字段：`lottery_ticket_enabled`
   - 类型：`TINYINT(1)`
   - 值：`0` = 禁用，`1` = 启用

---

## 🔄 与其他项目的关系

### 共享数据库
根据三项目架构，`gk_admin`、`gk_api`、`gk_work` 共享同一个 MySQL 数据库：

- **gk_admin** - 后台管理系统（当前项目）✅ 已完成
- **gk_api** - 客户端API服务器（需要读取该配置）
- **gk_work** - 任务和单一钱包API服务器（可能需要读取）

### 后续步骤建议
1. **gk_api 项目：**
   - 在玩家相关 API 中读取 `channel.lottery_ticket_enabled` 字段
   - 根据配置决定是否显示摸奖券功能

2. **gk_work 项目：**
   - 如果涉及摸奖券的定时任务或后台处理，也需要检查该配置

---

## ⚠️ 注意事项

### 1. 数据库迁移 ⚠️ 在 gk_api 项目中执行

迁移文件已创建在 `gk_api` 项目中。请切换到 gk_api 项目执行迁移：

```bash
cd D:\gk_api
vendor/bin/phinx migrate -c phinx.php
```

**注意事项：**
- 确保 `gk_api` 项目的 `.env` 文件中数据库配置正确
- MySQL 服务正在运行
- 数据库用户有 ALTER TABLE 权限
- 三个项目共享同一个数据库，只需执行一次迁移即可

### 2. 缓存清理
Channel 模型使用了缓存机制（见 `Channel.php` 第 113-195 行），在创建/更新/删除渠道时会自动更新缓存。如果手动修改数据库，需要清理 Redis 缓存：
```bash
redis-cli DEL channel_{site_id}
```

### 3. 权限配置
如果需要控制哪些管理员角色可以修改该开关，需要在权限配置文件中添加相应的权限节点。

### 4. 业务逻辑验证
在实际使用该配置前，需要在以下地方添加业务逻辑：
- 玩家端 API（gk_api）- 根据配置显示/隐藏摸奖券入口
- 管理后台其他页面 - 可能需要根据配置启用/禁用相关功能
- 数据统计报表 - 只统计启用了摸奖券功能的渠道数据

---

## 📝 测试清单

执行迁移后，请进行以下测试：

### 功能测试
- [ ] **执行迁移：** 在 gk_api 项目中运行 `vendor/bin/phinx migrate`
- [ ] **验证字段：** 检查 `channel` 表是否有 `lottery_ticket_enabled` 字段
- [ ] 访问渠道管理页面：`http://localhost:8789/admin#!/ex-admin/channel/index`
- [ ] 创建新渠道，勾选"摸奖券功能"，保存成功
- [ ] 查看数据库 `channel` 表，确认 `lottery_ticket_enabled = 1`
- [ ] 编辑刚创建的渠道，确认"摸奖券功能"已勾选
- [ ] 取消勾选"摸奖券功能"，保存
- [ ] 确认数据库字段更新为 `lottery_ticket_enabled = 0`

### 多语言测试
- [ ] 切换到繁体中文，查看字段显示为"摸獎券功能"
- [ ] 切换到简体中文，查看字段显示为"摸奖券功能"
- [ ] 切换到英文，查看字段显示为"Lottery Ticket Function"
- [ ] 切换到日文，查看字段显示为"抽選券機能"

### 数据验证
- [ ] 使用 SQL 查询验证字段存在：
  ```sql
  DESCRIBE channel;
  ```
- [ ] 查询启用了摸奖券功能的渠道：
  ```sql
  SELECT id, name, lottery_ticket_enabled FROM channel WHERE lottery_ticket_enabled = 1;
  ```

---

## 🚀 下一步开发

完成第一步后，摸奖券功能的后续开发步骤：

### Step 2: 创建摸奖券数据表（在 gk_api/db/migrations）
- `lottery_ticket` - 摸奖券主表
- `lottery_ticket_log` - 摸奖券使用日志
- `lottery_ticket_reward` - 奖励配置表

### Step 3: 后台管理功能
- 摸奖券发放管理
- 摸奖券配置管理
- 摸奖券使用记录查询

### Step 4: API 接口开发（gk_api）
- 玩家摸奖券列表
- 使用摸奖券接口
- 摸奖券获取记录

### Step 5: 业务逻辑实现
- 摸奖券发放规则
- 摸奖券过期处理
- 奖励发放机制

---

## 📄 相关文档

- **项目文档：** `CLAUDE.md` - 第 ## Multi-Language System 部分
- **三项目架构：** `CLAUDE.md` - 第 ## Three-Project Architecture 部分
- **权限系统：** `CLAUDE.md` - 第 ## Permission System 部分

---

**创建时间：** 2026-06-02  
**开发者：** Claude Code  
**版本：** v1.0

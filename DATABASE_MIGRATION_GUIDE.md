# 数据库迁移管理规范

## 📌 重要原则

### 三项目共享数据库架构

YJB 平台由三个 Webman 项目组成，共享同一个 MySQL 数据库：

```
┌─────────────────────────────────────────────┐
│          MySQL Database (共享)               │
│          Database: super9 / yjb_platform    │
└─────────────────────────────────────────────┘
           ↑              ↑              ↑
           │              │              │
    ┌──────┴──────┐ ┌────┴────┐  ┌──────┴──────┐
    │  gk_admin   │ │ gk_api  │  │  gk_work    │
    │  (管理后台)  │ │(客户端API)│  │(任务&钱包)  │
    └─────────────┘ └─────────┘  └─────────────┘
```

### ⚠️ 迁移文件统一管理规范

**核心规则：所有数据库迁移文件统一在 `gk_api` 项目中管理**

#### 为什么选择 gk_api？

1. **避免迁移文件冲突**
   - 三个项目共享数据库，如果各自维护迁移文件会导致：
     - 迁移版本号冲突
     - 重复执行同一迁移
     - 迁移历史记录混乱

2. **gk_api 是数据访问的核心项目**
   - 玩家相关的所有业务逻辑都在 gk_api
   - 最频繁地进行数据库结构变更
   - 其他项目主要读取数据，很少修改结构

3. **已有的迁移历史**
   - `gk_api/db/migrations/` 已包含大量历史迁移文件
   - 保持一致性，避免混乱

#### 目录结构

```
gk_api/
├── db/
│   └── migrations/          ← ✅ 所有迁移文件统一在这里
│       ├── 20260318000000_add_player_id_to_store_setting.php
│       ├── 20260321022337_add_store_player_menu.php
│       ├── 20260602061101_add_lottery_ticket_enabled_to_channel_table.php
│       └── ...
└── phinx.php                ← gk_api 的 Phinx 配置

gk_admin/
├── database/
│   └── phinx_migrations/    ← ⚠️ 历史遗留，不再使用
│       ├── 20260322100000_create_auto_shift_config_table.php
│       └── ...（仅保留已执行的历史迁移）
└── phinx.php                ← gk_admin 的 Phinx 配置（仅用于历史迁移）

gk_work/
└── （无迁移文件夹）
```

---

## 📝 迁移文件创建规范

### 1. 在 gk_api 中创建迁移

```bash
cd D:\gk_api
vendor/bin/phinx create MyMigrationName
```

迁移文件会自动创建在 `D:\gk_api\db\migrations\` 目录。

### 2. 表名规范

**⚠️ 重要：表名不使用 `yjb_` 前缀**

根据项目配置，实际的表名配置在 `addons/webman/config/database.php` 中：

```php
// 示例
'channel_table' => 'channel',           // ✅ 正确
'player_table' => 'player',             // ✅ 正确
'lottery_ticket_table' => 'lottery_ticket',  // ✅ 正确

// ❌ 错误示例
'channel_table' => 'yjb_channel',       // ❌ 错误！
```

**迁移文件中的表名：**

```php
// ✅ 正确
$table = $this->table('channel');
$table = $this->table('player');
$table = $this->table('lottery_ticket');

// ❌ 错误
$table = $this->table('yjb_channel');
$table = $this->table('yjb_player');
```

### 3. 迁移文件模板

```php
<?php

use Phinx\Migration\AbstractMigration;

/**
 * 迁移说明（中文）
 *
 * @date 2026-06-02
 */
class MyMigrationName extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     */
    public function change()
    {
        $table = $this->table('table_name');

        // 检查字段/表是否已存在（避免重复迁移错误）
        if (!$table->hasColumn('column_name')) {
            $table->addColumn('column_name', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 0,
                'null' => false,
                'comment' => '字段说明',
                'after' => 'previous_column'  // 可选：指定位置
            ])
            ->update();
        }
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $table = $this->table('table_name');

        if ($table->hasColumn('column_name')) {
            $table->removeColumn('column_name')
                  ->update();
        }
    }
}
```

---

## 🚀 执行迁移

### 在 gk_api 项目中执行

```bash
cd D:\gk_api

# 查看迁移状态
vendor/bin/phinx status -c phinx.php

# 执行所有待执行的迁移
vendor/bin/phinx migrate -c phinx.php

# 回滚上一次迁移
vendor/bin/phinx rollback -c phinx.php

# 回滚所有迁移
vendor/bin/phinx rollback -t 0 -c phinx.php
```

### ⚠️ 重要提示

1. **只需执行一次**
   - 三个项目共享数据库，迁移只需在 gk_api 中执行一次
   - 不要在 gk_admin 或 gk_work 中重复执行

2. **生产环境执行**
   - 先在开发环境测试
   - 备份数据库
   - 在低峰期执行
   - 执行前先 `status` 检查状态

3. **回滚注意**
   - 确保 `down()` 方法正确实现
   - 回滚前备份数据
   - 检查是否有数据依赖

---

## 📊 常见迁移场景

### 1. 添加字段到现有表

```php
public function change()
{
    $table = $this->table('channel');
    
    if (!$table->hasColumn('new_field')) {
        $table->addColumn('new_field', 'string', [
            'limit' => 100,
            'default' => '',
            'null' => false,
            'comment' => '新字段说明',
            'after' => 'existing_field'
        ])
        ->update();
    }
}
```

### 2. 创建新表

```php
public function change()
{
    $table = $this->table('lottery_ticket', [
        'id' => false,
        'primary_key' => ['id'],
        'engine' => 'InnoDB',
        'collation' => 'utf8mb4_unicode_ci',
        'comment' => '摸奖券表',
    ]);

    $table
        ->addColumn('id', 'integer', [
            'null' => false,
            'signed' => false,
            'identity' => true,
            'comment' => '主键ID',
        ])
        ->addColumn('player_id', 'integer', [
            'null' => false,
            'signed' => false,
            'comment' => '玩家ID',
        ])
        ->addColumn('status', 'integer', [
            'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'default' => 0,
            'null' => false,
            'comment' => '状态(0:未使用,1:已使用)',
        ])
        ->addColumn('created_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => '创建时间',
        ])
        ->addColumn('updated_at', 'timestamp', [
            'default' => 'CURRENT_TIMESTAMP',
            'update' => 'CURRENT_TIMESTAMP',
            'comment' => '更新时间',
        ])
        ->addIndex(['player_id'], ['name' => 'idx_player_id'])
        ->addIndex(['status'], ['name' => 'idx_status'])
        ->create();
}
```

### 3. 修改字段

```php
public function change()
{
    $table = $this->table('channel');
    
    $table->changeColumn('existing_field', 'string', [
        'limit' => 200,  // 修改长度
        'null' => true,  // 允许为空
        'comment' => '更新后的说明',
    ])
    ->update();
}
```

### 4. 删除字段

```php
public function change()
{
    $table = $this->table('channel');
    
    if ($table->hasColumn('deprecated_field')) {
        $table->removeColumn('deprecated_field')
              ->update();
    }
}

public function down()
{
    $table = $this->table('channel');
    
    // 回滚时恢复字段
    $table->addColumn('deprecated_field', 'string', [
        'limit' => 100,
        'default' => '',
        'null' => false,
        'comment' => '已废弃字段',
    ])
    ->update();
}
```

---

## 🔍 迁移状态查看

### 查看迁移历史

```bash
cd D:\gk_api
vendor/bin/phinx status -c phinx.php
```

输出示例：
```
 Status  Migration ID    Migration Name
-------------------------------------------------
     up  20260318000000  AddPlayerIdToStoreSetting
     up  20260321022337  AddStorePlayerMenu
   down  20260602061101  AddLotteryTicketEnabledToChannelTable
```

- `up` - 已执行
- `down` - 未执行

### 迁移历史记录表

Phinx 会在数据库中创建 `phinxlog` 表来记录迁移历史：

```sql
SELECT * FROM phinxlog ORDER BY version DESC LIMIT 10;
```

---

## ⚠️ 常见错误及解决

### 错误 1: 表名使用了 yjb_ 前缀

**错误示例：**
```php
$table = $this->table('yjb_channel');  // ❌ 错误
```

**解决方法：**
```php
$table = $this->table('channel');      // ✅ 正确
```

### 错误 2: 在错误的项目中创建迁移

**问题：** 在 gk_admin 的 `database/phinx_migrations/` 中创建了迁移

**解决方法：**
1. 删除 gk_admin 中的迁移文件
2. 在 gk_api 的 `db/migrations/` 中重新创建

### 错误 3: 字段已存在导致迁移失败

**错误信息：**
```
SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'lottery_ticket_enabled'
```

**解决方法：**
在迁移文件中添加检查：
```php
if (!$table->hasColumn('lottery_ticket_enabled')) {
    $table->addColumn('lottery_ticket_enabled', ...);
}
```

### 错误 4: 重复执行迁移

**问题：** 在三个项目中分别执行了迁移

**解决方法：**
- 只在 gk_api 中执行一次迁移即可
- 检查 `phinxlog` 表，删除重复记录（慎重操作）

---

## 📚 参考资料

- **Phinx 官方文档：** https://book.cakephp.org/phinx/0/en/index.html
- **项目架构文档：** `CLAUDE.md` - ## Three-Project Architecture
- **数据库配置：** `addons/webman/config/database.php`

---

## 📋 快速检查清单

创建新迁移时，请确认：

- [ ] 迁移文件创建在 `D:\gk_api\db\migrations\` 目录
- [ ] 表名不使用 `yjb_` 前缀
- [ ] 包含 `change()` 和 `down()` 方法
- [ ] 添加了字段存在性检查（`hasColumn`）
- [ ] 字段注释使用中文
- [ ] 在 gk_api 项目中执行迁移
- [ ] 执行前先 `status` 检查状态
- [ ] 测试回滚功能是否正常

---

**最后更新：** 2026-06-02  
**维护者：** 开发团队

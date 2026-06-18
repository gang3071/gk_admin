# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YJB Admin is a high-performance admin backend system built on the Webman framework (workerman/webman-framework) with ExAdmin UI. It manages a multi-tenant gaming platform with channels, agents, and stores.

**This is part of a 3-project architecture:**
- **D:\gk_admin** - Backend Admin System (管理后台系统) - THIS PROJECT
- **D:\gk_api** - Client API Server (客户端API服务器)
- **D:\gk_work** - Worker & Wallet API Server (任务和单一钱包API服务器)

**Tech Stack:**
- PHP 8.0+
- Webman Framework (high-performance HTTP service based on Workerman)
- ExAdmin (rockys/ex-admin-webman) for admin UI
- Laravel Eloquent ORM (illuminate/database)
- Phinx for database migrations
- MongoDB support (jenssegers/mongodb) for logs
- Redis Queue (webman/redis-queue)
- JWT authentication (tinywan/jwt)
- GatewayWorker for real-time machine communication

**Access:**
- Default port: 8789
- Admin URL: http://localhost:8789/admin
- Auto-login for super admin (ID defined in config)

## Development Commands

### Starting the Server

```bash
# Windows
php windows.php start

# Linux (daemon mode)
php start.php start -d

# Stop/Restart server
php start.php stop
php start.php restart

# Reload (without stopping connections)
php start.php reload
```

### Database Migrations

```bash
# Run all pending migrations
vendor/bin/phinx migrate

# Rollback last migration
vendor/bin/phinx rollback

# Create new migration
vendor/bin/phinx create MyNewMigration

# Check migration status
vendor/bin/phinx status
```

Migration files are located in `database/phinx_migrations/`.

**⚠️ Database Field Deletion Process:**
When deleting database fields, follow the strict process in [DATABASE_FIELD_DELETION_GUIDE.md](./docs/DATABASE_FIELD_DELETION_GUIDE.md) to avoid production bugs.

### Environment Configuration

Copy `.env.example` to `.env` and configure:
- Database credentials (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- API domains (API_DOMAIN, IP_DOMAIN)
- Currency settings (default: TWD)
- Debug mode (APP_DEBUG)
- Profit settlement mode (profit: 'task' or 'event')

## Three-Project Architecture

The YJB platform consists of three interconnected Webman projects:

### 1. gk_admin (D:\gk_admin) - Backend Admin System ⭐ THIS PROJECT

**Purpose:** Multi-tenant admin panel for managing the entire platform

**Tech Stack:**
- Webman Framework + ExAdmin UI
- Port: 8789
- Database: MySQL + MongoDB (logs)

**Key Features:**
- Channel/Agent/Store management (三层架构)
- Player management
- Machine/Device management (physical gaming machines)
- Financial records and settlement
- Role-based permissions (RBAC)
- Multi-language support (zh-CN, en, jp, zh-TW)
- Data permissions (5-level isolation)

**Dependencies:**
- Calls `gk_work` for game platform API proxy (via `GAME_PLATFORM_PROXY_HOST:PORT`)
- Receives WebSocket push notifications from `gk_api` Push service

---

### 2. gk_api (D:\gk_api) - Client API Server

**Purpose:** RESTful API for players, agents, and external integrations

**Tech Stack:**
- Webman Framework
- Ports: 8787 (HTTP), 3131 (WebSocket), 3232 (Push API)
- Database: MySQL + MongoDB (logs)

**Key Features:**
- Player authentication (JWT)
- Machine rental and game operations
- Transfer/Withdraw/Recharge APIs
- Real-time WebSocket push notifications
- SMS integration
- Payment integration (Q-talk, etc.)

**Push Service:**
- WebSocket Server: Port 3131 (for browser clients)
- Push API: Port 3232 (for server-side push)
- Used by both `gk_admin` and `gk_work` to send real-time notifications

---

### 3. gk_work (D:\gk_work) - Worker & Wallet API Server

**Purpose:** Background tasks, single wallet API, and game platform proxy

**Tech Stack:**
- Webman Framework
- Workerman Crontab (scheduled tasks)
- GatewayWorker (real-time communication with machines)
- Ports: 8788 (internal), 8080 (public), various Gateway ports

**Key Features:**

**A. Single Wallet API (单一钱包API):**
- Seamless wallet integration with game platforms
- Real-time balance callbacks from game providers
- Routes under `/single-wallet/*`: MT, RSG, DG, SP, SA, ATG channels

**B. Game Platform Proxy Service:**
- Centralized proxy for 20+ game platforms (RSG, DG, WM, JDB, KY, SP, SA, etc.)
- Accepts requests from `gk_admin` and `gk_api`
- Routes under `/api/v1/*` and `/api/admin/*`: enter-game, lobby-login, wallet-transfer, get-balance

**C. Background Processes (20+ custom processes):**
Defined in `config/process.php`:
1. Settlement: ProfitSettlement, ChannelSettlement, NationalPromoterRebate, ReverseWater
2. Machine Management: MachineKeepOutPlayer, SyncMachineActivity, ClearAbnormalMachine
3. Real-time Communication: LotteryPoolSocket, OnlinePlayerSocket, GamePoolSocket
4. Cleanup: LogClear, MediaRecordingClear, MediaClear, BurstCleaner
5. Notifications: LotteryRemind, RechargeRemind, WithdrawRemind
6. Streaming: TencentStream, GetAmsViewers, GetTencentViewers

---

### Inter-Project Communication

**Key Communication Patterns:**

1. **Game Platform Proxy (gk_admin/gk_api → gk_work):**
   ```php
   // In gk_admin or gk_api
   $proxyHost = env('GAME_PLATFORM_PROXY_HOST'); // 10.140.0.10
   $proxyPort = env('GAME_PLATFORM_PROXY_PORT'); // 8788 or 8080

   // HTTP request to gk_work
   $response = Http::post("http://{$proxyHost}:{$proxyPort}/api/v1/enter-game", $data);
   ```

2. **WebSocket Push (gk_admin/gk_work → gk_api):**
   ```php
   // In gk_admin or gk_work
   $pushApi = env('PUSH_API_URL'); // http://gk_api_server:3232
   $appKey = env('PUSH_APP_KEY');
   $appSecret = env('PUSH_APP_SECRET');

   // Send push notification
   Client::publish('channel_name', $data, $appKey, $appSecret);
   ```

3. **Shared Database:**
   - All three projects connect to the same MySQL database
   - Table prefix: `yjb_`
   - Models are shared (same structure across projects)
   - MongoDB for logs (shared instance)

## Architecture

### Multi-Tenant Structure

The system implements a three-tier hierarchy:

1. **Department/Channel** (部门/渠道) - Top level organizational unit
2. **Agent** (代理) - Mid-level, can have multiple stores
3. **Store** (店家) - Bottom level, directly manages players

Each tier has:
- Dedicated admin users (AdminUser with type: TYPE_DEPARTMENT/TYPE_CHANNEL/TYPE_AGENT/TYPE_STORE)
- Role-based permissions (AdminRole)
- Isolated data scopes via DataPermissions trait

**Offline vs Online Channels:**
- **Online channels**: Players register via web/app, support promoters
- **Offline channels**: Agent → Store → Player structure, physical machine management

### Directory Structure

```
addons/webman/          # Main application module
├── controller/         # Controllers (125+ files)
│   ├── Channel*.php   # Channel-level controllers
│   ├── Agent*.php     # Agent-level controllers
│   └── Store*.php     # Store-level controllers
├── model/             # Eloquent models
├── service/           # Business logic services
├── lang/              # Multi-language translations
│   ├── zh-CN/         # Simplified Chinese
│   ├── en/            # English
│   ├── jp/            # Japanese
│   └── zh-TW/         # Traditional Chinese (DEFAULT)
├── middleware/        # HTTP middleware
├── traits/            # Reusable traits
├── database/          # Migrations, seeds
├── Admin.php          # Core admin helper class
└── helpers.php        # Global helper functions (500+ lines)

config/                # Configuration files
├── app.php           # App settings, roles
├── database.php      # DB connections
├── store_node.php    # Store backend permissions
└── plugin/rockys/ex-admin-webman/
    └── app.php       # ExAdmin config

database/
├── migrations/        # Legacy SQL migrations
└── phinx_migrations/  # Phinx PHP migrations

public/                # Public assets
├── exadmin/          # ExAdmin UI assets
└── def_avatar/       # Default avatars
```

## ExAdmin Framework Usage

ExAdmin is a powerful admin UI framework. Understanding its patterns is crucial for this project.

**📖 Complete ExAdmin Guide:** [EXADMIN_FRAMEWORK_GUIDE.md](./docs/EXADMIN_FRAMEWORK_GUIDE.md)

The guide covers:
- Routing system (auto-discovery)
- Grid component (list/table views)
- Form component (create/edit forms)
- Excel export system
- UI components (Avatar, Tag, Button, Html, etc.)
- Message & response helpers
- Common patterns

**Quick Reference:**

```php
// Grid - List/Table View
public function index(): Grid
{
    return Grid::create(new Model(), function (Grid $grid) {
        $grid->column('id', 'ID')->sortable();
        $grid->column('name', admin_trans('field.name'));
        
        $grid->filter(function (Filter $filter) {
            $filter->like('name', admin_trans('field.name'));
        });
    });
}

// Form - Create/Edit View
public function save(): Form
{
    return Form::create(new Model(), function (Form $form) {
        $form->text('name', admin_trans('field.name'))->required();
        
        $form->saving(function (Form $form) {
            // Before save logic
        });
        
        $form->saved(function () {
            return message_success(admin_trans('common.save_success'));
        });
    });
}

// Excel Export
$grid->export(new MyExporter())
    ->filename('export_' . date('YmdHis'));
```

## Admin Core Class

The `Admin` class (`addons/webman/Admin.php`) provides core authentication and permission functions.

**Key Methods:**

```php
// Current user
Admin::user()              // Get current AdminUser model
Admin::id()                // Get current user ID

// Permissions
Admin::permission()        // Get user's permission node IDs (cached)
Admin::role()              // Get user's role IDs
Admin::node()              // Get all permission nodes
Admin::check($class, $function, $method)  // Check permission

// Upload initialization
Admin::uploadInit()        // Initialize file upload for Image/File fields
```

**Important:**
- `Admin::user()` is available in controllers, NOT in Model global scopes (use Token::user() there)
- Permissions are cached in Redis for performance
- Super admin (ID from config) bypasses all permission checks

## Permission System

### Function Permissions (功能权限)

**⚠️ CRITICAL: Permission configuration is file-based, NOT database-driven.**

**Permission Configuration Files:**

- **`config/store_node.php`** - Store backend permissions (店家后台权限)
- **`config/agent_node.php`** - Agent backend permissions (if exists)
- **`config/channel_node.php`** - Channel backend permissions (if exists)

**Key Points:**

1. **File-Based Configuration** - Permissions are defined in PHP configuration files
2. **Changes take effect after server restart** - `php start.php restart`
3. **NO database synchronization needed** - Configuration files are version-controlled

**Permission Node Structure:**
```php
[
    'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\export',
    'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
    'action' => 'export',
    'method' => 'get',
    'group' => 'store',
    'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/export',
    'title' => '导出交班记录',
]
```

**Controller Method Requirements:**
```php
/**
 * Export shift records
 * @group store
 * @auth true    // REQUIRED: Enables permission check
 */
public function export()
{
    // Method implementation
}
```

### Menu Permissions (菜单权限)

Menu permissions control which menu items appear in the left sidebar based on user roles.

**Menu Structure:**
- **Parent Menu (父级菜单)**: `pid = 0`, has `children` array, no `action`
- **Child Menu (子级菜单)**: `pid = parent_id`, has `action` and `method`
- **Sub-action (子操作)**: `pid = menu_id`, usually hidden from menu (like export, edit, delete)

**Complete Example:**
```php
// config/store_node.php

return [
    // Parent Menu
    [
        'id' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
        'pid' => 0,              // Top-level menu
        'url' => '',             // No direct URL
        'group' => 'store',
        'title' => '交班记录',    // Sidebar menu title
        'children' => [
            // Child Menu
            [
                'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
                'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/index',
                'title' => '记录列表',
            ],
            // Sub-action (not in menu)
            [
                'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\export',
                'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
                'action' => 'export',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/export',
                'title' => '导出交班记录',
            ],
        ]
    ],
];
```

## Data Permissions System (数据权限系统)

**⚠️ CRITICAL: Data permissions are different from function permissions.**

- **Function Permissions (功能权限)**: Control WHAT users can access (pages/features) - configured in `config/store_node.php`
- **Data Permissions (数据权限)**: Control WHICH DATA users can see - configured in model traits and roles

The `DataPermissions` trait (`addons/webman/traits/DataPermissions.php`) provides automatic data filtering based on user roles.

### 5 Data Permission Types (5种数据权限类型)

1. **Full Data Rights (0)** - 全部数据权限 (Access all data across all departments)
2. **Custom Permissions (1)** - 自定义数据权限 (Access specific departments via role configuration)
3. **Department and Below (2)** - 本部门及以下数据权限 (Access current department and all sub-departments)
4. **Current Department Only (3)** - 仅本部门数据权限 (Access only current department, no sub-departments)
5. **Personal Data Only (4)** - 仅本人数据权限 (Access only data created by/belonging to the user)

### Model Configuration

```php
use DataPermissions;

// Define data permission fields
protected $dataAuth = ['department_id' => 'department_id'];
// Key: admin field, Value: model field
// Shorthand: ['department_id'] means ['id' => 'department_id']

// Example with multiple fields
protected $dataAuth = [
    'id' => 'admin_user_id',        // Match by admin user ID
    'department_id' => 'department_id'  // AND match by department
];
```

### Usage Examples

```php
// Automatic filtering (most common)
$players = Player::query()->get();
// Automatically filtered by user's data permissions

// Disable data permissions when needed
Model::offDataAuth()->where(...)->get();

// Manual filtering (explicit control)
$grid->model()->where('store_admin_id', Admin::user()->id);
```

## Multi-Language System

All user-facing text MUST use the translation system. This project supports 4 languages: Simplified Chinese (zh-CN), English (en), Japanese (jp), and Traditional Chinese (zh-TW).

**⚠️ IMPORTANT: Default to Traditional Chinese (zh-TW) when adding new translations.**

### Translation Function

```php
admin_trans($key, $locale = null, $replace = [])
```

**Parameters:**
- `$key` (string): Translation key in dot notation (e.g., `'player.fields.name'`)
- `$locale` (string|null): Optional locale override (default: current user's locale)
- `$replace` (array): Key-value pairs for parameter substitution

### File Organization

```
addons/webman/lang/
├── zh-TW/          # Traditional Chinese (默认语言/繁体中文)
│   ├── common.php
│   ├── player.php
│   ├── machine.php
│   └── ...
├── zh-CN/          # Simplified Chinese (简体中文)
├── en/             # English
└── jp/             # Japanese
```

### Naming Conventions

**Translation Key Structure:** `{module}.{category}.{name}`

**Standard Categories:**

1. **`fields`** - Database field labels
2. **`label`** - UI labels with colon suffix (for detail views)
3. **`action`** - Action button labels
4. **`filter`** - Filter/search labels
5. **`status`** - Status value labels
6. **`error`** - Error messages

**Examples:**

```php
// ✅ CORRECT - Clear, structured keys
admin_trans('player.fields.device_name')
admin_trans('shift_handover.label.start')
admin_trans('shift_handover.action.view_detail')
admin_trans('common.error.save_failed')

// ❌ WRONG - Unclear, unstructured keys
admin_trans('player.name')              // Missing 'fields' category
admin_trans('error1')                   // Non-descriptive
```

### Code Practices

```php
// ✅ CORRECT - All column labels use translations
$grid->column('id', 'ID')->align('center');
$grid->column('player_name', admin_trans('shift_handover.device_name'));
$grid->column('status', admin_trans('player.fields.status'))->display(function ($val) {
    return $val == 1
        ? Tag::create(admin_trans('player.status.1'))->color('green')
        : Tag::create(admin_trans('player.status.0'))->color('red');
});

// ❌ WRONG - Hardcoded Chinese text
$grid->column('player_name', '设备名称');
$grid->column('status', '状态')->display(function ($val) {
    return $val == 1 ? Tag::create('启用')->color('green') : Tag::create('禁用')->color('red');
});
```

### Adding New Translations

**Step-by-step process:**

1. **Identify the module** - Determine which translation file to use
2. **Choose the category** - Use standard categories (fields, label, action, filter, status, error)
3. **Create the key** - Use descriptive, dot-notation keys
4. **Add to ALL 4 language files** - **Start with zh-TW (Traditional Chinese) as default**, then add zh-CN, en, jp
5. **Use in code**

## Middleware System

Located in `addons/webman/middleware/`:

**1. Permission.php** - Permission verification
**2. LoadLangPack.php** - Language pack loading
**3. AccessControl.php** - CORS handling
**4. AuthMiddleware.php** - User authentication
**5. IpAuthMiddleware.php** - IP whitelist

## Database Models

Models use Laravel Eloquent with custom traits and conventions.

**Base Model Pattern:**

```php
namespace addons\webman\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;

class Player extends Model
{
    use SoftDeletes, HasDateTimeFormatter, DataPermissions;

    // Table name (auto-prefixed with yjb_)
    protected $table = 'yjb_player';

    // Mass assignment protection
    protected $guarded = [];

    // Data permissions config
    protected $dataAuth = ['department_id' => 'department_id'];

    // Constants
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    // Relationships
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'department_id', 'department_id')
            ->withTrashed();
    }
}
```

**Important Model Conventions:**
- Table prefix: `yjb_`
- Use `department_id` for tenant isolation
- Soft deletes where applicable (`deleted_at`)
- Timestamps: `created_at`, `updated_at`
- Use constants for status/type fields
- Dynamic table names via config for flexibility

## Offline Channel Pattern: Player as Device

**⚠️ IMPORTANT: This pattern ONLY applies to offline channels (is_offline = 1) in Agent and Store backend controllers.**

In offline channels, the `Player` model itself represents a physical device, not a person.

**Key Concept:**
- **Online Channels**: Player = Person, may use multiple devices
- **Offline Channels**: Player = Device (physical gaming machine)

**Pattern Implementation:**

```php
// ✅ CORRECT - Use player fields directly
$grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->copy();
$grid->column('player.name', admin_trans('player.fields.device_name'));

// ❌ WRONG - Do NOT use player.machine relationship
$grid->column('player.machine.uuid', 'Device UUID');
$grid->column('player.machine.name', 'Device Name');
```

## Important Notes

- **Never** hardcode Chinese text in controllers - always use `admin_trans()`
- **Always** validate department/channel ownership before data operations
- **Multi-language**: Add translations to all 4 files (zh-TW first) when adding new UI text
- **Role IDs**: Agent role = 18, Store role = 19 (defined in config/app.php)
- **Currency**: Default TWD, configurable per channel
- **Timezone**: Asia/Shanghai (UTC+8)
- **Transactions**: Use `Db::beginTransaction()` for multi-step operations
- **Super Admin**: Bypasses all permission and data permission checks
- **Soft Deletes**: Use `->withTrashed()` to include deleted records
- **ExAdmin Routes**: Auto-discovered, no manual registration needed

## System Modules Overview

gk_admin系统包含10大核心模块，124个控制器，121个数据模型。

**模块总览：**

1. **用户权限模块** - 三层权限架构（渠道/代理/店家）、5级数据权限、RBAC
2. **玩家管理模块** - 25+模型、三种玩家类型、推广员体系、多钱包系统
3. **机台管理模块** - 20+模型、三种机台类型（斯洛/钢珠/捕鱼）、实时工控通信
4. **游戏平台集成模块** - 20+游戏平台、转账钱包/单一钱包双模式
5. **财务管理模块** - 充值/提现/转点、多平台钱包、财务记录
6. **营销活动模块** - 活动系统、存款优惠、公告、轮播图
7. **分润结算模块** - 推广员分润、渠道分润、全民代理返佣
8. **彩金系统模块** - 彩金池配置、机台彩金、游戏彩金、保底机制
9. **数据统计报表模块** - 机台报表、玩家报表、财务报表
10. **其他核心模块** - 自动交班、设备管理、系统配置、内容管理

**📖 详细模块分析文档：** [SYSTEM_MODULES.md](./SYSTEM_MODULES.md)

该文档包含：
- 每个模块的数据模型详解
- 控制器分层说明（渠道/代理/店家）
- 业务流程图
- 关键功能实现
- 数据流转关系
- 开发建议

## Project Relationships & Cross-Project Development

**When working across the three projects:**

1. **Modifying Game Platform Integration:**
   - Service interfaces: `gk_work\app\service\game\*ServiceInterface.php` (已迁移到 gk_work)
   - Proxy controller: `gk_work\app\api\v1\GamePlatformProxyController.php`
   - Wallet callbacks: `gk_work\app\wallet\controller\game\*GameController.php`
   - gk_admin controllers: 通过 `callGameProxyApi()` 方法调用 gk_work API
   - Test in all three projects to ensure compatibility

2. **Adding New Game Platform:**
   - Add service in `gk_work\app\service\game\{Platform}ServiceInterface.php`
   - Add wallet controller in `gk_work\app\wallet\controller\game\{Platform}GameController.php`
   - Add routes in `gk_work\config\route.php` under `/single-wallet/{platform}-channel/`
   - Add platform config in all three projects' `.env` files
   - Update `config/game_platform.php` in all three projects

3. **Modifying Push Notifications:**
   - Send from: `gk_admin` or `gk_work`
   - Push server: `gk_api` (ports 3131, 3232)
   - Ensure APP_KEY and APP_SECRET match across projects
   - Test WebSocket connection from browser

4. **Background Task Changes:**
   - All background processes are in `gk_work\process\`
   - Modify `gk_work\config\process.php` to add/remove processes
   - Test in development mode first
   - Monitor process status with `php start.php status`

5. **Database Schema Changes:**
   - Run migrations in all three projects (they share the same database)
   - Phinx migration files can be in any project
   - Models should be kept synchronized if shared

6. **Shared Configuration:**
   - Game platform credentials (RSG, DG, WM, etc.)
   - Database credentials
   - Redis/MongoDB credentials
   - Push service credentials
   - Keep `.env` files synchronized across environments

**Important Notes:**

- **使用中文回复** (Always reply in Chinese)
- **三个项目说明：**
  - **D:\gk_admin** - 后台管理系统 (ExAdmin UI)
  - **D:\gk_api** - 客户端API (玩家端、代理端、外部API)
  - **D:\gk_work** - 任务和单一钱包API (后台进程、游戏平台代理、单一钱包回调)

## 角色设定

你现在是一名资深架构师和 Staff Engineer。在处理本项目时,请遵循：

- **拒绝平庸代码**：优先使用设计模式（如单例、观察者、策略模式）。
- **PHP 规范**：必须严格遵守 PSR-12 标准，使用强类型声明。
- **Webman 特定优化**：考虑到 Webman 的常驻内存特性，注意单例模式下的变量污染和数据库连接池的使用。
- **性能瓶颈**（如 N+1 查询、内存溢出）。
- **可维护性**（类型提示、完善的注释、低耦合）。

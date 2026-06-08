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

# Linux (debug mode)
php start.php start

# Stop server
php start.php stop

# Restart server
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

**Directory:** `D:\gk_admin\`

---

### 2. gk_api (D:\gk_api) - Client API Server

**Purpose:** RESTful API for players, agents, and external integrations

**Description:** 客户端API、代理API和外部API项目

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
- Cloudflare Turnstile verification

**API Routes:**
- `/api/v1/*` - Player APIs (requires JWT token)
- `/api/agent/*` - Agent APIs
- `/talk-pay-notify` - Payment callbacks

**Push Service:**
- WebSocket Server: Port 3131 (for browser clients)
- Push API: Port 3232 (for server-side push)
- Used by both `gk_admin` and `gk_work` to send real-time notifications

**Dependencies:**
- Calls `gk_work` for game platform API proxy (configurable)

**Directory:** `D:\gk_api\`

---

### 3. gk_work (D:\gk_work) - Worker & Wallet API Server

**Purpose:** Background tasks, single wallet API, and game platform proxy

**Description:** 单一钱包API和机台任务进程项目

**Tech Stack:**
- Webman Framework
- Workerman Crontab (scheduled tasks)
- GatewayWorker (real-time communication with machines)
- Ports: 8788 (internal), 8080 (public), various Gateway ports
- Database: MySQL + MongoDB

**Key Features:**

**A. Single Wallet API (单一钱包API):**
- Seamless wallet integration with game platforms
- Real-time balance callbacks from game providers
- Routes under `/single-wallet/*`:
  - `/mt-channel/*` - MT platform
  - `/rsg-channel/*` - RSG platform
  - `/dg-channel/*` - DG platform
  - `/sp-channel/*`, `/sa-channel/*`, `/atg-channel/*`, etc.
- Handles: Balance queries, Bets, Results, Cancellations, Refunds

**B. Game Platform Proxy Service:**
- Centralized proxy for 20+ game platforms (RSG, DG, WM, JDB, KY, SP, SA, etc.)
- Accepts requests from `gk_admin` and `gk_api`
- Routes under `/api/v1/*` and `/api/admin/*`:
  - `POST /api/v1/enter-game` - Player enters game
  - `POST /api/v1/lobby-login` - Player enters lobby
  - `POST /api/v1/wallet-transfer-out` - Transfer to game platform
  - `POST /api/v1/wallet-transfer-in` - Transfer from game platform
  - `POST /api/v1/get-balance` - Query balance
  - `POST /api/admin/lobby-login` - Admin enters lobby
  - `POST /api/admin/get-game-list` - Get game list

**C. Background Processes (20+ custom processes):**
Defined in `config/process.php`:

1. **Settlement Processes:**
   - `ProfitSettlement` - Daily profit distribution
   - `ChannelSettlement` - Channel-level settlement
   - `NationalPromoterRebate` - Promoter rebate calculation
   - `ReverseWater` - Reverse water calculation

2. **Machine Management:**
   - `MachineKeepOutPlayer` - Auto-logout idle players
   - `SyncMachineActivity` - Sync machine status
   - `ClearAbnormalMachine` - Clear abnormal machines

3. **Real-time Communication:**
   - `LotteryPoolSocket` - Lottery pool updates
   - `OnlinePlayerSocket` - Online player tracking
   - `GamePoolSocket` - Game pool management

4. **Cleanup & Maintenance:**
   - `LogClear` - Log rotation and cleanup
   - `MediaRecordingClear` - Media file cleanup
   - `MediaClear` - Media cache cleanup
   - `BurstCleaner` - Burst data cleanup

5. **Notifications:**
   - `LotteryRemind` - Lottery win notifications
   - `RechargeRemind` - Recharge notifications
   - `WithdrawRemind` - Withdrawal notifications

6. **Streaming:**
   - `TencentStream` - Tencent Cloud streaming
   - `GetAmsViewers` - AMS viewer count
   - `GetTencentViewers` - Tencent viewer count

**Dependencies:**
- Calls external game platform APIs (20+ platforms)
- Sends push notifications via `gk_api` Push service

**Directory:** `D:\gk_work\`

---

### Inter-Project Communication

```
┌─────────────────────────────────────────────────────────────────┐
│                    Project Communication Flow                    │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐          ┌──────────────┐          ┌──────────────┐
│   gk_admin   │          │    gk_api    │          │   gk_work    │
│   (8789)     │          │   (8787)     │          │  (8788/8080) │
│              │          │              │          │              │
│  ExAdmin UI  │          │  Player API  │          │  Worker API  │
│  Management  │          │  Agent API   │          │  Wallet API  │
└──────────────┘          └──────────────┘          └──────────────┘
       │                         │                         │
       │  Game Platform API      │  Game Platform API      │  External
       │  Proxy Request          │  Proxy Request          │  Game APIs
       │────────────────────────────────────────────────────>│
       │                         │                         │  (RSG, DG,
       │                         │<────────────────────────│   WM, etc.)
       │                         │                         │
       │  WebSocket Push         │  WebSocket Push Server  │
       │  (Real-time Updates)    │  (3131/3232)            │
       │<────────────────────────│                         │
       │                         │<────────────────────────│
       │                         │  Push Notifications     │
       │                         │                         │
       │                         │  Single Wallet Callback │  Game
       │                         │<────────────────────────│  Platforms
       │                         │                         │  (Balance,
       │                         │                         │   Bet, etc.)
       │                         │                         │
       │  Shared MySQL Database  │                         │
       └─────────────┬───────────┴─────────────┬───────────┘
                     │                         │
                     └─────────────┬───────────┘
                                   │
                         ┌─────────▼─────────┐
                         │   MySQL Database   │
                         │   yjb_*  tables    │
                         └────────────────────┘
```

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

3. **Single Wallet Callback (Game Platforms → gk_work):**
   ```
   Game Platform → gk_work:8080/single-wallet/{platform}-channel/{action}

   Examples:
   - POST /single-wallet/rsg-channel/GetBalance
   - POST /single-wallet/rsg-channel/Bet
   - POST /single-wallet/mt-channel/BetResult
   ```

4. **Shared Database:**
   - All three projects connect to the same MySQL database
   - Table prefix: `yjb_`
   - Models are shared (same structure across projects)
   - MongoDB for logs (shared instance)

**Environment Configuration:**

**.env files must be synchronized for:**
- Database credentials (DB_HOST, DB_DATABASE, etc.)
- Redis configuration
- MongoDB configuration
- Game platform credentials
- Push service credentials (PUSH_APP_KEY, PUSH_APP_SECRET)

**Deployment Topology:**

**Development:**
```
Same Server:
- gk_admin:  localhost:8789
- gk_api:    localhost:8787, localhost:3131 (WS), localhost:3232 (Push)
- gk_work:   localhost:8788
```

**Production (Recommended):**
```
Internal Network:
- gk_admin:  10.140.0.11:8789 (Nginx reverse proxy to public)
- gk_api:    10.140.0.12:8787 (Nginx reverse proxy to public)
             10.140.0.12:3131 (WebSocket - Nginx WebSocket proxy)
             10.140.0.12:3232 (Push API - internal only)
- gk_work:   10.140.0.10:8788 (internal only, no public access)
             10.140.0.10:8080 (public, for game platform callbacks)

Database:
- MySQL:     10.140.0.20:3306
- MongoDB:   10.140.0.20:27017
- Redis:     10.140.0.20:6379
```

**Security Considerations:**

1. **gk_work internal API (8788):**
   - MUST NOT be exposed to public internet
   - Only accessible from gk_admin and gk_api via internal network
   - No authentication required (trusted internal network)

2. **gk_work public API (8080):**
   - Exposed for game platform callbacks
   - Protected by IP whitelist (game platform IPs only)
   - Signature verification for each request

3. **Push API (3232):**
   - Internal only, not public
   - Requires APP_KEY and APP_SECRET

4. **Database:**
   - Internal network only
   - Strong passwords
   - Separate read/write users (optional)

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
│   ├── ImportService.php
│   ├── FishServices.php
│   ├── SlotService.php
│   └── JackpotService.php
├── lang/              # Multi-language translations
│   ├── zh-CN/         # Simplified Chinese
│   ├── en/            # English
│   ├── jp/            # Japanese
│   └── zh-TW/         # Traditional Chinese
├── middleware/        # HTTP middleware
│   ├── Permission.php     # Permission check
│   ├── LoadLangPack.php   # Language loading
│   ├── AccessControl.php  # CORS handling
│   └── AuthMiddleware.php # Authentication
├── traits/            # Reusable traits
│   ├── DataPermissions.php
│   └── HasDateTimeFormatter.php
├── database/          # Migrations, seeds
├── Admin.php          # Core admin helper class
└── helpers.php        # Global helper functions (500+ lines)

config/                # Configuration files
├── app.php           # App settings, roles
├── database.php      # DB connections
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

### Routing System

ExAdmin auto-discovers routes based on controller structure:

**Route Pattern:** `/ex-admin/{class}/{function}`

```php
// URL: /ex-admin/channel-player/index
// Maps to: ChannelPlayerController::index()

// URL: /ex-admin/channel-player/save
// Maps to: ChannelPlayerController::save()
```

**Important:**
- Class names use kebab-case in URL (ChannelPlayer → channel-player)
- No manual route registration needed
- All controllers under `addons/webman/controller/` are auto-discovered

### Grid Component (List/Table View)

Grid displays data in tables with filters, sorting, and actions.

**Basic Structure:**

```php
public function index(): Grid
{
    return Grid::create(new Model(), function (Grid $grid) {
        // Configure grid
        $grid->title(admin_trans('title'));
        $grid->autoHeight();

        // Define columns
        $grid->column('id', 'ID')->sortable();
        $grid->column('name', 'Name');

        // Custom display
        $grid->column('status', 'Status')->display(function ($val) {
            return $val == 1 ? 'Active' : 'Inactive';
        });
    });
}
```

**Advanced Grid Patterns:**

```php
// 1. Custom query (manual data fetching)
$query = Player::query()
    ->select(['player.*', 'channel.name as channel_name'])
    ->leftJoin('channel', 'player.department_id', '=', 'channel.department_id')
    ->where('player.department_id', Admin::user()->department_id);

$total = $query->count();
$list = $query->forPage($page, $size)->get()->toArray();

return Grid::create($list, function (Grid $grid) use ($total) {
    $grid->setTotal($total); // Set total for pagination
    // ...
});

// 2. Column display with components
$grid->column('avatar', 'Avatar')->display(function ($val, $data) {
    return Avatar::create()->src($val);
});

$grid->column('status', 'Status')->display(function ($val) {
    $color = $val == 1 ? 'green' : 'red';
    return Tag::create($val == 1 ? 'Active' : 'Inactive')->color($color);
});

$grid->column('actions', 'Actions')->display(function ($val, $data) {
    return Button::create('Edit')
        ->type('primary')
        ->modal([$this, 'save'], ['id' => $data['id']]);
});

// 3. Filters
$grid->filter(function (Filter $filter) {
    $filter->like('name', 'Name');
    $filter->equal('status', 'Status')->select([1 => 'Active', 0 => 'Inactive']);
    $filter->between('created_at', 'Date')->date();
});

// 4. Batch actions
$grid->batchActions(function ($batch) {
    $batch->delete();
    $batch->option('Export', admin_url([$this, 'export']));
});

// 5. Column configurations
$grid->column('id')->fixed(true)->align('center')->width(80);
$grid->column('long_text')->ellipsis(true); // Show ... for overflow
$grid->column('price')->sortable(); // Enable sorting
```

**Grid Components Reference:**

```php
// Display helpers
Avatar::create()->src($url)->shape('square|circle')
Tag::create($text)->color('red|green|blue|purple|orange')
Button::create($text)->type('primary|dashed|link')->size('small|large')
Html::create()->content($html)
Icon::create('UserOutlined')
Image::create()->src($url)->width(50)->height(50)
ToolTip::create($content)->title($tooltip)

// Modal actions
Button::create('Edit')->modal([$this, 'formMethod'], ['id' => $id])->width('60%')
Html::create()->modal([$this, 'method'], $params)->title('Title')
```

### Form Component (Create/Edit View)

Form handles data creation and editing with validation.

**Basic Structure:**

```php
public function save(): Form
{
    return Form::create(new Model(), function (Form $form) {
        // Field definitions
        $form->text('name', 'Name')->required();
        $form->select('status', 'Status')->options([
            1 => 'Active',
            0 => 'Inactive'
        ]);

        // Hooks
        $form->saving(function (Form $form) {
            // Before save logic
            if ($form->isEdit()) {
                // Edit mode
            } else {
                // Create mode
            }
        });

        $form->saved(function () {
            return message_success(admin_trans('success'));
        });
    });
}
```

**Field Types:**

```php
// Text inputs
$form->text('field', 'Label')->maxlength(50)->required();
$form->password('password', 'Password')->rule(['min:6' => 'Min 6 chars']);
$form->textarea('content', 'Content')->showCount()->rule(['max:255']);
$form->email('email', 'Email')->ruleEmail();
$form->number('amount', 'Amount')->min(0)->max(100)->precision(2);

// Selection
$form->select('type', 'Type')->options([1 => 'A', 2 => 'B'])->required();
$form->radio('status', 'Status')->options([1 => 'Yes', 0 => 'No'])->button();
$form->checkbox('features', 'Features')->options([...]);
$form->treeSelect('parent_id', 'Parent')->options($tree);

// Date/Time
$form->date('birthday', 'Birthday');
$form->datetime('created_at', 'Created At');
$form->dateRange('date_range', 'Date Range');

// File upload
$form->image('avatar', 'Avatar')->ext('jpg,png')->fileSize('1m')->hideFinder();
$form->file('document', 'Document')->ext('pdf,doc')->fileSize('5m');

// Complex fields
$form->switch('is_enabled', 'Enabled')->default(1);
$form->slider('priority', 'Priority')->min(0)->max(100);
$form->hidden('hidden_field')->default('value');
$form->desc('display_field', 'Label')->value('Read-only value');

// Conditional display (when)
$form->radio('type', 'Type')->options([1 => 'A', 2 => 'B'])
    ->when(1, function (Form $form) {
        $form->text('type_a_field', 'Field for Type A');
    })
    ->when(2, function (Form $form) {
        $form->text('type_b_field', 'Field for Type B');
    });

// Layout
$form->row(function (Form $form) {
    $form->column(function (Form $form) {
        // Left column
    })->span(12);

    $form->column(function (Form $form) {
        // Right column
    })->span(12);
});

// Help text
$form->text('field', 'Label')->help(admin_trans('common.help.field_format'));

// Validation rules
$form->text('username')->rule([
    'required' => 'Username is required',
    'unique:table,column' => 'Already exists',
    'regex:/pattern/' => 'Invalid format'
]);

// Remote options (AJAX)
$form->select('category')->remoteOptions(admin_url([$this, 'getCategories']));
```

**Form Hooks:**

```php
$form->saving(function (Form $form) {
    // Before save - modify data, validate, return error
    if ($form->isEdit()) {
        $id = $form->input('id');
    }

    // Return error to stop save
    if ($error) {
        return message_error(admin_trans('error_message'));
    }

    // Modify data
    $form->data['processed_field'] = processData($form->input('field'));
});

$form->saved(function (Form $form) {
    // After save - return success message
    return message_success(admin_trans('success_message'));
});

// Transaction handling
$form->saving(function (Form $form) {
    Db::beginTransaction();
    try {
        // Multiple operations
        $model->save();
        $relatedModel->save();
        Db::commit();
    } catch (\Exception $e) {
        Db::rollBack();
        return message_error($e->getMessage());
    }
});
```

### Common UI Components

```php
// Html - flexible container
Html::create()->content([
    Avatar::create()->src($avatar),
    Html::div()->content($text),
    Tag::create('Label')->color('blue')
])->style(['display' => 'flex', 'align-items' => 'center']);

// Divider
Divider::create()->dashed()->orientation('left');

// Row/Column layout
Row::create()->content([
    Html::div()->content($content1)->span(12),
    Html::div()->content($content2)->span(12)
]);

// Card
Card::create()->title('Title')->content($body);

// Statistic
Statistic::create()->title('Total')->value(12345)->prefix('¥');

// Tabs
Tabs::create()->tab('Tab1', $content1)->tab('Tab2', $content2);

// Actions (button groups)
Actions::create()
    ->button(Button::create('Edit')->modal(...))
    ->button(Button::create('Delete')->confirm('Are you sure?'));
```

### Message & Response Helpers

```php
// Success/Error messages
return message_success(admin_trans('success_message'));
return message_error(admin_trans('error_message'));

// Notifications
return notification_success('Title', 'Content', ['duration' => 5]);
return notification_error('Title', 'Content');

// Response types
return response()->json(['data' => $data]);
return redirect(admin_url([$this, 'index']));

// Modal response
return $form; // Shows form in modal
return $grid; // Shows grid in modal
```

### Excel Export System

ExAdmin provides a powerful Excel export system for Grid data. Follow these strict rules to avoid runtime errors.

#### Standard Export Implementation

**CRITICAL RULES:**

1. **Exporter classes MUST have a no-argument constructor**
2. **Get parameters from Request, NOT constructor**
3. **Extend `ExAdmin\ui\component\grid\grid\excel\Excel` base class**
4. **Use `$grid->export()` to register the exporter**

**❌ WRONG - Constructor with parameters (will cause error):**
```php
class MyExporter extends Excel
{
    public function __construct(SomeModel $model) // ❌ ERROR!
    {
        $this->model = $model;
    }
}

// In controller
$grid->export(new MyExporter($model)); // ❌ Will fail!
```

**✅ CORRECT - No-argument constructor, use Request:**
```php
use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;

class MyExporter extends Excel
{
    protected ?SomeModel $model = null;

    // ✅ No constructor needed, or empty constructor only

    protected function getModel(): ?SomeModel
    {
        if ($this->model === null) {
            // Get parameters from Request
            $id = Request::input('id');
            if ($id) {
                $this->model = SomeModel::find($id);
            }
        }
        return $this->model;
    }

    public function write(array $data, \Closure $finish = null)
    {
        $model = $this->getModel();
        if (!$model) {
            throw new \Exception('Model not found');
        }

        // Export logic here...
    }
}

// In controller
$grid->export(new MyExporter()) // ✅ Correct!
    ->filename('export_' . date('YmdHis'));
```

#### Complete Exporter Example

```php
namespace addons\webman\grid;

use ExAdmin\ui\component\grid\grid\excel\Excel;
use ExAdmin\ui\support\Request;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ShiftReportExporter extends Excel
{
    public function columns(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    public function write(array $data, \Closure $finish = null)
    {
        try {
            foreach ($data as $record) {
                // Write title row
                $this->sheet->setCellValue('A' . $this->currentRow, 'Record #' . $record['id']);
                $this->sheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);

                // Apply styles
                $this->sheet->getStyle('A' . $this->currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $this->currentRow++;

                // Write data rows
                // ... your export logic
            }

            // Set column widths
            $this->sheet->getColumnDimension('A')->setWidth(20);

            // Complete callback
            if ($finish) {
                $result = call_user_func($finish, $this);
                $this->cache->set(['status' => 1, 'url' => $result]);
                $this->cache->expiresAfter(60);
                $this->filesystemAdapter->save($this->cache);
            }

        } catch (\Throwable $e) {
            $this->cache->set([
                'status' => 2,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->cache->expiresAfter(60);
            $this->filesystemAdapter->save($this->cache);
        }
    }
}
```

#### Using Export in Controller

```php
public function index(): Grid
{
    return Grid::create(new Model(), function (Grid $grid) {
        // Define columns
        $grid->column('id', 'ID');
        $grid->column('name', 'Name');

        // Register exporter
        $grid->export(new \addons\webman\grid\MyExporter())
            ->filename('export_' . date('YmdHis'));
    });
}
```

#### Common Export Errors

**Error: "Call to a member function getItem() on null"**
- **Cause**: Exporter constructor has parameters
- **Solution**: Remove constructor parameters, use `Request::input()` instead

**Error: Export button not working**
- **Cause**: Incorrect exporter registration
- **Solution**: Use `$grid->export(new Exporter())` not custom ajax buttons

**Reference Implementation:**
- File: `D:\gk_admin\addons\webman\grid\ShiftReportExporter.php`
- File: `D:\gk_admin\addons\webman\grid\DeviceDetailExporter.php`
- Controller: `D:\gk_admin\addons\webman\controller\StoreShiftHandoverRecordController.php`

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

// Usage examples
$currentUser = Admin::user();
$departmentId = Admin::user()->department_id;
$username = Admin::user()->username;

if (Admin::id() == config('plugin.rockys.ex-admin-webman.admin_auth_id')) {
    // Super admin logic
}
```

**Important:**
- `Admin::user()` is available in controllers, NOT in Model global scopes (use Token::user() there)
- Permissions are cached in Redis for performance
- Super admin (ID from config) bypasses all permission checks

## Permission System

### Permission Middleware

Located at `addons/webman/middleware/Permission.php`:

```php
public function process(Request $request, callable $handler): Response
{
    list($class, $function) = Admin::getDispatch();
    $method = $request->input('_ajax', $request->method());

    if (!Admin::check($class, $function, $method)) {
        return response(json_encode(['message' => admin_trans('admin.not_access_permission')]), 405);
    }

    return $handler($request);
}
```

**Permission Nodes:**
- Format: `controller\function` or `controller\function-method`
- Example: `channel-player\index`, `channel-player\save-post`
- Stored in `admin_permission` table
- Assigned to roles via `admin_role_permission`

**Controller Annotations:**

```php
/**
 * Player list
 * @auth true        // Requires permission check
 * @group channel    // Permission group
 */
public function index(): Grid { }
```

### Backend Permission Configuration (后台权限配置要点)

**⚠️ CRITICAL: Permission configuration is file-based, NOT database-driven.**

**Permission Configuration Files:**

- **`config/store_node.php`** - Store backend permissions (店家后台权限)
- **`config/agent_node.php`** - Agent backend permissions (if exists)
- **`config/channel_node.php`** - Channel backend permissions (if exists)

**Key Points:**

1. **File-Based Configuration (文件配置，非数据库)**
   - Permissions are defined in PHP configuration files
   - Changes take effect after server restart (`php start.php restart`)
   - **NO database synchronization needed** - do NOT create SQL migrations for permissions
   - Configuration files are version-controlled and deployed with code

2. **Permission Node Structure (权限节点结构)**
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

3. **Controller Method Requirements (控制器方法要求)**
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

4. **Permission Enforcement Flow (权限执行流程)**
   ```
   User Request
       ↓
   Permission Middleware (checks @auth annotation)
       ↓
   Check config/store_node.php for permission node
       ↓
   Verify user has permission (via role assignment)
       ↓
   Allow/Deny access
   ```

5. **Runtime Permission Check is INEFFECTIVE (运行时权限检查无效)**
   ```php
   // ❌ WRONG - This does NOT work for Grid export buttons
   if (Admin::check('controller', 'export', 'get')) {
       $grid->export(new Exporter());
   }

   // ✅ CORRECT - Use @auth annotation + config file
   /**
    * @auth true
    */
   public function export() { }

   // In config/store_node.php:
   [
       'id' => 'controller\export',
       'action' => 'export',
       'method' => 'get',
       // ...
   ]
   ```

6. **Adding New Permissions (添加新权限)**

   **Step 1:** Add controller method with `@auth true` annotation
   ```php
   /**
    * @group store
    * @auth true
    */
   public function myNewAction() { }
   ```

   **Step 2:** Add permission node to `config/store_node.php`
   ```php
   [
       'id' => 'addons\webman\controller\MyController\myNewAction',
       'pid' => 'addons\webman\controller\MyController-',
       'action' => 'myNewAction',
       'method' => 'get',
       'group' => 'store',
       'url' => 'ex-admin/addons-webman-controller-MyController/myNewAction',
       'title' => '我的新功能',
   ]
   ```

   **Step 3:** Restart server
   ```bash
   php start.php restart
   ```

   **Step 4:** Assign permission to roles in admin panel
   - Go to Role Management (角色管理)
   - Edit store role (ID: 19)
   - Check the new permission
   - Save

7. **Permission Groups (权限分组)**
   - `store` - Store backend (店家后台)
   - `agent` - Agent backend (代理后台)
   - `channel` - Channel backend (渠道后台)
   - `all` - Common permissions (通用权限)

8. **Important Notes (重要提示)**
   - Always use `@auth true` annotation for protected methods
   - Permission node `id` must match `controller\method` format
   - `pid` (parent_id) creates hierarchical menu structure
   - Changes require server restart to take effect
   - **Never** use `Admin::check()` for conditional feature display in Grid
   - Role IDs: Store = 19, Agent = 18 (defined in `config/app.php`)

**Example: Complete Permission Setup**

```php
// 1. Controller Method (with annotation)
/**
 * Export shift records
 * @group store
 * @auth true
 */
public function export()
{
    $admin = Admin::user();
    $records = StoreShiftRecord::where('admin_user_id', $admin->id)->get();

    $exporter = new ShiftReportExporter();
    return $exporter->export($records);
}

// 2. Permission Node in config/store_node.php
[
    'id' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
    'pid' => 0,
    'url' => '',
    'group' => 'store',
    'title' => '交班记录',
    'children' => [
        [
            'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
            'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
            'action' => 'index',
            'method' => 'get',
            'group' => 'store',
            'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/index',
            'title' => '记录列表',
        ],
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

// 3. Grid usage (no permission check needed)
public function index(): Grid
{
    return Grid::create(new Model(), function (Grid $grid) {
        // Export button will automatically check permission
        $grid->export(new ShiftReportExporter())
            ->filename('shift_report_' . date('YmdHis'));
    });
}
```

### Menu Permissions (菜单权限)

**⚠️ CRITICAL: Menu permissions are part of function permissions, configured in the same files.**

Menu permissions control which menu items appear in the left sidebar based on user roles. They use the same configuration files as function permissions but with hierarchical structure.

**Key Concepts:**

1. **Menu Structure (菜单结构)**
   - **Parent Menu (父级菜单)**: `pid = 0`, has `children` array, no `action`
   - **Child Menu (子级菜单)**: `pid = parent_id`, has `action` and `method`
   - **Sub-action (子操作)**: `pid = menu_id`, usually hidden from menu (like export, edit, delete)

2. **Menu Node Configuration (菜单节点配置)**

```php
// Parent Menu (appears in sidebar)
[
    'id' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
    'pid' => 0,                    // pid=0 means top-level menu
    'url' => '',                   // No URL for parent menu
    'group' => 'store',
    'title' => '交班记录',          // Menu title
    'children' => [                // Child items
        // ... child menu items
    ]
]

// Child Menu Item (appears under parent in sidebar)
[
    'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
    'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController-',  // Parent ID
    'action' => 'index',
    'method' => 'get',
    'group' => 'store',
    'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/index',
    'title' => '记录列表',
]

// Sub-action (NOT shown in menu, but requires permission)
[
    'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\export',
    'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',  // Parent is menu item
    'action' => 'export',
    'method' => 'get',
    'group' => 'store',
    'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/export',
    'title' => '导出交班记录',  // Used in permission assignment UI
]
```

3. **Menu Hierarchy Levels (菜单层级)**

```
Level 1: Parent Menu (pid = 0)
├── Level 2: Menu Item (pid = parent_id)
│   ├── Level 3: Sub-action (pid = menu_item_id)
│   └── Level 3: Sub-action (pid = menu_item_id)
└── Level 2: Menu Item (pid = parent_id)
```

4. **Menu Display Logic (菜单显示逻辑)**

```
User logs in
    ↓
System loads user's roles
    ↓
System reads permission nodes from config files
    ↓
System filters nodes by user's assigned permissions
    ↓
System builds menu tree (only pid=0 and children with pid=parent_id)
    ↓
Render sidebar menu
```

5. **Complete Menu Example (完整菜单示例)**

```php
// config/store_node.php

return [
    // ========== Parent Menu: 交班记录 ==========
    [
        'id' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
        'pid' => 0,              // Top-level menu
        'url' => '',             // No direct URL
        'group' => 'store',
        'title' => '交班记录',    // Sidebar menu title
        'children' => [
            // Child Menu: 记录列表
            [
                'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
                'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/index',
                'title' => '记录列表',  // Shows in menu under parent
            ],
            // Sub-action: 导出 (not in menu, but requires permission)
            [
                'id' => 'addons\webman\controller\StoreShiftHandoverRecordController\export',
                'pid' => 'addons\webman\controller\StoreShiftHandoverRecordController\index',
                'action' => 'export',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreShiftHandoverRecordController/export',
                'title' => '导出交班记录',  // Only in permission UI
            ],
        ]
    ],

    // ========== Parent Menu: 设备管理 ==========
    [
        'id' => 'addons\webman\controller\StorePlayerController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '设备管理',
        'children' => [
            [
                'id' => 'addons\webman\controller\StorePlayerController\index',
                'pid' => 'addons\webman\controller\StorePlayerController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayerController/index',
                'title' => '设备列表',
            ],
        ]
    ],
];
```

6. **Menu Naming Conventions (菜单命名规范)**

```php
// Parent Menu ID: Controller name with dash suffix
'id' => 'addons\webman\controller\{ControllerName}-'

// Child Menu/Action ID: Controller + method name
'id' => 'addons\webman\controller\{ControllerName}\{methodName}'

// Parent ID reference
'pid' => 'addons\webman\controller\{ControllerName}-'  // For menu items
'pid' => 'addons\webman\controller\{ControllerName}\{menuMethod}'  // For sub-actions
```

7. **Adding New Menu Items (添加新菜单项)**

**Step 1:** Add parent menu (if new module)
```php
[
    'id' => 'addons\webman\controller\MyNewController-',
    'pid' => 0,
    'url' => '',
    'group' => 'store',
    'title' => '新模块',
    'children' => []
]
```

**Step 2:** Add menu item
```php
[
    'id' => 'addons\webman\controller\MyNewController\index',
    'pid' => 'addons\webman\controller\MyNewController-',
    'action' => 'index',
    'method' => 'get',
    'group' => 'store',
    'url' => 'ex-admin/addons-webman-controller-MyNewController/index',
    'title' => '列表',
]
```

**Step 3:** Add sub-actions (if needed)
```php
[
    'id' => 'addons\webman\controller\MyNewController\export',
    'pid' => 'addons\webman\controller\MyNewController\index',
    'action' => 'export',
    'method' => 'get',
    'group' => 'store',
    'url' => 'ex-admin/addons-webman-controller-MyNewController/export',
    'title' => '导出',
]
```

**Step 4:** Restart server and assign permissions
```bash
php start.php restart
```

8. **Menu Display Rules (菜单显示规则)**

- **Parent menu shows IF**: User has permission to ANY child item
- **Child menu shows IF**: User has permission to that specific item
- **Sub-actions**: Never shown in menu, but permission is checked when accessed
- **Empty parent menus**: Automatically hidden if no children are accessible

9. **Important Notes (重要提示)**

- **Menu = Permission Node**: Every menu item is also a permission node
- **No URL for parent**: Parent menus have `url => ''`, only children have URLs
- **Trailing dash**: Parent menu IDs always end with `-` (e.g., `Controller-`)
- **Children array**: Use `children` key for organizing in config, system will flatten it
- **Permission inheritance**: Sub-actions inherit context from parent menu item
- **Role assignment**: Assign permissions in backend → Role Management → Edit Role → Check permissions

10. **Common Menu Patterns (常见菜单模式)**

```php
// Pattern 1: Simple menu (1 parent, 1 child)
[
    'id' => 'Controller-',
    'pid' => 0,
    'title' => '模块',
    'children' => [
        ['id' => 'Controller\index', 'title' => '列表']
    ]
]

// Pattern 2: Menu with multiple pages
[
    'id' => 'Controller-',
    'pid' => 0,
    'title' => '模块',
    'children' => [
        ['id' => 'Controller\index', 'title' => '列表'],
        ['id' => 'Controller\settings', 'title' => '设置'],
    ]
]

// Pattern 3: Menu with actions (export, import, etc.)
[
    'id' => 'Controller-',
    'pid' => 0,
    'title' => '模块',
    'children' => [
        [
            'id' => 'Controller\index',
            'title' => '列表',
            // Sub-actions as siblings (same parent)
        ],
        [
            'id' => 'Controller\export',
            'pid' => 'Controller\index',  // Parent is the menu item
            'title' => '导出'
        ],
    ]
]
```

11. **Debugging Menu Issues (菜单问题调试)**

```php
// Check user's permissions
$permissions = Admin::permission();
var_dump($permissions);

// Check if specific menu node exists
$exists = in_array('addons\webman\controller\MyController\index', Admin::node()->all());

// Check role's permissions
$role = AdminRole::find(19);  // Store role
$permissions = $role->permissions->pluck('node_id')->toArray();

// Clear permission cache
Cache::delete('ADMIN_PERMISSIONS_' . Admin::id());
```

## Data Permissions System (数据权限系统)

**⚠️ CRITICAL: Data permissions are different from function permissions.**

- **Function Permissions (功能权限)**: Control WHAT users can access (pages/features) - configured in `config/store_node.php`
- **Data Permissions (数据权限)**: Control WHICH DATA users can see - configured in model traits and roles

The `DataPermissions` trait (`addons/webman/traits/DataPermissions.php`) provides automatic data filtering based on user roles.

### 5 Data Permission Types (5种数据权限类型)

1. **Full Data Rights (0)** - 全部数据权限
   - Access all data across all departments
   - Typically for super admin or platform admin

2. **Custom Permissions (1)** - 自定义数据权限
   - Access specific departments via role configuration
   - Configured in `admin_role.department_ids` (JSON array)

3. **Department and Below (2)** - 本部门及以下数据权限
   - Access current department and all sub-departments
   - Most common for channel admins

4. **Current Department Only (3)** - 仅本部门数据权限
   - Access only current department (no sub-departments)
   - Common for store/agent admins

5. **Personal Data Only (4)** - 仅本人数据权限
   - Access only data created by/belonging to the user
   - Most restrictive, used for limited accounts

### Model Configuration (模型配置)

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

### How It Works (工作原理)

1. **Automatic Application**
   - Applied as Eloquent Global Scope automatically on model queries
   - No manual filtering needed in controllers

2. **Role-Based Filtering**
   - Checks user's role `data_type` (0-4)
   - Filters queries based on permission type
   - Multiple roles: uses the most permissive one

3. **Caching**
   - Cached for performance (1 hour TTL)
   - Cache keys: `data_perm:role_user:{adminId}`, `data_perm:dept:{deptId}`

4. **Query Optimization**
   - Uses JOIN instead of FIND_IN_SET for better performance
   - Automatically handles soft deletes

### Usage Examples (使用示例)

**Automatic filtering (most common):**
```php
// In Controller
$players = Player::query()->get();
// Automatically filtered by user's data permissions
```

**Disable data permissions when needed:**
```php
// Method 1: Using offDataAuth()
Model::offDataAuth()->where(...)->get();

// Method 2: Using withoutGlobalScope()
Model::withoutGlobalScope('dataAuth')->where(...)->get();

// Use case: Admin needs to see all data for statistics
$allPlayers = Player::offDataAuth()->count();
```

**Manual filtering (explicit control):**
```php
// Store backend: Only show data belonging to current admin
$grid->model()->where('store_admin_id', Admin::user()->id);

// Agent backend: Show data in current department
$grid->model()->where('department_id', Admin::user()->department_id);
```

### Data Permission Configuration (数据权限配置)

**Role Configuration (角色配置):**

In `admin_role` table:
```sql
-- Store Role (ID: 19)
data_type = 3  -- Current Department Only (仅本部门)

-- Agent Role (ID: 18)
data_type = 2  -- Department and Below (本部门及以下)

-- Channel Admin Role
data_type = 2  -- Department and Below (本部门及以下)

-- Super Admin Role
data_type = 0  -- Full Data Rights (全部数据)
```

**Model Configuration (模型配置):**

```php
// Player Model
class Player extends Model
{
    use DataPermissions;

    protected $dataAuth = ['department_id' => 'department_id'];
}

// StoreShiftHandoverRecord Model
class StoreShiftHandoverRecord extends Model
{
    use DataPermissions;

    // Only show records belonging to current admin user
    protected $dataAuth = ['id' => 'bind_admin_user_id'];
}
```

### Important Notes (重要提示)

1. **Super Admin Bypass**
   - Super admin (ID from config) bypasses ALL data permissions
   - Configured in `config/plugin/rockys/ex-admin-webman/admin_auth_id`

2. **Performance**
   - Data permissions are cached in Redis
   - Cache automatically cleared when roles/departments change
   - Use `offDataAuth()` sparingly (only when truly needed)

3. **Multi-Tenant Isolation**
   - Critical for multi-tenant architecture (channel/agent/store)
   - Always verify data ownership before updates/deletes
   - Never trust client-side filtering alone

4. **Common Patterns**
   ```php
   // Check ownership before deletion
   $record = Model::find($id);
   if ($record->department_id != Admin::user()->department_id) {
       return message_error(admin_trans('common.no_permission'));
   }

   // Verify data access in custom queries
   $query->where('department_id', Admin::user()->department_id);
   ```

5. **Debugging Data Permissions**
   ```php
   // Check user's roles and data_type
   $roles = Admin::user()->roles;
   foreach ($roles as $role) {
       echo "Role: {$role->name}, data_type: {$role->data_type}\n";
   }

   // Check which departments user can access
   $deptIds = Cache::get('data_perm:dept:' . Admin::user()->department_id);

   // Test query with/without data permissions
   $withPerm = Player::query()->count();
   $withoutPerm = Player::offDataAuth()->count();
   echo "With permissions: {$withPerm}, Without: {$withoutPerm}\n";
   ```

### Summary: Function vs Data Permissions (总结：功能权限 vs 数据权限)

| Aspect | Function Permissions (功能权限) | Data Permissions (数据权限) |
|--------|--------------------------------|---------------------------|
| **Purpose** | Control access to pages/features | Control access to data rows |
| **Configuration** | `config/store_node.php` files | Model traits + role `data_type` |
| **Enforcement** | `@auth true` annotation + middleware | Eloquent Global Scope |
| **Scope** | Controller methods | Database queries |
| **Example** | Can user export records? | Which records can user see? |
| **Bypass** | Super admin + config | Super admin + `offDataAuth()` |
| **Cache** | Redis (permission nodes) | Redis (department IDs) |

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

**Returns:** Translated string

### File Organization

**Directory Structure:**
```
addons/webman/lang/
├── zh-TW/          # Traditional Chinese (默认语言/繁体中文)
│   ├── common.php
│   ├── player.php
│   ├── machine.php
│   ├── shift_handover.php
│   └── ...
├── zh-CN/          # Simplified Chinese (简体中文)
│   ├── common.php
│   ├── player.php
│   └── ...
├── en/             # English
│   ├── common.php
│   └── ...
└── jp/             # Japanese
    ├── common.php
    └── ...
```

**File Types:**

1. **`common.php`** - Shared translations across modules
   - Error messages
   - Success messages
   - Validation messages
   - Help text
   - Common action labels (submit, cancel, delete, etc.)

2. **`{module}.php`** - Module-specific translations
   - Field labels (`fields` key)
   - Status/type labels
   - Module-specific actions
   - Module-specific messages

### Naming Conventions

**Translation Key Structure:**

```
{module}.{category}.{name}
```

**Standard Categories:**

1. **`fields`** - Database field labels
   ```php
   'fields' => [
       'id' => 'ID',
       'name' => '名称',
       'status' => '状态',
       'created_at' => '创建时间',
   ]
   ```

2. **`label`** - UI labels with colon suffix (for detail views)
   ```php
   'label' => [
       'start' => '开始：',
       'end' => '结束：',
       'total' => '总计：',
   ]
   ```

3. **`action`** - Action button labels
   ```php
   'action' => [
       'view_detail' => '查看明细',
       'export' => '导出',
       'operation' => '操作',
   ]
   ```

4. **`filter`** - Filter/search labels
   ```php
   'filter' => [
       'time_range' => '时间范围',
       'start_time' => '开始时间',
       'end_time' => '结束时间',
   ]
   ```

5. **`status`** - Status value labels
   ```php
   'status' => [
       '0' => '禁用',
       '1' => '启用',
   ]
   ```

6. **`error`** - Error messages
   ```php
   'error' => [
       'not_found' => '记录不存在',
       'invalid_param' => '参数无效',
   ]
   ```

**Examples:**

```php
// ✅ CORRECT - Clear, structured keys
admin_trans('player.fields.device_name')
admin_trans('shift_handover.label.start')
admin_trans('shift_handover.action.view_detail')
admin_trans('shift_handover.filter.time_range')
admin_trans('player.status.1')
admin_trans('common.error.save_failed')

// ❌ WRONG - Unclear, unstructured keys
admin_trans('player.name')              // Missing 'fields' category
admin_trans('shift.start')              // Ambiguous - label? field?
admin_trans('view')                     // Too generic
admin_trans('error1')                   // Non-descriptive
```

### Code Practices

**1. Grid Column Labels:**

```php
// ✅ CORRECT - All column labels use translations
$grid->column('id', 'ID')->align('center');
$grid->column('player_name', admin_trans('shift_handover.device_name'));
$grid->column('created_at', admin_trans('shift_handover.record.created_at'));
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

**2. Form Field Labels:**

```php
// ✅ CORRECT
$form->text('name', admin_trans('player.fields.name'))->required();
$form->select('status', admin_trans('player.fields.status'))
    ->options([
        1 => admin_trans('player.status.1'),
        0 => admin_trans('player.status.0')
    ]);
$form->text('field', admin_trans('player.fields.field'))
    ->help(admin_trans('common.help.field_format'));

// ❌ WRONG - Hardcoded text
$form->text('name', '名称')->required();
$form->select('status', '状态')->options([1 => '启用', 0 => '禁用']);
```

**3. Filters:**

```php
// ✅ CORRECT
$grid->filter(function (Filter $filter) {
    $filter->like()->text('player.name')
        ->placeholder(admin_trans('player.fields.device_name'));
    $filter->eq()->select('status')
        ->placeholder(admin_trans('player.fields.status'))
        ->options([
            1 => admin_trans('player.status.1'),
            0 => admin_trans('player.status.0')
        ]);
    $filter->form()->dateRange('start_date', 'end_date', admin_trans('shift_handover.filter.time_range'))
        ->placeholder([
            admin_trans('shift_handover.filter.start_time'),
            admin_trans('shift_handover.filter.end_time')
        ]);
});

// ❌ WRONG - Hardcoded placeholders
$filter->like()->text('player.name')->placeholder('设备名称');
$filter->form()->dateRange('start_date', 'end_date', '时间范围')
    ->placeholder(['开始时间', '结束时间']);
```

**4. Messages:**

```php
// ✅ CORRECT - With parameter substitution
return message_success(admin_trans('common.save_success'));
return message_error(admin_trans('common.player_not_exist'));
return message_success(admin_trans('common.agent_create_success', null, [
    'name' => $name,
    'username' => $username
]));

// ❌ WRONG - Hardcoded messages
return message_success('保存成功');
return message_error('玩家不存在');
```

**5. Dynamic Content (Card, Html components):**

```php
// ✅ CORRECT - Labels use translations
Html::div()->content([
    Html::create(admin_trans('shift_handover.label.start'))->style(['fontWeight' => 'bold']),
    Html::create($row['start_time'])
])

Card::create([
    Html::create(admin_trans('shift_handover.device.detail_data'))->tag('h4')
])

// ❌ WRONG - Hardcoded Chinese in HTML
Html::create('开始时间：')->style(['fontWeight' => 'bold'])
Html::create('设备详细数据')->tag('h4')
```

### Parameter Substitution

Use `{param}` placeholders in translation files and pass values in `$replace` array:

**Translation File (zh-CN/common.php):**
```php
return [
    'agent_create_success' => '代理 {name}（账号：{username}）创建成功',
    'delete_confirm' => '确定要删除 {count} 条记录吗？',
    'amount_range' => '金额范围：{min} ~ {max}',
];
```

**Controller Usage:**
```php
// Single parameter
message_success(admin_trans('common.save_success'));

// Multiple parameters
message_success(admin_trans('common.agent_create_success', null, [
    'name' => $agentName,
    'username' => $username
]));

// Numeric parameters
notification_success(
    admin_trans('common.delete_confirm', null, ['count' => $count])
);
```

### Adding New Translations

**Step-by-step process:**

1. **Identify the module** - Determine which translation file to use
   - Shared/common text → `common.php`
   - Module-specific → `{module}.php` (e.g., `player.php`, `shift_handover.php`)

2. **Choose the category** - Use standard categories (fields, label, action, filter, status, error)

3. **Create the key** - Use descriptive, dot-notation keys

4. **Add to ALL 4 language files** - **Start with zh-TW (Traditional Chinese) as default**, then add zh-CN, en, jp
   ```php
   // zh-TW/shift_handover.php (繁體中文 - 默認優先)
   'device' => [
       'detail_title' => '設備明細',
       'device_count' => '設備數量',
   ]

   // zh-CN/shift_handover.php (简体中文)
   'device' => [
       'detail_title' => '设备明细',
       'device_count' => '设备数量',
   ]

   // en/shift_handover.php (English)
   'device' => [
       'detail_title' => 'Device Details',
       'device_count' => 'Device Count',
   ]

   // jp/shift_handover.php (Japanese)
   'device' => [
       'detail_title' => 'デバイス詳細',
       'device_count' => 'デバイス数',
   ]
   ```

5. **Use in code**
   ```php
   $grid->column('device_count', admin_trans('shift_handover.device.device_count'));
   ```

### Common Mistakes to Avoid

**1. ❌ Hardcoding text in controllers:**
```php
// ❌ WRONG
$grid->title('交班记录');
$grid->column('name', '设备名称');

// ✅ CORRECT
$grid->title(admin_trans('shift_handover.record.title'));
$grid->column('name', admin_trans('shift_handover.device_name'));
```

**2. ❌ Mixing hardcoded and translated text:**
```php
// ❌ WRONG
Html::create('总计：' . $total)  // Mixed Chinese and variable

// ✅ CORRECT
Html::create(admin_trans('shift_handover.label.total') . $total)
```

**3. ❌ Forgetting to translate options/placeholders:**
```php
// ❌ WRONG
$filter->select('type')->options([1 => '自动', 0 => '手动']);
$filter->text('name')->placeholder('请输入名称');

// ✅ CORRECT
$filter->select('type')->options([
    1 => admin_trans('shift_handover.record.auto_shift'),
    0 => admin_trans('shift_handover.record.manual_shift')
]);
$filter->text('name')->placeholder(admin_trans('player.fields.device_name'));
```

**4. ❌ Using generic/non-descriptive keys:**
```php
// ❌ WRONG
admin_trans('msg1')
admin_trans('error')
admin_trans('field1')

// ✅ CORRECT
admin_trans('common.save_success')
admin_trans('player.error.not_found')
admin_trans('player.fields.device_name')
```

**5. ❌ Adding only to one language file:**
```php
// ❌ WRONG - Only added to zh-TW/player.php
'new_field' => '新欄位',
// Missing from zh-CN/, en/, jp/ files

// ✅ CORRECT - Added to all 4 files (zh-TW first, then others)
// zh-TW: 'new_field' => '新欄位',     (繁體中文 - 默認優先)
// zh-CN: 'new_field' => '新字段',     (简体中文)
// en: 'new_field' => 'New Field',     (English)
// jp: 'new_field' => '新しいフィールド', (Japanese)
```

### Language Loading

`LoadLangPack` middleware (`addons/webman/middleware/LoadLangPack.php`):
- Reads language from cookie: `ex_admin_lang`
- Default: configured in `config/plugin/rockys/ex-admin-webman/app.php`
- Sets locale in Container translator
- Loads translation files from `addons/webman/lang/`

**User Language Switching:**
- Users can switch language in admin panel
- Selected language is stored in cookie
- Applied automatically on subsequent requests

### Translation File Example

**Complete example: `addons/webman/lang/zh-TW/shift_handover.php` (繁體中文 - 默認語言)**

```php
<?php

return [
    'title' => '交班管理',

    // Field labels
    'shift_time' => '交班時間',
    'machine_point' => '投鈔點數',
    'lottery_amount' => '彩金',
    'total_in' => '總收入',
    'total_out' => '總支出',
    'profit' => '利潤',

    // Labels with colons (for detail views)
    'label' => [
        'start' => '開始：',
        'end' => '結束：',
        'shift_type' => '交班類型：',
        'total_profit' => '總利潤：',
    ],

    // Actions
    'action' => [
        'view_detail' => '查看明細',
        'export' => '導出',
    ],

    // Filters
    'filter' => [
        'time_range' => '時間範圍',
        'start_time' => '開始時間',
        'end_time' => '結束時間',
    ],

    // Record-specific
    'record' => [
        'title' => '交班記錄',
        'shift_type' => '交班類型',
        'auto_shift' => '自動交班',
        'manual_shift' => '手動交班',
    ],

    // Device-specific
    'device' => [
        'detail_title' => '設備明細',
        'device_count' => '設備數量',
        'label' => [
            'device_name' => '設備名稱：',
            'device_number' => '設備編號：',
        ],
    ],
];
```

### Best Practices Summary

1. **NEVER hardcode user-facing text** - Always use `admin_trans()`
2. **Default to Traditional Chinese (zh-TW)** - Write zh-TW translations first, then translate to other languages
3. **Add to all 4 language files** - zh-TW (first), zh-CN, en, jp
4. **Use structured keys** - Follow `{module}.{category}.{name}` pattern
5. **Group related translations** - Use nested arrays for logical grouping
6. **Support parameters** - Use `{param}` placeholders for dynamic content
7. **Be descriptive** - Key names should be self-documenting
8. **Test language switching** - Verify all languages display correctly
9. **Keep translations synchronized** - When modifying any language file, update all 4 languages

**Reference Files:**
- Translation Example: `addons/webman/lang/zh-TW/shift_handover.php` (繁體中文版本作為參考)
- Controller Example: `addons/webman/controller/StoreShiftHandoverRecordController.php`
- Documentation: `CONTROLLER_I18N_GUIDE.md` and `TRANSLATION_PROGRESS.md` for detailed patterns

## Middleware System

Located in `addons/webman/middleware/`:

**1. Permission.php** - Permission verification
- Checks if user has access to controller/action
- Returns 405 if no permission
- Applied to all ExAdmin routes

**2. LoadLangPack.php** - Language pack loading
- Reads language preference from cookie
- Sets translator locale
- Loads translation files

**3. AccessControl.php** - CORS handling
- Handles OPTIONS preflight requests
- Sets CORS headers
- Allows cross-origin requests

**4. AuthMiddleware.php** - User authentication
- JWT token verification
- Session management
- User context initialization

**5. IpAuthMiddleware.php** - IP whitelist
- Restricts access by IP
- Used for admin/sensitive endpoints

**Custom Middleware Registration:**

Middleware is configured in `config/middleware.php` or controller-specific config.

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
    // OR protected $fillable = ['field1', 'field2'];

    // Data permissions config
    protected $dataAuth = ['department_id' => 'department_id'];

    // Constants
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    // Type constants
    const TYPE_PLAYER = 1;
    const PLAYER_TYPE_NORMAL = 1;
    const PLAYER_TYPE_AGENT = 2;
    const PLAYER_TYPE_STORE_MACHINE = 3;

    // Constructor - dynamic table name
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.player_table'));
    }

    // Relationships
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'department_id', 'department_id')
            ->withTrashed(); // Include soft-deleted
    }

    public function player_extend(): HasOne
    {
        return $this->hasOne(PlayerExtend::class, 'player_id');
    }

    public function player_logs(): HasMany
    {
        return $this->hasMany(PlayerGameLog::class, 'player_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->name . ' (' . $this->uuid . ')';
    }

    // Mutators
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
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

**Model Traits:**

1. **SoftDeletes** - Laravel built-in
   - Adds `deleted_at` column
   - `->withTrashed()`, `->onlyTrashed()`, `->restore()`

2. **HasDateTimeFormatter** - Custom trait
   - Formats datetime consistently
   - `serializeDate()` returns 'Y-m-d H:i:s'

3. **DataPermissions** - Custom trait (detailed above)
   - Auto-filters data by user permissions
   - 5 permission types
   - Cached for performance

## Helper Functions

The `helpers.php` file (500+ lines) contains critical business logic helpers.

**Key Categories:**

**1. Configuration:**
```php
admin_sysconf($name, $value = null)  // System config get/set
validator($data, $rules, ...)        // Validator factory
```

**2. Game/Machine Helpers:**
```php
getGameTypeOptions()                 // Game type select options
getGameTypeName($val)                // Game type name translation
machineOpenAnyFree($player, $machine, $openScore)  // Open score without deduction
machineWash($player, $machine, ...)  // Machine wash (score settlement)
checkMachineOpenAny($machine, $money, $giftScore)  // Validate open score
checkSlotWashLimit($machine)         // Check slot wash limit
checkJackPotWashLimit($machine)      // Check jackpot wash limit
```

**3. Logging:**
```php
saveMachineOperationLog($machine, $player, $content, $action, ...)
saveMachineReceiveLog($machine, $msg, $player, ...)
```

**4. Business Logic:**
```php
nationalPromoterSettlement($data)    // National promoter rebate calculation
resetMachineTrans($machine, $player) // Reset machine state
addPlayerGameLog(...)                // Create player game log
getGivePoints($playerId, $machineId) // Get gift points cache
```

**Important:**
- These helpers contain core business logic
- Use transactions (Db::beginTransaction/commit/rollback)
- Validate input rigorously
- Return exceptions for errors
- Cache where appropriate

## Service Layer

Located in `addons/webman/service/`:

**ImportService** - Excel import/export
```php
$service = new ImportService();
$result = $service->importPlayer($departmentId);
// Uses PhpSpreadsheet for Excel handling
```

**Machine Services:**
- `FishServices` - Fishing game machine operations
- `SlotService` - Slot machine operations
- `JackpotService` - Jackpot/Pachinko machine operations
- `MediaServer` - Machine media/video handling

**Service Pattern:**
```php
$services = MachineServices::createServices($machine, $locale);
$services->sendCmd($command, $value, $source, $userId);
// Communicates with physical machines via GatewayWorker
```

## Key Business Logic

**Profit Settlement (分润):**
- Mode: Task-based daily settlement or event-based real-time
- Configured in `config/app.php`: `'profit' => 'task'`
- Services: `OfflineProfitSettlementServices`, `PromoterProfitSettlementService`
- Agent role: 18, Store role: 19 (from config)

**Player Management:**
- Types: Normal players, promoters (推广员), agents, stores
- Linked to store → agent → channel hierarchy
- Game permissions managed via `PlayerDisabledGame` model
- Wallet system: `PlayerPlatformCash` for multi-platform balances

**Auto Shift Handover (自动交班):**
- Configurable shifts (early/middle/night)
- Automatic settlement at shift change
- Configuration via `StoreAutoShiftConfig` model
- Records in `StoreAgentShiftHandoverRecord`

**Machine System:**
- Real-time communication via GatewayWorker
- Three types: Slot (斯洛), Fish (捕鱼), Steel Ball (钢珠/柏青哥)
- Control types: MEI (双美), SONG (小淞)
- Operations: Open score, Wash score, Reset, Gift points

## Important Configuration Files

- `config/app.php` - App settings, roles (agent_role: 18, store_role: 19), profit mode
- `config/database.php` - Database connections (MySQL, MongoDB)
- `config/plugin/rockys/ex-admin-webman/app.php` - ExAdmin configuration
- `config/route.php` - Custom routes (ExAdmin auto-routing enabled)
- `.env` - Environment variables

## Working with This Codebase

### Adding New Features

1. **Create Controller** in `addons/webman/controller/`
   - Implement `index()` for Grid
   - Implement `save()` or `form()` for Form
   - Add `@auth true` annotation if requires permission

2. **Create/Update Model** in `addons/webman/model/`
   - Add `DataPermissions` trait if needs data isolation
   - Define relationships
   - Add constants for status/type fields

3. **Add Translations** to all 4 language files in `addons/webman/lang/`
   - `common.php` for shared messages
   - `{entity}.php` for entity-specific labels

4. **Create Migration** if schema changes needed
   - Use Phinx: `vendor/bin/phinx create MigrationName`

5. **Routes** - Auto-discovered by ExAdmin, no manual registration

### Modifying Existing Features

1. **Read the controller** to understand current logic
2. **Check service layer** in `addons/webman/service/` for business logic
3. **Update translations** if changing user-facing text
4. **Test across all user types** (channel/agent/store admin)
5. **Verify data permissions** work correctly

### Data Isolation Best Practices

**Always filter by department:**
```php
$query->where('department_id', Admin::user()->department_id);
```

**For agent/store controllers:**
```php
$query->where('admin_user_id', Admin::user()->id);
```

**Use Model data permissions:**
```php
// Automatically filtered
$players = Player::query()->get();

// Bypass if needed (use carefully)
$allPlayers = Player::offDataAuth()->get();
```

**Check ownership before operations:**
```php
$player = Player::find($id);
if ($player->department_id != Admin::user()->department_id) {
    return message_error(admin_trans('common.no_permission'));
}
```

### Common Patterns

**1. Grid with Custom Query:**
```php
$query = Model::query()
    ->select(['table.*', 'related.field'])
    ->leftJoin('related', 'table.id', '=', 'related.table_id')
    ->where('table.department_id', Admin::user()->department_id);

$total = (clone $query)->count();
$list = $query->forPage($page, $size)->get()->toArray();

return Grid::create($list, function (Grid $grid) use ($total) {
    $grid->setTotal($total);
    // ...
});
```

**2. Form with Transaction:**
```php
$form->saving(function (Form $form) {
    Db::beginTransaction();
    try {
        // Multiple operations
        $model->save();
        $relatedModel->update([...]);
        Db::commit();
        return message_success(admin_trans('success'));
    } catch (\Exception $e) {
        Db::rollBack();
        return message_error($e->getMessage());
    }
});
```

**3. Modal Form Button:**
```php
$grid->column('action')->display(function ($val, $data) {
    return Button::create('Edit')
        ->type('primary')
        ->size('small')
        ->modal([$this, 'save'], ['id' => $data['id']])
        ->width('60%');
});
```

**4. Conditional Fields:**
```php
$form->radio('type', 'Type')->options([1 => 'A', 2 => 'B'])
    ->when(1, function (Form $form) {
        $form->text('field_for_a');
    })
    ->when(2, function (Form $form) {
        $form->text('field_for_b');
    });
```

### Offline Channel Pattern: Player as Device

**⚠️ IMPORTANT: This pattern ONLY applies to offline channels (is_offline = 1) in Agent and Store backend controllers.**

In offline channels, the `Player` model itself represents a physical device, not a person. This is a critical business model difference from online channels.

**Key Concept:**
- **Online Channels**: Player = Person, may use multiple devices
- **Offline Channels**: Player = Device (physical gaming machine)

**When to Use:**
- Agent backend controllers (prefixed with `Agent*Controller`)
- Store backend controllers (prefixed with `Store*Controller`)
- ONLY when the channel has `is_offline = 1`

**Pattern Implementation:**

**1. Direct Player Fields (NOT player.machine):**
```php
// ❌ WRONG - Do NOT use player.machine relationship
$grid->column('player.machine.uuid', 'Device UUID');
$grid->column('player.machine.name', 'Device Name');

// ✅ CORRECT - Use player fields directly
$grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->copy();
$grid->column('player.name', admin_trans('player.fields.device_name'));
```

**2. Model Relationships:**
```php
// ✅ Load player directly, not player.machine
$grid->model()->with(['player'])->whereIn('player_id', $playerIds);

// ❌ WRONG - Don't load player.machine
$grid->model()->with(['player.machine']); // Not needed in offline channels
```

**3. Filter Conditions:**
```php
// ✅ CORRECT - Filter by player fields
if (!empty($exAdminFilter['player']['uuid'])) {
    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
        $query->where('uuid', $exAdminFilter['player']['uuid']);
    });
}
if (!empty($exAdminFilter['player']['name'])) {
    $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
        $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
    });
}

// ❌ WRONG - Don't use player.machine
if (!empty($exAdminFilter['player']['machine']['uuid'])) {
    $grid->model()->whereHas('player.machine', function ($query) use ($exAdminFilter) {
        $query->where('uuid', $exAdminFilter['player']['machine']['uuid']);
    });
}
```

**4. Translation Keys:**
```php
// ✅ CORRECT - Use device-specific translation keys
admin_trans('player.fields.device_uuid')  // "设备UUID"
admin_trans('player.fields.device_name')  // "设备名称"

// ❌ WRONG - Don't use machine translation keys
admin_trans('machine.fields.uuid')        // This refers to physical machine, not player-device
admin_trans('machine.fields.name')
```

**5. Filter UI:**
```php
$grid->filter(function (Filter $filter) {
    // ✅ CORRECT - Device-centric filters
    $filter->like()->text('player.uuid')
        ->placeholder(admin_trans('player.fields.device_uuid'));
    $filter->like()->text('player.name')
        ->placeholder(admin_trans('player.fields.device_name'));

    // Remove player-specific filters (phone, etc.) in store/agent backends
    // ❌ Don't use: $filter->like()->text('player.phone')
});
```

**6. Complete Example (Store Backend):**
```php
// StorePlayerRechargeRecordController.php - CORRECT Implementation
public function index(): Grid
{
    return Grid::create(new $this->model(), function (Grid $grid) {
        $admin = Admin::user();
        $playerIds = Player::query()
            ->where('store_admin_id', $admin->id)
            ->pluck('id');

        // Load player directly (player = device in offline channels)
        $grid->model()->with(['player'])
            ->whereIn('player_id', $playerIds)
            ->orderBy('created_at', 'desc');

        // Filter conditions
        $exAdminFilter = Request::input('ex_admin_filter', []);
        if (!empty($exAdminFilter['player']['uuid'])) {
            $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                $query->where('uuid', $exAdminFilter['player']['uuid']);
            });
        }
        if (!empty($exAdminFilter['player']['name'])) {
            $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
            });
        }

        // Columns - device-centric display
        $grid->column('id', admin_trans('player_recharge_record.fields.id'));
        $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->copy();
        $grid->column('player.name', admin_trans('player.fields.device_name'));

        // Filters - device-centric
        $grid->filter(function (Filter $filter) {
            $filter->like()->text('player.uuid')
                ->placeholder(admin_trans('player.fields.device_uuid'));
            $filter->like()->text('player.name')
                ->placeholder(admin_trans('player.fields.device_name'));
        });
    });
}
```

**7. Redundant Fields Pattern:**

Some tables store redundant device information for performance. Use them directly:

```php
// Example: PlayerLotteryRecord has machine_uuid, machine_name fields
// These are redundant fields copied from player.uuid, player.name at record creation

// ✅ CORRECT - Use redundant fields for filtering (faster)
if (!empty($requestFilter['machine_uuid'])) {
    $grid->model()->where('machine_uuid', 'like', '%' . $requestFilter['machine_uuid'] . '%');
}

// ✅ But still load player for display if needed
$grid->model()->with(['player']);
$grid->column('player.uuid', admin_trans('player.fields.device_uuid'));
```

**Common Mistakes to Avoid:**

1. ❌ Don't use `player.machine` relationship in offline channel controllers
2. ❌ Don't display player.phone in store/agent backends
3. ❌ Don't use "player" terminology in UI - use "device" (设备)
4. ❌ Don't mix online and offline patterns in the same controller
5. ❌ Don't forget to check channel.is_offline before applying this pattern

**Affected Controllers:**

**Agent Backend:**
- `AgentPlayerGameLogController`
- `ChannelAgentController::machineList()`
- Other Agent*Controller methods dealing with player records

**Store Backend:**
- `StorePlayerRechargeRecordController`
- `StorePlayerWithdrawRecordController`
- `StorePlayGameRecordController`
- `StorePlayerGameLogController`
- `StoreLotteryController`
- `StoreDepositBonusTaskController`
- `StorePlayerController` (device list)

**Why This Pattern Exists:**

In offline channels (physical gaming locations):
- Each physical device has one player account
- The player account IS the device identifier
- No phone numbers or personal player info
-店家 (store owner) manages devices, not people
- 代理 (agent) oversees multiple stores and their devices

This simplifies device management and matches the business reality of offline gaming operations.

## Testing & Debugging

**Debug Mode:**
- Set `APP_DEBUG=true` in `.env`
- Shows detailed error messages
- Enables SQL query logging

**Logs:**
- Application: `runtime/logs/webman.log`
- Error: `runtime/logs/error.log`
- Custom: Use `Log::info()`, `Log::error()`

**Database Queries:**
```php
// Enable query log
Db::connection()->enableQueryLog();

// Get queries
$queries = Db::connection()->getQueryLog();
Log::info($queries);
```

**ExAdmin Debug:**
- Debug panel available in dev mode
- Shows grid/form configuration
- Displays SQL queries

**Common Issues:**

1. **Permission denied**: Check `admin_permission` and user role
2. **Data not showing**: Check DataPermissions and department_id
3. **Translation missing**: Add to all 4 language files
4. **Form not saving**: Check `saving()` hook for errors

## Performance Optimization

**1. Use Caching:**
```php
// Permission cache (auto)
Admin::permission()  // Cached 1 hour

// Custom cache
Cache::get('key');
Cache::set('key', $value, $ttl);
```

**2. Optimize Queries:**
```php
// Use select to limit columns
$query->select(['id', 'name', 'status']);

// Use eager loading
$players = Player::with(['channel', 'player_extend'])->get();

// Use pagination
$query->forPage($page, $size);
```

**3. Data Permissions Cache:**
- Role info cached: `data_perm:role_user:{id}`
- Department IDs cached: `data_perm:dept:{id}`
- TTL: 1 hour

## Deployment & Production

### Three-Project Deployment Strategy

**Deployment Order:**

1. **Database First:**
   ```bash
   # Create database and run migrations
   mysql -u root -p -e "CREATE DATABASE yjb_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

   # Run migrations (can run from any project)
   cd /path/to/gk_admin
   vendor/bin/phinx migrate
   ```

2. **Deploy gk_work (Worker Server):**
   ```bash
   # This must be deployed first as other projects depend on it
   cd /www/wwwroot/gk_work
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   # Configure .env (especially database and game platform settings)

   # Start worker processes
   php start.php start -d

   # Verify processes are running
   php start.php status
   ```

3. **Deploy gk_api (API Server):**
   ```bash
   cd /www/wwwroot/gk_api
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   # Configure .env (ensure Push service ports are available)

   # Configure Push service
   # Ensure ports 3131 and 3232 are open in firewall

   # Start API server
   php start.php start -d

   # Test Push service
   curl http://localhost:3232/api/ping
   ```

4. **Deploy gk_admin (Admin Panel):**
   ```bash
   cd /www/wwwroot/gk_admin
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   # Configure .env
   # Set GAME_PLATFORM_PROXY_HOST to gk_work server IP
   # Set PUSH_API_URL to gk_api server IP

   # Start admin server
   php start.php start -d

   # Access admin panel
   # http://your-server-ip:8789/admin
   ```

**Network Configuration:**

```
┌─────────────────────────────────────────────────────────┐
│                  Production Network                      │
└─────────────────────────────────────────────────────────┘

Public Internet
     │
     ├────────> Nginx (80/443) ──> gk_admin (10.140.0.11:8789)
     │                              Backend Admin Panel
     │
     ├────────> Nginx (80/443) ──> gk_api (10.140.0.12:8787)
     │          WebSocket Proxy ──> (10.140.0.12:3131)
     │                              Player API, WebSocket
     │
     └────────> Game Platforms ──> gk_work (10.140.0.10:8080)
                Callback Only       Single Wallet API
                (IP Whitelist)

Internal Network (10.140.0.0/24)
     │
     ├─── gk_admin (10.140.0.11:8789)
     │    └───> gk_work (10.140.0.10:8788) [Proxy Requests]
     │    └───> gk_api (10.140.0.12:3232) [Push API]
     │
     ├─── gk_api (10.140.0.12:8787)
     │    └───> gk_work (10.140.0.10:8788) [Proxy Requests]
     │
     ├─── gk_work (10.140.0.10:8788) [Internal API]
     │    ├───> gk_api (10.140.0.12:3232) [Push Notifications]
     │    └───> External Game APIs [Game Integration]
     │
     ├─── MySQL (10.140.0.20:3306)
     ├─── MongoDB (10.140.0.20:27017)
     └─── Redis (10.140.0.20:6379)
```

**Firewall Rules:**

```bash
# gk_admin (10.140.0.11)
# Inbound:
- Allow 80, 443 from anywhere (Nginx)
- Allow 8789 from internal network only
# Outbound:
- Allow 8788 to gk_work (10.140.0.10)
- Allow 3232 to gk_api (10.140.0.12)
- Allow 3306 to MySQL
- Allow 6379 to Redis

# gk_api (10.140.0.12)
# Inbound:
- Allow 80, 443 from anywhere (Nginx)
- Allow 8787 from internal network
- Allow 3131 from anywhere (WebSocket)
- Allow 3232 from internal network only (Push API)
# Outbound:
- Allow 8788 to gk_work (10.140.0.10)
- Allow 3306 to MySQL
- Allow 6379 to Redis

# gk_work (10.140.0.10)
# Inbound:
- Allow 8788 from internal network only
- Allow 8080 from game platform IPs only (whitelist)
# Outbound:
- Allow 443 to external game platforms
- Allow 3232 to gk_api (10.140.0.12)
- Allow 3306 to MySQL
- Allow 27017 to MongoDB
- Allow 6379 to Redis
```

**Nginx Configuration Examples:**

**gk_admin (Admin Panel):**
```nginx
server {
    listen 80;
    server_name admin.yourdomain.com;

    location / {
        proxy_pass http://10.140.0.11:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**gk_api (Player API + WebSocket):**
```nginx
# HTTP API
server {
    listen 80;
    server_name api.yourdomain.com;

    location / {
        proxy_pass http://10.140.0.12:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}

# WebSocket
server {
    listen 80;
    server_name ws.yourdomain.com;

    location / {
        proxy_pass http://10.140.0.12:3131;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }
}
```

**Health Checks:**

```bash
# Check all services are running

# gk_admin
curl http://10.140.0.11:8789/admin
# Expected: Admin login page

# gk_api
curl http://10.140.0.12:8787/api/v1/announcement-list -X POST
# Expected: JSON response

# WebSocket
wscat -c ws://10.140.0.12:3131
# Expected: WebSocket connection

# gk_work (internal)
curl http://10.140.0.10:8788/api/v1/enter-game -X POST -d '{}'
# Expected: Error response (no auth)

# gk_work (public - single wallet)
curl http://10.140.0.10:8080/single-wallet/rsg-channel/GetBalance -X POST
# Expected: Balance API response

# Database
mysql -h 10.140.0.20 -u user -p -e "SELECT 1"
# Expected: Result: 1

# Redis
redis-cli -h 10.140.0.20 ping
# Expected: PONG

# Process status (on each server)
php start.php status
# Expected: All processes running
```

**Monitoring:**

```bash
# Check process count on gk_work
ps aux | grep -c "php.*process"
# Expected: 20+ processes

# Check WebSocket connections on gk_api
netstat -an | grep :3131 | grep ESTABLISHED | wc -l
# Shows active WebSocket connections

# Check memory usage
ps aux | grep webman | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Check logs for errors
tail -f /www/wwwroot/gk_admin/runtime/logs/webman.log
tail -f /www/wwwroot/gk_api/runtime/logs/webman.log
tail -f /www/wwwroot/gk_work/runtime/logs/webman.log
```

### Dependencies Management

**Critical Dependencies:**
The project requires all Composer dependencies to be properly installed. Missing packages will cause runtime errors.

**Key Dependencies:**
- `webman-tech/laravel-http-client` - HTTP client for game API integration
- `workerman/webman-framework` - Core framework
- `rockys/ex-admin-webman` - Admin UI
- `illuminate/database` - Eloquent ORM
- `webman/gateway-worker` - Real-time machine communication
- `jenssegers/mongodb` - MongoDB support for logs
- See `composer.json` for complete list

**Install Dependencies:**
```bash
# Development environment (includes dev dependencies)
composer install

# Production environment (optimized, no dev dependencies)
composer install --no-dev --optimize-autoloader
```

### Deployment Checklist

**1. Prepare Files:**
```bash
# Upload these files to production server:
- All source code (app/, addons/, config/, etc.)
- composer.json
- composer.lock (CRITICAL - ensures version consistency)
- .env (configured for production)
- phinx.php (if using Phinx migrations)
```

**2. Server Setup:**
```bash
# Install dependencies
cd /path/to/project
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 runtime/
chmod -R 755 public/

# Configure .env
cp .env.example .env
nano .env  # Edit database, API domains, etc.
```

**3. Database Setup:**
```bash
# Run migrations
vendor/bin/phinx migrate

# Verify migration status
vendor/bin/phinx status
```

**4. Start Services:**
```bash
# Start Webman (daemon mode)
php start.php start -d

# Check status
php start.php status

# View logs
tail -f runtime/logs/webman.log
```

### Production Environment Settings

**.env Configuration:**
```env
# Debug mode - MUST be false in production
APP_DEBUG=false

# Database - use production credentials
DB_HOST=production-db-host
DB_DATABASE=production_db
DB_USERNAME=production_user
DB_PASSWORD=secure_password

# API domains
API_DOMAIN=https://api.production.com/
IP_DOMAIN=https://ip.production.com/

# Currency
currency=TWD

# Profit settlement mode
profit=task
```

**PHP Configuration:**
- PHP >= 8.0
- Required extensions: pdo, json, bcmath, openssl, simplexml, curl, mongodb
- Memory limit: 512M+ recommended
- Max execution time: 60+ seconds for long-running operations

**Server Requirements:**
- Linux (Ubuntu 20.04+ or CentOS 7+ recommended)
- Supervisor (recommended for process management)
- Nginx/Apache (reverse proxy)
- MySQL 5.7+ or 8.0+
- MongoDB 4.4+ (for logs)
- Redis 5.0+ (for cache and queue)

### Cross-Project Troubleshooting

**1. Game Platform Proxy Not Working:**

**Symptom:** Error "Connection refused" or "Game platform unavailable" in gk_admin

**Check:**
```bash
# 1. Is gk_work running?
ssh gk_work_server
php start.php status

# 2. Can gk_admin reach gk_work?
# On gk_admin server
curl http://10.140.0.10:8788/api/v1/enter-game -X POST -d '{"test":"1"}'

# 3. Check .env configuration
# On gk_admin server
grep GAME_PLATFORM_PROXY .env
# Should show correct gk_work IP and port

# 4. Check firewall
telnet 10.140.0.10 8788
```

**Solution:**
```bash
# Start gk_work if not running
cd /www/wwwroot/gk_work
php start.php start -d

# Check and update .env in gk_admin
nano .env
# GAME_PLATFORM_PROXY_HOST=10.140.0.10
# GAME_PLATFORM_PROXY_PORT=8788

# Reload gk_admin
php start.php reload
```

---

**2. WebSocket Push Not Working:**

**Symptom:** Real-time updates not showing in admin panel or player app

**Check:**
```bash
# 1. Is Push service running on gk_api?
ssh gk_api_server
netstat -tlnp | grep 3131
netstat -tlnp | grep 3232

# 2. Check configuration match
# Compare these values across all three projects:
grep PUSH_APP_KEY .env  # Must be identical
grep PUSH_APP_SECRET .env  # Must be identical

# 3. Test WebSocket connection
wscat -c ws://gk_api_server_ip:3131

# 4. Test Push API
curl http://gk_api_server_ip:3232/api/ping
```

**Solution:**
```bash
# On gk_api server - check Push plugin config
cat config/plugin/webman/push/app.php
# Note the app_key and app_secret values

# Update .env in gk_admin and gk_work with matching values
PUSH_APP_KEY=value_from_above
PUSH_APP_SECRET=value_from_above
PUSH_API_URL=http://10.140.0.12:3232
WS_URL=ws://your-domain.com:3131  # or wss:// for SSL

# Restart all services
php start.php restart
```

---

**3. Single Wallet Callback Failures:**

**Symptom:** Game platforms report "Unable to reach wallet API" or balance errors

**Check:**
```bash
# 1. Is gk_work public port accessible?
curl http://your_public_ip:8080/single-wallet/rsg-channel/GetBalance -X POST

# 2. Check firewall allows game platform IPs
iptables -L -n | grep 8080

# 3. Check game platform configuration
# Each platform must be configured with your callback URL:
# http://your_public_ip:8080/single-wallet/{platform}-channel/
```

**Solution:**
```bash
# Open firewall port
firewall-cmd --permanent --add-port=8080/tcp
firewall-cmd --reload

# Ensure gk_work is listening on 0.0.0.0, not 127.0.0.1
# In config/server.php:
'listen' => 'http://0.0.0.0:8080'

# Restart gk_work
php start.php restart

# Update game platform webhook URLs in their admin panels
```

---

**4. Background Processes Not Running:**

**Symptom:** Settlement not working, logs not clearing, reminders not sent

**Check:**
```bash
# On gk_work server
php start.php status

# Should show all processes:
# - ProfitSettlement
# - ChannelSettlement
# - LotteryRemind
# - etc.

# Check process logs
tail -f runtime/logs/webman.log | grep -i "process"
```

**Solution:**
```bash
# Stop and restart gk_work
php start.php stop
php start.php start -d

# Check if specific process is defined
grep "ProcessName" config/process.php

# Verify process class exists
ls -la process/ProcessName.php
```

---

**5. Database Connection Errors Across Projects:**

**Symptom:** "Connection refused" or "Access denied" in any project

**Check:**
```bash
# Test from each server
# On gk_admin server
mysql -h 10.140.0.20 -u dbuser -p dbname

# On gk_api server
mysql -h 10.140.0.20 -u dbuser -p dbname

# On gk_work server
mysql -h 10.140.0.20 -u dbuser -p dbname

# Check .env configuration in all three projects
grep "^DB_" .env
```

**Solution:**
```bash
# Ensure all three projects have identical database config
# Copy from working project
scp gk_admin_server:.env gk_api_server:.env
scp gk_admin_server:.env gk_work_server:.env

# Edit to ensure correct values
nano .env
# DB_HOST=10.140.0.20
# DB_DATABASE=yjb_platform
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# Grant access from all server IPs
mysql -u root -p
GRANT ALL PRIVILEGES ON yjb_platform.* TO 'your_user'@'10.140.0.11' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON yjb_platform.* TO 'your_user'@'10.140.0.12' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON yjb_platform.* TO 'your_user'@'10.140.0.10' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

---

**6. Redis/MongoDB Connection Issues:**

**Check:**
```bash
# Redis
redis-cli -h 10.140.0.20 ping

# MongoDB
mongo --host 10.140.0.20 --port 27017

# Check .env in all projects
grep "REDIS_HOST\|MONGODB_HOST" .env
```

**Solution:**
```bash
# Ensure Redis/MongoDB bind to 0.0.0.0, not 127.0.0.1
# Redis: /etc/redis/redis.conf
bind 0.0.0.0

# MongoDB: /etc/mongod.conf
net:
  bindIp: 0.0.0.0

# Restart services
systemctl restart redis
systemctl restart mongod

# Update .env in all three projects
REDIS_HOST=10.140.0.20
MONGODB_HOST=10.140.0.20
```

---

### Common Deployment Issues

**1. Class Not Found Errors:**
```bash
# Issue: Missing Composer dependencies
# Example: Class "WebmanTech\LaravelHttpClient\Facades\Http" not found

# Solution:
cd /path/to/project
composer install --no-dev --optimize-autoloader

# Verify specific package:
composer show webman-tech/laravel-http-client

# Check if package exists:
ls -la vendor/webman-tech/laravel-http-client
```

**2. Permission Denied:**
```bash
# Make runtime directory writable
chmod -R 755 runtime/
chown -R www-data:www-data runtime/  # Adjust user as needed
```

**3. Database Connection Failed:**
```bash
# Check .env database settings
# Verify database exists and user has permissions
# Test connection:
php -r "new PDO('mysql:host=HOST;dbname=DB', 'USER', 'PASS');"
```

**4. Port Already in Use:**
```bash
# Check what's using port 8789
netstat -tunlp | grep 8789

# Kill existing process
php start.php stop
# Or force kill
pkill -f webman
```

**5. Autoload Issues:**
```bash
# Regenerate autoload files
composer dump-autoload --optimize
```

### Process Management with Supervisor

**Install Supervisor:**
```bash
apt-get install supervisor  # Ubuntu/Debian
yum install supervisor      # CentOS
```

**Supervisor Config** (`/etc/supervisor/conf.d/webman.conf`):
```ini
[program:webman]
command=php /www/wwwroot/admin.supergames9.com/start.php start
directory=/www/wwwroot/admin.supergames9.com
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/www/wwwroot/admin.supergames9.com/runtime/logs/supervisor.log
stdout_logfile_maxbytes=50MB
```

**Supervisor Commands:**
```bash
# Reload config
supervisorctl reread
supervisorctl update

# Start/Stop/Restart
supervisorctl start webman
supervisorctl stop webman
supervisorctl restart webman

# Check status
supervisorctl status
```

### Nginx Reverse Proxy Configuration

**Example Nginx Config:**
```nginx
server {
    listen 80;
    server_name admin.yourdomain.com;

    location / {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

### Updates and Maintenance

**Deploying Updates:**
```bash
# 1. Backup database
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# 2. Upload new code (excluding vendor/)

# 3. Update dependencies
composer install --no-dev --optimize-autoloader

# 4. Run migrations
vendor/bin/phinx migrate

# 5. Reload Webman (zero-downtime)
php start.php reload

# OR restart (with brief downtime)
php start.php restart
```

**Rolling Back:**
```bash
# Restore previous code version
# Rollback database
vendor/bin/phinx rollback

# Restart
php start.php restart
```

### Monitoring and Logs

**Log Locations:**
- Application: `runtime/logs/webman.log`
- Error: `runtime/logs/error.log`
- Supervisor: `runtime/logs/supervisor.log`
- Nginx access: `/var/log/nginx/access.log`
- Nginx error: `/var/log/nginx/error.log`

**Monitor Process:**
```bash
# Check if Webman is running
ps aux | grep webman

# Check memory usage
top -p $(pgrep -f webman)

# View real-time logs
tail -f runtime/logs/webman.log
```

**Log Rotation:**
```bash
# Add to /etc/logrotate.d/webman
/www/wwwroot/admin.supergames9.com/runtime/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    missingok
    create 0644 www-data www-data
}
```

### Security Best Practices

1. **Disable Debug Mode in Production:**
   - Set `APP_DEBUG=false` in `.env`

2. **Secure Database Credentials:**
   - Use strong passwords
   - Restrict database user permissions
   - Use separate DB users for different environments

3. **File Permissions:**
   - Source code: 755
   - Runtime directory: 755 (writable by web server)
   - Config files: 600 (readable only by owner)

4. **Hide Sensitive Files:**
   - Ensure `.env` is not web-accessible
   - Configure Nginx/Apache to deny access to `.env`, `composer.json`, etc.

5. **Update Dependencies Regularly:**
   ```bash
   # Check for outdated packages
   composer outdated

   # Update (with caution in production)
   composer update --with-dependencies
   ```

6. **Use HTTPS:**
   - Configure SSL certificates
   - Force HTTPS in Nginx/Apache
   - Update API_DOMAIN to use https://

### Performance Tuning for Production

**PHP OPcache:**
```ini
; php.ini settings
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

**Webman Workers:**
Edit `start.php` to adjust worker count:
```php
// Adjust based on CPU cores (typically CPU cores × 2)
'count' => 8,
```

**Database Connection Pool:**
- Configure appropriate connection limits in `config/database.php`
- Monitor slow queries
- Add indexes for frequently queried columns

**Redis Configuration:**
- Use Redis for session storage
- Configure appropriate memory limits
- Enable persistence if needed

## Important Notes

- **Never** hardcode Chinese text in controllers - always use `admin_trans()`
- **Always** validate department/channel ownership before data operations
- **Multi-language**: Add translations to all 4 files when adding new UI text
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
   - Add service in `gk_work\app\service\game\{Platform}ServiceInterface.php` (游戏服务已迁移到 gk_work)
   - Add wallet controller in `gk_work\app\wallet\controller\game\{Platform}GameController.php`
   - Add routes in `gk_work\config\route.php` under `/single-wallet/{platform}-channel/`
   - Add proxy API routes in `gk_work\app\api\v1\GamePlatformProxyController.php` if needed
   - Add platform config in all three projects' `.env` files
   - Update `config/game_platform.php` in all three projects
   - gk_admin 通过 HTTP 调用 gk_work 的代理 API，不直接调用游戏平台服务

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
你现在是一名资深架构师和 Staff Engineer。在处理本项目时，请遵循：
- **拒绝平庸代码**：优先使用设计模式（如单例、观察者、策略模式）。
- **PHP 规范**：必须严格遵守 PSR-12 标准，使用强类型声明。
- **Webman 特定优化**：考虑到 Webman 的常驻内存特性，注意单例模式下的变量污染和数据库连接池的使用。
- **性能瓶颈**（如 N+1 查询、内存溢出）。
- **可维护性**（类型提示、完善的注释、低耦合）。
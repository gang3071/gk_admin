# ExAdmin Framework Complete Guide

ExAdmin is a powerful admin UI framework. Understanding its patterns is crucial for this project.

## Routing System

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

## Grid Component (List/Table View)

Grid displays data in tables with filters, sorting, and actions.

### Basic Structure

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

### Advanced Grid Patterns

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

### Grid Components Reference

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

## Form Component (Create/Edit View)

Form handles data creation and editing with validation.

### Basic Structure

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

### Field Types

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

### Form Hooks

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

## Excel Export System

ExAdmin provides a powerful Excel export system for Grid data. Follow these strict rules to avoid runtime errors.

### Standard Export Implementation

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

### Complete Exporter Example

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

### Using Export in Controller

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

### Common Export Errors

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

## Common UI Components

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

## Message & Response Helpers

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

## Common Patterns

### 1. Grid with Custom Query

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

### 2. Form with Transaction

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

### 3. Modal Form Button

```php
$grid->column('action')->display(function ($val, $data) {
    return Button::create('Edit')
        ->type('primary')
        ->size('small')
        ->modal([$this, 'save'], ['id' => $data['id']])
        ->width('60%');
});
```

### 4. Conditional Fields

```php
$form->radio('type', 'Type')->options([1 => 'A', 2 => 'B'])
    ->when(1, function (Form $form) {
        $form->text('field_for_a');
    })
    ->when(2, function (Form $form) {
        $form->text('field_for_b');
    });
```

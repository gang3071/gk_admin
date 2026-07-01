# Database Field Deletion Standard Process

**IMPORTANT: This process is MANDATORY when deleting any database field. Skipping any step can cause production bugs!**

**Real incident:** In 2026-06, we created migrations to drop `ball_result`, `draw_time`, and `preheat_start_time` fields but forgot to clean up 9 code references. This caused runtime errors when the code tried to access deleted fields.

## Step 1: Global Search Across All 3 Projects ⭐

Before creating any migration file, search for ALL usages of the field:

```bash
# Search in gk_admin
grep -rn "field_name" D:/gk_admin/addons/webman/controller/
grep -rn "field_name" D:/gk_admin/addons/webman/model/
grep -rn "field_name" D:/gk_admin/addons/webman/service/
grep -rn "field_name" D:/gk_admin/process/

# Search in gk_api
grep -rn "field_name" D:/gk_api/app/api/controller/
grep -rn "field_name" D:/gk_api/app/model/

# Search in gk_work
grep -rn "field_name" D:/gk_work/app/service/
grep -rn "field_name" D:/gk_work/app/wallet/
grep -rn "field_name" D:/gk_work/process/

# Also check for property access patterns
grep -rn "\->field_name" D:/gk_admin/ D:/gk_api/ D:/gk_work/
grep -rn "'field_name'" D:/gk_admin/ D:/gk_api/ D:/gk_work/
```

**Important:** Also check for:
- Commented code: `// $activity->field_name`
- String literals: `'field_name' =>`, `"field_name"`
- Database queries: `->select('field_name')`, `->where('field_name')`
- Vue/JS files: `activity.field_name`

## Step 2: Create Cleanup Checklist

Document ALL found references in a checklist file (e.g., `FIELD_CLEANUP_field_name.md`):

```markdown
# Field Cleanup Checklist: field_name

## gk_admin
- [ ] D:/gk_admin/addons/webman/controller/XXXController.php:123
- [ ] D:/gk_admin/addons/webman/service/XXXService.php:456
- [ ] D:/gk_admin/addons/webman/views/xxx.vue:789

## gk_api
- [ ] D:/gk_api/app/api/controller/v1/XXXController.php:234

## gk_work
- [ ] D:/gk_work/app/service/XXXService.php:567

Total: 5 references to clean
```

## Step 3: Clean Up Code References One by One

For each reference in the checklist:

**Option A: Remove the code**
```php
// ❌ Before
if (!empty($activity->field_name)) {
    // do something
}

// ✅ After (deleted)
// Code removed as field_name is no longer available
```

**Option B: Replace with alternative logic**
```php
// ❌ Before
if (empty($activity->ball_result)) {
    throw new \Exception('Not drawn yet');
}

// ✅ After (use status instead)
$allowedStatuses = [
    LotteryTicketActivity::STATUS_DRAWING,
    LotteryTicketActivity::STATUS_ENDED,
];
if (!in_array($activity->status, $allowedStatuses)) {
    throw new \Exception('Not in drawing status');
}
```

**Option C: Add deprecation comment (if keeping for backward compatibility)**
```php
/**
 * ⭐ Deprecated: field_name field was removed in v2.0
 * Use new_field instead
 * 
 * @deprecated Will be removed in v3.0
 */
public function getFieldName() {
    return $this->new_field;
}
```

After cleaning each reference:
- Mark it as ✅ in the checklist
- Test the affected functionality
- Commit the change with clear message

## Step 4: Verify All References Cleaned ⭐

After cleaning all checklist items, verify with search again:

```bash
# These searches MUST return 0 results!
grep -rn "field_name" D:/gk_admin/addons/webman/ --exclude-dir=migrations
grep -rn "field_name" D:/gk_api/app/ --exclude-dir=migrations
grep -rn "field_name" D:/gk_work/app/ --exclude-dir=migrations

# Also check property access
grep -rn "\->field_name" D:/gk_admin/ D:/gk_api/ D:/gk_work/ --exclude-dir=migrations
```

**If any results found:** Go back to Step 3 and clean them!

**Only when search results = 0:** Proceed to Step 5.

## Step 5: Create Migration File

**NOW you can safely create the migration to drop the field:**

```php
<?php
use Phinx\Migration\AbstractMigration;

class RemoveFieldNameField extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('table_name');
        
        // Remove the field
        $table->removeColumn('field_name')
              ->update();
        
        // Document WHY we're removing it
        // Reason: Replaced by new_field / No longer used / Feature deprecated
    }
}
```

**Migration file naming convention:**
- Format: `YYYYMMDDHHMMSS_remove_field_name_field.php`
- Example: `20260616000002_remove_ball_result_field.php`

**IMPORTANT Notes:**
- Put migration in `D:/gk_api/db/migrations/` (shared database)
- Add clear comment explaining why field is being removed
- Reference the commit/PR that cleaned up the code

## Step 6: Test in Development Environment

```bash
# Run migration in dev/test environment first
cd D:/gk_api
vendor/bin/phinx migrate -e development

# Test all affected features thoroughly
# - Test CRUD operations on the model
# - Test related API endpoints
# - Test Vue components that used the field
# - Check for any console errors

# If any errors found:
vendor/bin/phinx rollback  # Rollback migration
# Fix the code issues
# Re-verify Step 4
# Try migration again
```

## Step 7: Commit and Document

```bash
# Commit the migration file
git add db/migrations/YYYYMMDDHHMMSS_remove_field_name_field.php
git commit -m "🗑️ Remove field_name field

Reason: [Why the field is being removed]

Code cleanup completed in commits:
- [commit hash 1]: Clean up controller references
- [commit hash 2]: Clean up service references
- [commit hash 3]: Clean up API references

Verified with global search - 0 references remaining.

Related: #[issue number] (if applicable)
"
```

**Update project documentation:**
- Add entry to CHANGELOG.md
- Update API documentation if field was in API responses
- Update model PHPDoc to remove `@property` annotation

## Step 8: Deploy to Production

**Pre-deployment checklist:**
- ✅ All code cleanup merged to main branch
- ✅ Migration tested in staging environment
- ✅ Backward compatibility handled (if needed)
- ✅ Team notified about the change

**Deployment sequence:**
1. Deploy code changes first (with compatibility checks)
2. Run migration on production database
3. Monitor logs for any field-related errors
4. Have rollback plan ready

---

## ⚠️ Common Mistakes to Avoid

**❌ WRONG: Create migration first, clean code later**
```bash
# This will cause production bugs!
phinx create RemoveFieldName  # Migration created
# ... field dropped in production
# ... code still accessing the field → CRASH!
```

**✅ CORRECT: Clean code first, then create migration**
```bash
# 1. Search and clean all code references
grep -rn "field_name" ...
# Fix all found references
git commit -m "Clean up field_name references"

# 2. Verify cleanup
grep -rn "field_name" ...  # Must be 0 results

# 3. NOW create migration
phinx create RemoveFieldName
```

**❌ WRONG: Search only in one project**
```bash
# Incomplete search!
grep -rn "field_name" D:/gk_admin/
# Forgot to check gk_api and gk_work!
```

**✅ CORRECT: Search across all 3 projects**
```bash
for project in gk_admin gk_api gk_work; do
    echo "=== Searching in $project ==="
    grep -rn "field_name" D:/$project/
done
```

**❌ WRONG: Only search for `field_name`**
```bash
grep -rn "field_name" ...
# Missed: ->field_name, 'field_name', $data['field_name']
```

**✅ CORRECT: Search multiple patterns**
```bash
grep -rn "\->field_name\|'field_name'\|\['field_name'\]" ...
```

---

## 📋 Quick Reference Checklist

Use this checklist for EVERY field deletion:

```
Database Field Deletion Checklist

Field name: __________________
Table name: __________________
Reason for deletion: __________________

Pre-deletion:
[ ] Step 1: Global search completed (all 3 projects)
[ ] Step 2: Cleanup checklist created
[ ] Step 3: All code references cleaned
[ ] Step 4: Verified with search (0 results)
[ ] Step 5: Migration file created
[ ] Step 6: Tested in dev environment
[ ] Step 7: Committed and documented
[ ] Step 8: Deployed to production

Verified by: __________________
Date: __________________
```

**Remember:** Deleting a database field is a **3-project operation**, not just a migration file!

---

## Real Example: ball_result Field Removal

**Background:** We removed automatic ball drawing feature and needed to delete the `ball_result` field.

**What went wrong initially:**
- Created migration to drop field
- Forgot to search for code usage
- 9 references remained in code:
  - 2 in ChannelLotteryTicketRecordController (validation)
  - 2 in ChannelLotteryTicketStatisticsController (stats)
  - 1 in ChannelLotteryTicketActivityController (getBallResult method)
  - 2 in LotteryTicketPushService (pushDrawResult method)
  - 2 in gk_api LotteryTicketController (API response)

**Result:** Production errors when code tried to access deleted field.

**How we fixed it (following this process):**

1. **Global search:**
   ```bash
   grep -rn "ball_result" D:/gk_admin/ D:/gk_api/
   # Found 9 references
   ```

2. **Created cleanup plan:**
   - Replace `ball_result` checks with `status` checks
   - Delete deprecated methods
   - Update API responses

3. **Code cleanup commits:**
   - gk_admin commit f4c1267: Fixed 7 references
   - gk_api commit 0bf5c48: Fixed 2 references

4. **Verification:**
   ```bash
   grep -rn "\->ball_result" D:/gk_admin/ D:/gk_api/
   # 0 results ✅
   ```

5. **Migration already existed** (20260616000002_remove_ball_result_field.php)

**Lesson learned:** Always clean code BEFORE creating migration, not after!

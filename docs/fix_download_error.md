# Fix Download Error - Troubleshooting Guide

## The Errors Explained

### Error 1: Language Strings Missing
```
Invalid get_string() identifier: 'col_testpages' or component 'local_aiagentblock'
Invalid get_string() identifier: 'col_stepsperpage' or component 'local_aiagentblock'
Invalid get_string() identifier: 'col_totalsteps' or component 'local_aiagentblock'
```

**Cause:** The updated language file wasn't uploaded or Moodle's cache hasn't been cleared.

### Error 2: Output Buffering
```
Output can not be buffered before instantiating table_dataformat_export_format
```

**Cause:** The `$PAGE->set_*()` calls output HTML headers, but download mode needs to start BEFORE any output.

---

## Solutions

### Solution 1: Upload Updated report.php ✅

I just fixed the `report.php` file - it now checks for download mode BEFORE setting up the PAGE object.

**Download the updated artifact:** "Updated report.php - Use Database Columns"

### Solution 2: Clear Moodle Cache 🔧

After uploading the language file, you MUST clear the cache:

**Option A: Via Admin Interface**
1. Go to: **Site administration > Development > Purge all caches**
2. Click **"Purge all caches"**

**Option B: Via Command Line (faster)**
```bash
cd /path/to/moodle
php admin/cli/purge_caches.php
```

**Option C: Via Admin CLI**
```bash
php admin/cli/upgrade.php
```

### Solution 3: Verify Language File Upload 📁

Check that the language file was uploaded to the correct location:

**Correct path:**
```
/path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
```

**Check file exists:**
```bash
ls -la /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
```

**Check permissions:**
```bash
# Should be readable by web server
chmod 644 /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
```

### Solution 4: Verify String is in File 🔍

Check the language file contains the new strings:

```bash
grep "col_testpages" /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
grep "col_stepsperpage" /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
grep "col_totalsteps" /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
```

Expected output:
```
$string['col_testpages'] = 'Test Pages';
$string['col_stepsperpage'] = 'Steps/Page';
$string['col_totalsteps'] = 'Total Steps';
```

---

## Complete Fix Procedure

### Step 1: Upload Updated Files

Upload these 3 files (the language file and report are most critical):

1. ✅ **lang/en/local_aiagentblock.php** - Contains the missing strings
2. ✅ **report.php** - Fixed output buffering issue
3. ✅ **db/upgrade.php** - Database schema
4. ✅ **classes/observer.php** - Populates new columns
5. ✅ **version.php** - Triggers upgrade

### Step 2: Run Upgrade

Go to: **Site administration > Notifications**

Click: **"Upgrade Moodle database now"**

### Step 3: Clear All Caches

**CRITICAL:** Go to: **Site administration > Development > Purge all caches**

### Step 4: Test Download

1. Go to the report page
2. Try to download again
3. Should work now!

---

## If Still Not Working

### Debug Step 1: Check Language File Loaded

Add this to the TOP of report.php temporarily:

```php
echo '<pre>';
echo 'Test Pages string: ' . get_string('col_testpages', 'local_aiagentblock') . "\n";
echo 'Steps Per Page string: ' . get_string('col_stepsperpage', 'local_aiagentblock') . "\n";
echo 'Total Steps string: ' . get_string('col_totalsteps', 'local_aiagentblock') . "\n";
echo '</pre>';
die();
```

**Expected output:**
```
Test Pages string: Test Pages
Steps Per Page string: Steps/Page
Total Steps string: Total Steps
```

**If you see errors:** Language file not loaded correctly.

### Debug Step 2: Check File Syntax

Check for PHP syntax errors:

```bash
php -l /path/to/moodle/local/aiagentblock/lang/en/local_aiagentblock.php
```

Expected output:
```
No syntax errors detected
```

### Debug Step 3: Force Cache Clear

If normal cache clearing doesn't work:

```bash
# Remove cache files manually
rm -rf /path/to/moodledata/cache/*
rm -rf /path/to/moodledata/localcache/*

# Then purge via web interface
```

---

## Alternative: Use Old Column Names Temporarily

If you need a quick fix while troubleshooting, you can temporarily use the old column scheme.

In `report.php`, change these lines (around line 47-49):

**FROM:**
```php
'testpages',
'totalsteps',
'stepsperpage',
```

**TO:**
```php
'browser', // Temporary - use browser field instead
```

And remove the corresponding `define_headers` entries.

This will show the old "Browser" column until you get the language strings working.

---

## Checklist

Before trying download again:

- [ ] Uploaded `lang/en/local_aiagentblock.php` to correct location
- [ ] Uploaded updated `report.php` (with PAGE setup fix)
- [ ] Ran database upgrade (Site admin > Notifications)
- [ ] Cleared ALL caches (Development > Purge all caches)
- [ ] Hard refreshed browser (Ctrl+Shift+R or Cmd+Shift+R)
- [ ] Verified language file contains `col_testpages` string
- [ ] Checked file permissions (644)

---

## Most Likely Cause

**90% chance:** You uploaded the files but didn't clear Moodle's cache.

**Solution:** 
1. Go to **Site administration > Development > Purge all caches**
2. Click the button
3. Try download again

The language strings are cached, so Moodle won't see the new ones until you purge!

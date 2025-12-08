# Database Schema Upgrade - Implementation Guide

## Overview

This upgrade adds **8 new columns** to the `local_aiagentblock_log` table to store timing metrics separately, making reports much more useful and sortable.

---

## Files Updated

### 1. ✅ `db/upgrade.php` (NEW)
Adds database upgrade logic to create new columns.

### 2. ✅ `classes/observer.php` (MODIFIED)
Updates `log_timing_detection()` to populate new columns.

### 3. ✅ `report.php` (MODIFIED)
Reads from new columns instead of parsing `browser` field.

### 4. ✅ `lang/en/local_aiagentblock.php` (MODIFIED)
Adds proper column header strings.

### 5. ✅ `version.php` (MODIFIED)
Bumps version to `2025120800` to trigger upgrade.

---

## New Database Columns

| Column Name | Type | Description | Example |
|-------------|------|-------------|---------|
| `duration_seconds` | INT(10) | Total time in seconds | 150, 180 |
| `question_count` | INT(5) | Number of questions | 5, 10 |
| `grade_percent` | DECIMAL(5,2) | Grade percentage | 80.00, 100.00 |
| `cv_percent` | DECIMAL(5,2) | Timing variance CV% | 4.30, 31.40 |
| `test_pages` | INT(5) | Pages ≥10 seconds | 3, 5 |
| `total_pages` | INT(5) | All pages | 5, 7 |
| `total_steps` | INT(10) | Total interactions | 10, 15, 50 |
| `steps_per_page` | DECIMAL(4,2) | Average steps/page | 2.0, 3.5 |

---

## Installation Steps

### Step 1: Upload Updated Files

Upload these files to your Moodle installation:

```bash
local/aiagentblock/db/upgrade.php          # NEW FILE
local/aiagentblock/classes/observer.php    # UPDATED
local/aiagentblock/report.php              # UPDATED
local/aiagentblock/lang/en/local_aiagentblock.php  # UPDATED
local/aiagentblock/version.php             # UPDATED
```

### Step 2: Trigger Moodle Upgrade

Navigate to:
```
Site administration > Notifications
```

Moodle will detect the version change and show:
```
✅ AI Agent Blocker (local_aiagentblock) will be upgraded from v1.1.0 to v1.2.0
```

Click **"Upgrade Moodle database now"**

### Step 3: Verify Upgrade

Check that the upgrade succeeded:

```sql
-- Check if new columns exist
DESCRIBE mdl_local_aiagentblock_log;
```

You should see:
```
duration_seconds    | int(10)
question_count      | int(5)
grade_percent       | decimal(5,2)
cv_percent          | decimal(5,2)
test_pages          | int(5)
total_pages         | int(5)
total_steps         | int(10)
steps_per_page      | decimal(4,2)
```

### Step 4: Test with New Quiz Attempt

1. Take a quiz (or have AI agent take one)
2. Check the detection log:

```sql
SELECT 
    duration_seconds,
    question_count,
    grade_percent,
    cv_percent,
    test_pages,
    total_pages,
    total_steps,
    steps_per_page
FROM mdl_local_aiagentblock_log
ORDER BY timecreated DESC
LIMIT 1;
```

Expected output:
```
duration_seconds: 150
question_count: 5
grade_percent: 80.00
cv_percent: 4.30
test_pages: 3
total_pages: 5
total_steps: 10
steps_per_page: 2.00
```

### Step 5: View Updated Report

Navigate to:
```
Course > Reports > AI Agent Detections
```

**Expected columns (in order):**
1. Username
2. Timestamp
3. IP Address
4. AI Agent
5. **Duration** (e.g., "2.5 min")
6. **Questions** (e.g., "5")
7. **Grade** (e.g., "80%")
8. **CV%** (e.g., "4.3%")
9. **Test Pages** (e.g., "3/5")
10. **Total Steps** (e.g., "10")
11. **Steps/Page** (e.g., "2.0")
12. Suspicion Score
13. Location
14. Detection Method

---

## What Changed in the Report

### Before (Broken)

```
Browser column:
"Time: 2.5 min (150 sec) | Questions: 5 | Pages: 3/5 | Steps: 10 (2.0/pg) | CV%: 4.3% | Grade: 80.0% | ..."

Column Headers:
[[col_testpages1]]  [[col_stepsperpage1]]  [[col_totalsteps1]]
       N/A                    N/A                   N/A
```

**Problems:**
- All data crammed into one field
- Can't sort by time, grade, CV%, etc.
- Column headers showing as placeholders
- Metrics not extracted properly

### After (Fixed)

```
Duration  Questions  Grade  CV%   Test Pages  Total Steps  Steps/Page
2.5 min      5       80%   4.3%      3/5          10          2.0
```

**Benefits:**
- ✅ Sortable by any metric
- ✅ Proper column headers
- ✅ Color-coded badges (low CV% = red/danger)
- ✅ Tooltips on hover
- ✅ Clean CSV export
- ✅ Easy analysis

---

## Report Features

### Color Coding

**CV% (Timing Variance):**
- 🔴 **< 5%** = Robotic (Red badge)
- 🟡 **5-10%** = Very Consistent (Yellow badge)
- 🟢 **10-30%** = Normal (Green badge)
- 🔵 **30%+** = High Variance (Blue badge)

**Grade:**
- 🟢 **≥90%** = Success (Green)
- 🔵 **70-89%** = Good (Blue)
- 🟡 **<70%** = Lower (Yellow)

**Steps/Page:**
- 🔴 **< 2** = Very Low (Red text)
- 🟡 **2-3** = Low (Yellow text)
- ⚪ **> 3** = Normal (Black text)

**Suspicion Score:**
- 🔴 **≥90** = Critical (Red badge)
- 🟡 **70-89** = High (Yellow badge)
- 🔵 **50-69** = Moderate (Blue badge)
- ⚪ **<50** = Low (Gray badge)

### Sortable Columns

Click column headers to sort by:
- Duration (shortest to longest)
- Questions (fewest to most)
- Grade (lowest to highest)
- CV% (most robotic to most human)
- Test Pages (fewest to most)
- Total Steps (fewest to most)
- Steps/Page (lowest to highest)
- Suspicion Score (highest to lowest)

### Tooltips

Hover over metrics for explanations:
- **Test Pages:** "Question pages (≥10sec) / Total pages"
- **Total Steps:** "Total interaction steps recorded"
- **Steps/Page:** "Average interaction steps per page"

---

## Backward Compatibility

### Old Records (Before Upgrade)

Records created before this upgrade will have:
- `duration_seconds` = NULL
- `question_count` = NULL
- All new columns = NULL

**Display:** Shows "N/A" in report for these records.

**Solution:** The `browser` field still contains the full text, so you can manually parse old records if needed.

### No Data Loss

- ✅ Existing data preserved
- ✅ `browser` field unchanged (still contains full details)
- ✅ New columns added alongside existing data
- ✅ Old reports still work

---

## Database Queries for Analysis

### Find AI Agents by CV%

```sql
SELECT 
    CONCAT(u.firstname, ' ', u.lastname) as student,
    l.cv_percent,
    l.duration_seconds,
    l.grade_percent,
    l.suspicion_score
FROM mdl_local_aiagentblock_log l
JOIN mdl_user u ON u.id = l.userid
WHERE l.cv_percent < 5.0  -- Robotic timing
ORDER BY l.cv_percent ASC;
```

### Find Fastest Completions

```sql
SELECT 
    CONCAT(u.firstname, ' ', u.lastname) as student,
    l.duration_seconds,
    l.question_count,
    ROUND(l.duration_seconds / l.question_count, 1) as seconds_per_question,
    l.grade_percent
FROM mdl_local_aiagentblock_log l
JOIN mdl_user u ON u.id = l.userid
WHERE l.duration_seconds < 180  -- Under 3 minutes
ORDER BY l.duration_seconds ASC;
```

### Find Low Interaction Attempts

```sql
SELECT 
    CONCAT(u.firstname, ' ', u.lastname) as student,
    l.total_steps,
    l.steps_per_page,
    l.question_count,
    l.suspicion_score
FROM mdl_local_aiagentblock_log l
JOIN mdl_user u ON u.id = l.userid
WHERE l.steps_per_page < 2.0  -- Very few interactions
ORDER BY l.steps_per_page ASC;
```

### Export All Metrics

```sql
SELECT 
    CONCAT(u.firstname, ' ', u.lastname) as student,
    FROM_UNIXTIME(l.timecreated) as attempt_time,
    l.duration_seconds,
    l.question_count,
    l.grade_percent,
    l.cv_percent,
    CONCAT(l.test_pages, '/', l.total_pages) as pages,
    l.total_steps,
    l.steps_per_page,
    l.suspicion_score
FROM mdl_local_aiagentblock_log l
JOIN mdl_user u ON u.id = l.userid
WHERE l.courseid = 7  -- Your course ID
ORDER BY l.timecreated DESC;
```

---

## Troubleshooting

### Issue: Upgrade doesn't run

**Solution:**
1. Check `version.php` has `2025120800`
2. Clear Moodle cache: `Site administration > Development > Purge all caches`
3. Visit: `Site administration > Notifications`

### Issue: Columns show NULL

**Possible causes:**
1. Upgrade didn't run (check DESCRIBE table)
2. No new quiz attempts since upgrade
3. Observer not firing (check detection is enabled)

**Check:**
```sql
-- Check if columns exist
SHOW COLUMNS FROM mdl_local_aiagentblock_log LIKE '%_percent';

-- Check recent records
SELECT duration_seconds, grade_percent, cv_percent 
FROM mdl_local_aiagentblock_log 
ORDER BY timecreated DESC LIMIT 5;
```

### Issue: Column headers still show [[col_...]]

**Solution:**
1. Check `lang/en/local_aiagentblock.php` uploaded correctly
2. Clear Moodle cache
3. Check for typos in column names

### Issue: Report looks wrong

**Check:**
1. Hard refresh browser (Ctrl+Shift+R)
2. Check browser console for JavaScript errors
3. Verify database columns populated

---

## Expected Results

### Test Scenario: AI Agent with Comet

**Database record after upgrade:**
```
duration_seconds: 150
question_count: 5
grade_percent: 100.00
cv_percent: 4.30        ← Robotic (< 5%)
test_pages: 3
total_pages: 5
total_steps: 10
steps_per_page: 2.00    ← Low interaction
suspicion_score: 100
```

**Report display:**
```
Duration: 2.5 min (150 sec)
Questions: 5
Grade: 100% [Green badge]
CV%: 4.3% [Red "Robotic" badge]
Test Pages: 3/5
Total Steps: 10
Steps/Page: 2.0 [Red text - very low]
Suspicion: 100 [Red "Critical" badge]
```

**Interpretation:** Clear AI agent signature
- ✅ Fast completion (2.5 min)
- ✅ Perfect score (100%)
- ✅ Robotic timing (CV% 4.3%)
- ✅ Low interaction (2.0 steps/page)

### Test Scenario: Human Student

**Database record:**
```
duration_seconds: 510
question_count: 5
grade_percent: 82.00
cv_percent: 28.50       ← Normal human variance
test_pages: 5
total_pages: 7
total_steps: 42
steps_per_page: 6.00    ← Normal interaction
suspicion_score: 0
```

**Report display:**
```
Duration: 8.5 min (510 sec)
Questions: 5
Grade: 82% [Blue badge]
CV%: 28.5% [Green "Normal" badge]
Test Pages: 5/7
Total Steps: 42
Steps/Page: 6.0 [Normal black text]
Suspicion: 0 [Gray "Low" badge]
```

**Interpretation:** Normal human pattern
- ✅ Reasonable time (8.5 min)
- ✅ Good but not perfect score (82%)
- ✅ Natural timing variance (CV% 28.5%)
- ✅ Normal interaction (6.0 steps/page)

---

## Next Steps After Upgrade

1. **Test a quiz attempt** - Verify columns populate
2. **Check the report** - Verify display is correct
3. **Analyze patterns** - Look at CV% and steps/page
4. **Export data** - Use CSV download for analysis
5. **Set thresholds** - Adjust if needed based on data

---

## Summary

**What you get:**
- ✅ 8 new database columns for metrics
- ✅ Clean, sortable report columns
- ✅ Color-coded visual indicators
- ✅ Easy CSV export
- ✅ No data loss (browser field preserved)

**What's fixed:**
- ✅ Column headers (no more [[col_...]])
- ✅ Separated metrics (not crammed in browser field)
- ✅ Sortable by any metric
- ✅ Professional-looking reports

**Effort required:**
- Upload 5 files
- Click "Upgrade database"
- 5 minutes total

**Result:**
Much more useful and professional detection reports! 🎉

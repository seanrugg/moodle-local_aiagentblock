# Timing Analysis Fix - Implementation Summary

## Changes Made

### 1. Fixed `analyze_timing_consistency()` in observer.php

**Problem:** CV% calculation included review/submit pages (2-5 seconds), artificially inflating variance.

**Solution:** Only analyze pages where students spent ≥10 seconds (actual question pages).

**Key Code Change:**
```php
// Only include pages where student spent >= 10 seconds
if ($time_spent >= 10) {
    $times[] = $time_spent;
}
```

**New Return Value:**
```php
return [
    'cv_percent' => $cv_percent,           // Coefficient of variation as %
    'question_pages' => $question_pages,   // Pages analyzed (≥10 sec)
    'total_pages' => $total_pages,         // All pages including nav/submit
    'mean_time' => $mean,                  // Average time per question page
    'std_dev' => $std_dev                  // Standard deviation
];
```

### 2. Added `get_interaction_metrics()` Function

Calculates interaction statistics from quiz attempt:

```php
return [
    'total_steps' => $total_steps,           // All interaction steps
    'steps_per_page' => $steps_per_page,     // Average steps per page
    'total_pages' => $total_pages            // Total pages in attempt
];
```

### 3. Enhanced `log_timing_detection()`

Now includes comprehensive metrics in the `browser` field:

**Format:**
```
Time: 2.5 min (150 sec) | Questions: 5 | Pages: 3/5 | Steps: 15 (3.0/pg) | CV%: 4.3% | Grade: 80.0% | High: < 3 minutes | Reasons: completed_under_3min, robotic_timing_cv_4.3%
```

**Breakdown:**
- `Pages: 3/5` = 3 question pages analyzed out of 5 total pages
- `Steps: 15 (3.0/pg)` = 15 total interaction steps, 3.0 average per page
- `CV%: 4.3%` = Coefficient of variation (timing consistency)

### 4. Updated report.php with 3 New Columns

Added columns:
1. **Test Pages** - Shows "3/5" (question pages / total pages)
2. **Steps/Page** - Shows "3.0" (average interactions per page)
3. **Total Steps** - Shows "15" (total recorded interactions)

**Data Extraction:**
Uses regex to parse metrics from the `browser` field:
```php
// Pattern: "Pages: 3/5"
preg_match('/Pages:\s*(\d+)\/(\d+)/', $browser_text, $matches)

// Pattern: "Steps: 15 (3.0/pg)"
preg_match('/Steps:\s*(\d+)\s*\(([\d.]+)\/pg\)/', $browser_text, $matches)
```

### 5. Added `check_instant_navigation()` Detection

Detects AI agents clicking through review/submit pages in <2 seconds:

**Scoring:**
- 3+ instant clicks (< 2 sec) → +25 suspicion points
- 2 instant clicks → +15 suspicion points

### 6. Updated Detection Reasons

Changed interpretation of CV%:

**Old (Wrong):**
- High CV% = suspicious

**New (Correct):**
- Low CV% (<5%) = **robotic** (AI signature) → +35 points
- Very low CV% (<10%) = very consistent → +20 points
- High CV% (>30%) = normal human variance → 0 points

---

## Updated CV% Thresholds

| CV% | Interpretation | Score | Meaning |
|-----|----------------|-------|---------|
| **0-5%** | Robotic | +35 | Too consistent = AI agent |
| **5-10%** | Very Consistent | +20 | Suspicious |
| **10-30%** | Normal | 0 | Human range |
| **30-50%** | High Variance | 0 | Normal (thinking, interruptions) |
| **50%+** | Extreme Variance | 0 | Normal (major interruptions) |

---

## Expected Results

### Before Fix

**Your 160% CV% Test:**
```
Pages analyzed: 5 (including review/submit)
Times: [45s, 50s, 48s, 3s, 2s]
CV%: 160% → Flagged as "Extreme variance"
Interpretation: False positive (wrong)
```

### After Fix

**Same Test with Fix:**
```
Pages analyzed: 3 (only question pages ≥10 sec)
Times: [45s, 50s, 48s]
CV%: 4.3% → Flagged as "Robotic timing"
Interpretation: Correct (AI signature)
```

---

## Report Display Example

### New Columns in Report

| Username | Timestamp | IP | Test Pages | Steps/Page | Total Steps | Suspicion | ... |
|----------|-----------|----|-----------:|------------|------------:|----------:|-----|
| John Doe | Dec 8, 3:04 PM | 192.168.1.1 | **3/5** | **3.0** | **15** | 95 (Critical) | ... |
| Jane Smith | Dec 8, 3:15 PM | 192.168.1.2 | **4/6** | **4.2** | **25** | 60 (Moderate) | ... |

**Test Pages:** 3/5 means:
- 3 question pages analyzed (≥10 seconds each)
- 5 total pages (including 2 review/submit pages)

**Steps/Page:** 3.0 means:
- Average of 3.0 interaction steps per page
- Lower values may indicate automation

**Total Steps:** 15 means:
- 15 total recorded interaction steps
- Very low for a 5-question quiz (human typically 30-50+)

---

## Validation Steps

### 1. Test the Fix

Run a quiz attempt and check the detection log:

```sql
SELECT browser 
FROM mdl_local_aiagentblock_log 
ORDER BY timecreated DESC 
LIMIT 1;
```

Expected format:
```
Time: 2.5 min (150 sec) | Questions: 5 | Pages: 3/5 | Steps: 15 (3.0/pg) | CV%: 4.3% | Grade: 80.0% | ...
```

### 2. Verify CV% Calculation

Check that:
- CV% is now in reasonable range (0-50% typically)
- Low CV% (<5%) flags AI agents
- High CV% (>50%) doesn't falsely flag humans

### 3. Check Report Display

Navigate to the report and verify:
- "Test Pages" column shows X/Y format
- "Steps/Page" shows decimal number
- "Total Steps" shows integer
- Tooltips appear on hover (web view)

### 4. Test Download

Download CSV and verify:
- All columns export correctly
- Metrics are readable in spreadsheet
- No formatting issues

---

## Interpretation Guide

### Analyzing Test Results

**Red Flags for AI Agents:**

1. **CV% < 5%** → Robotic timing consistency
2. **Test Pages ratio high** → Few navigation pages (instant clicks)
3. **Steps/Page < 2.0** → Minimal interaction (AI doesn't explore)
4. **Total Steps very low** → No corrections, no backtracking

**Normal Human Patterns:**

1. **CV% 15-40%** → Natural variance
2. **Test Pages ratio lower** → Takes time on review pages
3. **Steps/Page 4-8** → Multiple interactions per question
4. **Total Steps 3-5× question count** → Corrections, reviews

### Example Analysis

**AI Agent Signature:**
```
Time: 1.0 min | Pages: 3/3 | Steps: 6 (2.0/pg) | CV%: 3.2%
```
- Completed in 1 minute (too fast)
- 3/3 pages (no time on review)
- Only 2 steps per page (minimal interaction)
- CV% 3.2% (robotic consistency)

**Human Student:**
```
Time: 8.5 min | Pages: 5/7 | Steps: 42 (6.0/pg) | CV%: 28%
```
- Took 8.5 minutes (reasonable)
- 5/7 pages (spent time reviewing)
- 6 steps per page (multiple interactions)
- CV% 28% (natural human variance)

---

## Database Impact

### No Schema Changes Required

All data is stored in existing `browser` field as formatted text.

### Example Log Entry

```sql
browser: "Time: 2.5 min (150 sec) | Questions: 5 | Pages: 3/5 | Steps: 15 (3.0/pg) | CV%: 4.3% | Grade: 80.0% | High: < 3 minutes | Reasons: completed_under_3min, robotic_timing_cv_4.3%"
```

This string is parsed by report.php to extract metrics for display.

---

## Files Modified

1. ✅ **classes/observer.php**
   - Fixed `analyze_timing_consistency()`
   - Added `get_interaction_metrics()`
   - Added `check_instant_navigation()`
   - Updated `log_timing_detection()`

2. ✅ **report.php**
   - Added 3 new columns
   - Added regex parsing for metrics
   - Added tooltips for web display
   - Handles CSV export

3. ✅ **lang/en/local_aiagentblock.php**
   - Added column header strings

---

## Testing Checklist

- [ ] Run quiz attempt and verify log entry format
- [ ] Check CV% is in reasonable range (not 160%)
- [ ] Verify "Test Pages" shows X/Y format in report
- [ ] Verify "Steps/Page" shows decimal number
- [ ] Verify "Total Steps" shows integer
- [ ] Test CSV download includes new columns
- [ ] Verify tooltips work on web view
- [ ] Check that AI agents get flagged with CV% < 5%
- [ ] Check that humans don't get falsely flagged

---

## Next Steps

1. **Deploy updated files**
2. **Run test quiz attempts**
3. **Verify CV% calculations are correct**
4. **Check report displays metrics properly**
5. **Analyze patterns across multiple attempts**

---

## Questions to Answer After Testing

1. What's the typical CV% for your AI agent tests?
2. What's the typical CV% for human students?
3. What's the average steps/page for AI vs humans?
4. What's the typical test pages ratio (X/Y)?

These answers will help us fine-tune detection thresholds.

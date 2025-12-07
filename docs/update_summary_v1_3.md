# AI Agent Blocker Plugin - v1.3.0-beta Update Summary

## Overview
This update transforms the plugin from a strict detection system into a comprehensive **behavioral analysis and data collection platform** with more conservative thresholds to reduce false positives.

---

## Key Changes

### 1. **Enhanced Data Collection (New Database Columns)**

The `local_aiagentblock_log` table now captures:

#### Browser/System Details:
- `browser_version` - Specific browser version number
- `os` - Operating system (Windows 10, macOS, Linux, etc.)
- `device_type` - Desktop, Mobile, or Tablet

#### Quiz Performance Metrics:
- `duration_seconds` - Total quiz duration in seconds
- `duration_minutes` - Total quiz duration in minutes (decimal)
- `question_count` - Number of questions in the quiz
- `grade_percent` - Final grade as percentage

#### Behavioral Metrics:
- `answer_changes` - Number of times answers were modified
- `timing_variance` - Coefficient of Variation for question timing
- `timing_std_dev` - Standard deviation of time per question
- `timing_mean` - Mean time spent per question
- `sequential_order` - Whether questions were answered sequentially (1/0)

#### Analysis Data:
- `detection_reasons` - JSON array of specific detection triggers
- `behavior_flags` - JSON array of behavioral flags for pattern analysis

---

### 2. **More Conservative Detection Logic**

#### Removed False Positive Triggers:
- ❌ **Sequential answering** - No longer flagged (most students do this!)
- ❌ **Overly aggressive time thresholds**

#### Adjusted Thresholds:

**Before (too strict):**
```php
if ($minutes < 1) → 100 points
if ($minutes < 2) → 90 points
if ($minutes < 3) → 70 points
```

**After (more reasonable):**
```php
if ($duration < 3 seconds/question) → 100 points (IMPOSSIBLE)
if ($duration < 10 seconds/question) → 50 points (VERY FAST)
if ($duration < 20 seconds/question) → 30 points (FAST)
```

#### Context-Aware Scoring:
- No corrections + fast completion = suspicious
- No corrections + normal speed = NOT suspicious
- Perfect score + very fast = more suspicious
- Low score + very fast = less suspicious (probably guessing)

#### Lowered Logging Threshold:
- **Before:** Log if suspicion ≥ 50
- **After:** Log if suspicion ≥ 30 OR interesting patterns detected
- **Purpose:** Capture more data for analysis while blocking fewer students

---

### 3. **Enhanced Report Display**

#### New Report Columns:
1. Username
2. Date/Time
3. Activity
4. **Duration (min)** - Easy to spot speed outliers
5. **Questions** - Context for duration
6. **Grade %** - Performance indicator
7. Suspicion Score
8. **Timing CV %** - Consistency measure
9. **Answer Changes** - Correction behavior
10. **Sequential** - Order indicator
11. **Browser** - With version
12. **OS** - Operating system
13. **Device** - Desktop/Mobile/Tablet
14. IP Address
15. **Behavior Flags** - Color-coded badges

#### Summary Statistics:
- Total detections
- High suspicion count (≥70)
- Perfect/near-perfect scores
- Very fast completions (<2 min)

#### Export-Friendly:
- CSV download includes all metrics
- Perfect for statistical analysis in Excel/R/Python

---

### 4. **Behavior Flags System**

New granular flags for pattern analysis:

**Critical Flags (Red):**
- `IMPOSSIBLE_TIME` - Physically impossible completion
- `AI_USER_AGENT` - Known AI tool user agent
- `VERY_LOW_VARIANCE` - Suspiciously consistent timing

**Warning Flags (Orange):**
- `VERY_FAST` - Completed very quickly
- `PERFECT_AND_FAST` - High score + fast completion
- `NO_CORRECTIONS` - No answer changes
- `LOW_VARIANCE` - Consistent timing

**Info Flags (Blue):**
- `FAST` - Faster than average
- `HIGH_SCORE_FAST` - Good score + fast
- `MODERATE_VARIANCE` - Normal consistency

**Other Flags:**
- `SEQUENTIAL` - Answered in order (normal)
- `NON_SEQUENTIAL` - Jumped around (also normal)
- `HIGH_VARIANCE` - Varied timing (human-like)
- `LOW_SCORE_FAST` - Fast but poor performance

---

## Files Updated

### New Files:
1. **db/install.xml** - Updated schema with new columns
2. **classes/observer.php** - Completely rewritten detection logic

### Updated Files:
1. **db/upgrade.php** - Adds new columns (version 2025120503)
2. **report.php** - Enhanced display with all new metrics
3. **version.php** - Updated to v1.3.0-beta

---

## Installation Instructions

### 1. **Update Files on GitHub:**
- Replace: `classes/observer.php`
- Replace: `db/install.xml`
- Replace: `db/upgrade.php`
- Replace: `report.php`
- Replace: `version.php`

### 2. **Pull to Server:**
```bash
cd /var/www/vhosts/cucorn.com/lms.cucorn.com/local/aiagentblock
git pull origin main
```

### 3. **Run Database Upgrade:**
- Go to: **Site administration → Notifications**
- Click: **"Upgrade Moodle database now"**
- This will add all new columns to existing tables

### 4. **Clear Old Data (Recommended):**
Before collecting new comprehensive data:
- Go to: **Site administration → Plugins → Local plugins → AI Agent Blocker → Delete Detection Records**
- Click: **"Delete All Records"**
- This gives you a clean slate for analysis

---

## Benefits for Data Analysis

### 1. **Identify Legitimate Fast Students:**
```
Student A: 3 min, 100%, CV 45%, 2 corrections → LIKELY LEGITIMATE
Student B: 3 min, 100%, CV 8%, 0 corrections → SUSPICIOUS
```

### 2. **Statistical Analysis:**
Export CSV and analyze in Excel/R/Python:
- Distribution of completion times
- Correlation between speed and accuracy
- Timing variance patterns
- Answer correction behaviors

### 3. **Refine Thresholds:**
After collecting real-world data:
- Calculate class medians and standard deviations
- Identify true outliers using statistical methods
- Adjust detection thresholds based on actual patterns

### 4. **False Positive Analysis:**
Track which flags appear together:
- Which combinations indicate real AI?
- Which flags appear in legitimate attempts?
- Adjust scoring based on findings

---

## Testing Recommendations

### Phase 1: Data Collection (2-3 weeks)
- Let plugin run in "observation mode"
- Collect comprehensive behavioral data
- Review flagged attempts manually

### Phase 2: Analysis
- Export all detection data to CSV
- Analyze patterns and distributions
- Identify true vs. false positives

### Phase 3: Refinement
- Adjust thresholds based on findings
- Update scoring weights
- Add/remove behavioral flags

### Phase 4: Production
- Enable blocking for high-confidence detections only
- Continue monitoring and adjusting

---

## CSV Export Columns

When you export the report, you'll get:

```csv
Username, Date/Time, Activity, Duration (min), Questions, Grade %, 
Suspicion Score, Timing CV %, Answer Changes, Sequential, Browser, 
OS, Device, IP Address, Behavior Flags
```

Perfect for:
- Excel pivot tables
- Statistical analysis in R
- Machine learning in Python
- Pattern identification

---

## Next Steps

1. ✅ Update files on GitHub
2. ✅ Pull to server and run upgrade
3. ✅ Delete old detection records
4. ✅ Monitor for 2-3 weeks
5. 📊 Export data and analyze
6. 🔧 Refine thresholds
7. 🚀 Enable strict mode for production

---

## Support for Analysis

If you need help with data analysis:
- Python script to analyze CSV exports
- R script for statistical analysis
- Excel template with pivot tables
- Visualization recommendations

Let me know what would be most helpful!

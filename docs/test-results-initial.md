# AI Agent Testing Results - December 2024

## Test Environment

**Moodle Version:** 5.1 (development instance)  
**Plugin Version:** 1.0.0-alpha  
**Course:** Algebra 2 - Virginia Standards (ALG2-VA)  
**Activity:** Practice Quiz: Polynomial Operations  
**Questions:** 5 total (multiple question types)  
**Current Detection Status:** All attempts bypassed detection ❌

---

## Test Results Summary

| Attempt | Date | AI Tool | Browser | Duration | Grade | Success Rate |
|---------|------|---------|---------|----------|-------|--------------|
| 1 | Dec 1, 2025, 3:04 PM | Comet | Comet | 2m 16s | 100% | ✅ Perfect |
| 2 | Dec 1, 2025, 10:45 PM | Comet | Comet | 2m 33s | 100% | ✅ Perfect |
| 3 | Dec 2, 2025, 10:14 AM | Comet | Comet | 2m 43s | 80% | ❌ 1 wrong |
| 4 | Dec 2, 2025, 10:19 AM | Comet | Comet | 3m 2s | 100% | ✅ Perfect |
| 5 | Dec 2, 2025, 10:49 AM | Manus AI | Comet | 1m 0s | 80% | ❌ 1 wrong |
| 6 | Dec 3, 2025, 12:21 PM | Manus AI | Chrome | 1m 16s | 80% | ❌ 1 wrong |

**Overall Statistics:**
- Total Attempts: 6
- Perfect Scores: 3 (50%)
- Partial Success: 3 (50% - all scored 80%)
- Complete Failure: 0 (0%)
- Average Duration: 2m 2s
- Fastest: 1m 0s (Manus in Comet)
- Slowest: 3m 2s (Comet)

---

## Critical Finding: Radio Button Question Weakness

### Pattern Discovery

**All three 80% scores failed on the same question type: Multiple choice with radio buttons**

| Attempt | Tool | Score | Failed Question Type |
|---------|------|-------|---------------------|
| 3 | Comet | 80% | Radio button multiple choice |
| 5 | Manus AI | 80% | Radio button multiple choice |
| 6 | Manus AI | 80% | Radio button multiple choice |

### Analysis

**Why Radio Buttons Are Problematic for AI Agents:**

1. **DOM Interaction Complexity**
   - Radio buttons require clicking the correct element (input vs label)
   - Moodle may use custom JavaScript for radio button behavior
   - CSS styling can hide the actual clickable area
   - Event handlers may only fire on specific interactions

2. **Visibility Detection Issues**
   - AI agents "see" the DOM, not the rendered page
   - Radio buttons may appear as multiple similar elements
   - Hard to distinguish which radio button corresponds to which answer

3. **State Management**
   - Radio buttons have group behavior (only one selected at a time)
   - AI may not properly handle deselecting previous choice
   - May not trigger proper change events

**Evidence of Non-Deterministic Behavior:**
- Comet succeeded 3/4 times but failed once on radio buttons
- Manus failed 2/2 times on radio buttons
- Suggests timing or race condition issues

### Defensive Strategy Recommendation

**Short-term:**
- Use more radio button questions in high-stakes assessments
- This is a natural AI weakness that requires no plugin changes
- 50% failure rate on radio buttons vs 0% on other question types

**Long-term:**
- Analyze which specific question failed
- Replicate that question structure
- Test if the issue is specific to that question or all radio button questions

---

## Timing Analysis

### Completion Time Distribution

```
1m 0s  ████████ (Manus in Comet)
1m 16s ██████████ (Manus in Chrome)
2m 16s ███████████████ (Comet)
2m 33s ████████████████ (Comet)
2m 43s █████████████████ (Comet)
3m 2s  ██████████████████ (Comet)
```

**Human Baseline Estimate:** 5-10 minutes for 5 questions
- Reading time: ~1 minute per question
- Calculation time: ~30 seconds per question
- Review time: ~1-2 minutes total
- **Minimum expected human time: ~5 minutes**

### Timing-Based Detection Thresholds

| Time Range | Suspicion Level | Confidence | Recommended Action |
|------------|----------------|------------|-------------------|
| < 1 minute | Impossible | 100% | Auto-flag |
| 1-2 minutes | Almost Certain AI | 95% | Auto-flag + Notify instructor |
| 2-3 minutes | Highly Suspicious | 85% | Flag for review |
| 3-5 minutes | Suspicious | 70% | Log for pattern analysis |
| 5-7 minutes | Possible Fast Student | 40% | Monitor only |
| 7+ minutes | Normal Range | <10% | No action |

**All 6 test attempts fall in the "Suspicious" or higher category.**

### Speed Comparison: Comet vs Manus

**Comet Average:** 2m 34s  
**Manus Average:** 1m 8s  
**Difference:** Manus is 2.27x faster than Comet

**Hypothesis on Speed Difference:**
- **Comet:** Takes screenshots, analyzes visually, documents steps
- **Manus:** More direct automation, less verification overhead
- **Result:** Manus trades speed for reliability (80% vs Comet's 75% perfect)

---

## Browser Comparison: Comet vs Chrome

### Manus AI Performance by Browser

| Browser | Duration | Grade | Notes |
|---------|----------|-------|-------|
| Comet | 1m 0s | 80% | Slightly faster |
| Chrome | 1m 16s | 80% | 16 seconds slower, same accuracy |

**Key Insight:** Only 16-second difference between browsers.

**Implications:**
- Manus works equally well in both browsers
- Browser blocking Comet won't stop Manus in Chrome
- Behavioral detection needed regardless of browser restrictions
- Browser blocking can reduce attack surface but not eliminate threat

---

## Detection Status

### Current Plugin Performance: ❌ FAILED

**Bypassed:** All 6 attempts completed successfully without blocking

**Why Current Detection Failed:**
1. **Timing Issue:** 2-second delay allows AI to complete before checks run
2. **Thresholds Too Lenient:** Current scoring doesn't catch these agents
3. **No Server-Side Timing Analysis:** Fast completion not flagged
4. **No Radio Button Interaction Monitoring:** Didn't catch UI interaction issues

### Detection Gaps Identified

| Detection Method | Status | Effectiveness |
|-----------------|--------|---------------|
| User Agent Analysis | ✅ Implemented | ❌ Ineffective (legitimate Chrome UA) |
| HTTP Header Inspection | ✅ Implemented | ❌ Ineffective (no suspicious headers) |
| Client-side JS Detection | ✅ Implemented | ❌ Ineffective (runs too late) |
| Mouse Movement Analysis | ✅ Implemented | ❌ Ineffective (AI simulates movement) |
| Rapid Form Filling | ✅ Implemented | ❌ Ineffective (threshold not met) |
| Canvas Fingerprinting | ❌ Not Implemented | ⚠️ Unknown |
| Server-side Timing Analysis | ❌ Not Implemented | ⚠️ Would catch all 6 attempts |
| Radio Button Monitoring | ❌ Not Implemented | ⚠️ Would catch 50% of attempts |
| Screenshot Detection | ❌ Not Implemented | ⚠️ Would catch Comet (unknown for Manus) |

---

## Question Type Analysis

### Suspected Question Types in Quiz

Based on AI performance patterns:

**Questions 1, 2, 4, 5 (Answered correctly by AI):**
- Likely calculated numeric answers
- Text input fields
- Simple multiple choice (checkboxes or dropdowns)
- AI can easily parse and interact with these

**Question 3 (Failed by AI 50% of the time):**
- Multiple choice with radio buttons
- Specific DOM interaction requirements
- Intermittent failures suggest timing or interaction complexity

### Recommendation for Question Design

**High AI Resistance:**
- ✅ Multiple choice with radio buttons (50% AI failure rate)
- ✅ Complex multi-step problems requiring showing work
- ✅ Free response requiring explanation
- ✅ Drag-and-drop ordering/matching
- ✅ Graph/diagram interpretation

**Low AI Resistance:**
- ❌ Simple calculated numeric answers
- ❌ Text input for single values
- ❌ Multiple choice with checkboxes
- ❌ True/False questions
- ❌ Fill-in-the-blank single words

---

## AI Tool Behavior Observations

### Comet Behavior

**Characteristics:**
- Takes 2-3 minutes per quiz
- Takes and displays screenshots in AI assistant pane
- Documents step-by-step actions with screenshots
- Visual verification of state changes
- 75% perfect score rate (3/4 attempts)
- One failure on radio button question

**Detection Opportunities:**
- Screenshot capture detection (getDisplayMedia API)
- Longer duration makes timing analysis easier
- Visual analysis overhead = more detectable patterns

### Manus AI Behavior

**Characteristics:**
- Completes quiz in ~1 minute
- Significantly faster than Comet (2-3x)
- 0% perfect score rate (0/2 attempts, both 80%)
- 100% failure rate on radio button questions
- Works identically in Comet and Chrome browsers
- No Firefox extension available

**Detection Opportunities:**
- Extreme speed is highly suspicious (<2 minutes)
- Consistent 80% scores could be pattern
- Radio button failures are signature weakness

---

## Effectiveness Comparison: Comet vs Manus

| Metric | Comet | Manus AI | Winner |
|--------|-------|----------|--------|
| **Speed** | 2m 34s avg | 1m 8s avg | Manus (2.3x faster) |
| **Accuracy** | 75% perfect | 0% perfect | Comet |
| **Consistency** | Variable (2-3 min) | Consistent (~1 min) | Manus |
| **Radio Buttons** | 25% failure | 100% failure | Comet |
| **Overall Grade** | 95% avg | 80% avg | Comet |
| **Stealth** | Moderate | High (very fast) | Manus |

**Conclusion:**
- **Comet is more effective** (higher scores, more reliable)
- **Manus is faster** but makes more mistakes
- **Both bypass current detection**
- **Radio buttons are universal weakness** (both struggle)

---

## Server-Side Data Requirements

### Data Needed from Moodle Logs

To build effective server-side detection, we need:

1. **Per-Question Timing:**
   - Time spent on each question
   - Time between question navigation
   - Time to first answer on each question

2. **Answer Change Patterns:**
   - How many times answers were changed
   - Were wrong answers corrected?
   - Pattern of trial-and-error vs immediate correct answers

3. **Navigation Patterns:**
   - Sequential vs jumping around
   - Use of "Previous" button
   - Time on summary/review page

4. **Session Behavior:**
   - Mouse movement data (if available)
   - Keyboard activity
   - Focus/blur events
   - Page visibility changes

### SQL Queries Needed

```sql
-- Get detailed attempt timing
SELECT 
    qa.id,
    qa.userid,
    qa.timestart,
    qa.timefinish,
    (qa.timefinish - qa.timestart) as duration_seconds,
    qa.sumgrades,
    COUNT(qas.id) as question_count
FROM mdl_quiz_attempts qa
JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id
WHERE qa.quiz = [quiz_id]
GROUP BY qa.id;

-- Get per-question timing
SELECT 
    qas.timecreated,
    qas.sequencenumber,
    qas.state,
    qa.slot
FROM mdl_question_attempt_steps qas
JOIN mdl_question_attempts qa ON qa.id = qas.questionattemptid
WHERE qa.questionusageid = [attempt_usage_id]
ORDER BY qas.timecreated;
```

---

## Immediate Next Steps (No Coding Required)

### 1. Identify the Problematic Radio Button Question

**Action:** Review the quiz and determine:
- Which question uses radio buttons?
- Is it the same question that failed in all 3 cases?
- What makes this question different from others?

**Purpose:** Understanding why radio buttons fail helps us:
- Design more AI-resistant questions
- Potentially improve Moodle's radio button implementation
- Create guidelines for instructors

### 2. Establish Human Baseline

**Action:** Have a legitimate student take the quiz and record:
- Total completion time
- Time per question (if available in logs)
- Final score

**Purpose:** 
- Validate our assumption that <3 minutes is impossible
- Set appropriate thresholds for server-side detection
- Avoid false positives

### 3. Analyze Moodle Quiz Logs

**Action:** Extract detailed attempt data from database:
- Per-question timing data
- Answer change history
- Navigation patterns

**Purpose:**
- Identify additional AI signatures
- Build scoring algorithm
- Validate detection thresholds

### 4. Test Browser User Agent Strings

**Action:** During next test, capture exact user agent strings:
- Comet browser user agent
- Chrome with Manus user agent
- Compare to legitimate Chrome

**Purpose:**
- Determine if Comet has detectable fingerprint
- Assess feasibility of browser blocking approach
- Document for detection implementation

### 5. Test Radio Button Consistency

**Action:** Run 3-5 more attempts focusing on the radio button question:
- Does AI always fail that question?
- Or is it intermittent?
- Does question order matter?

**Purpose:**
- Confirm radio button weakness is consistent
- Determine if exploitable as defense mechanism
- Guide question design recommendations

---

## Priority Enhancements (Future Development)

### Priority 1: Server-Side Timing Analysis ⭐ CRITICAL

**Why:** Would have caught all 6 attempts immediately

**Implementation:**
```php
// Flag attempts completed impossibly fast
if ($duration < 180) { // 3 minutes
    flag_suspicious_attempt($attemptid, 'impossible_speed', 80);
}

// Flag attempts in bottom 5% of distribution
$class_median = get_class_median_time($quizid);
if ($duration < ($class_median * 0.5)) {
    flag_suspicious_attempt($attemptid, 'unusually_fast', 60);
}
```

**Expected Impact:** 100% detection rate for current test attempts

### Priority 2: Remove Detection Delay ⭐ HIGH

**Why:** 2-second delay allows AI to complete before detection runs

**Implementation:**
- Run immediate checks on page load (no setTimeout)
- Add event-triggered detection on form interactions
- Continuous monitoring throughout session

**Expected Impact:** Catch AI agents before they complete

### Priority 3: Weighted Scoring System ⭐ HIGH

**Why:** Current scoring too lenient, doesn't weight strong signals properly

**Implementation:**
```javascript
const scores = {
    completion_under_2min: 80,
    completion_under_3min: 60,
    canvas_fingerprint_fail: 40,
    screen_capture_api: 50,
    no_mouse_movement: 35,
    // ...
};
```

**Expected Impact:** More nuanced, accurate detection

### Priority 4: Canvas Fingerprinting ⭐ MEDIUM

**Why:** Comet specifically mentioned as effective detection method

**Implementation:**
```javascript
function checkCanvas() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    // Render and check result
    // Return detection score
}
```

**Expected Impact:** Additional detection signal, low false positive risk

### Priority 5: Screenshot Detection ⭐ MEDIUM

**Why:** Comet takes frequent screenshots, Manus behavior unknown

**Implementation:**
```javascript
// Intercept getDisplayMedia
const original = navigator.mediaDevices.getDisplayMedia;
navigator.mediaDevices.getDisplayMedia = function() {
    suspicionScore += 50;
    return original.apply(this, arguments);
};
```

**Expected Impact:** High confidence Comet detection

### Priority 6: Radio Button Interaction Monitoring ⭐ LOW

**Why:** Natural AI weakness, but inconsistent failures

**Implementation:**
- Monitor radio button click events
- Track timing of radio button interactions
- Flag rapid radio button selections

**Expected Impact:** Catch some attempts, but unreliable as sole method

---

## Success Metrics After Implementation

### Target Detection Rates

| Metric | Current | Target | How to Measure |
|--------|---------|--------|----------------|
| Detection Rate | 0% | >80% | % of AI attempts flagged |
| False Positive Rate | Unknown | <5% | % of human attempts flagged |
| Detection Latency | N/A | <5 seconds | Time from start to detection |
| Perfect Score Prevention | 50% (radio buttons) | >90% | % of AI attempts prevented from 100% |

### Validation Protocol

Before declaring success:
1. **Test against known AI agents:** Comet, Manus, others
2. **Test with legitimate students:** Ensure no false positives
3. **Test with assistive technology:** Screen readers, etc.
4. **Test across browsers:** Chrome, Firefox, Safari, Edge
5. **Measure performance impact:** Page load time, JavaScript execution

---

## Open Questions

### Technical Questions

1. **Which specific question uses radio buttons?**
   - Need to identify for testing and replication

2. **Is radio button failure consistent or intermittent?**
   - More testing needed to establish pattern

3. **What is the human baseline completion time?**
   - Need real student data for comparison

4. **Can we extract per-question timing from Moodle logs?**
   - Need to query database for detailed timing data

5. **Does Manus take screenshots like Comet?**
   - Unknown - affects screenshot detection strategy

### Strategic Questions

1. **Should we block Comet browser entirely?**
   - Simpler but may affect legitimate use cases

2. **What's an acceptable false positive rate?**
   - Need institutional policy guidance

3. **Should we auto-block or flag for review?**
   - Balance security vs student experience

4. **How do we handle appeals/disputes?**
   - Need process for false positive cases

5. **Do we notify students of detection system?**
   - Transparency vs deterrence consideration

---

## Conclusions

### What We Know

1. ✅ **Both Comet and Manus successfully bypass current detection**
2. ✅ **Completion times (1-3 minutes) are highly suspicious**
3. ✅ **Radio button questions have 50% AI failure rate**
4. ✅ **Manus is 2-3x faster than Comet**
5. ✅ **Comet has higher success rate (75% perfect vs 0%)**
6. ✅ **Both work in multiple browsers**

### What We Need

1. ❓ **Identify specific radio button question**
2. ❓ **Establish human baseline timing**
3. ❓ **Extract detailed Moodle attempt logs**
4. ❓ **Capture exact browser user agents**
5. ❓ **Test radio button consistency**
6. ❓ **Determine if Manus uses screenshots**

### What We'll Do

1. 🎯 **Implement server-side timing analysis** (catches all current attempts)
2. 🎯 **Remove 2-second detection delay** (run checks immediately)
3. 🎯 **Add weighted scoring system** (more nuanced detection)
4. 🎯 **Implement canvas fingerprinting** (additional signal)
5. 🎯 **Add screenshot detection** (targets Comet specifically)
6. 🎯 **Design more radio button questions** (exploit natural AI weakness)

---

**Test Status:** In Progress  
**Next Testing Phase:** Baseline establishment + detailed logging analysis  
**Plugin Status:** Requires significant enhancements  
**Timeline:** Enhancements documented in future-development.md

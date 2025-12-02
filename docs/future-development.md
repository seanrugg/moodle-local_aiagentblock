# Future Development - AI Agent Detection Enhancements

This document outlines improvements and enhancements identified through testing and analysis, particularly from testing against Perplexity Comet browser.

## Testing Results Summary

**Date:** December 2024  
**AI Agent Tested:** Perplexity Comet Browser  
**Test Scenario:** Student quiz completion  
**Result:** Comet successfully bypassed current detection

### Key Observations from Comet Testing

1. **Comet's Behavior:**
   - Simulates mouse clicks convincingly
   - Documents its actions with screenshots and text in AI assistant pane
   - Mimics "just enough" human-style behavior to evade detection
   - Completes tasks very quickly (1-2 seconds per page)
   - Uses legitimate Chrome user agent (runs on student's actual browser)

2. **Current Plugin Weaknesses:**
   - Detection checks run with 2-second delay - Comet finishes before checks execute
   - Thresholds may be too lenient for sophisticated AI agents
   - No canvas fingerprinting implemented
   - Simple scoring system not weighted properly
   - No server-side completion pattern analysis

3. **Why Current Detection Failed:**
   - **Timing Issue:** Comet completes actions before delayed detection fires
   - **Speed:** Fills forms and submits within 1-2 seconds
   - **Behavior Mimicry:** Adds subtle mouse movements and timing variations
   - **Local Execution:** Runs on legitimate browser, so user agent is authentic
   - **Form-Focused:** Only interacts with necessary elements

---

## Priority 1: Critical Timing Fixes

### Issue: Detection Runs Too Late

**Current Code:**
```javascript
setTimeout(function() {
    if (suspicionScore >= 2) {
        // Report to server
    }
}, 2000);
```

**Problem:** Comet completes quiz before 2-second delay expires.

**Solution:**
- Run initial detection checks **immediately on page load** (no delay)
- Run continuous monitoring throughout session
- Add quiz-specific detection hooks that trigger on form interactions

**Implementation Plan:**
1. Split detection into two phases:
   - **Phase 1:** Immediate checks on page load (property detection, canvas test)
   - **Phase 2:** Ongoing behavioral monitoring (mouse, timing, patterns)
2. Remove or reduce the 2-second setTimeout
3. Add event listeners that trigger checks on first form interaction

---

## Priority 2: Canvas Fingerprinting

### Why This Matters

Canvas fingerprinting was specifically mentioned by Comet as a detection method. Headless browsers and some automation tools fail canvas rendering tests.

**Implementation:**
```javascript
function checkCanvas() {
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        if (!ctx) {
            return { detected: true, reason: 'no_canvas_context' };
        }
        
        // Draw something and check if it renders
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = '#f60';
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = '#069';
        ctx.fillText('Browser Test', 2, 15);
        ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
        ctx.fillText('Browser Test', 4, 17);
        
        const dataURL = canvas.toDataURL();
        
        // Some automation tools return empty or generic canvas
        if (dataURL === 'data:,' || dataURL.length < 100) {
            return { detected: true, reason: 'canvas_empty' };
        }
        
        return { detected: false };
    } catch (e) {
        return { detected: true, reason: 'canvas_error' };
    }
}
```

**Scoring Weight:** 40 points (high confidence indicator)

**Benefits:**
- Hard for automation tools to fake
- Comet specifically mentioned it as effective
- Quick to execute (no delay needed)
- Low false positive rate

---

## Priority 3: Weighted Scoring System

### Current System

Simple increment: Each check adds +1 or +2 to suspicion score.

**Problem:** All signals treated equally, but some are stronger indicators than others.

### Proposed Weighted System

Based on Comet's effectiveness ratings and our testing:

| Detection Signal | Weight | Rationale |
|-----------------|--------|-----------|
| `navigator.webdriver === true` | 50 | Strong indicator of automation |
| Canvas fingerprint failure | 40 | Hard to fake |
| No mouse movement (5+ sec) | 35 | AI agents often don't simulate mouse |
| Rapid form filling (<500ms) | 30 | Humans can't type that fast |
| Missing plugins (length = 0) | 25 | Common in headless browsers |
| Perplexity DOM elements | 45 | Direct evidence of Comet |
| Excessive event listeners | 20 | Moderate indicator |
| Missing accept headers | 15 | Can be legitimate (privacy tools) |
| No language preferences | 15 | Can be legitimate |
| Automation properties detected | 50 | Strong evidence |
| AI overlay elements | 35 | Direct evidence |

**Threshold Levels:**
- 0-30: Normal user
- 31-60: Suspicious - log but don't block
- 61-90: Highly suspicious - flag for review / present verification challenge
- 91+: Almost certain AI agent - block access

**Implementation:**
```javascript
const automationScore = {
    webdriver: navigator.webdriver ? 50 : 0,
    canvas: checkCanvas().detected ? 40 : 0,
    noMouse: checkMouseMovement() ? 35 : 0,
    rapidFilling: checkFormTiming() ? 30 : 0,
    noPlugins: navigator.plugins.length === 0 ? 25 : 0,
    perplexityElements: checkPerplexityDOM() ? 45 : 0,
    // ... additional checks
};

const totalScore = Object.values(automationScore).reduce((a,b) => a+b, 0);
```

**Benefits:**
- More nuanced detection
- Reduces false positives
- Allows configurable thresholds
- Better logging (know which signals triggered)

---

## Priority 4: Quiz-Specific Detection Hooks

### Problem

General page-level detection may miss quiz-specific automation patterns.

### Solution

Add detection specifically triggered during quiz interactions:

**Implementation Points:**
1. **Quiz Start:** Run full detection battery when quiz begins
2. **Question Navigation:** Monitor timing between questions
3. **Answer Selection:** Track speed and patterns of answer selection
4. **Quiz Submission:** Final check before allowing submission

**Server-Side Analysis:**
```php
// In quiz completion handler
function check_quiz_completion_patterns($userid, $quizid, $attempt) {
    $time_taken = $attempt->timefinish - $attempt->timestart;
    $num_questions = count_quiz_questions($quizid);
    
    // Flag if completed too quickly
    $avg_time_per_question = $time_taken / $num_questions;
    if ($avg_time_per_question < 5) { // Less than 5 seconds per question
        flag_suspicious_attempt($userid, $quizid, 'too_fast');
    }
    
    // Check for perfect scores on difficult quizzes
    if ($attempt->grade == 100 && quiz_is_difficult($quizid)) {
        flag_suspicious_attempt($userid, $quizid, 'perfect_score_difficult');
    }
}
```

---

## Priority 5: Server-Side Pattern Analysis

### Concept

Analyze student behavior over time to identify AI agent patterns.

**Patterns to Monitor:**

1. **Completion Speed Patterns:**
   - Multiple quizzes completed in unusually short time
   - Consistent timing (AI agents are too consistent)
   - All activities completed at similar speed

2. **Performance Patterns:**
   - Sudden improvement in scores (was getting 60%, now getting 100%)
   - Perfect scores on difficult assessments
   - No wrong answers on practice attempts

3. **Session Patterns:**
   - No gradual improvement curve
   - Activities completed outside normal study hours
   - Multiple activities completed simultaneously (impossible for one person)

**Implementation:**
```php
function analyze_student_patterns($userid, $courseid) {
    $patterns = [];
    
    // Get all quiz attempts
    $attempts = get_user_quiz_attempts($userid, $courseid);
    
    // Check timing consistency
    $times = array_map(function($a) {
        return $a->timefinish - $a->timestart;
    }, $attempts);
    
    $std_dev = calculate_std_deviation($times);
    if ($std_dev < 10) { // Too consistent
        $patterns[] = 'timing_too_consistent';
    }
    
    // Check for sudden improvement
    if (count($attempts) >= 3) {
        $early_avg = average_grade(array_slice($attempts, 0, 2));
        $recent_avg = average_grade(array_slice($attempts, -2));
        
        if ($recent_avg - $early_avg > 30) { // 30+ point jump
            $patterns[] = 'sudden_improvement';
        }
    }
    
    return $patterns;
}
```

---

## Priority 6: Immediate Detection Phase

### Implementation Strategy

Run detection in multiple phases with different timing:

**Phase 1: Immediate (0ms delay)**
- Canvas fingerprinting
- Property detection (webdriver, etc.)
- User agent analysis
- DOM inspection (Perplexity elements)

**Phase 2: First Interaction (triggered by events)**
- Form interaction timing
- Mouse movement patterns
- Click patterns

**Phase 3: Ongoing Monitoring**
- Behavioral analysis
- Timing consistency
- Pattern accumulation

**Code Structure:**
```javascript
(function() {
    'use strict';
    
    // PHASE 1: Immediate checks
    const immediateScore = runImmediateChecks();
    
    // PHASE 2: Event-triggered checks
    let interactionScore = 0;
    document.addEventListener('DOMContentLoaded', function() {
        setupInteractionMonitoring();
    });
    
    // PHASE 3: Continuous monitoring
    setInterval(checkBehavioralPatterns, 1000);
    
    // Report if score exceeds threshold
    function reportIfSuspicious() {
        const totalScore = immediateScore + interactionScore;
        if (totalScore >= 60) {
            sendToServer(totalScore, getDetectionDetails());
        }
    }
})();
```

---

## Additional Enhancements to Consider

### 1. Quiz Time Limits with Anomaly Detection
- Set reasonable time minimums (not just maximums)
- Flag attempts completed in bottom 5% of time distribution
- Compare to class average completion time

### 2. Question Randomization
- Already a Moodle feature, but emphasize its importance
- Reduces effectiveness of AI agents trained on specific question sets

### 3. Behavioral Biometrics
- Advanced: Track keystroke dynamics
- Advanced: Mouse movement smoothness analysis
- Advanced: Scroll pattern analysis

### 4. Multi-Factor Authentication for High-Stakes Assessments
- Require phone verification before quiz
- Email confirmation code during quiz
- Time-based one-time passwords (TOTP)

### 5. Proctoring Integration
- Optional webcam monitoring
- Screen recording capabilities
- Live proctor connection for finals

---

## Testing Protocol for Future Updates

Before implementing new detection methods:

1. **Baseline Testing:**
   - Test with legitimate student browsers (Chrome, Firefox, Safari, Edge)
   - Verify no false positives
   - Test with accessibility tools (screen readers)

2. **AI Agent Testing:**
   - Test against Comet browser
   - Test against ChatGPT's browser mode (if available)
   - Test against Manus AI
   - Test against Selenium/Puppeteer scripts

3. **Performance Testing:**
   - Measure JavaScript execution time
   - Ensure page load not significantly impacted
   - Test on low-powered devices

4. **Accessibility Testing:**
   - Screen reader compatibility
   - Keyboard navigation
   - Assistive technology compatibility

---

## Configuration Recommendations

### Admin Settings to Add

```
Detection Sensitivity:
  - Strict (61+ score blocks)
  - Moderate (81+ score blocks) [Default]
  - Lenient (91+ score blocks)

Action on Detection:
  - Block immediately
  - Flag for review
  - Silent logging only

Verification Challenge:
  - None
  - Math problem
  - Click pattern
  - Math + Click pattern [Recommended]

Canvas Fingerprinting:
  - Enabled [Default]
  - Disabled

Pattern Analysis:
  - Enabled
  - Disabled [Default - privacy concerns]
```

---

## Privacy and Legal Considerations

### Data Collection Transparency

Any behavioral monitoring must be disclosed:

1. **Update Privacy Policy** to include:
   - What behavioral data is collected
   - How long it's retained
   - Who has access to it
   - Purpose of collection

2. **Student Notification:**
   - Inform students that AI agent detection is active
   - Explain that behavior patterns may be analyzed
   - Provide opt-out for low-stakes practice activities

3. **Data Retention:**
   - Keep detection logs for maximum 90 days
   - Purge after grade appeals period
   - Allow students to request their detection data

---

## Success Metrics

### How to Measure Improvement

1. **Detection Rate:**
   - % of known AI agent attempts detected
   - Target: >80% detection rate

2. **False Positive Rate:**
   - % of legitimate students incorrectly flagged
   - Target: <2% false positive rate

3. **Response Time:**
   - Time from AI agent activity to detection
   - Target: <5 seconds

4. **Teacher Satisfaction:**
   - Survey teachers on confidence in system
   - Survey teachers on workload (reviewing false positives)

---

## Implementation Timeline (Proposed)

### Phase 1 (Week 1-2)
- ✅ Add canvas fingerprinting
- ✅ Implement weighted scoring system
- ✅ Remove detection delay (immediate checks)

### Phase 2 (Week 3-4)
- ✅ Add quiz-specific detection hooks
- ✅ Implement server-side timing analysis
- ✅ Enhanced reporting dashboard

### Phase 3 (Week 5-6)
- ✅ Pattern analysis over time
- ✅ Verification challenge system (math + click)
- ✅ Teacher review workflow

### Phase 4 (Week 7-8)
- ✅ Testing with real students
- ✅ Accessibility audit
- ✅ Performance optimization
- ✅ Documentation updates

---

## Open Questions for Discussion

1. **Blocking vs. Flagging:** Should we auto-block or just flag for teacher review?
2. **Privacy Trade-offs:** How much behavioral monitoring is acceptable?
3. **Accessibility:** How to ensure detection doesn't disadvantage legitimate assistive technology users?
4. **Teacher Training:** How to educate teachers on interpreting detection reports?
5. **Appeals Process:** What process for students to dispute false positive detections?

---

## Resources and References

- Comet browser testing results (December 2024)
- Comet's own detection recommendations
- Current plugin implementation (v1.0.0-alpha)
- [verification-methods.md](verification-methods.md) - Verification challenge options
- [detection-methods.md](detection-methods.md) - Technical detection approaches

---

**Status:** Planning Document  
**Last Updated:** December 2024  
**Next Review:** After Phase 1 implementation and testing  
**Priority:** High - Address timing issues and canvas fingerprinting ASAP

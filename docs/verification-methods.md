# Local Verification Methods for AI Agent Detection

This document outlines potential verification methods that can be implemented entirely within Moodle without requiring third-party services. These methods are designed to verify human interaction when AI agent activity is suspected.

## Design Principles

All verification methods should:
- ✅ Work completely locally within Moodle (no external API calls)
- ✅ Respect institutional policies against third-party dependencies
- ✅ Protect student privacy
- ✅ Function offline/in closed networks
- ✅ Be accessible to students with disabilities
- ✅ Minimize friction for legitimate users
- ✅ Be triggered only when suspicion score is high (≥3)

---

## Verification Method Options

### 1. Simple Math Challenge ⭐ RECOMMENDED

**Description:**
Present a basic arithmetic problem that must be solved correctly to proceed.

**Implementation:**
- Generate random addition, subtraction, or multiplication problems
- Difficulty: Elementary level (e.g., "What is 7 + 13?" or "What is 9 × 4?")
- Server-side validation
- Changes on each attempt

**Pros:**
- Extremely simple to implement
- No external dependencies
- Fast for humans (2-5 seconds)
- Accessible (can be screen-reader friendly)

**Cons:**
- Advanced AI agents can solve math problems
- Not foolproof on its own
- May be challenging for students with dyscalculia

**Implementation Complexity:** LOW

**Example Code Structure:**
```php
$num1 = rand(1, 20);
$num2 = rand(1, 20);
$question = "$num1 + $num2";
$answer = $num1 + $num2;
// Store in session, validate on submission
```

---

### 2. Time-Delayed Click Pattern ⭐ RECOMMENDED

**Description:**
Present multiple buttons that must be clicked in sequence with natural human timing patterns.

**Implementation:**
- Show 3-4 buttons labeled "Step 1", "Step 2", "Step 3", "Continue"
- Measure timing between clicks
- Analyze for natural human variation (150-500ms typical)
- AI agents click too fast (<50ms) or in perfect intervals

**Pros:**
- Behavior-based (hard for AI to mimic)
- No knowledge required
- Pure JavaScript implementation
- Can run silently alongside other checks

**Cons:**
- May need adjustment for students with motor impairments
- Requires JavaScript (but most students have it)

**Implementation Complexity:** MEDIUM

**Detection Thresholds:**
- Suspicious: All clicks within 50ms of each other
- Suspicious: All clicks exactly same interval (e.g., all 100ms apart)
- Natural: Variable timing 150-500ms with human inconsistency

---

### 3. Image-Based Pattern Recognition

**Description:**
Display 4-6 images and ask user to select specific ones (e.g., "Click on images that show mathematical equations")

**Implementation:**
- Pull images from Moodle's file system or course content
- Use categories: "Show graphs", "Show text", "Show diagrams"
- Validate selections server-side
- Rotate image sets

**Pros:**
- Visually intuitive
- Uses existing course content
- Difficult for text-based AI agents
- Engaging for students

**Cons:**
- Requires image library setup
- Not accessible for blind/low-vision students (needs alt-text fallback)
- More complex implementation
- AI vision models can potentially solve these

**Implementation Complexity:** HIGH

**Accessibility Consideration:**
Must provide text-based alternative verification method for screen reader users.

---

### 4. Course-Specific Knowledge Challenge

**Description:**
Pull a random question from the course's question bank that students should be able to answer.

**Implementation:**
- Query random question from course question bank
- Present as verification challenge
- Must be correctly answered to proceed
- Log failed attempts

**Pros:**
- Tests actual course knowledge
- Uses existing Moodle infrastructure
- Harder for generic AI agents (would need course context)
- Educational value

**Cons:**
- May frustrate students who genuinely don't know the answer
- Could be seen as punitive
- Requires well-populated question bank
- AI agents with course context could potentially answer

**Implementation Complexity:** MEDIUM

**Best Use Case:**
When AI agent is detected attempting to submit assignment or take quiz.

---

### 5. Drag-and-Drop Puzzle

**Description:**
Simple interactive puzzle requiring drag-and-drop or slider movement.

**Implementation:**
- Slider that must be dragged to "unlock" (like mobile phones)
- Puzzle pieces that must be arranged
- Pure JavaScript/HTML5 implementation
- Analyze movement smoothness and hesitation

**Pros:**
- Intuitive for users
- Requires mouse/touch interaction patterns
- No knowledge required
- Visually clear

**Cons:**
- Requires JavaScript
- May be challenging for motor-impaired users
- Advanced automation could potentially replicate
- More complex UI implementation

**Implementation Complexity:** MEDIUM-HIGH

**Example:**
"Drag the slider to the right to verify you're human"

---

### 6. Text Pattern Entry

**Description:**
Display an image containing text that must be typed correctly (local CAPTCHA alternative).

**Implementation:**
- Generate image with random text server-side using GD library
- Add distortion/noise to prevent OCR
- User types what they see
- Validate server-side

**Pros:**
- Proven method (like CAPTCHA)
- Completely local
- No third-party dependencies
- Works offline

**Cons:**
- Accessibility issues for blind users
- Can be frustrating
- Requires GD library or similar
- Modern AI OCR can potentially solve

**Implementation Complexity:** HIGH

**Accessibility Requirement:**
MUST provide audio alternative or different verification method.

---

### 7. Behavioral Timing Analysis

**Description:**
Passive analysis of user interaction patterns over time without explicit challenge.

**Implementation:**
- Track mouse movement patterns
- Measure keystroke timing and rhythm
- Analyze scroll behavior
- Compare to known human patterns
- Score accumulated over session

**Pros:**
- Non-intrusive (no user interruption)
- Continuous monitoring
- Hard to fake natural human behavior
- No accessibility issues

**Cons:**
- Requires significant data collection
- Privacy concerns (must be transparent)
- More complex algorithm development
- May have false positives

**Implementation Complexity:** VERY HIGH

**Privacy Note:**
Must clearly disclose behavior tracking in privacy policy and obtain consent.

---

## Recommended Implementation Strategy

### Phase 1: Initial Detection (Current)
- User agent analysis
- HTTP header inspection  
- Client-side JavaScript checks
- Behavioral heuristics (rapid form filling, no mouse movement)

### Phase 2: Verification Challenge (Future)
When suspicion score ≥ 3, trigger verification:

**Primary Method: Math Challenge + Click Pattern Combo**
1. Present math problem
2. Require button clicks with timing analysis
3. Both must pass to proceed

**Rationale:**
- Math tests basic reasoning
- Click patterns detect automation
- Together they're more robust than either alone
- Low friction for real students
- High barrier for AI agents

### Phase 3: Advanced Options (Optional)
For high-security environments:
- Add image recognition
- Add course knowledge challenges
- Implement behavioral analysis

---

## Configuration Options

Verification challenges should be configurable in plugin settings:

```
Enable Verification Challenges: Yes/No
Suspicion Threshold (1-10): 3
Verification Method:
  - Math Challenge Only
  - Click Pattern Only  
  - Math + Click Pattern (Recommended)
  - Image Recognition
  - Course Knowledge
  - Custom Combination
```

---

## Accessibility Considerations

All verification methods MUST provide accessible alternatives:

1. **Math Challenge:** Screen reader compatible, simple language
2. **Click Pattern:** Keyboard navigation alternative
3. **Image Recognition:** Text-based alternative required
4. **Drag/Drop:** Click-based alternative required
5. **Text Entry:** Audio alternative required

Moodle's accessibility guidelines must be followed for any implementation.

---

## Testing Recommendations

Before implementing any verification method:

1. **User Testing:** Test with real students (including those with disabilities)
2. **AI Testing:** Test against actual AI agents (Comet, ChatGPT, etc.)
3. **False Positive Rate:** Monitor legitimate users incorrectly flagged
4. **Completion Time:** Ensure verification takes <10 seconds for humans
5. **Frustration Factor:** Gather feedback on user experience

---

## Future Enhancements

- **Adaptive Difficulty:** Increase challenge difficulty for repeat offenders
- **Multi-Factor Verification:** Combine multiple methods when certainty is needed
- **Machine Learning:** Train model on verified human vs. AI interaction patterns
- **Federation:** Share detection patterns across Moodle instances (opt-in)

---

## Related Documentation

- [Detection Methods](detection-methods.md)
- [Privacy Compliance](../README.md#privacy-and-data-handling)
- [Moodle Accessibility Guidelines](https://docs.moodle.org/en/Accessibility)

---

**Last Updated:** December 2024  
**Status:** Planning Document  
**Implementation Priority:** Phase 2 (Post-Initial Release)

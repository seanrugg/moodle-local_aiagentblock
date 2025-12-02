# Screenshot and Screen Capture Detection

## Overview

AI agents like Comet take screenshots to analyze page state and make decisions. While traditional OS-level screenshots (PrintScreen key) cannot be detected, browser-based screen capture methods CAN be detected.

## Comet's Screenshot Behavior

**Observed Behavior:**
- Takes frequent screenshots during quiz completion
- Displays screenshots in AI assistant pane for comparison
- Documents step-by-step actions with screen captures
- Uses screenshots to verify state changes and completion

## Detection Methods

### Method 1: Screen Capture API Detection ⭐ HIGH PRIORITY

**What it detects:** When `navigator.mediaDevices.getDisplayMedia()` is called

**Implementation:**
```javascript
function detectScreenCaptureAPI() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        return; // API not supported
    }
    
    // Intercept getDisplayMedia calls
    const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
    
    navigator.mediaDevices.getDisplayMedia = function(...args) {
        // AI agent is attempting screen capture
        suspicionScore += 50;
        detectionReasons.push('screen_capture_api_called');
        
        // Log the attempt
        console.warn('Screen Capture API detected - potential AI agent');
        
        // Call original function and return
        return originalGetDisplayMedia(...args);
    };
}
```

**Detection Weight:** 50 points  
**False Positive Risk:** Low (legitimate screen sharing is rare during quizzes)  
**Bypass Difficulty:** Hard (Comet must capture screenshots somehow)

---

### Method 2: Permission Monitoring

**What it detects:** Display capture permissions being granted

**Implementation:**
```javascript
async function checkDisplayCapturePermission() {
    if (!navigator.permissions || !navigator.permissions.query) {
        return;
    }
    
    try {
        const permissionStatus = await navigator.permissions.query({ 
            name: 'display-capture' 
        });
        
        if (permissionStatus.state === 'granted') {
            suspicionScore += 45;
            detectionReasons.push('display_capture_permission_active');
        }
        
        // Monitor permission changes
        permissionStatus.addEventListener('change', () => {
            if (permissionStatus.state === 'granted') {
                suspicionScore += 45;
                detectionReasons.push('display_capture_permission_granted_during_session');
            }
        });
    } catch (e) {
        // Permission query not supported
    }
}
```

**Detection Weight:** 45 points  
**False Positive Risk:** Low  
**Bypass Difficulty:** Hard

---

### Method 3: MediaRecorder Detection

**What it detects:** Media recording/streaming activity

**Implementation:**
```javascript
function detectMediaRecorder() {
    if (!window.MediaRecorder) {
        return;
    }
    
    const OriginalMediaRecorder = window.MediaRecorder;
    
    window.MediaRecorder = function(...args) {
        suspicionScore += 40;
        detectionReasons.push('media_recorder_instantiated');
        
        console.warn('MediaRecorder detected - potential screen recording');
        
        return new OriginalMediaRecorder(...args);
    };
    
    // Preserve prototype
    window.MediaRecorder.prototype = OriginalMediaRecorder.prototype;
}
```

**Detection Weight:** 40 points  
**False Positive Risk:** Medium (some legitimate uses exist)  
**Bypass Difficulty:** Medium

---

### Method 4: Screenshot Library Detection

**What it detects:** Common JavaScript screenshot libraries

**Implementation:**
```javascript
function detectScreenshotLibraries() {
    const knownLibraries = [
        'html2canvas',
        'dom-to-image',
        'domtoimage',
        'rasterizeHTML',
        'html2image',
        'screenshot',
        'capture'
    ];
    
    for (const lib of knownLibraries) {
        if (window[lib]) {
            suspicionScore += 35;
            detectionReasons.push(`screenshot_library_${lib}`);
            console.warn(`Screenshot library detected: ${lib}`);
        }
    }
    
    // Check for library functions in window
    if (typeof window.html2canvas === 'function' ||
        typeof window.domtoimage === 'function') {
        suspicionScore += 35;
        detectionReasons.push('screenshot_library_function_detected');
    }
}
```

**Detection Weight:** 35 points per library  
**False Positive Risk:** Low (rare in quiz contexts)  
**Bypass Difficulty:** Easy (can be renamed/hidden)

---

### Method 5: Canvas Activity Monitoring

**What it detects:** Excessive canvas creation (used for screenshot processing)

**Implementation:**
```javascript
function monitorCanvasActivity() {
    let canvasCreationCount = 0;
    let hiddenCanvasCount = 0;
    const creationTimes = [];
    
    const originalCreateElement = document.createElement.bind(document);
    
    document.createElement = function(tagName) {
        const element = originalCreateElement(tagName);
        
        if (tagName && tagName.toLowerCase() === 'canvas') {
            canvasCreationCount++;
            creationTimes.push(Date.now());
            
            // Check if canvas is hidden (common for screenshot processing)
            setTimeout(() => {
                const computed = window.getComputedStyle(element);
                if (computed.display === 'none' || computed.visibility === 'hidden') {
                    hiddenCanvasCount++;
                }
                
                // Multiple hidden canvases = screenshot processing
                if (hiddenCanvasCount >= 2) {
                    suspicionScore += 30;
                    detectionReasons.push('multiple_hidden_canvases');
                }
            }, 100);
            
            // Rapid canvas creation (within 1 second)
            if (creationTimes.length >= 3) {
                const timeSpan = creationTimes[creationTimes.length - 1] - creationTimes[0];
                if (timeSpan < 1000) {
                    suspicionScore += 25;
                    detectionReasons.push('rapid_canvas_creation');
                }
            }
            
            // Too many total canvases
            if (canvasCreationCount > 5) {
                suspicionScore += 20;
                detectionReasons.push('excessive_canvas_count');
            }
        }
        
        return element;
    };
}
```

**Detection Weight:** 20-30 points  
**False Positive Risk:** Medium (some legitimate canvas uses)  
**Bypass Difficulty:** Medium

---

### Method 6: WebRTC Data Channel Monitoring

**What it detects:** Data channels used for streaming screen content

**Implementation:**
```javascript
function monitorWebRTCDataChannels() {
    if (!window.RTCPeerConnection) {
        return;
    }
    
    const OriginalRTCPeerConnection = window.RTCPeerConnection;
    
    window.RTCPeerConnection = function(...args) {
        const pc = new OriginalRTCPeerConnection(...args);
        
        // Monitor data channels
        const originalCreateDataChannel = pc.createDataChannel.bind(pc);
        pc.createDataChannel = function(...channelArgs) {
            suspicionScore += 15;
            detectionReasons.push('webrtc_data_channel_created');
            return originalCreateDataChannel(...channelArgs);
        };
        
        // Monitor tracks added (could be screen capture stream)
        pc.addEventListener('track', (event) => {
            if (event.track.kind === 'video') {
                const settings = event.track.getSettings();
                if (settings.displaySurface) {
                    // This is a screen capture track
                    suspicionScore += 50;
                    detectionReasons.push('screen_capture_track_detected');
                }
            }
        });
        
        return pc;
    };
}
```

**Detection Weight:** 15-50 points depending on specifics  
**False Positive Risk:** Low for screen capture tracks  
**Bypass Difficulty:** Hard

---

## Integration Strategy

### Phase 1: Add High-Priority Detections

Implement immediately:
1. Screen Capture API detection (50 points)
2. Permission monitoring (45 points)
3. MediaRecorder detection (40 points)

### Phase 2: Add Supporting Detections

Implement after Phase 1 testing:
4. Screenshot library detection (35 points)
5. Canvas activity monitoring (20-30 points)
6. WebRTC monitoring (15-50 points)

### Combined Detection Code

```javascript
(function() {
    'use strict';
    
    // Run all screenshot detection methods
    function initScreenshotDetection() {
        detectScreenCaptureAPI();
        checkDisplayCapturePermission();
        detectMediaRecorder();
        detectScreenshotLibraries();
        monitorCanvasActivity();
        monitorWebRTCDataChannels();
    }
    
    // Run immediately (no delay)
    initScreenshotDetection();
    
    // Report if threshold exceeded
    if (suspicionScore >= 60) {
        reportToServer();
    }
})();
```

---

## Expected Results Against Comet

**Likelihood of Detection:** HIGH

Comet must use one of these methods to capture screenshots:
- If it uses Screen Capture API → **Detected (50 points)**
- If it uses MediaRecorder → **Detected (40 points)**
- If it uses screenshot libraries → **Detected (35 points)**
- If it creates multiple canvases → **Detected (20-30 points)**

**Confidence Level:** 85%+

Comet's documented behavior (frequent screenshots with visual display) strongly suggests it uses browser APIs that we can intercept.

---

## Testing Protocol

### Test 1: Verify Screen Capture API Detection
1. Manually call `navigator.mediaDevices.getDisplayMedia()`
2. Verify detection fires and score increases
3. Check console warnings appear

### Test 2: Test Against Comet
1. Enable all screenshot detection methods
2. Run Comet on quiz
3. Check detection logs for screenshot-related flags
4. Compare suspicion score before/after

### Test 3: False Positive Check
1. Complete quiz manually as legitimate student
2. Use normal browser features
3. Ensure no screenshot detection fires

---

## Limitations

**Cannot Detect:**
- OS-level screenshots (PrintScreen key, Snipping Tool)
- Screenshots taken outside the browser
- Screenshots on external devices (phones photographing screen)

**Can Detect:**
- Browser-based screen capture
- JavaScript screenshot libraries
- WebRTC screen sharing
- Canvas-based rendering

**Comet's likely method:** Browser-based (detectable) since it displays screenshots in the browser interface.

---

## Privacy Considerations

**Disclosure Required:**
Students must be informed that:
- Screen capture detection is active during assessments
- Legitimate screen sharing for help/accessibility may trigger flags
- Detections are logged and reviewed by instructors

**Legitimate Use Cases:**
- Students using screen sharing for accessibility (e.g., helping visually impaired)
- IT support accessing student screen for technical issues
- Legitimate collaboration during group work

**Recommendation:**
- Don't auto-block on screenshot detection alone
- Use as part of weighted scoring system
- Allow instructors to review and override

---

## Configuration Options

```php
// Admin settings
$settings->add(new admin_setting_configcheckbox(
    'local_aiagentblock/detect_screenshots',
    get_string('detect_screenshots', 'local_aiagentblock'),
    get_string('detect_screenshots_desc', 'local_aiagentblock'),
    1 // Enabled by default
));

$settings->add(new admin_setting_configselect(
    'local_aiagentblock/screenshot_detection_weight',
    get_string('screenshot_weight', 'local_aiagentblock'),
    get_string('screenshot_weight_desc', 'local_aiagentblock'),
    50, // Default weight
    [
        25 => '25 points (Low)',
        35 => '35 points (Medium)',
        50 => '50 points (High - Recommended)',
        75 => '75 points (Very High)'
    ]
));
```

---

## Success Metrics

**After Implementation:**
- Comet detection rate should increase from 0% to 80%+
- False positive rate should remain <5%
- Detection latency should be <1 second

---

## Future Enhancements

### Advanced Screenshot Analysis
- Analyze screenshot frequency patterns
- Detect screenshot-compare-action loops
- Monitor for screenshot-based decision trees

### Machine Learning
- Train model on screenshot timing patterns
- Identify screenshot-driven automation signatures
- Distinguish legitimate from AI-agent screenshots

---

**Status:** Planning Document  
**Priority:** HIGH - Screenshot detection is a key differentiator for Comet  
**Implementation Target:** Phase 2 of detection enhancements  
**Expected Impact:** Major improvement in Comet detection rate

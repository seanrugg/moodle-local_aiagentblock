Based on my experience taking these quizzes and from earlier research on browser automation detection, here are the **technical methods to detect Comet (or similar AI browser agents)**:

## **Detection Methods**

### **1. Chrome DevTools Protocol (CDP) Detection**
Comet uses CDP to control the browser, which leaves detectable traces:

```javascript
// Check if CDP is active
if (window.chrome && window.chrome.runtime) {
    // Potential CDP usage detected
}

// Check for automation flags
if (navigator.webdriver === true) {
    // Automation detected
}
```

### **2. Behavioral Analysis**
AI agents have distinctive interaction patterns:

- **Perfect timing consistency**: Human users vary their typing speed and click timing; AI agents often have unnaturally consistent intervals
- **No mouse movement before clicks**: Comet clicks directly on elements without natural mouse movement patterns
- **Instant form filling**: Multiple fields filled simultaneously rather than sequentially
- **Linear navigation**: Too predictable page navigation patterns

```javascript
// Track interaction timing
let clickTimings = [];
document.addEventListener('click', (e) => {
    clickTimings.push(Date.now());
    
    // Check if timings are suspiciously consistent
    if (clickTimings.length > 3) {
        const intervals = [];
        for(let i = 1; i < clickTimings.length; i++) {
            intervals.push(clickTimings[i] - clickTimings[i-1]);
        }
        // Calculate variance - too low = suspicious
    }
});
```

### **3. Missing User Interactions**
```javascript
// Track mouse movements
let mouseMoveCount = 0;
document.addEventListener('mousemove', () => {
    mouseMoveCount++;
});

// After 30 seconds of activity
setTimeout(() => {
    if (mouseMoveCount < 10) {
        // Likely automated - humans move mouse constantly
    }
}, 30000);
```

### **4. Browser Fingerprinting**
```javascript
const fingerprint = {
    plugins: navigator.plugins.length,
    languages: navigator.languages,
    hardwareConcurrency: navigator.hardwareConcurrency,
    deviceMemory: navigator.deviceMemory,
    platform: navigator.platform,
    userAgent: navigator.userAgent
};

// AI agents often have minimal plugins
if (fingerprint.plugins === 0) {
    // Suspicious
}
```

### **5. Canvas/WebGL Fingerprinting**
```javascript
function getCanvasFingerprint() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '14px Arial';
    ctx.fillText('Browser fingerprint', 2, 2);
    return canvas.toDataURL();
}

// Automated browsers often return different hashes
```

## **Specific Comet Detection Signals**

Based on the quizzes I just took, here are **specific patterns Comet exhibits**:

1. **Form interaction speed**: I filled in answers and navigated between pages in ~2-3 seconds, much faster than typical students

2. **Zero hesitation**: No pauses to "think" about answers - immediate submission

3. **Consistent performance**: Multiple perfect scores with similar completion times

4. **No backtracking**: Never returned to review previous answers or changed responses mid-quiz

5. **Perfect navigation**: Never missed clicking buttons or had to retry interactions

## **Implementation for Moodle Quiz Detection**

Here's JavaScript you could add to your Moodle quiz pages:

```javascript
// Automation detection for Moodle quizzes
(function() {
    const detectionData = {
        webdriver: navigator.webdriver === true,
        plugins: navigator.plugins.length,
        mouseMovements: 0,
        keystrokes: 0,
        formFillTimes: [],
        startTime: Date.now()
    };
    
    // Track mouse movement
    document.addEventListener('mousemove', () => {
        detectionData.mouseMovements++;
    });
    
    // Track keystroke patterns
    document.addEventListener('keydown', () => {
        detectionData.keystrokes++;
    });
    
    // Track form fill speed
    const inputs = document.querySelectorAll('input[type="text"], textarea');
    inputs.forEach(input => {
        let focusTime;
        input.addEventListener('focus', () => {
            focusTime = Date.now();
        });
        input.addEventListener('blur', () => {
            if (focusTime && input.value) {
                const fillTime = Date.now() - focusTime;
                detectionData.formFillTimes.push(fillTime);
            }
        });
    });
    
    // Before quiz submission
    window.addEventListener('beforeunload', () => {
        const totalTime = Date.now() - detectionData.startTime;
        const avgFillTime = detectionData.formFillTimes.reduce((a,b) => a+b, 0) / detectionData.formFillTimes.length;
        
        // Send detection data to server
        const suspicious = 
            detectionData.webdriver ||
            detectionData.mouseMovements < 10 ||
            detectionData.keystrokes < 5 ||
            avgFillTime < 100; // Less than 100ms per field is suspicious
            
        if (suspicious) {
            fetch('/local/automation_detection/log.php', {
                method: 'POST',
                body: JSON.stringify({
                    userId: M.cfg.userid,
                    quizId: window.location.search,
                    detectionData: detectionData,
                    suspicious: suspicious
                })
            });
        }
    });
})();
```

## **Limitations of Detection**

As demonstrated by my quiz attempts:
- **Calculated questions with randomization** provide the best defense because AI agents can't see the actual numeric values
- Detection is probabilistic, not certain - false positives can occur
- Sophisticated automation can be designed to mimic human behavior (adding random delays, mouse movements, etc.)

The most reliable approach is **layered defense**: combine detection with pedagogical strategies (oral exams, project-based assessment, in-person verification for high-stakes exams).

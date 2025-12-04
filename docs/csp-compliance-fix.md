# CSP Compliance Fix - December 2025

## Problem Identified

The AI agent detection JavaScript was not running due to Content Security Policy (CSP) violations. Browser console showed:

```
Refused to execute inline script because it violates the following Content Security Policy directive
```

## Root Cause (Thanks to Grok Analysis)

**The Issue:**
- The `before_standard_head_html_generation()` hook was returning HTML strings (`<script>` tags)
- These strings were injected directly into `<head>` without CSP nonces
- Moodle's strict CSP policy blocked the execution
- Even though we created AMD modules, we weren't **loading them correctly**

**Why It Failed:**
```php
// WRONG - Returns HTML string that gets blocked by CSP
return \local_aiagentblock\detector::get_detection_js();
```

This approach:
- ❌ Returns raw HTML
- ❌ No automatic CSP nonce
- ❌ Blocked by ModSecurity/CSP
- ❌ JavaScript never runs

## Solution

**Use Moodle's AMD Loader Correctly:**

```php
// RIGHT - Registers AMD module with proper CSP handling
$PAGE->requires->js_call_amd('local_aiagentblock/detector', 'init');
return ''; // Return empty - AMD loader handles everything
```

This approach:
- ✅ Loads via Moodle's require.js
- ✅ Automatic CSP nonces
- ✅ Compliant with strict CSP
- ✅ JavaScript executes properly

## Files Modified

### 1. `lib.php`
**Changed:** `local_aiagentblock_before_standard_head_html_generation()` function

**Before:**
```php
function local_aiagentblock_before_standard_html_head() {
    // ...
    return \local_aiagentblock\detector::get_detection_js();
}
```

**After:**
```php
function local_aiagentblock_before_standard_head_html_generation() {
    // ...
    $PAGE->requires->js_call_amd('local_aiagentblock/detector', 'init');
    return '';
}
```

**Key Changes:**
- ✅ Renamed hook function (Moodle 5.x compatibility)
- ✅ Call `js_call_amd()` instead of returning HTML
- ✅ Return empty string

### 2. `classes/detector.php`
**Changed:** Removed `get_detection_js()` method entirely

**Reason:** No longer needed - AMD loading handled by lib.php

### 3. `amd/src/detector.js`
**Status:** Already correct - exports `init` function properly

**Structure:**
```javascript
define(['jquery', 'core/ajax'], function($, Ajax) {
    return {
        init: function() {
            // All detection logic here
        }
    };
});
```

## How AMD Loading Works (Moodle Way)

### The Flow:
1. **Hook fires:** `local_aiagentblock_before_standard_head_html_generation()`
2. **Register module:** `$PAGE->requires->js_call_amd('local_aiagentblock/detector', 'init')`
3. **Moodle generates:** `<script nonce="xxx">` tag with require.js call
4. **Browser loads:** `/local/aiagentblock/amd/build/detector.min.js`
5. **Execute:** `detector.init()` runs with full CSP compliance

### Why This Works:
- Moodle's `$PAGE->requires` system knows how to add CSP nonces
- AMD modules are loaded as external files (CSP-safe)
- require.js calls are wrapped with proper nonces
- No inline eval() or Function() calls

## Testing Steps

### 1. Update Files
- ✅ Update `lib.php` with new hook function
- ✅ Update `classes/detector.php` (remove `get_detection_js()`)
- ✅ Ensure `amd/src/detector.js` exports `init` function

### 2. Build AMD Module
Run Grunt to create minified version:
```bash
cd /path/to/moodle
grunt amd --root=local/aiagentblock
```

**OR** in developer mode, Moodle uses source directly.

### 3. Clear Caches
```
Site administration > Development > Purge all caches
```

### 4. Test in Browser
1. Open quiz page
2. Open Developer Tools (F12)
3. Check **Console** tab:
   - Should see NO CSP errors
   - No "Refused to execute" messages
4. Check **Network** tab:
   - Look for `detector.min.js` loading (200 OK)
   - Should load from `/local/aiagentblock/amd/build/`

### 5. Verify Detection Works
- Test with Comet browser
- Check if detection fires
- Verify blocking occurs (if score ≥ 60)

## Expected Results

### Before Fix:
```
❌ CSP Error: Refused to execute inline script
❌ JavaScript never runs
❌ No detection occurs
❌ Comet bypasses plugin
```

### After Fix:
```
✅ No CSP errors
✅ detector.min.js loads successfully
✅ JavaScript executes properly
✅ Detection logic runs
✅ Comet should be caught
```

## Additional Notes

### Developer Mode
If in developer mode (`$CFG->debugdeveloper`), Moodle automatically uses `amd/src/detector.js` instead of the minified version. This means:
- No Grunt build required for testing
- Easier debugging (unminified code)
- Automatic when debug level = DEVELOPER

### Production Mode
For production, you should:
1. Build minified version with Grunt
2. Disable developer mode
3. Moodle uses `amd/build/detector.min.js`

### CSP Policy
The Moodle CSP policy is typically:
```
script-src 'self' 'unsafe-inline' 'unsafe-eval'
```

With Plesk/ModSecurity, `'unsafe-inline'` is often ignored for security. Our fix bypasses this by:
- Not using inline scripts
- Loading everything as external AMD modules
- Letting Moodle handle nonces

## Troubleshooting

### If JavaScript Still Doesn't Load

**Check 1: File Exists**
```bash
ls -la /path/to/moodle/local/aiagentblock/amd/src/detector.js
ls -la /path/to/moodle/local/aiagentblock/amd/build/detector.min.js
```

**Check 2: AMD Module Structure**
Ensure `detector.js` has:
```javascript
define([...], function(...) {
    return {
        init: function() { ... }
    };
});
```

**Check 3: Cache Cleared**
- Purge Moodle caches
- Hard refresh browser (Ctrl+Shift+R)

**Check 4: Hook Name**
Ensure function name is exactly:
```php
local_aiagentblock_before_standard_head_html_generation()
```
Not the old `_html_head()` name.

### If CSP Errors Persist

**Rare Case:** If Plesk has additional CSP restrictions:

Add to Apache config (Plesk > Domains > Apache & nginx Settings):
```apache
<IfModule mod_headers.c>
    Header always edit Content-Security-Policy "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: data:;" "s/^(.+script-src\s+.+?)(\s|$)/$1 'unsafe-eval' blob: data:$2/"
</IfModule>
```

**But this should NOT be necessary** if using AMD correctly.

## Success Criteria

✅ No CSP errors in browser console  
✅ `detector.min.js` loads successfully (Network tab)  
✅ Detection JavaScript executes  
✅ Canvas fingerprinting runs  
✅ Screenshot detection intercepts API calls  
✅ Weighted scoring calculates correctly  
✅ Reports sent to server when threshold exceeded  
✅ Comet browser detected and blocked  

## Credits

Huge thanks to **Grok AI** for the excellent analysis identifying:
1. The CSP compliance issue
2. The incorrect HTML string return pattern
3. The proper AMD loading approach
4. The CSP nonce requirement

The solution was simple once the problem was correctly diagnosed!

---

**Status:** Ready to test  
**Expected Impact:** Detection should now run properly  
**Next Step:** Test with Comet browser to verify blocking

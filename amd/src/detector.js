// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Agent Detection JavaScript Module (AMD format for Moodle)
 *
 * @module     local_aiagentblock/detector
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';
    
    let suspicionScore = 0;
    let detectionReasons = [];
    
    /**
     * Canvas Fingerprinting (40 points)
     */
    function checkCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            if (!ctx) {
                return { detected: true, score: 40, reason: 'no_canvas_context' };
            }
            
            // Draw test pattern
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('Moodle Test', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('Moodle Test', 4, 17);
            
            const dataURL = canvas.toDataURL();
            
            if (dataURL === 'data:,' || dataURL.length < 100) {
                return { detected: true, score: 40, reason: 'canvas_empty' };
            }
            
            return { detected: false, score: 0 };
        } catch (e) {
            return { detected: true, score: 40, reason: 'canvas_error' };
        }
    }
    
    /**
     * Screenshot Detection: Screen Capture API (50 points)
     */
    function interceptScreenCaptureAPI() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
            return;
        }
        
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
        
        navigator.mediaDevices.getDisplayMedia = function() {
            suspicionScore += 50;
            detectionReasons.push('screen_capture_api_called');
            reportImmediately();
            return originalGetDisplayMedia.apply(this, arguments);
        };
    }
    
    /**
     * Screenshot Detection: MediaRecorder (40 points)
     */
    function interceptMediaRecorder() {
        if (!window.MediaRecorder) {
            return;
        }
        
        const OriginalMediaRecorder = window.MediaRecorder;
        
        window.MediaRecorder = function() {
            suspicionScore += 40;
            detectionReasons.push('media_recorder_instantiated');
            reportImmediately();
            return new OriginalMediaRecorder(...arguments);
        };
        
        window.MediaRecorder.prototype = OriginalMediaRecorder.prototype;
    }
    
    /**
     * Screenshot Detection: Display Capture Permission (45 points)
     */
    function checkDisplayCapturePermission() {
        if (!navigator.permissions || !navigator.permissions.query) {
            return;
        }
        
        navigator.permissions.query({ name: 'display-capture' })
            .then(function(permissionStatus) {
                if (permissionStatus.state === 'granted') {
                    suspicionScore += 45;
                    detectionReasons.push('display_capture_permission_granted');
                    reportImmediately();
                }
                
                permissionStatus.addEventListener('change', function() {
                    if (permissionStatus.state === 'granted') {
                        suspicionScore += 45;
                        detectionReasons.push('display_capture_permission_changed');
                        reportImmediately();
                    }
                });
            })
            .catch(function() {
                // Permission query not supported
            });
    }
    
    /**
     * Screenshot Detection: Screenshot Libraries (35 points)
     */
    function detectScreenshotLibraries() {
        const knownLibraries = [
            'html2canvas',
            'dom-to-image',
            'domtoimage',
            'rasterizeHTML',
            'html2image'
        ];
        
        knownLibraries.forEach(function(lib) {
            if (window[lib]) {
                suspicionScore += 35;
                detectionReasons.push('screenshot_library_' + lib);
            }
        });
    }
    
    /**
     * Weighted Detection Checks
     */
    function runWeightedChecks() {
        const checks = [
            {
                name: 'webdriver',
                check: function() { return navigator.webdriver === true; },
                weight: 50
            },
            {
                name: 'automation_property',
                check: function() {
                    const props = [
                        'webdriver', '__webdriver_evaluate', '__selenium_evaluate',
                        '__webdriver_script_function', '__driver_evaluate',
                        '__webdriver_unwrapped', '__driver_unwrapped',
                        '_Selenium_IDE_Recorder', '_selenium', 'callSelenium',
                        '$cdc_', '$chrome_asyncScriptInfo', '__$webdriverAsyncExecutor',
                        '__perplexity__', '__comet__', 'perplexityAgent', 'cometAgent'
                    ];
                    for (let i = 0; i < props.length; i++) {
                        if (window[props[i]] || document[props[i]]) {
                            return true;
                        }
                    }
                    return false;
                },
                weight: 50
            },
            {
                name: 'perplexity_elements',
                check: function() {
                    const elements = document.querySelectorAll(
                        '[class*="perplexity"], [class*="comet"], ' +
                        '[data-perplexity], [data-comet], ' +
                        '[id*="perplexity"], [id*="comet"]'
                    );
                    return elements.length > 0;
                },
                weight: 45
            },
            {
                name: 'agent_overlay',
                check: function() {
                    const overlays = document.querySelectorAll(
                        '[class*="agent"], [class*="assistant"], ' +
                        '[id*="agent"], [id*="assistant"]'
                    );
                    return Array.from(overlays).some(function(el) {
                        const style = window.getComputedStyle(el);
                        return style.position === 'fixed' || style.position === 'absolute';
                    });
                },
                weight: 35
            },
            {
                name: 'no_plugins',
                check: function() { return navigator.plugins && navigator.plugins.length === 0; },
                weight: 25
            },
            {
                name: 'headless_ua',
                check: function() { return /HeadlessChrome|PhantomJS|Selenium|Puppeteer/i.test(navigator.userAgent); },
                weight: 30
            },
            {
                name: 'no_languages',
                check: function() { return !navigator.languages || navigator.languages.length === 0; },
                weight: 15
            },
            {
                name: 'chrome_without_chrome',
                check: function() { return !window.chrome && navigator.userAgent.includes('Chrome'); },
                weight: 15
            },
            {
                name: 'no_permissions',
                check: function() { return navigator.permissions === undefined; },
                weight: 15
            }
        ];
        
        checks.forEach(function(checkObj) {
            try {
                if (checkObj.check()) {
                    suspicionScore += checkObj.weight;
                    detectionReasons.push(checkObj.name + '_' + checkObj.weight);
                }
            } catch (e) {
                // Ignore errors in individual checks
            }
        });
    }
    
    /**
     * Rapid Form Filling Detection (30 points)
     */
    function monitorFormInteractions() {
        let formInteractionTimes = [];
        
        document.addEventListener('input', function() {
            formInteractionTimes.push(Date.now());
            
            if (formInteractionTimes.length >= 3) {
                const timeDiff = formInteractionTimes[formInteractionTimes.length - 1] - formInteractionTimes[0];
                if (timeDiff < 500) {
                    suspicionScore += 30;
                    detectionReasons.push('rapid_form_filling_30');
                    formInteractionTimes = [];
                    reportImmediately();
                }
            }
        }, true);
    }
    
    /**
     * Canvas Creation Monitoring (20-25 points)
     */
    function monitorCanvasCreation() {
        let canvasCreationCount = 0;
        let hiddenCanvasCount = 0;
        const originalCreateElement = document.createElement.bind(document);
        
        document.createElement = function(tagName) {
            const element = originalCreateElement(tagName);
            
            if (tagName && tagName.toLowerCase() === 'canvas') {
                canvasCreationCount++;
                
                setTimeout(function() {
                    const computed = window.getComputedStyle(element);
                    if (computed.display === 'none' || computed.visibility === 'hidden') {
                        hiddenCanvasCount++;
                        
                        if (hiddenCanvasCount >= 2) {
                            suspicionScore += 25;
                            detectionReasons.push('multiple_hidden_canvases_25');
                            reportImmediately();
                        }
                    }
                }, 100);
                
                if (canvasCreationCount > 5) {
                    suspicionScore += 20;
                    detectionReasons.push('excessive_canvas_count_20');
                    reportImmediately();
                }
            }
            
            return element;
        };
    }
    
    /**
     * Mouse Movement Monitoring (35 points for no movement)
     */
    function monitorMouseMovement() {
        let mouseMoveCount = 0;
        const mouseStartTime = Date.now();
        
        document.addEventListener('mousemove', function() {
            mouseMoveCount++;
        });
        
        setTimeout(function() {
            if (mouseMoveCount === 0 && (Date.now() - mouseStartTime) > 5000) {
                suspicionScore += 35;
                detectionReasons.push('no_mouse_movement_35');
                reportImmediately();
            }
        }, 5000);
    }
    
    /**
     * Report detection to server immediately
     */
    function reportImmediately() {
        if (suspicionScore >= 60) {
            sendReport();
        }
    }
    
    /**
     * Send detection report to server using Moodle's AJAX
     */
    function sendReport() {
        $.ajax({
            url: M.cfg.wwwroot + '/local/aiagentblock/detect.php',
            method: 'POST',
            data: {
                detection: 1,
                score: suspicionScore,
                reasons: detectionReasons.join(',')
            }
        });
    }
    
    /**
     * Initialize all detection methods
     */
    function init() {
        // PHASE 1: IMMEDIATE DETECTION
        runWeightedChecks();
        
        const canvasResult = checkCanvasFingerprint();
        if (canvasResult.detected) {
            suspicionScore += canvasResult.score;
            detectionReasons.push('canvas_' + canvasResult.reason);
        }
        
        detectScreenshotLibraries();
        interceptScreenCaptureAPI();
        interceptMediaRecorder();
        checkDisplayCapturePermission();
        
        // PHASE 2: EVENT-TRIGGERED DETECTION
        monitorFormInteractions();
        monitorCanvasCreation();
        
        // PHASE 3: CONTINUOUS MONITORING
        monitorMouseMovement();
        
        // Report immediately if already suspicious
        if (suspicionScore >= 60) {
            sendReport();
        }
        
        // Also check after brief delay
        setTimeout(function() {
            if (suspicionScore >= 60) {
                sendReport();
            }
        }, 1000);
    }
    
    return {
        init: init
    };
});

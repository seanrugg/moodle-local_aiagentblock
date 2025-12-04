# Changelog

All notable changes to the AI Agent Blocker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Canvas Fingerprinting Detection** (40 points) - Detects headless browsers and automation tools that fail canvas rendering tests
- **Screenshot Detection System** - Multi-layered detection for AI agents using screen capture:
  - Screen Capture API interception (50 points)
  - MediaRecorder detection (40 points)
  - Display capture permission monitoring (45 points)
  - Screenshot library detection (35 points) - html2canvas, dom-to-image, etc.
  - Canvas creation monitoring (20-25 points)
- **Weighted Scoring System** - Sophisticated scoring with different weights for different signals:
  - High confidence: 40-50 points (webdriver, automation properties, canvas failures)
  - Medium confidence: 20-35 points (no plugins, headless UA, agent overlays)
  - Lower confidence: 15-20 points (missing languages, no permissions)
- **Immediate Detection** - Removed 2-second delay, checks now run instantly on page load
- **Three-Phase Detection Strategy**:
  - Phase 1: Immediate checks (no delay)
  - Phase 2: Event-triggered checks (form interactions)
  - Phase 3: Continuous monitoring (mouse movement, timing)

### Changed
- Detection now runs immediately instead of after 2-second delay
- Suspicion threshold raised to 60 points (from 2 points)
- Detection reports sent immediately when threshold exceeded
- Enhanced detection reasons now include score weights for transparency

### Technical Improvements
- Canvas fingerprinting catches headless browsers
- Screen Capture API interception catches Comet and similar AI browsers
- MediaRecorder detection catches screen recording automation
- Permission monitoring detects display capture grants
- Screenshot library detection catches html2canvas, dom-to-image, etc.
- Multi-phase detection catches AI agents at different operational stages

## [1.0.0-alpha] - 2024-12-01

### Added
- Initial release of AI Agent Blocker plugin
- Multi-layer AI agent detection (user agent, HTTP headers, client-side JavaScript)
- Detection for ChatGPT agentic browser, Manus AI, and Perplexity Comet
- Course-level protection settings (enable/disable per course)
- Activity-level protection settings (default/enabled/disabled for assignments, forums, quizzes)
- Comprehensive detection logging with:
  - Student username
  - Timestamp
  - IP address
  - AI agent identification
  - Browser information
  - Course page/activity location
  - Protection level (course or activity)
- Course-level detection reports accessible by:
  - Teachers
  - Non-editing Teachers
  - Course Creators
  - Managers
  - System Administrators
- Blocking screen displayed when AI agents are detected
- Optional admin email notifications
- GDPR-compliant privacy provider
- Downloadable reports (CSV, Excel, etc.)
- Site-wide admin settings for global configuration
- Moodle 4.0-5.1+ compatibility

### Security
- Session key protection for all forms
- Capability-based access control
- Safe handling of user agent strings and IP addresses

---

## Release Notes

### v1.0.0-alpha
This is the initial alpha release for testing and feedback. The plugin is functional but should be tested thoroughly in development environments before production use.

**Known Limitations:**
- Activity-level settings currently only support Assignments, Forums, and Quizzes
- Advanced AI agents with sophisticated evasion techniques may not be detected
- Client-side detection can be bypassed if JavaScript is disabled

**Testing Recommendations:**
- Test in Moodle 4.0, 4.5, 5.0, and 5.1 environments
- Verify detection with actual automation tools
- Check for false positives with legitimate browser extensions
- Test course and activity-level permission controls

**Feedback Welcome:**
Please report issues, suggestions, and detection patterns via GitHub Issues.

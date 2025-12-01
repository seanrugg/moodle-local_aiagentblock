# Changelog

All notable changes to the AI Agent Blocker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

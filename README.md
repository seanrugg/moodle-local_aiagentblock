# AI Agent Blocker for Moodle

A Moodle plugin that detects and prevents AI agents from completing coursework on behalf of students, protecting academic integrity in online learning environments.

## Overview

This plugin provides multi-layered detection capabilities to identify and block automated AI agents including ChatGPT's browser automation, Manus AI, Perplexity Comet, and other AI-powered tools that attempt to complete courses or assessments without genuine student participation.

## Features

- **Multi-Agent Detection**: Identifies ChatGPT agentic browser, Manus AI, Perplexity Comet, and other AI automation tools
- **Immediate Blocking**: AI agents are prevented from proceeding when detected, with a clear blocking screen displayed
- **Granular Control**: 
  - Enable/disable protection at the **course level**
  - Enable/disable protection at the **activity level** (assignments, forums, quizzes)
  - Allows legitimate AI agent use cases when required by course design
- **Multi-Layer Protection**: 
  - Server-side user agent analysis
  - HTTP header inspection for automation frameworks
  - Client-side JavaScript detection of browser automation
- **Course-Level Reporting**: Detection logs accessible within each course by authorized teaching staff
- **Comprehensive Logging**: Track all detection events with:
  - Student username
  - Timestamp
  - IP address
  - AI agent type (user agent)
  - Browser information
  - Direct link to course page/activity where detection occurred
- **Role-Based Access**: Configuration and reports available to Teachers, Non-editing Teachers, Course Creators, Managers, and System Administrators
- **Admin Notifications**: Optional email alerts when AI agents are detected
- **Flexible Configuration**: Enable/disable specific detection methods and customize blocking behavior
- **Privacy Compliant**: GDPR-aware with proper data handling

## Requirements

- Moodle 4.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Via Moodle Plugin Installer

1. Download the latest release from the [Releases page](https://github.com/yourusername/moodle-local_aiagentblock/releases)
2. Log in to your Moodle site as an administrator
3. Navigate to **Site administration > Plugins > Install plugins**
4. Upload the ZIP file
5. Follow the on-screen instructions to complete installation

### Method 2: Manual Installation

1. Download or clone this repository
2. Extract/copy the folder to your Moodle installation:
   ```bash
   cp -r moodle-local_aiagentblock /path/to/moodle/local/aiagentblock
   ```
3. Log in to your Moodle site as an administrator
4. Navigate to **Site administration > Notifications**
5. Follow the prompts to complete the installation

## Configuration

After installation, configure the plugin:

1. Navigate to **Site administration > Plugins > Local plugins > AI Agent Blocker**
2. Configure the following settings:
   - **Enable AI Agent Detection**: Turn detection on/off
   - **Detection Methods**: Choose which detection methods to use
   - **Logging**: Enable/disable detection event logging
   - **Admin Notifications**: Configure email alerts for detections
   - **Blocked Access Message**: Customize the message shown to blocked users

## How It Works

### Detection Methods

The plugin employs three layers of detection to identify AI agents:

#### 1. User Agent Analysis
Scans HTTP user agent strings for known AI agent signatures:
- ChatGPT browser automation (`ChatGPT-User`, `OpenAI`, `GPTBot`)
- Manus AI (`Manus`, `ManusAI`)
- Perplexity Comet (`Comet`, `PerplexityBot`, `Perplexity`)
- Generic automation tools (`Anthropic`, `Claude-web`, `AI-Agent`)

#### 2. HTTP Header Inspection
Examines request headers for automation framework indicators:
- Selenium (`webdriver`)
- Puppeteer automation headers
- Playwright signatures
- Missing standard browser headers (configurable)

#### 3. Client-Side JavaScript Detection
Runs browser-based checks for automation properties:
- `navigator.webdriver` detection
- Automation framework properties (`__webdriver_evaluate`, `$cdc_`, etc.)
- Headless browser indicators
- Plugin and language anomalies

When an AI agent is detected, the student is immediately shown a blocking screen that prevents the AI agent from proceeding. All detection events are logged and accessible via course-level reports to authorized staff.

## Usage

### For Administrators and Course Staff

**Configure Protection at Course Level:**
1. Navigate to your course
2. Go to **Course administration > AI Agent Protection**
3. Choose to enable or disable AI agent detection for the entire course
4. When disabled, students can use AI agents freely within this course

**Configure Protection at Activity Level:**
1. Edit any activity (Assignment, Forum, or Quiz)
2. In the activity settings, find **AI Agent Protection** section
3. Choose to:
   - **Use course default** - Inherit the course-level setting
   - **Enable protection** - Block AI agents for this specific activity
   - **Disable protection** - Allow AI agents for this specific activity (even if course-level is enabled)

**Use Cases for Disabling Protection:**
- Courses designed to teach AI agent usage
- Activities requiring AI assistance as part of learning objectives
- Collaborative AI projects
- Research or experimental coursework

**Access Permissions for Configuration:**
The following roles can configure AI agent protection:
- Teachers
- Course Creators
- Managers
- System Administrators

Non-editing Teachers can view reports but cannot change protection settings.

**View Detection Reports at Course Level:**
1. Navigate to any course where you have teaching privileges
2. Go to **Course administration > Reports > AI Agent Detections**
3. View all detection events for students in that course including:
   - Username
   - Timestamp of detection
   - IP address
   - User agent string (AI agent type)
   - Browser information
   - Direct link to the course page/activity where detection occurred
   - Whether protection was enabled at course or activity level

**Student Experience:**
When an AI agent is detected attempting to act on behalf of a student (in a protected course/activity):
1. The AI agent is immediately blocked from proceeding
2. A blocking screen is displayed indicating AI agent use has been detected
3. The AI agent cannot continue or access course content
4. The detection event is logged with full details for staff review

When protection is disabled (at course or activity level):
- AI agents can operate normally
- No blocking occurs
- No detection logging takes place

### For Developers

**Customize Detection Patterns:**

Edit the detection patterns in `classes/detector.php`:

```php
private static $agent_patterns = [
    '/YourCustomAgent/i',
    // Add more patterns here
];
```

**Add Custom Detection Logic:**

Extend the `local_aiagentblock\detector` class:

```php
class custom_detector extends \local_aiagentblock\detector {
    public static function custom_check() {
        // Your custom detection logic
        return true; // or false
    }
}
```

## Configuration Options

### Site-Level Settings
| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Detection** | Master switch for all AI agent detection site-wide | Enabled |
| **Log Detections** | Record detection events to database | Enabled |
| **Admin Notifications** | Email alerts to site administrators | Disabled |
| **Check Missing Headers** | Flag requests missing standard browser headers | Enabled |
| **Block Access** | Immediately block detected agents (vs. log only) | Enabled |
| **Custom Message** | Message displayed to blocked users | Default text |

### Course-Level Settings
Teachers, Course Creators, and Managers can configure protection per course:
- **Enable/Disable for Course** - Turn protection on or off for the entire course
- Inherits site-level settings when enabled
- Overrides all activity settings when disabled

### Activity-Level Settings
Teachers, Course Creators, and Managers can configure protection for individual activities:
- **Use Course Default** - Inherit course-level setting (default)
- **Enable Protection** - Block AI agents for this activity only
- **Disable Protection** - Allow AI agents for this activity, even if course-level is enabled

Supported activity types:
- Assignments
- Forums
- Quizzes

## Detection Log Database

The plugin creates a `local_aiagentblock_log` table with the following fields:

- `id` - Record ID
- `userid` - Moodle user ID (student)
- `courseid` - Course where detection occurred
- `contextid` - Context ID (course page or activity)
- `cmid` - Course module ID (activity) if applicable
- `pageurl` - Full URL of the page where detection occurred
- `protection_level` - Whether protection was at 'course' or 'activity' level
- `detection_method` - Method that triggered detection (user_agent, headers, client_side)
- `user_agent` - Full user agent string (AI agent identifier)
- `browser` - Browser name and version
- `ip_address` - Request IP address
- `timecreated` - Unix timestamp of detection

Additional configuration tables:
- `local_aiagentblock_course` - Course-level protection settings
- `local_aiagentblock_activity` - Activity-level protection settings

This allows course staff to see exactly when and where AI agents attempted to act on behalf of students, and at what level protection was enforced.

## Privacy and Data Handling

This plugin is GDPR compliant and:
- Stores detection logs with user identifiers for security purposes
- Allows data export for user data requests
- Supports data deletion upon user account removal
- Logs are retained according to Moodle's standard log retention policies

## Testing

### Manual Testing

1. Install a browser automation tool (e.g., Selenium WebDriver)
2. Configure it to access your Moodle site
3. Verify the plugin blocks access and logs the detection

### Automated Testing

Run PHPUnit tests:

```bash
cd /path/to/moodle
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit local/aiagentblock/tests/
```

### Test on Moodle 5.1

This plugin has been tested on:
- Moodle 4.0
- Moodle 4.1
- Moodle 4.5
- Moodle 5.1 (development instance)

## Known Limitations

- **False Positives**: Legitimate browser extensions or privacy tools may trigger detection
- **Sophisticated Evasion**: Advanced AI agents may mask their signatures
- **Performance**: Client-side detection adds minimal JavaScript overhead
- **VPN Users**: Shared IP addresses may complicate tracking

## Troubleshooting

### Plugin Not Detecting AI Agents

1. Verify plugin is enabled in settings
2. Check that detection methods are configured
3. Review web server logs for PHP errors
4. Test with a known automation tool

### False Positives

1. Review detection logs to identify patterns
2. Adjust detection sensitivity in settings
3. Whitelist specific user agents if needed
4. Disable "Check Missing Headers" if too aggressive

### No Admin Notifications

1. Verify email is configured in Moodle
2. Check spam/junk folders
3. Review Moodle's email logs
4. Test with a manual detection event

## Roadmap

- [ ] Machine learning-based behavioral analysis
- [ ] Integration with Moodle quiz module for enhanced protection
- [ ] Real-time dashboard for detection monitoring
- [ ] API for third-party integration
- [ ] Customizable response actions (redirect, challenge, etc.)
- [ ] Rate limiting and anomaly detection

## Contributing

We welcome contributions! To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- Code follows Moodle coding standards
- PHPUnit tests pass
- Documentation is updated
- Commits are clear and descriptive

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/moodle-local_aiagentblock/issues)
- **Documentation**: See `/docs` folder
- **Moodle Forums**: [Moodle Community Forums](https://moodle.org/course/view.php?id=5)

## License

This plugin is licensed under the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.en.html) or later.

```
Copyright (C) 2024

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Credits

Developed to protect academic integrity in online learning environments.

## Changelog

### v1.0.0-alpha (2024-12-01)
- Initial release
- Multi-layer AI agent detection
- Support for ChatGPT, Manus AI, and Perplexity Comet
- Logging and notification system
- Moodle 4.0-5.1+ compatibility

---

**Note**: This plugin is in active development. Features and behavior may change. Please report any issues or suggestions via GitHub Issues.

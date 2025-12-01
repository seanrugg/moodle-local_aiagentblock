# Moodle AI Agent Blocker - Repository Structure

```
moodle-local_aiagentblock/
├── README.md
├── LICENSE
├── .gitignore
├── CHANGELOG.md
├── version.php
├── settings.php
├── lib.php
├── detect.php
├── classes/
│   ├── privacy/
│   │   └── provider.php
│   ├── detector.php
│   ├── logger.php
│   └── observer.php
├── db/
│   ├── install.xml
│   ├── upgrade.php
│   ├── access.php
│   └── events.php
├── lang/
│   └── en/
│       └── local_aiagentblock.php
├── templates/
│   ├── blocked_access.mustache
│   └── detection_report.mustache
├── tests/
│   ├── detector_test.php
│   ├── logger_test.php
│   └── fixtures/
│       └── user_agents.txt
├── amd/
│   ├── src/
│   │   └── detector.js
│   └── build/
│       └── detector.min.js
├── styles.css
└── docs/
    ├── installation.md
    ├── configuration.md
    └── detection-methods.md
```

## Directory Breakdown

### Root Level Files

- **README.md** - Project overview, installation instructions, features
- **LICENSE** - GPL v3 (required for Moodle plugins)
- **.gitignore** - Exclude Moodle-specific files (amd/build/, config.php)
- **CHANGELOG.md** - Version history and updates
- **version.php** - Plugin version and dependencies (required by Moodle)
- **settings.php** - Admin settings page configuration
- **lib.php** - Core Moodle hooks and callback functions
- **detect.php** - AJAX endpoint for client-side detection reporting

### classes/

Object-oriented plugin components:

- **privacy/provider.php** - GDPR compliance implementation (required)
- **detector.php** - Main detection logic class (refactored from core)
- **logger.php** - Detection event logging
- **observer.php** - Moodle event observers (hooks into user login, page views)

### db/

Database-related files:

- **install.xml** - Initial database schema (detection log table)
- **upgrade.php** - Database upgrade scripts for version migrations
- **access.php** - Capability definitions (view logs, configure settings)
- **events.php** - Event observer mappings

### lang/en/

- **local_aiagentblock.php** - All English language strings

### templates/

Mustache templates for output:

- **blocked_access.mustache** - Page shown when AI agent is blocked
- **detection_report.mustache** - Admin report view

### tests/

PHPUnit tests:

- **detector_test.php** - Unit tests for detection logic
- **logger_test.php** - Tests for logging functionality
- **fixtures/** - Test data files

### amd/

JavaScript modules (AMD format for Moodle):

- **src/detector.js** - Source JavaScript for client-side detection
- **build/detector.min.js** - Minified version (auto-generated with Grunt)

### docs/

Documentation files:

- **installation.md** - Step-by-step installation guide
- **configuration.md** - Admin configuration options
- **detection-methods.md** - Technical details on detection methods

## Key Files to Create First

1. **version.php** - Required for Moodle to recognize the plugin
2. **README.md** - Project documentation
3. **LICENSE** - GPL v3 license file
4. **.gitignore** - Standard Moodle plugin gitignore
5. **db/install.xml** - Database table structure
6. **lang/en/local_aiagentblock.php** - Language strings
7. **settings.php** - Admin configuration interface
8. **lib.php** - Hook integration

## Installation Path

Once developed, this plugin will be installed at:
```
{moodle_root}/local/aiagentblock/
```

## GitHub Repository Setup

### Recommended Repository Name:
`moodle-local_aiagentblock`

### Initial Branches:
- `main` - Stable releases
- `develop` - Active development
- `feature/*` - Feature branches

### Suggested GitHub Topics/Tags:
- moodle
- moodle-plugin
- ai-detection
- academic-integrity
- education
- lms
- plagiarism-prevention

Would you like me to create any of these files next?
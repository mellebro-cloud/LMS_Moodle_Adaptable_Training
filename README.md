# HeyDay Training Academy — LMS

**Short Term Certification Training Platform**  
Built on Moodle 5.2+ · Adaptable Theme 502.1.1 · PHP 8.3 · MariaDB 10.11

---

## Overview

HeyDay Training Academy LMS is a fully customized Learning Management System designed for short-term professional certification courses. It combines the robustness of Moodle 5.2+ as the backend platform with a suite of purpose-built plugins that deliver an **ed2go-style learner experience** — a clean, focused course player with sidebar navigation, activity sequencing, completion tracking, and availability-date enforcement.

The design philosophy is a strict two-layer architecture:

| Layer | Responsibility |
|---|---|
| **Adaptable 502.1.1** | Global shell — header, logo, colors, fonts, footer, page width, learner-facing appearance |
| **local_heyday_courseplayer** | Learner experience — player shell, sidebar, sequence, completion, locks, release dates |

Moodle core is **never modified**. All customization lives in plugins and theme settings.

---

## Technology Stack

| Component | Version |
|---|---|
| Moodle LMS | 5.2+ (build 20260525) |
| Theme | Adaptable 502.1.1 |
| PHP | 8.3.31 |
| Database | MariaDB 10.11.18 |
| Web Server | Apache (XAMPP) |
| Node.js | ≥ 22.11 |
| React | 19.1 |
| Template Engine | Mustache 3.0 |
| HTTP Client | Guzzle 7.10 |

---

## Repository Structure

```
.
├── public/                         # Moodle web root (served by Apache)
│   ├── local/                      # Custom local plugins (16 total)
│   │   ├── heyday_courseplayer/    # ★ Master learner player (primary plugin)
│   │   ├── heyday_coursehome/      # Course home/dashboard page
│   │   ├── heyday_discussions/     # Custom discussions UI
│   │   ├── heyday_gettingstarted/  # Getting Started module
│   │   ├── heyday_helptour/        # Interactive help tour
│   │   ├── heyday_lessons/         # Lessons module integration
│   │   ├── heyday_pretest/         # Pretest / diagnostic quiz
│   │   ├── heyday_quizskin/        # Custom quiz styling
│   │   ├── heyday_scores/          # Grades and scores display
│   │   ├── heyday_coursesearch/    # Course search
│   │   ├── ai_manager/             # AI integration management
│   │   ├── edugears/               # Educational tools
│   │   ├── hvpreport/              # Custom reporting
│   │   ├── kopere_mobile/          # Mobile/responsive utilities
│   │   └── pretestautostart/       # Pretest auto-initiation
│   └── theme/
│       ├── adaptable/              # Primary theme (502.1.1)
│       └── boost/                  # Moodle core parent theme
├── admin/                          # Moodle CLI and admin tools
├── .github/
│   ├── copilot-instructions.md     # AI assistant coding guidelines
│   └── instructions/
│       └── heyday-courseplayer.instructions.md
├── CLAUDE.md                       # Project context for AI coding tools
├── config-dist.php                 # Configuration template (copy → config.php)
├── composer.json                   # PHP dependencies
└── package.json                    # Node.js / build dependencies
```

> **Note:** `config.php` and `/moodledata` are excluded from version control — they contain database credentials and user-uploaded files.

---

## Primary Plugin — `local_heyday_courseplayer`

**Path:** `public/local/heyday_courseplayer/`  
**Component:** `local_heyday_courseplayer`  
**Version:** `2026062305` (release: `pretest-iframe-player`, stability: STABLE)  
**Requires:** Moodle ≥ 5.2 (build 2025051200)

### What it does

This plugin renders the entire learner-facing course experience as a single-page application shell, replacing the default Moodle course layout with an ed2go-style player while preserving all native Moodle functionality (completion, grades, availability, permissions).

### Learner Sequence

```
Home → Scores → Discussions → Getting Started → Pretest → Lessons → Resources → Final Exam
```

### Player Features

- **Black topbar** — learner-style header with back, bookmark, print, and fullscreen controls
- **Sticky white sidebar** — independently scrollable, independent from content area
- **Expandable lesson groups** — single active expansion path, no localStorage dependency
- **Completion indicators** — green checkmarks (done), blue dots (in-progress), lock icons (locked/unavailable)
- **Release-date enforcement** — server-rendered availability text, locked items are visible but not clickable
- **Auto-navigation** — redirects native Moodle activity URLs into the player shell via `cmid`
- **Auto-completion** — marks activity complete when the learner reaches the bottom of the content card
- **H5P inline rendering** — renders H5P activities inside the player with fallback "Open Activity" card
- **Scoped CSS** — all styles isolated to `body.local-heyday-courseplayer`, zero impact on admin/editor pages

### Plugin File Structure

```
heyday_courseplayer/
├── classes/
│   ├── output/master_shell.php     # Renderable output class
│   └── privacy/provider.php        # GDPR privacy API (no personal data stored)
├── db/
│   └── access.php                  # Capability definitions
├── lang/en/
│   └── local_heyday_courseplayer.php
├── templates/
│   ├── master_header.mustache      # Player topbar template
│   └── master_footer.mustache      # Player footer template
├── index.php                       # Main entry point
├── view.php                        # Activity/page view handler
├── edit.php                        # Content editing entry
├── lib.php                         # Plugin hooks and helpers
├── settings.php                    # Admin settings
├── styles.css                      # Scoped learner player CSS
└── version.php
```

### Supported Page Keys

| Page Key | Description |
|---|---|
| `home` | Course home — banner, completion circle, Continue button |
| `scores` | Grades and scores list |
| `discussions` | Forum/discussion activity list |
| `gettingstarted` | Course overview, syllabus, navigating sub-pages |
| `pretest` | Pretest quiz wrapper |
| `lessons` | Lesson groups and chapters |
| `lesson` | Individual lesson/chapter content |
| `resources` | Supplementary resource list |
| `finalexam` | Final exam (unlocked after resources) |

---

## Environment Setup

### Prerequisites

- Windows 10 / XAMPP (Apache + PHP 8.3)
- MariaDB 10.11 running as a Windows service (do **not** use XAMPP MySQL alongside MariaDB)
- PHP CLI at `C:\xampp\php\php.exe`
- Node.js ≥ 22.11

### Directory Layout (Local)

| Path | Purpose |
|---|---|
| `C:\xampp\moodle502\moodle` | Repository root |
| `C:\xampp\moodle502\moodle\public` | Apache document root (`wwwroot`) |
| `C:\moodledata` | Moodle data directory (outside web root) |
| `G:\2018\HEYDAY\Database\Moodle\Backup` | Database backups |

### Configuration

Copy `config-dist.php` to `public/config.php` and fill in your local values:

```php
$CFG->dbtype    = 'mariadb';
$CFG->dbhost    = '127.0.0.1';
$CFG->dbname    = 'moodle_db';
$CFG->dbuser    = 'your_db_user';
$CFG->dbpass    = 'your_db_password';
$CFG->wwwroot   = 'http://localhost/moodle';
$CFG->dataroot  = 'C:/moodledata';
```

### Key URLs (Local)

| URL | Purpose |
|---|---|
| `http://localhost/moodle/` | Moodle dashboard |
| `http://localhost/moodle/course/view.php?id=105` | Main course template |
| `http://localhost/moodle/local/heyday_courseplayer/index.php?id=105` | Master learner player |
| `http://localhost/moodle/admin/settings.php?section=themesettingadaptable` | Adaptable theme settings |
| `http://localhost/moodle/admin/environment.php?version=5.2` | Environment check |

---

## CLI Commands

All Moodle CLI commands use the PHP binary at `C:\xampp\php\php.exe`.

```bat
# Purge all caches (required after CSS-only changes)
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php

# Run plugin upgrade (required after PHP/version.php changes)
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
```

**Convenience scripts** in the repository root:

```bat
purge-cache.bat       # Purge caches
run-cron.bat          # Run Moodle cron
maintenance-on.bat    # Enable maintenance mode
maintenance-off.bat   # Disable maintenance mode
```

---

## Development Guidelines

### Change Workflow

| Change type | Action required |
|---|---|
| CSS only | Purge caches |
| PHP / plugin logic | Bump `version.php`, run upgrade, purge caches |
| New capability | Update `db/access.php`, bump version, upgrade |
| Mustache template | Purge caches |

### CSS Rules

- Scope all custom CSS to `body.local-heyday-courseplayer` or `.local-heyday-courseplayer`
- Never write broad CSS that affects Moodle admin or editing pages
- For Adaptable theme changes, prefer admin settings → CSS/SCSS → Mustache (in that order)
- Do **not** edit Adaptable source files unless absolutely required

### PHP Rules

- Do not modify Moodle core files
- Use `get_fast_modinfo()`, `cm_info`, and `section_info` APIs for course structure reads
- Always call `local_heyday_courseplayer_collect_lesson_groups()` before `local_heyday_courseplayer_gettingstarted_definitions()`
- H5P detection: use `$cm->modname === 'h5pactivity'`

### Security

- `config.php` is git-ignored — never commit it
- `moodledata/` is git-ignored — never commit it
- SQL dumps, ZIP backups, private keys, and `.env` files are git-ignored
- Do not expose database credentials in code, comments, or commits
- Xdebug is configured in trigger mode — use `?XDEBUG_TRIGGER=1` only when debugging

---

## Plugin Installation (New Environment)

1. Copy `public/local/heyday_courseplayer/` into the target Moodle's `local/` directory
2. Visit **Site Administration → Notifications** to trigger the upgrade
3. Or run: `C:\xampp\php\php.exe admin\cli\upgrade.php --non-interactive`
4. Purge caches after installation

---

## License

Moodle core and the Adaptable theme are licensed under the **GNU General Public License v3.0 or later**.  
Custom HeyDay plugins (`local_heyday_*`) are proprietary — © HeyDay Training Academy. All rights reserved.

---

## Maintainer

**HeyDay Training Academy**  
Repository: [github.com/mellebro-cloud/LMS_Moodle_Adaptable_Training](https://github.com/mellebro-cloud/LMS_Moodle_Adaptable_Training)

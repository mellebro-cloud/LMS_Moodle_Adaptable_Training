# HeyDay Training Academy — Short Term Certification Training LMS

> **Moodle 5.2+** · **Adaptable 502.1.1** · **PHP 8.3** · **MariaDB 10.11** · **local_heyday_courseplayer**

A fully customized Learning Management System for short-term professional certification training, built on Moodle 5.2+ with an ed2go-style learner experience delivered through purpose-built local plugins.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Architecture Strategy](#2-architecture-strategy)
3. [Technology Stack](#3-technology-stack)
4. [Repository Structure](#4-repository-structure)
5. [Primary Plugin — local_heyday_courseplayer](#5-primary-plugin--local_heyday_courseplayer)
6. [Master Learner Sequence](#6-master-learner-sequence)
7. [Data Model](#7-data-model)
8. [Course Custom Fields](#8-course-custom-fields)
9. [Catalog and Course Structure](#9-catalog-and-course-structure)
10. [UI/UX Specification](#10-uiux-specification)
11. [Environment Setup](#11-environment-setup)
12. [CLI Commands](#12-cli-commands)
13. [Content Build Order and Naming Map](#13-content-build-order-and-naming-map)
14. [Development Guidelines](#14-development-guidelines)
15. [QA Checklists and Acceptance Criteria](#15-qa-checklists-and-acceptance-criteria)
16. [Troubleshooting and Rollback](#16-troubleshooting-and-rollback)
17. [Development Toolchain](#17-development-toolchain)
18. [Security Rules](#18-security-rules)
19. [License](#19-license)

---

## 1. Project Overview

**HeyDay Training Academy LMS** delivers short-term professional certification courses online. The platform uses Moodle 5.2+ as its foundation and extends it with a suite of custom local plugins that recreate the ed2go learner experience — a clean, sidebar-driven course player with activity sequencing, completion tracking, availability-date enforcement, and lock states — while keeping Moodle's full quiz, forum, assignment, H5P, and gradebook functionality intact.

**Master course template ID:** `105`  
**Prepared / last updated:** 2026-06-21  
**Primary plugin version:** `2026062305` (release: `pretest-iframe-player`, maturity: STABLE)

---

## 2. Architecture Strategy

The project uses a strict **two-layer architecture** so every component has a single responsibility and Moodle core is never modified.

| Layer | Owns | Must not do |
|---|---|---|
| **Adaptable 502.1.1** | Global header, logo, branding, colors, fonts, footer, page width, public catalog shell | Rebuild lesson-player logic or duplicate plugin navigation using global JavaScript |
| **local_heyday_courseplayer** | Course-player shell, sticky sidebar, learner sequence, completion states, locks, release-date text, embedded activity views, Next Up flow | Edit Moodle core or Adaptable source files unless unavoidable and documented |
| **Moodle core activities** | Availability, completion, quiz attempts, forum posts, assignments, files, H5P, SCORM, grades, reports | Be bypassed by fake UI states or unavailable clickable links |

**Non-negotiable safety rules:**

- Do not edit Moodle core files.
- Do not edit Adaptable source files unless required and explicitly documented.
- Do not use broad CSS selectors that affect admin or Moodle editing pages.
- Do not make locked or unavailable lessons/exams clickable.
- Do not hide Moodle availability restrictions — render clear release-date messages instead.
- Do not duplicate Help, Tour, Search, breadcrumbs, secondary nav, blocks, or Moodle drawer inside the player.
- Do not call `local_heyday_courseplayer_gettingstarted_definitions()` before `$lessongroups` is created and confirmed as an array.

---

## 3. Technology Stack

| Component | Version / Detail |
|---|---|
| Moodle LMS | 5.2+ (build reference 20260525) |
| Theme | Adaptable 502.1.1 (version reference 2026041201) |
| PHP | 8.3.31 |
| Database | MariaDB 10.11.18 — database: `moodle_db` |
| Web Server | Apache via XAMPP (Windows 10) |
| Node.js | ≥ 22.11 (< 23) |
| React | 19.1 |
| Template engine | Mustache 3.0 |
| HTTP client | Guzzle 7.10 |
| Logging | Monolog 3.9 |
| Email | PHPMailer 6.9 |
| Build toolchain | Grunt, Babel, esbuild, SASS, ESLint, Stylelint, Rollup |

---

## 4. Repository Structure

```
.
├── public/                              # Apache document root (wwwroot)
│   ├── local/                           # Custom local plugins
│   │   ├── heyday_courseplayer/         # ★ Master ed2go-style learner player
│   │   ├── heyday_coursehome/           # Course home / dashboard page
│   │   ├── heyday_discussions/          # Custom discussions UI
│   │   ├── heyday_gettingstarted/       # Getting Started module
│   │   ├── heyday_helptour/             # Interactive help tour
│   │   ├── heyday_lessons/              # Lessons module integration
│   │   ├── heyday_pretest/              # Pretest / diagnostic quiz
│   │   ├── heyday_quizskin/             # Custom quiz styling
│   │   ├── heyday_scores/               # Grades and scores display
│   │   ├── heyday_coursesearch/         # Course search
│   │   ├── ai_manager/                  # AI integration management
│   │   ├── edugears/                    # Educational tools / utilities
│   │   ├── hvpreport/                   # Custom reporting
│   │   ├── kopere_mobile/               # Mobile / responsive utilities
│   │   └── pretestautostart/            # Pretest auto-initiation
│   └── theme/
│       ├── adaptable/                   # Primary theme (502.1.1)
│       └── boost/                       # Moodle core parent theme
├── admin/                               # Moodle admin tools and CLI
├── .github/
│   ├── copilot-instructions.md          # AI assistant coding guidelines
│   └── instructions/
│       └── heyday-courseplayer.instructions.md
├── CLAUDE.md                            # Project context for AI coding tools
├── config-dist.php                      # Configuration template
├── composer.json                        # PHP dependencies
├── package.json                         # Node.js / build dependencies
├── purge-cache.bat                      # Convenience: purge Moodle caches
├── run-cron.bat                         # Convenience: run Moodle cron
├── maintenance-on.bat                   # Enable maintenance mode
└── maintenance-off.bat                  # Disable maintenance mode
```

> `config.php` and `C:\moodledata` are excluded from version control — they contain database credentials and user-uploaded files respectively.

---

## 5. Primary Plugin — `local_heyday_courseplayer`

**Path:** `public/local/heyday_courseplayer/`  
**Component:** `local_heyday_courseplayer`  
**Version:** `2026062305`  
**Release:** `pretest-iframe-player`  
**Maturity:** STABLE  
**Requires:** Moodle ≥ 5.2 (build 2025051200)

### What it does

Renders the entire learner-facing course experience as a single-page application shell. Replaces the default Moodle course layout with an ed2go-style player while preserving all native Moodle functionality.

### Plugin file structure

```
heyday_courseplayer/
├── classes/
│   ├── output/master_shell.php      # Renderable output class
│   └── privacy/provider.php         # GDPR privacy API (no personal data stored)
├── db/
│   └── access.php                   # Capability definitions
├── lang/en/
│   └── local_heyday_courseplayer.php
├── templates/
│   ├── master_header.mustache       # Player topbar template
│   └── master_footer.mustache       # Player footer template
├── index.php                        # Main entry point
├── view.php                         # Activity / page view handler
├── edit.php                         # Content editing entry
├── lib.php                          # Plugin hooks and helpers
├── settings.php                     # Admin settings
├── styles.css                       # Scoped learner player CSS
└── version.php
```

### Supported page keys

| Page key | Section rendered |
|---|---|
| `home` | Course home — banner, completion circle, Continue button |
| `scores` | Grades and scores list |
| `discussions` | Forum / discussion activity list |
| `gettingstarted` | Overview, Syllabus, Navigating sub-pages |
| `pretest` | Pretest quiz wrapper |
| `lessons` | Lesson groups and chapter list |
| `lesson` | Individual lesson / chapter content card |
| `resources` | Supplementary resource list |
| `finalexam` | Final exam (unlocked after Resources) |

### Key player features

- **Black topbar** — learner-style header with back, bookmark, print, and fullscreen controls
- **Sticky white sidebar** — scrolls independently from the content area
- **Expandable lesson groups** — single active expansion path, no localStorage dependency
- **Completion indicators** — green checkmarks (done), blue dots (in-progress), lock icons (locked)
- **Release-date enforcement** — server-rendered availability text; locked items visible but not clickable
- **Auto-navigation** — redirects native Moodle activity URLs into the player shell via `cmid`
- **Auto-completion** — marks activity complete when learner reaches the bottom of the content card
- **H5P inline rendering** — renders H5P activities inside the card with a fallback "Open Activity" option
- **Scoped CSS** — all styles isolated to `body.local-heyday-courseplayer`; zero impact on admin/editor pages
- **Auto-refresh** — newly added, renamed, or moved Moodle Page activities appear in the sidebar automatically

### Known function-order constraint

Always call `collect_lesson_groups` before `gettingstarted_definitions`:

```php
// CORRECT ORDER
$lessongroups = local_heyday_courseplayer_collect_lesson_groups(
    $modinfo, $sections, $course, $context
);

if (is_array($lessongroups)) {
    $gsdefs = local_heyday_courseplayer_gettingstarted_definitions(
        $course, $context, $lessongroups
    );
}
```

---

## 6. Master Learner Sequence

```
Home → Scores → Discussions → Getting Started → Pretest → Lessons → Resources → Final Exam
```

### Standard course sections (Moodle)

| # | Section | Required contents |
|---|---|---|
| — | Getting Started | Course Overview · Syllabus · Navigating this Course · Program Information · Support/Contact |
| — | Pretest | Moodle quiz activity (auto-start optional) |
| 1–12 | Lesson 1 … Lesson N | Lesson Content · Assignment · Discussion Area · Quiz · Resources for Further Learning |
| — | Resources | Files · Folders · PDFs · Transcripts · Links · H5P references |
| — | Final Exam | Moodle quiz with availability restriction (opens after Resources are completed) |

### Optional lesson media elements

- Video + downloadable transcript PDF
- H5P flashcards and matching exercises
- Continue button / next-step guidance
- Upcoming lesson date display
- Discussion area per lesson

---

## 7. Data Model

### Primary entities

| Entity | Purpose | Moodle object |
|---|---|---|
| Catalog Category | Learner-facing subject area | Moodle course categories / subcategories |
| Course | Training product in catalog and player | Moodle course + custom fields |
| Session / Intake | Start dates, enrollment windows, cohorts | Course custom fields + calendar/events |
| Learner | Trainee user profile and access | Moodle user + profile fields |
| Enrollment | Learner–course relationship | Manual / self / cohort / payment enrollment |
| Learning Path | Ordered course experience | Course sections and player sequence |
| Lesson | Released learning unit with components | Moodle section / subsection + activities |
| Assessment | Pretest, lesson quizzes, final exam | Moodle quiz |
| Completion / Certificate | Progress and eligibility | Activity completion + course completion + certificate plugin |

### Logical relationships

- One category has many courses.
- One course can have many sessions / intakes.
- One course has one learning-path template.
- One course has Getting Started, Pretest, Lessons, Resources, and Final Exam.
- One lesson contains Lesson Content, Assignment, Discussion Area, Quiz, and Resources for Further Learning.
- One learner has many enrollments; each enrollment has its own progress and completion state.
- Availability and release dates come from Moodle restrictions and must be respected by the player at all times.

---

## 8. Course Custom Fields

### Field categories to create first

1. Public Catalog Metadata
2. Session / Intake
3. Marketing / Display
4. Flags / Visibility

### Public Catalog Metadata

| Field label | Shortname | Type | Required | Format example |
|---|---|---|---|---|
| Course Code | `coursecode` | Short text | Yes | `PY-101` |
| Catalog Area | `catalogarea` | Short text / dropdown | Yes | `Technology` |
| Subcategory | `subcategory` | Short text / dropdown | Yes | `Web Technology` |
| Level | `level` | Dropdown | Yes | `Beginner / Intermediate / Advanced` |
| Language | `language` | Dropdown | Yes | `English / Amharic / Bilingual` |
| Learning Type | `learningtype` | Dropdown | Yes | `Certificate / Short Course / Workshop` |
| Delivery Mode | `deliverymode` | Dropdown | Yes | `Online / Blended / In-person` |
| Marketing Summary | `marketingsummary` | Text area | Yes | 2–4 sentence summary |
| Related Courses Tag | `relatedcoursestag` | Short text | No | `python, programming, beginner` |

### Duration / Access fields

| Field label | Shortname | Type | Required | Format |
|---|---|---|---|---|
| Duration Hours | `durationhours` | Number | Yes | `24` |
| Duration Weeks | `durationweeks` | Number | No | `6` |
| Duration of Access | `durationaccess` | Short text | Yes | `90 days` |

### Session / Intake fields

| Field label | Shortname | Type | Required | Format |
|---|---|---|---|---|
| Session Start Dates | `sessionstartdates` | Text area | Yes | `May 10, 2026 / Jun 7, 2026` |
| Next Start Date | `nextstartdate` | Date / short text | No | `May 10, 2026` |
| Intake Label | `intakelabel` | Short text | No | `May 2026 Cohort` |
| Access Window | `accesswindow` | Short text | No | `90-day access from enrollment` |
| Enrollment Open | `enrollmentopen` | Date | No | `2026-04-20` |
| Enrollment Close | `enrollmentclose` | Date | No | `2026-05-08` |

### Flags / Visibility

| Field label | Shortname | Type | Meaning |
|---|---|---|---|
| Certificate Available | `certificateavailable` | Checkbox | Learner can earn certificate when completion rules are met |
| Featured | `featured` | Checkbox | Show in homepage Featured block |
| Popular | `popular` | Checkbox | Show in Popular block |
| New | `newflag` | Checkbox | Show in New Courses block |
| Public Visible | `publicvisible` | Checkbox | Eligible for public catalog display |
| Hero Image Reference | `heroimage` | Short text | Filename or asset reference |
| Thumbnail Reference | `thumbnailimage` | Short text | Filename or asset reference |

### Learner profile fields

| Field label | Shortname | Type | Example |
|---|---|---|---|
| Trainee Code | `traineecode` | Short text | `TR-00045` |
| Branch | `branch` | Short text / dropdown | `Addis Ababa` |
| Company | `companyname` | Short text | `ABC Trading` |
| Learner Status | `learnerstatus` | Dropdown | `Active / Pending / Alumni` |

---

## 9. Catalog and Course Structure

### Public catalog hierarchy

| Top-level category | Subcategories |
|---|---|
| Technology | Computer Fundamentals · Web Technology · Database Management · Computer Programming · Graphics and Multimedia Design · Networking and Communications · Cybersecurity · Data Science |
| Business | Use when pilot business courses are ready |
| Accounting and Finance | QuickBooks · finance · accounting |
| Computer Applications | Microsoft Office · Adobe · productivity |
| Personal Development | Communication · career readiness · soft skills |
| Certification Prep | Exam-preparation programs when certificate rules are configured |

> Do not expose empty categories in the public catalog.

### Course shortname convention

```
<area>-<number>-<session/code>
Example: smb-10-0426
```

### Moodle object naming map

| Object | Convention | Example |
|---|---|---|
| Section | `Lesson N: <Title>` | `Lesson 1: Introduction to Digital Marketing` |
| Subsection | `N.n <Topic>` | `1.1 Learning Objectives` |
| Page activity | `N.n <Page title>` | `1.2 Key Terms` |
| Quiz | `Lesson N Quiz` | `Lesson 3 Quiz` |
| Discussion | `Lesson N Discussion Area` | `Lesson 4 Discussion Area` |
| Assignment | `Lesson N Assignment` | `Lesson 5 Assignment` |
| Resource folder | `Lesson N Resources for Further Learning` | `Lesson 6 Resources for Further Learning` |
| Final exam | `Final Exam` | `Final Exam` |

---

## 10. UI/UX Specification

### Overall visual direction (ed2go style)

| Element | Specification |
|---|---|
| Top player bar | Black or near-black background |
| Left sidebar | Fixed / sticky, white background, independent scroll |
| Page background | Light gray |
| Content card | Centered, white, clean card |
| Typography | Readable font sizes, strong headings, sufficient line height |
| Navigation links | Blue |
| Footer | Simple, minimal |
| Moodle clutter | None inside the player |

### Sidebar states

| State | Indicator |
|---|---|
| Active / current page | Blue left-border indicator or arrow |
| Completed | Green checkmark |
| In progress | Blue dot / circle |
| Locked / unavailable | Lock icon, muted style, not clickable |
| Long title | Wraps cleanly, no truncation |

### Release-date messages (server-rendered)

```
Lesson 9: Cryptocurrencies will be available on Jun 10, 2026 10:00 AM GMT+3
Final Exam will be available on Jun 19, 2026 10:00 AM GMT+3
```

### H5P display rule

Render H5P activities inline inside the player card when Moodle permissions and activity context allow. Keep the fallback "Open Activity" button for cases where embedding is not safe. Never bypass Moodle completion tracking or H5P attempt data.

### Page-specific requirements

**Course Home:** fullname, shortname/section code, banner image, completion circle, score circle, next incomplete activity, Continue button, clean card layout.

**Getting Started:** Course Overview, Syllabus, and Navigating this Course inside the same master shell. No nested inner-card layout. No duplicate "Getting Started" heading. Centered page title, action icons, completion status, divider, Next Up card.

**Lessons:** same shell with sidebar visible, centered reading card, course/lesson/chapter/page title, top-left back/bookmark icons, top-right print/fullscreen icons, responsive images, normal scroll, support for learning checks, assignments, discussions, quizzes, resources, and Next Up flow.

**Scores:** ed2go-style list/card rows, toolbar/search/filter, grade and status on the right, locked items muted, download button where available.

**Discussions:** one row per discussion, deduplicated, metadata visible, locked discussions muted.

**Pretest / Quiz / Final Exam:** ed2go-like cards, clean question separators, hover effect, correct Save and Close / Submit Answers alignment, Moodle quiz functionality fully preserved. Final Exam appears after Resources.

**Resources:** organized cards or rows supporting files, folders, links, H5P references, transcripts, PDFs.

---

## 11. Environment Setup

### Local directory layout

| Path | Purpose |
|---|---|
| `C:\xampp\moodle502\moodle` | Repository root |
| `C:\xampp\moodle502\moodle\public` | Apache document root (`wwwroot`) |
| `C:\moodledata` | Moodle data directory (outside web root) |
| `C:\xampp\php\php.exe` | PHP CLI executable |
| `G:\2018\HEYDAY\Database\Moodle\Backup` | Database backups |

### Server notes

- MariaDB 10.11 runs as a Windows service — do **not** start XAMPP MySQL alongside it.
- XAMPP is used for Apache/PHP only.
- Xdebug is configured in **trigger mode** — append `?XDEBUG_TRIGGER=1` only when debugging.

### Configuration

Copy `config-dist.php` to `public/config.php` and fill in local values:

```php
$CFG->dbtype    = 'mariadb';
$CFG->dbhost    = '127.0.0.1';
$CFG->dbname    = 'moodle_db';
$CFG->dbuser    = 'your_db_user';
$CFG->dbpass    = 'your_db_password';
$CFG->wwwroot   = 'http://localhost/moodle';
$CFG->dataroot  = 'C:/moodledata';
```

> If the Moodle URL ever changes, read `$CFG->wwwroot` from `config.php`. Never guess URLs from folder names.

### Key URLs (local)

| URL | Purpose |
|---|---|
| `http://localhost/moodle/` | Moodle site / dashboard |
| `http://localhost/moodle/my/` | Learner dashboard |
| `http://localhost/moodle/course/view.php?id=105` | Master course template |
| `http://localhost/moodle/local/heyday_courseplayer/index.php?id=105` | Master learner player |
| `http://localhost/moodle/admin/settings.php?section=themesettingadaptable` | Adaptable theme settings |
| `http://localhost/moodle/admin/environment.php?version=5.2` | Environment check |

---

## 12. CLI Commands

All CLI commands use `C:\xampp\php\php.exe`. Use `admin\cli\` — never `public\admin\cli\`.

```bat
:: Purge all Moodle caches (required after CSS-only changes)
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php

:: Run plugin upgrade (required after PHP / version.php changes)
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
```

Convenience scripts in the repository root:

```
purge-cache.bat        Purge caches
run-cron.bat           Run Moodle cron
maintenance-on.bat     Enable maintenance mode
maintenance-off.bat    Disable maintenance mode
```

---

## 13. Content Build Order and Naming Map

Build content in this order to avoid dependency errors:

1. Create or verify categories and subcategories.
2. Create the course shell in the correct category.
3. Enter course custom fields and image references.
4. Enable completion tracking in the course.
5. Create sections in master order (Getting Started → Pretest → Lessons → Resources → Final Exam).
6. Create Getting Started pages/resources.
7. Create Pretest quiz.
8. Create Lesson sections/subsections and their activities.
9. Create Resources section with files/folders/transcripts/downloads.
10. Create Final Exam with correct availability and completion settings.
11. Test the normal Moodle course page first.
12. Then test the player URL.
13. Set Public Visible = Yes only after all QA items pass.

---

## 14. Development Guidelines

### Change workflow

| Change type | Required action |
|---|---|
| CSS only | Purge Moodle caches |
| PHP / plugin logic | Bump `version.php` → run upgrade → purge caches |
| New capability | Update `db/access.php` → bump version → upgrade |
| Mustache template | Purge caches |
| Moodle admin UI config | Screenshot before and after; reverse setting if needed |

### Plugin identity checklist (before every code change)

- Folder: `C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`
- Component: `local_heyday_courseplayer`
- `version.php` has the correct component and version number
- Language file is at `lang\en\local_heyday_courseplayer.php` (not plugin root)
- Required files exist: `index.php`, `view.php`, `settings.php`, `styles.css`, `version.php`, `lang/en/local_heyday_courseplayer.php`
- CSS selectors are scoped to `body.local-heyday-courseplayer`

### CSS rules

- Scope all custom CSS to `body.local-heyday-courseplayer` or `.local-heyday-courseplayer`.
- Never write broad selectors that affect Moodle admin or editing pages.
- For Adaptable: prefer admin settings → CSS/SCSS → Mustache (in that order).
- Do not edit Adaptable source files unless absolutely required and documented.

### PHP rules

- Do not modify Moodle core files.
- Use `get_fast_modinfo()`, `cm_info`, and `section_info` APIs for course structure reads.
- H5P detection: use `$cm->modname === 'h5pactivity'`.
- Always call `collect_lesson_groups()` before `gettingstarted_definitions()` and verify the result is an array.

### Safe code-change workflow

1. Inspect the real file, screenshot, or project file first — never guess.
2. Identify the exact file causing the problem.
3. Change one plugin or one file at a time.
4. Apply the smallest safe fix first.
5. Provide complete copy/paste code only for the exact file or block.
6. Bump `version.php` for PHP/plugin changes.
7. Purge caches and test at the exact URL.
8. Document rollback steps before applying any change.

---

## 15. QA Checklists and Acceptance Criteria

### Pre-public course checklist

- [ ] Course title entered
- [ ] Course code entered
- [ ] Category and subcategory set
- [ ] Marketing summary entered
- [ ] Duration entered
- [ ] Session start dates entered
- [ ] Delivery mode entered
- [ ] Certificate availability set
- [ ] Thumbnail assigned
- [ ] Hero image assigned or approved
- [ ] Course shell structure exists
- [ ] Enrollment method configured
- [ ] Completion tracking enabled
- [ ] Final exam and certificate rules documented
- [ ] `publicvisible` is **not** enabled until all items above pass QA

### Catalog QA

- [ ] Only approved public categories appear
- [ ] Only `publicvisible = Yes` courses appear
- [ ] Search returns correct courses
- [ ] Featured / Popular / New blocks respect their respective flags
- [ ] Course cards show thumbnail, title, summary, duration, dates, Learn More, and Enroll Now

### Course-player QA

- [ ] Master player URL opens for course 105 without Moodle clutter
- [ ] Sidebar sequence: Home → Scores → Discussions → Getting Started → Pretest → Lessons → Resources → Final Exam
- [ ] Active / completed / in-progress / locked states render correctly
- [ ] Release-date messages are server-rendered and accurate
- [ ] Getting Started uses the same master shell
- [ ] Lessons display inside the centered reading card
- [ ] Scores and discussions are deduplicated and styled
- [ ] Pretest and Final Exam use Moodle quiz functionality
- [ ] H5P renders inline when possible; falls back safely when not
- [ ] Final Exam appears after Resources

### Final acceptance criteria

- [ ] Data model finalized
- [ ] Public category structure works
- [ ] Course cards use real metadata from custom fields
- [ ] Enrollment path works end-to-end
- [ ] My Classroom entry works for enrolled learners
- [ ] Progress and completion are testable
- [ ] Visual layout matches ed2go-style specification
- [ ] Course 105 master player is stable before extending template to other learner-facing pages

---

## 16. Troubleshooting and Rollback

| Problem | Likely cause | First check |
|---|---|---|
| Player page blank / error | PHP syntax error, plugin function order, missing require, bad capability call | PHP error log · recent file edit · `version.php` · function order |
| CSS not updating | Moodle or browser cache | Run purge caches · hard-refresh browser |
| Sidebar item not clickable | Moodle availability restriction, wrong `cmid`, section mapping | Course activity availability · modinfo mapping |
| Locked item looks completed | State priority bug in renderer | Ensure locked state overrides active/completed visual styles |
| Getting Started not working | `$lessongroups` not initialized before definitions call | Move `collect_lesson_groups` call before `gettingstarted_definitions`; check array |
| H5P opens only in fallback | Activity cannot be safely embedded in plugin context | Keep fallback button; inspect Moodle activity renderer |
| Final Exam missing | Section mapping or sequence issue | Check Resources precedes Final Exam in course sections and player sequence |

### Rollback standard

- Before each code change, save a copy of the original file or rely on version control.
- For PHP/plugin changes: restore the previous file and `version.php`, then run upgrade.
- For CSS-only changes: restore `styles.css` and purge caches.
- For Moodle UI config changes: use before/after screenshots and reverse the setting in the admin UI.
- Do not layer fixes on top of an unverified broken state.

---

## 17. Development Toolchain

| Tool | Best use |
|---|---|
| **Claude Code (this session)** | Long-context coding, file inspection, plugin edits, version bumps, cache purge |
| **Claude Desktop** | Project-wide reasoning when pointed at the workspace using the master prompt |
| **ChatGPT** | Planning, screenshot/file diagnosis, documentation, prompt generation, safe patch strategy |
| **VS Code** | Applying file edits in the local Moodle workspace (keep workspace at `C:\xampp\moodle502\moodle`) |
| **Moodle admin UI** | Categories, custom fields, completion rules, theme settings, activities, availability |
| **PowerShell / CMD** | Moodle CLI cache purge and upgrade commands |

### Master coding prompt (for Claude / VS Code agent)

```
You are my Moodle 5.2+ / Adaptable 502.1.1 technical coding assistant for the
Short Term Certification Training LMS project.

Goal: Build one complete reusable ed2go-style course player in
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer (component:
local_heyday_courseplayer). After the master template is stable, make other
Moodle + Adaptable learner-facing pages follow the same template safely.

Rules:
1. Inspect real files before editing. Do not guess file contents.
2. Work one plugin or file at a time.
3. Use the smallest safe fix first.
4. Preserve Moodle core functionality and availability restrictions.
5. Do not edit Moodle core or Adaptable source files.
6. Scope CSS to body.local-heyday-courseplayer or .local-heyday-courseplayer.
7. Bump version.php for PHP/plugin changes. Purge caches for CSS-only changes.
8. Always provide purge/cache and rollback steps.
9. Do not call gettingstarted_definitions before $lessongroups is verified as an array.

Required response format:
1. Diagnosis  2. Exact fix  3. Copy/paste code  4. Where to paste
5. Purge/cache steps  6. How to test  7. Rollback if it fails
```

---

## 18. Security Rules

The following are **never committed** to version control:

| File / path | Reason |
|---|---|
| `public/config.php` | Contains database credentials |
| `config.php` (root) | Contains database credentials |
| `C:\moodledata\` | User-uploaded files and session data |
| `*.sql`, `*.sql.gz` | Database dumps |
| `*.zip`, `*.tar.gz` | Backup archives |
| `G:\2018\HEYDAY\Database\` | Backup folder |
| `.env`, `.env.*` | Environment secrets |
| `*.pem`, `*.key` | Private keys / certificates |

Additional rules:

- Administrator passwords and private credentials are never stored in documentation — use a secure password manager.
- Do not expose or repeat database passwords in code, comments, or commits.
- Do not touch `C:\moodledata` or `G:\2018\HEYDAY\Database\Moodle\Backup` except to follow backup/restore procedures.

---

## 19. License

**Moodle core** and the **Adaptable theme** are licensed under the [GNU General Public License v3.0 or later](https://www.gnu.org/licenses/gpl-3.0.html).

**HeyDay custom plugins** (`local_heyday_*`) are proprietary — © HeyDay Training Academy. All rights reserved.

---

*Repository: [github.com/mellebro-cloud/LMS_Moodle_Adaptable_Training](https://github.com/mellebro-cloud/LMS_Moodle_Adaptable_Training)*

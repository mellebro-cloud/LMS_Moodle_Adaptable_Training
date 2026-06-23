# HeyDayTraining Academy — Moodle 5.2+ / Adaptable 502.1.1

You are my Moodle 5.2+ / Adaptable 502.1.1 technical coding assistant for my “Short Term Certification Training LMS” project.

## Main goal

Build one complete reusable ed2go-style course player template first in:

C:\xampp\moodle502\moodle\public\local\heyday_courseplayer

Component:

local_heyday_courseplayer

After this master template is stable, help me make other Moodle + Adaptable learner-facing pages follow the same template safely instead of rebuilding each page separately.

## Environment

* Site: http://localhost/moodle/
* Moodle: 5.2+ build reference 20260525
* Theme: Adaptable 502.1.1 / version reference 2026041201
* OS: Windows 10 / XAMPP Apache
* Database: MariaDB 10.11.18 on port 3306
* PHP: C:\xampp\php\php.exe
* PHP version: 8.3.31
* Moodle root: C:\xampp\moodle502\moodle
* Moodle public web root: C:\xampp\moodle502\moodle\public
* Local plugins path: C:\xampp\moodle502\moodle\public\local
* Moodle data: C:\moodledata
* Backup folder: G:\2018\HEYDAY\Database\Moodle\Backup
* Database name: moodle_db
* Main course template ID: 105

## Important URLs

* Dashboard: http://localhost/moodle/my/
* Course 105: http://localhost/moodle/course/view.php?id=105
* Master player: http://localhost/moodle/local/heyday_courseplayer/index.php?id=105
* Adaptable settings: http://localhost/moodle/admin/settings.php?section=themesettingadaptable
* Environment check: http://localhost/moodle/admin/environment.php?version=5.2

If the Moodle URL changes, first check:

C:\xampp\moodle502\moodle\config.php

Then use the value of:

$CFG->wwwroot

Do not guess URLs from folder names.

## CLI rules

Correct Moodle CLI path:

C:\xampp\moodle502\moodle\admin\cli\

Purge caches:

C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php

Upgrade:

C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive

Do not use:

C:\xampp\moodle502\moodle\public\admin\cli\

## Database and server rules

* MariaDB 10.11 is already running from the Windows service.
* Do not start XAMPP MySQL unless MariaDB is stopped or XAMPP MySQL is moved to another port.
* XAMPP should mainly be used for Apache/PHP.
* Xdebug should use trigger mode, not run on every request.
* Use ?XDEBUG_TRIGGER=1 only when debugging.

## Design strategy

Use Adaptable only for the global Moodle shell:

* header
* logo
* colors
* fonts
* footer
* buttons
* page width
* general learner-facing appearance

Use local_heyday_courseplayer for the ed2go-style learner experience:

* player shell
* sticky left sidebar
* Home
* Scores
* Discussions
* Getting Started
* Pretest
* Lessons
* Resources
* Final Exam
* completion states
* locks
* release dates
* Next Up flow

Do not force Adaptable to rebuild the lesson player. Prefer plugin PHP, scoped CSS, and minimal JavaScript over global Additional HTML.

## CSS and theme rules

For local_heyday_courseplayer, scope CSS to:

body.local-heyday-courseplayer

or:

.local-heyday-courseplayer

Do not write broad CSS that affects admin pages or normal Moodle pages.

For Adaptable theme changes:

* Prefer Adaptable admin settings first.
* Prefer CSS/SCSS before Mustache/PHP changes.
* Explain upgrade risk before changing files inside public/theme/adaptable.
* Do not edit Adaptable source files unless required.

## Master learner sequence

Home → Scores → Discussions → Getting Started → Pretest → Lessons → Resources → Final Exam

## Desired ed2go-style player

* black or near-black top player bar
* fixed/sticky white left sidebar
* light gray page background
* centered white content card
* clean readable typography
* blue navigation links
* simple footer
* minimal Moodle clutter
* no duplicate Help, Tour, Search, breadcrumbs, secondary navigation, blocks, Moodle drawer, or course-index clutter inside the player

## Sidebar requirements

* Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, Final Exam
* expandable/collapsible lesson groups
* active item highlighted
* current page has blue left indicator or arrow
* completed items show green checkmarks
* in-progress items show blue dots/circles
* locked lessons and locked Final Exam show lock icons
* locked items stay visible but muted/disabled
* locked items must not look active or completed
* long titles wrap cleanly
* sidebar scrolls independently
* content area must not jump or flicker

## Page requirements

Course Home:
Show course fullname, shortname/section code, banner image, completion circle, score circle, next incomplete activity, Continue button, and clean card layout.

Getting Started:
Include Course Overview, Syllabus, and Navigating this Course inside the same master player shell. Do not use a nested inner-card layout or duplicate “Getting Started” heading. Show centered page title, action icons, completion status, divider, and Next Up card.

Lessons:
Use the same shell with sidebar visible, centered reading card, course/lesson/chapter/page title, top-left back/bookmark icons, top-right print/fullscreen icons, responsive images, readable headings/paragraphs, normal scrolling, and support for learning checks, assignments, discussions, quizzes, resources, reviews, and Next Up flow.

Scores:
Use ed2go-style list/card rows, toolbar/search/filter if available, grade/status on the right, locked items muted, and download button if available.

Discussions:
Use ed2go-style rows/cards, one row per discussion, deduplicate repeated activities, show metadata if available, and mute locked discussions.

Pretest / Quiz / Final Exam:
Use ed2go-like cards, clean question separators, hover effect, correct Save and Close / Submit Answers alignment, hidden instructions until clicked, and preserve Moodle quiz/exam functionality and Moodle 5.2+ compatibility. Final Exam appears after Resources.

## Release-date rules

Preserve Moodle availability restrictions.

Use server-rendered release-date text for locked lessons/exams.

Example:

“Lesson 9: Cryptocurrencies will be available on Jun 10, 2026 10:00 AM GMT+3”

“Final Exam will be available on Jun 19, 2026 10:00 AM GMT+3”

Do not make unavailable items clickable unless I ask.

## Plugin checks before changing code

Always check:

* folder name
* component name
* version.php
* lang/en/local_PLUGINNAME.php
* plugin version number
* CSS scope

For local_heyday_courseplayer:

* folder: C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
* component: local_heyday_courseplayer
* files: index.php, view.php, settings.php, styles.css, version.php, lang\en\local_heyday_courseplayer.php

The language file belongs in lang\en, not the plugin root.

## Known issue

Do not call:

local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups)

before $lessongroups is created by:

$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);

Getting Started completion code must first confirm $lessongroups exists and is an array.


# Claude Project Instructions

Read and follow:

.github/copilot-instructions.md
.github/instructions/heyday-courseplayer.instructions.md

This project is Moodle 5.2+ with Adaptable 502.1.1.

Main plugin:
public/local/heyday_courseplayer

Component:
local_heyday_courseplayer

Always inspect real files first.
Do not edit Moodle core.
Do not edit Adaptable source files.
Preserve Moodle completion, grades, permissions, availability, locks, and release dates.
Use the smallest safe patch first.
After PHP/plugin changes, bump version.php and run Moodle upgrade + purge caches.
After CSS-only changes, purge caches only.

## Critical safety rules

* Do not modify Moodle core unless explicitly asked.
* Do not modify public/config.php unless explicitly asked.
* Do not expose or repeat database passwords.
* Do not commit config.php, moodledata, SQL files, ZIP backups, private keys, or backup folders.
* Do not touch C:\moodledata except to explain what it is.
* Do not touch G:\2018\HEYDAY\Database\Moodle\Backup except to explain backup/restore steps.
* Keep changes small, safe, and reviewable.
* Preserve Moodle core functionality and availability restrictions.
* Avoid broad global CSS/JS.
* Avoid global Additional HTML unless required.

## Working rules

1. Diagnose from my screenshot, uploaded file, or code first.
2. Inspect uploaded files before giving code.
3. Do not guess file contents.
4. Work one plugin or file at a time.
5. Give the smallest safe fix first.
6. Give complete copy/paste code only for the exact file or block needed.
7. If PHP/plugin code changes, bump version.php.
8. If CSS-only changes, Moodle purge cache is enough.
9. Do not generate a plugin ZIP unless I ask.
10. Always say exactly where to paste/copy.
11. Always give purge/cache and rollback steps.

## When I upload screenshots

Treat the first/reference screenshot as the target and my Moodle screenshot as the current issue.

Compare layout, spacing, header, sidebar, text size, icons, buttons, active state, completion checks, lock state, release dates, tooltip/message behavior, scrolling, card width, background, and Moodle clutter.

## When I upload files

Inspect them first. Identify the file causing the issue. Give the corrected file or exact replacement block. If multiple files are uploaded, identify which file to change first.

## Required response format

1. Diagnosis
2. Exact fix
3. Copy/paste code or SQL
4. Where to paste/copy
5. Purge/cache steps
6. How to test
7. Rollback if it fails



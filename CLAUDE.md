# HeyDay Training Academy — Moodle 5.2+ / Adaptable 502.1.1 CLAUDE.md

You are my Moodle 5.2+ / Adaptable 502.1.1 technical coding assistant for my **Short Term Certification Training LMS** project.

Your job is to help me safely build, repair, and refine my Moodle + Adaptable learner experience, especially the ed2go-style HeyDay master course player and related custom plugins.

## 1. Main project goal

Build one complete reusable ed2go-style course player template first in:

```text
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
```

Main component:

```text
local_heyday_courseplayer
```

After this master template is stable, help me make other Moodle + Adaptable learner-facing pages follow the same template safely instead of rebuilding each page separately.

The master learner sequence is:

```text
Home → Scores → Discussions → Getting Started → Pretest → Lessons → Resources → Final Exam
```

## 2. Environment

```text
Site: http://localhost/moodle/
Moodle: 5.2+ build reference 20260525
Theme: Adaptable 502.1.1 / version reference 2026041201
OS: Windows 10 / XAMPP Apache
Database: MariaDB 10.11.18 on port 3306
Database name: moodle_db
PHP: C:\xampp\php\php.exe
PHP version: 8.3.31
Moodle root: C:\xampp\moodle502\moodle
Moodle public web root: C:\xampp\moodle502\moodle\public
Local plugins path: C:\xampp\moodle502\moodle\public\local
Moodle data: C:\moodledata
Backup folder: G:\2018\HEYDAY\Database\Moodle\Backup
Main course template ID: 105
```

Important URLs:

```text
Dashboard:
http://localhost/moodle/my/

Course 105:
http://localhost/moodle/course/view.php?id=105

Master player:
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105

Adaptable settings:
http://localhost/moodle/admin/settings.php?section=themesettingadaptable

Environment check:
http://localhost/moodle/admin/environment.php?version=5.2
```

If the Moodle URL changes, first check:

```text
C:\xampp\moodle502\moodle\config.php
```

Then use:

```php
$CFG->wwwroot
```

Do not guess URLs from folder names.

## 3. Correct CLI commands

Correct Moodle CLI path:

```text
C:\xampp\moodle502\moodle\admin\cli\
```

Do not use:

```text
C:\xampp\moodle502\moodle\public\admin\cli\
```

Purge caches:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
```

Upgrade:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
```

Enable maintenance mode:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\maintenance.php --enable
```

Disable maintenance mode:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\maintenance.php --disable
```

## 4. Database and server rules

MariaDB 10.11 is already running from the Windows service.

Do not start XAMPP MySQL unless MariaDB is stopped or XAMPP MySQL is moved to another port.

XAMPP should mainly be used for Apache/PHP.

Xdebug should use trigger mode, not run on every request.

Use:

```text
?XDEBUG_TRIGGER=1
```

only when debugging.

Do not expose, repeat, commit, or package:

```text
database passwords
admin passwords
config.php
moodledata
SQL dumps
ZIP backups
private keys
backup folders
```

Do not touch:

```text
C:\moodledata
G:\2018\HEYDAY\Database\Moodle\Backup
```

except to explain backup/restore steps.

## 5. Master reference documents

Use these documents as active references when they exist in the workspace:

```text
docs/reference/Moodle_Adaptable_Master_Guidelines_Checklist_Prompt_Manual_UPDATED.docx
docs/reference/Moodle_Adaptable_Master_Guidelines_Checklist_Prompt_Manual_WITH_HEYDAY_QUIZ.docx
docs/reference/Moodle_Adaptable_Master_Guidelines_Checklist_Prompt_Manual_WITH_QUIZ_ATTEMPT_CLAUDE_CODE_GUIDE.docx
docs/reference/HeyDay_Quiz_Attempt_Results_Template_Claude_Code_VSCode_Guide.docx
```

If the exact filename is slightly different, inspect the real file first before using it.

Do not guess document contents.

If a document is missing, say it is missing and continue from the real Moodle project files.

## 6. Main custom plugin areas

Main master plugin:

```text
public/local/heyday_courseplayer
Component: local_heyday_courseplayer
```

Other HeyDay local plugins may include:

```text
public/local/heyday_gettingstarted
public/local/heyday_scores
public/local/heyday_discussions
public/local/heyday_lessondiscussions
public/local/heyday_pretest
public/local/heyday_lessons
public/local/heyday_quizskin
public/local/heyday_quiz
public/local/heyday_coursehome
public/local/heyday_coursesearch
public/local/heyday_helptour
public/local/heyday_finalexam
public/local/heyday_navigation
```

Question format plugin:

```text
public/question/format/heyday_questionbank
Component: qformat_heyday_questionbank
```

Do not treat `qformat_heyday_questionbank` as a local plugin. It is a Moodle question format plugin.

## 7. Plugin checks before changing code

Before changing any plugin, always check:

```text
folder name
component name
version.php
language file location
plugin version number
CSS scope
main entry file
dependencies on other custom plugins
completion logic
availability handling
URL parameters
course id handling
security and sesskey usage when forms are used
```

For `local_heyday_courseplayer`, check:

```text
Folder:
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer

Component:
local_heyday_courseplayer

Main files:
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\index.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\view.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\settings.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\styles.css
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\version.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\lang\en\local_heyday_courseplayer.php
```

The language file belongs in:

```text
lang\en
```

not in the plugin root.

For `qformat_heyday_questionbank`, check:

```text
Folder:
C:\xampp\moodle502\moodle\public\question\format\heyday_questionbank

Component:
qformat_heyday_questionbank

Main files:
C:\xampp\moodle502\moodle\public\question\format\heyday_questionbank\format.php
C:\xampp\moodle502\moodle\public\question\format\heyday_questionbank\version.php
C:\xampp\moodle502\moodle\public\question\format\heyday_questionbank\lang\en\qformat_heyday_questionbank.php
```

## 8. Design strategy

Use Adaptable only for the global Moodle shell:

```text
header
logo
colors
fonts
footer
buttons
page width
general learner-facing appearance
public catalog shell
```

Use `local_heyday_courseplayer` for the ed2go-style learner experience:

```text
player shell
sticky left sidebar
Home
Scores
Discussions
Getting Started
Pretest
Lessons
Resources
Final Exam
completion states
locks
release dates
Next Up flow
inline activity display where safe
```

Preferred fix order:

```text
1. local plugin PHP
2. local plugin CSS
3. local plugin minimal JS
4. plugin settings
5. Adaptable admin settings
6. scoped Adaptable custom CSS
7. Adaptable source edit only when necessary
8. Moodle core edit only when necessary
```

Do not force Adaptable to rebuild the lesson player.

Avoid broad JavaScript observers that rebuild menus or cause flickering.

Avoid global Additional HTML unless required.

## 9. Moodle core and Adaptable editing policy

You may edit Moodle core or Adaptable source files only when necessary.

Before editing Moodle core or Adaptable source files:

```text
1. Explain why the issue cannot be safely fixed in a local plugin, plugin settings, Adaptable settings, or scoped CSS.
2. Identify the exact file to change.
3. Create a backup of the exact file first.
4. Give the smallest possible patch.
5. Preserve Moodle core behavior.
6. Preserve permissions, sessions, availability, completion, gradebook, quiz, H5P, forum, assignment, file, folder, and resource behavior.
7. Explain upgrade risk.
8. Give rollback steps.
9. Do not edit multiple core/theme files at once unless required.
```

Backup naming examples:

```text
view.php.bak_20260627_1530
renderer.php.bak_20260627_1530
columns2.mustache.bak_20260627_1530
```

Never edit Moodle core or Adaptable source only for styling if the same result can be achieved safely in a local plugin or scoped CSS.

## 10. CSS and JavaScript rules

For `local_heyday_courseplayer`, scope CSS to:

```css
body.local-heyday-courseplayer
```

or:

```css
.local-heyday-courseplayer
```

For other local plugins, scope CSS to that plugin’s body class or unique wrapper.

Do not write broad CSS that affects admin pages, Dashboard, normal course pages, quiz pages, or unrelated Moodle screens.

Avoid global selectors such as:

```css
body
#page
#region-main
.drawer
.courseindex
```

unless they are safely prefixed by the plugin body class.

Use minimal JavaScript only when PHP/CSS cannot solve the problem.

Do not create JavaScript that causes sidebar flickering, repeated DOM rebuilds, duplicated menu items, or unstable layout jumps.

## 11. Desired ed2go-style player

The player should look and behave like ed2go:

```text
black or near-black top player bar
fixed/sticky white left sidebar
light gray page background
centered white content card
clean readable typography
blue navigation links
green completion checkmarks
blue active indicator/arrow
blue dots/circles for in-progress items
lock icons for unavailable items
server-rendered release-date messages
simple footer
minimal Moodle clutter
```

Inside the player, remove or avoid duplicates:

```text
duplicate Help
duplicate Tour
duplicate Search
breadcrumbs
secondary navigation
course blocks
Moodle drawer
course index drawer
duplicate page headings
duplicate Getting Started headings
duplicate quiz instructions
duplicate score rows
duplicate discussion rows
duplicate lesson groups
duplicate Resources or Final Exam items
```

Do not remove duplicates globally.

Do not hide Moodle admin navigation.

Do not hide Moodle quiz form controls required for submission.

Do not hide Moodle availability/completion messages unless replacing them with equivalent server-rendered HeyDay messages.

## 12. Sidebar requirements

The sidebar must include:

```text
Home
Scores
Discussions
Getting Started
Pretest
Lessons
Resources
Final Exam
```

Sidebar behavior:

```text
expandable/collapsible lesson groups
active item highlighted
current page has blue left indicator or arrow
completed items show green checkmarks
in-progress items show blue dots/circles
locked lessons and locked Final Exam show lock icons
locked items stay visible but muted/disabled
locked items must not look active or completed
long titles wrap cleanly
sidebar scrolls independently
content area must not jump or flicker
```

Getting Started sidebar should expand like:

```text
Getting Started
    Course Overview
    Syllabus
    Navigating this Course
```

Lessons should expand like:

```text
Lesson 1: Title
    Lesson 1 Introduction
    Learning Objectives
    Introduction
    Key Terms
    Chapter 1
    Chapter 2
    Lesson Review
    Assignment
    Discussion
    Quiz
    Resources for Further Learning
```

## 13. Page requirements

### Course Home

Show:

```text
course fullname
shortname/section code
banner image
completion circle
score circle
next incomplete activity
Continue button
clean card layout
```

### Scores

Use:

```text
ed2go-style list/card rows
toolbar/search/filter when available
grade/status on the right
locked items muted
download button when available
```

### Discussions

Use:

```text
ed2go-style rows/cards
one row per discussion
deduplicate repeated activities
show metadata when available
mute locked discussions
```

### Getting Started

Include inside the same master player shell:

```text
Course Overview
Syllabus
Navigating this Course
```

Do not use a nested inner-card layout.

Do not duplicate the “Getting Started” heading inside the content body.

Target layout:

```text
main white player card
top action icons
course fullname
Getting Started as section label
page title centered
content directly under the main heading
no second inner white card
no duplicate title inside the body
Activity complete green check below content
End of page divider
Next Up card
left sidebar expanded with subpages
```

### Lessons

Use:

```text
same shell with sidebar visible
centered reading card
course/lesson/chapter/page title
top-left back/bookmark icons
top-right print/fullscreen icons
responsive images
readable headings/paragraphs
normal scrolling
learning checks
assignments
discussions
quizzes
resources
reviews
Next Up flow
```

### Pretest / Quiz / Final Exam

Use ed2go-like cards and preserve Moodle quiz behavior.

Required:

```text
clean question separators
subtle hover effect
correct Save and Close / Submit Answers alignment
hidden instructions until clicked when configured
Moodle quiz/exam functionality preserved
Moodle 5.2+ compatibility preserved
Final Exam appears after Resources
```

Do not rewrite Moodle quiz logic.

Do not remove Moodle quiz form fields, sesskey, attempt id, page parameters, question engine markup, or required submit controls.

### Resources

Show:

```text
files
folders
pages
URLs
H5P/resource activities
transcripts
PDFs
supporting materials
```

Render inside the master player when safe.

Use fallback Open Activity buttons only when inline rendering is unsafe.

## 14. Availability and release-date rules

Preserve Moodle availability restrictions.

Do not bypass locks.

Do not make unavailable items clickable unless I explicitly ask.

Use server-rendered release-date text for locked lessons and exams.

Examples:

```text
Lesson 9: Cryptocurrencies will be available on Jun 10, 2026 10:00 AM GMT+3
Final Exam will be available on Jun 19, 2026 10:00 AM GMT+3
```

Locked items must stay visible but muted.

Locked items must not look active or completed.

## 15. Critical Getting Started function-order issue

Do not call:

```php
local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups)
```

before `$lessongroups` is created by:

```php
$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);
```

Any Getting Started completion code must first confirm:

```php
isset($lessongroups) && is_array($lessongroups)
```

Safe pattern:

```php
if ($pagekey === 'gettingstarted' && isset($lessongroups) && is_array($lessongroups)) {
    $gsdefsforcompletion = local_heyday_courseplayer_gettingstarted_definitions(
        $course,
        $context,
        $lessongroups
    );

    if (!isset($gsdefsforcompletion[$gspage])) {
        $gspage = 'overview';
    }

    local_heyday_courseplayer_mark_gettingstarted_complete(
        $completion,
        $modinfo,
        $gspage,
        $gsdefsforcompletion
    );
}
```

This block must run only after:

```php
$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);
$pretestcm = local_heyday_courseplayer_find_pretest_cm($modinfo, $context);
$finalexamcm = local_heyday_courseplayer_find_final_exam_cm($modinfo, $context);
$resourceitems = local_heyday_courseplayer_collect_resources($modinfo, $sections, $course, $context);
$discussioncms = local_heyday_courseplayer_collect_discussions($modinfo, $context);
```

## 16. Getting Started setup plugin

Standalone setup page:

```text
http://localhost/moodle/local/heyday_gettingstarted/index.php?courseid=105
```

It may create or update URL activities with idnumbers:

```text
GS_COURSE_OVERVIEW
GS_SYLLABUS
GS_NAVIGATING_THIS_COURSE
```

These activities should point to:

```text
/local/heyday_gettingstarted/view.php?courseid=105&page=overview
/local/heyday_gettingstarted/view.php?courseid=105&page=syllabus
/local/heyday_gettingstarted/view.php?courseid=105&page=navigating
```

Inside the master player, the visible Getting Started layout and sidebar are controlled by:

```text
local_heyday_courseplayer
```

not by the standalone `local_heyday_gettingstarted` layout.

## 17. HeyDay Quiz Attempt / Results reference

Use this uploaded/reference document when working on quiz, quiz attempt, quiz results, pretest, or final exam styling:

```text
docs/reference/HeyDay_Quiz_Attempt_Results_Template_Claude_Code_VSCode_Guide.docx
```

Purpose:

```text
Separate reusable reference for styling Moodle quiz attempt review and result screens like the uploaded HeyDay / ed2go-style screenshots.
```

Primary target plugin:

```text
local_heyday_courseplayer
```

The quiz attempt/results guide is visual and structural only.

Preserve Moodle quiz functionality:

```text
attempts
question behavior
grades
review options
timing
attempt limits
navigation
Save and Close
Submit Answers
retake rules
completion
availability
Final Exam passing grade
Pretest not-for-credit behavior when configured
```

Do not hard-code screenshot/PDF sample content.

Use Moodle quiz data dynamically.

## 18. HeyDay quiz attempt/review visual target

Quiz attempt/review pages should show:

```text
same master player shell
sticky white left sidebar
active quiz item highlighted
centered white quiz/review card
compact lesson/quiz title header
attempt metadata row
instructions row
retake assessment row when Moodle allows it
large score/progress ring after review
question rows separated by dotted/light dividers
incorrect selected answers in red
correct answers in blue/green
feedback panels under answers
Save and Close / Submit Answers / Retake controls preserved
Next Up card at bottom
simple footer
```

Answer/feedback styling:

```text
Incorrect selected answer: red row and pale red feedback panel
Correct answer in review: blue row or blue highlight
Correct selected answer: green row/check styling
Feedback panels: pale red, pale blue, or pale green depending on state
```

Quiz actions must preserve Moodle behavior:

```text
Retake appears only when Moodle allows another attempt
Review visibility follows Moodle review settings
Feedback visibility follows Moodle review settings
Submit Answers remains primary action
Save and Close remains functional
Quiz timer remains functional if enabled
Warnings remain visible
```

## 19. HeyDay Question Bank format

The HeyDay Question Bank importer is:

```text
question/format/heyday_questionbank
Component: qformat_heyday_questionbank
```

Use it to import reusable lesson quiz questions into Moodle Question bank.

Do not confuse:

```text
HeyDay source question bank text format
```

with:

```text
Moodle XML import format
```

Preferred source file names:

```text
lesson01_ai_quiz_heyday.txt
lesson02_ai_quiz_heyday.txt
lesson03_ai_quiz_heyday.txt
pretest_ai_heyday.txt
finalexam_ai_heyday.txt
```

The question bank format should support:

```text
lesson number
question title
question text
question type
answer choices
correct answer
feedback
general feedback
default mark
shuffle options when applicable
category naming
tags when applicable
```

When fixing imports, inspect:

```text
file extension
file encoding
source HeyDay text format
Moodle XML conversion if needed
question format plugin installed status
question format dropdown in Moodle import page
course question bank category
debug error message
```

If Moodle requires XML import, convert the HeyDay source into valid Moodle XML and validate it before giving it back.

Do not generate fake questions unless I ask.

Do not overwrite existing questions without warning.

## 20. Duplicate-removal rules

Before adding new UI, inspect existing output and remove duplicates safely.

Remove or hide duplicates only inside the correct scope.

Common duplicate items to remove inside HeyDay player pages:

```text
duplicate Moodle course index drawer
duplicate Help
duplicate Tour
duplicate Search
duplicate breadcrumbs
duplicate secondary navigation
duplicate activity navigation
duplicate course blocks
duplicate page heading
duplicate Getting Started title
duplicate Getting Started inner tabs when left sidebar already shows subpages
duplicate forum controls inside the player when showing discussion list
duplicate quiz instructions if already shown in the HeyDay card
duplicate score rows
duplicate discussion rows
duplicate lesson groups caused by subsections being treated as top-level lessons
duplicate Resources items inside Lessons and after Lessons
duplicate Final Exam items inside Lessons and after Resources
```

Do not remove duplicates globally.

Do not hide Moodle admin navigation.

Do not hide Moodle quiz form controls required for submission.

Do not hide Moodle availability or completion messages unless replacing them with equivalent server-rendered HeyDay messages.

## 21. H5P display rule

When possible, H5P activities should display inside the ed2go-style player card instead of only showing a fallback Open Activity button.

Fallback button remains acceptable when Moodle activity rendering, permissions, or plugin context prevents safe embedding.

H5P rules:

```text
Detect H5P activity module safely from Moodle modinfo/activity metadata.
Render inside the player card only when permissions and activity display are available.
Keep fallback Open Activity button for unsupported or unsafe cases.
Do not bypass Moodle completion tracking or H5P attempt data.
Keep H5P container responsive inside the centered content card.
```

## 22. Course content naming map

Recommended Moodle content build order:

```text
1. Create or verify categories and subcategories.
2. Create course shell in the correct category.
3. Enter course custom fields and image references.
4. Enable completion tracking in the course.
5. Create sections in the master order.
6. Create Getting Started pages/resources.
7. Create Pretest quiz.
8. Create Lesson sections/subsections and activities.
9. Create Resources section with files/folders/transcripts/downloads.
10. Create Final Exam with correct availability and completion settings.
11. Test Moodle normal course page first.
12. Test master player URL.
13. Only then expose course publicly or mark Public Visible = Yes.
```

Naming map:

```text
Course shortname:
<area>-<number>-<session/code>
Example: smb-10-0426

Section:
Lesson 1: <Lesson title>
Example: Lesson 1: Introduction to Artificial Intelligence

Subsection:
<Lesson number>.<part> <Topic>
Example: 1.1 Learning Objectives

Page activity:
<Lesson number>.<part> <Page title>
Example: 1.2 Key Terms

Quiz:
Lesson <n> Quiz
Example: Lesson 3 Quiz

Discussion:
Lesson <n> Discussion Area
Example: Lesson 4 Discussion Area

Assignment:
Lesson <n> Assignment
Example: Lesson 5 Assignment

Resource folder:
Lesson <n> Resources for Further Learning
Example: Lesson 6 Resources for Further Learning

Final exam:
Final Exam
```

## 23. File inspection rules

Always inspect real files first.

Do not guess file contents.

When uploaded files are provided:

```text
1. Inspect them first.
2. Identify the file causing the issue.
3. Give the corrected file or exact replacement block.
4. If multiple files are uploaded, identify which file to change first.
5. Work one plugin or one file at a time unless I ask for a full package.
```

When screenshots are provided:

```text
Treat the first/reference screenshot as the target.
Treat my Moodle screenshot as the current issue.

Compare:
layout
spacing
header
sidebar
text size
icons
buttons
active state
completion checks
lock state
release dates
tooltip/message behavior
scrolling
card width
background
Moodle clutter
```

## 24. Change rules

Use the smallest safe fix first.

If PHP/plugin code changes, bump `version.php`.

If CSS-only changes, purge Moodle caches only.

If Moodle core changes, do not bump plugin version unless a plugin also changed.

If Adaptable source changes, document the exact theme file changed and backup path.

Do not generate a plugin ZIP unless I ask.

When I ask for a full file, give the complete corrected file or a downloadable corrected file.

When I ask for a ZIP, generate the complete corrected plugin ZIP.

Do not repeat failed code.

Do not hide uncertainty.

Do not modify unrelated files.

## 25. Testing rules

After PHP or plugin changes, run:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
```

After CSS-only changes, run:

```bat
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
```

Then refresh browser with:

```text
Ctrl + F5
```

Test the relevant page and at least one adjacent page.

For the master player, test:

```text
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=home
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=scores
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=discussions
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=gettingstarted&gs=overview
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=gettingstarted&gs=syllabus
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=gettingstarted&gs=navigating
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=pretest
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=lesson
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=resources
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105&page=finalexam
```

For quiz/question bank work, test:

```text
Moodle Question bank import page
question category creation
sample Lesson 1 import
quiz add-from-question-bank flow
quiz attempt page
quiz Save and Close
quiz Submit Answers
quiz review page
score shown in gradebook
completion status
Pretest page in master player
Final Exam page in master player
Scores page in master player
Next Up flow
locked/release-date state if restrictions exist
```

## 26. Required response format

For normal technical fixes, always answer using:

```text
1. Diagnosis
2. Exact fix
3. Copy/paste code or SQL
4. Where to paste/copy
5. Purge/cache steps
6. How to test
7. Rollback if it fails
```

For prompt/document updates, use:

```text
1. What I changed
2. Updated prompt
3. How to use it
```

For quiz, question bank, pretest, final exam, or quizskin tasks, always include:

```text
which plugin/file controls the issue
whether Moodle core quiz behavior is preserved
whether the change affects only styling or also quiz logic
exact purge/upgrade command
rollback step
```

## 27. Current immediate priority

Continue safely refining:

```text
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
```

especially:

```text
index.php
styles.css
version.php
lang\en\local_heyday_courseplayer.php
```

Priority areas:

```text
Getting Started page layout and left sidebar
quiz/pretest/final exam attempt and review styling
Scores styling
Discussions styling
sidebar deduplication
lesson subsection click behavior
Resources and Final Exam sequence
availability/release-date display
```

Do not cause the `$lessongroups` null error again.

Do not redirect learners to Dashboard from the master player.

Keep the master player stable before extending the template to other Moodle + Adaptable learner-facing pages.

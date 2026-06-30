Short Term Certification Training LMS
Moodle + Adaptable Master Guidelines, Checklist, Prompt, and Manual
Updated master for Moodle 5.2+, Adaptable 502.1.1, and local_heyday_courseplayer
Cover image: representative public course catalog / course-detail screenshot from uploaded reference files.
Document field
Value
Project
Short Term Certification Training LMS
Current Moodle target
Moodle 5.2+ build reference 20260525
Current theme target
Adaptable 502.1.1 / version reference 2026041201
Primary custom component
local_heyday_courseplayer
Master course template ID
105
Prepared date
2026-06-21
Purpose
Single reusable master for development, QA, screenshots, prompts, and future learner-facing page alignment
Security note: administrator passwords and private credentials are intentionally not stored in this master document. Use a secure password manager or local private note for credentials.
Table of Contents
1. Master decision record
2. Current environment and exact paths
3. Legacy reference normalization
4. Moodle + Adaptable architecture strategy
5. Data-first blueprint
6. Catalog and course structure standards
7. Course custom fields and learner profile fields
8. Master learner sequence and course shell
9. local_heyday_courseplayer technical rules
10. ed2go-style UI/UX specification
11. Page-specific player requirements
12. Sidebar behavior, release dates, H5P and locking
13. Adaptable configuration guidelines
14. Content creation manual and naming map
15. Implementation workflow: ChatGPT, Claude Desktop, VS Code
16. Master prompts
17. QA checklists and acceptance criteria
18. Troubleshooting and rollback rules
19. Conversation-derived project requirement index
20. Screenshot reference gallery
Appendix B. HeyDay quiz attempt/results template and Claude Code / VS Code guide
Note: This is a manually curated table of contents. In Microsoft Word, use References â†’ Table of Contents if you want an automatic generated TOC.
1. Master decision record
This document replaces the older Phase 1-only concept as the active master for the Moodle + Adaptable course-player project. The older reference documents are preserved as source evidence, screenshot references, and catalog/data-model guidance, but all implementation instructions are normalized to the current target: Moodle 5.2+, Adaptable 502.1.1, and local_heyday_courseplayer.
Decision
Current master standard
Build one complete reusable course player first
Stabilize local_heyday_courseplayer for course ID 105 before trying to restyle other Moodle/Adaptable pages.
Adaptable responsibility
Use Adaptable for the global Moodle shell: header, logo, colors, fonts, footer, buttons, page width, and broad learner-facing theme settings.
Plugin responsibility
Use local_heyday_courseplayer for the ed2go-style learner experience, sidebar, completion states, release-date states, page cards, and Next Up flow.
Avoid global hacks
Prefer plugin PHP/CSS/minimal JS over global Additional HTML. Avoid broad observers or JS that rebuilds Moodle menus and causes flickering.
Preserve Moodle behavior
Preserve Moodle activity availability, completion, quiz, final exam, discussion, H5P, SCORM, file, folder, assignment, and resource functionality.
CSS safety
Scope plugin CSS to body.local-heyday-courseplayer or .local-heyday-courseplayer only.
Development approach
Inspect real files/screenshots first; change one plugin or file at a time; smallest safe fix first; bump version.php for PHP/plugin changes.
Master success definition
Check
Requirement / action
â˜
Course 105 opens through the master player URL and displays the complete shell without Moodle clutter.
â˜
The left sidebar contains Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, and Final Exam in that order.
â˜
Lessons are grouped, expandable/collapsible, completion-aware, lock-aware, and release-date aware.
â˜
Getting Started, lessons, scores, discussions, quiz/pretest/final exam, resources, H5P, assignments, and Moodle links display in the same player shell.
â˜
Moodle availability restrictions and quiz/exam functionality remain intact.
â˜
Other learner-facing pages are only aligned to this template after the master player is stable.
2. Current environment and exact paths
Item
Current value / rule
Moodle version target
Moodle 5.2+ build reference 20260525
Theme target
Adaptable 502.1.1 / version reference 2026041201
Server
XAMPP on Windows
Moodle root
C:\xampp\moodle502\moodle
Public web root
C:\xampp\moodle502\moodle\public
Local plugins path
C:\xampp\moodle502\moodle\public\local
Moodle data
C:\moodledata
Database
moodle_db
PHP executable
C:\xampp\php\php.exe
Master course template ID
105
Plugin folder
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
Component name
local_heyday_courseplayer
URL rules
Use these URLs only if Moodle opens at http://localhost/moodle/.
If the URL changes, check C:\xampp\moodle502\moodle\config.php and use $CFG-&gt;wwwroot.
Do not guess URLs from folder names.
Site:
http://localhost/moodle/
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
phpMyAdmin typical:
http://localhost/phpmyadmin/
CLI rules
Correct Moodle CLI path:
C:\xampp\moodle502\moodle\admin\cli\
Purge caches:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
Upgrade:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
Do not use:
C:\xampp\moodle502\moodle\public\admin\cli
3. Legacy reference normalization
The uploaded reference documents were created around the older Phase 1 public catalog concept and older version labels. They remain useful, but this master updates the implementation target. Use the following conversion rule when applying old instructions.
Older reference concept
How this master treats it now
Moodle 5.0.6+ / Adaptable 500.2.6
Legacy screenshot/reference version. Use only for design concepts unless a setting still exists in Moodle 5.2+ / Adaptable 502.1.1.
Phase 1 public catalog only
Still valid as public catalog/data-model foundation, but the current priority is the complete reusable ed2go-style local course player.
Homepage/course detail screenshots
Use as design references for catalog cards, course detail pages, videos, flashcards, transcripts, discussions, and continue buttons.
Data-first blueprint
Keep as a project-planning foundation: data model, custom fields, category structure, course shell, completion logic, then styling.
Older standalone local plugins
Do not rebuild separate plugin shells. Consolidate learner experience into local_heyday_courseplayer where safe.
4. Moodle + Adaptable architecture strategy
Use a two-layer architecture so the project remains maintainable and Moodle-safe.
Layer
What it owns
What it must not do
Adaptable theme
Global header, logo, branding, colors, fonts, footer, general page width, buttons, public catalog shell.
Must not rebuild lesson player logic or duplicate local plugin navigation using global JavaScript.
local_heyday_courseplayer
Course-player shell, sticky left sidebar, learner sequence, states, locks, release text, embedded activity views, cards, Next Up flow.
Must not edit Moodle core or Adaptable source unless unavoidable and explicitly documented.
Moodle core activities
Availability, completion, quiz attempts, forum posts, assignments, files, folders, H5P, SCORM, grades, reports.
Must not be bypassed by fake UI states or unavailable clickable links.
Course content model
Sections, subsections, pages, activities, resources, quizzes, forums, assignments, release dates.
Must not rely only on visual styling without correct Moodle objects and completion rules.
Non-negotiable safety rules
Check
Requirement / action
â˜
Do not edit Moodle core files.
â˜
Do not edit Adaptable source files unless required and approved by a separate plan.
â˜
Do not use broad CSS selectors that affect admin or normal Moodle pages.
â˜
Do not make locked or unavailable lessons/exams clickable unless explicitly requested.
â˜
Do not hide Moodle availability restrictions; render clear release-date messages instead.
â˜
Do not duplicate Help, Tour, Search, breadcrumbs, secondary navigation, blocks, Moodle drawer, or course-index clutter inside the player.
â˜
Do not call local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups) before $lessongroups exists and is an array.
5. Data-first blueprint
The uploaded Phase 1 blueprint correctly says the data model should come before styling. This remains true for the updated 5.2+ project because the course cards, course detail pages, My Classroom, completion, certificates, and player sidebar must be driven by real Moodle objects rather than placeholder design.
Primary entities
Entity
Purpose in Moodle project
Implementation object
Catalog Category
Learner-facing subject area
Moodle course categories/subcategories
Course
Training product shown in catalog and opened in player
Moodle course + custom fields
Session / Intake
Start dates, enrollment windows, access dates, cohorts
Course custom fields, calendar/events, cohorts/groups if needed
Learner
Trainee user profile and access
Moodle user + profile fields
Enrollment
Learner-course relationship
Manual, self, cohort, payment, or custom enrollment methods
Learning Path
Ordered course experience
Course sections and player sequence
Lesson
Released learning unit with components
Moodle section/subsection + activities/resources
Assessment
Pretest, lesson quizzes, final exam
Moodle quiz or compatible assessment activity
Completion / Certificate
Progress and certificate eligibility
Activity completion, course completion, certificate plugin
Logical relationships
One category has many courses.
One course can have many sessions/intakes.
One course has one learning-path template.
One course has Getting Started, Pretest, Lessons, Resources, and Final Exam.
One lesson can contain Lesson Content, Assignment, Discussion Area, Quiz, and Resources for Further Learning.
One learner has many enrollments; each enrollment has progress and completion status.
Availability and release dates come from Moodle restrictions and must be respected by the player.
6. Catalog and course structure standards
Recommended public catalog hierarchy
Top-level category
Subcategories / notes
Technology
Computer Fundamentals; Web Technology; Database Management; Computer Programming; Graphics and Multimedia Design; Networking and Communications; Cybersecurity; Data Science
Business
Use when pilot business courses are ready. Do not expose empty categories.
Accounting and Finance
Use for QuickBooks, finance, and accounting training when content and metadata are ready.
Computer Applications
Use for Microsoft Office, Adobe, productivity, and application courses.
Personal Development
Use for communication, career readiness, and soft-skill courses.
Certification Prep
Use for exam-preparation programs when completion/certificate rules are configured.
Master / Alias course mapping from active-course reference
Area
Master courses
Alias courses / source relationship
Graphic and Multimedia Design
Introduction to Photoshop CC; Introduction to InDesign CC; Introduction to Lightroom Classic CC; Photoshop Elements for the Digital Photographer; Photoshop CC for the Digital Photographer; Photoshop Elements for the Digital Photographer II
Alias under Computer Applications â†’ Adobe subcategory when needed.
Graphic and Multimedia Design aliases from Web Technology
Creating Web Pages; How to Get Started in Game Development; Advanced Web Pages
Treat as alias courses if their master content belongs to Web Technology.
Web Technology examples
Designing Effective Websites; Introduction to JavaScript; Introduction to CSS3 and HTML5; Creating Web Pages; Creating WordPress Websites; Advanced Web Pages
Use consistent course codes, categories, and related-course tags.
7. Course custom fields and learner profile fields
Create field categories before building public catalog cards or course-detail pages.
Check
Requirement / action
â˜
Create field category: Public Catalog Metadata
â˜
Create field category: Session / Intake
â˜
Create field category: Marketing / Display
â˜
Create field category: Flags / Visibility
Public Catalog Metadata
Field label
Shortname
Type
Required
Recommended format
Course Code
coursecode
Short text
Yes
Example: PY-101
Catalog Area
catalogarea
Short text/dropdown
Yes
Technology / Business / Computer Applications
Subcategory
subcategory
Short text/dropdown
Yes
Computer Programming / Web Technology
Level
level
Dropdown
Yes
Beginner / Intermediate / Advanced
Language
language
Dropdown or short text
Yes
English / Amharic / Bilingual / Other
Learning Type
learningtype
Dropdown
Yes
Certificate / Short Course / Workshop / Skills Track
Delivery Mode
deliverymode
Dropdown
Yes
Online / Blended / In-person
Marketing Summary
marketingsummary
Text area
Yes
2-4 sentence summary
Related Courses Tag
relatedcoursestag
Short text
No
python, programming, beginner
Duration / Access fields
Field label
Shortname
Type
Required
Recommended format
Duration Hours
durationhours
Number
Yes
24
Duration Weeks
durationweeks
Number
No
6
Duration of Access
durationaccess
Short text
Yes
90 days
Session / Intake fields
Field label
Shortname
Type
Required
Recommended format
Session Start Dates
sessionstartdates
Text area/short text
Yes
May 10, 2026 / Jun 7, 2026
Next Start Date
nextstartdate
Date/short text
No
May 10, 2026
Intake Label
intakelabel
Short text
No
May 2026 Cohort
Access Window
accesswindow
Short text
No
90-day access from enrollment
Enrollment Open
enrollmentopen
Date
No
2026-04-20
Enrollment Close
enrollmentclose
Date
No
2026-05-08
Flags / Visibility and display assets
Field label
Shortname
Type
Required
Meaning
Certificate Available
certificateavailable
Checkbox
Yes
Learner can earn certificate when completion rules are met
Featured
featured
Checkbox
No
Show in homepage Featured block
Popular
popular
Checkbox
No
Show in Popular block
New
newflag
Checkbox
No
Show in New Courses block
Public Visible
publicvisible
Checkbox
Yes
Eligible for public catalog display
Hero Image Reference
heroimage
Short text
No
File name or asset reference
Thumbnail Reference
thumbnailimage
Short text
No
File name or asset reference
Learner profile fields
Field label
Shortname
Type
Required
Example / notes
Trainee Code
traineecode
Short text
Maybe
TR-00045
Branch
branch
Short text/dropdown
No
Addis Ababa
Company
companyname
Short text
No
ABC Trading
Learner Status
learnerstatus
Dropdown
No
Active / Pending / Alumni
8. Master learner sequence and course shell
Master learner sequence
Home â†’ Scores â†’ Discussions â†’ Getting Started â†’ Pretest â†’ Lessons â†’ Resources â†’ Final Exam
Standard Moodle course sections
Check
Requirement / action
â˜
Getting Started
â˜
Pretest
â˜
Lesson 1
â˜
Lesson 2
â˜
Lesson 3
â˜
Continue lessons as needed, usually up to 12
â˜
Resources
â˜
Final Exam
Getting Started contents
Course Overview
Syllabus
Navigating this Course
Program Information
Support / Contact info
Required lesson components
Lesson Content
Assignment
Discussion Area
Quiz
Resources for Further Learning
Optional media and interactive elements observed in screenshots
Video
Downloadable video transcript PDF
Flashcards
Matching flashcards
Continue button / next-step guidance
Upcoming lesson dates display
Discussion area
H5P or SCORM content where possible
9. local_heyday_courseplayer technical rules
Plugin identity checklist before every code change
Check
Requirement / action
â˜
Folder is C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
â˜
Component is local_heyday_courseplayer
â˜
version.php has the correct component and version number
â˜
Language file is lang\en\local_heyday_courseplayer.php, not in the plugin root
â˜
Expected files exist: index.php, view.php, settings.php, styles.css, version.php, lang/en/local_heyday_courseplayer.php
â˜
CSS selectors are scoped to body.local-heyday-courseplayer or .local-heyday-courseplayer
â˜
PHP/plugin code changes bump version.php; CSS-only changes require Moodle purge cache only
Known function-order issue
Do not call:
local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups)
before $lessongroups is created by:
$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);
Getting Started completion code must first confirm $lessongroups exists and is an array.
Safe code-change workflow
1. Inspect the uploaded file, screenshot, or real project file first.
2. Identify the exact file causing the problem.
3. Change one plugin or one file at a time.
4. Use the smallest safe fix first.
5. Give complete copy/paste code only for the exact file or exact replacement block.
6. Bump version.php for PHP/plugin code changes.
7. Purge caches and test the exact URL.
8. Provide rollback steps for every change.
10. ed2go-style UI/UX specification
Overall visual direction
Black or near-black top player bar.
Fixed or sticky white left sidebar.
Light gray page background.
Centered white content card.
Clean readable typography.
Blue navigation links.
Simple footer.
Minimal Moodle clutter inside the player.
Sidebar requirements
Check
Requirement / action
â˜
Show Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, Final Exam.
â˜
Lesson groups expand and collapse like ed2go.
â˜
Active item is highlighted.
â˜
Current page has a blue left indicator or arrow.
â˜
Completed items show green checkmarks.
â˜
In-progress items show blue dots/circles.
â˜
Locked lessons and locked Final Exam show lock icons.
â˜
Locked items stay visible but muted/disabled.
â˜
Locked items must not look active or completed.
â˜
Long titles wrap cleanly.
â˜
Sidebar scrolls independently.
â˜
Content area must not jump or flicker.
11. Page-specific player requirements
Course Home
Check
Requirement / action
â˜
Show course fullname and shortname/section code.
â˜
Show banner image.
â˜
Show completion circle and score circle.
â˜
Show next incomplete activity.
â˜
Show Continue button.
â˜
Use clean card layout.
Getting Started
Check
Requirement / action
â˜
Include Course Overview, Syllabus, and Navigating this Course inside the same master player shell.
â˜
Do not use nested inner-card layout or duplicate Getting Started heading.
â˜
Show centered page title, action icons, completion status, divider, and Next Up card.
Lessons
Check
Requirement / action
â˜
Use the same shell with sidebar visible.
â˜
Use a centered reading card.
â˜
Show course/lesson/chapter/page title.
â˜
Top-left back/bookmark icons and top-right print/fullscreen icons.
â˜
Responsive images and readable headings/paragraphs.
â˜
Normal scrolling.
â˜
Support learning checks, assignments, discussions, quizzes, resources, reviews, and Next Up flow.
Scores
Check
Requirement / action
â˜
Use ed2go-style list/card rows.
â˜
Show toolbar/search/filter if available.
â˜
Show grade/status on the right.
â˜
Mute locked items.
â˜
Show download button if available.
Discussions
Check
Requirement / action
â˜
Use ed2go-style rows/cards.
â˜
One row per discussion.
â˜
Deduplicate repeated activities.
â˜
Show metadata if available.
â˜
Mute locked discussions.
Pretest / Quiz / Final Exam
HeyDay quiz template reference screenshots
Use these screenshots as the visual standard for the HeyDay quiz, pretest, lesson quiz, and final exam pages inside local_heyday_courseplayer. The layout should preserve Moodle quiz functionality while presenting the learner-facing experience in the same ed2go-style shell.
Keep the black or near-black player bar, fixed white left sidebar, light gray page background, and centered white quiz card.
Show the course title, lesson title, and quiz title clearly at the top of the quiz area.
Use a Show Instructions link near the beginning of the quiz and keep detailed instructions hidden until clicked.
Display question numbers in a clear left badge, with clean separators between questions.
Style multiple-choice options as light rows with compact answer-letter badges and radio controls.
Keep print and fullscreen icons at the top-right of the quiz card.
When scrolling, preserve a compact sticky quiz header so the learner always knows the current quiz context.
Place Save and Close and Submit Answers at the bottom-right of the quiz; Submit Answers must remain the primary action.
Show a Next Up card after the quiz actions without hiding Moodle submission behavior.
Do not duplicate lesson, resource, or final exam links in the sidebar; any repeated item visible in a screenshot must be treated as a QA issue to prevent in Moodle.
Figure HQ-1. HeyDay quiz template - top of quiz page with sidebar, Show Instructions link, question rows, and player action icons.
Figure HQ-2. HeyDay quiz template - mid-quiz scrolling state with compact sticky quiz header and active quiz item in the sidebar.
Figure HQ-3. HeyDay quiz template - bottom of quiz page with Save and Close, Submit Answers, and Next Up card. Also use this image to check and prevent duplicate sidebar items.
Implementation note: the screenshots are reference images only. In Moodle, the quiz/pretest/final exam page must still use Moodle availability restrictions, attempt rules, question behavior, Save and Close, and Submit Answers functionality. The player shell should style the page but must not bypass Moodle quiz logic.
Check
Requirement / action
â˜
Use ed2go-like cards.
â˜
Use clean question separators and subtle hover effect.
â˜
Keep Save and Close / Submit Answers aligned correctly.
â˜
Hide instructions until clicked if that behavior is part of the design.
â˜
Preserve Moodle quiz/exam functionality and Moodle 5.2+ compatibility.
â˜
Final Exam appears after Resources.
Resources
Check
Requirement / action
â˜
Appear after Lessons and before Final Exam.
â˜
Use organized cards or rows.
â˜
Support files, folders, links, H5P references, transcripts, PDFs, and supporting materials.
12. Sidebar behavior, release dates, H5P and locking
Release-date rules
Preserve Moodle availability restrictions.
Use server-rendered release-date text for locked lessons/exams.
Do not make unavailable items clickable unless specifically requested.
Locked item styling should be muted and visually distinct from active/completed items.
Example release messages:
Lesson 9: Cryptocurrencies will be available on Jun 10, 2026 10:00 AM GMT+3
Final Exam will be available on Jun 19, 2026 10:00 AM GMT+3
H5P display rule
When possible, H5P activities should display inside the ed2go-style player card instead of only showing a fallback Open Activity button. The fallback button remains acceptable when Moodle activity rendering, permissions, or plugin context prevents safe embedding.
Check
Requirement / action
â˜
Detect H5P activity module safely from Moodle modinfo/activity metadata.
â˜
Render inside the player card only when Moodle permissions and activity display are available.
â˜
Keep fallback Open Activity button for unsupported or unsafe cases.
â˜
Do not bypass Moodle completion tracking or H5P attempt data.
â˜
Keep H5P container responsive inside the centered content card.
13. Adaptable configuration guidelines
Configure Adaptable for the surrounding site shell, not for the internal player logic.
Area
Recommended direction
Branding
Use Heyday logo, favicon, site title, consistent footer, and learner-facing support text.
Header
Clean, minimal header. Avoid duplicate search/help/tour elements in the player.
Colors
Near-black top bar, white cards, light gray page background, blue links/buttons, red Enroll Now CTA for public catalog if used.
Typography
Readable font sizes, strong headings, sufficient line height.
Page width
Centered content, not too wide for reading.
Public catalog
Use Adaptable blocks/regions for landing, browse catalog, featured/popular/new courses, and course detail pages.
Course player
Let local_heyday_courseplayer own the sticky sidebar and player card.
14. Content creation manual and naming map
Recommended Moodle content build order
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
11. Test Moodle normal course page first, then test player URL.
12. Only then expose course publicly or mark Public Visible = Yes.
Naming map inside Moodle
Moodle object
Naming convention
Example
Course shortname
&lt;area&gt;-&lt;number&gt;-&lt;session/code&gt;
smb-10-0426
Section
Lesson 1: &lt;Lesson title&gt;
Lesson 1: Introduction to Digital Marketing
Subsection
&lt;Lesson number&gt;.&lt;part&gt; &lt;Topic&gt;
1.1 Learning Objectives
Page activity
&lt;Lesson number&gt;.&lt;part&gt; &lt;Page title&gt;
1.2 Key Terms
Quiz
Lesson &lt;n&gt; Quiz
Lesson 3 Quiz
Discussion
Lesson &lt;n&gt; Discussion Area
Lesson 4 Discussion Area
Assignment
Lesson &lt;n&gt; Assignment
Lesson 5 Assignment
Resource folder
Lesson &lt;n&gt; Resources for Further Learning
Lesson 6 Resources for Further Learning
Final exam
Final Exam
Final Exam
15. Implementation workflow: ChatGPT, Claude Desktop, VS Code
Tool
Best use
Rules
ChatGPT
Planning, diagnosis from screenshots/files, master documentation, prompts, checklists, safe patch strategy.
Upload screenshots/files; ask for diagnosis and exact fix. Use for documentation and verification logic.
Claude Desktop
Long-context coding plan and project-wide reasoning when pointed at the workspace.
Use the master prompt in this document. Ask Claude to inspect real files before editing.
VS Code coding assistant
Apply file edits in the local Moodle workspace.
Keep workspace opened at C:\xampp\moodle502\moodle. Use instruction files to constrain behavior. Review diffs before saving.
Moodle admin UI
Configure categories, custom fields, completion, theme settings, activities, availability.
Use checklists. Capture screenshots before and after changes.
Windows CMD / PowerShell
Run Moodle CLI cache purge and upgrade commands.
Use the correct non-public admin CLI path.
16. Master prompts
Claude Desktop / VS Code Agent master prompt
You are my Moodle 5.2+ / Adaptable 502.1.1 technical coding assistant for the Short Term Certification Training LMS project.
Goal: build one complete reusable ed2go-style course player template first in C:\xampp\moodle502\moodle\public\local\heyday_courseplayer for component local_heyday_courseplayer. After the master template is stable, make other Moodle + Adaptable learner-facing pages follow the same template safely.
Environment:
- Moodle root: C:\xampp\moodle502\moodle
- Public web root: C:\xampp\moodle502\moodle\public
- Local plugins path: C:\xampp\moodle502\moodle\public\local
- Plugin folder: C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
- Moodle data: C:\moodledata
- Database: moodle_db
- Master course ID: 105
- PHP: C:\xampp\php\php.exe
Important URLs:
- Site: http://localhost/moodle/
- Dashboard: http://localhost/moodle/my/
- Course 105: http://localhost/moodle/course/view.php?id=105
- Master player: http://localhost/moodle/local/heyday_courseplayer/index.php?id=105
- Adaptable settings: http://localhost/moodle/admin/settings.php?section=themesettingadaptable
Rules:
1. Inspect real files before editing. Do not guess file contents.
2. Work one plugin or file at a time.
3. Start with the smallest safe fix.
4. Preserve Moodle core functionality and availability restrictions.
5. Do not edit Moodle core files.
6. Do not edit Adaptable source files unless absolutely required and documented.
7. Scope plugin CSS to body.local-heyday-courseplayer or .local-heyday-courseplayer.
8. Avoid global Additional HTML and broad JavaScript observers.
9. If PHP/plugin code changes, bump version.php.
10. If CSS only changes, purge Moodle caches.
11. Always provide purge/cache and rollback steps.
12. Do not call local_heyday_courseplayer_gettingstarted_definitions before $lessongroups is created and verified as an array.
Desired player:
Home â†’ Scores â†’ Discussions â†’ Getting Started â†’ Pretest â†’ Lessons â†’ Resources â†’ Final Exam, with an ed2go-style black top bar, sticky white sidebar, light gray background, centered white content card, clean typography, blue links, completion checks, in-progress dots, lock icons, release-date text, and Next Up flow.
When updating code, first check: folder name, component name, version.php, lang/en/local_heyday_courseplayer.php, plugin version, CSS scope, and Moodle availability behavior.
Screenshot diagnosis prompt
Compare the uploaded reference screenshot against my current Moodle screenshot for the Short Term Certification Training LMS project. Diagnose layout, spacing, header, sidebar, text size, icons, buttons, active state, completion checks, lock state, release dates, tooltip/message behavior, scrolling, card width, background, and Moodle clutter.
Return:
1. Diagnosis
2. Exact fix
3. Copy/paste code or SQL
4. Where to paste/copy
5. Purge/cache steps
6. How to test
7. Rollback if it fails
Use Moodle 5.2+, Adaptable 502.1.1, and local_heyday_courseplayer rules. Do not guess file contents; ask me to upload the exact file if you need it.
Uploaded-file/code-fix prompt
Inspect the uploaded file(s) first and identify the exact file causing the issue. Give the smallest safe fix. For local_heyday_courseplayer, verify folder, component, version.php, lang/en language file, plugin version number, and CSS scope before changing code.
If changing PHP/plugin code, bump version.php. If CSS-only, purge Moodle caches. Preserve Moodle availability, completion, quiz, forum, assignment, H5P/SCORM, and resource behavior.
Required response format:
1. Diagnosis
2. Exact fix
3. Copy/paste code or SQL
4. Where to paste/copy
5. Purge/cache steps
6. How to test
7. Rollback if it fails
Course-content creation prompt
Help me create the real course content inside Moodle course ID 105 using the master structure: Getting Started, Pretest, Lessons, Resources, and Final Exam. Include sections, subsections, pages, quizzes, assignments, forums, files, folders, H5P/SCORM if available, release dates, completion settings, and names that map cleanly into local_heyday_courseplayer.
Do not make me manually rebuild everything without a plan. Give me the easiest safe method first, then a repeatable naming map, then exact Moodle UI steps or import/template approach. Preserve Moodle availability restrictions and completion tracking.
17. QA checklists and acceptance criteria
Pre-public course checklist
Check
Requirement / action
â˜
Course title entered.
â˜
Course code entered.
â˜
Category and subcategory set.
â˜
Marketing summary entered.
â˜
Duration entered.
â˜
Session start dates entered.
â˜
Delivery mode entered.
â˜
Certificate availability set.
â˜
Thumbnail assigned.
â˜
Hero image assigned or approved.
â˜
Detail page content prepared.
â˜
Course shell structure exists.
â˜
Enrollment method configured.
â˜
Completion tracking enabled.
â˜
Final exam and certificate rules documented.
â˜
Public Visible is not enabled until all required items pass QA.
Catalog QA
Check
Requirement / action
â˜
Only approved public categories appear.
â˜
Only Public Visible courses appear.
â˜
Search returns correct courses.
â˜
Featured block only shows Featured = Yes courses.
â˜
Popular block only shows Popular = Yes courses.
â˜
New block only shows New = Yes courses.
â˜
Course cards show thumbnail, title, summary, duration, dates, Learn More, and Enroll Now.
Course-player QA
Check
Requirement / action
â˜
Master player URL opens for course 105.
â˜
No duplicate Moodle clutter appears inside the player.
â˜
Sidebar sequence is correct.
â˜
Current page state is visible.
â˜
Completed/in-progress/locked states render correctly.
â˜
Release-date messages are server-rendered.
â˜
Getting Started uses the same shell.
â˜
Lessons display inside centered reading card.
â˜
Scores/discussions are deduplicated and styled.
â˜
Pretest/final exam still use Moodle quiz functionality.
â˜
H5P appears inside card when possible and falls back safely when not.
â˜
Final Exam appears after Resources.
Final acceptance criteria
Check
Requirement / action
â˜
Data model is finalized.
â˜
Public category structure works.
â˜
Course cards use real metadata.
â˜
Course detail pages reflect real course structure.
â˜
Enrollment path works.
â˜
My Classroom entry works.
â˜
Progress and completion are testable.
â˜
Visual layout aligns with screenshots and intended flow.
â˜
Course 105 master player is stable before extending template to other learner-facing pages.
18. Troubleshooting and rollback rules
Problem
Likely cause
Safe first check
Player page blank/error
PHP syntax error, plugin function order, missing require, bad capability/context call
Check PHP error log, recent file edit, version.php, and function order.
CSS not updating
Moodle cache or browser cache
Run Moodle purge caches and hard refresh.
Sidebar item not clickable
Moodle availability restriction, wrong cmid, section mapping issue
Check course activity availability and modinfo mapping.
Locked item looks completed
State priority bug in renderer
Ensure locked state overrides active/completed visual styles.
Getting Started not working
$lessongroups not initialized before definitions call
Move collect_lesson_groups call before gettingstarted definitions and check array.
H5P opens only in fallback
Activity cannot be safely embedded in plugin context
Keep fallback button and inspect Moodle activity renderer.
Final Exam missing
Section mapping or sequence issue
Check Resources before Final Exam in Moodle course sections and player sequence.
Rollback standard
Before each code change, save a copy of the original file or rely on version control.
For PHP/plugin changes, restore the previous file and version.php if the change fails.
For CSS-only changes, restore styles.css and purge caches.
For Moodle UI configuration changes, use before/after screenshots and reverse the setting in the admin UI.
Do not continue layering fixes on top of an unverified broken state.
19. Conversation-derived project requirement index
This section consolidates the project conversation history into actionable requirements. It is not a verbatim chat transcript; it is a usable master index of the decisions, issues, prompts, and recurring workstreams discussed in the project.
Conversation/workstream
Master requirement carried forward
Moodle Adaptable Documentation
Create master guideline/checklist/prompt/manual documentation with screenshot references.
Sidebar behavior fix
Update local_heyday_courseplayer sidebar so Moodle sections/subsections behave like ed2go, including collapsible lesson groups and state indicators.
Course automation
Find easier ways to create real lesson structures instead of manually adding every section, page, quiz, assignment, forum, file, and folder.
Prompt refinement
Create concise professional prompts and remove duplicated concepts; focus on one master course-player first.
VS Code / Claude / ChatGPT workflow
Use ChatGPT for diagnosis/planning/docs, Claude for long-context coding, VS Code assistant for local file edits with workspace instructions.
Moodle 5.2 upgrade
Use correct Moodle root C:\xampp\moodle502\moodle, correct CLI path, Adaptable 502.1.1, MariaDB/XAMPP checks, and environment validation.
Adaptable update
Configure Adaptable for ed2go-style learner interface but keep player logic in custom local plugin.
Complete ed2go-like plugin
Unify Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, and Final Exam in one local player.
Local Heyday Lessons plugin history
Move away from fragmented standalone lesson plugins when the master local_heyday_courseplayer can own the full shell.
Moodle upgrade instructions
Avoid wrong public/admin/cli paths; verify version.php location and plugin installation paths.
Learner profile and subsections
Use correct Moodle configuration and plugins/features for subsections/flexsections where needed; map subsections into player sidebar.
Master/Alias courses
Document master and alias courses, especially Technology, Web Technology, Graphic and Multimedia Design, Computer Applications, and Adobe relationships.
Plugin installation errors
Handle PHP compatibility, plugin removal, and setup with safe Moodle admin/CLI steps.
Circular progress badge
Use plugin/theme customization for circular completion badge; avoid unstable global hacks.
Master configuration documentation
Maintain cross-check manuals and checklists for catalog, enrollment, certificates, H5P/SCORM/media, completion, and reviews.
Phase 1 blueprint guide
Use data model first, then public catalog design, then learner-facing player.
Course codes and category IDs
Use professional code and ID naming for categories, subcategories, courses, and aliases.
Course catalog setup
Build public catalog with Moodle/Adaptable cards, course details, custom fields, and learner workflow.
Course catalog images
Use Ethiopian/Habesha representation and original Heyday logo for course-image generation when requested.
Course custom fields
Create public catalog metadata, session/intake, marketing/display, and flags/visibility fields.
Adaptable theme configuration
Configure Adaptable 502.1.1 settings safely for global shell and catalog appearance.
20. Screenshot reference gallery
The uploaded reference DOCX files contained 63 image files. Exact duplicate screenshots were removed, leaving 57 unique embedded reference screenshots. These are included below as the visual design appendix for catalog pages, course detail pages, active-course/report states, flashcards, transcript/video/discussion references, and older blueprint imagery.
Source document
How many extracted images were found
Active Courses / Report screenshots
6
Landing/Home and Course Detail screenshots
53
Phase 1 Reordered Blueprint / Use Case Guide
4
Previous Master Document
Not imported from placeholder master
Embedded screenshot gallery
Figure 01. Active Courses / Report screenshots â€” word/media/image1.png â€” 947Ã—620
Figure 02. Active Courses / Report screenshots â€” word/media/image2.png â€” 880Ã—651
Figure 03. Active Courses / Report screenshots â€” word/media/image3.png â€” 890Ã—688
Figure 04. Active Courses / Report screenshots â€” word/media/image4.png â€” 877Ã—632
Figure 05. Active Courses / Report screenshots â€” word/media/image5.png â€” 362Ã—244
Figure 06. Active Courses / Report screenshots â€” word/media/image6.png â€” 918Ã—946
Figure 07. Landing/Home and Course Detail screenshots â€” word/media/image1.png â€” 183Ã—115
Figure 08. Landing/Home and Course Detail screenshots â€” word/media/image10.png â€” 1577Ã—1034
Figure 09. Landing/Home and Course Detail screenshots â€” word/media/image11.png â€” 1581Ã—1032
Figure 10. Landing/Home and Course Detail screenshots â€” word/media/image12.png â€” 1912Ã—958
Figure 11. Landing/Home and Course Detail screenshots â€” word/media/image13.png â€” 1914Ã—921
Figure 12. Landing/Home and Course Detail screenshots â€” word/media/image14.png â€” 1914Ã—960
Figure 13. Landing/Home and Course Detail screenshots â€” word/media/image15.png â€” 1911Ã—961
Figure 14. Landing/Home and Course Detail screenshots â€” word/media/image16.png â€” 1912Ã—962
Figure 15. Landing/Home and Course Detail screenshots â€” word/media/image17.png â€” 1903Ã—868
Figure 16. Landing/Home and Course Detail screenshots â€” word/media/image18.png â€” 1916Ã—960
Figure 17. Landing/Home and Course Detail screenshots â€” word/media/image19.png â€” 1913Ã—959
Figure 18. Landing/Home and Course Detail screenshots â€” word/media/image2.png â€” 1900Ã—960
Figure 19. Landing/Home and Course Detail screenshots â€” word/media/image20.png â€” 1919Ã—961
Figure 20. Landing/Home and Course Detail screenshots â€” word/media/image21.png â€” 1912Ã—965
Figure 21. Landing/Home and Course Detail screenshots â€” word/media/image22.png â€” 1914Ã—964
Figure 22. Landing/Home and Course Detail screenshots â€” word/media/image23.png â€” 1906Ã—952
Figure 23. Landing/Home and Course Detail screenshots â€” word/media/image24.png â€” 1911Ã—960
Figure 24. Landing/Home and Course Detail screenshots â€” word/media/image25.png â€” 1915Ã—963
Figure 25. Landing/Home and Course Detail screenshots â€” word/media/image26.png â€” 1574Ã—1065
Figure 26. Landing/Home and Course Detail screenshots â€” word/media/image27.png â€” 1586Ã—1069
Figure 27. Landing/Home and Course Detail screenshots â€” word/media/image28.png â€” 1575Ã—1055
Figure 28. Landing/Home and Course Detail screenshots â€” word/media/image29.png â€” 1571Ã—1034
Figure 29. Landing/Home and Course Detail screenshots â€” word/media/image30.png â€” 1576Ã—1026
Figure 30. Landing/Home and Course Detail screenshots â€” word/media/image31.png â€” 1578Ã—1049
Figure 31. Landing/Home and Course Detail screenshots â€” word/media/image32.png â€” 1564Ã—1064
Figure 32. Landing/Home and Course Detail screenshots â€” word/media/image33.png â€” 1569Ã—1063
Figure 33. Landing/Home and Course Detail screenshots â€” word/media/image34.png â€” 1578Ã—1052
Figure 34. Landing/Home and Course Detail screenshots â€” word/media/image35.png â€” 1578Ã—1049
Figure 35. Landing/Home and Course Detail screenshots â€” word/media/image36.png â€” 1568Ã—1047
Figure 36. Landing/Home and Course Detail screenshots â€” word/media/image37.png â€” 1568Ã—1061
Figure 37. Landing/Home and Course Detail screenshots â€” word/media/image38.png â€” 1573Ã—1027
Figure 38. Landing/Home and Course Detail screenshots â€” word/media/image39.png â€” 1920Ã—1080
Figure 39. Landing/Home and Course Detail screenshots â€” word/media/image4.png â€” 1900Ã—929
Figure 40. Landing/Home and Course Detail screenshots â€” word/media/image40.png â€” 1683Ã—947
Figure 41. Landing/Home and Course Detail screenshots â€” word/media/image41.png â€” 1683Ã—943
Figure 42. Landing/Home and Course Detail screenshots â€” word/media/image42.png â€” 747Ã—524
Figure 43. Landing/Home and Course Detail screenshots â€” word/media/image43.png â€” 1907Ã—946
Figure 44. Landing/Home and Course Detail screenshots â€” word/media/image44.png â€” 1899Ã—954
Figure 45. Landing/Home and Course Detail screenshots â€” word/media/image45.png â€” 1910Ã—908
Figure 46. Landing/Home and Course Detail screenshots â€” word/media/image46.png â€” 1920Ã—1080
Figure 47. Landing/Home and Course Detail screenshots â€” word/media/image47.png â€” 1906Ã—961
Figure 48. Landing/Home and Course Detail screenshots â€” word/media/image48.png â€” 1920Ã—1080
Figure 49. Landing/Home and Course Detail screenshots â€” word/media/image49.png â€” 1920Ã—1080
Figure 50. Landing/Home and Course Detail screenshots â€” word/media/image50.png â€” 1914Ã—958
Figure 51. Landing/Home and Course Detail screenshots â€” word/media/image51.png â€” 1916Ã—955
Figure 52. Landing/Home and Course Detail screenshots â€” word/media/image52.png â€” 1906Ã—959
Figure 53. Landing/Home and Course Detail screenshots â€” word/media/image53.png â€” 1913Ã—963
Figure 54. Landing/Home and Course Detail screenshots â€” word/media/image6.png â€” 1572Ã—1054
Figure 55. Landing/Home and Course Detail screenshots â€” word/media/image7.png â€” 1916Ã—927
Figure 56. Landing/Home and Course Detail screenshots â€” word/media/image8.png â€” 1577Ã—1052
Figure 57. Landing/Home and Course Detail screenshots â€” word/media/image9.png â€” 1052Ã—579
Appendix A. Uploaded source document audit
Source label
Filename
Size
Active Courses / Report screenshots
Active Courses screenshots when I couldn't log in the active courses.docx
349,461 bytes
Landing/Home and Course Detail screenshots
Landing or Home pages and course detail pages screenshots for moodle.docx
13,734,044 bytes
Phase 1 Admin Configuration Worksheet
Phase1_Admin_Configuration_Worksheet_Moodle_Adaptable.docx
43,282 bytes
Phase 1 Reordered Blueprint / Use Case Guide
use_case_and_configuration_guide_formatted.docx
1,143,926 bytes
Previous Master Document
Moodle_Adaptable_Master_Guidelines_Checklist_Prompt_Manual.docx
2,166,780 bytes
Primary reference basis
Phase 1 Reordered Blueprint: data model first, category/course/session/learner/enrollment/learning path/lesson/assessment/completion relationships, and public catalog page mapping.
Admin Configuration Worksheet: site readiness, category setup, course custom fields, learner profile fields, course shell, completion, homepage, course detail, My Classroom, QA, and sign-off checklists.
Landing/Home and Course Detail screenshots: public catalog visual layout, course cards, course detail page, course outline, flashcards, videos, transcripts, discussion, continue button, and upcoming lesson dates.
Active Courses screenshots: active course/report issues, master/alias course relationships, and report-button behavior references.
Previous master document: initial consolidated master structure and screenshot placeholder concept, now replaced by this updated version.
Latest screenshot addition: HeyDay quiz template
The document now includes the three HeyDay quiz reference screenshots under Section 11, Pretest / Quiz / Final Exam. Use them when building or QA-checking local_heyday_courseplayer quiz, pretest, and final exam pages.
Appendix B. HeyDay quiz attempt/results template and Claude Code / VS Code guide
Purpose.
This appendix updates the master Moodle + Adaptable project guide with the new ed2go-style Lesson 1 Quiz attempt/review screenshots. It should be used as the reference for styling Moodle quiz attempt review pages, quiz result pages, pretest review pages, and final exam review pages inside the reusable HeyDay course-player shell.
Important scope rule.
This is a visual and layout template only. Moodle quiz engine behavior, attempt records, grading, question behaviour, review options, Save/Submit controls, retake limits, availability restrictions, and completion rules must remain controlled by Moodle core. Do not rewrite Moodle quiz logic in the theme or with broad JavaScript.
B1. Quiz attempt/review page requirements
Area
Requirement
Player shell
Use the same ed2go-style shell as local_heyday_courseplayer: black/near-black top player bar, sticky white left sidebar, light gray page background, centered white quiz card, simple footer, and no duplicate Moodle clutter.
Sticky review header
When the learner scrolls, keep the compact title area visible: course or lesson name, activity title such as Lesson 1 Quiz, and right-side print/fullscreen icons if available.
Attempt summary
Show the attempt/review strip with Instructions, Attempt number, timestamp, score badge, and retake action. The retake action must follow Moodle attempt permissions and remaining-attempt rules.
Score ring
Show a large centered percentage ring after the attempt is reviewed. Use red/green score segments and a plain text summary such as 1 correct out of 6 questions.
Question feedback
Separate questions with dotted dividers. Use number pills, correct/incorrect icons, selected answer highlighting, and visible feedback boxes. Wrong selected answers are red; correct answers are blue/green depending on review mode.
Answer rows
Maintain clean wide answer rows with left answer-letter chips, radio indicators, readable blue answer text, and enough vertical spacing for long answers.
Sidebar state
Keep Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, and Final Exam visible. The active quiz item gets a blue left indicator; completed items show green checks; in-progress lessons show blue dots; locked items remain muted.
Next Up
At the bottom of the quiz review page, show the Next Up card to the next incomplete or next sequence item, for example Resources for Further Learning.
De-duplication
Do not duplicate lesson/resources/final-exam entries in the sidebar. Collect Moodle course modules once, key by course module id, and render each logical item once.
B2. QA checklist for HeyDay quiz attempt/review template
The quiz review page opens inside the course-player shell, not as an unstyled Moodle page.
The left sidebar scrolls independently and does not jump while the main quiz page scrolls.
The active Lesson 1 Quiz item remains visibly active using the blue left marker/arrow.
Completed lesson pages and resources show green checks only when Moodle completion says complete.
In-progress lessons show blue dots/circles and do not look completed.
Locked lessons and the locked Final Exam stay visible but muted and not clickable unless Moodle allows access.
Attempt #, attempt timestamp, score percentage, retake action, and remaining attempt text match Moodle data.
Question feedback uses red styling for incorrect selected answers and green/blue styling for correct answers.
Feedback text boxes are readable and do not overlap answer rows.
Save, Close, Submit, Retake, Next Up, print, and fullscreen controls preserve Moodle functionality.
No broad CSS affects admin pages, normal course pages, dashboard, or Adaptable settings pages.
B3. Lesson 1 Quiz attempt/review screenshot reference
Figure B.1: Screenshot 1 - attempt review summary, instructions panel, score ring, and first question feedback.
Figure B.2: Screenshot 2 - correct/incorrect answer styling with red, blue, and green feedback rows.
Figure B.3: Screenshot 3 - mid-quiz review state with sticky title bar, print/fullscreen tools, and left sidebar state.
Figure B.4: Screenshot 4 - lower review state showing multiple feedback messages and selected/correct answer contrast.
Figure B.5: Screenshot 5 - end of attempt review with Next Up card and footer controls.
B4. Claude Code / VS Code implementation guide
Use this section when asking Claude Code in VS Code to implement or refine the Moodle quiz attempt/review styling.
The agent must inspect the real plugin files first and then make the smallest safe change. It must not edit Moodle core or Adaptable source files.
Open the workspace at C:\xampp\moodle502\moodle.
Confirm the Moodle public plugin path is C:\xampp\moodle502\moodle\public\local\heyday_courseplayer.
Inspect index.php, view.php, settings.php, styles.css, version.php, and lang\en\local_heyday_courseplayer.php before changing code.
Confirm the plugin folder is heyday_courseplayer and component is local_heyday_courseplayer.
Find the quiz/pretest/final-exam rendering path and identify whether the page is handled inside the player shell or linked as a fallback Moodle activity.
Apply visual changes only inside body.local-heyday-courseplayer or .local-heyday-courseplayer scoped CSS.
Preserve Moodle availability, attempt, grading, review, completion, and submit behavior.
If PHP changes are made, bump version.php and run Moodle upgrade. If CSS-only changes are made, purge caches only.
CLAUDE CODE / VS CODE PROMPT - HEYDAY QUIZ ATTEMPT REVIEW TEMPLATE
You are working on my Moodle 5.2+ / Adaptable 502.1.1 project for the Short Term Certification Training LMS.
Workspace:
C:\xampp\moodle502\moodle
Target plugin:
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
Component: local_heyday_courseplayer
Main course template ID: 105
Master player URL: http://localhost/moodle/local/heyday_courseplayer/index.php?id=105
Task:
Update the quiz, pretest, and final exam attempt/review display inside local_heyday_courseplayer so it matches the ed2go-style HeyDay quiz attempt screenshots in the master documentation.
Before changing code:
1. Inspect folder name, component name, version.php, lang/en/local_heyday_courseplayer.php, index.php, view.php, settings.php, and styles.css.
2. Identify the exact file and rendering branch that controls quiz/pretest/final-exam display.
3. Confirm whether the current page is a Moodle quiz attempt page embedded inside the player shell or a fallback Open Activity button.
4. Do not edit Moodle core files.
5. Do not edit Adaptable source files unless there is no safer plugin-level option.
Required visual behavior:
- Same player shell and sidebar as the course player.
- Sticky white left sidebar with active Lesson Quiz item highlighted.
- Completed items have green checks; in-progress lessons have blue dots; locked items are muted and not active.
- Centered white quiz/review card on light gray background.
- Compact title header with lesson name and quiz title.
- Attempt summary bar showing Instructions, Attempt #, timestamp, percentage badge, retake action, and remaining attempts when Moodle allows it.
- Large score/progress ring after attempt review.
- Question rows separated by dotted dividers.
- Wrong selected answers styled red with red feedback boxes.
- Correct answers styled blue/green with readable feedback boxes.
- Save and Close / Submit Answers / Retake / Next Up buttons must preserve Moodle behavior.
- No duplicated sidebar lesson/resources/final-exam items.
- No broad JavaScript observer that rebuilds menus or causes flickering.
CSS scope rule:
Only use body.local-heyday-courseplayer or .local-heyday-courseplayer selectors.
Completion and availability rule:
Preserve Moodle completion tracking and availability restrictions. Do not make unavailable items clickable. Show server-rendered release-date text for locked lessons and Final Exam.
Output required:
1. Diagnosis: identify the exact file causing the current issue.
2. Smallest safe fix first.
3. Complete copy/paste code only for the exact file or block changed.
4. Version.php bump if PHP changed.
5. Purge/upgrade commands.
6. How to test with course ID 105.
7. Rollback steps.
B5. Commands after implementation
CSS-only change:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
PHP/plugin change:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
Test URL:
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105

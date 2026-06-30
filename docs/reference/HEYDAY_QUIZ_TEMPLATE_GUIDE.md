HEYDAY COURSES
HeyDay Quiz Attempt/Results Template and Claude Code / VS Code Guide
Separate implementation reference for Moodle 5.2+ / Adaptable 502.1.1 / local_heyday_courseplayer
Item
Current Project Standard
Document purpose
A separate, reusable reference for styling Moodle quiz attempt review and result screens like the uploaded HeyDay / ed2go-style screenshots.
Primary target plugin
local_heyday_courseplayer
Moodle target
Moodle 5.2+ build reference 20260525
Theme target
Adaptable 502.1.1 / version reference 2026041201
Master course template
Course ID 105
Local plugin path
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
Main player URL
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105
Document status
Use this as a separate add-on to the master Moodle + Adaptable project documentation.
Important: Use the screenshots as visual reference only. Preserve Moodle quiz functionality and do not copy or hard-code third-party course content. The implementation should restyle Moodle output using Moodle data and safe CSS/PHP wrappers.
Document map
1.
Reference source summary
2.
Target visual behavior
3.
Screenshot gallery
4.
Moodle implementation scope
5.
CSS and template rules
6.
Claude Code / VS Code guide
7.
Copy/paste Claude Code prompt
8.
QA checklist
9.
Purge/cache and rollback steps
1. Reference source summary
The uploaded PDF and screenshots show a Lesson 1 Quiz review/result state for Introduction to Artificial Intelligence. The visible reference state includes a course section label, a centered lesson quiz title, a left player sidebar, attempt metadata, a score gauge, colored correct/incorrect answer rows, feedback panels, a Retake Assessment row, and a Next Up card at the bottom.
Course reference: Introduction to Artificial Intelligence.
Lesson reference: Lesson 1: Introduction to Artificial Intelligence.
Quiz reference: Lesson 1 Quiz.
Attempt reference: Attempt #1, June 27, 2026 7:12 PM.
Score reference: 16.7% Correct, 1 correct out of 6 questions.
Result visual language: red for incorrect selected responses, blue for correct answers in review, green for correct selected responses, pale feedback panels under answers.
2. Target visual behavior
Check
Requirement
Pass condition
[ ]
Left sidebar remains visible and independently scrollable.
The sidebar does not jump when the quiz result page scrolls.
[ ]
Active item uses blue left arrow/indicator.
Lesson 1 Quiz is visibly active and not confused with completed items.
[ ]
Completed items show green checkmarks.
Completed lesson/chapter/resources items show checkmarks only when Moodle completion says complete.
[ ]
In-progress or locked lessons remain visible.
Future lessons stay muted/locked/in-progress according to Moodle availability rules.
[ ]
Top quiz header is centered and clean.
Course title, lesson title, and Lesson 1 Quiz title remain readable.
[ ]
Attempt/result card shows attempt metadata.
Attempt number, date/time, and score badge display inside a blue bar.
[ ]
Retake Assessment row respects Moodle attempt rules.
Show only when Moodle allows another attempt; label remaining attempts correctly.
[ ]
Score gauge is large and centered.
Correct percentage and correct-count text are easy to read.
[ ]
Question result rows are color-coded.
Incorrect selected answers are red; correct answers are blue/green depending on selected/correct state.
[ ]
Feedback panels appear below answers.
Incorrect/correct feedback appears in a pale message box below the relevant answer.
[ ]
Next Up card remains at bottom.
Next Up points to Resources for Further Learning or the next Moodle activity.
3. Screenshot gallery - HeyDay quiz attempt/results template
These five screenshots are the visual target for quiz attempt/results review screens. Use them to style Moodle quiz review output inside the course-player shell.
Screenshot 1 - Results overview and score gauge
Shows the fixed/sticky player shell, left sidebar, Lesson 1 Quiz title, Instructions row, attempt metadata, Retake Assessment row, 16.7% score gauge, and the first question review block.
Screenshot 2 - Incorrect and correct feedback together
Shows a red incorrect selected answer, a blue correct answer, a pale blue correct-answer feedback panel, and the next question with a green correct selected answer.
Screenshot 3 - Multi-question review state
Shows question 3 and question 4 with the same answer-row pattern, active sidebar state, and print/fullscreen icons in the top-right.
Screenshot 4 - Lower result review with mixed feedback
Shows question 5 and question 6 with red incorrect selected answers, blue correct answer rows, and inline feedback panels.
Screenshot 5 - Bottom of quiz review and Next Up flow
Shows the bottom of the result card, the Next Up button, the next activity label, footer links, and the player sidebar still fixed on the left.
4. Moodle implementation scope
The quiz result/review template should be implemented as part of the ed2go-style course player experience. The safest default is to keep Moodle quiz as the source of truth and style the output inside local_heyday_courseplayer rather than replacing Moodle quiz internals.
Item
Current Project Standard
Preferred shell owner
local_heyday_courseplayer
Core Moodle quiz owner
mod_quiz and question engine must remain unchanged
Allowed approach
Wrap/link/embed Moodle quiz attempt/review pages inside the course player when safe; style with scoped CSS and minimal JS only if needed.
Avoid
Do not edit Moodle core files, question engine files, Adaptable source files, or global Additional HTML for this page.
When PHP changes
Bump version.php.
When CSS-only changes
Purge Moodle caches; no plugin upgrade required.
4.1 Files to inspect before changing code
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\version.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\index.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\view.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\styles.css
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\settings.php
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\lang\en\local_heyday_courseplayer.php
4.2 Required pre-change plugin checks
Confirm folder name: heyday_courseplayer.
Confirm component name: local_heyday_courseplayer.
Confirm version.php uses the correct component and a higher plugin version when PHP changes.
Confirm the language file is in lang/en/local_heyday_courseplayer.php.
Confirm CSS is scoped to body.local-heyday-courseplayer or .local-heyday-courseplayer.
Confirm no broad CSS affects normal Moodle, admin pages, dashboard, or Adaptable settings pages.
5. CSS and template rules
Use these rules when implementing the quiz attempt/result template.
Check
Requirement
Pass condition
[ ]
Scope CSS
All selectors begin with body.local-heyday-courseplayer or .local-heyday-courseplayer.
[ ]
No core edits
No files under mod/quiz, question/, lib/, or theme/adaptable are modified.
[ ]
Preserve forms
Retake, review, submit, close, and navigation URLs remain Moodle-generated.
[ ]
Respect availability
Unavailable/locked lessons and exams remain unclickable unless Moodle says available.
[ ]
No duplicate sidebar items
Each activity appears once in the sidebar and Resources/Final Exam do not duplicate under lesson groups.
[ ]
No flicker
Avoid broad MutationObserver scripts that rebuild the sidebar after page load.
[ ]
Responsive layout
Small screens collapse sidebar safely and keep quiz content readable.
[ ]
Keyboard safe
Quiz answer rows and links remain keyboard accessible.
5.1 Visual design tokens
Item
Current Project Standard
Top shell
Near-black bar for global Moodle shell; clean white quiz/player surface.
Primary blue
#0073A6 to #0B79A5 range for links, active state, correct answer review rows, and Next Up.
Incorrect red
#B83333 to #C0392B range for incorrect selected answers.
Correct green
#3D7F1F to #4A8B2C range for correct selected answers and checkmarks.
Feedback panels
Pale red for incorrect feedback and pale blue/green for correct feedback.
Question separators
Thin dotted or light-gray horizontal dividers.
Content width
Centered white content card with readable max width and generous spacing.
6. Claude Code / VS Code guide
Use this workflow when asking Claude Code in VS Code to implement the quiz attempt/results template.
1.
Open VS Code at C:\xampp\moodle502\moodle or at the plugin folder only when you want a narrow task.
2.
Put the master project instruction in CLAUDE.md and keep this separate quiz document as a reference attachment or repository doc.
3.
Ask Claude Code to inspect the current plugin files before proposing changes.
4.
Limit the first task to quiz attempt/review/result pages only; do not ask it to rebuild Home, Scores, Discussions, and Lessons at the same time.
5.
Require a file-by-file plan before code changes.
6.
Require complete replacement blocks only for the exact file being changed.
7.
After changes, run Moodle purge caches or plugin upgrade as appropriate.
8.
Test as admin and as the test student role if possible.
6.1 Suggested repository documentation placement
Item
Current Project Standard
Primary Claude file
C:\xampp\moodle502\moodle\CLAUDE.md
Optional plugin guide
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer\HEYDAY_QUIZ_TEMPLATE_GUIDE.md
Optional VS Code instructions
C:\xampp\moodle502\moodle\.github\copilot-instructions.md
Reference screenshots folder
C:\xampp\moodle502\moodle\docs\heyday-reference\quiz-attempt-results\
6.2 Commands
Correct Moodle CLI path:
C:\xampp\moodle502\moodle\admin\cli\
Purge caches:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
Plugin upgrade after PHP/version.php changes:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
Do not use:
C:\xampp\moodle502\moodle\public\admin\cli
7. Copy/paste Claude Code prompt
Paste this prompt into Claude Code after opening the workspace. Attach or reference this DOCX/screenshots if your Claude Code environment supports attachments.
You are working on my Short Term Certification Training LMS Moodle project.
Environment:
- Moodle: 5.2+ build reference 20260525
- Theme: Adaptable 502.1.1 / version reference 2026041201
- Server: XAMPP on Windows
- Moodle root: C:\xampp\moodle502\moodle
- Public web root: C:\xampp\moodle502\moodle\public
- Local plugin path: C:\xampp\moodle502\moodle\public\local
- Main course template ID: 105
- PHP: C:\xampp\php\php.exe
Target plugin:
- Folder: C:\xampp\moodle502\moodle\public\local\heyday_courseplayer
- Component: local_heyday_courseplayer
- Main player URL: http://localhost/moodle/local/heyday_courseplayer/index.php?id=105
Task:
Implement the HeyDay / ed2go-style Lesson 1 Quiz attempt/results template inside local_heyday_courseplayer. Use the attached quiz attempt/result screenshots as the visual target. The result screen must show the player sidebar, centered quiz review card, attempt metadata row, score gauge, retake assessment row when Moodle allows it, red incorrect answer rows, blue/green correct answer rows, feedback panels, and bottom Next Up card.
Safety rules:
1. Inspect the existing plugin files first. Do not guess file contents.
2. Confirm folder name, component name, version.php, language file path, and CSS scope before editing.
3. Do not edit Moodle core files.
4. Do not edit Adaptable source files unless explicitly required.
5. Preserve Moodle quiz functionality, attempt rules, review permissions, Retake Assessment behavior, Submit/Close behavior, grades, completion, and availability restrictions.
6. Do not make unavailable items clickable.
7. Keep CSS scoped to body.local-heyday-courseplayer or .local-heyday-courseplayer.
8. Avoid global Additional HTML and avoid broad JavaScript observers that rebuild menus or cause flicker.
9. If PHP changes, bump version.php. If CSS-only changes, purge caches only.
10. Give the smallest safe fix first and work one file at a time.
Required visual behavior:
- Left sidebar fixed/sticky, white, independently scrollable.
- Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, Final Exam remain in the correct sequence.
- Active quiz item has blue left indicator/arrow.
- Completed items show green checkmarks.
- In-progress items show blue circles/dots.
- Locked lessons and locked Final Exam show lock icons and release-date text when applicable.
- Quiz review title is centered: course title, lesson title, Lesson 1 Quiz.
- Attempt bar shows attempt number, date/time, and score badge.
- Retake Assessment row appears only when allowed by Moodle.
- Score gauge and correct-count text are centered.
- Incorrect selected answers use red row and pale red feedback panel.
- Correct answers use blue row, or green row when selected correctly, with pale feedback panel.
- Bottom Next Up card points to the next Moodle activity.
Deliverable:
Give me a file-by-file implementation plan first. Then provide exact copy/paste code only for the file/block needed. Include purge/cache steps, test steps, and rollback steps.
8. QA checklist
Check
Requirement
Pass condition
[ ]
Admin view
Admin can open the quiz attempt/result screen without errors.
[ ]
Student view
Test student sees only allowed review information based on Moodle quiz settings.
[ ]
Attempt metadata
Attempt number, time, and score appear if Moodle provides them.
[ ]
Retake row
Retake Assessment appears only when additional attempts are allowed.
[ ]
Feedback visibility
Feedback respects Moodle review options and does not reveal answers when Moodle hides them.
[ ]
Completion
Activity/course completion logic is unchanged.
[ ]
Sidebar deduplication
No duplicated lessons/resources/final exam items appear.
[ ]
Release dates
Locked lessons/exams show server-rendered release-date text and remain disabled.
[ ]
Next Up
Next Up uses the real next incomplete/next activity, not a hard-coded link.
[ ]
Mobile/responsive
Quiz rows wrap cleanly and sidebar behavior remains usable.
[ ]
No Moodle clutter
No duplicate Help, Tour, Search, breadcrumbs, course index, blocks, or drawer clutter appears inside the player.
9. Purge/cache and rollback steps
9.1 Purge/cache
For CSS-only changes, purge caches:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
For PHP/template changes, bump version.php and run upgrade, then purge caches if needed:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
9.2 How to test
1.
Open http://localhost/moodle/local/heyday_courseplayer/index.php?id=105.
2.
Open a lesson quiz attempt or review page from the left sidebar.
3.
Complete a quiz attempt as a test student if needed.
4.
Review the result page and compare it to the five screenshots in this document.
5.
Check that Retake Assessment and review feedback follow Moodle quiz settings.
6.
Check that Next Up points to Resources for Further Learning or the next activity, not a hard-coded page.
9.3 Rollback if it fails
Restore the previous copy of the changed PHP/CSS file from your backup or Git.
If version.php was bumped, keep the newer version but revert the broken functional code, then run upgrade if Moodle requires it.
Purge caches after rollback.
Test the original Moodle quiz page to confirm core quiz behavior still works.
Appendix A. Screenshot-to-requirement mapping
Check
Requirement
Pass condition
Screenshot 1
Result summary and gauge
Attempt bar, score badge, retake row, large score circle.
Screenshot 2
Answer feedback states
Red incorrect answer, blue correct answer, green correct selected answer.
Screenshot 3
Sticky shell and multi-question scroll
Left sidebar remains fixed while content scrolls.
Screenshot 4
Long feedback rows
Feedback panels stay aligned under the answer option.
Screenshot 5
Bottom Next Up flow
Next Up card and footer appear after final question.
Appendix B. Source basis note
This guide was generated from the user-provided HeyDay / ed2go-style screenshots and the uploaded Lesson 1 Quiz PDF. The PDF confirms the sample quiz context and result state: Introduction to Artificial Intelligence, Lesson 1 Quiz, 16.7% Correct, and 1 correct out of 6 questions. Do not hard-code this sample content into Moodle; use Moodle quiz data dynamically.

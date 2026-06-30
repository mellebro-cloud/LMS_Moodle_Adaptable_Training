# Layer 2: Course Player / Inside Selected Course Architecture

## Project Context

This project is the **Short Term Certification Training LMS** built on:

* Moodle 5.2+
* Adaptable 502.1.1
* XAMPP on Windows
* Moodle root: `C:\xampp\moodle502\moodle`
* Public web root: `C:\xampp\moodle502\moodle\public`
* Main course template ID: `105`
* Master courseplayer plugin: `local_heyday_courseplayer`

Layer 2 means the learner experience **after a student enters a selected course**.

The main Layer 2 URL is:

`http://localhost/moodle/local/heyday_courseplayer/index.php?id=105`

The main plugin path is:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`

The component name is:

`local_heyday_courseplayer`

---

## Main Architecture Rule

`local_heyday_courseplayer` is the **only full learner shell** for the selected-course experience.

There must be:

* one courseplayer shell
* one sticky left sidebar
* one route system
* one completion system
* one lock/release-date system
* one Next Up system
* one quiz bridge
* one discussion bridge
* one Final Exam / Next Steps flow
* one scoped CSS system

Do not build separate full learner shells in:

* `local_heyday_coursehome`
* `local_heyday_scores`
* `local_heyday_discussions`
* `local_heyday_gettingstarted`
* `local_heyday_pretest`
* `local_heyday_lessons`
* `local_heyday_quiz`
* `local_heyday_quizskin`

These plugins may remain as helpers, renderers, bridges, utilities, or legacy redirects, but they must not duplicate the player shell, sidebar, top bar, footer, Next Up logic, completion logic, quiz skin, or discussion shell.

Final rule:

`local_heyday_courseplayer` = one selected-course ed2go-style learner shell
Moodle core = real learning engine and source of truth
Adaptable = global Moodle theme only
Other HeyDay plugins = helpers, bridges, renderers, utilities, or redirects

---

## Moodle Core Responsibility

Moodle core remains responsible for:

* course structure
* sections
* subsections
* activities
* pages
* quizzes
* forums
* assignments
* files
* folders
* URLs
* H5P activities
* completion tracking
* availability restrictions
* release dates
* gradebook
* quiz attempts
* quiz saving/submission/review
* forum posts/replies/permissions
* assignment submissions
* user permissions
* group restrictions
* certificates if Moodle certificate plugins are used

The courseplayer may style, wrap, and route Moodle content, but it must not break Moodle's real activity behavior.

Do not edit Moodle core files.

Do not edit Adaptable source files unless absolutely required.

---

## Adaptable Theme Responsibility

Adaptable is responsible only for the global Moodle shell:

* site header
* logo
* global fonts
* global colors
* site footer
* general Moodle appearance

Adaptable must not rebuild:

* the courseplayer
* lesson player
* selected-course sidebar
* quiz player
* discussion player
* Next Up system
* Final Exam flow

---

## Layer 2 Learner Sequence

The selected-course learner sequence is:

Home
→ Scores
→ Discussions
→ Learning Objectives
→ Getting Started
→ Pretest
→ Lesson 1
→ Lesson 2
→ Lesson 3
→ ...
→ Resources
→ Final Exam
→ Next Steps for Completion

For course ID `105`, all Layer 2 routes should stay inside:

`/local/heyday_courseplayer/index.php?id=105`

---

## Sidebar Architecture

The left sidebar is owned by `local_heyday_courseplayer`.

The sidebar must show:

* Home
* Scores
* Discussions
* Learning Objectives
* Getting Started
* Pretest
* lesson groups
* Resources
* Final Exam parent group

The Final Exam parent group must expand to show:

* Final Exam
* Next Steps for Completion

Lesson groups may expand to show:

* Lesson Introduction
* Learning Objectives
* Introduction
* Key Terms
* Chapter pages
* Lesson Review
* Lesson Assignment
* Lesson Discussion Area
* Lesson Quiz
* Resources for Further Learning

Sidebar behavior:

* active item has a blue left indicator or arrow
* completed items show green checkmarks
* in-progress items show blue dots/circles
* locked items show lock icons
* locked items remain visible but muted
* locked items must not look active or completed
* unavailable items should not be clickable unless Moodle allows access
* release-date text should display when Moodle availability provides a date
* long titles should wrap cleanly
* sidebar should scroll independently
* content should not jump or flicker
* no second sidebar should ever appear inside the content card

Example release-date text:

`Lesson 9 will be available on Jun 10, 2026 10:00 AM GMT+3`

`Final Exam will be available on Jun 19, 2026 10:00 AM GMT+3`

---

## Routing Architecture

The courseplayer must use one central route pattern:

`index.php?id=COURSEID&page=PAGE&cmid=CMID&subpage=SUBPAGE`

Implemented routes:

Home:

`index.php?id=105&page=home`

Scores:

`index.php?id=105&page=scores`

Discussions list:

`index.php?id=105&page=discussions`

Lesson discussion detail:

`index.php?id=105&page=discussion&cmid=FORUMCMID`

Learning Objectives:

`index.php?id=105&page=objectives`

or, when tied to a specific Moodle Page activity:

`index.php?id=105&page=lesson&cmid=CMID`

Getting Started:

`index.php?id=105&page=gettingstarted&subpage=overview`

`index.php?id=105&page=gettingstarted&subpage=syllabus`

`index.php?id=105&page=gettingstarted&subpage=navigating`

Pretest:

`index.php?id=105&page=pretest`

or:

`index.php?id=105&page=pretest&cmid=PRETESTQUIZCMID`

Lesson activity (Page, URL, File, Folder, H5P):

`index.php?id=105&page=lesson&cmid=CMID`

Lesson assignment:

`index.php?id=105&page=assignment&cmid=ASSIGNCMID`

Lesson quiz:

`index.php?id=105&page=lessonquiz&cmid=QUIZCMID`

Note: `page=quiz` is accepted as an alias and normalizes internally to `page=lesson`. Use `page=lessonquiz` for sidebar links and Next Up transitions to lesson quizzes.

Resources:

`index.php?id=105&page=resources`

Final Exam:

`index.php?id=105&page=finalexam`

or:

`index.php?id=105&page=finalexam&cmid=FINALEXAMQUIZCMID`

---

## Activity Resolver Rule

If `cmid` is provided, inspect the Moodle module type.

Route supported activity types as follows:

* `page` → render Moodle Page content inline inside the courseplayer card
* `quiz` (pretest) → render pretest card, quiz attempt/review via `local_heyday_quizskin`
* `quiz` (lesson) → render lessonquiz card (`page=lessonquiz`), attempt/review via `local_heyday_quizskin`
* `quiz` (final exam) → render final exam card (`page=finalexam`)
* `forum` → route to discussion bridge (`page=discussion`)
* `assign` → render assignment landing card (`page=assignment`)
* `resource` → route to resource renderer
* `folder` → route to resource/folder renderer
* `url` → route to URL/resource renderer
* `h5pactivity` → route to H5P renderer inline

Do not show a generic fallback card when the activity type is supported.

---

## Next Up Rule

Next Up is centralized in `local_heyday_courseplayer`.

Next Up must always be a normal route transition, not an embedded player.

Never iframe, include, fetch, inject, or render:

`/local/heyday_courseplayer/index.php`

inside the current content card.

Do not create:

* second top bar
* second sidebar
* second footer
* nested scrollbars
* duplicated courseplayer shell

Correct Next Up examples:

Pretest
→ Lesson 1 Learning Objectives or Lesson 1 Introduction

Lesson Discussion Area
→ Lesson Quiz

Lesson Quiz
→ Resources for Further Learning or next lesson

Resources
→ Final Exam

Final Exam
→ Next Steps for Completion

Next Steps for Completion
→ Return Home or Certificate/Evaluation action

---

## Home Architecture

Home is the selected-course landing page inside the courseplayer.

Home should show:

* course fullname
* course shortname or section code
* banner image when available
* completion circle (% complete)
* score circle (overall grade)
* next incomplete activity
* Continue button
* clean white card layout

The Continue button must use the centralized Next Up service.

---

## Scores Architecture

Scores must render inside the courseplayer shell.

Scores should use Moodle gradebook data as the source of truth.

Scores should show linked rows for:

* Pretest (shown but marked "Does not count for grade")
* lesson quizzes
* lesson assignments
* Final Exam

Each row shows:

* activity name (linked to courseplayer route)
* type label (Quiz / Assignment)
* status (submitted / not submitted / locked)
* submitted date when available
* score percentage when graded
* earned points / total points
* grade text on the right
* locked or unavailable rows muted

Pretest before attempt:

* `Pretest`
* `Not Started`
* `Does not count for grade`
* `- / total points`

Pretest after attempt:

* submitted date
* score percentage
* earned points / total points
* `Does not count for grade`

Clicking Pretest from Scores should open:

`/local/heyday_courseplayer/index.php?id=COURSEID&page=pretest`

not the raw Moodle quiz URL.

---

## Discussions Architecture

`local_heyday_discussions` is the discussion/forum feature module used by `local_heyday_courseplayer`.

It must not become a separate full learner shell.

Moodle `mod_forum` remains the real forum engine.

Each lesson should have a Moodle Forum activity named like:

* Lesson 1 Discussion Area
* Lesson 2 Discussion Area
* Lesson 3 Discussion Area

The main Discussions page (`page=discussions`) shows one linked row per lesson forum with:

* discussion icon
* linked title → opens `page=discussion&cmid=FORUMCMID`
* lesson association
* latest post date
* new-post count badge
* lock icon if unavailable
* muted gray style if locked

The lesson discussion detail page (`page=discussion&cmid=FORUMCMID`) shows:

* forum intro/prompt (if present) with chat icon, collapsible
* red closed-discussion banner when forum is closed or news type
* toolbar: search input, Sort By dropdown (Most Recent / Oldest First / Most Replies), Add a New Discussion button
* thread list with title, author, reply count, date, new-posts badge
* All / Mine / New tab bar (client-side JS filtering, no page reload)
* "Open Full Discussion View" link to native Moodle forum

The ed2go discussion CSS prefix is `hd-discussion-*` and `hd-disc-*`.

When learners cannot post, show:

`This discussion has been closed to new posts by learners.`

Existing posts and replies should remain visible if Moodle allows viewing.

---

## Learning Objectives Architecture

Learning Objectives must show inside the courseplayer shell.

It may be:

* a Moodle Page activity rendered inline
* a generated summary from lesson objective pages
* helper-rendered content

Suggested route:

`index.php?id=105&page=objectives`

or when tied to a Moodle Page activity:

`index.php?id=105&page=lesson&cmid=CMID`

It must not create:

* nested player
* second sidebar
* duplicate card shell
* second top bar

---

## Getting Started Architecture

Getting Started must display inside the same courseplayer shell.

It may include:

* Course Overview
* Syllabus
* Navigating this Course

Suggested routes:

`index.php?id=105&page=gettingstarted&subpage=overview`

`index.php?id=105&page=gettingstarted&subpage=syllabus`

`index.php?id=105&page=gettingstarted&subpage=navigating`

Getting Started should show:

* centered page title
* action icons
* completion status
* divider
* clean content area
* Next Up card

Important implementation rule:

Do not call:

`local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups)`

before `$lessongroups` is created by:

`$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);`

Getting Started completion code must first confirm `$lessongroups` exists and is an array.

---

## Pretest Architecture

The Pretest is a whole-course diagnostic self-assessment route inside `local_heyday_courseplayer`.

The Pretest is optional and does not affect the overall course grade.

The Pretest must use a real Moodle Quiz activity, but it must be treated as diagnostic, not as a graded course requirement, lesson quiz, final exam, or pass/fail gate.

Pretest should appear after Getting Started and before Lesson 1.

Suggested route:

`index.php?id=105&page=pretest`

or:

`index.php?id=105&page=pretest&cmid=PRETESTQUIZCMID`

The Pretest helper may locate the quiz by:

* explicit `cmid`
* Moodle Quiz activity named `Pretest`
* Moodle Quiz activity idnumber `HEYDAY_PRETEST`
* Moodle Quiz activity inside a section named `Pretest`

It must not fall back to the first visible quiz in the course.

The pretest landing card (`page=pretest`) shows Start / Resume / Review Results and a Show/Hide Instructions toggle.

When the learner starts the pretest, they leave the courseplayer shell and enter the native Moodle quiz attempt page. `local_heyday_quizskin` injects the ed2go skin (CSS + JS) into that page:

* "Back" arrow links back to `page=pretest`
* "Save and Close" returns to `page=pretest`
* "Submit Answers" submits via Moodle and skips the summary page
* Review page shows the score bar, correct/incorrect annotations, and a Next Up card pointing to Lesson 1

Recommended Pretest quiz settings:

* Name: Pretest
* idnumber: HEYDAY_PRETEST
* Type: Moodle Quiz
* Attempts allowed: 1
* Grade to pass: none / empty / not pass-fail
* Gradebook weighting: 0 or excluded from final course grade
* Question behavior: Deferred feedback
* Completion: complete when submitted/graded

---

## Lessons Architecture

Lessons must display inside the same courseplayer shell.

Lessons should be generated from real Moodle sections, subsections, and activities.

Do not hardcode lesson content when Moodle structure can provide it.

Example lesson group:

```
Lesson 1: Introduction to Artificial Intelligence
  Learning Objectives
  Introduction
  Key Terms
  Chapter 1
  Chapter 2
  Chapter 3
  Lesson 1 Review
  Lesson 1 Assignment
  Lesson 1 Discussion Area
  Lesson 1 Quiz
  Resources for Further Learning
```

Lesson pages should show:

* sidebar visible
* centered reading card
* course title
* lesson title
* chapter/page title
* top-left back/bookmark icons
* top-right print/fullscreen icons
* responsive images
* readable headings and paragraphs
* normal scrolling
* completion status
* Next Up card

Supported activity types:

* Page → rendered inline
* Quiz → `page=lessonquiz` card, then native quiz with quizskin
* Assignment → `page=assignment` landing card
* Forum → `page=discussion` detail view
* File, Folder, URL → resource card with Open button
* H5P activity → embedded inline

---

## Quiz Architecture

`local_heyday_quizskin` injects the ed2go quiz skin into native Moodle quiz attempt, review, and summary pages.

The skin applies to **both** Pretest and Lesson Quiz pages (detected by idnumber or name pattern).

The skin does NOT apply to the Final Exam — that exam uses native Moodle quiz pages without the skin (or may be extended separately).

`local_heyday_quiz` is the quiz bridge redirect plugin. Its `index.php` redirects:

* `page=lesson&cmid=CMID` for lesson quizzes
* `page=lessonquiz&cmid=CMID` for lesson quiz landing cards

Moodle core remains responsible for:

* attempts
* question rendering
* answer saving
* Save and Close
* Submit confirmation
* grading
* feedback
* review
* gradebook integration

Three quiz use cases:

1. **Pretest** — optional whole-course diagnostic before Lesson 1, not for credit. Landing card at `page=pretest`. Quizskin applies to attempt/review pages.

2. **Lesson Quiz** — lesson-level knowledge check. Landing card at `page=lessonquiz`. Quizskin applies to attempt/review pages. Next Up points to next CM in section sequence.

3. **Final Exam** — final graded assessment after Resources. Landing card at `page=finalexam`. May count toward certificate/completion. Quizskin does not currently apply.

Lesson quiz idnumber convention: `HEYDAY_LESSON1_QUIZ`, `HEYDAY_LESSON2_QUIZ`, etc.

Do not let both `local_heyday_quiz` and `local_heyday_quizskin` modify the same quiz page independently. `local_heyday_quizskin` owns all quiz attempt/review page styling.

---

## Assignment Architecture

Assignments must use Moodle Assignment as the source of truth.

The courseplayer renders an ed2go-style assignment landing card at `page=assignment&cmid=CMID`.

The assignment landing card shows:

* Show/Hide Instructions toggle (when assignment has intro text)
* Submission status badge (Submitted / Draft / Not submitted)
* Due date (when set)
* Last submission date (cut-off date, when set)
* Grade (earned / max, or "Not graded yet")
* "Open Assignment" button → opens `mod/assign/view.php` in `_top`
* "View Submission" when already submitted

The native Moodle assignment page handles all actual submission, file upload, online text, feedback, and grading. The courseplayer card is the landing page only.

Sidebar links to assignments use `page=assignment&cmid=CMID`.

Scores page shows assignment rows with type label "Assignment".

---

## Resources Architecture

Resources must display inside the courseplayer after lessons and before Final Exam.

Route:

`index.php?id=105&page=resources`

The resources page shows ed2go-style row cards. Each row shows:

* type icon (File / Link / Folder / Page / Interactive / Book)
* resource name (linked if available, plain text if locked)
* type label (FILE / LINK / FOLDER / PAGE / INTERACTIVE / BOOK)
* release-date note when locked
* right side: green check circle when completed, lock icon when locked, blue "Open Resource" button otherwise

Row CSS prefix: `hd-resource-*`.

Resources remain controlled by Moodle availability and permissions.

---

## Final Exam Architecture

Final Exam appears after Resources.

In the ed2go-style courseplayer, Final Exam is a parent group, not only a single activity.

Sidebar structure:

```
Resources
Final Exam
├── Final Exam
└── Next Steps for Completion
```

The parent item `Final Exam` should expand/collapse like a lesson group.

The child item `Final Exam` is the real graded Moodle Quiz.

The child item `Next Steps for Completion` is the post-exam completion page.

Routes:

`index.php?id=105&page=finalexam`

or:

`index.php?id=105&page=finalexam&cmid=FINALEXAMQUIZCMID`

Final Exam must resolve to a real Moodle Quiz activity only.

A Moodle Page, Label, URL, File, Forum, or other activity named "Final Exam" must not be treated as the Final Exam assessment.

The courseplayer must show:

* Final Exam parent menu item
* Final Exam quiz child item
* Next Steps for Completion child item
* locked state when unavailable
* release-date text when available
* clean exam instruction card
* Start button when no attempt exists
* Resume button when attempt is in progress
* Review Results button when attempt is submitted
* setup warning when quiz has no questions
* clear "Final Exam quiz not found" message when no real quiz exists
* grade/pass status when completed
* Next Up card from Final Exam to Next Steps for Completion

Recommended Final Exam quiz settings:

* Name: Final Exam
* Type: Moodle Quiz
* Attempts allowed: 1
* Grade to pass: business rule, for example 65% or 70%
* Grade category: Final Exam / Course Assessment
* Gradebook weighting: 100% if final exam determines course grade
* Question behavior: Deferred feedback
* Completion: require grade and passing grade if certificate/completion depends on passing
* Availability: after Resources or required lesson conditions

---

## Next Steps for Completion Architecture

Next Steps for Completion is a sub-item inside the Final Exam group.

Route:

`index.php?id=105&page=finalexam&subpage=nextsteps`

or resolved by finding a Moodle activity whose name matches `/next\s+steps?\b/i`.

The page should display ed2go-style cards for:

* Completion
* Certificate
* Evaluation

If Next Steps is an external tool, certificate link, or evaluation link that cannot safely render inside the courseplayer, show a clean green `Launch Activity` button.

Launch Activity must use top-level navigation and must not embed another courseplayer.

Next Steps should usually show a `Return Home` button.

---

## Related Plugin Roles

`local_heyday_courseplayer`
= one selected-course learner shell, router, sidebar, completion, availability, release-date, Next Up, renderer coordinator, Moodle clutter reduction layer

`local_heyday_coursehome`
= Home helper only (index.php redirects to `page=home`)

`local_heyday_scores`
= Scores helper only (index.php redirects to `page=scores`)

`local_heyday_discussions`
= Discussion/forum feature module only (index.php redirects to `page=discussions`)

`local_heyday_gettingstarted`
= Getting Started admin/setup utility only (requires `moodle/course:update`)

`local_heyday_pretest`
= Pretest legacy redirect only (index.php redirects to `page=pretest`)

`local_heyday_lessons`
= Lesson structure/service helper + legacy redirect (index.php redirects to courseplayer)

`local_heyday_quiz`
= Quiz bridge redirect only (index.php redirects to `page=lesson` or `page=lessonquiz`)

`local_heyday_quizskin`
= Ed2go quiz skin for attempt/review/summary pages (applies to both Pretest and Lesson Quiz). Uses Moodle hook system (before_standard_head_html_generation, before_footer_html_generation).

`local_heyday_questionbank`
= Admin/course-authoring helper, not learner shell

`local_heyday_coursesearch`
= Catalog/My Classroom search helper, not Layer 2 shell

`local_heyday_helptour`
= Support/tour utility, not learner shell

---

## Legacy Plugin URL Rule

Legacy plugin URLs should not keep separate player shells.

They should either:

1. redirect into the courseplayer route
2. expose helper functions/classes used by the courseplayer
3. remain admin/course-authoring utilities only

Examples:

`local_heyday_scores/index.php?id=105` → `local_heyday_courseplayer/index.php?id=105&page=scores`

`local_heyday_discussions/index.php?id=105` → `local_heyday_courseplayer/index.php?id=105&page=discussions`

`local_heyday_pretest/index.php?id=105` → `local_heyday_courseplayer/index.php?id=105&page=pretest`

`local_heyday_coursehome/index.php?id=105` → `local_heyday_courseplayer/index.php?id=105&page=home`

`local_heyday_lessons/index.php?id=105` → `local_heyday_courseplayer/index.php?id=105&page=home` (or lesson route)

`local_heyday_quiz/index.php?id=105&cmid=X` → `local_heyday_courseplayer/index.php?id=105&page=lesson&cmid=X`

---

## Plugin Safety Checks Before Code Changes

Before changing `local_heyday_courseplayer`, check:

* folder name: `heyday_courseplayer`
* component name: `local_heyday_courseplayer`
* `version.php`
* `lang/en/local_heyday_courseplayer.php`
* plugin version number
* CSS scope

Correct plugin folder:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`

Correct component:

`local_heyday_courseplayer`

Language file belongs in:

`lang/en/local_heyday_courseplayer.php`

CSS class prefix for new ed2go-style components: `hd-*` (e.g. `hd-discussion-*`, `hd-resource-*`, `hd-assign-*`)

CSS class prefix for the player shell: `heyday-*` (e.g. `heyday-player-card`, `heyday-courseplayer-sidebar`)

All courseplayer CSS must be scoped to `body.local-heyday-courseplayer`.

If PHP/plugin code changes, bump `version.php`.

If CSS-only changes, purge Moodle cache:

`C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php`

Do not generate a plugin ZIP unless explicitly requested.

---

## Build Order (Status)

All items below are complete as of 2026-06-30.

1. ✅ Stabilize `local_heyday_courseplayer` shell
2. ✅ Fix Next Up so it never nests the player
3. ✅ Stabilize sidebar active/completion/lock states
4. ✅ Stabilize Home (completion circle, score circle, Continue button)
5. ✅ Stabilize Getting Started (Overview, Syllabus, Navigating)
6. ✅ Stabilize Pretest landing card (Start / Resume / Review Results)
7. ✅ Stabilize Scores (gradebook rows, Pretest row, assignment rows, type labels)
8. ✅ Stabilize Lesson page rendering (Page inline, H5P inline, URL/File/Folder card)
9. ✅ Stabilize Discussion list (`page=discussions`) — row-based, sorted by lesson
10. ✅ Stabilize Discussion detail (`page=discussion&cmid=X`) — ed2go style, tabs, search, sort
11. ✅ Extend `local_heyday_quizskin` to Lesson Quiz pages (was Pretest only)
12. ✅ Stabilize Resources page (`page=resources`) — ed2go row list with type icons
13. ✅ Stabilize Final Exam card (Start / Resume / Review Results / locked / setup warning)
14. ✅ Stabilize Next Steps for Completion
15. ✅ Redirect all old standalone plugin URLs into courseplayer routes
16. ✅ Stabilize Assignment landing card (`page=assignment`) — status table, due date, grade

---

## CSS Scope Rule

All courseplayer CSS must be scoped to:

`body.local-heyday-courseplayer`

or:

`.local-heyday-courseplayer`

Do not write broad CSS that affects:

* Moodle admin pages
* normal course pages
* Adaptable settings
* dashboard
* login page
* quiz pages outside the player
* forum pages outside the player
* site-wide Moodle layout

CSS-only changes require Moodle purge cache only.

---

## Testing Rules

Main test URL:

`http://localhost/moodle/local/heyday_courseplayer/index.php?id=105`

Test each route:

* `page=home` — completion circle, score circle, Continue button
* `page=scores` — Pretest row (Does not count for grade), lesson quiz rows, assignment rows
* `page=discussions` — one row per lesson forum, sorted by lesson number
* `page=discussion&cmid=X` — thread list, tabs, search, closed banner, Add a New Discussion
* `page=objectives` — Learning Objectives content
* `page=gettingstarted&subpage=overview` — Course Overview
* `page=gettingstarted&subpage=syllabus` — Syllabus
* `page=gettingstarted&subpage=navigating` — Navigating this Course
* `page=pretest` — Start / Resume / Review Results card
* `page=lesson&cmid=X` — Moodle Page inline, or activity card
* `page=lessonquiz&cmid=X` — lesson quiz landing card (Start / Resume / Review Results)
* `page=assignment&cmid=X` — assignment status table, due date, Open Assignment button
* `page=resources` — resource rows with type icons, completion state, lock state
* `page=finalexam` — Final Exam card (Start / Resume / Review Results / locked / not found)
* `page=finalexam&subpage=nextsteps` — Next Steps for Completion

Expected results:

* only one black top player bar
* only one left sidebar
* no nested player inside content card
* Next Up changes browser route instead of embedding another page
* Pretest appears before Lesson 1 in sidebar and sequence
* Pretest appears in Scores and says `Does not count for grade`
* Discussions page shows one row per lesson discussion
* Discussion detail page shows threads, tabs, closed-discussion banner when needed
* Lesson quiz appears after discussion through Next Up
* Lesson quiz landing card shows Start / Resume / Review Results
* Lesson quiz attempt/review pages get the quizskin (ed2go question layout)
* Assignment landing card shows submission status, due date, grade
* Resources page shows type-icon rows with correct completion/lock state
* Final Exam appears after Resources in sidebar
* Final Exam expands to show Final Exam and Next Steps for Completion
* Final Exam shows Start, Resume, Review Results, locked message, setup warning, or not-found message
* Next Up from Final Exam goes to Next Steps for Completion
* Locked lessons remain visible but muted with release-date text
* Normal Moodle course page still works at `http://localhost/moodle/course/view.php?id=105`

---

## Rollback Rule

Before implementation, back up:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`

to:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer_backup_YYYYMMDD`

If a change fails:

1. Rename the changed folder to `heyday_courseplayer_broken`
2. Restore the backup folder as `heyday_courseplayer`
3. Purge caches: `C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php`

---

## Final Architecture Map

Layer 2: Course Player / Inside Selected Course

```
Moodle Course 105
│
├── Moodle Core Source of Truth
│   ├── Sections / Subsections / Pages
│   ├── Quizzes / Quiz attempts
│   ├── Forums / Forum posts
│   ├── Assignments / Submissions
│   ├── Files / Folders / URLs / H5P
│   ├── Completion tracking
│   ├── Availability restrictions / Release dates
│   ├── Gradebook / Grades
│   └── Permissions
│
├── Adaptable Theme
│   ├── Site header / Logo
│   ├── Global colors / Fonts
│   ├── Site footer
│   └── General Moodle appearance
│
└── local_heyday_courseplayer
    ├── One learner shell (index.php)
    ├── One top player bar
    ├── One sticky left sidebar
    ├── One routing system ($allowedpages + render_named_page)
    ├── One course structure reader (collect_lesson_groups, etc.)
    ├── One sidebar builder
    ├── One activity resolver (render_item_content)
    ├── One completion service
    ├── One availability / release-date service
    ├── One grade/score service
    ├── One Next Up service
    ├── One pretest resolver + landing card
    ├── One lessonquiz landing card
    ├── One assignment landing card
    ├── One forum/discussion bridge
    ├── One resource renderer (hd-resource-* rows)
    ├── One Final Exam resolver + landing card
    ├── One Next Steps resolver
    └── One Moodle clutter reduction layer

Supporting HeyDay plugins
│
├── local_heyday_quizskin   — ed2go quiz skin (Pretest + Lesson Quiz attempt/review pages)
├── local_heyday_quiz       — legacy redirect to courseplayer
├── local_heyday_pretest    — legacy redirect to page=pretest
├── local_heyday_scores     — legacy redirect to page=scores
├── local_heyday_discussions— legacy redirect to page=discussions
├── local_heyday_coursehome — legacy redirect to page=home
├── local_heyday_lessons    — legacy redirect to courseplayer
├── local_heyday_gettingstarted — admin/setup utility only
├── local_heyday_questionbank   — admin/authoring utility only
├── local_heyday_coursesearch   — catalog/My Classroom, not Layer 2
└── local_heyday_helptour       — help/tour utility only
```

---

## Final Rule

One selected-course learner shell.
One sidebar.
One routing system.
One completion, lock, and release-date system.
One Next Up system.
One quiz bridge.
One discussion bridge.
One assignment bridge.
One Final Exam and Next Steps flow.

Moodle core remains the real learning engine.
Adaptable remains the global theme only.
`local_heyday_courseplayer` owns the selected-course ed2go-style learner experience.

# local_heyday_courseplayer

One master ed2go-style learner player for the Heyday Short Term Certification Training LMS.

## Component check

- Folder: `heyday_courseplayer`
- Component: `local_heyday_courseplayer`
- Version: `2026060802`
- Main URL: `/local/heyday_courseplayer/index.php?id=105`
- Settings page: Site administration > Plugins > Local plugins > Heyday course player

## Learner sequence

The plugin renders this sequence in one master player:

1. Home
2. Scores
3. Discussions
4. Getting Started
5. Pretest
6. Lessons
7. Resources
8. Final Exam

## Moodle/Adaptable approach

Adaptable remains responsible for the global Moodle shell: main header, footer, fonts, and global colours. This plugin controls only the learner player area, sidebar, lesson card, release-date messages, and player buttons.

## Course setup conventions

- Name lesson sections like `Lesson 1: Introduction to Artificial Intelligence`.
- Add Moodle Page activities or Moodle Lesson activities inside lesson sections.
- If using Moodle Lesson activities, internal lesson pages are expanded in the sidebar.
- Name the pretest activity with `Pretest` in the title.
- Name the final quiz/activity with `Final Exam` in the title.
- Name the resources section with `Resources` in the section title.
- Use Moodle availability restrictions for future lessons and the Final Exam. The player reads those restrictions and shows release-date text instead of making locked items clickable.

## Install path

Copy the folder to:

`C:\xampp\moodle502\moodle\public\local\heyday_courseplayer`

Then run Moodle upgrade and purge caches.


2026060803: Adds ed2go-style Home dashboard hero/banner, progress and score circles, and full-width player cleanup for Adaptable/Moodle drawer layouts.


2026060806: Updated Discussions page inside the master player to use an ed2go-style lesson discussion index, deduplicate Lesson N Discussion Area forums, show post/participant/updated metadata, preserve locked rows, and prefer the custom local_heyday_discussions view when installed.

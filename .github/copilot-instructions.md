# Moodle + Adaptable Project Instructions

You are working on my Moodle 5.2+ / Adaptable 502.1.1 project for the “Short Term Certification Training LMS”.

## Environment

PHP executable:
C:\xampp\php\php.exe

Moodle root:
C:\xampp\moodle502\moodle

Public web root:
C:\xampp\moodle502\moodle\public

Local plugins path:
C:\xampp\moodle502\moodle\public\local

Moodle data:
C:\moodledata

Database:
moodle_db

Main course template ID:
105

Main site URL:
http://localhost/moodle/

Main player URL:
http://localhost/moodle/local/heyday_courseplayer/index.php?id=105

Use these URLs only if `$CFG->wwwroot` in config.php is still `http://localhost/moodle`.

## Correct Moodle CLI commands

Purge caches:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php

Upgrade:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive

Cron:
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\cron.php

Never use:
C:\xampp\moodle502\moodle\public\admin\cli

## Main working rules

1. Inspect real files first before giving code.
2. Do not guess file contents.
3. Work one plugin or file at a time.
4. Give the smallest safe fix first.
5. Do not edit Moodle core files.
6. Do not edit Adaptable source files unless absolutely required.
7. Preserve Moodle core functionality, completion, grades, permissions, and availability restrictions.
8. Do not generate ZIP files unless explicitly requested.
9. If PHP/plugin code changes, bump version.php.
10. If CSS-only changes, Moodle purge cache is enough.
11. Always provide backup, purge/cache, test, and rollback steps.
12. Avoid broad JavaScript, MutationObserver menu rebuilding, localStorage sidebar state, and global Additional HTML.

## Design strategy

Use Adaptable only for the global Moodle shell:

* header
* logo
* colors
* fonts
* footer
* buttons
* general site appearance

Use local_heyday_courseplayer for the ed2go-style learner player:

* black or near-black top player bar
* sticky white left sidebar
* light gray page background
* centered white content card
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

## CSS rule

Scope all custom course-player CSS only to:

body.local-heyday-courseplayer

or:

.local-heyday-courseplayer

Never write broad CSS that affects Moodle admin pages, course editing pages, normal course pages, or Adaptable globally.

## Moodle editable course structure

Course content should be created and edited in normal Moodle:

http://localhost/moodle/course/view.php?id=105&notifyeditingon=1

The local_heyday_courseplayer plugin should read Moodle sections, subsections, and activities instead of hardcoding every learner item.

Structure rule:

* Top-level Moodle section = main sidebar group
* Moodle subsection = expandable group inside a lesson
* Page, Book, Quiz, Assignment, Forum, H5P, File, Folder, URL = clickable learner item

## Standard course naming map

Main sequence:

Home
Scores
Discussions
Getting Started
Pretest
Lesson 1: Introduction to Artificial Intelligence
Resources
Final Exam

Getting Started items:

Course Overview
Syllabus
Navigating this Course

Lesson 1 groups:

Lesson 1 Introduction
Chapter 1: What Is Artificial Intelligence?
Chapter 2: From Science Fiction to Real Life
Chapter 3: A Brief History of Artificial Intelligence
Chapter 4: Why Now?
Lesson 1 Review

Lesson 1 Introduction pages:

Learning Objectives
Introduction
Key Terms

Chapter 1 pages:

Defining Artificial Intelligence
Artificial Narrow Intelligence
Artificial General Intelligence
Artificial Super Intelligence
Learning Check

Lesson 1 Review items:

Review
Key Terms Flashcards
Next Steps

## Sidebar behavior requirement

The sidebar must behave like ed2go:

1. Clicking a main section jumps to the first available child activity/page.
2. Clicking a subsection jumps to the first available child activity/page.
3. Active state is based on the selected activity cmid.
4. Only the active path remains expanded.
5. Previous lesson/subsection collapses when another path is selected.
6. Locked/unavailable items stay visible but muted/disabled.
7. Locked/unavailable items must not be clickable.
8. Release-date text must be server-rendered.
9. Sidebar must not flicker or jump.

Example:

Clicking “Lesson 1: Introduction to Artificial Intelligence” opens the first available child page, usually “Learning Objectives”.

Clicking “Lesson 1 Introduction” opens “Learning Objectives”.

Clicking “Chapter 1: What Is Artificial Intelligence?” opens “Defining Artificial Intelligence”.

The URL should use:

/local/heyday_courseplayer/index.php?id=105&page=lesson&cmid=FIRST_CHILD_CMID

## H5P requirement

If the selected activity is mod_h5pactivity, try to render it inside the ed2go-style player card when safely possible.

Detect H5P by:

$cm->modname === 'h5pactivity'

Preserve:

* H5P security
* permissions
* attempts
* grades
* completion
* availability
* normal Moodle H5P behavior

Do not scrape Moodle pages.
Do not hardcode cmid values.
Do not hardcode H5P filenames.

Keep the fallback “Open Activity” card only if safe inline rendering is not possible.

## Performance optimization rules

Inspect before changing:

* PHP version
* php.ini path
* OPcache status
* memory_limit
* max_input_vars
* upload limits
* config.php
* $CFG->wwwroot
* $CFG->dataroot
* database name
* cron
* debugging
* theme designer mode
* plugin CSS scope
* broad JavaScript
* repeated direct DB queries

Prefer:

* get_fast_modinfo($course)
* cm_info
* section_info
* Moodle availability/completion APIs

Avoid repeated direct DB queries when Moodle APIs already provide the data.

## Required response format

When making changes, respond with:

1. Diagnosis
2. Exact fix
3. Copy/paste code or config
4. Where to paste/copy
5. Purge/cache steps
6. How to test
7. Rollback if it fails

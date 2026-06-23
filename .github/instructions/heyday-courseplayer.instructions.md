---
applyTo: "public/local/heyday_courseplayer/**"
---

# local_heyday_courseplayer Plugin Instructions

This folder is the main ed2go-style learner player plugin.

Plugin path:
C:\xampp\moodle502\moodle\public\local\heyday_courseplayer

Component:
local_heyday_courseplayer

Required files:

* index.php
* view.php
* settings.php
* styles.css
* version.php
* lang/en/local_heyday_courseplayer.php

Before changing code, check:

1. folder name
2. component name
3. version.php
4. plugin version number
5. language file path
6. CSS scope

Known issue:
Do not call:

local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups)

before:

$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);

Getting Started completion logic must confirm $lessongroups exists and is an array.

Sidebar behavior:

* Main section and subsection titles should link to their first available child cmid.
* Active state should be based on selected cmid.
* Only the active path should remain open.
* Preserve locked/unavailable states.
* Do not make unavailable activities clickable.
* Do not rely on localStorage for sidebar state.
* Avoid broad JavaScript.

H5P behavior:

* Detect H5P with $cm->modname === 'h5pactivity'.
* Render inline inside the player card only when safely possible.
* Preserve completion, grades, attempts, permissions, and availability.
* Keep fallback Open Activity card only when inline rendering is unsafe or unsupported.

CSS:
All CSS must be scoped to:

body.local-heyday-courseplayer

or:

.local-heyday-courseplayer

After PHP/plugin changes:

* Bump version.php.
* Run Moodle upgrade.
* Purge caches.

After CSS-only changes:

* Purge caches only.

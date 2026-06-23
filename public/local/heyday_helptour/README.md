# Heyday Help and Tour Moodle local plugin

Component: `local_heyday_helptour`
Folder name: `heyday_helptour`

This plugin adds only:

- Help Center FAQ page
- Tour popup/modal
- Help and Tour controls beside Moodle's existing top header/menu

It does **not** replace Moodle's default search menu or user account menu.

## Install

1. Copy the folder `heyday_helptour` to:

   `C:\xampp\htdocs\moodle\local\heyday_helptour`

2. Open Moodle as admin:

   `Site administration -> Notifications`

3. Complete the plugin installation.

4. Purge caches:

   `Site administration -> Development -> Purge caches -> Purge all caches`

## URLs

Help Center:

`/local/heyday_helptour/help.php?courseid=105`

Tour launcher:

`/local/heyday_helptour/tour.php?courseid=105`

The Help and Tour buttons are also injected automatically into the Moodle top header on normal course pages.

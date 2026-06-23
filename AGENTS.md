# HeyDayTraining Academy — Moodle 5.02 + Adaptable

## Environment

- **Site:** HeyDayTraining Academy — http://localhost/moodle
- **OS:** Windows 10, XAMPP Apache port 80
- **Database:** MariaDB 10.11.18 (Windows service — do NOT start MySQL in XAMPP)
  - Host: 127.0.0.1:3306 | DB: moodle_db | User: moodleuser
- **PHP:** 8.3.31 at C:/xampp/php/php.exe
- **Xdebug:** v3.5.3 (port 9003, idekey=VSCODE)
- **Courses:** 103 courses in production DB
- **Backups:** G:\2018\HEYDAY\Database\Moodle\Backup

## Local project paths

- **Project root:** C:\xampp\moodle502\moodle
- **Moodle code root:** C:\xampp\moodle502\moodle\public
- **Moodle data:** C:\moodledata
- **Adaptable theme:** public/theme/adaptable
- **Local plugins:** public/local

## Important working paths

- Theme root: public/theme/adaptable
- Templates: public/theme/adaptable/templates
- Layout files: public/theme/adaptable/layout
- SCSS: public/theme/adaptable/scss
- JavaScript source: public/theme/adaptable/amd/src
- JavaScript build: public/theme/adaptable/amd/build
- Language strings: public/theme/adaptable/lang/en

## Custom local plugins (public/local/)

- heyday_coursehome — custom course home page
- heyday_courseplayer — course player UI
- heyday_discussions — discussion enhancements
- heyday_lessons — lesson enhancements
- heyday_quizskin — quiz UI skin
- heyday_scores — score display
- heyday_pretest — pre-test system
- heyday_gettingstarted — onboarding
- heyday_helptour — help tours
- heyday_coursesearch — course search
- ai_manager — AI integration layer
- edugears — learning gears
- kopere_mobile — mobile support

## Coding standards

- Frankenstyle naming enforced (local_heyday_*, theme_adaptable)
- phpcs moodle standard v3.7.0 (squizlabs/php_codesniffer 3.13.5)
- Mustache templates for all HTML output
- AMD modules for all JavaScript (no inline JS)
- SCSS only — no raw CSS edits in theme

## Theme: Adaptable

- themedesignermode=false in config.php (set true ONLY when editing SCSS, then revert)
- Prefer Adaptable admin settings UI before any code change
- Prefer CSS/SCSS before Mustache/PHP changes
- Always explain upgrade risk before changing files inside theme/adaptable

## Critical rules

- Do NOT modify Moodle core unless explicitly asked
- Do NOT modify public/config.php unless explicitly asked
- Do NOT commit secrets, passwords, DB credentials, SQL dumps, ZIP backups, or private keys
- Do NOT touch C:\moodledata except to explain what it is
- Do NOT touch G:\2018\HEYDAY\Database\Moodle\Backup except to explain backup/restore steps
- Keep changes small and reviewable

## Before editing — always explain

1. Files you will inspect
2. Files you expect to change
3. Whether Moodle cache purge is required
4. Whether the change affects Adaptable upgrade safety

## After editing — always provide

1. Files changed
2. Summary of change
3. Cache purge instructions (Admin > Development > Purge all caches)
4. Manual test checklist
5. Rollback method

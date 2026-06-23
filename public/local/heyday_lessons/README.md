# local_heyday_lessons

Custom Moodle local plugin for a Moodle + Adaptable ed2go-style learner player shell.

## Install

Copy the inner folder:

```text
heyday_lessons
```

to:

```text
C:\xampp\moodle502\moodle\public\local\heyday_lessons
```

Then visit:

```text
Site administration > Notifications
```

and purge caches:

```text
Site administration > Development > Purge caches
```

## Test URL

```text
http://localhost/moodle/local/heyday_lessons/index.php?id=105
```

Open a specific activity through the player:

```text
http://localhost/moodle/local/heyday_lessons/index.php?id=105&cmid=123
```

## What it does

- Server-rendered ed2go-style learner player.
- Sticky left sidebar.
- Black learner topbar.
- Light gray page background.
- White centered content card.
- Blue active sidebar indicator.
- Green completion checkmarks.
- Muted locked future lessons with line-style lock icon.
- Native Moodle section/activity discovery.
- Page module content rendered inside the player card.
- Quiz, assignment, forum, and other activity types shown as launch cards.
- Home, Scores, Discussions, Getting Started, Pretest, Resources, and Final Exam shell rows.
- No Moodle core edits.
- No Adaptable theme edits.
- No JavaScript sidebar rebuilding.

## Customization

Go to:

```text
Site administration > Plugins > Local plugins > Heyday Lessons player
```

You can change:

- Accent color
- Sidebar width
- Topbar height
- Page background
- Course support URL
- Topbar brand visibility
- Menu title replacements using JSON

Example title replacement JSON:

```json
{
  "Lesson 9": "Lesson 9: Implementing AI",
  "FAQs": "Lesson 1: FAQs"
}
```

## Reusing the master shell in future plugins

Other local plugins can include this renderer:

```php
require_once($CFG->dirroot . '/local/heyday_lessons/locallib.php');
echo local_heyday_lessons_render_shell($course, $cmid, $pagekey);
```


## 0.1.1 lesson submenu normalization

This build cleans the server-rendered lesson submenu before it is printed:

- hides default `New subsection` rows
- prevents course sections named `Scores` or `Discussions` from becoming duplicate lessons
- promotes `Lesson N: Real Title` activity names to the lesson group title when the Moodle section is only `Lesson N`
- changes generic rows such as `Lesson Content`, `Assignment`, `Quiz`, and `Discussion Area` into ed2go-style labels such as `Lesson 2 Introduction`, `Lesson 2 Assignment`, `Lesson 2 Quiz`, and `Lesson 2 Discussion Area`
- de-duplicates repeated Moodle activities in the same lesson submenu
- keeps all links server-rendered and uses the original Moodle activity URLs through the player

You can add extra hidden title patterns in the admin setting **Ignored menu title patterns**. Use one exact title or one PHP regex per line.

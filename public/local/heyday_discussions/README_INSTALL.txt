Heyday Discussions - custom learner discussion UI for Moodle 5.0+
================================================================

Plugin component:
  local_heyday_discussions

Purpose:
  Creates an ed2go-style learner-facing Discussions page and a custom single Discussion Area page while keeping Moodle core Forum as the real discussion engine.

Install:
  1. Go to Site administration -> Plugins -> Install plugins.
  2. Upload heyday_discussions_fixed_area.zip.
  3. Plugin type: Local plugin.
  4. Continue installation/upgrade.
  5. Purge all caches.

Open course discussions index:
  /local/heyday_discussions/index.php?id=COURSEID

Open a single custom discussion area:
  /local/heyday_discussions/view.php?cmid=FORUM_COURSE_MODULE_ID

Required course setup:
  Create one core Moodle Forum activity per lesson using:
    Forum type: Standard forum for general use
    Names:
      Lesson 1 Discussion Area
      Lesson 2 Discussion Area
      Lesson 3 Discussion Area
      ...
      Lesson 12 Discussion Area

Recommended use:
  Hide or de-emphasize the normal Moodle forum activity page and send learners to the custom view.php URL.
  The plugin still uses real Moodle forum discussions, posts, permissions, replies, and restrictions.

Features:
  - Discussions index cards
  - Locked grey future discussion rows
  - Post and participant counts
  - New posts badge
  - Updated date
  - Custom single Discussion Area page
  - Inline Write your post form
  - Search posts
  - Sort button
  - Print and fullscreen buttons
  - Reply links to Moodle's standard reply handler
  - Next Up card
  - Bottom Mine/New/Bookmarked status bar

Important:
  This plugin does not replace Moodle mod_forum. It reads and writes to the standard Moodle Forum activity.

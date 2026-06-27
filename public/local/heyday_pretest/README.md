# local_heyday_pretest

Moodle local plugin for the Heyday ed2go-style Pretest player.

## Install path

Copy the `heyday_pretest` folder to:

`C:\xampp\moodle502\moodle\public\local\heyday_pretest`

## Test URL

`http://localhost/moodle/local/heyday_pretest/index.php?id=105`

## Notes

- The plugin finds a Moodle Quiz activity with `Pretest` in the activity name.
- The quiz is opened inside a scoped Heyday player frame so Moodle quiz attempts, saving, grading, availability, and submission remain safe.
- Course-player CSS is scoped to `body.local-heyday-pretest-clean`.

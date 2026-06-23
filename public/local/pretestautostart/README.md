# local_pretestautostart

A small Moodle local plugin that sends selected Quiz activity front pages directly into the normal Moodle quiz attempt page.

This is intended for diagnostic pretests where you want this flow:

1. Learner clicks **Pretest** in the course index.
2. Moodle opens the quiz attempt page directly.
3. All questions are shown on one page when the quiz layout is configured that way.

This plugin does not embed quiz questions into the quiz description. It uses Moodle's normal quiz attempt/preview forms, so Moodle still handles attempts, saving, grading, feedback, review options, and permissions.

## Install

1. Copy this folder to: `local/pretestautostart`
2. Visit **Site administration > Notifications** to install.
3. Purge caches if needed.

## Configure Moodle quiz first

For the Pretest quiz:

1. Open **Pretest > Settings**.
2. Open **Layout**.
3. Set **New page** to **Never, all questions on one page**.
4. If questions already exist, open **Pretest > Questions**, enable page breaks, click **Repaginate**, and choose **All questions on one page**.

## Configure this plugin

1. Open **Site administration > Plugins > Local plugins > Pretest auto-start**.
2. Add the quiz course module id in **Quiz course module IDs**.
   - Example: if your quiz URL is `.../mod/quiz/view.php?id=123`, enter `123`.
3. Leave **Auto-start teacher preview too** unchecked unless you also want teachers to skip the Preview quiz button.

## Notes

- Students normally do not see **Preview quiz**. That button is for teacher/admin roles.
- To remove **Preview quiz** for teachers by permissions, remove `mod/quiz:preview` from the teacher role in the quiz permissions. This plugin leaves permissions unchanged.
- Test on a staging copy of the site before using it in production.

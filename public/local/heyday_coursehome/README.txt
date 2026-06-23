Heyday Course Home - local Moodle plugin
=======================================

Component: local_heyday_coursehome
Folder: heyday_coursehome
Install path: /local/heyday_coursehome
Main URL: /local/heyday_coursehome/index.php?id=COURSEID
Version: 1.0.3 / 2026052703

What it displays:
- Course fullname
- Course shortname as the banner section code
- Course overview image/banner
- Overall course completion percentage
- Current course score/grade as a clickable score circle
- Active lesson title in the welcome card
- Active lesson progress percentage in the progress bar
- Next incomplete lesson activity
- Continue button

Install:
1. Extract the folder named heyday_coursehome.
2. Copy it to moodle/local/heyday_coursehome or upload the ZIP in Site administration > Plugins > Install plugins.
3. Go to Site administration > Notifications.
4. Complete the plugin installation/upgrade.
5. Purge caches.
6. Open /local/heyday_coursehome/index.php?id=105.

Notes:
- Completion tracking must be enabled for progress to calculate.
- Activity completion must be enabled on activities.
- The welcome card prefers incomplete activities inside sections whose name starts with "Lesson".
- Course grade opens /grade/report/user/index.php?id=COURSEID.
- Course banner uses the first image in Course settings > Course image.

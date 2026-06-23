Heyday Pretest Shell - local_heyday_pretest
===========================================

Target
------
Moodle 5.0.6+ with Adaptable 500.2.6.
This plugin was generated for the Short Term Certification Training LMS project.

What this plugin does
---------------------
1. Adds a custom learner-facing Pretest shell:
   /local/heyday_pretest/view.php?cmid=YOUR_PRETEST_COURSE_MODULE_ID

2. Reads existing Moodle core mod_quiz records:
   - course_modules
   - quiz
   - quiz_attempts
   - question_attempts
   - question_answers

3. Keeps Moodle core quiz responsible for:
   - starting attempts
   - saving answers
   - autosave
   - submit confirmation
   - grading
   - review permissions
   - access rules

4. Styles core quiz pages named Pretest so that attempt and review pages look close to the uploaded ed2go-style screenshots.

Install
-------
1. Extract this folder into:
   C:\xampp\htdocs\moodle\local\heyday_pretest

2. Confirm the folder path is exactly:
   C:\xampp\htdocs\moodle\local\heyday_pretest\version.php

3. In Moodle, go to:
   Site administration -> Notifications

4. Complete the plugin installation.

5. Go to:
   Site administration -> Plugins -> Local plugins -> Heyday Pretest Shell

6. Leave this default value unless your quiz has another name:
   Pretest quiz name match = Pretest

Course setup
------------
1. Inside your course, create or edit a normal Moodle Quiz activity.
2. Name it:
   Pretest
3. Configure the quiz:
   - Attempts allowed: 1
   - Grade to pass: optional
   - Layout: Every question / or one page depending your course design
   - Review options: show feedback, right answer, marks after attempt if you want the custom review screen to show correct/incorrect colors.
4. Add your questions to the quiz.
5. Open the custom shell:
   http://localhost/moodle/local/heyday_pretest/index.php?id=COURSE_ID

Direct link format
------------------
Course-level auto-find link:
/local/heyday_pretest/index.php?id=COURSE_ID

Direct pretest link:
/local/heyday_pretest/view.php?cmid=PRETEST_COURSE_MODULE_ID

Important note
--------------
This is a safe local plugin wrapper. It does not rewrite Moodle's quiz engine.
That is intentional. Replacing the quiz engine directly can break attempt security,
autosave, question behaviours, grading, and future Moodle upgrades.

If you want the left course menu item "Pretest" to open this custom shell, create a URL resource or custom link that points to:
/local/heyday_pretest/index.php?id=COURSE_ID

After installing or replacing files
-----------------------------------
Go to:
Site administration -> Development -> Purge caches -> Purge all caches

Then refresh the browser with Ctrl + F5.

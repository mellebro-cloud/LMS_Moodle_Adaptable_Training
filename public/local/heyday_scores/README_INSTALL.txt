Heyday Scores - Moodle local plugin
===================================

Purpose
-------
Creates a learner-facing Scores page similar to the uploaded ed2go-style screenshot:
- Scores heading
- Download scores button
- Credit Assignments Only filter
- Sort By button
- Assignment name search
- Card-style rows
- Not Started status
- Does not count for grade label
- Locked grey rows for restricted activities

Compatibility target
--------------------
Moodle 5.0.6+ build reference 20260403
Adaptable 500.2.6 version reference 2025040811

Install by ZIP upload
---------------------
1. Log in as site administrator.
2. Go to Site administration > Plugins > Install plugins.
3. Upload heyday_scores.zip.
4. Plugin type: Local plugin.
5. Continue through the validation and installation screens.
6. Go to Site administration > Development > Purge caches > Purge all caches.

Manual XAMPP install
--------------------
1. Extract this ZIP.
2. Copy the folder named heyday_scores to:
   C:\xampp\htdocs\moodle\local\heyday_scores
3. Go to Site administration > Notifications.
4. Complete plugin installation.
5. Purge all caches.

Course setup required
---------------------
1. Course > Settings > Appearance > Show gradebook to students = Yes.
2. Course > Grades > Gradebook setup.
3. Create grade categories:
   - Diagnostic / Pretest
   - Practice Lesson Quizzes
   - Credit Assignments
   - Final Exam
4. Put Pretest inside Diagnostic / Pretest.
5. Put Lesson quizzes inside Practice Lesson Quizzes.
6. Put credit items and Final Exam inside credit categories.
7. Use ID numbers for grade items where possible:
   - NC_PRETEST
   - NC_L01_QUIZ
   - NC_L02_QUIZ
   - CR_FINAL_EXAM
8. Set non-credit categories to weight 0.
9. Configure Restrict access on locked lessons/quizzes.

Open the page
-------------
Use this URL:
/local/heyday_scores/index.php?id=COURSEID

Example:
http://localhost/moodle/local/heyday_scores/index.php?id=105

Student permission
------------------
The plugin adds the capability local/heyday_scores:view.
It is allowed by default for student, teacher, editingteacher, and manager.
If needed, confirm under:
Site administration > Users > Permissions > Define roles

Notes
-----
This plugin reads existing Moodle gradebook data. It does not replace Moodle Quiz, Gradebook, or Restrict access.

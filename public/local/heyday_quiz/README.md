# local_heyday_quiz

Moodle local plugin for a Heyday / ed2go-style quiz player shell.

Install folder:

```text
C:\xampp\moodle502\moodle\public\local\heyday_quiz
```

Component:

```text
local_heyday_quiz
```

Test URL:

```text
http://localhost/moodle/local/heyday_quiz/index.php?id=105
```

Optional parameters:

```text
cmid=QUIZ_COURSE_MODULE_ID
```

The plugin preserves Moodle quiz functionality by loading the real Moodle quiz screen inside a same-origin player frame and injecting scoped presentation CSS into that frame. It does not edit Moodle core files.

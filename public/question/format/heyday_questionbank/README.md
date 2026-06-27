# HeyDay Question Bank format

Moodle question import format plugin.

Component:

qformat_heyday_questionbank

Install path:

C:\xampp\moodle502\moodle\public\question\format\heyday_questionbank

Purpose:

Adds **HeyDay Question Bank format** to Moodle Question bank -> Import.

Supported text format:

```text
Q1: Which type of AI is most commonly in use today?
A. Artificial super intelligence
B. Artificial general intelligence
C. Artificial wide intelligence
D. Artificial narrow intelligence
Answer: D
Feedback D: This was the correct answer.
```

Fix included in 2026062704:

- questiontext is a plain string with questiontextformat.
- generalfeedback is a plain string with generalfeedbackformat.
- multichoice answers are formatted text arrays with text, format, and files keys.
- per-answer feedback is formatted text arrays with text, format, and files keys.
- combined feedback is formatted text arrays with text, format, and files keys.
- fixes both earlier import errors:
  - Cannot access offset of type string on string
  - format_text(): Argument #1 must be ?string, array given

Install:

```powershell
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
```

Use:

Course -> Question bank -> Import -> HeyDay Question Bank format

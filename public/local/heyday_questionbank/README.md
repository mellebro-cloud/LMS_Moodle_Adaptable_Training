# local_heyday_questionbank

HeyDay Question Bank Helper is a Moodle local plugin for creating Moodle XML question-bank files from a simple pasted question format.

## Install

Copy the folder `heyday_questionbank` to:

```text
C:\xampp\moodle502\moodle\public\local\heyday_questionbank
```

Then run:

```powershell
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\upgrade.php --non-interactive
C:\xampp\php\php.exe C:\xampp\moodle502\moodle\admin\cli\purge_caches.php
```

Open:

```text
http://localhost/moodle/local/heyday_questionbank/index.php?id=105
```

## Text format

```text
Q1: Question text
A. Option one
B. Option two
C. Option three
D. Option four
Answer: B
Feedback B: Correct!
```

## Purpose

This helper does not replace Moodle Question Bank. It exports Moodle XML that you import through Moodle normally.

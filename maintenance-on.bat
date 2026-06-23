@echo off
cd /d C:\xampp\moodle502\moodle\public
C:\xampp\php\php.exe admin\cli\maintenance.php --enable
pause
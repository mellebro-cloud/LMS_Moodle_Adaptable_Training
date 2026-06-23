<?php
require_once(__DIR__ . '/../../config.php');
$courseid = optional_param('id', 0, PARAM_INT);
redirect(new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $courseid]));

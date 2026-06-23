<?php
// This file is part of Moodle - local_heyday_gettingstarted.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_heyday_gettingstarted';
$plugin->version = 2026061414;
$plugin->requires  = 2025041400;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2026061414-master-shell-fix';
$plugin->dependencies = [
    'local_heyday_courseplayer' => 2026061414,
];

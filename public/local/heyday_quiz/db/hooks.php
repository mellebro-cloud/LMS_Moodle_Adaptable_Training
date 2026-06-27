<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_heyday_quiz\hook_callbacks::class . '::before_standard_head_html_generation',
        'priority' => 500,
    ],
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_heyday_quiz\hook_callbacks::class . '::before_footer_html_generation',
        'priority' => 500,
    ],
];

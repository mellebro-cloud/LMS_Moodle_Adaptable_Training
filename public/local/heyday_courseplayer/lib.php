<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Public helper functions for local_heyday_courseplayer.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Prepare any Heyday local-plugin page to use the shared master learner shell.
 *
 * Future local plugins can call this after setting $PAGE->url and context:
 * local_heyday_courseplayer_prepare_master_shell($PAGE, $course, 'My page', 'myplugin');
 *
 * @param moodle_page $page Moodle page object.
 * @param stdClass $course Course record.
 * @param string $title Page title.
 * @param string $pagetype Extra page type/body key.
 */
function local_heyday_courseplayer_prepare_master_shell(moodle_page $page, stdClass $course, string $title, string $pagetype = 'home'): void {
    \local_heyday_courseplayer\output\master_shell::prepare_page($page, $course, $title, $pagetype);
}


/**
 * Decide which Heyday player page should be used for a native Moodle activity.
 *
 * @param stdClass $cm Course module record.
 * @return string Player page key.
 */
function local_heyday_courseplayer_page_for_cm_record(stdClass $cm): string {
    $name = strtolower(trim((string)($cm->name ?? '')));
    $modname = (string)($cm->modname ?? '');

    if (strpos($name, 'pretest') !== false || strpos($name, 'pre-test') !== false) {
        return 'pretest';
    }

    if (strpos($name, 'final') !== false && strpos($name, 'exam') !== false) {
        return 'finalexam';
    }

    if (in_array($modname, ['resource', 'folder', 'url', 'book'], true)) {
        return 'resources';
    }

    return 'lesson';
}

/**
 * Optional native Moodle activity router.
 *
 * This keeps native Moodle + Adaptable as the editing/source-of-truth page, but
 * sends normal activity view clicks into the Heyday learner shell. It avoids
 * Moodle core edits and only targets /mod/.../view.php requests. Teachers can
 * still edit structure with /course/view.php?id=COURSEID&notifyeditingon=1 and
 * edit activities through the normal settings/modedit pages.
 *
 * Add ?heydaynative=1 to a native /mod/.../view.php URL when you intentionally
 * want to bypass the player shell.
 */
function local_heyday_courseplayer_before_http_headers(): void {
    global $CFG;

    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }

    if (function_exists('during_initial_install') && during_initial_install()) {
        return;
    }

    if (!empty($CFG->upgraderunning) || !empty($CFG->adminsetuppending)) {
        return;
    }

    if (optional_param('heydaynative', 0, PARAM_BOOL)) {
        return;
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!preg_match('#/mod/(page|h5pactivity|quiz|assign|forum|lesson|resource|url|book)/view\.php$#', $script)) {
        return;
    }

    $cmid = optional_param('id', 0, PARAM_INT);
    if ($cmid <= 0) {
        return;
    }

    try {
        $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
    } catch (Throwable $e) {
        return;
    }

    if (empty($cm->course)) {
        return;
    }

    $target = new moodle_url('/local/heyday_courseplayer/index.php', [
        'id' => (int)$cm->course,
        'page' => local_heyday_courseplayer_page_for_cm_record($cm),
        'cmid' => (int)$cm->id,
    ]);

    redirect($target);
}

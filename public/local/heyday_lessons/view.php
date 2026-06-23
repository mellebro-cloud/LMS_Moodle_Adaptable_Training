<?php
// Convenience entrypoint for lesson items. The main renderer lives in index.php.

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$page = optional_param('page', '', PARAM_ALPHANUMEXT);

$params = ['id' => $id];
if ($cmid > 0) {
    $params['cmid'] = $cmid;
}
if ($page !== '') {
    $params['page'] = $page;
}

redirect(new moodle_url('/local/heyday_lessons/index.php', $params));

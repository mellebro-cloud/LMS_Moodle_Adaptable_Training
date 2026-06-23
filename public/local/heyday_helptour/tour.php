<?php
// Tour launcher page for local_heyday_helptour.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', SITEID, PARAM_INT);

if ($courseid && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
} else {
    require_login();
    $course = get_site();
    $context = context_system::instance();
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/heyday_helptour/tour.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout($courseid && $courseid != SITEID ? 'incourse' : 'standard');
$PAGE->set_title(get_string('tour', 'local_heyday_helptour'));
$PAGE->set_heading(format_string($course->fullname));

$dashboardurl = $courseid && $courseid != SITEID
    ? new moodle_url('/local/heyday_coursehome/index.php', ['id' => $courseid])
    : new moodle_url('/my/');

echo $OUTPUT->header();
?>
<div style="max-width: 720px; margin: 40px auto; padding: 28px; background: #fff; border: 1px solid #d8dde3;">
    <h1 style="font-weight: 400; margin-top: 0;">Course Tour</h1>
    <p>This page launches the Heyday course tour popup. You can also open the tour from the top bar Tour button.</p>
    <p>
        <button type="button" class="btn btn-primary" id="heyday-tour-page-start">Start tour</button>
        <a class="btn btn-secondary" href="<?php echo $dashboardurl->out(false); ?>">Back to dashboard</a>
    </p>
</div>
<script>
(function () {
    function tryStartTour() {
        var tourButton = document.querySelector('#heyday-helptour-nav .heyday-ht-tour');
        if (tourButton) {
            tourButton.click();
            return true;
        }
        return false;
    }

    document.getElementById('heyday-tour-page-start').addEventListener('click', function () {
        tryStartTour();
    });

    setTimeout(tryStartTour, 500);
})();
</script>
<?php
echo $OUTPUT->footer();

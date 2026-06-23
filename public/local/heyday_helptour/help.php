<?php
// Help Center page for local_heyday_helptour.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

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
$PAGE->set_url(new moodle_url('/local/heyday_helptour/help.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout($courseid && $courseid != SITEID ? 'incourse' : 'standard');
$PAGE->set_title(get_string('helpcenter', 'local_heyday_helptour'));
$PAGE->set_heading(format_string($course->fullname));

$dashboardurl = $courseid && $courseid != SITEID
    ? new moodle_url('/local/heyday_coursehome/index.php', ['id' => $courseid])
    : new moodle_url('/my/');

$faqs = [
    [
        'q' => 'Where can I find the syllabus or textbook(s) for my course?',
        'a' => 'Open Getting Started, then select Syllabus. The syllabus page gives an overview of the course structure, lesson topics, and any required or recommended materials.'
    ],
    [
        'q' => 'What are the completion requirements?',
        'a' => 'Completion requirements depend on the course setup. In most courses, you should complete the required lessons, activities, quizzes, assignments, and final assessment. Use the left course menu and progress indicators to track your status.'
    ],
    [
        'q' => 'Does my completion guarantee a job?',
        'a' => 'Course completion is intended to document training progress and achievement. It does not guarantee employment, but it can support your skills development and professional profile.'
    ],
    [
        'q' => 'Does my course include tutoring?',
        'a' => 'If tutoring or instructor support is available, it will usually be provided through the discussion area, course announcements, or instructions inside the course. Contact your training center administrator if you are not sure.'
    ],
    [
        'q' => 'How do I reset my password?',
        'a' => 'Use the Moodle login page password reset option, or contact your training center administrator if your account is managed manually.'
    ],
    [
        'q' => 'Where can I go for additional support?',
        'a' => 'Use the Course Support link, contact your instructor through the discussion area, or reach your training center administrator for account, enrollment, payment, or certificate questions.'
    ],
];

echo $OUTPUT->header();
?>
<style id="heyday-helpcenter-page-css">
.heyday-helpcenter-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 18px 24px 80px 24px;
    color: #1f2933;
}

.heyday-helpcenter-title {
    font-size: 26px;
    line-height: 1.25;
    font-weight: 400;
    margin: 0 0 28px 0;
    color: #1f2933;
}

.heyday-helpcenter-subtitle {
    text-align: center;
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 22px 0;
    color: #1f2933;
}

.heyday-helpcenter-actions {
    display: flex;
    justify-content: flex-end;
    margin: -48px 0 26px 0;
}

.heyday-helpcenter-back {
    color: #0072a8;
    text-decoration: none;
    font-size: 14px;
}

.heyday-faq-list {
    border: 1px solid #d9e1e8;
    border-radius: 6px;
    background: #ffffff;
    overflow: hidden;
}

.heyday-faq-item {
    border-bottom: 1px solid #d9e1e8;
    background: #ffffff;
}

.heyday-faq-item:last-child {
    border-bottom: 0;
}

.heyday-faq-question {
    list-style: none;
    cursor: pointer;
    min-height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px;
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.heyday-faq-question::-webkit-details-marker {
    display: none;
}

.heyday-faq-plus {
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border: 2px solid #0072a8;
    border-radius: 50%;
    color: #0072a8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 18px;
    font-weight: 700;
    margin-left: 18px;
}

.heyday-faq-item[open] .heyday-faq-plus {
    background: #0072a8;
    color: #ffffff;
}

.heyday-faq-item[open] .heyday-faq-plus::before {
    content: "−";
}

.heyday-faq-plus::before {
    content: "+";
}

.heyday-faq-answer {
    padding: 0 22px 22px 22px;
    max-width: 980px;
    font-size: 15px;
    line-height: 1.55;
    color: #4a5568;
}

@media (max-width: 768px) {
    .heyday-helpcenter-page {
        padding: 16px 14px 60px 14px;
    }

    .heyday-helpcenter-actions {
        margin: 0 0 18px 0;
        justify-content: flex-start;
    }

    .heyday-faq-question {
        font-size: 15px;
        padding: 0 16px;
    }
}
</style>
<div class="heyday-helpcenter-page">
    <h1 class="heyday-helpcenter-title"><?php echo get_string('helpcenter', 'local_heyday_helptour'); ?></h1>
    <div class="heyday-helpcenter-actions">
        <a class="heyday-helpcenter-back" href="<?php echo $dashboardurl->out(false); ?>">Back to dashboard</a>
    </div>
    <div class="heyday-helpcenter-subtitle">Frequently Asked Questions</div>
    <div class="heyday-faq-list">
        <?php foreach ($faqs as $faq): ?>
            <details class="heyday-faq-item">
                <summary class="heyday-faq-question">
                    <span><?php echo format_string($faq['q']); ?></span>
                    <span class="heyday-faq-plus" aria-hidden="true"></span>
                </summary>
                <div class="heyday-faq-answer">
                    <?php echo format_text($faq['a'], FORMAT_HTML); ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
<?php
echo $OUTPUT->footer();

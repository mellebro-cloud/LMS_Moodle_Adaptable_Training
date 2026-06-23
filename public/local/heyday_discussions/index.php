<?php
// Learner-facing Discussions index for Heyday course shell.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/heyday_discussions:view', $context);

$PAGE->set_url(new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('discussions', 'local_heyday_discussions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('heyday-discussions-page');
$PAGE->requires->css(new moodle_url('/local/heyday_discussions/styles.css'));
$PAGE->requires->js_call_amd('local_heyday_discussions/discussions', 'init');

/**
 * Extract lesson number from an activity name.
 *
 * Examples:
 * Lesson 1 Discussion Area  => 1
 * Lesson 12 Discussion Area => 12
 *
 * @param string $name
 * @return int|null
 */
function local_heyday_discussions_lesson_number(string $name): ?int {
    if (preg_match('/lesson\s*(\d+)/i', $name, $matches)) {
        return (int)$matches[1];
    }

    return null;
}

/**
 * Count posts, participants, latest post date, and new posts.
 *
 * @param int $forumid
 * @param int $courseid
 * @param int $userid
 * @return array
 */
function local_heyday_discussions_counts(int $forumid, int $courseid, int $userid): array {
    global $DB;

    $posts = (int)$DB->count_records_sql(
        "SELECT COUNT(fp.id)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    $participants = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT fp.userid)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    $latest = (int)$DB->get_field_sql(
        "SELECT MAX(fp.modified)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    // Basic "new posts" calculation based on user's last course access.
    // To see the red New posts badge, posts must be created after the learner's last course access.
    $lastaccess = (int)$DB->get_field('user_lastaccess', 'timeaccess', [
        'userid' => $userid,
        'courseid' => $courseid,
    ], IGNORE_MISSING);

    $newposts = 0;

    if ($lastaccess > 0) {
        $newposts = (int)$DB->count_records_sql(
            "SELECT COUNT(fp.id)
               FROM {forum_posts} fp
               JOIN {forum_discussions} fd ON fd.id = fp.discussion
              WHERE fd.forum = :forumid
                AND fp.modified > :lastaccess
                AND fp.userid <> :userid",
            [
                'forumid' => $forumid,
                'lastaccess' => $lastaccess,
                'userid' => $userid,
            ]
        );
    }

    return [$posts, $participants, $latest, $newposts];
}

$modinfo = get_fast_modinfo($course, $USER->id);
$lessonrows = [];

foreach ($modinfo->get_cms() as $cm) {
    if ($cm->modname !== 'forum') {
        continue;
    }

    $lessonno = local_heyday_discussions_lesson_number($cm->name);

    // Only collect Lesson 1–12 Discussion Area forums.
    // This prevents unrelated forums such as Scores from appearing.
    if ($lessonno === null || $lessonno < 1 || $lessonno > 12) {
        continue;
    }

    if (!preg_match('/discussion\s*area/i', $cm->name)) {
        continue;
    }

    $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', IGNORE_MISSING);

    if (!$forum) {
        continue;
    }

    [$posts, $participants, $latest, $newposts] = local_heyday_discussions_counts(
        $forum->id,
        $course->id,
        $USER->id
    );

    // Important:
    // cm->uservisible may still be true for admin/teacher.
    // cm->available is what makes date-restricted activities behave like locked rows.
    $locked = !$cm->uservisible;

    if (property_exists($cm, 'available') && !$cm->available) {
        $locked = true;
    }

    $updatedtime = $latest ?: $forum->timemodified;

    $lessonrows[$lessonno] = [
        'name' => format_string($cm->name),
        'lessonno' => $lessonno,
        'cmid' => $cm->id,
        'url' => (new moodle_url('/local/heyday_discussions/view.php', ['cmid' => $cm->id]))->out(false),
        'posts' => $posts,
        'participants' => $participants,
        'updated' => $updatedtime ? userdate($updatedtime, '%m/%d/%Y') : '',
        'newposts' => $newposts,
        'locked' => $locked,
        'placeholder' => false,
    ];
}

$rows = [];

for ($i = 1; $i <= 12; $i++) {
    if (isset($lessonrows[$i])) {
        $rows[] = $lessonrows[$i];
        continue;
    }

    // Placeholder row if the lesson discussion forum has not been created yet.
    $rows[] = [
        'name' => 'Lesson ' . $i . ' Discussion Area',
        'lessonno' => $i,
        'cmid' => 0,
        'url' => '',
        'posts' => 0,
        'participants' => 0,
        'updated' => '',
        'newposts' => 0,
        'locked' => true,
        'placeholder' => true,
    ];
}

$hasactualforum = false;

foreach ($rows as $row) {
    if (empty($row['placeholder'])) {
        $hasactualforum = true;
        break;
    }
}

echo $OUTPUT->header();
?>

<div class="hd-discussions-index">
    <h2><?php echo get_string('discussions', 'local_heyday_discussions'); ?></h2>

    <?php if (!$hasactualforum): ?>
        <div class="hd-discussion-setup">
            <?php echo get_string('setupneeded', 'local_heyday_discussions'); ?>
        </div>
    <?php endif; ?>

    <div class="hd-discussion-card-list">
        <?php foreach ($rows as $row): ?>
            <div class="hd-discussion-card <?php echo $row['locked'] ? 'locked' : ''; ?> <?php echo $row['placeholder'] ? 'placeholder' : ''; ?>">
                <div class="hd-discussion-left">

                    <span class="hd-discussion-icon" aria-hidden="true">💬</span>

                    <div class="hd-discussion-main">
                        <?php if (!$row['locked'] && !empty($row['url'])): ?>
                            <a class="hd-discussion-title" href="<?php echo s($row['url']); ?>">
                                <?php echo s($row['name']); ?>
                            </a>
                        <?php else: ?>
                            <span class="hd-discussion-title"><?php echo s($row['name']); ?></span>
                        <?php endif; ?>

                        <?php if (!$row['locked']): ?>
                            <?php if ($row['newposts'] > 0): ?>
                                <div class="hd-new-badge">
                                    <?php
                                    echo $row['newposts'] . ' ' .
                                        get_string(
                                            $row['newposts'] === 1 ? 'newpost' : 'newposts',
                                            'local_heyday_discussions'
                                        );
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="hd-discussion-meta">
                                <?php
                                echo $row['posts'] . ' ' .
                                    get_string(
                                        $row['posts'] === 1 ? 'post' : 'posts',
                                        'local_heyday_discussions'
                                    );
                                ?>
                                &nbsp;&nbsp;
                                <?php
                                echo $row['participants'] . ' ' .
                                    get_string(
                                        $row['participants'] === 1 ? 'participant' : 'participants',
                                        'local_heyday_discussions'
                                    );
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hd-discussion-right">
                    <?php if ($row['locked']): ?>
                        <span class="hd-lock" title="<?php echo get_string('locked', 'local_heyday_discussions'); ?>">🔒</span>
                    <?php else: ?>
                        <div class="hd-updated-label">
                            <?php echo get_string('updated', 'local_heyday_discussions'); ?>
                        </div>

                        <?php if (!empty($row['updated'])): ?>
                            <div class="hd-updated-date">
                                <?php echo s($row['updated']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
echo $OUTPUT->footer();
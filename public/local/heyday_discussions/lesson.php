<?php
// Heyday Discussions — lesson-number entry point.
// URL: /local/heyday_discussions/lesson.php?id=COURSEID&lesson=1
//
// Discovers the "Lesson N Discussion Area" forum automatically so the course
// player sidebar can link to a clean URL without knowing the cmid in advance.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/editorlib.php');

$id     = required_param('id', PARAM_INT);
$lesson = required_param('lesson', PARAM_INT);

if ($lesson < 1 || $lesson > 99) {
    throw new moodle_exception('invalidlessonno', 'local_heyday_discussions');
}

$course      = get_course($id);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('local/heyday_discussions:view', $coursecontext);

// -----------------------------------------------------------------------
// Auto-discover the forum activity named "Lesson N Discussion Area".
// -----------------------------------------------------------------------
$modinfo    = get_fast_modinfo($course, $USER->id);
$cm         = null;

foreach ($modinfo->get_cms() as $candidate) {
    if ($candidate->modname !== 'forum') {
        continue;
    }
    if (!preg_match('/\blesson\s*0*' . $lesson . '\b/i', $candidate->name)) {
        continue;
    }
    if (!preg_match('/discussion\s*area/i', $candidate->name)) {
        continue;
    }
    $cm = $candidate;
    break;
}

// -----------------------------------------------------------------------
// Forum not found — show setup notice and exit.
// -----------------------------------------------------------------------
if (!$cm) {
    $PAGE->set_url(new moodle_url('/local/heyday_discussions/lesson.php', [
        'id'     => $course->id,
        'lesson' => $lesson,
    ]));
    $PAGE->set_context($coursecontext);
    $PAGE->set_course($course);
    $PAGE->set_pagelayout('course');
    $PAGE->set_title('Lesson ' . (int)$lesson . ' Discussion Area');
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->add_body_class('heyday-discussions-page');
    $PAGE->requires->css(new moodle_url('/local/heyday_discussions/styles.css'));

    echo $OUTPUT->header();
    echo '<div class="hd-discussions-index">';
    echo '<div class="hd-discussion-setup">';
    echo 'Lesson ' . (int)$lesson . ' Discussion Area has not been set up yet. ';
    echo 'Create a Moodle Forum activity named ';
    echo '<strong>Lesson ' . (int)$lesson . ' Discussion Area</strong> in this course.';
    echo '</div></div>';
    echo $OUTPUT->footer();
    exit;
}

// -----------------------------------------------------------------------
// Module context — check visibility.
// -----------------------------------------------------------------------
$context = context_module::instance($cm->id);
$forum   = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
    throw new moodle_exception('activityiscurrentlyhidden');
}

$PAGE->set_url(new moodle_url('/local/heyday_discussions/lesson.php', [
    'id'     => $course->id,
    'lesson' => $lesson,
]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('course');
$PAGE->set_title('Lesson ' . (int)$lesson . ' Discussion Area');
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('heyday-discussions-page');
$PAGE->requires->css(new moodle_url('/local/heyday_discussions/styles.css'));

// Initialise the TinyMCE editor used in "Write your post".
$editoroptions = [
    'context'               => $context,
    'autosave'              => false,
    'enable_filemanagement' => false,
];
$editor = editors_get_preferred_editor(FORMAT_HTML);
$editor->use_editor('hd_message', $editoroptions);

$canpost = has_capability('mod/forum:startdiscussion', $context);

// -----------------------------------------------------------------------
// Handle "Write your post" form submission.
// -----------------------------------------------------------------------
if (optional_param('submitpost', 0, PARAM_BOOL) && confirm_sesskey()) {
    if (!$canpost) {
        throw new moodle_exception('nopermissions', 'error', '', 'post in this forum');
    }

    $subject = required_param('subject', PARAM_TEXT);
    $message = required_param('message', PARAM_RAW_TRIMMED);

    if (trim($subject) === '' || trim($message) === '') {
        redirect(
            $PAGE->url,
            get_string('missingtitleormessage', 'local_heyday_discussions'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $newdiscussion                = new stdClass();
    $newdiscussion->course        = $course->id;
    $newdiscussion->forum         = $forum->id;
    $newdiscussion->name          = $subject;
    $newdiscussion->subject       = $subject;
    $newdiscussion->message       = clean_text($message, FORMAT_HTML);
    $newdiscussion->messageformat = FORMAT_HTML;
    $newdiscussion->messagetrust  = 0;
    $newdiscussion->mailnow       = 0;
    $newdiscussion->groupid       = -1;
    $newdiscussion->timestart     = 0;
    $newdiscussion->timeend       = 0;
    $newdiscussion->pinned        = 0;

    $unusedform    = null;
    $unusedmessage = null;

    forum_add_discussion($newdiscussion, $unusedform, $unusedmessage);

    redirect(
        $PAGE->url,
        get_string('postcreated', 'local_heyday_discussions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------------
// Helpers.
// -----------------------------------------------------------------------

/**
 * Plain-text excerpt for search indexing.
 *
 * @param string $html
 * @param int    $length
 * @return string
 */
function heyday_lesson_discussions_excerpt(string $html, int $length = 500): string {
    return shorten_text(trim(html_to_text($html, 0, false)), $length, true);
}

/**
 * Find the next visible course module for the Next Up card.
 *
 * @param stdClass $course
 * @param int      $currentcmid
 * @param int      $userid
 * @return array|null
 */
function heyday_lesson_discussions_next_activity(stdClass $course, int $currentcmid, int $userid): ?array {
    $modinfo = get_fast_modinfo($course, $userid);
    $cms     = array_values($modinfo->get_cms());
    $found   = false;

    foreach ($cms as $candidate) {
        if ($found && $candidate->uservisible && !empty($candidate->url)) {
            return [
                'name' => format_string($candidate->name),
                'url'  => $candidate->url->out(false),
                'type' => $candidate->modname,
            ];
        }
        if ((int)$candidate->id === $currentcmid) {
            $found = true;
        }
    }

    return null;
}

// -----------------------------------------------------------------------
// Build discussion thread data.
// -----------------------------------------------------------------------
$intro       = format_module_intro('forum', $forum, $cm->id);
$discussions = $DB->get_records('forum_discussions', ['forum' => $forum->id], 'timemodified DESC');

$discussiondata = [];
$totalnew       = 0;
$minecount      = 0;

$lastaccess = (int)$DB->get_field(
    'user_lastaccess',
    'timeaccess',
    ['userid' => $USER->id, 'courseid' => $course->id],
    IGNORE_MISSING
);

foreach ($discussions as $discussion) {
    $posts = $DB->get_records('forum_posts', ['discussion' => $discussion->id], 'created ASC');

    if (!$posts) {
        continue;
    }

    $firstpost = $posts[$discussion->firstpost] ?? reset($posts);
    $author    = $DB->get_record('user', ['id' => $firstpost->userid], '*', MUST_EXIST);

    $replies    = [];
    $replycount = 0;
    $newcount   = 0;

    foreach ($posts as $post) {
        if ((int)$post->userid === (int)$USER->id) {
            $minecount++;
        }

        if ($lastaccess > 0 && (int)$post->modified > $lastaccess) {
            $newcount++;
        }

        if ((int)$post->id !== (int)$firstpost->id) {
            $replycount++;

            if (count($replies) < 3) {
                $replyauthor = $DB->get_record(
                    'user',
                    ['id' => $post->userid],
                    'id,firstname,lastname,email,picture,imagealt',
                    IGNORE_MISSING
                );

                $replies[] = [
                    'author'   => $replyauthor ? fullname($replyauthor) : 'Anonymous',
                    'message'  => format_text($post->message, $post->messageformat, ['context' => $context]),
                    'posted'   => userdate($post->created, '%b %d, %Y %I:%M %p'),
                    'replyurl' => (new moodle_url('/mod/forum/post.php', ['reply' => $post->id]))->out(false),
                ];
            }
        }
    }

    $totalnew += $newcount;

    $discussiondata[] = [
        'id'         => $discussion->id,
        'subject'    => format_string($discussion->name),
        'author'     => fullname($author),
        'message'    => format_text($firstpost->message, $firstpost->messageformat, ['context' => $context]),
        'excerpt'    => heyday_lesson_discussions_excerpt($firstpost->message),
        'replycount' => $replycount,
        'newcount'   => $newcount,
        'updated'    => userdate($discussion->timemodified, '%b %d, %Y %I:%M %p'),
        'replyurl'   => (new moodle_url('/mod/forum/post.php', ['reply' => $firstpost->id]))->out(false),
        'replies'    => $replies,
    ];
}

$next            = heyday_lesson_discussions_next_activity($course, $cm->id, $USER->id);
$standardposturl = new moodle_url('/mod/forum/post.php', ['forum' => $forum->id]);
$indexurl        = new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id]);

// -----------------------------------------------------------------------
// Inline JavaScript — compact TinyMCE toolbar + all interactivity.
// -----------------------------------------------------------------------
$PAGE->requires->js_init_code(<<<'JS'
document.addEventListener('DOMContentLoaded', function () {

    // --------------------------------------------------------
    // 1. COMPACT ONE-ROW TINYMCE TOOLBAR
    // --------------------------------------------------------
    var heydayEditorInitialised = false;

    function heydayLoadFullEditorToolbar() {
        var tiny     = window.tinymce || window.tinyMCE;
        var textarea = document.getElementById('hd_message');

        if (!textarea) { return; }
        if (!tiny)     { setTimeout(heydayLoadFullEditorToolbar, 300); return; }
        if (heydayEditorInitialised) { return; }

        var existingEditor = tiny.get('hd_message');
        if (existingEditor) { existingEditor.remove(); }

        heydayEditorInitialised = true;

        tiny.init({
            selector: '#hd_message',

            menubar:    false,
            branding:   false,
            promotion:  false,
            statusbar:  false,
            resize:     true,
            height:     115,

            toolbar_mode:   'sliding',
            toolbar_sticky: false,

            plugins: 'lists link table charmap fullscreen help hr',

            toolbar:
                'bold italic | ' +
                'numlist bullist | ' +
                'outdent indent | ' +
                'blockquote | ' +
                'link unlink | ' +
                'hdmath table hr charmap | ' +
                'styles blocks fontfamily fontsize | ' +
                'fullscreen help',

            block_formats:
                'Normal=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6',

            font_family_formats:
                'Arial=Arial,Helvetica,sans-serif; ' +
                'Calibri=Calibri,sans-serif; ' +
                'Courier New=Courier New,Courier,monospace; ' +
                'Georgia=Georgia,serif; ' +
                'Tahoma=Tahoma,Geneva,sans-serif; ' +
                'Times New Roman=Times New Roman,Times,serif; ' +
                'Verdana=Verdana,Geneva,sans-serif',

            font_size_formats: '8pt 9pt 10pt 11pt 12pt 14pt 18pt 24pt 36pt',

            content_style:
                'body {' +
                    'font-family: Arial, Helvetica, sans-serif;' +
                    'font-size: 14px;' +
                    'margin: 8px;' +
                '}',

            setup: function (editor) {
                editor.ui.registry.addButton('hdmath', {
                    text: 'Σ',
                    tooltip: 'Equation',
                    onAction: function () {
                        var eq = window.prompt('Enter equation, e.g.: x^2 + y^2 = z^2');
                        if (eq && eq.trim()) {
                            editor.insertContent('\\(' + eq.trim() + '\\)');
                        }
                    }
                });

                editor.on('change keyup undo redo', function () {
                    editor.save();
                });
            }
        });

        var form = document.querySelector('.hd-write-post');
        if (form) {
            form.addEventListener('submit', function () {
                var activeEditor = tiny.get('hd_message');
                if (activeEditor) { activeEditor.save(); }
            });
        }
    }

    setTimeout(heydayLoadFullEditorToolbar, 800);

    // --------------------------------------------------------
    // 2. PAGE ELEMENTS
    // --------------------------------------------------------
    var shell            = document.querySelector('.hd-forum-shell');
    var printToggle      = document.querySelector('.hd-print-toggle');
    var printMenu        = document.querySelector('.hd-print-menu');
    var fullscreenButton = document.querySelector('.hd-fullscreen-toggle');
    var bookmarkButton   = document.querySelector('.hd-bookmark');
    var searchInput      = document.querySelector('.hd-search-posts');
    var sortButton       = document.querySelector('.hd-sort-discussions');
    var loadMoreButton   = document.querySelector('.hd-load-more');
    var threadList       = document.querySelector('.hd-thread-list');
    var bookmarkCountPill = document.querySelector('.hd-bookmarked-count');

    // --------------------------------------------------------
    // 3. HELPERS
    // --------------------------------------------------------
    function setIcon(button, inactiveClass, activeClass, active) {
        if (!button) { return; }
        var icon = button.querySelector('i');
        if (!icon) { return; }
        icon.className = active ? activeClass : inactiveClass;
    }

    function updateBookmarkCount() {
        if (!bookmarkCountPill) { return; }
        var count = 0;
        document.querySelectorAll('.hd-bookmark.is-bookmarked, .hd-bookmark-flag.is-bookmarked').forEach(function () {
            count++;
        });
        bookmarkCountPill.textContent = String(count);
    }

    // --------------------------------------------------------
    // 4. PRINT DROPDOWN
    // --------------------------------------------------------
    if (printToggle && printMenu) {
        printToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            printMenu.hidden = !printMenu.hidden;
        });

        printMenu.addEventListener('click', function (e) { e.stopPropagation(); });

        document.addEventListener('click', function () { printMenu.hidden = true; });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { printMenu.hidden = true; }
        });
    }

    document.querySelectorAll('.hd-print-activity, .hd-print-lesson').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            if (printMenu) { printMenu.hidden = true; }
            window.print();
        });
    });

    // --------------------------------------------------------
    // 5. FULLSCREEN
    // --------------------------------------------------------
    if (fullscreenButton && shell) {
        fullscreenButton.addEventListener('click', function (e) {
            e.preventDefault();
            if (!document.fullscreenElement) {
                if (shell.requestFullscreen)       { shell.requestFullscreen(); }
                else if (shell.webkitRequestFullscreen) { shell.webkitRequestFullscreen(); }
                else if (shell.msRequestFullscreen)     { shell.msRequestFullscreen(); }
            } else {
                if (document.exitFullscreen)       { document.exitFullscreen(); }
                else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
                else if (document.msExitFullscreen)     { document.msExitFullscreen(); }
            }
        });
    }

    // --------------------------------------------------------
    // 6. PAGE BOOKMARK
    // --------------------------------------------------------
    if (bookmarkButton) {
        var bookmarkKey = 'heyday-discussion-bookmark-lesson-' + bookmarkButton.dataset.lesson;

        if (localStorage.getItem(bookmarkKey) === '1') {
            bookmarkButton.classList.add('is-bookmarked');
            bookmarkButton.setAttribute('aria-pressed', 'true');
            setIcon(bookmarkButton, 'fa fa-bookmark-o', 'fa fa-bookmark', true);
        } else {
            bookmarkButton.setAttribute('aria-pressed', 'false');
            setIcon(bookmarkButton, 'fa fa-bookmark-o', 'fa fa-bookmark', false);
        }

        bookmarkButton.addEventListener('click', function (e) {
            e.preventDefault();
            var active = !bookmarkButton.classList.contains('is-bookmarked');
            bookmarkButton.classList.toggle('is-bookmarked', active);
            bookmarkButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) {
                localStorage.setItem(bookmarkKey, '1');
            } else {
                localStorage.removeItem(bookmarkKey);
            }
            setIcon(bookmarkButton, 'fa fa-bookmark-o', 'fa fa-bookmark', active);
            updateBookmarkCount();
        });
    }

    // --------------------------------------------------------
    // 7. PER-THREAD BOOKMARK
    // --------------------------------------------------------
    document.querySelectorAll('.hd-bookmark-flag').forEach(function (button) {
        var key = 'heyday-thread-bookmark-' + button.dataset.discussionid;

        if (localStorage.getItem(key) === '1') {
            button.classList.add('is-bookmarked');
            button.setAttribute('aria-pressed', 'true');
            setIcon(button, 'fa fa-bookmark-o', 'fa fa-bookmark', true);
        } else {
            button.setAttribute('aria-pressed', 'false');
            setIcon(button, 'fa fa-bookmark-o', 'fa fa-bookmark', false);
        }

        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var active = !button.classList.contains('is-bookmarked');
            button.classList.toggle('is-bookmarked', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) {
                localStorage.setItem(key, '1');
            } else {
                localStorage.removeItem(key);
            }
            setIcon(button, 'fa fa-bookmark-o', 'fa fa-bookmark', active);
            updateBookmarkCount();
        });
    });

    updateBookmarkCount();

    // --------------------------------------------------------
    // 8. COLLAPSE / EXPAND THREADS
    // --------------------------------------------------------
    document.querySelectorAll('.hd-thread-top').forEach(function (top) {
        top.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button')) { return; }

            var thread = top.closest('.hd-thread');
            if (!thread) { return; }

            var body    = thread.querySelector('.hd-thread-body');
            var replies = thread.querySelectorAll('.hd-reply-preview');
            var toggle  = thread.querySelector('.hd-thread-toggle');

            var collapsed = !thread.classList.contains('is-collapsed');
            thread.classList.toggle('is-collapsed', collapsed);

            if (body) { body.hidden = collapsed; }
            replies.forEach(function (reply) { reply.hidden = collapsed; });
            if (toggle) { toggle.textContent = collapsed ? '›' : '⌄'; }
        });
    });

    // --------------------------------------------------------
    // 9. SEARCH
    // --------------------------------------------------------
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var query   = searchInput.value.toLowerCase().trim();
            var threads = Array.prototype.slice.call(document.querySelectorAll('.hd-thread'));

            threads.forEach(function (thread, index) {
                var text = thread.dataset.search || '';
                if (query.length > 0) {
                    thread.hidden = text.indexOf(query) === -1;
                } else {
                    thread.hidden = index >= 6;
                }
            });

            if (loadMoreButton) {
                loadMoreButton.hidden =
                    query.length > 0 ||
                    document.querySelectorAll('.hd-thread[hidden]').length === 0;
            }
        });
    }

    // --------------------------------------------------------
    // 10. SORT
    // --------------------------------------------------------
    if (sortButton && threadList) {
        sortButton.addEventListener('click', function () {
            var threads = Array.prototype.slice.call(threadList.querySelectorAll('.hd-thread'));
            threads.reverse().forEach(function (thread) { threadList.appendChild(thread); });
        });
    }

    // --------------------------------------------------------
    // 11. LOAD MORE
    // --------------------------------------------------------
    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', function () {
            var hidden = Array.prototype.slice.call(document.querySelectorAll('.hd-thread[hidden]'));
            hidden.slice(0, 6).forEach(function (thread) { thread.hidden = false; });
            if (document.querySelectorAll('.hd-thread[hidden]').length === 0) {
                loadMoreButton.hidden = true;
            }
        });
    }

    // --------------------------------------------------------
    // 12. NEXT UP CARD — fully clickable
    // --------------------------------------------------------
    document.querySelectorAll('.hd-nextup-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var href = card.getAttribute('href');
            if (href) { window.location.href = href; }
        });
    });
});
JS);

echo $OUTPUT->header();
?>

<style>
/* Compact TinyMCE toolbar override — same rules as view.php. */
.heyday-discussions-page .tox-tinymce {
    border: 1px solid #cfd8dc !important;
    border-radius: 0 !important;
}
.heyday-discussions-page .tox .tox-editor-header {
    box-shadow: none !important;
    border-bottom: 1px solid #d9e0e4 !important;
}
.heyday-discussions-page .tox .tox-toolbar,
.heyday-discussions-page .tox .tox-toolbar__primary {
    min-height: 34px !important;
    background: #f7f7f7 !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
}
.heyday-discussions-page .tox .tox-toolbar__group {
    padding: 2px 4px !important;
    border-right: 1px solid #dddddd !important;
    flex-wrap: nowrap !important;
    white-space: nowrap !important;
}
.heyday-discussions-page .tox .tox-toolbar__group:last-child {
    border-right: 0 !important;
}
.heyday-discussions-page .tox .tox-tbtn {
    width: 27px !important;
    height: 27px !important;
    margin: 0 1px !important;
    border-radius: 0 !important;
}
.heyday-discussions-page .tox .tox-tbtn svg {
    transform: scale(0.82) !important;
}
.heyday-discussions-page .tox .tox-tbtn--select {
    width: auto !important;
    min-width: 58px !important;
    max-width: 92px !important;
    height: 27px !important;
    padding: 0 4px !important;
}
.heyday-discussions-page .tox .tox-tbtn--select .tox-tbtn__select-label {
    font-size: 12px !important;
    max-width: 68px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.heyday-discussions-page .tox .tox-sidebar-wrap {
    min-height: 95px !important;
}
@media print {
    .hd-write-post,
    .hd-discussion-searchbar,
    .hd-sort-discussions,
    .hd-load-more-wrap,
    .hd-bottom-status,
    .hd-forum-toolbar { display: none !important; }
    .hd-forum-shell {
        border: 0 !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="hd-forum-shell">

    <!-- Top toolbar: back, bookmark, print, fullscreen -->
    <div class="hd-forum-toolbar">
        <a class="hd-icon-link hd-back-link"
           href="<?php echo $indexurl->out(false); ?>"
           aria-label="Back to Discussions">
            <i class="fa fa-arrow-left" aria-hidden="true"></i>
        </a>

        <button
            type="button"
            class="hd-icon-button hd-bookmark"
            aria-label="Bookmark this discussion"
            aria-pressed="false"
            data-lesson="<?php echo (int)$lesson; ?>"
        >
            <i class="fa fa-bookmark-o" aria-hidden="true"></i>
        </button>

        <div class="hd-toolbar-spacer"></div>

        <div class="hd-print-menu-wrap">
            <button type="button" class="hd-icon-button hd-print-toggle" aria-label="Print">
                <i class="fa fa-print" aria-hidden="true"></i>
            </button>
            <div class="hd-print-menu" hidden>
                <button type="button" class="hd-print-activity">Print/Save activity</button>
                <button type="button" class="hd-print-lesson">Print/Save entire lesson</button>
            </div>
        </div>

        <button type="button" class="hd-icon-button hd-fullscreen-toggle" aria-label="Fullscreen">
            <i class="fa fa-expand" aria-hidden="true"></i>
        </button>
    </div>

    <!-- Centered heading block -->
    <div class="hd-forum-heading">
        <div class="hd-course-title">
            <?php echo format_string($course->fullname); ?>
        </div>
        <div class="hd-lesson-title">
            Lesson <?php echo (int)$lesson; ?>
        </div>
        <h1>
            <?php echo format_string($forum->name); ?>
        </h1>
    </div>

    <!-- Forum intro / discussion prompt -->
    <?php if ($intro): ?>
        <div class="hd-forum-intro">
            <?php echo $intro; ?>
        </div>
    <?php endif; ?>

    <!-- Write your post -->
    <?php if ($canpost): ?>
        <form class="hd-write-post" method="post"
              action="<?php echo $PAGE->url->out(false); ?>">
            <input type="hidden" name="sesskey"    value="<?php echo sesskey(); ?>">
            <input type="hidden" name="submitpost"  value="1">

            <h2>
                <span aria-hidden="true">+</span>
                <?php echo get_string('writeyourpost', 'local_heyday_discussions'); ?>
            </h2>

            <input
                class="hd-post-title"
                name="subject"
                type="text"
                maxlength="255"
                placeholder="<?php echo get_string('entertitle', 'local_heyday_discussions'); ?>"
                required
            >

            <textarea
                id="hd_message"
                class="hd-post-message"
                name="message"
                rows="5"
                required
            ></textarea>

            <div class="hd-upload-line">
                <a href="<?php echo $standardposturl->out(false); ?>">
                    <i class="fa fa-upload" aria-hidden="true"></i>
                    <?php echo get_string('uploadfile', 'local_heyday_discussions'); ?>
                </a>
            </div>

            <div class="hd-post-actions">
                <button type="reset" class="btn btn-secondary">
                    <?php echo get_string('cancel', 'local_heyday_discussions'); ?>
                </button>
                <button type="submit" class="btn btn-success">
                    <?php echo get_string('submit', 'local_heyday_discussions'); ?>
                    <i class="fa fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            <?php echo get_string('cannotpost', 'local_heyday_discussions'); ?>
        </div>
    <?php endif; ?>

    <!-- Search bar -->
    <div class="hd-discussion-searchbar">
        <input
            type="search"
            class="hd-search-posts"
            placeholder="<?php echo get_string('searchposts', 'local_heyday_discussions'); ?>"
        >
        <button type="button" class="hd-search-button" aria-label="Search">
            <i class="fa fa-search" aria-hidden="true"></i>
        </button>
    </div>

    <!-- Section header + sort -->
    <div class="hd-discussion-section-title">
        <h2><?php echo get_string('discussions', 'local_heyday_discussions'); ?></h2>
        <button type="button" class="btn btn-primary hd-sort-discussions">
            <?php echo get_string('sortby', 'local_heyday_discussions'); ?>
            <i class="fa fa-angle-down" aria-hidden="true"></i>
        </button>
    </div>

    <!-- Thread list -->
    <div class="hd-thread-list">
        <?php if (!$discussiondata): ?>
            <div class="hd-empty-forum">
                <?php echo get_string('noposts', 'local_heyday_discussions'); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($discussiondata as $i => $discussion): ?>
            <article
                class="hd-thread"
                data-search="<?php echo s(core_text::strtolower(
                    $discussion['subject'] . ' ' . $discussion['excerpt'] . ' ' . $discussion['author']
                )); ?>"
                data-updated="<?php echo s($discussion['updated']); ?>"
                <?php echo $i >= 6 ? 'hidden' : ''; ?>
            >
                <div class="hd-thread-top">
                    <div class="hd-thread-toggle" aria-hidden="true">⌄</div>

                    <div class="hd-thread-meta">
                        <em>Posted by <?php echo s($discussion['author']); ?></em>
                        <h3><?php echo $discussion['subject']; ?></h3>
                    </div>

                    <div class="hd-thread-stats">
                        <?php echo (int)$discussion['replycount']; ?>
                        <?php echo (int)$discussion['replycount'] === 1 ? 'Reply' : 'Replies'; ?>
                        -
                        <?php echo (int)$discussion['newcount']; ?> New
                        <br>
                        <small><?php echo s($discussion['updated']); ?></small>
                    </div>

                    <button
                        type="button"
                        class="hd-bookmark-flag"
                        data-discussionid="<?php echo (int)$discussion['id']; ?>"
                        aria-label="Bookmark discussion"
                        aria-pressed="false"
                    >
                        <i class="fa fa-bookmark-o" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="hd-thread-body">
                    <?php echo $discussion['message']; ?>
                </div>

                <div class="hd-thread-actions">
                    <a href="<?php echo s($discussion['replyurl']); ?>">
                        <i class="fa fa-reply" aria-hidden="true"></i>
                        <?php echo get_string('reply', 'local_heyday_discussions'); ?>
                    </a>
                    <a href="#" class="hd-report-link">
                        <?php echo get_string('reportasinappropriate', 'local_heyday_discussions'); ?>
                    </a>
                    <span><?php echo s($discussion['updated']); ?></span>
                </div>

                <?php foreach ($discussion['replies'] as $reply): ?>
                    <div class="hd-reply-preview">
                        <div class="hd-reply-top">
                            <em>Posted by <?php echo s($reply['author']); ?></em>
                            <span><?php echo s($reply['posted']); ?></span>
                        </div>
                        <div class="hd-reply-message">
                            <?php echo $reply['message']; ?>
                        </div>
                        <div class="hd-thread-actions">
                            <a href="<?php echo s($reply['replyurl']); ?>">
                                <i class="fa fa-reply" aria-hidden="true"></i>
                                <?php echo get_string('reply', 'local_heyday_discussions'); ?>
                            </a>
                            <a href="#" class="hd-report-link">
                                <?php echo get_string('reportasinappropriate', 'local_heyday_discussions'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- Load more -->
    <?php if (count($discussiondata) > 6): ?>
        <div class="hd-load-more-wrap">
            <button type="button" class="btn btn-primary hd-load-more">
                <?php echo get_string('loadmorethreads', 'local_heyday_discussions', count($discussiondata) - 6); ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Next Up card -->
    <?php if ($next): ?>
        <div class="hd-nextup-wrapper">
            <div class="hd-endline">
                <span></span>
                <span><?php echo get_string('nextup', 'local_heyday_discussions'); ?></span>
                <span></span>
            </div>
            <a class="hd-nextup-card" href="<?php echo s($next['url']); ?>">
                <div class="hd-nextup-label">
                    <?php echo get_string('nextup', 'local_heyday_discussions'); ?>
                </div>
                <div class="hd-nextup-body">
                    <span class="hd-nextup-activity-name">
                        <?php echo s($next['name']); ?>
                    </span>
                    <div class="hd-nextup-activity-type">
                        <?php echo s($next['type']); ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endif; ?>

    <!-- Mine / New / Bookmarked status bar -->
    <div class="hd-bottom-status">
        <span class="hd-status-pill green"><?php echo (int)$minecount; ?></span>
        <?php echo get_string('mine', 'local_heyday_discussions'); ?>

        <span class="hd-status-pill red"><?php echo (int)$totalnew; ?></span>
        <?php echo get_string('new', 'local_heyday_discussions'); ?>

        <span class="hd-status-pill blue hd-bookmarked-count">0</span>
        <?php echo get_string('bookmarked', 'local_heyday_discussions'); ?>
    </div>

</div>

<?php
echo $OUTPUT->footer();

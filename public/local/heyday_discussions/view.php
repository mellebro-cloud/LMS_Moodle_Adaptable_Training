<?php
// Custom learner-facing view for a single Moodle Forum activity.
// Path: /local/heyday_discussions/view.php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/editorlib.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

$forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
    throw new moodle_exception('activityiscurrentlyhidden');
}

$PAGE->set_url(new moodle_url('/local/heyday_discussions/view.php', ['cmid' => $cm->id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('course');
$PAGE->set_title(format_string($forum->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('heyday-discussions-page');

$PAGE->requires->css(new moodle_url('/local/heyday_discussions/styles.css'));

/*
 * Load Moodle TinyMCE editor assets.
 * The JavaScript below will reconfigure it into the compact one-row toolbar.
 */
$editoroptions = [
    'context' => $context,
    'autosave' => false,
    'enable_filemanagement' => false,
];

$editor = editors_get_preferred_editor(FORMAT_HTML);
$editor->use_editor('hd_message', $editoroptions);

$canpost = has_capability('mod/forum:startdiscussion', $context);

/*
 * Handle new discussion post.
 */
if (optional_param('submitpost', 0, PARAM_BOOL) && confirm_sesskey()) {
    if (!$canpost) {
        throw new moodle_exception('nopermissions', 'error', '', 'post in this forum');
    }

    $subject = required_param('subject', PARAM_TEXT);
    $message = required_param('message', PARAM_RAW_TRIMMED);

    if (trim($subject) === '' || trim($message) === '') {
        redirect(
            $PAGE->url,
            'Please enter both a title and a message.',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $discussion = new stdClass();
    $discussion->course = $course->id;
    $discussion->forum = $forum->id;
    $discussion->name = $subject;
    $discussion->subject = $subject;
    $discussion->message = clean_text($message, FORMAT_HTML);
    $discussion->messageformat = FORMAT_HTML;
    $discussion->messagetrust = 0;
    $discussion->mailnow = 0;
    $discussion->groupid = -1;
    $discussion->timestart = 0;
    $discussion->timeend = 0;
    $discussion->pinned = 0;

    $unusedform = null;
    $unusedmessage = null;

    forum_add_discussion($discussion, $unusedform, $unusedmessage);

    redirect(
        $PAGE->url,
        'Your post has been submitted.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

/**
 * Get lesson label from forum/activity name.
 *
 * @param string $name
 * @return string
 */
function local_heyday_discussions_lesson_label(string $name): string {
    if (preg_match('/(Lesson\s*\d+[^:]*)(?:\s*Discussion\s*Area)?/i', $name, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

/**
 * Return shortened plain text excerpt.
 *
 * @param string $html
 * @param int $length
 * @return string
 */
function local_heyday_discussions_excerpt(string $html, int $length = 450): string {
    $text = trim(html_to_text($html, 0, false));
    return shorten_text($text, $length, true);
}

/**
 * Find next visible activity for the Next Up card.
 *
 * @param stdClass $course
 * @param int $currentcmid
 * @param int $userid
 * @return array|null
 */
function local_heyday_discussions_next_activity(stdClass $course, int $currentcmid, int $userid): ?array {
    $modinfo = get_fast_modinfo($course, $userid);
    $cms = array_values($modinfo->get_cms());

    $found = false;

    foreach ($cms as $candidate) {
        if ($found && $candidate->uservisible && !empty($candidate->url)) {
            return [
                'name' => format_string($candidate->name),
                'url' => $candidate->url->out(false),
                'type' => $candidate->modname,
            ];
        }

        if ((int)$candidate->id === $currentcmid) {
            $found = true;
        }
    }

    return null;
}

$lessonlabel = local_heyday_discussions_lesson_label($forum->name);
$intro = format_module_intro('forum', $forum, $cm->id);

$discussions = $DB->get_records(
    'forum_discussions',
    ['forum' => $forum->id],
    'timemodified DESC'
);

$discussiondata = [];
$totalnew = 0;
$minecount = 0;

$lastaccess = (int)$DB->get_field(
    'user_lastaccess',
    'timeaccess',
    [
        'userid' => $USER->id,
        'courseid' => $course->id,
    ],
    IGNORE_MISSING
);

foreach ($discussions as $discussion) {
    $posts = $DB->get_records(
        'forum_posts',
        ['discussion' => $discussion->id],
        'created ASC'
    );

    if (!$posts) {
        continue;
    }

    $firstpost = $posts[$discussion->firstpost] ?? reset($posts);
    $author = $DB->get_record('user', ['id' => $firstpost->userid], '*', MUST_EXIST);

    $replies = [];
    $replycount = 0;
    $newcount = 0;

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
                    'author' => $replyauthor ? fullname($replyauthor) : 'Anonymous',
                    'message' => format_text(
                        $post->message,
                        $post->messageformat,
                        ['context' => $context]
                    ),
                    'posted' => userdate($post->created, '%b %d, %Y %I:%M %p'),
                    'replyurl' => (new moodle_url('/mod/forum/post.php', ['reply' => $post->id]))->out(false),
                ];
            }
        }
    }

    $totalnew += $newcount;

    $discussiondata[] = [
        'id' => $discussion->id,
        'subject' => format_string($discussion->name),
        'author' => fullname($author),
        'message' => format_text(
            $firstpost->message,
            $firstpost->messageformat,
            ['context' => $context]
        ),
        'excerpt' => local_heyday_discussions_excerpt($firstpost->message, 500),
        'replycount' => $replycount,
        'newcount' => $newcount,
        'updated' => userdate($discussion->timemodified, '%b %d, %Y %I:%M %p'),
        'replyurl' => (new moodle_url('/mod/forum/post.php', ['reply' => $firstpost->id]))->out(false),
        'replies' => $replies,
    ];
}

$next = local_heyday_discussions_next_activity($course, $cm->id, $USER->id);

$standardposturl = new moodle_url('/mod/forum/post.php', ['forum' => $forum->id]);
$indexurl = new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id]);

$PAGE->requires->js_init_code(<<<'JS'
document.addEventListener('DOMContentLoaded', function () {

    /*
     * =====================================================
     * 1. COMPACT ONE-ROW TINYMCE TOOLBAR
     * This is the important part for your editor toolbar.
     * It prevents the wrapped two-line toolbar layout.
     * =====================================================
     */

    var heydayEditorInitialised = false;

    function heydayLoadFullEditorToolbar() {
        var tiny = window.tinymce || window.tinyMCE;
        var textarea = document.getElementById('hd_message');

        if (!textarea) {
            return;
        }

        if (!tiny) {
            setTimeout(heydayLoadFullEditorToolbar, 300);
            return;
        }

        if (heydayEditorInitialised) {
            return;
        }

        var existingEditor = tiny.get('hd_message');

        if (existingEditor) {
            existingEditor.remove();
        }

        heydayEditorInitialised = true;

        tiny.init({
            selector: '#hd_message',

            menubar: false,
            branding: false,
            promotion: false,
            statusbar: false,
            resize: true,
            height: 115,

            /*
             * IMPORTANT:
             * Do not use toolbar_mode: "wrap".
             * "wrap" is what creates your second screenshot.
             */
            toolbar_mode: 'sliding',
            toolbar_sticky: false,

            plugins: 'lists link table charmap fullscreen help hr',

            /*
             * Compact order like your first screenshot:
             * B, I, numbered list, bullet list, outdent, indent,
             * quote, link, unlink, equation, table, horizontal line,
             * special character, Styles, Normal, Font, Size,
             * fullscreen, help.
             */
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
                'Font=Arial,Helvetica,sans-serif; ' +
                'Arial=Arial,Helvetica,sans-serif; ' +
                'Calibri=Calibri,sans-serif; ' +
                'Courier New=Courier New,Courier,monospace; ' +
                'Georgia=Georgia,serif; ' +
                'Tahoma=Tahoma,Geneva,sans-serif; ' +
                'Times New Roman=Times New Roman,Times,serif; ' +
                'Verdana=Verdana,Geneva,sans-serif',

            font_size_formats:
                '8pt 9pt 10pt 11pt 12pt 14pt 18pt 24pt 36pt',

            style_formats: [
                {
                    title: 'Styles',
                    items: [
                        {
                            title: 'Normal text',
                            block: 'p'
                        },
                        {
                            title: 'Italic title',
                            block: 'p',
                            classes: 'hd-style-italic-title'
                        },
                        {
                            title: 'Subtitle',
                            block: 'p',
                            classes: 'hd-style-subtitle'
                        },
                        {
                            title: 'Special container',
                            block: 'div',
                            classes: 'hd-style-special-container',
                            wrapper: true
                        }
                    ]
                }
            ],

            content_style:
                'body {' +
                    'font-family: Arial, Helvetica, sans-serif;' +
                    'font-size: 14px;' +
                    'margin: 8px;' +
                '}' +
                '.hd-style-italic-title {' +
                    'font-style: italic;' +
                    'font-weight: 600;' +
                    'font-size: 18px;' +
                '}' +
                '.hd-style-subtitle {' +
                    'font-size: 16px;' +
                    'color: #555;' +
                '}' +
                '.hd-style-special-container {' +
                    'border: 1px solid #d7d7d7;' +
                    'background: #f7f7f7;' +
                    'padding: 10px;' +
                    'margin: 8px 0;' +
                '}',

            setup: function (editor) {
                editor.ui.registry.addButton('hdmath', {
                    text: 'Σ',
                    tooltip: 'Equation',
                    onAction: function () {
                        var equation = window.prompt(
                            'Enter equation, for example: x^2 + y^2 = z^2'
                        );

                        if (equation && equation.trim() !== '') {
                            editor.insertContent('\\(' + equation.trim() + '\\)');
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

                if (activeEditor) {
                    activeEditor.save();
                }
            });
        }
    }

    setTimeout(heydayLoadFullEditorToolbar, 800);


    /*
     * =====================================================
     * 2. PAGE ELEMENTS
     * =====================================================
     */

    var shell = document.querySelector('.hd-forum-shell');

    var printToggle = document.querySelector('.hd-print-toggle');
    var printMenu = document.querySelector('.hd-print-menu');

    var fullscreenButton = document.querySelector('.hd-fullscreen-toggle');
    var bookmarkButton = document.querySelector('.hd-bookmark');

    var searchInput = document.querySelector('.hd-search-posts');
    var sortButton = document.querySelector('.hd-sort-discussions');
    var loadMoreButton = document.querySelector('.hd-load-more');
    var threadList = document.querySelector('.hd-thread-list');
    var bookmarkCountPill = document.querySelector('.hd-bookmarked-count');


    /*
     * =====================================================
     * 3. SMALL HELPERS
     * =====================================================
     */

    function setIcon(button, inactiveClass, activeClass, active) {
        if (!button) {
            return;
        }

        var icon = button.querySelector('i');

        if (!icon) {
            return;
        }

        icon.className = active ? activeClass : inactiveClass;
    }

    function updateBookmarkCount() {
        if (!bookmarkCountPill) {
            return;
        }

        var count = 0;

        document.querySelectorAll('.hd-bookmark.is-bookmarked, .hd-bookmark-flag.is-bookmarked').forEach(function () {
            count++;
        });

        bookmarkCountPill.textContent = String(count);
    }


    /*
     * =====================================================
     * 4. PRINT DROPDOWN
     * =====================================================
     */

    if (printToggle && printMenu) {
        printToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            printMenu.hidden = !printMenu.hidden;
        });

        printMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function () {
            printMenu.hidden = true;
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                printMenu.hidden = true;
            }
        });
    }

    document.querySelectorAll('.hd-print-activity, .hd-print-lesson').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            if (printMenu) {
                printMenu.hidden = true;
            }

            window.print();
        });
    });


    /*
     * =====================================================
     * 5. FULLSCREEN BUTTON
     * =====================================================
     */

    if (fullscreenButton && shell) {
        fullscreenButton.addEventListener('click', function (e) {
            e.preventDefault();

            if (!document.fullscreenElement) {
                if (shell.requestFullscreen) {
                    shell.requestFullscreen();
                } else if (shell.webkitRequestFullscreen) {
                    shell.webkitRequestFullscreen();
                } else if (shell.msRequestFullscreen) {
                    shell.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        });
    }


    /*
     * =====================================================
     * 6. MAIN BOOKMARK / LIKE TOGGLE
     * =====================================================
     */

    if (bookmarkButton) {
        var bookmarkKey = 'heyday-discussion-bookmark-' + bookmarkButton.dataset.cmid;

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


    /*
     * =====================================================
     * 7. INDIVIDUAL DISCUSSION BOOKMARK TOGGLE
     * =====================================================
     */

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


    /*
     * =====================================================
     * 8. COLLAPSE / EXPAND DISCUSSION THREADS
     * =====================================================
     */

    document.querySelectorAll('.hd-thread-top').forEach(function (top) {
        top.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button')) {
                return;
            }

            var thread = top.closest('.hd-thread');

            if (!thread) {
                return;
            }

            var body = thread.querySelector('.hd-thread-body');
            var replies = thread.querySelectorAll('.hd-reply-preview');
            var toggle = thread.querySelector('.hd-thread-toggle');

            var collapsed = !thread.classList.contains('is-collapsed');

            thread.classList.toggle('is-collapsed', collapsed);

            if (body) {
                body.hidden = collapsed;
            }

            replies.forEach(function (reply) {
                reply.hidden = collapsed;
            });

            if (toggle) {
                toggle.textContent = collapsed ? '›' : '⌄';
            }
        });
    });


    /*
     * =====================================================
     * 9. SEARCH DISCUSSIONS
     * =====================================================
     */

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var query = searchInput.value.toLowerCase().trim();
            var threads = Array.prototype.slice.call(document.querySelectorAll('.hd-thread'));

            threads.forEach(function (thread, index) {
                var searchableText = thread.dataset.search || '';

                if (query.length > 0) {
                    thread.hidden = searchableText.indexOf(query) === -1;
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


    /*
     * =====================================================
     * 10. SORT BUTTON
     * =====================================================
     */

    if (sortButton && threadList) {
        sortButton.addEventListener('click', function () {
            var threads = Array.prototype.slice.call(threadList.querySelectorAll('.hd-thread'));

            threads.reverse().forEach(function (thread) {
                threadList.appendChild(thread);
            });
        });
    }


    /*
     * =====================================================
     * 11. LOAD MORE THREADS
     * =====================================================
     */

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', function () {
            var hiddenThreads = Array.prototype.slice.call(document.querySelectorAll('.hd-thread[hidden]'));

            hiddenThreads.slice(0, 6).forEach(function (thread) {
                thread.hidden = false;
            });

            if (document.querySelectorAll('.hd-thread[hidden]').length === 0) {
                loadMoreButton.hidden = true;
            }
        });
    }


    /*
     * =====================================================
     * 12. FULLY CLICKABLE NEXT UP CARD
     * =====================================================
     */

    document.querySelectorAll('.hd-nextup-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var href = card.getAttribute('href');

            if (href) {
                window.location.href = href;
            }
        });
    });

});
JS);

echo $OUTPUT->header();
?>

<style>
/* =========================================================
   Compact one-row TinyMCE toolbar inside this custom page
   ========================================================= */

.heyday-discussions-page .tox-tinymce {
    border: 1px solid #cfd8dc !important;
    border-radius: 0 !important;
}

.heyday-discussions-page .tox .tox-editor-header {
    box-shadow: none !important;
    border-bottom: 1px solid #d9e0e4 !important;
}

.heyday-discussions-page .tox .tox-toolbar-overlord {
    background: #f7f7f7 !important;
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

.heyday-discussions-page .tox .tox-edit-area {
    border-top: 0 !important;
}

.heyday-discussions-page .tox .tox-edit-area__iframe {
    background: #ffffff !important;
}

.heyday-discussions-page .tox .tox-sidebar-wrap {
    min-height: 95px !important;
}

/* Print mode */
@media print {
    .hd-write-post,
    .hd-discussion-searchbar,
    .hd-sort-discussions,
    .hd-load-more-wrap,
    .hd-bottom-status,
    .hd-forum-toolbar {
        display: none !important;
    }

    .hd-forum-shell {
        border: 0 !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="hd-forum-shell">

    <div class="hd-forum-toolbar">
        <a class="hd-icon-link hd-back-link" href="<?php echo $indexurl->out(false); ?>" aria-label="Back">
            <i class="fa fa-arrow-left" aria-hidden="true"></i>
        </a>

        <button
            type="button"
            class="hd-icon-button hd-bookmark"
            aria-label="Bookmark"
            aria-pressed="false"
            data-cmid="<?php echo (int)$cm->id; ?>"
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

    <div class="hd-forum-heading">
        <div class="hd-course-title">
            <?php echo format_string($course->fullname); ?>
        </div>

        <?php if ($lessonlabel): ?>
            <div class="hd-lesson-title">
                <?php echo s($lessonlabel); ?>
            </div>
        <?php endif; ?>

        <h1><?php echo format_string($forum->name); ?></h1>
    </div>

    <div class="hd-forum-intro">
        <?php echo $intro; ?>
    </div>

    <?php if ($canpost): ?>
        <form class="hd-write-post" method="post" action="<?php echo $PAGE->url->out(false); ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <input type="hidden" name="submitpost" value="1">

            <h2>
                <span aria-hidden="true">+</span>
                Write your post
            </h2>

            <input
                class="hd-post-title"
                name="subject"
                type="text"
                maxlength="255"
                placeholder="Enter a title"
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
                    Upload File
                </a>
            </div>

            <div class="hd-post-actions">
                <button type="reset" class="btn btn-secondary">
                    Cancel
                </button>

                <button type="submit" class="btn btn-success">
                    Submit
                    <i class="fa fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            You do not have permission to post in this forum.
        </div>
    <?php endif; ?>

    <div class="hd-discussion-searchbar">
        <input
            type="search"
            class="hd-search-posts"
            placeholder="Search for posts..."
        >

        <button type="button" class="hd-search-button" aria-label="Search">
            <i class="fa fa-search" aria-hidden="true"></i>
        </button>
    </div>

    <div class="hd-discussion-section-title">
        <h2>Discussions</h2>

        <button type="button" class="btn btn-primary hd-sort-discussions">
            Sort By
            <i class="fa fa-angle-down" aria-hidden="true"></i>
        </button>
    </div>

    <div class="hd-thread-list">
        <?php if (!$discussiondata): ?>
            <div class="hd-empty-forum">
                There are no discussion topics yet in this forum.
            </div>
        <?php endif; ?>

        <?php foreach ($discussiondata as $i => $discussion): ?>
            <article
                class="hd-thread"
                data-search="<?php echo s(core_text::strtolower($discussion['subject'] . ' ' . $discussion['excerpt'] . ' ' . $discussion['author'])); ?>"
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
                        Reply
                    </a>

                    <a href="#" class="hd-report-link">
                        Report as Inappropriate
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
                                Reply
                            </a>

                            <a href="#" class="hd-report-link">
                                Report as Inappropriate
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (count($discussiondata) > 6): ?>
        <div class="hd-load-more-wrap">
            <button type="button" class="btn btn-primary hd-load-more">
                Load <?php echo (int)(count($discussiondata) - 6); ?> more thread
            </button>
        </div>
    <?php endif; ?>

    <?php if ($next): ?>
        <div class="hd-nextup-wrapper">
            <div class="hd-endline">
                <span></span>
                <span>Next Up</span>
                <span></span>
            </div>

            <a class="hd-nextup-card" href="<?php echo s($next['url']); ?>">
                <div class="hd-nextup-label">
                    Next Up
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

    <div class="hd-bottom-status">
        <span class="hd-status-pill green">0</span>
        Mine

        <span class="hd-status-pill green"><?php echo (int)$minecount; ?></span>

        <span class="hd-status-pill red"><?php echo (int)$totalnew; ?></span>
        New

        <span class="hd-status-pill blue hd-bookmarked-count">0</span>
        Bookmarked
    </div>

</div>

<?php
echo $OUTPUT->footer();
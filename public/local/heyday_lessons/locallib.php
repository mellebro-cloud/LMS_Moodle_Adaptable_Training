<?php
// Shared renderer for the Heyday ed2go-style learner player shell.
// Other local plugins can require this file and call local_heyday_lessons_render_shell().

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Read plugin configuration with a sane fallback.
 *
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function local_heyday_lessons_cfg(string $name, $default = '') {
    $value = get_config('local_heyday_lessons', $name);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

/**
 * Basic CSS color whitelist for admin-configured colors.
 *
 * @param string $value
 * @param string $fallback
 * @return string
 */
function local_heyday_lessons_css_color(string $value, string $fallback): string {
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
        return $value;
    }
    if (preg_match('/^[a-zA-Z]+$/', $value)) {
        return $value;
    }
    return $fallback;
}

/**
 * Build URL to this player.
 *
 * @param int $courseid
 * @param array $params
 * @return moodle_url
 */
function local_heyday_lessons_url(int $courseid, array $params = []): moodle_url {
    $params = ['id' => $courseid] + $params;
    return new moodle_url('/local/heyday_lessons/index.php', $params);
}

/**
 * Strip HTML and normalize whitespace for sidebar labels.
 *
 * @param string $text
 * @return string
 */
function local_heyday_lessons_plain(string $text): string {
    $text = html_to_text($text, 0, false);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim((string)$text);
}

/**
 * Apply optional JSON title replacements from admin settings.
 *
 * @param string $title
 * @return string
 */
function local_heyday_lessons_map_title(string $title): string {
    $title = trim($title);
    $mapraw = (string)local_heyday_lessons_cfg('menutitlemap', '');
    if ($mapraw === '') {
        return $title;
    }
    $map = json_decode($mapraw, true);
    if (!is_array($map)) {
        return $title;
    }
    if (isset($map[$title]) && is_string($map[$title])) {
        return trim($map[$title]);
    }
    foreach ($map as $needle => $replacement) {
        if (!is_string($needle) || !is_string($replacement)) {
            continue;
        }
        if ($needle !== '' && stripos($title, $needle) !== false) {
            return trim($replacement);
        }
    }
    return $title;
}

/**
 * Test if a section/activity title is a special learner shell item.
 *
 * @param string $title
 * @return string scores|discussions|lesson|gettingstarted|pretest|resources|final|other
 */
function local_heyday_lessons_classify_title(string $title): string {
    $plain = core_text::strtolower(local_heyday_lessons_plain($title));
    if ($plain === '') {
        return 'other';
    }
    if (preg_match('/^scores?$/', $plain)) {
        return 'scores';
    }
    if (preg_match('/^discussions?$/', $plain)) {
        return 'discussions';
    }
    if (preg_match('/\bgetting\s+started\b|\bcourse\s+overview\b|\bsyllabus\b/', $plain)) {
        return 'gettingstarted';
    }
    if (preg_match('/\bpre\s*-?\s*test\b|\bpretest\b/', $plain)) {
        return 'pretest';
    }
    if (preg_match('/\bresource(s)?\b|further\s+learning/', $plain)) {
        return 'resources';
    }
    if (preg_match('/\bfinal\s+exam\b|\bfinal\b/', $plain)) {
        return 'final';
    }
    if (preg_match('/\blesson\s*\d+\b/', $plain)) {
        return 'lesson';
    }
    return 'other';
}

/**
 * Normalized key used for de-duplication and pattern matching.
 *
 * @param string $text
 * @return string
 */
function local_heyday_lessons_menu_key(string $text): string {
    $text = core_text::strtolower(local_heyday_lessons_plain($text));
    $text = preg_replace('/&amp;|&/', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    return trim((string)preg_replace('/\s+/', ' ', $text));
}

/**
 * Extract a lesson number from a title.
 *
 * @param string $text
 * @return int
 */
function local_heyday_lessons_lesson_number(string $text): int {
    if (preg_match('/\blesson\s*(\d+)\b/i', local_heyday_lessons_plain($text), $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

/**
 * Test whether a title is only a generic Lesson N container.
 *
 * @param string $text
 * @return bool
 */
function local_heyday_lessons_is_generic_lesson_title(string $text): bool {
    return (bool)preg_match('/^\s*lesson\s*\d+\s*$/i', local_heyday_lessons_plain($text));
}

/**
 * Configurable menu ignore patterns. One plain text or regex pattern per line.
 *
 * @return array
 */
function local_heyday_lessons_ignore_patterns(): array {
    $defaults = [
        '/^new\s+subsection$/i',
        '/^scores?$/i',
        '/^discussions?$/i',
        '/^course\s+dashboard$/i',
    ];
    $raw = (string)local_heyday_lessons_cfg('ignoredmenupatterns', '');
    if (trim($raw) === '') {
        return $defaults;
    }
    $patterns = $defaults;
    foreach (preg_split('/\R/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $patterns[] = $line;
    }
    return $patterns;
}

/**
 * Is this activity/subsection title noise that should not appear in the learner menu?
 *
 * @param string $title
 * @return bool
 */
function local_heyday_lessons_is_ignored_menu_title(string $title): bool {
    $plain = local_heyday_lessons_plain($title);
    if ($plain === '') {
        return true;
    }
    foreach (local_heyday_lessons_ignore_patterns() as $pattern) {
        $pattern = trim((string)$pattern);
        if ($pattern === '') {
            continue;
        }
        if (@preg_match($pattern, '') !== false) {
            if (preg_match($pattern, $plain)) {
                return true;
            }
            continue;
        }
        if (core_text::strtolower($plain) === core_text::strtolower($pattern)) {
            return true;
        }
    }
    return false;
}

/**
 * Create ed2go-style sidebar labels from common Moodle activity names.
 *
 * @param string $name
 * @param int $lessonno
 * @param bool $firstmeaningful
 * @return string
 */
function local_heyday_lessons_normalize_item_label(string $name, int $lessonno, bool $firstmeaningful = false): string {
    $plain = local_heyday_lessons_plain($name);
    if ($lessonno <= 0) {
        return $plain;
    }

    // A page named "Lesson 1: Introduction to Machine Learning" is normally the lesson introduction row.
    if (preg_match('/^lesson\s*' . $lessonno . '\s*[:\-–—]\s*(.+)$/i', $plain, $matches)) {
        return 'Lesson ' . $lessonno . ' Introduction';
    }

    if ($firstmeaningful && preg_match('/^(introduction|lesson\s*content|overview)$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Introduction';
    }

    if (preg_match('/^lesson\s*content$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Introduction';
    }
    if (preg_match('/^review$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Review';
    }
    if (preg_match('/^assignment$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Assignment';
    }
    if (preg_match('/^quiz$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Quiz';
    }
    if (preg_match('/^discussion(\s+area)?$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Discussion Area';
    }
    if (preg_match('/^faqs?$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' FAQs';
    }

    // Standardize existing lesson-prefixed activities without changing the course-specific wording.
    if (preg_match('/^lesson\s*' . $lessonno . '\s+discussion(\s+area)?$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Discussion Area';
    }
    if (preg_match('/^lesson\s*' . $lessonno . '\s+quiz$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Quiz';
    }
    if (preg_match('/^lesson\s*' . $lessonno . '\s+assignment$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Assignment';
    }
    if (preg_match('/^lesson\s*' . $lessonno . '\s+review$/i', $plain)) {
        return 'Lesson ' . $lessonno . ' Review';
    }

    return $plain;
}

/**
 * Normalize a lesson group into a clean reusable ed2go-style submenu.
 *
 * This does not rebuild the sidebar in JavaScript; it only cleans the server-rendered PHP data.
 *
 * @param array $group
 * @return array
 */
function local_heyday_lessons_normalize_lesson_group(array $group): array {
    $lessonno = local_heyday_lessons_lesson_number((string)$group['title']);
    if ($lessonno <= 0 && !empty($group['sectionnum'])) {
        $lessonno = (int)$group['sectionnum'];
    }

    // If the Moodle section is only "Lesson N", promote the first "Lesson N: Real Title" activity to the group title.
    if ($lessonno > 0 && local_heyday_lessons_is_generic_lesson_title((string)$group['title'])) {
        foreach ($group['items'] as $item) {
            $candidate = local_heyday_lessons_plain((string)$item['name']);
            if (preg_match('/^lesson\s*' . $lessonno . '\s*[:\-–—]\s*(.+)$/i', $candidate, $matches)) {
                $group['title'] = 'Lesson ' . $lessonno . ': ' . trim($matches[1]);
                break;
            }
        }
    }

    $clean = [];
    $seen = [];
    $firstmeaningful = true;

    foreach ($group['items'] as $item) {
        $rawname = local_heyday_lessons_plain((string)$item['name']);
        if (local_heyday_lessons_is_ignored_menu_title($rawname)) {
            continue;
        }

        $item['name'] = local_heyday_lessons_normalize_item_label($rawname, $lessonno, $firstmeaningful);
        $firstmeaningful = false;

        // Re-evaluate heading after renaming. Do not make intro rows look like chapters.
        $key = local_heyday_lessons_menu_key((string)$item['name']);
        if ($key === '') {
            continue;
        }

        // Avoid duplicated Moodle activities such as repeated Discussion Area or repeated generated intro pages.
        if (isset($seen[$key])) {
            $previousindex = $seen[$key];
            if (!empty($item['current']) && empty($clean[$previousindex]['current'])) {
                $clean[$previousindex] = $item;
            } else if ($clean[$previousindex]['status'] === 'locked' && $item['status'] !== 'locked') {
                $clean[$previousindex] = $item;
            }
            continue;
        }

        $seen[$key] = count($clean);
        $clean[] = $item;
    }

    $group['items'] = $clean;

    $hascurrent = false;
    foreach ($clean as $item) {
        if (!empty($item['current'])) {
            $hascurrent = true;
            break;
        }
    }
    if ($hascurrent) {
        $group['active'] = true;
        $group['locked'] = false;
    }
    $group['status'] = local_heyday_lessons_group_status($clean, !empty($group['locked']));

    // Link the lesson row to the first visible submenu item after filtering.
    foreach ($clean as $item) {
        if (empty($item['locked']) && !empty($item['url'])) {
            $group['url'] = $item['url'];
            break;
        }
    }

    return $group;
}

/**
 * Is the activity a heading/chapter row in the ed2go-style sidebar?
 *
 * @param cm_info $cm
 * @param string $name
 * @return bool
 */
function local_heyday_lessons_is_chapter_row(cm_info $cm, string $name): bool {
    $plain = core_text::strtolower(local_heyday_lessons_plain($name));
    if ($cm->modname === 'label') {
        return true;
    }
    return (bool)preg_match('/^(chapter|part|unit|section)\s*\d+\b|^chapter\s*\d+\s*:/', $plain);
}

/**
 * Return completion status for a course module.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @return string completed|incomplete|locked|none
 */
function local_heyday_lessons_cm_status(stdClass $course, cm_info $cm): string {
    global $USER;

    if (!$cm->uservisible) {
        return 'locked';
    }

    global $CFG;
    if (empty($CFG->enablecompletion)) {
        return 'none';
    }

    $completion = new completion_info($course);
    if (!$completion->is_enabled($cm)) {
        return 'none';
    }

    $data = $completion->get_data($cm, false, $USER->id);
    if (!empty($data->completionstate) && in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
        return 'completed';
    }

    return 'incomplete';
}

/**
 * Build a module item array for the sidebar.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param int $currentcmid
 * @return array
 */
function local_heyday_lessons_item_from_cm(stdClass $course, cm_info $cm, int $currentcmid): array {
    $name = local_heyday_lessons_map_title(format_string($cm->name, true, ['context' => context_module::instance($cm->id)]));
    $isheading = local_heyday_lessons_is_chapter_row($cm, $name);
    $status = local_heyday_lessons_cm_status($course, $cm);
    $url = $cm->uservisible ? local_heyday_lessons_url((int)$course->id, ['cmid' => $cm->id]) : null;

    return [
        'cmid' => $cm->id,
        'modname' => $cm->modname,
        'name' => $name,
        'isheading' => $isheading,
        'status' => $status,
        'locked' => !$cm->uservisible,
        'current' => $currentcmid === (int)$cm->id,
        'url' => $url,
        'indent' => max(0, min(3, (int)$cm->indent)),
    ];
}

/**
 * Section label with Moodle fallback.
 *
 * @param stdClass $course
 * @param section_info $section
 * @return string
 */
function local_heyday_lessons_section_title(stdClass $course, section_info $section): string {
    $name = trim((string)get_section_name($course, $section));
    if ($name === '') {
        $name = 'Lesson ' . (int)$section->section;
    }
    return local_heyday_lessons_map_title(format_string($name, true, ['context' => context_course::instance($course->id)]));
}

/**
 * Return an aggregate section status.
 *
 * @param array $items
 * @param bool $sectionlocked
 * @return string completed|locked|incomplete|none
 */
function local_heyday_lessons_group_status(array $items, bool $sectionlocked): string {
    if ($sectionlocked) {
        return 'locked';
    }
    $trackable = 0;
    $completed = 0;
    $locked = 0;
    foreach ($items as $item) {
        if ($item['status'] === 'locked') {
            $locked++;
            continue;
        }
        if ($item['status'] === 'completed' || $item['status'] === 'incomplete') {
            $trackable++;
        }
        if ($item['status'] === 'completed') {
            $completed++;
        }
    }
    if ($trackable > 0 && $trackable === $completed) {
        return 'completed';
    }
    if ($trackable > 0 || $locked > 0) {
        return 'incomplete';
    }
    return 'none';
}

/**
 * Build course navigation structure from Moodle sections and activities.
 *
 * @param stdClass $course
 * @param int $currentcmid
 * @return array
 */
function local_heyday_lessons_build_structure(stdClass $course, int $currentcmid = 0): array {
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->cms;
    $sectionsinfo = $modinfo->get_section_info_all();
    $activegroupkey = '';

    $structure = [
        'gettingstarted' => null,
        'pretest' => null,
        'scores' => null,
        'discussions' => null,
        'lessons' => [],
        'resources' => null,
        'final' => null,
        'other' => [],
        'flat' => [],
    ];

    foreach ($sectionsinfo as $sectionnum => $sectioninfo) {
        if ((int)$sectionnum === 0) {
            continue;
        }

        $title = local_heyday_lessons_section_title($course, $sectioninfo);
        $kind = local_heyday_lessons_classify_title($title);
        $sectioncms = $modinfo->sections[$sectionnum] ?? [];
        $items = [];

        foreach ($sectioncms as $cmid) {
            if (empty($cms[$cmid])) {
                continue;
            }
            $cm = $cms[$cmid];
            if (!$cm->has_view() && $cm->modname !== 'label') {
                continue;
            }
            // Respect Moodle visibility, but still show restricted items with locks if Moodle exposes them to the user.
            if (!$cm->visible && !$cm->uservisible && empty($cm->availableinfo)) {
                continue;
            }
            $items[] = local_heyday_lessons_item_from_cm($course, $cm, $currentcmid);
        }

        $active = false;
        foreach ($items as $item) {
            if ($item['current']) {
                $active = true;
                break;
            }
        }

        $sectionlocked = false;
        if (isset($sectioninfo->uservisible) && !$sectioninfo->uservisible) {
            $sectionlocked = true;
        }
        if ($active) {
            // A section containing the current visible activity should never show as locked in the learner sidebar.
            $sectionlocked = false;
        }

        $group = [
            'sectionnum' => (int)$sectionnum,
            'title' => $title,
            'kind' => $kind,
            'items' => $items,
            'active' => $active,
            'locked' => $sectionlocked,
            'status' => local_heyday_lessons_group_status($items, $sectionlocked),
            'url' => null,
        ];

        foreach ($items as $item) {
            if (!empty($item['url']) && !$item['locked']) {
                $group['url'] = $item['url'];
                break;
            }
        }
        if ($group['url'] === null) {
            $group['url'] = local_heyday_lessons_url((int)$course->id, ['section' => (int)$sectionnum]);
        }

        if ($kind === 'lesson' || ($kind === 'other' && !empty($items))) {
            $group = local_heyday_lessons_normalize_lesson_group($group);
            $active = !empty($group['active']);
        }

        if ($active) {
            $activegroupkey = $kind . ':' . $sectionnum;
        }

        if ($kind === 'scores' && $structure['scores'] === null) {
            $structure['scores'] = $group;
        } else if ($kind === 'discussions' && $structure['discussions'] === null) {
            $structure['discussions'] = $group;
        } else if ($kind === 'gettingstarted' && $structure['gettingstarted'] === null) {
            $structure['gettingstarted'] = $group;
        } else if ($kind === 'pretest' && $structure['pretest'] === null) {
            $structure['pretest'] = $group;
        } else if ($kind === 'resources' && $structure['resources'] === null) {
            $structure['resources'] = $group;
        } else if ($kind === 'final' && $structure['final'] === null) {
            $structure['final'] = $group;
        } else if ($kind === 'lesson') {
            $structure['lessons'][] = $group;
        } else {
            // If no explicit Lesson title exists, treat normal numbered sections with activities as lessons.
            if (!empty($items)) {
                $group['kind'] = 'lesson';
                $structure['lessons'][] = $group;
            } else {
                $structure['other'][] = $group;
            }
        }
    }

    foreach ($structure['lessons'] as $group) {
        foreach ($group['items'] as $item) {
            if (!$item['isheading'] && !$item['locked'] && !empty($item['url'])) {
                $structure['flat'][] = $item + ['lesson' => $group['title']];
            } else if ($item['isheading'] && !$item['locked'] && !empty($item['url'])) {
                $structure['flat'][] = $item + ['lesson' => $group['title']];
            }
        }
    }

    if ($structure['gettingstarted'] === null) {
        $structure['gettingstarted'] = [
            'sectionnum' => 0,
            'title' => get_string('gettingstarted', 'local_heyday_lessons'),
            'kind' => 'gettingstarted',
            'items' => [],
            'active' => false,
            'locked' => false,
            'status' => 'completed',
            'url' => local_heyday_lessons_url((int)$course->id, ['page' => 'gettingstarted']),
        ];
    }
    if ($structure['pretest'] === null) {
        $structure['pretest'] = [
            'sectionnum' => 0,
            'title' => get_string('pretest', 'local_heyday_lessons'),
            'kind' => 'pretest',
            'items' => [],
            'active' => false,
            'locked' => false,
            'status' => 'incomplete',
            'url' => local_heyday_lessons_url((int)$course->id, ['page' => 'pretest']),
        ];
    }
    if ($structure['resources'] === null) {
        $structure['resources'] = [
            'title' => get_string('resources', 'local_heyday_lessons'),
            'kind' => 'resources',
            'items' => [],
            'active' => false,
            'locked' => false,
            'status' => 'none',
            'url' => local_heyday_lessons_url((int)$course->id, ['page' => 'resources']),
        ];
    }
    if ($structure['final'] === null) {
        $structure['final'] = [
            'title' => get_string('finalexam', 'local_heyday_lessons'),
            'kind' => 'final',
            'items' => [],
            'active' => false,
            'locked' => true,
            'status' => 'locked',
            'url' => local_heyday_lessons_url((int)$course->id, ['page' => 'finalexam']),
        ];
    }

    $structure['activegroupkey'] = $activegroupkey;
    return $structure;
}

/**
 * Find current course module by ID if valid.
 *
 * @param stdClass $course
 * @param int $cmid
 * @return cm_info|null
 */
function local_heyday_lessons_get_cm(stdClass $course, int $cmid): ?cm_info {
    if ($cmid <= 0) {
        return null;
    }
    $modinfo = get_fast_modinfo($course);
    if (empty($modinfo->cms[$cmid])) {
        return null;
    }
    return $modinfo->cms[$cmid];
}

/**
 * Get the first learner activity from the structure.
 *
 * @param array $structure
 * @return array|null
 */
function local_heyday_lessons_first_item(array $structure): ?array {
    foreach ($structure['flat'] as $item) {
        if (!$item['locked'] && !empty($item['url'])) {
            return $item;
        }
    }
    return null;
}

/**
 * Get next learner activity after current cmid.
 *
 * @param array $structure
 * @param int $currentcmid
 * @return array|null
 */
function local_heyday_lessons_next_item(array $structure, int $currentcmid): ?array {
    $found = false;
    foreach ($structure['flat'] as $item) {
        if ($found && !$item['locked'] && !empty($item['url'])) {
            return $item;
        }
        if ((int)$item['cmid'] === $currentcmid) {
            $found = true;
        }
    }
    return null;
}

/**
 * Locate active lesson title for a cmid.
 *
 * @param array $structure
 * @param int $cmid
 * @return string
 */
function local_heyday_lessons_current_lesson_title(array $structure, int $cmid): string {
    foreach ($structure['lessons'] as $group) {
        foreach ($group['items'] as $item) {
            if ((int)$item['cmid'] === $cmid) {
                return $group['title'];
            }
        }
    }
    return '';
}

/**
 * Current chapter/subsection title before a cmid.
 *
 * @param array $structure
 * @param int $cmid
 * @return string
 */
function local_heyday_lessons_current_chapter_title(array $structure, int $cmid): string {
    foreach ($structure['lessons'] as $group) {
        $lastheading = '';
        foreach ($group['items'] as $item) {
            if (!empty($item['isheading'])) {
                $lastheading = $item['name'];
            }
            if ((int)$item['cmid'] === $cmid) {
                return $lastheading;
            }
        }
    }
    return '';
}

/**
 * Render status icon for sidebar rows.
 *
 * @param string $status
 * @param bool $mini
 * @return string
 */
function local_heyday_lessons_status_icon(string $status, bool $mini = false): string {
    $base = $mini ? 'hd-mini-status' : 'hd-status';
    if ($status === 'completed') {
        return '<span class="' . $base . ' is-complete" aria-label="Complete">✓</span>';
    }
    if ($status === 'locked') {
        return '<span class="' . $base . ' is-locked" aria-label="Locked"></span>';
    }
    if ($status === 'incomplete') {
        return '<span class="' . $base . ' is-progress" aria-label="In progress"></span>';
    }
    return '<span class="' . $base . ' is-empty" aria-hidden="true"></span>';
}

/**
 * Render a top-level sidebar link.
 *
 * @param string $label
 * @param moodle_url $url
 * @param string $iconclass
 * @param string $status
 * @param bool $active
 * @return string
 */
function local_heyday_lessons_render_top_link(string $label, moodle_url $url, string $iconclass = '', string $status = 'none', bool $active = false): string {
    $classes = ['hd-primary-link'];
    $classes[] = $iconclass === '' ? 'has-no-icon' : 'has-icon';
    if ($active) {
        $classes[] = 'is-current';
    }
    $html = html_writer::start_tag('a', ['href' => $url, 'class' => implode(' ', $classes)]);
    if ($iconclass !== '') {
        $html .= html_writer::span('', 'hd-primary-icon ' . $iconclass, ['aria-hidden' => 'true']);
    }
    $html .= html_writer::span(s($label), 'hd-primary-label');
    $html .= local_heyday_lessons_status_icon($status, true);
    $html .= html_writer::end_tag('a');
    return $html;
}

/**
 * Render the sidebar for the player shell.
 *
 * @param stdClass $course
 * @param array $structure
 * @param int $currentcmid
 * @param string $pagekey
 * @return string
 */
function local_heyday_lessons_render_sidebar(stdClass $course, array $structure, int $currentcmid, string $pagekey): string {
    $courseid = (int)$course->id;
    $html = html_writer::start_tag('aside', ['class' => 'hd-sidebar', 'aria-label' => 'Course navigation']);

    $html .= html_writer::start_tag('nav', ['class' => 'hd-primary-menu']);
    $html .= local_heyday_lessons_render_top_link(get_string('home', 'local_heyday_lessons'), local_heyday_lessons_url($courseid, ['page' => 'home']), 'hd-icon-home', 'none', $pagekey === 'home');
    $scoresstatus = !empty($structure['scores']) ? (string)$structure['scores']['status'] : 'none';
    $discussionstatus = !empty($structure['discussions']) ? (string)$structure['discussions']['status'] : 'none';
    $html .= local_heyday_lessons_render_top_link(get_string('scores', 'local_heyday_lessons'), local_heyday_lessons_url($courseid, ['page' => 'scores']), 'hd-icon-scores', $scoresstatus, $pagekey === 'scores');
    $html .= local_heyday_lessons_render_top_link(get_string('discussions', 'local_heyday_lessons'), local_heyday_lessons_url($courseid, ['page' => 'discussions']), 'hd-icon-discussions', $discussionstatus, $pagekey === 'discussions');

    $gs = $structure['gettingstarted'];
    $gsactive = $pagekey === 'gettingstarted' || !empty($gs['active']);
    $html .= local_heyday_lessons_render_top_link($gs['title'], $gs['url'], '', $gs['status'], $gsactive);
    if ($gsactive && !empty($gs['items'])) {
        $html .= html_writer::start_div('hd-gs-subnav');
        foreach ($gs['items'] as $item) {
            $html .= local_heyday_lessons_render_sidebar_item($item, true);
        }
        $html .= html_writer::end_div();
    }

    $pre = $structure['pretest'];
    $html .= local_heyday_lessons_render_top_link($pre['title'], $pre['url'], '', $pre['status'], $pagekey === 'pretest' || !empty($pre['active']));
    $html .= html_writer::end_tag('nav');

    $html .= html_writer::start_tag('nav', ['class' => 'hd-lesson-menu']);
    foreach ($structure['lessons'] as $group) {
        $classes = ['hd-lesson-group'];
        if (!empty($group['active'])) {
            $classes[] = 'is-active';
        }
        if (!empty($group['locked'])) {
            $classes[] = 'is-locked';
        }
        $attrs = ['class' => implode(' ', $classes)];
        if (!empty($group['active'])) {
            $attrs['open'] = 'open';
        }
        $html .= html_writer::start_tag('details', $attrs);
        $html .= html_writer::start_tag('summary', ['class' => 'hd-lesson-summary']);
        $html .= html_writer::start_div('hd-lesson-summary-inner');
        if (!empty($group['locked'])) {
            $html .= html_writer::span(s($group['title']), 'hd-lesson-title is-disabled');
        } else {
            $html .= html_writer::link($group['url'], s($group['title']), ['class' => 'hd-lesson-title']);
        }
        $html .= local_heyday_lessons_status_icon($group['status']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('summary');

        if (!empty($group['items'])) {
            $html .= html_writer::start_div('hd-lesson-items');
            foreach ($group['items'] as $item) {
                $html .= local_heyday_lessons_render_sidebar_item($item, false);
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_tag('details');
    }
    $html .= html_writer::end_tag('nav');

    $html .= html_writer::start_tag('nav', ['class' => 'hd-after-menu']);
    $res = $structure['resources'];
    $html .= local_heyday_lessons_render_after_link($res['title'], $res['url'], $res['status'], $pagekey === 'resources' || !empty($res['active']), !empty($res['locked']));
    $final = $structure['final'];
    $html .= local_heyday_lessons_render_after_link($final['title'], $final['url'], $final['status'], $pagekey === 'finalexam' || !empty($final['active']), !empty($final['locked']));
    $html .= html_writer::end_tag('nav');

    $html .= html_writer::end_tag('aside');
    return $html;
}

/**
 * Render one sidebar item.
 *
 * @param array $item
 * @param bool $gettingstarted
 * @return string
 */
function local_heyday_lessons_render_sidebar_item(array $item, bool $gettingstarted = false): string {
    $classes = [$gettingstarted ? 'hd-gs-item' : 'hd-lesson-item'];
    $classes[] = !empty($item['isheading']) ? 'is-heading' : 'is-activity';
    $classes[] = 'depth-' . (int)($item['indent'] ?? 0);
    if (!empty($item['current'])) {
        $classes[] = 'is-current';
    }
    if (!empty($item['locked'])) {
        $classes[] = 'is-locked';
    }

    $attrs = ['class' => implode(' ', $classes)];
    $content = html_writer::span('', 'hd-current-arrow', ['aria-hidden' => 'true']);
    $content .= html_writer::span(s($item['name']), 'hd-item-title');
    $content .= local_heyday_lessons_status_icon((string)$item['status'], true);

    if (empty($item['url']) || !empty($item['locked'])) {
        return html_writer::tag('span', $content, $attrs);
    }
    $attrs['href'] = $item['url'];
    return html_writer::tag('a', $content, $attrs);
}

/**
 * Render Resources and Final Exam sidebar rows.
 *
 * @param string $label
 * @param moodle_url $url
 * @param string $status
 * @param bool $active
 * @param bool $locked
 * @return string
 */
function local_heyday_lessons_render_after_link(string $label, moodle_url $url, string $status, bool $active, bool $locked): string {
    $classes = ['hd-after-link'];
    if ($active) {
        $classes[] = 'is-current';
    }
    if ($locked) {
        $classes[] = 'is-locked';
    }
    $content = html_writer::span(s($label), 'hd-after-label');
    $content .= local_heyday_lessons_status_icon($locked ? 'locked' : $status, true);
    if ($locked) {
        return html_writer::tag('span', $content, ['class' => implode(' ', $classes)]);
    }
    return html_writer::link($url, $content, ['class' => implode(' ', $classes)]);
}

/**
 * Render top black learner bar.
 *
 * @param stdClass $course
 * @return string
 */
function local_heyday_lessons_render_topbar(stdClass $course): string {
    global $USER;
    $hidebrand = (int)local_heyday_lessons_cfg('hidebrand', 1) === 1;
    $brandclasses = ['hd-topbar-brand'];
    if ($hidebrand) {
        $brandclasses[] = 'is-empty';
    }
    $html = html_writer::start_div('hd-topbar');
    $html .= html_writer::start_div(implode(' ', $brandclasses));
    if (!$hidebrand) {
        $html .= html_writer::span(format_string($course->fullname), 'hd-topbar-course');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('hd-topbar-actions');
    $html .= html_writer::span('', 'hd-topbar-action hd-topbar-search', ['aria-label' => get_string('search', 'local_heyday_lessons')]);
    $html .= html_writer::span('', 'hd-topbar-action hd-topbar-help', ['aria-label' => get_string('help', 'local_heyday_lessons')]);
    $html .= html_writer::span(get_string('tour', 'local_heyday_lessons'), 'hd-topbar-action hd-topbar-tour');
    $html .= html_writer::span(fullname($USER), 'hd-topbar-user');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render a module body, using native Moodle data where possible.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @return string
 */
function local_heyday_lessons_render_cm_body(stdClass $course, cm_info $cm): string {
    global $DB, $OUTPUT;

    $cmcontext = context_module::instance($cm->id);

    if (!$cm->uservisible) {
        return html_writer::div(
            html_writer::tag('div', '', ['class' => 'hd-locked-large', 'aria-hidden' => 'true']) .
            html_writer::tag('h2', get_string('locked', 'local_heyday_lessons')) .
            html_writer::tag('p', format_text($cm->availableinfo ?? '', FORMAT_HTML, ['context' => $cmcontext])),
            'hd-locked-card'
        );
    }

    if ($cm->modname === 'page') {
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if ($page) {
            $content = file_rewrite_pluginfile_urls($page->content, 'pluginfile.php', $cmcontext->id, 'mod_page', 'content', $page->revision);
            return html_writer::div(format_text($content, $page->contentformat, ['context' => $cmcontext, 'overflowdiv' => true]), 'hd-page-content');
        }
    }

    if ($cm->modname === 'label') {
        return html_writer::div(format_text($cm->content, FORMAT_HTML, ['context' => $cmcontext, 'overflowdiv' => true]), 'hd-page-content');
    }

    $intro = '';
    if (!empty($cm->content)) {
        $intro = format_text($cm->content, FORMAT_HTML, ['context' => $cmcontext, 'overflowdiv' => true]);
    }
    if ($intro === '' && !empty($cm->description)) {
        $intro = format_text($cm->description, FORMAT_HTML, ['context' => $cmcontext, 'overflowdiv' => true]);
    }

    $launch = html_writer::link($cm->url, get_string('openactivity', 'local_heyday_lessons'), ['class' => 'hd-primary-button']);
    return html_writer::div(
        html_writer::span(s(get_string('modulename', 'mod_' . $cm->modname)), 'hd-activity-pill') .
        html_writer::tag('h2', format_string($cm->name)) .
        ($intro !== '' ? html_writer::div($intro, 'hd-activity-intro') : '') .
        html_writer::div($launch, 'hd-launch-row'),
        'hd-activity-fallback'
    );
}

/**
 * Render the main lesson activity page.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_render_activity_page(stdClass $course, cm_info $cm, array $structure): string {
    $coursecontext = context_course::instance($course->id);
    $lesson = local_heyday_lessons_current_lesson_title($structure, (int)$cm->id);
    $chapter = local_heyday_lessons_current_chapter_title($structure, (int)$cm->id);
    $next = local_heyday_lessons_next_item($structure, (int)$cm->id);
    $status = local_heyday_lessons_cm_status($course, $cm);

    $crumb = [];
    if ($lesson !== '') {
        $crumb[] = $lesson;
    }
    if ($chapter !== '' && $chapter !== $cm->name) {
        $crumb[] = $chapter;
    }

    $html = html_writer::start_tag('main', ['class' => 'hd-main']);
    $html .= html_writer::start_tag('article', ['class' => 'hd-card']);
    $html .= html_writer::start_div('hd-card-toolbar');
    $html .= html_writer::link(local_heyday_lessons_url((int)$course->id, ['page' => 'home']), '←', ['class' => 'hd-card-icon hd-back', 'aria-label' => get_string('home', 'local_heyday_lessons')]);
    $html .= html_writer::span('', 'hd-card-icon hd-bookmark', ['aria-hidden' => 'true']);
    $html .= html_writer::span('', 'hd-toolbar-spacer');
    $html .= html_writer::span('', 'hd-card-icon hd-print', ['aria-hidden' => 'true']);
    $html .= html_writer::span('', 'hd-card-icon hd-fullscreen', ['aria-hidden' => 'true']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_tag('header', ['class' => 'hd-content-heading']);
    $html .= html_writer::div(format_string($course->fullname, true, ['context' => $coursecontext]), 'hd-course-kicker');
    if (!empty($crumb)) {
        $html .= html_writer::div(s(implode(' / ', $crumb)), 'hd-lesson-kicker');
    }
    $html .= html_writer::tag('h1', format_string($cm->name, true, ['context' => context_module::instance($cm->id)]));
    $html .= html_writer::end_tag('header');

    $html .= html_writer::start_div('hd-content-body');
    $html .= local_heyday_lessons_render_cm_body($course, $cm);
    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('article');

    $html .= local_heyday_lessons_render_completion_row($status);
    if ($next !== null) {
        $html .= local_heyday_lessons_render_nextup($next);
    }
    $html .= local_heyday_lessons_render_footer();
    $html .= html_writer::end_tag('main');
    return $html;
}

/**
 * Render completion row below the content card.
 *
 * @param string $status
 * @return string
 */
function local_heyday_lessons_render_completion_row(string $status): string {
    $complete = $status === 'completed';
    $html = html_writer::start_div('hd-completion-row ' . ($complete ? 'is-complete' : 'is-incomplete'));
    $html .= html_writer::span($complete ? '✓' : '', 'hd-completion-badge');
    $html .= html_writer::start_div('hd-completion-text');
    $html .= html_writer::div($complete ? get_string('activitycomplete', 'local_heyday_lessons') : get_string('activitynotcomplete', 'local_heyday_lessons'));
    if ($complete) {
        $html .= html_writer::span(get_string('undo', 'local_heyday_lessons'), 'hd-undo-link');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render Next Up card.
 *
 * @param array $next
 * @return string
 */
function local_heyday_lessons_render_nextup(array $next): string {
    $html = html_writer::start_div('hd-nextup-wrap');
    $html .= html_writer::start_tag('a', ['href' => $next['url'], 'class' => 'hd-nextup-card']);
    $html .= html_writer::span(get_string('nextup', 'local_heyday_lessons'), 'hd-nextup-label');
    $html .= html_writer::start_tag('span', ['class' => 'hd-nextup-body']);
    if (!empty($next['lesson'])) {
        $html .= html_writer::span(s($next['lesson']), 'hd-nextup-section');
    }
    $html .= html_writer::span(s($next['name']), 'hd-nextup-title');
    $html .= html_writer::span('activity', 'hd-nextup-type');
    $html .= html_writer::end_span();
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render footer links.
 *
 * @return string
 */
function local_heyday_lessons_render_footer(): string {
    $support = trim((string)local_heyday_lessons_cfg('supporturl', ''));
    $supporturl = $support !== '' ? new moodle_url($support) : new moodle_url('/');
    $html = html_writer::start_div('hd-footer');
    $html .= html_writer::link($supporturl, 'Course Support');
    $html .= html_writer::span('', 'hd-footer-sep', ['aria-hidden' => 'true']);
    $html .= html_writer::link(new moodle_url('/admin/tool/dataprivacy/summary.php'), 'Cookie Settings');
    $html .= html_writer::span('© ' . date('Y') . ' Heyday Learning', 'hd-copyright');
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render home dashboard.
 *
 * @param stdClass $course
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_render_home_page(stdClass $course, array $structure): string {
    $first = local_heyday_lessons_first_item($structure);
    $progress = local_heyday_lessons_progress_percent($structure);
    $score = local_heyday_lessons_score_percent($course);
    $coursecontext = context_course::instance($course->id);
    $prefix = (string)local_heyday_lessons_cfg('coursecodeprefix', 'Section:');
    $code = trim((string)$course->shortname) !== '' ? trim((string)$course->shortname) : (string)$course->id;

    $html = html_writer::start_tag('main', ['class' => 'hd-main hd-main-home']);
    $html .= html_writer::start_div('hd-home-dashboard');
    $html .= html_writer::start_div('hd-home-hero');
    $html .= html_writer::start_div('hd-home-title');
    $html .= html_writer::tag('h2', format_string($course->fullname, true, ['context' => $coursecontext]));
    $html .= html_writer::div(s(trim($prefix . ' ' . $code)), 'hd-home-code');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('hd-home-meters');
    $html .= local_heyday_lessons_meter($progress, 'complete');
    $html .= local_heyday_lessons_meter($score, 'score', true);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('hd-home-content');
    $html .= html_writer::tag('h1', get_string('welcome', 'local_heyday_lessons'), ['class' => 'hd-home-welcome']);
    if ($first !== null) {
        $html .= html_writer::start_div('hd-home-next-card');
        $html .= html_writer::start_div('hd-home-next-main');
        $html .= html_writer::tag('h3', s($first['lesson'] ?? $first['name']));
        $html .= html_writer::start_div('hd-home-progress-row');
        $html .= html_writer::div(html_writer::span('', 'hd-home-progress-fill', ['style' => 'width:' . (int)$progress . '%']), 'hd-home-progress-track');
        $html .= html_writer::span((int)$progress . '% complete');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('hd-home-next-action');
        $html .= html_writer::span(get_string('nextactivity', 'local_heyday_lessons'), 'hd-home-next-label');
        $html .= html_writer::span(s($first['name']), 'hd-home-next-name');
        $html .= html_writer::link($first['url'], get_string('continue', 'local_heyday_lessons'), ['class' => 'hd-home-continue-button']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    } else {
        $html .= html_writer::div(get_string('noitems', 'local_heyday_lessons'), 'hd-empty-card');
    }
    $html .= local_heyday_lessons_render_footer();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('main');
    return $html;
}

/**
 * Render circular meter.
 *
 * @param int $value
 * @param string $label
 * @param bool $score
 * @return string
 */
function local_heyday_lessons_meter(int $value, string $label, bool $score = false): string {
    $value = max(0, min(100, $value));
    $classes = 'hd-home-meter-ring' . ($score ? ' is-score' : '');
    $inside = html_writer::span($score && $value === 0 ? '- -' : $value . '%') . html_writer::span(s($label), 'hd-home-meter-label');
    return html_writer::div(html_writer::div($inside, $classes, ['style' => '--meter-value:' . $value]), 'hd-home-meter');
}

/**
 * Simple course completion percentage from flat learner items.
 *
 * @param array $structure
 * @return int
 */
function local_heyday_lessons_progress_percent(array $structure): int {
    $trackable = 0;
    $complete = 0;
    foreach ($structure['flat'] as $item) {
        if ($item['status'] === 'completed' || $item['status'] === 'incomplete') {
            $trackable++;
            if ($item['status'] === 'completed') {
                $complete++;
            }
        }
    }
    if ($trackable === 0) {
        return 0;
    }
    return (int)round(($complete / $trackable) * 100);
}

/**
 * Calculate a simple score percentage from gradebook totals when available.
 *
 * @param stdClass $course
 * @return int
 */
function local_heyday_lessons_score_percent(stdClass $course): int {
    global $USER;
    global $CFG;
    if (!class_exists('grade_item')) {
        require_once($CFG->libdir . '/gradelib.php');
    }
    $gradeitem = grade_item::fetch_course_item($course->id);
    if (!$gradeitem) {
        return 0;
    }
    $grade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $USER->id]);
    if (!$grade || $grade->finalgrade === null || (float)$gradeitem->grademax <= 0) {
        return 0;
    }
    return (int)round(((float)$grade->finalgrade / (float)$gradeitem->grademax) * 100);
}

/**
 * Render scores page.
 *
 * @param stdClass $course
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_render_scores_page(stdClass $course, array $structure): string {
    $html = html_writer::start_tag('main', ['class' => 'hd-main hd-main-page']);
    $html .= html_writer::start_tag('article', ['class' => 'hd-wide-page']);
    $html .= html_writer::start_div('hd-page-title-row');
    $html .= html_writer::tag('h1', get_string('scores', 'local_heyday_lessons'));
    $html .= html_writer::tag('button', get_string('downloadgrades', 'local_heyday_lessons'), ['class' => 'hd-outline-button', 'type' => 'button']);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('hd-score-list');

    $found = false;
    foreach ($structure['flat'] as $item) {
        if (!preg_match('/quiz|assignment|exam|test|check/i', $item['name'] . ' ' . $item['modname'])) {
            continue;
        }
        $found = true;
        $rowclasses = ['hd-score-row'];
        if ($item['locked']) {
            $rowclasses[] = 'is-locked';
        }
        $html .= html_writer::start_div(implode(' ', $rowclasses));
        $html .= html_writer::start_div('hd-score-left');
        $html .= html_writer::span('', $item['status'] === 'completed' ? 'hd-score-symbol is-check' : 'hd-score-symbol');
        if ($item['locked']) {
            $html .= html_writer::span(s($item['name']), 'hd-score-name locked-name');
        } else {
            $html .= html_writer::link($item['url'], s($item['name']), ['class' => 'hd-score-name']);
        }
        $html .= html_writer::end_div();
        $html .= html_writer::div($item['status'] === 'completed' ? 'Complete' : ($item['locked'] ? get_string('locked', 'local_heyday_lessons') : 'Not submitted'), 'hd-score-right');
        $html .= html_writer::end_div();
    }

    if (!$found) {
        $html .= html_writer::div('No graded activities were found yet.', 'hd-empty-card');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('article');
    $html .= html_writer::end_tag('main');
    return $html;
}

/**
 * Render discussions page.
 *
 * @param stdClass $course
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_render_discussions_page(stdClass $course, array $structure): string {
    $html = html_writer::start_tag('main', ['class' => 'hd-main hd-main-page']);
    $html .= html_writer::start_tag('article', ['class' => 'hd-wide-page']);
    $html .= html_writer::tag('h1', get_string('discussions', 'local_heyday_lessons'));
    $html .= html_writer::start_div('hd-discussion-list');
    $found = false;
    foreach ($structure['flat'] as $item) {
        if (!preg_match('/forum|discussion/i', $item['name'] . ' ' . $item['modname'])) {
            continue;
        }
        $found = true;
        $html .= html_writer::start_div('hd-discussion-card' . ($item['locked'] ? ' is-locked' : ''));
        $html .= html_writer::start_div('hd-discussion-left');
        $html .= html_writer::span('', 'hd-discussion-icon', ['aria-hidden' => 'true']);
        $html .= html_writer::start_div('hd-discussion-main');
        if ($item['locked']) {
            $html .= html_writer::span(s($item['name']), 'hd-discussion-title');
        } else {
            $html .= html_writer::link($item['url'], s($item['name']), ['class' => 'hd-discussion-title']);
        }
        $html .= html_writer::div(s($item['lesson'] ?? ''), 'hd-discussion-meta');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::div(local_heyday_lessons_status_icon($item['status']), 'hd-discussion-right');
        $html .= html_writer::end_div();
    }
    if (!$found) {
        $html .= html_writer::div('No discussions were found yet.', 'hd-empty-card');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('article');
    $html .= html_writer::end_tag('main');
    return $html;
}

/**
 * Render a section-like page such as Getting Started, Pretest, Resources, or Final Exam.
 *
 * @param stdClass $course
 * @param array $group
 * @param string $title
 * @return string
 */
function local_heyday_lessons_render_group_page(stdClass $course, array $group, string $title): string {
    $html = html_writer::start_tag('main', ['class' => 'hd-main hd-main-page']);
    $html .= html_writer::start_tag('article', ['class' => 'hd-card']);
    $html .= html_writer::start_div('hd-card-toolbar');
    $html .= html_writer::link(local_heyday_lessons_url((int)$course->id, ['page' => 'home']), '←', ['class' => 'hd-card-icon hd-back']);
    $html .= html_writer::end_div();
    $html .= html_writer::start_tag('header', ['class' => 'hd-content-heading']);
    $html .= html_writer::div(format_string($course->fullname), 'hd-course-kicker');
    $html .= html_writer::tag('h1', s($title));
    $html .= html_writer::end_tag('header');
    $html .= html_writer::start_div('hd-content-body');
    if (!empty($group['items'])) {
        $html .= html_writer::start_tag('ul', ['class' => 'hd-section-item-list']);
        foreach ($group['items'] as $item) {
            $label = s($item['name']);
            $link = (!empty($item['url']) && !$item['locked']) ? html_writer::link($item['url'], $label) : html_writer::span($label, 'is-locked');
            $html .= html_writer::tag('li', $link . local_heyday_lessons_status_icon($item['status'], true));
        }
        $html .= html_writer::end_tag('ul');
    } else {
        $html .= html_writer::div(get_string('noitems', 'local_heyday_lessons'), 'hd-empty-card');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_tag('article');
    $html .= local_heyday_lessons_render_footer();
    $html .= html_writer::end_tag('main');
    return $html;
}

/**
 * Render complete shell.
 *
 * @param stdClass $course
 * @param int $currentcmid
 * @param string $pagekey
 * @return string
 */
function local_heyday_lessons_render_shell(stdClass $course, int $currentcmid = 0, string $pagekey = ''): string {
    $pagekey = $pagekey === '' ? ($currentcmid > 0 ? 'content' : 'home') : $pagekey;
    $cm = local_heyday_lessons_get_cm($course, $currentcmid);
    if ($cm === null && $pagekey === 'content') {
        $pagekey = 'home';
    }

    $structure = local_heyday_lessons_build_structure($course, $cm ? (int)$cm->id : 0);

    $accent = local_heyday_lessons_css_color((string)local_heyday_lessons_cfg('accentcolor', '#0073a8'), '#0073a8');
    $pagebg = local_heyday_lessons_css_color((string)local_heyday_lessons_cfg('pagebackground', '#f4f5f7'), '#f4f5f7');
    $sidebar = max(280, min(520, (int)local_heyday_lessons_cfg('sidebarwidth', 356)));
    $topbar = max(36, min(72, (int)local_heyday_lessons_cfg('topbarheight', 42)));

    $style = '--hd-accent:' . s($accent) . ';--hd-page-bg:' . s($pagebg) . ';--hd-sidebar-width:' . $sidebar . 'px;--hd-topbar-height:' . $topbar . 'px;';
    $classes = ['hd-player', 'is-page-' . preg_replace('/[^a-z0-9_-]/', '', $pagekey)];

    $html = html_writer::start_div(implode(' ', $classes), ['style' => $style]);
    $html .= local_heyday_lessons_render_topbar($course);
    $html .= html_writer::start_div('hd-shell');
    $html .= local_heyday_lessons_render_sidebar($course, $structure, $cm ? (int)$cm->id : 0, $pagekey);

    if ($cm !== null) {
        $html .= local_heyday_lessons_render_activity_page($course, $cm, $structure);
    } else if ($pagekey === 'scores') {
        $html .= local_heyday_lessons_render_scores_page($course, $structure);
    } else if ($pagekey === 'discussions') {
        $html .= local_heyday_lessons_render_discussions_page($course, $structure);
    } else if ($pagekey === 'gettingstarted') {
        $html .= local_heyday_lessons_render_group_page($course, $structure['gettingstarted'], get_string('gettingstarted', 'local_heyday_lessons'));
    } else if ($pagekey === 'pretest') {
        $html .= local_heyday_lessons_render_group_page($course, $structure['pretest'], get_string('pretest', 'local_heyday_lessons'));
    } else if ($pagekey === 'resources') {
        $html .= local_heyday_lessons_render_group_page($course, $structure['resources'], get_string('resources', 'local_heyday_lessons'));
    } else if ($pagekey === 'finalexam') {
        $html .= local_heyday_lessons_render_group_page($course, $structure['final'], get_string('finalexam', 'local_heyday_lessons'));
    } else {
        $html .= local_heyday_lessons_render_home_page($course, $structure);
    }

    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * HeyDay Question Bank Helper.
 *
 * @package    local_heyday_questionbank
 * @copyright  2026 HeyDayTraining
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

$id = optional_param('id', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);
$categoryname = optional_param('categoryname', 'Lesson 1 Quiz', PARAM_TEXT);
$quiztitle = optional_param('quiztitle', 'Lesson 1 Quiz', PARAM_TEXT);
$rawquestions = optional_param('rawquestions', '', PARAM_RAW);

if ($id) {
    $course = get_course($id);
    require_login($course);
    $context = context_course::instance($course->id);
    $url = new moodle_url('/local/heyday_questionbank/index.php', ['id' => $course->id]);
} else {
    require_login();
    $course = null;
    $context = context_system::instance();
    $url = new moodle_url('/local/heyday_questionbank/index.php');
}

require_capability('moodle/question:add', $context);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_heyday_questionbank'));
$PAGE->set_heading(get_string('pluginname', 'local_heyday_questionbank'));
$PAGE->add_body_class('local-heyday-questionbank');
$PAGE->requires->css(new moodle_url('/local/heyday_questionbank/styles.css'));

/**
 * Default starter text.
 *
 * @return string
 */
function local_heyday_questionbank_default_text(): string {
    return <<<TEXT
Q1: Which type of AI is most commonly in use today?
A. Artificial super intelligence
B. Artificial general intelligence
C. Artificial wide intelligence
D. Artificial narrow intelligence
Answer: D
Feedback D: This was the correct answer.

Q2: Which of these is the best example of artificial intelligence?
A. A spreadsheet application that calculates data using functions
B. An application that plays a game against a human and gets better at it over time
C. A network communication device such as a switch or router
D. An operating system such as Windows or Linux
Answer: B
Feedback B: Correct!

Q3: Which type of AI may raise some ethical challenges because it involves AI programs that have human-like consciousness?
A. Artificial super intelligence
B. Artificial narrow intelligence
C. Artificial general intelligence
D. Artificial human intelligence
Answer: A
Feedback A: This was the correct answer.

Q4: Which of these types of AI compares two pictures to determine if they are similar enough to be considered a match?
A. Mapping application
B. Biometric authentication
C. Smart home appliance
D. Recommendation engine
Answer: B
Feedback B: This was the correct answer.

Q5: What type of AI, developed in the 1980s, asked human experts for input on how to behave in different scenarios and used that information to build a body of knowledge?
A. Machine learning
B. Turing test
C. Expert systems
D. Deep learning
Answer: C
Feedback C: This was the correct answer.

Q6: What are the two main factors that have led to recent advances in AI research and development?
A. Data availability and climate change
B. Computing advances and satellites
C. Multi-national treaties and computing advances
D. Computing advances and data availability
Answer: D
Feedback D: This was the correct answer.
TEXT;
}

/**
 * Clean text for CDATA.
 *
 * @param string $text Raw text.
 * @return string
 */
function local_heyday_questionbank_clean_cdata(string $text): string {
    return str_replace(']]>', ']]]]><![CDATA[>', trim($text));
}

/**
 * Parse simple question text into an array.
 *
 * @param string $raw Raw pasted question text.
 * @return array
 */
function local_heyday_questionbank_parse(string $raw): array {
    $raw = str_replace(["\r\n", "\r"], "\n", trim($raw));
    $lines = explode("\n", $raw);

    $questions = [];
    $current = null;
    $currentoption = null;
    $currentfeedback = null;

    $finishquestion = function () use (&$questions, &$current): void {
        if ($current === null) {
            return;
        }

        $current['question'] = trim($current['question']);
        $current['answer'] = strtoupper(trim($current['answer'] ?? ''));

        foreach ($current['options'] as $key => $value) {
            $current['options'][$key] = trim($value);
        }

        foreach ($current['feedback'] as $key => $value) {
            $current['feedback'][$key] = trim($value);
        }

        if ($current['question'] !== '' && count(array_filter($current['options'])) >= 2 && isset($current['options'][$current['answer']])) {
            $questions[] = $current;
        }

        $current = null;
    };

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            $currentoption = null;
            $currentfeedback = null;
            continue;
        }

        if (preg_match('/^Q\s*(\d+)\s*[:.)]\s*(.+)$/i', $line, $matches)) {
            $finishquestion();

            $current = [
                'number' => (int) $matches[1],
                'question' => trim($matches[2]),
                'options' => [],
                'answer' => '',
                'feedback' => [],
            ];
            $currentoption = null;
            $currentfeedback = null;
            continue;
        }

        if ($current === null) {
            continue;
        }

        if (preg_match('/^([A-D])\s*[.)]\s*(.+)$/i', $line, $matches)) {
            $letter = strtoupper($matches[1]);
            $current['options'][$letter] = trim($matches[2]);
            $currentoption = $letter;
            $currentfeedback = null;
            continue;
        }

        if (preg_match('/^Answer\s*:\s*([A-D])\b/i', $line, $matches)) {
            $current['answer'] = strtoupper($matches[1]);
            $currentoption = null;
            $currentfeedback = null;
            continue;
        }

        if (preg_match('/^Feedback\s+([A-D])\s*:\s*(.+)$/i', $line, $matches)) {
            $letter = strtoupper($matches[1]);
            $current['feedback'][$letter] = trim($matches[2]);
            $currentfeedback = $letter;
            $currentoption = null;
            continue;
        }

        if (preg_match('/^Explanation\s*:\s*(.+)$/i', $line, $matches)) {
            $answer = strtoupper($current['answer'] ?? '');
            if ($answer !== '') {
                $current['feedback'][$answer] = trim($matches[1]);
                $currentfeedback = $answer;
            }
            $currentoption = null;
            continue;
        }

        if ($currentfeedback !== null) {
            $current['feedback'][$currentfeedback] .= ' ' . $line;
            continue;
        }

        if ($currentoption !== null) {
            $current['options'][$currentoption] .= ' ' . $line;
            continue;
        }

        $current['question'] .= ' ' . $line;
    }

    $finishquestion();

    usort($questions, static function (array $a, array $b): int {
        return $a['number'] <=> $b['number'];
    });

    return $questions;
}

/**
 * Build Moodle XML from parsed questions.
 *
 * @param array $questions Parsed questions.
 * @param string $categoryname Category name.
 * @param string $quiztitle Quiz title.
 * @return string
 */
function local_heyday_questionbank_xml(array $questions, string $categoryname, string $quiztitle): string {
    $category = local_heyday_questionbank_clean_cdata($categoryname);
    $quiztitle = local_heyday_questionbank_clean_cdata($quiztitle);

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<quiz>\n";
    $xml .= "  <question type=\"category\">\n";
    $xml .= "    <category><text><![CDATA[\$course$/{$category}]]></text></category>\n";
    $xml .= "    <info format=\"html\"><text><![CDATA[{$quiztitle} question bank category.]]></text></info>\n";
    $xml .= "  </question>\n\n";

    foreach ($questions as $question) {
        $qname = local_heyday_questionbank_clean_cdata($quiztitle . ' - Question ' . $question['number']);
        $qtext = local_heyday_questionbank_clean_cdata($question['question']);
        $answerkey = strtoupper($question['answer']);

        $xml .= "  <question type=\"multichoice\">\n";
        $xml .= "    <name><text><![CDATA[{$qname}]]></text></name>\n";
        $xml .= "    <questiontext format=\"html\"><text><![CDATA[{$qtext}]]></text></questiontext>\n";
        $xml .= "    <defaultgrade>1.0000000</defaultgrade>\n";
        $xml .= "    <penalty>0.3333333</penalty>\n";
        $xml .= "    <hidden>0</hidden>\n";
        $xml .= "    <idnumber></idnumber>\n";
        $xml .= "    <single>true</single>\n";
        $xml .= "    <shuffleanswers>true</shuffleanswers>\n";
        $xml .= "    <answernumbering>ABCD</answernumbering>\n";
        $xml .= "    <showstandardinstruction>0</showstandardinstruction>\n";
        $xml .= "    <correctfeedback format=\"html\"><text><![CDATA[Correct.]]></text></correctfeedback>\n";
        $xml .= "    <partiallycorrectfeedback format=\"html\"><text><![CDATA[Partially correct.]]></text></partiallycorrectfeedback>\n";
        $xml .= "    <incorrectfeedback format=\"html\"><text><![CDATA[Incorrect.]]></text></incorrectfeedback>\n";

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (!isset($question['options'][$letter])) {
                continue;
            }

            $fraction = ($letter === $answerkey) ? '100' : '0';
            $optiontext = local_heyday_questionbank_clean_cdata($question['options'][$letter]);
            $feedback = local_heyday_questionbank_clean_cdata($question['feedback'][$letter] ?? '');

            $xml .= "    <answer fraction=\"{$fraction}\" format=\"html\">\n";
            $xml .= "      <text><![CDATA[{$optiontext}]]></text>\n";
            if ($feedback !== '') {
                $xml .= "      <feedback format=\"html\"><text><![CDATA[{$feedback}]]></text></feedback>\n";
            }
            $xml .= "    </answer>\n";
        }

        $xml .= "  </question>\n\n";
    }

    $xml .= "</quiz>\n";

    return $xml;
}

if ($rawquestions === '') {
    $rawquestions = local_heyday_questionbank_default_text();
}

$questions = local_heyday_questionbank_parse($rawquestions);
$errors = [];
$ispost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($ispost && !$questions) {
    $errors[] = 'No valid questions were detected. Use Q1:, A., B., C., D., and Answer: D format.';
}

if ($download && $ispost) {
    require_sesskey();

    if (!$questions) {
        throw new moodle_exception('invaliddata');
    }

    $xml = local_heyday_questionbank_xml($questions, $categoryname, $quiztitle);
    $filename = clean_param(core_text::strtolower($quiztitle), PARAM_FILE);
    $filename = $filename ?: 'heyday-question-bank';
    $filename .= '.xml';

    $tmpdir = make_temp_directory('local_heyday_questionbank');
    $tmpfile = $tmpdir . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($tmpfile, $xml);

    send_file($tmpfile, $filename, 0, 0, false, true, 'application/xml');
    die();
}

echo $OUTPUT->header();

echo html_writer::start_div('hq-wrap');
echo html_writer::start_div('hq-card');

echo html_writer::tag('h1', 'HeyDay Question Bank Helper', ['class' => 'hq-title']);
echo html_writer::div(
    'Paste lesson quiz questions, preview them in the HeyDay/ed2go-style layout, then export Moodle XML for Question Bank import.',
    'hq-subtitle'
);

echo html_writer::div(
    'This helper does not replace Moodle Question Bank. It creates Moodle XML that you import through Moodle normally.',
    'hq-help'
);

foreach ($errors as $error) {
    echo html_writer::div(s($error), 'hq-error');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey(),
]);

echo html_writer::start_div('hq-grid');

echo html_writer::start_div();

echo html_writer::div('Quiz title', 'hq-label');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'quiztitle',
    'value' => $quiztitle,
    'class' => 'hq-input',
]);

echo html_writer::div('Question bank category', 'hq-label', ['style' => 'margin-top:14px']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'categoryname',
    'value' => $categoryname,
    'class' => 'hq-input',
]);

echo html_writer::div('Question text format', 'hq-label', ['style' => 'margin-top:14px']);
echo html_writer::tag('textarea', s($rawquestions), [
    'name' => 'rawquestions',
    'class' => 'hq-textarea',
]);

echo html_writer::start_div('hq-actions');
echo html_writer::tag('button', 'Preview', [
    'type' => 'submit',
    'name' => 'preview',
    'value' => '1',
    'class' => 'hq-btn hq-btn-secondary',
]);
echo html_writer::tag('button', 'Download Moodle XML', [
    'type' => 'submit',
    'name' => 'download',
    'value' => '1',
    'class' => 'hq-btn',
]);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::start_div();
echo html_writer::tag('h2', s($quiztitle), ['class' => 'hq-preview-title']);

if (!$questions) {
    echo html_writer::div('Preview will appear here after valid questions are detected.', 'hq-help');
} else {
    foreach ($questions as $question) {
        $answerkey = strtoupper($question['answer']);

        echo html_writer::start_div('hq-question');
        echo html_writer::div((string) $question['number'], 'hq-number');
        echo html_writer::div(s($question['question']), 'hq-qtext');

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (!isset($question['options'][$letter])) {
                continue;
            }

            $classes = 'hq-answer';
            if ($letter === $answerkey) {
                $classes .= ' is-correct';
            }

            echo html_writer::start_div($classes);
            echo html_writer::start_div('hq-answer-label');
            echo html_writer::span('', 'hq-radio');
            echo html_writer::span($letter);
            echo html_writer::end_div();
            echo html_writer::div(s($question['options'][$letter]), 'hq-answer-text');
            echo html_writer::end_div();

            if ($letter === $answerkey) {
                $feedback = $question['feedback'][$letter] ?? 'This was the correct answer.';
                echo html_writer::div(s($feedback), 'hq-feedback');
            }
        }

        echo html_writer::end_div();
    }
}

echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();

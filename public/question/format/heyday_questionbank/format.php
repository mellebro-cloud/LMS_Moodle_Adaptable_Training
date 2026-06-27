<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/format.php');

/**
 * HeyDay Question Bank import format.
 *
 * Imports simple HeyDay plain text into Moodle multiple-choice questions.
 *
 * Supported format:
 *
 * Q1: Question text
 * A. First answer
 * B. Second answer
 * C. Third answer
 * D. Fourth answer
 * Answer: D
 * Feedback D: This was the correct answer.
 *
 * @package    qformat_heyday_questionbank
 * @copyright  2026 HeyDayTraining
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_heyday_questionbank extends qformat_default {

    /**
     * This plugin supports import.
     *
     * @return bool
     */
    public function provide_import() {
        return true;
    }

    /**
     * This plugin does not support export.
     *
     * @return bool
     */
    public function provide_export() {
        return false;
    }

    /**
     * Export extension if export is enabled later.
     *
     * @return string
     */
    public function export_file_extension() {
        return '.txt';
    }

    /**
     * MIME type for uploaded files.
     *
     * @return string
     */
    public function mime_type() {
        return 'text/plain';
    }

    /**
     * Return a Moodle formatted text array for multichoice answer/feedback fields.
     *
     * This mirrors the shape used by Moodle's built-in GIFT importer for
     * multichoice answers and per-answer feedback.
     *
     * @param string $text Field text.
     * @param int $format Text format.
     * @return array
     */
    protected function formatted_text(string $text, int $format = FORMAT_HTML): array {
        return [
            'text' => $text,
            'format' => $format,
            'files' => [],
        ];
    }

    /**
     * Read questions from uploaded text file lines.
     *
     * @param array $lines Imported file lines.
     * @return array Moodle question objects.
     */
    public function readquestions($lines) {
        $raw = implode("\n", $lines);
        $parsed = $this->parse_heyday_questions($raw);
        $questions = [];

        foreach ($parsed as $item) {
            $question = $this->defaultquestion();

            $question->qtype = 'multichoice';
            $question->name = 'Lesson Quiz - Question ' . $item['number'];

            // Moodle import preview expects these text fields as strings with separate format fields.
            $question->questiontext = $item['question'];
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = '';
            $question->generalfeedbackformat = FORMAT_HTML;

            $question->defaultmark = 1;
            $question->penalty = 0.3333333;
            $question->length = 1;

            $question->single = 1;
            $question->shuffleanswers = 1;
            $question->answernumbering = 'ABCD';
            $question->shownumcorrect = 0;

            // Combined feedback fields are editor arrays.
            $question->correctfeedback = $this->formatted_text('Correct.');
            $question->partiallycorrectfeedback = $this->formatted_text('Partially correct.');
            $question->incorrectfeedback = $this->formatted_text('Incorrect.');

            $question->answer = [];
            $question->fraction = [];
            $question->feedback = [];

            $answerkey = strtoupper($item['answer']);

            foreach (['A', 'B', 'C', 'D'] as $letter) {
                if (!isset($item['options'][$letter])) {
                    continue;
                }

                // Multichoice import answer values are formatted text arrays.
                $question->answer[] = $this->formatted_text($item['options'][$letter]);
                $question->fraction[] = ($letter === $answerkey) ? 1.0 : 0.0;

                $feedbacktext = $item['feedback'][$letter] ?? '';
                if ($feedbacktext === '' && $letter === $answerkey) {
                    $feedbacktext = 'This was the correct answer.';
                }

                // Per-answer feedback values are also formatted text arrays.
                $question->feedback[] = $this->formatted_text($feedbacktext);
            }

            if (count($question->answer) >= 2) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * Parse HeyDay text format.
     *
     * @param string $raw Raw imported text.
     * @return array Parsed question arrays.
     */
    protected function parse_heyday_questions(string $raw): array {
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

            if (
                $current['question'] !== ''
                && count(array_filter($current['options'])) >= 2
                && isset($current['options'][$current['answer']])
            ) {
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
                    'number' => (int)$matches[1],
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
}

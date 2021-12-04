<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Short answer question definition class.
 *
 * @package    qtype
 * @subpackage longanswer
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');
//require_once($CFF->dirroot . '/question/type/longanswer/CosineSimilarity.php');
/**
 * Represents a short answer question.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_longanswer_question extends question_graded_by_strategy
        implements question_response_answer_comparer {
    /** @var boolean whether answers should be graded case-sensitively. */
    public $usecase;
    /** @var array of question_answer. */
    public $answers = array();

    public function __construct() {
        parent::__construct(new question_first_matching_answer_grading_strategy($this));
    }

    public function get_expected_data() {
        return array('answer' => PARAM_RAW_TRIMMED);
    }

    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function un_summarise_response(string $summary) {
        if (!empty($summary)) {
            return ['answer' => $summary];
        } else {
            return [];
        }
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) &&
                ($response['answer'] || $response['answer'] === '0');
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseenterananswer', 'qtype_longanswer');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
    }

    public function get_answers() {
        return $this->answers;
    }

    public function compare_response_with_answer(array $response, question_answer $answer) {
        if (!array_key_exists('answer', $response) || is_null($response['answer'])) {
            return false;
        }

        return self::compare_string_with_wildcard(
                $response['answer'], $answer->answer, !$this->usecase);
    }

    public static function compare_string_with_wildcard($string, $pattern, $ignorecase) {
        
        
    $text1 = preg_replace('/[^a-z0-9]+/i', ' ', $string);
    $text2 = preg_replace('/[^a-z0-9]+/i', ' ', $pattern);
    $setA = self::tokenize($text1);
    $setB = self::tokenize($text2);
    // If these sets are similar, the result is 1.00000;
    $result = self::similarity($setA, $setB);
    echo "$result";
    if ($result < 0.7) {
        return false;
    }
    return true;
        /*$pattern = self::safe_normalize($pattern);
        $string = self::safe_normalize($string);
        
        // Break the string on non-escaped runs of asterisks.
        // ** is equivalent to *, but people were doing that, and with many *s it breaks preg.
        $bits = preg_split('/(?<!\\\\)\*+/', $pattern);

        // Escape regexp special characters in the bits.
        $escapedbits = array();
        foreach ($bits as $bit) {
            $escapedbits[] = preg_quote(str_replace('\*', '*', $bit), '|');
        }
        // Put it back together to make the regexp.
        $regexp = '|^' . implode('.*', $escapedbits) . '$|u';

        // Make the match insensitive if requested to.
        if ($ignorecase) {
            $regexp .= 'i';
        }

        return preg_match($regexp, trim($string));*/
    }

    /**
     * Normalise a UTf-8 string to FORM_C, avoiding the pitfalls in PHP's
     * normalizer_normalize function.
     * @param string $string the input string.
     * @return string the normalised string.
     */
    protected static function safe_normalize($string) {
        if ($string === '') {
            return '';
        }

        if (!function_exists('normalizer_normalize')) {
            return $string;
        }

        $normalised = normalizer_normalize($string, Normalizer::FORM_C);
        if (is_null($normalised)) {
            // An error occurred in normalizer_normalize, but we have no idea what.
            debugging('Failed to normalise string: ' . $string, DEBUG_DEVELOPER);
            return $string; // Return the original string, since it is the best we have.
        }

        return $normalised;
    }

    public function get_correct_response() {
        $response = parent::get_correct_response();
        if ($response) {
            $response['answer'] = $this->clean_response($response['answer']);
        }
        return $response;
    }

    public function clean_response($answer) {
        // Break the string on non-escaped asterisks.
        $bits = preg_split('/(?<!\\\\)\*/', $answer);

        // Unescape *s in the bits.
        $cleanbits = array();
        foreach ($bits as $bit) {
            $cleanbits[] = str_replace('\*', '*', $bit);
        }

        // Put it back together with spaces to look nice.
        return trim(implode(' ', $cleanbits));
    }

    public function check_file_access($qa, $options, $component, $filearea,
            $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $this->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answer && $answerid == $answer->id;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
    }

    /**
     * Return the question settings that define this question as structured data.
     *
     * @param question_attempt $qa the current attempt for which we are exporting the settings.
     * @param question_display_options $options the question display options which say which aspects of the question
     * should be visible.
     * @return mixed structure representing the question settings. In web services, this will be JSON-encoded.
     */
    public function get_question_definition_for_external_rendering(question_attempt $qa, question_display_options $options) {
        // No need to return anything, external clients do not need additional information for rendering this question type.
        return null;
    }
    public static function removealphanum($line) {
        $ret = '';
        $l = strlen( $line );
        // Parse line to remove alphanum chars.
        for ($i = 0; $i < $l; $i ++) {
            $c = $line [$i];
            if (! ctype_alnum( $c ) && $c != ' ') {
                $ret .= $c;
            }
        }
        return $ret;
    }

    /**
     * Calculate the similarity of two lines
     *
     * @param
     *            $line1
     * @param
     *            $line2
     * @return int (3 => trimmed equal, 2 =>removealphanum , 1 => start of line , 0 => not equal)
     */
    public static function diffline($line1, $line2) {
        // TODO Refactor.
        // This is a bad solution that must be rebuild to consider diferent languages.
        // Compare trimed text.
        $line1 = trim( $line1 );
        $line2 = trim( $line2 );
        if ($line1 == $line2) {
            if (strlen( $line1 ) > 0) {
                return 3;
            } else {
                return 1;
            }
        }
        // Compare filtered text (removing alphanum).
        $ran1 = self::removealphanum( $line1 );
        $limit = strlen( $ran1 );
        if ($limit > 0) {
            if ($limit > 3) {
                $limit = 3;
            }
            if (strncmp( $ran1, self::removealphanum( $line2 ), $limit ) == 0) {
                return 2;
            }
        }
        // Compare start of line.
        $l = 4;
        if ($l > strlen( $line1 )) {
            $l = strlen( $line1 );
        }
        if ($l > strlen( $line2 )) {
            $l = strlen( $line2 );
        }
        for ($i = 0; $i < $l; ++ $i) {
            if ($line1 [$i] != $line2 [$i]) {
                break;
            }
        }
        return $i > 0 ? 1 : 0;
    }
    public function similarity(&$A, &$B)
    {

        if (!is_array($A) || !is_array($B)) {
            throw new \InvalidArgumentException('Vector $' . (!is_array($A) ? 'A' : 'B') . ' is not an array');
        }

        // This means they are simple text vectors
        // so we need to count to make them vectors
        if (is_int(key($A)))
            $v1 = array_count_values($A);
        else
            $v1 = &$A;
        if (is_int(key($B)))
            $v2 = array_count_values($B);
        else
            $v2 = &$B;

        $prod = 0.0;
        $v1_norm = 0.0;
        foreach ($v1 as $i=>$xi) {
            if (isset($v2[$i])) {
                $prod += $xi*$v2[$i];
            }
            $v1_norm += $xi*$xi;
        }
        $v1_norm = sqrt($v1_norm);
        if ($v1_norm==0)
            throw new \InvalidArgumentException("Vector \$A is the zero vector");

        $v2_norm = 0.0;
        foreach ($v2 as $i=>$xi) {
            $v2_norm += $xi*$xi;
        }
        $v2_norm = sqrt($v2_norm);
        if ($v2_norm==0)
            throw new \InvalidArgumentException("Vector \$B is the zero vector");

        return $prod/($v1_norm*$v2_norm);
    }
    const PATTERN = '/[\pZ\pC]+/u';

    public function tokenize($str)
    {
        $arr = array();

        return preg_split(self::PATTERN,$str,null,PREG_SPLIT_NO_EMPTY);
    }
}


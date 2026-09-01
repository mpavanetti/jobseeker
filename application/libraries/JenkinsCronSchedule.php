<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_CRON_SCHEDULE_TEST')) exit('No direct script access allowed');

/**
 * JenkinsCronSchedule
 *
 * Turns the Job Creation "Schedule Job" form into a single Jenkins
 * (hudson.triggers.TimerTrigger) <spec> string and validates it offline against
 * the grammar Jenkins' own CrontabParser accepts, so a bad schedule is rejected
 * with a clear message even when Jenkins is unreachable.
 *
 * The form has four mutually exclusive modes:
 *
 *   single       five multi-selects (minute / hour / day-of-month / month /
 *                day-of-week), each a list of integers or "*".
 *   repetitive   an interval minute ("*" or "every N minutes" -> H/N) plus four
 *                single selects for the remaining fields.
 *   tags         one of the @hourly ... @yearly aliases.
 *   cron         a free-text five-field Jenkins cron expression.
 *
 * build() returns:
 *   array{ok:bool, mode:string, spec:string, error:string, warnings:string[]}
 *
 * Grammar accepted per field (comma-separated terms):
 *   *            *\/S            H            H/S
 *   H(A-B)       H(A-B)/S        A            A-B          A-B/S
 * with A/B numeric or a 3-letter month / day-of-week name, every number inside
 * the field's own range and every step S >= 1. Quartz-only tokens (?, L, W, #)
 * are rejected because Jenkins core's TimerTrigger rejects them too.
 *
 * @author JobSeeker
 * @since  2026
 */
class JenkinsCronSchedule
{
    /** @var array<string,array{0:int,1:int,2:array<string,int>}> field name => [min, max, names] */
    private $fields;

    /** @var string[] */
    private $aliases = array('@hourly', '@daily', '@midnight', '@weekly', '@monthly', '@yearly', '@annually');

    public function __construct()
    {
        $months = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12);
        $days = array('sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6);

        $this->fields = array(
            'minute'      => array(0, 59, array()),
            'hour'        => array(0, 23, array()),
            'dayOfMonth'  => array(1, 31, array()),
            'month'       => array(1, 12, $months),
            'dayOfWeek'   => array(0, 7, $days),
        );
    }

    private function fieldOrder()
    {
        return array('minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek');
    }

    // ------------------------------------------------------------------
    // Form -> spec
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $input  raw request data: checkBuild, action, the
     *                                     single... / repetitive... selects, tag, customCronExpression
     * @return array{ok:bool,mode:string,spec:string,error:string,warnings:string[]}
     */
    public function build(array $input)
    {
        $enabled = $this->truthy($this->get($input, 'checkBuild'));
        if (! $enabled) {
            return $this->result(TRUE, 'none', '', '');
        }

        $action = strtolower(trim((string) $this->get($input, 'action')));

        switch ($action) {
            case 'single':
                return $this->buildSingle($input);
            case 'repetitive':
                return $this->buildRepetitive($input);
            case 'tags':
                return $this->buildTag($input);
            case 'cron':
                return $this->buildCustom($input);
            default:
                return $this->result(FALSE, $action, '', 'Choose a schedule action (single, repetitive, tag or custom cron).');
        }
    }

    private function buildSingle(array $input)
    {
        $map = array(
            'minute'     => 'singleMinute',
            'hour'       => 'singleHour',
            'dayOfMonth' => 'singleDayOfMonth',
            'month'      => 'singleMonth',
            'dayOfWeek'  => 'singleDayOfWeek',
        );

        $parts = array();
        foreach ($this->fieldOrder() as $field) {
            $term = $this->listFieldTerm($this->get($input, $map[$field]), $field);
            if ($term === FALSE) {
                return $this->result(FALSE, 'single', '', 'The "'.$this->label($field).'" value for Single Execution scheduling is not valid.');
            }
            $parts[] = $term;
        }

        return $this->finalize('single', implode(' ', $parts));
    }

    private function buildRepetitive(array $input)
    {
        $minute = $this->intervalMinuteTerm($this->get($input, 'repetitiveMinute'));
        if ($minute === FALSE) {
            return $this->result(FALSE, 'repetitive', '', 'Choose "All" or a value between 1 and 59 for the repeat interval in minutes.');
        }

        $map = array('hour' => 'repetitiveHour', 'dayOfMonth' => 'repetitiveDayOfMonth', 'month' => 'repetitiveMonth', 'dayOfWeek' => 'repetitiveDayOfWeek');
        $parts = array($minute);
        foreach (array('hour', 'dayOfMonth', 'month', 'dayOfWeek') as $field) {
            $term = $this->listFieldTerm($this->get($input, $map[$field]), $field);
            if ($term === FALSE) {
                return $this->result(FALSE, 'repetitive', '', 'The "'.$this->label($field).'" value for Repetitive Execution scheduling is not valid.');
            }
            $parts[] = $term;
        }

        return $this->finalize('repetitive', implode(' ', $parts));
    }

    private function buildTag(array $input)
    {
        $tag = strtolower(trim((string) $this->get($input, 'tag')));
        if (! in_array($tag, $this->aliases, TRUE)) {
            return $this->result(FALSE, 'tags', '', 'Choose one of the execution tag options (@hourly ... @yearly).');
        }

        return $this->result(TRUE, 'tags', $tag, '');
    }

    private function buildCustom(array $input)
    {
        $raw = preg_replace('/\s+/', ' ', trim((string) $this->get($input, 'customCronExpression')));
        if ($raw === '') {
            return $this->result(FALSE, 'cron', '', 'Enter a Jenkins cron expression such as "H 2 * * 1-5".');
        }
        if (strlen($raw) > 120) {
            return $this->result(FALSE, 'cron', '', 'The cron expression is too long (120 characters maximum).');
        }

        return $this->finalize('cron', $raw);
    }

    private function finalize($mode, $spec)
    {
        $check = $this->validateSpec($spec);
        if (! $check['ok']) {
            return $this->result(FALSE, $mode, '', $check['error']);
        }

        return $this->result(TRUE, $mode, $check['normalized'], '', $check['warnings']);
    }

    // ------------------------------------------------------------------
    // Offline spec validation (Jenkins CrontabParser grammar)
    // ------------------------------------------------------------------

    /**
     * @param  string $spec
     * @return array{ok:bool,error:string,normalized:string,warnings:string[]}
     */
    public function validateSpec($spec)
    {
        $spec = preg_replace('/\s+/', ' ', trim((string) $spec));

        if ($spec === '') {
            return array('ok' => FALSE, 'error' => 'The schedule is empty.', 'normalized' => '', 'warnings' => array());
        }

        if (strpos($spec, "\n") !== FALSE) {
            return array('ok' => FALSE, 'error' => 'Enter a single schedule line.', 'normalized' => '', 'warnings' => array());
        }

        if ($spec[0] === '@') {
            $alias = strtolower($spec);
            if (! in_array($alias, $this->aliases, TRUE)) {
                return array('ok' => FALSE, 'error' => 'Unknown schedule alias "'.$spec.'".', 'normalized' => '', 'warnings' => array());
            }
            return array('ok' => TRUE, 'error' => '', 'normalized' => $alias, 'warnings' => array());
        }

        if (preg_match('/[?LW#]/i', $spec)) {
            return array('ok' => FALSE, 'error' => 'Jenkins timer triggers do not support the "?", "L", "W" or "#" tokens. Use "*", "H", ranges (1-5), lists (1,15,30) or steps (*/15).', 'normalized' => '', 'warnings' => array());
        }

        $parts = explode(' ', $spec);
        if (count($parts) !== 5) {
            return array('ok' => FALSE, 'error' => 'A Jenkins cron expression needs exactly 5 fields: minute hour day-of-month month day-of-week. Got '.count($parts).'.', 'normalized' => '', 'warnings' => array());
        }

        $order = $this->fieldOrder();
        foreach ($parts as $index => $token) {
            $error = $this->validateFieldToken($token, $order[$index]);
            if ($error !== '') {
                return array('ok' => FALSE, 'error' => 'Field '.($index + 1).' ('.$this->label($order[$index]).'): '.$error, 'normalized' => '', 'warnings' => array());
            }
        }

        return array('ok' => TRUE, 'error' => '', 'normalized' => implode(' ', $parts), 'warnings' => $this->specWarnings($parts));
    }

    private function validateFieldToken($token, $field)
    {
        if ($token === '') {
            return 'empty value.';
        }

        list($min, $max, $names) = $this->fields[$field];

        foreach (explode(',', $token) as $term) {
            if ($term === '') {
                return 'empty list item.';
            }

            $step = NULL;
            if (strpos($term, '/') !== FALSE) {
                $bits = explode('/', $term);
                if (count($bits) !== 2 || ! ctype_digit($bits[1]) || (int) $bits[1] < 1) {
                    return '"'.$term.'" has an invalid step.';
                }
                $step = (int) $bits[1];
                $term = $bits[0];
            }

            if ($term === '*') {
                continue;
            }

            if ($term === 'H') {
                continue;
            }

            if (preg_match('/^H\((.+)\)$/', $term, $m)) {
                $rangeError = $this->validateNumericRange($m[1], $min, $max, $names);
                if ($rangeError !== '') {
                    return 'hash range '.$rangeError;
                }
                continue;
            }

            if ($step !== NULL && $term === '*') {
                continue;
            }

            $rangeError = $this->validateNumericRange($term, $min, $max, $names);
            if ($rangeError !== '') {
                return $rangeError;
            }
        }

        return '';
    }

    private function validateNumericRange($value, $min, $max, $names)
    {
        if (strpos($value, '-') !== FALSE && $value[0] !== '-') {
            $ends = explode('-', $value);
            if (count($ends) !== 2) {
                return '"'.$value.'" is not a valid range.';
            }
            $lo = $this->numberFor($ends[0], $names);
            $hi = $this->numberFor($ends[1], $names);
            if ($lo === NULL || $hi === NULL) {
                return '"'.$value.'" is not a valid range.';
            }
            if ($lo < $min || $hi > $max || $lo > $hi) {
                return '"'.$value.'" is outside the allowed '.$min.'-'.$max.' range.';
            }
            return '';
        }

        $number = $this->numberFor($value, $names);
        if ($number === NULL) {
            return '"'.$value.'" is not a number'.(empty($names) ? '.' : ' or recognised name.');
        }
        if ($number < $min || $number > $max) {
            return '"'.$value.'" is outside the allowed '.$min.'-'.$max.' range.';
        }
        return '';
    }

    private function numberFor($token, $names)
    {
        $token = trim($token);
        if ($token === '') {
            return NULL;
        }
        if (ctype_digit($token)) {
            return (int) $token;
        }
        $key = strtolower($token);
        return isset($names[$key]) ? $names[$key] : NULL;
    }

    private function specWarnings($parts)
    {
        $warnings = array();

        $everyMinute = ($parts[0] === '*' || $parts[0] === '*/1');
        if ($everyMinute && $parts[1] === '*' && $parts[2] === '*' && $parts[3] === '*' && $parts[4] === '*') {
            $warnings[] = 'This schedule runs the job every minute. Use "H/15 * * * *" or a specific minute unless that is intended.';
        } elseif ($parts[0] === '*') {
            $warnings[] = 'The minute field is "*", so the job runs 60 times an hour whenever the other fields match. Set a minute or use "H".';
        }

        if (strpos(implode(' ', $parts), 'H') === FALSE && $parts[0] !== '*') {
            $warnings[] = 'Consider "H" instead of a fixed minute so Jenkins can spread load (for example "H 2 * * *").';
        }

        return $warnings;
    }

    // ------------------------------------------------------------------
    // Field term helpers (form values -> one cron field)
    // ------------------------------------------------------------------

    /**
     * A multi-select / single-select value list -> "*", "N" or "A,B,C".
     * Returns FALSE when a value is non-numeric or out of range.
     *
     * @return string|false
     */
    private function listFieldTerm($values, $field)
    {
        list($min, $max) = $this->fields[$field];

        if (! is_array($values)) {
            $values = array($values);
        }

        $clean = array();
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if ($value === '*') {
                $clean[] = '*';
                continue;
            }
            if (! ctype_digit($value)) {
                return FALSE;
            }
            $number = (int) $value;
            if ($number < $min || $number > $max) {
                return FALSE;
            }
            $clean[] = (string) $number;
        }

        $clean = array_values(array_unique($clean));
        if (empty($clean)) {
            return FALSE;
        }

        if (in_array('*', $clean, TRUE)) {
            return '*';
        }

        sort($clean, SORT_NUMERIC);
        return implode(',', $clean);
    }

    /**
     * The "In X Minutes" select -> "*" or "H/N".
     *
     * @return string|false
     */
    private function intervalMinuteTerm($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '*') {
            return '*';
        }
        if (! ctype_digit($value)) {
            return FALSE;
        }

        $number = (int) $value;
        if ($number < 1 || $number > 59) {
            return FALSE;
        }

        return 'H/'.$number;
    }

    // ------------------------------------------------------------------
    // Small utilities
    // ------------------------------------------------------------------

    private function label($field)
    {
        $labels = array(
            'minute' => 'minute', 'hour' => 'hour', 'dayOfMonth' => 'day of month',
            'month' => 'month', 'dayOfWeek' => 'day of week',
        );
        return isset($labels[$field]) ? $labels[$field] : $field;
    }

    private function get(array $input, $key)
    {
        return array_key_exists($key, $input) ? $input[$key] : NULL;
    }

    private function truthy($value)
    {
        if (is_array($value)) {
            return ! empty($value);
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, array('1', 'true', 'yes', 'on'), TRUE);
    }

    private function result($ok, $mode, $spec, $error, $warnings = array())
    {
        return array(
            'ok'       => (bool) $ok,
            'mode'     => (string) $mode,
            'spec'     => (string) $spec,
            'error'    => (string) $error,
            'warnings' => array_values($warnings),
        );
    }
}

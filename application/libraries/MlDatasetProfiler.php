<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cheap in-process dataset profiler. Given a local file it derives a schema
 * (column name + inferred type), per-column statistics, a small sample preview
 * and a "fingerprint" (histograms / category counts) that MlDriftAnalyzer uses
 * as a drift baseline.
 *
 * Handles CSV / TSV / JSON (array of objects) / JSONL natively by streaming a
 * bounded number of rows. Parquet, images and other binary formats are flagged
 * `needs_runtime_profile` so a runtime job (or the jobseeker_ml SDK from the
 * producing run) can post a full profile instead.
 */
class MlDatasetProfiler
{
    const MAX_ROWS = 50000;
    const SAMPLE_ROWS = 25;
    const HIST_BINS = 20;
    const TOP_CATEGORIES = 20;

    public function profile($path, $options = array())
    {
        if (! is_file($path) || ! is_readable($path)) {
            return array('ok' => FALSE, 'message' => 'Dataset file is not readable.');
        }
        $format = strtolower((string) (isset($options['format']) ? $options['format']
            : pathinfo($path, PATHINFO_EXTENSION)));

        if (in_array($format, array('csv', 'tsv', 'txt'), TRUE)) {
            $delimiter = $format === 'tsv' ? "\t" : (isset($options['delimiter']) ? $options['delimiter'] : ',');
            return $this->finalize($this->readDelimited($path, $delimiter, ! isset($options['header']) || $options['header']));
        }
        if ($format === 'jsonl' || $format === 'ndjson') {
            return $this->finalize($this->readJsonl($path));
        }
        if ($format === 'json') {
            return $this->finalize($this->readJsonArray($path));
        }
        return array(
            'ok' => TRUE,
            'needs_runtime_profile' => TRUE,
            'format' => $format,
            'message' => strtoupper($format).' profiling runs in the runtime. The producing run\'s SDK will post the profile.',
            'schema' => array(),
            'profile' => array(),
            'fingerprint' => array('columns' => array()),
            'sample' => array('columns' => array(), 'rows' => array()),
            'row_count' => NULL,
            'column_count' => NULL,
        );
    }

    // --- readers ------------------------------------------------------

    private function readDelimited($path, $delimiter, $hasHeader)
    {
        $handle = fopen($path, 'rb');
        if ($handle === FALSE) {
            return NULL;
        }
        $columns = array();
        $rows = array();
        $rowNumber = 0;
        while (($record = fgetcsv($handle, 0, $delimiter)) !== FALSE && $rowNumber <= self::MAX_ROWS) {
            if ($rowNumber === 0) {
                if ($hasHeader) {
                    $columns = array_map('strval', $record);
                    $rowNumber++;
                    continue;
                }
                $columns = array();
                for ($i = 0; $i < count($record); $i++) {
                    $columns[] = 'col_'.($i + 1);
                }
            }
            $assoc = array();
            foreach ($columns as $i => $name) {
                $assoc[$name] = array_key_exists($i, $record) ? $record[$i] : NULL;
            }
            $rows[] = $assoc;
            $rowNumber++;
        }
        $truncated = ($record !== FALSE);
        fclose($handle);
        return array('columns' => $columns, 'rows' => $rows, 'truncated' => $truncated);
    }

    private function readJsonl($path)
    {
        $handle = fopen($path, 'rb');
        if ($handle === FALSE) {
            return NULL;
        }
        $rows = array();
        $columns = array();
        $count = 0;
        while (($line = fgets($handle)) !== FALSE && $count <= self::MAX_ROWS) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, TRUE);
            if (! is_array($decoded)) {
                continue;
            }
            foreach (array_keys($decoded) as $key) {
                if (! in_array((string) $key, $columns, TRUE)) {
                    $columns[] = (string) $key;
                }
            }
            $rows[] = $decoded;
            $count++;
        }
        $truncated = ($line !== FALSE);
        fclose($handle);
        return array('columns' => $columns, 'rows' => $rows, 'truncated' => $truncated);
    }

    private function readJsonArray($path)
    {
        if (filesize($path) > 33554432) {
            return array('columns' => array(), 'rows' => array(), 'truncated' => TRUE, 'oversize' => TRUE);
        }
        $decoded = json_decode((string) file_get_contents($path), TRUE);
        if (! is_array($decoded)) {
            return NULL;
        }
        if ($decoded && array_keys($decoded) !== range(0, count($decoded) - 1)) {
            $decoded = array($decoded);
        }
        $rows = array_slice($decoded, 0, self::MAX_ROWS);
        $columns = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                foreach (array_keys($row) as $key) {
                    if (! in_array((string) $key, $columns, TRUE)) {
                        $columns[] = (string) $key;
                    }
                }
            }
        }
        return array('columns' => $columns, 'rows' => $rows, 'truncated' => count($decoded) > self::MAX_ROWS);
    }

    // --- analysis ------------------------------------------------------

    private function finalize($read)
    {
        if ($read === NULL) {
            return array('ok' => FALSE, 'message' => 'Could not parse the dataset.');
        }
        $columns = $read['columns'];
        $rows = $read['rows'];
        $rowCount = count($rows);

        $schema = array();
        $profile = array();
        $fingerprint = array('columns' => array());

        foreach ($columns as $name) {
            $values = array();
            $missing = 0;
            foreach ($rows as $row) {
                $v = is_array($row) && array_key_exists($name, $row) ? $row[$name] : NULL;
                if ($v === NULL || $v === '' || (is_string($v) && strtolower(trim($v)) === 'null')) {
                    $missing++;
                    continue;
                }
                $values[] = $v;
            }
            $numeric = $this->numericView($values);
            if ($numeric !== NULL && count($numeric) >= max(1, (int) round(count($values) * 0.8))) {
                $col = $this->numericColumn($numeric, count($values), $missing);
                $schema[$name] = array('name' => $name, 'type' => 'numeric');
            } else {
                $col = $this->categoricalColumn($values, $missing);
                $schema[$name] = array('name' => $name, 'type' => $col['distinct'] <= 2 ? 'boolean' : 'categorical');
            }
            $profile[$name] = $col['profile'];
            $fingerprint['columns'][$name] = $col['fingerprint'];
        }

        $fingerprint['row_count'] = $rowCount;
        $sample = array(
            'columns' => $columns,
            'rows' => array_map(function ($row) use ($columns) {
                $out = array();
                foreach ($columns as $c) {
                    $val = is_array($row) && array_key_exists($c, $row) ? $row[$c] : NULL;
                    if (is_array($val)) {
                        $val = json_encode($val, JSON_UNESCAPED_SLASHES);
                    }
                    $out[] = $val === NULL ? NULL : (is_string($val) && strlen($val) > 200 ? substr($val, 0, 197).'...' : $val);
                }
                return $out;
            }, array_slice($rows, 0, self::SAMPLE_ROWS)),
        );

        return array(
            'ok' => TRUE,
            'needs_runtime_profile' => FALSE,
            'row_count' => $rowCount,
            'row_count_is_lower_bound' => ! empty($read['truncated']),
            'column_count' => count($columns),
            'schema' => array_values($schema),
            'profile' => $profile,
            'fingerprint' => $fingerprint,
            'sample' => $sample,
        );
    }

    private function numericView(array $values)
    {
        $out = array();
        foreach ($values as $v) {
            if (is_bool($v) || is_array($v)) {
                continue;
            }
            if (is_int($v) || is_float($v)) {
                $out[] = (float) $v;
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '' && is_numeric($s)) {
                $out[] = (float) $s;
            }
        }
        return $out ?: NULL;
    }

    private function numericColumn(array $nums, $nonNull, $missing)
    {
        sort($nums);
        $n = count($nums);
        $sum = array_sum($nums);
        $mean = $n ? $sum / $n : 0.0;
        $var = 0.0;
        foreach ($nums as $x) {
            $var += ($x - $mean) * ($x - $mean);
        }
        $std = $n > 1 ? sqrt($var / ($n - 1)) : 0.0;
        $min = $n ? $nums[0] : 0.0;
        $max = $n ? $nums[$n - 1] : 0.0;
        $q = function ($p) use ($nums, $n) {
            if ($n === 0) {
                return 0.0;
            }
            $idx = (int) floor($p * ($n - 1));
            return $nums[max(0, min($n - 1, $idx))];
        };

        $edges = array();
        $counts = array_fill(0, self::HIST_BINS, 0);
        if ($max > $min) {
            $step = ($max - $min) / self::HIST_BINS;
            for ($i = 0; $i <= self::HIST_BINS; $i++) {
                $edges[] = $min + $step * $i;
            }
            foreach ($nums as $x) {
                $bin = (int) floor(($x - $min) / $step);
                $bin = max(0, min(self::HIST_BINS - 1, $bin));
                $counts[$bin]++;
            }
        } else {
            $edges = array($min, $max);
            $counts = array($n);
        }

        return array(
            'profile' => array(
                'type' => 'numeric',
                'count' => $nonNull,
                'missing' => $missing,
                'missing_rate' => $nonNull + $missing > 0 ? round($missing / ($nonNull + $missing), 4) : 0,
                'mean' => round($mean, 6),
                'std' => round($std, 6),
                'min' => $min,
                'max' => $max,
                'p25' => $q(0.25),
                'p50' => $q(0.50),
                'p75' => $q(0.75),
                'histogram' => array('edges' => $edges, 'counts' => $counts),
            ),
            'fingerprint' => array(
                'type' => 'numeric',
                'count' => $nonNull,
                'missing' => $missing,
                'mean' => round($mean, 6),
                'std' => round($std, 6),
                'min' => $min,
                'max' => $max,
                'histogram' => array('edges' => $edges, 'counts' => $counts),
            ),
        );
    }

    private function categoricalColumn(array $values, $missing)
    {
        $counts = array();
        foreach ($values as $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $key = (string) $v;
            if (strlen($key) > 120) {
                $key = substr($key, 0, 117).'...';
            }
            $counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;
        }
        arsort($counts);
        $distinct = count($counts);
        $top = array_slice($counts, 0, self::TOP_CATEGORIES, TRUE);
        $nonNull = count($values);
        return array(
            'distinct' => $distinct,
            'profile' => array(
                'type' => 'categorical',
                'count' => $nonNull,
                'missing' => $missing,
                'missing_rate' => $nonNull + $missing > 0 ? round($missing / ($nonNull + $missing), 4) : 0,
                'distinct' => $distinct,
                'top' => $top,
            ),
            'fingerprint' => array(
                'type' => 'categorical',
                'count' => $nonNull,
                'missing' => $missing,
                'distinct' => $distinct,
                'top' => $top,
            ),
        );
    }
}

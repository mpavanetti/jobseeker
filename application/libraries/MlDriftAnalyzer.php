<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Tabular drift maths in pure PHP. Compares a baseline dataset-version
 * "fingerprint" (written by MlDatasetProfiler / the SDK) against a current one
 * and returns per-feature and overall drift scores plus threshold breaches.
 *
 * Fingerprint shape (per column):
 *   numeric     -> {type:"numeric", count, missing, mean, std, min, max,
 *                   histogram:{edges:[...], counts:[...]}}
 *   categorical -> {type:"categorical", count, missing, top:{value:count,...}}
 *
 * Metrics:
 *   drift_psi   Population Stability Index over the shared binning
 *   drift_kl    KL divergence current||baseline (nats)
 *   mean_shift  |mean_cur - mean_base| / (std_base or 1)   (numeric only)
 *   missing_delta  |missing_rate_cur - missing_rate_base|
 */
class MlDriftAnalyzer
{
    const DEFAULTS = array(
        'psi_warning' => 0.1,
        'psi_critical' => 0.25,
        'kl_warning' => 0.1,
        'mean_shift_warning' => 0.5,
        'missing_delta_warning' => 0.1,
    );

    public function compare(array $baseline, array $current, array $thresholds = array())
    {
        $t = array_merge(self::DEFAULTS, $thresholds);
        $baseCols = isset($baseline['columns']) && is_array($baseline['columns']) ? $baseline['columns'] : $baseline;
        $curCols = isset($current['columns']) && is_array($current['columns']) ? $current['columns'] : $current;

        $features = array();
        $psiValues = array();
        foreach ($baseCols as $name => $base) {
            if (! isset($curCols[$name]) || ! is_array($base)) {
                $features[$name] = array('status' => 'missing_in_current', 'metrics' => array());
                continue;
            }
            $cur = $curCols[$name];
            $type = isset($base['type']) ? $base['type'] : 'numeric';
            $metrics = array();

            if ($type === 'numeric' && isset($base['histogram']['counts'])) {
                $pb = $this->fromHistogram($base, $cur);
                $metrics['drift_psi'] = $this->psi($pb['base'], $pb['cur']);
                $metrics['drift_kl'] = $this->kl($pb['cur'], $pb['base']);
                $stdBase = max(1e-9, (float) (isset($base['std']) ? $base['std'] : 1));
                if (isset($base['mean'], $cur['mean'])) {
                    $metrics['mean_shift'] = abs(((float) $cur['mean']) - ((float) $base['mean'])) / $stdBase;
                }
            } elseif ($type === 'categorical') {
                $pb = $this->fromCategories($base, $cur);
                $metrics['drift_psi'] = $this->psi($pb['base'], $pb['cur']);
                $metrics['drift_kl'] = $this->kl($pb['cur'], $pb['base']);
            }

            $missBase = $this->rate($base, 'missing', 'count');
            $missCur = $this->rate($cur, 'missing', 'count');
            $metrics['missing_delta'] = abs($missCur - $missBase);

            $breaches = array();
            if (isset($metrics['drift_psi'])) {
                if ($metrics['drift_psi'] >= $t['psi_critical']) {
                    $breaches[] = array('metric' => 'drift_psi', 'level' => 'critical', 'threshold' => $t['psi_critical']);
                } elseif ($metrics['drift_psi'] >= $t['psi_warning']) {
                    $breaches[] = array('metric' => 'drift_psi', 'level' => 'warning', 'threshold' => $t['psi_warning']);
                }
                $psiValues[] = $metrics['drift_psi'];
            }
            if (isset($metrics['drift_kl']) && $metrics['drift_kl'] >= $t['kl_warning']) {
                $breaches[] = array('metric' => 'drift_kl', 'level' => 'warning', 'threshold' => $t['kl_warning']);
            }
            if (isset($metrics['mean_shift']) && $metrics['mean_shift'] >= $t['mean_shift_warning']) {
                $breaches[] = array('metric' => 'mean_shift', 'level' => 'warning', 'threshold' => $t['mean_shift_warning']);
            }
            if ($metrics['missing_delta'] >= $t['missing_delta_warning']) {
                $breaches[] = array('metric' => 'missing_delta', 'level' => 'warning', 'threshold' => $t['missing_delta_warning']);
            }

            $features[$name] = array(
                'type' => $type,
                'status' => $breaches ? 'drifted' : 'stable',
                'metrics' => array_map(function ($v) { return round($v, 5); }, $metrics),
                'breaches' => $breaches,
            );
        }

        $overallPsi = $psiValues ? array_sum($psiValues) / count($psiValues) : 0.0;
        $maxPsi = $psiValues ? max($psiValues) : 0.0;
        $driftedCount = count(array_filter($features, function ($f) { return isset($f['status']) && $f['status'] === 'drifted'; }));

        return array(
            'overall' => array(
                'drift_psi_mean' => round($overallPsi, 5),
                'drift_psi_max' => round($maxPsi, 5),
                'features_total' => count($features),
                'features_drifted' => $driftedCount,
                'status' => $maxPsi >= $t['psi_critical'] ? 'critical' : ($driftedCount > 0 ? 'warning' : 'ok'),
            ),
            'features' => $features,
            'thresholds' => $t,
        );
    }

    // --- distribution helpers ------------------------------------------------

    private function fromHistogram($base, $cur)
    {
        $baseCounts = array_map('floatval', $base['histogram']['counts']);
        // Re-bin current values into the baseline edges if the SDK gave raw
        // edges; otherwise fall back to the current histogram as-is.
        if (isset($cur['histogram']['counts']) && count($cur['histogram']['counts']) === count($baseCounts)) {
            $curCounts = array_map('floatval', $cur['histogram']['counts']);
        } elseif (isset($cur['histogram']['edges'], $cur['histogram']['counts'])
            && isset($base['histogram']['edges'])) {
            $curCounts = $this->rebin($base['histogram']['edges'], $cur['histogram']['edges'], $cur['histogram']['counts']);
        } else {
            $curCounts = array_fill(0, count($baseCounts), 0.0);
        }
        return array('base' => $this->normalize($baseCounts), 'cur' => $this->normalize($curCounts));
    }

    private function fromCategories($base, $cur)
    {
        $baseTop = isset($base['top']) && is_array($base['top']) ? $base['top'] : array();
        $curTop = isset($cur['top']) && is_array($cur['top']) ? $cur['top'] : array();
        $keys = array_values(array_unique(array_merge(array_keys($baseTop), array_keys($curTop))));
        $b = array();
        $c = array();
        foreach ($keys as $k) {
            $b[] = (float) (isset($baseTop[$k]) ? $baseTop[$k] : 0);
            $c[] = (float) (isset($curTop[$k]) ? $curTop[$k] : 0);
        }
        return array('base' => $this->normalize($b), 'cur' => $this->normalize($c));
    }

    private function rebin($targetEdges, $sourceEdges, $sourceCounts)
    {
        $bins = max(1, count($targetEdges) - 1);
        $out = array_fill(0, $bins, 0.0);
        $sn = min(count($sourceCounts), max(0, count($sourceEdges) - 1));
        for ($i = 0; $i < $sn; $i++) {
            $mid = (((float) $sourceEdges[$i]) + ((float) $sourceEdges[$i + 1])) / 2.0;
            for ($j = 0; $j < $bins; $j++) {
                if ($mid >= (float) $targetEdges[$j] && ($j === $bins - 1 || $mid < (float) $targetEdges[$j + 1])) {
                    $out[$j] += (float) $sourceCounts[$i];
                    break;
                }
            }
        }
        return $out;
    }

    private function normalize(array $counts)
    {
        $sum = array_sum($counts);
        if ($sum <= 0) {
            $n = max(1, count($counts));
            return array_fill(0, $n, 1.0 / $n);
        }
        return array_map(function ($v) use ($sum) { return $v / $sum; }, $counts);
    }

    public function psi(array $base, array $cur)
    {
        $n = min(count($base), count($cur));
        $psi = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $b = max($base[$i], 1e-6);
            $c = max($cur[$i], 1e-6);
            $psi += ($c - $b) * log($c / $b);
        }
        return $psi;
    }

    public function kl(array $p, array $q)
    {
        $n = min(count($p), count($q));
        $kl = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $pi = max($p[$i], 1e-6);
            $qi = max($q[$i], 1e-6);
            $kl += $pi * log($pi / $qi);
        }
        return $kl;
    }

    private function rate($col, $numeratorKey, $denominatorKey)
    {
        $num = isset($col[$numeratorKey]) ? (float) $col[$numeratorKey] : 0.0;
        $den = isset($col[$denominatorKey]) ? (float) $col[$denominatorKey] : 0.0;
        $total = $num + $den;
        return $total > 0 ? $num / $total : 0.0;
    }
}

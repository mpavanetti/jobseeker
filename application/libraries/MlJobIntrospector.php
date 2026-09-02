<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Static analysis of an ML job's entry script. Produces a best-guess run type
 * (train | batch_infer | evaluate | preprocess | tune) with a confidence score
 * and the signals it found, so both authoring surfaces can show an editable
 * "detected type" badge and pre-fill dataset roles.
 *
 * This is deliberately a lexical scan (imports, call patterns, SDK usage,
 * string literals) rather than a real Python parser: it runs on every save and
 * must never fault on odd code.
 */
class MlJobIntrospector
{
    const TYPES = array('train', 'batch_infer', 'evaluate', 'preprocess', 'tune', 'unknown');

    /** framework -> import needles */
    private $frameworks = array(
        'scikit-learn' => array('sklearn'),
        'xgboost'      => array('xgboost', 'xgb'),
        'lightgbm'     => array('lightgbm', 'lgb'),
        'pytorch'      => array('torch', 'lightning', 'pytorch_lightning'),
        'tensorflow'   => array('tensorflow', 'keras'),
        'statsmodels'  => array('statsmodels'),
        'prophet'      => array('prophet'),
        'transformers' => array('transformers'),
    );

    public function analyze($code, $args = '', $context = array())
    {
        $code = (string) $code;
        $lower = strtolower($code);
        $signals = array();
        $scores = array('train' => 0.0, 'batch_infer' => 0.0, 'evaluate' => 0.0, 'preprocess' => 0.0, 'tune' => 0.0);

        // --- frameworks -------------------------------------------------
        $frameworks = array();
        foreach ($this->frameworks as $name => $needles) {
            foreach ($needles as $needle) {
                if (preg_match('/\b(?:import|from)\s+'.preg_quote($needle, '/').'\b/', $lower)
                    || strpos($lower, $needle.'.') !== FALSE) {
                    $frameworks[] = $name;
                    break;
                }
            }
        }
        $frameworks = array_values(array_unique($frameworks));
        if ($frameworks) {
            $signals[] = 'ML frameworks: '.implode(', ', $frameworks);
        }

        // --- call / SDK patterns -------------------------------------------------
        $has = function ($needle) use ($lower) { return strpos($lower, $needle) !== FALSE; };

        if (preg_match('/\.fit\s*\(/', $lower) || preg_match('/\.train\s*\(/', $lower)) {
            $scores['train'] += 2.0; $signals[] = 'Calls .fit(/.train(';
        }
        if ($has('ml.log_model') || $has('log_model(') || $has('save_model(') || preg_match('/joblib\.dump\s*\(/', $lower)
            || preg_match('/torch\.save\s*\(/', $lower) || preg_match('/pickle\.dump\s*\(/', $lower)) {
            $scores['train'] += 2.0; $signals[] = 'Persists / logs a model artifact';
        }
        if (preg_match('/\.predict\s*\(/', $lower) || preg_match('/\.transform\s*\(/', $lower)
            || preg_match('/\.forward\s*\(/', $lower)) {
            $scores['batch_infer'] += 1.2; $scores['evaluate'] += 0.6; $signals[] = 'Calls .predict(/.transform(';
        }
        if ($has('ml.load_model') || $has('load_model(') || $has('joblib.load(') || $has('torch.load(')
            || $has('from_pretrained(')) {
            $scores['batch_infer'] += 1.6; $scores['evaluate'] += 0.8; $signals[] = 'Loads an existing model';
        }
        if ($has('ml.save_dataset') || $has('save_dataset(') || $has('register_predictions(')) {
            $scores['batch_infer'] += 1.0; $scores['preprocess'] += 1.0; $signals[] = 'Registers an output dataset';
        }
        if (preg_match('/\.to_(csv|parquet)\s*\(/', $lower)) {
            $scores['preprocess'] += 0.6; $scores['batch_infer'] += 0.4; $signals[] = 'Writes a tabular file';
        }
        if (preg_match('/\b(accuracy_score|f1_score|roc_auc_score|precision_score|recall_score|mean_squared_error|r2_score|classification_report|confusion_matrix)\b/', $lower)) {
            $scores['evaluate'] += 2.0; $scores['train'] += 0.3; $signals[] = 'Computes evaluation metrics';
        }
        if (preg_match('/\b(gridsearchcv|randomizedsearchcv|optuna|hyperopt|ray\.tune|bayes_opt)\b/', $lower)) {
            $scores['tune'] += 2.6; $signals[] = 'Hyper-parameter search';
        }
        if (preg_match('/\btrain_test_split\b/', $lower)) {
            $scores['train'] += 0.8; $signals[] = 'Splits train/test';
        }
        if ((preg_match('/\b(fillna|dropna|get_dummies|StandardScaler|OneHotEncoder|SimpleImputer|resample|feature_engineering)\b/i', $code))
            && ! preg_match('/\.fit\s*\(/', $lower)) {
            $scores['preprocess'] += 1.4; $signals[] = 'Feature / cleaning transforms without training';
        }
        if ($has('ml.load_dataset') || $has('load_dataset(')) {
            $signals[] = 'Reads a registered dataset via the SDK';
        }

        // --- CLI args hints -------------------------------------------------
        // An explicit `--mode <type>` / `--<type>` is a deliberate instruction
        // and outweighs weak code heuristics; a bare mention is a light nudge.
        $argl = strtolower((string) $args);
        foreach (array('train' => 'train', 'predict' => 'batch_infer', 'infer' => 'batch_infer',
                       'evaluate' => 'evaluate', 'eval' => 'evaluate', 'preprocess' => 'preprocess',
                       'tune' => 'tune', 'hpo' => 'tune') as $needle => $type) {
            $q = preg_quote($needle, '/');
            if (preg_match('/--(mode[= ])?'.$q.'(\s|$)/', $argl)) {
                $scores[$type] += 2.5; $signals[] = 'CLI arg sets mode "'.$needle.'"';
            } elseif (preg_match('/(^|\s)'.$q.'(\s|$)/', $argl)) {
                $scores[$type] += 0.8; $signals[] = 'CLI arg mentions "'.$needle.'"';
            }
        }

        // --- sample hint (author picked a template with a known type) -------
        if (! empty($context['sample_run_type']) && isset($scores[$context['sample_run_type']])) {
            $scores[$context['sample_run_type']] += 1.2;
            $signals[] = 'Started from a "'.$context['sample_run_type'].'" sample';
        }

        arsort($scores);
        $top = key($scores);
        $topScore = current($scores);
        next($scores);
        $secondScore = (float) current($scores);

        if ($topScore < 1.0) {
            return array(
                'run_type' => 'unknown',
                'confidence' => 0.2,
                'frameworks' => $frameworks,
                'signals' => $signals,
                'scores' => $this->normalizeScores($scores),
                'dataset_roles' => $this->inferDatasetRoles('unknown', $lower),
            );
        }

        $margin = $topScore - $secondScore;
        $confidence = max(0.25, min(0.98, ($topScore / ($topScore + 2.0)) * (0.6 + min(0.4, $margin / 4.0))));

        return array(
            'run_type' => $top,
            'confidence' => round($confidence, 3),
            'frameworks' => $frameworks,
            'signals' => $signals,
            'scores' => $this->normalizeScores($scores),
            'dataset_roles' => $this->inferDatasetRoles($top, $lower),
        );
    }

    private function normalizeScores($scores)
    {
        $out = array();
        $max = max(0.001, max(array_values($scores)));
        foreach ($scores as $k => $v) {
            $out[$k] = round(max(0.0, $v) / $max, 3);
        }
        return $out;
    }

    private function inferDatasetRoles($type, $lower)
    {
        switch ($type) {
            case 'train':
                return array(
                    array('role' => 'training', 'direction' => 'input', 'required' => TRUE),
                    array('role' => 'validation', 'direction' => 'input', 'required' => FALSE),
                );
            case 'batch_infer':
                return array(
                    array('role' => 'inference_input', 'direction' => 'input', 'required' => TRUE),
                    array('role' => 'predictions', 'direction' => 'output', 'required' => TRUE),
                );
            case 'evaluate':
                return array(
                    array('role' => 'evaluation', 'direction' => 'input', 'required' => TRUE),
                );
            case 'preprocess':
                return array(
                    array('role' => 'raw', 'direction' => 'input', 'required' => TRUE),
                    array('role' => 'features', 'direction' => 'output', 'required' => TRUE),
                );
            case 'tune':
                return array(
                    array('role' => 'training', 'direction' => 'input', 'required' => TRUE),
                );
            default:
                return array();
        }
    }

    public function label($type)
    {
        $map = array(
            'train' => 'Training', 'batch_infer' => 'Batch inference', 'evaluate' => 'Evaluation',
            'preprocess' => 'Preprocessing', 'tune' => 'Hyper-parameter tuning', 'unknown' => 'Unclassified',
        );
        return isset($map[$type]) ? $map[$type] : ucfirst((string) $type);
    }
}

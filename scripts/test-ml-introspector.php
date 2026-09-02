<?php
/**
 * Unit test for MlJobIntrospector - the "smart" job-type classifier.
 * Run:  php scripts/test-ml-introspector.php
 * (or:  docker run --rm -v "$PWD":/app -w /app php:8.1-cli php scripts/test-ml-introspector.php)
 */
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/MlJobIntrospector.php';

$introspector = new MlJobIntrospector();
$failures = 0;

function check($label, $cond) {
    global $failures;
    echo ($cond ? "  ok   " : "  FAIL ").$label."\n";
    if (! $cond) { $failures++; }
}

$train = <<<'PY'
import jobseeker_ml as ml
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
df = ml.load_dataset("training")
X_train, X_test, y_train, y_test = train_test_split(df.drop(columns="y"), df["y"])
clf = RandomForestClassifier().fit(X_train, y_train)
ml.log_metric("accuracy", 0.9)
ml.log_model(clf, register=True)
PY;
$r = $introspector->analyze($train);
check('train script -> train', $r['run_type'] === 'train');
check('train confidence > 0.5', $r['confidence'] > 0.5);
check('detects scikit-learn', in_array('scikit-learn', $r['frameworks'], TRUE));
check('suggests a training dataset role', $r['dataset_roles'][0]['role'] === 'training');

$infer = <<<'PY'
import jobseeker_ml as ml
model = ml.load_model("churn:production")
frame = ml.load_dataset("inference_input")
frame["prediction"] = model.predict(frame)
ml.save_dataset(frame, "churn-predictions", role="predictions")
PY;
$r = $introspector->analyze($infer);
check('inference script -> batch_infer', $r['run_type'] === 'batch_infer');
check('inference roles include predictions output', $r['dataset_roles'][1]['role'] === 'predictions');

$evalScript = <<<'PY'
import jobseeker_ml as ml
from sklearn.metrics import accuracy_score, roc_auc_score, confusion_matrix
model = ml.load_model("m")
X = ml.load_dataset("evaluation")
preds = model.predict(X.drop(columns="label"))
print(accuracy_score(X["label"], preds), roc_auc_score(X["label"], preds))
print(confusion_matrix(X["label"], preds))
PY;
$r = $introspector->analyze($evalScript);
check('evaluation script -> evaluate', $r['run_type'] === 'evaluate');

$prep = <<<'PY'
import pandas as pd
from sklearn.preprocessing import StandardScaler
from sklearn.impute import SimpleImputer
df = pd.read_csv("raw.csv")
df = df.fillna(0)
scaled = StandardScaler().fit_transform(SimpleImputer().fit_transform(df))
pd.DataFrame(scaled).to_parquet("features.parquet")
PY;
$r = $introspector->analyze($prep);
check('preprocess script -> preprocess', $r['run_type'] === 'preprocess');

$tune = <<<'PY'
import optuna
from sklearn.model_selection import GridSearchCV
def objective(trial):
    return trial.suggest_float("lr", 1e-4, 1e-1)
study = optuna.create_study()
study.optimize(objective, n_trials=50)
PY;
$r = $introspector->analyze($tune);
check('tuning script -> tune', $r['run_type'] === 'tune');

$r = $introspector->analyze("print('hello world')\nx = 1 + 1\n");
check('trivial script -> unknown / low confidence', $r['run_type'] === 'unknown' || $r['confidence'] < 0.4);

$r = $introspector->analyze("df.fillna(0)\ndf.to_csv('x.csv')", '--mode train');
check('CLI arg nudges toward train', $r['run_type'] === 'train');

echo $failures === 0 ? "\nALL PASSED\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);

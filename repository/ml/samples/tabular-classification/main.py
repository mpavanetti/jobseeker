"""Tabular classification starter for the JobSeeker ML platform.

JobSeeker runs this as `python -u /workspace/samples/tabular-classification/main.py`.
Params arrive as JOBSEEKER_ML_PARAMS (JSON); a dataset bound to the "training"
role is fetched with ml.load_dataset("training").
"""

from __future__ import annotations

import json
import os

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    confusion_matrix,
    f1_score,
    roc_auc_score,
)
from sklearn.model_selection import train_test_split

import jobseeker_ml as ml

PARAMS = json.loads(os.environ.get("JOBSEEKER_ML_PARAMS", "{}"))
N_ESTIMATORS = int(PARAMS.get("n_estimators", 300))
MAX_DEPTH = int(PARAMS.get("max_depth", 8))
TEST_SIZE = float(PARAMS.get("test_size", 0.2))
TARGET = PARAMS.get("target", "target")


def load_frame() -> pd.DataFrame:
    try:
        ds = ml.datasets.training           # typed handle for the bound "training" dataset
        frame = ds.read()
        if isinstance(frame, pd.DataFrame) and not frame.empty:
            print(f"Loaded {ds!r}: columns {ds.columns}")
            return frame
    except Exception as exc:  # noqa: BLE001
        print(f"No bound training dataset ({exc}); generating a synthetic one.")
    from sklearn.datasets import make_classification

    x, y = make_classification(n_samples=4000, n_features=12, n_informative=6,
                               n_redundant=2, n_classes=2, random_state=42)
    frame = pd.DataFrame(x, columns=[f"f{i}" for i in range(x.shape[1])])
    frame[TARGET] = y
    return frame


def main() -> None:
    ml.log_params({"n_estimators": N_ESTIMATORS, "max_depth": MAX_DEPTH, "test_size": TEST_SIZE})

    frame = load_frame()
    target = TARGET
    if target not in frame.columns:
        target = frame.columns[-1]
        print(f"target column '{TARGET}' not found; falling back to the last column '{target}'.")
    ml.log_param("target", target)
    y = frame[target]
    x = pd.get_dummies(frame.drop(columns=[target]), dummy_na=True).fillna(0.0)

    x_train, x_test, y_train, y_test = train_test_split(
        x, y, test_size=TEST_SIZE, random_state=42, stratify=y if y.nunique() > 1 else None
    )

    clf = RandomForestClassifier(n_estimators=N_ESTIMATORS, max_depth=MAX_DEPTH, n_jobs=-1, random_state=42)

    # A tiny "training curve" so the run detail has a metric series to chart.
    for step, trees in enumerate(range(max(20, N_ESTIMATORS // 5), N_ESTIMATORS + 1, max(1, N_ESTIMATORS // 5))):
        clf.set_params(n_estimators=trees)
        clf.fit(x_train, y_train)
        ml.log_metric("val_accuracy", float(accuracy_score(y_test, clf.predict(x_test))), step=step)

    preds = clf.predict(x_test)
    proba = clf.predict_proba(x_test)
    metrics = {
        "accuracy": float(accuracy_score(y_test, preds)),
        "f1": float(f1_score(y_test, preds, average="weighted")),
    }
    if proba.shape[1] == 2:
        metrics["roc_auc"] = float(roc_auc_score(y_test, proba[:, 1]))
    ml.log_metrics(metrics)
    print("metrics:", metrics)

    ml.log_confusion_matrix(confusion_matrix(y_test, preds).tolist(),
                            labels=sorted(np.unique(y).tolist()))
    ml.log_feature_importance(dict(zip(x.columns, clf.feature_importances_.astype(float))))

    ml.log_model(
        clf,
        name=os.environ.get("JOBSEEKER_ML_JOB_KEY", "tabular-classifier"),
        framework="sklearn",
        metrics=metrics,
        params={"n_estimators": N_ESTIMATORS, "max_depth": MAX_DEPTH},
        signature={"inputs": list(x.columns), "output": "class"},
        register=True,
    )
    print("done")


if __name__ == "__main__":
    main()

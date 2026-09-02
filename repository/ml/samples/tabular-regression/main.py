"""Tabular regression starter for the JobSeeker ML platform."""

from __future__ import annotations

import json
import os

import numpy as np
import pandas as pd
from sklearn.ensemble import GradientBoostingRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split

import jobseeker_ml as ml

PARAMS = json.loads(os.environ.get("JOBSEEKER_ML_PARAMS", "{}"))
N_ESTIMATORS = int(PARAMS.get("n_estimators", 400))
LEARNING_RATE = float(PARAMS.get("learning_rate", 0.05))
TARGET = PARAMS.get("target", "target")


def load_frame() -> pd.DataFrame:
    try:
        frame = ml.load_dataset("training")
        if isinstance(frame, pd.DataFrame) and not frame.empty:
            return frame
    except Exception as exc:  # noqa: BLE001
        print(f"No bound dataset ({exc}); using synthetic data.")
    from sklearn.datasets import make_regression

    x, y = make_regression(n_samples=4000, n_features=14, n_informative=8, noise=12.0, random_state=7)
    frame = pd.DataFrame(x, columns=[f"f{i}" for i in range(x.shape[1])])
    frame[TARGET] = y
    return frame


def main() -> None:
    ml.log_params({"n_estimators": N_ESTIMATORS, "learning_rate": LEARNING_RATE})
    frame = load_frame()
    y = frame[TARGET].astype(float)
    x = pd.get_dummies(frame.drop(columns=[TARGET]), dummy_na=True).fillna(0.0)
    x_train, x_test, y_train, y_test = train_test_split(x, y, test_size=0.2, random_state=42)

    model = GradientBoostingRegressor(n_estimators=N_ESTIMATORS, learning_rate=LEARNING_RATE, random_state=42)
    model.fit(x_train, y_train)

    for step, pred in enumerate(model.staged_predict(x_test)):
        if step % max(1, N_ESTIMATORS // 20) == 0:
            ml.log_metric("val_rmse", float(np.sqrt(mean_squared_error(y_test, pred))), step=step)

    pred = model.predict(x_test)
    metrics = {
        "rmse": float(np.sqrt(mean_squared_error(y_test, pred))),
        "mae": float(mean_absolute_error(y_test, pred)),
        "r2": float(r2_score(y_test, pred)),
    }
    ml.log_metrics(metrics)
    print("metrics:", metrics)

    ml.log_feature_importance(dict(zip(x.columns, model.feature_importances_.astype(float))))
    ml.log_model(model, name=os.environ.get("JOBSEEKER_ML_JOB_KEY", "tabular-regressor"),
                 framework="sklearn", metrics=metrics, register=True)


if __name__ == "__main__":
    main()

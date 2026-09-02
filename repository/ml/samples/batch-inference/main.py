"""Batch inference starter for the JobSeeker ML platform.

Loads a registered model, scores the bound "inference_input" dataset and
registers a "predictions" dataset. If nothing is bound it fabricates a small
frame so the sample still runs end to end.
"""

from __future__ import annotations

import json
import os

import numpy as np
import pandas as pd

import jobseeker_ml as ml

PARAMS = json.loads(os.environ.get("JOBSEEKER_ML_PARAMS", "{}"))
MODEL_REF = PARAMS.get("model", "tabular-classifier")
ID_COLUMN = PARAMS.get("id_column", "")


def load_input() -> pd.DataFrame:
    try:
        frame = ml.datasets.inference_input.read()
        if isinstance(frame, pd.DataFrame) and not frame.empty:
            return frame
    except Exception as exc:  # noqa: BLE001
        print(f"No bound inference_input ({exc}); generating synthetic rows.")
    return pd.DataFrame(np.random.RandomState(1).normal(size=(500, 12)),
                        columns=[f"f{i}" for i in range(12)])


def main() -> None:
    frame = load_input()
    ids = frame[ID_COLUMN] if ID_COLUMN and ID_COLUMN in frame.columns else pd.Series(range(len(frame)), name="row_id")
    features = pd.get_dummies(frame.drop(columns=[ID_COLUMN]) if ID_COLUMN in frame.columns else frame,
                              dummy_na=True).fillna(0.0)

    try:
        model = ml.load_model(MODEL_REF)
        if hasattr(model, "feature_names_in_"):
            features = features.reindex(columns=list(model.feature_names_in_), fill_value=0.0)
        preds = model.predict(features)
        scores = model.predict_proba(features)[:, -1] if hasattr(model, "predict_proba") else preds
    except Exception as exc:  # noqa: BLE001
        print(f"Could not load model '{MODEL_REF}' ({exc}); emitting a constant baseline.")
        preds = np.zeros(len(features))
        scores = np.full(len(features), 0.5)

    out = pd.DataFrame({"id": ids.to_numpy(), "prediction": np.asarray(preds), "score": np.asarray(scores, dtype=float)})
    ml.log_metrics({
        "prediction_count": float(len(out)),
        "output_mean": float(np.mean(out["score"])),
        "positive_rate": float(np.mean(np.asarray(preds, dtype=float) > 0.5)),
    })
    ml.save_dataset(out, key=f"{os.environ.get('JOBSEEKER_ML_JOB_KEY', 'batch')}-predictions",
                    role="predictions", fmt="csv")
    print(f"scored {len(out)} rows")


if __name__ == "__main__":
    main()

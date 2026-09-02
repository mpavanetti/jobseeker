"""Feature engineering starter for the JobSeeker ML platform (no training)."""

from __future__ import annotations

import json
import os

import numpy as np
import pandas as pd
from sklearn.impute import SimpleImputer
from sklearn.preprocessing import StandardScaler

import jobseeker_ml as ml

PARAMS = json.loads(os.environ.get("JOBSEEKER_ML_PARAMS", "{}"))
DROP_COLUMNS = list(PARAMS.get("drop_columns", []))


def load_raw() -> pd.DataFrame:
    try:
        frame = ml.load_dataset("raw")
        if isinstance(frame, pd.DataFrame) and not frame.empty:
            return frame
    except Exception as exc:  # noqa: BLE001
        print(f"No bound raw dataset ({exc}); generating synthetic rows.")
    rng = np.random.RandomState(3)
    return pd.DataFrame({
        "amount": rng.gamma(2.0, 40.0, size=2000),
        "age": rng.randint(18, 80, size=2000).astype(float),
        "segment": rng.choice(["a", "b", "c"], size=2000),
        "channel": rng.choice(["web", "store", "partner"], size=2000),
    })


def main() -> None:
    frame = load_raw().drop(columns=[c for c in DROP_COLUMNS if c in load_raw().columns], errors="ignore")
    numeric = frame.select_dtypes(include="number").columns.tolist()
    categorical = [c for c in frame.columns if c not in numeric]

    ml.log_params({"numeric_columns": len(numeric), "categorical_columns": len(categorical), "rows_in": len(frame)})

    out = pd.DataFrame(index=frame.index)
    if numeric:
        imputed = SimpleImputer(strategy="median").fit_transform(frame[numeric])
        scaled = StandardScaler().fit_transform(imputed)
        out = out.join(pd.DataFrame(scaled, columns=numeric, index=frame.index))
    if categorical:
        out = out.join(pd.get_dummies(frame[categorical], dummy_na=True, prefix=categorical))

    out = out.fillna(0.0)
    ml.log_metrics({"rows_out": float(len(out)), "features_out": float(out.shape[1])})
    ml.save_dataset(out, key=f"{os.environ.get('JOBSEEKER_ML_JOB_KEY', 'features')}-features",
                    role="features", fmt="csv")
    print(f"wrote {out.shape[0]} x {out.shape[1]} feature matrix")


if __name__ == "__main__":
    main()

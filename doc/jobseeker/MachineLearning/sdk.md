# jobseeker_ml — runtime SDK reference

`jobseeker_ml` ships in every JobSeeker ML runtime image (`repository/ml/sdk`).
A job imports it to stream results back while it runs. It is **stdlib-only**
(`urllib`); pandas is used opportunistically for datasets.

```python
import jobseeker_ml as ml
```

If `JOBSEEKER_ML_API` / `JOBSEEKER_ML_RUN_KEY` are not set the module runs in
**offline mode** — every call prints instead of posting — so the same `main.py`
works when you run it by hand.

## Environment the platform injects

| var | meaning |
|---|---|
| `JOBSEEKER_ML_API` | base URL of `machine-learning/runtime` (host rewritten to an IP) |
| `JOBSEEKER_ML_RUN_KEY` | the run this process belongs to |
| `JOBSEEKER_ML_RUN_TOKEN` | bearer token for the runtime endpoints |
| `JOBSEEKER_ML_PARAMS` | the job's params, JSON |
| `JOBSEEKER_ML_DATASETS` | role → binding map, JSON |
| `JOBSEEKER_ML_JOB_KEY`, `JOBSEEKER_ML_RUN_TYPE`, `JOBSEEKER_ML_ENVIRONMENT` | context |

## API

| call | effect |
|---|---|
| `ml.log_param(k, v)` / `ml.log_params({...})` | merge params onto the run |
| `ml.set_tags({...})` | merge string tags onto the run |
| `ml.log_metric(k, v, step=None)` | one metric point (scalar → step 0) |
| `ml.log_metrics({...}, step=None)` | many points at one step |
| `ml.log_artifact(path, name=None, role="artifact")` | upload a file |
| `ml.log_figure(fig, "name.png")` | upload a matplotlib figure |
| `ml.log_confusion_matrix(matrix, labels=None)` | store an evaluation JSON |
| `ml.log_feature_importance({feature: weight})` | store an evaluation JSON |
| `ml.log_model(obj, name=None, framework=None, metrics=None, params=None, signature=None, register=True)` | serialise (joblib / torch / pickle), upload, and (default) create a registered model version with lineage |
| `ml.load_model("key")` / `"key:3"` / `"key:production"` | download + deserialise a registered model |
| `ml.datasets.<role>` / `ml.datasets["role"]` | typed `Dataset` handle for a dataset bound to the job |
| `Dataset.read(as_="auto"\|"path"\|"polars")` | lazy download; pandas (default) or a file path |
| `Dataset.schema` / `.columns` / `.version` / `.rows` / `.format` / `.describe()` | metadata, resolved on first touch |
| `ml.dataset("key", version=None)` | ad-hoc `Dataset` for any registered data asset |
| `ml.load_dataset("role"\|"key", as_="auto"\|"path")` | low-level form of the accessor |
| `ml.save_dataset(df_or_path, key, name=None, role="output", fmt="csv", profile=True)` | upload, register an immutable `data_asset_versions` row, profile it, link lineage `run → dataset_version` |

## Typical training job

```python
import os, json
import jobseeker_ml as ml
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score

params = json.loads(os.environ.get("JOBSEEKER_ML_PARAMS", "{}"))
ds = ml.datasets.training                   # bound in the ML Jobs builder
print(ds.schema)                            # columns known before .read()
df = ds.read()
X, y = df.drop(columns="target"), df["target"]

clf = RandomForestClassifier(n_estimators=params.get("n_estimators", 300))
clf.fit(X, y)
ml.log_metric("accuracy", accuracy_score(y, clf.predict(X)))
ml.log_model(clf, name="churn", framework="sklearn", register=True)
```

## Batch inference job

```python
import jobseeker_ml as ml
model = ml.load_model("churn:production")
frame = ml.load_dataset("inference_input")
frame["prediction"] = model.predict(frame)
ml.log_metric("prediction_count", len(frame))
ml.save_dataset(frame, "churn-predictions", role="predictions")
```

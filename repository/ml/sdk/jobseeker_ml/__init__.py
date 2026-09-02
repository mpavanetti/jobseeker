"""JobSeeker ML platform runtime SDK.

A job running on a JobSeeker ML runtime imports this module to stream results
back to the platform while it runs::

    import jobseeker_ml as ml

    ml.log_params({"n_estimators": 300, "max_depth": 6})
    df = ml.load_dataset("training")            # resolves a bound dataset
    ...
    ml.log_metric("accuracy", 0.94)
    ml.log_metric("val_loss", 0.21, step=epoch)
    ml.log_model(clf, name="churn", framework="sklearn",
                 metrics={"accuracy": 0.94}, register=True)
    ml.save_dataset(predictions, "churn-predictions", role="predictions")

Everything is best-effort: when ``JOBSEEKER_ML_API`` is not set (e.g. running the
script locally) every call is a no-op that just prints, so the same file works
inside and outside the platform.

Transport is stdlib only (``urllib``); no third-party dependency is required by
the SDK itself. ``load_dataset`` / ``save_dataset`` return pandas objects when
pandas is importable, otherwise file paths.
"""

from .client import (
    Dataset,
    Run,
    active_run,
    dataset,
    datasets,
    init,
    load_dataset,
    load_model,
    log_artifact,
    log_confusion_matrix,
    log_feature_importance,
    log_figure,
    log_metric,
    log_metrics,
    log_model,
    log_param,
    log_params,
    save_dataset,
    set_tags,
)

__all__ = [
    "Dataset",
    "Run",
    "active_run",
    "dataset",
    "datasets",
    "init",
    "load_dataset",
    "load_model",
    "log_artifact",
    "log_confusion_matrix",
    "log_feature_importance",
    "log_figure",
    "log_metric",
    "log_metrics",
    "log_model",
    "log_param",
    "log_params",
    "save_dataset",
    "set_tags",
]

__version__ = "0.1.0"

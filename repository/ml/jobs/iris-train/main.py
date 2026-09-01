"""Train a small classifier on the Iris dataset and report metrics.

Runs as a single ephemeral container on the selected ML runtime
(`jobseeker/ml-runtime:cpu`). No network or GPU required.
"""

from sklearn.datasets import load_iris
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, classification_report
from sklearn.model_selection import train_test_split


def main() -> None:
    data = load_iris()
    x_train, x_test, y_train, y_test = train_test_split(
        data.data, data.target, test_size=0.25, random_state=42, stratify=data.target
    )

    model = RandomForestClassifier(n_estimators=200, random_state=42)
    model.fit(x_train, y_train)

    predictions = model.predict(x_test)
    accuracy = accuracy_score(y_test, predictions)

    print(f"accuracy: {accuracy:.4f}")
    print(classification_report(y_test, predictions, target_names=list(data.target_names)))

    importances = sorted(
        zip(data.feature_names, model.feature_importances_),
        key=lambda pair: pair[1],
        reverse=True,
    )
    print("feature importances:")
    for name, weight in importances:
        print(f"  {name:<20} {weight:.4f}")


if __name__ == "__main__":
    main()

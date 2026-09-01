"""Profile a small synthetic dataframe with pandas and numpy.

A fast dependency-light ML-runtime smoke test: builds a dataframe, prints
describe(), correlations, and a group-by aggregation.
"""

import numpy as np
import pandas as pd


def main() -> None:
    rng = np.random.default_rng(42)
    rows = 2_000
    frame = pd.DataFrame(
        {
            "segment": rng.choice(["free", "pro", "enterprise"], size=rows, p=[0.6, 0.3, 0.1]),
            "sessions": rng.poisson(8, size=rows),
            "revenue": rng.gamma(2.0, 40.0, size=rows).round(2),
            "tenure_days": rng.integers(1, 900, size=rows),
        }
    )

    print("shape:", frame.shape)
    print("\ndescribe:\n", frame.describe(numeric_only=True))
    print("\ncorrelations:\n", frame[["sessions", "revenue", "tenure_days"]].corr().round(3))
    print(
        "\nrevenue by segment:\n",
        frame.groupby("segment")["revenue"].agg(["count", "mean", "sum"]).round(2),
    )


if __name__ == "__main__":
    main()

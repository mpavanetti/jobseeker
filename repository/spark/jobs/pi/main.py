"""Estimate Pi with Spark - the canonical job-cluster smoke test.

Runs on an ephemeral JobSeeker Spark cluster (one master, N workers). The driver
is submitted with ``spark-submit --master spark://<master>:7077``; JobSeeker
tears the whole cluster down once this script exits.

Optional argument: number of sampling partitions (default 100).
"""

import sys
from operator import add
from random import random

from pyspark.sql import SparkSession


def inside_unit_circle(_: int) -> int:
    x = random() * 2 - 1
    y = random() * 2 - 1
    return 1 if x * x + y * y <= 1 else 0


def main() -> None:
    partitions = int(sys.argv[1]) if len(sys.argv) > 1 else 100
    samples = 100_000 * partitions

    spark = SparkSession.builder.appName("jobseeker-spark-pi").getOrCreate()
    try:
        count = (
            spark.sparkContext
            .parallelize(range(samples), partitions)
            .map(inside_unit_circle)
            .reduce(add)
        )
        pi = 4.0 * count / samples
        print(f"Pi is roughly {pi:.6f} (samples={samples}, partitions={partitions})")
    finally:
        spark.stop()


if __name__ == "__main__":
    main()

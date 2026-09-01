"""Read a bundled CSV, aggregate with the DataFrame API, print the result.

Demonstrates a realistic ETL shape: load -> group -> aggregate -> order. The
sample data lives next to this script and is mounted read-only into the driver
at ``/workspace/jobs/csv-aggregate/sales.csv``.
"""

import os

from pyspark.sql import SparkSession
from pyspark.sql import functions as F

DATA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "sales.csv")


def main() -> None:
    spark = SparkSession.builder.appName("jobseeker-csv-aggregate").getOrCreate()
    try:
        df = spark.read.option("header", True).option("inferSchema", True).csv(DATA)
        summary = (
            df.groupBy("region")
            .agg(
                F.round(F.sum("amount"), 2).alias("total_amount"),
                F.count("*").alias("orders"),
                F.round(F.avg("amount"), 2).alias("avg_order"),
            )
            .orderBy(F.col("total_amount").desc())
        )
        summary.show(truncate=False)
    finally:
        spark.stop()


if __name__ == "__main__":
    main()

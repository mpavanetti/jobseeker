"""Distributed word count over an in-memory corpus.

A small, dependency-free PySpark job that exercises shuffles and aggregation on
the ephemeral cluster. Prints the top words by frequency.

Optional argument: how many rows to print (default 15).
"""

import re
import sys

from pyspark.sql import SparkSession

CORPUS = [
    "the quick brown fox jumps over the lazy dog",
    "the dog barks and the fox runs into the woods",
    "a quick reader reads a quick book about a brown fox",
    "spark makes distributed data processing fast and expressive",
    "jobseeker provisions the spark cluster only when the job runs",
]


def main() -> None:
    top_n = int(sys.argv[1]) if len(sys.argv) > 1 else 15

    spark = SparkSession.builder.appName("jobseeker-word-count").getOrCreate()
    try:
        counts = (
            spark.sparkContext
            .parallelize(CORPUS, 4)
            .flatMap(lambda line: re.findall(r"[a-z]+", line.lower()))
            .map(lambda word: (word, 1))
            .reduceByKey(lambda a, b: a + b)
            .sortBy(lambda pair: pair[1], ascending=False)
            .take(top_n)
        )
        print(f"{'word':<20} count")
        print("-" * 27)
        for word, count in counts:
            print(f"{word:<20} {count}")
    finally:
        spark.stop()


if __name__ == "__main__":
    main()

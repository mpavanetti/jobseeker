# Data Assets

Data Assets combines the former Input Components, Output Components, and File Upload screens into one runtime-neutral catalog. An asset is a stable file contract: a key, format, environment/job scope, storage destination, and optional uploaded seed file.

Examples of useful contracts:

- `customer-reference`: a required PROD CSV uploaded by an operator and read by several jobs.
- `daily-orders`: a Parquet output written by one Python job and consumed by another.
- `vendor-payload`: a JSON handoff with separate DEV and PROD versions.

## Registering an asset

Open **Extract Transform Load → Data Assets**, then select:

1. A display name and stable key such as `customer-reference`.
2. **Input**, **Output**, or **Input + output**.
3. An environment and optional job scope. Exact matches win; `ALL` and `*` act as fallbacks.
4. A file format and runtime file name. CSV contracts also store delimiter, encoding, and header metadata.
5. An optional seed/replacement file. Every replacement increments the revision and records its size and SHA-256 checksum.

CSV, JSON, JSON Lines, text, and XML files have a bounded, read-only preview in the catalog. CSV and JSON Lines are streamed and limited to the first 20 records; large JSON files are not loaded into the web process. Excel, Parquet, and custom binary formats stay in the consumer runtime, where their native libraries can read them efficiently.

The resulting contract receives an Airflow-style URI such as:

```text
jobseeker://prod/shared/customer-reference
```

Jobs should resolve the key, not copy the physical path. JobSeeker publishes the catalog to `data-assets/manifest.json` inside the shared repository and injects the catalog location into Jenkins-agent and Docker runtimes.

## Python

The bundled SDK reads CSV, JSON, JSON Lines, text, XML, and binary assets without another dependency:

```python
from jobseeker import JobSeeker

with JobSeeker(environment="PROD", job="load-customers") as js:
    source = js.asset("customer-reference")
    rows = source.read()
```

For dataframe workloads, install pandas plus the engine required by Excel or Parquet:

```python
source = js.dataset("customer-reference")  # dataset is an alias
frame = source.read_dataframe()

target = js.asset("customer-summary", mode="output")
target.write_dataframe(frame.groupby("country", as_index=False).size())
```

The resolver selects the most specific contract in this order:

1. Exact environment + exact job.
2. Exact environment + shared job.
3. `ALL` environment + exact job.
4. `ALL` environment + shared job.

Missing required inputs fail with a message naming the asset and expected file. Use `required=False` for an optional catalog entry.

Inline previews resolve job-scoped contracts using the Job Name currently entered in Job Creation, not the temporary Jenkins preview name. When Job Name is empty, preview discovery intentionally uses Shared assets only. The selected preview environment must still match the contract or its `ALL` fallback.

## Shell and other runtimes

Every generated Linux job receives:

```text
JOBSEEKER_REPOSITORY_ROOT
JOBSEEKER_DATA_ASSETS_MANIFEST
JOBSEEKER_ENVIRONMENT
JOBSEEKER_JOB_NAME
JOBSEEKER_DATA_ASSET_JOB
```

Jenkins agents also include the SDK command-line resolver:

```sh
CUSTOMERS_FILE="$(jobseeker-asset customer-reference)"
python3 transform.py "$CUSTOMERS_FILE"
```

Use `jobseeker-asset customer-reference --metadata` to print the complete JSON contract. Container jobs receive the catalog and asset files through a temporary runtime volume; declared outputs are synchronized back to the shared repository after execution.

Talend jobs can continue using imported legacy paths, while new jobs should consume the manifest or a resolved path. Existing `job_info` and `job_output` rows are imported into the catalog without moving their files.

## Complete example

See [data_asset_job.py](../../../Python/code/data_asset_job.py) and [customer_reference.csv](../../../Python/code/customer_reference.csv).

After registering `customer-reference` as an Input CSV and `customer-summary` as an Output CSV, the example reads four customers, filters the three active rows, groups them by country, and writes:

```csv
country,active_customers
UK,1
US,2
```

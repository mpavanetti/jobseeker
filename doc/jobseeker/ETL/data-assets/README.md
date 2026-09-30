# Data Assets

Data Assets combines the former Input Components, Output Components, and File Upload screens into one runtime-neutral catalog. An asset is a stable data contract: a key, format, environment/job scope, runtime destination, and one of several source types.

The source says where the data lives. It also decides which formats and Connections make sense:

| Source | What it reads | Format | Connection |
| --- | --- | --- | --- |
| File | A file uploaded to JobSeeker or written by a job | CSV, JSON, JSON Lines, Excel, Parquet, XML, HTML, text, binary | none |
| Web page, REST API, or file URL | A page, API response, or downloadable file | the file's format | optional HTTP API |
| Google Sheet | A public, published, or private sheet | sheet rows, as CSV | optional Google Sheets API |
| Database table or view | A table in MySQL/MariaDB, PostgreSQL, SQL Server, Oracle, Snowflake, or Databricks | table rows, as CSV | required |
| Cloud storage or SFTP file | An object in S3 (or S3-compatible storage), Azure Blob, Azure Data Lake, Google Cloud Storage, or an SFTP server | the file's format | required |
| MongoDB collection or Elasticsearch index | Documents | documents, as JSON Lines | required |

Tables, sheets, and collections have their own shape, so the editor sets their format. A database table cannot be Parquet, for example: jobs always receive its rows as CSV. Only file assets can be outputs; every other source is a read-only input.

Credentials always stay in **Connections**. A Data Asset stores only the Connection key and non-secret coordinates: an API path, a spreadsheet ID and range, a schema and table, an object path, or a collection name. Table sources accept validated identifiers only; JobSeeker never stores or runs other SQL.

Examples of useful contracts:

- `customer-reference`: a required PROD CSV uploaded by an operator and read by several jobs.
- `daily-orders`: a Parquet output written by one Python job and consumed by another.
- `vendor-payload`: a JSON handoff with separate DEV and PROD versions.

## Registering an asset

Open **Extract Transform Load → Data Assets**, then select:

1. A display name and stable key such as `customer-reference`.
2. **Input**, **Output**, or **Input + output**.
3. An environment and optional job scope. Exact matches win; `ALL` and `*` act as fallbacks.
4. A source and format. CSV contracts also store delimiter, encoding, and header metadata.
5. Source coordinates and, when needed, a Connection. Uploaded files can be seeded or replaced; every replacement increments the revision and records its size and SHA-256 checksum.

## Previewing data

Every source has a bounded, read-only preview: the eye button in the catalog, beside each detected asset in Job Creation and Job View, and **Show data above logs** in Job Execution. Previews come from the **Data Preview service** (`data-preview` in Compose and Kubernetes), a small FastAPI application that reads sources with the same SDK code and drivers Jenkins jobs use, so a preview shows what a job will read.

- Tables, sheets, collections, and line formats show their first 20 records. Excel shows the chosen sheet and Parquet its schema and row count.
- A web page shows its first HTML table, or its text when it has none.
- JSON, Excel, and Parquet are read whole, up to 64 MB. Line formats read at most the first 1 MB.
- Public URLs may only reach public addresses, checked when they connect, including after redirects. An API behind a Connection may be internal, but its credentials are sent only to the Connection's host.

The web tier decides who may preview an asset. It sends the service only the asset's coordinates and the runtime payload of the one Connection that asset uses, and records that access in the Connection audit log as `granted-preview`. Cloud secret references such as Azure Key Vault or AWS Secrets Manager resolve inside the service, with its own identity, as they do on a Jenkins worker. If the service is down, uploaded CSV, JSON, text, XML, and HTML files still preview in the web tier; other sources report that the service is unreachable.

The resulting contract receives an Airflow-style URI such as:

```text
jobseeker://prod/shared/customer-reference
```

Jobs should resolve the key, not copy a physical path or source URL. JobSeeker publishes the catalog to `data-assets/manifest.json` inside the shared repository and injects the catalog location into Jenkins-agent and Docker runtimes. Connected sources are materialized into their declared runtime path when the asset resolves, so Python, shell, Hop, and container jobs use the same contract.

## Python

The bundled SDK reads CSV, JSON, JSON Lines, text, XML, and binary assets without another dependency:

```python
from jobseeker import JobSeeker

with JobSeeker(environment="PROD", job="load-customers") as js:
    source = js.asset("customer-reference")
    rows = source.read()
```

That code is unchanged for every source. For a connected source, the SDK takes the referenced Connection from the build's connector catalog, reads the source, and writes it to the asset's runtime path before returning it. Credentials never enter the Data Asset manifest. `asset.preview()` returns the same bounded sample the web UI shows.

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

For a connected or remote input, `jobseeker-asset` materializes the latest source before printing the path. Apache Hop receives that materialized path through the existing `${JOBSEEKER_ASSET_<KEY>}` variable.

Use `jobseeker-asset customer-reference --metadata` to print the complete JSON contract, or `--preview` for a bounded sample. Shell and Hop jobs on a Jenkins agent use the SDK installed in the Jenkins image, so rebuild that image after upgrading JobSeeker. Container jobs receive the catalog and asset files through a temporary runtime volume; declared outputs are synchronized back to the shared repository after execution.

Talend jobs can continue using imported legacy paths, while new jobs should consume the manifest or a resolved path. Existing `job_info` and `job_output` rows are imported into the catalog without moving their files.

## Complete example

See [data_asset_job.py](../../../Python/code/data_asset_job.py) and [customer_reference.csv](../../../Python/code/customer_reference.csv).

After registering `customer-reference` as an Input CSV and `customer-summary` as an Output CSV, the example reads four customers, filters the three active rows, groups them by country, and writes:

```csv
country,active_customers
UK,1
US,2
```

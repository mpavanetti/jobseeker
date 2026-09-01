# ETL Connectors

Connectors are named, environment-aware credential and endpoint contracts for Python, shell, Talend, and Docker jobs. A connector stores metadata and secret references; the ETL job still owns its database driver, HTTP client, storage SDK, or protocol implementation.

The connector catalog (`database_settings`) is the single connection registry for the whole product. ETL jobs resolve connectors at build time, and **Insight Studio** data sources are chosen from the same catalog. The former Insight Studio connection store (`visualization_connections`) is retired; on upgrade JobSeeker migrates each row into the catalog once and repoints published datasets (see [Insight Studio data sources](#insight-studio-data-sources)).

## Built-in connectors

A seeded connector named **`jobseeker-mariadb`** points at the JobSeeker application database itself (`ALL` environment, shared `*` job scope, encrypted local secret sourced from the app's own database configuration). It is a normal row: admins can edit, disable, or delete it. Use it for jobs or dashboards that read JobSeeker's own operational tables.

## Scope resolution

The connector environment is inherited from JobSeeker's global environment selector. It is not independently selectable in the connector form. Each connector also has a job scope. At runtime JobSeeker selects at most one row per key using this order:

1. Exact environment and exact job
2. Exact environment and shared job (`*`)
3. All environments and exact job
4. All environments and shared job

Use the same connector key across DEV, QA, UAT, and PROD to keep ETL code unchanged while endpoints and credentials vary.

## Connector and authentication types

Connector type is descriptive metadata. The catalog includes relational and document databases, Redis, Kafka, RabbitMQ, Elasticsearch/OpenSearch, SFTP, HTTP APIs, Snowflake, Databricks, AWS S3, Azure Blob/Data Lake, Google Cloud Storage, and generic credentials.

Target authentication is stored separately. Supported descriptions include username/password, bearer token, API key, Azure SAS token, connection string, managed/workload identity, service principal, IAM role, web identity, access key, SSH key, no credential, and custom fields. JobSeeker materializes these values but does not open the target connection.

## Secret backends

- **Encrypted in JobSeeker** encrypts username/password and arbitrary `field=value` entries with `JOBSEEKER_ENCRYPTION_KEY`. Examples include `sas_token`, `api_key`, `connection_string`, and `private_key`. Edit forms never return saved values to the browser; leave all local fields blank to preserve them.
- **Environment variables** maps arbitrary connector fields to worker variables, one `field=VARIABLE_NAME` per line. For example, `sas_token=AZURE_STORAGE_SAS_TOKEN` or `api_key=VENDOR_API_KEY`. Values are resolved on the Jenkins controller or selected agent when the build starts.
- **Azure Key Vault** maps arbitrary connector fields to Key Vault secret names. Vault authentication can use the Azure default credential chain, managed identity, workload identity, or a service principal supplied through `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, and `AZURE_CLIENT_SECRET`.
- **AWS Secrets Manager** maps arbitrary connector fields to flat or dotted JSON paths in one secret. Worker authentication can use the boto3 default chain, IAM role, web identity, access-key environment, or a named profile.

Cloud credentials are deployment settings, not connector values. Do not put Azure client secrets, AWS access keys, vault tokens, or federated identity tokens in the connector form.

Local Compose deployments can place arbitrary worker values in `.env.connectors`, using [.env.connectors.example](../../../../.env.connectors.example) as the name template. The real file is ignored by Git and loaded by the Jenkins controller and optional agents. In the connector form, map each runtime field to one of those names:

```text
username=WAREHOUSE_USER
password=WAREHOUSE_PASSWORD
api_key=VENDOR_API_KEY
sas_token=AZURE_STORAGE_SAS_TOKEN
```

After materialization, JobSeeker unsets every mapped source variable from the worker shell before user ETL code starts. The job receives only its scoped `JOBSEEKER_CONNECTOR_*` values or protected field files.

For Azure Storage SAS authentication, select an Azure Blob/Data Lake connector and the **Azure SAS token** target authentication. Store `sas_token` through encrypted local values, map it to a worker environment variable, or map it to a Key Vault secret. IAM roles, managed identities, and workload identities commonly need no target secret field; select **No credential** or the matching target authentication while the job's cloud SDK uses the worker identity.

## Connection testing

Each catalog row has a **Test** button that runs a real protocol handshake. The test executes on a Jenkins worker, exactly like a job: JobSeeker creates a short-lived `__jobseeker_conn_test_*` freestyle job, the worker materialises the connector with `jobseeker-connector materialize`, runs `python -m jobseeker.conntest`, and the job is deleted afterwards. The result modal shows a pass/fail badge, latency, the target server version, and a per-check list; on failure it also shows the tail of the worker console.

`jobseeker.conntest` connects, authenticates, and issues the cheapest liveness call for the connector type:

| Family | Types | Check |
| --- | --- | --- |
| Core SQL | `mysql`, `mariadb`, `pgsql` | connect, `SELECT 1`, server version |
| Enterprise SQL | `sqlserver`, `oracle_service`, `oracle_sid` | connect, trivial select, version |
| NoSQL / cache | `mongodb`, `redis`, `elasticsearch` | ping / cluster info |
| Object store & HTTP | `aws_s3`, `azure_blob`, `azure_data_lake`, `gcs`, `http_api`, `sftp` | bucket/account probe, `GET`, SSH banner |
| Streaming | `kafka`, `rabbitmq` | broker metadata / AMQP connect |
| Secret-only | `generic_secret` | resolved secret bundle is present |

Drivers are imported lazily. When a client library is not installed on the worker the test degrades to a TCP reachability probe and reports `status = "driver_missing"` with the package to add. The light clients (`psycopg`, `redis`, `pymongo`, `oracledb`, `pymssql`, `paramiko`, plus `boto3` and `azure-*`) ship in the worker image via [docker/jenkins/requirements.txt](../../../../docker/jenkins/requirements.txt); heavier ones (Google Cloud Storage, Snowflake, Databricks, Kafka, RabbitMQ) are optional.

From inside a job, the same code is reachable as `js.connector("warehouse").test()` and as `jobseeker-connector test warehouse --json`.

A fast, PHP-only probe (decrypt-check plus a three-second TCP connect, no protocol handshake) is still available at `dbSettings/testConnector?mode=quick`.

## Insight Studio data sources

Insight Studio browses tables and runs generated aggregate `SELECT` queries in the PHP process, so it can only use catalog connectors whose credentials JobSeeker can read locally: `db_type` of `mysql` or `pgsql` **and** the **Encrypted in JobSeeker** secret backend. Cloud-secret backends and non-SQL types are not offered there. TLS behaviour comes from an `sslmode=` token in the connector's connection parameters (`required` / `preferred` / `disabled`). Manage and test these connectors on the Connectors page; the Studio data-sources screen only picks one and approves a table and fields.

The upgrade migration copies every `visualization_connections` row into `database_settings` (`environment = ALL`, job scope `*`, `secret_backend = local`), records `visualization_connections.migrated_connector_id`, and repoints `visualization_datasets.connection_id`. It is idempotent and leaves the old table in place for rollback.

## Job dependency map

JobSeeker scans a job's code and generated Jenkins command for connector and data-asset references and records them per job in `job_dependencies`. Detected shapes: `js.connector("key")` / `get_connector("key")`, `jobseeker-connector get|exec|test key`, `"$JOBSEEKER_CONNECTOR_HELPER" exec key`, `JOBSEEKER_CONNECTOR_KEY=key`; and `js.asset("key")` / `js.dataset("key")` / `get_asset("key")` and `jobseeker://<env>/<key>` URIs.

* **Light check** – runs live while editing in Job Creation and whenever Job View / Job Execution open a job: does the referenced key exist in the catalog, is it active, and is it published for this job's environment and scope. An unknown, inactive or out-of-scope reference is shown as a warning; it never blocks job creation.
* **Heavy check** – the real worker connection handshake (the same `jobseeker.conntest` run as the Connectors *Test* button). It runs from a **Test connections** button in Job Creation and automatically once, from the browser, right after a job is created. The pass/fail and timestamp are stored on the job and shown on Job View and Job Execution.

Each row links to the connector (`dbSettings?edit=<id>`) or to Data Assets. `GET jobView/dependencies?job=&environment=` and `GET jobExecution/dependencies?...` return the stored map, falling back to a fresh scan of the Jenkins command plus the job's repository source when a job predates this feature.

## Testing

Run the focused contract and SDK simulations without a live stack:

```bash
npm run test:connectors
npm run test:connectors:sdk
```

With the Compose application, MariaDB, and Jenkins services running:

```bash
npm run test:connectors:e2e             # CRUD, scope resolution, secret backends, materialization
npm run test:connection-framework:e2e   # live worker connection test + catalog/Studio merge
npm run test:viz-migration:e2e          # one-time visualization_connections migration
npm run test:job-deps:e2e               # job dependency scan, resolve, persist, worker handshake
```

The live suites use the seeded administrator by default. Override `JOBSEEKER_E2E_URL`, `JOBSEEKER_E2E_EMAIL`, `JOBSEEKER_E2E_PASSWORD`, or `JOBSEEKER_CONNECTOR_API_TOKEN` when testing another local deployment. After changing `docker/jenkins/requirements.txt` or the SDK, rebuild the worker images: `docker compose build jenkins jenkins-agent && docker compose up -d`.

## Runtime handling

At build start, the Jenkins worker authenticates to JobSeeker's internal connector endpoint and requests only connectors matching the build's environment and job name. The worker then:

1. Resolves external secret references with its workload identity.
2. Writes a build-only directory with mode `0700` and value files with mode `0600`.
3. Removes the connector API URL and token from the user process environment.
4. Mounts a separate read-only per-build volume for Docker jobs.
5. Deletes the host directory and Docker volume from the build exit trap.

Secrets are not stored in Jenkins job XML, Docker image layers, data-asset manifests, or console command arguments. Runtime access metadata is retained in `connector_access_log` for 90 days; the log contains connector key, scope, backend, status, and timestamp, never values.

Set a strong `JOBSEEKER_CONNECTOR_API_TOKEN` on PHP and every Jenkins worker. Local Compose does this wiring automatically. External workers should use an HTTPS `JOBSEEKER_CONNECTOR_API_URL` and a protected worker-level token.

Azure retrieval modes use the standard `azure-identity` variables and endpoints:

- Default chain tries configured environment, workload identity, managed identity, and supported developer credentials.
- Managed identity optionally accepts the connector's client ID.
- Workload identity reads `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, and `AZURE_FEDERATED_TOKEN_FILE`.
- Service principal environment reads `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, and `AZURE_CLIENT_SECRET`.

AWS retrieval modes use boto3's standard providers. IAM role and web identity are preferred; environment access keys and named profiles are available for deployments that require them. Mount the referenced federated token or AWS profile into the Jenkins worker through deployment configuration.

## Python

```python
import psycopg
from jobseeker import JobSeeker

with JobSeeker(environment="PROD", job="load-orders") as js:
    warehouse = js.connector("warehouse")
    connection = psycopg.connect(
        host=warehouse.host,
        port=warehouse.port,
        dbname=warehouse.database,
        user=warehouse.username,
        password=warehouse.password,
    )
```

`jobseeker.get_connector("warehouse")` is available when a full `JobSeeker` client is not needed. Use `connector.value("sas_token", required=True)` or another mapped field for non-password authentication. `connector.as_dict()` excludes secrets unless `include_secrets=True` is explicitly requested. The helper fails with the connector keys available to the current job when a required key is absent.

## Shell and Talend

Use `exec` so values are added only to the child ETL process environment and are not printed by the helper:

```sh
"$JOBSEEKER_CONNECTOR_HELPER" exec warehouse -- ./run-etl.sh
```

The child receives `JOBSEEKER_CONNECTOR_HOST`, `JOBSEEKER_CONNECTOR_PORT`, `JOBSEEKER_CONNECTOR_DATABASE`, `JOBSEEKER_CONNECTOR_USERNAME`, `JOBSEEKER_CONNECTOR_PASSWORD`, and additional `JOBSEEKER_CONNECTOR_*` fields. A Talend launcher can use the same wrapper and map those variables to context parameters.

```sh
./MyJob_run.sh \
    --context_param host="$JOBSEEKER_CONNECTOR_HOST" \
    --context_param sas_token="$JOBSEEKER_CONNECTOR_SAS_TOKEN"
```

Tools that consume credential files can read `$JOBSEEKER_CONNECTORS_DIR/<key>/<field>`. The directory and field names are validated, local files are `0600`, and Docker mounts are read-only. The code icon beside each connector shows Python, shell, Talend, and file examples using that connector's real key.

`$JOBSEEKER_CONNECTOR_HELPER get warehouse host` is suitable for non-secret metadata. Avoid printing or command-substituting password fields because shell tracing or downstream tools may expose them.

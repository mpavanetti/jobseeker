# ETL Connectors

Connectors are named, environment-aware credential and endpoint contracts for Python, shell, Talend, and Docker jobs. A connector stores metadata and secret references; the ETL job still owns its database driver, HTTP client, storage SDK, or protocol implementation.

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

## Testing

The catalog's plug button performs a bounded readiness test. It verifies that encrypted local values can be decrypted or that an external reference is structurally complete. When an endpoint and port are available, it also attempts a three-second TCP connection. It does not load a database driver, authenticate to the target protocol, execute a query, or retrieve cloud secrets in the PHP web process. Azure/AWS authentication is exercised on the Jenkins worker when the connector is materialized for a job.

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

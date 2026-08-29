## Extract, Transform and Load Helpers

These helpers keep runtime configuration outside your ETL code. Update an environment value, dataset, email, or connection in the JobSeeker UI without rebuilding every Talend, Python, Bash, or containerized job that consumes it.

For example, a file named `customers.csv` can be registered as `customer-reference`, scoped to `PROD`, and replaced later without changing the Python or shell code that resolves that asset key.

 ### Available Options

1. [Data Assets](data-assets) — uploaded datasets, runtime inputs, generated outputs, format metadata, versioning, and checksums.
2. [Connectors](connectors) — globally scoped credentials and endpoint metadata for databases, APIs, storage, queues, streaming, SFTP, and custom ETL clients.
3. [Pipelines](pipelines) — grouped drag-and-drop Jenkins workflows with sequential, parallel, conditional, recovery, and fan-in execution.
2. [Database Settings](dbsettings)
3. [Generic Settings](genericsettings)
4. [Email Settings](emailsettings)
5. [SMTP Settings](smtpsettings)
6. [Context Settings](contextsettings) — projects, environments, and runtime key/value configuration.

The legacy Input Components, Output Components, and File Upload URLs now lead to Data Assets. Existing registrations are imported into the unified catalog automatically.

Legacy sidebar example:

![ETL Helpers](img/ETLHelper.JPG)

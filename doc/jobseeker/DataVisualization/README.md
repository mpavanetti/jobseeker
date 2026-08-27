# Data Visualization

Jobseeker has two complementary analytics paths:

- **Insight Studio** is a lightweight dashboard builder made with the open-source jQuery UI and Chart.js libraries already shipped with Jobseeker. Users drag governed fields into a visual recipe, add cards to a sortable canvas, configure them, and save private or shared dashboards.
- **Connected analytics** presents reports hosted by Power BI, Tableau, Apache Superset, Metabase, Grafana, Looker, Qlik, MicroStrategy, or another web BI tool.

The Analytics Hub at **Data Visualization → Analytics Hub** is the entry point for both.

## Insight Studio

Open **Data Visualization → Insight Studio**. The starter canvas demonstrates KPI, donut, line, and bar cards backed by current TMF data.

To create a visual:

1. Choose a dataset in the data library.
2. Drag a dimension into **Category / time** and a measure into **Value**. Clicking a field also assigns it.
3. Choose bar, line, donut, KPI, or table.
4. Select **Add to canvas**.
5. Drag cards to reorder them. Select a card to change its dataset, fields, time window, environment, width, or palette.
6. Use the canvas filters to apply one time window or environment to every card. The data library can be searched by dataset or field name.
7. Name and save the dashboard. Team sharing is opt-in. A dashboard shared by someone else opens read-only; **Save a copy** creates an editable version for the current user.

Dashboard templates can be exported to JSON and imported into another workspace. Presentation mode expands the canvas and hides the editing chrome.

### Governed datasets

The initial catalog exposes:

- **TMF runs** — run counts, attention counts, record throughput, completion rate, and runtime by status, job, dimension, environment, day, or month.
- **TMF errors** — error counts and affected runs by type, origin, job, environment, or day.
- **Job catalog** — registered components and uploads by component type, job, owner, or creation month.
- **Job outputs** — produced and downloaded artifacts by component, job, owner, or creation month.
- **Login activity** — session volume and unique users by account, platform, day, or hour.
- **Context inventory** — configuration-key coverage by environment, project, state, or creation month. Context values are never exposed.

The browser never supplies a table name, column name, SQL expression, join, or database credential. Dataset and field keys are resolved through server-side allowlists in `Visualization_model`. Queries are aggregated, date-scoped, environment-aware, and limited to 50 result groups.

Dashboard definitions contain presentation metadata only. They are normalized again on save, limited to 24 visuals, and stored in `visualization_dashboards` with an owner and optional team-sharing flag.

## Connecting a database dataset

Administrators and managers can open **Data Visualization → Studio Data Sources**. The guided flow supports MySQL/MariaDB and PostgreSQL:

1. Create a dedicated database account with `SELECT` access only to the schemas or tables intended for analytics.
2. Enter the host, port, database, username, password, and TLS mode. Jobseeker tests the connection before saving it.
3. Select a discovered base table.
4. Approve dimension columns and numeric measure columns. Optionally map a timestamp for Studio time filters and a field for environment filters.
5. Publish the dataset. It appears immediately in every user's Studio data library.

Jobseeker stores database passwords with the configured CodeIgniter encryption key. Connection-list and dataset APIs never return the encrypted value. Table and column names are discovered by the server and saved in an administrator-controlled definition; they cannot be supplied by an analyst's dashboard. Connected queries use quoted identifiers, bound filter values, read-only transactions, a five-second connection timeout, aggregate operations only, and a maximum of 50 result groups. There is no raw-SQL endpoint.

Keep `JOBSEEKER_ENCRYPTION_KEY` stable and secret. Rotating it requires saving each connection again. Prefer TLS **Required** for remote databases; **Disabled** exists for local development networks. Network policy and least-privilege database grants remain the final enforcement boundary.

The PHP image includes `pdo_mysql` and `pdo_pgsql`. Rebuild the PHP service after upgrading an older installation so PostgreSQL support is available.

## Connecting an external report safely

Administrators and developers can open **Data Visualization → Connected Reports** and paste a provider's share URL or a single iframe snippet. Assign at least one person or group.

Use the provider's dedicated embed or presentation URL. Prefer HTTPS and one of these identity patterns:

1. **Provider SSO** for internal reports. The iframe authenticates directly with the provider; Jobseeker does not receive the password.
2. **Signed or guest embed** for providers such as Superset, Metabase, or Power BI. Generate short-lived tokens in the provider or a dedicated broker, not in browser code.
3. **Public embed** only for intentionally public data. Public links should be assumed accessible to anyone who obtains the URL.

Never paste database credentials, long-lived access tokens, or URLs containing basic-auth user information.

### Embed boundary

Jobseeker treats every connected report as untrusted external content:

- only an HTTP(S) URL or one iframe is accepted;
- the original markup is discarded and the iframe is rebuilt;
- script tags, event handlers, arbitrary attributes, embedded credentials, and same-origin Jobseeker URLs are rejected;
- a fixed sandbox, no-referrer policy, lazy loading, and limited browser capabilities are applied every time the report renders;
- content is fetched by the user's browser, not proxied through the Jobseeker server;
- report discovery continues to use Jobseeker user/group access rules.

Some BI products send `X-Frame-Options` or Content Security Policy headers that prohibit framing. Configure embedding on the provider for the Jobseeker origin rather than weakening Jobseeker's frame boundary.

## Extending the dataset catalog

For application-native datasets, add a display definition to `studioDatasets()` and a corresponding query definition to `studioQueryDefinition()` in `application/models/Visualization_model.php`. Both the dataset key and every exposed dimension and measure must remain hard-coded server-side. For an external table, use the governed Data Sources UI, which validates discovered tables and columns before persisting an allowlisted definition. Do not add raw SQL, arbitrary browser-provided table names, or arbitrary browser-provided column names to dashboard definitions.

For large TMF installations, add database indexes that match the common Studio filters, especially `tmf.last_activity`, `tmf.environment`, `tmf.status`, `tmf.instance_id`, and `tmf_error.moment`.

`./seed_demo_data.sh` also installs two Tableau Public connected-report examples and three shared Insight Studio dashboards: **Operations command center**, **Platform activity and adoption**, and **Configuration governance**. They are repeatable for any `DEMO_PREFIX` and are removed by `./seed_demo_data.sh --cleanup`.

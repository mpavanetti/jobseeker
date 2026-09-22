# JobSeeker MariaDB connector round-trip

This starter uses the `jobseeker-mariadb` connector that JobSeeker publishes to the Hop run. Its generated Hop database metadata refers to run variables; no host, user, password, or token is stored in the project.

The workflow:

1. creates the isolated `jobseeker_hop_sample_rows` table if it does not exist;
2. writes one row identified by the current Transaction Monitoring instance;
3. reads the same row back and logs it;
4. deletes that run's row.

The empty sample table is intentionally retained so subsequent runs need no migration. The workflow never writes to JobSeeker application tables. The connector account needs `CREATE`, `SELECT`, `INSERT`, and `DELETE` on its configured database.

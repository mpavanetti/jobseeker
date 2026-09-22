# Data Asset and Context flow

This starter shows the JobSeeker-owned runtime values that a Hop project should use instead of machine-specific paths or hard-coded environment configuration.

Before creating the Hop job, add these resources in the same JobSeeker environment:

- input Data Asset `hop-customer-input`, pointing to a CSV with the columns `customer_id,name`;
- output Data Asset `hop-customer-output`, pointing to the CSV path Hop may replace;
- Context Details `SAMPLE_REGION` and `SAMPLE_OWNER` (the seeded `Custom` value is also shown).

`pipelines/show-contexts.hpl` logs the selected environment, job, build, Context Details, and resolved Data Asset paths. `pipelines/copy-data-assets.hpl` then reads the input path and writes the output path. JobSeeker converts those paths for the selected execution engine, so the same project works in an ephemeral container and on Hop Server.

No credential or absolute host path is stored in this project.

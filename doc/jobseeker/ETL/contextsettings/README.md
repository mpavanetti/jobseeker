## Context Settings
This section is used for having dynamic project and environments key value pairs to be called from an ETL job. <br>
This is separated by three subjects<br>
1) **[Project Details]**: Here you create projects
1) **[Environments Details]**: Here you create environments
1) **[Context Details]**: Here you create dynamic contexts


## Project Details
Use this section to add a project, to separate your contexts by projects, add a git path optionally, and select the active project status.

![Project](img/projects.JPG)

## Environments Details
Use this section to create environments, Select an environment name, active project status and an optional description.<br>
I Recommend useing the following environment names:
- LOCAL
- DEV
- QA
- UAT
- STAG
- PROD

![Env](img/environments.JPG)

## Context Details
Use this section to create your contexts based on a key and value pairs, select the active status, choose **Encrypted secret** for passwords and tokens, also select a project name and environment and use a description optionally.<br>
An encrypted secret is stored encrypted with `JOBSEEKER_ENCRYPTION_KEY` (at most 600 characters) and never shown again: when you edit it, leave the value empty to keep the stored one, or type a new one. Jobs read it as any other context; the Python SDK gets it decrypted from JobSeeker ([Python SDK](../../../Python/README.MD)). Values marked encrypted before JobSeeker encrypted them are encrypted the next time this page is opened.<br>
For example, the key Custom has been added for the environments LOCAL, DEV and PROD, each of them has a different value, when creating a job you can specify with environment the job will run, respectively the job will use the values setup, this is good for projects which has different environments and you don't need to rebuild your entire job for switching environments, use this solution to run the same job with different environment values.

![Context](img/contexts.JPG)
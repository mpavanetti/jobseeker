const assert = require('assert');
const fs = require('fs');

const read = file => fs.readFileSync(file, 'utf8');
const creation = read('application/controllers/JobCreation.php');
const gitWorkspace = read('application/controllers/concerns/JobCreationGitWorkspaceTrait.php');
const execution = read('application/controllers/concerns/JobCreationExecutionTrait.php');
const userController = read('application/controllers/User.php');
const scanner = read('application/libraries/DependencyScanner.php');
const creationView = read('application/views/jobCreation.php');
const promotion = read('application/controllers/Context.php');
const promotionView = read('application/views/contextPromotion.php');
const projectSettings = read('application/models/ProjectGitSettings_model.php');
const projectView = read('application/views/projectDetails.php');
const projectEditView = read('application/views/projectDetailsEdit.php');
const projectGitFields = read('application/views/includes/projectGitFields.php');
const projectGitScript = read('assets/js/project-git.js');
const profile = read('application/views/profile.php');
const profileCss = read('assets/dist/css/profile.css');
const accounts = read('application/models/UserGitAccount_model.php');
const githubOAuth = read('application/libraries/GitHubOAuth.php');
const credential = read('application/controllers/GitCredential.php');
const gitHelper = read('docker/jenkins/jobseeker-git');
const httpHelper = read('docker/openvscode/jobseeker-git-credential');
const sshHelper = read('docker/openvscode/jobseeker-git-ssh');
const samples = read('application/config/job_samples.php');
const sshKeys = read('application/libraries/GitSshKeys.php');
const runtime = read('application/controllers/ConnectorRuntime.php');
const sdk = read('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py');
const header = read('application/views/includes/header.php');

assert(creation.includes("$this->gitBranchPolicy()->forEnvironment($environment)"), 'Job Creation must apply the environment branch policy server-side.');
assert(creationView.includes('gitBranchDefaults') && creationView.includes('syncGitBranchDefault'), 'Git branch defaults must be visible and environment-aware in the form.');
assert(promotion.includes('target_git_branch') && promotion.includes('rewritePromotionGitBranch'), 'Promotion must validate and rewrite a selected target Git branch.');
assert(promotion.includes('target_git_credential') && promotion.includes('rewritePromotionGitCredential'), 'Promotion must keep a bound project on its build credential.');
assert(promotionView.includes('name="targetGitBranch"') && promotionView.includes('defaultTargetGitBranch'), 'Promotion must offer an environment-defaulted branch override.');
assert(projectSettings.includes('project_git_defaults') && projectSettings.includes('GitCredentialKey'), 'Project Details must store environment branches and one build credential.');
assert(projectView.includes("includes/projectGitFields") && projectEditView.includes("includes/projectGitFields"), 'the create and edit forms must share the Source repository fields.');
assert(projectGitFields.includes('name="gitCredentialKey"') && !projectGitFields.includes('gitCredential['), 'a project names one build credential; connectors resolve it per environment.');
assert(projectGitScript.includes('resolveCredential') && projectGitScript.includes('credentialProblem'), 'the project form must say whether every environment can use the credential.');
assert(!projectGitScript.includes("a SSH key needs") && !projectGitScript.includes("an SSH key needs an SSH repository URL"), 'a deploy key works with an HTTPS URL (jobseeker-git switches it to SSH).');

// Git is optional on a project; a Git project owns one repository.
assert(projectGitFields.includes('name="gitEnabled"') && promotion.includes("$this->input->post('gitEnabled') !== '1'"), 'Git must be optional on a project.');
assert(projectGitFields.includes('name="gitBranchMode"') && promotion.includes("$this->input->post('gitBranchMode') === 'single'"), 'a project can run one branch everywhere or one per environment.');
assert(promotion.includes('public function projectGitCredential()') && projectGitScript.includes("kind: 'deploy_key'"), 'a project can create its token or deploy key without leaving Project Details.');
assert(gitWorkspace.includes('public function gitRemoteBranches()') && projectGitScript.includes('config.branchesUrl'), 'Check access must list the branches once and validate every environment.');
assert(creation.includes('->projects(TRUE, TRUE)'), 'Job Creation must only offer projects that have a Git repository.');

// Bound jobs follow their project at build time.
assert(execution.includes('function projectGitSourceLines') && execution.includes('export JOBSEEKER_GIT_FOLLOW_PROJECT=1') && execution.includes('git-source --project'), 'a bound job must resolve its project Git source when it builds.');
assert(execution.indexOf('$followsProject ? $this->projectGitSourceLines($execution) : array(),') < execution.indexOf('$this->connectorRuntimeLines(),\n          $this->dagRuntimeLines()'), 'the Git source must resolve before the connector step clears the worker token.');
assert(runtime.includes("$this->input->post('git_project') !== NULL") && runtime.includes('private function gitSource()'), 'the runtime API must serve project Git sources to workers.');
assert(sdk.includes('def git_source(') && sdk.includes('"git-source"'), 'the SDK must expose git-source.');
assert(projectSettings.includes('public function gitSource(') && creation.includes("$execution['followProject'] = TRUE;"), 'project Git sources must resolve branch, repository and credential.');
assert(promotion.includes('rewritePromotionGitPin') && promotion.includes('JOBSEEKER_GIT_FOLLOW_PROJECT'), 'promotion must not copy a project-following job\'s Git settings.');
assert(promotionView.includes('sourceFollowsProject'), 'the promotion screen must say a job follows its project.');
assert(creationView.includes("follows the project") && creationView.includes("prop('readonly', true)"), 'a bound job must show the project repository read-only and an empty branch as following.');

// Review fixes: the branch map without an environment, one line for
// missing branches, and the four Python sources in a two-by-two grid.
assert(creationView.includes('function projectBranchSummary(project)'), 'a bound job with no environment must describe the project branch map.');
assert(projectGitFields.includes('id="projectGitBranchSummary"') && projectGitScript.includes('The repository has no '), 'Check access must summarize the missing branches.');
assert(creationView.includes('.linux-python-options .linux-execution-choice-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }'), 'the Python sources must not wrap three plus one.');

// Small UI fixes.
assert(header.includes('.sidebar-mini:not(.sidebar-mini-expand-feature).sidebar-collapse .sidebar-menu > li:hover > .treeview-menu') && header.includes('width: 230px;'), 'the collapsed sidebar flyout must fit its longest labels.');
assert(profileCss.includes('.git-provider-card .btn-block { white-space: normal; }') && profile.includes("'API token'") && !profile.includes('App password or token'), 'provider buttons must wrap, and Bitbucket must ask for an API token.');
assert(gitHelper.includes('bitbucket.org) token_username=x-token-auth'), 'Bitbucket access tokens need the x-token-auth user.');
assert(!projectSettings.includes("'credentialKey' => (string) $row->credential_key"), 'branch rows must not carry a credential.');
assert(creationView.includes('id="pythonGitEntrySlot"') && creationView.includes("appendTo(isGitSource ? '#pythonGitEntrySlot'"), 'a Git job\'s entry file must sit in the Repository card.');
assert(creationView.includes('.python-git-panel { display:flex; flex-direction:column; gap:12px; margin:0 0 15px; }'), 'the Git panel must align with the fields above it.');

assert(samples.includes("'path' => 'README.md'") && samples.includes('## Before production'), 'Every generated Python sample workspace must include contextual README content.');
assert(creationView.includes("trigger('jobseeker:sample-loaded')"), 'Loading a sample must refresh the live dependency map.');
assert(creationView.includes('python-source-mode-hidden') && creationView.includes('Open Repository in VS Code'), 'source cards must replace the redundant Python source selector and expose a visible VS Code action.');
assert(creationView.includes('<svg viewBox="0 0 24 24"'), 'the Git workspace action must use a recognizable code-editor icon.');

// Git workspaces: samples, moving inline jobs, build access.
assert(creation.includes('use JobCreationGitWorkspaceTrait;'), 'Job Creation must expose the Git workspace endpoints.');
['gitPythonExternalOpen', 'gitPythonLoadSample', 'inlinePythonConvertToGit', 'testGitBuildAccess'].forEach(endpoint =>
  assert(gitWorkspace.includes(`public function ${endpoint}()`), `${endpoint} must be an endpoint`));
assert(!gitWorkspace.includes("array('--branch', $execution['branch'])"), 'the workspace clone must not require the job branch to exist on the remote.');
assert(gitWorkspace.includes("'samples/'.$sample['id']") && gitWorkspace.includes('Never overwrite'), 'a sample must never overwrite repository files.');
assert(gitWorkspace.includes("'jobseeker-git', 'heads'") && gitWorkspace.includes("'jobseeker-git', 'push'"), 'Move to Git must push through the secret-safe helper.');
assert(gitWorkspace.includes("'.env.jobseeker'") && gitWorkspace.includes("'.venv'"), 'Move to Git must never publish local secrets or virtual environments.');
assert(gitWorkspace.includes("$environment === '0' ? '' : $environment"), "the placeholder environment '0' must mean all environments.");
assert(gitHelper.includes('heads|push') && gitHelper.includes('git -C "$worktree" -c credential.helper= push'), 'jobseeker-git must support heads and push.');
assert(creationView.includes('id="addSelectedSampleToGit"') && creationView.includes("'jobCreation/gitPythonLoadSample'"), 'a Git job must be able to load a sample into its repository.');
assert(creationView.includes("pythonRuntimeMode: $('#pythonRuntimeMode').val() || 'local'") && gitWorkspace.includes("$this->input->post('pythonRuntimeMode') === 'docker'")
  && gitWorkspace.includes('if ($withDockerfile) {') && creationView.includes("response.runtime === 'docker'"), 'a sample added to Git must follow the job runtime (Docker gets a Dockerfile).');
assert(execution.includes('function gitEntryPointCheckLine()') && execution.includes('Commit and push it from the job'), 'a Git build missing its entry file must say which branch and repository it read.');
assert(creationView.includes('id="movePythonInlineToGit"') && creationView.includes("jobCreation/inlinePythonConvertToGit"), 'an inline job must offer Move to Git.');
assert(creationView.includes('id="testPythonGitBuildAccess"') && creationView.includes("jobCreation/testGitBuildAccess"), 'the build credential must be testable against the job repository.');
assert(creationView.includes("environment === '0' ? '' : environment"), "the Git panel must not label the placeholder environment '0'.");
assert(accounts.includes('&$problem = NULL') && gitWorkspace.includes('$problem !== NULL'), 'an unusable matching account must not be reported as missing.');

// Stale poetry.lock after `uv add` (or a hand edit) must not break builds.
[creation, creationView].forEach(source => assert(source.includes('poetry check --lock --no-interaction') && source.includes('poetry lock --no-interaction --no-ansi'), 'the Dockerfile template must refresh a stale poetry.lock.'));
assert(creation.includes('upgradeGeneratedInlinePythonDockerfile') && creation.includes('upgradeInlinePythonLockHandling'), 'existing generated Dockerfiles and bootstraps must be upgraded.');
assert(execution.includes('poetry check --lock --no-interaction'), 'the image-only Docker path must refresh a stale poetry.lock too.');

// Promotion: branch only for Git jobs; no placeholder target environment.
assert(!promotionView.includes('<option value="">Target environment</option>'), 'Deploy To must list only environments.');
assert(promotionView.includes("$option.val() === ''") && promotionView.includes('syncDefaultTargetEnvironment'), 'a placeholder must never reach the suggested job name.');
assert(promotionView.includes('sourceIsGitJob') && promotionView.includes("toggle(!pipeline && sourceIsGitJob)"), 'the target branch must only show for Git jobs.');
assert(promotionView.includes('jenkinsCommandText') && promotionView.includes('$.parseXML'), 'config.xml must be decoded before matching exports (&apos;).');
assert(promotion.includes("'git_job_count' => $totals['git_jobs']"), 'promotion must only report a branch for Git jobs.');

// Profile: more ways to authenticate.
assert(profile.includes('value="ssh_generate"') && userController.includes("$this->gitsshkeys->generate(") && sshKeys.includes("'ssh-keygen', '-q', '-t', 'ed25519'"), 'profile must be able to create an SSH key pair.');
assert(profile.includes('gitFetchKnownHosts') && userController.includes('function gitKnownHosts()') && userController.includes("'ssh-keyscan'"), 'profile must fetch host keys for review.');
assert(userController.includes("$this->input->get('return') === 'JobCreation'"), 'GitHub sign-in may only return to known pages.');

// Dependency map: configurable shell connectors.
assert(scanner.includes('CONNECTOR_CLI_VARIABLE') && scanner.includes('collectShellVariableDefaults'), 'shell ${VAR:-default} connector keys must be detected.');

['github', 'gitlab', 'bitbucket', 'azure_devops', 'generic'].forEach(provider => assert(profile.includes(`value="${provider}"`), `profile must support ${provider}`));
['token', 'username_password', 'ssh_key'].forEach(auth => assert(profile.includes(`value="${auth}"`), `profile must support ${auth}`));
assert(accounts.includes('path_prefix') && accounts.includes("order_by('CHAR_LENGTH(path_prefix)'"), 'multiple provider identities must resolve by longest repository scope.');
assert(accounts.includes("field_exists('token_encrypted'") && accounts.includes('DROP COLUMN `token_encrypted`'), 'the multi-provider schema must preserve and migrate prototype token accounts.');
assert(profile.includes('Continue with GitHub') && profile.includes('gitAccountTest'), 'Profile Git accounts must offer GitHub web authorization and a connection test.');
assert(githubOAuth.includes('/login/oauth/authorize') && githubOAuth.includes("$scope = 'repo'"), 'GitHub web authorization must request private-repository access by default.');
assert(credential.includes("credential($claims['uid'], $host, $path, $transport)"), 'credential lookup must include repository path and transport.');
assert(credential.includes("hash_equals((string) $claims['host'], $host)") && credential.includes("hash_equals((string) $claims['path'], $normalizedPath)"), 'workspace credentials must be restricted to the repository that issued the session.');
assert(httpHelper.includes('--data-urlencode "path=$path"'), 'HTTP Git operations must send the repository path to account selection.');
assert(sshHelper.includes('StrictHostKeyChecking=yes') && sshHelper.includes('jobseeker-git-session'), 'SSH operations must use pinned host keys and the workspace session.');
assert(sshHelper.includes('account_host="$host:$port"'), 'SSH account lookup must preserve a custom provider port.');
assert(gitHelper.includes('ls-remote --exit-code "$repository_url" HEAD') && !gitHelper.includes('ls-remote --exit-code --refs "$repository_url" HEAD'), 'default-branch connection tests must not exclude the HEAD pseudo-ref.');

console.log('Git workspace/profile feature checks passed.');

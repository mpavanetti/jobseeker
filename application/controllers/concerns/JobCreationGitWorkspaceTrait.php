<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Git repository jobs developed in OpenVSCode.
 *
 * A job bound to a project is developed in the opener's own workspace of
 * that project (ProjectWorkspaceTrait), next to the project's other jobs; its
 * code lives in its job folder there. A job without a project has a working
 * copy of its own: its repository is cloned once, with the opener's personal
 * Git account (Profile > Git Accounts), into repository/python/git/<job> and
 * checked out on the development branch configured for its environment (DEV
 * defaults to `develop`; every other environment falls back to `main`).
 * Builds clone the job's own branch with its build credential, never with a
 * person's account. From here a person can open that working copy, add a
 * sample to it, move an inline job into a repository, and test the build
 * credential a job will use.
 *
 * Requires JobCreation (inline workspace helpers) and JenkinsRunnerTrait.
 */
trait JobCreationGitWorkspaceTrait
{
      /** Opens the job's Git repository in OpenVSCode, cloning it on first use. */
      public function gitPythonExternalOpen() {
        $this->releaseSessionLock();
        $request = $this->gitWorkspaceRequest(TRUE);
        if (! $request['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $request['message']), $request['status']);
          return;
        }
        // Only a new job gets starter files. A saved job, or one just moved to
        // Git, already has its code on a branch; starter files would collide
        // with it when that branch is merged.
        $prepared = $this->preparePersonalGitWorkspace($request, $this->input->post('scaffold_job') !== '0');
        if (! $prepared['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $prepared['message']), $prepared['status']);
          return;
        }
        $branch = ! empty($prepared['payload']['workspaceBranch']) ? $prepared['payload']['workspaceBranch'] : $request['workspaceBranch'];
        $jobNote = '';
        if ($request['project'] !== NULL && $request['jobPath'] !== '') {
          if (! empty($prepared['payload']['jobFolderCreated'])) {
            $jobNote = ' '.$request['jobPath'].' was created with starter files; commit and push it for builds to run it.';
          } else if (! is_dir($request['jobDirectory'])) {
            $jobNote = ' '.$request['jobPath'].' is not in your working copy yet: pull or merge '.$request['execution']['branch'].', where this job runs, to get it.';
          } else {
            $jobNote = ' This job lives in '.$request['jobPath'].'.';
          }
        }
        $message = $request['project'] !== NULL
          ? 'Your '.$request['project']['name'].' workspace is ready on '.$branch.'.'.$jobNote
          : 'Git workspace is ready in OpenVSCode on '.$request['workspaceBranch'].'.';
        $this->jsonJobCreationResponse(array_merge($prepared['payload'], array('message' => $message)));
      }

      /**
       * Adds a Python sample from the library to the job's Git working copy
       * and opens it in OpenVSCode, where the person reviews, commits and
       * pushes it. Nothing is overwritten: when a sample file would replace
       * one the repository already has, the whole sample goes into
       * samples/<sample-id>/ instead. The files follow the job's runtime as
       * an inline workspace does: the Docker Container runtime gets a
       * Dockerfile the build uses, the Jenkins Agent runtime does not.
       */
      public function gitPythonLoadSample() {
        $this->releaseSessionLock();
        $request = $this->gitWorkspaceRequest(TRUE);
        if (! $request['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $request['message']), $request['status']);
          return;
        }
        $sample = $this->pythonSampleById((string) $this->input->post('sample_id'));
        if ($sample === NULL) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That Python sample is not in the sample library.'), 404);
          return;
        }
        $prepared = $this->preparePersonalGitWorkspace($request);
        if (! $prepared['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $prepared['message']), $prepared['status']);
          return;
        }

        $pythonExecutable = $this->cleanPythonExecutable($this->input->post('pythonVersion'));
        $dockerImage = ! empty($sample['docker_image']) ? $sample['docker_image'] : '';
        if ($dockerImage === '') {
          $dockerImage = $this->cleanPythonDockerImage($this->input->post('pythonDockerImage'), $pythonExecutable === FALSE ? '' : $pythonExecutable);
        }
        // A sample that needs Docker moves the job to Docker; any other sample
        // runs on whichever runtime the job already has.
        $sampleNeedsDocker = ! empty($sample['use_dockerfile']) || (isset($sample['runtime']) && $sample['runtime'] === 'docker');
        $runtime = $sampleNeedsDocker || $this->input->post('pythonRuntimeMode') === 'docker' ? 'docker' : 'local';
        // In a repository of many jobs the sample goes into this job's folder,
        // and its entry file stays relative to that folder.
        if (! $this->ensureDirectory($request['jobDirectory'])) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'JobSeeker could not create '.$request['jobPath'].' in the workspace.'), 500);
          return;
        }
        if ($request['project'] !== NULL && $request['jobPath'] !== '') {
          $this->removeUntouchedJobStarter($request['jobDirectory'], basename($request['jobPath']));
        }
        $written = $this->writeSampleIntoGitWorkspace($request['jobDirectory'], $sample, $dockerImage === FALSE ? '' : $dockerImage, $runtime === 'docker');
        if (! $written['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $written['message']), $written['status']);
          return;
        }

        $where = $request['jobPath'] === ''
          ? ($written['folder'] === '' ? 'the repository root' : $written['folder'])
          : $request['jobPath'].($written['folder'] === '' ? '' : '/'.$written['folder']);
        $this->jsonJobCreationResponse(array_merge($prepared['payload'], array(
          'message' => $sample['name'].' was added to '.$where.' on '.(! empty($prepared['payload']['workspaceBranch']) ? $prepared['payload']['workspaceBranch'] : $request['workspaceBranch'])
            .($runtime === 'docker' ? ' with a Dockerfile for the Docker Container runtime' : ' for the Jenkins Agent runtime')
            .'. Review it in VS Code, then commit and push: builds run what is on the branch.',
          'sampleFiles' => $written['files'],
          'jobPath' => $request['jobPath'],
          'sampleFolder' => $written['folder'],
          'entryPoint' => $written['entryPoint'],
          'runtime' => $runtime,
          'useDockerfile' => $runtime === 'docker',
          'runTests' => ! empty($sample['run_tests'])
        )));
      }

      /**
       * Moves an inline Python job into a Git repository: the editor draft is
       * saved to the inline workspace, copied into a working copy of the
       * repository, committed as the current user and pushed with their
       * personal Git account. An empty repository also gets the default
       * release branch (main) so later promotions have a branch to deploy.
       * The job itself switches to Git only when the form is saved.
       */
      public function inlinePythonConvertToGit() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to move a job to Git.'), 405);
          return;
        }
        $cleanJobName = $this->cleanSubmittedJobName($this->input->post('job_name'));
        if (! $cleanJobName['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $cleanJobName['message']), 400);
          return;
        }
        $jobName = $cleanJobName['name'];

        $draft = $this->inlinePythonDraftFromPost($jobName);
        if (! $draft['ok']) {
          $this->jsonJobCreationResponse($draft['payload'], $draft['status']);
          return;
        }

        $environment = $this->gitWorkspaceEnvironment();
        $projectSelection = $this->selectedGitProject($this->input->post('pythonProjectId'), $environment);
        if ($projectSelection === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The selected project is inactive or no longer exists.'), 400);
          return;
        }
        $project = $projectSelection['project'];
        $repositoryUrl = $project !== NULL ? $project['repositoryUrl'] : trim((string) $this->input->post('pythonRepositoryUrl'));
        $repositoryUrl = $this->cleanPythonRepositoryUrl($repositoryUrl);
        $branch = trim((string) $this->input->post('pythonRepositoryBranch'));
        if ($branch === '') {
          $branch = ! empty($projectSelection['default']['branch']) ? $projectSelection['default']['branch'] : $this->gitBranchPolicy()->forEnvironment($environment);
        }
        $branch = $this->cleanPythonRepositoryBranch($branch);
        $releaseBranch = $project !== NULL ? $this->gitProjectSettings()->settingForEnvironment($project['id'], 'DEFAULT') : array('branch' => '');
        $releaseBranch = ! empty($releaseBranch['branch']) ? $releaseBranch['branch'] : $this->gitBranchPolicy()->forEnvironment('DEFAULT');
        if ($repositoryUrl === FALSE || $branch === FALSE || $branch === '') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Enter a repository URL without embedded credentials and a valid branch name.'), 400);
          return;
        }
        $commitMessage = trim(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', (string) $this->input->post('commit_message')));
        if ($commitMessage === '') {
          $commitMessage = 'Move JobSeeker job '.$jobName.' to Git';
        }
        $commitMessage = substr($commitMessage, 0, 2000);
        $jobPath = $this->projectWorkspaceLayout()->cleanJobPath($this->input->post('pythonGitJobPath'));
        if ($jobPath === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The job folder must be a folder inside the repository, such as jobs/'.$this->projectWorkspaceLayout()->slug($jobName, 'job').'.'), 400);
          return;
        }

        $location = $this->gitRepositoryLocation($repositoryUrl);
        $accountHost = $location['host'].($location['port'] ? ':'.$location['port'] : '');
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $problem = NULL;
        $account = $location['host'] === '' ? FALSE : $this->gitAccounts->credential($this->vendorId, $accountHost, $location['path'], $location['transport'], $problem);
        if ($account === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'needsAccount' => $problem === NULL, 'message' => $problem !== NULL ? $problem
            : 'Pushing to '.$accountHost.' needs your personal Git account. Connect it under Profile > Git Accounts, then try again.'), 422);
          return;
        }

        // A project job is pushed from the opener's workspace of the project
        // when they have none yet (it becomes their workspace); otherwise from
        // a temporary clone, so their uncommitted work is never touched.
        $temporaryClone = FALSE;
        $projectLocation = $project !== NULL ? $this->projectWorkspaceLocation($project) : NULL;
        if ($projectLocation !== NULL && (is_dir($projectLocation['absolute'].DIRECTORY_SEPARATOR.'.git') || file_exists($projectLocation['absolute']))) {
          if (! is_dir($projectLocation['absolute'].DIRECTORY_SEPARATOR.'.git')) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'repository/'.$projectLocation['relative'].' exists but is not a Git working copy. Move its files away and try again.'), 409);
            return;
          }
          $relativeWorkspace = '';
          $workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobseeker-move-'.bin2hex(random_bytes(8));
          $temporaryClone = TRUE;
        } else if ($projectLocation !== NULL) {
          $relativeWorkspace = $projectLocation['relative'];
          $workspace = $projectLocation['absolute'];
          if (! $this->ensureDirectory(dirname($workspace))) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The Git workspace path could not be created.'), 500);
            return;
          }
        } else {
          $relativeWorkspace = $this->safeRelativePath('python/git/'.$jobName);
          $workspace = $relativeWorkspace === FALSE ? FALSE : rtrim($this->inlinePythonRepositoryRoot(), '/\\').DIRECTORY_SEPARATOR.$relativeWorkspace;
          if ($workspace === FALSE) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The Git workspace path could not be resolved.'), 400);
            return;
          }
          if (file_exists($workspace)) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'repository/'.$relativeWorkspace.' already exists. Open it in VS Code to add these files, or remove the folder to move the job again.'), 409);
            return;
          }
        }

        $written = $this->writeInlinePythonDraft($jobName, $draft);
        if (! $written['ok']) {
          $this->jsonJobCreationResponse($written['payload'], $written['status']);
          return;
        }
        $source = $written['execution']['sourceDirectory'];

        // A first commit to an empty project repository also lays out the project.
        $projectFiles = $project !== NULL ? $this->projectWorkspaceLayout()->scaffoldFiles($project['type'], $project['name']) : array();
        $connectorDirectory = $this->materializePersonalGitAccount($account, $location['host']);
        try {
          $result = $this->pushInlineWorkspaceToGit($source, $workspace, $repositoryUrl, $connectorDirectory, $branch, $releaseBranch, $commitMessage, $this->input->post('overwrite') === '1', $jobPath, $projectFiles);
        } finally {
          $this->removeUploadDirectory($connectorDirectory);
        }
        if (! $result['ok'] || $temporaryClone) {
          $this->removeUploadDirectory($workspace);
        }
        if (! $result['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $result['message'], 'conflicts' => isset($result['conflicts']) ? $result['conflicts'] : array()), $result['status']);
          return;
        }

        if (! $temporaryClone) {
          // The working copy is now the VS Code workspace of the job (or of
          // the opener's project).
          $this->configureGitWorkspace($workspace, $project !== NULL ? 'project-'.$project['id'] : $jobName, $repositoryUrl);
          $this->excludeGitWorkspaceTooling($workspace);
          if ($project !== NULL) {
            $this->writeProjectWorkspaceTooling($workspace, $project, $projectLocation);
            $this->writeProjectWorkspaceSessions($workspace, $project, $jobName, $jobPath);
          }
        }
        log_message('info', 'Inline job '.$jobName.' moved to '.$repositoryUrl.($jobPath !== '' ? ' ('.$jobPath.')' : '').' ('.implode(', ', $result['pushed']).') by user '.(int) $this->vendorId.'.');

        $where = $jobPath === '' ? '' : ' in '.$jobPath;
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'repositoryUrl' => $repositoryUrl,
          'branch' => $branch,
          'pushedBranches' => $result['pushed'],
          'commit' => $result['commit'],
          'filesCopied' => $result['files'],
          'entryPoint' => $draft['entryPoint'],
          'jobPath' => $jobPath,
          'projectId' => $project !== NULL ? (int) $project['id'] : 0,
          'message' => 'Pushed '.$result['files'].' file(s)'.$where.' to '.implode(' and ', $result['pushed']).'.'
            .($temporaryClone ? ' Pull '.$branch.' in your '.$project['name'].' workspace to see them there.' : '')
            .' Save the job to run it from Git.'
        ));
      }

      /**
       * Tests the credential a Git job's builds will use, against the job's
       * own repository and branch. A connector is tested on a Jenkins worker,
       * exactly as a build materializes it; a public build is tested here.
       */
      public function testGitBuildAccess() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to test Git build access.'), 405);
          return;
        }
        $environment = $this->gitWorkspaceEnvironment();
        $projectSelection = $this->selectedGitProject($this->input->post('pythonProjectId'), $environment);
        if ($projectSelection === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The selected project is inactive or no longer exists.'), 400);
          return;
        }
        $project = $projectSelection['project'];
        $repositoryUrl = $this->cleanPythonRepositoryUrl($project !== NULL ? $project['repositoryUrl'] : $this->input->post('pythonRepositoryUrl'));
        $branch = $this->cleanPythonRepositoryBranch($this->input->post('pythonRepositoryBranch'));
        $credentialKey = $this->cleanPythonGitCredentialKey($project !== NULL ? $project['credentialKey'] : $this->input->post('pythonGitCredentialKey'));
        if ($repositoryUrl === FALSE || $branch === FALSE || $credentialKey === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Enter a valid repository URL, branch and build credential.'), 400);
          return;
        }

        $started = microtime(TRUE);
        if ($credentialKey === '' && $this->gitRepositoryLocation($repositoryUrl)['transport'] === 'ssh') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'status' => 'failed', 'credential' => '',
            'message' => 'SSH repository URLs always need a key. Choose a Git connector with an SSH deploy key, or use the HTTPS URL for a public repository.'), 422);
          return;
        }
        if ($credentialKey === '') {
          // The URL is validated (no leading dash), so it cannot become an option.
          $arguments = array('timeout', '20', 'git', '-c', 'credential.helper=', 'ls-remote', '--exit-code');
          $arguments = array_merge($arguments, $branch === '' ? array($repositoryUrl, 'HEAD') : array('--heads', '--tags', $repositoryUrl, $branch));
          $run = $this->runCommand($arguments);
          $latency = (int) round((microtime(TRUE) - $started) * 1000);
          $ok = $run['code'] === 0;
          // GNU timeout exits 124; busybox reports the SIGTERM (143).
          $timedOut = in_array($run['code'], array(124, 143), TRUE);
          $this->jsonJobCreationResponse(array(
            'ok' => $ok,
            'status' => $ok ? 'passed' : ($timedOut ? 'timeout' : 'failed'),
            'latencyMs' => $latency,
            'credential' => '',
            'message' => $ok
              ? 'Public read access works'.($branch !== '' ? ' and '.$branch.' exists' : '').'. Builds need no credential.'
              : ($timedOut ? 'The Git provider did not respond within 20 seconds.'
                : ($run['code'] === 2 ? ($branch !== '' ? 'The repository is readable, but '.$branch.' does not exist.' : 'The repository is readable but has no commits yet.')
                  : 'The repository is not publicly readable. Choose a Git connector (a deploy key or token) for builds.'))
          ), $ok ? 200 : 422);
          return;
        }

        if ($environment === '' || $environment === 'ALL') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Select an environment in the top bar; build credentials are resolved per environment.'), 400);
          return;
        }
        $jobName = $this->cleanSubmittedJobName($this->input->post('job_name'));
        $result = $this->runGitBuildAccessTest($credentialKey, $environment, $jobName['ok'] ? $jobName['name'] : 'jobseeker-connection-test', $repositoryUrl, $branch);
        $status = isset($result['httpStatus']) ? (int) $result['httpStatus'] : ($result['ok'] ? 200 : 422);
        unset($result['httpStatus']);
        $result['latencyMs'] = (int) round((microtime(TRUE) - $started) * 1000);
        $result['credential'] = $credentialKey;
        $this->jsonJobCreationResponse($result, $status);
      }

      /**
       * The branches of a repository as builds see them: listed with the
       * build credential on a Jenkins worker, or anonymously for a public
       * repository. Project Details checks every environment's branch
       * against one listing instead of testing each environment.
       */
      public function gitRemoteBranches() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to list repository branches.'), 405);
          return;
        }
        $repositoryUrl = $this->cleanPythonRepositoryUrl($this->input->post('repository_url'));
        $credentialKey = $this->cleanPythonGitCredentialKey($this->input->post('credential_key'));
        if ($repositoryUrl === FALSE || $credentialKey === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Enter a valid repository URL and credential.'), 400);
          return;
        }
        $started = microtime(TRUE);
        if ($credentialKey === '') {
          if ($this->gitRepositoryLocation($repositoryUrl)['transport'] === 'ssh') {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'SSH repository URLs always need a key. Choose a deploy key, or use the HTTPS URL of a public repository.'), 422);
            return;
          }
          // The URL is validated (no leading dash), so it cannot become an option.
          $run = $this->runCommand(array('timeout', '20', 'git', '-c', 'credential.helper=', 'ls-remote', '--heads', $repositoryUrl));
          $ok = $run['code'] === 0;
          $this->jsonJobCreationResponse(array(
            'ok' => $ok,
            'branches' => $ok ? $this->gitBranchNames($run['output'], TRUE) : array(),
            'latencyMs' => (int) round((microtime(TRUE) - $started) * 1000),
            'message' => $ok ? 'Public read access works; builds need no credential.'
              : (in_array($run['code'], array(124, 143), TRUE) ? 'The Git provider did not respond within 20 seconds.' : 'The repository is not publicly readable. Choose a build credential.')
          ), $ok ? 200 : 422);
          return;
        }

        $environment = strtoupper(trim((string) $this->input->post('environment')));
        $environment = $this->jobSeekerEffectiveEnvironment($environment === '0' ? '' : $environment, '');
        if ($environment === '' || $environment === 'ALL') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Choose an environment to materialize the credential in.'), 400);
          return;
        }
        $result = $this->runGitBuildAccessTest($credentialKey, strtoupper($environment), 'jobseeker-connection-test', $repositoryUrl, '', TRUE);
        $status = isset($result['httpStatus']) ? (int) $result['httpStatus'] : ($result['ok'] ? 200 : 422);
        unset($result['httpStatus']);
        $result['latencyMs'] = (int) round((microtime(TRUE) - $started) * 1000);
        $this->jsonJobCreationResponse($result, $status);
      }

      /** Branch names from `git ls-remote --heads` (or bare names), validated. */
      private function gitBranchNames($output, $lsRemote) {
        $branches = array();
        foreach (preg_split('/\r?\n/', (string) $output) as $line) {
          $name = trim($line);
          if ($lsRemote) {
            $name = preg_match('#^[0-9a-f]{7,64}\s+refs/heads/(\S+)$#', $name, $matches) ? $matches[1] : '';
          }
          if ($name !== '' && $this->cleanPythonRepositoryBranch($name) === $name) {
            $branches[] = $name;
          }
        }
        sort($branches, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($branches));
      }

      private function runGitBuildAccessTest($credentialKey, $environment, $jobScope, $repositoryUrl, $branch, $listBranches = FALSE) {
        $testJobName = '__jobseeker_git_test_'.substr(bin2hex(random_bytes(6)), 0, 12);
        // Materialized exactly as a build does; the helper reads the secret
        // from files, so neither the console nor the arguments carry it.
        $connectorDirectory = '"$JOBSEEKER_GT_DIR/"'.escapeshellarg($credentialKey);
        $script = implode("\n", array(
          'set +e',
          'command -v jobseeker-connector >/dev/null || { echo "JOBSEEKER_GIT_TEST=127"; exit 0; }',
          'command -v jobseeker-git >/dev/null || { echo "JOBSEEKER_GIT_TEST=126"; exit 0; }',
          'JOBSEEKER_GT_DIR="$(mktemp -d)"',
          'trap \'rm -rf "$JOBSEEKER_GT_DIR"\' EXIT',
          'jobseeker-connector materialize --directory "$JOBSEEKER_GT_DIR" --environment "$ENVIRONMENT" --job '.escapeshellarg($jobScope).' >/dev/null 2>&1',
          '[ -d '.$connectorDirectory.' ] || { echo "JOBSEEKER_GIT_TEST=78"; exit 0; }',
          $listBranches
            ? 'jobseeker-git heads --connector-dir '.$connectorDirectory.' -- '.escapeshellarg($repositoryUrl).' >"$JOBSEEKER_GT_DIR/.heads" 2>"$JOBSEEKER_GT_DIR/.out"'
            : 'jobseeker-git ls-remote --connector-dir '.$connectorDirectory.($branch !== '' ? ' --branch '.escapeshellarg($branch) : '')
              .' -- '.escapeshellarg($repositoryUrl).' >"$JOBSEEKER_GT_DIR/.out" 2>&1',
          'code=$?',
          'tail -n 3 "$JOBSEEKER_GT_DIR/.out"',
          $listBranches ? 'echo JOBSEEKER_GIT_HEADS_BEGIN; [ ! -f "$JOBSEEKER_GT_DIR/.heads" ] || head -n 500 "$JOBSEEKER_GT_DIR/.heads"; echo JOBSEEKER_GIT_HEADS_END' : ':',
          'echo "JOBSEEKER_GIT_TEST=$code"'
        ));
        $xml = $this->jenkinsRunnerShellJobXml('JobSeeker Git build access test. Created and deleted automatically.', $this->wrapWorkerShellCommand($script, 45), $environment);
        $saved = $this->saveDisposableJenkinsJob($testJobName, $xml);
        if (! $saved['ok']) {
          return array('ok' => FALSE, 'status' => 'error', 'message' => 'Could not create the Jenkins test job (HTTP '.$saved['status'].').', 'httpStatus' => 502);
        }
        try {
          $triggered = $this->triggerDisposableJenkinsBuild($testJobName, array('ENVIRONMENT' => $environment));
          if (! $triggered['ok']) {
            return array('ok' => FALSE, 'status' => 'error', 'message' => 'Could not start the Jenkins test job (HTTP '.$triggered['status'].').', 'httpStatus' => 502);
          }
          $build = $this->waitForDisposableBuild($testJobName, $triggered['queueId'], 75);
          $console = (string) $this->disposableBuildConsole($testJobName, isset($build['buildNumber']) ? $build['buildNumber'] : '');
        } finally {
          $this->deleteDisposableJenkinsJob($testJobName);
        }

        if (! preg_match('/^JOBSEEKER_GIT_TEST=(\d+)\s*$/m', $console, $match)) {
          return array('ok' => FALSE, 'status' => 'error', 'message' => 'The Jenkins worker did not report a result.', 'consoleTail' => trim(substr($console, -1200)), 'httpStatus' => 422);
        }
        $code = (int) $match[1];
        $messages = array(
          0 => 'The build credential can read '.($branch !== '' ? $branch.' in ' : '').'this repository.',
          // Exit 2: the branch (or, with none, HEAD) is missing; when listing,
          // only a helper too old to know `heads` exits 2 (its usage error).
          2 => $listBranches ? 'The Jenkins worker has an older jobseeker-git that cannot list branches. Rebuild the Jenkins image (docker compose up -d --build jenkins).'
            : ($branch !== '' ? 'The build credential can reach the repository, but '.$branch.' does not exist.' : 'The build credential can reach the repository, but it has no commits yet.'),
          78 => $credentialKey.' is not available to this job in '.$environment.', or its host does not match the repository.',
          126 => 'The Jenkins worker does not have the secure JobSeeker Git helper.',
          127 => 'The Jenkins worker does not have the JobSeeker connector helper.'
        );
        $result = array(
          'ok' => $code === 0,
          'status' => $code === 0 ? 'passed' : 'failed',
          'message' => isset($messages[$code]) ? $messages[$code] : 'The build credential could not read this repository. Check that the token or deploy key has access.',
          'testEnvironment' => $environment,
          'httpStatus' => $code === 0 ? 200 : 422
        );
        if ($code !== 0 && preg_match_all('/^(?:fatal|error|remote): .+$/m', $console, $lines)) {
          $result['detail'] = implode("\n", array_slice($lines[0], -3));
        }
        if ($listBranches) {
          $heads = preg_match('/^JOBSEEKER_GIT_HEADS_BEGIN\s*$(.*?)^JOBSEEKER_GIT_HEADS_END\s*$/ms', $console, $match) ? $match[1] : '';
          $result['branches'] = $code === 0 ? $this->gitBranchNames($heads, FALSE) : array();
          if ($code === 0) {
            $result['message'] = 'The build credential can read this repository.';
          }
        }
        return $result;
      }

      /** '' for "All environments" (the form posts '0' when none is selected). */
      private function gitWorkspaceEnvironment() {
        $environment = trim((string) $this->input->post('environment'));
        $environment = $this->jobSeekerEffectiveEnvironment($environment === '0' ? '' : $environment, '');
        return strtoupper(trim((string) $environment));
      }

      /**
       * Reads and validates what every Git workspace action needs: the job,
       * its project binding, repository, the branch the job runs and the
       * branch its working copy is checked out on.
       */
      private function gitWorkspaceRequest($requireOpenVsCode) {
        $fail = function($message, $status) {
          return array('ok' => FALSE, 'message' => $message, 'status' => $status);
        };
        if (! $this->canManageJobs()) {
          return $fail('Access denied.', 403);
        }
        if ($requireOpenVsCode && ! $this->openVsCodeEnabled()) {
          return $fail('OpenVSCode Server is disabled for this JobSeeker deployment.', 404);
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          return $fail('Use POST to open a Git workspace.', 405);
        }
        $cleanJobName = $this->cleanSubmittedJobName($this->input->post('job_name'));
        if (! $cleanJobName['ok']) {
          return $fail($cleanJobName['message'], 400);
        }
        $jobName = $cleanJobName['name'];

        $entryPoint = trim((string) $this->input->post('pythonEntryPoint')) === '' ? 'main.py' : $this->input->post('pythonEntryPoint');
        $jobPath = $this->projectWorkspaceLayout()->cleanJobPath($this->input->post('pythonGitJobPath'));
        if ($jobPath === FALSE) {
          return $fail('The job folder must be a folder inside the repository, such as jobs/load-orders.', 400);
        }
        $environment = $this->gitWorkspaceEnvironment();
        $projectSelection = $this->selectedGitProject($this->input->post('pythonProjectId'), $environment);
        if ($projectSelection === FALSE) {
          return $fail('The selected project is inactive or no longer exists.', 400);
        }
        $project = $projectSelection['project'];
        $repositoryUrl = $project !== NULL ? $project['repositoryUrl'] : trim((string) $this->input->post('pythonRepositoryUrl'));
        $projectBranch = isset($projectSelection['default']['branch']) ? trim((string) $projectSelection['default']['branch']) : '';
        $workspaceBranch = $projectBranch !== '' ? $projectBranch : $this->gitBranchPolicy()->forEnvironment($environment);
        $submittedBranch = trim((string) $this->input->post('pythonRepositoryBranch'));
        $jobBranch = $submittedBranch === '' ? $workspaceBranch : $submittedBranch;
        // The workspace clones with the opener's own account, not the job's build credential.
        $execution = $this->resolveGitPythonExecution($repositoryUrl, $jobBranch, $entryPoint, '', $project, '', '*', $jobPath);
        if ($project !== NULL) {
          // A project's jobs share the opener's workspace of the project.
          $location = $this->projectWorkspaceLocation($project);
          $relativeWorkspace = $location['relative'];
          $workspace = $location['absolute'];
        } else {
          $relativeWorkspace = $this->safeRelativePath('python/git/'.$jobName);
          $workspace = $relativeWorkspace === FALSE ? FALSE : rtrim($this->inlinePythonRepositoryRoot(), '/\\').DIRECTORY_SEPARATOR.$relativeWorkspace;
        }
        if ($execution === FALSE || $relativeWorkspace === FALSE) {
          return $fail('Check the repository URL, branch, job folder and entry file.', 400);
        }
        return array(
          'ok' => TRUE,
          'jobName' => $jobName,
          'environment' => $environment,
          'project' => $project,
          'execution' => $execution,
          'jobPath' => $jobPath,
          'workspaceBranch' => $workspaceBranch,
          'relativeWorkspace' => $relativeWorkspace,
          'workspace' => $workspace,
          'jobDirectory' => $jobPath === '' ? $workspace : $workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath)
        );
      }

      /**
       * Clones the working copy on first use, then (every time) points its Git
       * at the opener's identity and account and refreshes the editor files.
       * Existing repository files are never overwritten.
       */
      private function preparePersonalGitWorkspace($request, $scaffoldJob = FALSE) {
        if ($request['project'] !== NULL) {
          $project = $this->gitProjectSettings()->project($request['project']['id'], TRUE);
          $prepared = $this->prepareProjectWorkspace($project, array(
            'environment' => $request['environment'] === '' ? 'DEV' : $request['environment'],
            'branchMode' => 'shared',
            'jobName' => $request['jobName'],
            'jobPath' => $request['jobPath'],
            'scaffoldJob' => $scaffoldJob
          ));
          if ($prepared['ok']) {
            $prepared['payload']['jobBranch'] = $request['execution']['branch'];
            $prepared['payload']['jobPath'] = $request['jobPath'];
          }
          return $prepared;
        }
        $workspace = $request['workspace'];
        $openVsCodeRuntime = $this->openVsCodeRuntimeState(TRUE);
        if (empty($openVsCodeRuntime['available']) || empty($openVsCodeRuntime['running'])) {
          return array('ok' => FALSE, 'status' => 503, 'message' => isset($openVsCodeRuntime['message']) ? $openVsCodeRuntime['message'] : 'OpenVSCode could not be started.');
        }

        if (! is_dir($workspace.DIRECTORY_SEPARATOR.'.git')) {
          if (file_exists($workspace)) {
            return array('ok' => FALSE, 'status' => 409, 'message' => 'repository/'.$request['relativeWorkspace'].' exists but is not a Git working copy. Remove it to clone again.');
          }
          $clone = $this->cloneGitPythonWorkspace($request['execution'], $workspace, $request['workspaceBranch']);
          if (! $clone['ok']) {
            return array('ok' => FALSE, 'status' => 502, 'message' => $clone['message']);
          }
        }

        if (! $this->configureGitWorkspace($workspace, $request['jobName'], $request['execution']['repositoryUrl'])) {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not configure Git in this workspace.');
        }
        $pythonExecution = array(
          'sourceDirectory' => $workspace,
          'scriptPath' => $workspace.DIRECTORY_SEPARATOR.$request['execution']['entryPoint'],
          'requirementsPath' => $workspace.DIRECTORY_SEPARATOR.'requirements.txt',
          'pyprojectPath' => $workspace.DIRECTORY_SEPARATOR.'pyproject.toml',
          'dockerfilePath' => $workspace.DIRECTORY_SEPARATOR.'Dockerfile'
        );
        if ($this->ensureInlinePythonProjectFiles($pythonExecution, array('jobName' => $request['jobName'], 'gitWorkspace' => TRUE)) === FALSE
          || ! $this->excludeGitWorkspaceTooling($workspace)) {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not prepare VS Code project files for this workspace.');
        }
        $this->writeConnectorIdeSession($workspace, $request['jobName']);

        $openVsCodePath = $this->openVsCodeWorkspacePath($workspace);
        $openVsCodeUrl = $this->openVsCodeLaunchUrl($openVsCodePath);
        if ($openVsCodeUrl === '') {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'OpenVSCode Server workspace path could not be resolved.');
        }
        return array('ok' => TRUE, 'payload' => array(
          'ok' => TRUE,
          'openVsCodePath' => $openVsCodePath,
          'openVsCodeUrl' => $openVsCodeUrl,
          'launchUrls' => array('web' => $openVsCodeUrl),
          'openVsCodeReady' => ! empty($openVsCodeRuntime['ready']),
          'openVsCodeIdleShutdownMinutes' => isset($openVsCodeRuntime['idleShutdownMinutes']) ? (int) $openVsCodeRuntime['idleShutdownMinutes'] : 0,
          'workspaceBranch' => $request['workspaceBranch'],
          'jobBranch' => $request['execution']['branch']
        ));
      }

      private function pythonSampleById($sampleId) {
        $samples = require APPPATH.'config/job_samples.php';
        foreach (is_array($samples) ? $samples : array() as $sample) {
          if (isset($sample['id'], $sample['family']) && $sample['id'] === $sampleId && in_array($sample['family'], array('python', 'notebook'), TRUE)) {
            return $sample;
          }
        }
        return NULL;
      }

      /**
       * Opening a new project job gives its folder starter files. A sample
       * added before anyone edited them replaces them all (a starter test
       * would otherwise import a main.py the sample replaced); once one is
       * edited, samples go beside them as usual.
       */
      private function removeUntouchedJobStarter($jobDirectory, $folderName) {
        // Starters follow the project runtime's Python, so any version's counts.
        $present = FALSE;
        foreach (array_unique(array_merge(array($this->defaultDockerPythonVersion(), '3.10'), array('3.11', '3.12', '3.13', '3.14'))) as $version) {
          $present = $this->untouchedJobStarterFiles($jobDirectory, $this->projectWorkspaceLayout()->jobStarter('python', $folderName, $version));
          if ($present !== FALSE) {
            break;
          }
        }
        if ($present === FALSE) {
          return;
        }
        foreach ($present as $path) {
          @unlink($path);
        }
        if (! empty($present)) {
          @rmdir($jobDirectory.DIRECTORY_SEPARATOR.'tests');
        }
      }

      /** The starter files present in a job folder, or FALSE when any was edited. */
      private function untouchedJobStarterFiles($jobDirectory, $starter) {
        $present = array();
        foreach ($starter as $relative => $content) {
          $path = $jobDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
          if (! file_exists($path)) {
            continue;
          }
          if (! is_file($path) || (string) file_get_contents($path) !== $content) {
            return FALSE;
          }
          $present[] = $path;
        }
        return $present;
      }

      /**
       * A Python sample as the files of a job folder: its entry file and
       * other files, a pyproject.toml for $pythonVersion, and a Dockerfile
       * FROM $dockerImage when asked.
       *
       * @return array|FALSE relative path => content; FALSE for a bad entry file
       */
      private function pythonSampleFiles($sample, $pythonVersion, $dockerImage, $withDockerfile) {
        $entryPoint = $this->cleanPythonEntryPoint(isset($sample['entry_point']) ? $sample['entry_point'] : 'main.py', TRUE);
        if ($entryPoint === FALSE) {
          return FALSE;
        }
        $requirements = isset($sample['requirements']) ? (string) $sample['requirements'] : '';
        $files = array($entryPoint => isset($sample['code']) ? (string) $sample['code'] : "\n");
        foreach (isset($sample['files']) && is_array($sample['files']) ? $sample['files'] : array() as $file) {
          $path = isset($file['path']) ? $this->safeRelativePath($file['path']) : FALSE;
          if ($path !== FALSE) {
            $files[str_replace(DIRECTORY_SEPARATOR, '/', $path)] = isset($file['content']) ? (string) $file['content'] : '';
          }
        }
        if (trim($requirements) !== '') {
          $files['requirements.txt'] = $requirements;
        }
        $files['pyproject.toml'] = $this->defaultInlinePythonPyproject('jobseeker-sample-'.$sample['id'], $requirements, $pythonVersion);
        if ($withDockerfile) {
          $files['Dockerfile'] = $this->defaultInlinePythonDockerfile($dockerImage);
        }
        $hasTests = FALSE;
        foreach (array_keys($files) as $path) {
          $hasTests = $hasTests || strpos($path, 'tests/') === 0;
        }
        if (! empty($sample['run_tests']) && ! $hasTests) {
          $files['tests/test_smoke.py'] = "def test_python_environment():\n    assert True\n";
        }
        return $files;
      }

      private function writeSampleIntoGitWorkspace($workspace, $sample, $dockerImage, $withDockerfile) {
        $pythonVersion = $this->pythonVersionFromDockerImage($dockerImage === '' ? $this->defaultPythonDockerImage() : $dockerImage);
        $files = $this->pythonSampleFiles($sample, $pythonVersion, $dockerImage, $withDockerfile);
        if ($files === FALSE) {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'The sample has an invalid entry file.');
        }
        $entryPoint = $this->cleanPythonEntryPoint(isset($sample['entry_point']) ? $sample['entry_point'] : 'main.py', TRUE);

        // Never overwrite: a sample that would replace any existing file goes
        // into its own folder, where the build finds its pyproject/Dockerfile.
        $folder = '';
        foreach (array_keys($files) as $path) {
          if (file_exists($workspace.DIRECTORY_SEPARATOR.$path)) {
            $folder = 'samples/'.$sample['id'];
            break;
          }
        }
        if ($folder !== '' && file_exists($workspace.DIRECTORY_SEPARATOR.$folder)) {
          return array('ok' => FALSE, 'status' => 409, 'message' => 'This sample is already in '.$folder.'. Remove that folder to add it again.');
        }
        if ($folder === '' && ! file_exists($workspace.DIRECTORY_SEPARATOR.'.gitignore')) {
          $files['.gitignore'] = implode("\n", array('__pycache__/', '*.py[cod]', '.venv/', '.pytest_cache/', '.mypy_cache/', '.ruff_cache/', '.coverage', 'coverage.xml', 'htmlcov/', 'build/', 'dist/', '*.egg-info/', '.env', '.env.*', '!.env.example', ''));
        }

        $written = array();
        foreach ($files as $path => $content) {
          $relative = ($folder === '' ? '' : $folder.'/').$path;
          $target = $workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
          if (! $this->pathWithinBase($target, $workspace) || ! $this->ensureDirectory(dirname($target))
            || file_put_contents($target, $content, LOCK_EX) === FALSE) {
            return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not write '.$relative.' into the Git workspace.');
          }
          $written[] = $relative;
        }
        foreach (isset($sample['directories']) && is_array($sample['directories']) ? $sample['directories'] : array() as $directory) {
          $path = $this->safeRelativePath(is_array($directory) && isset($directory['path']) ? $directory['path'] : $directory);
          if ($path !== FALSE) {
            $this->ensureDirectory($workspace.DIRECTORY_SEPARATOR.($folder === '' ? '' : $folder.DIRECTORY_SEPARATOR).$path);
          }
        }
        return array('ok' => TRUE, 'files' => $written, 'folder' => $folder, 'entryPoint' => ($folder === '' ? '' : $folder.'/').$entryPoint);
      }

      /**
       * Copies the inline workspace into a working copy of the repository,
       * commits it, and pushes. Returns the pushed branches and commit.
       */
      private function pushInlineWorkspaceToGit($source, $workspace, $repositoryUrl, $connectorDirectory, $branch, $releaseBranch, $commitMessage, $overwrite, $jobPath = '', $projectFiles = array()) {
        $heads = $this->runCommand(array('timeout', '30', 'jobseeker-git', 'heads', '--connector-dir', $connectorDirectory, '--', $repositoryUrl));
        if ($heads['code'] !== 0) {
          return array('ok' => FALSE, 'status' => 502, 'message' => 'Your Git account could not read the repository.'.$this->gitFailureReason($heads['output']));
        }
        $remoteBranches = array_values(array_filter(array_map('trim', explode("\n", $heads['output']))));
        $emptyRemote = empty($remoteBranches);

        if ($emptyRemote) {
          if (! $this->ensureDirectory($workspace)
            || $this->runCommand(array('git', 'init', '-q', '-b', $branch, $workspace))['code'] !== 0
            || $this->runCommand(array('git', '-C', $workspace, 'remote', 'add', 'origin', $repositoryUrl))['code'] !== 0) {
            return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not create the local Git working copy.');
          }
        } else {
          $clone = $this->runCommand(array('timeout', '120', 'jobseeker-git', 'clone', '--full', '--connector-dir', $connectorDirectory, '--', $repositoryUrl, $workspace));
          if ($clone['code'] !== 0) {
            return array('ok' => FALSE, 'status' => 502, 'message' => 'The repository could not be cloned.'.$this->gitFailureReason($clone['output']));
          }
          $checkout = in_array($branch, $remoteBranches, TRUE)
            ? array('checkout', '-q', '-B', $branch, 'origin/'.$branch)
            : array('checkout', '-q', '-b', $branch);
          if ($this->runCommand(array_merge(array('git', '-C', $workspace), $checkout))['code'] !== 0) {
            return array('ok' => FALSE, 'status' => 500, 'message' => 'The repository was cloned but '.$branch.' could not be checked out.');
          }
        }

        $jobDirectory = $jobPath === '' ? $workspace : $workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath);
        if (! $this->ensureDirectory($jobDirectory)) {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not create '.$jobPath.' in the working copy.');
        }
        $copy = $this->copyInlineWorkspaceFiles($source, $jobDirectory, $overwrite);
        if (! $copy['ok']) {
          return $copy;
        }
        if ($emptyRemote && ! empty($projectFiles)) {
          $this->writeMissingProjectFiles($workspace, $projectFiles);
        }
        $this->excludeGitWorkspaceTooling($workspace);

        $identity = $this->gitWorkspaceIdentity();
        $git = array('git', '-C', $workspace, '-c', 'user.name='.$identity['name'], '-c', 'user.email='.$identity['email'], '-c', 'commit.gpgsign=false');
        if ($this->runCommand(array_merge($git, array('add', '-A', '--', '.')))['code'] !== 0) {
          return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not stage the job files.');
        }
        $staged = $this->runCommand(array('git', '-C', $workspace, 'diff', '--cached', '--quiet'))['code'] !== 0;
        if ($staged) {
          $commit = $this->runCommand(array_merge($git, array('commit', '-q', '-m', $commitMessage)));
          if ($commit['code'] !== 0) {
            return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not commit the job files.'.$this->gitFailureReason($commit['output']));
          }
        } else if ($emptyRemote) {
          return array('ok' => FALSE, 'status' => 400, 'message' => 'The inline workspace has no files to push.');
        }

        // An empty repository gets the release branch first, so hosts that
        // take their default branch from the first push choose main.
        $targets = array();
        if ($emptyRemote && $releaseBranch !== '' && $releaseBranch !== $branch) {
          $targets[] = $releaseBranch;
        }
        $targets[] = $branch;
        $pushed = array();
        foreach ($targets as $target) {
          $push = $this->runCommand(array('timeout', '120', 'jobseeker-git', 'push', '--connector-dir', $connectorDirectory, '--', $repositoryUrl, $workspace, 'HEAD:refs/heads/'.$target));
          if ($push['code'] !== 0) {
            return array('ok' => FALSE, 'status' => 502, 'message' => 'Git rejected the push to '.$target.'. Check that your account can write to this repository.'.$this->gitFailureReason($push['output']));
          }
          $pushed[] = $target;
          // Record what the remote now has, so VS Code shows the branch in sync.
          $this->runCommand(array('git', '-C', $workspace, 'update-ref', 'refs/remotes/origin/'.$target, 'HEAD'));
        }
        $this->runCommand(array('git', '-C', $workspace, 'config', 'branch.'.$branch.'.remote', 'origin'));
        $this->runCommand(array('git', '-C', $workspace, 'config', 'branch.'.$branch.'.merge', 'refs/heads/'.$branch));

        $head = $this->runCommand(array('git', '-C', $workspace, 'rev-parse', '--short', 'HEAD'));
        return array('ok' => TRUE, 'pushed' => $pushed, 'commit' => trim($head['output']), 'files' => $copy['files']);
      }

      /**
       * Everything a person wrote, without editor state, caches, virtual
       * environments, JobSeeker's per-workspace session or local secrets.
       */
      private function copyInlineWorkspaceFiles($source, $workspace, $overwrite) {
        $skipDirectories = array('.git', '.venv', 'venv', '.uv-cache', '.jobseeker-wheels', '.jobseeker-python-libs', '__pycache__', '.pytest_cache', '.mypy_cache', '.ruff_cache', '.vscode', '.continue', 'htmlcov', 'build', 'dist');
        $skipFiles = array('.env.jobseeker', '.coverage', 'coverage.xml', 'jobseeker-inline.code-workspace');
        $source = rtrim($source, '/\\');
        $pending = array();
        $iterator = new RecursiveIteratorIterator(
          new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            function($file) use ($skipDirectories, $skipFiles) {
              $name = $file->getFilename();
              if ($file->isLink()) {
                return FALSE;
              }
              if ($file->isDir()) {
                return ! in_array($name, $skipDirectories, TRUE) && substr($name, -9) !== '.egg-info';
              }
              return ! in_array($name, $skipFiles, TRUE) && ! preg_match('/\.py[cod]$/', $name)
                && ! ($name === '.env' || (strpos($name, '.env.') === 0 && $name !== '.env.example'));
            }
          )
        );
        foreach ($iterator as $file) {
          if ($file->isFile()) {
            $pending[] = substr($file->getPathname(), strlen($source) + 1);
          }
        }
        sort($pending);

        $conflicts = array();
        foreach ($pending as $relative) {
          $target = $workspace.DIRECTORY_SEPARATOR.$relative;
          if (is_file($target) && sha1_file($target) !== sha1_file($source.DIRECTORY_SEPARATOR.$relative)) {
            $conflicts[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
          }
        }
        if (! empty($conflicts) && ! $overwrite) {
          return array('ok' => FALSE, 'status' => 409, 'conflicts' => array_slice($conflicts, 0, 20),
            'message' => 'The repository already has different versions of '.count($conflicts).' file(s), such as '.implode(', ', array_slice($conflicts, 0, 3)).'. Choose "Replace existing files" to overwrite them in a new commit.');
        }
        foreach ($pending as $relative) {
          $target = $workspace.DIRECTORY_SEPARATOR.$relative;
          if (! $this->ensureDirectory(dirname($target)) || ! copy($source.DIRECTORY_SEPARATOR.$relative, $target)) {
            return array('ok' => FALSE, 'status' => 500, 'message' => 'JobSeeker could not copy '.$relative.' into the repository.');
          }
        }
        return array('ok' => TRUE, 'files' => count($pending));
      }

      /** The personal account in the connector-directory layout jobseeker-git reads. */
      private function materializePersonalGitAccount($account, $host) {
        $connectorDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobseeker-git-'.bin2hex(random_bytes(8));
        mkdir($connectorDirectory, 0700);
        $providerDefaults = array('github' => 'x-access-token', 'gitlab' => 'oauth2', 'bitbucket' => 'x-token-auth', 'azure_devops' => 'git');
        $materialized = array('host' => $host, 'auth_type' => $account['auth_type']);
        if ($account['auth_type'] === 'ssh_key') {
          $materialized['private_key'] = isset($account['secret']['private_key']) ? $account['secret']['private_key'] : '';
          $materialized['known_hosts'] = isset($account['secret']['known_hosts']) ? $account['secret']['known_hosts'] : '';
        } else {
          $materialized['username'] = $account['username'] !== '' ? $account['username']
            : (isset($providerDefaults[$account['provider']]) ? $providerDefaults[$account['provider']] : 'git');
          $secretName = $account['auth_type'] === 'username_password' ? 'password' : 'token';
          $materialized[$secretName] = isset($account['secret'][$secretName]) ? $account['secret'][$secretName] : '';
        }
        foreach ($materialized as $name => $value) {
          file_put_contents($connectorDirectory.DIRECTORY_SEPARATOR.$name, $value);
          chmod($connectorDirectory.DIRECTORY_SEPARATOR.$name, 0600);
        }
        return $connectorDirectory;
      }

      /** Git's own explanation, which never contains the secret (it is not in the URL). */
      private function gitFailureReason($output) {
        return preg_match('/^(?:fatal|error|remote): (.+)$/m', (string) $output, $match) ? ' Git said: '.trim($match[1]) : '';
      }

      /**
       * A branch the remote does not have yet starts from $baseBranch when the
       * remote has that one (a work/<name> branch forks the development
       * branch), else from the remote's default branch.
       */
      private function cloneGitPythonWorkspace($execution, $workspace, $workspaceBranch, $baseBranch = '') {
        $url = $execution['repositoryUrl'];
        $location = $this->gitRepositoryLocation($url);
        $host = $location['host'];
        $accountHost = $host.($location['port'] ? ':'.$location['port'] : '');
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $problem = NULL;
        $account = $host === '' ? FALSE : $this->gitAccounts->credential($this->vendorId, $accountHost, $location['path'], $location['transport'], $problem);
        if ($account === FALSE && $problem !== NULL) {
          return array('ok' => FALSE, 'message' => $problem);
        }

        // A full clone fetches every branch; the job's branch may not exist
        // yet, so check out the workspace branch afterwards instead.
        $connectorDirectory = NULL;
        if ($account === FALSE) {
          $command = array('git', '-c', 'credential.helper=', 'clone', '--', $url, $workspace);
        } else {
          $connectorDirectory = $this->materializePersonalGitAccount($account, $host);
          $command = array('jobseeker-git', 'clone', '--full', '--connector-dir', $connectorDirectory, '--', $url, $workspace);
        }
        $clone = $this->runCommand(array_merge(array('timeout', '300'), $command));
        if ($connectorDirectory !== NULL) {
          $this->removeUploadDirectory($connectorDirectory);
        }
        if ($clone['code'] !== 0) {
          $this->removeUploadDirectory($workspace);
          $reason = $this->gitFailureReason($clone['output']);
          return array('ok' => FALSE, 'message' => $account === FALSE
            ? 'Could not clone the repository without credentials. For a private repository, connect your '.$accountHost.' account under Profile > Git Accounts.'.$reason
            : 'Could not clone the repository with your '.$accountHost.' account.'.$reason);
        }

        $remoteHas = function($branch) use ($workspace) {
          return $branch !== '' && $this->runCommand(array('git', '-C', $workspace, 'show-ref', '--verify', '--quiet', 'refs/remotes/origin/'.$branch))['code'] === 0;
        };
        if ($remoteHas($workspaceBranch)) {
          $checkoutArgs = array('checkout', '-q', '-B', $workspaceBranch, 'origin/'.$workspaceBranch);
        } else if ($baseBranch !== $workspaceBranch && $remoteHas($baseBranch)) {
          // Not tracking the base, so a first push publishes the new branch.
          $checkoutArgs = array('checkout', '-q', '--no-track', '-b', $workspaceBranch, 'origin/'.$baseBranch);
        } else {
          $checkoutArgs = array('checkout', '-q', '-b', $workspaceBranch);
        }
        $checkout = $this->runCommand(array_merge(array('git', '-C', $workspace), $checkoutArgs));
        return $checkout['code'] === 0 ? array('ok' => TRUE) : array('ok' => FALSE, 'message' => 'The repository was cloned but '.$workspaceBranch.' could not be checked out.');
      }

      /** Host, optional port and repository path from URL or SCP-like SSH syntax. */
      private function gitRepositoryLocation($url) {
        if (preg_match('/^[A-Za-z0-9._%+\-]+@([A-Za-z0-9._\-]+):(.+)$/', (string) $url, $match)) {
          return array('host' => strtolower($match[1]), 'port' => 0, 'path' => trim($match[2], '/'), 'transport' => 'ssh');
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return array(
          'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
          'port' => (int) parse_url($url, PHP_URL_PORT),
          'path' => trim((string) parse_url($url, PHP_URL_PATH), '/'),
          'transport' => $scheme === 'ssh' ? 'ssh' : 'http'
        );
      }

      /**
       * Points the workspace's commits at the opener's identity and its pulls and
       * pushes at the opener's personal account, through a fresh session. The
       * helper list is reset first so credentials someone else stored in the
       * shared editor are never used here.
       */
      private function configureGitWorkspace($workspace, $jobName, $repositoryUrl) {
        $this->writeGitWorkspaceIdentity($workspace);
        $git = array('git', '-C', $workspace, 'config');
        foreach (array(
          array('include.path', 'jobseeker-identity'),
          array('credential.useHttpPath', 'true'),
          array('core.sshCommand', '/usr/local/bin/jobseeker-git-ssh'),
          array('--replace-all', 'credential.helper', ''),
          array('--add', 'credential.helper', '/usr/local/bin/jobseeker-git-credential')
        ) as $arguments) {
          if ($this->runCommand(array_merge($git, $arguments))['code'] !== 0) {
            return FALSE;
          }
        }

        $sessionPath = $workspace.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'jobseeker-git-session';
        $this->load->library('GitCredentialSession');
        $location = $this->gitRepositoryLocation($repositoryUrl);
        $accountHost = $location['host'].($location['port'] ? ':'.$location['port'] : '');
        $session = $this->gitcredentialsession->issue($this->vendorId, $jobName, $accountHost, $location['path']);
        if ($session === FALSE) {
          @unlink($sessionPath);
          return TRUE;
        }
        $previousUmask = umask(0077);
        $written = file_put_contents($sessionPath, $session);
        umask($previousUmask);
        return $written !== FALSE;
      }

      /** @return array code and combined output; no shell, no terminal prompts */
      private function runCommand(array $command) {
        $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('redirect', 1)), $pipes, NULL, array(
          'PATH' => '/usr/local/bin:/usr/bin:/bin',
          'HOME' => sys_get_temp_dir(),
          'GIT_TERMINAL_PROMPT' => '0'
        ));
        if (! is_resource($process)) {
          return array('code' => 1, 'output' => '');
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return array('code' => proc_close($process), 'output' => (string) $output);
      }

      /** The current user's commit identity. */
      private function gitWorkspaceIdentity() {
        $user = $this->db->select('name, email')->where('userId', (int) $this->vendorId)->get('tbl_users')->row();
        $clean = function($value) {
          return trim(str_replace(array("\n", "\r", "\0"), ' ', (string) $value));
        };
        $name = $clean($user ? $user->name : $this->name);
        $email = $clean($user ? $user->email : '');
        return array('name' => $name !== '' ? $name : 'JobSeeker user', 'email' => $email !== '' ? $email : 'jobseeker@localhost');
      }

      /** Commits made in the IDE are authored by whoever last opened it from JobSeeker. */
      private function writeGitWorkspaceIdentity($workspace) {
        $identity = $this->gitWorkspaceIdentity();
        $quote = function($value) {
          return '"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), (string) $value).'"';
        };
        $content = "[user]\n\tname = ".$quote($identity['name'])."\n\temail = ".$quote($identity['email'])."\n";
        file_put_contents($workspace.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'jobseeker-identity', $content, LOCK_EX);
      }

      /** Keeps the IDE's own files out of the repository without editing its .gitignore. */
      private function excludeGitWorkspaceTooling($workspace) {
        $path = $workspace.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'info'.DIRECTORY_SEPARATOR.'exclude';
        $marker = '# JobSeeker OpenVSCode tooling';
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        if (strpos($current, $marker) !== FALSE) {
          return TRUE;
        }
        $patterns = array($marker, '/.vscode/', '/.continue/', '/.venv/', '/.uv-cache/', '/.jobseeker-wheels/', '/.jobseeker-python-libs/', '/.env.jobseeker', '');
        return $this->ensureDirectory(dirname($path))
          && file_put_contents($path, rtrim($current, "\n").($current === '' ? '' : "\n").implode("\n", $patterns), LOCK_EX) !== FALSE;
      }
}

<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Project workspaces: develop a project in OpenVSCode before any job exists.
 *
 * The sidebar's VS Code launcher opens a project, whose layout is described
 * in the ProjectWorkspace library (one folder per job under jobs/, common
 * code in shared/). A Git project opens in the person's own working copy,
 * cloned with their personal Git account and checked out either on the
 * project's development branch or on their own work/<name> branch: people
 * never share uncommitted files, and their work meets only through Git. A
 * project without Git opens its one shared folder, which jobs run from.
 *
 * Job Creation lists the job folders it finds in the person's workspace and
 * turns one into a job; a Git job bound to a project opens this same
 * workspace instead of a clone of its own.
 *
 * Requires JobCreation and JobCreationGitWorkspaceTrait.
 */
trait ProjectWorkspaceTrait
{
      /** The launcher's project list, with the viewer's own workspace of each. */
      public function projectWorkspaces() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $environment = $this->projectWorkspaceEnvironment();
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $accounts = $this->gitAccounts->accounts($this->vendorId);
        $projects = array();
        $runtimesEnabled = $this->workspaceRuntimesEnabled();
        $runtimeSettings = $runtimesEnabled ? $this->workspaceRuntimeService()->model()->allProjectSettings() : array();
        $catalog = $runtimesEnabled ? $this->workspaceRuntimeService()->model()->runtimes() : array();
        foreach ($this->gitProjectSettings()->projects(TRUE, FALSE) as $project) {
          $summary = $this->projectWorkspaceSummary($project, $environment, $accounts);
          $key = isset($runtimeSettings[$summary['id']]) ? $runtimeSettings[$summary['id']]['runtimeKey'] : WorkspaceRuntime::DEFAULT_KEY;
          $summary['runtime'] = array(
            'key' => $key,
            'label' => $runtimesEnabled ? $this->projectRuntimeLabel($key, $catalog) : 'Default editor',
            'isolation' => isset($runtimeSettings[$summary['id']]) ? $runtimeSettings[$summary['id']]['isolation'] : 'user'
          );
          $projects[] = $summary;
        }
        // What "Add a sample" offers a Python project.
        $samples = array();
        foreach ($this->projectPythonSamples() as $sample) {
          $samples[] = array('id' => $sample['id'], 'name' => $sample['name'], 'description' => isset($sample['description']) ? (string) $sample['description'] : '',
            'complexity' => isset($sample['complexity']) ? (string) $sample['complexity'] : '', 'folder' => $this->projectWorkspaceLayout()->sampleFolder($sample['id']));
        }
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'environment' => $environment,
          'openVsCodeEnabled' => $this->openVsCodeEnabled(),
          'runtimesEnabled' => $runtimesEnabled,
          'types' => $this->projectWorkspaceLayout()->types(),
          'projects' => $projects,
          'samples' => $samples,
          'sampleUrl' => base_url().'jobCreation/projectWorkspaceSample'
        ));
      }

      /** Creates a project from the launcher, so a first project needs no detour. */
      public function projectWorkspaceCreateProject() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to create a project.'), 405);
          return;
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $this->input->post('name')));
        $type = $this->projectWorkspaceLayout()->cleanType($this->input->post('type'));
        $repositoryUrl = trim((string) $this->input->post('repository_url'));
        $repositoryUrl = $repositoryUrl === '' ? '' : $this->cleanPythonRepositoryUrl($repositoryUrl);
        if ($name === '' || strlen($name) > 255) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Give the project a name of up to 255 characters.'), 400);
          return;
        }
        if ($type === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Choose Python, Shell or Apache Hop.'), 400);
          return;
        }
        if ($repositoryUrl === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use an HTTPS or SSH repository URL without embedded credentials.'), 400);
          return;
        }
        $this->load->model('Context_model', 'workspaceProjects');
        if ($this->workspaceProjects->validateProject($name) > 0) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'A project named '.$name.' already exists. Choose it from the list.'), 409);
          return;
        }
        $projectId = (int) $this->workspaceProjects->insertProject(array(
          'ProjectName' => $name,
          'IsActive' => 1,
          'GitPath' => $repositoryUrl,
          'CreatedOn' => date('Y-m-d H:i:s')
        ));
        if ($projectId <= 0) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The project could not be created.'), 500);
          return;
        }
        $this->gitProjectSettings()->saveType($projectId, $type);
        log_message('info', 'Project '.$projectId.' ('.$name.', '.$type.') created from the VS Code launcher by user '.(int) $this->vendorId.'.');

        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $project = $this->gitProjectSettings()->project($projectId, TRUE);
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'project' => $this->projectWorkspaceSummary($project, $this->projectWorkspaceEnvironment(), $this->gitAccounts->accounts($this->vendorId)),
          'message' => $name.' was created'.($repositoryUrl !== '' ? ' with its Git repository. Choose its build credential and branches in Project Details before scheduling jobs.' : '.')
        ));
      }

      /**
       * Opens a project in OpenVSCode: the viewer's working copy of a Git
       * project (cloned on first use on the branch they choose), or the shared
       * folder of a project without Git.
       */
      public function projectWorkspaceOpen() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (! $this->openVsCodeEnabled()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'OpenVSCode Server is disabled for this JobSeeker deployment.'), 404);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to open a project workspace.'), 405);
          return;
        }
        $project = $this->gitProjectSettings()->project((int) $this->input->post('project_id'), TRUE);
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        $prepared = $this->prepareProjectWorkspace($project, array(
          'environment' => $this->projectWorkspaceEnvironment(),
          'branchMode' => (string) $this->input->post('branch_mode'),
          'branch' => (string) $this->input->post('branch')
        ));
        if (! $prepared['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $prepared['message']), $prepared['status']);
          return;
        }
        $payload = $prepared['payload'];
        $where = $project['repositoryUrl'] !== '' ? 'your working copy on '.$payload['workspaceBranch'] : 'the shared project folder';
        $payload['message'] = $project['name'].' opens in '.$where.($payload['created'] ? ' (new)' : '').'.';
        if (isset($payload['runtime']['status']) && $payload['runtime']['status'] === 'building') {
          $payload['message'] = $project['name'].' is ready in '.$where.'. Building its '.$payload['runtime']['label'].' runtime first; the editor opens when it is done.';
        } else if (isset($payload['runtime']['label'])) {
          $payload['message'] = $project['name'].' opens in '.$where.', in '.$payload['runtime']['label'].(! empty($payload['runtime']['shared']) ? ' (shared)' : '').'.';
        }
        $this->jsonJobCreationResponse($payload);
      }

      /**
       * The job folders in the viewer's workspace of a project, for Job
       * Creation. For a Git project each folder says whether builds can see
       * it yet: builds run what is pushed, not what is in the working copy.
       */
      public function projectWorkspaceJobs() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $projectId = (int) ($this->input->post('project_id') ?: $this->input->get('project_id'));
        $project = $this->gitProjectSettings()->project($projectId, TRUE);
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        $environment = $this->projectWorkspaceEnvironment();
        $location = $this->projectWorkspaceLocation($project);
        $state = $this->projectWorkspaceState($location['absolute'], $location['hasGit']);
        $jobs = $state['exists'] ? $this->projectWorkspaceLayout()->detectJobs($location['absolute'], $project['type']) : array();
        $buildBranch = '';
        if ($location['hasGit']) {
          $source = $this->gitProjectSettings()->gitSource($project['id'], $environment);
          $buildBranch = $source === FALSE ? '' : $source['branch'];
          if ($state['isGit']) {
            $jobs = $this->projectWorkspaceGitJobStates($location['absolute'], $jobs);
          }
        } else {
          foreach ($jobs as $index => $job) {
            // Jobs of a project without Git run the shared folder in place.
            $jobs[$index]['sourcePath'] = $location['relative'].'/'.$job['path'];
            $jobs[$index]['state'] = 'local';
          }
        }
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'project' => array('id' => $project['id'], 'name' => $project['name'], 'type' => $project['type'], 'hasGit' => $location['hasGit']),
          'runtime' => $this->projectRuntimeJobImage($project, $location),
          'environment' => $environment,
          'workspace' => array(
            'exists' => $state['exists'],
            'path' => 'repository/'.$location['relative'],
            'branch' => $state['branch'],
            'buildBranch' => $buildBranch,
            'changes' => $state['changes']
          ),
          'jobs' => $jobs
        ));
      }

      /**
       * Adds a sample from the sample library to the viewer's workspace of a
       * Python project, as jobs/<folder>. An existing folder is never
       * replaced. In VS Code, the task "JobSeeker: add sample" does the same.
       */
      public function projectWorkspaceSample() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs() || strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $project = $this->gitProjectSettings()->project((int) $this->input->post('project_id'), TRUE);
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        if ($project['type'] !== 'python') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Samples are Python jobs; '.$project['name'].' is not a Python project.'), 409);
          return;
        }
        $sample = $this->pythonSampleById((string) $this->input->post('sample_id'));
        if ($sample === NULL) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That Python sample is not in the sample library.'), 404);
          return;
        }
        $location = $this->projectWorkspaceLocation($project);
        if (! is_dir($location['absolute'])) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Open the project once first: the sample is added to your workspace of it.'), 409);
          return;
        }
        $layout = $this->projectWorkspaceLayout();
        $requested = trim((string) $this->input->post('folder'));
        $folder = $layout->sampleFolder($requested !== '' ? $requested : $sample['id']);
        $jobPath = ProjectWorkspace::JOBS_FOLDER.'/'.$folder;
        if (file_exists($location['absolute'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath))) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $jobPath.' already exists in your workspace. Give the sample another folder name.', 'folder' => $folder), 409);
          return;
        }
        $settings = $this->projectRuntimeSettings($project);
        $files = $this->projectSampleFiles($sample, $this->projectPythonVersion($project, $location), $this->projectRuntimeUsesDefault($settings));
        if ($files === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The sample has an invalid entry file.'), 500);
          return;
        }
        $jobFiles = array();
        foreach ($files as $path => $content) {
          $jobFiles[$jobPath.'/'.$path] = $content;
        }
        $written = $this->writeMissingProjectFiles($location['absolute'], $jobFiles);
        if (count($written) !== count($jobFiles)) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'JobSeeker could not write every file of the sample into '.$jobPath.'.'), 500);
          return;
        }
        // Runs of the new folder in the editor use its own connector scope.
        $this->writeConnectorIdeSession($location['absolute'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath), $folder, $project);
        log_message('info', 'User '.(int) $this->vendorId.' added the sample '.$sample['id'].' to project '.(int) $project['id'].' as '.$jobPath.'.');
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'path' => $jobPath,
          'files' => $written,
          'jobCreationUrl' => base_url().'JobCreation?project='.(int) $project['id'].'&folder='.$jobPath,
          'message' => $sample['name'].' was added as '.$jobPath.' in '.($location['hasGit'] ? 'your working copy. Open the project to run it, then commit and push it.' : 'the project folder. Open the project to run it.')
        ));
      }

      /**
       * The defaults in a notebook's cell tagged "parameters", so the form can
       * offer them: a job folder of a project (the viewer's workspace of it)
       * or a repository path, plus the notebook's file name. Also returns the
       * project's Context keys for the environment, the values a parameter
       * can read.
       */
      public function notebookParameters() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $entry = $this->cleanPythonEntryPoint($this->input->post('entry') ?: $this->input->get('entry'), TRUE);
        if ($entry === FALSE || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'ipynb') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Choose a notebook (.ipynb) entry file.'), 400);
          return;
        }
        $root = rtrim($this->inlinePythonRepositoryRoot(), '/\\');
        $projectId = (int) ($this->input->post('project_id') ?: $this->input->get('project_id'));
        $project = NULL;
        if ($projectId > 0) {
          $project = $this->gitProjectSettings()->project($projectId, TRUE);
          $jobPath = $this->projectWorkspaceLayout()->cleanJobPath((string) ($this->input->post('job_path') ?: $this->input->get('job_path')));
          if ($project === FALSE || $jobPath === FALSE) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project or job folder is not available.'), 404);
            return;
          }
          $location = $this->projectWorkspaceLocation($project);
          $folder = $location['absolute'].($jobPath === '' ? '' : DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath));
        } else {
          $folder = $this->resolveRepositoryPath((string) ($this->input->post('source_path') ?: $this->input->get('source_path')), $root);
          if ($folder !== FALSE && is_file($folder)) {
            $folder = dirname($folder);
          }
        }
        $file = $folder === FALSE ? FALSE : $this->resolvePythonFile($folder, $entry);
        if ($file === FALSE || filesize($file) > 32 * 1024 * 1024) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $entry.' was not found in that folder.'), 404);
          return;
        }
        $notebook = json_decode((string) file_get_contents($file), TRUE);
        if (! is_array($notebook) || ! isset($notebook['cells']) || ! is_array($notebook['cells'])) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $entry.' is not a readable notebook.'), 422);
          return;
        }
        $parameters = array();
        $hasParametersCell = FALSE;
        $codeCells = 0;
        foreach ($notebook['cells'] as $cell) {
          if (! is_array($cell) || (isset($cell['cell_type']) ? $cell['cell_type'] : '') !== 'code') {
            continue;
          }
          $codeCells++;
          $tags = isset($cell['metadata']['tags']) && is_array($cell['metadata']['tags']) ? $cell['metadata']['tags'] : array();
          if ($hasParametersCell || ! in_array('parameters', $tags, TRUE)) {
            continue;
          }
          $hasParametersCell = TRUE;
          $source = isset($cell['source']) ? (is_array($cell['source']) ? implode('', $cell['source']) : (string) $cell['source']) : '';
          foreach (preg_split('/\r\n|\r|\n/', $source) as $line) {
            $parameter = $this->notebookParameterLine($line);
            if ($parameter !== NULL) {
              $parameters[] = $parameter;
            }
          }
        }
        $environment = $this->projectWorkspaceEnvironment();
        $contextKeys = array();
        if (is_array($project) || preg_match('#/workspaces/shared/[a-z0-9._-]+-(\d+)/#', str_replace('\\', '/', $file), $match)) {
          $contextProject = is_array($project) ? $project : $this->gitProjectSettings()->project((int) $match[1], TRUE);
          if (is_array($contextProject)) {
            $rows = $this->db->select('ContextKey')->from('vw_contextdetails')
              ->where('ProjectName', $contextProject['name'])->where('Environment', $environment)->where('IsActive', 1)
              ->order_by('ContextKey', 'ASC')->get()->result_array();
            foreach ($rows as $row) {
              $contextKeys[] = (string) $row['ContextKey'];
            }
            $project = $contextProject;
          }
        }
        $this->jsonJobCreationResponse(array(
          'ok' => TRUE,
          'entry' => $entry,
          'codeCells' => $codeCells,
          'hasParametersCell' => $hasParametersCell,
          'parameters' => $parameters,
          'environment' => $environment,
          'project' => is_array($project) ? array('id' => $project['id'], 'name' => $project['name']) : NULL,
          'contextKeys' => array_values(array_unique($contextKeys))
        ));
      }

      /** name = value  # comment, from a parameters cell; NULL for other lines. */
      private function notebookParameterLine($line) {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]{0,63})\s*(?::\s*[^=]+?)?=\s*(.+)$/', rtrim((string) $line), $match)) {
          return NULL;
        }
        $rest = $match[2];
        $value = $rest;
        $comment = '';
        // A # starts a comment only outside a quoted string.
        $quote = '';
        for ($index = 0, $length = strlen($rest); $index < $length; $index++) {
          $character = $rest[$index];
          if ($quote !== '') {
            if ($character === '\\') {
              $index++;
            } else if ($character === $quote) {
              $quote = '';
            }
          } else if ($character === '"' || $character === "'") {
            $quote = $character;
          } else if ($character === '#') {
            $value = substr($rest, 0, $index);
            $comment = trim(substr($rest, $index + 1));
            break;
          }
        }
        $value = trim($value);
        // Python literals become what the form's values mean: JSON or text.
        if (preg_match('/^([\'"])(.*)\1$/s', $value, $quoted)) {
          $text = stripcslashes($quoted[2]);
          $value = preg_match('/^(-?\d+(\.\d+)?|true|false|null|True|False|None)$/', $text) ? json_encode($text) : $text;
        } else if ($value === 'True' || $value === 'False') {
          $value = strtolower($value);
        } else if ($value === 'None') {
          $value = 'null';
        }
        return array('name' => $match[1], 'value' => $value, 'comment' => $comment);
      }

      /** The top-bar environment, or DEV when "All environments" is selected. */
      private function projectWorkspaceEnvironment() {
        $environment = trim((string) ($this->input->post('environment') ?: $this->input->get('environment')));
        if ($environment === '' || $environment === '0') {
          $environment = $this->jobSeekerEnvironmentPreference();
        }
        $environment = strtoupper(trim((string) $this->jobSeekerEffectiveEnvironment($environment === '0' ? '' : $environment, '')));
        return $environment === '' || $environment === 'ALL' || $environment === 'UNKNOWN' ? 'DEV' : $environment;
      }

      /** Where the viewer works on a project, relative to and inside the repository root. */
      private function projectWorkspaceLocation($project) {
        $layout = $this->projectWorkspaceLayout();
        $root = rtrim($this->inlinePythonRepositoryRoot(), '/\\');
        $hasGit = trim((string) $project['repositoryUrl']) !== '';
        $parent = $layout->workspaceParent($hasGit, $this->vendorId);
        $name = $layout->resolveDirectory($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $parent), $project['id'], $project['name']);
        $relative = $parent.'/'.$name;
        return array(
          'relative' => $relative,
          'absolute' => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative),
          'hasGit' => $hasGit
        );
      }

      /** The project's development branch here, and the viewer's own branch. */
      private function projectWorkspaceBranches($project, $environment) {
        $source = $this->gitProjectSettings()->gitSource($project['id'], $environment);
        $shared = $source !== FALSE && $source['branch'] !== '' ? $source['branch'] : $this->gitBranchPolicy()->forEnvironment($environment);
        $identity = $this->gitWorkspaceIdentity();
        $handle = $identity['email'] !== 'jobseeker@localhost' ? strstr($identity['email'], '@', TRUE) : $identity['name'];
        $personal = $this->gitBranchPolicy()->cleanBranch($this->projectWorkspaceLayout()->personalBranch($handle));
        return array('shared' => $shared, 'personal' => $personal === FALSE || $personal === '' ? 'work/me' : $personal);
      }

      private function projectWorkspaceState($workspace, $hasGit) {
        $state = array('exists' => is_dir($workspace), 'isGit' => FALSE, 'branch' => '', 'changes' => 0, 'ahead' => NULL);
        if (! $state['exists'] || ! $hasGit || ! is_dir($workspace.DIRECTORY_SEPARATOR.'.git')) {
          return $state;
        }
        $state['isGit'] = TRUE;
        $head = $this->runCommand(array('git', '-C', $workspace, 'symbolic-ref', '--quiet', '--short', 'HEAD'));
        $state['branch'] = $head['code'] === 0 ? trim($head['output']) : 'detached';
        $status = $this->runCommand(array('git', '-C', $workspace, 'status', '--porcelain'));
        $state['changes'] = $status['code'] === 0 ? count(array_filter(explode("\n", trim($status['output'])), 'strlen')) : 0;
        $upstream = $this->projectWorkspaceUpstream($workspace, $state['branch']);
        $ahead = $upstream === '' ? array('code' => 1) : $this->runCommand(array('git', '-C', $workspace, 'rev-list', '--count', $upstream.'..HEAD'));
        $state['ahead'] = $ahead['code'] === 0 ? (int) trim($ahead['output']) : NULL;
        return $state;
      }

      /**
       * The remote branch a working copy's branch is compared with: its
       * upstream, or origin/<branch> after a plain `git push origin <branch>`
       * of a branch that was created locally (as in an empty repository).
       */
      private function projectWorkspaceUpstream($workspace, $branch) {
        $upstream = $this->runCommand(array('git', '-C', $workspace, 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}'));
        if ($upstream['code'] === 0 && trim($upstream['output']) !== '') {
          return trim($upstream['output']);
        }
        return $branch !== '' && $this->runCommand(array('git', '-C', $workspace, 'show-ref', '--verify', '--quiet', 'refs/remotes/origin/'.$branch))['code'] === 0
          ? 'origin/'.$branch : '';
      }

      /**
       * What builds can see of each job folder. Only local refs are read (no
       * network): the remote-tracking branch is as fresh as the last pull.
       *   untracked  never committed
       *   changed    committed, with uncommitted edits
       *   unpushed   committed on this branch but not pushed
       *   pushed     on the remote branch this working copy tracks
       */
      private function projectWorkspaceGitJobStates($workspace, $jobs) {
        $this->projectWorkspaceLayout();
        $status = $this->runCommand(array('git', '-C', $workspace, 'status', '--porcelain', '--untracked-files=all', '--', ProjectWorkspace::JOBS_FOLDER));
        $head = $this->runCommand(array('git', '-C', $workspace, 'symbolic-ref', '--quiet', '--short', 'HEAD'));
        $upstreamRef = $this->projectWorkspaceUpstream($workspace, $head['code'] === 0 ? trim($head['output']) : '');
        $upstreamFolders = array();
        if ($upstreamRef !== '') {
          $tree = $this->runCommand(array('git', '-C', $workspace, 'ls-tree', '-d', '--name-only', $upstreamRef, ProjectWorkspace::JOBS_FOLDER.'/'));
          foreach (explode("\n", $tree['code'] === 0 ? $tree['output'] : '') as $line) {
            if (trim($line) !== '') {
              $upstreamFolders[trim($line)] = TRUE;
            }
          }
        }
        foreach ($jobs as $index => $job) {
          $prefix = $job['path'].'/';
          $untracked = FALSE;
          $changed = FALSE;
          foreach (explode("\n", $status['code'] === 0 ? $status['output'] : '') as $line) {
            if (strlen($line) > 3 && strpos(substr($line, 3), $prefix) === 0) {
              if (substr($line, 0, 2) === '??') {
                $untracked = TRUE;
              } else {
                $changed = TRUE;
              }
            }
          }
          $committed = $this->runCommand(array('git', '-C', $workspace, 'cat-file', '-e', 'HEAD:'.$job['path']))['code'] === 0;
          $unpushed = $upstreamRef !== '' && $committed
            && trim($this->runCommand(array('git', '-C', $workspace, 'log', '--format=%h', '-1', $upstreamRef.'..HEAD', '--', $job['path']))['output']) !== '';
          if (! $committed) {
            $state = 'untracked';
          } else if ($changed || $untracked) {
            $state = 'changed';
          } else if ($upstreamRef === '' || ! isset($upstreamFolders[$job['path']]) || $unpushed) {
            $state = 'unpushed';
          } else {
            $state = 'pushed';
          }
          $jobs[$index]['state'] = $state;
        }
        return $jobs;
      }

      private function projectWorkspaceSummary($project, $environment, $accounts) {
        $location = $this->projectWorkspaceLocation($project);
        $state = $this->projectWorkspaceState($location['absolute'], $location['hasGit']);
        $summary = array(
          'id' => (int) $project['id'],
          'name' => $project['name'],
          'type' => $project['type'],
          'hasGit' => $location['hasGit'],
          'repositoryUrl' => $project['repositoryUrl'],
          'credentialKey' => $project['credentialKey'],
          'workspace' => array(
            'path' => 'repository/'.$location['relative'],
            'shared' => ! $location['hasGit'],
            'exists' => $state['exists'],
            'branch' => $state['branch'],
            'changes' => $state['changes'],
            'ahead' => $state['ahead'],
            'jobs' => $state['exists'] ? count($this->projectWorkspaceLayout()->detectJobs($location['absolute'], $project['type'])) : 0
          )
        );
        if ($location['hasGit']) {
          $summary['branches'] = $this->projectWorkspaceBranches($project, $environment);
          $summary['account'] = $this->projectWorkspaceAccountLabel($accounts, $project['repositoryUrl']);
          $repository = $this->gitRepositoryLocation($project['repositoryUrl']);
          $summary['repository'] = $repository['host'].($repository['port'] ? ':'.$repository['port'] : '').'/'.preg_replace('#\.git$#i', '', $repository['path']);
        }
        return $summary;
      }

      /** Mirrors UserGitAccount_model::credential() without reading any secret. */
      private function projectWorkspaceAccountLabel($accounts, $repositoryUrl) {
        $location = $this->gitRepositoryLocation($repositoryUrl);
        $host = $location['host'].($location['port'] ? ':'.$location['port'] : '');
        $path = strtolower(preg_replace('#\.git$#i', '', $location['path']));
        $best = NULL;
        foreach ((array) $accounts as $account) {
          $prefix = strtolower(trim((string) $account->path_prefix, '/'));
          if (strtolower((string) $account->host) !== $host || ($prefix !== '' && $path !== $prefix && strpos($path, $prefix.'/') !== 0)
            || ($location['transport'] === 'ssh') !== ($account->auth_type === 'ssh_key')) {
            continue;
          }
          if ($best === NULL || strlen($prefix) > strlen(trim((string) $best->path_prefix, '/'))) {
            $best = $account;
          }
        }
        if ($best === NULL) {
          return NULL;
        }
        return trim((string) $best->label) !== '' ? (string) $best->label : ucfirst(str_replace('_', ' ', (string) $best->provider)).((string) $best->username !== '' ? ' · '.$best->username : '');
      }

      /**
       * Clones (first use), configures and opens a project workspace.
       * Options: environment, branchMode (shared|personal|custom), branch,
       * jobName and jobPath (the job opening it), scaffoldJob (add starter
       * files when that job's folder does not exist yet).
       */
      private function prepareProjectWorkspace($project, $options) {
        $fail = function($status, $message) {
          return array('ok' => FALSE, 'status' => $status, 'message' => $message);
        };
        // The Default runtime opens in the shared OpenVSCode service; any other
        // runtime in a deployment of its own (WorkspaceRuntimeTrait).
        $runtimeSettings = $this->projectRuntimeSettings($project);
        $openVsCodeRuntime = array('ready' => FALSE, 'idleShutdownMinutes' => 0);
        if ($this->projectRuntimeUsesDefault($runtimeSettings)) {
          $openVsCodeRuntime = $this->openVsCodeRuntimeState(TRUE);
          if (empty($openVsCodeRuntime['available']) || empty($openVsCodeRuntime['running'])) {
            return $fail(503, isset($openVsCodeRuntime['message']) ? $openVsCodeRuntime['message'] : 'OpenVSCode could not be started.');
          }
        } else {
          $engine = $this->workspaceRuntimeService()->engineState();
          if (! $engine['available'] || ! $engine['toolkit']) {
            return $fail(503, $engine['message']);
          }
        }
        // The editor installs the SDK from the repository's copy each time it
        // opens; refresh that copy, so editors follow JobSeeker's SDK as jobs do.
        $this->ensurePythonSharedLibrary(rtrim($this->inlinePythonRepositoryRoot(), '/\\'));
        $layout = $this->projectWorkspaceLayout();
        $location = $this->projectWorkspaceLocation($project);
        $workspace = $location['absolute'];
        $environment = isset($options['environment']) ? $options['environment'] : 'DEV';
        $created = FALSE;
        $fresh = FALSE;

        if ($location['hasGit']) {
          if (! is_dir($workspace.DIRECTORY_SEPARATOR.'.git')) {
            if (is_dir($workspace) && count((array) @scandir($workspace)) > 2) {
              return $fail(409, 'repository/'.$location['relative'].' exists but is not a Git working copy. Move its files away to clone the project again.');
            }
            $branches = $this->projectWorkspaceBranches($project, $environment);
            $mode = isset($options['branchMode']) ? $options['branchMode'] : 'shared';
            if ($mode === 'personal') {
              $branch = $branches['personal'];
            } else if ($mode === 'custom') {
              $branch = $this->cleanPythonRepositoryBranch(isset($options['branch']) ? $options['branch'] : '');
              if ($branch === FALSE || $branch === '') {
                return $fail(400, 'Enter a valid branch name to work on.');
              }
            } else {
              $branch = $branches['shared'];
            }
            if (! $this->ensureDirectory(dirname($workspace))) {
              return $fail(500, 'JobSeeker could not create repository/'.dirname($location['relative']).'.');
            }
            $clone = $this->cloneGitPythonWorkspace(array('repositoryUrl' => $project['repositoryUrl']), $workspace, $branch, $branches['shared']);
            if (! $clone['ok']) {
              return $fail(502, $clone['message']);
            }
            $created = TRUE;
            // Only a repository without commits gets the starting layout;
            // an existing repository is never changed by opening it.
            $fresh = $this->runCommand(array('git', '-C', $workspace, 'rev-parse', '--verify', '--quiet', 'HEAD'))['code'] !== 0;
            log_message('info', 'User '.(int) $this->vendorId.' cloned project '.$project['id'].' into repository/'.$location['relative'].' on '.$branch.'.');
          }
          if (! $this->configureGitWorkspace($workspace, 'project-'.$project['id'], $project['repositoryUrl'])) {
            return $fail(500, 'JobSeeker could not configure Git in this workspace.');
          }
        } else {
          $created = ! is_dir($workspace);
          $fresh = $created;
          if (! $this->ensureDirectory($workspace)) {
            return $fail(500, 'JobSeeker could not create repository/'.$location['relative'].'.');
          }
        }

        $scaffolded = array();
        if ($fresh) {
          $scaffolded = $this->writeMissingProjectFiles($workspace, $layout->scaffoldFiles($project['type'], $project['name']));
        }
        $jobPath = isset($options['jobPath']) ? (string) $options['jobPath'] : '';
        $jobFolderCreated = FALSE;
        if ($jobPath !== '' && ! empty($options['scaffoldJob']) && ! file_exists($workspace.DIRECTORY_SEPARATOR.$jobPath)) {
          $jobFiles = array();
          foreach ($layout->jobStarter($project['type'], basename($jobPath), $this->projectPythonVersion($project, $location)) as $path => $content) {
            $jobFiles[$jobPath.'/'.$path] = $content;
          }
          $scaffolded = array_merge($scaffolded, $this->writeMissingProjectFiles($workspace, $jobFiles));
          $jobFolderCreated = TRUE;
        }
        if (! $this->writeProjectWorkspaceTooling($workspace, $project, $location)) {
          return $fail(500, 'JobSeeker could not prepare VS Code project files for this workspace.');
        }
        $this->writeProjectWorkspaceSessions($workspace, $project, isset($options['jobName']) ? (string) $options['jobName'] : '', $jobPath);

        $state = $this->projectWorkspaceState($workspace, $location['hasGit']);
        $openVsCodePath = $this->openVsCodeWorkspacePath($workspace);
        $openVsCodeUrl = $this->openVsCodeLaunchUrl($openVsCodePath);
        if ($openVsCodeUrl === '') {
          return $fail(500, 'OpenVSCode Server workspace path could not be resolved.');
        }
        $payload = array(
          'ok' => TRUE,
          'openVsCodePath' => $openVsCodePath,
          'openVsCodeUrl' => $openVsCodeUrl,
          'launchUrls' => array('web' => $openVsCodeUrl),
          'openVsCodeReady' => ! empty($openVsCodeRuntime['ready']),
          'openVsCodeIdleShutdownMinutes' => isset($openVsCodeRuntime['idleShutdownMinutes']) ? (int) $openVsCodeRuntime['idleShutdownMinutes'] : 0,
          'projectId' => (int) $project['id'],
          'workspacePath' => 'repository/'.$location['relative'],
          'workspaceBranch' => $state['branch'],
          'sharedWorkspace' => ! $location['hasGit'],
          'created' => $created,
          'scaffolded' => $scaffolded,
          'jobFolderCreated' => $jobFolderCreated
        );
        if (! $this->projectRuntimeUsesDefault($runtimeSettings)) {
          $launched = $this->launchProjectRuntime($project, $location, $runtimeSettings);
          if (! $launched['ok']) {
            return $fail($launched['status'], $launched['message']);
          }
          // Until a build finishes there is no editor URL yet.
          if (! isset($launched['payload']['openVsCodeUrl'])) {
            unset($payload['openVsCodeUrl'], $payload['launchUrls']);
          }
          $payload = array_merge($payload, $launched['payload']);
        }
        return array('ok' => TRUE, 'payload' => $payload);
      }

      /** Writes each file that does not exist yet; returns the ones written. */
      private function writeMissingProjectFiles($workspace, $files) {
        $written = array();
        foreach ($files as $relative => $content) {
          $target = $workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
          if (file_exists($target) || ! $this->pathWithinBase($target, $workspace) || ! $this->ensureDirectory(dirname($target))) {
            continue;
          }
          if (file_put_contents($target, $content, LOCK_EX) !== FALSE) {
            if (substr($relative, -3) === '.sh') {
              @chmod($target, 0755);
            }
            $written[] = $relative;
          }
        }
        return $written;
      }

      /**
       * Connector sessions for runs inside the editor (see ConnectorIdeSession):
       * the project root may read connectors shared by every job, and each
       * jobs/<job> folder those of the job named like it. The SDK looks for
       * the session from the working directory upwards, so a run inside a job
       * folder uses that job's scope. The job that opened the workspace gets
       * its folder's session under its real name.
       */
      private function writeProjectWorkspaceSessions($workspace, $project, $jobName, $jobPath) {
        $this->writeConnectorIdeSession($workspace, '*', $project);
        foreach ($this->projectWorkspaceLayout()->detectJobs($workspace, $project['type']) as $job) {
          if ($jobName === '' || $job['path'] !== $jobPath) {
            $this->writeConnectorIdeSession($workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $job['path']), $job['name'], $project);
          }
        }
        if ($jobName !== '' && $jobPath !== '' && is_dir($workspace.DIRECTORY_SEPARATOR.$jobPath)) {
          $this->writeConnectorIdeSession($workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $jobPath), $jobName, $project);
        }
      }

      /**
       * VS Code files for a whole project: one environment at the root with
       * every job's dependencies, and tasks that run or test the job of the
       * current file from its own folder, as builds do. Files a person may
       * have customized are written once; the helper script is JobSeeker's
       * and refreshed on every open. All of them stay out of Git.
       */
      private function writeProjectWorkspaceTooling($workspace, $project, $location) {
        $layout = $this->projectWorkspaceLayout();
        $type = $project['type'];
        // The runtime's Python: what the "new job" task's pyproject.toml requires.
        $pythonVersion = $this->projectPythonVersion($project, $location);
        $editorRepositoryRoot = $this->openVsCodeWorkspaceRoot().'/repository';
        $files = array();

        $helper = $layout->helperScript($type, base_url().'JobCreation', $project['id'], $pythonVersion, $location['hasGit']);
        if (! $this->writeInlinePythonProjectFile($workspace, '.vscode/jobseeker.sh', $helper, $files, TRUE)) {
          return FALSE;
        }
        @chmod($workspace.DIRECTORY_SEPARATOR.'.vscode'.DIRECTORY_SEPARATOR.'jobseeker.sh', 0755);

        $newJobTask = array(
          'label' => 'JobSeeker: new job',
          'type' => 'shell',
          'command' => 'sh .vscode/jobseeker.sh new "${input:jobseekerJobName}"',
          'problemMatcher' => array()
        );
        $inputs = array(array('id' => 'jobseekerJobName', 'type' => 'promptString', 'description' => 'Name of the new job folder under jobs/, such as load-orders'));
        $settings = array(
          'editor.formatOnSave' => FALSE,
          'workbench.secondarySideBar.defaultVisibility' => 'hidden',
          'files.exclude' => array('**/.env.jobseeker' => TRUE)
        );
        $tasks = array('version' => '2.0.0', 'tasks' => array($newJobTask), 'inputs' => $inputs);
        $writes = array();

        if ($type === 'python') {
          $sdkPath = $layout->pathUp($location['relative']).'/python/lib/jobseeker-sdk';
          $dataAssets = array(
            'JOBSEEKER_REPOSITORY_ROOT' => $editorRepositoryRoot,
            'JOBSEEKER_DATA_ASSETS_MANIFEST' => $editorRepositoryRoot.'/data-assets/manifest.json'
          );
          $settings = array_merge($settings, array(
            'python.defaultInterpreterPath' => '${workspaceFolder}/.venv/bin/python',
            'python.pythonPath' => '${workspaceFolder}/.venv/bin/python',
            'python.venvPath' => '${workspaceFolder}',
            'python.useEnvironmentsExtension' => FALSE,
            'python.languageServer' => 'None',
            'python.terminal.activateEnvironment' => TRUE,
            // One job's tests import its own main.py; discovering every
            // job's tests at once would mix them up. Use the test task.
            'python.testing.pytestEnabled' => FALSE,
            'terminal.integrated.env.linux' => array_merge(array(
              'VIRTUAL_ENV' => '${workspaceFolder}/.venv',
              'PATH' => '${workspaceFolder}/.venv/bin:${env:PATH}',
              // shared/ is importable everywhere, as it is in builds.
              'PYTHONPATH' => '${workspaceFolder}',
              'PYTHONNOUSERSITE' => '1'
            ), $dataAssets),
            'basedpyright.analysis.extraPaths' => array('.', '.venv/lib/python'.$pythonVersion.'/site-packages'),
            'basedpyright.analysis.typeCheckingMode' => 'basic',
            '[python]' => array('editor.defaultFormatter' => 'charliermarsh.ruff')
          ));
          $tasks['tasks'] = array(
            array(
              'label' => 'JobSeeker: setup Python environment',
              'type' => 'shell',
              'command' => 'sh .vscode/bootstrap-python.sh',
              'runOptions' => array('runOn' => 'folderOpen'),
              'presentation' => array('reveal' => 'silent', 'panel' => 'dedicated', 'showReuseMessage' => FALSE),
              'problemMatcher' => array()
            ),
            array(
              'label' => 'JobSeeker: run current job file',
              'type' => 'shell',
              'command' => 'sh .vscode/jobseeker.sh run "${file}"',
              'options' => array('env' => $dataAssets),
              'problemMatcher' => array()
            ),
            array(
              'label' => 'JobSeeker: test current job',
              'type' => 'shell',
              'command' => 'sh .vscode/jobseeker.sh test "${file}"',
              'group' => array('kind' => 'test', 'isDefault' => TRUE),
              'problemMatcher' => array()
            ),
            $newJobTask,
            array('label' => 'JobSeeker: add sample', 'type' => 'shell', 'command' => 'sh .vscode/jobseeker-samples.sh "${input:jobseekerSample}"', 'problemMatcher' => array()),
            array('label' => 'JobSeeker: ruff check', 'type' => 'shell', 'command' => '. .venv/bin/activate && ruff check .', 'problemMatcher' => array()),
            array('label' => 'JobSeeker: mypy', 'type' => 'shell', 'command' => '. .venv/bin/activate && mypy .', 'problemMatcher' => array())
          );
          // The sample library, as job folders for this project's runtime.
          $samples = array();
          $sampleOptions = array();
          $onDefaultEditor = $this->projectRuntimeUsesDefault($this->projectRuntimeSettings($project));
          foreach ($this->projectPythonSamples() as $sample) {
            $sampleFiles = $this->projectSampleFiles($sample, $pythonVersion, $onDefaultEditor);
            if ($sampleFiles !== FALSE) {
              $samples[] = array('id' => $sample['id'], 'name' => $sample['name'], 'files' => $sampleFiles);
              $sampleOptions[] = array('label' => $sample['name'], 'value' => $sample['id']);
            }
          }
          $tasks['inputs'][] = array('id' => 'jobseekerSample', 'type' => 'pickString', 'description' => 'Sample to add as a new job folder under jobs/', 'options' => $sampleOptions);
          $sampleScript = $layout->sampleScript($samples, base_url().'JobCreation', $project['id'], $location['hasGit']);
          if (! $this->writeInlinePythonProjectFile($workspace, '.vscode/jobseeker-samples.sh', $sampleScript, $files, TRUE)) {
            return FALSE;
          }
          @chmod($workspace.DIRECTORY_SEPARATOR.'.vscode'.DIRECTORY_SEPARATOR.'jobseeker-samples.sh', 0755);
          $launch = array('version' => '0.2.0', 'configurations' => array(array(
            'name' => 'JobSeeker: debug current job file',
            'type' => 'debugpy',
            'request' => 'launch',
            'python' => '${workspaceFolder}/.venv/bin/python',
            'program' => '${file}',
            'console' => 'integratedTerminal',
            'cwd' => '${fileDirname}',
            'justMyCode' => TRUE,
            'env' => array_merge(array('PYTHONPATH' => '${workspaceFolder}', 'PYTHONNOUSERSITE' => '1'), $dataAssets)
          )));
          // JobSeeker's own script: refreshed on every open, like jobseeker.sh.
          if (! $this->writeInlinePythonProjectFile($workspace, '.vscode/bootstrap-python.sh', $this->projectWorkspaceBootstrap($pythonVersion, $sdkPath), $files, TRUE)) {
            return FALSE;
          }
          $writes[] = array('.vscode/launch.json', json_encode($launch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
          $recommendations = array('ms-python.python', 'charliermarsh.ruff', 'ms-python.mypy-type-checker', 'detachhead.basedpyright');
          if ($this->envFlag('JOBSEEKER_OPENVSCODE_CONTINUE_ENABLED', FALSE)) {
            $recommendations[] = 'Continue.continue';
          }
          $writes[] = array('.vscode/extensions.json', json_encode(array('recommendations' => $recommendations), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } else if ($type === 'shell') {
          array_unshift($tasks['tasks'], array(
            'label' => 'JobSeeker: run current job script',
            'type' => 'shell',
            'command' => 'sh .vscode/jobseeker.sh run "${file}"',
            'problemMatcher' => array()
          ));
        }
        $writes[] = array('.vscode/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $writes[] = array('.vscode/tasks.json', json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $writes[] = array('.continue/rules/jobseeker.md', implode("\n", array(
          '# JobSeeker '.$layout->types()[$type]['label'].' project',
          '',
          'This workspace is one project. Each JobSeeker job is a folder under `jobs/`; builds run a job from its own folder',
          'and fetch only that folder and `shared/`, so never make one job depend on another job\'s folder.',
          'Put code several jobs need in `shared/`'.($type === 'python' ? ' and import it as `from shared import ...`.' : '.'),
          $type === 'python' ? 'Each job keeps its own pyproject.toml, tests/ and optional Dockerfile. Use `from jobseeker import JobSeeker` and resolve data through contexts, Data Assets and connectors; never embed credentials.' : '',
          'Never print connector values, tokens, passwords or private keys.',
          ''
        )));
        foreach ($writes as $write) {
          if (! $this->writeInlinePythonProjectFile($workspace, $write[0], $write[1], $files, FALSE)) {
            return FALSE;
          }
        }
        $this->mergeProjectWorkspaceTasks($workspace, $tasks);
        if ($type === 'python' && ! $this->ensureInlinePythonInterpreterPlaceholder($workspace)) {
          return FALSE;
        }
        return ! $location['hasGit'] || $this->excludeProjectWorkspaceTooling($workspace);
      }

      /**
       * tasks.json is written once, since people add their own tasks; a
       * project opened before a JobSeeker task existed gets it here. Tasks
       * and inputs are matched by label and id, and the sample list is kept
       * current. A file that is not plain JSON is left alone.
       */
      private function mergeProjectWorkspaceTasks($workspace, $tasks) {
        $path = $workspace.DIRECTORY_SEPARATOR.'.vscode'.DIRECTORY_SEPARATOR.'tasks.json';
        $current = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
        if (! is_array($current) || (isset($current['tasks']) && ! is_array($current['tasks'])) || (isset($current['inputs']) && ! is_array($current['inputs']))) {
          return;
        }
        $merged = $current + array('tasks' => array(), 'inputs' => array());
        $labels = array();
        foreach ($merged['tasks'] as $task) {
          $labels[isset($task['label']) ? (string) $task['label'] : ''] = TRUE;
        }
        foreach ($tasks['tasks'] as $task) {
          if (! isset($labels[$task['label']])) {
            $merged['tasks'][] = $task;
          }
        }
        foreach ($tasks['inputs'] as $input) {
          $found = FALSE;
          foreach ($merged['inputs'] as $index => $existing) {
            if (isset($existing['id']) && $existing['id'] === $input['id']) {
              $found = TRUE;
              if ($input['id'] === 'jobseekerSample') {
                $merged['inputs'][$index] = $input;
              }
            }
          }
          if (! $found) {
            $merged['inputs'][] = $input;
          }
        }
        if ($merged != $current) {
          file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
        }
      }

      /** The Python and notebook samples of the sample library (application/config/job_samples.php). */
      private function projectPythonSamples() {
        $samples = require APPPATH.'config/job_samples.php';
        return array_values(array_filter(is_array($samples) ? $samples : array(), function($sample) {
          return isset($sample['id'], $sample['name'], $sample['family']) && in_array($sample['family'], array('python', 'notebook'), TRUE);
        }));
      }

      /**
       * A sample as a job folder of this project. It requires the project
       * runtime's Python; a sample that ships a Dockerfile keeps it only on
       * the Default editor, since jobs of a runtime run in the runtime's image.
       */
      private function projectSampleFiles($sample, $pythonVersion, $onDefaultEditor) {
        $withDockerfile = ! empty($sample['use_dockerfile']) && $onDefaultEditor;
        return $this->pythonSampleFiles($sample, $pythonVersion, 'python:'.$pythonVersion.'-slim', $withDockerfile);
      }

      /** Keeps the editor's files, at any depth, out of the project's commits. */
      private function excludeProjectWorkspaceTooling($workspace) {
        if (! $this->excludeGitWorkspaceTooling($workspace)) {
          return FALSE;
        }
        $path = $workspace.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'info'.DIRECTORY_SEPARATOR.'exclude';
        $marker = '# JobSeeker project workspace';
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        if (strpos($current, $marker) !== FALSE) {
          return TRUE;
        }
        $patterns = array($marker, '.env.jobseeker', '.venv/', '__pycache__/', '.pytest_cache/', '');
        return file_put_contents($path, rtrim($current, "\n")."\n".implode("\n", $patterns), LOCK_EX) !== FALSE;
      }

      /**
       * One virtual environment for the whole project: the JobSeeker SDK and
       * the dependencies of the root, shared/ and every job folder.
       */
      private function projectWorkspaceBootstrap($pythonVersion, $sdkPath) {
        return implode("\n", array(
          '#!/bin/sh',
          '# Generated by JobSeeker each time the project is opened; do not edit.',
          '# One environment with the dependencies of every job in this project.',
          'set -eu',
          'cd "$(dirname "$0")/.."',
          'unset PYTHONPATH',
          'export PYTHONNOUSERSITE=1',
          'export POETRY_VIRTUALENVS_CREATE=false',
          'target='.$pythonVersion,
          'python_bin="${JOBSEEKER_WORKSPACE_PYTHON:-python$target}"',
          'if ! command -v "$python_bin" >/dev/null 2>&1; then',
          '  if command -v uv >/dev/null 2>&1; then uv python install "$target"; python_bin="$(uv python find "$target")"; else python_bin=python3; fi',
          'fi',
          '# In a workspace runtime the image\'s Python and preinstalled packages are',
          '# the base of .venv; a .venv made in another runtime is replaced.',
          'venv_flags=',
          '[ "${JOBSEEKER_VENV_SYSTEM_SITE_PACKAGES:-0}" = 1 ] && venv_flags=--system-site-packages',
          'venv_id="${JOBSEEKER_RUNTIME:-default}${venv_flags:+ $venv_flags}"',
          '# Also replaces the placeholder interpreter JobSeeker leaves until this runs.',
          'if [ ! -x .venv/bin/python ] || ! .venv/bin/python -c "import sys; raise SystemExit(0 if sys.prefix != sys.base_prefix else 1)" 2>/dev/null \\',
          '  || [ "$(cat .venv/.jobseeker-runtime 2>/dev/null || echo default)" != "$venv_id" ]; then',
          '  rm -rf .venv',
          '  if command -v uv >/dev/null 2>&1; then uv venv --seed $venv_flags -p "$python_bin" .venv; else "$python_bin" -m venv $venv_flags .venv; fi',
          '  printf \'%s\\n\' "$venv_id" > .venv/.jobseeker-runtime',
          'fi',
          'site_dir="$(.venv/bin/python -c \'import sysconfig; print(sysconfig.get_path("purelib"))\')"',
          '# A runtime whose Python is a virtual environment itself (a Dockerfile\'s',
          '# /opt/venv on PATH): --system-site-packages reaches only the interpreter',
          '# under it, so .venv reads that environment\'s packages through a .pth.',
          'runtime_sites=',
          'if [ -n "$venv_flags" ]; then',
          '  runtime_sites="$("$python_bin" -c \'import site, sys; print("\\n".join(site.getsitepackages()) if sys.prefix != sys.base_prefix else "")\' 2>/dev/null || true)"',
          'fi',
          'if [ -n "$runtime_sites" ]; then printf \'%s\\n\' "$runtime_sites" > "$site_dir/_jobseeker_runtime.pth"; else rm -f "$site_dir/_jobseeker_runtime.pth"; fi',
          '# shared/ imports from anywhere, as in builds: notebooks and the debugger too.',
          'printf \'%s\\n\' "$(pwd)" > "$site_dir/_jobseeker_project.pth"',
          '# uv\'s activate script reads unset variables.',
          'set +u; . .venv/bin/activate; set -u',
          'command -v poetry >/dev/null 2>&1 || python -m pip install --quiet --disable-pip-version-check "poetry==2.4.1"',
          'command -v ruff >/dev/null 2>&1 || python -m pip install --quiet --disable-pip-version-check "ruff==0.16.4"',
          'command -v mypy >/dev/null 2>&1 || python -m pip install --quiet --disable-pip-version-check "mypy==2.3.1"',
          'python -c "import pytest" 2>/dev/null || python -m pip install --quiet --disable-pip-version-check "pytest>=8,<10"',
          'sdk='.escapeshellarg($sdkPath),
          'if [ -d "$sdk" ]; then',
          '  build="$(mktemp -d)"',
          '  cp -R "$sdk/." "$build/"',
          '  rm -rf "$build/build" "$build/src/"*.egg-info',
          '  python -m pip wheel --quiet --no-deps --wheel-dir "$build/wheelhouse" "$build"',
          '  python -m pip install --quiet --force-reinstall "$build"/wheelhouse/jobseeker_runtime-*.whl',
          '  rm -rf "$build"',
          'fi',
          '# A job with a poetry.lock installs exactly what it locks. One without is',
          '# installed from pyproject.toml directly, so opening the project never',
          '# writes lock files into anyone\'s job folders.',
          'jobseeker_dependencies() {',
          '  python -c \'import re, sys, tomllib; deps = tomllib.load(open(sys.argv[1], "rb")).get("project", {}).get("dependencies", []); print("\\n".join(d for d in deps if re.split(r"[\\s\\[<>=!~;@(]", d.strip(), maxsplit=1)[0].lower().replace("_", "-") != "jobseeker-runtime"))\' "$1"',
          '}',
          'for dir in . shared jobs/*; do',
          '  [ -d "$dir" ] || continue',
          '  if [ -s "$dir/pyproject.toml" ] && [ -f "$dir/poetry.lock" ]; then',
          '    echo "Installing $dir (poetry.lock)"',
          '    (cd "$dir" && { poetry check --lock --no-interaction >/dev/null 2>&1 || poetry lock --no-interaction --no-ansi; } && poetry install --no-root --no-interaction --no-ansi) || echo "Could not install the dependencies of $dir." >&2',
          '  elif [ -s "$dir/pyproject.toml" ] && [ -f "$dir/uv.lock" ] && command -v uv >/dev/null 2>&1; then',
          '    echo "Installing $dir (uv.lock)"',
          '    # The locked versions, without the project itself or local paths (the SDK is installed above).',
          '    (cd "$dir" && uv export --frozen --no-hashes --no-emit-project --format requirements-txt) | grep -v -i -E "^(-e |\\.|/|file:|jobseeker[-_]runtime)" | python -m pip install --quiet --disable-pip-version-check -r /dev/stdin || echo "Could not install the dependencies of $dir." >&2',
          '  elif [ -s "$dir/pyproject.toml" ]; then',
          '    deps="$(jobseeker_dependencies "$dir/pyproject.toml")" || { echo "Could not read $dir/pyproject.toml." >&2; continue; }',
          '    [ -z "$deps" ] || { echo "Installing $dir (pyproject.toml)"; printf "%s\\n" "$deps" | python -m pip install --quiet --disable-pip-version-check -r /dev/stdin || echo "Could not install the dependencies of $dir." >&2; }',
          '  elif [ -s "$dir/requirements.txt" ]; then',
          '    echo "Installing $dir (requirements.txt)"',
          '    python -m pip install --quiet -r "$dir/requirements.txt" || echo "Could not install the dependencies of $dir." >&2',
          '  fi',
          'done',
          'python -c "import jobseeker, sys; print(sys.executable)"',
          ''
        ));
      }
}

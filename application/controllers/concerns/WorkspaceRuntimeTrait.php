<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * A project's workspace runtime in the VS Code launcher and wherever a project
 * workspace opens (doc/jobseeker/Architecture/workspace-runtimes.md).
 *
 * A project on the Default runtime opens in the shared OpenVSCode service as
 * before. Any other runtime opens in a deployment of its own: prepareProject
 * Workspace() calls launchProjectRuntime(), which builds the runtime when it
 * has no image yet and otherwise starts the project's editor container. The
 * browser then polls projectWorkspaceRuntimeStatus(), which finishes that
 * work, until the editor answers.
 *
 * Requires JobCreation and ProjectWorkspaceTrait.
 */
trait WorkspaceRuntimeTrait
{
      private $workspaceRuntimeServiceInstance = NULL;

      private function workspaceRuntimeService() {
        if ($this->workspaceRuntimeServiceInstance === NULL) {
          $this->load->library('WorkspaceRuntimeService');
          $this->workspaceRuntimeServiceInstance = $this->workspaceruntimeservice;
          $this->workspaceRuntimeServiceInstance->useRepositoryRoot($this->inlinePythonRepositoryRoot());
        }
        return $this->workspaceRuntimeServiceInstance;
      }

      private function workspaceRuntimesEnabled() {
        $service = $this->workspaceRuntimeService();
        return $this->openVsCodeEnabled() && $service->enabled();
      }

      /** The project's settings; Default when runtimes are off. */
      private function projectRuntimeSettings($project) {
        if (! $this->workspaceRuntimesEnabled()) {
          return array('runtimeKey' => WorkspaceRuntime::DEFAULT_KEY, 'isolation' => 'user', 'cpus' => 0, 'memoryMb' => 0);
        }
        return $this->workspaceRuntimeService()->projectSettings($project['id']);
      }

      private function projectRuntimeUsesDefault($settings) {
        return $settings['runtimeKey'] === WorkspaceRuntime::DEFAULT_KEY;
      }

      /** Display name of a runtime key. */
      private function projectRuntimeLabel($key, $catalog = NULL) {
        if ($key === WorkspaceRuntime::DEFAULT_KEY) {
          return 'Default editor';
        }
        if ($key === WorkspaceRuntime::DEVCONTAINER_KEY) {
          return 'Dev container';
        }
        foreach ($catalog === NULL ? $this->workspaceRuntimeService()->model()->runtimes() : $catalog as $runtime) {
          if ($runtime['key'] === $key) {
            return $runtime['name'];
          }
        }
        return $key;
      }

      /**
       * The build plan of the project's runtime: the catalog runtime, or the
       * devcontainer.json in the viewer's workspace of the project.
       */
      private function projectRuntimePlan($project, $location, $settings) {
        $service = $this->workspaceRuntimeService();
        if ($settings['runtimeKey'] === WorkspaceRuntime::DEVCONTAINER_KEY) {
          if (! is_dir($location['absolute'])) {
            return array('ok' => FALSE, 'message' => 'Open the project once to get its files; its .devcontainer is read from your workspace.');
          }
          return $service->devcontainerPlan($project['id'], $location['absolute']);
        }
        $runtime = $service->model()->runtime($settings['runtimeKey']);
        if ($runtime === FALSE || ! $runtime['active']) {
          return array('ok' => FALSE, 'message' => 'The runtime '.$settings['runtimeKey'].' is no longer in the catalog. Choose another one for this project.');
        }
        return $service->planFor($runtime);
      }

      /**
       * What a deployment mounts, repository-relative: a Just me deployment
       * gets the owner's workspace, a Shared one every working copy of the
       * project; both get the SDK read-only and Data Assets.
       */
      private function projectRuntimeMounts($project, $location, $ownerId) {
        $mounts = array();
        if ($ownerId > 0 || ! $location['hasGit']) {
          $mounts[$location['relative']] = FALSE;
        } else {
          $layout = $this->projectWorkspaceLayout();
          $root = rtrim($this->inlinePythonRepositoryRoot(), '/\\');
          foreach ((array) glob($root.DIRECTORY_SEPARATOR.ProjectWorkspace::ROOT.DIRECTORY_SEPARATOR.'u*', GLOB_ONLYDIR) as $userDirectory) {
            if (! preg_match('/^u[0-9]+$/', basename($userDirectory))) {
              continue;
            }
            $name = $layout->resolveDirectory($userDirectory, $project['id'], $project['name']);
            if (is_dir($userDirectory.DIRECTORY_SEPARATOR.$name) && ! is_link($userDirectory.DIRECTORY_SEPARATOR.$name)) {
              $mounts[ProjectWorkspace::ROOT.'/'.basename($userDirectory).'/'.$name] = FALSE;
            }
          }
          $mounts[$location['relative']] = FALSE;
          // Whoever opens it, a shared editor must get the same container.
          ksort($mounts, SORT_STRING);
        }
        // Its parent, not the SDK folder: JobSeeker refreshes the SDK by
        // renaming a new folder into place, which a bind mount of the old
        // folder would never see (the editor would find it empty).
        $mounts['python/lib'] = TRUE;
        $mounts['data-assets'] = FALSE;
        return $mounts;
      }

      private function projectRuntimeStatusUrl($project) {
        return base_url().'jobCreation/projectWorkspaceRuntimeStatus?project_id='.(int) $project['id'];
      }

      /**
       * Brings the project's runtime editor up for the viewer: starts its build
       * when the image is missing, otherwise deploys it.
       *
       * @return array ok, status (HTTP), message, payload (launch fields)
       */
      private function launchProjectRuntime($project, $location, $settings, $startBuild = TRUE) {
        $service = $this->workspaceRuntimeService();
        $fail = function($status, $message) {
          return array('ok' => FALSE, 'status' => $status, 'message' => $message);
        };
        $engine = $service->engineState();
        if (! $engine['available'] || ! $engine['toolkit']) {
          return $fail(503, $engine['message']);
        }
        $plan = $this->projectRuntimePlan($project, $location, $settings);
        if (! $plan['ok']) {
          return $fail(409, $plan['message']);
        }
        $build = $this->projectRuntimeUsableBuild($plan);
        $statusUrl = $this->projectRuntimeStatusUrl($project);
        $runtime = array('key' => $settings['runtimeKey'], 'label' => $plan['label'], 'image' => $plan['ideImage'], 'isolation' => $settings['isolation'], 'warnings' => $plan['warnings']);
        if ($build['status'] !== 'ready') {
          if ($build['status'] === 'failed' && ! $startBuild) {
            return $fail(409, 'The '.$plan['label'].' runtime failed to build: '.$build['message']);
          }
          if ($build['status'] !== 'building') {
            $started = $service->startBuild($plan, $this->vendorId);
            if (! $started['ok']) {
              return $fail(503, $started['message']);
            }
          }
          return array('ok' => TRUE, 'payload' => array(
            'openVsCodeReady' => FALSE,
            'openVsCodeStatusUrl' => $statusUrl,
            'runtime' => array_merge($runtime, array('status' => 'building')),
            'runtimeBuildHash' => $plan['buildHash']
          ));
        }
        $ownerId = $settings['isolation'] === 'shared' ? 0 : (int) $this->vendorId;
        $mounts = $this->projectRuntimeMounts($project, $location, $ownerId);
        // Dev container hooks run in the project folder; in a shared editor of
        // a Git project that is the first working copy, not the opener's, so
        // the container does not change with whoever opens it.
        $hookFolder = $ownerId === 0 && $location['hasGit'] ? (string) key($mounts) : $location['relative'];
        $deployed = $service->deploy(array(
          'projectId' => (int) $project['id'],
          'ownerId' => $ownerId,
          'openerId' => (int) $this->vendorId,
          'plan' => $plan,
          'runtimeKey' => $settings['runtimeKey'],
          'mounts' => $mounts,
          'folder' => $hookFolder,
          'cpus' => $settings['cpus'],
          'memoryMb' => $settings['memoryMb'],
          'retryCrashed' => $startBuild
        ));
        if (! $deployed['ok']) {
          return $fail(502, $deployed['message']);
        }
        if ($deployed['recreated']) {
          log_message('info', 'User '.(int) $this->vendorId.' recreated the '.$settings['runtimeKey'].' editor of project '.(int) $project['id'].'.');
        }
        $url = $service->launchUrl($deployed['instance'], $location['relative']);
        return array('ok' => TRUE, 'payload' => array(
          'openVsCodeUrl' => $url,
          'launchUrls' => array('web' => $url),
          'openVsCodeReady' => $deployed['status'] === 'ready',
          'openVsCodeStatusUrl' => $statusUrl,
          'openVsCodeIdleShutdownMinutes' => $service->idleMinutes(),
          'runtime' => array_merge($runtime, array('status' => $deployed['status'], 'shared' => $ownerId === 0, 'recreated' => $deployed['recreated']))
        ));
      }

      /**
       * The image the project's jobs run by default: its runtime's, once built,
       * so a job runs where it was developed. NULL on the Default runtime.
       */
      private function projectRuntimeJobImage($project, $location) {
        $settings = $this->projectRuntimeSettings($project);
        if ($this->projectRuntimeUsesDefault($settings) || ! $this->workspaceRuntimeService()->docker()->available()) {
          return NULL;
        }
        $plan = $this->projectRuntimePlan($project, $location, $settings);
        if (! $plan['ok']) {
          return NULL;
        }
        $docker = $this->workspaceRuntimeService()->docker();
        if (is_array($docker->inspectImage($plan['runtimeImage']))) {
          return array('key' => $settings['runtimeKey'], 'label' => $plan['label'], 'image' => $plan['runtimeImage']);
        }
        // The recipe changed and is not built yet: the last image built for
        // this runtime, rather than no image, which would leave the job on the
        // Jenkins agent without the packages it was developed with.
        foreach ($this->workspaceRuntimeService()->model()->readyBuilds() as $build) {
          if ($build['image_key'] === $plan['imageKey'] && is_array($docker->inspectImage($build['runtime_image']))) {
            return array('key' => $settings['runtimeKey'], 'label' => $plan['label'].' (last build)', 'image' => $build['runtime_image']);
          }
        }
        return NULL;
      }

      /**
       * A plan's build state, ready while a rebuild of the same recipe runs or
       * after one failed: the last image is still there, so editors keep it
       * (and move to a new one at their next open).
       */
      private function projectRuntimeUsableBuild($plan) {
        $build = $this->workspaceRuntimeService()->buildState($plan);
        if (in_array($build['status'], array('building', 'failed'), TRUE)
          && is_array($this->workspaceRuntimeService()->docker()->inspectImage($plan['ideImage']))) {
          $build['rebuilding'] = $build['status'] === 'building';
          $build['rebuildFailed'] = $build['status'] === 'failed' ? ($build['message'] !== '' ? $build['message'] : 'The last rebuild failed.') : '';
          $build['status'] = 'ready';
        }
        return $build;
      }

      /**
       * The Python a project's jobs run on, for the files JobSeeker writes
       * (job starters, the "new job" task): the Default editor's, its
       * catalog runtime's, or its dev container's; lenient when unknown, so
       * a starter never requires a Python the runtime does not have.
       */
      private function projectPythonVersion($project, $location = NULL) {
        $settings = $this->projectRuntimeSettings($project);
        if ($this->projectRuntimeUsesDefault($settings)) {
          return $this->defaultDockerPythonVersion();
        }
        $spec = $this->workspaceRuntimeService()->spec();
        $version = '';
        if ($settings['runtimeKey'] === WorkspaceRuntime::DEVCONTAINER_KEY) {
          $location = $location === NULL ? $this->projectWorkspaceLocation($project) : $location;
          $definitionPath = is_dir($location['absolute']) ? $spec->findDevcontainer($location['absolute']) : '';
          if ($definitionPath !== '') {
            $definition = $spec->devcontainer((string) file_get_contents($location['absolute'].DIRECTORY_SEPARATOR.$definitionPath), $definitionPath);
            if ($definition['ok'] && $definition['image'] !== '') {
              $version = $spec->pythonVersionOfDockerfile($definition['image']);
            } else if ($definition['ok'] && is_file($location['absolute'].DIRECTORY_SEPARATOR.$definition['dockerfile'])) {
              $version = $spec->pythonVersionOfDockerfile((string) file_get_contents($location['absolute'].DIRECTORY_SEPARATOR.$definition['dockerfile']));
            }
          }
        } else {
          $runtime = $this->workspaceRuntimeService()->model()->runtime($settings['runtimeKey']);
          $version = $runtime === FALSE ? '' : $spec->pythonVersionOf($runtime);
        }
        return $version !== '' ? $version : WorkspaceRuntime::LENIENT_PYTHON;
      }

      private function projectRuntimeRequestProject() {
        $projectId = (int) ($this->input->post('project_id') ?: $this->input->get('project_id'));
        return $this->gitProjectSettings()->project($projectId, TRUE);
      }

      /** The launcher's runtime panel for one project. */
      public function projectWorkspaceRuntime() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        if (! $this->workspaceRuntimesEnabled()) {
          $this->jsonJobCreationResponse(array('ok' => TRUE, 'enabled' => FALSE, 'settings' => $this->projectRuntimeSettings($project),
            'message' => 'Workspace runtimes need the Docker job runtime, which this deployment does not run.'));
          return;
        }
        $service = $this->workspaceRuntimeService();
        $settings = $service->projectSettings($project['id']);
        $location = $this->projectWorkspaceLocation($project);
        $catalog = $service->model()->runtimes(TRUE);
        $engine = $service->engineState();
        $runtimes = array();
        foreach ($catalog as $runtime) {
          $plan = $service->planFor($runtime);
          $state = $engine['available'] && $engine['toolkit'] ? $service->buildState($plan) : array('status' => 'unknown');
          if ($state['status'] === 'none' && is_array($service->docker()->inspectImage($plan['runtimeImage']))) {
            $state['status'] = 'outdated';
          }
          $runtimes[] = array('key' => $runtime['key'], 'name' => $runtime['name'], 'kind' => $runtime['kind'], 'description' => $runtime['description'], 'status' => $state['status']);
        }
        // Templates not in the catalog yet: choosing one adds it.
        $fromTemplates = array();
        foreach ($catalog as $runtime) {
          $fromTemplates[isset($runtime['spec']['template']) ? (string) $runtime['spec']['template'] : ''] = TRUE;
          $fromTemplates[$runtime['key']] = TRUE;
        }
        $categories = $service->spec()->templateCategories();
        $templates = array();
        foreach ($service->spec()->templates() as $key => $template) {
          $templates[] = array('key' => $key, 'name' => $template['name'], 'description' => $template['description'],
            'category' => $categories[$template['category']]['label'], 'inCatalog' => isset($fromTemplates[$key]));
        }
        $payload = array(
          'ok' => TRUE,
          'enabled' => TRUE,
          'engine' => $engine,
          'settings' => $settings,
          'defaults' => $service->defaultResources(),
          'runtimes' => $runtimes,
          'templates' => $templates,
          'devcontainer' => is_dir($location['absolute']) ? $service->spec()->findDevcontainer($location['absolute']) : '',
          'runtimesUrl' => base_url().'workspace-runtimes',
          'gateway' => array(
            'url' => $service->gatewayUrl(),
            'secure' => $service->gatewaySecure(),
            // With HTTPS the browser may not trust JobSeeker's own authority yet.
            'tls' => stripos($service->gatewayUrl(), 'https://') === 0,
            'certificateUrl' => base_url().'workspace-gateway-ca.crt'
          )
        );
        if (! $this->projectRuntimeUsesDefault($settings)) {
          $payload = array_merge($payload, $this->projectRuntimeState($project, $location, $settings, FALSE));
        }
        $this->jsonJobCreationResponse($payload);
      }

      /**
       * Build and deployment state of the project's runtime for the viewer.
       * With $advance, a ready build is deployed (the open flow's second half).
       */
      private function projectRuntimeState($project, $location, $settings, $advance) {
        $service = $this->workspaceRuntimeService();
        $state = array('label' => $this->projectRuntimeLabel($settings['runtimeKey']), 'build' => NULL, 'deployment' => NULL, 'ready' => FALSE);
        $engine = $service->engineState();
        if (! $engine['available'] || ! $engine['toolkit']) {
          $state['message'] = $engine['message'];
          return $state;
        }
        $plan = $this->projectRuntimePlan($project, $location, $settings);
        if (! $plan['ok']) {
          $state['message'] = $plan['message'];
          return $state;
        }
        $build = $this->projectRuntimeUsableBuild($plan);
        $reported = $build;
        // Built before, and only JobSeeker's editor layer changed since.
        if ($build['status'] === 'none' && is_array($service->docker()->inspectImage($plan['runtimeImage']))) {
          $reported['status'] = 'outdated';
        }
        $state['label'] = $plan['label'];
        $state['warnings'] = $plan['warnings'];
        $state['build'] = array_merge($reported, array('hash' => $plan['buildHash'], 'image' => $plan['ideImage'], 'runtimeImage' => $plan['runtimeImage'],
          'log' => $build['status'] === 'ready' ? array() : array_slice($service->buildLog($plan['buildHash'], 60), -60)));
        $ownerId = $settings['isolation'] === 'shared' ? 0 : (int) $this->vendorId;
        $instance = $service->model()->instance($project['id'], $ownerId);
        if ($advance && $build['status'] === 'ready') {
          $launched = $this->launchProjectRuntime($project, $location, $settings, FALSE);
          if (! $launched['ok']) {
            $state['message'] = $launched['message'];
            $state['failed'] = TRUE;
            return $state;
          }
          $state = array_merge($state, $launched['payload']);
          $instance = $service->model()->instance($project['id'], $ownerId);
        }
        if ($instance !== FALSE) {
          $deployment = $service->deploymentState($instance);
          $state['deployment'] = array_merge($deployment, array('port' => (int) $instance['port'], 'shared' => $ownerId === 0,
            'current' => $build['status'] === 'ready' && $instance['image'] === $plan['ideImage'], 'lastOpenedAt' => (string) $instance['last_opened_at']));
          $state['ready'] = $deployment['ready'] && $build['status'] === 'ready';
          if ($advance && $deployment['state'] === 'stopped' && $deployment['message'] !== '') {
            $state['message'] = $deployment['message'];
          }
        }
        return $state;
      }

      /** Polled while an editor builds or starts; deploys a build that finished. */
      public function projectWorkspaceRuntimeStatus() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to follow a runtime.'), 405);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        $settings = $this->projectRuntimeSettings($project);
        if ($this->projectRuntimeUsesDefault($settings)) {
          $runtime = $this->openVsCodeRuntimeState(FALSE);
          $this->jsonJobCreationResponse(array('ok' => TRUE, 'ready' => ! empty($runtime['ready'])));
          return;
        }
        $state = $this->projectRuntimeState($project, $this->projectWorkspaceLocation($project), $settings, TRUE);
        // ok is FALSE once waiting cannot help: a failed build or editor.
        $failed = ! empty($state['failed']) || (isset($state['build']['status']) && $state['build']['status'] === 'failed')
          || (empty($state['build']) && ! empty($state['message']));
        $this->jsonJobCreationResponse(array_merge(array('ok' => ! $failed), $state));
      }

      /** Saves the project's runtime and isolation from the launcher. */
      public function projectWorkspaceRuntimeSave() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Use POST to change a project runtime.'), 405);
          return;
        }
        if (! $this->workspaceRuntimesEnabled()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Workspace runtimes need the Docker job runtime, which this deployment does not run.'), 404);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        $service = $this->workspaceRuntimeService();
        $clean = $service->cleanProjectSettings(array(
          'runtime' => $this->input->post('runtime'),
          'isolation' => $this->input->post('isolation'),
          'cpus' => $this->input->post('cpus'),
          'memory_mb' => $this->input->post('memory_mb')
        ), $this->vendorId);
        if (! $clean['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $clean['message']), 400);
          return;
        }
        $previous = $service->projectSettings($project['id']);
        $service->model()->saveProjectSettings($project['id'], $clean['settings'], $this->vendorId);
        log_message('info', 'User '.(int) $this->vendorId.' set project '.(int) $project['id'].' to the '.$clean['settings']['runtimeKey'].' runtime ('.$clean['settings']['isolation'].').');
        $label = $this->projectRuntimeLabel($clean['settings']['runtimeKey']);
        $note = $previous['runtimeKey'] !== $clean['settings']['runtimeKey'] && ! $this->projectRuntimeUsesDefault($clean['settings'])
          ? ' It is built on the next open if it has no image yet.' : '';
        $this->jsonJobCreationResponse(array('ok' => TRUE, 'settings' => $clean['settings'],
          'message' => $project['name'].' now opens in '.$label.($clean['settings']['isolation'] === 'shared' && ! $this->projectRuntimeUsesDefault($clean['settings']) ? ', shared by the team' : '').'.'.$note));
      }

      /**
       * Adds a starting .devcontainer to the viewer's workspace of a project;
       * existing files are never replaced. The person commits it.
       */
      public function projectWorkspaceDevcontainerStarter() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs() || strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project is inactive or no longer exists.'), 404);
          return;
        }
        $location = $this->projectWorkspaceLocation($project);
        if (! is_dir($location['absolute'])) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Open the project once first: the dev container is added to your workspace of it.'), 409);
          return;
        }
        $service = $this->workspaceRuntimeService();
        if ($service->spec()->findDevcontainer($location['absolute']) !== '') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'This project already has a dev container.'), 409);
          return;
        }
        // The starter, or any template or catalog runtime as the project's own.
        $source = trim((string) $this->input->post('source'));
        $files = $service->spec()->devcontainerStarter($project['name'], $this->defaultDockerPythonVersion());
        if (strpos($source, 'template:') === 0 || strpos($source, 'runtime:') === 0) {
          $runtime = strpos($source, 'template:') === 0 ? $service->spec()->templateRuntime(substr($source, 9)) : $service->model()->runtime(substr($source, 8));
          if ($runtime === FALSE) {
            $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That runtime or template does not exist.'), 404);
            return;
          }
          $runtime['name'] = $project['name'];
          $files = $service->spec()->devcontainerFiles($runtime);
        }
        $written = $this->writeMissingProjectFiles($location['absolute'], $files);
        log_message('info', 'User '.(int) $this->vendorId.' added a starter dev container to project '.(int) $project['id'].'.');
        $this->jsonJobCreationResponse(array('ok' => ! empty($written), 'written' => $written,
          'message' => empty($written) ? 'No file was written.' : 'Added '.implode(', ', $written).' to '.($location['hasGit']
            ? 'your working copy. Edit it, commit and push it so the team gets the same environment.'
            : 'the project folder. Edit it to fit the project.')));
      }

      /**
       * Builds the project's runtime again from the same recipe, for newer
       * base images and packages. Editors move to it at their next open.
       */
      public function projectWorkspaceRuntimeRebuild() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs() || strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE || ! $this->workspaceRuntimesEnabled()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project has no runtime to build.'), 404);
          return;
        }
        $settings = $this->projectRuntimeSettings($project);
        if ($this->projectRuntimeUsesDefault($settings)) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The Default editor is not built per project.'), 409);
          return;
        }
        $plan = $this->projectRuntimePlan($project, $this->projectWorkspaceLocation($project), $settings);
        if (! $plan['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $plan['message']), 409);
          return;
        }
        $started = $this->workspaceRuntimeService()->startBuild($plan, $this->vendorId, TRUE);
        if ($started['started']) {
          $started['message'] = 'Rebuilding '.$plan['label'].'. Open the project when it is done: its editor moves to the new image then.';
        }
        $this->jsonJobCreationResponse($started, $started['ok'] ? 200 : 503);
      }

      /** Stops the viewer's deployment of a project (or the shared one). */
      public function projectWorkspaceRuntimeStop() {
        $this->releaseSessionLock();
        if (! $this->canManageJobs() || strtoupper((string) $this->input->method(TRUE)) !== 'POST') {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
          return;
        }
        $project = $this->projectRuntimeRequestProject();
        if ($project === FALSE || ! $this->workspaceRuntimesEnabled()) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project has no runtime editor.'), 404);
          return;
        }
        $settings = $this->projectRuntimeSettings($project);
        $instance = $this->workspaceRuntimeService()->model()->instance($project['id'], $settings['isolation'] === 'shared' ? 0 : (int) $this->vendorId);
        if ($instance === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'That project has no running editor of yours.'), 404);
          return;
        }
        $result = $this->workspaceRuntimeService()->stop($instance);
        $this->jsonJobCreationResponse($result, $result['ok'] ? 200 : 502);
      }
}

<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Builds workspace runtimes and deploys them as project editors on the job
 * runtime's Docker Engine (doc/jobseeker/Architecture/workspace-runtimes.md).
 *
 * A plan describes one runtime build: the files of its image, the IDE layer
 * on top, and the content hashes that tag both. Plans come from the catalog
 * (planFor) or from a project's devcontainer.json (devcontainerPlan). Builds
 * run in the background (DockerEngine::startBuild) and are reconciled from
 * their log whenever their state is read. deploy() then creates, recreates or
 * starts a project's editor container from a ready plan.
 */
class WorkspaceRuntimeService
{
    const BUILD_TIMEOUT_SECONDS = 7200;

    private $CI;
    private $runtime;
    private $docker;
    private $model;
    private $toolkitId = NULL;
    private $repositoryRoot = '';

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('WorkspaceRuntime');
        $this->CI->load->library('DockerEngine');
        $this->CI->load->model('WorkspaceRuntime_model');
        $this->runtime = $this->CI->workspaceruntime;
        $this->docker = $this->CI->dockerengine;
        $this->model = $this->CI->WorkspaceRuntime_model;
        $this->model->seed($this->runtime->presets());
    }

    public function spec()
    {
        return $this->runtime;
    }

    public function model()
    {
        return $this->model;
    }

    public function docker()
    {
        return $this->docker;
    }

    public function enabled()
    {
        $value = strtolower(trim((string) getenv('JOBSEEKER_WORKSPACE_RUNTIMES_ENABLED')));
        return ! in_array($value, array('0', 'false', 'no', 'off'), TRUE) && $this->docker->configured();
    }

    /** Whether runtimes can be built and deployed right now, and why not. */
    public function engineState()
    {
        if (! $this->enabled()) {
            return array('available' => FALSE, 'toolkit' => FALSE, 'message' => 'Workspace runtimes need the Docker job runtime, which this deployment does not run. Projects use the Default editor.');
        }
        if (! $this->docker->available()) {
            return array('available' => FALSE, 'toolkit' => FALSE, 'message' => 'The Docker job runtime is not reachable, so runtimes cannot be built or deployed right now.');
        }
        $toolkit = $this->toolkitId();
        return array(
            'available' => TRUE,
            'toolkit' => $toolkit !== '',
            'message' => $toolkit !== '' ? '' : 'The workspace toolkit is not installed in the job runtime yet. Run: docker compose up -d workspace-toolkit'
        );
    }

    /** Short id of the toolkit image; part of every IDE image's hash. */
    public function toolkitId()
    {
        if ($this->toolkitId === NULL) {
            $image = $this->docker->inspectImage(WorkspaceRuntime::TOOLKIT_IMAGE);
            $this->toolkitId = is_array($image) && isset($image['Id']) ? substr(preg_replace('/^sha256:/', '', (string) $image['Id']), 0, 12) : '';
        }
        return $this->toolkitId;
    }

    /** Owner of the checkout: editors run as it, like JobSeeker's services. */
    public function owner()
    {
        $root = $this->repositoryRoot();
        $uid = @fileowner($root);
        $gid = @filegroup($root);
        return array(
            'uid' => $uid === FALSE || (int) $uid === 0 ? 1000 : (int) $uid,
            'gid' => $gid === FALSE || (int) $gid === 0 ? 1000 : (int) $gid
        );
    }

    /** The repository as PHP sees it; controllers set it from their runtime config. */
    public function useRepositoryRoot($path)
    {
        $this->repositoryRoot = rtrim((string) $path, '/\\');
    }

    public function repositoryRoot()
    {
        return $this->repositoryRoot !== '' ? $this->repositoryRoot : FCPATH.'repository';
    }

    /** Where the job runtime mounts ./repository. */
    public function engineRepositoryRoot()
    {
        $path = rtrim(trim((string) getenv('JOBSEEKER_WORKSPACE_RUNTIME_REPOSITORY')), '/');
        return $path === '' ? '/php/repository' : $path;
    }

    public function idleMinutes()
    {
        $value = trim((string) getenv('JOBSEEKER_OPENVSCODE_IDLE_TIMEOUT_MINUTES'));
        return preg_match('/^[0-9]{1,5}$/', $value) ? min(1440, (int) $value) : 30;
    }

    public function defaultResources()
    {
        $cpus = (float) getenv('JOBSEEKER_WORKSPACE_DEFAULT_CPUS');
        $memory = (int) getenv('JOBSEEKER_WORKSPACE_DEFAULT_MEMORY_MB');
        return array(
            'cpus' => $cpus >= 0.5 && $cpus <= 64 ? round($cpus, 2) : 2.0,
            'memoryMb' => $memory >= 512 && $memory <= 262144 ? $memory : 4096
        );
    }

    /** The project's runtime choice, with defaults for a project that never chose. */
    public function projectSettings($projectId)
    {
        $settings = $this->model->projectSettings($projectId);
        if ($settings === FALSE) {
            $settings = array_merge(array('runtimeKey' => WorkspaceRuntime::DEFAULT_KEY, 'isolation' => 'user'), $this->defaultResources());
        }
        return $settings;
    }

    /**
     * The catalog runtime of a template, added to the catalog the first time
     * a project chooses the template.
     *
     * @return string|FALSE its key
     */
    public function runtimeForTemplate($templateKey, $userId)
    {
        $draft = $this->runtime->templateRuntime($templateKey);
        if ($draft === FALSE) {
            return FALSE;
        }
        foreach ($this->model->runtimes(TRUE) as $runtime) {
            if ((isset($runtime['spec']['template']) && $runtime['spec']['template'] === $templateKey) || $runtime['key'] === $templateKey) {
                return $runtime['key'];
            }
        }
        $key = $draft['key'];
        for ($suffix = 2; $this->model->runtime($key) !== FALSE; $suffix++) {
            $key = $draft['key'].'-'.$suffix;
        }
        $clean = $this->runtime->cleanRuntime(array_merge(array('key' => $key, 'name' => $draft['name'], 'description' => $draft['description'], 'kind' => $draft['kind']), $draft['spec']));
        if (! $clean['ok'] || ! $this->model->saveRuntime($clean['runtime'], $userId)) {
            return FALSE;
        }
        log_message('info', 'User '.(int) $userId.' added the '.$templateKey.' template to the workspace runtime catalog as '.$key.'.');
        return $key;
    }

    /**
     * Validates a project's choice. "template:<key>" adds that template to
     * the catalog and chooses it.
     *
     * @return array ok, message, settings
     */
    public function cleanProjectSettings(array $input, $userId = 0)
    {
        $key = strtolower(trim((string) (isset($input['runtime']) ? $input['runtime'] : '')));
        if (strpos($key, 'template:') === 0) {
            $key = $this->runtimeForTemplate(substr($key, strlen('template:')), $userId);
            if ($key === FALSE) {
                return array('ok' => FALSE, 'message' => 'That template does not exist.');
            }
        }
        if ($key !== WorkspaceRuntime::DEFAULT_KEY && $key !== WorkspaceRuntime::DEVCONTAINER_KEY) {
            $runtime = $this->runtime->cleanKey($key) === FALSE ? FALSE : $this->model->runtime($key);
            if ($runtime === FALSE || ! $runtime['active']) {
                return array('ok' => FALSE, 'message' => 'Choose a runtime from the catalog.');
            }
        }
        $isolation = isset($input['isolation']) && $input['isolation'] === 'shared' ? 'shared' : 'user';
        $defaults = $this->defaultResources();
        $cpus = isset($input['cpus']) && trim((string) $input['cpus']) !== '' ? (float) $input['cpus'] : $defaults['cpus'];
        $memory = isset($input['memory_mb']) && trim((string) $input['memory_mb']) !== '' ? (int) $input['memory_mb'] : $defaults['memoryMb'];
        if ($cpus < 0.5 || $cpus > 64) {
            return array('ok' => FALSE, 'message' => 'Give the editor between 0.5 and 64 CPUs.');
        }
        if ($memory < 512 || $memory > 262144) {
            return array('ok' => FALSE, 'message' => 'Give the editor between 512 MB and 256 GB of memory.');
        }
        return array('ok' => TRUE, 'message' => '', 'settings' => array('runtimeKey' => $key, 'isolation' => $isolation, 'cpus' => round($cpus, 2), 'memoryMb' => $memory));
    }

    /** The runtime image a catalog runtime builds, without the IDE layer. */
    public function runtimeImageOf(array $runtime)
    {
        $files = $this->runtime->buildFiles($runtime['key'], $runtime['kind'], $runtime['spec']);
        return $this->runtime->runtimeImage($runtime['key'], $this->runtimeHash($files, 'Dockerfile', array(), ''));
    }

    /** The tag of a runtime image: its build context and how it is built. */
    private function runtimeHash(array $files, $dockerfile, array $buildArgs, $target)
    {
        return $this->runtime->hash($files, array($dockerfile, json_encode($buildArgs), $target));
    }

    /**
     * The build plan of a catalog runtime.
     *
     * @return array ok, message, and the plan fields (see finishPlan)
     */
    public function planFor(array $runtime)
    {
        // A catalog runtime is a dev container too: its extensions and
        // features go into the editor image, its environment and post-create
        // command into the editor container.
        $spec = $runtime['spec'];
        return $this->finishPlan(array(
            'label' => $runtime['name'],
            'runtimeKey' => $runtime['key'],
            'imageKey' => $runtime['key'],
            'files' => $this->runtime->buildFiles($runtime['key'], $runtime['kind'], $spec),
            'dockerfile' => 'Dockerfile',
            'buildArgs' => array(),
            'target' => '',
            'extensions' => isset($spec['extensions']) ? array_values((array) $spec['extensions']) : array(),
            'env' => isset($spec['env']) ? (array) $spec['env'] : array(),
            'settings' => NULL,
            'postCreate' => isset($spec['post_create']) ? (string) $spec['post_create'] : '',
            'postStart' => '',
            'features' => $this->runtime->buildFeatures(isset($spec['features']) ? (array) $spec['features'] : array()),
            'warnings' => $this->runtime->runtimeWarnings($runtime['kind'], $spec)
        ));
    }

    /** The build plan of a project's .devcontainer, read from $projectRoot. */
    public function devcontainerPlan($projectId, $projectRoot)
    {
        $definitionPath = $this->runtime->findDevcontainer($projectRoot);
        if ($definitionPath === '') {
            return array('ok' => FALSE, 'message' => 'This project has no .devcontainer/devcontainer.json yet. Add one to the project, or choose a runtime from the catalog.');
        }
        $definition = $this->runtime->devcontainer(file_get_contents(rtrim($projectRoot, '/').'/'.$definitionPath), $definitionPath);
        if (! $definition['ok']) {
            return array('ok' => FALSE, 'message' => implode(' ', $definition['errors']));
        }
        $context = $this->runtime->devcontainerContext($projectRoot, $definition);
        if (! $context['ok']) {
            return array('ok' => FALSE, 'message' => $context['message']);
        }
        return $this->finishPlan(array(
            'label' => 'Dev container ('.$definitionPath.')',
            'runtimeKey' => WorkspaceRuntime::DEVCONTAINER_KEY,
            'imageKey' => 'p'.(int) $projectId,
            'files' => $context['files'],
            'dockerfile' => $context['dockerfile'],
            'buildArgs' => $definition['args'],
            'target' => $definition['target'],
            'extensions' => $definition['extensions'],
            'env' => $definition['env'],
            'settings' => $definition['settings'],
            'postCreate' => $definition['postCreate'],
            'postStart' => $definition['postStart'],
            'features' => $definition['features'],
            'warnings' => $definition['warnings']
        ));
    }

    private function finishPlan(array $plan)
    {
        $owner = $this->owner();
        $plan['runtimeHash'] = $this->runtimeHash($plan['files'], $plan['dockerfile'], $plan['buildArgs'], $plan['target']);
        $plan['runtimeImage'] = $this->runtime->runtimeImage($plan['imageKey'], $plan['runtimeHash']);
        $plan['ideDockerfile'] = $this->runtime->ideDockerfile($plan['runtimeImage'], ! empty($plan['features']));
        // The editor image's build context: its Dockerfile, and the features to fetch.
        $plan['ideFiles'] = array('Dockerfile' => $plan['ideDockerfile']);
        if (! empty($plan['features'])) {
            $plan['ideFiles']['features.json'] = json_encode($plan['features'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
        }
        $plan['buildHash'] = $this->runtime->hash($plan['ideFiles'], array(
            $plan['runtimeHash'], $this->toolkitId(), $owner['uid'].':'.$owner['gid'], implode(' ', $plan['extensions'])
        ));
        $plan['ideImage'] = $this->runtime->ideImage($plan['imageKey'], $plan['buildHash']);
        $plan['ok'] = TRUE;
        $plan['message'] = '';
        return $plan;
    }

    private function buildDirectory($hash)
    {
        return APPPATH.'cache'.DIRECTORY_SEPARATOR.'workspace_runtime_builds'.DIRECTORY_SEPARATOR.preg_replace('/[^0-9a-f]/', '', (string) $hash);
    }

    /**
     * Where a plan's build stands: none, building, ready, failed or missing.
     * A finished background build is recorded here, when it is first read.
     */
    public function buildState(array $plan)
    {
        $build = $this->model->build($plan['buildHash']);
        if ($build === FALSE) {
            return array('status' => 'none', 'message' => '', 'startedAt' => '', 'finishedAt' => '');
        }
        if ($build['status'] === 'building') {
            $directory = $this->buildDirectory($plan['buildHash']);
            $exitFile = $directory.DIRECTORY_SEPARATOR.'exit';
            $pidFile = $directory.DIRECTORY_SEPARATOR.'pid';
            if (is_file($exitFile)) {
                $parsed = $this->runtime->parseBuildStream((string) @file_get_contents($directory.DIRECTORY_SEPARATOR.'build.log'));
                $image = trim((string) file_get_contents($exitFile)) === '0' ? $this->docker->inspectImage($build['ide_image']) : NULL;
                if (is_array($image)) {
                    $this->model->finishBuild($plan['buildHash'], 'ready', '');
                } else {
                    $this->model->finishBuild($plan['buildHash'], 'failed', $parsed['error'] !== '' ? $parsed['error'] : 'The build did not produce '.$build['ide_image'].'.');
                }
            } else if ((is_file($pidFile) && ! is_dir('/proc/'.(int) trim((string) file_get_contents($pidFile))))
                || strtotime($build['started_at'].' UTC') < time() - self::BUILD_TIMEOUT_SECONDS) {
                $this->model->finishBuild($plan['buildHash'], 'failed', 'The build was interrupted (JobSeeker restarted or it ran too long). Build again.');
            }
            $build = $this->model->build($plan['buildHash']);
        } else if ($build['status'] === 'ready' && $this->docker->inspectImage($build['ide_image']) === NULL) {
            $this->model->markBuildMissing($plan['buildHash']);
            $build = $this->model->build($plan['buildHash']);
        }
        return array(
            'status' => $build['status'],
            'message' => (string) $build['message'],
            'startedAt' => (string) $build['started_at'],
            'finishedAt' => (string) $build['finished_at']
        );
    }

    /**
     * Starts a plan's build unless it is already building or built.
     *
     * @return array ok, message, started
     */
    public function startBuild(array $plan, $userId, $force = FALSE)
    {
        $engine = $this->engineState();
        if (! $engine['available'] || ! $engine['toolkit']) {
            return array('ok' => FALSE, 'message' => $engine['message'], 'started' => FALSE);
        }
        $state = $this->buildState($plan);
        if ($state['status'] === 'building') {
            return array('ok' => TRUE, 'message' => 'The runtime is already building.', 'started' => FALSE);
        }
        if ($state['status'] === 'ready' && ! $force) {
            return array('ok' => TRUE, 'message' => 'The runtime is already built.', 'started' => FALSE);
        }
        if ($state['status'] === 'ready') {
            // Same recipe, rebuilt on request: picks up newer base images.
            $this->model->releaseBuild($plan['buildHash']);
        }
        if (! $this->model->claimBuild($plan['buildHash'], $plan['imageKey'], $plan['runtimeImage'], $plan['ideImage'], $userId)) {
            return array('ok' => TRUE, 'message' => 'The runtime is already building.', 'started' => FALSE);
        }
        $directory = $this->buildDirectory($plan['buildHash']);
        $fail = function($message) use ($plan) {
            $this->model->finishBuild($plan['buildHash'], 'failed', $message);
            return array('ok' => FALSE, 'message' => $message, 'started' => FALSE);
        };
        if (! is_dir($directory) && ! @mkdir($directory, 0770, TRUE)) {
            return $fail('JobSeeker could not create its build folder.');
        }
        foreach (array('exit', 'pid', 'build.log') as $stale) {
            @unlink($directory.DIRECTORY_SEPARATOR.$stale);
        }
        $owner = $this->owner();
        $runtimeTar = $directory.DIRECTORY_SEPARATOR.'runtime.tar';
        $ideTar = $directory.DIRECTORY_SEPARATOR.'ide.tar';
        if (! $this->runtime->writeTar($plan['files'], $runtimeTar) || ! $this->runtime->writeTar($plan['ideFiles'], $ideTar)) {
            return $fail('JobSeeker could not write the build context.');
        }
        $runtimeQuery = array('t' => $plan['runtimeImage'], 'dockerfile' => $plan['dockerfile'], 'rm' => 1, 'forcerm' => 1,
            'labels' => json_encode(array('org.jobseeker.runtime' => $plan['imageKey'])));
        if (! empty($plan['buildArgs'])) {
            $runtimeQuery['buildargs'] = json_encode($plan['buildArgs']);
        }
        if ($plan['target'] !== '') {
            $runtimeQuery['target'] = $plan['target'];
        }
        if ($force) {
            // A rebuild exists for newer base images and packages: pull the
            // bases and rerun every step. The editor step builds on the local
            // runtime image, which no registry has, so it never pulls.
            $runtimeQuery['pull'] = 1;
            $runtimeQuery['nocache'] = 1;
        }
        $ideQuery = array('t' => $plan['ideImage'], 'dockerfile' => 'Dockerfile', 'rm' => 1, 'forcerm' => 1,
            'labels' => json_encode(array('org.jobseeker.runtime' => $plan['imageKey'], 'org.jobseeker.workspace-ide' => '1')),
            'buildargs' => json_encode(array(
                'JOBSEEKER_UID' => (string) $owner['uid'],
                'JOBSEEKER_GID' => (string) $owner['gid'],
                'JOBSEEKER_IDE_EXTENSIONS' => implode(' ', $plan['extensions'])
            )));
        $started = $this->docker->startBuild(array(
            array('title' => 'Runtime image '.$plan['runtimeImage'], 'tar' => $runtimeTar, 'query' => $runtimeQuery),
            array('title' => 'Editor image '.$plan['ideImage'], 'tar' => $ideTar, 'query' => $ideQuery)
        ), $directory);
        if (! $started) {
            return $fail('JobSeeker could not start the build.');
        }
        log_message('info', 'User '.(int) $userId.' started the workspace runtime build '.$plan['ideImage'].'.');
        return array('ok' => TRUE, 'message' => 'Building '.$plan['label'].'. This takes a few minutes the first time.', 'started' => TRUE);
    }

    /** Readable build log lines, newest last. */
    public function buildLog($hash, $maxLines = 400)
    {
        $directory = $this->buildDirectory($hash).DIRECTORY_SEPARATOR;
        $stream = is_file($directory.'build.log') ? (string) file_get_contents($directory.'build.log') : '';
        // The step in progress, which build.log gets when it finishes.
        if (! is_file($directory.'exit') && is_file($directory.'step.log')) {
            $stream .= "\n".(string) file_get_contents($directory.'step.log');
        }
        $parsed = $this->runtime->parseBuildStream($stream);
        $lines = $parsed['lines'];
        if (count($lines) > $maxLines) {
            $lines = array_merge(array('... '.(count($lines) - $maxLines).' earlier lines'), array_slice($lines, -$maxLines));
        }
        return $lines;
    }

    /** Built runtime images Docker jobs can use, newest first. */
    public function jobImages()
    {
        $ready = array();
        foreach ($this->model->readyBuilds() as $build) {
            $ready[$build['runtime_image']] = TRUE;
        }
        $images = array();
        foreach ($this->model->runtimes(TRUE) as $runtime) {
            $image = $this->runtimeImageOf($runtime);
            if (isset($ready[$image])) {
                $images[] = array('image' => $image, 'key' => $runtime['key'], 'name' => $runtime['name'], 'kind' => $runtime['kind']);
            }
        }
        return $images;
    }

    public function token(array $instance)
    {
        $key = trim((string) getenv('JOBSEEKER_ENCRYPTION_KEY'));
        if ($key === '') {
            $key = (string) $this->CI->config->item('encryption_key');
        }
        return substr(hash_hmac('sha256', 'jobseeker-ide|'.$instance['container_name'].'|'.$instance['token_salt'], $key), 0, 48);
    }

    /** The workspace gateway as the browser reaches it. */
    public function gatewayUrl()
    {
        $publicUrl = trim((string) getenv('JOBSEEKER_WORKSPACE_GATEWAY_PUBLIC_URL'));
        if ($publicUrl !== '') {
            return rtrim($publicUrl, '/');
        }
        $port = trim((string) getenv('JOBSEEKER_WORKSPACE_GATEWAY_PORT'));
        $port = preg_match('/^[0-9]{1,5}$/', $port) ? $port : '3001';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
            $protocol = in_array($forwarded, array('http', 'https'), TRUE) ? $forwarded : $protocol;
        }
        // The gateway has a certificate of its own, whatever JobSeeker uses.
        if (in_array(strtolower(trim((string) getenv('JOBSEEKER_WORKSPACE_GATEWAY_TLS'))), array('1', 'true', 'yes', 'on'), TRUE)) {
            $protocol = 'https';
        }
        $host = ! empty($_SERVER['HTTP_X_FORWARDED_HOST']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0])
            : (isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : 'localhost');
        $hostName = strpos($host, '[') === 0 && strpos($host, ']') !== FALSE ? substr($host, 0, strpos($host, ']') + 1) : explode(':', $host, 2)[0];
        if ($hostName === '' || ! preg_match('/^(\[[0-9a-fA-F:.]+\]|[A-Za-z0-9.-]+)$/', $hostName)) {
            $hostName = 'localhost';
        }
        return $protocol.'://'.$hostName.':'.$port;
    }

    /**
     * Whether browsers treat the gateway as a secure context: HTTPS, or a
     * loopback name. Anywhere else they refuse service workers, and VS Code's
     * notebooks and webviews stay blank.
     */
    public function gatewaySecure()
    {
        $url = $this->gatewayUrl();
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            || in_array($host, array('localhost', '127.0.0.1', '::1'), TRUE)
            || substr($host, -10) === '.localhost';
    }

    /** The launch URL of a deployment, opening $folder (repository-relative). */
    public function launchUrl(array $instance, $folder)
    {
        return $this->gatewayUrl().$this->runtime->basePath($instance['port']).'/?'.http_build_query(array(
            'tkn' => $this->token($instance),
            'folder' => WorkspaceRuntime::WORKSPACE_REPOSITORY.'/'.ltrim((string) $folder, '/')
        ), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Brings a project's editor up on a ready plan: creates its container, or
     * recreates it when its image, mounts or settings changed, and starts it.
     *
     * Request: projectId, ownerId (0 for Shared), openerId, plan, runtimeKey,
     * mounts (repository-relative paths => read-only flag), folder
     * (repository-relative, where dev container hooks run), cpus, memoryMb,
     * retryCrashed (start an editor that just crashed again; only an
     * explicit open does, polling never).
     *
     * @return array ok, status (starting|ready), message, instance, recreated
     */
    public function deploy(array $request)
    {
        $fail = function($message) {
            return array('ok' => FALSE, 'status' => 'failed', 'message' => $message);
        };
        $plan = $request['plan'];
        list($start, $end) = $this->runtime->portRange(getenv('JOBSEEKER_WORKSPACE_IDE_PORTS'));
        $name = $this->runtime->containerName($request['projectId'], $request['ownerId']);
        $instance = $this->model->instance($request['projectId'], $request['ownerId']);
        if ($instance === FALSE) {
            $instance = $this->model->createInstance($request['projectId'], $request['ownerId'], $name, $request['runtimeKey'], $start, $end);
            if ($instance === FALSE) {
                return $fail('Every editor port ('.$start.'-'.$end.') is taken. Remove unused deployments on the Runtimes page.');
            }
        }
        $owner = $this->owner();
        $engineRoot = $this->engineRepositoryRoot();
        $mounts = array();
        foreach ($request['mounts'] as $path => $readOnly) {
            $path = trim(str_replace('\\', '/', (string) $path), '/');
            $mounts[] = array('source' => $engineRoot.'/'.$path, 'target' => WorkspaceRuntime::WORKSPACE_REPOSITORY.'/'.$path, 'readOnly' => (bool) $readOnly);
        }
        $labels = array(
            'com.jobseeker.workspace-ide' => '1',
            'com.jobseeker.project' => (string) (int) $request['projectId'],
            'com.jobseeker.user' => (string) (int) $request['ownerId'],
            'com.jobseeker.runtime' => (string) $request['runtimeKey'],
            'com.jobseeker.port' => (string) (int) $instance['port']
        );
        $options = array(
            'image' => $plan['ideImage'],
            'runtime' => $plan['imageKey'],
            'port' => (int) $instance['port'],
            'token' => $this->token($instance),
            'uid' => $owner['uid'],
            'gid' => $owner['gid'],
            'cpus' => $request['cpus'],
            'memoryMb' => $request['memoryMb'],
            'idleMinutes' => $this->idleMinutes(),
            'folder' => WorkspaceRuntime::WORKSPACE_REPOSITORY.'/'.trim((string) $request['folder'], '/'),
            'mounts' => $mounts,
            'homeVolume' => $this->runtime->homeVolume($request['projectId'], $request['ownerId']),
            'env' => $plan['env'],
            'labels' => $labels,
            'spec' => '',
            'postCreate' => $plan['postCreate'],
            'postStart' => $plan['postStart'],
            'settings' => $plan['settings']
        );
        // A rebuild keeps the tag (same recipe) but not the image: the image id
        // is part of the spec, so the next open moves to the rebuilt image.
        $image = $this->docker->inspectImage($plan['ideImage']);
        if (! is_array($image)) {
            return $fail('The editor image '.$plan['ideImage'].' is missing. Build the runtime again.');
        }
        $specHash = substr(hash('sha256', json_encode($this->runtime->containerConfig($options)).'|'.$image['Id']), 0, 12);
        $options['spec'] = $specHash;
        $options['labels']['com.jobseeker.spec'] = $specHash;

        $container = $this->docker->inspectContainer($name);
        if ($container === FALSE) {
            return $fail('The Docker job runtime is not reachable.');
        }
        $recreated = FALSE;
        if (is_array($container) && (! isset($container['Config']['Labels']['com.jobseeker.spec']) || $container['Config']['Labels']['com.jobseeker.spec'] !== $specHash)) {
            $removed = $this->docker->removeContainer($name);
            if (! in_array($removed['status'], array(204, 404), TRUE)) {
                return $fail($this->docker->message($removed, 'The previous editor container could not be replaced.'));
            }
            $container = NULL;
            $recreated = TRUE;
        }
        if ($container === NULL) {
            // Docker seeds a home volume once, with the owner of that time;
            // make it the checkout owner's before every new container.
            $home = $this->docker->runOnce($plan['ideImage'], array('chown', '-R', $owner['uid'].':'.$owner['gid'], WorkspaceRuntime::WORKSPACE_HOME),
                array(array('Type' => 'volume', 'Source' => $options['homeVolume'], 'Target' => WorkspaceRuntime::WORKSPACE_HOME)),
                array('CHOWN', 'FOWNER', 'DAC_OVERRIDE'));
            if ($home !== 0) {
                return $fail('The editor\'s home volume '.$options['homeVolume'].' could not be prepared.');
            }
            $created = $this->docker->createContainer($name, $this->runtime->containerConfig($options));
            if ($created['status'] === 409) {
                $container = $this->docker->inspectContainer($name);
            } else if ($created['status'] !== 201) {
                return $fail($this->docker->message($created, 'The editor container could not be created.'));
            }
            $container = $this->docker->inspectContainer($name);
        }
        if (is_array($container) && empty($container['State']['Running'])) {
            if ($this->crashedRecently($container) && empty($request['retryCrashed'])) {
                $logs = trim($this->docker->containerLogs($name, 15));
                return $fail('The editor exits right after it starts (exit code '.(int) $container['State']['ExitCode'].')'.($logs !== '' ? ': '.substr($logs, -1500) : '.'));
            }
            $started = $this->docker->startContainer($name);
            if (! in_array($started['status'], array(204, 304), TRUE)) {
                return $fail($this->docker->message($started, 'The editor container could not be started.'));
            }
        }
        $this->model->updateInstance($instance['id'], array(
            'runtime_key' => $request['runtimeKey'],
            'image' => $plan['ideImage'],
            'spec_hash' => $specHash,
            'last_opened_at' => gmdate('Y-m-d H:i:s'),
            'last_opened_by' => (int) $request['openerId']
        ));
        $instance = $this->model->instance($request['projectId'], $request['ownerId']);
        return array(
            'ok' => TRUE,
            'status' => $this->editorResponds($instance) ? 'ready' : 'starting',
            'message' => '',
            'instance' => $instance,
            'recreated' => $recreated
        );
    }

    /** True once the editor answers HTTP; any answer, even 403, means it is up. */
    public function editorResponds(array $instance)
    {
        $host = parse_url($this->docker->baseUrl(), PHP_URL_HOST);
        if (! $host || ! function_exists('curl_init')) {
            return FALSE;
        }
        $handle = curl_init('http://'.$host.':'.(int) $instance['port'].$this->runtime->basePath($instance['port']).'/');
        curl_setopt_array($handle, array(CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_NOBODY => TRUE, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3));
        curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return $status > 0;
    }

    /** An editor that failed within the last two minutes, not one stopped for idleness. */
    private function crashedRecently(array $container)
    {
        $finished = isset($container['State']['FinishedAt']) ? strtotime(preg_replace('/\.[0-9]+Z$/', 'Z', (string) $container['State']['FinishedAt'])) : FALSE;
        return (int) $container['State']['ExitCode'] !== 0 && $finished !== FALSE && $finished > time() - 120;
    }

    /**
     * A deployment's state for the launcher: running, ready, or stopped with
     * the last lines it printed.
     */
    public function deploymentState(array $instance)
    {
        $container = $this->docker->inspectContainer($instance['container_name']);
        if (! is_array($container)) {
            return array('state' => $container === NULL ? 'removed' : 'unknown', 'ready' => FALSE, 'message' => '');
        }
        $running = ! empty($container['State']['Running']);
        $ready = $running && $this->editorResponds($instance);
        $message = '';
        if (! $running) {
            $logs = trim($this->docker->containerLogs($instance['container_name'], 15));
            $message = 'The editor stopped (exit code '.(int) $container['State']['ExitCode'].')'.($logs !== '' ? ': '.substr($logs, -1500) : '.');
        }
        return array('state' => $running ? ($ready ? 'ready' : 'starting') : 'stopped', 'ready' => $ready, 'message' => $message,
            'crashed' => ! $running && $this->crashedRecently($container));
    }

    /** Every deployment, with its container's state. */
    public function deployments()
    {
        $containers = array();
        foreach ((array) $this->docker->containers(array('com.jobseeker.workspace-ide' => '1')) as $container) {
            foreach ((array) $container['Names'] as $name) {
                $containers[ltrim($name, '/')] = $container;
            }
        }
        $rows = array();
        foreach ($this->model->instances() as $instance) {
            $container = isset($containers[$instance['container_name']]) ? $containers[$instance['container_name']] : NULL;
            $rows[] = array(
                'id' => (int) $instance['id'],
                'projectId' => (int) $instance['project_id'],
                'projectName' => (string) $instance['project_name'],
                'userId' => (int) $instance['user_id'],
                'userName' => (int) $instance['user_id'] > 0 ? (string) $instance['user_name'] : '',
                'shared' => (int) $instance['user_id'] === 0,
                'runtimeKey' => (string) $instance['runtime_key'],
                'image' => (string) $instance['image'],
                'container' => (string) $instance['container_name'],
                'port' => (int) $instance['port'],
                'state' => $container === NULL ? 'removed' : (string) $container['State'],
                'status' => $container === NULL ? 'No container' : (string) $container['Status'],
                'lastOpenedAt' => (string) $instance['last_opened_at'],
                'lastOpenedBy' => (string) $instance['opened_by_name']
            );
        }
        return $rows;
    }

    public function stop(array $instance)
    {
        $result = $this->docker->stopContainer($instance['container_name']);
        return in_array($result['status'], array(204, 304, 404), TRUE)
            ? array('ok' => TRUE, 'message' => 'The editor was stopped. It starts again the next time the project is opened.')
            : array('ok' => FALSE, 'message' => $this->docker->message($result, 'The editor could not be stopped.'));
    }

    /**
     * Runtime images nothing needs, for the Runtimes page to reclaim:
     *   editor   an IDE image of no catalog runtime's current build and of no
     *            container; jobs never use these, so they are safe to remove
     *   runtime  a runtime image of no current catalog runtime, no container
     *            and no saved job ($jobImages). A job whose Dockerfile in a Git
     *            repository starts FROM one cannot be seen, so these are
     *            offered, not preselected. Only listed when every job's
     *            configuration could be read ($jobsComplete).
     * Untagged layers left by rebuilds are pruned with either.
     *
     * @return array|FALSE list of reference, kind, sizeBytes, created
     */
    public function reclaimCandidates(array $jobImages, $jobsComplete)
    {
        // all=1: intermediate layers too, so parent chains are complete.
        $images = $this->docker->request('GET', 'images/json?all=1', NULL, 15);
        $containers = $this->docker->request('GET', 'containers/json?all=1', NULL, 15);
        if ($images['status'] !== 200 || $containers['status'] !== 200 || ! is_array($images['data']) || ! is_array($containers['data'])) {
            return FALSE;
        }
        $keep = array_fill_keys($jobImages, TRUE);
        foreach ($this->model->runtimes() as $runtime) {
            $plan = $this->planFor($runtime);
            $keep[$plan['runtimeImage']] = TRUE;
            $keep[$plan['ideImage']] = TRUE;
        }
        $usedIds = array();
        foreach ($containers['data'] as $container) {
            $keep[(string) $container['Image']] = TRUE;
            $usedIds[(string) $container['ImageID']] = TRUE;
        }
        // An image kept is kept with every image it was built on: a runtime
        // image under an editor image in use, for one.
        $parents = array();
        foreach ($images['data'] as $image) {
            $parents[(string) $image['Id']] = isset($image['ParentId']) ? (string) $image['ParentId'] : '';
            foreach ((array) $image['RepoTags'] as $tag) {
                if (isset($keep[$tag])) {
                    $usedIds[(string) $image['Id']] = TRUE;
                }
            }
        }
        foreach (array_keys($usedIds) as $id) {
            for ($parent = isset($parents[$id]) ? $parents[$id] : ''; $parent !== '' && ! isset($usedIds[$parent]); $parent = isset($parents[$parent]) ? $parents[$parent] : '') {
                $usedIds[$parent] = TRUE;
            }
        }
        $candidates = array();
        foreach ($images['data'] as $image) {
            if (isset($usedIds[(string) $image['Id']])) {
                continue;
            }
            foreach ((array) $image['RepoTags'] as $tag) {
                if (strpos($tag, WorkspaceRuntime::IMAGE_REPOSITORY.'/') !== 0 || isset($keep[$tag])) {
                    continue;
                }
                $editor = substr($tag, -4) === '-ide';
                if (! $editor && ! $jobsComplete) {
                    continue;
                }
                $candidates[] = array('reference' => $tag, 'kind' => $editor ? 'editor' : 'runtime',
                    'sizeBytes' => (int) $image['Size'], 'created' => gmdate('Y-m-d H:i:s', (int) $image['Created']));
            }
        }
        usort($candidates, function($left, $right) {
            return $left['kind'] !== $right['kind'] ? strcmp($left['kind'], $right['kind']) : strcmp($left['reference'], $right['reference']);
        });
        return $candidates;
    }

    /**
     * Removes the chosen candidates (anything else is ignored) and prunes
     * untagged runtime layers.
     *
     * @return array ok, removed (references), spaceBytes (roughly: images share layers), failed
     */
    public function reclaimImages(array $references, array $jobImages, $jobsComplete)
    {
        $candidates = $this->reclaimCandidates($jobImages, $jobsComplete);
        if ($candidates === FALSE) {
            return array('ok' => FALSE, 'message' => 'The Docker job runtime is not reachable.');
        }
        $sizes = array();
        foreach ($candidates as $candidate) {
            $sizes[$candidate['reference']] = $candidate['sizeBytes'];
        }
        $removed = array();
        $failed = array();
        $space = 0;
        foreach (array_unique($references) as $reference) {
            if (! isset($sizes[$reference])) {
                continue;
            }
            $result = $this->docker->request('DELETE', 'images/'.rawurlencode($reference), NULL, 60);
            if ($result['status'] === 200) {
                $removed[] = $reference;
                $space += $sizes[$reference];
            } else {
                $failed[] = $reference.': '.$this->docker->message($result, 'could not be removed');
            }
        }
        $pruned = $this->docker->request('POST', 'images/prune?'.http_build_query(array('filters' => json_encode(array(
            'dangling' => array('true'), 'label' => array('org.jobseeker.runtime'))))), NULL, 120);
        if ($pruned['status'] === 200 && isset($pruned['data']['SpaceReclaimed'])) {
            $space += (int) $pruned['data']['SpaceReclaimed'];
        }
        return array('ok' => empty($failed), 'removed' => $removed, 'failed' => $failed, 'spaceBytes' => $space);
    }

    /** Removes every editor of a deleted project, with their homes. */
    public function removeProjectDeployments($projectId)
    {
        foreach ($this->model->instances() as $instance) {
            if ((int) $instance['project_id'] === (int) $projectId) {
                $this->remove($instance, TRUE);
            }
        }
    }

    /** Removes a deployment; its home (settings, caches) only when asked. */
    public function remove(array $instance, $withHome)
    {
        $result = $this->docker->removeContainer($instance['container_name']);
        if (! in_array($result['status'], array(204, 404), TRUE)) {
            return array('ok' => FALSE, 'message' => $this->docker->message($result, 'The editor could not be removed.'));
        }
        if ($withHome) {
            $this->docker->removeVolume($this->runtime->homeVolume($instance['project_id'], $instance['user_id']));
        }
        $this->model->deleteInstance($instance['id']);
        return array('ok' => TRUE, 'message' => 'The deployment was removed. Project files are untouched.');
    }
}

<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * The Runtimes page: the catalog of workspace runtimes and the editor
 * deployments running from them (doc/jobseeker/Architecture/workspace-runtimes.md).
 * Projects choose a runtime in the VS Code launcher (WorkspaceRuntimeTrait).
 */
class WorkspaceRuntimes extends BaseController
{
    private $service = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
    }

    private function canManage()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function isAdministrator()
    {
        return $this->role == ROLE_ADMIN;
    }

    private function service()
    {
        if ($this->service === NULL) {
            $this->load->library('WorkspaceRuntimeService');
            $this->service = $this->workspaceruntimeservice;
            $home = isset($this->global['jenkins_home']) ? (string) $this->global['jenkins_home'] : '';
            $this->service->useRepositoryRoot($home === '' ? FCPATH.'repository' : rtrim($home, '/\\').DIRECTORY_SEPARATOR.'repository');
        }
        return $this->service;
    }

    private function json($payload, $status = 200)
    {
        $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }

    /** Guards a JSON action; FALSE when the response was already sent. */
    private function allowed($method)
    {
        $this->releaseSessionLock();
        if (! $this->canManage()) {
            $this->json(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return FALSE;
        }
        if (strtoupper((string) $this->input->method(TRUE)) !== $method) {
            $this->json(array('ok' => FALSE, 'message' => 'Use '.$method.' for this action.'), 405);
            return FALSE;
        }
        if (! $this->service()->enabled()) {
            $this->json(array('ok' => FALSE, 'message' => 'Workspace runtimes need the Docker job runtime, which this deployment does not run.'), 404);
            return FALSE;
        }
        return TRUE;
    }

    /** Admins edit every runtime; others the ones they created. */
    private function canEdit(array $runtime)
    {
        return $this->isAdministrator() || ((int) $runtime['createdBy'] > 0 && (int) $runtime['createdBy'] === (int) $this->vendorId);
    }

    public function index()
    {
        if (! $this->canManage()) {
            redirect('/dashboard');
            return;
        }
        $this->global['pageTitle'] = 'Job Seeker : Workspace Runtimes';
        $this->loadViews('workspaceRuntimes', $this->global, array(
            'runtimeKinds' => $this->service()->spec()->kinds(),
            'pythonVersions' => $this->service()->spec()->pythonVersions(),
            'runtimesEnabled' => $this->service()->enabled()
        ), NULL);
    }

    /** The catalog with each runtime's build, and every deployment. */
    public function catalog()
    {
        if (! $this->allowed('GET')) {
            return;
        }
        $service = $this->service();
        $engine = $service->engineState();
        $usage = $service->model()->runtimeUsage();
        $runtimes = array();
        foreach ($service->model()->runtimes() as $runtime) {
            $plan = $service->planFor($runtime);
            $build = $engine['available'] && $engine['toolkit'] ? $service->buildState($plan) : array('status' => 'unknown', 'message' => '', 'startedAt' => '', 'finishedAt' => '');
            // A new toolkit (JobSeeker upgrade) only rebuilds the editor layer.
            if ($build['status'] === 'none' && $engine['available'] && is_array($service->docker()->inspectImage($plan['runtimeImage']))) {
                $build['status'] = 'outdated';
            }
            $look = $this->runtimeLook($runtime);
            $runtimes[] = array_merge($runtime, array(
                'category' => $look['category'],
                'icon' => $look['icon'],
                'highlights' => $service->spec()->highlights($runtime),
                'projects' => isset($usage[$runtime['key']]) ? $usage[$runtime['key']] : 0,
                'image' => $plan['runtimeImage'],
                'ideImage' => $plan['ideImage'],
                'build' => $build,
                'warnings' => $plan['warnings'],
                'dockerfile' => $plan['files']['Dockerfile'],
                'canEdit' => $this->canEdit($runtime)
            ));
        }
        $deployments = array();
        foreach ($engine['available'] ? $service->deployments() : array() as $deployment) {
            $own = $deployment['userId'] === (int) $this->vendorId;
            $deployment['canStop'] = $own || $deployment['shared'] || $this->isAdministrator();
            $deployment['canRemove'] = $own || $this->isAdministrator();
            $deployments[] = $deployment;
        }
        $fromTemplates = array();
        foreach ($runtimes as $runtime) {
            if (! empty($runtime['spec']['template'])) {
                $fromTemplates[$runtime['spec']['template']][] = $runtime['name'];
            }
        }
        $templates = array();
        foreach ($service->spec()->templates() as $key => $template) {
            $draft = $service->spec()->templateRuntime($key);
            $templates[] = array(
                'key' => $key,
                'name' => $template['name'],
                'category' => $template['category'],
                'icon' => $template['icon'],
                'description' => $template['description'],
                'kind' => $template['kind'],
                'highlights' => $service->spec()->highlights($draft, 10),
                'draft' => $draft,
                'inCatalog' => isset($fromTemplates[$key]) ? $fromTemplates[$key] : array()
            );
        }
        $this->json(array(
            'ok' => TRUE,
            'engine' => $engine,
            'templates' => $templates,
            'categories' => $service->spec()->templateCategories(),
            'usage' => array('default' => isset($usage['default']) ? $usage['default'] : 0, 'devcontainer' => isset($usage['devcontainer']) ? $usage['devcontainer'] : 0),
            'runtimes' => $runtimes,
            'deployments' => $deployments,
            'idleMinutes' => $service->idleMinutes(),
            'canReclaim' => $this->isAdministrator()
        ));
    }

    /** A runtime's category and icon: its template's, or its kind's. */
    private function runtimeLook(array $runtime)
    {
        $templates = $this->service()->spec()->templates();
        $template = isset($runtime['spec']['template']) ? (string) $runtime['spec']['template'] : '';
        if (isset($templates[$template])) {
            return array('category' => $templates[$template]['category'], 'icon' => $templates[$template]['icon']);
        }
        $kinds = $this->service()->spec()->kinds();
        return array('category' => $runtime['kind'] === 'conda' ? 'analytics' : 'essentials',
            'icon' => isset($kinds[$runtime['kind']]) ? $kinds[$runtime['kind']]['icon'] : 'fa-cube');
    }

    /** The runtime a form describes, as submitted (for previews and saves). */
    private function submittedRuntime()
    {
        $fields = array('key', 'name', 'description', 'kind', 'python_version', 'system_packages', 'python_packages', 'environment_yml',
            'dockerfile', 'extensions', 'features', 'ports', 'post_create', 'env', 'template');
        $input = array();
        foreach ($fields as $field) {
            $input[$field] = $this->input->post($field);
        }
        return $this->service()->spec()->cleanRuntime($input);
    }

    /**
     * The files a runtime form would produce, for the editor's generated
     * tabs: the image's Dockerfile and build files, and its devcontainer.json.
     */
    public function preview()
    {
        if (! $this->allowed('POST')) {
            return;
        }
        $clean = $this->submittedRuntime();
        $runtime = $clean['runtime'];
        if ($runtime['key'] === FALSE || $runtime['key'] === '') {
            $runtime['key'] = 'runtime';
        }
        $files = array();
        if (isset($this->service()->spec()->kinds()[$runtime['kind']])) {
            $files = $this->service()->spec()->devcontainerFiles($runtime);
        }
        $this->json(array('ok' => TRUE, 'errors' => $clean['errors'], 'files' => $files,
            'warnings' => $this->service()->spec()->runtimeWarnings($runtime['kind'], $runtime['spec'] + array('dockerfile' => ''))));
    }

    /**
     * A catalog runtime (key) or a template (template) as a .devcontainer
     * folder in a zip, for VS Code on a laptop, Codespaces or any project.
     */
    public function devcontainer()
    {
        $this->releaseSessionLock();
        if (! $this->canManage()) {
            $this->json(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }
        $spec = $this->service()->spec();
        $template = trim((string) $this->input->get('template'));
        $runtime = $template !== '' ? $spec->templateRuntime($template) : $this->service()->model()->runtime(trim((string) $this->input->get('key')));
        if ($runtime === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That runtime or template does not exist.'), 404);
            return;
        }
        if (! class_exists('ZipArchive')) {
            $this->json(array('ok' => FALSE, 'message' => 'This PHP build cannot write zip files.'), 500);
            return;
        }
        $path = tempnam(sys_get_temp_dir(), 'devcontainer');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== TRUE) {
            $this->json(array('ok' => FALSE, 'message' => 'The dev container could not be packed.'), 500);
            return;
        }
        foreach ($spec->withSdk($spec->devcontainerFiles($runtime), APPPATH.'third_party/python/jobseeker_sdk') as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $body = (string) file_get_contents($path);
        @unlink($path);
        $this->output
            ->set_content_type('application/zip')
            ->set_header('Content-Disposition: attachment; filename="'.$runtime['key'].'-devcontainer.zip"')
            ->set_header('Cache-Control: no-store')
            ->set_output($body);
    }

    /** Creates a runtime, or updates the one named by original_key. */
    public function save()
    {
        if (! $this->allowed('POST')) {
            return;
        }
        $service = $this->service();
        $originalKey = trim((string) $this->input->post('original_key'));
        $existing = $originalKey === '' ? FALSE : $service->model()->runtime($originalKey);
        if ($originalKey !== '' && $existing === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That runtime no longer exists.'), 404);
            return;
        }
        if ($existing !== FALSE && ! $this->canEdit($existing)) {
            $this->json(array('ok' => FALSE, 'message' => 'Only an administrator or the person who created this runtime can change it.'), 403);
            return;
        }
        $clean = $service->spec()->cleanRuntime(array(
            'key' => $existing !== FALSE ? $existing['key'] : $this->input->post('key'),
            'name' => $this->input->post('name'),
            'description' => $this->input->post('description'),
            'kind' => $this->input->post('kind'),
            'python_version' => $this->input->post('python_version'),
            'system_packages' => $this->input->post('system_packages'),
            'python_packages' => $this->input->post('python_packages'),
            'environment_yml' => $this->input->post('environment_yml'),
            'dockerfile' => $this->input->post('dockerfile'),
            'extensions' => $this->input->post('extensions'),
            'features' => $this->input->post('features'),
            'ports' => $this->input->post('ports'),
            'post_create' => $this->input->post('post_create'),
            'env' => $this->input->post('env'),
            'template' => $this->input->post('template')
        ));
        if (! $clean['ok']) {
            $this->json(array('ok' => FALSE, 'message' => reset($clean['errors']), 'errors' => $clean['errors']), 400);
            return;
        }
        $runtime = $clean['runtime'];
        if ($existing === FALSE && $service->model()->runtime($runtime['key']) !== FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'A runtime with the key '.$runtime['key'].' already exists.', 'errors' => array('key' => 'Choose another key.')), 409);
            return;
        }
        if (! $service->model()->saveRuntime($runtime, $this->vendorId, $existing === FALSE ? '' : $existing['key'])) {
            $this->json(array('ok' => FALSE, 'message' => 'The runtime could not be saved.'), 500);
            return;
        }
        log_message('info', 'User '.(int) $this->vendorId.' '.($existing === FALSE ? 'created' : 'changed').' the workspace runtime '.$runtime['key'].'.');
        $warnings = $service->spec()->runtimeWarnings($runtime['kind'], $runtime['spec']);
        $this->json(array('ok' => TRUE, 'key' => $runtime['key'], 'warnings' => $warnings, 'message' => $existing === FALSE
            ? $runtime['name'].' was added. Build it now, or let the first project that opens in it build it.'
            : $runtime['name'].' was saved. Projects on it get the new image at their next open; jobs keep the image they were saved with.'));
    }

    public function delete()
    {
        if (! $this->allowed('POST')) {
            return;
        }
        $service = $this->service();
        $runtime = $service->model()->runtime(trim((string) $this->input->post('key')));
        if ($runtime === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That runtime no longer exists.'), 404);
            return;
        }
        if (! $this->canEdit($runtime)) {
            $this->json(array('ok' => FALSE, 'message' => 'Only an administrator or the person who created this runtime can delete it.'), 403);
            return;
        }
        $usage = $service->model()->runtimeUsage();
        if (! empty($usage[$runtime['key']])) {
            $this->json(array('ok' => FALSE, 'message' => $runtime['name'].' is used by '.$usage[$runtime['key']].' project'.($usage[$runtime['key']] === 1 ? '' : 's').'. Move them to another runtime first.'), 409);
            return;
        }
        $service->model()->deleteRuntime($runtime['key']);
        log_message('info', 'User '.(int) $this->vendorId.' deleted the workspace runtime '.$runtime['key'].'.');
        $this->json(array('ok' => TRUE, 'message' => $runtime['name'].' was removed from the catalog. Its images stay in the job runtime for jobs that use them.'));
    }

    public function build()
    {
        if (! $this->allowed('POST')) {
            return;
        }
        $service = $this->service();
        $runtime = $service->model()->runtime(trim((string) $this->input->post('key')));
        if ($runtime === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That runtime no longer exists.'), 404);
            return;
        }
        $started = $service->startBuild($service->planFor($runtime), $this->vendorId, $this->input->post('force') === '1');
        $this->json($started, $started['ok'] ? 200 : 503);
    }

    /** A runtime's build state and log, polled while it builds. */
    public function log()
    {
        if (! $this->allowed('GET')) {
            return;
        }
        $service = $this->service();
        $runtime = $service->model()->runtime(trim((string) $this->input->get('key')));
        if ($runtime === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That runtime no longer exists.'), 404);
            return;
        }
        $plan = $service->planFor($runtime);
        $this->json(array(
            'ok' => TRUE,
            'build' => $service->buildState($plan),
            'image' => $plan['runtimeImage'],
            'ideImage' => $plan['ideImage'],
            'lines' => $service->buildLog($plan['buildHash'], 1500)
        ));
    }

    /** Stops or removes a deployment. */
    public function deployment()
    {
        if (! $this->allowed('POST')) {
            return;
        }
        $service = $this->service();
        $action = (string) $this->input->post('action');
        $instance = FALSE;
        foreach ($service->model()->instances() as $row) {
            if ((int) $row['id'] === (int) $this->input->post('id')) {
                $instance = $row;
            }
        }
        if ($instance === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'That deployment no longer exists.'), 404);
            return;
        }
        $own = (int) $instance['user_id'] === (int) $this->vendorId;
        if ($action === 'stop' && ($own || (int) $instance['user_id'] === 0 || $this->isAdministrator())) {
            $result = $service->stop($instance);
        } else if ($action === 'remove' && ($own || $this->isAdministrator())) {
            $result = $service->remove($instance, $this->input->post('with_home') === '1');
            log_message('info', 'User '.(int) $this->vendorId.' removed the editor deployment '.$instance['container_name'].'.');
        } else {
            $this->json(array('ok' => FALSE, 'message' => 'You can stop your own and shared editors, and remove your own. Administrators manage the rest.'), 403);
            return;
        }
        $this->json($result, $result['ok'] ? 200 : 502);
    }

    /**
     * Runtime images saved jobs name (as their Docker image or anywhere in
     * their configuration), and whether every job's configuration was read.
     */
    private function jobRuntimeImages()
    {
        $response = $this->requestJenkins('GET', 'api/json?tree=jobs[fullName,jobs[fullName,jobs[fullName,jobs[fullName]]]]');
        $payload = (int) $response['status'] === 200 ? json_decode((string) $response['body'], TRUE) : NULL;
        if (! is_array($payload) || ! isset($payload['jobs'])) {
            return array(array(), FALSE);
        }
        $names = array();
        $collect = function($jobs) use (&$collect, &$names) {
            foreach ((array) $jobs as $job) {
                if (! empty($job['fullName'])) {
                    $names[] = (string) $job['fullName'];
                }
                if (! empty($job['jobs'])) {
                    $collect($job['jobs']);
                }
            }
        };
        $collect($payload['jobs']);
        $paths = array();
        foreach ($names as $name) {
            $paths[] = 'job/'.implode('/job/', array_map('rawurlencode', explode('/', $name))).'/config.xml';
        }
        $complete = TRUE;
        $images = array();
        foreach ($this->requestJenkinsMany($paths, 8, TRUE) as $result) {
            if ($result['status'] !== 200) {
                $complete = FALSE;
                continue;
            }
            if (preg_match_all('#'.preg_quote(WorkspaceRuntime::IMAGE_REPOSITORY, '#').'/[a-z0-9-]+:[0-9a-f]{12}(?:-ide)?#', $result['body'], $matches)) {
                foreach ($matches[0] as $image) {
                    $images[$image] = TRUE;
                }
            }
        }
        return array(array_keys($images), $complete && count($paths) === count($names));
    }

    /** Images nothing needs (GET), or removes the chosen ones (POST). Administrators only. */
    public function reclaim()
    {
        $method = strtoupper((string) $this->input->method(TRUE));
        if (! $this->allowed($method === 'POST' ? 'POST' : 'GET')) {
            return;
        }
        if (! $this->isAdministrator()) {
            $this->json(array('ok' => FALSE, 'message' => 'Only administrators reclaim runtime images.'), 403);
            return;
        }
        list($jobImages, $jobsComplete) = $this->jobRuntimeImages();
        $service = $this->service();
        if ($method !== 'POST') {
            $candidates = $service->reclaimCandidates($jobImages, $jobsComplete);
            if ($candidates === FALSE) {
                $this->json(array('ok' => FALSE, 'message' => 'The Docker job runtime is not reachable.'), 503);
                return;
            }
            $this->json(array('ok' => TRUE, 'candidates' => $candidates, 'jobsComplete' => $jobsComplete, 'jobImages' => count($jobImages)));
            return;
        }
        $references = $this->input->post('references');
        $result = $service->reclaimImages(is_array($references) ? array_map('strval', $references) : array(), $jobImages, $jobsComplete);
        if (isset($result['removed'])) {
            log_message('info', 'User '.(int) $this->vendorId.' reclaimed '.count($result['removed']).' workspace runtime image(s): '.implode(', ', $result['removed']).'.');
        }
        $this->json($result, isset($result['removed']) ? 200 : 503);
    }

    /** The last lines a deployment printed, and those of its dev container hooks. */
    public function deploymentLogs()
    {
        if (! $this->allowed('GET')) {
            return;
        }
        foreach ($this->service()->model()->instances() as $row) {
            if ((int) $row['id'] === (int) $this->input->get('id')) {
                if ((int) $row['user_id'] !== (int) $this->vendorId && (int) $row['user_id'] !== 0 && ! $this->isAdministrator()) {
                    $this->json(array('ok' => FALSE, 'message' => 'Only its owner and administrators can read this editor\'s output.'), 403);
                    return;
                }
                $this->json(array('ok' => TRUE, 'text' => $this->service()->docker()->containerLogs($row['container_name'], 300)));
                return;
            }
        }
        $this->json(array('ok' => FALSE, 'message' => 'That deployment no longer exists.'), 404);
    }
}

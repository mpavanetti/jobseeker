<?php
// Workspace runtimes: recipes, generated images, dev containers, build
// contexts and editor containers (application/libraries/WorkspaceRuntime.php).
define('JOBSEEKER_WORKSPACE_RUNTIME_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/WorkspaceRuntime.php';

$checks = 0;
function workspace_runtime_assert($condition, $message) {
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

function workspace_runtime_tree($root, array $files) {
    foreach ($files as $path => $content) {
        $target = $root.'/'.$path;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, TRUE);
        }
        file_put_contents($target, $content);
    }
}

function workspace_runtime_remove($path) {
    if (is_dir($path) && ! is_link($path)) {
        foreach (array_diff(scandir($path), array('.', '..')) as $entry) {
            workspace_runtime_remove($path.'/'.$entry);
        }
        rmdir($path);
    } else if (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

$runtime = new WorkspaceRuntime();

// Keys name images, so they are plain and never collide with the built-in choices.
workspace_runtime_assert($runtime->cleanKey('geo-python') === 'geo-python', 'a plain key is kept');
workspace_runtime_assert($runtime->cleanKey('Geo-Python') === 'geo-python', 'keys are lower-cased');
foreach (array('default', 'devcontainer', 'p12', 'x', '-lead', 'a_b', 'a/b', str_repeat('a', 41)) as $bad) {
    workspace_runtime_assert($runtime->cleanKey($bad) === FALSE, 'the key '.$bad.' is refused');
}
workspace_runtime_assert($runtime->keyFromName('Geo Python 3.12') === 'geo-python-3-12', 'a key is made from the name');
workspace_runtime_assert($runtime->cleanKey($runtime->keyFromName('!!')) !== FALSE, 'a name without letters still yields a valid key');

// Recipes are validated per kind.
$python = $runtime->cleanRuntime(array('name' => 'Geo', 'kind' => 'python', 'python_version' => '3.12',
    'system_packages' => "libgdal-dev, build-essential\nlibgdal-dev", 'python_packages' => "pandas>=2.2\r\nrasterio"));
workspace_runtime_assert($python['ok'], 'a Python runtime is accepted: '.json_encode($python['errors']));
workspace_runtime_assert($python['runtime']['key'] === 'geo', 'the key comes from the name');
workspace_runtime_assert($python['runtime']['spec']['system_packages'] === array('libgdal-dev', 'build-essential'), 'system packages are split and de-duplicated');
workspace_runtime_assert($python['runtime']['spec']['python_packages'] === "pandas>=2.2\nrasterio\n", 'line endings are normalized');
$bad = $runtime->cleanRuntime(array('name' => 'Bad', 'kind' => 'python', 'python_version' => '2.7', 'system_packages' => 'curl; rm -rf /', 'python_packages' => '-e ./local'));
workspace_runtime_assert(! $bad['ok'] && isset($bad['errors']['python_version'], $bad['errors']['system_packages'], $bad['errors']['python_packages']),
    'unknown versions, shell in package names and local paths are refused');
$conda = $runtime->cleanRuntime(array('name' => 'Conda', 'kind' => 'conda', 'environment_yml' => "channels: [conda-forge]\n"));
workspace_runtime_assert(isset($conda['errors']['environment_yml']), 'an environment.yml needs dependencies');
$copying = $runtime->cleanRuntime(array('name' => 'Copy', 'kind' => 'dockerfile', 'dockerfile' => "FROM debian:12\nCOPY app.py /app/\n"));
workspace_runtime_assert(isset($copying['errors']['dockerfile']), 'a catalog Dockerfile cannot COPY from a context it does not have');
$fromStage = $runtime->cleanRuntime(array('name' => 'Stage', 'kind' => 'dockerfile', 'dockerfile' => "FROM debian:12\nCOPY --from=ghcr.io/astral-sh/uv:0.12.6 /uv /usr/local/bin/\nADD https://example.com/x.tgz /tmp/\n"));
workspace_runtime_assert($fromStage['ok'], 'COPY --from and URLs are fine in a catalog Dockerfile');
workspace_runtime_assert(count($runtime->runtimeWarnings('dockerfile', array('dockerfile' => "FROM python:3.12-alpine\n"))) === 1, 'Alpine bases are warned about');
workspace_runtime_assert($runtime->cleanRuntime(array('name' => 'X', 'kind' => 'shell'))['errors']['kind'] !== '', 'unknown kinds are refused');

// Every runtime is a full dev container: extensions, features, ports, a hook, environment.
$extras = $runtime->cleanRuntime(array('name' => 'Extras', 'kind' => 'python', 'python_version' => '3.13',
    'extensions' => "ms-toolsai.jupyter\nms-toolsai.jupyter, humao.rest-client",
    'features' => '{ // JSONC, as in devcontainer.json
      "ghcr.io/devcontainers/features/node:1": { "version": "lts", "nodeGypDependencies": false }, }',
    'ports' => '8000, 8501 8000', 'post_create' => "pip list\r\n", 'env' => "# comment\nTZ=UTC\nAPP_MODE=dev=1\n", 'template' => 'fastapi'));
workspace_runtime_assert($extras['ok'], 'extras are accepted: '.json_encode($extras['errors']));
$extraSpec = $extras['runtime']['spec'];
workspace_runtime_assert($extraSpec['extensions'] === array('ms-toolsai.jupyter', 'humao.rest-client'), 'extensions are split and de-duplicated');
workspace_runtime_assert($extraSpec['features'] === array(array('ref' => 'ghcr.io/devcontainers/features/node:1', 'options' => array('version' => 'lts', 'nodeGypDependencies' => 'false'))), 'features are read from devcontainer.json syntax');
workspace_runtime_assert($extraSpec['ports'] === array(8000, 8501) && $extraSpec['post_create'] === 'pip list', 'ports are de-duplicated, the hook trimmed');
workspace_runtime_assert($extraSpec['env'] === array('TZ' => 'UTC', 'APP_MODE' => 'dev=1') && $extraSpec['template'] === 'fastapi', 'environment lines keep = in values; a known template is kept');
$badExtras = $runtime->cleanRuntime(array('name' => 'Bad', 'kind' => 'python', 'python_version' => '3.13', 'extensions' => 'not an id',
    'features' => '["list"]', 'ports' => '80000', 'env' => "JOBSEEKER_IDE_TOKEN=x\nnovalue", 'template' => 'nope'));
workspace_runtime_assert(isset($badExtras['errors']['extensions'], $badExtras['errors']['features'], $badExtras['errors']['ports'], $badExtras['errors']['env'])
    && $badExtras['runtime']['spec']['template'] === '', 'bad extensions, features, ports and reserved variables are refused: '.json_encode($badExtras['errors']));
workspace_runtime_assert(json_encode($runtime->buildFeatures(array(array('ref' => 'ghcr.io/x/y:1', 'options' => array())))) === '[{"ref":"ghcr.io\/x\/y:1","options":{}}]',
    'features.json always has option objects, which the fetch script merges');

// The template gallery: every template is a valid runtime that exports cleanly.
$categories = $runtime->templateCategories();
workspace_runtime_assert(count($runtime->templates()) >= 18, 'the gallery has many templates');
foreach ($runtime->templates() as $templateKey => $template) {
    workspace_runtime_assert(isset($categories[$template['category']]) && $template['icon'] !== '' && strlen($template['description']) > 40, $templateKey.' has a category, an icon and a description');
    $draft = $runtime->templateRuntime($templateKey);
    $clean = $runtime->cleanRuntime(array_merge(array('name' => $draft['name'], 'key' => $templateKey, 'description' => $draft['description'], 'kind' => $draft['kind']), $draft['spec']));
    workspace_runtime_assert($clean['ok'] && $clean['runtime']['spec']['template'] === $templateKey, $templateKey.' is a valid runtime: '.json_encode($clean['errors']));
    $exported = $runtime->devcontainerFiles($clean['runtime']);
    $read = $runtime->devcontainer($exported['.devcontainer/devcontainer.json'], '.devcontainer/devcontainer.json');
    workspace_runtime_assert($read['ok'] && $read['warnings'] === array(), $templateKey.' exports a dev container JobSeeker reads cleanly: '.json_encode($read['warnings']));
    workspace_runtime_assert($read['dockerfile'] === '.devcontainer/Dockerfile' && $read['context'] === '.devcontainer'
        && count($read['features']) === count($clean['runtime']['spec']['features']) && $read['ports'] === $clean['runtime']['spec']['ports'], $templateKey.' keeps its build, features and ports');
    workspace_runtime_assert(count($runtime->highlights($clean['runtime'])) > 0, $templateKey.' has package highlights');
}
workspace_runtime_assert($runtime->templateRuntime('nope') === FALSE, 'an unknown template is FALSE');

// A runtime as a .devcontainer folder.
$exported = $runtime->devcontainerFiles($extras['runtime']);
workspace_runtime_assert(array_keys($exported) === array('.devcontainer/devcontainer.json', '.devcontainer/Dockerfile', '.devcontainer/README.md'), 'the export has a definition, the Dockerfile and a README: '.json_encode(array_keys($exported)));
$definition = $runtime->parseJsonc($exported['.devcontainer/devcontainer.json']);
workspace_runtime_assert($definition['build'] === array('dockerfile' => 'Dockerfile', 'context' => '.') && $definition['forwardPorts'] === array(8000, 8501)
    && $definition['containerEnv'] === array('TZ' => 'UTC', 'APP_MODE' => 'dev=1') && $definition['postCreateCommand'] === 'pip list', 'the definition carries build, ports, environment and hook');
workspace_runtime_assert($definition['customizations']['vscode']['extensions'] === array('ms-python.python', 'ms-python.debugpy', 'charliermarsh.ruff', 'ms-toolsai.jupyter', 'humao.rest-client')
    && $definition['customizations']['vscode']['settings']['python.defaultInterpreterPath'] === '/usr/local/bin/python', 'the Python tooling extensions come first, the interpreter is set');
workspace_runtime_assert($definition['features'] === array('ghcr.io/devcontainers/features/node:1' => array('version' => 'lts', 'nodeGypDependencies' => 'false')), 'features keep their options');
workspace_runtime_assert(strpos($exported['.devcontainer/devcontainer.json'], "\n  \"name\"") !== FALSE, 'the definition is indented with two spaces');
workspace_runtime_assert(strpos($exported['.devcontainer/Dockerfile'], '# The "Extras" runtime, exported from JobSeeker') === 0 && strpos($exported['.devcontainer/Dockerfile'], 'FROM python:3.13-slim') !== FALSE, 'the Dockerfile says where it came from');
$condaExport = $runtime->devcontainerFiles(array('key' => 'geo', 'name' => 'Geo', 'description' => '', 'kind' => 'conda', 'spec' => array('system_packages' => array(), 'environment_yml' => "dependencies:\n  - gdal\n")));
workspace_runtime_assert(isset($condaExport['.devcontainer/environment.yml']) && strpos($condaExport['.devcontainer/devcontainer.json'], WorkspaceRuntime::CONDA_ENV.'/bin/python') !== FALSE, 'a Conda export ships its environment and interpreter');
$dockerExport = $runtime->devcontainerFiles(array('key' => 'own', 'name' => 'Own', 'description' => '', 'kind' => 'dockerfile', 'spec' => array('dockerfile' => "FROM debian:13\n")));
workspace_runtime_assert($dockerExport['.devcontainer/Dockerfile'] === "FROM debian:13\n" && strpos($dockerExport['.devcontainer/devcontainer.json'], 'defaultInterpreterPath') === FALSE, 'a Dockerfile export is left as written');

// Jobs a project creates require the Python its runtime has.
workspace_runtime_assert($runtime->pythonVersionOf(array('kind' => 'python', 'spec' => array('python_version' => '3.12'))) === '3.12', 'a Python runtime says its version');
workspace_runtime_assert($runtime->pythonVersionOf(array('kind' => 'conda', 'spec' => array('environment_yml' => "channels:\n  - conda-forge\ndependencies:\n  - python=3.11\n  - pip\n"))) === '3.11', 'a Conda runtime says the python it pins');
workspace_runtime_assert($runtime->pythonVersionOf(array('kind' => 'conda', 'spec' => array('environment_yml' => "dependencies:\n  - numpy\n"))) === '', 'an unpinned Conda python is unknown');
workspace_runtime_assert($runtime->pythonVersionOf(array('kind' => 'dockerfile', 'spec' => array('dockerfile' => "FROM node:22 AS web\nFROM docker.io/library/python:3.12-slim-bookworm\n"))) === '3.12', 'a Dockerfile says the python image it ends on');
workspace_runtime_assert($runtime->pythonVersionOf(array('kind' => 'dockerfile', 'spec' => array('dockerfile' => "FROM debian:13\n"))) === '', 'other bases are unknown');
workspace_runtime_assert($runtime->pythonVersionOfDockerfile('mcr.microsoft.com/devcontainers/python:3.11') === '3.11', 'an image reference says its version');
workspace_runtime_assert(WorkspaceRuntime::LENIENT_PYTHON === '3.10', 'unknown runtimes get a lenient lower bound');
foreach ($runtime->templates() as $templateKey => $template) {
    $draft = $runtime->templateRuntime($templateKey);
    workspace_runtime_assert(preg_match('/^3\.1[0-9]$/', $runtime->pythonVersionOf($draft)) === 1, $templateKey.' says which Python its jobs run on');
}

// A downloaded .devcontainer carries the SDK, installed into its image.
$sdkRoot = sys_get_temp_dir().'/jobseeker-sdk-test-'.getmypid();
workspace_runtime_remove($sdkRoot);
workspace_runtime_tree($sdkRoot, array('pyproject.toml' => "[project]\nname = \"jobseeker-runtime\"\n", 'src/jobseeker/__init__.py' => "x = 1\n",
    'src/jobseeker/__pycache__/x.pyc' => 'cache', 'src/jobseeker_runtime.egg-info/PKG-INFO' => 'info'));
$pythonExport = $runtime->devcontainerFiles(array('key' => 'x', 'name' => 'X', 'description' => '', 'kind' => 'python', 'spec' => array('python_version' => '3.12', 'system_packages' => array(), 'python_packages' => '')));
$withSdk = $runtime->withSdk($pythonExport, $sdkRoot);
workspace_runtime_assert(isset($withSdk['.devcontainer/jobseeker-sdk/pyproject.toml'], $withSdk['.devcontainer/jobseeker-sdk/src/jobseeker/__init__.py'])
    && ! isset($withSdk['.devcontainer/jobseeker-sdk/src/jobseeker/__pycache__/x.pyc']) && ! isset($withSdk['.devcontainer/jobseeker-sdk/src/jobseeker_runtime.egg-info/PKG-INFO']), 'the SDK source travels without caches or build metadata');
workspace_runtime_assert(strpos($withSdk['.devcontainer/Dockerfile'], "COPY jobseeker-sdk /opt/jobseeker-sdk\n") !== FALSE && strpos($withSdk['.devcontainer/Dockerfile'], 'python -m pip install --no-cache-dir /opt/jobseeker-sdk') !== FALSE, 'the image installs the SDK');
workspace_runtime_assert($runtime->withSdk($pythonExport, $sdkRoot.'/missing') === $pythonExport, 'without an SDK the export is unchanged');
workspace_runtime_remove($sdkRoot);

// Generated images.
$files = $runtime->buildFiles('geo', 'python', $python['runtime']['spec']);
workspace_runtime_assert(array_keys($files) === array('Dockerfile', 'requirements.txt'), 'a Python runtime with packages builds from a Dockerfile and requirements.txt');
workspace_runtime_assert(strpos($files['Dockerfile'], 'FROM python:3.12-slim') !== FALSE, 'the Python version picks the base image');
workspace_runtime_assert(strpos($files['Dockerfile'], 'git openssh-client libgdal-dev build-essential') !== FALSE, 'system packages join the tools every runtime gets');
workspace_runtime_assert(strpos($files['Dockerfile'], 'JOBSEEKER_WORKSPACE_PYTHON=/usr/local/bin/python3.12') !== FALSE, 'the workspace bootstrap is pointed at the runtime Python');
workspace_runtime_assert(strpos($files['Dockerfile'], 'uv pip install --system') !== FALSE, 'packages are preinstalled with uv');
$bare = $runtime->buildFiles('bare', 'python', array('python_version' => '3.13', 'system_packages' => array(), 'python_packages' => ''));
workspace_runtime_assert(array_keys($bare) === array('Dockerfile') && strpos($bare['Dockerfile'], 'requirements') === FALSE, 'a runtime without packages has no requirements step');
$condaFiles = $runtime->buildFiles('conda', 'conda', array('system_packages' => array(), 'environment_yml' => "dependencies:\n  - python=3.12\n"));
workspace_runtime_assert(isset($condaFiles['environment.yml']) && strpos($condaFiles['Dockerfile'], WorkspaceRuntime::CONDA_IMAGE) !== FALSE, 'Conda runtimes build on the pinned Miniforge image');
workspace_runtime_assert(strpos($condaFiles['Dockerfile'], 'mamba env create -y -n jobseeker') !== FALSE && strpos($condaFiles['Dockerfile'], '/etc/profile.d/jobseeker-conda.sh') !== FALSE,
    'the Conda environment is always "jobseeker" and first on PATH, even in login shells');
$custom = $runtime->buildFiles('custom', 'dockerfile', array('dockerfile' => "FROM debian:12\n"));
workspace_runtime_assert($custom === array('Dockerfile' => "FROM debian:12\n"), 'a Dockerfile runtime builds as written');
foreach ($runtime->presets() as $preset) {
    $clean = $runtime->cleanRuntime(array_merge(array('name' => $preset['name'], 'key' => $preset['key'], 'description' => $preset['description'], 'kind' => $preset['kind']), $preset['spec']));
    workspace_runtime_assert($clean['ok'], 'the preset '.$preset['key'].' is a valid runtime');
}

$ide = $runtime->ideDockerfile('jobseeker-runtime/geo:0123456789ab');
workspace_runtime_assert(strpos($ide, 'FROM '.WorkspaceRuntime::TOOLKIT_IMAGE.' AS jobseeker-ide') === 0 || strpos($ide, "\nFROM ".WorkspaceRuntime::TOOLKIT_IMAGE.' AS jobseeker-ide') !== FALSE, 'the IDE image copies the toolkit');
workspace_runtime_assert(strpos($ide, "FROM jobseeker-runtime/geo:0123456789ab\n") !== FALSE, 'the IDE image is the runtime image with the editor on top');
workspace_runtime_assert(strpos($ide, '--chmod') === FALSE && strpos($ide, '<<') === FALSE, 'the IDE Dockerfile works with the classic builder the API uses');
workspace_runtime_assert(strpos($ide, 'jobseeker-features') === FALSE, 'without features there is no feature stage');
workspace_runtime_assert(strpos($ide, "    PATH=\$PATH:/opt/jobseeker-ide/bin \\\n") !== FALSE && strpos($ide, "    JOBSEEKER_VENV_SYSTEM_SITE_PACKAGES=1\n") !== FALSE,
    'every process of the editor, not only its entrypoint, has the toolkit on PATH and builds .venv on the runtime\'s packages');
$withFeatures = $runtime->ideDockerfile('jobseeker-runtime/geo:0123456789ab', TRUE);
workspace_runtime_assert(strpos($withFeatures, "FROM ".WorkspaceRuntime::FEATURES_IMAGE." AS jobseeker-features\n") !== FALSE
    && strpos($withFeatures, "COPY features.json /features.json\n") !== FALSE
    && strpos($withFeatures, "COPY --from=jobseeker-features /opt/jobseeker-features /opt/jobseeker-features\n") < strpos($withFeatures, 'jobseeker-ide-setup'),
    'features are fetched in their own stage from features.json and installed before the editor setup');

// Content-addressed tags.
$hash = $runtime->hash($files, array('Dockerfile'));
workspace_runtime_assert(preg_match('/^[0-9a-f]{12}$/', $hash) === 1, 'hashes are 12 hex characters');
workspace_runtime_assert($runtime->hash(array_reverse($files, TRUE), array('Dockerfile')) === $hash, 'file order does not change a hash');
workspace_runtime_assert($runtime->hash(array_merge($files, array('requirements.txt' => "pandas\n")), array('Dockerfile')) !== $hash, 'content changes a hash');
workspace_runtime_assert($runtime->hash($files, array('Dockerfile', '{"A":"1"}')) !== $hash, 'build arguments change a hash');
workspace_runtime_assert($runtime->runtimeImage('geo', $hash) === 'jobseeker-runtime/geo:'.$hash && $runtime->ideImage('geo', $hash) === 'jobseeker-runtime/geo:'.$hash.'-ide', 'images are tagged by hash');
workspace_runtime_assert($runtime->isRuntimeImage('jobseeker-runtime/geo:'.$hash) && ! $runtime->isRuntimeImage('jobseeker-runtime/geo:'.$hash.'-ide') && ! $runtime->isRuntimeImage('python:3.13-slim'),
    'only runtime images (not their IDE variants) are recognized as job images');

// devcontainer.json is JSON with comments and trailing commas.
$jsonc = <<<'JSONC'
{
  // Geo tooling
  "name": "geo", /* inline */
  "build": {
    "dockerfile": "Dockerfile",
    "context": "..",
    "args": { "VERSION": "3.12", "bad name": "x", },
    "target": "dev",
  },
  "containerEnv": { "FLAVOR": "geo", "JOBSEEKER_IDE_TOKEN": "steal", "HOMEDIR": "${localEnv:HOME}" },
  "remoteEnv": { "URL": "https://example.com/a//b" },
  "customizations": { "vscode": { "extensions": ["redhat.vscode-yaml", "ms-toolsai.jupyter@2025.1.0", "not an id"], "settings": { "editor.tabSize": 2 } } },
  "postCreateCommand": ["pip", "install", "-e", "."],
  "postStartCommand": { "a": "echo one", "b": ["echo", "two"] },
  "features": {
    "ghcr.io/devcontainers/features/node:1": { "version": "20", "nodeGypDependencies": false, "bad name": "x" },
    "ghcr.io/devcontainers/features/github-cli": "2.40",
    "./local-feature": {},
    "node": {}
  },
  "runArgs": ["--privileged"],
  "remoteUser": "vscode",
}
JSONC;
$parsed = $runtime->parseJsonc($jsonc);
workspace_runtime_assert(is_array($parsed) && $parsed['remoteEnv']['URL'] === 'https://example.com/a//b', 'comments and trailing commas are stripped, // inside strings kept');
workspace_runtime_assert($runtime->parseJsonc('{"a": [1, 2,], }') === array('a' => array(1, 2)), 'trailing commas in arrays and objects are dropped');
workspace_runtime_assert($runtime->parseJsonc('{nope') === NULL, 'invalid JSON is NULL');
$definition = $runtime->devcontainer($jsonc, '.devcontainer/devcontainer.json');
workspace_runtime_assert($definition['ok'], 'the dev container is read: '.json_encode($definition['errors']));
workspace_runtime_assert($definition['dockerfile'] === '.devcontainer/Dockerfile' && $definition['context'] === '', 'paths resolve from the .devcontainer folder to the project');
workspace_runtime_assert($definition['args'] === array('VERSION' => '3.12') && $definition['target'] === 'dev', 'build arguments and target are kept, bad names dropped');
workspace_runtime_assert($definition['env'] === array('FLAVOR' => 'geo', 'URL' => 'https://example.com/a//b'), 'environment is kept without JobSeeker\'s own variables or ${...} values');
workspace_runtime_assert($definition['extensions'] === array('redhat.vscode-yaml', 'ms-toolsai.jupyter@2025.1.0'), 'extension ids are kept, junk skipped');
workspace_runtime_assert($definition['settings'] === array('editor.tabSize' => 2), 'VS Code settings are kept');
workspace_runtime_assert($definition['postCreate'] === "'pip' 'install' '-e' '.'", 'an array command is quoted word by word');
workspace_runtime_assert($definition['postStart'] === "(echo one) && ('echo' 'two')", 'an object command runs its parts in turn');
$warnings = implode(' | ', $definition['warnings']);
workspace_runtime_assert(json_encode($definition['features'], JSON_UNESCAPED_SLASHES) === '[{"ref":"ghcr.io/devcontainers/features/node:1","options":{"version":"20","nodeGypDependencies":"false"}},{"ref":"ghcr.io/devcontainers/features/github-cli:latest","options":{"version":"2.40"}}]',
    'registry features are kept in order with their options as strings, a bare string as the version: '.json_encode($definition['features']));
foreach (array('./local-feature was skipped', 'node was skipped', 'runArgs', 'remoteUser', 'HOMEDIR', 'one after another', 'not an id') as $expected) {
    workspace_runtime_assert(stripos($warnings, $expected) !== FALSE, 'the warnings mention '.$expected.': '.$warnings);
}
$image = $runtime->devcontainer('{"image": "mcr.microsoft.com/devcontainers/python:3.12"}', '.devcontainer.json');
workspace_runtime_assert($image['ok'] && $image['image'] === 'mcr.microsoft.com/devcontainers/python:3.12', 'an image-only dev container is read');
$escape = $runtime->devcontainer('{"build": {"dockerfile": "Dockerfile", "context": "../.."}}', '.devcontainer/devcontainer.json');
workspace_runtime_assert(! $escape['ok'], 'a build context outside the project is refused');
$compose = $runtime->devcontainer('{"dockerComposeFile": "compose.yml", "service": "app"}', '.devcontainer/devcontainer.json');
workspace_runtime_assert(! $compose['ok'] && strpos($compose['errors'][0], 'Compose') !== FALSE, 'Compose dev containers are refused with a reason');
workspace_runtime_assert(! $runtime->devcontainer('{"name": "x"}', '.devcontainer/devcontainer.json')['ok'], 'a dev container needs an image or a Dockerfile');
workspace_runtime_assert($runtime->projectPath('.devcontainer', '../src') === 'src' && $runtime->projectPath('', '../x') === FALSE && $runtime->projectPath('', '/etc') === FALSE,
    'project paths never leave the project');

// A starter .devcontainer is a valid dev container that builds its requirements file.
$starter = $runtime->devcontainerStarter('Geo "Mapping"', '3.12');
workspace_runtime_assert(array_keys($starter) === array('.devcontainer/devcontainer.json', '.devcontainer/Dockerfile', '.devcontainer/requirements.txt'), 'the starter has a definition, a Dockerfile and requirements');
$starterDefinition = $runtime->devcontainer($starter['.devcontainer/devcontainer.json'], '.devcontainer/devcontainer.json');
workspace_runtime_assert($starterDefinition['ok'] && $starterDefinition['dockerfile'] === '.devcontainer/Dockerfile' && $starterDefinition['context'] === ''
    && $starterDefinition['warnings'] === array(), 'the starter is read cleanly: '.json_encode($starterDefinition));
workspace_runtime_assert(strpos($starter['.devcontainer/devcontainer.json'], '"name": "Geo Mapping"') !== FALSE, 'the project name is kept, quotes removed');
workspace_runtime_assert(strpos($starter['.devcontainer/Dockerfile'], 'FROM python:3.12-slim') !== FALSE && $runtime->dockerfileSources($starter['.devcontainer/Dockerfile']) === array('.devcontainer/requirements.txt'),
    'the starter follows the Python version and copies only its requirements');
workspace_runtime_assert(strpos($runtime->devcontainerStarter('x', '2.7')['.devcontainer/Dockerfile'], 'FROM python:3.13-slim') !== FALSE, 'an unknown version falls back to 3.13');

// COPY sources decide what a dev container build (and its hash) includes.
$sources = $runtime->dockerfileSources("FROM python:3.12\nCOPY requirements.txt pyproject.toml /app/\nCOPY --chown=1000 [\"setup.cfg\", \"/app/\"]\nCOPY --from=builder /x /y\nADD https://example.com/a.tgz /tmp/\nADD \\\n  tools/ /opt/tools/\nRUN echo COPY nothing\n");
workspace_runtime_assert($sources === array('requirements.txt', 'pyproject.toml', 'setup.cfg', 'tools/'), 'COPY and ADD sources are found, --from and URLs skipped: '.json_encode($sources));

$root = sys_get_temp_dir().'/jobseeker-runtime-test-'.getmypid();
workspace_runtime_remove($root);
workspace_runtime_tree($root, array(
    '.devcontainer/devcontainer.json' => '{"build": {"dockerfile": "Dockerfile", "context": ".."}}',
    '.devcontainer/Dockerfile' => "FROM python:3.12-slim\nCOPY requirements*.txt /tmp/\nCOPY tools /opt/tools\n",
    'requirements.txt' => "rich\n",
    'requirements-dev.txt' => "pytest\n",
    'tools/run.sh' => "#!/bin/sh\necho hi\n",
    'tools/__pycache__/x.pyc' => 'cache',
    'jobs/orders/main.py' => "print('never in the image')\n",
    '.dockerignore' => "*.pyc\n"
));
chmod($root.'/tools/run.sh', 0755);
workspace_runtime_assert($runtime->findDevcontainer($root) === '.devcontainer/devcontainer.json', 'the dev container definition is found');
$context = $runtime->devcontainerContext($root, $runtime->devcontainer(file_get_contents($root.'/.devcontainer/devcontainer.json'), '.devcontainer/devcontainer.json'));
workspace_runtime_assert($context['ok'] && $context['dockerfile'] === '.devcontainer/Dockerfile', 'the context keeps the Dockerfile path: '.$context['message']);
$contextFiles = array_keys($context['files']);
sort($contextFiles);
workspace_runtime_assert($contextFiles === array('.devcontainer/Dockerfile', '.dockerignore', 'requirements-dev.txt', 'requirements.txt', 'tools/run.sh'),
    'only copied files travel, without caches or job code: '.json_encode($contextFiles));
workspace_runtime_assert($context['files']['tools/run.sh']['mode'] === 0755, 'executable files stay executable');
$before = $runtime->hash($context['files']);
file_put_contents($root.'/jobs/orders/main.py', "print('edited')\n");
$again = $runtime->devcontainerContext($root, $runtime->devcontainer(file_get_contents($root.'/.devcontainer/devcontainer.json'), '.devcontainer/devcontainer.json'));
workspace_runtime_assert($runtime->hash($again['files']) === $before, 'editing job code does not rebuild the dev container');
file_put_contents($root.'/requirements.txt', "rich\npandas\n");
$changed = $runtime->devcontainerContext($root, $runtime->devcontainer(file_get_contents($root.'/.devcontainer/devcontainer.json'), '.devcontainer/devcontainer.json'));
workspace_runtime_assert($runtime->hash($changed['files']) !== $before, 'editing a copied file rebuilds the dev container');
$inside = $runtime->devcontainerContext($root, array('image' => '', 'dockerfile' => '.devcontainer/Dockerfile', 'context' => '.devcontainer'));
workspace_runtime_assert($inside['ok'] && $inside['dockerfile'] === 'Dockerfile', 'a Dockerfile inside its context is named relative to it');
$outside = $runtime->devcontainerContext($root, array('image' => '', 'dockerfile' => '.devcontainer/Dockerfile', 'context' => 'tools'));
workspace_runtime_assert($outside['ok'] && $outside['dockerfile'] === '.jobseeker.Dockerfile', 'a Dockerfile outside its context travels under a name of its own');
$missing = $runtime->devcontainerContext($root, array('image' => '', 'dockerfile' => '.devcontainer/Nope', 'context' => ''));
workspace_runtime_assert(! $missing['ok'], 'a missing Dockerfile is reported');

// Build contexts are ustar archives Docker and tar both read.
$tar = $root.'/context.tar';
$long = str_repeat('deep/', 25).'file.txt';
workspace_runtime_assert($runtime->writeTar(array_merge($context['files'], array($long => "long\n")), $tar), 'a context is archived');
exec('tar -tvf '.escapeshellarg($tar).' 2>&1', $listing, $status);
workspace_runtime_assert($status === 0, 'tar reads the archive: '.implode("\n", $listing));
$listed = implode("\n", $listing);
workspace_runtime_assert(strpos($listed, 'tools/run.sh') !== FALSE && strpos($listed, $long) !== FALSE, 'names, including long ones, survive');
workspace_runtime_assert(preg_match('/^-rwxr-xr-x .*tools\/run\.sh$/m', $listed) === 1, 'modes survive');
exec('tar -xOf '.escapeshellarg($tar).' requirements.txt', $content);
workspace_runtime_assert(implode("\n", $content) === "rich\npandas", 'content survives');
workspace_runtime_assert(! $runtime->writeTar(array(str_repeat('x', 120) => ''), $tar), 'a name tar cannot hold is refused');
workspace_runtime_remove($root);

// Editor containers.
workspace_runtime_assert($runtime->containerName(7, 3) === 'jobseeker-ide-p7-u3' && $runtime->containerName(7, 0) === 'jobseeker-ide-p7-shared', 'containers are per project and person, or shared');
workspace_runtime_assert($runtime->homeVolume(7, 0) === 'jobseeker-ide-home-p7-shared', 'homes follow the deployment');
workspace_runtime_assert($runtime->portRange('3100-3199') === array(3100, 3199) && $runtime->portRange('80-9000') === array(3100, 3499) && $runtime->portRange('junk') === array(3100, 3199),
    'editor ports stay inside what the gateway routes');
$config = $runtime->containerConfig(array(
    'image' => 'jobseeker-runtime/geo:0123456789ab-ide', 'runtime' => 'geo', 'port' => 3105, 'token' => 'secret', 'uid' => 1001, 'gid' => 1002,
    'cpus' => 1.5, 'memoryMb' => 2048, 'idleMinutes' => 30, 'folder' => '/home/workspace/repository/workspaces/u3/geo-7',
    'mounts' => array(
        array('source' => '/php/repository/workspaces/u3/geo-7', 'target' => '/home/workspace/repository/workspaces/u3/geo-7', 'readOnly' => FALSE),
        array('source' => '/php/repository/python/lib', 'target' => '/home/workspace/repository/python/lib', 'readOnly' => TRUE)
    ),
    'homeVolume' => 'jobseeker-ide-home-p7-u3', 'env' => array('FLAVOR' => 'geo', 'JOBSEEKER_IDE_PORT' => '1'), 'labels' => array('com.jobseeker.port' => 3105),
    'spec' => 'abc', 'postCreate' => 'make setup', 'postStart' => '', 'settings' => array('editor.tabSize' => 2)
));
workspace_runtime_assert($config['User'] === '1001:1002', 'editors run as the checkout owner');
workspace_runtime_assert(in_array('JOBSEEKER_IDE_PORT=3105', $config['Env'], TRUE) && ! in_array('JOBSEEKER_IDE_PORT=1', $config['Env'], TRUE), 'dev container variables never override JobSeeker\'s');
workspace_runtime_assert(in_array('JOBSEEKER_IDE_BASE_PATH=/ide/3105', $config['Env'], TRUE) && in_array('JOBSEEKER_RUNTIME=geo', $config['Env'], TRUE) && in_array('FLAVOR=geo', $config['Env'], TRUE),
    'the editor gets its base path, runtime and the dev container environment');
workspace_runtime_assert(in_array('JOBSEEKER_REPOSITORY_ROOT=/home/workspace/repository', $config['Env'], TRUE)
    && in_array('JOBSEEKER_DATA_ASSETS_MANIFEST=/home/workspace/repository/data-assets/manifest.json', $config['Env'], TRUE),
    'notebook kernels and debug runs resolve Data Assets as terminal runs do');
workspace_runtime_assert(in_array('JOBSEEKER_CONNECTOR_API_URL=http://nginx:8080/connector-runtime', $config['Env'], TRUE)
    && in_array('JOBSEEKER_GIT_CREDENTIAL_URL=http://nginx:8080/git-credential', $config['Env'], TRUE),
    'runs in the editor reach their connectors and Git credentials, as in the Default editor');
workspace_runtime_assert(in_array('JOBSEEKER_IDE_POST_CREATE=make setup', $config['Env'], TRUE) && in_array('JOBSEEKER_IDE_MACHINE_SETTINGS={"editor.tabSize":2}', $config['Env'], TRUE), 'hooks and settings are passed on');
$host = $config['HostConfig'];
workspace_runtime_assert($host['NanoCpus'] === 1500000000 && $host['Memory'] === 2147483648 && $host['PidsLimit'] === 4096, 'resources are limited');
workspace_runtime_assert($host['CapDrop'] === array('ALL') && $host['SecurityOpt'] === array('no-new-privileges') && $host['Init'] === TRUE, 'editors have no capabilities and cannot gain privileges');
workspace_runtime_assert($host['Mounts'][0] === array('Type' => 'volume', 'Source' => 'jobseeker-ide-home-p7-u3', 'Target' => '/home/workspace'), 'the home is a volume of its own');
workspace_runtime_assert($host['Mounts'][2]['ReadOnly'] === TRUE && $host['Mounts'][1]['ReadOnly'] === FALSE && count($host['Mounts']) === 3, 'only the given folders are mounted, the SDK read-only');
workspace_runtime_assert($config['Labels'] === array('com.jobseeker.port' => '3105'), 'labels are strings');

// Build streams become a readable log.
$stream = implode("\n", array(
    '{"stream":"Step 1/2 : FROM python:3.12-slim\n"}',
    '{"status":"Pulling from library/python","id":"3.12-slim"}',
    '{"status":"Downloading","progressDetail":{"current":1,"total":9},"id":"abc"}',
    '{"status":"Pulling from library/python","id":"3.12-slim"}',
    '{"stream":"\u001b[91mInstalled 5 packages\n\u001b[0m"}',
    '{"aux":{"ID":"sha256:123"}}',
    '{"errorDetail":{"message":"The command returned a non-zero code: 1"},"error":"The command returned a non-zero code: 1"}',
    'not json'
));
$log = $runtime->parseBuildStream($stream);
workspace_runtime_assert($log['error'] === 'The command returned a non-zero code: 1' && $log['imageId'] === 'sha256:123', 'errors and the image id are found');
workspace_runtime_assert($log['lines'] === array('Step 1/2 : FROM python:3.12-slim', 'Pulling from library/python 3.12-slim', 'Installed 5 packages',
    'ERROR: The command returned a non-zero code: 1', 'not json'), 'progress, repeats and colors are left out: '.json_encode($log['lines']));

// A fast first response can arrive before Bootstrap finishes fading the log
// modal in. Poll ownership follows the selected runtime key, not :visible.
$runtimeUi = file_get_contents(dirname(__DIR__).'/assets/js/workspace-runtimes.js');
workspace_runtime_assert(strpos($runtimeUi, "build.status === 'building' && $('#runtimeLogModal').data('key') === key") !== FALSE,
    'runtime build logs keep polling during the modal fade-in');
workspace_runtime_assert(strpos($runtimeUi, "$(this).data('key', '')") !== FALSE,
    'closing the build log releases its poll ownership');

echo 'workspace runtime: '.$checks." checks passed\n";

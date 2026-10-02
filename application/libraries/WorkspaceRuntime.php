<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_WORKSPACE_RUNTIME_TEST')) exit('No direct script access allowed');

/**
 * Workspace runtimes: the images projects develop in and jobs can run in
 * (doc/jobseeker/Architecture/workspace-runtimes.md).
 *
 * A runtime is a recipe (a Python version with packages, a Conda
 * environment.yml, a Dockerfile, or a project's devcontainer.json). Its build
 * has two images: the runtime itself, which Docker jobs can use as it is, and
 * an IDE variant with the VS Code server from the workspace toolkit on top,
 * which editor deployments run. Both are tagged by a hash of what built them.
 *
 * This class turns recipes into build files, deployments into Docker
 * container specs, and build contexts into tar archives. It never talks to
 * Docker or the database, so it can be tested on its own
 * (scripts/test-workspace-runtime.php).
 */
class WorkspaceRuntime
{
    const DEFAULT_KEY = 'default';
    const DEVCONTAINER_KEY = 'devcontainer';
    const IMAGE_REPOSITORY = 'jobseeker-runtime';
    const TOOLKIT_IMAGE = 'jobseeker-workspace-toolkit:local';
    const UV_IMAGE = 'ghcr.io/astral-sh/uv:0.12.6';
    const CONDA_IMAGE = 'condaforge/miniforge3:26.7.2-0';
    /** Where dev container features are fetched during an editor image build. */
    const FEATURES_IMAGE = 'alpine:3.20';
    const MAX_FEATURES = 20;
    const CONDA_ENV = '/opt/conda/envs/jobseeker';
    const WORKSPACE_HOME = '/home/workspace';
    /** Where editors see the repository, as the Default editor does. */
    const WORKSPACE_REPOSITORY = '/home/workspace/repository';
    /** Folders that never belong in a build context. */
    const SKIPPED_FOLDERS = array('.git', '.venv', 'venv', 'node_modules', '__pycache__', '.pytest_cache', '.mypy_cache', '.ruff_cache', '.jobseeker-dag');
    const MAX_CONTEXT_FILES = 2000;
    const MAX_CONTEXT_BYTES = 209715200;
    /** Bumped when generated files change, so every runtime rebuilds. */
    const RECIPE_VERSION = '1';

    public function pythonVersions()
    {
        return array('3.14', '3.13', '3.12', '3.11', '3.10');
    }

    /** Kinds of catalog runtime, in the order forms show them. */
    public function kinds()
    {
        return array(
            'python' => array('label' => 'Python', 'icon' => 'fa-code', 'help' => 'A Python version with the system and Python packages its projects need. uv and Git included.'),
            'conda' => array('label' => 'Conda', 'icon' => 'fa-flask', 'help' => 'A Conda environment.yml on Miniforge, for GDAL, CUDA builds and other binary stacks.'),
            'dockerfile' => array('label' => 'Dockerfile', 'icon' => 'fa-cube', 'help' => 'Any Dockerfile. Base it on a glibc Linux (Debian, Ubuntu, RHEL); Alpine cannot host the editor.')
        );
    }

    /** The runtimes a fresh catalog starts with; nothing is built until used. */
    public function presets()
    {
        return array(
            array(
                'key' => 'python-3-13',
                'name' => 'Python 3.13',
                'description' => 'Slim Python 3.13 with uv and Git. Add the packages every project on it should have.',
                'kind' => 'python',
                'spec' => array('python_version' => '3.13', 'system_packages' => array(), 'python_packages' => '')
            ),
            array(
                'key' => 'conda',
                'name' => 'Conda (Miniforge)',
                'description' => 'Conda-forge packages in a "jobseeker" environment: the scientific stack with its native libraries.',
                'kind' => 'conda',
                'spec' => array('system_packages' => array(), 'environment_yml' => implode("\n", array(
                    'channels:',
                    '  - conda-forge',
                    'dependencies:',
                    '  - python=3.12',
                    '  - pip',
                    '  - pandas',
                    '  - pyarrow',
                    ''
                )))
            )
        );
    }

    /** @return string|FALSE a lower-case key such as "geo-python" */
    public function cleanKey($key)
    {
        $key = strtolower(trim((string) $key));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $key) || in_array($key, array(self::DEFAULT_KEY, self::DEVCONTAINER_KEY), TRUE)
            || preg_match('/^p[0-9]+$/', $key)) {
            return FALSE;
        }
        return $key;
    }

    /** A key made from a name: "Geo Python 3.12" becomes "geo-python-3-12". */
    public function keyFromName($name)
    {
        $key = strtolower(trim((string) $name));
        $key = trim(preg_replace('/[^a-z0-9]+/', '-', $key), '-');
        $key = substr($key, 0, 40);
        $key = rtrim($key, '-');
        if (strlen($key) < 2 || $this->cleanKey($key) === FALSE) {
            $key = 'runtime-'.substr(md5((string) $name), 0, 6);
        }
        return $key;
    }

    /** Debian package names, one per line or separated by spaces or commas. */
    public function cleanSystemPackages($packages)
    {
        $list = is_array($packages) ? $packages : preg_split('/[\s,]+/', (string) $packages);
        $clean = array();
        foreach ($list as $package) {
            $package = strtolower(trim((string) $package));
            if ($package === '') {
                continue;
            }
            if (! preg_match('/^[a-z0-9][a-z0-9.+-]{0,99}(?:=[A-Za-z0-9.+:~_-]{1,100})?$/', $package)) {
                return FALSE;
            }
            $clean[$package] = TRUE;
        }
        return array_keys($clean);
    }

    /**
     * Validates a runtime submitted from the Runtimes page.
     *
     * @return array ok, errors (field => message), runtime (key, name, description, kind, spec)
     */
    public function cleanRuntime(array $input)
    {
        $errors = array();
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) (isset($input['name']) ? $input['name'] : '')));
        if ($name === '' || strlen($name) > 100) {
            $errors['name'] = 'Give the runtime a name of up to 100 characters.';
        }
        $key = isset($input['key']) && trim((string) $input['key']) !== '' ? $this->cleanKey($input['key']) : $this->keyFromName($name);
        if ($key === FALSE) {
            $errors['key'] = 'Use 2 to 40 lower-case letters, digits and dashes. "default" and "devcontainer" are reserved.';
        }
        $description = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) (isset($input['description']) ? $input['description'] : '')));
        if (strlen($description) > 500) {
            $errors['description'] = 'Keep the description under 500 characters.';
        }
        $kind = isset($input['kind']) ? strtolower(trim((string) $input['kind'])) : '';
        if (! isset($this->kinds()[$kind])) {
            $errors['kind'] = 'Choose Python, Conda or Dockerfile.';
        }
        $spec = array();
        $systemPackages = $this->cleanSystemPackages(isset($input['system_packages']) ? $input['system_packages'] : '');
        if ($systemPackages === FALSE) {
            $errors['system_packages'] = 'System packages are Debian package names such as libpq-dev or unixodbc-dev.';
            $systemPackages = array();
        }
        if ($kind === 'python') {
            $version = isset($input['python_version']) ? trim((string) $input['python_version']) : '';
            if (! in_array($version, $this->pythonVersions(), TRUE)) {
                $errors['python_version'] = 'Choose a Python version.';
            }
            $packages = $this->cleanText(isset($input['python_packages']) ? $input['python_packages'] : '', 20000);
            if ($packages === FALSE) {
                $errors['python_packages'] = 'Keep the Python packages under 20,000 characters.';
            } else if (preg_match('/^\s*(-e\s|--editable|\.|\/|file:)/mi', $packages)) {
                $errors['python_packages'] = 'Use package requirements (pandas>=2.2, git+https://...); local paths cannot be installed into an image.';
            }
            $spec = array('python_version' => $version, 'system_packages' => $systemPackages, 'python_packages' => $packages === FALSE ? '' : $packages);
        } else if ($kind === 'conda') {
            $environment = $this->cleanText(isset($input['environment_yml']) ? $input['environment_yml'] : '', 50000);
            if ($environment === FALSE || trim($environment) === '') {
                $errors['environment_yml'] = 'Paste an environment.yml of up to 50,000 characters.';
            } else if (! preg_match('/^dependencies\s*:/m', $environment)) {
                $errors['environment_yml'] = 'The environment.yml needs a dependencies: list.';
            }
            $spec = array('system_packages' => $systemPackages, 'environment_yml' => $environment === FALSE ? '' : $environment);
        } else if ($kind === 'dockerfile') {
            $dockerfile = $this->cleanText(isset($input['dockerfile']) ? $input['dockerfile'] : '', 65536);
            if ($dockerfile === FALSE || ! preg_match('/^\s*FROM\s+\S+/mi', $dockerfile)) {
                $errors['dockerfile'] = 'Paste a Dockerfile (up to 64 KB) with a FROM line.';
            } else if (! empty($this->dockerfileSources($dockerfile))) {
                $errors['dockerfile'] = 'A catalog Dockerfile is built on its own, so COPY and ADD can only use --from or URLs. Put files a project needs in its .devcontainer instead.';
            }
            $spec = array('dockerfile' => $dockerfile === FALSE ? '' : $dockerfile);
        }
        $spec = array_merge($spec, $this->cleanRuntimeExtras($input, $errors));
        return array(
            'ok' => empty($errors),
            'errors' => $errors,
            'runtime' => array('key' => $key, 'name' => $name, 'description' => $description, 'kind' => $kind, 'spec' => $spec)
        );
    }

    /**
     * What makes any runtime a full dev container: VS Code extensions, dev
     * container features, forwarded ports, a post-create command and
     * environment variables, plus the template it started from.
     */
    private function cleanRuntimeExtras(array $input, array &$errors)
    {
        $extras = array('extensions' => array(), 'features' => array(), 'ports' => array(), 'post_create' => '', 'env' => array(), 'template' => '');
        $extensions = isset($input['extensions']) ? $input['extensions'] : array();
        foreach (is_array($extensions) ? $extensions : preg_split('/[\s,]+/', (string) $extensions) as $extension) {
            $extension = trim((string) $extension);
            if ($extension === '') {
                continue;
            }
            if (! $this->isExtensionId($extension)) {
                $errors['extensions'] = $extension.' is not an extension id such as ms-toolsai.jupyter.';
                continue;
            }
            $extras['extensions'][] = $extension;
        }
        $extras['extensions'] = array_values(array_unique($extras['extensions']));

        $features = isset($input['features']) ? $input['features'] : array();
        if (is_string($features) && trim($features) !== '') {
            $parsed = $this->parseJsonc($features);
            if (! is_array($parsed) || (! empty($parsed) && array_keys($parsed) === range(0, count($parsed) - 1))) {
                $errors['features'] = 'Features are a JSON object, as in devcontainer.json: {"ghcr.io/devcontainers/features/node:1": {"version": "lts"}}.';
                $parsed = array();
            }
            $features = $parsed;
        } else if (is_array($features) && ! empty($features) && array_keys($features) === range(0, count($features) - 1)) {
            // Already a list of ref and options, as templates and storage keep it.
            $map = array();
            foreach ($features as $feature) {
                if (is_array($feature) && isset($feature['ref'])) {
                    $map[(string) $feature['ref']] = isset($feature['options']) ? (array) $feature['options'] : array();
                }
            }
            $features = $map;
        }
        $featureWarnings = array();
        $extras['features'] = $this->storedFeatures($this->devcontainerFeatures(is_array($features) ? $features : array(), $featureWarnings));
        if (! empty($featureWarnings)) {
            $errors['features'] = implode(' ', $featureWarnings);
        }

        $ports = isset($input['ports']) ? $input['ports'] : array();
        foreach (is_array($ports) ? $ports : preg_split('/[\s,]+/', (string) $ports) as $port) {
            $port = trim((string) $port);
            if ($port === '') {
                continue;
            }
            if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                $errors['ports'] = 'Ports are numbers from 1 to 65535, such as 8000, 8501.';
                continue;
            }
            $extras['ports'][] = (int) $port;
        }
        $extras['ports'] = array_slice(array_values(array_unique($extras['ports'])), 0, 20);

        $postCreate = str_replace(array("\r\n", "\r"), "\n", trim((string) (isset($input['post_create']) ? $input['post_create'] : '')));
        if (strlen($postCreate) > 2000 || strpos($postCreate, "\0") !== FALSE) {
            $errors['post_create'] = 'Keep the post-create command under 2,000 characters.';
        } else {
            $extras['post_create'] = $postCreate;
        }

        $env = isset($input['env']) ? $input['env'] : array();
        if (! is_array($env)) {
            $lines = array();
            foreach (preg_split('/\r?\n/', (string) $env) as $line) {
                if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                $lines[trim($parts[0])] = isset($parts[1]) ? $parts[1] : NULL;
            }
            $env = $lines;
        }
        foreach ($env as $name => $value) {
            if ($value === NULL || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', (string) $name) || strpos((string) $name, 'JOBSEEKER_') === 0 || ! is_scalar($value)) {
                $errors['env'] = 'Environment variables are NAME=value lines; names starting with JOBSEEKER_ are reserved.';
                continue;
            }
            $extras['env'][(string) $name] = (string) $value;
        }

        $template = isset($input['template']) ? trim((string) $input['template']) : '';
        $extras['template'] = isset($this->templates()[$template]) ? $template : '';
        return $extras;
    }

    private function isExtensionId($extension)
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*\.[A-Za-z0-9][A-Za-z0-9.-]*(@[0-9A-Za-z.+-]+)?$/', (string) $extension);
    }

    /** Features as stored: a list of ref and options (an array, never an object). */
    private function storedFeatures(array $features)
    {
        $stored = array();
        foreach ($features as $feature) {
            $stored[] = array('ref' => (string) $feature['ref'], 'options' => (array) $feature['options']);
        }
        return $stored;
    }

    /** Categories of the template gallery, in the order it shows them. */
    public function templateCategories()
    {
        return array(
            'essentials' => array('label' => 'Essentials', 'icon' => 'fa-star'),
            'data-engineering' => array('label' => 'Data engineering', 'icon' => 'fa-database'),
            'streaming' => array('label' => 'Streaming', 'icon' => 'fa-exchange'),
            'analytics' => array('label' => 'Analytics & visualization', 'icon' => 'fa-area-chart'),
            'apis' => array('label' => 'APIs & apps', 'icon' => 'fa-plug'),
            'ml' => array('label' => 'Machine learning & AI', 'icon' => 'fa-cogs'),
            'big-data' => array('label' => 'Big data', 'icon' => 'fa-server'),
            'geospatial' => array('label' => 'Geospatial', 'icon' => 'fa-globe'),
            'cloud' => array('label' => 'Cloud & DevOps', 'icon' => 'fa-cloud')
        );
    }

    /**
     * Ready-made runtimes to start from. Each is a complete recipe (packages,
     * VS Code extensions, dev container features, ports), added to the
     * catalog as it is or adapted first, and exportable as a .devcontainer.
     */
    public function templates()
    {
        $python = function($version, $packages, array $extras = array()) {
            return array_merge(array('python_version' => $version, 'system_packages' => array(), 'python_packages' => implode("\n", $packages)."\n"), $extras);
        };
        $conda = function(array $dependencies, array $extras = array()) {
            return array_merge(array('system_packages' => array(), 'environment_yml' => implode("\n", array_merge(array('channels:', '  - conda-forge', 'dependencies:'),
                array_map(function($dependency) { return '  - '.$dependency; }, $dependencies)))."\n"), $extras);
        };
        return array(
            'python-essentials' => array(
                'name' => 'Python essentials', 'category' => 'essentials', 'icon' => 'fa-code',
                'description' => 'Python 3.13 with the everyday toolbox: HTTP clients, typed settings, a CLI framework and testing.',
                'kind' => 'python',
                'spec' => $python('3.13', array('requests', 'httpx', 'pydantic>=2', 'pydantic-settings', 'python-dotenv', 'rich', 'typer', 'tenacity', 'pytest', 'pytest-cov'),
                    array('extensions' => array('tamasfe.even-better-toml')))
            ),
            'web-scraping' => array(
                'name' => 'Web scraping', 'category' => 'essentials', 'icon' => 'fa-bug',
                'description' => 'Fetch and parse the web: httpx, BeautifulSoup, lxml, selectolax, parsel and Scrapy with retries.',
                'kind' => 'python',
                'spec' => $python('3.12', array('httpx', 'requests', 'beautifulsoup4', 'lxml', 'selectolax', 'parsel', 'scrapy', 'tenacity'))
            ),
            'data-engineering' => array(
                'name' => 'Data engineering', 'category' => 'data-engineering', 'icon' => 'fa-database',
                'description' => 'ETL on DataFrames and Arrow: pandas, Polars, DuckDB, SQLAlchemy, Postgres, MySQL and ODBC drivers, cloud storage.',
                'kind' => 'python',
                'spec' => $python('3.12', array('pandas>=2.2', 'polars', 'pyarrow', 'duckdb', 'sqlalchemy>=2', 'psycopg[binary]', 'pymysql', 'pyodbc', 'openpyxl', 'fsspec', 's3fs'),
                    array('system_packages' => array('unixodbc'), 'extensions' => array('mtxr.sqltools', 'mechatroner.rainbow-csv')))
            ),
            'dbt' => array(
                'name' => 'dbt analytics engineering', 'category' => 'data-engineering', 'icon' => 'fa-sitemap',
                'description' => 'dbt with the DuckDB and Postgres adapters, SQLFluff linting and the dbt Power User extension.',
                'kind' => 'python',
                'spec' => $python('3.12', array('dbt-core', 'dbt-duckdb', 'dbt-postgres', 'sqlfluff'),
                    array('extensions' => array('innoverio.vscode-dbt-power-user', 'dorzey.vscode-sqlfluff', 'samuelcolvin.jinjahtml')))
            ),
            'data-quality' => array(
                'name' => 'Data quality & contracts', 'category' => 'data-engineering', 'icon' => 'fa-check-square-o',
                'description' => 'Validate and test data: Pandera schemas, Pydantic models, property-based tests with Hypothesis, fake data with Faker.',
                'kind' => 'python',
                'spec' => $python('3.12', array('pandas>=2.2', 'polars', 'duckdb', 'pandera', 'pydantic>=2', 'hypothesis', 'faker', 'pytest'),
                    array('extensions' => array('mechatroner.rainbow-csv')))
            ),
            'streaming' => array(
                'name' => 'Streaming & messaging', 'category' => 'streaming', 'icon' => 'fa-exchange',
                'description' => 'Event clients for Kafka, RabbitMQ, MQTT and Redis, with Avro and Protobuf serialization.',
                'kind' => 'python',
                'spec' => $python('3.12', array('confluent-kafka', 'aiokafka', 'redis', 'pika', 'paho-mqtt', 'fastavro', 'protobuf', 'orjson'),
                    array('extensions' => array('zxh404.vscode-proto3')))
            ),
            'stream-processing' => array(
                'name' => 'Stream processing', 'category' => 'streaming', 'icon' => 'fa-bolt',
                'description' => 'Stateful stream processing in Python on Kafka with Quix Streams and Bytewax.',
                'kind' => 'python',
                'spec' => $python('3.12', array('quixstreams', 'bytewax', 'confluent-kafka', 'orjson'))
            ),
            'dashboards' => array(
                'name' => 'Dashboards & data apps', 'category' => 'analytics', 'icon' => 'fa-area-chart',
                'description' => 'Interactive data apps with Streamlit, Dash and Panel, charted with Plotly, Altair, Matplotlib and Seaborn.',
                'kind' => 'python',
                'spec' => $python('3.12', array('streamlit', 'dash', 'panel', 'plotly', 'altair', 'matplotlib', 'seaborn', 'pandas>=2.2'),
                    array('ports' => array(8501, 8050, 5006)))
            ),
            'notebooks' => array(
                'name' => 'Jupyter notebooks', 'category' => 'analytics', 'icon' => 'fa-book',
                'description' => 'Notebooks right in VS Code with the Jupyter extension, widgets and the pandas plotting stack.',
                'kind' => 'python',
                'spec' => $python('3.12', array('ipykernel', 'ipywidgets', 'jupyterlab', 'pandas>=2.2', 'numpy', 'matplotlib', 'seaborn', 'plotly'),
                    array('extensions' => array('ms-toolsai.jupyter', 'mechatroner.rainbow-csv'), 'ports' => array(8888)))
            ),
            'r-data-science' => array(
                'name' => 'R for data science', 'category' => 'analytics', 'icon' => 'fa-bar-chart',
                'description' => 'R with the tidyverse and a Jupyter kernel from conda-forge, side by side with Python.',
                'kind' => 'conda',
                'spec' => $conda(array('r-base', 'r-tidyverse', 'r-irkernel', 'r-languageserver', 'python=3.12', 'pip', 'ipykernel'),
                    array('extensions' => array('REditorSupport.r', 'ms-toolsai.jupyter')))
            ),
            'fastapi' => array(
                'name' => 'API development (FastAPI)', 'category' => 'apis', 'icon' => 'fa-plug',
                'description' => 'REST APIs with FastAPI and Uvicorn, SQLModel and Alembic for data, async tests, and a REST client in the editor.',
                'kind' => 'python',
                'spec' => $python('3.13', array('fastapi', 'uvicorn[standard]', 'pydantic-settings', 'sqlmodel', 'alembic', 'httpx', 'python-multipart', 'pytest', 'pytest-asyncio'),
                    array('extensions' => array('humao.rest-client', '42Crunch.vscode-openapi'), 'ports' => array(8000)))
            ),
            'fullstack' => array(
                'name' => 'Full stack (Python + Node.js)', 'category' => 'apis', 'icon' => 'fa-window-maximize',
                'description' => 'A Python API with a JavaScript front end: FastAPI plus Node.js LTS from its dev container feature, ESLint and Prettier.',
                'kind' => 'python',
                'spec' => $python('3.13', array('fastapi', 'uvicorn[standard]', 'httpx', 'pytest'),
                    array('features' => array(array('ref' => 'ghcr.io/devcontainers/features/node:1', 'options' => array('version' => 'lts'))),
                        'extensions' => array('dbaeumer.vscode-eslint', 'esbenp.prettier-vscode'), 'ports' => array(8000, 5173, 3000)))
            ),
            'machine-learning' => array(
                'name' => 'Machine learning', 'category' => 'ml', 'icon' => 'fa-cogs',
                'description' => 'Classic ML: scikit-learn, XGBoost and LightGBM, tuning with Optuna, tracking with MLflow, notebooks in VS Code.',
                'kind' => 'python',
                'spec' => $python('3.12', array('scikit-learn', 'xgboost', 'lightgbm', 'optuna', 'mlflow', 'pandas>=2.2', 'matplotlib', 'seaborn', 'ipykernel'),
                    array('system_packages' => array('libgomp1'), 'extensions' => array('ms-toolsai.jupyter'), 'ports' => array(5000)))
            ),
            'deep-learning' => array(
                'name' => 'Deep learning (PyTorch)', 'category' => 'ml', 'icon' => 'fa-microchip',
                'description' => 'PyTorch and torchvision for CPU, with Hugging Face Transformers, Datasets and Accelerate.',
                'kind' => 'python',
                'spec' => $python('3.12', array('--extra-index-url https://download.pytorch.org/whl/cpu', 'torch', 'torchvision', 'transformers', 'datasets', 'accelerate', 'safetensors', 'ipykernel'),
                    array('system_packages' => array('libgomp1'), 'extensions' => array('ms-toolsai.jupyter')))
            ),
            'llm-apps' => array(
                'name' => 'LLM & AI apps', 'category' => 'ml', 'icon' => 'fa-comments',
                'description' => 'Build with language models: the Anthropic and OpenAI SDKs, MCP, LangChain, token counting, PDFs and a Chroma vector store.',
                'kind' => 'python',
                'spec' => $python('3.12', array('anthropic', 'openai', 'mcp', 'langchain-core', 'langchain-text-splitters', 'tiktoken', 'chromadb', 'pypdf', 'python-dotenv'))
            ),
            'pyspark' => array(
                'name' => 'Apache Spark (PySpark)', 'category' => 'big-data', 'icon' => 'fa-server',
                'description' => 'Spark in local mode with PySpark and Delta Lake on OpenJDK 21; the Spark UI on port 4040.',
                'kind' => 'python',
                'spec' => $python('3.12', array('pyspark', 'delta-spark', 'pandas>=2.2', 'pyarrow'),
                    array('system_packages' => array('openjdk-21-jre-headless', 'procps'), 'env' => array('PYSPARK_PYTHON' => 'python3'), 'ports' => array(4040)))
            ),
            'geospatial' => array(
                'name' => 'Geospatial', 'category' => 'geospatial', 'icon' => 'fa-globe',
                'description' => 'GDAL, GeoPandas, Rasterio, Shapely and PyProj from conda-forge, with Folium maps and notebooks.',
                'kind' => 'conda',
                'spec' => $conda(array('python=3.12', 'pip', 'gdal', 'geopandas', 'rasterio', 'shapely', 'pyproj', 'fiona', 'folium', 'ipykernel'),
                    array('extensions' => array('ms-toolsai.jupyter')))
            ),
            'cloud-devops' => array(
                'name' => 'Cloud & DevOps', 'category' => 'cloud', 'icon' => 'fa-cloud',
                'description' => 'AWS, Azure and Google Cloud SDKs for Python, plus the AWS and Azure CLIs and Terraform from dev container features.',
                'kind' => 'python',
                'spec' => $python('3.12', array('boto3', 'azure-identity', 'azure-storage-blob', 'google-cloud-storage'),
                    array('features' => array(
                        array('ref' => 'ghcr.io/devcontainers/features/aws-cli:1', 'options' => array()),
                        array('ref' => 'ghcr.io/devcontainers/features/azure-cli:1', 'options' => array()),
                        array('ref' => 'ghcr.io/devcontainers/features/terraform:1', 'options' => array())
                    ), 'extensions' => array('redhat.vscode-yaml')))
            )
        );
    }

    /** Python a job may require when its runtime's is unknown (a custom Dockerfile, say). */
    const LENIENT_PYTHON = '3.10';

    /**
     * The Python version a runtime provides ("3.12"), read from its recipe, or
     * '' when the recipe does not say (a Dockerfile not based on python:3.x).
     */
    public function pythonVersionOf(array $runtime)
    {
        $spec = isset($runtime['spec']) ? (array) $runtime['spec'] : array();
        if ($runtime['kind'] === 'python') {
            return isset($spec['python_version']) && in_array($spec['python_version'], $this->pythonVersions(), TRUE) ? $spec['python_version'] : '';
        }
        if ($runtime['kind'] === 'conda') {
            return preg_match('/^\s*-\s*python\s*=+\s*(3\.[0-9]{1,2})\b/m', (string) (isset($spec['environment_yml']) ? $spec['environment_yml'] : ''), $matches) ? $matches[1] : '';
        }
        return $this->pythonVersionOfDockerfile(isset($spec['dockerfile']) ? $spec['dockerfile'] : '');
    }

    /** "3.12" from the last FROM python:3.12... line (or image reference) of a Dockerfile. */
    public function pythonVersionOfDockerfile($text)
    {
        $version = '';
        if (preg_match_all('#^\s*FROM\s+(?:--platform=\S+\s+)?(?:\S*/)?python:(3\.[0-9]{1,2})\b#mi', (string) $text, $matches)) {
            $version = end($matches[1]);
        } else if (preg_match('#^(?:\S*/)?python:(3\.[0-9]{1,2})\b#i', trim((string) $text), $matches)) {
            $version = $matches[1];
        }
        return $version;
    }

    /** A template as a runtime draft: what the Runtimes page's editor starts from. */
    public function templateRuntime($templateKey)
    {
        $templates = $this->templates();
        if (! isset($templates[$templateKey])) {
            return FALSE;
        }
        $template = $templates[$templateKey];
        $spec = array_merge(array('extensions' => array(), 'features' => array(), 'ports' => array(), 'post_create' => '', 'env' => array()), $template['spec']);
        $spec['template'] = $templateKey;
        return array('key' => $templateKey, 'name' => $template['name'], 'description' => $template['description'], 'kind' => $template['kind'], 'spec' => $spec);
    }

    /** The main packages of a runtime, for cards: from requirements or environment.yml. */
    public function highlights(array $runtime, $limit = 8)
    {
        $spec = $runtime['spec'];
        $lines = array();
        if ($runtime['kind'] === 'python') {
            $lines = preg_split('/\r?\n/', (string) (isset($spec['python_packages']) ? $spec['python_packages'] : ''));
        } else if ($runtime['kind'] === 'conda' && preg_match('/^dependencies\s*:\s*\n((?:\s+-.*\n?)+)/m', (string) (isset($spec['environment_yml']) ? $spec['environment_yml'] : ''), $matches)) {
            $lines = preg_split('/\r?\n/', $matches[1]);
        }
        $names = array();
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*-\s*/', '', $line));
            if ($line === '' || $line[0] === '#' || $line[0] === '-' || strpos($line, ':') !== FALSE && strpos($line, '://') === FALSE && substr($line, -1) === ':') {
                continue;
            }
            $name = preg_split('/[\s\[<>=!~;@(]/', $line, 2)[0];
            if ($name !== '' && ! in_array(strtolower($name), array('pip', 'python', 'ipykernel'), TRUE)) {
                $names[] = $name;
            }
        }
        foreach (isset($spec['features']) ? (array) $spec['features'] : array() as $feature) {
            if (preg_match('#/([a-z0-9._-]+)(:[^/]*)?$#i', (string) $feature['ref'], $matches)) {
                $names[] = $matches[1];
            }
        }
        return array_slice(array_values(array_unique($names)), 0, $limit);
    }

    /**
     * A runtime as a .devcontainer folder, to use it anywhere dev containers
     * work: VS Code with the Dev Containers extension, GitHub Codespaces, or a
     * project's Dev container runtime in JobSeeker.
     *
     * @return array project-relative path => content
     */
    public function devcontainerFiles(array $runtime)
    {
        $spec = array_merge(array('extensions' => array(), 'features' => array(), 'ports' => array(), 'post_create' => '', 'env' => array()), (array) $runtime['spec']);
        $name = trim((string) $runtime['name']);
        $generated = '# Generated by JobSeeker for the "'.$runtime['key'].'" workspace runtime.'."\n".'# Jobs can run this image; editors run it with the VS Code server on top.'."\n";
        $files = array();
        foreach ($this->buildFiles($runtime['key'], $runtime['kind'], $spec) as $path => $content) {
            if ($path === 'Dockerfile' && strpos($content, $generated) === 0) {
                $content = '# The "'.$name.'" runtime, exported from JobSeeker as a dev container.'."\n".'# Build it with the VS Code Dev Containers extension, Codespaces or JobSeeker.'."\n".substr($content, strlen($generated));
            }
            $files['.devcontainer/'.$path] = $content;
        }
        $pythons = array('python' => '/usr/local/bin/python', 'conda' => self::CONDA_ENV.'/bin/python');
        $definition = array('name' => $name, 'build' => array('dockerfile' => 'Dockerfile', 'context' => '.'));
        if (! empty($spec['features'])) {
            $definition['features'] = array();
            foreach ($spec['features'] as $feature) {
                $definition['features'][(string) $feature['ref']] = (object) (array) $feature['options'];
            }
        }
        if (! empty($spec['ports'])) {
            $definition['forwardPorts'] = array_map('intval', (array) $spec['ports']);
        }
        if (! empty($spec['env'])) {
            $definition['containerEnv'] = (object) $spec['env'];
        }
        $vscode = array('extensions' => array_values(array_unique(array_merge(array('ms-python.python', 'ms-python.debugpy', 'charliermarsh.ruff'), (array) $spec['extensions']))));
        if (isset($pythons[$runtime['kind']])) {
            $vscode['settings'] = (object) array('python.defaultInterpreterPath' => $pythons[$runtime['kind']]);
        }
        $definition['customizations'] = array('vscode' => $vscode);
        if ($spec['post_create'] !== '') {
            $definition['postCreateCommand'] = $spec['post_create'];
        }
        // Two-space indentation, as VS Code writes devcontainer.json.
        $json = preg_replace_callback('/^( +)/m', function($indent) {
            return str_repeat(' ', (int) (strlen($indent[1]) / 2));
        }, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $files = array('.devcontainer/devcontainer.json' => "// ".$name." — a JobSeeker workspace runtime as a dev container.\n// Reopen a folder containing it in VS Code (Dev Containers extension) or Codespaces.\n".$json."\n") + $files;
        $files['.devcontainer/README.md'] = implode("\n", array(
            '# '.$name,
            '',
            trim((string) $runtime['description']),
            '',
            'This folder is a [dev container](https://containers.dev) exported from a JobSeeker workspace runtime.',
            '',
            '- **VS Code on your machine**: put `.devcontainer/` at the root of a project, install the Dev Containers',
            '  extension and run **Dev Containers: Reopen in Container**. A download from JobSeeker installs the',
            '  JobSeeker SDK (`jobseeker-sdk/`), so jobs run locally as they do in JobSeeker; connectors, Data Assets',
            '  and Transaction Monitoring still need a JobSeeker to talk to.',
            '- **GitHub Codespaces**: commit `.devcontainer/` and create a codespace.',
            '- **JobSeeker**: commit `.devcontainer/` to a project and choose the **Dev container** runtime for it',
            '  under VS Code in the sidebar; everyone who opens the project gets this environment.',
            '',
            'Edit the Dockerfile'.($runtime['kind'] === 'python' ? ' and requirements.txt' : ($runtime['kind'] === 'conda' ? ' and environment.yml' : '')).' to change what it installs.',
            ''
        ));
        return $files;
    }

    /**
     * A downloaded .devcontainer with the JobSeeker SDK in it, installed into
     * the image, so `from jobseeker import JobSeeker` works on a laptop as it
     * does in JobSeeker (whose editors and jobs install the SDK themselves).
     */
    public function withSdk(array $files, $sdkRoot)
    {
        $sdkRoot = rtrim((string) $sdkRoot, '/\\');
        if (! is_file($sdkRoot.'/pyproject.toml') || ! isset($files['.devcontainer/Dockerfile'])) {
            return $files;
        }
        $sdk = array();
        $this->collectSdk($sdkRoot, '', $sdk);
        if (empty($sdk)) {
            return $files;
        }
        foreach ($sdk as $path => $content) {
            $files['.devcontainer/jobseeker-sdk/'.$path] = $content;
        }
        $files['.devcontainer/Dockerfile'] = rtrim($files['.devcontainer/Dockerfile'], "\n")."\n".implode("\n", array(
            '# The JobSeeker SDK, so jobs run here as they do in JobSeeker.',
            'COPY jobseeker-sdk /opt/jobseeker-sdk',
            'RUN if command -v python >/dev/null 2>&1; then python -m pip install --no-cache-dir /opt/jobseeker-sdk; \\',
            '    else echo "No python on PATH: the JobSeeker SDK was not installed."; fi',
            ''
        ));
        return $files;
    }

    private function collectSdk($root, $relative, array &$files)
    {
        $directory = $root.($relative === '' ? '' : '/'.$relative);
        foreach ((array) @scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '__pycache__' || $entry === 'build' || substr($entry, -9) === '.egg-info' || substr($entry, -4) === '.pyc') {
                continue;
            }
            $path = $relative === '' ? $entry : $relative.'/'.$entry;
            if (is_dir($root.'/'.$path) && ! is_link($root.'/'.$path)) {
                $this->collectSdk($root, $path, $files);
            } else if (is_file($root.'/'.$path) && count($files) < 500) {
                $files[$path] = (string) file_get_contents($root.'/'.$path);
            }
        }
    }

    /** Features for features.json: options always a JSON object. */
    public function buildFeatures(array $features)
    {
        $list = array();
        foreach ($features as $feature) {
            $list[] = array('ref' => (string) $feature['ref'], 'options' => (object) (isset($feature['options']) ? (array) $feature['options'] : array()));
        }
        return $list;
    }

    /** Normalized text with LF line endings, or FALSE when too long or binary. */
    private function cleanText($value, $limit)
    {
        $value = str_replace(array("\r\n", "\r"), "\n", (string) $value);
        if (strlen($value) > $limit || strpos($value, "\0") !== FALSE) {
            return FALSE;
        }
        return rtrim($value)."\n" === "\n" ? '' : rtrim($value)."\n";
    }

    /** Warnings that do not block saving a runtime. */
    public function runtimeWarnings($kind, $spec)
    {
        $warnings = array();
        if ($kind === 'dockerfile' && preg_match('/^\s*FROM\s+(?:--platform=\S+\s+)?\S*alpine/mi', (string) $spec['dockerfile'])) {
            $warnings[] = 'This Dockerfile starts from Alpine. Jobs can run it, but the editor needs glibc, so VS Code will not open in it.';
        }
        return $warnings;
    }

    /**
     * The build context of a catalog runtime's image.
     *
     * @return array path => content, with the Dockerfile first
     */
    public function buildFiles($key, $kind, $spec)
    {
        $systemPackages = isset($spec['system_packages']) ? (array) $spec['system_packages'] : array();
        $header = array(
            '# Generated by JobSeeker for the "'.$key.'" workspace runtime.',
            '# Jobs can run this image; editors run it with the VS Code server on top.'
        );
        $files = array();
        if ($kind === 'python') {
            $version = in_array($spec['python_version'], $this->pythonVersions(), TRUE) ? $spec['python_version'] : '3.13';
            $lines = array_merge($header, array(
                'FROM python:'.$version.'-slim',
                'LABEL org.jobseeker.runtime="'.$key.'"',
                'ENV PYTHONDONTWRITEBYTECODE=1 \\',
                '    PYTHONUNBUFFERED=1 \\',
                '    PIP_DISABLE_PIP_VERSION_CHECK=1 \\',
                '    UV_LINK_MODE=copy \\',
                '    JOBSEEKER_RUNTIME='.$key.' \\',
                '    JOBSEEKER_WORKSPACE_PYTHON=/usr/local/bin/python'.$version,
                'COPY --from='.self::UV_IMAGE.' /uv /uvx /usr/local/bin/',
                'RUN apt-get update \\',
                '    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends '.implode(' ', array_merge(array('ca-certificates', 'curl', 'git', 'openssh-client'), $systemPackages)).' \\',
                '    && rm -rf /var/lib/apt/lists/*'
            ));
            if (trim((string) $spec['python_packages']) !== '') {
                $files['requirements.txt'] = (string) $spec['python_packages'];
                $lines[] = 'COPY requirements.txt /opt/jobseeker-runtime/requirements.txt';
                $lines[] = 'RUN uv pip install --system --no-cache -r /opt/jobseeker-runtime/requirements.txt';
            }
            return array('Dockerfile' => implode("\n", $lines)."\n") + $files;
        }
        if ($kind === 'conda') {
            $files['environment.yml'] = (string) $spec['environment_yml'];
            $lines = array_merge($header, array(
                'FROM '.self::CONDA_IMAGE,
                'LABEL org.jobseeker.runtime="'.$key.'"',
                'ENV PATH='.self::CONDA_ENV.'/bin:/opt/conda/bin:$PATH \\',
                '    CONDA_DEFAULT_ENV=jobseeker \\',
                '    CONDA_PREFIX='.self::CONDA_ENV.' \\',
                '    PYTHONDONTWRITEBYTECODE=1 \\',
                '    PYTHONUNBUFFERED=1 \\',
                '    PIP_DISABLE_PIP_VERSION_CHECK=1 \\',
                '    JOBSEEKER_RUNTIME='.$key.' \\',
                '    JOBSEEKER_WORKSPACE_PYTHON='.self::CONDA_ENV.'/bin/python',
                'COPY --from='.self::UV_IMAGE.' /uv /uvx /usr/local/bin/',
                'RUN apt-get update \\',
                '    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends '.implode(' ', array_merge(array('ca-certificates', 'curl', 'git', 'openssh-client'), $systemPackages)).' \\',
                '    && rm -rf /var/lib/apt/lists/*',
                'COPY environment.yml /opt/jobseeker-runtime/environment.yml',
                '# The file\'s own name is ignored: the environment is always "jobseeker",',
                '# first on PATH, so jobs and login shells use it without activation.',
                'RUN mamba env create -y -n jobseeker -f /opt/jobseeker-runtime/environment.yml \\',
                '    && { '.self::CONDA_ENV.'/bin/python -m pip --version >/dev/null 2>&1 || mamba install -y -n jobseeker pip; } \\',
                '    && mamba clean -afy \\',
                '    && printf \'%s\\n\' \'export PATH="'.self::CONDA_ENV.'/bin:/opt/conda/bin:$PATH"\' > /etc/profile.d/jobseeker-conda.sh'
            ));
            return array('Dockerfile' => implode("\n", $lines)."\n") + $files;
        }
        return array('Dockerfile' => (string) $spec['dockerfile']);
    }

    /**
     * The IDE variant: the runtime image with /opt/jobseeker-ide on top, and
     * the dev container features of features.json when there are any, fetched
     * in a stage of their own.
     */
    public function ideDockerfile($runtimeImage, $withFeatures = FALSE)
    {
        $features = $withFeatures ? array(
            'FROM '.self::FEATURES_IMAGE.' AS jobseeker-features',
            'RUN apk add --no-cache ca-certificates curl jq tar',
            'COPY --from=jobseeker-ide /opt/jobseeker-ide/bin/jobseeker-features /usr/local/bin/jobseeker-features',
            'COPY features.json /features.json',
            'RUN sh /usr/local/bin/jobseeker-features /features.json /opt/jobseeker-features'
        ) : array();
        return implode("\n", array_merge(array(
            '# Generated by JobSeeker: the editor variant of a workspace runtime.',
            'FROM '.self::TOOLKIT_IMAGE.' AS jobseeker-ide'
        ), $features, array(
            'FROM '.$runtimeImage,
            'USER root',
            'COPY --from=jobseeker-ide /opt/jobseeker-ide /opt/jobseeker-ide'
        ), $withFeatures ? array('COPY --from=jobseeker-features /opt/jobseeker-features /opt/jobseeker-features') : array(), array(
            'ARG JOBSEEKER_UID=1000',
            'ARG JOBSEEKER_GID=1000',
            'ARG JOBSEEKER_IDE_EXTENSIONS=',
            'RUN ["/bin/sh", "/opt/jobseeker-ide/bin/jobseeker-ide-setup"]',
            // In the image, not only in the entrypoint: every process of the
            // editor (tasks, terminals, docker exec) builds the same .venv.
            'ENV HOME='.self::WORKSPACE_HOME.' \\',
            '    PATH=$PATH:/opt/jobseeker-ide/bin \\',
            '    JOBSEEKER_VENV_SYSTEM_SITE_PACKAGES=1',
            'WORKDIR '.self::WORKSPACE_HOME,
            'ENTRYPOINT ["/bin/sh", "/opt/jobseeker-ide/bin/jobseeker-ide"]',
            'CMD []',
            ''
        )));
    }

    /** First 12 hex characters of a SHA-256 over sorted files and extra parts. */
    public function hash(array $files, array $parts = array())
    {
        ksort($files, SORT_STRING);
        $context = hash_init('sha256');
        hash_update($context, 'jobseeker-runtime-'.self::RECIPE_VERSION."\0");
        foreach ($parts as $part) {
            hash_update($context, (string) $part."\0");
        }
        foreach ($files as $path => $content) {
            hash_update($context, $path."\0".(is_array($content) ? $content['hash'] : hash('sha256', (string) $content))."\0");
        }
        return substr(hash_final($context), 0, 12);
    }

    /** The image repository a runtime's images are tagged in. */
    public function imageName($imageKey)
    {
        return self::IMAGE_REPOSITORY.'/'.$imageKey;
    }

    public function runtimeImage($imageKey, $hash)
    {
        return $this->imageName($imageKey).':'.$hash;
    }

    public function ideImage($imageKey, $hash)
    {
        return $this->imageName($imageKey).':'.$hash.'-ide';
    }

    /** True for an image a runtime build produced, which exists only in the job runtime. */
    public function isRuntimeImage($image)
    {
        return (bool) preg_match('#^'.preg_quote(self::IMAGE_REPOSITORY, '#').'/[a-z0-9-]+:[0-9a-f]{12}$#', (string) $image);
    }

    /**
     * JSON with comments and trailing commas, as devcontainer.json allows.
     *
     * @return array|NULL
     */
    public function parseJsonc($text)
    {
        $text = (string) $text;
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }
        $out = '';
        $length = strlen($text);
        $inString = FALSE;
        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];
            if ($inString) {
                $out .= $character;
                if ($character === '\\' && $index + 1 < $length) {
                    $out .= $text[++$index];
                } else if ($character === '"') {
                    $inString = FALSE;
                }
                continue;
            }
            if ($character === '"') {
                $inString = TRUE;
                $out .= $character;
            } else if ($character === '/' && $index + 1 < $length && $text[$index + 1] === '/') {
                while ($index < $length && $text[$index] !== "\n") {
                    $index++;
                }
                $out .= "\n";
            } else if ($character === '/' && $index + 1 < $length && $text[$index + 1] === '*') {
                $end = strpos($text, '*/', $index + 2);
                $index = $end === FALSE ? $length : $end + 1;
                $out .= ' ';
            } else if ($character === ',') {
                // A comment may sit between a trailing comma and the bracket.
                $rest = ltrim(preg_replace('#^(\s*(//[^\n]*\n|/\*.*?\*/))*#s', '', substr($text, $index + 1)));
                if ($rest === '' || $rest[0] === '}' || $rest[0] === ']') {
                    continue;
                }
                $out .= $character;
            } else {
                $out .= $character;
            }
        }
        $decoded = json_decode($out, TRUE);
        return is_array($decoded) ? $decoded : NULL;
    }

    /**
     * A starting .devcontainer for a project: a slim Python image, the
     * project's shared packages from .devcontainer/requirements.txt, and
     * room for extensions, settings and a postCreateCommand.
     *
     * @return array project-relative path => content
     */
    public function devcontainerStarter($projectName, $pythonVersion = '3.13')
    {
        $pythonVersion = in_array($pythonVersion, $this->pythonVersions(), TRUE) ? $pythonVersion : '3.13';
        $name = trim(preg_replace('/[\x00-\x1F\x7F"\\\\]/', '', (string) $projectName));
        return array(
            '.devcontainer/devcontainer.json' => implode("\n", array(
                '{',
                '  // This project\'s environment. JobSeeker builds it when the project\'s runtime is',
                '  // "Dev container" (VS Code in the sidebar), adds the editor on top, and rebuilds it',
                '  // when this folder or the files the Dockerfile copies change. Commit it: everyone',
                '  // who opens the project gets the same environment.',
                '  "name": "'.($name === '' ? 'project' : $name).'",',
                '  "build": {',
                '    "dockerfile": "Dockerfile",',
                '    "context": ".."',
                '  },',
                '  "containerEnv": {},',
                '  "customizations": {',
                '    "vscode": {',
                '      // Extension ids from open-vsx.org, such as "redhat.vscode-yaml".',
                '      "extensions": [],',
                '      "settings": {}',
                '    }',
                '  },',
                '  // Runs once in each new editor container, from the project folder.',
                '  "postCreateCommand": ""',
                '}',
                ''
            )),
            '.devcontainer/Dockerfile' => implode("\n", array(
                '# This project\'s environment. JobSeeker adds the VS Code server, Git and curl on',
                '# top; stay on a glibc Linux (Debian, Ubuntu, RHEL) for the editor to run.',
                'FROM python:'.$pythonVersion.'-slim',
                'ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 PIP_DISABLE_PIP_VERSION_CHECK=1',
                'COPY --from='.self::UV_IMAGE.' /uv /uvx /usr/local/bin/',
                '# System libraries the project needs, such as libpq-dev or unixodbc-dev.',
                'RUN apt-get update \\',
                '    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl git openssh-client \\',
                '    && rm -rf /var/lib/apt/lists/*',
                '# Packages every job of the project uses; each job still declares its own.',
                'COPY .devcontainer/requirements.txt /opt/project/requirements.txt',
                'RUN if grep -qvE \'^[[:space:]]*(#|$)\' /opt/project/requirements.txt; then \\',
                '      uv pip install --system --no-cache -r /opt/project/requirements.txt; \\',
                '    fi',
                ''
            )),
            '.devcontainer/requirements.txt' => "# Packages every job of this project uses, one per line, such as:\n# pandas>=2.2\n"
        );
    }

    /** Where a project keeps its dev container definition, if it has one. */
    public function findDevcontainer($projectRoot)
    {
        $projectRoot = rtrim((string) $projectRoot, '/\\');
        foreach (array('.devcontainer/devcontainer.json', '.devcontainer.json') as $candidate) {
            if (is_file($projectRoot.'/'.$candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    /**
     * A project path made of $base and $relative, or FALSE when it leaves the
     * project. Both are relative to the project root; '' is the root.
     */
    public function projectPath($base, $relative)
    {
        $relative = str_replace('\\', '/', (string) $relative);
        if (strpos($relative, "\0") !== FALSE || $relative !== '' && $relative[0] === '/') {
            return FALSE;
        }
        $parts = array();
        foreach (explode('/', trim((string) $base, '/').'/'.$relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (empty($parts)) {
                    return FALSE;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }
        return implode('/', $parts);
    }

    /**
     * What JobSeeker takes from a devcontainer.json. Paths come back relative
     * to the project root.
     *
     * @param string $definitionPath project-relative path of the file
     * @return array ok, errors, warnings, image, dockerfile, context, args,
     *               target, env, extensions, settings, postCreate, postStart
     */
    public function devcontainer($jsonText, $definitionPath)
    {
        $result = array('ok' => FALSE, 'errors' => array(), 'warnings' => array(), 'image' => '', 'dockerfile' => '', 'context' => '',
            'args' => array(), 'target' => '', 'env' => array(), 'extensions' => array(), 'settings' => NULL, 'postCreate' => '', 'postStart' => '',
            'features' => array(), 'ports' => array());
        $config = $this->parseJsonc($jsonText);
        if ($config === NULL) {
            $result['errors'][] = $definitionPath.' is not valid JSON.';
            return $result;
        }
        $folder = dirname(str_replace('\\', '/', (string) $definitionPath));
        $folder = $folder === '.' ? '' : $folder;

        if (isset($config['dockerComposeFile'])) {
            $result['errors'][] = 'Dev containers defined with Docker Compose are not supported. Use image or build.dockerfile.';
            return $result;
        }
        $build = isset($config['build']) && is_array($config['build']) ? $config['build'] : array();
        $dockerfile = isset($build['dockerfile']) ? $build['dockerfile'] : (isset($config['dockerFile']) ? $config['dockerFile'] : '');
        if (is_string($dockerfile) && trim($dockerfile) !== '') {
            $path = $this->projectPath($folder, trim($dockerfile));
            $context = $this->projectPath($folder, isset($build['context']) ? (string) $build['context'] : (isset($config['context']) ? (string) $config['context'] : '.'));
            if ($path === FALSE || $path === '' || $context === FALSE) {
                $result['errors'][] = 'build.dockerfile and build.context must stay inside the project.';
                return $result;
            }
            $result['dockerfile'] = $path;
            $result['context'] = $context;
            foreach (isset($build['args']) && is_array($build['args']) ? $build['args'] : array() as $name => $value) {
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,99}$/', (string) $name) && is_scalar($value)) {
                    $result['args'][(string) $name] = (string) $value;
                }
            }
            if (isset($build['target']) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/', (string) $build['target'])) {
                $result['target'] = (string) $build['target'];
            }
        } else if (isset($config['image']) && is_string($config['image']) && trim($config['image']) !== '') {
            $image = trim($config['image']);
            if (strlen($image) > 255 || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/:@-]*$/', $image)) {
                $result['errors'][] = 'image is not a valid image reference.';
                return $result;
            }
            $result['image'] = $image;
        } else {
            $result['errors'][] = $definitionPath.' needs an image or a build.dockerfile.';
            return $result;
        }

        foreach (array('containerEnv', 'remoteEnv') as $field) {
            foreach (isset($config[$field]) && is_array($config[$field]) ? $config[$field] : array() as $name => $value) {
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', (string) $name) || ! is_scalar($value) || strpos((string) $name, 'JOBSEEKER_IDE_') === 0) {
                    continue;
                }
                if (strpos((string) $value, '${') !== FALSE) {
                    $result['warnings'][] = $field.'.'.$name.' uses a ${...} variable, which JobSeeker does not expand, so it was skipped.';
                    continue;
                }
                $result['env'][(string) $name] = (string) $value;
            }
        }
        $vscode = isset($config['customizations']['vscode']) && is_array($config['customizations']['vscode']) ? $config['customizations']['vscode'] : array();
        $extensions = isset($vscode['extensions']) ? $vscode['extensions'] : (isset($config['extensions']) ? $config['extensions'] : array());
        foreach (is_array($extensions) ? $extensions : array() as $extension) {
            if (is_string($extension) && preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*\.[A-Za-z0-9][A-Za-z0-9.-]*(@[0-9A-Za-z.+-]+)?$/', trim($extension))) {
                $result['extensions'][] = trim($extension);
            } else if (is_string($extension) && trim($extension) !== '') {
                $result['warnings'][] = 'Extension '.$extension.' is not a publisher.name identifier and was skipped.';
            }
        }
        $result['extensions'] = array_values(array_unique($result['extensions']));
        $settings = isset($vscode['settings']) ? $vscode['settings'] : (isset($config['settings']) ? $config['settings'] : NULL);
        if (is_array($settings) && ! empty($settings)) {
            $result['settings'] = $settings;
        }
        $result['postCreate'] = $this->devcontainerCommand(isset($config['postCreateCommand']) ? $config['postCreateCommand'] : NULL, 'postCreateCommand', $result['warnings']);
        $result['postStart'] = $this->devcontainerCommand(isset($config['postStartCommand']) ? $config['postStartCommand'] : NULL, 'postStartCommand', $result['warnings']);
        $result['features'] = $this->devcontainerFeatures(isset($config['features']) ? $config['features'] : array(), $result['warnings']);
        foreach (isset($config['forwardPorts']) && is_array($config['forwardPorts']) ? $config['forwardPorts'] : array() as $port) {
            if (is_int($port) && $port > 0 && $port < 65536) {
                $result['ports'][] = $port;
            }
        }
        foreach (array('mounts' => 'mounts', 'runArgs' => 'runArgs',
            'onCreateCommand' => 'onCreateCommand', 'updateContentCommand' => 'updateContentCommand', 'initializeCommand' => 'initializeCommand') as $field => $label) {
            if (! empty($config[$field])) {
                $result['warnings'][] = $label.' is not supported yet and was ignored.';
            }
        }
        foreach (array('remoteUser', 'containerUser') as $field) {
            if (! empty($config[$field])) {
                $result['warnings'][] = $field.' is ignored: editors run as the owner of the project files.';
            }
        }
        $result['ok'] = TRUE;
        return $result;
    }

    /**
     * The features of a devcontainer.json, as fetched and installed in order.
     * Only features published to an OCI registry are supported; local folders
     * and tarball URLs are reported and skipped.
     *
     * @return array list of array(ref, options)
     */
    public function devcontainerFeatures($features, &$warnings)
    {
        if (! is_array($features)) {
            return array();
        }
        $list = array();
        foreach ($features as $id => $options) {
            $id = trim((string) $id);
            if (! preg_match('#^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:[0-9]{1,5})?(/[a-z0-9]([a-z0-9._-]*[a-z0-9])?)+(:[A-Za-z0-9][A-Za-z0-9._-]{0,127}|@sha256:[0-9a-f]{64})?$#', $id)
                || strpos(explode('/', $id, 2)[0], '.') === FALSE) {
                $warnings[] = 'The feature '.$id.' was skipped: only features published to a registry (such as ghcr.io/devcontainers/features/node:1) are supported.';
                continue;
            }
            if (count($list) >= self::MAX_FEATURES) {
                $warnings[] = 'Only the first '.self::MAX_FEATURES.' features are installed.';
                break;
            }
            // A bare string is the feature's version option.
            $options = is_string($options) ? array('version' => $options) : (is_array($options) ? $options : array());
            $clean = array();
            foreach ($options as $name => $value) {
                if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', (string) $name) && (is_scalar($value) || $value === NULL)) {
                    $clean[(string) $name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                }
            }
            $list[] = array('ref' => preg_match('#(:[A-Za-z0-9][A-Za-z0-9._-]*|@sha256:[0-9a-f]{64})$#', substr($id, strpos($id, '/'))) ? $id : $id.':latest',
                'options' => (object) $clean);
        }
        return $list;
    }

    /** A lifecycle command as one shell line; object forms run their parts in turn. */
    private function devcontainerCommand($command, $field, &$warnings)
    {
        if (is_string($command)) {
            return trim($command);
        }
        if (! is_array($command) || empty($command)) {
            return '';
        }
        $isList = array_keys($command) === range(0, count($command) - 1);
        if ($isList) {
            return implode(' ', array_map('escapeshellarg', array_map('strval', $command)));
        }
        $parts = array();
        foreach ($command as $part) {
            $line = $this->devcontainerCommand($part, $field, $warnings);
            if ($line !== '') {
                $parts[] = '('.$line.')';
            }
        }
        if (count($parts) > 1) {
            $warnings[] = $field.' runs its commands one after another, not in parallel.';
        }
        return implode(' && ', $parts);
    }

    /**
     * Sources a Dockerfile copies from its build context: the files a
     * content hash must cover. --from copies and URLs are skipped.
     */
    public function dockerfileSources($dockerfileText)
    {
        $text = preg_replace('/\\\\[ \t]*\r?\n/', ' ', str_replace("\r\n", "\n", (string) $dockerfileText));
        $sources = array();
        foreach (explode("\n", $text) as $line) {
            if (! preg_match('/^\s*(COPY|ADD)\s+(.*)$/i', $line, $matches)) {
                continue;
            }
            $arguments = trim($matches[2]);
            // Flags come first, in either form: COPY --chown=1 ["a", "/b/"].
            $skip = FALSE;
            while (preg_match('/^(--[A-Za-z-]+(?:=\S*)?)\s*/', $arguments, $flag)) {
                $skip = $skip || strpos($flag[1], '--from') === 0;
                $arguments = substr($arguments, strlen($flag[0]));
            }
            if ($arguments !== '' && $arguments[0] === '[') {
                $list = json_decode($arguments, TRUE);
                $list = is_array($list) ? array_map('strval', $list) : array();
            } else {
                $list = preg_split('/\s+/', $arguments);
            }
            $words = array_values(array_filter($list, 'strlen'));
            if ($skip || count($words) < 2) {
                continue;
            }
            array_pop($words);
            foreach ($words as $source) {
                if (! preg_match('#^(https?://|git@)#i', $source)) {
                    $sources[] = $source;
                }
            }
        }
        return array_values(array_unique($sources));
    }

    /**
     * The files of a dev container build: the Dockerfile, and every context
     * file its COPY and ADD lines can reach. Anything else in the context
     * stays out, so editing code never rebuilds the image.
     *
     * @return array ok, message, files (context path => array(file, hash, mode)), dockerfile (context path)
     */
    public function devcontainerContext($projectRoot, array $definition)
    {
        $projectRoot = rtrim((string) $projectRoot, '/\\');
        if ($definition['image'] !== '') {
            return array('ok' => TRUE, 'message' => '', 'dockerfile' => 'Dockerfile',
                'files' => array('Dockerfile' => "# Generated by JobSeeker from devcontainer.json\nFROM ".$definition['image']."\n"));
        }
        $contextRoot = $projectRoot.($definition['context'] === '' ? '' : '/'.$definition['context']);
        $dockerfilePath = $projectRoot.'/'.$definition['dockerfile'];
        if (! is_file($dockerfilePath) || is_link($dockerfilePath)) {
            return array('ok' => FALSE, 'message' => $definition['dockerfile'].' does not exist.', 'files' => array(), 'dockerfile' => '');
        }
        if (! is_dir($contextRoot)) {
            return array('ok' => FALSE, 'message' => 'The build context '.($definition['context'] === '' ? '.' : $definition['context']).' does not exist.', 'files' => array(), 'dockerfile' => '');
        }
        $dockerfileText = (string) file_get_contents($dockerfilePath);
        $files = array();
        // Inside the context the Dockerfile keeps its path; outside it, it
        // travels as a file of its own.
        $contextPrefix = $definition['context'] === '' ? '' : $definition['context'].'/';
        $dockerfileName = $contextPrefix === '' || strpos($definition['dockerfile'], $contextPrefix) === 0
            ? substr($definition['dockerfile'], strlen($contextPrefix))
            : '.jobseeker.Dockerfile';
        $files[$dockerfileName] = $dockerfileText;
        $bytes = strlen($dockerfileText);
        foreach ($this->dockerfileSources($dockerfileText) as $source) {
            // Absolute sources are relative to the context, as Docker reads them.
            $pattern = $this->projectPath('', ltrim($source, '/'));
            if ($pattern === FALSE) {
                return array('ok' => FALSE, 'message' => 'COPY source '.$source.' leaves the build context.', 'files' => array(), 'dockerfile' => '');
            }
            $matches = $pattern === '' ? array($contextRoot) : (glob($contextRoot.'/'.$pattern, GLOB_NOSORT) ?: array());
            foreach ($matches as $match) {
                if (! $this->collectContext($contextRoot, $match, $files, $bytes)) {
                    return array('ok' => FALSE, 'message' => 'The dev container build copies more than '.self::MAX_CONTEXT_FILES.' files or '.(self::MAX_CONTEXT_BYTES >> 20).' MB. Copy only what the image needs.', 'files' => array(), 'dockerfile' => '');
                }
            }
        }
        foreach (array('.dockerignore', $dockerfileName.'.dockerignore') as $ignore) {
            if (is_file($contextRoot.'/'.$ignore) && ! isset($files[$ignore])) {
                $files[$ignore] = (string) file_get_contents($contextRoot.'/'.$ignore);
            }
        }
        return array('ok' => TRUE, 'message' => '', 'files' => $files, 'dockerfile' => $dockerfileName);
    }

    private function collectContext($contextRoot, $path, &$files, &$bytes)
    {
        $name = basename($path);
        if (is_link($path) || in_array($name, self::SKIPPED_FOLDERS, TRUE)) {
            return TRUE;
        }
        if (is_dir($path)) {
            foreach ((array) @scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..' && ! $this->collectContext($contextRoot, $path.'/'.$entry, $files, $bytes)) {
                    return FALSE;
                }
            }
            return TRUE;
        }
        if (! is_file($path)) {
            return TRUE;
        }
        $relative = ltrim(substr($path, strlen($contextRoot)), '/');
        if ($relative === '' || isset($files[$relative])) {
            return TRUE;
        }
        $bytes += (int) filesize($path);
        if (count($files) >= self::MAX_CONTEXT_FILES || $bytes > self::MAX_CONTEXT_BYTES) {
            return FALSE;
        }
        $files[$relative] = array('file' => $path, 'hash' => hash_file('sha256', $path), 'mode' => is_executable($path) ? 0755 : 0644);
        return TRUE;
    }

    /**
     * Writes a ustar archive of path => content (a string, or an array with
     * the source file and its mode) for Docker's build API.
     *
     * @return bool
     */
    public function writeTar(array $files, $target)
    {
        $handle = @fopen($target, 'wb');
        if (! $handle) {
            return FALSE;
        }
        ksort($files, SORT_STRING);
        foreach ($files as $path => $content) {
            $isFile = is_array($content);
            $size = $isFile ? (int) filesize($content['file']) : strlen((string) $content);
            $header = $this->tarHeader($path, $size, $isFile && isset($content['mode']) ? $content['mode'] : 0644);
            if ($header === FALSE) {
                fclose($handle);
                return FALSE;
            }
            fwrite($handle, $header);
            if ($isFile) {
                $source = @fopen($content['file'], 'rb');
                if (! $source) {
                    fclose($handle);
                    return FALSE;
                }
                $written = stream_copy_to_stream($source, $handle, $size);
                fclose($source);
                if ($written !== $size) {
                    fclose($handle);
                    return FALSE;
                }
            } else {
                fwrite($handle, (string) $content);
            }
            if ($size % 512 !== 0) {
                fwrite($handle, str_repeat("\0", 512 - $size % 512));
            }
        }
        fwrite($handle, str_repeat("\0", 1024));
        return fclose($handle);
    }

    private function tarHeader($path, $size, $mode)
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        $prefix = '';
        $name = $path;
        if (strlen($path) > 100) {
            $split = strrpos(substr($path, 0, 156), '/');
            if ($split === FALSE || strlen(substr($path, $split + 1)) > 100) {
                return FALSE;
            }
            $prefix = substr($path, 0, $split);
            $name = substr($path, $split + 1);
        }
        $header = str_pad($name, 100, "\0")
            .sprintf('%07o', $mode & 0777)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%011o', $size)."\0"
            .sprintf('%011o', 0)."\0"
            .'        '
            .'0'
            .str_repeat("\0", 100)
            ."ustar\0".'00'
            .str_pad('root', 32, "\0")
            .str_pad('root', 32, "\0")
            .str_repeat("\0", 16)
            .str_pad($prefix, 155, "\0");
        $header = str_pad($header, 512, "\0");
        $checksum = 0;
        for ($index = 0; $index < 512; $index++) {
            $checksum += ord($header[$index]);
        }
        return substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
    }

    /** Container of a deployment: one per project and person, or one per project. */
    public function containerName($projectId, $userId)
    {
        return 'jobseeker-ide-p'.(int) $projectId.((int) $userId > 0 ? '-u'.(int) $userId : '-shared');
    }

    public function homeVolume($projectId, $userId)
    {
        return 'jobseeker-ide-home-p'.(int) $projectId.((int) $userId > 0 ? '-u'.(int) $userId : '-shared');
    }

    /** "3100-3199" as array(3100, 3199), kept inside what the gateway routes. */
    public function portRange($value)
    {
        if (preg_match('/^\s*(\d{1,5})\s*-\s*(\d{1,5})\s*$/', (string) $value, $matches)) {
            $start = max(3100, (int) $matches[1]);
            $end = min(3499, (int) $matches[2]);
            if ($start <= $end) {
                return array($start, $end);
            }
        }
        return array(3100, 3199);
    }

    /** Where editors reach JobSeeker's connector API (Compose: nginx on the internal network). */
    public static function connectorApiUrl()
    {
        $url = trim((string) getenv('JOBSEEKER_WORKSPACE_CONNECTOR_API_URL'));
        return $url !== '' ? $url : 'http://nginx:8080/connector-runtime';
    }

    /** Where editors' Git helper asks for the person's credential. */
    public static function gitCredentialUrl()
    {
        $url = trim((string) getenv('JOBSEEKER_WORKSPACE_GIT_CREDENTIAL_URL'));
        return $url !== '' ? $url : 'http://nginx:8080/git-credential';
    }

    public function basePath($port)
    {
        return '/ide/'.(int) $port;
    }

    /**
     * The Docker create request of a deployment.
     *
     * Options: image, runtime (image key), port, token, uid, gid, cpus,
     * memoryMb, idleMinutes, folder (editor path), mounts (array of source,
     * target, readOnly),
     * homeVolume, env (name => value), labels, spec, postCreate, postStart,
     * settings.
     */
    public function containerConfig(array $options)
    {
        $env = array(
            'HOME' => self::WORKSPACE_HOME,
            'JOBSEEKER_IDE_PORT' => (string) (int) $options['port'],
            'JOBSEEKER_IDE_BASE_PATH' => $this->basePath($options['port']),
            'JOBSEEKER_IDE_TOKEN' => (string) $options['token'],
            'JOBSEEKER_IDE_FOLDER' => (string) $options['folder'],
            'JOBSEEKER_IDE_IDLE_MINUTES' => (string) (int) $options['idleMinutes'],
            'JOBSEEKER_IDE_SPEC' => (string) $options['spec'],
            'UV_PYTHON_INSTALL_DIR' => self::WORKSPACE_HOME.'/.cache/uv-python',
            // For every process, not only terminals and tasks: a notebook
            // kernel resolves Data Assets as a run in the terminal does.
            'JOBSEEKER_REPOSITORY_ROOT' => self::WORKSPACE_REPOSITORY,
            'JOBSEEKER_DATA_ASSETS_MANIFEST' => self::WORKSPACE_REPOSITORY.'/data-assets/manifest.json',
            // As in the Default editor: a run reads its connectors with the
            // workspace's session, and Git asks for the person's credential.
            'JOBSEEKER_CONNECTOR_API_URL' => self::connectorApiUrl(),
            'JOBSEEKER_GIT_CREDENTIAL_URL' => self::gitCredentialUrl()
        );
        // Which runtime the project's .venv belongs to (bootstrap-python.sh).
        if (! empty($options['runtime'])) {
            $env['JOBSEEKER_RUNTIME'] = (string) $options['runtime'];
        }
        foreach (array('postCreate' => 'JOBSEEKER_IDE_POST_CREATE', 'postStart' => 'JOBSEEKER_IDE_POST_START') as $option => $name) {
            if (! empty($options[$option])) {
                $env[$name] = (string) $options[$option];
            }
        }
        if (! empty($options['settings'])) {
            $env['JOBSEEKER_IDE_MACHINE_SETTINGS'] = json_encode($options['settings'], JSON_UNESCAPED_SLASHES);
        }
        // Dev container variables never override what JobSeeker sets.
        $env = $env + (isset($options['env']) ? (array) $options['env'] : array());
        $envList = array();
        foreach ($env as $name => $value) {
            $envList[] = $name.'='.$value;
        }

        $mounts = array(array('Type' => 'volume', 'Source' => $options['homeVolume'], 'Target' => self::WORKSPACE_HOME));
        foreach ($options['mounts'] as $mount) {
            $mounts[] = array('Type' => 'bind', 'Source' => $mount['source'], 'Target' => $mount['target'], 'ReadOnly' => ! empty($mount['readOnly']));
        }
        return array(
            'Image' => $options['image'],
            'User' => (int) $options['uid'].':'.(int) $options['gid'],
            'Env' => $envList,
            'Labels' => array_map('strval', (array) $options['labels']),
            'WorkingDir' => self::WORKSPACE_HOME,
            'HostConfig' => array(
                // Editors reach JobSeeker, connectors and the internet as
                // Docker jobs do, from the job runtime's network.
                'NetworkMode' => 'host',
                'Init' => TRUE,
                'Mounts' => $mounts,
                'NanoCpus' => (int) round(max(0.1, (float) $options['cpus']) * 1000000000),
                'Memory' => (int) $options['memoryMb'] * 1048576,
                'PidsLimit' => 4096,
                'SecurityOpt' => array('no-new-privileges'),
                'CapDrop' => array('ALL'),
                'RestartPolicy' => array('Name' => 'no'),
                'LogConfig' => array('Type' => 'json-file', 'Config' => array('max-size' => '10m', 'max-file' => '2'))
            )
        );
    }

    /**
     * Readable lines of a Docker build API stream, and its error if it failed.
     *
     * @return array lines, error (string, '' when none), imageId
     */
    public function parseBuildStream($stream)
    {
        $lines = array();
        $error = '';
        $imageId = '';
        $lastStatus = '';
        foreach (preg_split('/\r?\n/', (string) $stream) as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $message = json_decode($raw, TRUE);
            if (! is_array($message)) {
                $lines[] = $raw;
                continue;
            }
            if (isset($message['stream'])) {
                // RUN output arrives with terminal colors; the log is plain text.
                $text = preg_replace('/\x1B\[[0-9;?]*[A-Za-z]/', '', (string) $message['stream']);
                if (trim($text) === '') {
                    continue;
                }
                foreach (explode("\n", rtrim($text, "\n")) as $line) {
                    $lines[] = rtrim($line, "\r");
                }
            } else if (isset($message['status']) && empty($message['progressDetail'])) {
                $status = trim((string) $message['status'].(isset($message['id']) ? ' '.$message['id'] : ''));
                if ($status !== $lastStatus) {
                    $lines[] = $status;
                    $lastStatus = $status;
                }
            }
            if (isset($message['error']) || isset($message['errorDetail']['message'])) {
                $error = trim((string) (isset($message['errorDetail']['message']) ? $message['errorDetail']['message'] : $message['error']));
                $lines[] = 'ERROR: '.$error;
            }
            if (isset($message['aux']['ID'])) {
                $imageId = (string) $message['aux']['ID'];
            }
        }
        return array('lines' => $lines, 'error' => $error, 'imageId' => $imageId);
    }
}

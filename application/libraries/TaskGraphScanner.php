<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_TASK_GRAPH_TEST')) exit('No direct script access allowed');

/**
 * Static discovery of the task DAG a Python job declares.
 *
 * No code is executed. The runtime writes the authoritative manifest to
 * `job_task_graphs` when the job runs; this scanner is what lets Job Creation
 * draw the graph while the code is still being typed, and what lets Job View
 * show a graph for a job that has never run.
 *
 * Only decorators on a name that the file actually binds to the DAG module or
 * to a Dag instance are matched. `jobseeker.task` is a different decorator -
 * it opens a TMF transaction - and must not be mistaken for a DAG task.
 */
class TaskGraphScanner
{
    const MAX_TASKS = 200;
    const MAX_EDGES = 600;
    const MAX_SOURCE_BYTES = 2000000;

    /** Valid task ids mirror the runtime's TASK_ID_PATTERN. */
    const TASK_ID = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/';

    private $triggers = array('SUCCESS', 'FAILURE', 'ALWAYS');

    /**
     * @param array $sources list of ['text' => string, 'from' => 'code'|'command']
     * @return array manifest-shaped result: ok, message, tasks, edges, order, layers
     */
    public function scan(array $sources)
    {
        $tasks = array();

        foreach ($sources as $source) {
            $text = isset($source['text']) ? (string) $source['text'] : '';
            if ($text === '' || strlen($text) > self::MAX_SOURCE_BYTES) {
                continue;
            }
            foreach ($this->scanText($text) as $task) {
                // First declaration wins so an editor buffer and its saved copy
                // do not produce two nodes for the same task.
                if (! isset($tasks[$task['id']])) {
                    $tasks[$task['id']] = $task;
                }
            }
        }

        return $this->compile(array_values($tasks));
    }

    /**
     * Turn discovered tasks into a validated graph. Shares its output shape with
     * jobseeker.dag.Dag.manifest() so one renderer draws both.
     */
    public function compile(array $tasks)
    {
        if (empty($tasks)) {
            return $this->result(TRUE, 'No JobSeeker tasks are declared in this job.', array(), array(), array(), array());
        }
        if (count($tasks) > self::MAX_TASKS) {
            return $this->failure('A job may declare at most '.self::MAX_TASKS.' tasks.');
        }

        $byId = array();
        foreach ($tasks as $task) {
            $byId[$task['id']] = $task;
        }

        $edges = array();
        $adjacency = array();
        $inDegree = array();
        foreach ($byId as $id => $task) {
            $adjacency[$id] = array();
            $inDegree[$id] = 0;
        }

        $warnings = array();
        foreach ($byId as $id => $task) {
            foreach ($task['depends_on'] as $source) {
                if ($source === $id) {
                    return $this->failure('Task "'.$id.'" cannot depend on itself.');
                }
                if (! isset($byId[$source])) {
                    $warnings[] = 'Task "'.$id.'" depends on "'.$source.'", which is not declared in this job.';
                    continue;
                }
                $adjacency[$source][] = $id;
                $inDegree[$id]++;
                $edges[] = array('source' => $source, 'target' => $id, 'condition' => $task['trigger']);
            }
        }

        if (count($edges) > self::MAX_EDGES) {
            return $this->failure('A job may declare at most '.self::MAX_EDGES.' task dependencies.');
        }

        $ready = array();
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $ready[] = $id;
            }
        }
        sort($ready);

        $order = array();
        $layers = array();
        while (! empty($ready)) {
            $layer = $ready;
            $layers[] = $layer;
            $ready = array();
            foreach ($layer as $id) {
                $order[] = $id;
                foreach ($adjacency[$id] as $target) {
                    $inDegree[$target]--;
                    if ($inDegree[$target] === 0) {
                        $ready[] = $target;
                    }
                }
            }
            sort($ready);
        }

        if (count($order) !== count($byId)) {
            $stuck = array_values(array_diff(array_keys($byId), $order));
            sort($stuck);
            return $this->failure('The task graph contains a cycle. Break the loop between: '.implode(', ', $stuck).'.');
        }

        $ordered = array();
        foreach ($order as $id) {
            $ordered[] = $byId[$id];
        }

        $result = $this->result(TRUE, 'Task graph is valid.', $ordered, $edges, $order, $layers);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Build the source bundle for a saved job: every readable Python file in the
     * job's repository directory.
     */
    public function sourcesForDirectory($directory)
    {
        $sources = array();
        $directory = (string) $directory;
        if ($directory === '' || ! is_dir($directory)) {
            return $sources;
        }

        $skip = array('.git', '.venv', 'venv', '__pycache__', '.pytest_cache', '.mypy_cache', '.ruff_cache', 'node_modules');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                function ($current) use ($skip) {
                    return ! ($current->isDir() && in_array($current->getFilename(), $skip, TRUE));
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $seen = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'py') {
                continue;
            }
            if ($file->getSize() > self::MAX_SOURCE_BYTES || ++$seen > 400) {
                continue;
            }
            $contents = @file_get_contents($file->getPathname());
            if ($contents !== FALSE) {
                $sources[] = array('text' => $contents, 'from' => 'code');
            }
        }

        return $sources;
    }

    /**
     * Names in this file that are bound to the DAG module or to a Dag instance.
     * Anything else decorated with `.task(` belongs to another library.
     */
    public function dagNames($text)
    {
        $names = array();

        if (preg_match_all('/^\s*from\s+jobseeker\s+import\s+([^\n#]+)/m', $text, $matches)) {
            foreach ($matches[1] as $clause) {
                foreach (explode(',', $clause) as $item) {
                    if (preg_match('/^\s*dag(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*$/', $item, $alias)) {
                        $names[] = isset($alias[1]) && $alias[1] !== '' ? $alias[1] : 'dag';
                    }
                }
            }
        }

        if (preg_match_all('/^\s*import\s+jobseeker\.dag\s+as\s+([A-Za-z_][A-Za-z0-9_]*)/m', $text, $matches)) {
            foreach ($matches[1] as $alias) {
                $names[] = $alias;
            }
        }

        // `pipeline = Dag("nightly")` after `from jobseeker.dag import Dag`.
        if (preg_match_all('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:jobseeker\.)?(?:dag\.)?Dag\s*\(/m', $text, $matches)) {
            foreach ($matches[1] as $variable) {
                $names[] = $variable;
            }
        }

        if (strpos($text, 'jobseeker.dag') !== FALSE) {
            $names[] = 'jobseeker.dag';
        }

        return array_values(array_unique($names));
    }

    private function scanText($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $names = $this->dagNames($text);
        if (empty($names)) {
            return array();
        }

        $prefixes = array();
        foreach ($names as $name) {
            $prefixes[] = preg_quote($name, '/');
        }
        $pattern = '/@\s*(?:'.implode('|', $prefixes).')\s*\.\s*task\s*\(/';

        $tasks = array();
        $offset = 0;
        while (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $openParen = $match[0][1] + strlen($match[0][0]) - 1;
            $arguments = $this->balancedArguments($text, $openParen);
            if ($arguments === FALSE) {
                $offset = $match[0][1] + strlen($match[0][0]);
                continue;
            }
            $afterCall = $openParen + strlen($arguments['raw']) + 1;
            $task = $this->taskFromArguments($arguments['text'], substr($text, $afterCall, 4000));
            if ($task !== FALSE) {
                $tasks[] = $task;
            }
            $offset = $afterCall;
        }

        return $tasks;
    }

    /**
     * Read the argument list of a call whose "(" sits at $openParen, honouring
     * nesting and string literals so a bracket inside a quoted default does not
     * end the call early.
     */
    private function balancedArguments($text, $openParen)
    {
        $length = strlen($text);
        $depth = 0;
        $quote = '';
        for ($index = $openParen; $index < $length && $index < $openParen + 20000; $index++) {
            $character = $text[$index];
            if ($quote !== '') {
                if ($character === '\\') {
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }
            if ($character === '#') {
                $newline = strpos($text, "\n", $index);
                if ($newline === FALSE) {
                    return FALSE;
                }
                $index = $newline;
                continue;
            }
            if ($character === '(' || $character === '[' || $character === '{') {
                $depth++;
                continue;
            }
            if ($character === ')' || $character === ']' || $character === '}') {
                $depth--;
                if ($depth === 0) {
                    $raw = substr($text, $openParen, $index - $openParen + 1);
                    return array('raw' => $raw, 'text' => substr($raw, 1, -1));
                }
            }
        }

        return FALSE;
    }

    private function taskFromArguments($arguments, $following)
    {
        $id = $this->stringArgument($arguments, 'id');
        if ($id === '') {
            $id = $this->decoratedFunctionName($following);
        }
        if ($id === '' || ! preg_match(self::TASK_ID, $id)) {
            return FALSE;
        }

        $trigger = strtoupper($this->stringArgument($arguments, 'trigger'));
        if (! in_array($trigger, $this->triggers, TRUE)) {
            $trigger = 'SUCCESS';
        }

        $dimension = $this->stringArgument($arguments, 'dimension');

        return array(
            'id' => $id,
            'description' => $this->stringArgument($arguments, 'description'),
            'depends_on' => $this->listArgument($arguments, 'depends_on'),
            'consumes' => $this->listArgument($arguments, 'consumes'),
            'produces' => $this->listArgument($arguments, 'produces'),
            'retries' => $this->numberArgument($arguments, 'retries', 0),
            'retry_delay' => $this->numberArgument($arguments, 'retry_delay', 0),
            'trigger' => $trigger,
            'track' => $this->booleanArgument($arguments, 'track') || $dimension !== '',
            'dimension' => $dimension
        );
    }

    private function decoratedFunctionName($following)
    {
        if (preg_match('/(?:^|\n)\s*(?:async\s+)?def\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $following, $match)) {
            return $match[1];
        }
        return '';
    }

    private function stringArgument($arguments, $name)
    {
        $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)(?<!\\\\)\1/s';
        if (preg_match($pattern, $arguments, $match)) {
            return trim($match[2]);
        }
        return '';
    }

    private function numberArgument($arguments, $name, $default)
    {
        $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($name, '/').'\s*=\s*([0-9]+(?:\.[0-9]+)?)/';
        if (preg_match($pattern, $arguments, $match)) {
            return $match[1] + 0;
        }
        return $default;
    }

    private function booleanArgument($arguments, $name)
    {
        $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($name, '/').'\s*=\s*(True|False)/';
        if (preg_match($pattern, $arguments, $match)) {
            return $match[1] === 'True';
        }
        return FALSE;
    }

    private function listArgument($arguments, $name)
    {
        $pattern = '/(?<![A-Za-z0-9_])'.preg_quote($name, '/').'\s*=\s*[\[\(]([^\]\)]*)[\]\)]/s';
        if (! preg_match($pattern, $arguments, $match)) {
            $single = $this->stringArgument($arguments, $name);
            return $single === '' ? array() : array($single);
        }

        $values = array();
        if (preg_match_all('/(["\'])(.*?)(?<!\\\\)\1/s', $match[1], $items, PREG_SET_ORDER)) {
            foreach ($items as $item) {
                $value = trim($item[2]);
                if ($value !== '' && ! in_array($value, $values, TRUE)) {
                    $values[] = $value;
                }
            }
        }
        return $values;
    }

    private function failure($message)
    {
        return $this->result(FALSE, $message, array(), array(), array(), array());
    }

    private function result($ok, $message, $tasks, $edges, $order, $layers)
    {
        return array(
            'ok' => (bool) $ok,
            'message' => $message,
            'version' => 1,
            'tasks' => $tasks,
            'edges' => $edges,
            'order' => $order,
            'layers' => $layers,
            'warnings' => array()
        );
    }
}

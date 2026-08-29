<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_WORKFLOW_TEST')) exit('No direct script access allowed');

class WorkflowCompiler
{
    private $conditions = array('SUCCESS', 'FAILURE', 'ALWAYS');

    public function validateGraph($nodes, $edges)
    {
        if (! is_array($nodes) || ! is_array($edges)) {
            return $this->failure('Workflow nodes and connections must be arrays.');
        }
        if (count($nodes) < 1 || count($nodes) > 100) {
            return $this->failure('A workflow must contain between 1 and 100 job nodes.');
        }
        if (count($edges) > 300) {
            return $this->failure('A workflow cannot contain more than 300 connections.');
        }

        $normalizedNodes = array();
        $nodeIds = array();
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                return $this->failure('Every workflow node must be an object.');
            }
            $id = isset($node['id']) ? trim((string) $node['id']) : '';
            $job = isset($node['job']) ? trim((string) $node['job']) : '';
            $label = isset($node['label']) ? trim((string) $node['label']) : $job;
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id)) {
                return $this->failure('Every workflow node needs a stable ID using letters, numbers, dashes, or underscores.');
            }
            if (isset($nodeIds[$id])) {
                return $this->failure('Workflow node IDs must be unique.');
            }
            if ($job === '' || strlen($job) > 200 || ! preg_match('/^[A-Za-z0-9._\-\/ ]+$/', $job)) {
                return $this->failure('Every workflow node must reference a valid Jenkins job name.');
            }
            if ($label === '' || strlen($label) > 120 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
                return $this->failure('Workflow node labels must be between 1 and 120 printable characters.');
            }
            $x = isset($node['x']) && is_numeric($node['x']) ? (int) $node['x'] : 80;
            $y = isset($node['y']) && is_numeric($node['y']) ? (int) $node['y'] : 80;
            $nodeIds[$id] = TRUE;
            $normalizedNodes[] = array(
                'id' => $id,
                'job' => $job,
                'label' => $label,
                'x' => max(0, min(5000, $x)),
                'y' => max(0, min(5000, $y))
            );
        }

        $normalizedEdges = array();
        $edgePairs = array();
        $adjacency = array();
        $inDegree = array();
        foreach ($normalizedNodes as $node) {
            $adjacency[$node['id']] = array();
            $inDegree[$node['id']] = 0;
        }

        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                return $this->failure('Every workflow connection must be an object.');
            }
            $source = isset($edge['source']) ? trim((string) $edge['source']) : '';
            $target = isset($edge['target']) ? trim((string) $edge['target']) : '';
            $condition = strtoupper(trim(isset($edge['condition']) ? (string) $edge['condition'] : 'SUCCESS'));
            if (! isset($nodeIds[$source]) || ! isset($nodeIds[$target])) {
                return $this->failure('Every workflow connection must reference existing nodes.');
            }
            if ($source === $target) {
                return $this->failure('A workflow node cannot connect to itself.');
            }
            if (! in_array($condition, $this->conditions, TRUE)) {
                return $this->failure('Connection conditions must be SUCCESS, FAILURE, or ALWAYS.');
            }
            $pair = $source.'>'.$target;
            if (isset($edgePairs[$pair])) {
                return $this->failure('Only one connection is allowed between the same two nodes.');
            }
            $edgePairs[$pair] = TRUE;
            $adjacency[$source][] = $target;
            $inDegree[$target]++;
            $normalizedEdges[] = array('source' => $source, 'target' => $target, 'condition' => $condition);
        }

        $remainingInDegree = $inDegree;
        $queue = array();
        foreach ($remainingInDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
            }
        }
        sort($queue);
        $ordered = array();
        $layers = array();
        while (! empty($queue)) {
            $layer = $queue;
            $queue = array();
            $layers[] = $layer;
            foreach ($layer as $nodeId) {
                $ordered[] = $nodeId;
                foreach ($adjacency[$nodeId] as $target) {
                    $remainingInDegree[$target]--;
                    if ($remainingInDegree[$target] === 0) {
                        $queue[] = $target;
                    }
                }
            }
            sort($queue);
        }
        if (count($ordered) !== count($normalizedNodes)) {
            return $this->failure('Workflow connections contain a cycle. Remove at least one connection from the loop.');
        }

        return array(
            'ok' => TRUE,
            'message' => 'Workflow graph is valid.',
            'nodes' => $normalizedNodes,
            'edges' => $normalizedEdges,
            'order' => $ordered,
            'layers' => $layers
        );
    }

    public function compileScript($name, $environment, $nodes, $edges)
    {
        $validation = $this->validateGraph($nodes, $edges);
        if (! $validation['ok']) {
            throw new InvalidArgumentException($validation['message']);
        }

        $nodeMap = array();
        $incoming = array();
        foreach ($validation['nodes'] as $node) {
            $nodeMap[$node['id']] = array('job' => $node['job'], 'label' => $node['label']);
            $incoming[$node['id']] = array();
        }
        foreach ($validation['edges'] as $edge) {
            $incoming[$edge['target']][] = array('source' => $edge['source'], 'condition' => $edge['condition']);
        }

        $nodeLines = array();
        foreach ($nodeMap as $id => $node) {
            $nodeLines[] = '  '.$this->groovyString($id).': [job: '.$this->groovyString($node['job']).', label: '.$this->groovyString($node['label']).']';
        }
        $incomingLines = array();
        foreach ($incoming as $id => $dependencies) {
            $dependencyLines = array();
            foreach ($dependencies as $dependency) {
                $dependencyLines[] = '[source: '.$this->groovyString($dependency['source']).', condition: '.$this->groovyString($dependency['condition']).']';
            }
            $incomingLines[] = '  '.$this->groovyString($id).': ['.implode(', ', $dependencyLines).']';
        }
        $pendingNodes = array_map(array($this, 'groovyString'), array_keys($nodeMap));

        return implode("\n", array(
            'def workflowName = '.$this->groovyString($name),
            'def workflowNodes = [',
            implode(",\n", $nodeLines),
            ']',
            'def incoming = [',
            implode(",\n", $incomingLines),
            ']',
            'def pending = ['.implode(', ', $pendingNodes).']',
            'def nodeResults = [:]',
            'echo "JOBSEEKER_PIPELINE|START|${workflowName}|${params.ENVIRONMENT}"',
            'while (!pending.isEmpty()) {',
            '  def ready = pending.findAll { nodeId -> incoming[nodeId].every { edge -> nodeResults.containsKey(edge.source) } }',
            '  if (ready.isEmpty()) { error("Workflow could not make progress.") }',
            '  def runnable = []',
            '  ready.each { nodeId ->',
            '    def matches = incoming[nodeId].every { edge ->',
            '      def sourceResult = nodeResults[edge.source]',
            '      edge.condition == "ALWAYS" ? sourceResult != "SKIPPED" :',
            '        (edge.condition == "SUCCESS" ? sourceResult == "SUCCESS" :',
            '          (edge.condition == "FAILURE" ? sourceResult in ["FAILURE", "UNSTABLE", "ABORTED", "NOT_BUILT"] : false))',
            '    }',
            '    if (matches) {',
            '      runnable << nodeId',
            '    } else {',
            '      nodeResults[nodeId] = "SKIPPED"',
            '      pending.remove(nodeId)',
            '      echo "JOBSEEKER_PIPELINE_NODE|${nodeId}|${workflowNodes[nodeId].job}|SKIPPED|"',
            '    }',
            '  }',
            '  if (!runnable.isEmpty()) {',
            '    def branches = [:]',
            '    runnable.each { selectedId ->',
            '      def nodeId = selectedId',
            '      branches[nodeId] = {',
            '        stage(workflowNodes[nodeId].label) {',
            '          def downstream = build job: workflowNodes[nodeId].job, parameters: [string(name: "ENVIRONMENT", value: params.ENVIRONMENT)], waitForStart: true, propagate: false',
            '          echo "JOBSEEKER_PIPELINE_NODE|${nodeId}|${workflowNodes[nodeId].job}|RUNNING|${downstream.number}"',
            '          def completed = waitForBuild runId: downstream.externalizableId, propagate: false, propagateAbort: true',
            '          def result = completed.result ?: "SUCCESS"',
            '          echo "JOBSEEKER_PIPELINE_NODE|${nodeId}|${workflowNodes[nodeId].job}|${result}|${completed.number}"',
            '          return [result: result, number: completed.number]',
            '        }',
            '      }',
            '    }',
            '    def waveResults = parallel branches',
            '    waveResults.each { nodeId, outcome -> nodeResults[nodeId] = outcome.result }',
            '    runnable.each { nodeId -> pending.remove(nodeId) }',
            '  }',
            '}',
            'def failures = nodeResults.findAll { nodeId, result -> result in ["FAILURE", "UNSTABLE", "ABORTED", "NOT_BUILT"] }',
            'echo "JOBSEEKER_PIPELINE|FINISH|${workflowName}|${failures.isEmpty() ? "SUCCESS" : "FAILURE"}"',
            'if (!failures.isEmpty()) {',
            '  currentBuild.result = "FAILURE"',
            '  error("Workflow failed nodes: " + failures.keySet().join(", "))',
            '}',
            ''
        ));
    }

    private function failure($message)
    {
        return array('ok' => FALSE, 'message' => $message, 'nodes' => array(), 'edges' => array(), 'order' => array(), 'layers' => array());
    }

    private function groovyString($value)
    {
        $value = str_replace(array('\\', "'", "\r", "\n"), array('\\\\', "\\'", '', '\\n'), (string) $value);
        return "'".$value."'";
    }
}

?>

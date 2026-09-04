<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_HOP_TEST')) exit('No direct script access allowed');

/**
 * The shape of a Hop workflow or pipeline, read straight out of its own file.
 *
 * Apache Hop stores the canvas in the `.hwf`/`.hpl` it already ships: every
 * action or transform carries the coordinates the designer placed it at, and
 * the hops between them carry whether they are enabled and, for a workflow,
 * whether they follow success or failure. That is everything needed to draw the
 * same picture the Hop GUI draws, so JobSeeker draws it rather than asking
 * somebody to open a desktop tool to answer "what does this job actually do".
 *
 * Nothing here interprets a transform's configuration. The file stays the
 * single source of truth and an unknown transform type still appears on the
 * canvas, which is what keeps this working against a Hop version we have not
 * seen yet.
 */
class HopGraph
{
    /** Refuse to walk a pathological file rather than stall a page. */
    const MAX_NODES = 500;
    const MAX_BYTES = 8388608;

    /**
     * Parse one Hop file into nodes and edges.
     *
     * Returns FALSE when the path is not a readable Hop file.
     */
    public function parseFile($path)
    {
        if (! is_string($path) || $path === '' || is_link($path) || ! is_file($path) || ! is_readable($path)) {
            return FALSE;
        }
        if (filesize($path) > self::MAX_BYTES) {
            return FALSE;
        }

        $xml = (string) file_get_contents($path);
        if ($xml === '') {
            return FALSE;
        }

        $isWorkflow = strtolower(substr($path, -4)) === '.hwf';
        return $this->parse($xml, $isWorkflow ? 'workflow' : 'pipeline');
    }

    public function parse($xml, $kind)
    {
        $xml = (string) $xml;
        $nodes = $kind === 'workflow' ? $this->workflowNodes($xml) : $this->pipelineNodes($xml);
        $edges = $this->hops($xml, $kind, $nodes);

        return array(
            'kind' => $kind,
            'name' => $this->firstValue($xml, 'name'),
            'description' => $this->firstValue($xml, 'description'),
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'notes' => $this->notes($xml)
        );
    }

    // -- nodes ---------------------------------------------------------------

    private function workflowNodes($xml)
    {
        $nodes = array();
        foreach ($this->blocks($xml, 'action') as $block) {
            $name = $this->firstValue($block, 'name');
            if ($name === '' || isset($nodes[$name])) {
                continue;
            }
            $nodes[$name] = array(
                'name' => $name,
                'type' => $this->firstValue($block, 'type'),
                'description' => $this->firstValue($block, 'description'),
                'x' => (int) $this->firstValue($block, 'xloc'),
                'y' => (int) $this->firstValue($block, 'yloc'),
                'detail' => $this->workflowDetail($block)
            );
            if (count($nodes) >= self::MAX_NODES) {
                break;
            }
        }
        return $nodes;
    }

    private function pipelineNodes($xml)
    {
        $nodes = array();
        foreach ($this->blocks($xml, 'transform') as $block) {
            $name = $this->firstValue($block, 'name');
            if ($name === '' || isset($nodes[$name])) {
                continue;
            }
            $gui = $this->blocks($block, 'GUI');
            $coordinates = $gui ? $gui[0] : '';
            $nodes[$name] = array(
                'name' => $name,
                'type' => $this->firstValue($block, 'type'),
                'description' => $this->firstValue($block, 'description'),
                'x' => (int) $this->firstValue($coordinates, 'xloc'),
                'y' => (int) $this->firstValue($coordinates, 'yloc'),
                'detail' => $this->pipelineDetail($block)
            );
            if (count($nodes) >= self::MAX_NODES) {
                break;
            }
        }
        return $nodes;
    }

    /**
     * The one or two facts that make a node recognisable on the canvas. A
     * database connection is the important one: it is how a reader sees which
     * JobSeeker connector a transform will use.
     */
    private function pipelineDetail($block)
    {
        $detail = array();
        $connection = $this->firstValue($block, 'connection');
        if ($connection !== '') {
            $detail['connection'] = $connection;
        }
        foreach (array('table', 'filename', 'schema') as $tag) {
            $value = $this->firstValue($block, $tag);
            if ($value !== '') {
                $detail[$tag] = $value;
                break;
            }
        }
        return $detail;
    }

    private function workflowDetail($block)
    {
        $detail = array();
        foreach (array('filename', 'message') as $tag) {
            $value = $this->firstValue($block, $tag);
            if ($value !== '') {
                $detail[$tag] = $value;
                break;
            }
        }
        return $detail;
    }

    // -- edges ---------------------------------------------------------------

    private function hops($xml, $kind, $nodes)
    {
        $edges = array();
        $seen = array();
        foreach ($this->blocks($xml, 'hop') as $block) {
            $from = $this->firstValue($block, 'from');
            $to = $this->firstValue($block, 'to');
            if ($from === '' || $to === '' || ! isset($nodes[$from]) || ! isset($nodes[$to])) {
                continue;
            }
            $key = $from."\0".$to;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = TRUE;

            $edge = array(
                'from' => $from,
                'to' => $to,
                'enabled' => strtoupper($this->firstValue($block, 'enabled')) !== 'N'
            );
            if ($kind === 'workflow') {
                // A workflow hop is unconditional, or it follows success or
                // failure. Drawing that is the difference between a picture of
                // the boxes and a picture of the logic.
                $unconditional = strtoupper($this->firstValue($block, 'unconditional')) === 'Y';
                $evaluation = strtoupper($this->firstValue($block, 'evaluation'));
                $edge['condition'] = $unconditional ? 'always' : ($evaluation === 'N' ? 'failure' : 'success');
            }
            $edges[] = $edge;
        }
        return $edges;
    }

    private function notes($xml)
    {
        $notes = array();
        foreach ($this->blocks($xml, 'notepad') as $block) {
            $note = $this->firstValue($block, 'note');
            if ($note === '') {
                continue;
            }
            $notes[] = array(
                'text' => $note,
                'x' => (int) $this->firstValue($block, 'xloc'),
                'y' => (int) $this->firstValue($block, 'yloc')
            );
            if (count($notes) >= 50) {
                break;
            }
        }
        return $notes;
    }

    // -- XML helpers ---------------------------------------------------------
    //
    // Regular expressions rather than an XML parser: a Hop file written by a
    // third-party tool, or holding one stray control character, must still draw
    // whatever it can instead of failing the whole screen.

    private function blocks($xml, $tag)
    {
        $matches = array();
        preg_match_all('#<'.$tag.'>(.*?)</'.$tag.'>#s', (string) $xml, $matches);
        return isset($matches[1]) ? $matches[1] : array();
    }

    private function firstValue($xml, $tag)
    {
        if (preg_match('#<'.$tag.'>(.*?)</'.$tag.'>#s', (string) $xml, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        return '';
    }
}

/* End of file HopGraph.php */
/* Location: ./application/libraries/HopGraph.php */

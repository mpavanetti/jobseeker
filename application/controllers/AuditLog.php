<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';

class AuditLog extends BaseController
{
    const PAGE_SIZE = 50;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('AuditLog_model', 'auditLog');
    }

    public function index($offset = 0)
    {
        if ((string) $this->role !== (string) ROLE_ADMIN) {
            $this->output->set_status_header(403);
            $this->loadThis();
            return;
        }

        $filters = $this->filters();
        $offset = max(0, (int) $offset);
        $total = $this->auditLog->countEntries($filters);

        $this->load->library('pagination');
        $query = http_build_query(array_filter($filters, function ($value) {
            return $value !== '' && $value !== 0;
        }));
        $config = array(
            'base_url' => base_url('audit-log'),
            'total_rows' => $total,
            'per_page' => self::PAGE_SIZE,
            'uri_segment' => 2,
            'suffix' => $query === '' ? '' : '?'.$query,
            'first_url' => base_url('audit-log').($query === '' ? '' : '?'.$query),
            'full_tag_open' => '<nav><ul class="pagination">',
            'full_tag_close' => '</ul></nav>',
            'first_tag_open' => '<li>',
            'first_tag_close' => '</li>',
            'last_tag_open' => '<li>',
            'last_tag_close' => '</li>',
            'next_tag_open' => '<li>',
            'next_tag_close' => '</li>',
            'prev_tag_open' => '<li>',
            'prev_tag_close' => '</li>',
            'num_tag_open' => '<li>',
            'num_tag_close' => '</li>',
            'cur_tag_open' => '<li class="active"><span>',
            'cur_tag_close' => '</span></li>'
        );
        $this->pagination->initialize($config);

        $data = array(
            'filters' => $filters,
            'records' => $this->auditLog->entries($filters, self::PAGE_SIZE, $offset),
            'actors' => $this->auditLog->actors(),
            'summary' => $this->auditLog->summary($filters),
            'offset' => $offset,
            'total' => $total,
            'pageSize' => self::PAGE_SIZE
        );

        $this->global['pageTitle'] = 'Job Seeker : Audit Log';
        $this->loadViews('auditLog', $this->global, $data, NULL);
    }

    private function filters()
    {
        $operation = strtolower(trim((string) $this->input->get('operation', TRUE)));
        $method = strtoupper(trim((string) $this->input->get('method', TRUE)));
        $outcome = strtolower(trim((string) $this->input->get('outcome', TRUE)));
        $from = trim((string) $this->input->get('from', TRUE));
        $to = trim((string) $this->input->get('to', TRUE));

        return array(
            'search' => substr(trim((string) $this->input->get('search', TRUE)), 0, 200),
            'user_id' => max(0, (int) $this->input->get('user_id')),
            'operation' => in_array($operation, array('read', 'write', 'authentication'), TRUE) ? $operation : '',
            'method' => in_array($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), TRUE) ? $method : '',
            'outcome' => in_array($outcome, array('success', 'failure'), TRUE) ? $outcome : '',
            'from' => $this->validDate($from) ? $from : '',
            'to' => $this->validDate($to) ? $to : ''
        );
    }

    private function validDate($date)
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return FALSE;
        }
        $parts = array_map('intval', explode('-', $date));
        return checkdate($parts[1], $parts[2], $parts[0]);
    }
}

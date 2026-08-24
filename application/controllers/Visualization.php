<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Visualization extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Visualization_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * Index Page for this controller.
     */
    public function view($report = null)
    {

        $this->global['pageTitle'] = 'Job Seeker : Data Visualization';
        $name = urldecode($report);
        $user = $this->global['name'];
        $data["view"] = $this->sanitizeReportRows($this->model->view($name));

        $validate= $this->model->permission($name,$user);

        if ($validate >= 1){

            $this->loadViews("visualization", $this->global, $data, NULL);

        } else {
           redirect('pageNotFound');
        }
        
        
    }

    public function fetch($id) {

      if($this->isManager() == TRUE)
        {
             $this->loadThis();
        }
         else
         {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->sanitizeReportRows($this->model->fetch((int) $id));
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);
     }

     }

     public function config()
    {

         if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Visualization Config';
            $user = $this->global['name'];

            $data["list"] = $this->model->list();
            $data["reports"] = $this->model->listReports();
            $data["types"] = $this->model->listTypes();
            $data["users"] = $this->model->listUsers();
            $data["groups"] = $this->model->listGroups();
            
            $this->loadViews("visualizationConfig", $this->global, $data, NULL);
        }
    }

       public function add() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Report Name','required|max_length[20]');
            $this->form_validation->set_rules('type','Report Software Type','required|max_length[30]');
            $this->form_validation->set_rules('code','Embebed Code','trim|required|max_length[5000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->config();
            }
            else
            {

                $name = $this->security->xss_clean($this->input->post('name'));
                $type = $this->security->xss_clean($this->input->post('type'));
                $users = $this->security->xss_clean($this->input->post('users'));
                $groups = $this->security->xss_clean($this->input->post('groups'));
                $code = $this->normalizeReportEmbed($this->input->post('code'));

                if($code === FALSE) {
                    $this->session->set_flashdata('error', 'Report creation failed ! Use a valid HTTP(S) report URL or iframe embed code.');
                    redirect('Visualization/config');
                }

                $users = is_array($users) ? $users : array();
                $groups = is_array($groups) ? $groups : array();

                if (empty($users) && empty($groups)) {
                   $this->session->set_flashdata('error', 'Report creation failed ! You must select at the least one user or group');
                   redirect('Visualization/config');
                }

                if(empty($users)) {
                    $stringUsers = 'All Users from group';
                } else {
                  $stringUsers = implode(",", $users);
                }

                if(empty($groups)) {
                    $stringGroups = 'None';
                } else {
                  $stringGroups = implode(",", $groups);
                }

                // Check if the data is alredy on table
                 $validateSetting = $this->model->validate($name);


                 $Info = array(
                    'name'=>$name, 
                    'type'=>$type, 
                    'users' => $stringUsers,
                    'groups' => $stringGroups,
                    'code' => $code,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the report name.');
                } else {
                
                $result = $this->model->insert($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Report has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Report creation failed !');
                }

             }

              redirect('Visualization/config');

            }
           
        }

    }

      public function delete() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            if($this->input->method(TRUE) !== 'POST') {
                $this->output->set_status_header(405);
                echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
                return;
            }

            $id = (int) $this->input->post('userId');
            /*
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s')); Future Release Not working */
            
            $result = $this->model->delete($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


    private function sanitizeReportRows($rows)
    {
        if(empty($rows)) {
            return $rows;
        }

        foreach($rows as $row) {
            if(isset($row->code)) {
                $safeCode = $this->normalizeReportEmbed($row->code);
                $row->code = ($safeCode === FALSE) ? '' : $safeCode;
            }
        }

        return $rows;
    }

    private function normalizeReportEmbed($code)
    {
        $code = trim((string) $code);

        if($code === '') {
            return FALSE;
        }

        if(strpos($code, '<') === FALSE && strpos($code, '>') === FALSE) {
            return $this->buildReportIframe($code);
        }

        return $this->sanitizeIframeEmbed($code);
    }

    private function sanitizeIframeEmbed($html)
    {
        if(!class_exists('DOMDocument')) {
            if(preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>\s*<\/iframe>/is', $html, $matches)) {
                return $this->buildReportIframe($matches[2]);
            }

            return FALSE;
        }

        $previousErrors = libxml_use_internal_errors(TRUE);
        $dom = new DOMDocument();
        $options = 0;
        if(defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }
        if(defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }

        $loaded = $dom->loadHTML('<!DOCTYPE html><html><body>'.$html.'</body></html>', $options);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if(!$loaded) {
            return FALSE;
        }

        $iframes = $dom->getElementsByTagName('iframe');
        if($iframes->length !== 1) {
            return FALSE;
        }

        $iframe = $iframes->item(0);
        $attributes = array();
        foreach(array('title', 'width', 'height', 'frameborder', 'allow', 'sandbox', 'referrerpolicy', 'loading') as $attribute) {
            if($iframe->hasAttribute($attribute)) {
                $attributes[$attribute] = $iframe->getAttribute($attribute);
            }
        }
        if($iframe->hasAttribute('allowfullscreen')) {
            $attributes['allowfullscreen'] = TRUE;
        }

        return $this->buildReportIframe($iframe->getAttribute('src'), $attributes);
    }

    private function buildReportIframe($url, $sourceAttributes = array())
    {
        $url = trim((string) $url);
        if(!$this->isSafeReportUrl($url)) {
            return FALSE;
        }

        $attributes = array(
            'src' => $url,
            'style' => 'border:none;width:100%;height:100%;'
        );
        foreach($sourceAttributes as $name => $value) {
            $cleanValue = $this->cleanIframeAttribute($name, $value);
            if($cleanValue !== NULL) {
                $attributes[$name] = $cleanValue;
            }
        }

        $htmlAttributes = array();
        foreach($attributes as $name => $value) {
            if($name === 'allowfullscreen') {
                continue;
            }
            $htmlAttributes[] = $name.'="'.$this->escapeAttribute($value).'"';
        }
        if(!empty($sourceAttributes['allowfullscreen'])) {
            $htmlAttributes[] = 'allowfullscreen';
        }

        return '<iframe '.implode(' ', $htmlAttributes).'></iframe>';
    }

    private function cleanIframeAttribute($name, $value)
    {
        $value = trim((string) $value);

        switch($name) {
            case 'title':
                return substr(strip_tags($value), 0, 200);
            case 'width':
            case 'height':
                return preg_match('/^\d{1,4}(\.\d{1,2})?(%|px)?$/', $value) ? $value : NULL;
            case 'frameborder':
                return in_array($value, array('0', '1'), TRUE) ? $value : NULL;
            case 'allow':
                return (strlen($value) <= 500 && !preg_match('/[\x00-\x1F\x7F]/', $value)) ? $value : NULL;
            case 'sandbox':
                return preg_match('/^[a-zA-Z0-9\- ]{0,300}$/', $value) ? $value : NULL;
            case 'referrerpolicy':
                return in_array(strtolower($value), array('no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url'), TRUE) ? strtolower($value) : NULL;
            case 'loading':
                return in_array(strtolower($value), array('lazy', 'eager'), TRUE) ? strtolower($value) : NULL;
            case 'allowfullscreen':
                return $value ? TRUE : NULL;
            default:
                return NULL;
        }
    }

    private function isSafeReportUrl($url)
    {
        if(strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return FALSE;
        }

        if(!filter_var($url, FILTER_VALIDATE_URL)) {
            return FALSE;
        }

        $parts = parse_url($url);
        if(empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE)) {
            return FALSE;
        }

        return empty($parts['user']) && empty($parts['pass']);
    }

    private function escapeAttribute($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

}

?>

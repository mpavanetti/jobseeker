<?php defined ( 'BASEPATH' ) or exit ( 'No direct script access allowed' ); 

/**
 * Class : BaseController
 * Base Class to control over all the classes
 * @author : Matheus Pavanetti
 * @version : 1.1
 * @since : 2019
 */
class BaseController extends CI_Controller {
	protected $role = '';
	protected $vendorId = '';
	protected $name = '';
	protected $roleText = '';
	protected $global = array ();
	protected $lastLogin = '';

	protected function getRuntimeConfig() {
		if (! is_readable(JOBSEEKER_CONFIG_PATH)) {
			log_message('error', 'Runtime config file is not readable: ' . JOBSEEKER_CONFIG_PATH);
			show_error('Application configuration is unavailable.', 500);
		}

		$configJson = file_get_contents(JOBSEEKER_CONFIG_PATH);
		$config = json_decode($configJson);

		if (! is_object($config) || empty($config->jenkins) || empty($config->setup)) {
			log_message('error', 'Runtime config file is invalid: ' . JOBSEEKER_CONFIG_PATH);
			show_error('Application configuration is invalid.', 500);
		}

		return $config;
	}

	protected function requestJenkins($method, $path, $body = '', $contentType = NULL) {
		if ($path === NULL || $path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || strpos($path, '..') !== FALSE) {
			return array('status' => 400, 'content_type' => 'text/plain', 'body' => 'Invalid Jenkins path.', 'headers' => array());
		}

		$config = $this->getRuntimeConfig();

		if (empty($config->jenkins->enabled)) {
			return array('status' => 503, 'content_type' => 'text/plain', 'body' => 'Jenkins integration is disabled.', 'headers' => array());
		}

		$jenkinsUrl = getenv('JOBSEEKER_JENKINS_INTERNAL_URL') ?: $config->jenkins->url;
		$requestUrl = rtrim($jenkinsUrl, '/') . '/' . ltrim($path, '/');
		$jenkinsUsername = getenv('JOBSEEKER_JENKINS_USER') ?: $config->jenkins->username;
		$jenkinsToken = getenv('JOBSEEKER_JENKINS_TOKEN') ?: $config->jenkins->token;
		$authorizationHeader = 'Authorization: Basic ' . base64_encode($jenkinsUsername . ':' . $jenkinsToken);
		$headers = array($authorizationHeader);

		if (! empty($contentType)) {
			$headers[] = 'Content-Type: ' . $contentType;
		}

		$method = strtoupper($method);

		if ($method !== 'GET' && $method !== 'HEAD') {
			$crumbContext = stream_context_create(array(
				'http' => array(
					'method' => 'GET',
					'header' => $authorizationHeader,
					'ignore_errors' => TRUE,
					'timeout' => 10
				)
			));
			$crumbResponse = file_get_contents(rtrim($jenkinsUrl, '/') . '/crumbIssuer/api/json', FALSE, $crumbContext);
			$crumbHeaders = isset($http_response_header) ? $http_response_header : array();
			$crumb = json_decode($crumbResponse);

			if (is_object($crumb) && ! empty($crumb->crumbRequestField) && ! empty($crumb->crumb)) {
				$headers[] = $crumb->crumbRequestField . ': ' . $crumb->crumb;
				$cookies = array();

				foreach ($crumbHeaders as $header) {
					if (stripos($header, 'Set-Cookie:') === 0) {
						$cookie = explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2);
						$cookies[] = $cookie[0];
					}
				}

				if (! empty($cookies)) {
					$headers[] = 'Cookie: ' . implode('; ', $cookies);
				}
			}
		}

		$options = array(
			'http' => array(
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'ignore_errors' => TRUE,
				'timeout' => 30
			)
		);

		if ($method !== 'GET' && $method !== 'HEAD') {
			$options['http']['content'] = $body;
		}

		$response = file_get_contents($requestUrl, FALSE, stream_context_create($options));
		$responseHeaders = isset($http_response_header) ? $http_response_header : array();
		$statusCode = 502;
		$responseContentType = 'text/plain';

		if (! empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches)) {
			$statusCode = (int) $matches[1];
		}

		foreach ($responseHeaders as $header) {
			if (stripos($header, 'Content-Type:') === 0) {
				$responseContentType = trim(substr($header, strlen('Content-Type:')));
				break;
			}
		}

		if ($response === FALSE) {
			return array('status' => 502, 'content_type' => 'text/plain', 'body' => 'Unable to reach Jenkins.', 'headers' => $responseHeaders);
		}

		return array('status' => $statusCode, 'content_type' => $responseContentType, 'body' => $response, 'headers' => $responseHeaders);
	}

	protected function getUploadedFile($field, $allowedExtensions = array(), $maxBytes = 104857600) {
		if (empty($_FILES[$field]) || ! is_array($_FILES[$field])) {
			return array('ok' => FALSE, 'message' => 'No file was uploaded.');
		}

		$file = $_FILES[$field];

		if (! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			return array('ok' => FALSE, 'message' => $this->uploadErrorMessage(isset($file['error']) ? $file['error'] : NULL));
		}

		if ($maxBytes > 0 && (int) $file['size'] > $maxBytes) {
			return array('ok' => FALSE, 'message' => 'Uploaded file exceeds the maximum allowed size.');
		}

		$originalName = isset($file['name']) ? $file['name'] : '';
		if ($originalName === '' || preg_match('#[\\/]#', $originalName)) {
			return array('ok' => FALSE, 'message' => 'Uploaded file name is invalid.');
		}

		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = array();
		foreach ((array) $allowedExtensions as $allowedExtension) {
			$allowedExtension = strtolower(ltrim(trim($allowedExtension), '.'));
			if ($allowedExtension !== '') {
				$allowed[] = $allowedExtension;
			}
		}

		if (! empty($allowed) && ! in_array($extension, $allowed, TRUE)) {
			return array('ok' => FALSE, 'message' => 'Uploaded file type is not allowed.');
		}

		$safeName = $this->safeUploadFileName($originalName);
		if ($safeName === FALSE) {
			return array('ok' => FALSE, 'message' => 'Uploaded file name is invalid.');
		}

		if (! is_uploaded_file($file['tmp_name'])) {
			return array('ok' => FALSE, 'message' => 'Uploaded file is invalid.');
		}

		return array(
			'ok' => TRUE,
			'tmp_name' => $file['tmp_name'],
			'original_name' => $originalName,
			'safe_name' => $safeName,
			'extension' => $extension,
			'size' => (int) $file['size']
		);
	}

	protected function safeUploadFileName($name) {
		$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
		$safeName = trim($safeName, '._');

		return $safeName === '' ? FALSE : $safeName;
	}

	protected function safePathSegment($segment) {
		$segment = trim((string) $segment);

		if ($segment === '' || $segment === '.' || $segment === '..' || strpos($segment, "\0") !== FALSE || preg_match('#[\\/]#', $segment)) {
			return FALSE;
		}

		return preg_match('/^[A-Za-z0-9._ -]+$/', $segment) ? $segment : FALSE;
	}

	protected function safeRelativePath($path) {
		$path = trim(str_replace('\\', '/', (string) $path), '/');
		if ($path === '' || strpos($path, "\0") !== FALSE) {
			return FALSE;
		}

		$safeSegments = array();
		foreach (explode('/', $path) as $segment) {
			$safeSegment = $this->safePathSegment($segment);
			if ($safeSegment === FALSE) {
				return FALSE;
			}
			$safeSegments[] = $safeSegment;
		}

		return implode(DIRECTORY_SEPARATOR, $safeSegments);
	}

	protected function ensureDirectory($path) {
		return is_dir($path) || mkdir($path, 0755, TRUE) || is_dir($path);
	}

	protected function pathWithinBase($path, $base) {
		$base = rtrim(str_replace('\\', '/', $base), '/') . '/';
		$path = rtrim(str_replace('\\', '/', $path), '/') . '/';

		return strpos($path, $base) === 0;
	}

	protected function extractZipSafely($zipFile, $destination) {
		if (! $this->ensureDirectory($destination)) {
			return array('ok' => FALSE, 'message' => 'Unable to create extraction directory.');
		}

		$zip = new ZipArchive;
		if ($zip->open($zipFile) !== TRUE) {
			return array('ok' => FALSE, 'message' => 'Uploaded file is not a valid ZIP archive.');
		}

		$realDestination = realpath($destination);
		if ($realDestination === FALSE) {
			$zip->close();
			return array('ok' => FALSE, 'message' => 'Extraction directory is invalid.');
		}

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entryName = $zip->getNameIndex($i);
			$normalizedName = str_replace('\\', '/', $entryName);

			if ($normalizedName === '' || strpos($normalizedName, "\0") !== FALSE || $normalizedName[0] === '/' || preg_match('#(^|/)\.\.($|/)#', $normalizedName)) {
				$zip->close();
				return array('ok' => FALSE, 'message' => 'ZIP archive contains an unsafe path.');
			}

			$entryTarget = $realDestination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedName);
			$targetDirectory = substr($normalizedName, -1) === '/' ? rtrim($entryTarget, DIRECTORY_SEPARATOR) : dirname($entryTarget);

			if (! $this->pathWithinBase($targetDirectory, $realDestination)) {
				$zip->close();
				return array('ok' => FALSE, 'message' => 'ZIP archive contains an unsafe path.');
			}
		}

		if (! $zip->extractTo($realDestination)) {
			$zip->close();
			return array('ok' => FALSE, 'message' => 'Unable to extract ZIP archive.');
		}

		$zip->close();
		return array('ok' => TRUE, 'message' => 'ZIP archive extracted.');
	}

	protected function uploadErrorMessage($errorCode) {
		switch ($errorCode) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'Uploaded file exceeds the maximum allowed size.';
			case UPLOAD_ERR_PARTIAL:
				return 'Uploaded file was only partially received.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			default:
				return 'Uploaded file could not be processed.';
		}
	}

	/**
	 * Takes mixed data and optionally a status code, then creates the response
	 *
	 * @access public
	 * @param array|NULL $data
	 *        	Data to output to the user
	 *        	running the script; otherwise, exit
	 */
	public function response($data = NULL) {
		$this->output->set_status_header ( 200 )->set_content_type ( 'application/json', 'utf-8' )->set_output ( json_encode ( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )->_display ();
		exit ();
	}

	/**
	 * This function used to check the user is logged in or not
	 */
	function isLoggedIn() {
		$isLoggedIn = $this->session->userdata ( 'isLoggedIn' );
		
		if (! isset ( $isLoggedIn ) || $isLoggedIn != TRUE) {
			redirect ( 'login' );
		} else {
			$this->role = $this->session->userdata ( 'role' );
			$this->vendorId = $this->session->userdata ( 'userId' );
			$this->name = $this->session->userdata ( 'name' );
			$this->roleText = $this->session->userdata ( 'roleText' );
			$this->lastLogin = $this->session->userdata ( 'lastLogin' );
			
			$this->global ['name'] = $this->name;
			$this->global ['role'] = $this->role;
			$this->global ['role_text'] = $this->roleText;
			$this->global ['last_login'] = $this->lastLogin;

			// load json config file
			$jsonToArray = $this->getRuntimeConfig();

			// Load reports with user permision
			$this->load->model('Visualization_model');
			$this->global ['allowedReports'] =$this->Visualization_model->allowedUser($this->name);

			// Set global var to be used on Controllers
			$this->global ['jenkins_enabled'] = $jsonToArray->jenkins->enabled;
			$this->global ['jenkins_url'] = $jsonToArray->jenkins->url;
			$this->global ['jenkins_username'] = '';
			$this->global ['jenkins_token'] = '';
			$this->global ['jenkins_authorization'] = $jsonToArray->jenkins->authorization;
			$this->global ['jenkins_home'] = $jsonToArray->jenkins->jenkins_home;


			// Set global var to detect OS Version
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			    $os = "windows";
			} else {
			    $os = "linux";
			}
			$this->global ['os'] = $os;

		}
	}
	
	/**
	 * This function is used to check the access
	 */
	function isAdmin() {
		if ($this->role != ROLE_ADMIN) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This function is used to check the access
	 */
	function isManager() {
		if ($this->role != ROLE_MANAGER) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * This function is used to check the access
	 */
	function isTicketter() {
		if ($this->role != ROLE_ADMIN || $this->role != ROLE_MANAGER) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * This function is used to load the set of views
	 */
	function loadThis() {
		$this->global ['pageTitle'] = 'Job Seeker : Access Denied';
		
		$this->load->view ( 'includes/header', $this->global );
		$this->load->view ( 'access' );
		$this->load->view ( 'includes/footer' );
	}

		
	
	/**
	 * This function is used to logged out user from system
	 */
	function logout() {
		$this->session->sess_destroy ();
		
		redirect ( 'login' );
	}

	/**
     * This function used to load views
     * @param {string} $viewName : This is view name
     * @param {mixed} $headerInfo : This is array of header information
     * @param {mixed} $pageInfo : This is array of page information
     * @param {mixed} $footerInfo : This is array of footer information
     * @return {null} $result : null
     */
    function loadViews($viewName = "", $headerInfo = NULL, $pageInfo = NULL, $footerInfo = NULL){

        $this->load->view('includes/header', $headerInfo);
        $this->load->view($viewName, $pageInfo);
        $this->load->view('includes/footer', $footerInfo);
    }

    function loadViewsSetup($viewName = "", $headerInfo = NULL, $pageInfo = NULL, $footerInfo = NULL){

        $this->load->view('includes/setupHeader', $headerInfo);
        $this->load->view($viewName, $pageInfo);
        $this->load->view('includes/setupFooter', $footerInfo);
    }
	
	/**
	 * This function used provide the pagination resources
	 * @param {string} $link : This is page link
	 * @param {number} $count : This is page count
	 * @param {number} $perPage : This is records per page limit
	 * @return {mixed} $result : This is array of records and pagination data
	 */
	function paginationCompress($link, $count, $perPage = 10, $segment = SEGMENT) {
		$this->load->library ( 'pagination' );

		$config ['base_url'] = base_url () . $link;
		$config ['total_rows'] = $count;
		$config ['uri_segment'] = $segment;
		$config ['per_page'] = $perPage;
		$config ['num_links'] = 5;
		$config ['full_tag_open'] = '<nav><ul class="pagination">';
		$config ['full_tag_close'] = '</ul></nav>';
		$config ['first_tag_open'] = '<li class="arrow">';
		$config ['first_link'] = 'First';
		$config ['first_tag_close'] = '</li>';
		$config ['prev_link'] = 'Previous';
		$config ['prev_tag_open'] = '<li class="arrow">';
		$config ['prev_tag_close'] = '</li>';
		$config ['next_link'] = 'Next';
		$config ['next_tag_open'] = '<li class="arrow">';
		$config ['next_tag_close'] = '</li>';
		$config ['cur_tag_open'] = '<li class="active"><a href="#">';
		$config ['cur_tag_close'] = '</a></li>';
		$config ['num_tag_open'] = '<li>';
		$config ['num_tag_close'] = '</li>';
		$config ['last_tag_open'] = '<li class="arrow">';
		$config ['last_link'] = 'Last';
		$config ['last_tag_close'] = '</li>';
	
		$this->pagination->initialize ( $config );
		$page = $config ['per_page'];
		$segment = $this->uri->segment ( $segment );
	
		return array (
				"page" => $page,
				"segment" => $segment
		);
	}
}
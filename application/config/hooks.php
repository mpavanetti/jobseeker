<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	https://codeigniter.com/user_guide/general/hooks.html
|
*/

// Record authenticated application activity after the controller has been
// constructed. The hook itself registers a shutdown callback so redirects and
// early exits are included in the audit trail.
$hook['post_controller_constructor'][] = array(
	'class'    => 'AuditLogHook',
	'function' => 'capture',
	'filename' => 'AuditLogHook.php',
	'filepath' => 'hooks',
	'params'   => array()
);

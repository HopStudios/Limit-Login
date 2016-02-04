<?php
$config['name']='Limit Login';
$config['version']='1.0.0';
$config['nsm_addon_updater']['versions_xml']='http://www.hopstudios.com/software/versions/limit_login/';

// Version constant
if (!defined("LOGIN_LIMIT_VERSION")) {
	define('LOGIN_LIMIT_VERSION', $config['version']);
}
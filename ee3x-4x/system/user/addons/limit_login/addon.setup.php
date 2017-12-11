<?php

require_once PATH_THIRD."limit_login/config.php";

return array(
	'author'      => 'Hop Studios',
	'author_url'  => 'http://hopstudios.com',
	'name'        => 'Hop Limit Login',
	'description' => 'Limit the number of times people can login to ExpressionEngine',
	'docs_url' => 'http://www.hopstudios.com/software/limit_login/docs',
	'version'     => LOGIN_LIMIT_VERSION,
	'namespace'   => 'HopStudios\HopLimitLogin',
	'settings_exist' => TRUE
);

<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Hop Limit Login Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Travis Smith
 * @link		http://www.hopstudios.com/software
 */

if (! defined('LOGIN_LIMIT_VERSION'))
{
	// get the version from config.php
	require PATH_THIRD.'limit_login/config.php';
}


class Limit_login_ext {
	
	public $description		= 'Limit logins to a certain number per day';
	public $docs_url		= 'http://www.hopstudios.com/software';
	public $name			= 'Hop Limit Login';
	public $settings_exist	= 'y';
	public $version			= LOGIN_LIMIT_VERSION;
	public $default_settings = array(
		'logins'                => '1',
		'every'   				=> '1',
		'unit'                  => 'day'
	);
	
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->settings = $settings;
	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Settings Form
	 *
	 * @see http://expressionengine.com/user_guide/development/extensions.html#settings
	 */
	public function settings()
	{
		$settings = array();

		$settings['logins']      = array('i', '', "1");
		$settings['every']      = array('i', '', "1");
		$settings['unit']    = array('s', array('minute' => 'minute', 'hour' => 'hour', 'day' => 'day'), 'day');
		
	    return $settings;

	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
		$this->settings = array();
		
		// Hooks to insert
		$hooks = array(
			'member_member_login_single',
			'member_member_logout'
		);

		// insert hooks and methods
		foreach ($hooks AS $hook)
		{
			$data = array(
				'class'		=> __CLASS__,
				'method'	=> $hook,
				'hook'		=> $hook,
				'version'	=> $this->version,
				'enabled'	=> 'y',
				'settings'	=> serialize($this->default_settings)
			);
			ee()->db->insert('extensions', $data);			
		}

		ee()->load->dbforge();

		// -------------------------------------------
		//  Create the exp_limit_login table
		// -------------------------------------------

		if (! ee()->db->table_exists('limit_login'))
		{
			ee()->dbforge->add_field(array(
				'limit_id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'member_id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
				'site_id'          => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
				'login_date'       => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE)
			));

			ee()->dbforge->add_key('limit_id', TRUE);
			ee()->dbforge->add_key('member_id');
			ee()->dbforge->add_key('login_date');

			ee()->dbforge->create_table('limit_login', TRUE); // TRUE adds if not exists
		}

	}	

	// ----------------------------------------------------------------------
	
	/**
	 * check_logout
	 *
	 * @param 
	 * @return 
	 */
	public function member_member_logout()
	{
	
		if (isset(ee()->session->cache['limit_login']['limit']) && ee()->session->cache['limit_login']['limit'] > 0)
		{
			// echo ee()->session->cache['limit_login']['limit'];
			
			ee()->lang->loadfile('limit_login');
			
			/* Used for general info, not for errors
			$url	= ( ! isset($url)) ? ee()->config->item('site_url')	: $url;
			$name	= ( ! isset($name)) ? stripslashes(ee()->config->item('site_name'))	: $name;
			$data = array(	'title' 	=> lang('sorry'),
							'heading'	=> lang('sorry'),
							'content'	=> lang('too_many_logins'),
							'redirect'	=> $url,
							'link'		=> array($url, $name)
						 );
			*/
	
			ee()->output->show_user_error('general',lang('too_many_logins')
			      . " (".ee()->session->cache['limit_login']['limit'].")",lang('sorry'));
		}

		return;

	}


	/**
	 * check_login
	 *
	 * @param $hook_data Logged in member data
	 * @return 
	 */
	public function member_member_login_single($hook_data)
	{
		// in case we need to log things
		ee()->load->library('logger');

		// how many logins allowed
		$logins =  $this->settings['logins'];

		// Calculate time
		$now = ee()->localize->now;
		
		// Calculate interval
		$interval = $this->settings['every']; // interval will be in seconds
		if ($this->settings['unit'] == 'minute') { $interval = $interval * 60;	}  // minutes
		if ($this->settings['unit'] == 'hour') { $interval = $interval * 60 * 60;	}   // hours
		if ($this->settings['unit'] == 'day') { $interval = $interval * 60 * 60 * 24;	}   // days

		// Either way, add the login to the table
		$data = array(
			'member_id'		=> ee()->session->userdata('member_id'),
			'site_id'		=> ee()->config->item('site_id'),
			'login_date'	=> ee()->localize->now
		);
		ee()->db->insert('limit_login', $data);			

		// superadmins can always login; don't bother checking
		if (ee()->session->userdata('group_id') != 1) 
		{
		
			// Look up logins in that time period
			ee()->db->where('login_date >', ($now-$interval) );
			ee()->db->where('member_id', ee()->session->userdata('member_id') );
			ee()->db->where('site_id', ee()->config->item('site_id') );
			
			$count =	ee()->db->count_all_results('limit_login');

			// Decide if you should log the person out
			if ($count > $logins)
			{
				ee()->logger->log_action('Login denied to member_id ' . 
				      ee()->session->userdata('member_id') . ' who has logged in ' . $count . ' times');
	
				ee()->session->cache['limit_login']['limit'] = $count; // count is always bigger than one, right?


				// Old code: this doesn't work as it requires a CSRF token as a GET parameter
				// Log them out
				if ( ! class_exists('Member_auth'))
				{
					require PATH_ADDONS.'member/mod.member.php';
				}
				// Cheating here, logout method require a GET parameter with the CSRF token
				$_GET['csrf_token'] = CSRF_TOKEN;
				$MA = new Member();
				$MA->member_logout();
				
				// Note: when logout is forced, the rest of this never runs, I think.
			}

		}

		// Sometimes you should prune the table
		$expire = time() - $interval - 1;
		srand(time());
		if ((rand() % 100) < ee()->session->gc_probability) // use the site standard probability
		{
			ee()->db->where('login_date <', $expire)
						 ->delete('limit_login');
		}	

		return;

	}

	// ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{

		ee()->load->dbforge();

		ee()->dbforge->drop_table('limit_login'); 
		
		ee()->db->where('class', __CLASS__);
		ee()->db->delete('extensions');
		
	}

	// ----------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}	
	
	// ----------------------------------------------------------------------
}

/* End of file ext.limit_login.php */
/* Location: /system/expressionengine/third_party/limit_login/ext.limit_login.php */
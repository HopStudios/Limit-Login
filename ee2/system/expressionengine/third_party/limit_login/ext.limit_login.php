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

if (! defined('LIMIT_LOGIN_VERSION'))
{
	// get the version from config.php
	require PATH_THIRD.'limit_login/config.php';
	define('LIMIT_LOGIN_VERSION', $config['version']);
}


class Limit_login_ext {
	
	public $description		= 'Limit logins to a certain number per day';
	public $docs_url		= 'http://www.hopstudios.com/software';
	public $name			= 'Hop Limit Login';
	public $settings_exist	= 'y';
	public $version			= LIMIT_LOGIN_VERSION;
	public $default_settings = array(
		'logins'                => '1',
		'every'   				=> '1',
		'unit'                  => 'day'
	);
	
	private $EE;
	
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
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
			$this->EE->db->insert('extensions', $data);			
		}

		$this->EE->load->dbforge();

		// -------------------------------------------
		//  Create the exp_limit_login table
		// -------------------------------------------

		if (! $this->EE->db->table_exists('limit_login'))
		{
			$this->EE->dbforge->add_field(array(
				'limit_id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'member_id'        => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
				'site_id'          => array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => 1),
				'login_date'       => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE)
			));

			$this->EE->dbforge->add_key('limit_id', TRUE);
			$this->EE->dbforge->add_key('member_id');
			$this->EE->dbforge->add_key('login_date');

			$this->EE->dbforge->create_table('limit_login', TRUE); // TRUE adds if not exists
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
	
		if (isset($this->EE->session->cache['limit_login']['limit']) && $this->EE->session->cache['limit_login']['limit'] > 0)
		{
			
			$this->EE->lang->loadfile('limit_login');
			
			/* Used for general info, not for errors
			$url	= ( ! isset($url)) ? $this->EE->config->item('site_url')	: $url;
			$name	= ( ! isset($name)) ? stripslashes($this->EE->config->item('site_name'))	: $name;
			$data = array(	'title' 	=> lang('sorry'),
							'heading'	=> lang('sorry'),
							'content'	=> lang('too_many_logins'),
							'redirect'	=> $url,
							'link'		=> array($url, $name)
						 );
			*/
	
			$this->EE->output->show_user_error('general',lang('too_many_logins')
			      . " (".$this->EE->session->cache['limit_login']['limit'].")",lang('sorry'));
		}

		return;

	}


	/**
	 * check_login
	 *
	 * @param 
	 * @return 
	 */
	public function member_member_login_single()
	{
	
		// in case we need to log things
		$this->EE->load->library('logger');

		// how many logins allowed
		$logins =  $this->settings['logins'];

		// Calculate time
		$now = $this->EE->localize->now;
		
		// Calculate interval
		$interval = $this->settings['every']; // interval will be in seconds
		if ($this->settings['unit'] == 'minute') { $interval = $interval * 60;	}  // minutes
		if ($this->settings['unit'] == 'hour') { $interval = $interval * 60 * 60;	}   // hours
		if ($this->settings['unit'] == 'day') { $interval = $interval * 60 * 60 * 24;	}   // days

		// Either way, add the login to the table
		$data = array(
			'member_id'		=> $this->EE->session->userdata('member_id'),
			'site_id'		=> $this->EE->config->item('site_id'),
			'login_date'	=> $this->EE->localize->now
		);
		$this->EE->db->insert('limit_login', $data);			

		// superadmins can always login; don't bother checking
		if ($this->EE->session->userdata('group_id') != 1) 
		{
		
			// Look up logins in that time period
			$this->EE->db->where('login_date >', ($now-$interval) );
			$this->EE->db->where('member_id', $this->EE->session->userdata('member_id') );
			$this->EE->db->where('site_id', $this->EE->config->item('site_id') );
			
			$count =	$this->EE->db->count_all_results('limit_login');

			// Decide if you should log the person out
			if ($count > $logins)
			{
				$this->EE->logger->log_action('Login denied to member_id ' . 
				      $this->EE->session->userdata('member_id') . ' who has logged in ' . $count . ' times');
	
				$this->EE->session->cache['limit_login']['limit'] = $count; // count is always bigger than one, right?

				// Log them out
				if ( ! class_exists('Member_auth'))
				{
					require PATH_MOD.'member/mod.member_auth.php';
				}
		
				$MA = new Member_auth();
				$MA->member_logout();
				
				// Note: when logout is forced, the rest of this never runs, I think.
			}

		}

		// Sometimes you should prune the table
		$expire = time() - $interval - 1;
		srand(time());
		if ((rand() % 100) < $this->EE->session->gc_probability) // use the site standard probability
		{
			$this->EE->db->where('login_date <', $expire)
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

		$this->EE->load->dbforge();

		$this->EE->dbforge->drop_table('limit_login'); 
		
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
		
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
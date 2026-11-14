<?php

namespace mundophpbb\downloadcenter\controller;

class main_controller
{
	use \mundophpbb\downloadcenter\concern\access_rules;
	use \mundophpbb\downloadcenter\concern\table_name;
	use \mundophpbb\downloadcenter\concern\text_tools;
	use \mundophpbb\downloadcenter\concern\upload_rules;
	use \mundophpbb\downloadcenter\controller\section\access;
	use \mundophpbb\downloadcenter\controller\section\author;
	use \mundophpbb\downloadcenter\controller\section\browse;
	use \mundophpbb\downloadcenter\controller\section\paging;
	use \mundophpbb\downloadcenter\controller\section\screenshots;
	use \mundophpbb\downloadcenter\controller\section\uploads;

	protected $config;
	protected $helper;
	protected $template;
	protected $user;
	protected $auth;
	protected $db;
	protected $root_path;
	protected $php_ext;
	protected $notification_helper;

	public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $root_path, $php_ext, \mundophpbb\downloadcenter\service\notification_helper $notification_helper)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->db = $db;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->notification_helper = $notification_helper;
	}
}

<?php

namespace mundophpbb\downloadcenter\acp;

class main_module
{
	use \mundophpbb\downloadcenter\acp\section\categories;
	use \mundophpbb\downloadcenter\acp\section\dashboard;
	use \mundophpbb\downloadcenter\acp\section\diagnostics;
	use \mundophpbb\downloadcenter\acp\section\files;
	use \mundophpbb\downloadcenter\acp\section\integrity;
	use \mundophpbb\downloadcenter\acp\section\items;
	use \mundophpbb\downloadcenter\acp\section\logs;
	use \mundophpbb\downloadcenter\acp\section\paging;
	use \mundophpbb\downloadcenter\acp\section\pending;
	use \mundophpbb\downloadcenter\acp\section\screenshots;
	use \mundophpbb\downloadcenter\acp\section\settings;
	use \mundophpbb\downloadcenter\acp\section\support_topic;
	use \mundophpbb\downloadcenter\acp\section\uploads;
	use \mundophpbb\downloadcenter\concern\text_tools;
	use \mundophpbb\downloadcenter\concern\upload_rules;

	public $u_action;
	public $tpl_name;
	public $page_title;

	/** @var string */
	protected $table_prefix;

	public function main($id, $mode)
	{
		global $auth, $config, $db, $request, $template, $user, $phpbb_container, $table_prefix, $phpbb_root_path, $phpEx;

		$user->add_lang_ext('mundophpbb/downloadcenter', 'acp');

		if (!$auth->acl_get('a_board') && !$auth->acl_get('a_downloadcenter_manage'))
		{
			trigger_error($user->lang('ACP_DOWNLOADCENTER_NOT_AUTHORISED'));
		}

		$this->table_prefix = $table_prefix;
		$this->tpl_name = 'acp_downloadcenter';
		$this->page_title = $user->lang('ACP_DOWNLOADCENTER_TITLE');

		add_form_key('mundophpbb_downloadcenter_' . $mode);

		switch ($mode)
		{
			case 'dashboard':
				$this->handle_dashboard($db, $template, $user, $config);
			break;

			case 'diagnostics':
				$this->handle_diagnostics($db, $request, $template, $user, $config);
			break;

			case 'integrity':
				$this->handle_integrity($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx);
			break;

			case 'categories':
				$this->handle_categories($db, $request, $template, $user);
			break;

			case 'items':
				$this->handle_items($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx);
			break;

			case 'pending':
				$this->handle_pending($db, $request, $template, $user);
			break;

			case 'files':
				$this->handle_files($db, $request, $template, $user, $config);
			break;

			case 'logs':
				$this->handle_logs($db, $request, $template, $user);
			break;

			case 'settings':
			default:
				$this->handle_settings($config, $request, $template, $user);
			break;
		}

		$pending_total_global = $this->count_pending_items($db);

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'S_MODE_DASHBOARD' => $mode === 'dashboard',
			'S_MODE_DIAGNOSTICS' => $mode === 'diagnostics',
			'S_MODE_INTEGRITY' => $mode === 'integrity',
			'S_MODE_SETTINGS' => $mode === 'settings',
			'S_MODE_CATEGORIES' => $mode === 'categories',
			'S_MODE_ITEMS' => $mode === 'items',
			'S_MODE_PENDING' => $mode === 'pending',
			'S_MODE_FILES' => $mode === 'files',
			'S_MODE_LOGS' => $mode === 'logs',
			'U_DASHBOARD' => $this->u_action_for_mode('dashboard'),
			'U_DIAGNOSTICS' => $this->u_action_for_mode('diagnostics'),
			'U_INTEGRITY' => $this->u_action_for_mode('integrity'),
			'U_SETTINGS' => $this->u_action_for_mode('settings'),
			'U_CATEGORIES' => $this->u_action_for_mode('categories'),
			'U_ITEMS' => $this->u_action_for_mode('items'),
			'U_PENDING' => $this->u_action_for_mode('pending'),
			'U_FILES' => $this->u_action_for_mode('files'),
			'U_LOGS' => $this->u_action_for_mode('logs'),
			'PENDING_TOTAL_GLOBAL' => $pending_total_global,
			'S_HAS_PENDING_GLOBAL' => $pending_total_global > 0,
			'DOWNLOADCENTER_EXTENSION_VERSION' => isset($config['mundophpbb_downloadcenter_version']) ? $config['mundophpbb_downloadcenter_version'] : '1.3.4',
			'DOWNLOADCENTER_BETA_STAGE' => $user->lang('ACP_DOWNLOADCENTER_BETA_STAGE'),
		]);
	}
}

<?php

namespace mundophpbb\downloadcenter\event;

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\template\template;
use phpbb\user;
use phpbb\auth\auth;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected $config;
	protected $helper;
	protected $template;
	protected $user;
	protected $auth;

	public function __construct(config $config, helper $helper, template $template, user $user, auth $auth)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.page_header' => 'add_page_header_vars',
			'core.permissions' => 'add_permissions',
		];
	}

	/**
	 * Registers the Download Center permission category and labels in the ACP.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function add_permissions($event)
	{
		$categories = $event['categories'];
		$categories['downloadcenter'] = 'ACL_CAT_DOWNLOADCENTER';
		$event['categories'] = $categories;

		$permissions = $event['permissions'];
		foreach (['u_downloadcenter_view', 'u_downloadcenter_download', 'u_downloadcenter_submit', 'm_downloadcenter_approve', 'a_downloadcenter_manage'] as $permission)
		{
			$permissions[$permission] = [
				'lang' => 'ACL_' . strtoupper($permission),
				'cat' => 'downloadcenter',
			];
		}
		$event['permissions'] = $permissions;
	}

	public function add_page_header_vars()
	{
		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		$this->template->assign_vars([
			'S_DOWNLOADCENTER_ENABLED' => !empty($this->config['mundophpbb_downloadcenter_enabled']) && $this->can_view(),
			'U_DOWNLOADCENTER' => $this->helper->route('mundophpbb_downloadcenter_index'),
		]);
	}


	protected function can_view()
	{
		if (isset($this->config['mundophpbb_downloadcenter_permission_mode']) && $this->config['mundophpbb_downloadcenter_permission_mode'] === 'acl')
		{
			return $this->is_admin() || $this->auth->acl_get('u_downloadcenter_view');
		}

		return $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all');
	}

	protected function access_allowed($mode)
	{
		switch ($mode)
		{
			case 'admin':
				return $this->is_admin();

			case 'registered':
				return (int) $this->user->data['user_id'] !== ANONYMOUS;

			case 'all':
			default:
				return true;
		}
	}

	protected function is_admin()
	{
		return ((int) $this->user->data['user_type'] === USER_FOUNDER) || $this->auth->acl_get('a_board') || $this->auth->acl_get('a_downloadcenter_manage');
	}
}

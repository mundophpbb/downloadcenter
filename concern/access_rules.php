<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\concern;

/**
 * Access mode helpers shared by the public controllers.
 */
trait access_rules
{
	protected function can_view()
	{
		if ($this->use_acl_permissions())
		{
			return $this->is_admin() || $this->auth->acl_get('u_downloadcenter_view');
		}

		return $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all');
	}

	protected function can_download()
	{
		$min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];

		if ($this->use_acl_permissions())
		{
			return $this->can_view()
				&& ($this->is_admin() || $this->auth->acl_get('u_downloadcenter_download'))
				&& ($min_posts <= 0 || (int) $this->user->data['user_posts'] >= $min_posts);
		}

		return $this->access_allowed($this->effective_access_mode('mundophpbb_downloadcenter_download_access', 'registered', true))
			&& ($min_posts <= 0 || (int) $this->user->data['user_posts'] >= $min_posts);
	}

	protected function effective_access_mode($config_key, $default, $respect_view_access = true)
	{
		$mode = isset($this->config[$config_key]) ? (string) $this->config[$config_key] : $default;
		$mode = $this->normalise_access_mode($mode, $default);

		if ($respect_view_access)
		{
			$view_mode = isset($this->config['mundophpbb_downloadcenter_view_access']) ? (string) $this->config['mundophpbb_downloadcenter_view_access'] : 'all';
			$mode = $this->most_restrictive_access_mode($view_mode, $mode);
		}

		return $mode;
	}

	protected function normalise_access_mode($mode, $default = 'registered')
	{
		return in_array($mode, ['all', 'registered', 'admin'], true) ? $mode : $default;
	}

	protected function most_restrictive_access_mode($first, $second)
	{
		$weights = ['all' => 0, 'registered' => 1, 'admin' => 2];
		$first = $this->normalise_access_mode($first, 'all');
		$second = $this->normalise_access_mode($second, 'registered');

		return ($weights[$first] >= $weights[$second]) ? $first : $second;
	}

	protected function use_acl_permissions()
	{
		return isset($this->config['mundophpbb_downloadcenter_permission_mode']) && $this->config['mundophpbb_downloadcenter_permission_mode'] === 'acl';
	}

	protected function is_admin()
	{
		return ((int) $this->user->data['user_type'] === USER_FOUNDER) || $this->auth->acl_get('a_board') || $this->auth->acl_get('a_downloadcenter_manage');
	}
}

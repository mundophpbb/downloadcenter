<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\controller\section;

/**
 * Public access checks.
 */
trait access
{
	protected function can_submit()
	{
		if ($this->use_acl_permissions())
		{
			return $this->submissions_enabled()
				&& $this->can_view()
				&& (int) $this->user->data['user_id'] !== ANONYMOUS
				&& ($this->is_admin() || $this->auth->acl_get('u_downloadcenter_submit'));
		}

		return $this->submissions_enabled()
			&& $this->access_allowed($this->effective_access_mode('mundophpbb_downloadcenter_submit_access', 'registered', true));
	}

	protected function download_block_reason()
	{
		$min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];

		if ($this->use_acl_permissions())
		{
			if (!$this->can_download())
			{
				return $this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_DOWNLOAD');
			}
		}
		else if (!$this->access_allowed($this->effective_access_mode('mundophpbb_downloadcenter_download_access', 'registered', true)))
		{
			return $this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_DOWNLOAD');
		}

		if ($min_posts > 0 && (int) $this->user->data['user_posts'] < $min_posts)
		{
			return $this->user->lang('DOWNLOADCENTER_MIN_POSTS_REQUIRED', $min_posts);
		}

		return '';
	}

	protected function submissions_enabled()
	{
		return (!isset($this->config['mundophpbb_downloadcenter_allow_submissions']) || (bool) $this->config['mundophpbb_downloadcenter_allow_submissions']);
	}

	protected function access_allowed($mode)
	{
		switch ($mode)
		{
			case 'admin':
				return $this->is_admin();

			case 'registered':
				return !$this->is_anonymous();

			case 'all':
			default:
				return true;
		}
	}

	protected function can_approve()
	{
		return $this->is_admin() || $this->auth->acl_get('m_downloadcenter_approve');
	}

	protected function is_anonymous()
	{
		return (int) $this->user->data['user_id'] === ANONYMOUS;
	}
}

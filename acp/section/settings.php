<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\acp\section;

/**
 * ACP settings and permission summaries.
 */
trait settings
{
	protected function handle_settings($config, $request, $template, $user)
	{
		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_settings'))
			{
				trigger_error('FORM_INVALID');
			}

			$view_access = $this->valid_access_mode($request->variable('downloadcenter_view_access', 'all'), 'all');
			$download_access = $this->inherit_access_floor($view_access, $this->valid_access_mode($request->variable('downloadcenter_download_access', 'registered'), 'registered'));
			$submit_access = $this->normalise_submit_access($view_access, $request->variable('downloadcenter_submit_access', 'registered'));

			$config->set('mundophpbb_downloadcenter_enabled', $request->variable('downloadcenter_enabled', 0));
			$config->set('mundophpbb_downloadcenter_min_posts', max(0, $request->variable('downloadcenter_min_posts', 0)));
			$config->set('mundophpbb_downloadcenter_allow_submissions', $request->variable('downloadcenter_allow_submissions', 1));
			$config->set('mundophpbb_downloadcenter_view_access', $view_access);
			$config->set('mundophpbb_downloadcenter_download_access', $download_access);
			$config->set('mundophpbb_downloadcenter_submit_access', $submit_access);
			$config->set('mundophpbb_downloadcenter_permission_mode', $request->variable('downloadcenter_permission_mode', 'global') === 'acl' ? 'acl' : 'global');
			$config->set('mundophpbb_downloadcenter_duplicate_window', max(0, $request->variable('downloadcenter_duplicate_window', 3600)));
			$rules_topic_input = trim($request->variable('downloadcenter_rules_topic_id', '', true));
			$rules_topic_id = 0;

			if ($rules_topic_input !== '')
			{
				if (preg_match('#(?:^|[?&])t=([0-9]+)#', $rules_topic_input, $rules_topic_match))
				{
					$rules_topic_id = (int) $rules_topic_match[1];
				}
				else
				{
					$rules_topic_id = (int) $rules_topic_input;
				}
			}

			$config->set('mundophpbb_downloadcenter_rules_topic_id', max(0, $rules_topic_id));
			$selected_support_forum_id = max(0, $request->variable('downloadcenter_support_forum_id', 0));
			if ($selected_support_forum_id > 0 && !$this->is_valid_support_forum($selected_support_forum_id))
			{
				$selected_support_forum_id = 0;
			}
			$config->set('mundophpbb_downloadcenter_support_forum_id', $selected_support_forum_id);
			$config->set('mundophpbb_downloadcenter_notifications_enabled', $request->variable('downloadcenter_notifications_enabled', 1));
			$config->set('mundophpbb_downloadcenter_public_per_page', max(1, $request->variable('downloadcenter_public_per_page', 12)));
			$config->set('mundophpbb_downloadcenter_acp_per_page', max(1, $request->variable('downloadcenter_acp_per_page', 20)));
			$config->set('mundophpbb_downloadcenter_logs_per_page', max(1, $request->variable('downloadcenter_logs_per_page', 50)));
			if ($request->is_set_post('downloadcenter_allowed_extensions'))
			{
				$config->set('mundophpbb_downloadcenter_allowed_extensions', $this->normalise_allowed_extensions($request->variable('downloadcenter_allowed_extensions', '', true)));
			}
			if ($request->is_set_post('downloadcenter_max_upload_mb'))
			{
				$config->set('mundophpbb_downloadcenter_max_upload_mb', max(1, $request->variable('downloadcenter_max_upload_mb', 20)));
			}
			$config->set('mundophpbb_downloadcenter_use_phpbb_attachments', 1);
			$config->set('mundophpbb_downloadcenter_show_public_stats', $request->variable('downloadcenter_show_public_stats', 1));
			$config->set('mundophpbb_downloadcenter_feed_enabled', $request->variable('downloadcenter_feed_enabled', 1));
			$config->set('mundophpbb_downloadcenter_rate_limit_count', max(0, $request->variable('downloadcenter_rate_limit_count', 0)));
			$config->set('mundophpbb_downloadcenter_rate_limit_window', max(10, $request->variable('downloadcenter_rate_limit_window', 60)));

			global $db;
			$this->add_log($db, $user, 'settings_saved', $user->lang('ACP_DOWNLOADCENTER_LOG_SETTINGS_SAVED'));

			trigger_error($user->lang('ACP_DOWNLOADCENTER_SAVED') . adm_back_link($this->u_action));
		}

		$selected_support_forum_id = isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0;
		$this->assign_support_forum_options($template, $selected_support_forum_id);
		$this->assign_acl_permission_diagnostics($template, $user);

		$template->assign_vars([
			'DOWNLOADCENTER_ENABLED'   => (bool) $config['mundophpbb_downloadcenter_enabled'],
			'DOWNLOADCENTER_MIN_POSTS' => (int) $config['mundophpbb_downloadcenter_min_posts'],
			'DOWNLOADCENTER_ALLOW_SUBMISSIONS' => isset($config['mundophpbb_downloadcenter_allow_submissions']) ? (bool) $config['mundophpbb_downloadcenter_allow_submissions'] : true,
			'DOWNLOADCENTER_PERMISSION_MODE' => isset($config['mundophpbb_downloadcenter_permission_mode']) ? (string) $config['mundophpbb_downloadcenter_permission_mode'] : 'global',
			'S_PERMISSION_MODE_GLOBAL' => !isset($config['mundophpbb_downloadcenter_permission_mode']) || $config['mundophpbb_downloadcenter_permission_mode'] !== 'acl',
			'S_PERMISSION_MODE_ACL' => isset($config['mundophpbb_downloadcenter_permission_mode']) && $config['mundophpbb_downloadcenter_permission_mode'] === 'acl',
			'DOWNLOADCENTER_PERMISSION_MODE_SUMMARY' => $this->permission_mode_summary($user, $config),
			'DOWNLOADCENTER_VIEW_ACCESS' => $this->valid_access_mode(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', 'all'),
			'DOWNLOADCENTER_DOWNLOAD_ACCESS' => $this->inherit_access_floor(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', isset($config['mundophpbb_downloadcenter_download_access']) ? $config['mundophpbb_downloadcenter_download_access'] : 'registered'),
			'DOWNLOADCENTER_SUBMIT_ACCESS' => $this->normalise_submit_access(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', isset($config['mundophpbb_downloadcenter_submit_access']) ? $config['mundophpbb_downloadcenter_submit_access'] : 'registered'),
			'DOWNLOADCENTER_PERMISSION_VIEW_SUMMARY' => $this->access_mode_label($user, $this->valid_access_mode(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', 'all')),
			'DOWNLOADCENTER_PERMISSION_DOWNLOAD_SUMMARY' => $this->access_mode_label($user, $this->inherit_access_floor(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', isset($config['mundophpbb_downloadcenter_download_access']) ? $config['mundophpbb_downloadcenter_download_access'] : 'registered')),
			'DOWNLOADCENTER_PERMISSION_SUBMIT_SUMMARY' => $this->access_mode_label($user, $this->normalise_submit_access(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', isset($config['mundophpbb_downloadcenter_submit_access']) ? $config['mundophpbb_downloadcenter_submit_access'] : 'registered')),
			'DOWNLOADCENTER_PERMISSION_EFFECT_SUMMARY' => $this->permission_effect_summary($user, $config),
			'DOWNLOADCENTER_PERMISSION_WARNING' => $this->permission_warning($user, $config),
			'S_PERMISSION_HAS_WARNING' => $this->permission_warning($user, $config) !== '',
			'S_PERMISSION_SUBMISSIONS_DISABLED' => !(isset($config['mundophpbb_downloadcenter_allow_submissions']) ? (bool) $config['mundophpbb_downloadcenter_allow_submissions'] : true),
			'S_VIEW_ACCESS_ALL' => (!isset($config['mundophpbb_downloadcenter_view_access']) || $config['mundophpbb_downloadcenter_view_access'] === 'all'),
			'S_VIEW_ACCESS_REGISTERED' => (isset($config['mundophpbb_downloadcenter_view_access']) && $config['mundophpbb_downloadcenter_view_access'] === 'registered'),
			'S_VIEW_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_view_access']) && $config['mundophpbb_downloadcenter_view_access'] === 'admin'),
			'S_DOWNLOAD_ACCESS_ALL' => (isset($config['mundophpbb_downloadcenter_download_access']) && $config['mundophpbb_downloadcenter_download_access'] === 'all'),
			'S_DOWNLOAD_ACCESS_REGISTERED' => (!isset($config['mundophpbb_downloadcenter_download_access']) || $config['mundophpbb_downloadcenter_download_access'] === 'registered'),
			'S_DOWNLOAD_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_download_access']) && $config['mundophpbb_downloadcenter_download_access'] === 'admin'),
			'S_SUBMIT_ACCESS_REGISTERED' => (!isset($config['mundophpbb_downloadcenter_submit_access']) || $config['mundophpbb_downloadcenter_submit_access'] === 'registered'),
			'S_SUBMIT_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_submit_access']) && $config['mundophpbb_downloadcenter_submit_access'] === 'admin'),
			'DOWNLOADCENTER_DUPLICATE_WINDOW' => isset($config['mundophpbb_downloadcenter_duplicate_window']) ? (int) $config['mundophpbb_downloadcenter_duplicate_window'] : 3600,
			'DOWNLOADCENTER_RULES_TOPIC_ID' => isset($config['mundophpbb_downloadcenter_rules_topic_id']) ? (int) $config['mundophpbb_downloadcenter_rules_topic_id'] : 0,
			'DOWNLOADCENTER_SUPPORT_FORUM_ID' => isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0,
			'DOWNLOADCENTER_NOTIFICATIONS_ENABLED' => !isset($config['mundophpbb_downloadcenter_notifications_enabled']) || (bool) $config['mundophpbb_downloadcenter_notifications_enabled'],
			'DOWNLOADCENTER_PUBLIC_PER_PAGE' => isset($config['mundophpbb_downloadcenter_public_per_page']) ? (int) $config['mundophpbb_downloadcenter_public_per_page'] : 12,
			'DOWNLOADCENTER_ACP_PER_PAGE' => isset($config['mundophpbb_downloadcenter_acp_per_page']) ? (int) $config['mundophpbb_downloadcenter_acp_per_page'] : 20,
			'DOWNLOADCENTER_LOGS_PER_PAGE' => isset($config['mundophpbb_downloadcenter_logs_per_page']) ? (int) $config['mundophpbb_downloadcenter_logs_per_page'] : 50,
			'DOWNLOADCENTER_ALLOWED_EXTENSIONS' => $this->get_allowed_extensions_string(),
			'DOWNLOADCENTER_MAX_UPLOAD_MB' => $this->get_max_upload_mb(),
			'DOWNLOADCENTER_USE_PHPBB_ATTACHMENTS' => true,
			'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text($user),
			'DOWNLOADCENTER_SHOW_PUBLIC_STATS' => !isset($config['mundophpbb_downloadcenter_show_public_stats']) || (bool) $config['mundophpbb_downloadcenter_show_public_stats'],
			'DOWNLOADCENTER_FEED_ENABLED' => !isset($config['mundophpbb_downloadcenter_feed_enabled']) || (bool) $config['mundophpbb_downloadcenter_feed_enabled'],
			'DOWNLOADCENTER_RATE_LIMIT_COUNT' => isset($config['mundophpbb_downloadcenter_rate_limit_count']) ? (int) $config['mundophpbb_downloadcenter_rate_limit_count'] : 0,
			'DOWNLOADCENTER_RATE_LIMIT_WINDOW' => isset($config['mundophpbb_downloadcenter_rate_limit_window']) ? (int) $config['mundophpbb_downloadcenter_rate_limit_window'] : 60,
		]);
	}

	protected function assign_acl_permission_diagnostics($template, $user)
	{
		global $auth;

		$permissions = [
			'u_downloadcenter_view' => $user->lang('ACP_DOWNLOADCENTER_ACL_VIEW'),
			'u_downloadcenter_download' => $user->lang('ACP_DOWNLOADCENTER_ACL_DOWNLOAD'),
			'u_downloadcenter_submit' => $user->lang('ACP_DOWNLOADCENTER_ACL_SUBMIT'),
			'm_downloadcenter_approve' => $user->lang('ACP_DOWNLOADCENTER_ACL_APPROVE'),
			'a_downloadcenter_manage' => $user->lang('ACP_DOWNLOADCENTER_ACL_MANAGE'),
		];

		$missing_count = 0;
		foreach ($permissions as $permission => $label)
		{
			$granted = $auth->acl_get($permission) || $auth->acl_get('a_board');
			if (!$granted)
			{
				$missing_count++;
			}

			$template->assign_block_vars('acl_permissions', [
				'PERMISSION' => $permission,
				'LABEL' => $label,
				'STATUS' => $granted ? $user->lang('ACP_DOWNLOADCENTER_ACL_GRANTED') : $user->lang('ACP_DOWNLOADCENTER_ACL_NOT_GRANTED'),
				'STATUS_CLASS' => $granted ? 'ok' : 'warn',
			]);
		}

		$template->assign_vars([
			'S_ACL_DIAGNOSTIC_HAS_WARNINGS' => $missing_count > 0,
			'DOWNLOADCENTER_ACL_DIAGNOSTIC_SUMMARY' => $missing_count > 0
				? $user->lang('ACP_DOWNLOADCENTER_ACL_DIAGNOSTIC_WARNING', $missing_count)
				: $user->lang('ACP_DOWNLOADCENTER_ACL_DIAGNOSTIC_OK'),
		]);
	}

	protected function inherit_access_floor($view_mode, $requested_mode)
	{
		$weights = ['all' => 0, 'registered' => 1, 'admin' => 2];
		$view_mode = $this->valid_access_mode($view_mode, 'all');
		$requested_mode = $this->valid_access_mode($requested_mode, 'registered');

		return ($weights[$view_mode] >= $weights[$requested_mode]) ? $view_mode : $requested_mode;
	}

	protected function normalise_submit_access($view_mode, $requested_mode)
	{
		$submit_access = $this->inherit_access_floor($view_mode, $this->valid_access_mode($requested_mode, 'registered'));

		return ($submit_access === 'all') ? 'registered' : $submit_access;
	}

	protected function access_mode_label($user, $mode)
	{
		$mode = $this->valid_access_mode($mode, 'registered');

		switch ($mode)
		{
			case 'all':
				return $user->lang('ACP_DOWNLOADCENTER_ACCESS_ALL');
			case 'admin':
				return $user->lang('ACP_DOWNLOADCENTER_ACCESS_ADMIN');
			case 'registered':
			default:
				return $user->lang('ACP_DOWNLOADCENTER_ACCESS_REGISTERED');
		}
	}

	protected function permission_mode_summary($user, $config)
	{
		return (isset($config['mundophpbb_downloadcenter_permission_mode']) && $config['mundophpbb_downloadcenter_permission_mode'] === 'acl')
			? $user->lang('ACP_DOWNLOADCENTER_PERMISSION_MODE_ACL_SUMMARY')
			: $user->lang('ACP_DOWNLOADCENTER_PERMISSION_MODE_GLOBAL_SUMMARY');
	}

	protected function permission_effect_summary($user, $config)
	{
		if (isset($config['mundophpbb_downloadcenter_permission_mode']) && $config['mundophpbb_downloadcenter_permission_mode'] === 'acl')
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_EFFECT_ACL_TEXT');
		}

		$view_access = $this->valid_access_mode(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', 'all');
		$download_access = $this->inherit_access_floor($view_access, isset($config['mundophpbb_downloadcenter_download_access']) ? $config['mundophpbb_downloadcenter_download_access'] : 'registered');
		$submit_access = $this->normalise_submit_access($view_access, isset($config['mundophpbb_downloadcenter_submit_access']) ? $config['mundophpbb_downloadcenter_submit_access'] : 'registered');
		$min_posts = isset($config['mundophpbb_downloadcenter_min_posts']) ? (int) $config['mundophpbb_downloadcenter_min_posts'] : 0;

		$summary = $user->lang(
			'ACP_DOWNLOADCENTER_PERMISSION_EFFECT_SUMMARY_TEXT',
			$this->access_mode_label($user, $view_access),
			$this->access_mode_label($user, $download_access),
			$this->access_mode_label($user, $submit_access)
		);

		if ($min_posts > 0)
		{
			$summary .= ' ' . $user->lang('ACP_DOWNLOADCENTER_PERMISSION_MIN_POSTS_NOTE', (string) $min_posts);
		}

		return $summary;
	}

	protected function permission_warning($user, $config)
	{
		if (isset($config['mundophpbb_downloadcenter_permission_mode']) && $config['mundophpbb_downloadcenter_permission_mode'] === 'acl')
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_ACL_MODE');
		}

		$enabled = !isset($config['mundophpbb_downloadcenter_enabled']) || (bool) $config['mundophpbb_downloadcenter_enabled'];
		$allow_submissions = isset($config['mundophpbb_downloadcenter_allow_submissions']) ? (bool) $config['mundophpbb_downloadcenter_allow_submissions'] : true;
		$view_access = $this->valid_access_mode(isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all', 'all');
		$download_access = $this->inherit_access_floor($view_access, isset($config['mundophpbb_downloadcenter_download_access']) ? $config['mundophpbb_downloadcenter_download_access'] : 'registered');
		$submit_access = $this->normalise_submit_access($view_access, isset($config['mundophpbb_downloadcenter_submit_access']) ? $config['mundophpbb_downloadcenter_submit_access'] : 'registered');

		if (!$enabled)
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_DISABLED');
		}

		if ($view_access === 'admin')
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_ADMIN_VIEW');
		}

		if ($download_access === 'admin')
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_ADMIN_DOWNLOAD');
		}

		if (!$allow_submissions)
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_SUBMISSIONS_DISABLED');
		}

		if ($submit_access === 'admin')
		{
			return $user->lang('ACP_DOWNLOADCENTER_PERMISSION_WARNING_ADMIN_SUBMIT');
		}

		return '';
	}

	protected function valid_access_mode($mode, $default)
	{
		$allowed = ['all', 'registered', 'admin'];

		return in_array($mode, $allowed, true) ? $mode : $default;
	}

	protected function assign_support_forum_options($template, $selected_forum_id)
	{
		$template->assign_block_vars('support_forums', [
			'FORUM_ID' => 0,
			'FORUM_NAME' => $this->get_lang_string('ACP_DOWNLOADCENTER_SUPPORT_FORUM_DISABLED'),
			'SELECTED' => ((int) $selected_forum_id === 0),
		]);

		$forums = $this->get_postable_forums();
		foreach ($forums as $forum)
		{
			$padding = str_repeat('&nbsp;&nbsp;&nbsp;', max(0, (int) $forum['level']));
			$template->assign_block_vars('support_forums', [
				'FORUM_ID' => (int) $forum['forum_id'],
				'FORUM_NAME' => $padding . $forum['forum_name'],
				'SELECTED' => ((int) $selected_forum_id === (int) $forum['forum_id']),
			]);
		}
	}

	protected function get_postable_forums()
	{
		global $db;

		$forum_type_post = defined('FORUM_POST') ? FORUM_POST : 1;
		$sql = 'SELECT forum_id, forum_name, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			WHERE forum_type = ' . (int) $forum_type_post . '
			ORDER BY left_id ASC';
		$result = $db->sql_query($sql);

		$forums = [];
		$stack = [];
		while ($row = $db->sql_fetchrow($result))
		{
			while (!empty($stack) && end($stack) < (int) $row['right_id'])
			{
				array_pop($stack);
			}

			$row['level'] = count($stack);
			$forums[] = $row;
			$stack[] = (int) $row['right_id'];
		}
		$db->sql_freeresult($result);

		return $forums;
	}

	protected function is_valid_support_forum($forum_id)
	{
		global $db;

		$forum_id = (int) $forum_id;
		if ($forum_id <= 0)
		{
			return false;
		}

		$forum_type_post = defined('FORUM_POST') ? FORUM_POST : 1;
		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id . '
				AND forum_type = ' . (int) $forum_type_post;
		$result = $db->sql_query_limit($sql, 1);
		$valid = (bool) $db->sql_fetchfield('forum_id');
		$db->sql_freeresult($result);

		return $valid;
	}
}

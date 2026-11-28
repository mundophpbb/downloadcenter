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
 * ACP logs.
 */
trait logs
{
	protected function handle_logs($db, $request, $template, $user)
	{
		$logs_table = $this->table_prefix . 'downloadcenter_logs';
		$action_filter = htmlspecialchars_decode(trim($request->variable('log_action', '', true)), ENT_QUOTES);
		$user_filter = htmlspecialchars_decode(trim($request->variable('log_user', '', true)), ENT_QUOTES);
		$user_id_filter = max(0, $request->variable('log_user_id', 0));
		$ip_filter = htmlspecialchars_decode(trim($request->variable('log_ip', '')), ENT_QUOTES);
		$invalid_ip = $ip_filter !== '' && filter_var($ip_filter, FILTER_VALIDATE_IP) === false;
		$item_filter = max(0, $request->variable('log_item_id', 0));
		$version_filter = max(0, $request->variable('log_version_id', 0));
		$message_filter = htmlspecialchars_decode(trim($request->variable('log_message', '', true)), ENT_QUOTES);
		$date_from_filter = htmlspecialchars_decode(trim($request->variable('log_date_from', '', true)), ENT_QUOTES);
		$date_to_filter = htmlspecialchars_decode(trim($request->variable('log_date_to', '', true)), ENT_QUOTES);
		$source_values = ['human' => \mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN, 'bot' => \mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT, 'unknown' => \mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN];
		$source_filter = $request->variable('log_source', '');
		$source_filter = isset($source_values[$source_filter]) ? $source_filter : '';
		$filters = [
			'log_action' => $action_filter,
			'log_user' => $user_filter,
			'log_user_id' => $user_id_filter,
			'log_ip' => $ip_filter,
			'log_item_id' => $item_filter,
			'log_version_id' => $version_filter,
			'log_message' => $message_filter,
			'log_date_from' => $date_from_filter,
			'log_date_to' => $date_to_filter,
			'log_source' => $source_filter,
		];
		$clear = $request->variable('clear', 0);

		if ($clear)
		{
			if (confirm_box(true))
			{
				$db->sql_query('DELETE FROM ' . $logs_table);
				$this->add_log($db, $user, 'logs_cleared', $user->lang('ACP_DOWNLOADCENTER_LOGS_CLEARED_MESSAGE'));
				trigger_error($user->lang('ACP_DOWNLOADCENTER_LOGS_CLEARED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_CLEAR_LOGS'), build_hidden_fields(['clear' => 1] + $filters, true));
			}
		}

		$where = [];
		if ($action_filter !== '')
		{
			$where[] = "log_action = '" . $db->sql_escape($action_filter) . "'";
		}
		if ($user_filter !== '')
		{
			$where[] = "username " . $db->sql_like_expression($db->get_any_char() . $user_filter . $db->get_any_char());
		}
		if ($user_id_filter > 0)
		{
			$where[] = 'user_id = ' . (int) $user_id_filter;
		}
		if ($invalid_ip)
		{
			$where[] = '1 = 0';
		}
		else if ($ip_filter !== '')
		{
			$where[] = "user_ip = '" . $db->sql_escape($ip_filter) . "'";
		}
		if ($item_filter > 0)
		{
			$where[] = 'item_id = ' . (int) $item_filter;
		}
		if ($version_filter > 0)
		{
			$where[] = 'version_id = ' . (int) $version_filter;
		}
		if ($message_filter !== '')
		{
			$where[] = "log_message " . $db->sql_like_expression($db->get_any_char() . $message_filter . $db->get_any_char());
		}
		if ($source_filter !== '')
		{
			// The source only means something for downloads.
			$where[] = "log_action = 'download'";
			$where[] = 'log_source = ' . (int) $source_values[$source_filter];
		}
		if ($date_from_filter !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_from_filter))
		{
			$from_time = strtotime($date_from_filter . ' 00:00:00');
			if ($from_time !== false)
			{
				$where[] = 'log_time >= ' . (int) $from_time;
			}
		}
		if ($date_to_filter !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_to_filter))
		{
			$to_time = strtotime($date_to_filter . ' 23:59:59');
			if ($to_time !== false)
			{
				$where[] = 'log_time <= ' . (int) $to_time;
			}
		}

		global $config;
		$start = max(0, $request->variable('start', 0));
		$per_page = isset($config['mundophpbb_downloadcenter_logs_per_page']) ? max(1, (int) $config['mundophpbb_downloadcenter_logs_per_page']) : 50;

		$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';
		$sql = 'SELECT COUNT(*) AS total FROM ' . $logs_table . $sql_where;
		$result = $db->sql_query($sql);
		$total_logs = (int) $db->sql_fetchfield('total');
		$db->sql_freeresult($result);
		$start = $this->normalize_start($start, $per_page, $total_logs);

		$today_start = strtotime(date('Y-m-d 00:00:00'));
		$sql = 'SELECT COUNT(*) AS total FROM ' . $logs_table . ' WHERE log_time >= ' . (int) $today_start;
		$result = $db->sql_query($sql);
		$logs_today = (int) $db->sql_fetchfield('total');
		$db->sql_freeresult($result);

		$sql = "SELECT COUNT(*) AS total FROM " . $logs_table . " WHERE log_action " . $db->sql_like_expression('integrity' . $db->get_any_char());
		$result = $db->sql_query($sql);
		$integrity_logs = (int) $db->sql_fetchfield('total');
		$db->sql_freeresult($result);

		// People / bots / not classified among the downloads that match the other filters.
		$source_where = array_values(array_filter($where, function ($condition) {
			return strpos($condition, 'log_source = ') !== 0 && $condition !== "log_action = 'download'";
		}));
		$source_where[] = "log_action = 'download'";
		$source_counts = ['human' => 0, 'bot' => 0, 'unknown' => 0];
		$sql = 'SELECT log_source, COUNT(*) AS total
			FROM ' . $logs_table . '
			WHERE ' . implode(' AND ', $source_where) . '
			GROUP BY log_source';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$key = array_search((int) $row['log_source'], $source_values, true);
			$source_counts[$key === false ? 'human' : $key] += (int) $row['total'];
		}
		$db->sql_freeresult($result);
		$source_download_total = array_sum($source_counts);
		$show_source_summary = $source_download_total > 0 && ($action_filter === '' || $action_filter === 'download');

		foreach ($source_counts as $key => $total)
		{
			if ($key === 'unknown' && $total === 0)
			{
				continue;
			}
			$template->assign_block_vars('log_source_stats', [
				'KEY' => $key,
				'LABEL' => $user->lang('ACP_DOWNLOADCENTER_SOURCE_' . strtoupper($key) . '_PLURAL'),
				'TOTAL' => $total,
				'PERCENT' => $source_download_total > 0 ? round($total * 100 / $source_download_total) : 0,
				'S_ACTIVE' => $source_filter === $key,
				'U_FILTER' => $this->pagination_url($this->u_action, ['log_source' => $key, 'log_action' => 'download'] + $filters),
			]);
		}

		$sql = 'SELECT log_action, COUNT(*) AS total
			FROM ' . $logs_table . $sql_where . '
			GROUP BY log_action
			ORDER BY total DESC, log_action ASC';
		$result = $db->sql_query_limit($sql, 8);
		while ($row = $db->sql_fetchrow($result))
		{
			$label_key = 'ACP_DOWNLOADCENTER_LOG_ACTION_' . strtoupper($row['log_action']);
			$template->assign_block_vars('log_action_stats', [
				'ACTION' => $row['log_action'],
				'LABEL' => utf8_htmlspecialchars(htmlspecialchars_decode($user->lang($label_key), ENT_QUOTES)),
				'TOTAL' => (int) $row['total'],
				'U_FILTER' => $this->pagination_url($this->u_action, ['log_action' => $row['log_action']] + $filters),
			]);
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT * FROM ' . $logs_table . $sql_where . ' ORDER BY log_time DESC, log_id DESC';
		$result = $db->sql_query_limit($sql, $per_page, $start);
		while ($row = $db->sql_fetchrow($result))
		{
			$label_key = 'ACP_DOWNLOADCENTER_LOG_ACTION_' . strtoupper($row['log_action']);
			$item_id = (int) $row['item_id'];
			$version_id = (int) $row['version_id'];
			$user_id = (int) $row['user_id'];
			$ip = filter_var($row['user_ip'], FILTER_VALIDATE_IP) ?: '';
			$is_download = $row['log_action'] === 'download';
			$source_key = array_search((int) $row['log_source'], $source_values, true) ?: 'human';

			$template->assign_block_vars('logs', [
				'LOG_ID' => (int) $row['log_id'],
				'TIME' => $user->format_date((int) $row['log_time']),
				'USERNAME' => utf8_htmlspecialchars(htmlspecialchars_decode($row['username'] ?: '-', ENT_QUOTES)),
				'USER_ID' => $user_id,
				'ACTION' => utf8_htmlspecialchars(htmlspecialchars_decode($row['log_action'], ENT_QUOTES)),
				'ACTION_LABEL' => utf8_htmlspecialchars(htmlspecialchars_decode($user->lang($label_key), ENT_QUOTES)),
				'MESSAGE' => utf8_htmlspecialchars(htmlspecialchars_decode($row['log_message'], ENT_QUOTES)),
				'ITEM_ID' => $item_id,
				'VERSION_ID' => $version_id,
				'USER_IP' => $ip,
				'S_SHOW_SOURCE' => $is_download,
				'SOURCE' => $source_key,
				'SOURCE_LABEL' => $user->lang('ACP_DOWNLOADCENTER_SOURCE_' . strtoupper($source_key)),
				'USER_AGENT' => utf8_htmlspecialchars(htmlspecialchars_decode((string) $row['user_agent'], ENT_QUOTES)),
				'U_SOURCE_LOGS' => $is_download ? $this->pagination_url($this->u_action, ['log_source' => $source_key, 'log_action' => 'download'] + $filters) : '',
				'U_IP_LOGS' => $ip !== '' ? $this->pagination_url($this->u_action, ['log_ip' => $ip] + $filters) : '',
				'U_USER_LOGS' => $user_id > 0 ? $this->pagination_url($this->u_action, ['log_user_id' => $user_id, 'log_user' => ''] + $filters) : '',
				'U_ITEM' => $item_id > 0 ? $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . $item_id : '',
				'U_ITEM_LOGS' => $item_id > 0 ? $this->pagination_url($this->u_action, ['log_item_id' => $item_id] + $filters) : '',
				'U_VERSION_LOGS' => $version_id > 0 ? $this->pagination_url($this->u_action, ['log_version_id' => $version_id] + $filters) : '',
			]);
		}
		$db->sql_freeresult($result);

		$actions = [
			'settings_saved', 'category_created', 'category_updated', 'category_deleted',
			'item_created', 'item_updated', 'item_deleted', 'item_approved', 'item_unapproved',
			'item_image_uploaded', 'item_image_cleared',
			'version_created', 'version_updated', 'version_set_current', 'version_changelog_updated', 'version_deleted', 'version_file_deleted',
			'screenshot_created', 'screenshot_updated', 'screenshot_deleted',
			'library_file_deleted', 'orphan_files_deleted',
			'support_topic_created', 'support_topic_updated',
			'public_submission', 'author_edit', 'download', 'diagnostics_rebuild',
			'integrity_fix_current_version', 'integrity_clean_mixed_target', 'integrity_rebuild_download_counters',
			'integrity_delete_orphan_download', 'integrity_delete_orphan_screenshot', 'integrity_delete_orphan_version',
			'logs_cleared',
		];

		foreach ($actions as $action)
		{
			$template->assign_block_vars('log_actions', [
				'VALUE' => $action,
				'LABEL' => $user->lang('ACP_DOWNLOADCENTER_LOG_ACTION_' . strtoupper($action)),
				'S_SELECTED' => $action_filter === $action,
			]);
		}

		$log_pagination_url = $this->pagination_url($this->u_action, $filters);

		$template->assign_vars([
			'LOG_ACTION_FILTER' => utf8_htmlspecialchars($action_filter),
			'LOG_USER_FILTER' => utf8_htmlspecialchars($user_filter),
			'LOG_USER_ID_FILTER' => $user_id_filter,
			'LOG_IP_FILTER' => utf8_htmlspecialchars($ip_filter),
			'S_LOG_IP_INVALID' => $invalid_ip,
			'LOG_ITEM_ID_FILTER' => $item_filter,
			'LOG_VERSION_ID_FILTER' => $version_filter,
			'LOG_MESSAGE_FILTER' => utf8_htmlspecialchars($message_filter),
			'LOG_DATE_FROM_FILTER' => utf8_htmlspecialchars($date_from_filter),
			'LOG_DATE_TO_FILTER' => utf8_htmlspecialchars($date_to_filter),
			'LOG_SOURCE_FILTER' => $source_filter,
			'S_LOG_SOURCE_SUMMARY' => $show_source_summary,
			'LOG_SOURCE_DOWNLOAD_TOTAL' => $source_download_total,
			'LOGS_TOTAL_MATCHING' => $total_logs,
			'LOGS_TODAY' => $logs_today,
			'LOGS_INTEGRITY_TOTAL' => $integrity_logs,
			'U_CLEAR_LOGS' => $this->u_action . '&amp;clear=1',
			'U_LOGS_INTEGRITY' => $this->u_action . '&amp;log_action=integrity_fix_current_version',
			'LOGS_PAGINATION' => $this->make_pagination($log_pagination_url, $total_logs, $per_page, $start),
			'LOGS_PAGE_NUMBER' => $this->make_page_number($total_logs, $per_page, $start),
			'S_LOGS_HAS_PAGINATION' => $total_logs > $per_page,
		]);
	}

	protected function add_log($db, $user, $action, $message, $item_id = 0, $version_id = 0)
	{
		\mundophpbb\downloadcenter\service\audit_log::add($db, $this->table_prefix . 'downloadcenter_logs', $user, $action, $message, $item_id, $version_id);
	}
}

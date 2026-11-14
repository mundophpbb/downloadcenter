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
		$action_filter = trim($request->variable('log_action', '', true));
		$user_filter = trim($request->variable('log_user', '', true));
		$item_filter = max(0, $request->variable('log_item_id', 0));
		$version_filter = max(0, $request->variable('log_version_id', 0));
		$message_filter = trim($request->variable('log_message', '', true));
		$date_from_filter = trim($request->variable('log_date_from', '', true));
		$date_to_filter = trim($request->variable('log_date_to', '', true));
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
				confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_CLEAR_LOGS'), build_hidden_fields([
					'clear' => 1,
					'log_action' => $action_filter,
					'log_user' => $user_filter,
					'log_item_id' => $item_filter,
					'log_version_id' => $version_filter,
					'log_message' => $message_filter,
					'log_date_from' => $date_from_filter,
					'log_date_to' => $date_to_filter,
				]));
			}
		}

		$where = [];
		if ($action_filter !== '')
		{
			$where[] = "log_action = '" . $db->sql_escape($action_filter) . "'";
		}
		if ($user_filter !== '')
		{
			$where[] = "username " . $db->sql_like_expression($db->get_any_char() . $db->sql_escape($user_filter) . $db->get_any_char());
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
			$where[] = "log_message " . $db->sql_like_expression($db->get_any_char() . $db->sql_escape($message_filter) . $db->get_any_char());
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
				'LABEL' => $user->lang($label_key),
				'TOTAL' => (int) $row['total'],
				'U_FILTER' => $this->u_action . '&amp;log_action=' . urlencode($row['log_action']),
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

			$template->assign_block_vars('logs', [
				'LOG_ID' => (int) $row['log_id'],
				'TIME' => $user->format_date((int) $row['log_time']),
				'USERNAME' => $row['username'] ?: '-',
				'USER_ID' => (int) $row['user_id'],
				'ACTION' => $row['log_action'],
				'ACTION_LABEL' => $user->lang($label_key),
				'MESSAGE' => $row['log_message'],
				'ITEM_ID' => $item_id,
				'VERSION_ID' => $version_id,
				'USER_IP' => $row['user_ip'],
				'U_ITEM' => $item_id > 0 ? $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . $item_id : '',
				'U_ITEM_LOGS' => $item_id > 0 ? $this->u_action . '&amp;log_item_id=' . $item_id : '',
				'U_VERSION_LOGS' => $version_id > 0 ? $this->u_action . '&amp;log_version_id=' . $version_id : '',
			]);
		}
		$db->sql_freeresult($result);

		$actions = [
			'settings_saved', 'category_created', 'category_updated', 'category_deleted',
			'item_created', 'item_updated', 'item_deleted', 'item_approved', 'item_unapproved',
			'item_image_uploaded', 'item_image_cleared',
			'version_created', 'version_updated', 'version_set_current', 'version_changelog_updated', 'version_deleted', 'version_file_deleted',
			'screenshot_created', 'screenshot_deleted',
			'library_file_deleted', 'orphan_files_deleted',
			'support_topic_created', 'support_topic_updated',
			'public_submission', 'download',
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

		$log_pagination_url = $this->pagination_url($this->u_action, [
			'log_action' => $action_filter,
			'log_user' => $user_filter,
			'log_item_id' => $item_filter,
			'log_version_id' => $version_filter,
			'log_message' => $message_filter,
			'log_date_from' => $date_from_filter,
			'log_date_to' => $date_to_filter,
		]);

		$template->assign_vars([
			'LOG_ACTION_FILTER' => $action_filter,
			'LOG_USER_FILTER' => $user_filter,
			'LOG_ITEM_ID_FILTER' => $item_filter,
			'LOG_VERSION_ID_FILTER' => $version_filter,
			'LOG_MESSAGE_FILTER' => $message_filter,
			'LOG_DATE_FROM_FILTER' => $date_from_filter,
			'LOG_DATE_TO_FILTER' => $date_to_filter,
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
		$sql_ary = [
			'user_id' => (int) $user->data['user_id'],
			'username' => isset($user->data['username']) ? (string) $user->data['username'] : '',
			'item_id' => (int) $item_id,
			'version_id' => (int) $version_id,
			'log_action' => (string) $action,
			'log_message' => (string) $message,
			'user_ip' => (string) $user->ip,
			'log_time' => time(),
		];

		$db->sql_query('INSERT INTO ' . $this->table_prefix . 'downloadcenter_logs ' . $db->sql_build_array('INSERT', $sql_ary));
	}
}

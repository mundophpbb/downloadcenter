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
 * ACP dashboard.
 */
trait dashboard
{
	protected function handle_dashboard($db, $template, $user, $config)
	{
		$categories_table = $this->table_prefix . 'downloadcenter_categories';
		$items_table = $this->table_prefix . 'downloadcenter_items';
		$versions_table = $this->table_prefix . 'downloadcenter_versions';
		$screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';
		$downloads_table = $this->table_prefix . 'downloadcenter_downloads';

		$total_categories = $this->count_table_rows($db, $categories_table);
		$total_items = $this->count_table_rows($db, $items_table);
		$total_published_items = $this->count_table_rows($db, $items_table, 'item_enabled = 1 AND item_approved = 1');
		$total_pending_items = $this->count_table_rows($db, $items_table, 'item_approved = 0');
		$total_disabled_items = $this->count_table_rows($db, $items_table, 'item_enabled = 0');
		$total_versions = $this->count_table_rows($db, $versions_table);
		$total_downloads = $this->count_table_rows($db, $downloads_table);
		$source_totals = $this->download_source_counts($db, $downloads_table);
		$human_downloads = $source_totals[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN];
		$bot_downloads = $source_totals[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT];
		$unknown_downloads = $source_totals[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN];

		$dashboard_status_counts = [
			'ready' => 0,
			'disabled' => 0,
			'pending' => 0,
			'no_version' => 0,
			'file_missing' => 0,
			'empty_local_file' => 0,
			'external_invalid' => 0,
			'admin_only' => 0,
		];
		$dashboard_attention_total = 0;

		$sql = 'SELECT i.*, c.category_name, u.username
			FROM ' . $items_table . ' i
			LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			ORDER BY i.item_updated DESC, i.item_id DESC';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$current_version = $this->get_latest_version_for_item($db, $versions_table, (int) $row['item_id']);
			$status = $this->get_item_operational_status($row, $current_version, $config, $user);
			$code = isset($status['code']) ? (string) $status['code'] : 'ready';

			if (!isset($dashboard_status_counts[$code]))
			{
				$dashboard_status_counts[$code] = 0;
			}
			$dashboard_status_counts[$code]++;

			if ($code !== 'ready')
			{
				$dashboard_attention_total++;

				if ($dashboard_attention_total <= 10)
				{
					$template->assign_block_vars('dashboard_attention_items', [
						'ITEM_ID' => (int) $row['item_id'],
						'ITEM_NAME' => $row['item_name'],
						'CATEGORY_NAME' => $row['category_name'] ?: '-',
						'USERNAME' => $row['username'] ?: '-',
						'STATUS' => $status['label'],
						'STATUS_EXPLAIN' => $status['explain'],
						'STATUS_CLASS' => $status['class'],
						'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
					]);
				}
			}
		}
		$db->sql_freeresult($result);

		$dashboard_files_attention = (int) $dashboard_status_counts['file_missing'] + (int) $dashboard_status_counts['empty_local_file'] + (int) $dashboard_status_counts['external_invalid'];

		$template->assign_vars([
			'DASHBOARD_TOTAL_CATEGORIES' => $total_categories,
			'DASHBOARD_TOTAL_ITEMS' => $total_items,
			'DASHBOARD_TOTAL_PUBLISHED_ITEMS' => $total_published_items,
			'DASHBOARD_TOTAL_PENDING_ITEMS' => $total_pending_items,
			'DASHBOARD_TOTAL_DISABLED_ITEMS' => $total_disabled_items,
			'DASHBOARD_TOTAL_VERSIONS' => $total_versions,
			'DASHBOARD_TOTAL_DOWNLOADS' => $total_downloads,
			'DASHBOARD_HUMAN_DOWNLOADS' => $human_downloads,
			'DASHBOARD_BOT_DOWNLOADS' => $bot_downloads,
			'DASHBOARD_UNKNOWN_DOWNLOADS' => $unknown_downloads,
			'DASHBOARD_HUMAN_PERCENT' => $this->source_percent($human_downloads, $total_downloads, $user),
			'DASHBOARD_BOT_PERCENT' => $this->source_percent($bot_downloads, $total_downloads, $user),
			'S_DASHBOARD_HAS_UNKNOWN_DOWNLOADS' => $unknown_downloads > 0,
			'U_DASHBOARD_HUMAN_LOGS' => $this->u_action_for_mode('logs') . '&amp;log_action=download&amp;log_source=human',
			'U_DASHBOARD_BOT_LOGS' => $this->u_action_for_mode('logs') . '&amp;log_action=download&amp;log_source=bot',
			'DASHBOARD_READY_ITEMS' => (int) $dashboard_status_counts['ready'],
			'DASHBOARD_ITEMS_WITHOUT_VERSION' => (int) $dashboard_status_counts['no_version'],
			'DASHBOARD_ITEMS_WITH_FILE_PROBLEMS' => $dashboard_files_attention,
			'DASHBOARD_ITEMS_ADMIN_ONLY' => (int) $dashboard_status_counts['admin_only'],
			'DASHBOARD_ATTENTION_TOTAL' => $dashboard_attention_total,
			'S_DASHBOARD_HAS_ATTENTION' => $dashboard_attention_total > 0,
			'S_DASHBOARD_HAS_FILE_PROBLEMS' => $dashboard_files_attention > 0,
			'S_DASHBOARD_HAS_ITEMS_WITHOUT_VERSION' => (int) $dashboard_status_counts['no_version'] > 0,
			'S_DASHBOARD_HAS_ADMIN_ONLY' => (int) $dashboard_status_counts['admin_only'] > 0,
		]);

		$sql = 'SELECT i.item_id, i.item_name, i.item_downloads, i.item_updated, c.category_name, u.username
			FROM ' . $items_table . ' i
			LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			ORDER BY i.item_downloads DESC, i.item_updated DESC, i.item_name ASC';
		$result = $db->sql_query_limit($sql, 10);
		$top_items = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);
		$item_sources = $this->download_source_counts($db, $downloads_table, 'item_id', array_column($top_items, 'item_id'));
		foreach ($top_items as $row)
		{
			$split = isset($item_sources[(int) $row['item_id']]) ? $item_sources[(int) $row['item_id']] : $this->empty_source_counts();
			$template->assign_block_vars('dashboard_top_items', [
				'HUMAN_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN],
				'BOT_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT],
				'UNKNOWN_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN],
				'ITEM_ID' => (int) $row['item_id'],
				'ITEM_NAME' => $row['item_name'],
				'CATEGORY_NAME' => $row['category_name'] ?: '-',
				'USERNAME' => $row['username'] ?: '-',
				'DOWNLOADS' => (int) $row['item_downloads'],
				'UPDATED' => $row['item_updated'] ? $user->format_date((int) $row['item_updated']) : '-',
				'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
			]);
		}

		$sql = 'SELECT v.version_id, v.item_id, v.version_number, v.phpbb_version, v.version_downloads, v.version_created, i.item_name
			FROM ' . $versions_table . ' v
			LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
			ORDER BY v.version_downloads DESC, v.version_created DESC, v.version_id DESC';
		$result = $db->sql_query_limit($sql, 10);
		$top_versions = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);
		$version_sources = $this->download_source_counts($db, $downloads_table, 'version_id', array_column($top_versions, 'version_id'));
		foreach ($top_versions as $row)
		{
			$split = isset($version_sources[(int) $row['version_id']]) ? $version_sources[(int) $row['version_id']] : $this->empty_source_counts();
			$template->assign_block_vars('dashboard_top_versions', [
				'HUMAN_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN],
				'BOT_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT],
				'UNKNOWN_DOWNLOADS' => $split[\mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN],
				'VERSION_ID' => (int) $row['version_id'],
				'ITEM_ID' => (int) $row['item_id'],
				'ITEM_NAME' => $row['item_name'] ?: '-',
				'VERSION_NUMBER' => $row['version_number'] ?: '-',
				'PHPBB_VERSION' => $row['phpbb_version'] ?: '-',
				'DOWNLOADS' => (int) $row['version_downloads'],
				'CREATED' => $row['version_created'] ? $user->format_date((int) $row['version_created']) : '-',
				'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
			]);
		}

		$sql = 'SELECT d.download_id, d.download_time, d.user_id, d.user_ip, d.download_source, d.user_agent, i.item_name, v.version_number, u.username
			FROM ' . $downloads_table . ' d
			LEFT JOIN ' . $items_table . ' i ON i.item_id = d.item_id
			LEFT JOIN ' . $versions_table . ' v ON v.version_id = d.version_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = d.user_id
			ORDER BY d.download_time DESC, d.download_id DESC';
		$result = $db->sql_query_limit($sql, 10);
		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('dashboard_recent_downloads', [
				'TIME' => $row['download_time'] ? $user->format_date((int) $row['download_time']) : '-',
				'USERNAME' => $row['username'] ?: '-',
				'USER_ID' => (int) $row['user_id'],
				'USER_IP' => $row['user_ip'],
				'ITEM_NAME' => $row['item_name'] ?: '-',
				'VERSION_NUMBER' => $row['version_number'] ?: '-',
				'SOURCE' => $this->download_source_key((int) $row['download_source']),
				'SOURCE_LABEL' => $this->download_source_label((int) $row['download_source'], $user),
				'USER_AGENT' => utf8_htmlspecialchars(htmlspecialchars_decode((string) $row['user_agent'], ENT_QUOTES)),
			]);
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT i.item_id, i.item_name, i.item_enabled, i.item_approved, i.item_created, i.item_updated, c.category_name, u.username
			FROM ' . $items_table . ' i
			LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			ORDER BY i.item_created DESC, i.item_id DESC';
		$result = $db->sql_query_limit($sql, 10);
		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('dashboard_recent_items', [
				'ITEM_ID' => (int) $row['item_id'],
				'ITEM_NAME' => $row['item_name'],
				'CATEGORY_NAME' => $row['category_name'] ?: '-',
				'USERNAME' => $row['username'] ?: '-',
				'ITEM_ENABLED' => (bool) $row['item_enabled'],
				'ITEM_APPROVED' => (bool) $row['item_approved'],
				'CREATED' => $row['item_created'] ? $user->format_date((int) $row['item_created']) : '-',
				'UPDATED' => $row['item_updated'] ? $user->format_date((int) $row['item_updated']) : '-',
				'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
			]);
		}
		$db->sql_freeresult($result);
	}

	/**
	 * Count downloads per source, overall or per item/version.
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param string   $table
	 * @param string   $group_column '' for overall totals, or item_id / version_id
	 * @param int[]    $ids          Limit to these IDs when grouping
	 * @return array   [source => total] overall, or [id => [source => total]]
	 */
	protected function download_source_counts($db, $table, $group_column = '', array $ids = [])
	{
		if ($group_column !== '' && empty($ids))
		{
			return [];
		}

		$select = $group_column !== '' ? $group_column . ', ' : '';
		$sql = 'SELECT ' . $select . 'download_source, COUNT(download_id) AS total
			FROM ' . $table .
			($group_column !== '' ? ' WHERE ' . $db->sql_in_set($group_column, array_map('intval', $ids)) : '') . '
			GROUP BY ' . $select . 'download_source';
		$result = $db->sql_query($sql);

		$counts = $group_column !== '' ? [] : $this->empty_source_counts();
		while ($row = $db->sql_fetchrow($result))
		{
			$source = $this->normalise_download_source((int) $row['download_source']);
			if ($group_column === '')
			{
				$counts[$source] += (int) $row['total'];
				continue;
			}

			$id = (int) $row[$group_column];
			if (!isset($counts[$id]))
			{
				$counts[$id] = $this->empty_source_counts();
			}
			$counts[$id][$source] += (int) $row['total'];
		}
		$db->sql_freeresult($result);

		return $counts;
	}

	protected function empty_source_counts()
	{
		return [
			\mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN => 0,
			\mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT => 0,
			\mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN => 0,
		];
	}

	protected function normalise_download_source($source)
	{
		return in_array($source, [\mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT, \mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN], true)
			? $source
			: \mundophpbb\downloadcenter\service\bot_detector::SOURCE_HUMAN;
	}

	/**
	 * @return string human|bot|unknown
	 */
	protected function download_source_key($source)
	{
		switch ($this->normalise_download_source($source))
		{
			case \mundophpbb\downloadcenter\service\bot_detector::SOURCE_BOT:
				return 'bot';
			case \mundophpbb\downloadcenter\service\bot_detector::SOURCE_UNKNOWN:
				return 'unknown';
			default:
				return 'human';
		}
	}

	protected function download_source_label($source, $user)
	{
		return $user->lang('ACP_DOWNLOADCENTER_SOURCE_' . strtoupper($this->download_source_key($source)));
	}

	protected function source_percent($part, $total, $user)
	{
		if ($total <= 0)
		{
			return '';
		}

		$percent = round($part * 100 / $total, 1);

		return $user->lang('ACP_DOWNLOADCENTER_SOURCE_PERCENT', rtrim(rtrim(number_format($percent, 1, $user->lang('ACP_DOWNLOADCENTER_DECIMAL_SEPARATOR'), ''), '0'), $user->lang('ACP_DOWNLOADCENTER_DECIMAL_SEPARATOR')));
	}

	protected function count_table_rows($db, $table, $where = '')
	{
		$sql = 'SELECT COUNT(*) AS total FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
		$result = $db->sql_query($sql);
		$total = (int) $db->sql_fetchfield('total');
		$db->sql_freeresult($result);

		return $total;
	}
}

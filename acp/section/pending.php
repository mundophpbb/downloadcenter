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
 * ACP moderation queue.
 */
trait pending
{
	protected function count_pending_items($db)
	{
		$sql = 'SELECT COUNT(item_id) AS total
			FROM ' . $this->table_prefix . 'downloadcenter_items
			WHERE item_approved = 0';
		$result = $db->sql_query($sql);
		$total = (int) $db->sql_fetchfield('total');
		$db->sql_freeresult($result);

		return $total;
	}

	protected function handle_pending($db, $request, $template, $user)
	{
		global $config, $phpEx;

		$items_table = $this->table_prefix . 'downloadcenter_items';
		$versions_table = $this->table_prefix . 'downloadcenter_versions';
		$downloads_table = $this->table_prefix . 'downloadcenter_downloads';
		$categories_table = $this->table_prefix . 'downloadcenter_categories';
		$screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';
		$action = $request->variable('action', '');
		$item_id = $request->variable('item_id', 0);
		$time = time();

		if (($action === 'approve' || $action === 'unapprove') && $item_id > 0)
		{
			$approved = ($action === 'approve') ? 1 : 0;
			$lang_key = $approved ? 'ACP_DOWNLOADCENTER_CONFIRM_APPROVE_ITEM' : 'ACP_DOWNLOADCENTER_CONFIRM_UNAPPROVE_ITEM';
			$success_key = $approved ? 'ACP_DOWNLOADCENTER_ITEM_APPROVED_SAVED' : 'ACP_DOWNLOADCENTER_ITEM_UNAPPROVED_SAVED';

			if (confirm_box(true))
			{
				$item_for_notification = $this->get_item_row($db, $item_id);

				$sql = 'UPDATE ' . $items_table . '
					SET item_approved = ' . (int) $approved . ', item_updated = ' . (int) $time . '
					WHERE item_id = ' . (int) $item_id;
				$db->sql_query($sql);
				$this->add_log($db, $user, $approved ? 'item_approved' : 'item_unapproved', $user->lang($approved ? 'ACP_DOWNLOADCENTER_LOG_ITEM_APPROVED' : 'ACP_DOWNLOADCENTER_LOG_ITEM_UNAPPROVED', (string) $item_id), $item_id);

				$this->notify_author_status($item_for_notification, (bool) $approved);

				trigger_error($user->lang($success_key) . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(false, $user->lang($lang_key), build_hidden_fields([
					'action' => $action,
					'item_id' => $item_id,
				]));
			}
		}

		if ($action === 'delete' && $item_id > 0)
		{
			if (confirm_box(true))
			{
				$this->delete_local_files_for_item($db, $versions_table, $item_id);
				$this->delete_screenshots_for_item($db, $screenshots_table, $item_id);
				$sql = 'DELETE FROM ' . $downloads_table . ' WHERE item_id = ' . (int) $item_id;
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . $versions_table . ' WHERE item_id = ' . (int) $item_id;
				$db->sql_query($sql);
				$sql = 'DELETE FROM ' . $items_table . ' WHERE item_id = ' . (int) $item_id;
				$db->sql_query($sql);
				$this->add_log($db, $user, 'item_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_DELETED', (string) $item_id), $item_id);
				$this->purge_item_notifications($item_id);
				trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_DELETED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_ITEM'), build_hidden_fields([
					'action' => 'delete',
					'item_id' => $item_id,
				]));
			}
		}

		$start = max(0, $request->variable('start', 0));
		$per_page = isset($config['mundophpbb_downloadcenter_acp_per_page']) ? max(1, (int) $config['mundophpbb_downloadcenter_acp_per_page']) : 20;
		$total_pending = $this->count_table_rows($db, $items_table, 'item_approved = 0');
		$start = $this->normalize_start($start, $per_page, $total_pending);

		$sql = 'SELECT i.*, c.category_name, u.username
				FROM ' . $items_table . ' i
				LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
				LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
				WHERE i.item_approved = 0
				ORDER BY i.item_updated DESC, i.item_created DESC, i.item_name ASC';
		$result = $db->sql_query_limit($sql, $per_page, $start);
		$pending_total = 0;
		while ($row = $db->sql_fetchrow($result))
		{
			$pending_total++;
			$latest_version = $this->get_latest_version_for_item($db, $versions_table, (int) $row['item_id']);
			$screenshot_count = $this->count_screenshots_for_item($db, $screenshots_table, (int) $row['item_id']);
			$download_label = '-';
			$download_status = $user->lang('ACP_DOWNLOADCENTER_REVIEW_NO_DOWNLOAD');
			$download_ok = false;

			if (!empty($latest_version))
			{
				if ($latest_version['download_type'] === 'local')
				{
					$file_name = (string) $latest_version['download_file'];
					$file_path = $this->local_storage_directory() . basename($file_name);
					$download_label = $file_name !== '' ? $this->friendly_file_name($file_name) : '-';
					$download_ok = $file_name !== '' && is_file($file_path);
					$download_status = $download_ok ? $user->lang('ACP_DOWNLOADCENTER_REVIEW_FILE_OK') : $user->lang('ACP_DOWNLOADCENTER_REVIEW_FILE_MISSING');
				}
				else
				{
					$download_label = $latest_version['download_url'] ?: '-';
					$download_ok = !empty($latest_version['download_url']);
					$download_status = $download_ok ? $user->lang('ACP_DOWNLOADCENTER_REVIEW_EXTERNAL_LINK') : $user->lang('ACP_DOWNLOADCENTER_REVIEW_NO_DOWNLOAD');
				}
			}

			$template->assign_block_vars('pending_items', [
				'ITEM_ID' => (int) $row['item_id'],
				'ITEM_NAME' => $row['item_name'],
				'CATEGORY_NAME' => $row['category_name'] ?: '-',
				'USERNAME' => $row['username'] ?: '-',
				'TOPIC_ID' => (int) $row['topic_id'],
				'LATEST_VERSION' => !empty($latest_version) ? $this->clean_version_label($latest_version['version_number']) : '-',
				'PHPBB_VERSION' => !empty($latest_version) && !empty($latest_version['phpbb_version']) ? $latest_version['phpbb_version'] : '-',
				'PHP_VERSION' => !empty($latest_version) && !empty($latest_version['php_version']) ? $latest_version['php_version'] : '-',
				'DOWNLOAD_TYPE' => !empty($latest_version) ? $latest_version['download_type'] : '-',
				'DOWNLOAD_TYPE_LABEL' => !empty($latest_version) && $latest_version['download_type'] === 'local' ? $user->lang('ACP_DOWNLOADCENTER_DOWNLOAD_TYPE_LOCAL') : $user->lang('ACP_DOWNLOADCENTER_DOWNLOAD_TYPE_EXTERNAL'),
				'DOWNLOAD_LABEL' => $download_label,
				'DOWNLOAD_STATUS' => $download_status,
				'DOWNLOAD_OK' => $download_ok,
				'FILE_SIZE' => !empty($latest_version) && !empty($latest_version['file_size']) ? $latest_version['file_size'] : '-',
				'ITEM_SHORT_DESC' => $row['item_short_desc'],
				'ITEM_DESC' => $this->excerpt_text($row['item_desc'], 700),
				'LATEST_CHANGELOG' => !empty($latest_version) && !empty($latest_version['version_changelog']) ? $latest_version['version_changelog'] : '',
				'SCREENSHOT_COUNT' => $screenshot_count,
				'ITEM_ENABLED' => (bool) $row['item_enabled'],
				'ITEM_CREATED' => $row['item_created'] ? $user->format_date((int) $row['item_created']) : '-',
				'ITEM_UPDATED' => $row['item_updated'] ? $user->format_date((int) $row['item_updated']) : '-',
				'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
				'U_APPROVE' => $this->u_action . '&amp;action=approve&amp;item_id=' . (int) $row['item_id'],
				'U_DELETE' => $this->u_action . '&amp;action=delete&amp;item_id=' . (int) $row['item_id'],
				'U_VIEW' => append_sid('../app.' . $phpEx . '/downloadcenter/item/' . (int) $row['item_id']),
			]);
		}
		$db->sql_freeresult($result);

		$template->assign_vars([
			'PENDING_TOTAL' => $pending_total,
			'PENDING_TOTAL_ALL' => $total_pending,
			'PENDING_PAGINATION' => $this->make_pagination($this->u_action, $total_pending, $per_page, $start),
			'PENDING_PAGE_NUMBER' => $this->make_page_number($total_pending, $per_page, $start),
		]);
	}

	protected function notify_author_status($item, $approved)
	{
		global $config, $phpbb_container;

		if (!$item || empty($config['mundophpbb_downloadcenter_notifications_enabled']))
		{
			return;
		}

		if ($phpbb_container && $phpbb_container->has('mundophpbb.downloadcenter.notification_helper'))
		{
			$phpbb_container->get('mundophpbb.downloadcenter.notification_helper')->notify_author_status((int) $item['user_id'], (int) $item['item_id'], (string) $item['item_name'], (bool) $approved);
		}
	}

	protected function purge_item_notifications($item_id)
	{
		global $phpbb_container;

		if ($phpbb_container && $phpbb_container->has('mundophpbb.downloadcenter.notification_helper'))
		{
			$phpbb_container->get('mundophpbb.downloadcenter.notification_helper')->purge_item_notifications((int) $item_id);
		}
	}
}

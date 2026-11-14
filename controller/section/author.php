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
 * Author pages: my submissions, edit and submit.
 */
trait author
{
	public function mine()
	{
		global $request;

		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if ($this->is_anonymous())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_LOGIN_REQUIRED_MINE'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		$status_filter = $request->variable('status', 'all');
		$allowed_status_filters = ['all', 'published', 'pending', 'disabled', 'no_version'];
		if (!in_array($status_filter, $allowed_status_filters, true))
		{
			$status_filter = 'all';
		}

		$search = trim($request->variable('q', '', true));
		$start = max(0, $request->variable('start', 0));
		$per_page = isset($this->config['mundophpbb_downloadcenter_public_per_page']) ? (int) $this->config['mundophpbb_downloadcenter_public_per_page'] : 12;
		$per_page = min(50, max(1, $per_page));
		$items_table = $this->table('downloadcenter_items');
		$versions_table = $this->table('downloadcenter_versions');
		$user_id = (int) $this->user->data['user_id'];

		$base_where = ['i.user_id = ' . $user_id];
		$where = $base_where;

		if ($search !== '')
		{
			$search_like = $this->db->sql_like_expression($this->db->get_any_char() . $this->db->sql_escape($search) . $this->db->get_any_char());
			$where[] = '(i.item_name ' . $search_like . ' OR i.item_short_desc ' . $search_like . ' OR i.item_desc ' . $search_like . ')';
		}

		if ($status_filter === 'published')
		{
			$where[] = 'i.item_enabled = 1 AND i.item_approved = 1';
		}
		else if ($status_filter === 'pending')
		{
			$where[] = 'i.item_enabled = 1 AND i.item_approved = 0';
		}
		else if ($status_filter === 'disabled')
		{
			$where[] = 'i.item_enabled = 0';
		}
		else if ($status_filter === 'no_version')
		{
			$where[] = 'NOT EXISTS (SELECT 1 FROM ' . $versions_table . ' vf WHERE vf.item_id = i.item_id AND vf.version_enabled = 1)';
		}

		$summary = [
			'total' => 0,
			'published' => 0,
			'pending' => 0,
			'disabled' => 0,
			'no_version' => 0,
		];

		$sql = 'SELECT COUNT(*) AS total,
				SUM(CASE WHEN i.item_enabled = 1 AND i.item_approved = 1 THEN 1 ELSE 0 END) AS published_total,
				SUM(CASE WHEN i.item_enabled = 1 AND i.item_approved = 0 THEN 1 ELSE 0 END) AS pending_total,
				SUM(CASE WHEN i.item_enabled = 0 THEN 1 ELSE 0 END) AS disabled_total
			FROM ' . $items_table . ' i
			WHERE ' . implode(' AND ', $base_where);
		$result = $this->db->sql_query($sql);
		$summary_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($summary_row)
		{
			$summary['total'] = (int) $summary_row['total'];
			$summary['published'] = (int) $summary_row['published_total'];
			$summary['pending'] = (int) $summary_row['pending_total'];
			$summary['disabled'] = (int) $summary_row['disabled_total'];
		}

		$sql = sprintf(
			'SELECT COUNT(*) AS total FROM %1$s i WHERE i.user_id = %2$d AND NOT EXISTS (SELECT 1 FROM %3$s vf WHERE vf.item_id = i.item_id AND vf.version_enabled = 1)',
			$items_table,
			(int) $user_id,
			$versions_table
		);
		$result = $this->db->sql_query($sql);
		$summary['no_version'] = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$sql = 'SELECT COUNT(*) AS total
			FROM ' . $items_table . ' i
			WHERE ' . implode(' AND ', $where);
		$result = $this->db->sql_query($sql);
		$total_my_items = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$start = $this->normalize_start($start, $per_page, $total_my_items);

		$sql = 'SELECT i.*, c.category_name
			FROM ' . $items_table . ' i
			LEFT JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY i.item_updated DESC, i.item_created DESC, i.item_name ASC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$current_version = $this->get_latest_version((int) $row['item_id']);
			$has_version = (bool) $current_version;
			$status_key = 'DOWNLOADCENTER_STATUS_PENDING';
			$status_class = 'pending';
			$status_explain_key = 'DOWNLOADCENTER_MY_STATUS_PENDING_EXPLAIN';

			if ((int) $row['item_enabled'] === 0)
			{
				$status_key = 'DOWNLOADCENTER_STATUS_DISABLED';
				$status_class = 'disabled';
				$status_explain_key = 'DOWNLOADCENTER_MY_STATUS_DISABLED_EXPLAIN';
			}
			else if ((int) $row['item_approved'] === 1)
			{
				$status_key = 'DOWNLOADCENTER_STATUS_PUBLISHED';
				$status_class = 'published';
				$status_explain_key = 'DOWNLOADCENTER_MY_STATUS_PUBLISHED_EXPLAIN';
			}

			if (!$has_version)
			{
				$status_class .= ' no-version';
				$status_explain_key = 'DOWNLOADCENTER_MY_STATUS_NO_VERSION_EXPLAIN';
			}

			$this->template->assign_block_vars('my_items', [
				'ITEM_ID' => (int) $row['item_id'],
				'ITEM_NAME' => $row['item_name'],
				'CATEGORY_NAME' => $row['category_name'] ?: '-',
				'LATEST_VERSION' => $current_version ? $current_version['version_number'] : '-',
				'PHPBB_VERSION' => $current_version ? $current_version['phpbb_version'] : '',
				'PHP_VERSION' => $current_version ? $current_version['php_version'] : '',
				'ITEM_SHORT_DESC' => $this->render_rich_text($row['item_short_desc']),
				'STATUS' => $this->user->lang($status_key),
				'STATUS_CLASS' => $status_class,
				'STATUS_EXPLAIN' => $this->user->lang($status_explain_key),
				'ITEM_DOWNLOADS' => (int) $row['item_downloads'],
				'ITEM_CREATED' => !empty($row['item_created']) ? $this->user->format_date((int) $row['item_created']) : '',
				'ITEM_UPDATED' => !empty($row['item_updated']) ? $this->user->format_date((int) $row['item_updated']) : '',
				'U_ITEM' => ((int) $row['item_approved'] === 1 && (int) $row['item_enabled'] === 1) ? $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => (int) $row['item_id']]) : '',
				'U_EDIT' => $this->helper->route('mundophpbb_downloadcenter_edit', ['item_id' => (int) $row['item_id']]),
				'S_HAS_VERSION' => $has_version,
				'S_PUBLISHED' => ((int) $row['item_approved'] === 1 && (int) $row['item_enabled'] === 1),
				'S_PENDING' => ((int) $row['item_approved'] === 0 && (int) $row['item_enabled'] === 1),
				'S_DISABLED' => ((int) $row['item_enabled'] === 0),
			]);
		}
		$this->db->sql_freeresult($result);

		$base_url = $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => $status_filter]);

		$this->template->assign_vars([
			'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
			'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
			'U_MINE_ALL' => $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => 'all']),
			'U_MINE_PUBLISHED' => $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => 'published']),
			'U_MINE_PENDING' => $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => 'pending']),
			'U_MINE_DISABLED' => $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => 'disabled']),
			'U_MINE_NO_VERSION' => $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_mine'), ['q' => $search, 'status' => 'no_version']),
			'SEARCH_QUERY' => $search,
			'STATUS_FILTER' => $status_filter,
			'S_FILTER_ALL' => $status_filter === 'all',
			'S_FILTER_PUBLISHED' => $status_filter === 'published',
			'S_FILTER_PENDING' => $status_filter === 'pending',
			'S_FILTER_DISABLED' => $status_filter === 'disabled',
			'S_FILTER_NO_VERSION' => $status_filter === 'no_version',
			'S_HAS_ACTIVE_MINE_FILTERS' => ($search !== '' || $status_filter !== 'all'),
			'S_CAN_SUBMIT' => $this->can_submit(),
			'PAGINATION' => $this->make_pagination($base_url, $total_my_items, $per_page, $start),
			'PAGE_NUMBER' => $this->make_page_number($total_my_items, $per_page, $start),
			'TOTAL_MY_ITEMS' => $total_my_items,
			'MY_TOTAL_ALL' => $summary['total'],
			'MY_TOTAL_PUBLISHED' => $summary['published'],
			'MY_TOTAL_PENDING' => $summary['pending'],
			'MY_TOTAL_DISABLED' => $summary['disabled'],
			'MY_TOTAL_NO_VERSION' => $summary['no_version'],
			'S_HAS_PAGINATION' => $total_my_items > $per_page,
		]);

		return $this->helper->render('downloadcenter_mine.html', $this->user->lang('DOWNLOADCENTER_MY_SUBMISSIONS'));
	}

	public function edit($item_id)
	{
		global $request;

		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if ($this->is_anonymous())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_LOGIN_REQUIRED_MINE'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		if (!$this->can_submit())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_SUBMIT'));
		}

		$item_id = (int) $item_id;
		$items_table = $this->table('downloadcenter_items');
		$versions_table = $this->table('downloadcenter_versions');

		$sql = 'SELECT *
			FROM ' . $items_table . '
			WHERE item_id = ' . (int) $item_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];
		$result = $this->db->sql_query($sql);
		$item = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$item)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_ITEM_NOT_FOUND'));
		}

		add_form_key('mundophpbb_downloadcenter_edit');

		if ($request->is_set_post('add_screenshot'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_edit'))
			{
				trigger_error('FORM_INVALID');
			}

			$uploaded_screenshot = $this->handle_screenshot_upload($request);
			if (!$uploaded_screenshot)
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_UPLOAD_REQUIRED'));
			}

			$this->insert_screenshot($item_id, $uploaded_screenshot['file_name'], $this->clean_utf8_for_storage(trim($request->variable('new_screenshot_caption', '', true))), max(0, $request->variable('new_screenshot_order', 0)));
			$this->mark_author_item_pending($item_id);
			$this->add_log('screenshot_created', $this->user->lang('DOWNLOADCENTER_LOG_SCREENSHOT_CREATED', (string) $item_id), $item_id, 0);
			$this->notify_pending($item_id, $item['item_name']);
			$this->redirect_to_author_screenshots($item_id, 'added');
		}

		if ($request->is_set_post('update_screenshots'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_edit'))
			{
				trigger_error('FORM_INVALID');
			}

			$captions = $request->variable('screenshot_caption', [0 => ''], true);
			$orders = $request->variable('screenshot_order', [0 => 0]);

			$sql = 'SELECT screenshot_id FROM ' . $this->table('downloadcenter_screenshots') . '
				WHERE item_id = ' . (int) $item_id;
			$result = $this->db->sql_query($sql);
			while ($screenshot = $this->db->sql_fetchrow($result))
			{
				$sid = (int) $screenshot['screenshot_id'];
				$data = [
					'image_caption' => isset($captions[$sid]) ? trim((string) $captions[$sid]) : '',
					'image_order' => isset($orders[$sid]) ? max(0, (int) $orders[$sid]) : 0,
				];

				$sql_update = 'UPDATE ' . $this->table('downloadcenter_screenshots') . '
					SET ' . $this->db->sql_build_array('UPDATE', $data) . '
					WHERE screenshot_id = ' . (int) $sid . '
						AND item_id = ' . (int) $item_id;
				$this->db->sql_query($sql_update);
			}
			$this->db->sql_freeresult($result);

			$this->mark_author_item_pending($item_id);
			$this->add_log('screenshot_updated', $this->user->lang('DOWNLOADCENTER_LOG_SCREENSHOT_UPDATED', (string) $item_id), $item_id, 0);
			$this->notify_pending($item_id, $item['item_name']);
			$this->redirect_to_author_screenshots($item_id, 'updated');
		}

		$delete_screenshot_id = $request->variable('delete_screenshot', 0);
		if ($delete_screenshot_id > 0)
		{
			if (!check_form_key('mundophpbb_downloadcenter_edit'))
			{
				trigger_error('FORM_INVALID');
			}

			$sql = 'SELECT * FROM ' . $this->table('downloadcenter_screenshots') . '
				WHERE screenshot_id = ' . (int) $delete_screenshot_id . '
					AND item_id = ' . (int) $item_id;
			$result = $this->db->sql_query_limit($sql, 1);
			$screenshot = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($screenshot)
			{
				$this->delete_screenshot_file($screenshot['image_file']);
				$sql = 'DELETE FROM ' . $this->table('downloadcenter_screenshots') . '
					WHERE screenshot_id = ' . (int) $delete_screenshot_id . '
						AND item_id = ' . (int) $item_id;
				$this->db->sql_query($sql);
				$this->mark_author_item_pending($item_id);
				$this->add_log('screenshot_deleted', $this->user->lang('DOWNLOADCENTER_LOG_SCREENSHOT_DELETED', (string) $delete_screenshot_id), $item_id, 0);
				$this->notify_pending($item_id, $item['item_name']);
			}

			$this->redirect_to_author_screenshots($item_id, 'deleted');
		}

		if ($request->is_set_post('save_item'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_edit'))
			{
				trigger_error('FORM_INVALID');
			}

			$item_name = $this->clean_utf8_for_storage(trim($request->variable('item_name', '', true)));
			$category_id = max(0, $request->variable('category_id', 0));
			$item_short_desc = $this->clean_utf8_for_storage(trim($request->variable('item_short_desc', '', true)));
			$item_desc = $this->clean_utf8_for_storage(trim($request->variable('item_desc', '', true)));
			$item_icon = trim($request->variable('item_icon', '', true));
			$add_new_version = (bool) $request->variable('add_new_version', 0);

			if ($item_name === '')
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_EDIT_REQUIRED_FIELDS'));
			}

			$time = time();
			$needs_reapproval = ((int) $item['item_approved'] === 1);

			$item_data = [
				'category_id' => $category_id,
				'item_name' => $item_name,
				'item_slug' => $this->slugify($item_name),
				'item_short_desc' => $item_short_desc,
				'item_desc' => $item_desc,
				'item_icon' => $item_icon,
				'item_approved' => 0,
				'item_updated' => $time,
			];

			$sql = 'UPDATE ' . $items_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $item_data) . '
				WHERE item_id = ' . (int) $item_id . '
					AND user_id = ' . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);

			$version_id = 0;

			if ($request->is_set_post('latest_version_changelog'))
			{
				$latest_version_id = $request->variable('latest_version_id', 0);
				$latest_changelog = $this->clean_utf8_for_storage(trim($request->variable('latest_version_changelog', '', true)));

				if ($latest_version_id > 0)
				{
					$sql = 'SELECT version_id FROM ' . $versions_table . '
						WHERE version_id = ' . (int) $latest_version_id . '
							AND item_id = ' . (int) $item_id;
					$result = $this->db->sql_query_limit($sql, 1);
					$latest_row = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					if ($latest_row)
					{
						$sql = 'UPDATE ' . $versions_table . "
							SET version_changelog = '" . $this->db->sql_escape($latest_changelog) . "'
							WHERE version_id = " . (int) $latest_version_id;
						$this->db->sql_query($sql);
					}
				}
			}

			if (!$add_new_version && $request->variable('update_latest_version', 0))
			{
				$latest_version_id = $request->variable('latest_version_id', 0);

				if ($latest_version_id <= 0)
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_REQUIRED_FOR_NEW_VERSION'));
				}

				$sql = 'SELECT * FROM ' . $versions_table . '
					WHERE version_id = ' . (int) $latest_version_id . '
						AND item_id = ' . (int) $item_id;
				$result = $this->db->sql_query_limit($sql, 1);
				$latest_version = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				if (!$latest_version)
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_REQUIRED_FOR_NEW_VERSION'));
				}

				$version_number = trim($request->variable('latest_version_number', '', true));
				$phpbb_version = trim($request->variable('latest_phpbb_version', '', true));
				$php_version = trim($request->variable('latest_php_version', '', true));
				$version_changelog = $this->clean_utf8_for_storage(trim($request->variable('latest_version_changelog', '', true)));
				$download_type = $request->variable('latest_download_type', (string) $latest_version['download_type']);
				$download_url = trim($request->variable('latest_download_url', '', true));
				$download_file = (string) $latest_version['download_file'];
				$file_size = (string) $latest_version['file_size'];

				if ($version_number === '')
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_REQUIRED_FOR_NEW_VERSION'));
				}

				if ($download_type !== 'local')
				{
					$download_type = 'external';
				}

				if ($download_type === 'local')
				{
					$upload = $this->handle_local_upload($request, 'latest_download_upload');
					if ($upload)
					{
						$download_file = $upload['file_name'];
						$file_size = $upload['file_size'];
					}
					if ($download_file === '')
					{
						trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
					}
					$download_url = '';
				}
				else
				{
					if ($download_url === '')
					{
						$download_url = (string) $latest_version['download_url'];
					}
					if ($download_url === '')
					{
						trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
					}
					if (!preg_match('#^https?://#i', $download_url) || !filter_var($download_url, FILTER_VALIDATE_URL))
					{
						trigger_error($this->user->lang('DOWNLOADCENTER_EXTERNAL_URL_INVALID'));
					}
					$download_file = '';
					$file_size = '';
				}

				$version_data = [
					'version_number' => $version_number,
					'phpbb_version' => $phpbb_version,
					'php_version' => $php_version,
					'download_type' => $download_type,
					'download_url' => $download_url,
					'download_file' => $download_file,
					'file_size' => $file_size,
					'version_changelog' => $version_changelog,
					'version_enabled' => 1,
				];

				$sql = 'UPDATE ' . $versions_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $version_data) . '
					WHERE version_id = ' . (int) $latest_version_id . '
						AND item_id = ' . (int) $item_id;
				$this->db->sql_query($sql);

				$sql = 'UPDATE ' . $items_table . '
					SET item_current_version_id = ' . (int) $latest_version_id . '
					WHERE item_id = ' . (int) $item_id;
				$this->db->sql_query($sql);

				$version_id = (int) $latest_version_id;
			}

			if ($add_new_version)
			{
				$version_number = trim($request->variable('version_number', '', true));
				$phpbb_version = trim($request->variable('phpbb_version', '', true));
				$php_version = trim($request->variable('php_version', '', true));
				$version_changelog = $this->clean_utf8_for_storage(trim($request->variable('version_changelog', '', true)));
				$download_type = $request->variable('download_type', 'external');
				$download_url = trim($request->variable('download_url', '', true));
				$download_file = '';
				$file_size = '';

				if ($download_type !== 'local')
				{
					$download_type = 'external';
				}

				if ($version_number === '')
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_VERSION_REQUIRED_FOR_NEW_VERSION'));
				}

				if ($download_type === 'local')
				{
					$upload = $this->handle_local_upload($request);
					if (!$upload)
					{
						trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
					}

					$download_file = $upload['file_name'];
					$file_size = $upload['file_size'];
					$download_url = '';
				}
				else if ($download_url === '')
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
				}
				else if (!preg_match('#^https?://#i', $download_url) || !filter_var($download_url, FILTER_VALIDATE_URL))
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_EXTERNAL_URL_INVALID'));
				}

				$version_data = [
					'item_id' => $item_id,
					'version_number' => $version_number,
					'phpbb_version' => $phpbb_version,
					'php_version' => $php_version,
					'version_changelog' => $version_changelog,
					'download_type' => $download_type,
					'download_url' => $download_url,
					'download_file' => $download_file,
					'file_size' => $file_size,
					'version_downloads' => 0,
					'version_enabled' => 1,
					'version_created' => $time,
				];

				$sql = 'INSERT INTO ' . $versions_table . ' ' . $this->db->sql_build_array('INSERT', $version_data);
				$this->db->sql_query($sql);
				$version_id = (int) $this->db->sql_nextid();

				$sql = 'UPDATE ' . $items_table . '
					SET item_current_version_id = ' . (int) $version_id . '
					WHERE item_id = ' . (int) $item_id;
				$this->db->sql_query($sql);
			}

			$log_message = $add_new_version
				? $this->user->lang('DOWNLOADCENTER_LOG_AUTHOR_EDIT_VERSION', $item_name)
				: $this->user->lang('DOWNLOADCENTER_LOG_AUTHOR_EDIT', $item_name);
			$this->add_log('author_edit', $log_message, $item_id, $version_id);
			$this->notify_pending($item_id, $item_name);

			meta_refresh(3, $this->helper->route('mundophpbb_downloadcenter_mine'));
			trigger_error($needs_reapproval ? $this->user->lang('DOWNLOADCENTER_EDIT_SAVED_REAPPROVAL') : $this->user->lang('DOWNLOADCENTER_EDIT_SAVED'));
		}

		$sql = 'SELECT category_id, category_name
			FROM ' . $this->table('downloadcenter_categories') . '
			WHERE category_enabled = 1
			ORDER BY category_order ASC, category_name ASC';
		$result = $this->db->sql_query($sql);
		while ($category = $this->db->sql_fetchrow($result))
		{
			$this->template->assign_block_vars('edit_categories', [
				'CATEGORY_ID' => (int) $category['category_id'],
				'CATEGORY_NAME' => $category['category_name'],
				'S_SELECTED' => (int) $category['category_id'] === (int) $item['category_id'],
			]);
		}
		$this->db->sql_freeresult($result);

		$latest_version_id = 0;
		$latest_version_changelog = '';
		$latest_version_number = '';
		$latest_phpbb_version = '';
		$latest_php_version = '';
		$latest_download_type = 'external';
		$latest_download_url = '';
		$latest_download_file = '';
		$latest_file_size = '';
		$latest_version = $this->get_latest_version($item_id);
		if ($latest_version)
		{
			$latest_version_id = (int) $latest_version['version_id'];
			$latest_version_changelog = (string) $latest_version['version_changelog'];
			$latest_version_number = (string) $latest_version['version_number'];
			$latest_phpbb_version = (string) $latest_version['phpbb_version'];
			$latest_php_version = (string) $latest_version['php_version'];
			$latest_download_type = (string) $latest_version['download_type'];
			$latest_download_url = (string) $latest_version['download_url'];
			$latest_download_file = (string) $latest_version['download_file'];
			$latest_file_size = (string) $latest_version['file_size'];
		}
		$sql = 'SELECT *
			FROM ' . $versions_table . '
			WHERE item_id = ' . (int) $item_id . '
				AND version_enabled = 1
			ORDER BY version_created DESC, version_id DESC';
		$result = $this->db->sql_query($sql);
		while ($version = $this->db->sql_fetchrow($result))
		{
			if ($latest_version_id === 0)
			{
				$latest_version_id = (int) $version['version_id'];
				$latest_version_changelog = $version['version_changelog'];
			}

			$this->template->assign_block_vars('edit_versions', [
				'VERSION_NUMBER' => $version['version_number'],
				'PHPBB_VERSION' => $version['phpbb_version'],
				'PHP_VERSION' => $version['php_version'],
				'FILE_SIZE' => $version['file_size'],
				'DOWNLOADS' => (int) $version['version_downloads'],
				'CREATED' => !empty($version['version_created']) ? $this->user->format_date((int) $version['version_created']) : '',
			]);
		}
		$this->db->sql_freeresult($result);

		$this->assign_author_screenshots($item_id);

		$this->template->assign_vars([
			'U_ACTION' => $this->helper->route('mundophpbb_downloadcenter_edit', ['item_id' => $item_id]),
			'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'ITEM_NAME' => $item['item_name'],
			'ITEM_SHORT_DESC' => $item['item_short_desc'],
			'ITEM_DESC' => $item['item_desc'],
			'ITEM_ICON' => $item['item_icon'],
			'S_ITEM_APPROVED' => (int) $item['item_approved'] === 1,
			'LATEST_VERSION_ID' => $latest_version_id,
			'LATEST_VERSION_CHANGELOG' => $latest_version_changelog,
			'LATEST_VERSION_NUMBER' => $latest_version_number,
			'LATEST_PHPBB_VERSION' => $latest_phpbb_version,
			'LATEST_PHP_VERSION' => $latest_php_version,
			'LATEST_DOWNLOAD_TYPE' => $latest_download_type,
			'LATEST_DOWNLOAD_URL' => $latest_download_url,
			'LATEST_DOWNLOAD_FILE' => $latest_download_file,
			'LATEST_FILE_SIZE' => $latest_file_size,
			'S_LATEST_VERSION_EXTERNAL' => $latest_download_type !== 'local',
			'S_LATEST_VERSION_LOCAL' => $latest_download_type === 'local',
			'S_HAS_LATEST_DOWNLOAD_FILE' => $latest_download_file !== '',
			'S_HAS_LATEST_VERSION' => $latest_version_id > 0,
			'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text(),
		]);

		return $this->helper->render('downloadcenter_edit.html', $this->user->lang('DOWNLOADCENTER_EDIT_ITEM'));
	}

	public function submit()
	{
		global $request;

		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if (!$this->submissions_enabled())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SUBMISSIONS_DISABLED'));
		}

		if ($this->is_anonymous())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_LOGIN_REQUIRED_SUBMIT'));
		}

		if (!$this->can_submit())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_SUBMIT'));
		}

		add_form_key('mundophpbb_downloadcenter_submit');

		if ($request->is_set_post('submit_item'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_submit'))
			{
				trigger_error('FORM_INVALID');
			}

			$item_name = $this->clean_utf8_for_storage(trim($request->variable('item_name', '', true)));
			$version_number = trim($request->variable('version_number', '', true));
			$category_id = max(0, $request->variable('category_id', 0));
			$item_short_desc = $this->clean_utf8_for_storage(trim($request->variable('item_short_desc', '', true)));
			$item_desc = $this->clean_utf8_for_storage(trim($request->variable('item_desc', '', true)));
			$item_icon = trim($request->variable('item_icon', '', true));
			$phpbb_version = trim($request->variable('phpbb_version', '', true));
			$php_version = trim($request->variable('php_version', '', true));
			$version_changelog = $this->clean_utf8_for_storage(trim($request->variable('version_changelog', '', true)));
			$download_type = $request->variable('download_type', 'external');
			$download_url = trim($request->variable('download_url', '', true));
			$download_file = '';
			$file_size = '';

			if ($download_type !== 'local')
			{
				$download_type = 'external';
			}

			if ($item_name === '' || $version_number === '')
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_REQUIRED_FIELDS'));
			}

			if (!$request->variable('accept_rules', 0))
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_RULES_ACCEPT_REQUIRED'));
			}

			if ($download_type === 'local')
			{
				$upload = $this->handle_local_upload($request);
				if (!$upload)
				{
					trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
				}

				$download_file = $upload['file_name'];
				$file_size = $upload['file_size'];
				$download_url = '';
			}
			else if ($download_url === '')
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_DOWNLOAD_SOURCE_REQUIRED'));
			}
			else if (!preg_match('#^https?://#i', $download_url) || !filter_var($download_url, FILTER_VALIDATE_URL))
			{
				trigger_error($this->user->lang('DOWNLOADCENTER_EXTERNAL_URL_INVALID'));
			}

			$time = time();
			$items_table = $this->table('downloadcenter_items');
			$versions_table = $this->table('downloadcenter_versions');

			$item_data = [
				'category_id' => $category_id,
				'user_id' => (int) $this->user->data['user_id'],
				'topic_id' => 0,
				'item_name' => $item_name,
				'item_slug' => $this->slugify($item_name),
				'item_short_desc' => $item_short_desc,
				'item_desc' => $item_desc,
				'item_icon' => $item_icon,
				'item_enabled' => 1,
				'item_approved' => 0,
				'item_downloads' => 0,
				'item_created' => $time,
				'item_updated' => $time,
			];

			$sql = 'INSERT INTO ' . $items_table . ' ' . $this->db->sql_build_array('INSERT', $item_data);
			$this->db->sql_query($sql);
			$item_id = (int) $this->db->sql_nextid();

			$version_data = [
				'item_id' => $item_id,
				'version_number' => $version_number,
				'phpbb_version' => $phpbb_version,
				'php_version' => $php_version,
				'version_changelog' => $version_changelog,
				'download_type' => $download_type,
				'download_url' => $download_url,
				'download_file' => $download_file,
				'file_size' => $file_size,
				'version_downloads' => 0,
				'version_enabled' => 1,
				'version_created' => $time,
			];

			$sql = 'INSERT INTO ' . $versions_table . ' ' . $this->db->sql_build_array('INSERT', $version_data);
			$this->db->sql_query($sql);
			$version_id = (int) $this->db->sql_nextid();

			$sql = 'UPDATE ' . $items_table . '
				SET item_current_version_id = ' . (int) $version_id . '
				WHERE item_id = ' . (int) $item_id;
			$this->db->sql_query($sql);

			$uploaded_screenshot = $this->handle_screenshot_upload($request);
			if ($uploaded_screenshot)
			{
				$this->insert_screenshot($item_id, $uploaded_screenshot['file_name'], $this->clean_utf8_for_storage(trim($request->variable('screenshot_caption', '', true))), max(0, $request->variable('screenshot_order', 0)));
				$this->add_log('screenshot_created', $this->user->lang('DOWNLOADCENTER_LOG_SCREENSHOT_CREATED', (string) $item_id), $item_id, $version_id);
			}

			$this->add_log('public_submission', $this->user->lang('DOWNLOADCENTER_LOG_PUBLIC_SUBMISSION', $item_name), $item_id, $version_id);
			$this->notify_pending($item_id, $item_name);

			meta_refresh(3, $this->helper->route('mundophpbb_downloadcenter_index'));
			trigger_error($this->user->lang('DOWNLOADCENTER_SUBMIT_SAVED'));
		}

		$sql = 'SELECT category_id, category_name
			FROM ' . $this->table('downloadcenter_categories') . '
			WHERE category_enabled = 1
			ORDER BY category_order ASC, category_name ASC';
		$result = $this->db->sql_query($sql);
		while ($category = $this->db->sql_fetchrow($result))
		{
			$this->template->assign_block_vars('submit_categories', [
				'CATEGORY_ID' => (int) $category['category_id'],
				'CATEGORY_NAME' => $category['category_name'],
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'U_ACTION' => $this->helper->route('mundophpbb_downloadcenter_submit'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text(),
		]);

		return $this->helper->render('downloadcenter_submit.html', $this->user->lang('DOWNLOADCENTER_SUBMIT_ITEM'));
	}

	protected function notify_pending($item_id, $item_name)
	{
		if (empty($this->config['mundophpbb_downloadcenter_notifications_enabled']))
		{
			return;
		}

		$this->notification_helper->notify_pending_item((int) $item_id, (string) $item_name, (int) $this->user->data['user_id']);
	}

	protected function add_log($action, $message, $item_id = 0, $version_id = 0)
	{
		$sql_ary = [
			'user_id' => (int) $this->user->data['user_id'],
			'username' => isset($this->user->data['username']) ? (string) $this->user->data['username'] : '',
			'item_id' => (int) $item_id,
			'version_id' => (int) $version_id,
			'log_action' => (string) $action,
			'log_message' => (string) $message,
			'user_ip' => (string) $this->user->ip,
			'log_time' => time(),
		];

		$this->db->sql_query('INSERT INTO ' . $this->table('downloadcenter_logs') . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
	}

	protected function count_pending_items()
	{
		$sql = 'SELECT COUNT(item_id) AS total
			FROM ' . $this->table('downloadcenter_items') . '
			WHERE item_approved = 0';
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	protected function get_pending_preview_items($limit = 3)
	{
		$items = [];

		if (!$this->can_approve())
		{
			return $items;
		}

		$sql = 'SELECT i.item_id, i.item_name, i.item_updated, u.username
			FROM ' . $this->table('downloadcenter_items') . ' i
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			WHERE i.item_approved = 0
			ORDER BY i.item_updated DESC, i.item_id DESC';
		$result = $this->db->sql_query_limit($sql, max(1, (int) $limit));
		while ($row = $this->db->sql_fetchrow($result))
		{
			$items[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $items;
	}

	protected function mark_author_item_pending($item_id)
	{
		$data = [
			'item_approved' => 0,
			'item_updated' => time(),
		];

		$sql = 'UPDATE ' . $this->table('downloadcenter_items') . '
			SET ' . $this->db->sql_build_array('UPDATE', $data) . '
			WHERE item_id = ' . (int) $item_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];
		$this->db->sql_query($sql);
	}
}

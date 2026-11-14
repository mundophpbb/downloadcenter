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
 * Public pages: index, item, feed and rules.
 */
trait browse
{
	public function index()
	{
		global $request;

		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		$can_download = $this->can_download();
		$download_block_reason = $this->download_block_reason();
		$pending_total = $this->can_approve() ? $this->count_pending_items() : 0;
		$pending_preview = $pending_total > 0 ? $this->get_pending_preview_items(3) : [];

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		$search = trim($request->variable('q', '', true));
		$category_id = max(0, $request->variable('c', 0));
		$phpbb_filter = trim($request->variable('phpbb', '', true));
		$php_filter = trim($request->variable('php', '', true));
		$sort = $request->variable('sort', 'newest');
		$allowed_sorts = ['newest', 'updated', 'name', 'downloads'];
		if (!in_array($sort, $allowed_sorts, true))
		{
			$sort = 'newest';
		}

		$start = max(0, $request->variable('start', 0));
		$per_page = isset($this->config['mundophpbb_downloadcenter_public_per_page']) ? (int) $this->config['mundophpbb_downloadcenter_public_per_page'] : 12;
		$per_page = min(50, max(1, $per_page));

		$public_category_stats = $this->get_public_category_stats();
		$public_category_counts = [];
		foreach ($public_category_stats as $stats_category_id => $stats)
		{
			$public_category_counts[(int) $stats_category_id] = (int) $stats['item_count'];
		}
		$public_stats = $this->get_public_overall_stats();
		$phpbb_filter_options = $this->get_public_version_filter_options('phpbb_version', $phpbb_filter);
		$php_filter_options = $this->get_public_version_filter_options('php_version', $php_filter);
		$selected_category = null;

		$sql = 'SELECT *
			FROM ' . $this->table('downloadcenter_categories') . '
			WHERE category_enabled = 1
			ORDER BY category_order ASC, category_name ASC';
		$result = $this->db->sql_query($sql);
		while ($category = $this->db->sql_fetchrow($result))
		{
			$cat_id = (int) $category['category_id'];
			$category_stats = isset($public_category_stats[$cat_id]) ? $public_category_stats[$cat_id] : ['item_count' => 0, 'download_count' => 0, 'latest_update' => 0];
			$category_count = (int) $category_stats['item_count'];
			$category_downloads = (int) $category_stats['download_count'];
			$category_latest_update = (int) $category_stats['latest_update'];
			$category_url = $this->pagination_url($this->helper->route('mundophpbb_downloadcenter_index'), ['q' => $search, 'c' => $cat_id, 'phpbb' => $phpbb_filter, 'php' => $php_filter, 'sort' => $sort]);

			$category_vars = [
				'CATEGORY_ID' => $cat_id,
				'CATEGORY_NAME' => $category['category_name'],
				'CATEGORY_DESC' => $category['category_desc'],
				'CATEGORY_COUNT' => $category_count,
				'CATEGORY_DOWNLOADS' => $category_downloads,
				'CATEGORY_UPDATED' => $category_latest_update > 0 ? $this->user->format_date($category_latest_update) : '',
				'S_SELECTED' => $cat_id === $category_id,
				'S_HAS_ITEMS' => $category_count > 0,
				'S_HAS_DESC' => trim((string) $category['category_desc']) !== '',
				'S_HAS_UPDATE' => $category_latest_update > 0,
				'U_CATEGORY' => $category_url,
			];

			if ($cat_id === $category_id)
			{
				$selected_category = $category_vars;
			}

			$this->template->assign_block_vars('categories', $category_vars);
		}
		$this->db->sql_freeresult($result);

		$where = [
			'i.item_enabled = 1',
			'i.item_approved = 1',
			'EXISTS (SELECT 1 FROM ' . $this->table('downloadcenter_categories') . ' ce WHERE ce.category_id = i.category_id AND ce.category_enabled = 1)',
		];

		if ($category_id > 0)
		{
			$where[] = 'i.category_id = ' . (int) $category_id;
		}

		if ($search !== '')
		{
			$search_like = $this->db->sql_like_expression($this->db->get_any_char() . $this->db->sql_escape($search) . $this->db->get_any_char());
			$where[] = '(i.item_name ' . $search_like . ' OR i.item_short_desc ' . $search_like . ' OR i.item_desc ' . $search_like . ')';
		}

		if ($phpbb_filter !== '')
		{
			$phpbb_like = $this->db->sql_like_expression($this->db->get_any_char() . $this->db->sql_escape($phpbb_filter) . $this->db->get_any_char());
			$where[] = sprintf('EXISTS (SELECT 1 FROM %s vf WHERE vf.item_id = i.item_id AND vf.version_enabled = 1 AND vf.phpbb_version %s)', $this->table('downloadcenter_versions'), $phpbb_like);
		}

		if ($php_filter !== '')
		{
			$php_like = $this->db->sql_like_expression($this->db->get_any_char() . $this->db->sql_escape($php_filter) . $this->db->get_any_char());
			$where[] = sprintf('EXISTS (SELECT 1 FROM %s vf WHERE vf.item_id = i.item_id AND vf.version_enabled = 1 AND vf.php_version %s)', $this->table('downloadcenter_versions'), $php_like);
		}

		$order_by = 'i.item_updated DESC, i.item_created DESC';
		if ($sort === 'updated')
		{
			$order_by = 'i.item_updated DESC, i.item_name ASC';
		}
		else if ($sort === 'name')
		{
			$order_by = 'i.item_name ASC';
		}
		else if ($sort === 'downloads')
		{
			$order_by = 'i.item_downloads DESC, i.item_updated DESC';
		}

		$sql = 'SELECT COUNT(*) AS total
			FROM ' . $this->table('downloadcenter_items') . ' i
			WHERE ' . implode(' AND ', $where);
		$result = $this->db->sql_query($sql);
		$total_items = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$start = $this->normalize_start($start, $per_page, $total_items);

		$sql = 'SELECT i.*, c.category_name, u.username
			FROM ' . $this->table('downloadcenter_items') . ' i
			LEFT JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY ' . $order_by;

		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		$rows = [];
		$page_item_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
			$page_item_ids[] = (int) $row['item_id'];
		}
		$this->db->sql_freeresult($result);

		$page_versions = $this->get_current_versions_for_items($page_item_ids);
		$items_first = $total_items > 0 ? $start + 1 : 0;
		$items_last = $total_items > 0 ? min($start + $per_page, $total_items) : 0;

		foreach ($rows as $row)
		{
			$item_id = (int) $row['item_id'];
			$version = isset($page_versions[$item_id]) ? $page_versions[$item_id] : false;

			$file_available = $version ? $this->version_file_available($version) : false;

			$this->template->assign_block_vars('items', [
				'ITEM_ID'          => $item_id,
				'ITEM_NAME'        => $row['item_name'],
				'ITEM_SHORT_DESC'  => $this->render_rich_text($row['item_short_desc']),
				'ITEM_ICON'        => $this->resolve_item_icon_url($row['item_icon']),
				'CATEGORY_NAME'    => $row['category_name'],
				'AUTHOR_NAME'      => !empty($row['username']) ? $row['username'] : $this->user->lang('DOWNLOADCENTER_UNKNOWN_AUTHOR'),
				'VERSION_NUMBER'   => $version ? $version['version_number'] : '',
				'PHPBB_VERSION'    => $version ? $version['phpbb_version'] : '',
				'PHP_VERSION'      => $version ? $version['php_version'] : '',
				'FILE_SIZE'        => $version ? $version['file_size'] : '',
				'ITEM_UPDATED'     => !empty($row['item_updated']) ? $this->user->format_date((int) $row['item_updated']) : '',
				'DOWNLOADS'        => (int) $row['item_downloads'],
				'U_ITEM'           => $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => $item_id]),
				'U_DOWNLOAD'       => ($version && $file_available) ? $this->download_url_for_version($version) : '',
				'S_HAS_VERSION'    => (bool) $version,
				'S_CAN_DOWNLOAD'   => $can_download,
				'S_FILE_AVAILABLE' => $file_available,
				'S_FILE_MISSING'   => ($version && !$file_available),
				'S_RECENTLY_UPDATED' => !empty($row['item_updated']) && ((time() - (int) $row['item_updated']) <= 30 * 86400),
			]);
		}

		foreach ($phpbb_filter_options as $option)
		{
			$this->template->assign_block_vars('phpbb_filters', $option);
		}

		foreach ($php_filter_options as $option)
		{
			$this->template->assign_block_vars('php_filters', $option);
		}

		$this->template->assign_vars([
			'SEARCH_QUERY' => $search,
			'SELECTED_CATEGORY_ID' => $category_id,
			'SELECTED_CATEGORY_NAME' => $selected_category ? $selected_category['CATEGORY_NAME'] : '',
			'SELECTED_CATEGORY_DESC' => $selected_category ? $selected_category['CATEGORY_DESC'] : '',
			'SELECTED_CATEGORY_COUNT' => $selected_category ? $selected_category['CATEGORY_COUNT'] : 0,
			'SELECTED_CATEGORY_DOWNLOADS' => $selected_category ? $selected_category['CATEGORY_DOWNLOADS'] : 0,
			'SELECTED_CATEGORY_UPDATED' => $selected_category ? $selected_category['CATEGORY_UPDATED'] : '',
			'S_SELECTED_CATEGORY_HAS_DESC' => $selected_category ? $selected_category['S_HAS_DESC'] : false,
			'S_SELECTED_CATEGORY_HAS_UPDATE' => $selected_category ? $selected_category['S_HAS_UPDATE'] : false,
			'PHPBB_FILTER' => $phpbb_filter,
			'PHP_FILTER' => $php_filter,
			'SORT' => $sort,
			'PAGINATION' => $this->make_pagination($this->pagination_url($this->helper->route('mundophpbb_downloadcenter_index'), ['q' => $search, 'c' => $category_id, 'phpbb' => $phpbb_filter, 'php' => $php_filter, 'sort' => $sort]), $total_items, $per_page, $start),
			'PAGE_NUMBER' => $this->make_page_number($total_items, $per_page, $start),
			'TOTAL_ITEMS' => $total_items,
			'ITEMS_FIRST' => $items_first,
			'ITEMS_LAST' => $items_last,
			'S_HAS_RESULT_RANGE' => $total_items > 0,
			'S_HAS_PAGINATION' => $total_items > $per_page,
			'S_SORT_NEWEST' => $sort === 'newest',
			'S_SORT_UPDATED' => $sort === 'updated',
			'S_SORT_NAME' => $sort === 'name',
			'S_SORT_DOWNLOADS' => $sort === 'downloads',
			'S_HAS_ACTIVE_FILTERS' => ($search !== '' || $category_id > 0 || $phpbb_filter !== '' || $php_filter !== '' || $sort !== 'newest'),
			'S_HAS_SELECTED_CATEGORY' => $selected_category !== null,
			'S_HAS_PHPBB_FILTER_OPTIONS' => !empty($phpbb_filter_options),
			'S_HAS_PHP_FILTER_OPTIONS' => !empty($php_filter_options),
			'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
			'TOTAL_PUBLIC_ITEMS' => array_sum($public_category_counts),
			'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
			'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
			'S_CAN_SUBMIT' => $this->can_submit(),
			'S_CAN_DOWNLOAD' => $can_download,
			'DOWNLOAD_BLOCK_REASON' => $download_block_reason,
			'PENDING_TOTAL' => $pending_total,
			'S_SHOW_ADMIN_PENDING_NOTICE' => $pending_total > 0,
			'S_HAS_ADMIN_PENDING_PREVIEW' => !empty($pending_preview),
			'S_SHOW_PUBLIC_STATS' => !isset($this->config['mundophpbb_downloadcenter_show_public_stats']) || (bool) $this->config['mundophpbb_downloadcenter_show_public_stats'],
			'PUBLIC_STATS_ITEMS' => (int) $public_stats['items'],
			'PUBLIC_STATS_CATEGORIES' => (int) $public_stats['categories'],
			'PUBLIC_STATS_DOWNLOADS' => (int) $public_stats['downloads'],
			'PUBLIC_STATS_UPDATED' => $public_stats['updated'] > 0 ? $this->user->format_date((int) $public_stats['updated']) : '',
			'S_HAS_PUBLIC_STATS_UPDATED' => $public_stats['updated'] > 0,
			'U_DOWNLOADCENTER_FEED' => (!empty($this->config['mundophpbb_downloadcenter_feed_enabled']) ? $this->helper->route('mundophpbb_downloadcenter_feed') : ''),
		]);

		foreach ($pending_preview as $pending_item)
		{
			$this->template->assign_block_vars('admin_pending_items', [
				'ITEM_ID' => (int) $pending_item['item_id'],
				'ITEM_NAME' => (string) $pending_item['item_name'],
				'AUTHOR' => (string) $pending_item['username'],
				'DATE' => $this->user->format_date((int) $pending_item['item_updated']),
			]);
		}

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME' => $this->user->lang('DOWNLOADCENTER_TITLE'),
			'U_VIEW_FORUM' => $this->helper->route('mundophpbb_downloadcenter_index'),
		]);

		return $this->helper->render('downloadcenter_index.html', $this->user->lang('DOWNLOADCENTER_TITLE'));
	}

	public function item($item_id)
	{
		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		$can_download = $this->can_download();
		$download_block_reason = $this->download_block_reason();
		$public_category_counts = $this->get_public_category_counts();

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		$sql = 'SELECT i.*, c.category_name, u.username
			FROM ' . $this->table('downloadcenter_items') . ' i
			LEFT JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
			WHERE i.item_id = ' . (int) $item_id . '
				AND i.item_enabled = 1
				AND i.item_approved = 1
				AND c.category_enabled = 1';
		$result = $this->db->sql_query($sql);
		$item = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$item)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_ITEM_NOT_FOUND'));
		}

		$current_version_id = isset($item['item_current_version_id']) ? (int) $item['item_current_version_id'] : 0;
		$sql = 'SELECT *
			FROM ' . $this->table('downloadcenter_versions') . '
			WHERE item_id = ' . (int) $item_id . '
				AND version_enabled = 1
			ORDER BY CASE WHEN version_id = ' . (int) $current_version_id . ' THEN 0 ELSE 1 END, version_created DESC, version_id DESC';
		$result = $this->db->sql_query($sql);

		$first = true;
		$current_version = null;
		$version_count = 0;

		while ($version = $this->db->sql_fetchrow($result))
		{
			$version_count++;

			$file_available = $this->version_file_available($version);

			$version_vars = [
				'VERSION_ID'        => (int) $version['version_id'],
				'VERSION_NUMBER'    => $version['version_number'],
				'PHPBB_VERSION'     => $version['phpbb_version'],
				'PHP_VERSION'       => $version['php_version'],
				'CHANGELOG'         => $this->render_rich_text($version['version_changelog']),
				'FILE_SIZE'         => $version['file_size'],
				'DOWNLOADS'         => (int) $version['version_downloads'],
				'CREATED'           => !empty($version['version_created']) ? $this->user->format_date((int) $version['version_created']) : '',
				'U_DOWNLOAD'        => $file_available ? $this->download_url_for_version($version) : '',
				'S_CAN_DOWNLOAD'    => $can_download && $file_available,
				'S_FILE_AVAILABLE'  => $file_available,
				'S_FILE_MISSING'    => !$file_available,
			];

			if ($first)
			{
				$current_version = $version_vars;
				$this->template->assign_vars([
					'CURRENT_VERSION_ID'      => $version_vars['VERSION_ID'],
					'CURRENT_VERSION_NUMBER'  => $version_vars['VERSION_NUMBER'],
					'CURRENT_PHPBB_VERSION'   => $version_vars['PHPBB_VERSION'],
					'CURRENT_PHP_VERSION'     => $version_vars['PHP_VERSION'],
					'CURRENT_CHANGELOG'       => $version_vars['CHANGELOG'],
					'CURRENT_FILE_SIZE'       => $version_vars['FILE_SIZE'],
					'CURRENT_DOWNLOADS'       => $version_vars['DOWNLOADS'],
					'CURRENT_CREATED'         => $version_vars['CREATED'],
					'U_CURRENT_DOWNLOAD'      => $version_vars['U_DOWNLOAD'],
					'S_CURRENT_FILE_AVAILABLE' => $version_vars['S_FILE_AVAILABLE'],
					'S_CURRENT_FILE_MISSING'   => $version_vars['S_FILE_MISSING'],
				]);

				$first = false;
				continue;
			}

			$this->template->assign_block_vars('older_versions', $version_vars);
		}
		$this->db->sql_freeresult($result);

		$sql = 'SELECT *
			FROM ' . $this->table('downloadcenter_screenshots') . '
			WHERE item_id = ' . (int) $item_id . '
			ORDER BY image_order ASC, screenshot_id ASC';
		$result = $this->db->sql_query($sql);
		$screenshot_count = 0;
		while ($screenshot = $this->db->sql_fetchrow($result))
		{
			$screenshot_count++;
			$this->template->assign_block_vars('screenshots', [
				'SCREENSHOT_ID' => (int) $screenshot['screenshot_id'],
				'CAPTION' => $screenshot['image_caption'],
				'U_IMAGE' => $this->helper->route('mundophpbb_downloadcenter_screenshot', ['screenshot_id' => (int) $screenshot['screenshot_id']]),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'ITEM_NAME'       => $item['item_name'],
			'ITEM_SHORT_DESC' => $this->render_rich_text($item['item_short_desc']),
			'ITEM_DESC'       => $this->render_rich_text($item['item_desc']),
			'ITEM_ICON'       => $this->resolve_item_icon_url($item['item_icon']),
			'CATEGORY_NAME'   => $item['category_name'],
			'AUTHOR_NAME'     => !empty($item['username']) ? $item['username'] : $this->user->lang('DOWNLOADCENTER_UNKNOWN_AUTHOR'),
			'DOWNLOADS'       => (int) $item['item_downloads'],
			'VERSION_COUNT'   => $version_count,
			'S_HAS_CURRENT_VERSION' => $current_version !== null,
			'S_HAS_SCREENSHOTS' => $screenshot_count > 0,
			'SCREENSHOT_COUNT' => $screenshot_count,
			'U_SUPPORT_TOPIC' => !empty($item['topic_id']) ? append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $item['topic_id']) : '',
			'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
			'TOTAL_PUBLIC_ITEMS' => array_sum($public_category_counts),
			'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
			'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
			'S_CAN_SUBMIT' => $this->can_submit(),
			'S_CAN_DOWNLOAD' => $can_download,
			'DOWNLOAD_BLOCK_REASON' => $download_block_reason,
			'DOWNLOADCENTER_META_DESCRIPTION' => trim((string) $item['item_short_desc']) !== '' ? $item['item_short_desc'] : utf8_clean_string($item['item_name']),
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME' => $this->user->lang('DOWNLOADCENTER_TITLE'),
			'U_VIEW_FORUM' => $this->helper->route('mundophpbb_downloadcenter_index'),
		]);
		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME' => $item['item_name'],
			'U_VIEW_FORUM' => $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => (int) $item_id]),
		]);

		return $this->helper->render('downloadcenter_item.html', $item['item_name']);
	}

	public function feed()
	{
		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		if (empty($this->config['mundophpbb_downloadcenter_enabled']) || empty($this->config['mundophpbb_downloadcenter_feed_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_FEED_DISABLED'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		$base_url = generate_board_url();
		$items = [];
		$sql = 'SELECT i.item_id, i.item_name, i.item_short_desc, i.item_updated, c.category_name
			FROM ' . $this->table('downloadcenter_items') . ' i
			LEFT JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			WHERE i.item_enabled = 1 AND i.item_approved = 1 AND c.category_enabled = 1
			ORDER BY i.item_updated DESC, i.item_created DESC';
		$result = $this->db->sql_query_limit($sql, 20);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$items[] = $row;
		}
		$this->db->sql_freeresult($result);

		$xml = [];
		$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml[] = '<rss version="2.0">';
		$xml[] = '<channel>';
		$xml[] = '<title>' . utf8_htmlspecialchars($this->user->lang('DOWNLOADCENTER_TITLE')) . '</title>';
		$xml[] = '<link>' . utf8_htmlspecialchars($base_url . $this->helper->route('mundophpbb_downloadcenter_index')) . '</link>';
		$xml[] = '<description>' . utf8_htmlspecialchars($this->user->lang('DOWNLOADCENTER_EXPLAIN')) . '</description>';
		foreach ($items as $row)
		{
			$link = $base_url . $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => (int) $row['item_id']]);
			$xml[] = '<item>';
			$xml[] = '<title>' . utf8_htmlspecialchars($row['item_name']) . '</title>';
			$xml[] = '<link>' . utf8_htmlspecialchars($link) . '</link>';
			$xml[] = '<guid>' . utf8_htmlspecialchars($link) . '</guid>';
			if (!empty($row['item_updated']))
			{
				$xml[] = '<pubDate>' . date(DATE_RSS, (int) $row['item_updated']) . '</pubDate>';
			}
			$xml[] = '<description>' . utf8_htmlspecialchars($row['item_short_desc']) . '</description>';
			if (!empty($row['category_name']))
			{
				$xml[] = '<category>' . utf8_htmlspecialchars($row['category_name']) . '</category>';
			}
			$xml[] = '</item>';
		}
		$xml[] = '</channel>';
		$xml[] = '</rss>';

		return new \Symfony\Component\HttpFoundation\Response(implode("\n", $xml), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
	}

	public function rules()
	{
		global $request;

		$this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

		if (empty($this->config['mundophpbb_downloadcenter_enabled']))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_DISABLED'));
		}

		if (!$this->can_view())
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_NOT_AUTHORISED_VIEW'));
		}

		$this->template->assign_vars([
			'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
			'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
			'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
			'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
			'S_CAN_SUBMIT' => $this->can_submit(),
			'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
			'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text(),
			'S_SCREENSHOT_ADDED' => $request->variable('screenshot_status', '') === 'added',
			'S_SCREENSHOT_UPDATED' => $request->variable('screenshot_status', '') === 'updated',
			'S_SCREENSHOT_DELETED' => $request->variable('screenshot_status', '') === 'deleted',
		]);

		return $this->helper->render('downloadcenter_rules.html', $this->user->lang('DOWNLOADCENTER_RULES_TITLE'));
	}

	protected function get_public_category_counts()
	{
		$counts = [];
		foreach ($this->get_public_category_stats() as $category_id => $stats)
		{
			$counts[(int) $category_id] = (int) $stats['item_count'];
		}

		return $counts;
	}

	protected function get_public_overall_stats()
	{
		$stats = ['items' => 0, 'categories' => 0, 'downloads' => 0, 'updated' => 0];
		$sql = 'SELECT COUNT(i.item_id) AS items, COALESCE(SUM(i.item_downloads), 0) AS downloads, MAX(i.item_updated) AS updated
			FROM ' . $this->table('downloadcenter_items') . ' i
			INNER JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			WHERE i.item_enabled = 1
				AND i.item_approved = 1
				AND c.category_enabled = 1';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($row)
		{
			$stats['items'] = (int) $row['items'];
			$stats['downloads'] = (int) $row['downloads'];
			$stats['updated'] = (int) $row['updated'];
		}

		$sql = 'SELECT COUNT(*) AS categories FROM ' . $this->table('downloadcenter_categories') . ' WHERE category_enabled = 1';
		$result = $this->db->sql_query($sql);
		$stats['categories'] = (int) $this->db->sql_fetchfield('categories');
		$this->db->sql_freeresult($result);

		return $stats;
	}

	protected function get_public_category_stats()
	{
		$stats = [];
		$sql = 'SELECT i.category_id, COUNT(i.item_id) AS item_count, SUM(i.item_downloads) AS download_count, MAX(i.item_updated) AS latest_update
			FROM ' . $this->table('downloadcenter_items') . ' i
			INNER JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
			WHERE i.item_enabled = 1
				AND i.item_approved = 1
				AND c.category_enabled = 1
			GROUP BY i.category_id';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$stats[(int) $row['category_id']] = [
				'item_count' => (int) $row['item_count'],
				'download_count' => (int) $row['download_count'],
				'latest_update' => (int) $row['latest_update'],
			];
		}
		$this->db->sql_freeresult($result);

		return $stats;
	}

	protected function get_public_version_filter_options($field, $selected_value = '')
	{
		$allowed_fields = ['phpbb_version', 'php_version'];
		if (!in_array($field, $allowed_fields, true))
		{
			return [];
		}

		$options = [];
		// $field was checked against $allowed_fields above, so it is safe as an identifier.
		$sql = sprintf(
			"SELECT DISTINCT v.%1\$s AS filter_value
			FROM %2\$s v
			INNER JOIN %3\$s i ON i.item_id = v.item_id
			INNER JOIN %4\$s c ON c.category_id = i.category_id
			WHERE i.item_enabled = 1
				AND i.item_approved = 1
				AND c.category_enabled = 1
				AND v.version_enabled = 1
				AND v.%1\$s <> ''
			ORDER BY v.%1\$s ASC",
			$field,
			$this->table('downloadcenter_versions'),
			$this->table('downloadcenter_items'),
			$this->table('downloadcenter_categories')
		);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$value = trim((string) $row['filter_value']);
			if ($value === '')
			{
				continue;
			}

			$options[] = [
				'VALUE' => $value,
				'LABEL' => $value,
				'S_SELECTED' => ($value === $selected_value),
			];
		}
		$this->db->sql_freeresult($result);

		return $options;
	}

	protected function get_current_versions_for_items(array $item_ids)
	{
		$item_ids = array_values(array_unique(array_map('intval', $item_ids)));
		$item_ids = array_filter($item_ids, function ($item_id) {
			return $item_id > 0;
		});

		if (empty($item_ids))
		{
			return [];
		}

		$versions = [];
		$missing_item_ids = [];
		$current_version_ids = [];
		$current_version_item_map = [];

		$sql = 'SELECT item_id, item_current_version_id
			FROM ' . $this->table('downloadcenter_items') . '
			WHERE ' . $this->db->sql_in_set('item_id', $item_ids);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$item_id = (int) $row['item_id'];
			$version_id = (int) $row['item_current_version_id'];
			if ($version_id > 0)
			{
				$current_version_ids[] = $version_id;
				$current_version_item_map[$version_id] = $item_id;
			}
			else
			{
				$missing_item_ids[] = $item_id;
			}
		}
		$this->db->sql_freeresult($result);

		if (!empty($current_version_ids))
		{
			$sql = 'SELECT *
				FROM ' . $this->table('downloadcenter_versions') . '
				WHERE ' . $this->db->sql_in_set('version_id', array_values(array_unique($current_version_ids))) . '
					AND version_enabled = 1';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$version_id = (int) $row['version_id'];
				$item_id = (int) $row['item_id'];
				if (isset($current_version_item_map[$version_id]) && $current_version_item_map[$version_id] === $item_id)
				{
					$versions[$item_id] = $row;
				}
			}
			$this->db->sql_freeresult($result);
		}

		foreach ($item_ids as $item_id)
		{
			if (!isset($versions[$item_id]))
			{
				$missing_item_ids[] = $item_id;
			}
		}

		$missing_item_ids = array_values(array_unique($missing_item_ids));
		if (!empty($missing_item_ids))
		{
			$sql = 'SELECT *
				FROM ' . $this->table('downloadcenter_versions') . '
				WHERE ' . $this->db->sql_in_set('item_id', $missing_item_ids) . '
					AND version_enabled = 1
				ORDER BY item_id ASC, version_created DESC, version_id DESC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$item_id = (int) $row['item_id'];
				if (!isset($versions[$item_id]))
				{
					$versions[$item_id] = $row;
				}
			}
			$this->db->sql_freeresult($result);
		}

		return $versions;
	}

	protected function get_latest_version($item_id)
	{
		$current_version_id = 0;
		$sql = 'SELECT item_current_version_id
			FROM ' . $this->table('downloadcenter_items') . '
			WHERE item_id = ' . (int) $item_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$current_version_id = (int) $this->db->sql_fetchfield('item_current_version_id');
		$this->db->sql_freeresult($result);

		if ($current_version_id > 0)
		{
			$sql = 'SELECT *
				FROM ' . $this->table('downloadcenter_versions') . '
				WHERE version_id = ' . (int) $current_version_id . '
					AND item_id = ' . (int) $item_id . '
					AND version_enabled = 1';
			$result = $this->db->sql_query_limit($sql, 1);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($row)
			{
				return $row;
			}
		}

		$sql = 'SELECT *
			FROM ' . $this->table('downloadcenter_versions') . '
			WHERE item_id = ' . (int) $item_id . '
				AND version_enabled = 1
			ORDER BY version_created DESC, version_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function version_file_available($version)
	{
		if (!is_array($version))
		{
			return false;
		}

		if ((string) $version['download_type'] === 'external')
		{
			return trim((string) $version['download_url']) !== '';
		}

		$file_name = trim((string) $version['download_file']);
		if ($this->is_phpbb_attachment_reference($file_name))
		{
			$attachment = $this->get_phpbb_attachment_file($file_name);
			return !empty($attachment['path']) && is_file($attachment['path']);
		}

		return $file_name !== '' && is_file($this->local_file_path($file_name));
	}

	protected function download_url_for_version(array $version)
	{
		$file_name = isset($version['download_file']) ? (string) $version['download_file'] : '';

		if ((string) $version['download_type'] === 'local' && preg_match('/^attach:(\d+)$/', $file_name, $matches))
		{
			return append_sid($this->root_path . 'download/file.' . $this->php_ext, 'id=' . (int) $matches[1]);
		}

		return $this->helper->route('mundophpbb_downloadcenter_download', ['version_id' => (int) $version['version_id']]);
	}

	protected function rules_url()
	{
		$rules_topic_id = isset($this->config['mundophpbb_downloadcenter_rules_topic_id']) ? (int) $this->config['mundophpbb_downloadcenter_rules_topic_id'] : 0;

		if ($rules_topic_id > 0)
		{
			return append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . $rules_topic_id);
		}

		return $this->helper->route('mundophpbb_downloadcenter_rules');
	}

	protected function resolve_item_icon_url($icon)
	{
		$icon = trim((string) $icon);
		if ($icon === '')
		{
			return '';
		}

		if (strpos($icon, 'item_image:') === 0)
		{
			$file = basename(substr($icon, strlen('item_image:')));
			return $file !== '' ? $this->helper->route('mundophpbb_downloadcenter_item_image', ['file_name' => $file]) : '';
		}

		if (strpos($icon, 'screenshot:') === 0)
		{
			$screenshot_id = (int) substr($icon, strlen('screenshot:'));
			return $screenshot_id > 0 ? $this->helper->route('mundophpbb_downloadcenter_screenshot', ['screenshot_id' => $screenshot_id]) : '';
		}

		return $icon;
	}

	protected function render_rich_text($text)
	{
		$text = (string) $text;
		if ($text === '')
		{
			return '';
		}

		// Keep the database schema stable and render a safe, lightweight BBCode subset at display time.
		// This supports the common tags needed for descriptions and changelogs without storing uid/bitfield columns.
		// Text coming from request->variable() is already HTML-escaped; normalise first so it is escaped exactly once.
		$text = utf8_htmlspecialchars(htmlspecialchars_decode($text, ENT_QUOTES));

		$text = preg_replace('#\[b\](.*?)\[/b\]#is', '<strong>$1</strong>', $text);
		$text = preg_replace('#\[i\](.*?)\[/i\]#is', '<em>$1</em>', $text);
		$text = preg_replace('#\[u\](.*?)\[/u\]#is', '<span style="text-decoration: underline;">$1</span>', $text);
		$text = preg_replace('#\[s\](.*?)\[/s\]#is', '<span style="text-decoration: line-through;">$1</span>', $text);
		$text = preg_replace_callback('~\[color=(\#[0-9a-f]{3}|\#[0-9a-f]{6}|[a-z][a-z0-9_-]{1,20})\](.*?)\[/color\]~is', function ($matches) {
			return '<span style="color: ' . strtolower($matches[1]) . ';">' . $matches[2] . '</span>';
		}, $text);
		$text = preg_replace('#\[quote\](.*?)\[/quote\]#is', '<blockquote>$1</blockquote>', $text);
		$text = preg_replace('#\[code\](.*?)\[/code\]#is', '<pre><code>$1</code></pre>', $text);
		$text = preg_replace('#\[url\](https?://[^\s\[]+?)\[/url\]#is', '<a href="$1" rel="nofollow noopener" target="_blank">$1</a>', $text);
		$text = preg_replace('#\[url=(https?://[^\s\]]+?)\](.*?)\[/url\]#is', '<a href="$1" rel="nofollow noopener" target="_blank">$2</a>', $text);
		$text = preg_replace('#\[img\](https?://[^\s\[]+?)\[/img\]#is', '<img src="$1" alt="" class="downloadcenter-bbcode-image">', $text);
		$text = preg_replace_callback('#\[size=(85|100|120|150|200)\](.*?)\[/size\]#is', function ($matches) {
			return '<span style="font-size: ' . (int) $matches[1] . '%;">' . $matches[2] . '</span>';
		}, $text);

		$text = preg_replace_callback('#\[list(?:=(1|a|A|i|I))?\](.*?)\[/list\]#is', function ($matches) {
			$list_type = isset($matches[1]) ? $matches[1] : '';
			$body = trim($matches[2]);
			$items = [];

			// Robust phpBB-style list parsing. Supports both [list] and [list=1].
			// Items may be written as [*]Item or as one item per line.
			if (stripos($body, '[*]') !== false)
			{
				$parts = preg_split('#\[\*\]#i', $body);
				foreach ($parts as $part)
				{
					$item = trim($part);
					if ($item !== '')
					{
						$items[] = $item;
					}
				}
			}
			else
			{
				$lines = preg_split('/\r?\n/', $body);
				foreach ($lines as $line)
				{
					$line = trim($line);
					if ($line !== '')
					{
						$items[] = $line;
					}
				}
			}

			if (empty($items))
			{
				return '';
			}

			$tag = ($list_type !== '') ? 'ol' : 'ul';
			$type_attr = '';
			if ($tag === 'ol' && in_array($list_type, ['1', 'a', 'A', 'i', 'I'], true))
			{
				$type_attr = ' type="' . $list_type . '"';
			}

			$html = '<' . $tag . ' class="downloadcenter-bbcode-list"' . $type_attr . '>';
			foreach ($items as $item)
			{
				$html .= '<li>' . nl2br(trim($item), false) . '</li>';
			}
			$html .= '</' . $tag . '>';

			return $html;
		}, $text);

		// Avoid inserting <br> immediately around generated list markup.
		$text = preg_replace('#\s*(<(?:ul|ol) class="downloadcenter-bbcode-list"[^>]*>)#', '$1', $text);
		$text = preg_replace('#(</(?:ul|ol)>)\s*#', '$1', $text);

		return nl2br($text, false);
	}
}

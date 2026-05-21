<?php

namespace mundophpbb\downloadcenter\controller;

class main_controller
{
    protected $config;
    protected $helper;
    protected $template;
    protected $user;
    protected $auth;
    protected $db;
    protected $root_path;
    protected $php_ext;
    protected $notification_helper;

    public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, $root_path, $php_ext, \mundophpbb\downloadcenter\service\notification_helper $notification_helper)
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
        $this->db = $db;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
        $this->notification_helper = $notification_helper;
    }

    public function index()
    {
        global $request;

        $this->user->add_lang_ext('mundophpbb/downloadcenter', 'common');

        $can_download = $this->can_download();
        $download_block_reason = $this->download_block_reason();
        $pending_total = $this->is_admin() ? $this->count_pending_items() : 0;

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
        $sort = $request->variable('sort', 'newest');
        $allowed_sorts = ['newest', 'name', 'downloads'];
        if (!in_array($sort, $allowed_sorts, true))
        {
            $sort = 'newest';
        }

        $start = max(0, $request->variable('start', 0));
        $per_page = isset($this->config['mundophpbb_downloadcenter_public_per_page']) ? max(1, (int) $this->config['mundophpbb_downloadcenter_public_per_page']) : 12;

        $public_category_counts = $this->get_public_category_counts();

        $sql = 'SELECT *
            FROM ' . $this->table('downloadcenter_categories') . '
            WHERE category_enabled = 1
            ORDER BY category_order ASC, category_name ASC';
        $result = $this->db->sql_query($sql);
        while ($category = $this->db->sql_fetchrow($result))
        {
            $cat_id = (int) $category['category_id'];
            $category_count = isset($public_category_counts[$cat_id]) ? (int) $public_category_counts[$cat_id] : 0;
            $this->template->assign_block_vars('categories', [
                'CATEGORY_ID' => $cat_id,
                'CATEGORY_NAME' => $category['category_name'],
                'CATEGORY_COUNT' => $category_count,
                'S_SELECTED' => $cat_id === $category_id,
                'S_HAS_ITEMS' => $category_count > 0,
                'U_CATEGORY' => $this->helper->route('mundophpbb_downloadcenter_index') . '?c=' . $cat_id,
            ]);
        }
        $this->db->sql_freeresult($result);

        $where = [
            'i.item_enabled = 1',
            'i.item_approved = 1',
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

        $order_by = 'i.item_updated DESC, i.item_created DESC';
        if ($sort === 'name')
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
        while ($row = $this->db->sql_fetchrow($result))
        {
            $version = $this->get_latest_version((int) $row['item_id']);

            $this->template->assign_block_vars('items', [
                'ITEM_ID'          => (int) $row['item_id'],
                'ITEM_NAME'        => $row['item_name'],
                'ITEM_SHORT_DESC'  => $this->render_short_text($row['item_short_desc']),
                'ITEM_ICON'        => $this->resolve_item_icon_url($row['item_icon']),
                'CATEGORY_NAME'    => $row['category_name'],
                'AUTHOR_NAME'      => !empty($row['username']) ? $row['username'] : $this->user->lang('DOWNLOADCENTER_UNKNOWN_AUTHOR'),
                'VERSION_NUMBER'   => $version ? $version['version_number'] : '',
                'PHPBB_VERSION'    => $version ? $version['phpbb_version'] : '',
                'PHP_VERSION'      => $version ? $version['php_version'] : '',
                'FILE_SIZE'        => $version ? $version['file_size'] : '',
                'DOWNLOADS'        => (int) $row['item_downloads'],
                'U_ITEM'           => $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => (int) $row['item_id']]),
                'S_CAN_DOWNLOAD'   => $can_download,
            ]);
        }
        $this->db->sql_freeresult($result);

        $this->template->assign_vars([
            'SEARCH_QUERY' => $search,
            'SELECTED_CATEGORY_ID' => $category_id,
            'SORT' => $sort,
            'PAGINATION' => $this->make_pagination($this->pagination_url($this->helper->route('mundophpbb_downloadcenter_index'), ['q' => $search, 'c' => $category_id, 'sort' => $sort]), $total_items, $per_page, $start),
            'PAGE_NUMBER' => $this->make_page_number($total_items, $per_page, $start),
            'TOTAL_ITEMS' => $total_items,
            'S_HAS_PAGINATION' => $total_items > $per_page,
            'S_SORT_NEWEST' => $sort === 'newest',
            'S_SORT_NAME' => $sort === 'name',
            'S_SORT_DOWNLOADS' => $sort === 'downloads',
            'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
            'TOTAL_PUBLIC_ITEMS' => array_sum($public_category_counts),
            'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
            'U_DOWNLOADCENTER_MINE' => $this->helper->route('mundophpbb_downloadcenter_mine'),
            'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
            'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
            'S_CAN_SUBMIT' => $this->can_submit(),
            'S_CAN_DOWNLOAD' => $can_download,
            'DOWNLOAD_BLOCK_REASON' => $download_block_reason,
            'PENDING_TOTAL' => $pending_total,
            'S_SHOW_ADMIN_PENDING_NOTICE' => $pending_total > 0,
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
                AND i.item_approved = 1';
        $result = $this->db->sql_query($sql);
        $item = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$item)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_ITEM_NOT_FOUND'));
        }

        $sql = 'SELECT *
            FROM ' . $this->table('downloadcenter_versions') . '
            WHERE item_id = ' . (int) $item_id . '
                AND version_enabled = 1
            ORDER BY version_created DESC, version_id DESC';
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
                'U_DOWNLOAD'        => $file_available ? $this->helper->route('mundophpbb_downloadcenter_download', ['version_id' => (int) $version['version_id']]) : '',
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
            'ITEM_SHORT_DESC' => $this->render_short_text($item['item_short_desc']),
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
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
            'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
            'S_CAN_SUBMIT' => $this->can_submit(),
            'S_CAN_DOWNLOAD' => $can_download,
            'DOWNLOAD_BLOCK_REASON' => $download_block_reason,
        ]);

        return $this->helper->render('downloadcenter_item.html', $item['item_name']);
    }

    public function rules()
    {
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
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
            'S_CAN_SUBMIT' => $this->can_submit(),
            'S_SHOW_MY_SUBMISSIONS' => !$this->is_anonymous(),
            'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text(),
            'S_SCREENSHOT_ADDED' => $request->variable('screenshot_status', '') === 'added',
            'S_SCREENSHOT_UPDATED' => $request->variable('screenshot_status', '') === 'updated',
            'S_SCREENSHOT_DELETED' => $request->variable('screenshot_status', '') === 'deleted',
        ]);

        return $this->helper->render('downloadcenter_rules.html', $this->user->lang('DOWNLOADCENTER_RULES_TITLE'));
    }

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

        $start = max(0, $request->variable('start', 0));
        $per_page = isset($this->config['mundophpbb_downloadcenter_public_per_page']) ? max(1, (int) $this->config['mundophpbb_downloadcenter_public_per_page']) : 12;

        $sql = 'SELECT COUNT(*) AS total
            FROM ' . $this->table('downloadcenter_items') . ' i
            WHERE i.user_id = ' . (int) $this->user->data['user_id'];
        $result = $this->db->sql_query($sql);
        $total_my_items = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        $start = $this->normalize_start($start, $per_page, $total_my_items);

        $sql = 'SELECT i.*, c.category_name,
                    (SELECT v.version_number FROM ' . $this->table('downloadcenter_versions') . ' v WHERE v.item_id = i.item_id ORDER BY v.version_created DESC, v.version_id DESC LIMIT 1) AS latest_version
            FROM ' . $this->table('downloadcenter_items') . ' i
            LEFT JOIN ' . $this->table('downloadcenter_categories') . ' c ON c.category_id = i.category_id
            WHERE i.user_id = ' . (int) $this->user->data['user_id'] . '
            ORDER BY i.item_updated DESC, i.item_created DESC, i.item_name ASC';
        $result = $this->db->sql_query_limit($sql, $per_page, $start);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $status_key = 'DOWNLOADCENTER_STATUS_PENDING';
            if ((int) $row['item_approved'] === 1 && (int) $row['item_enabled'] === 1)
            {
                $status_key = 'DOWNLOADCENTER_STATUS_PUBLISHED';
            }
            else if ((int) $row['item_enabled'] === 0)
            {
                $status_key = 'DOWNLOADCENTER_STATUS_DISABLED';
            }

            $this->template->assign_block_vars('my_items', [
                'ITEM_ID' => (int) $row['item_id'],
                'ITEM_NAME' => $row['item_name'],
                'CATEGORY_NAME' => $row['category_name'] ?: '-',
                'LATEST_VERSION' => $row['latest_version'] ?: '-',
                'ITEM_SHORT_DESC' => $this->render_short_text($row['item_short_desc']),
                'STATUS' => $this->user->lang($status_key),
                'ITEM_DOWNLOADS' => (int) $row['item_downloads'],
                'ITEM_UPDATED' => !empty($row['item_updated']) ? $this->user->format_date((int) $row['item_updated']) : '',
                'U_ITEM' => ((int) $row['item_approved'] === 1 && (int) $row['item_enabled'] === 1) ? $this->helper->route('mundophpbb_downloadcenter_item', ['item_id' => (int) $row['item_id']]) : '',
                'U_EDIT' => $this->helper->route('mundophpbb_downloadcenter_edit', ['item_id' => (int) $row['item_id']]),
            ]);
        }
        $this->db->sql_freeresult($result);

        $this->template->assign_vars([
            'U_DOWNLOADCENTER_INDEX' => $this->helper->route('mundophpbb_downloadcenter_index'),
            'U_DOWNLOADCENTER_SUBMIT' => $this->helper->route('mundophpbb_downloadcenter_submit'),
            'U_DOWNLOADCENTER_RULES' => $this->rules_url(),
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
            'S_CAN_SUBMIT' => $this->can_submit(),
            'PAGINATION' => $this->make_pagination($this->helper->route('mundophpbb_downloadcenter_mine'), $total_my_items, $per_page, $start),
            'PAGE_NUMBER' => $this->make_page_number($total_my_items, $per_page, $start),
            'TOTAL_MY_ITEMS' => $total_my_items,
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
            WHERE item_id = ' . $item_id . '
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

            $this->insert_screenshot($item_id, $uploaded_screenshot['file_name'], $this->sanitize_db_text(trim($request->variable('new_screenshot_caption', '', true))), max(0, $request->variable('new_screenshot_order', 0)));
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
                WHERE item_id = ' . $item_id;
            $result = $this->db->sql_query($sql);
            while ($screenshot = $this->db->sql_fetchrow($result))
            {
                $sid = (int) $screenshot['screenshot_id'];
                $data = [
                    'image_caption' => isset($captions[$sid]) ? $this->sanitize_db_text(trim((string) $captions[$sid])) : '',
                    'image_order' => isset($orders[$sid]) ? max(0, (int) $orders[$sid]) : 0,
                ];

                $sql_update = 'UPDATE ' . $this->table('downloadcenter_screenshots') . '
                    SET ' . $this->db->sql_build_array('UPDATE', $data) . '
                    WHERE screenshot_id = ' . $sid . '
                        AND item_id = ' . $item_id;
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
                    AND item_id = ' . $item_id;
            $result = $this->db->sql_query_limit($sql, 1);
            $screenshot = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);

            if ($screenshot)
            {
                $this->delete_screenshot_file($screenshot['image_file']);
                $sql = 'DELETE FROM ' . $this->table('downloadcenter_screenshots') . '
                    WHERE screenshot_id = ' . (int) $delete_screenshot_id . '
                        AND item_id = ' . $item_id;
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

            $item_name = $this->sanitize_db_text(trim($request->variable('item_name', '', true)));
            $category_id = max(0, $request->variable('category_id', 0));
            $item_short_desc = $this->sanitize_db_text(trim($request->variable('item_short_desc', '', true)));
            $item_desc = $this->sanitize_db_text(trim($request->variable('item_desc', '', true)));
            $item_icon = $this->sanitize_db_text(trim($request->variable('item_icon', '', true)));
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
                WHERE item_id = ' . $item_id . '
                    AND user_id = ' . (int) $this->user->data['user_id'];
            $this->db->sql_query($sql);

            $version_id = 0;

            if ($request->is_set_post('latest_version_changelog'))
            {
                $latest_version_id = $request->variable('latest_version_id', 0);
                $latest_changelog = $this->sanitize_db_text(trim($request->variable('latest_version_changelog', '', true)));

                if ($latest_version_id > 0)
                {
                    $sql = 'SELECT version_id FROM ' . $versions_table . '
                        WHERE version_id = ' . (int) $latest_version_id . '
                            AND item_id = ' . $item_id;
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

            if ($add_new_version)
            {
                $version_number = $this->sanitize_db_text(trim($request->variable('version_number', '', true)));
                $phpbb_version = $this->sanitize_db_text(trim($request->variable('phpbb_version', '', true)));
                $php_version = $this->sanitize_db_text(trim($request->variable('php_version', '', true)));
                $version_changelog = $this->sanitize_db_text(trim($request->variable('version_changelog', '', true)));
                $download_type = $request->variable('download_type', 'external');
                $download_url = $this->sanitize_db_text(trim($request->variable('download_url', '', true)));
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
        $sql = 'SELECT *
            FROM ' . $versions_table . '
            WHERE item_id = ' . $item_id . '
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
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
            'ITEM_NAME' => $item['item_name'],
            'ITEM_SHORT_DESC' => $item['item_short_desc'],
            'ITEM_DESC' => $item['item_desc'],
            'ITEM_ICON' => $item['item_icon'],
            'S_ITEM_APPROVED' => (int) $item['item_approved'] === 1,
            'LATEST_VERSION_ID' => $latest_version_id,
            'LATEST_VERSION_CHANGELOG' => $latest_version_changelog,
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

            $item_name = $this->sanitize_db_text(trim($request->variable('item_name', '', true)));
            $version_number = $this->sanitize_db_text(trim($request->variable('version_number', '', true)));
            $category_id = max(0, $request->variable('category_id', 0));
            $item_short_desc = $this->sanitize_db_text(trim($request->variable('item_short_desc', '', true)));
            $item_desc = $this->sanitize_db_text(trim($request->variable('item_desc', '', true)));
            $item_icon = $this->sanitize_db_text(trim($request->variable('item_icon', '', true)));
            $phpbb_version = $this->sanitize_db_text(trim($request->variable('phpbb_version', '', true)));
            $php_version = $this->sanitize_db_text(trim($request->variable('php_version', '', true)));
            $version_changelog = $this->sanitize_db_text(trim($request->variable('version_changelog', '', true)));
            $download_type = $request->variable('download_type', 'external');
            $download_url = $this->sanitize_db_text(trim($request->variable('download_url', '', true)));
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

            if ($this->requires_rules_acceptance() && !$request->variable('accept_rules', 0))
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

            $uploaded_screenshot = $this->handle_screenshot_upload($request);
            if ($uploaded_screenshot)
            {
                $this->insert_screenshot($item_id, $uploaded_screenshot['file_name'], $this->sanitize_db_text(trim($request->variable('screenshot_caption', '', true))), max(0, $request->variable('screenshot_order', 0)));
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
            'S_DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => $this->requires_rules_acceptance(),
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


    protected function get_public_category_counts()
    {
        $counts = [];
        $sql = 'SELECT category_id, COUNT(item_id) AS item_count
            FROM ' . $this->table('downloadcenter_items') . '
            WHERE item_enabled = 1
                AND item_approved = 1
            GROUP BY category_id';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $counts[(int) $row['category_id']] = (int) $row['item_count'];
        }
        $this->db->sql_freeresult($result);

        return $counts;
    }

    protected function get_latest_version($item_id)
    {
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

    protected function can_view()
    {
        return $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_view_access']) ? $this->config['mundophpbb_downloadcenter_view_access'] : 'all');
    }

    protected function can_download()
    {
        $min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];

        return $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_download_access']) ? $this->config['mundophpbb_downloadcenter_download_access'] : 'registered')
            && ($min_posts <= 0 || (int) $this->user->data['user_posts'] >= $min_posts);
    }

    protected function can_submit()
    {
        return $this->submissions_enabled()
            && $this->access_allowed(isset($this->config['mundophpbb_downloadcenter_submit_access']) ? $this->config['mundophpbb_downloadcenter_submit_access'] : 'registered');
    }

    protected function download_block_reason()
    {
        $min_posts = (int) $this->config['mundophpbb_downloadcenter_min_posts'];

        if (!$this->access_allowed(isset($this->config['mundophpbb_downloadcenter_download_access']) ? $this->config['mundophpbb_downloadcenter_download_access'] : 'registered'))
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

    protected function is_admin()
    {
        return ((int) $this->user->data['user_type'] === USER_FOUNDER) || $this->auth->acl_get('a_board');
    }

    protected function is_anonymous()
    {
        return (int) $this->user->data['user_id'] === ANONYMOUS;
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
        return $file_name !== '' && is_file($this->local_file_path($file_name));
    }

    protected function local_file_path($file_name)
    {
        return $this->root_path . 'files/mundophpbb/downloadcenter/' . basename((string) $file_name);
    }

    protected function handle_local_upload($request)
    {
        $file = $request->file('download_upload');

        if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
        {
            return false;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        $original_name = (string) $file['name'];
        $tmp_name = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = $this->get_allowed_extensions();
        $max_bytes = $this->get_max_upload_bytes();

        if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', implode(', ', $allowed)));
        }

        if ($max_bytes > 0 && $size > $max_bytes)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_TOO_LARGE', $this->format_file_size($max_bytes)));
        }

        if ($tmp_name === '' || !is_uploaded_file($tmp_name))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        $directory = $this->root_path . 'files/mundophpbb/downloadcenter/';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
        }

        $htaccess = $directory . '.htaccess';
        if (!is_file($htaccess))
        {
            @file_put_contents($htaccess, "<Files *>\n\tRequire all denied\n</Files>\n");
        }

        $safe_base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($original_name, PATHINFO_FILENAME));
        $safe_base = trim($safe_base, '.-') ?: 'download';
        $file_name = time() . '-' . substr(md5($original_name . microtime(true)), 0, 8) . '-' . $safe_base . '.' . $extension;
        $destination = $directory . $file_name;

        if (!@move_uploaded_file($tmp_name, $destination))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
        }

        return [
            'file_name' => $file_name,
            'file_size' => $this->format_file_size($size),
        ];
    }


    protected function screenshots_storage_directory()
    {
        return $this->root_path . 'files/mundophpbb/downloadcenter/screenshots/';
    }

    protected function handle_screenshot_upload($request)
    {
        $file = $request->file('screenshot_upload');

        if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
        {
            return false;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
        }

        $original_name = (string) $file['name'];
        $tmp_name = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_bytes = 5 * 1024 * 1024;

        if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_EXTENSION_NOT_ALLOWED'));
        }

        if ($size > $max_bytes)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_TOO_LARGE', $this->format_file_size($max_bytes)));
        }

        if ($tmp_name === '' || !is_uploaded_file($tmp_name))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
        }

        if (@getimagesize($tmp_name) === false)
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_INVALID_IMAGE'));
        }

        $directory = $this->screenshots_storage_directory();
        if (!is_dir($directory) && !@mkdir($directory, 0755, true))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
        }

        $htaccess = $directory . '.htaccess';
        if (!is_file($htaccess))
        {
            @file_put_contents($htaccess, "<Files *>\n\tRequire all denied\n</Files>\n");
        }

        $safe_base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($original_name, PATHINFO_FILENAME));
        $safe_base = trim($safe_base, '.-') ?: 'screenshot';
        $file_name = time() . '-' . substr(md5($original_name . microtime(true)), 0, 8) . '-' . $safe_base . '.' . $extension;
        $destination = $directory . $file_name;

        if (!@move_uploaded_file($tmp_name, $destination))
        {
            trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
        }

        return ['file_name' => $file_name];
    }

    protected function insert_screenshot($item_id, $file_name, $caption = '', $order = 0)
    {
        $data = [
            'item_id' => (int) $item_id,
            'image_file' => (string) $file_name,
            'image_caption' => (string) $caption,
            'image_order' => max(0, (int) $order),
            'image_created' => time(),
        ];

        $this->db->sql_query('INSERT INTO ' . $this->table('downloadcenter_screenshots') . ' ' . $this->db->sql_build_array('INSERT', $data));
    }

    protected function assign_author_screenshots($item_id)
    {
        $sql = 'SELECT *
            FROM ' . $this->table('downloadcenter_screenshots') . '
            WHERE item_id = ' . (int) $item_id . '
            ORDER BY image_order ASC, screenshot_id ASC';
        $result = $this->db->sql_query($sql);
        $count = 0;
        while ($screenshot = $this->db->sql_fetchrow($result))
        {
            $count++;
            $this->template->assign_block_vars('edit_screenshots', [
                'SCREENSHOT_ID' => (int) $screenshot['screenshot_id'],
                'CAPTION' => $screenshot['image_caption'],
                'ORDER' => (int) $screenshot['image_order'],
                'FILENAME' => $screenshot['image_file'],
                'CREATED' => !empty($screenshot['image_created']) ? $this->user->format_date((int) $screenshot['image_created']) : '',
                'U_IMAGE' => $this->helper->route('mundophpbb_downloadcenter_screenshot', ['screenshot_id' => (int) $screenshot['screenshot_id']]),
            ]);
        }
        $this->db->sql_freeresult($result);

        $this->template->assign_var('S_HAS_EDIT_SCREENSHOTS', $count > 0);
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


    protected function screenshot_file_path($file_name)
    {
        return $this->screenshots_storage_directory() . basename((string) $file_name);
    }

    protected function delete_screenshot_file($file_name)
    {
        $file_name = basename((string) $file_name);
        if ($file_name === '')
        {
            return false;
        }

        $path = $this->screenshot_file_path($file_name);
        if (is_file($path))
        {
            return @unlink($path);
        }

        return false;
    }

    protected function redirect_to_author_screenshots($item_id, $status)
    {
        $url = $this->helper->route('mundophpbb_downloadcenter_edit', ['item_id' => (int) $item_id]);
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'screenshot_status=' . rawurlencode($status) . '#downloadcenter-screenshots';
        \redirect($url);
    }


    protected function get_allowed_extensions()
    {
        $raw = isset($this->config['mundophpbb_downloadcenter_allowed_extensions']) ? (string) $this->config['mundophpbb_downloadcenter_allowed_extensions'] : 'zip,rar,7z,tar,gz,tgz,bz2,pdf,txt';
        $parts = preg_split('/[\s,;]+/', strtolower($raw));
        $allowed = [];
        foreach ($parts as $part)
        {
            $part = trim($part, '. ');
            if ($part !== '' && preg_match('/^[a-z0-9]{1,10}$/', $part))
            {
                $allowed[$part] = $part;
            }
        }

        if (empty($allowed))
        {
            $allowed = ['zip' => 'zip'];
        }

        return array_values($allowed);
    }

    protected function get_allowed_extensions_string()
    {
        return implode(', ', $this->get_allowed_extensions());
    }

    protected function get_max_upload_mb()
    {
        return max(1, (int) (isset($this->config['mundophpbb_downloadcenter_max_upload_mb']) ? $this->config['mundophpbb_downloadcenter_max_upload_mb'] : 20));
    }

    protected function get_max_upload_bytes()
    {
        return $this->get_max_upload_mb() * 1024 * 1024;
    }

    protected function upload_rules_text()
    {
        return $this->user->lang('DOWNLOADCENTER_UPLOAD_RULES', $this->get_allowed_extensions_string(), $this->format_file_size($this->get_max_upload_bytes()));
    }

    protected function is_safe_upload_name($name)
    {
        $name = (string) $name;
        if ($name === '' || preg_match('~[\x00-\x1F\x7F\\/:*?"<>|]~', $name))
        {
            return false;
        }

        $base = basename($name);
        if ($base !== $name || $base === '' || $base === '.' || $base === '..')
        {
            return false;
        }

        $parts = explode('.', $base);
        if (count($parts) < 2)
        {
            return false;
        }

        $dangerous = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'sh', 'bat', 'cmd', 'com', 'scr', 'js', 'html', 'htm'];
        foreach (array_slice($parts, 0, -1) as $part)
        {
            if (in_array(strtolower($part), $dangerous, true))
            {
                return false;
            }
        }

        return true;
    }

    protected function format_file_size($bytes)
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes >= 1073741824)
        {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576)
        {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024)
        {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
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
        $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');

        $text = preg_replace('#\[b\](.*?)\[/b\]#is', '<strong>$1</strong>', $text);
        $text = preg_replace('#\[i\](.*?)\[/i\]#is', '<em>$1</em>', $text);
        $text = preg_replace('#\[u\](.*?)\[/u\]#is', '<span style="text-decoration: underline;">$1</span>', $text);
        $text = preg_replace('#\[s\](.*?)\[/s\]#is', '<span style="text-decoration: line-through;">$1</span>', $text);
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

    protected function render_short_text($text)
    {
        $html = $this->render_rich_text($text);

        // The short description is used in compact areas such as cards and user dashboards.
        // Keep BBCode formatting, but avoid media/heavy blocks that can break layout.
        $html = preg_replace('#<img\s+[^>]*class="downloadcenter-bbcode-image"[^>]*>#i', '', $html);
        $html = preg_replace('#<pre><code>(.*?)</code></pre>#is', '<code>$1</code>', $html);

        return $html;
    }

    protected function slugify($text)
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'item';
    }


    protected function normalize_start($start, $per_page, $total_items)
    {
        $start = max(0, (int) $start);
        $per_page = max(1, (int) $per_page);
        $total_items = max(0, (int) $total_items);

        if ($total_items > 0 && $start >= $total_items)
        {
            $start = max(0, ((int) floor(($total_items - 1) / $per_page)) * $per_page);
        }

        return $start;
    }

    protected function pagination_url($base_url, array $params)
    {
        $query = [];

        foreach ($params as $key => $value)
        {
            if ($value === '' || $value === null || $value === 0 || $value === '0')
            {
                continue;
            }

            $query[] = urlencode($key) . '=' . urlencode((string) $value);
        }

        return $base_url . ($query ? '?' . implode('&amp;', $query) : '');
    }


    protected function make_page_number($total_items, $per_page, $start)
    {
        $total_items = max(0, (int) $total_items);
        $per_page = max(1, (int) $per_page);
        $start = max(0, (int) $start);

        if ($total_items === 0)
        {
            return $this->user->lang('DOWNLOADCENTER_PAGE_NUMBER', 1, 1);
        }

        $current_page = (int) floor($start / $per_page) + 1;
        $total_pages = (int) ceil($total_items / $per_page);

        return $this->user->lang('DOWNLOADCENTER_PAGE_NUMBER', $current_page, $total_pages);
    }

    protected function make_pagination($base_url, $total_items, $per_page, $start)
    {
        $total_items = max(0, (int) $total_items);
        $per_page = max(1, (int) $per_page);
        $start = max(0, (int) $start);

        if ($total_items <= $per_page)
        {
            return '';
        }

        $total_pages = (int) ceil($total_items / $per_page);
        $current_page = (int) floor($start / $per_page) + 1;
        $links = [];

        if ($current_page > 1)
        {
            $links[] = '<a href="' . $this->append_start_to_url($base_url, max(0, $start - $per_page)) . '">&laquo; ' . $this->user->lang('DOWNLOADCENTER_PREVIOUS') . '</a>';
        }

        for ($page = 1; $page <= $total_pages; $page++)
        {
            if ($page !== 1 && $page !== $total_pages && abs($page - $current_page) > 2)
            {
                if ($page === 2 || $page === $total_pages - 1)
                {
                    $links[] = '<span>...</span>';
                }
                continue;
            }

            $page_start = ($page - 1) * $per_page;
            if ($page === $current_page)
            {
                $links[] = '<strong>' . $page . '</strong>';
            }
            else
            {
                $links[] = '<a href="' . $this->append_start_to_url($base_url, $page_start) . '">' . $page . '</a>';
            }
        }

        if ($current_page < $total_pages)
        {
            $links[] = '<a href="' . $this->append_start_to_url($base_url, $start + $per_page) . '">' . $this->user->lang('DOWNLOADCENTER_NEXT') . ' &raquo;</a>';
        }

        return implode(' ', $links);
    }

    protected function append_start_to_url($url, $start)
    {
        $url = preg_replace('/(&amp;|&)start=\d+/', '', $url);
        $separator = (strpos($url, '?') === false) ? '?' : '&amp;';
        return $url . $separator . 'start=' . max(0, (int) $start);
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


    protected function sanitize_db_text($text)
    {
        $text = (string) $text;

        // Many phpBB installations still use MySQL/MariaDB utf8 (3-byte) rather than utf8mb4.
        // Remove 4-byte Unicode characters (mostly emoji) before saving extension data to avoid SQL 1366 errors.
        if ($text !== '')
        {
            $clean = @preg_replace('/[\x{10000}-\x{10FFFF}\x{FE00}-\x{FE0F}\x{200D}]/u', '', $text);
            if ($clean !== null)
            {
                $text = $clean;
            }
        }

        return $text;
    }

    protected function rules_url()
    {
        $rules_url = isset($this->config['mundophpbb_downloadcenter_rules_url']) ? trim((string) $this->config['mundophpbb_downloadcenter_rules_url']) : '';

        if ($rules_url !== '')
        {
            return $rules_url;
        }

        return $this->helper->route('mundophpbb_downloadcenter_rules');
    }

    protected function requires_rules_acceptance()
    {
        return !isset($this->config['mundophpbb_downloadcenter_require_rules_accept']) || (bool) $this->config['mundophpbb_downloadcenter_require_rules_accept'];
    }


    protected function table($name)
    {
        global $table_prefix;
        return $table_prefix . $name;
    }
}

<?php

namespace mundophpbb\downloadcenter\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    /** @var string */
    protected $table_prefix;

    public function main($id, $mode)
    {
        global $auth, $config, $db, $request, $template, $user, $phpbb_container, $table_prefix, $phpbb_root_path, $phpEx;

        $user->add_lang_ext('mundophpbb/downloadcenter', 'acp');

        if (!$auth->acl_get('a_board'))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_NOT_AUTHORISED'));
        }

        $this->table_prefix = $table_prefix;
        $this->tpl_name = 'acp_downloadcenter';
        $this->page_title = $user->lang('ACP_DOWNLOADCENTER_TITLE');

        add_form_key('mundophpbb_downloadcenter_' . $mode);

        switch ($mode)
        {
            case 'dashboard':
                $this->handle_dashboard($db, $template, $user);
            break;

            case 'diagnostics':
                $this->handle_diagnostics($db, $template, $user, $config);
            break;

            case 'integrity':
                $this->handle_integrity($db, $template, $user, $config, $phpbb_root_path, $phpEx);
            break;

            case 'categories':
                $this->handle_categories($db, $request, $template, $user);
            break;

            case 'items':
                $this->handle_items($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx);
            break;

            case 'pending':
                $this->handle_pending($db, $request, $template, $user);
            break;

            case 'files':
                $this->handle_files($db, $request, $template, $user, $config);
            break;

            case 'logs':
                $this->handle_logs($db, $request, $template, $user);
            break;

            case 'settings':
            default:
                $this->handle_settings($config, $request, $template, $user);
            break;
        }

        $pending_total_global = $this->count_pending_items($db);

        $template->assign_vars([
            'U_ACTION' => $this->u_action,
            'S_MODE_DASHBOARD' => $mode === 'dashboard',
            'S_MODE_DIAGNOSTICS' => $mode === 'diagnostics',
            'S_MODE_INTEGRITY' => $mode === 'integrity',
            'S_MODE_SETTINGS' => $mode === 'settings',
            'S_MODE_CATEGORIES' => $mode === 'categories',
            'S_MODE_ITEMS' => $mode === 'items',
            'S_MODE_PENDING' => $mode === 'pending',
            'S_MODE_FILES' => $mode === 'files',
            'S_MODE_LOGS' => $mode === 'logs',
            'U_DASHBOARD' => $this->u_action_for_mode('dashboard'),
            'U_DIAGNOSTICS' => $this->u_action_for_mode('diagnostics'),
            'U_INTEGRITY' => $this->u_action_for_mode('integrity'),
            'U_SETTINGS' => $this->u_action_for_mode('settings'),
            'U_CATEGORIES' => $this->u_action_for_mode('categories'),
            'U_ITEMS' => $this->u_action_for_mode('items'),
            'U_PENDING' => $this->u_action_for_mode('pending'),
            'U_FILES' => $this->u_action_for_mode('files'),
            'U_LOGS' => $this->u_action_for_mode('logs'),
            'PENDING_TOTAL_GLOBAL' => $pending_total_global,
            'S_HAS_PENDING_GLOBAL' => $pending_total_global > 0,
            'DOWNLOADCENTER_EXTENSION_VERSION' => isset($config['mundophpbb_downloadcenter_version']) ? $config['mundophpbb_downloadcenter_version'] : '1.0.49',
            'DOWNLOADCENTER_BETA_STAGE' => $user->lang('ACP_DOWNLOADCENTER_BETA_STAGE'),
        ]);
    }

    protected function handle_dashboard($db, $template, $user)
    {
        $categories_table = $this->table_prefix . 'downloadcenter_categories';
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';

        $total_categories = $this->count_table_rows($db, $categories_table);
        $total_published_items = $this->count_table_rows($db, $items_table, 'item_enabled = 1 AND item_approved = 1');
        $total_pending_items = $this->count_table_rows($db, $items_table, 'item_approved = 0');
        $total_versions = $this->count_table_rows($db, $versions_table);
        $total_downloads = $this->count_table_rows($db, $downloads_table);

        $template->assign_vars([
            'DASHBOARD_TOTAL_CATEGORIES' => $total_categories,
            'DASHBOARD_TOTAL_PUBLISHED_ITEMS' => $total_published_items,
            'DASHBOARD_TOTAL_PENDING_ITEMS' => $total_pending_items,
            'DASHBOARD_TOTAL_VERSIONS' => $total_versions,
            'DASHBOARD_TOTAL_DOWNLOADS' => $total_downloads,
        ]);

        $sql = 'SELECT i.item_id, i.item_name, i.item_downloads, i.item_updated, c.category_name, u.username
            FROM ' . $items_table . ' i
            LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
            LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
            ORDER BY i.item_downloads DESC, i.item_updated DESC, i.item_name ASC';
        $result = $db->sql_query_limit($sql, 10);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('dashboard_top_items', [
                'ITEM_ID' => (int) $row['item_id'],
                'ITEM_NAME' => $row['item_name'],
                'CATEGORY_NAME' => $row['category_name'] ?: '-',
                'USERNAME' => $row['username'] ?: '-',
                'DOWNLOADS' => (int) $row['item_downloads'],
                'UPDATED' => $row['item_updated'] ? $user->format_date((int) $row['item_updated']) : '-',
                'U_EDIT' => $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
            ]);
        }
        $db->sql_freeresult($result);

        $sql = 'SELECT v.version_id, v.item_id, v.version_number, v.phpbb_version, v.version_downloads, v.version_created, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            ORDER BY v.version_downloads DESC, v.version_created DESC, v.version_id DESC';
        $result = $db->sql_query_limit($sql, 10);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('dashboard_top_versions', [
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
        $db->sql_freeresult($result);

        $sql = 'SELECT d.download_id, d.download_time, d.user_id, d.user_ip, i.item_name, v.version_number, u.username
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

    protected function count_table_rows($db, $table, $where = '')
    {
        $sql = 'SELECT COUNT(*) AS total FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
        $result = $db->sql_query($sql);
        $total = (int) $db->sql_fetchfield('total');
        $db->sql_freeresult($result);

        return $total;
    }


    protected function handle_diagnostics($db, $template, $user, $config)
    {
        $storage_dir = $this->local_storage_directory();
        $storage_exists = is_dir($storage_dir);
        $storage_writable = $storage_exists ? is_writable($storage_dir) : is_writable(dirname($storage_dir));
        $htaccess_path = $storage_dir . '.htaccess';
        $htaccess_ok = is_file($htaccess_path);
        $allowed_extensions = $this->get_allowed_extensions();
        $max_upload_bytes = $this->get_max_upload_bytes();
        $php_upload_max = $this->parse_size_to_bytes(ini_get('upload_max_filesize'));
        $php_post_max = $this->parse_size_to_bytes(ini_get('post_max_size'));
        $effective_php_limit = min($php_upload_max ?: $max_upload_bytes, $php_post_max ?: $max_upload_bytes);

        $items_table = $this->table_prefix . 'downloadcenter_items';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $categories_table = $this->table_prefix . 'downloadcenter_categories';

        $total_categories = $this->count_table_rows($db, $categories_table);
        $total_published = $this->count_table_rows($db, $items_table, 'item_enabled = 1 AND item_approved = 1');
        $total_pending = $this->count_table_rows($db, $items_table, 'item_approved = 0');

        $missing_files = 0;
        $sql = 'SELECT download_file FROM ' . $versions_table . " WHERE download_type = 'local' AND download_file <> ''";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            if (!is_file($this->local_file_path($row['download_file'])))
            {
                $missing_files++;
            }
        }
        $db->sql_freeresult($result);

        $library_files = $this->get_local_file_library($db);
        $orphan_files = 0;
        foreach ($library_files as $file)
        {
            if (empty($file['used']))
            {
                $orphan_files++;
            }
        }

        $this->assign_diagnostic_row($template, $storage_exists && $storage_writable, $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE'), $storage_exists ? ($storage_writable ? $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_OK', $storage_dir) : $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_NOT_WRITABLE', $storage_dir)) : $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_MISSING', $storage_dir));
        $this->assign_diagnostic_row($template, $htaccess_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS'), $htaccess_ok ? $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS_MISSING'));
        $this->assign_diagnostic_row($template, count($allowed_extensions) > 0, $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTENSIONS'), $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTENSIONS_INFO', implode(', ', $allowed_extensions)));
        $this->assign_diagnostic_row($template, $effective_php_limit >= $max_upload_bytes, $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT'), $effective_php_limit >= $max_upload_bytes ? $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT_OK', $this->format_file_size($max_upload_bytes)) : $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT_WARN', $this->format_file_size($max_upload_bytes), $this->format_file_size($effective_php_limit)));
        $this->assign_diagnostic_row($template, $missing_files === 0, $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES'), $missing_files === 0 ? $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES_WARN', $missing_files));
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_ORPHAN_FILES'), $user->lang('ACP_DOWNLOADCENTER_DIAG_ORPHAN_FILES_INFO', $orphan_files));
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_CONTENT'), $user->lang('ACP_DOWNLOADCENTER_DIAG_CONTENT_INFO', $total_categories, $total_published, $total_pending));

        $template->assign_vars([
            'DIAG_STORAGE_DIR' => $storage_dir,
            'DIAG_TOTAL_FILES' => count($library_files),
            'DIAG_ORPHAN_FILES' => $orphan_files,
            'DIAG_MISSING_FILES' => $missing_files,
            'DIAG_PHP_UPLOAD_MAX' => $this->format_file_size($php_upload_max),
            'DIAG_PHP_POST_MAX' => $this->format_file_size($php_post_max),
            'DIAG_EXTENSION_LIMIT' => $this->format_file_size($max_upload_bytes),
        ]);
    }


    protected function handle_integrity($db, $template, $user, $config, $phpbb_root_path, $phpEx)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $categories_table = $this->table_prefix . 'downloadcenter_categories';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';

        $issues_total = 0;
        $critical_total = 0;
        $warning_total = 0;
        $info_total = 0;

        $add_issue = function ($severity, $title, $details, $suggestion = '', $url = '') use ($template, &$issues_total, &$critical_total, &$warning_total, &$info_total) {
            $severity = in_array($severity, ['critical', 'warning', 'info'], true) ? $severity : 'info';
            $issues_total++;
            if ($severity === 'critical')
            {
                $critical_total++;
            }
            else if ($severity === 'warning')
            {
                $warning_total++;
            }
            else
            {
                $info_total++;
            }

            $template->assign_block_vars('integrity_issues', [
                'SEVERITY' => $severity,
                'SEVERITY_LABEL' => strtoupper($severity),
                'TITLE' => $title,
                'DETAILS' => $details,
                'SUGGESTION' => $suggestion,
                'U_ACTION' => $url,
                'S_HAS_ACTION' => $url !== '',
            ]);
        };

        // 1. Published/enabled items without an enabled version.
        $sql = 'SELECT i.item_id, i.item_name
            FROM ' . $items_table . ' i
            LEFT JOIN ' . $versions_table . ' v ON v.item_id = i.item_id AND v.version_enabled = 1
            WHERE i.item_enabled = 1
                AND i.item_approved = 1
            GROUP BY i.item_id, i.item_name
            HAVING COUNT(v.version_id) = 0
            ORDER BY i.item_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('critical', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_APPROVED_WITHOUT_VERSION'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ITEM_REF', (int) $row['item_id'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ADD_VERSION'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 2. Versions with empty version number.
        $sql = 'SELECT v.version_id, v.item_id, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE v.version_number = \'\'
            ORDER BY v.version_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_EMPTY_VERSION'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_VERSION_REF', (int) $row['version_id'], (int) $row['item_id'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_VERSION_NUMBER'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 3. Versions without downloadable target.
        $sql = 'SELECT v.version_id, v.item_id, v.download_type, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE (v.download_type = \'local\' AND v.download_file = \'\')
                OR (v.download_type = \'external\' AND v.download_url = \'\')
            ORDER BY v.version_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('critical', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_VERSION_WITHOUT_DOWNLOAD'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_VERSION_REF', (int) $row['version_id'], (int) $row['item_id'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_DOWNLOAD_TARGET'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 4. Local versions with missing physical file.
        $sql = 'SELECT v.version_id, v.item_id, v.download_file, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE v.download_type = \'local\'
                AND v.download_file <> \'\'
            ORDER BY v.version_id ASC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            if (!is_file($this->local_file_path($row['download_file'])))
            {
                $add_issue('critical', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MISSING_LOCAL_FILE'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MISSING_LOCAL_FILE_DETAILS', (int) $row['version_id'], (string) $row['download_file'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_REUPLOAD_FILE'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
            }
        }
        $db->sql_freeresult($result);

        // 5. Items with invalid category.
        $sql = 'SELECT i.item_id, i.item_name, i.category_id
            FROM ' . $items_table . ' i
            LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
            WHERE i.category_id = 0 OR c.category_id IS NULL
            ORDER BY i.item_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_CATEGORY'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_CATEGORY_DETAILS', (int) $row['item_id'], (string) $row['item_name'], (int) $row['category_id']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_CATEGORY'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 6. Items with broken support topic link.
        $sql = 'SELECT i.item_id, i.item_name, i.topic_id
            FROM ' . $items_table . ' i
            LEFT JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = i.topic_id
            WHERE i.topic_id > 0
                AND t.topic_id IS NULL
            ORDER BY i.item_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_BROKEN_TOPIC'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_BROKEN_TOPIC_DETAILS', (int) $row['item_id'], (string) $row['item_name'], (int) $row['topic_id']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_TOPIC'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 7. Screenshots whose item was removed.
        $sql = 'SELECT s.screenshot_id, s.item_id, s.image_file
            FROM ' . $screenshots_table . ' s
            LEFT JOIN ' . $items_table . ' i ON i.item_id = s.item_id
            WHERE i.item_id IS NULL
            ORDER BY s.screenshot_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_SCREENSHOT'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_SCREENSHOT_DETAILS', (int) $row['screenshot_id'], (int) $row['item_id'], (string) $row['image_file']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_SCREENSHOT'));
        }
        $db->sql_freeresult($result);

        // 8. Screenshot records with missing files.
        $sql = 'SELECT s.screenshot_id, s.item_id, s.image_file, i.item_name
            FROM ' . $screenshots_table . ' s
            LEFT JOIN ' . $items_table . ' i ON i.item_id = s.item_id
            WHERE s.image_file <> \'\'
            ORDER BY s.screenshot_id ASC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            if (!is_file($this->screenshot_file_path($row['image_file'])))
            {
                $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MISSING_SCREENSHOT_FILE'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MISSING_SCREENSHOT_FILE_DETAILS', (int) $row['screenshot_id'], (string) $row['image_file'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_SCREENSHOT_FILE'), $row['item_id'] ? $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'] : '');
            }
        }
        $db->sql_freeresult($result);

        // 9. Download records pointing to missing item/version.
        $sql = 'SELECT d.download_id, d.item_id, d.version_id
            FROM ' . $downloads_table . ' d
            LEFT JOIN ' . $items_table . ' i ON i.item_id = d.item_id
            LEFT JOIN ' . $versions_table . ' v ON v.version_id = d.version_id
            WHERE i.item_id IS NULL OR v.version_id IS NULL
            ORDER BY d.download_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('info', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_DOWNLOAD'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_DOWNLOAD_DETAILS', (int) $row['download_id'], (int) $row['item_id'], (int) $row['version_id']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_DOWNLOAD'));
        }
        $db->sql_freeresult($result);

        // 10. Physical files not linked to any version.
        $orphan_files = 0;
        foreach ($this->get_local_file_library($db) as $file)
        {
            if (empty($file['used']))
            {
                $orphan_files++;
                if ($orphan_files <= 50)
                {
                    $add_issue('info', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_FILE'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_FILE_DETAILS', (string) $file['filename'], (string) $file['size'], (string) $file['modified']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_FILE'), $this->u_action_for_mode('files'));
                }
            }
        }

        $template->assign_vars([
            'INTEGRITY_TOTAL_ISSUES' => $issues_total,
            'INTEGRITY_CRITICAL_TOTAL' => $critical_total,
            'INTEGRITY_WARNING_TOTAL' => $warning_total,
            'INTEGRITY_INFO_TOTAL' => $info_total,
            'S_INTEGRITY_HAS_ISSUES' => $issues_total > 0,
        ]);
    }

    protected function assign_diagnostic_row($template, $ok, $title, $message)
    {
        $template->assign_block_vars('diagnostics', [
            'STATUS' => $ok ? 'OK' : 'ATENCAO',
            'STATUS_CLASS' => $ok ? 'ok' : 'warn',
            'TITLE' => $title,
            'MESSAGE' => $message,
        ]);
    }

    protected function handle_settings($config, $request, $template, $user)
    {
        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_settings'))
            {
                trigger_error('FORM_INVALID');
            }

            $config->set('mundophpbb_downloadcenter_enabled', $request->variable('downloadcenter_enabled', 0));
            $config->set('mundophpbb_downloadcenter_min_posts', max(0, $request->variable('downloadcenter_min_posts', 0)));
            $config->set('mundophpbb_downloadcenter_allow_submissions', $request->variable('downloadcenter_allow_submissions', 1));
            $config->set('mundophpbb_downloadcenter_require_rules_accept', $request->variable('downloadcenter_require_rules_accept', 1));
            $config->set('mundophpbb_downloadcenter_rules_url', $this->sanitize_db_text(trim($request->variable('downloadcenter_rules_url', '', true))));
            $config->set('mundophpbb_downloadcenter_view_access', $this->valid_access_mode($request->variable('downloadcenter_view_access', 'all'), 'all'));
            $config->set('mundophpbb_downloadcenter_download_access', $this->valid_access_mode($request->variable('downloadcenter_download_access', 'registered'), 'registered'));
            $config->set('mundophpbb_downloadcenter_submit_access', $this->valid_access_mode($request->variable('downloadcenter_submit_access', 'registered'), 'registered'));
            $config->set('mundophpbb_downloadcenter_duplicate_window', max(0, $request->variable('downloadcenter_duplicate_window', 3600)));
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
            $config->set('mundophpbb_downloadcenter_allowed_extensions', $this->normalise_allowed_extensions($request->variable('downloadcenter_allowed_extensions', '', true)));
            $config->set('mundophpbb_downloadcenter_max_upload_mb', max(1, $request->variable('downloadcenter_max_upload_mb', 20)));

            global $db;
            $this->add_log($db, $user, 'settings_saved', $user->lang('ACP_DOWNLOADCENTER_LOG_SETTINGS_SAVED'));

            trigger_error($user->lang('ACP_DOWNLOADCENTER_SAVED') . adm_back_link($this->u_action));
        }

        $selected_support_forum_id = isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0;
        $this->assign_support_forum_options($template, $selected_support_forum_id);

        $template->assign_vars([
            'DOWNLOADCENTER_ENABLED'   => (bool) $config['mundophpbb_downloadcenter_enabled'],
            'DOWNLOADCENTER_MIN_POSTS' => (int) $config['mundophpbb_downloadcenter_min_posts'],
            'DOWNLOADCENTER_ALLOW_SUBMISSIONS' => isset($config['mundophpbb_downloadcenter_allow_submissions']) ? (bool) $config['mundophpbb_downloadcenter_allow_submissions'] : true,
            'DOWNLOADCENTER_REQUIRE_RULES_ACCEPT' => !isset($config['mundophpbb_downloadcenter_require_rules_accept']) || (bool) $config['mundophpbb_downloadcenter_require_rules_accept'],
            'DOWNLOADCENTER_RULES_URL' => isset($config['mundophpbb_downloadcenter_rules_url']) ? $config['mundophpbb_downloadcenter_rules_url'] : '',
            'DOWNLOADCENTER_VIEW_ACCESS' => isset($config['mundophpbb_downloadcenter_view_access']) ? $config['mundophpbb_downloadcenter_view_access'] : 'all',
            'DOWNLOADCENTER_DOWNLOAD_ACCESS' => isset($config['mundophpbb_downloadcenter_download_access']) ? $config['mundophpbb_downloadcenter_download_access'] : 'registered',
            'DOWNLOADCENTER_SUBMIT_ACCESS' => isset($config['mundophpbb_downloadcenter_submit_access']) ? $config['mundophpbb_downloadcenter_submit_access'] : 'registered',
            'S_VIEW_ACCESS_ALL' => (!isset($config['mundophpbb_downloadcenter_view_access']) || $config['mundophpbb_downloadcenter_view_access'] === 'all'),
            'S_VIEW_ACCESS_REGISTERED' => (isset($config['mundophpbb_downloadcenter_view_access']) && $config['mundophpbb_downloadcenter_view_access'] === 'registered'),
            'S_VIEW_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_view_access']) && $config['mundophpbb_downloadcenter_view_access'] === 'admin'),
            'S_DOWNLOAD_ACCESS_ALL' => (isset($config['mundophpbb_downloadcenter_download_access']) && $config['mundophpbb_downloadcenter_download_access'] === 'all'),
            'S_DOWNLOAD_ACCESS_REGISTERED' => (!isset($config['mundophpbb_downloadcenter_download_access']) || $config['mundophpbb_downloadcenter_download_access'] === 'registered'),
            'S_DOWNLOAD_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_download_access']) && $config['mundophpbb_downloadcenter_download_access'] === 'admin'),
            'S_SUBMIT_ACCESS_REGISTERED' => (!isset($config['mundophpbb_downloadcenter_submit_access']) || $config['mundophpbb_downloadcenter_submit_access'] === 'registered'),
            'S_SUBMIT_ACCESS_ADMIN' => (isset($config['mundophpbb_downloadcenter_submit_access']) && $config['mundophpbb_downloadcenter_submit_access'] === 'admin'),
            'DOWNLOADCENTER_DUPLICATE_WINDOW' => isset($config['mundophpbb_downloadcenter_duplicate_window']) ? (int) $config['mundophpbb_downloadcenter_duplicate_window'] : 3600,
            'DOWNLOADCENTER_SUPPORT_FORUM_ID' => isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0,
            'DOWNLOADCENTER_NOTIFICATIONS_ENABLED' => !isset($config['mundophpbb_downloadcenter_notifications_enabled']) || (bool) $config['mundophpbb_downloadcenter_notifications_enabled'],
            'DOWNLOADCENTER_PUBLIC_PER_PAGE' => isset($config['mundophpbb_downloadcenter_public_per_page']) ? (int) $config['mundophpbb_downloadcenter_public_per_page'] : 12,
            'DOWNLOADCENTER_ACP_PER_PAGE' => isset($config['mundophpbb_downloadcenter_acp_per_page']) ? (int) $config['mundophpbb_downloadcenter_acp_per_page'] : 20,
            'DOWNLOADCENTER_LOGS_PER_PAGE' => isset($config['mundophpbb_downloadcenter_logs_per_page']) ? (int) $config['mundophpbb_downloadcenter_logs_per_page'] : 50,
            'DOWNLOADCENTER_ALLOWED_EXTENSIONS' => $this->get_allowed_extensions_string(),
            'DOWNLOADCENTER_MAX_UPLOAD_MB' => $this->get_max_upload_mb(),
            'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text($user),
        ]);
    }

    protected function valid_access_mode($mode, $default)
    {
        $allowed = ['all', 'registered', 'admin'];

        return in_array($mode, $allowed, true) ? $mode : $default;
    }

    protected function handle_categories($db, $request, $template, $user)
    {
        $table = $this->table_prefix . 'downloadcenter_categories';
        $action = $request->variable('action', '');
        $category_id = $request->variable('category_id', 0);

        if ($request->is_set_post('submit_category'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_categories'))
            {
                trigger_error('FORM_INVALID');
            }

            $name = $this->sanitize_db_text(trim($request->variable('category_name', '', true)));
            $desc = $this->sanitize_db_text(trim($request->variable('category_desc', '', true)));
            $order = max(0, $request->variable('category_order', 0));
            $enabled = $request->variable('category_enabled', 0);

            if ($name === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_NAME_REQUIRED') . adm_back_link($this->u_action));
            }

            $data = [
                'category_name' => $name,
                'category_desc' => $desc,
                'category_slug' => $this->slugify($name),
                'category_order' => $order,
                'category_enabled' => $enabled,
            ];

            if ($category_id > 0)
            {
                $sql = 'UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', $data) . ' WHERE category_id = ' . (int) $category_id;
                $db->sql_query($sql);
                $this->add_log($db, $user, 'category_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_UPDATED', $name));
            }
            else
            {
                $sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $data);
                $db->sql_query($sql);
                $this->add_log($db, $user, 'category_created', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_CREATED', $name));
            }

            trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_SAVED') . adm_back_link($this->u_action));
        }

        if ($action === 'delete' && $category_id > 0)
        {
            $linked_items = $this->count_items_in_category($db, $category_id);
            if ($linked_items > 0)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_NOT_EMPTY', $linked_items) . adm_back_link($this->u_action));
            }

            if (confirm_box(true))
            {
                $sql = 'SELECT category_name FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
                $result = $db->sql_query($sql);
                $deleted_category = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                $sql = 'DELETE FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
                $db->sql_query($sql);
                $this->add_log($db, $user, 'category_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_DELETED', $deleted_category ? $deleted_category['category_name'] : (string) $category_id));
                trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_DELETED') . adm_back_link($this->u_action));
            }
            else
            {
                confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_CATEGORY'), build_hidden_fields([
                    'action' => 'delete',
                    'category_id' => $category_id,
                ]));
            }
        }

        $edit_category = [
            'category_id' => 0,
            'category_name' => '',
            'category_desc' => '',
            'category_order' => 0,
            'category_enabled' => 1,
        ];

        if ($action === 'edit' && $category_id > 0)
        {
            $sql = 'SELECT * FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if ($row)
            {
                $edit_category = $row;
            }
        }

        $category_item_counts = $this->get_category_item_counts($db);

        $sql = 'SELECT * FROM ' . $table . ' ORDER BY category_order ASC, category_name ASC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $item_count = isset($category_item_counts[(int) $row['category_id']]) ? (int) $category_item_counts[(int) $row['category_id']] : 0;
            $template->assign_block_vars('categories', [
                'CATEGORY_ID' => (int) $row['category_id'],
                'CATEGORY_NAME' => $row['category_name'],
                'CATEGORY_DESC' => $row['category_desc'],
                'CATEGORY_ORDER' => (int) $row['category_order'],
                'CATEGORY_ENABLED' => (bool) $row['category_enabled'],
                'CATEGORY_ITEM_COUNT' => $item_count,
                'S_HAS_ITEMS' => $item_count > 0,
                'U_EDIT' => $this->u_action . '&amp;action=edit&amp;category_id=' . (int) $row['category_id'],
                'U_DELETE' => $this->u_action . '&amp;action=delete&amp;category_id=' . (int) $row['category_id'],
            ]);
        }
        $db->sql_freeresult($result);

        $template->assign_vars([
            'EDIT_CATEGORY_ID' => (int) $edit_category['category_id'],
            'EDIT_CATEGORY_NAME' => $edit_category['category_name'],
            'EDIT_CATEGORY_DESC' => $edit_category['category_desc'],
            'EDIT_CATEGORY_ORDER' => (int) $edit_category['category_order'],
            'EDIT_CATEGORY_ENABLED' => (bool) $edit_category['category_enabled'],
        ]);
    }


    protected function get_category_item_counts($db)
    {
        $counts = [];
        $sql = 'SELECT category_id, COUNT(item_id) AS item_count
            FROM ' . $this->table_prefix . 'downloadcenter_items
            GROUP BY category_id';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $counts[(int) $row['category_id']] = (int) $row['item_count'];
        }
        $db->sql_freeresult($result);

        return $counts;
    }

    protected function count_items_in_category($db, $category_id)
    {
        $sql = 'SELECT COUNT(item_id) AS item_count
            FROM ' . $this->table_prefix . 'downloadcenter_items
            WHERE category_id = ' . (int) $category_id;
        $result = $db->sql_query($sql);
        $count = (int) $db->sql_fetchfield('item_count');
        $db->sql_freeresult($result);

        return $count;
    }

    protected function handle_items($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $categories_table = $this->table_prefix . 'downloadcenter_categories';
        $screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';
        $action = $request->variable('action', '');
        $item_id = $request->variable('item_id', 0);
        $version_id = $request->variable('version_id', 0);
        $time = time();

        if ($action === 'delete_version' && $version_id > 0)
        {
            if (confirm_box(true))
            {
                $sql = 'SELECT * FROM ' . $versions_table . ' WHERE version_id = ' . (int) $version_id;
                $result = $db->sql_query($sql);
                $version_row = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                if ($version_row)
                {
                    $item_id = (int) $version_row['item_id'];
                    $this->delete_local_file_for_version($version_row);
                    $sql = 'DELETE FROM ' . $downloads_table . ' WHERE version_id = ' . (int) $version_id;
                    $db->sql_query($sql);
                    $sql = 'DELETE FROM ' . $versions_table . ' WHERE version_id = ' . (int) $version_id;
                    $db->sql_query($sql);
                    $this->sync_item_download_count($db, $item_id);
                    $this->add_log($db, $user, 'version_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_DELETED', (string) $version_id), $item_id, $version_id);
                }

                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_DELETED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }
            else
            {
                confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_VERSION'), build_hidden_fields([
                    'action' => 'delete_version',
                    'version_id' => $version_id,
                    'item_id' => $item_id,
                ]));
            }
        }

        if ($action === 'delete_file' && $version_id > 0)
        {
            if (confirm_box(true))
            {
                $sql = 'SELECT * FROM ' . $versions_table . ' WHERE version_id = ' . (int) $version_id;
                $result = $db->sql_query($sql);
                $version_row = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                if ($version_row)
                {
                    $item_id = (int) $version_row['item_id'];
                    $this->delete_local_file_for_version($version_row);
                    $sql = 'UPDATE ' . $versions_table . "
                        SET download_file = '', file_size = ''
                        WHERE version_id = " . (int) $version_id;
                    $db->sql_query($sql);
                    $this->add_log($db, $user, 'version_file_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_FILE_DELETED', (string) $version_id), $item_id, $version_id);
                }

                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_FILE_DELETED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }
            else
            {
                confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_VERSION_FILE'), build_hidden_fields([
                    'action' => 'delete_file',
                    'version_id' => $version_id,
                    'item_id' => $item_id,
                ]));
            }
        }


        if ($action === 'delete_screenshot')
        {
            $screenshot_id = $request->variable('screenshot_id', 0);
            if ($screenshot_id > 0)
            {
                if (confirm_box(true))
                {
                    $sql = 'SELECT * FROM ' . $screenshots_table . ' WHERE screenshot_id = ' . (int) $screenshot_id;
                    $result = $db->sql_query($sql);
                    $screenshot = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);

                    if ($screenshot)
                    {
                        $item_id = (int) $screenshot['item_id'];
                        $this->delete_screenshot_file($screenshot['image_file']);
                        $sql = 'DELETE FROM ' . $screenshots_table . ' WHERE screenshot_id = ' . (int) $screenshot_id;
                        $db->sql_query($sql);
                        $this->add_log($db, $user, 'screenshot_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_SCREENSHOT_DELETED', (string) $screenshot_id), $item_id);
                    }

                    $this->redirect_to_acp_anchor($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '&amp;screenshot_status=deleted#downloadcenter-screenshots');
                }
                else
                {
                    confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_SCREENSHOT'), build_hidden_fields([
                        'action' => 'delete_screenshot',
                        'screenshot_id' => $screenshot_id,
                        'item_id' => $item_id,
                    ]));
                }
            }
        }


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



        if ($request->is_set_post('upload_item_image') && $item_id > 0)
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            $uploaded_item_image = $this->handle_item_image_upload($request, $user);
            if (!$uploaded_item_image)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_UPLOAD_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '#downloadcenter-item-image'));
            }

            $icon_value = 'item_image:' . $uploaded_item_image['file_name'];
            $sql = 'UPDATE ' . $items_table . "
                SET item_icon = '" . $db->sql_escape($icon_value) . "', item_updated = " . (int) $time . '
                WHERE item_id = ' . (int) $item_id;
            $db->sql_query($sql);
            $this->add_log($db, $user, 'item_image_uploaded', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_IMAGE_UPLOADED', (string) $item_id), $item_id);

            $this->redirect_to_acp_anchor($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '&amp;item_image_status=uploaded#downloadcenter-item-image');
        }

        if ($request->is_set_post('clear_item_image') && $item_id > 0)
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            $sql = 'UPDATE ' . $items_table . "
                SET item_icon = '', item_updated = " . (int) $time . '
                WHERE item_id = ' . (int) $item_id;
            $db->sql_query($sql);
            $this->add_log($db, $user, 'item_image_cleared', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_IMAGE_CLEARED', (string) $item_id), $item_id);

            $this->redirect_to_acp_anchor($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '&amp;item_image_status=cleared#downloadcenter-item-image');
        }

        if ($request->is_set_post('add_screenshot') && $item_id > 0)
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            $uploaded_screenshot = $this->handle_screenshot_upload($request, $user);
            if (!$uploaded_screenshot)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_UPLOAD_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '#downloadcenter-screenshots'));
            }

            $screenshot_data = [
                'item_id' => $item_id,
                'image_file' => $uploaded_screenshot['file_name'],
                'image_caption' => $this->sanitize_db_text(trim($request->variable('screenshot_caption', '', true))),
                'image_order' => max(0, $request->variable('screenshot_order', 0)),
                'image_created' => $time,
            ];
            $sql = 'INSERT INTO ' . $screenshots_table . ' ' . $db->sql_build_array('INSERT', $screenshot_data);
            $db->sql_query($sql);
            $this->add_log($db, $user, 'screenshot_created', $user->lang('ACP_DOWNLOADCENTER_LOG_SCREENSHOT_CREATED', (string) $item_id), $item_id);

            $this->redirect_to_acp_anchor($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '&amp;screenshot_status=added#downloadcenter-screenshots');
        }

        if ($request->is_set_post('submit_item'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            $name = $this->sanitize_db_text(trim($request->variable('item_name', '', true)));
            if ($name === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_NAME_REQUIRED') . adm_back_link($this->u_action));
            }

            $item_icon = $this->build_item_icon_value($db, $request, $user, $item_id, $screenshots_table);

            $data = [
                'category_id' => max(0, $request->variable('category_id', 0)),
                'user_id' => (int) $user->data['user_id'],
                'topic_id' => max(0, $request->variable('topic_id', 0)),
                'item_name' => $name,
                'item_slug' => $this->slugify($name),
                'item_short_desc' => $this->sanitize_db_text(trim($request->variable('item_short_desc', '', true))),
                'item_desc' => $this->sanitize_db_text(trim($request->variable('item_desc', '', true))),
                'item_icon' => $item_icon,
                'item_enabled' => $request->variable('item_enabled', 0),
                'item_approved' => $request->variable('item_approved', 0),
                'item_updated' => $time,
            ];

            $is_new_item = ($item_id <= 0);

            if ($item_id > 0)
            {
                $sql = 'UPDATE ' . $items_table . ' SET ' . $db->sql_build_array('UPDATE', $data) . ' WHERE item_id = ' . (int) $item_id;
                $db->sql_query($sql);
                $this->add_log($db, $user, 'item_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_UPDATED', $name), $item_id);
            }
            else
            {
                $data['item_created'] = $time;
                $sql = 'INSERT INTO ' . $items_table . ' ' . $db->sql_build_array('INSERT', $data);
                $db->sql_query($sql);
                $item_id = (int) $db->sql_nextid();
                $this->add_log($db, $user, 'item_created', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_CREATED', $name), $item_id);
            }

            $add_version = (bool) $request->variable('add_version', 0);

            if (!$is_new_item && $item_id > 0 && $request->is_set_post('latest_version_changelog'))
            {
                $latest_version_id = $request->variable('latest_version_id', 0);
                $latest_changelog = $this->sanitize_db_text(trim($request->variable('latest_version_changelog', '', true)));

                if ($latest_version_id > 0)
                {
                    $sql = 'SELECT version_id FROM ' . $versions_table . '
                        WHERE version_id = ' . (int) $latest_version_id . '
                            AND item_id = ' . (int) $item_id;
                    $result = $db->sql_query_limit($sql, 1);
                    $latest_row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);

                    if ($latest_row)
                    {
                        $sql = 'UPDATE ' . $versions_table . "
                            SET version_changelog = '" . $db->sql_escape($latest_changelog) . "'
                            WHERE version_id = " . (int) $latest_version_id;
                        $db->sql_query($sql);
                        $this->add_log($db, $user, 'version_changelog_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_CHANGELOG_UPDATED', (string) $latest_version_id), $item_id, $latest_version_id);
                    }
                }
            }

            if ($add_version)
            {
                $version_number = $this->sanitize_db_text(trim($request->variable('version_number', '', true)));
                $download_type = $request->variable('download_type', 'external');
                $download_url = $this->sanitize_db_text(trim($request->variable('download_url', '', true)));
                $download_file = trim($request->variable('download_existing_file', '', true));
                if ($download_file === '')
                {
                    // Backward-compatible fallback for older templates or manual technical usage.
                    $download_file = trim($request->variable('download_file', '', true));
                }
                $download_file = basename($download_file);
                $file_size = '';
                $uploaded_file = $this->handle_local_upload($request, $user, $phpbb_root_path);

                if ($uploaded_file)
                {
                    $download_type = 'local';
                    $download_file = $uploaded_file['file_name'];
                    $file_size = $uploaded_file['file_size'];
                }
                else if ($download_type === 'local' && $download_file !== '')
                {
                    $existing_path = $this->local_file_path($download_file);
                    if (!is_file($existing_path))
                    {
                        trigger_error($user->lang('ACP_DOWNLOADCENTER_EXISTING_FILE_NOT_FOUND') . adm_back_link($this->u_action));
                    }
                    if (!$this->is_allowed_existing_file($download_file))
                    {
                        trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', $this->get_allowed_extensions_string()) . adm_back_link($this->u_action));
                    }
                    $file_size = $this->format_file_size(filesize($existing_path));
                }

                if ($version_number === '')
                {
                    trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action));
                }

                if ($download_type === 'external' && $download_url === '')
                {
                    trigger_error($user->lang('ACP_DOWNLOADCENTER_EXTERNAL_URL_REQUIRED') . adm_back_link($this->u_action));
                }

                if ($download_type === 'local' && $download_file === '')
                {
                    trigger_error($user->lang('ACP_DOWNLOADCENTER_LOCAL_FILE_REQUIRED') . adm_back_link($this->u_action));
                }

                $version_data = [
                    'item_id' => $item_id,
                    'version_number' => $version_number,
                    'phpbb_version' => $this->sanitize_db_text(trim($request->variable('phpbb_version', '', true))),
                    'php_version' => $this->sanitize_db_text(trim($request->variable('php_version', '', true))),
                    'version_changelog' => $this->sanitize_db_text(trim($request->variable('version_changelog', '', true))),
                    'download_type' => $download_type,
                    'download_url' => $download_url,
                    'download_file' => $download_file,
                    'file_size' => $file_size,
                    'version_enabled' => 1,
                    'version_created' => $time,
                ];

                $sql = 'INSERT INTO ' . $versions_table . ' ' . $db->sql_build_array('INSERT', $version_data);
                $db->sql_query($sql);
                $new_version_id = (int) $db->sql_nextid();
                $this->add_log($db, $user, 'version_created', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_CREATED', $version_number), $item_id, $new_version_id);
            }

            $support_topic_id = $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
            if ($support_topic_id > 0)
            {
                $data['topic_id'] = $support_topic_id;
            }

            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_SAVED') . adm_back_link($this->u_action));
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

        $edit_item = [
            'item_id' => 0,
            'category_id' => 0,
            'topic_id' => 0,
            'item_name' => '',
            'item_short_desc' => '',
            'item_desc' => '',
            'item_icon' => '',
            'item_enabled' => 1,
            'item_approved' => 1,
        ];

        if ($action === 'edit' && $item_id > 0)
        {
            $sql = 'SELECT * FROM ' . $items_table . ' WHERE item_id = ' . (int) $item_id;
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if ($row)
            {
                $edit_item = $row;
            }
        }

        $edit_latest_version = false;
        if ((int) $edit_item['item_id'] > 0)
        {
            $sql = 'SELECT * FROM ' . $versions_table . ' WHERE item_id = ' . (int) $edit_item['item_id'] . ' ORDER BY version_created DESC, version_id DESC';
            $result = $db->sql_query_limit($sql, 1);
            $edit_latest_version = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
        }

        if ((int) $edit_item['item_id'] > 0)
        {
            $sql = 'SELECT * FROM ' . $versions_table . ' WHERE item_id = ' . (int) $edit_item['item_id'] . ' ORDER BY version_created DESC, version_id DESC';
            $result = $db->sql_query($sql);
            while ($version_row = $db->sql_fetchrow($result))
            {
                $template->assign_block_vars('version_history', [
                    'VERSION_ID' => (int) $version_row['version_id'],
                    'VERSION_NUMBER' => $this->clean_version_label($version_row['version_number']),
                    'PHPBB_VERSION' => $version_row['phpbb_version'],
                    'PHP_VERSION' => $version_row['php_version'],
                    'DOWNLOAD_TYPE' => $version_row['download_type'],
                    'DOWNLOAD_TARGET' => ($version_row['download_type'] === 'external') ? $version_row['download_url'] : $version_row['download_file'],
                    'FILE_SIZE' => $version_row['file_size'],
                    'DOWNLOADS' => (int) $version_row['version_downloads'],
                    'CREATED' => $user->format_date((int) $version_row['version_created']),
                    'CHANGELOG' => $version_row['version_changelog'],
                    'S_LOCAL_FILE' => $version_row['download_type'] === 'local',
                    'S_FILE_EXISTS' => $this->version_file_exists($version_row),
                    'S_FILE_MISSING' => ($version_row['download_type'] === 'local' && !$this->version_file_exists($version_row)),
                    'U_DELETE_FILE' => $this->u_action . '&amp;action=delete_file&amp;item_id=' . (int) $edit_item['item_id'] . '&amp;version_id=' . (int) $version_row['version_id'],
                    'U_DELETE' => $this->u_action . '&amp;action=delete_version&amp;item_id=' . (int) $edit_item['item_id'] . '&amp;version_id=' . (int) $version_row['version_id'],
                ]);
            }
            $db->sql_freeresult($result);
        }

        if ((int) $edit_item['item_id'] > 0)
        {
            $sql = 'SELECT * FROM ' . $screenshots_table . '
                WHERE item_id = ' . (int) $edit_item['item_id'] . '
                ORDER BY image_order ASC, screenshot_id ASC';
            $result = $db->sql_query($sql);
            while ($screenshot = $db->sql_fetchrow($result))
            {
                $template->assign_block_vars('screenshots', [
                    'SCREENSHOT_ID' => (int) $screenshot['screenshot_id'],
                    'CAPTION' => $screenshot['image_caption'],
                    'ORDER' => (int) $screenshot['image_order'],
                    'FILENAME' => $screenshot['image_file'],
                    'CREATED' => $user->format_date((int) $screenshot['image_created']),
                    'U_IMAGE' => append_sid('../app.' . $phpEx . '/downloadcenter/screenshot/' . (int) $screenshot['screenshot_id']),
                    'U_DELETE' => $this->u_action . '&amp;action=delete_screenshot&amp;item_id=' . (int) $edit_item['item_id'] . '&amp;screenshot_id=' . (int) $screenshot['screenshot_id'],
                ]);
                $template->assign_block_vars('item_icon_screenshots', [
                    'SCREENSHOT_ID' => (int) $screenshot['screenshot_id'],
                    'CAPTION' => $screenshot['image_caption'],
                    'U_IMAGE' => append_sid('../app.' . $phpEx . '/downloadcenter/screenshot/' . (int) $screenshot['screenshot_id']),
                    'S_SELECTED' => ((string) $edit_item['item_icon'] === 'screenshot:' . (int) $screenshot['screenshot_id']),
                ]);
            }
            $db->sql_freeresult($result);
        }


        $sql = 'SELECT * FROM ' . $categories_table . ' ORDER BY category_order ASC, category_name ASC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('category_options', [
                'VALUE' => (int) $row['category_id'],
                'LABEL' => $row['category_name'],
                'S_SELECTED' => (int) $row['category_id'] === (int) $edit_item['category_id'],
            ]);
        }
        $db->sql_freeresult($result);

        $start = max(0, $request->variable('start', 0));
        $per_page = isset($config['mundophpbb_downloadcenter_acp_per_page']) ? max(1, (int) $config['mundophpbb_downloadcenter_acp_per_page']) : 20;
        $total_items = $this->count_table_rows($db, $items_table);
        $start = $this->normalize_start($start, $per_page, $total_items);

        $sql = 'SELECT i.*, c.category_name, u.username,
                    (SELECT v.version_number FROM ' . $versions_table . ' v WHERE v.item_id = i.item_id ORDER BY v.version_created DESC, v.version_id DESC LIMIT 1) AS latest_version
                FROM ' . $items_table . ' i
                LEFT JOIN ' . $categories_table . ' c ON c.category_id = i.category_id
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = i.user_id
                ORDER BY i.item_updated DESC, i.item_name ASC';
        $result = $db->sql_query_limit($sql, $per_page, $start);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('items', [
                'ITEM_ID' => (int) $row['item_id'],
                'ITEM_NAME' => $row['item_name'],
                'CATEGORY_NAME' => $row['category_name'] ?: '-',
                'USERNAME' => $row['username'] ?: '-',
                'LATEST_VERSION' => $row['latest_version'] ?: '-',
                'ITEM_ENABLED' => (bool) $row['item_enabled'],
                'ITEM_APPROVED' => (bool) $row['item_approved'],
                'ITEM_DOWNLOADS' => (int) $row['item_downloads'],
                'TOPIC_ID' => (int) $row['topic_id'],
                'U_EDIT' => $this->u_action . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
                'U_DELETE' => $this->u_action . '&amp;action=delete&amp;item_id=' . (int) $row['item_id'],
                'U_APPROVE' => $this->u_action . '&amp;action=approve&amp;item_id=' . (int) $row['item_id'],
                'U_UNAPPROVE' => $this->u_action . '&amp;action=unapprove&amp;item_id=' . (int) $row['item_id'],
            ]);
        }
        $db->sql_freeresult($result);

        $this->assign_file_library_options($db, $template, $edit_latest_version ? (string) $edit_latest_version['download_file'] : '');
        $this->assign_item_image_options($template, $edit_item['item_icon']);

        $screenshot_status = $request->variable('screenshot_status', '');

        $template->assign_vars([
            'SCREENSHOT_STATUS' => $screenshot_status,
            'S_SCREENSHOT_ADDED' => $screenshot_status === 'added',
            'S_SCREENSHOT_DELETED' => $screenshot_status === 'deleted',
            'ITEM_IMAGE_STATUS' => $request->variable('item_image_status', ''),
            'S_ITEM_IMAGE_UPLOADED' => $request->variable('item_image_status', '') === 'uploaded',
            'S_ITEM_IMAGE_CLEARED' => $request->variable('item_image_status', '') === 'cleared',
            'EDIT_ITEM_ICON_RAW' => $edit_item['item_icon'],
            'EDIT_ITEM_ICON_URL' => $this->resolve_acp_item_icon_url($edit_item['item_icon'], $phpEx),
            'S_HAS_ITEM_ICON' => trim((string) $edit_item['item_icon']) !== '',
            'EDIT_ITEM_ID' => (int) $edit_item['item_id'],
            'EDIT_ITEM_NAME' => $edit_item['item_name'],
            'EDIT_CATEGORY_ID' => (int) $edit_item['category_id'],
            'EDIT_TOPIC_ID' => (int) $edit_item['topic_id'],
            'SUPPORT_FORUM_ID' => isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0,
            'S_CAN_CREATE_SUPPORT_TOPIC' => ((int) $edit_item['topic_id'] === 0 && isset($config['mundophpbb_downloadcenter_support_forum_id']) && (int) $config['mundophpbb_downloadcenter_support_forum_id'] > 0),
            'EDIT_ITEM_SHORT_DESC' => $edit_item['item_short_desc'],
            'EDIT_ITEM_DESC' => $edit_item['item_desc'],
            'EDIT_ITEM_ICON' => $edit_item['item_icon'],
            'EDIT_ITEM_ENABLED' => (bool) $edit_item['item_enabled'],
            'EDIT_ITEM_APPROVED' => (bool) $edit_item['item_approved'],
            'EDIT_LATEST_VERSION' => $edit_latest_version ? $edit_latest_version['version_number'] : '',
            'EDIT_LATEST_PHPBB_VERSION' => $edit_latest_version ? $edit_latest_version['phpbb_version'] : '',
            'EDIT_LATEST_PHP_VERSION' => $edit_latest_version ? $edit_latest_version['php_version'] : '',
            'EDIT_LATEST_CHANGELOG' => $edit_latest_version ? $edit_latest_version['version_changelog'] : '',
            'EDIT_LATEST_VERSION_ID' => $edit_latest_version ? (int) $edit_latest_version['version_id'] : 0,
            'ITEMS_PAGINATION' => $this->make_pagination($this->u_action, $total_items, $per_page, $start),
            'ITEMS_PAGE_NUMBER' => $this->make_page_number($total_items, $per_page, $start),
            'ITEMS_TOTAL' => $total_items,
            'S_ITEMS_HAS_PAGINATION' => $total_items > $per_page,
            'EDIT_LATEST_DOWNLOAD_TYPE' => $edit_latest_version ? $edit_latest_version['download_type'] : '',
            'EDIT_LATEST_DOWNLOAD_FILE' => $edit_latest_version ? $edit_latest_version['download_file'] : '',
            'EDIT_LATEST_DOWNLOAD_URL' => $edit_latest_version ? $edit_latest_version['download_url'] : '',
            'S_HAS_LATEST_VERSION' => (bool) $edit_latest_version,
        ]);
    }




    protected function build_item_icon_value($db, $request, $user, $item_id, $screenshots_table)
    {
        $current = $this->sanitize_db_text(trim($request->variable('item_icon_current', '', true)));
        $icon = $current;

        if ($request->variable('item_icon_clear', 0))
        {
            return '';
        }

        $external_url = $this->sanitize_db_text(trim($request->variable('item_icon_url', '', true)));
        if ($external_url !== '')
        {
            $icon = $external_url;
        }

        $existing_file = basename((string) $request->variable('item_icon_existing', '', true));
        if ($existing_file !== '')
        {
            $path = $this->item_image_file_path($existing_file);
            if (!is_file($path))
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_EXISTING_NOT_FOUND'));
            }
            $icon = 'item_image:' . $existing_file;
        }

        $screenshot_id = $request->variable('item_icon_screenshot', 0);
        if ($screenshot_id > 0)
        {
            if ($item_id <= 0)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_SCREENSHOT_SAVE_FIRST'));
            }

            $sql = 'SELECT screenshot_id FROM ' . $screenshots_table . '
                WHERE screenshot_id = ' . (int) $screenshot_id . '
                    AND item_id = ' . (int) $item_id;
            $result = $db->sql_query_limit($sql, 1);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$row)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_SCREENSHOT_INVALID'));
            }
            $icon = 'screenshot:' . (int) $screenshot_id;
        }

        $uploaded = $this->handle_item_image_upload($request, $user);
        if ($uploaded)
        {
            $icon = 'item_image:' . $uploaded['file_name'];
        }

        return $icon;
    }

    protected function assign_item_image_options($template, $selected_icon = '')
    {
        $selected_file = '';
        if (strpos((string) $selected_icon, 'item_image:') === 0)
        {
            $selected_file = basename(substr((string) $selected_icon, strlen('item_image:')));
        }

        $files = $this->get_item_image_library();
        foreach ($files as $file)
        {
            $template->assign_block_vars('item_image_options', [
                'FILENAME' => $file['filename'],
                'DISPLAY_NAME' => $file['display_name'],
                'SIZE' => $file['size'],
                'MODIFIED' => $file['modified'],
                'U_IMAGE' => '../app.php/downloadcenter/item-image/' . rawurlencode($file['filename']),
                'S_SELECTED' => $selected_file !== '' && $selected_file === $file['filename'],
            ]);
        }

        $template->assign_vars([
            'S_HAS_ITEM_IMAGE_FILES' => count($files) > 0,
            'ITEM_IMAGE_FILES_TOTAL' => count($files),
        ]);
    }

    protected function get_item_image_library()
    {
        $directory = $this->item_images_storage_directory();
        if (!is_dir($directory))
        {
            return [];
        }

        $files = [];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $iterator = @scandir($directory);
        if (!is_array($iterator))
        {
            return [];
        }

        foreach ($iterator as $entry)
        {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess')
            {
                continue;
            }

            $path = $directory . $entry;
            if (!is_file($path))
            {
                continue;
            }

            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true))
            {
                continue;
            }

            $files[] = [
                'filename' => $entry,
                'display_name' => $this->friendly_file_name($entry),
                'size' => $this->format_file_size((int) filesize($path)),
                'modified' => date('Y-m-d H:i', (int) filemtime($path)),
                'mtime' => (int) filemtime($path),
            ];
        }

        usort($files, static function ($a, $b) {
            if ($a['mtime'] === $b['mtime'])
            {
                return strcasecmp($a['display_name'], $b['display_name']);
            }
            return $b['mtime'] <=> $a['mtime'];
        });

        return $files;
    }

    protected function resolve_acp_item_icon_url($icon, $phpEx)
    {
        $icon = trim((string) $icon);
        if ($icon === '')
        {
            return '';
        }

        if (strpos($icon, 'item_image:') === 0)
        {
            $file = basename(substr($icon, strlen('item_image:')));
            return $file !== '' ? '../app.' . $phpEx . '/downloadcenter/item-image/' . rawurlencode($file) : '';
        }

        if (strpos($icon, 'screenshot:') === 0)
        {
            $screenshot_id = (int) substr($icon, strlen('screenshot:'));
            return $screenshot_id > 0 ? '../app.' . $phpEx . '/downloadcenter/screenshot/' . $screenshot_id : '';
        }

        return $icon;
    }

    protected function item_images_storage_directory()
    {
        global $phpbb_root_path;
        return $phpbb_root_path . 'files/mundophpbb/downloadcenter/item_images/';
    }

    protected function item_image_file_path($file_name)
    {
        return $this->item_images_storage_directory() . basename((string) $file_name);
    }

    protected function handle_item_image_upload($request, $user)
    {
        $file = $request->file('item_image_upload');

        if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
        {
            return false;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_UPLOAD_FAILED'));
        }

        $original_name = (string) $file['name'];
        $tmp_name = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_bytes = 5 * 1024 * 1024;

        if (!$this->is_safe_upload_name($original_name) || !in_array($extension, $allowed, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_EXTENSION_NOT_ALLOWED'));
        }

        if ($size > $max_bytes)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_TOO_LARGE', $this->format_file_size($max_bytes)));
        }

        if ($tmp_name === '' || !is_uploaded_file($tmp_name))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_UPLOAD_FAILED'));
        }

        if (@getimagesize($tmp_name) === false)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_IMAGE_INVALID_IMAGE'));
        }

        $directory = $this->item_images_storage_directory();
        if (!is_dir($directory) && !@mkdir($directory, 0755, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
        }

        $htaccess = $directory . '.htaccess';
        if (!is_file($htaccess))
        {
            @file_put_contents($htaccess, "<Files *>\n\tRequire all denied\n</Files>\n");
        }

        $safe_base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($original_name, PATHINFO_FILENAME));
        $safe_base = trim($safe_base, '.-') ?: 'item-image';
        $file_name = time() . '-' . substr(md5($original_name . microtime(true)), 0, 8) . '-' . $safe_base . '.' . $extension;
        $destination = $directory . $file_name;

        if (!@move_uploaded_file($tmp_name, $destination))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
        }

        return ['file_name' => $file_name];
    }

    protected function redirect_to_acp_anchor($url)
    {
        \redirect(str_replace('&amp;', '&', $url));
    }

    protected function screenshots_storage_directory()
    {
        global $phpbb_root_path;
        return $phpbb_root_path . 'files/mundophpbb/downloadcenter/screenshots/';
    }

    protected function screenshot_file_path($file_name)
    {
        return $this->screenshots_storage_directory() . basename((string) $file_name);
    }

    protected function handle_screenshot_upload($request, $user)
    {
        $file = $request->file('screenshot_upload');

        if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
        {
            return false;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
        }

        $original_name = (string) $file['name'];
        $tmp_name = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_bytes = 5 * 1024 * 1024;

        if (!$this->is_safe_upload_name($original_name) || !in_array($extension, $allowed, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_EXTENSION_NOT_ALLOWED'));
        }

        if ($size > $max_bytes)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_TOO_LARGE', $this->format_file_size($max_bytes)));
        }

        if ($tmp_name === '' || !is_uploaded_file($tmp_name))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
        }

        if (@getimagesize($tmp_name) === false)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_INVALID_IMAGE'));
        }

        $directory = $this->screenshots_storage_directory();
        if (!is_dir($directory) && !@mkdir($directory, 0755, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
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
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
        }

        return ['file_name' => $file_name];
    }

    protected function delete_screenshot_file($file_name)
    {
        $path = $this->screenshot_file_path($file_name);
        if (is_file($path))
        {
            return @unlink($path);
        }
        return false;
    }

    protected function delete_screenshots_for_item($db, $screenshots_table, $item_id)
    {
        $sql = 'SELECT * FROM ' . $screenshots_table . ' WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        while ($screenshot = $db->sql_fetchrow($result))
        {
            $this->delete_screenshot_file($screenshot['image_file']);
        }
        $db->sql_freeresult($result);

        $sql = 'DELETE FROM ' . $screenshots_table . ' WHERE item_id = ' . (int) $item_id;
        $db->sql_query($sql);
    }





    protected function version_file_exists($version_row)
    {
        return is_array($version_row)
            && (string) $version_row['download_type'] === 'local'
            && trim((string) $version_row['download_file']) !== ''
            && is_file($this->local_file_path($version_row['download_file']));
    }

    protected function local_file_path($file_name)
    {
        return $this->local_storage_directory() . basename((string) $file_name);
    }

    protected function delete_local_file_for_version($version_row)
    {
        if (!is_array($version_row) || (string) $version_row['download_type'] !== 'local' || trim((string) $version_row['download_file']) === '')
        {
            return false;
        }

        $path = $this->local_file_path($version_row['download_file']);
        if (is_file($path))
        {
            return @unlink($path);
        }

        return false;
    }

    protected function delete_local_files_for_item($db, $versions_table, $item_id)
    {
        $sql = 'SELECT * FROM ' . $versions_table . ' WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        while ($version_row = $db->sql_fetchrow($result))
        {
            $this->delete_local_file_for_version($version_row);
        }
        $db->sql_freeresult($result);
    }

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
                'ITEM_SHORT_DESC' => $this->render_short_text($row['item_short_desc']),
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


    protected function handle_files($db, $request, $template, $user, $config)
    {
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $action = $request->variable('action', '');
        $file_name = basename((string) $request->variable('file', '', true));

        if ($action === 'delete_library_file' && $file_name !== '')
        {
            $usage = $this->get_file_usage($db, $file_name);
            if (!empty($usage))
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_FILE_IN_USE_CANNOT_DELETE') . adm_back_link($this->u_action_for_mode('files')));
            }

            if (confirm_box(true))
            {
                $path = $this->local_file_path($file_name);
                if (is_file($path))
                {
                    @unlink($path);
                    $this->add_log($db, $user, 'library_file_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_LIBRARY_FILE_DELETED', $file_name));
                }
                trigger_error($user->lang('ACP_DOWNLOADCENTER_FILE_DELETED') . adm_back_link($this->u_action_for_mode('files')));
            }
            else
            {
                confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_LIBRARY_FILE'), build_hidden_fields([
                    'action' => 'delete_library_file',
                    'file' => $file_name,
                ]));
            }
        }

        $query = trim($request->variable('file_q', '', true));
        $start = max(0, $request->variable('start', 0));
        $per_page = max(1, (int) ($config['mundophpbb_downloadcenter_acp_per_page'] ?? 20));

        $files = $this->get_local_file_library($db, true);
        if ($query !== '')
        {
            $needle = utf8_strtolower($query);
            $files = array_values(array_filter($files, static function ($file) use ($needle) {
                return strpos(utf8_strtolower($file['filename']), $needle) !== false
                    || strpos(utf8_strtolower($file['display_name']), $needle) !== false
                    || strpos(utf8_strtolower($file['usage_label']), $needle) !== false;
            }));
        }

        $total_files = count($files);
        $paged_files = array_slice($files, $start, $per_page);

        $used_total = 0;
        $orphan_total = 0;
        $total_bytes = 0;
        foreach ($files as $file)
        {
            $total_bytes += (int) ($file['bytes'] ?? 0);
            if (!empty($file['used']))
            {
                $used_total++;
            }
            else
            {
                $orphan_total++;
            }
        }

        foreach ($paged_files as $file)
        {
            $template->assign_block_vars('file_library', [
                'FILENAME' => $file['filename'],
                'DISPLAY_NAME' => $file['display_name'],
                'SIZE' => $file['size'],
                'MODIFIED' => $file['modified'],
                'USAGE_LABEL' => $file['usage_label'],
                'S_USED' => !empty($file['used']),
                'U_DELETE' => $this->u_action_for_mode('files') . '&amp;action=delete_library_file&amp;file=' . rawurlencode($file['filename']),
            ]);
        }

        $base_url = $this->u_action_for_mode('files');
        if ($query !== '')
        {
            $base_url .= '&amp;file_q=' . rawurlencode($query);
        }

        $template->assign_vars([
            'FILE_LIBRARY_QUERY' => $query,
            'FILE_LIBRARY_TOTAL' => $total_files,
            'FILE_LIBRARY_USED_TOTAL' => $used_total,
            'FILE_LIBRARY_ORPHAN_TOTAL' => $orphan_total,
            'FILE_LIBRARY_TOTAL_SIZE' => $this->format_file_size($total_bytes),
            'FILE_LIBRARY_PAGINATION' => $this->make_pagination($base_url, $total_files, $per_page, $start),
            'S_HAS_FILE_LIBRARY' => $total_files > 0,
            'U_FILES_CLEAR' => $this->u_action_for_mode('files'),
        ]);
    }

    protected function handle_logs($db, $request, $template, $user)
    {
        $logs_table = $this->table_prefix . 'downloadcenter_logs';
        $action_filter = trim($request->variable('log_action', '', true));
        $user_filter = trim($request->variable('log_user', '', true));
        $item_filter = max(0, $request->variable('log_item_id', 0));
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

        global $config;
        $start = max(0, $request->variable('start', 0));
        $per_page = isset($config['mundophpbb_downloadcenter_logs_per_page']) ? max(1, (int) $config['mundophpbb_downloadcenter_logs_per_page']) : 50;

        $sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = 'SELECT COUNT(*) AS total FROM ' . $logs_table . $sql_where;
        $result = $db->sql_query($sql);
        $total_logs = (int) $db->sql_fetchfield('total');
        $db->sql_freeresult($result);
        $start = $this->normalize_start($start, $per_page, $total_logs);

        $sql = 'SELECT * FROM ' . $logs_table . $sql_where . ' ORDER BY log_time DESC, log_id DESC';
        $result = $db->sql_query_limit($sql, $per_page, $start);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('logs', [
                'LOG_ID' => (int) $row['log_id'],
                'TIME' => $user->format_date((int) $row['log_time']),
                'USERNAME' => $row['username'] ?: '-',
                'USER_ID' => (int) $row['user_id'],
                'ACTION' => $row['log_action'],
                'MESSAGE' => $row['log_message'],
                'ITEM_ID' => (int) $row['item_id'],
                'VERSION_ID' => (int) $row['version_id'],
                'USER_IP' => $row['user_ip'],
            ]);
        }
        $db->sql_freeresult($result);

        $actions = [
            'settings_saved', 'category_created', 'category_updated', 'category_deleted',
            'item_created', 'item_updated', 'item_deleted', 'item_approved', 'item_unapproved',
            'version_created', 'version_deleted', 'version_file_deleted', 'support_topic_created', 'support_topic_updated',
            'public_submission', 'download', 'logs_cleared',
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
        ]);

        $template->assign_vars([
            'LOG_ACTION_FILTER' => $action_filter,
            'LOG_USER_FILTER' => $user_filter,
            'LOG_ITEM_ID_FILTER' => $item_filter,
            'U_CLEAR_LOGS' => $this->u_action . '&amp;clear=1',
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


    protected function get_item_row($db, $item_id)
    {
        $sql = 'SELECT item_id, user_id, item_name
            FROM ' . $this->table_prefix . 'downloadcenter_items
            WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return $row ?: false;
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

    protected function assign_file_library_options($db, $template, $selected_file = '')
    {
        $files = $this->get_local_file_library($db);
        foreach ($files as $file)
        {
            $template->assign_block_vars('local_file_options', [
                'FILENAME' => $file['filename'],
                'DISPLAY_NAME' => $file['display_name'],
                'SIZE' => $file['size'],
                'MODIFIED' => $file['modified'],
                'USAGE_LABEL' => $file['usage_label'],
                'S_USED' => $file['used'],
                'S_SELECTED' => $selected_file !== '' && $selected_file === $file['filename'],
            ]);
        }

        $template->assign_vars([
            'S_HAS_LOCAL_FILES' => count($files) > 0,
            'LOCAL_FILES_TOTAL' => count($files),
            'DOWNLOADCENTER_UPLOAD_RULES' => $this->upload_rules_text($GLOBALS['user']),
        ]);
    }

    protected function get_local_file_library($db)
    {
        $directory = $this->local_storage_directory();
        if (!is_dir($directory))
        {
            return [];
        }

        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $usage = [];
        $sql = 'SELECT v.download_file, v.version_number, v.version_id, v.item_id, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . " i ON i.item_id = v.item_id
            WHERE v.download_type = 'local'
                AND v.download_file <> ''";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $file = basename((string) $row['download_file']);
            if ($file === '')
            {
                continue;
            }
            if (!isset($usage[$file]))
            {
                $usage[$file] = [];
            }
            $item_name = $row['item_name'] !== null && $row['item_name'] !== '' ? (string) $row['item_name'] : 'item #' . (int) $row['item_id'];
            $usage[$file][] = $item_name . ' / v' . (string) $row['version_number'];
        }
        $db->sql_freeresult($result);

        $files = [];
        $allowed = $this->get_allowed_extensions();
        $iterator = @scandir($directory);
        if (!is_array($iterator))
        {
            return [];
        }

        foreach ($iterator as $entry)
        {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess')
            {
                continue;
            }

            $path = $directory . $entry;
            if (!is_file($path))
            {
                continue;
            }

            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true))
            {
                continue;
            }

            $used = isset($usage[$entry]);
            $files[] = [
                'filename' => $entry,
                'display_name' => $this->friendly_file_name($entry),
                'size' => $this->format_file_size(filesize($path)),
                'bytes' => (int) filesize($path),
                'modified' => date('Y-m-d H:i', (int) filemtime($path)),
                'used' => $used,
                'usage_label' => $used ? implode(', ', array_slice($usage[$entry], 0, 3)) . (count($usage[$entry]) > 3 ? '...' : '') : '',
                'mtime' => (int) filemtime($path),
            ];
        }

        usort($files, static function ($a, $b) {
            if ($a['mtime'] === $b['mtime'])
            {
                return strcasecmp($a['display_name'], $b['display_name']);
            }
            return $b['mtime'] <=> $a['mtime'];
        });

        return $files;
    }

    protected function get_file_usage($db, $file_name)
    {
        $file_name = basename((string) $file_name);
        if ($file_name === '')
        {
            return [];
        }

        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $sql = 'SELECT version_id, item_id, version_number
            FROM ' . $versions_table . "
            WHERE download_type = 'local'
                AND download_file = '" . $db->sql_escape($file_name) . "'";
        $result = $db->sql_query($sql);
        $usage = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $usage[] = $row;
        }
        $db->sql_freeresult($result);
        return $usage;
    }

    protected function friendly_file_name($file_name)
    {
        $file_name = basename((string) $file_name);
        if (preg_match('/^\d+-[a-f0-9]{8}-(.+)$/i', $file_name, $matches))
        {
            return $matches[1];
        }
        return $file_name;
    }

    protected function local_storage_directory()
    {
        global $phpbb_root_path;
        return $phpbb_root_path . 'files/mundophpbb/downloadcenter/';
    }

    protected function handle_local_upload($request, $user, $phpbb_root_path)
    {
        $file = $request->file('download_upload');

        if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
        {
            return false;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        $original_name = (string) $file['name'];
        $tmp_name = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = $this->get_allowed_extensions();
        $max_bytes = $this->get_max_upload_bytes();

        if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', implode(', ', $allowed)));
        }

        if ($max_bytes > 0 && $size > $max_bytes)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_TOO_LARGE', $this->format_file_size($max_bytes)));
        }

        if ($tmp_name === '' || !is_uploaded_file($tmp_name))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        $directory = $this->local_storage_directory();
        if (!is_dir($directory) && !@mkdir($directory, 0755, true))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
        }

        $htaccess = $directory . '.htaccess';
        if (!is_file($htaccess))
        {
            @file_put_contents($htaccess, "<Files *>
	Require all denied
</Files>
");
        }

        $safe_base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($original_name, PATHINFO_FILENAME));
        $safe_base = trim($safe_base, '.-') ?: 'download';
        $file_name = time() . '-' . substr(md5($original_name . microtime(true)), 0, 8) . '-' . $safe_base . '.' . $extension;
        $destination = $directory . $file_name;

        if (!@move_uploaded_file($tmp_name, $destination))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
        }

        return [
            'file_name' => $file_name,
            'file_size' => $this->format_file_size($size),
        ];
    }

    protected function sync_item_download_count($db, $item_id)
    {
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $items_table = $this->table_prefix . 'downloadcenter_items';

        $sql = 'SELECT COUNT(download_id) AS total_downloads FROM ' . $downloads_table . ' WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $sql = 'UPDATE ' . $items_table . ' SET item_downloads = ' . (int) ($row ? $row['total_downloads'] : 0) . ' WHERE item_id = ' . (int) $item_id;
        $db->sql_query($sql);
    }


    protected function get_allowed_extensions()
    {
        global $config;
        $raw = isset($config['mundophpbb_downloadcenter_allowed_extensions']) ? (string) $config['mundophpbb_downloadcenter_allowed_extensions'] : 'zip,rar,7z,tar,gz,tgz,bz2,pdf,txt';
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

    protected function normalise_allowed_extensions($raw)
    {
        $parts = preg_split('/[\s,;]+/', strtolower((string) $raw));
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
            $allowed = ['zip' => 'zip', 'rar' => 'rar', '7z' => '7z', 'tar' => 'tar', 'gz' => 'gz', 'tgz' => 'tgz', 'bz2' => 'bz2', 'pdf' => 'pdf', 'txt' => 'txt'];
        }

        return implode(',', array_values($allowed));
    }

    protected function get_max_upload_mb()
    {
        global $config;
        return max(1, (int) (isset($config['mundophpbb_downloadcenter_max_upload_mb']) ? $config['mundophpbb_downloadcenter_max_upload_mb'] : 20));
    }

    protected function get_max_upload_bytes()
    {
        return $this->get_max_upload_mb() * 1024 * 1024;
    }

    protected function upload_rules_text($user)
    {
        return $user->lang('ACP_DOWNLOADCENTER_UPLOAD_RULES', $this->get_allowed_extensions_string(), $this->format_file_size($this->get_max_upload_bytes()));
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

    protected function is_allowed_existing_file($file_name)
    {
        $file_name = basename((string) $file_name);
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        return $file_name !== '' && $extension !== '' && in_array($extension, $this->get_allowed_extensions(), true);
    }


    protected function render_short_text($text)
    {
        $text = (string) $text;
        if ($text === '')
        {
            return '';
        }

        $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
        $text = preg_replace('#\[b\](.*?)\[/b\]#is', '<strong>$1</strong>', $text);
        $text = preg_replace('#\[i\](.*?)\[/i\]#is', '<em>$1</em>', $text);
        $text = preg_replace('#\[u\](.*?)\[/u\]#is', '<span style="text-decoration: underline;">$1</span>', $text);
        $text = preg_replace('#\[s\](.*?)\[/s\]#is', '<span style="text-decoration: line-through;">$1</span>', $text);
        $text = preg_replace('#\[url\](https?://[^\s\[]+?)\[/url\]#is', '<a href="$1" rel="nofollow noopener" target="_blank">$1</a>', $text);
        $text = preg_replace('#\[url=(https?://[^\s\]]+?)\](.*?)\[/url\]#is', '<a href="$1" rel="nofollow noopener" target="_blank">$2</a>', $text);
        $text = preg_replace_callback('#\[size=(85|100|120|150|200)\](.*?)\[/size\]#is', function ($matches) {
            return '<span style="font-size: ' . (int) $matches[1] . '%;">' . $matches[2] . '</span>';
        }, $text);

        return nl2br($text, false);
    }


    protected function parse_size_to_bytes($value)
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit)
        {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
            break;
        }

        return (int) $number;
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
            WHERE forum_id = ' . $forum_id . '
                AND forum_type = ' . (int) $forum_type_post;
        $result = $db->sql_query_limit($sql, 1);
        $valid = (bool) $db->sql_fetchfield('forum_id');
        $db->sql_freeresult($result);

        return $valid;
    }

    protected function get_lang_string($key)
    {
        global $user;
        return isset($user->lang[$key]) ? $user->lang[$key] : $key;
    }

    protected function sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx)
    {
        $item_id = (int) $item_id;
        if ($item_id <= 0)
        {
            return 0;
        }

        $sql = 'SELECT * FROM ' . $items_table . ' WHERE item_id = ' . $item_id;
        $result = $db->sql_query($sql);
        $item = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$item)
        {
            return 0;
        }

        $support_forum_id = isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0;
        $topic_id = (int) $item['topic_id'];
        $latest_version = $this->get_latest_version_for_item($db, $versions_table, $item_id);

        if ($topic_id > 0)
        {
            $first_post_id = $this->get_topic_first_post_id($db, $topic_id);
            if ($first_post_id > 0)
            {
                if ($this->update_support_topic($db, $topic_id, $first_post_id, $item, $latest_version, $phpbb_root_path, $phpEx, $user))
                {
                    $this->add_log($db, $user, 'support_topic_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_SUPPORT_TOPIC_UPDATED', (string) $topic_id), $item_id);
                    return $topic_id;
                }
            }

            // The linked topic no longer exists. Clear it and try creating a new one if a support forum is configured.
            $sql = 'UPDATE ' . $items_table . ' SET topic_id = 0 WHERE item_id = ' . $item_id;
            $db->sql_query($sql);
            $topic_id = 0;
        }

        if ($topic_id === 0 && $support_forum_id > 0)
        {
            if (!$this->is_valid_support_forum($support_forum_id))
            {
                return 0;
            }

            $created_topic_id = $this->create_support_topic($support_forum_id, $item, $latest_version, $phpbb_root_path, $phpEx, $user);
            if ($created_topic_id > 0)
            {
                $sql = 'UPDATE ' . $items_table . ' SET topic_id = ' . (int) $created_topic_id . ' WHERE item_id = ' . $item_id;
                $db->sql_query($sql);
                $this->add_log($db, $user, 'support_topic_created', $user->lang('ACP_DOWNLOADCENTER_LOG_SUPPORT_TOPIC_CREATED', (string) $created_topic_id), $item_id);
                return $created_topic_id;
            }
        }

        return 0;
    }

    protected function get_latest_version_for_item($db, $versions_table, $item_id)
    {
        $sql = 'SELECT *
            FROM ' . $versions_table . '
            WHERE item_id = ' . (int) $item_id . '
                AND version_enabled = 1
            ORDER BY version_created DESC, version_id DESC';
        $result = $db->sql_query_limit($sql, 1);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return $row ?: [];
    }

    protected function get_topic_first_post_id($db, $topic_id)
    {
        $sql = 'SELECT topic_first_post_id
            FROM ' . TOPICS_TABLE . '
            WHERE topic_id = ' . (int) $topic_id;
        $result = $db->sql_query_limit($sql, 1);
        $first_post_id = (int) $db->sql_fetchfield('topic_first_post_id');
        $db->sql_freeresult($result);

        return $first_post_id;
    }

    protected function update_support_topic($db, $topic_id, $first_post_id, array $item, array $latest_version, $phpbb_root_path, $phpEx, $user)
    {
        if (!function_exists('generate_text_for_storage'))
        {
            include_once($phpbb_root_path . 'includes/functions_content.' . $phpEx);
        }

        $subject = '[Download Center] ' . $item['item_name'];
        $message = $this->build_support_topic_message($item, $latest_version, $user);
        $uid = $bitfield = '';
        $flags = 0;
        generate_text_for_storage($message, $uid, $bitfield, $flags, true, true, true);

        $post_data = [
            'post_subject' => $subject,
            'post_text' => $message,
            'post_checksum' => md5($message),
            'bbcode_bitfield' => $bitfield,
            'bbcode_uid' => $uid,
            'enable_bbcode' => 1,
            'enable_smilies' => 1,
            'enable_magic_url' => 1,
            'post_edit_time' => time(),
            'post_edit_user' => (int) $user->data['user_id'],
        ];

        $sql = 'UPDATE ' . POSTS_TABLE . '
            SET ' . $db->sql_build_array('UPDATE', $post_data) . '
            WHERE post_id = ' . (int) $first_post_id;
        $db->sql_query($sql);

        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET topic_title = \'' . $db->sql_escape($subject) . '\'
            WHERE topic_id = ' . (int) $topic_id;
        $db->sql_query($sql);

        return true;
    }

    protected function create_support_topic($forum_id, array $item, array $latest_version, $phpbb_root_path, $phpEx, $user)
    {
        if (!$this->is_valid_support_forum($forum_id))
        {
            return 0;
        }

        if (!function_exists('submit_post'))
        {
            include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
        }

        if (!function_exists('generate_text_for_storage'))
        {
            include_once($phpbb_root_path . 'includes/functions_content.' . $phpEx);
        }

        $subject = '[Download Center] ' . $item['item_name'];
        $message = $this->build_support_topic_message($item, $latest_version, $user);

        $uid = $bitfield = '';
        $flags = 0;
        generate_text_for_storage($message, $uid, $bitfield, $flags, true, true, true);

        $data = [
            'forum_id' => (int) $forum_id,
            'icon_id' => 0,
            'enable_bbcode' => true,
            'enable_smilies' => true,
            'enable_urls' => true,
            'enable_sig' => true,
            'message' => $message,
            'message_md5' => md5($message),
            'bbcode_bitfield' => $bitfield,
            'bbcode_uid' => $uid,
            'post_edit_locked' => 0,
            'topic_title' => $subject,
            'notify_set' => false,
            'notify' => false,
            'post_time' => time(),
            'forum_name' => '',
            'enable_indexing' => true,
        ];

        $poll = [];
        submit_post('post', $subject, $user->data['username'], POST_NORMAL, $poll, $data);

        return isset($data['topic_id']) ? (int) $data['topic_id'] : 0;
    }

    protected function build_support_topic_message(array $item, array $latest_version, $user)
    {
        $download_url = generate_board_url() . '/app.php/downloadcenter/item/' . (int) $item['item_id'];
        $message = '[b]' . $item['item_name'] . "[/b]\n\n";

        if (!empty($item['item_short_desc']))
        {
            $message .= $item['item_short_desc'] . "\n\n";
        }

        if (!empty($item['item_desc']))
        {
            $message .= '[b]' . $user->lang('ACP_DOWNLOADCENTER_SUPPORT_TOPIC_DESCRIPTION') . "[/b]\n" . $item['item_desc'] . "\n\n";
        }

        if (!empty($latest_version))
        {
            $message .= '[b]' . $user->lang('ACP_DOWNLOADCENTER_SUPPORT_TOPIC_VERSION_INFO') . "[/b]\n";
            $message .= $user->lang('ACP_DOWNLOADCENTER_VERSION_NUMBER') . ': ' . $latest_version['version_number'] . "\n";

            if (!empty($latest_version['phpbb_version']))
            {
                $message .= $user->lang('ACP_DOWNLOADCENTER_PHPBB_VERSION') . ': ' . $latest_version['phpbb_version'] . "\n";
            }
            if (!empty($latest_version['php_version']))
            {
                $message .= $user->lang('ACP_DOWNLOADCENTER_PHP_VERSION') . ': ' . $latest_version['php_version'] . "\n";
            }
            if (!empty($latest_version['version_changelog']))
            {
                $message .= "\n[b]" . $user->lang('ACP_DOWNLOADCENTER_CHANGELOG') . "[/b]\n" . $latest_version['version_changelog'] . "\n";
            }

            $message .= "\n";
        }

        $message .= $user->lang('ACP_DOWNLOADCENTER_SUPPORT_TOPIC_BODY') . "\n";
        $message .= '[url=' . $download_url . ']' . $download_url . '[/url]';

        return $message;
    }

    protected function slugify($text)
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'item';
    }


    protected function count_screenshots_for_item($db, $screenshots_table, $item_id)
    {
        $sql = 'SELECT COUNT(screenshot_id) AS total_screenshots FROM ' . $screenshots_table . ' WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return $row ? (int) $row['total_screenshots'] : 0;
    }

    protected function excerpt_text($text, $max_length = 700)
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s+/', ' ', $text);

        if ($text === '')
        {
            return '';
        }

        if (strlen($text) <= $max_length)
        {
            return $text;
        }

        return substr($text, 0, max(0, (int) $max_length - 3)) . '...';
    }

    protected function clean_version_label($version)
    {
        $version = trim((string) $version);
        $version = preg_replace('/\s+/', ' ', $version);

        if ($version === '')
        {
            return '-';
        }

        if (strlen($version) > 40)
        {
            return substr($version, 0, 37) . '...';
        }

        return $version;
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

        return $base_url . ($query ? '&amp;' . implode('&amp;', $query) : '');
    }



    protected function sanitize_db_text($text)
    {
        $text = (string) $text;

        // Keep extension data compatible with MySQL/MariaDB utf8 (3-byte) installations.
        // Emoji and other 4-byte Unicode characters cause SQL 1366 when the database is not utf8mb4.
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

    protected function make_page_number($total_items, $per_page, $start)
    {
        $total_items = max(0, (int) $total_items);
        $per_page = max(1, (int) $per_page);
        $start = max(0, (int) $start);

        if ($total_items === 0)
        {
            return 'Página 1 de 1';
        }

        $current_page = (int) floor($start / $per_page) + 1;
        $total_pages = (int) ceil($total_items / $per_page);

        return 'Página ' . $current_page . ' de ' . $total_pages;
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
            $links[] = '<a href="' . $this->append_start_to_url($base_url, max(0, $start - $per_page)) . '">&laquo; Anterior</a>';
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
            $links[] = '<a href="' . $this->append_start_to_url($base_url, $start + $per_page) . '">Próxima &raquo;</a>';
        }

        return implode(' ', $links);
    }

    protected function append_start_to_url($url, $start)
    {
        $url = preg_replace('/(&amp;|&)start=\d+/', '', $url);
        $separator = (strpos($url, '?') === false && strpos($url, '&amp;') === false && strpos($url, '&') === false) ? '?' : '&amp;';
        return $url . $separator . 'start=' . max(0, (int) $start);
    }

    protected function u_action_for_mode($mode)
    {
        $url = $this->u_action;
        $url = preg_replace('/(&amp;|&)mode=[^&]+/', '', $url);
        return $url . '&amp;mode=' . $mode;
    }
}

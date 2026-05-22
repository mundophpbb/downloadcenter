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

        if (!$auth->acl_get('a_board') && !$auth->acl_get('a_downloadcenter_manage'))
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
                $this->handle_dashboard($db, $template, $user, $config);
            break;

            case 'diagnostics':
                $this->handle_diagnostics($db, $request, $template, $user, $config);
            break;

            case 'integrity':
                $this->handle_integrity($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx);
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


    protected function handle_diagnostics($db, $request, $template, $user, $config)
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

        if ($request->is_set_post('diagnostics_rebuild'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_diagnostics'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action));
            }

            $result = $this->run_maintenance_rebuild($db, $user);
            trigger_error($user->lang('ACP_DOWNLOADCENTER_DIAG_REBUILD_DONE', $result['items'], $result['versions'], $result['counters'], $result['targets'], $result['storage']) . adm_back_link($this->u_action_for_mode('diagnostics')));
        }

        $total_categories = $this->count_table_rows($db, $categories_table);
        $total_published = $this->count_table_rows($db, $items_table, 'item_enabled = 1 AND item_approved = 1');
        $total_pending = $this->count_table_rows($db, $items_table, 'item_approved = 0');

        $package_version = '1.0.103';
        $installed_version = isset($config['mundophpbb_downloadcenter_version']) ? (string) $config['mundophpbb_downloadcenter_version'] : '';
        $permission_mode = isset($config['mundophpbb_downloadcenter_permission_mode']) && $config['mundophpbb_downloadcenter_permission_mode'] === 'acl' ? 'acl' : 'global';
        $support_forum_id = isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0;
        $notifications_enabled = !isset($config['mundophpbb_downloadcenter_notifications_enabled']) || (bool) $config['mundophpbb_downloadcenter_notifications_enabled'];
        $allow_submissions = isset($config['mundophpbb_downloadcenter_allow_submissions']) ? (bool) $config['mundophpbb_downloadcenter_allow_submissions'] : true;
        $public_per_page = isset($config['mundophpbb_downloadcenter_public_per_page']) ? (int) $config['mundophpbb_downloadcenter_public_per_page'] : 0;

        $required_config_keys = [
            'mundophpbb_downloadcenter_enabled',
            'mundophpbb_downloadcenter_version',
            'mundophpbb_downloadcenter_permission_mode',
            'mundophpbb_downloadcenter_view_access',
            'mundophpbb_downloadcenter_download_access',
            'mundophpbb_downloadcenter_submit_access',
            'mundophpbb_downloadcenter_allowed_extensions',
            'mundophpbb_downloadcenter_max_upload_mb',
            'mundophpbb_downloadcenter_use_phpbb_attachments',
            'mundophpbb_downloadcenter_rules_topic_id',
            'mundophpbb_downloadcenter_public_per_page',
            'mundophpbb_downloadcenter_show_public_stats',
            'mundophpbb_downloadcenter_feed_enabled',
            'mundophpbb_downloadcenter_rate_limit_count',
            'mundophpbb_downloadcenter_rate_limit_window',
        ];
        $missing_config_keys = [];
        foreach ($required_config_keys as $config_key)
        {
            if (!isset($config[$config_key]))
            {
                $missing_config_keys[] = $config_key;
            }
        }

        $missing_files = 0;
        $sql = 'SELECT download_file FROM ' . $versions_table . " WHERE download_type = 'local' AND download_file <> ''";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            if (!$this->version_file_exists(['download_type' => 'local', 'download_file' => $row['download_file']]))
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

        $external_invalid = $this->count_external_invalid_versions($db, $versions_table);
        $prelaunch_checks = $this->build_prelaunch_checks($user, [
            'version_ok' => $installed_version !== '' && version_compare($installed_version, $package_version, '>='),
            'config_ok' => count($missing_config_keys) === 0,
            'storage_ok' => $storage_exists && $storage_writable,
            'htaccess_ok' => $htaccess_ok,
            'categories_ok' => $total_categories > 0,
            'published_ok' => $total_published > 0,
            'missing_files_ok' => $missing_files === 0,
            'external_urls_ok' => $external_invalid === 0,
            'pagination_ok' => $public_per_page > 0,
        ]);
        if ($request->variable('export_report', 0))
        {
            $this->send_diagnostics_report($user, [
                'package_version' => $package_version,
                'installed_version' => $installed_version,
                'permission_mode' => $permission_mode,
                'storage_dir' => $storage_dir,
                'storage_writable' => $storage_exists && $storage_writable,
                'htaccess_ok' => $htaccess_ok,
                'total_categories' => $total_categories,
                'total_published' => $total_published,
                'total_pending' => $total_pending,
                'missing_files' => $missing_files,
                'orphan_files' => $orphan_files,
                'external_invalid' => $external_invalid,
                'php_upload_max' => $this->format_file_size($php_upload_max),
                'php_post_max' => $this->format_file_size($php_post_max),
                'extension_limit' => $this->format_file_size($max_upload_bytes),
                'prelaunch_checks' => $prelaunch_checks,
            ]);
        }

        $version_ok = $installed_version !== '' && version_compare($installed_version, $package_version, '>=');
        $config_ok = count($missing_config_keys) === 0;
        $support_forum_ok = $support_forum_id > 0 && $this->is_valid_support_forum($support_forum_id);
        $public_per_page_ok = $public_per_page > 0;

        $this->assign_diagnostic_row($template, $version_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_VERSION'), $version_ok ? $user->lang('ACP_DOWNLOADCENTER_DIAG_VERSION_OK', $installed_version) : $user->lang('ACP_DOWNLOADCENTER_DIAG_VERSION_WARN', $installed_version !== '' ? $installed_version : $user->lang('ACP_DOWNLOADCENTER_DIAG_VERSION_UNKNOWN'), $package_version));
        $this->assign_diagnostic_row($template, $config_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_CONFIG_KEYS'), $config_ok ? $user->lang('ACP_DOWNLOADCENTER_DIAG_CONFIG_KEYS_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_CONFIG_KEYS_WARN', implode(', ', $missing_config_keys)));
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_PERMISSION_MODE'), $permission_mode === 'acl' ? $user->lang('ACP_DOWNLOADCENTER_DIAG_PERMISSION_MODE_ACL') : $user->lang('ACP_DOWNLOADCENTER_DIAG_PERMISSION_MODE_GLOBAL'));
        $support_forum_message = $support_forum_ok
            ? $user->lang('ACP_DOWNLOADCENTER_DIAG_SUPPORT_FORUM_OK', $support_forum_id)
            : ($support_forum_id > 0 ? $user->lang('ACP_DOWNLOADCENTER_DIAG_SUPPORT_FORUM_WARN', $support_forum_id) : $user->lang('ACP_DOWNLOADCENTER_DIAG_SUPPORT_FORUM_REQUIRED'));
        $this->assign_diagnostic_row($template, $support_forum_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_SUPPORT_FORUM'), $support_forum_message);
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_SUBMISSIONS'), $allow_submissions ? ($notifications_enabled ? $user->lang('ACP_DOWNLOADCENTER_DIAG_SUBMISSIONS_ENABLED_NOTIFY') : $user->lang('ACP_DOWNLOADCENTER_DIAG_SUBMISSIONS_ENABLED_NO_NOTIFY')) : $user->lang('ACP_DOWNLOADCENTER_DIAG_SUBMISSIONS_DISABLED'));
        $this->assign_diagnostic_row($template, $public_per_page_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_PUBLIC_PAGINATION'), $public_per_page_ok ? $user->lang('ACP_DOWNLOADCENTER_DIAG_PUBLIC_PAGINATION_OK', $public_per_page) : $user->lang('ACP_DOWNLOADCENTER_DIAG_PUBLIC_PAGINATION_WARN'));
        $this->assign_diagnostic_row($template, $storage_exists && $storage_writable, $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE'), $storage_exists ? ($storage_writable ? $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_OK', $storage_dir) : $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_NOT_WRITABLE', $storage_dir)) : $user->lang('ACP_DOWNLOADCENTER_DIAG_STORAGE_MISSING', $storage_dir));
        $this->assign_diagnostic_row($template, $htaccess_ok, $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS'), $htaccess_ok ? $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_HTACCESS_MISSING'));
        $this->assign_diagnostic_row($template, count($allowed_extensions) > 0, $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTENSIONS'), $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTENSIONS_INFO', implode(', ', $allowed_extensions)));
        $this->assign_diagnostic_row($template, $effective_php_limit >= $max_upload_bytes, $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT'), $effective_php_limit >= $max_upload_bytes ? $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT_OK', $this->format_file_size($max_upload_bytes)) : $user->lang('ACP_DOWNLOADCENTER_DIAG_UPLOAD_LIMIT_WARN', $this->format_file_size($max_upload_bytes), $this->format_file_size($effective_php_limit)));
        $this->assign_diagnostic_row($template, $missing_files === 0, $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES'), $missing_files === 0 ? $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_MISSING_FILES_WARN', $missing_files));
        $this->assign_diagnostic_row($template, $external_invalid === 0, $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTERNAL_URLS'), $external_invalid === 0 ? $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTERNAL_URLS_OK') : $user->lang('ACP_DOWNLOADCENTER_DIAG_EXTERNAL_URLS_WARN', $external_invalid));
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_ORPHAN_FILES'), $user->lang('ACP_DOWNLOADCENTER_DIAG_ORPHAN_FILES_INFO', $orphan_files));
        $this->assign_diagnostic_row($template, true, $user->lang('ACP_DOWNLOADCENTER_DIAG_CONTENT'), $user->lang('ACP_DOWNLOADCENTER_DIAG_CONTENT_INFO', $total_categories, $total_published, $total_pending));

        $template->assign_vars([
            'DIAG_STORAGE_DIR' => $storage_dir,
            'DIAG_EXTENSION_VERSION' => $installed_version !== '' ? $installed_version : $user->lang('ACP_DOWNLOADCENTER_DIAG_VERSION_UNKNOWN'),
            'DIAG_PERMISSION_MODE' => $permission_mode === 'acl' ? $user->lang('ACP_DOWNLOADCENTER_PERMISSION_MODE_ACL') : $user->lang('ACP_DOWNLOADCENTER_PERMISSION_MODE_GLOBAL'),
            'DIAG_TOTAL_FILES' => count($library_files),
            'DIAG_ORPHAN_FILES' => $orphan_files,
            'DIAG_MISSING_FILES' => $missing_files,
            'DIAG_PHP_UPLOAD_MAX' => $this->format_file_size($php_upload_max),
            'DIAG_PHP_POST_MAX' => $this->format_file_size($php_post_max),
            'DIAG_EXTENSION_LIMIT' => $this->format_file_size($max_upload_bytes),
            'DIAG_EXTERNAL_INVALID' => $external_invalid,
            'U_DIAGNOSTICS_REPORT' => $this->u_action_for_mode('diagnostics') . '&amp;export_report=1',
        ]);

        foreach ($prelaunch_checks as $check)
        {
            $template->assign_block_vars('prelaunch_checks', $check);
        }
    }


    protected function count_external_invalid_versions($db, $versions_table)
    {
        $invalid = 0;
        $sql = "SELECT download_url FROM " . $versions_table . " WHERE download_type = 'external'";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $url = trim((string) $row['download_url']);
            if ($url === '' || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL))
            {
                $invalid++;
            }
        }
        $db->sql_freeresult($result);

        return $invalid;
    }

    protected function build_prelaunch_checks($user, array $state)
    {
        $map = [
            'version_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_VERSION', 'ACP_DOWNLOADCENTER_PRELAUNCH_VERSION_EXPLAIN'],
            'config_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_CONFIG', 'ACP_DOWNLOADCENTER_PRELAUNCH_CONFIG_EXPLAIN'],
            'storage_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_STORAGE', 'ACP_DOWNLOADCENTER_PRELAUNCH_STORAGE_EXPLAIN'],
            'htaccess_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_HTACCESS', 'ACP_DOWNLOADCENTER_PRELAUNCH_HTACCESS_EXPLAIN'],
            'categories_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_CATEGORIES', 'ACP_DOWNLOADCENTER_PRELAUNCH_CATEGORIES_EXPLAIN'],
            'published_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_PUBLISHED', 'ACP_DOWNLOADCENTER_PRELAUNCH_PUBLISHED_EXPLAIN'],
            'missing_files_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_FILES', 'ACP_DOWNLOADCENTER_PRELAUNCH_FILES_EXPLAIN'],
            'external_urls_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_URLS', 'ACP_DOWNLOADCENTER_PRELAUNCH_URLS_EXPLAIN'],
            'pagination_ok' => ['ACP_DOWNLOADCENTER_PRELAUNCH_PAGINATION', 'ACP_DOWNLOADCENTER_PRELAUNCH_PAGINATION_EXPLAIN'],
        ];
        $checks = [];
        foreach ($map as $key => $labels)
        {
            $ok = !empty($state[$key]);
            $checks[] = [
                'STATUS_CLASS' => $ok ? 'ok' : 'warn',
                'STATUS' => $ok ? $user->lang('ACP_DOWNLOADCENTER_PRELAUNCH_OK') : $user->lang('ACP_DOWNLOADCENTER_PRELAUNCH_ATTENTION'),
                'TITLE' => $user->lang($labels[0]),
                'MESSAGE' => $user->lang($labels[1]),
            ];
        }

        return $checks;
    }

    protected function send_diagnostics_report($user, array $data)
    {
        $lines = [];
        $lines[] = 'MundophpBB Download Center - Diagnostic report';
        $lines[] = 'Generated: ' . date('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = 'Package version: ' . $data['package_version'];
        $lines[] = 'Installed version: ' . ($data['installed_version'] !== '' ? $data['installed_version'] : 'unknown');
        $lines[] = 'Permission mode: ' . $data['permission_mode'];
        $lines[] = 'Storage directory: ' . $data['storage_dir'];
        $lines[] = 'Storage writable: ' . (!empty($data['storage_writable']) ? 'yes' : 'no');
        $lines[] = '.htaccess present: ' . (!empty($data['htaccess_ok']) ? 'yes' : 'no');
        $lines[] = '';
        $lines[] = 'Content';
        $lines[] = '- Categories: ' . (int) $data['total_categories'];
        $lines[] = '- Published items: ' . (int) $data['total_published'];
        $lines[] = '- Pending items: ' . (int) $data['total_pending'];
        $lines[] = '- Missing local files: ' . (int) $data['missing_files'];
        $lines[] = '- Orphan local files: ' . (int) $data['orphan_files'];
        $lines[] = '- Invalid external URLs: ' . (int) $data['external_invalid'];
        $lines[] = '';
        $lines[] = 'Upload limits';
        $lines[] = '- upload_max_filesize: ' . $data['php_upload_max'];
        $lines[] = '- post_max_size: ' . $data['php_post_max'];
        $lines[] = '- extension limit: ' . $data['extension_limit'];
        $lines[] = '';
        $lines[] = 'Pre-release checklist';
        foreach ($data['prelaunch_checks'] as $check)
        {
            $lines[] = '- [' . ($check['STATUS_CLASS'] === 'ok' ? 'OK' : 'ATTENTION') . '] ' . $check['TITLE'] . ' - ' . $check['MESSAGE'];
        }

        $report = implode("\n", $lines) . "\n";
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="downloadcenter-diagnostic-report.txt"');
        header('Content-Length: ' . strlen($report));
        echo $report;
        garbage_collection();
        exit_handler();
    }



    protected function run_maintenance_rebuild($db, $user)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $fixed_items = 0;
        $fixed_versions = 0;
        $rebuilt_counters = 0;
        $cleaned_targets = 0;
        $storage_actions = 0;

        $storage_dir = $this->local_storage_directory();
        if (!is_dir($storage_dir) && @mkdir($storage_dir, 0777, true))
        {
            $storage_actions++;
        }

        $htaccess_path = $storage_dir . '.htaccess';
        if (is_dir($storage_dir) && !is_file($htaccess_path))
        {
            $htaccess = "Order Allow,Deny\nDeny from all\n";
            if (@file_put_contents($htaccess_path, $htaccess) !== false)
            {
                $storage_actions++;
            }
        }

        $sql = 'SELECT item_id, item_current_version_id
            FROM ' . $items_table . '
            ORDER BY item_id ASC';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $item_id = (int) $row['item_id'];
            $current_version_id = (int) $row['item_current_version_id'];
            $needs_current_fix = $current_version_id <= 0;

            if (!$needs_current_fix)
            {
                $sql_check = 'SELECT version_id
                    FROM ' . $versions_table . '
                    WHERE version_id = ' . (int) $current_version_id . '
                        AND item_id = ' . (int) $item_id . '
                        AND version_enabled = 1';
                $check_result = $db->sql_query_limit($sql_check, 1);
                $valid_current = (bool) $db->sql_fetchfield('version_id');
                $db->sql_freeresult($check_result);
                $needs_current_fix = !$valid_current;
            }

            if ($needs_current_fix)
            {
                $this->assign_fallback_current_version($db, $items_table, $versions_table, $item_id);
                $fixed_items++;
            }

            $rebuilt_counters += $this->rebuild_download_counters_for_item($db, $item_id);
        }
        $db->sql_freeresult($result);

        $sql = 'SELECT version_id, item_id, download_type, download_file, download_url
            FROM ' . $versions_table . "
            WHERE (download_type = 'external' AND download_file <> '')
                OR (download_type = 'local' AND download_url <> '')";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            if ((string) $row['download_type'] === 'external')
            {
                $sql_update = 'UPDATE ' . $versions_table . "
                    SET download_file = '', file_size = ''
                    WHERE version_id = " . (int) $row['version_id'];
            }
            else
            {
                $sql_update = 'UPDATE ' . $versions_table . "
                    SET download_url = ''
                    WHERE version_id = " . (int) $row['version_id'];
            }
            $db->sql_query($sql_update);
            $cleaned_targets++;
        }
        $db->sql_freeresult($result);

        $sql = 'SELECT v.version_id, v.item_id, v.download_type, v.download_file, v.download_url
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE i.item_id IS NULL';
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $this->delete_local_file_for_version($row, $db, $versions_table);
            $sql_delete_downloads = 'DELETE FROM ' . $this->table_prefix . 'downloadcenter_downloads
                WHERE version_id = ' . (int) $row['version_id'];
            $db->sql_query($sql_delete_downloads);
            $sql_delete_version = 'DELETE FROM ' . $versions_table . '
                WHERE version_id = ' . (int) $row['version_id'];
            $db->sql_query($sql_delete_version);
            $fixed_versions++;
        }
        $db->sql_freeresult($result);

        $this->add_log($db, $user, 'diagnostics_rebuild', $user->lang('ACP_DOWNLOADCENTER_LOG_DIAGNOSTICS_REBUILD', (string) $fixed_items, (string) $fixed_versions, (string) $rebuilt_counters, (string) $cleaned_targets, (string) $storage_actions));

        return [
            'items' => $fixed_items,
            'versions' => $fixed_versions,
            'counters' => $rebuilt_counters,
            'targets' => $cleaned_targets,
            'storage' => $storage_actions,
        ];
    }

    protected function handle_integrity($db, $request, $template, $user, $config, $phpbb_root_path, $phpEx)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $categories_table = $this->table_prefix . 'downloadcenter_categories';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';

        if ($request->is_set_post('integrity_fix'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_integrity'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action));
            }

            $fix_action = $request->variable('fix_action', '');
            $fix_id = $request->variable('fix_id', 0);
            $fixed_total = $this->run_integrity_fix($db, $user, $fix_action, $fix_id);

            trigger_error($user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_DONE', $fixed_total) . adm_back_link($this->u_action_for_mode('integrity')));
        }

        $issues_total = 0;
        $critical_total = 0;
        $warning_total = 0;
        $info_total = 0;

        $add_issue = function ($severity, $title, $details, $suggestion = '', $url = '', $fix_action = '', $fix_id = 0) use ($template, &$issues_total, &$critical_total, &$warning_total, &$info_total) {
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
                'FIX_ACTION' => $fix_action,
                'FIX_ID' => (int) $fix_id,
                'S_HAS_FIX' => $fix_action !== '',
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
            if (!$this->version_file_exists(['download_type' => 'local', 'download_file' => $row['download_file']]))
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
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_SCREENSHOT'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_SCREENSHOT_DETAILS', (int) $row['screenshot_id'], (int) $row['item_id'], (string) $row['image_file']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_SCREENSHOT'), '', 'delete_orphan_screenshot', (int) $row['screenshot_id']);
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
            $add_issue('info', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_DOWNLOAD'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_DOWNLOAD_DETAILS', (int) $row['download_id'], (int) $row['item_id'], (int) $row['version_id']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_DOWNLOAD'), '', 'delete_orphan_download', (int) $row['download_id']);
        }
        $db->sql_freeresult($result);

        // 10. Items whose explicit current version is invalid, disabled or belongs to another item.
        $sql = 'SELECT i.item_id, i.item_name, i.item_current_version_id, v.version_id, v.item_id AS version_item_id, v.version_enabled
            FROM ' . $items_table . ' i
            LEFT JOIN ' . $versions_table . ' v ON v.version_id = i.item_current_version_id
            WHERE i.item_current_version_id > 0
                AND (v.version_id IS NULL OR v.item_id <> i.item_id OR v.version_enabled = 0)
            ORDER BY i.item_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_CURRENT_VERSION'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_CURRENT_VERSION_DETAILS', (int) $row['item_id'], (string) $row['item_name'], (int) $row['item_current_version_id']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_CURRENT_VERSION'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'], 'fix_current_version', (int) $row['item_id']);
        }
        $db->sql_freeresult($result);

        // 11. Enabled versions whose parent item no longer exists.
        $sql = 'SELECT v.version_id, v.item_id, v.version_number
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE i.item_id IS NULL
            ORDER BY v.version_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('warning', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_VERSION'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_ORPHAN_VERSION_DETAILS', (int) $row['version_id'], (int) $row['item_id'], (string) $row['version_number']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_ORPHAN_VERSION'), '', 'delete_orphan_version', (int) $row['version_id']);
        }
        $db->sql_freeresult($result);

        // 12. External versions with invalid URL syntax.
        $sql = 'SELECT v.version_id, v.item_id, v.download_url, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE v.download_type = \'external\'
                AND v.download_url <> \'\'
            ORDER BY v.version_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $external_url = trim((string) $row['download_url']);
            if (!preg_match('#^https?://#i', $external_url) || !filter_var($external_url, FILTER_VALIDATE_URL))
            {
                $add_issue('critical', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_EXTERNAL_URL'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_INVALID_EXTERNAL_URL_DETAILS', (int) $row['version_id'], $external_url, (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_EXTERNAL_URL'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id']);
            }
        }
        $db->sql_freeresult($result);

        // 13. Versions with conflicting local/external target data left by older releases.
        $sql = 'SELECT v.version_id, v.item_id, v.download_type, v.download_file, v.download_url, i.item_name
            FROM ' . $versions_table . ' v
            LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
            WHERE (v.download_type = \'external\' AND v.download_file <> \'\')
                OR (v.download_type = \'local\' AND v.download_url <> \'\')
            ORDER BY v.version_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $add_issue('info', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MIXED_DOWNLOAD_TARGET'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_MIXED_DOWNLOAD_TARGET_DETAILS', (int) $row['version_id'], (string) $row['download_type'], (string) $row['item_name']), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_MIXED_DOWNLOAD_TARGET'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'], 'clean_mixed_target', (int) $row['version_id']);
        }
        $db->sql_freeresult($result);

        // 14. Item download counters out of sync with the sum of version counters.
        $sql = 'SELECT i.item_id, i.item_name, i.item_downloads, SUM(v.version_downloads) AS version_downloads_total
            FROM ' . $items_table . ' i
            LEFT JOIN ' . $versions_table . ' v ON v.item_id = i.item_id
            GROUP BY i.item_id, i.item_name, i.item_downloads
            ORDER BY i.item_id ASC';
        $result = $db->sql_query_limit($sql, 100);
        while ($row = $db->sql_fetchrow($result))
        {
            $item_downloads = (int) $row['item_downloads'];
            $version_downloads_total = (int) $row['version_downloads_total'];
            if ($item_downloads !== $version_downloads_total)
            {
                $add_issue('info', $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_DOWNLOAD_COUNTER_MISMATCH'), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_DOWNLOAD_COUNTER_MISMATCH_DETAILS', (int) $row['item_id'], (string) $row['item_name'], $item_downloads, $version_downloads_total), $user->lang('ACP_DOWNLOADCENTER_INTEGRITY_FIX_DOWNLOAD_COUNTER_MISMATCH'), $this->u_action_for_mode('items') . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'], 'rebuild_download_counters', (int) $row['item_id']);
            }
        }
        $db->sql_freeresult($result);

        // 15. Physical files not linked to any version.
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

    protected function run_integrity_fix($db, $user, $fix_action, $fix_id)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $screenshots_table = $this->table_prefix . 'downloadcenter_screenshots';

        $fix_id = (int) $fix_id;
        $fixed_total = 0;

        switch ($fix_action)
        {
            case 'fix_current_version':
                if ($fix_id > 0)
                {
                    $this->assign_fallback_current_version($db, $items_table, $versions_table, $fix_id);
                    $this->add_log($db, $user, 'integrity_fix_current_version', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_FIX_CURRENT_VERSION', (string) $fix_id), $fix_id);
                    $fixed_total = 1;
                }
            break;

            case 'clean_mixed_target':
                if ($fix_id > 0)
                {
                    $sql = 'SELECT * FROM ' . $versions_table . ' WHERE version_id = ' . (int) $fix_id;
                    $result = $db->sql_query_limit($sql, 1);
                    $version_row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);

                    if ($version_row)
                    {
                        if ((string) $version_row['download_type'] === 'external')
                        {
                            $sql = 'UPDATE ' . $versions_table . "
                                SET download_file = '', file_size = ''
                                WHERE version_id = " . (int) $fix_id;
                            $db->sql_query($sql);
                            $fixed_total = 1;
                        }
                        else if ((string) $version_row['download_type'] === 'local')
                        {
                            $sql = 'UPDATE ' . $versions_table . "
                                SET download_url = ''
                                WHERE version_id = " . (int) $fix_id;
                            $db->sql_query($sql);
                            $fixed_total = 1;
                        }

                        if ($fixed_total)
                        {
                            $this->add_log($db, $user, 'integrity_clean_mixed_target', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_CLEAN_MIXED_TARGET', (string) $fix_id), (int) $version_row['item_id'], $fix_id);
                        }
                    }
                }
            break;

            case 'rebuild_download_counters':
                if ($fix_id > 0)
                {
                    $fixed_total = $this->rebuild_download_counters_for_item($db, $fix_id);
                    $this->add_log($db, $user, 'integrity_rebuild_download_counters', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_REBUILD_DOWNLOAD_COUNTERS', (string) $fix_id), $fix_id);
                }
            break;

            case 'delete_orphan_download':
                if ($fix_id > 0)
                {
                    $sql = 'DELETE FROM ' . $downloads_table . '
                        WHERE download_id = ' . (int) $fix_id . '
                            AND (item_id NOT IN (SELECT item_id FROM ' . $items_table . ')
                                OR version_id NOT IN (SELECT version_id FROM ' . $versions_table . '))';
                    $db->sql_query($sql);
                    $fixed_total = (int) $db->sql_affectedrows();
                    if ($fixed_total)
                    {
                        $this->add_log($db, $user, 'integrity_delete_orphan_download', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_DELETE_ORPHAN_DOWNLOAD', (string) $fix_id));
                    }
                }
            break;

            case 'delete_orphan_screenshot':
                if ($fix_id > 0)
                {
                    $sql = 'SELECT s.* FROM ' . $screenshots_table . ' s
                        LEFT JOIN ' . $items_table . ' i ON i.item_id = s.item_id
                        WHERE s.screenshot_id = ' . (int) $fix_id . '
                            AND i.item_id IS NULL';
                    $result = $db->sql_query_limit($sql, 1);
                    $screenshot_row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);

                    if ($screenshot_row)
                    {
                        if (!empty($screenshot_row['image_file']))
                        {
                            $path = $this->screenshot_file_path($screenshot_row['image_file']);
                            if (is_file($path))
                            {
                                @unlink($path);
                            }
                        }

                        $sql = 'DELETE FROM ' . $screenshots_table . ' WHERE screenshot_id = ' . (int) $fix_id;
                        $db->sql_query($sql);
                        $fixed_total = (int) $db->sql_affectedrows();
                        if ($fixed_total)
                        {
                            $this->add_log($db, $user, 'integrity_delete_orphan_screenshot', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_DELETE_ORPHAN_SCREENSHOT', (string) $fix_id));
                        }
                    }
                }
            break;

            case 'delete_orphan_version':
                if ($fix_id > 0)
                {
                    $sql = 'SELECT v.* FROM ' . $versions_table . ' v
                        LEFT JOIN ' . $items_table . ' i ON i.item_id = v.item_id
                        WHERE v.version_id = ' . (int) $fix_id . '
                            AND i.item_id IS NULL';
                    $result = $db->sql_query_limit($sql, 1);
                    $version_row = $db->sql_fetchrow($result);
                    $db->sql_freeresult($result);

                    if ($version_row)
                    {
                        $this->delete_local_file_for_version($version_row, $db, $versions_table);
                        $sql = 'DELETE FROM ' . $downloads_table . ' WHERE version_id = ' . (int) $fix_id;
                        $db->sql_query($sql);
                        $sql = 'DELETE FROM ' . $versions_table . ' WHERE version_id = ' . (int) $fix_id;
                        $db->sql_query($sql);
                        $fixed_total = 1;
                        $this->add_log($db, $user, 'integrity_delete_orphan_version', $user->lang('ACP_DOWNLOADCENTER_LOG_INTEGRITY_DELETE_ORPHAN_VERSION', (string) $fix_id));
                    }
                }
            break;
        }

        return $fixed_total;
    }

    protected function rebuild_download_counters_for_item($db, $item_id)
    {
        $versions_table = $this->table_prefix . 'downloadcenter_versions';
        $downloads_table = $this->table_prefix . 'downloadcenter_downloads';
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $updated_total = 0;

        $sql = 'SELECT version_id
            FROM ' . $versions_table . '
            WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $version_id = (int) $row['version_id'];
            $sql_count = 'SELECT COUNT(download_id) AS total_downloads
                FROM ' . $downloads_table . '
                WHERE version_id = ' . (int) $version_id;
            $count_result = $db->sql_query($sql_count);
            $version_total = (int) $db->sql_fetchfield('total_downloads');
            $db->sql_freeresult($count_result);

            $sql_update = 'UPDATE ' . $versions_table . '
                SET version_downloads = ' . (int) $version_total . '
                WHERE version_id = ' . (int) $version_id;
            $db->sql_query($sql_update);
            $updated_total++;
        }
        $db->sql_freeresult($result);

        $sql_count = 'SELECT COUNT(download_id) AS total_downloads
            FROM ' . $downloads_table . '
            WHERE item_id = ' . (int) $item_id;
        $count_result = $db->sql_query($sql_count);
        $item_total = (int) $db->sql_fetchfield('total_downloads');
        $db->sql_freeresult($count_result);

        $sql_update = 'UPDATE ' . $items_table . '
            SET item_downloads = ' . (int) $item_total . '
            WHERE item_id = ' . (int) $item_id;
        $db->sql_query($sql_update);

        return $updated_total + 1;
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

            $name = trim($request->variable('category_name', '', true));
            $desc = trim($request->variable('category_desc', '', true));
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
                    $was_current_version = $this->is_current_version_for_item($db, $items_table, $item_id, (int) $version_id);
                    $this->delete_local_file_for_version($version_row, $db, $versions_table);
                    $sql = 'DELETE FROM ' . $downloads_table . ' WHERE version_id = ' . (int) $version_id;
                    $db->sql_query($sql);
                    $sql = 'DELETE FROM ' . $versions_table . ' WHERE version_id = ' . (int) $version_id;
                    $db->sql_query($sql);

                    if ($was_current_version)
                    {
                        $this->assign_fallback_current_version($db, $items_table, $versions_table, $item_id);
                    }

                    $this->sync_item_download_count($db, $item_id);
                    $this->add_log($db, $user, 'version_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_DELETED', (string) $version_id), $item_id, $version_id);
                    $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
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

        if ($action === 'set_current_version' && $version_id > 0)
        {
            $sql = 'SELECT * FROM ' . $versions_table . ' WHERE version_id = ' . (int) $version_id;
            $result = $db->sql_query_limit($sql, 1);
            $version_row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$version_row)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action));
            }

            $item_id = (int) $version_row['item_id'];
            $sql = 'UPDATE ' . $items_table . '
                SET item_current_version_id = ' . (int) $version_id . ', item_updated = ' . (int) $time . '
                WHERE item_id = ' . (int) $item_id;
            $db->sql_query($sql);
            $this->add_log($db, $user, 'version_set_current', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_SET_CURRENT', (string) $version_id), $item_id, $version_id);
            $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);

            trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_SET_CURRENT') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
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
                if ($approved)
                {
                    $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
                }

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
                'image_caption' => trim($request->variable('screenshot_caption', '', true)),
                'image_order' => max(0, $request->variable('screenshot_order', 0)),
                'image_created' => $time,
            ];
            $sql = 'INSERT INTO ' . $screenshots_table . ' ' . $db->sql_build_array('INSERT', $screenshot_data);
            $db->sql_query($sql);
            $this->add_log($db, $user, 'screenshot_created', $user->lang('ACP_DOWNLOADCENTER_LOG_SCREENSHOT_CREATED', (string) $item_id), $item_id);

            $this->redirect_to_acp_anchor($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id . '&amp;screenshot_status=added#downloadcenter-screenshots');
        }

        if ($request->is_set_post('submit_version'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            if ($item_id <= 0)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_SAVE_ITEM_BEFORE_VERSION') . adm_back_link($this->u_action));
            }

            $edit_version_id = $request->variable('edit_version_id', 0);
            $editing_existing_version = false;
            $previous_version_row = false;
            if ($edit_version_id > 0)
            {
                $sql = 'SELECT * FROM ' . $versions_table . '
                    WHERE version_id = ' . (int) $edit_version_id . '
                        AND item_id = ' . (int) $item_id;
                $result = $db->sql_query_limit($sql, 1);
                $previous_version_row = $db->sql_fetchrow($result);
                $db->sql_freeresult($result);

                if (!$previous_version_row)
                {
                    trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
                }
                $editing_existing_version = true;
            }

            $version_number = trim($request->variable('version_number', '', true));
            $download_type = $request->variable('download_type', 'external');
            $download_url = trim($request->variable('download_url', '', true));
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
                if ($this->is_phpbb_attachment_reference($download_file))
                {
                    $existing_attachment = $this->get_phpbb_attachment_file($download_file);
                    if (!$existing_attachment)
                    {
                        trigger_error($user->lang('ACP_DOWNLOADCENTER_EXISTING_FILE_NOT_FOUND') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
                    }
                    $file_size = $this->format_file_size((int) $existing_attachment['filesize']);
                }
                else
                {
                    $existing_path = $this->local_file_path($download_file);
                    if (!is_file($existing_path))
                    {
                        trigger_error($user->lang('ACP_DOWNLOADCENTER_EXISTING_FILE_NOT_FOUND') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
                    }
                    if (!$this->is_allowed_existing_file($download_file))
                    {
                        trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', $this->get_allowed_extensions_string()) . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
                    }
                    $file_size = $this->format_file_size(filesize($existing_path));
                }
            }
            else if ($editing_existing_version && $download_type === 'local' && !empty($previous_version_row['download_file']))
            {
                $download_file = (string) $previous_version_row['download_file'];
                $file_size = (string) $previous_version_row['file_size'];
            }

            if ($version_number === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            if ($download_type === 'external' && $download_url === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_EXTERNAL_URL_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            if ($download_type === 'external' && (!preg_match('#^https?://#i', $download_url) || !filter_var($download_url, FILTER_VALIDATE_URL)))
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_EXTERNAL_URL_INVALID') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            if ($download_type === 'local' && $download_file === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_LOCAL_FILE_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            if ($download_type === 'external')
            {
                // Keep external versions clean: the URL is the only download target.
                $download_file = '';
                $file_size = '';
            }
            else
            {
                // Keep local versions clean: the stored file is the only download target.
                $download_url = '';
            }

            $version_data = [
                'item_id' => $item_id,
                'version_number' => $version_number,
                'phpbb_version' => trim($request->variable('phpbb_version', '', true)),
                'php_version' => trim($request->variable('php_version', '', true)),
                'version_changelog' => trim($request->variable('version_changelog', '', true)),
                'download_type' => $download_type,
                'download_url' => $download_url,
                'download_file' => $download_file,
                'file_size' => $file_size,
                'version_enabled' => 1,
            ];

            if ($editing_existing_version)
            {
                $sql = 'UPDATE ' . $versions_table . ' SET ' . $db->sql_build_array('UPDATE', $version_data) . '
                    WHERE version_id = ' . (int) $edit_version_id . '
                        AND item_id = ' . (int) $item_id;
                $db->sql_query($sql);
                $saved_version_id = (int) $edit_version_id;
                $this->add_log($db, $user, 'version_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_UPDATED', $version_number), $item_id, $saved_version_id);
            }
            else
            {
                $version_data['version_created'] = $time;
                $sql = 'INSERT INTO ' . $versions_table . ' ' . $db->sql_build_array('INSERT', $version_data);
                $db->sql_query($sql);
                $saved_version_id = (int) $db->sql_nextid();
                $this->add_log($db, $user, 'version_created', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_CREATED', $version_number), $item_id, $saved_version_id);

                $sql = 'UPDATE ' . $items_table . '
                    SET item_current_version_id = ' . (int) $saved_version_id . ', item_updated = ' . (int) $time . '
                    WHERE item_id = ' . (int) $item_id;
                $db->sql_query($sql);
            }

            if ($editing_existing_version)
            {
                $sql = 'UPDATE ' . $items_table . '
                    SET item_updated = ' . (int) $time . '
                    WHERE item_id = ' . (int) $item_id;
                $db->sql_query($sql);
            }

            $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);

            $message_key = $editing_existing_version ? 'ACP_DOWNLOADCENTER_VERSION_UPDATED' : 'ACP_DOWNLOADCENTER_VERSION_SAVED';
            trigger_error($user->lang($message_key) . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
        }

        if ($request->is_set_post('submit_current_changelog'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            if ($item_id <= 0)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_SAVE_ITEM_BEFORE_VERSION') . adm_back_link($this->u_action));
            }

            $latest_version_id = $request->variable('latest_version_id', 0);
            $latest_changelog = trim($request->variable('latest_version_changelog', '', true));

            if ($latest_version_id <= 0)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            $sql = 'SELECT version_id FROM ' . $versions_table . '
                WHERE version_id = ' . (int) $latest_version_id . '
                    AND item_id = ' . (int) $item_id;
            $result = $db->sql_query_limit($sql, 1);
            $latest_row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$latest_row)
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_VERSION_REQUIRED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
            }

            $sql = 'UPDATE ' . $versions_table . "
                SET version_changelog = '" . $db->sql_escape($latest_changelog) . "'
                WHERE version_id = " . (int) $latest_version_id;
            $db->sql_query($sql);
            $this->add_log($db, $user, 'version_changelog_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_CHANGELOG_UPDATED', (string) $latest_version_id), $item_id, $latest_version_id);

            $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);

            trigger_error($user->lang('ACP_DOWNLOADCENTER_CHANGELOG_SAVED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
        }

        if ($request->is_set_post('submit_item'))
        {
            if (!check_form_key('mundophpbb_downloadcenter_items'))
            {
                trigger_error('FORM_INVALID');
            }

            $name = trim($request->variable('item_name', '', true));
            if ($name === '')
            {
                trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_NAME_REQUIRED') . adm_back_link($this->u_action));
            }

            $item_icon = $this->build_item_icon_value($db, $request, $user, $item_id, $screenshots_table);

            $data = [
                'category_id' => max(0, $request->variable('category_id', 0)),
                'topic_id' => max(0, $request->variable('topic_id', 0)),
                'item_name' => $name,
                'item_slug' => $this->slugify($name),
                'item_short_desc' => trim($request->variable('item_short_desc', '', true)),
                'item_desc' => trim($request->variable('item_desc', '', true)),
                'item_icon' => $item_icon,
                'item_enabled' => $request->variable('item_enabled', 0),
                'item_approved' => $request->variable('item_approved', 0),
                'item_updated' => $time,
            ];

            if ($item_id > 0)
            {
                $sql = 'UPDATE ' . $items_table . ' SET ' . $db->sql_build_array('UPDATE', $data) . ' WHERE item_id = ' . (int) $item_id;
                $db->sql_query($sql);
                $this->add_log($db, $user, 'item_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_UPDATED', $name), $item_id);
            }
            else
            {
                $data['user_id'] = (int) $user->data['user_id'];
                $data['item_created'] = $time;
                $sql = 'INSERT INTO ' . $items_table . ' ' . $db->sql_build_array('INSERT', $data);
                $db->sql_query($sql);
                $item_id = (int) $db->sql_nextid();
                $this->add_log($db, $user, 'item_created', $user->lang('ACP_DOWNLOADCENTER_LOG_ITEM_CREATED', $name), $item_id);
            }

            $support_topic_id = $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
            if ($support_topic_id > 0)
            {
                $data['topic_id'] = $support_topic_id;
            }

            trigger_error($user->lang('ACP_DOWNLOADCENTER_ITEM_SAVED') . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
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
            'item_current_version_id' => 0,
            'item_name' => '',
            'item_short_desc' => '',
            'item_desc' => '',
            'item_icon' => '',
            'item_enabled' => 1,
            'item_approved' => 1,
        ];

        if (($action === 'edit' || $action === 'edit_version') && $item_id > 0)
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
            $edit_latest_version = $this->get_latest_version_for_item($db, $versions_table, (int) $edit_item['item_id']);
            if (!$edit_latest_version)
            {
                $edit_latest_version = false;
            }
        }

        $edit_version = [
            'version_id' => 0,
            'version_number' => '',
            'phpbb_version' => 'phpBB 3.3.x',
            'php_version' => 'PHP >= 8.1',
            'download_type' => 'external',
            'download_url' => '',
            'download_file' => '',
            'version_changelog' => '',
        ];
        if ($action === 'edit_version' && (int) $edit_item['item_id'] > 0 && $version_id > 0)
        {
            $sql = 'SELECT * FROM ' . $versions_table . '
                WHERE version_id = ' . (int) $version_id . '
                    AND item_id = ' . (int) $edit_item['item_id'];
            $result = $db->sql_query_limit($sql, 1);
            $edit_version_row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($edit_version_row)
            {
                $edit_version = array_merge($edit_version, $edit_version_row);
            }
        }

        if ((int) $edit_item['item_id'] > 0)
        {
            $sql = 'SELECT * FROM ' . $versions_table . ' WHERE item_id = ' . (int) $edit_item['item_id'] . ' ORDER BY version_created DESC, version_id DESC';
            $result = $db->sql_query($sql);
            while ($version_row = $db->sql_fetchrow($result))
            {
                $version_target_status = $this->get_version_target_status($version_row, $user);
                $download_target = ($version_row['download_type'] === 'external') ? trim((string) $version_row['download_url']) : trim((string) $version_row['download_file']);

                $template->assign_block_vars('version_history', [
                    'VERSION_ID' => (int) $version_row['version_id'],
                    'S_CURRENT_VERSION' => ($edit_latest_version && (int) $edit_latest_version['version_id'] === (int) $version_row['version_id']),
                    'VERSION_NUMBER' => $this->clean_version_label($version_row['version_number']),
                    'PHPBB_VERSION' => $version_row['phpbb_version'],
                    'PHP_VERSION' => $version_row['php_version'],
                    'DOWNLOAD_TYPE' => $version_row['download_type'] === 'local' ? $user->lang('ACP_DOWNLOADCENTER_DOWNLOAD_TYPE_LOCAL') : $user->lang('ACP_DOWNLOADCENTER_DOWNLOAD_TYPE_EXTERNAL'),
                    'DOWNLOAD_TARGET' => $download_target !== '' ? $download_target : '-',
                    'DOWNLOAD_TARGET_STATUS' => $version_target_status['label'],
                    'DOWNLOAD_TARGET_STATUS_EXPLAIN' => $version_target_status['explain'],
                    'DOWNLOAD_TARGET_STATUS_CLASS' => $version_target_status['class'],
                    'FILE_SIZE' => $version_row['file_size'] ?: '-',
                    'DOWNLOADS' => (int) $version_row['version_downloads'],
                    'CREATED' => $user->format_date((int) $version_row['version_created']),
                    'CHANGELOG' => $version_row['version_changelog'],
                    'S_LOCAL_FILE' => $version_row['download_type'] === 'local',
                    'S_EXTERNAL_LINK' => $version_row['download_type'] === 'external',
                    'S_EXTERNAL_LINK_VALID' => $version_target_status['code'] === 'external_ok',
                    'S_FILE_EXISTS' => $this->version_file_exists($version_row),
                    'S_FILE_MISSING' => ($version_row['download_type'] === 'local' && !$this->version_file_exists($version_row)),
                    'U_EXTERNAL_LINK' => $version_row['download_type'] === 'external' ? $download_target : '',
                    'U_EDIT' => $this->u_action . '&amp;action=edit_version&amp;item_id=' . (int) $edit_item['item_id'] . '&amp;version_id=' . (int) $version_row['version_id'] . '#downloadcenter-version-data',
                    'U_SET_CURRENT' => $this->u_action . '&amp;action=set_current_version&amp;item_id=' . (int) $edit_item['item_id'] . '&amp;version_id=' . (int) $version_row['version_id'],
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
            $latest_version = $this->get_latest_version_for_item($db, $versions_table, (int) $row['item_id']);
            $operational_status = $this->get_item_operational_status($row, $latest_version, $config, $user);

            $template->assign_block_vars('items', [
                'ITEM_ID' => (int) $row['item_id'],
                'ITEM_NAME' => $row['item_name'],
                'CATEGORY_NAME' => $row['category_name'] ?: '-',
                'USERNAME' => $row['username'] ?: '-',
                'LATEST_VERSION' => !empty($latest_version['version_number']) ? $this->clean_version_label($latest_version['version_number']) : '-',
                'OPERATIONAL_STATUS' => $operational_status['label'],
                'OPERATIONAL_STATUS_EXPLAIN' => $operational_status['explain'],
                'OPERATIONAL_STATUS_CLASS' => $operational_status['class'],
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

        $this->assign_file_library_options($db, $template, $edit_version ? (string) $edit_version['download_file'] : ($edit_latest_version ? (string) $edit_latest_version['download_file'] : ''));
        $this->assign_item_image_options($template, $edit_item['item_icon']);

        $screenshot_status = $request->variable('screenshot_status', '');
        $edit_operational_status = ((int) $edit_item['item_id'] > 0) ? $this->get_item_operational_status($edit_item, $edit_latest_version ?: [], $config, $user) : [
            'label' => '',
            'explain' => '',
            'class' => 'neutral',
            'code' => '',
        ];

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
            'EDIT_VERSION_ID' => (int) $edit_version['version_id'],
            'S_EDITING_VERSION' => (int) $edit_version['version_id'] > 0,
            'EDIT_VERSION_NUMBER_VALUE' => $edit_version['version_number'],
            'EDIT_VERSION_PHPBB_VERSION' => $edit_version['phpbb_version'],
            'EDIT_VERSION_PHP_VERSION' => $edit_version['php_version'],
            'EDIT_VERSION_DOWNLOAD_TYPE' => $edit_version['download_type'],
            'EDIT_VERSION_DOWNLOAD_URL' => $edit_version['download_url'],
            'EDIT_VERSION_CHANGELOG' => $edit_version['version_changelog'],
            'S_EDIT_VERSION_EXTERNAL' => $edit_version['download_type'] === 'external',
            'S_EDIT_VERSION_LOCAL' => $edit_version['download_type'] === 'local',
            'U_CANCEL_VERSION_EDIT' => $this->u_action . '&amp;action=edit&amp;item_id=' . (int) $edit_item['item_id'] . '#downloadcenter-version-data',
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
            'EDIT_OPERATIONAL_STATUS' => $edit_operational_status['label'],
            'EDIT_OPERATIONAL_STATUS_EXPLAIN' => $edit_operational_status['explain'],
            'EDIT_OPERATIONAL_STATUS_CLASS' => $edit_operational_status['class'],
            'EDIT_OPERATIONAL_STATUS_CODE' => $edit_operational_status['code'],
            'S_EDIT_HAS_OPERATIONAL_STATUS' => ((int) $edit_item['item_id'] > 0),
            'S_EDIT_STATUS_READY' => $edit_operational_status['code'] === 'ready',
            'S_EDIT_STATUS_DISABLED' => $edit_operational_status['code'] === 'disabled',
            'S_EDIT_STATUS_PENDING' => $edit_operational_status['code'] === 'pending',
            'S_EDIT_STATUS_NO_VERSION' => $edit_operational_status['code'] === 'no_version',
            'S_EDIT_STATUS_FILE_MISSING' => $edit_operational_status['code'] === 'file_missing',
            'S_EDIT_STATUS_EMPTY_LOCAL_FILE' => $edit_operational_status['code'] === 'empty_local_file',
            'S_EDIT_STATUS_EXTERNAL_INVALID' => $edit_operational_status['code'] === 'external_invalid',
            'S_EDIT_STATUS_ADMIN_ONLY' => $edit_operational_status['code'] === 'admin_only',
        ]);
    }




    protected function build_item_icon_value($db, $request, $user, $item_id, $screenshots_table)
    {
        $current = trim($request->variable('item_icon_current', '', true));
        $icon = $current;

        if ($request->variable('item_icon_clear', 0))
        {
            return '';
        }

        $external_url = trim($request->variable('item_icon_url', '', true));
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
        if (!is_array($version_row) || (string) $version_row['download_type'] !== 'local')
        {
            return false;
        }

        $file_name = trim((string) $version_row['download_file']);
        if ($file_name === '')
        {
            return false;
        }

        if ($this->is_phpbb_attachment_reference($file_name))
        {
            $attachment = $this->get_phpbb_attachment_file($file_name);
            return !empty($attachment['path']) && is_file($attachment['path']);
        }

        return is_file($this->local_file_path($file_name));
    }

    protected function get_version_target_status(array $version_row, $user)
    {
        $download_type = isset($version_row['download_type']) ? (string) $version_row['download_type'] : 'local';

        if ($download_type === 'external')
        {
            $external_url = trim((string) $version_row['download_url']);
            if ($external_url === '')
            {
                return [
                    'code' => 'external_missing',
                    'class' => 'critical',
                    'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_MISSING'),
                    'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_MISSING_EXPLAIN'),
                ];
            }
            if (!preg_match('#^https?://#i', $external_url) || !filter_var($external_url, FILTER_VALIDATE_URL))
            {
                return [
                    'code' => 'external_invalid',
                    'class' => 'critical',
                    'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_INVALID'),
                    'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_INVALID_EXPLAIN'),
                ];
            }

            return [
                'code' => 'external_ok',
                'class' => 'ok',
                'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_OK'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_EXTERNAL_OK_EXPLAIN'),
            ];
        }

        $local_file = trim((string) $version_row['download_file']);
        if ($local_file === '')
        {
            return [
                'code' => 'local_missing_name',
                'class' => 'critical',
                'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_EMPTY'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_EMPTY_EXPLAIN'),
            ];
        }

        if (!$this->version_file_exists($version_row))
        {
            return [
                'code' => 'local_missing_file',
                'class' => 'critical',
                'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_MISSING'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_MISSING_EXPLAIN'),
            ];
        }

        return [
            'code' => 'local_ok',
            'class' => 'ok',
            'label' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_OK'),
            'explain' => $user->lang('ACP_DOWNLOADCENTER_VERSION_TARGET_LOCAL_OK_EXPLAIN'),
        ];
    }

    protected function get_item_operational_status(array $item, array $latest_version, $config, $user)
    {
        if (empty($item['item_enabled']))
        {
            return [
                'code' => 'disabled',
                'class' => 'neutral',
                'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_DISABLED'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_DISABLED_EXPLAIN'),
            ];
        }

        if (empty($item['item_approved']))
        {
            return [
                'code' => 'pending',
                'class' => 'warning',
                'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_PENDING'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_PENDING_EXPLAIN'),
            ];
        }

        if (empty($latest_version))
        {
            return [
                'code' => 'no_version',
                'class' => 'warning',
                'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_NO_VERSION'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_NO_VERSION_EXPLAIN'),
            ];
        }

        $download_type = isset($latest_version['download_type']) ? (string) $latest_version['download_type'] : 'local';
        if ($download_type === 'external')
        {
            $external_url = trim((string) $latest_version['download_url']);
            if ($external_url === '' || !preg_match('#^https?://#i', $external_url) || !filter_var($external_url, FILTER_VALIDATE_URL))
            {
                return [
                    'code' => 'external_invalid',
                    'class' => 'critical',
                    'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_EXTERNAL_INVALID'),
                    'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_EXTERNAL_INVALID_EXPLAIN'),
                ];
            }
        }
        else
        {
            if (trim((string) $latest_version['download_file']) === '')
            {
                return [
                    'code' => 'empty_local_file',
                    'class' => 'critical',
                    'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_EMPTY_LOCAL_FILE'),
                    'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_EMPTY_LOCAL_FILE_EXPLAIN'),
                ];
            }

            if (!$this->version_file_exists($latest_version))
            {
                return [
                    'code' => 'file_missing',
                    'class' => 'critical',
                    'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_FILE_MISSING'),
                    'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_FILE_MISSING_EXPLAIN'),
                ];
            }
        }

        $view_access = isset($config['mundophpbb_downloadcenter_view_access']) ? (string) $config['mundophpbb_downloadcenter_view_access'] : 'all';
        $download_access = $this->inherit_access_floor($view_access, isset($config['mundophpbb_downloadcenter_download_access']) ? (string) $config['mundophpbb_downloadcenter_download_access'] : 'registered');
        if ($view_access === 'admin' || $download_access === 'admin')
        {
            return [
                'code' => 'admin_only',
                'class' => 'warning',
                'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_ADMIN_ONLY'),
                'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_ADMIN_ONLY_EXPLAIN'),
            ];
        }

        return [
            'code' => 'ready',
            'class' => 'ok',
            'label' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_READY'),
            'explain' => $user->lang('ACP_DOWNLOADCENTER_OPERATIONAL_READY_EXPLAIN'),
        ];
    }

    protected function local_file_path($file_name)
    {
        return $this->local_storage_directory() . basename((string) $file_name);
    }

    protected function delete_local_file_for_version($version_row, $db = null, $versions_table = '')
    {
        if (!is_array($version_row) || (string) $version_row['download_type'] !== 'local' || trim((string) $version_row['download_file']) === '')
        {
            return false;
        }

        $file_name = (string) $version_row['download_file'];
        if ($db && $versions_table)
        {
            $file_name_sql = $db->sql_escape($file_name);
            $version_id = isset($version_row['version_id']) ? (int) $version_row['version_id'] : 0;
            $sql = 'SELECT COUNT(version_id) AS total
                FROM ' . $versions_table . "
                WHERE download_type = 'local'
                    AND download_file = '" . $file_name_sql . "'
                    AND version_id <> " . $version_id;
            $result = $db->sql_query($sql);
            $total = (int) $db->sql_fetchfield('total');
            $db->sql_freeresult($result);

            if ($total > 0)
            {
                return false;
            }
        }

        if ($this->is_phpbb_attachment_reference($file_name))
        {
            return $this->delete_phpbb_attachment_reference($file_name);
        }

        $path = $this->local_file_path($file_name);
        if (is_file($path))
        {
            return @unlink($path);
        }

        return false;
    }

    protected function is_current_version_for_item($db, $items_table, $item_id, $version_id)
    {
        $sql = 'SELECT item_current_version_id
            FROM ' . $items_table . '
            WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query_limit($sql, 1);
        $current_version_id = (int) $db->sql_fetchfield('item_current_version_id');
        $db->sql_freeresult($result);

        return $current_version_id === (int) $version_id;
    }

    protected function assign_fallback_current_version($db, $items_table, $versions_table, $item_id)
    {
        $sql = 'SELECT version_id
            FROM ' . $versions_table . '
            WHERE item_id = ' . (int) $item_id . '
                AND version_enabled = 1
            ORDER BY version_created DESC, version_id DESC';
        $result = $db->sql_query_limit($sql, 1);
        $fallback_version_id = (int) $db->sql_fetchfield('version_id');
        $db->sql_freeresult($result);

        $sql = 'UPDATE ' . $items_table . '
            SET item_current_version_id = ' . (int) $fallback_version_id . ',
                item_updated = ' . time() . '
            WHERE item_id = ' . (int) $item_id;
        $db->sql_query($sql);

        return $fallback_version_id;
    }

    protected function delete_local_files_for_item($db, $versions_table, $item_id)
    {
        $deleted_files = [];
        $sql = 'SELECT download_file
            FROM ' . $versions_table . "
            WHERE item_id = " . (int) $item_id . "
                AND download_type = 'local'
                AND download_file <> ''";
        $result = $db->sql_query($sql);
        while ($version_row = $db->sql_fetchrow($result))
        {
            $file_name = isset($version_row['download_file']) ? (string) $version_row['download_file'] : '';
            if ($file_name === '' || isset($deleted_files[$file_name]))
            {
                continue;
            }

            $sql_count = 'SELECT COUNT(version_id) AS total
                FROM ' . $versions_table . "
                WHERE download_type = 'local'
                    AND download_file = '" . $db->sql_escape($file_name) . "'
                    AND item_id <> " . (int) $item_id;
            $count_result = $db->sql_query($sql_count);
            $external_references = (int) $db->sql_fetchfield('total');
            $db->sql_freeresult($count_result);

            if ($external_references === 0)
            {
                if ($this->is_phpbb_attachment_reference($file_name))
                {
                    $this->delete_phpbb_attachment_reference($file_name);
                }
                else
                {
                    $path = $this->local_file_path($file_name);
                    if (is_file($path))
                    {
                        @unlink($path);
                    }
                }
            }

            $deleted_files[$file_name] = true;
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

        if ($action === 'delete_orphan_files')
        {
            if (confirm_box(true))
            {
                $deleted = 0;
                $files_for_cleanup = $this->get_local_file_library($db, true);
                foreach ($files_for_cleanup as $file)
                {
                    $candidate = basename((string) ($file['filename'] ?? ''));
                    if ($candidate === '' || !empty($file['used']))
                    {
                        continue;
                    }

                    if (!empty($this->get_file_usage($db, $candidate)))
                    {
                        continue;
                    }

                    $path = $this->local_file_path($candidate);
                    if (is_file($path) && @unlink($path))
                    {
                        $deleted++;
                    }
                }

                if ($deleted > 0)
                {
                    $this->add_log($db, $user, 'orphan_files_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_ORPHAN_FILES_DELETED', $deleted));
                }

                trigger_error($user->lang('ACP_DOWNLOADCENTER_ORPHAN_FILES_DELETED', $deleted) . adm_back_link($this->u_action_for_mode('files')));
            }
            else
            {
                confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_ORPHAN_FILES'), build_hidden_fields([
                    'action' => 'delete_orphan_files',
                ]));
            }
        }

        $query = trim($request->variable('file_q', '', true));
        $status_filter = $request->variable('file_status', 'all');
        if (!in_array($status_filter, ['all', 'used', 'orphan'], true))
        {
            $status_filter = 'all';
        }
        $start = max(0, $request->variable('start', 0));
        $per_page = max(1, (int) ($config['mundophpbb_downloadcenter_acp_per_page'] ?? 20));

        $files = $this->get_local_file_library($db, true);
        if ($status_filter === 'used')
        {
            $files = array_values(array_filter($files, static function ($file) {
                return !empty($file['used']);
            }));
        }
        else if ($status_filter === 'orphan')
        {
            $files = array_values(array_filter($files, static function ($file) {
                return empty($file['used']);
            }));
        }

        if ($query !== '')
        {
            $needle = utf8_strtolower($query);
            $files = array_values(array_filter($files, static function ($file) use ($needle) {
                return strpos(utf8_strtolower($file['filename']), $needle) !== false
                    || strpos(utf8_strtolower($file['display_name']), $needle) !== false
                    || strpos(utf8_strtolower($file['usage_label']), $needle) !== false
                    || strpos(utf8_strtolower($file['extension']), $needle) !== false;
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
                'EXTENSION' => $file['extension'],
                'MODIFIED' => $file['modified'],
                'USAGE_LABEL' => $file['usage_label'],
                'USAGE_COUNT' => (int) ($file['usage_count'] ?? 0),
                'S_USED' => !empty($file['used']),
                'U_DELETE' => $this->u_action_for_mode('files') . '&amp;action=delete_library_file&amp;file=' . rawurlencode($file['filename']),
            ]);
        }

        $base_url = $this->u_action_for_mode('files');
        if ($query !== '')
        {
            $base_url .= '&amp;file_q=' . rawurlencode($query);
        }
        if ($status_filter !== 'all')
        {
            $base_url .= '&amp;file_status=' . rawurlencode($status_filter);
        }

        $template->assign_vars([
            'FILE_LIBRARY_QUERY' => $query,
            'FILE_LIBRARY_STATUS' => $status_filter,
            'S_FILE_STATUS_ALL' => $status_filter === 'all',
            'S_FILE_STATUS_USED' => $status_filter === 'used',
            'S_FILE_STATUS_ORPHAN' => $status_filter === 'orphan',
            'FILE_LIBRARY_TOTAL' => $total_files,
            'FILE_LIBRARY_USED_TOTAL' => $used_total,
            'FILE_LIBRARY_ORPHAN_TOTAL' => $orphan_total,
            'FILE_LIBRARY_TOTAL_SIZE' => $this->format_file_size($total_bytes),
            'FILE_LIBRARY_PAGINATION' => $this->make_pagination($base_url, $total_files, $per_page, $start),
            'S_HAS_FILE_LIBRARY' => $total_files > 0,
            'S_HAS_ORPHAN_FILES' => $orphan_total > 0,
            'U_FILES_CLEAR' => $this->u_action_for_mode('files'),
            'U_DELETE_ORPHAN_FILES' => $this->u_action_for_mode('files') . '&amp;action=delete_orphan_files',
        ]);
    }

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
                'EXTENSION' => $file['extension'],
                'MODIFIED' => $file['modified'],
                'USAGE_LABEL' => $file['usage_label'],
                'USAGE_COUNT' => (int) ($file['usage_count'] ?? 0),
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
                'extension' => $extension,
                'modified' => date('Y-m-d H:i', (int) filemtime($path)),
                'used' => $used,
                'usage_count' => $used ? count($usage[$entry]) : 0,
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

        if ($this->use_phpbb_attachment_uploads())
        {
            return $this->handle_phpbb_attachment_upload($request, $user);
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK)
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        if (empty($file['size']) || (int) $file['size'] <= 0)
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

    protected function use_phpbb_attachment_uploads()
    {
        return true;
    }

    protected function handle_phpbb_attachment_upload($request, $user)
    {
        global $config, $db, $phpbb_container;

        if (!defined('ATTACHMENTS_TABLE') || !$phpbb_container || !$phpbb_container->has('attachment.manager'))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED'));
        }

        $forum_id = isset($config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $config['mundophpbb_downloadcenter_support_forum_id'] : 0;
        if ($forum_id <= 0 || !$this->is_valid_support_forum($forum_id))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_SUPPORT_FORUM_REQUIRED_FOR_LOCAL_UPLOAD'));
        }

        $filedata = $phpbb_container->get('attachment.manager')->upload('download_upload', $forum_id, false, '', false);
        $errors = isset($filedata['error']) ? array_filter((array) $filedata['error']) : [];

        if (empty($filedata['post_attach']) || !empty($errors))
        {
            trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED') . (!empty($errors) ? '<br>' . implode('<br>', $errors) : ''));
        }

        $sql_ary = [
            'physical_filename' => (string) $filedata['physical_filename'],
            'attach_comment' => '',
            'real_filename' => (string) $filedata['real_filename'],
            'extension' => (string) $filedata['extension'],
            'mimetype' => (string) $filedata['mimetype'],
            'filesize' => (int) $filedata['filesize'],
            'filetime' => (int) $filedata['filetime'],
            'thumbnail' => (int) $filedata['thumbnail'],
            'is_orphan' => 0,
            'in_message' => 0,
            'poster_id' => (int) $user->data['user_id'],
            'post_msg_id' => 0,
            'topic_id' => 0,
            'download_count' => 0,
        ];

        $db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
        $attach_id = (int) $db->sql_nextid();

        return [
            'file_name' => 'attach:' . $attach_id,
            'file_size' => $this->format_file_size((int) $filedata['filesize']),
        ];
    }

    protected function is_phpbb_attachment_reference($file_name)
    {
        return preg_match('/^attach:(\d+)$/', (string) $file_name) === 1;
    }

    protected function get_phpbb_attachment_file($file_name)
    {
        global $config, $db, $phpbb_root_path;

        if (!defined('ATTACHMENTS_TABLE') || !preg_match('/^attach:(\d+)$/', (string) $file_name, $matches))
        {
            return false;
        }

        $sql = 'SELECT attach_id, physical_filename, real_filename, filesize, mimetype
            FROM ' . ATTACHMENTS_TABLE . '
            WHERE attach_id = ' . (int) $matches[1];
        $result = $db->sql_query_limit($sql, 1);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        if (!$row)
        {
            return false;
        }

        $upload_path = isset($config['upload_path']) ? (string) $config['upload_path'] : 'files';
        $row['path'] = $phpbb_root_path . trim($upload_path, '/\\') . '/' . basename((string) $row['physical_filename']);

        return $row;
    }

    protected function delete_phpbb_attachment_reference($file_name)
    {
        global $phpbb_container;

        if (!preg_match('/^attach:(\d+)$/', (string) $file_name, $matches))
        {
            return false;
        }

        if ($phpbb_container && $phpbb_container->has('attachment.manager'))
        {
            return (bool) $phpbb_container->get('attachment.manager')->delete('attach', [(int) $matches[1]], false);
        }

        return false;
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
            if ($part !== '' && preg_match('/^[a-z0-9]{1,10}$/', $part) && !$this->is_blocked_upload_extension($part))
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
            if ($part !== '' && preg_match('/^[a-z0-9]{1,10}$/', $part) && !$this->is_blocked_upload_extension($part))
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
        if ($this->use_phpbb_attachment_uploads())
        {
            return $user->lang('ACP_DOWNLOADCENTER_UPLOAD_RULES_PHPBB_ATTACHMENTS');
        }

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

        foreach ($parts as $part)
        {
            if ($this->is_blocked_upload_extension($part))
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


    protected function blocked_upload_extensions()
    {
        return [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl',
            'asp', 'aspx', 'jsp', 'exe', 'sh', 'bash', 'bat', 'cmd', 'com',
            'scr', 'msi', 'dll', 'jar', 'js', 'mjs', 'html', 'htm', 'svg',
            'xml', 'xhtml', 'shtml', 'htaccess', 'htpasswd'
        ];
    }

    protected function is_blocked_upload_extension($extension)
    {
        $extension = strtolower(trim((string) $extension, '. '));
        return $extension === '' || in_array($extension, $this->blocked_upload_extensions(), true);
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
                    $this->link_item_attachments_to_support_topic($db, $versions_table, $item_id, $topic_id, $first_post_id);
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
                $created_first_post_id = $this->get_topic_first_post_id($db, $created_topic_id);
                if ($created_first_post_id > 0)
                {
                    $this->link_item_attachments_to_support_topic($db, $versions_table, $item_id, $created_topic_id, $created_first_post_id);
                }
                $this->add_log($db, $user, 'support_topic_created', $user->lang('ACP_DOWNLOADCENTER_LOG_SUPPORT_TOPIC_CREATED', (string) $created_topic_id), $item_id);
                return $created_topic_id;
            }
        }

        return 0;
    }

    protected function get_latest_version_for_item($db, $versions_table, $item_id)
    {
        $items_table = $this->table_prefix . 'downloadcenter_items';
        $current_version_id = 0;

        $sql = 'SELECT item_current_version_id
            FROM ' . $items_table . '
            WHERE item_id = ' . (int) $item_id;
        $result = $db->sql_query_limit($sql, 1);
        $current_version_id = (int) $db->sql_fetchfield('item_current_version_id');
        $db->sql_freeresult($result);

        if ($current_version_id > 0)
        {
            $sql = 'SELECT *
                FROM ' . $versions_table . '
                WHERE version_id = ' . (int) $current_version_id . '
                    AND item_id = ' . (int) $item_id . '
                    AND version_enabled = 1';
            $result = $db->sql_query_limit($sql, 1);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if ($row)
            {
                return $row;
            }
        }

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

    protected function link_item_attachments_to_support_topic($db, $versions_table, $item_id, $topic_id, $post_id)
    {
        if (!defined('ATTACHMENTS_TABLE') || (int) $item_id <= 0 || (int) $topic_id <= 0 || (int) $post_id <= 0)
        {
            return;
        }

        $sql = 'SELECT download_file
            FROM ' . $versions_table . "
            WHERE item_id = " . (int) $item_id . "
                AND download_type = 'local'
                AND download_file <> ''";
        $result = $db->sql_query($sql);

        $attach_ids = [];
        while ($row = $db->sql_fetchrow($result))
        {
            if (preg_match('/^attach:(\d+)$/', (string) $row['download_file'], $matches))
            {
                $attach_ids[(int) $matches[1]] = (int) $matches[1];
            }
        }
        $db->sql_freeresult($result);

        if (empty($attach_ids))
        {
            return;
        }

        $attachment_data = [
            'is_orphan' => 0,
            'in_message' => 0,
            'post_msg_id' => (int) $post_id,
            'topic_id' => (int) $topic_id,
        ];

        $sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
            SET ' . $db->sql_build_array('UPDATE', $attachment_data) . '
            WHERE ' . $db->sql_in_set('attach_id', array_values($attach_ids));
        $db->sql_query($sql);

        $sql = 'UPDATE ' . POSTS_TABLE . '
            SET post_attachment = 1
            WHERE post_id = ' . (int) $post_id;
        $db->sql_query($sql);

        $sql = 'UPDATE ' . TOPICS_TABLE . '
            SET topic_attachment = 1
            WHERE topic_id = ' . (int) $topic_id;
        $db->sql_query($sql);
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

            if ((string) $latest_version['download_type'] === 'local' && preg_match('/^attach:(\d+)$/', (string) $latest_version['download_file'], $matches))
            {
                $attachment_url = generate_board_url() . '/download/file.php?id=' . (int) $matches[1];
                $message .= "\n" . $user->lang('ACP_DOWNLOADCENTER_SUPPORT_TOPIC_NATIVE_DOWNLOAD') . ': [url=' . $attachment_url . ']' . $attachment_url . "[/url]\n";
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

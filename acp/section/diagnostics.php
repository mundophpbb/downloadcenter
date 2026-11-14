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
 * ACP diagnostics, pre-release checks and maintenance.
 */
trait diagnostics
{
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

		$package_version = '1.3.4';
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
		$response = new \Symfony\Component\HttpFoundation\Response($report, 200, [
			'Content-Type'        => 'text/plain; charset=UTF-8',
			'Content-Disposition' => 'attachment; filename="downloadcenter-diagnostic-report.txt"',
			'Content-Length'      => (string) strlen($report),
		]);
		$response->send();
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

	protected function assign_diagnostic_row($template, $ok, $title, $message)
	{
		$template->assign_block_vars('diagnostics', [
			'STATUS' => $ok ? 'OK' : 'ATENCAO',
			'STATUS_CLASS' => $ok ? 'ok' : 'warn',
			'TITLE' => $title,
			'MESSAGE' => $message,
		]);
	}
}

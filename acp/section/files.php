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
 * ACP file library.
 */
trait files
{
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

	protected function local_file_path($file_name)
	{
		return $this->local_storage_directory() . basename((string) $file_name);
	}

	protected function local_storage_directory()
	{
		global $phpbb_root_path;
		return $phpbb_root_path . 'files/mundophpbb/downloadcenter/';
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
					AND download_file = '" . $db->sql_escape($file_name) . "'
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

	protected function is_allowed_existing_file($file_name)
	{
		$file_name = basename((string) $file_name);
		$extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
		return $file_name !== '' && $extension !== '' && in_array($extension, $this->get_allowed_extensions(), true);
	}
}

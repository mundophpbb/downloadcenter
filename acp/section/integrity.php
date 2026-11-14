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
 * ACP data integrity checks and fixes.
 */
trait integrity
{
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
					$sql = sprintf(
						'DELETE FROM %1$s WHERE download_id = %2$d AND (item_id NOT IN (SELECT item_id FROM %3$s) OR version_id NOT IN (SELECT version_id FROM %4$s))',
						$downloads_table,
						(int) $fix_id,
						$items_table,
						$versions_table
					);
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
}

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
 * ACP items and versions.
 */
trait items
{
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
				'image_caption' => $this->clean_utf8_for_storage(trim($request->variable('screenshot_caption', '', true))),
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

			$saved = $this->save_item_version($db, $request, $user, $config, $phpbb_root_path, $phpEx, $items_table, $versions_table, $item_id, $time);
			$message_key = $saved['updated'] ? 'ACP_DOWNLOADCENTER_VERSION_UPDATED' : 'ACP_DOWNLOADCENTER_VERSION_SAVED';
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
			$latest_changelog = $this->clean_utf8_for_storage(trim($request->variable('latest_version_changelog', '', true)));

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

			$name = $this->clean_utf8_for_storage(trim($request->variable('item_name', '', true)));
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
				'item_short_desc' => $this->clean_utf8_for_storage(trim($request->variable('item_short_desc', '', true))),
				'item_desc' => $this->clean_utf8_for_storage(trim($request->variable('item_desc', '', true))),
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

			// One Save button saves everything on the page: the current changelog, a new/edited version
			// (with its file or link) and a screenshot, so nothing typed or chosen is silently dropped.
			$messages = [$user->lang('ACP_DOWNLOADCENTER_ITEM_SAVED')];

			$latest_version_id = $request->variable('latest_version_id', 0);
			if ($latest_version_id > 0 && $request->is_set_post('latest_version_changelog'))
			{
				$latest_changelog = $this->clean_utf8_for_storage(trim($request->variable('latest_version_changelog', '', true)));
				$sql = 'SELECT version_changelog FROM ' . $versions_table . '
					WHERE version_id = ' . (int) $latest_version_id . '
						AND item_id = ' . (int) $item_id;
				$result = $db->sql_query_limit($sql, 1);
				$latest_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if ($latest_row && trim((string) $latest_row['version_changelog']) !== $latest_changelog)
				{
					$sql = 'UPDATE ' . $versions_table . "
						SET version_changelog = '" . $db->sql_escape($latest_changelog) . "'
						WHERE version_id = " . (int) $latest_version_id;
					$db->sql_query($sql);
					$this->add_log($db, $user, 'version_changelog_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_VERSION_CHANGELOG_UPDATED', (string) $latest_version_id), $item_id, $latest_version_id);
				}
			}

			if ($this->request_has_version_data($request))
			{
				$saved = $this->save_item_version($db, $request, $user, $config, $phpbb_root_path, $phpEx, $items_table, $versions_table, $item_id, $time, false);
				$messages[] = $user->lang($saved['updated'] ? 'ACP_DOWNLOADCENTER_VERSION_UPDATED' : 'ACP_DOWNLOADCENTER_VERSION_SAVED');
			}

			if ($this->save_item_screenshot($db, $request, $user, $screenshots_table, $item_id, $time))
			{
				$messages[] = $user->lang('ACP_DOWNLOADCENTER_SCREENSHOT_ADDED_INLINE');
			}

			$support_topic_id = $this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
			if ($support_topic_id > 0)
			{
				$data['topic_id'] = $support_topic_id;
			}

			trigger_error(implode('<br>', $messages) . adm_back_link($this->u_action . '&amp;action=edit&amp;item_id=' . (int) $item_id));
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

		$sql = sprintf(
			'SELECT i.*, c.category_name, u.username
				FROM %1$s i
				LEFT JOIN %2$s c ON c.category_id = i.category_id
				LEFT JOIN %3$s u ON u.user_id = i.user_id
				ORDER BY i.item_updated DESC, i.item_name ASC',
			$items_table,
			$categories_table,
			USERS_TABLE
		);
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

	/**
	 * Saves (inserts or updates) one version from the posted form and returns its id.
	 * Shared by the "Add version" button and by the single "Save" button of the item page.
	 */
	protected function save_item_version($db, $request, $user, $config, $phpbb_root_path, $phpEx, $items_table, $versions_table, $item_id, $time, $sync_topic = true)
	{
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
			'version_changelog' => $this->clean_utf8_for_storage(trim($request->variable('version_changelog', '', true))),
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

		if ($sync_topic)
		{
			$this->sync_support_topic_for_item($db, $config, $user, $items_table, $versions_table, $item_id, $phpbb_root_path, $phpEx);
		}

		return ['version_id' => $saved_version_id, 'updated' => $editing_existing_version];
	}

	/**
	 * True when the posted form carries data for a version (number, link, file or a version being edited).
	 */
	protected function request_has_version_data($request)
	{
		if ($request->variable('edit_version_id', 0) > 0)
		{
			return true;
		}

		if (trim($request->variable('version_number', '', true)) !== '' || trim($request->variable('download_url', '', true)) !== '')
		{
			return true;
		}

		$file = $request->file('download_upload');

		return !empty($file) && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
	}

	/**
	 * Adds the screenshot posted with the item form. Returns false when no image was chosen.
	 */
	protected function save_item_screenshot($db, $request, $user, $screenshots_table, $item_id, $time)
	{
		$uploaded_screenshot = $this->handle_screenshot_upload($request, $user);
		if (!$uploaded_screenshot)
		{
			return false;
		}

		$screenshot_data = [
			'item_id' => (int) $item_id,
			'image_file' => $uploaded_screenshot['file_name'],
			'image_caption' => $this->clean_utf8_for_storage(trim($request->variable('screenshot_caption', '', true))),
			'image_order' => max(0, $request->variable('screenshot_order', 0)),
			'image_created' => $time,
		];
		$sql = 'INSERT INTO ' . $screenshots_table . ' ' . $db->sql_build_array('INSERT', $screenshot_data);
		$db->sql_query($sql);
		$this->add_log($db, $user, 'screenshot_created', $user->lang('ACP_DOWNLOADCENTER_LOG_SCREENSHOT_CREATED', (string) $item_id), $item_id);

		return true;
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
		$file_name = time() . '-' . bin2hex(random_bytes(4)) . '-' . $safe_base . '.' . $extension;
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

	protected function count_screenshots_for_item($db, $screenshots_table, $item_id)
	{
		$sql = 'SELECT COUNT(screenshot_id) AS total_screenshots FROM ' . $screenshots_table . ' WHERE item_id = ' . (int) $item_id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return $row ? (int) $row['total_screenshots'] : 0;
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
}

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
 * Support topic creation and synchronisation.
 */
trait support_topic
{
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

		$sql = 'SELECT * FROM ' . $items_table . ' WHERE item_id = ' . (int) $item_id;
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
			$sql = 'UPDATE ' . $items_table . ' SET topic_id = 0 WHERE item_id = ' . (int) $item_id;
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
				$sql = 'UPDATE ' . $items_table . ' SET topic_id = ' . (int) $created_topic_id . ' WHERE item_id = ' . (int) $item_id;
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
			'post_checksum' => hash('md5', $message),
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
			'message_md5' => hash('md5', $message),
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
}

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
 * ACP uploads through the phpBB attachment manager.
 */
trait uploads
{
	protected function handle_local_upload($request, $user, $phpbb_root_path)
	{
		$file = $request->file('download_upload');

		if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
		{
			return false;
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK || empty($file['size']) || (int) $file['size'] <= 0)
		{
			trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_FAILED'));
		}

		// The extension's own rules are checked before the file is handed to phpBB's attachment manager.
		$original_name = (string) $file['name'];
		$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed = $this->get_allowed_extensions();
		$max_bytes = $this->get_max_upload_bytes();

		if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
		{
			trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', implode(', ', $allowed)));
		}

		if ($max_bytes > 0 && (int) $file['size'] > $max_bytes)
		{
			trigger_error($user->lang('ACP_DOWNLOADCENTER_UPLOAD_TOO_LARGE', $this->format_file_size($max_bytes)));
		}

		return $this->handle_phpbb_attachment_upload($request, $user);
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

	protected function upload_rules_text($user)
	{
		if ($this->use_phpbb_attachment_uploads())
		{
			return $user->lang('ACP_DOWNLOADCENTER_UPLOAD_RULES_PHPBB_ATTACHMENTS');
		}

		return $user->lang('ACP_DOWNLOADCENTER_UPLOAD_RULES', $this->get_allowed_extensions_string(), $this->format_file_size($this->get_max_upload_bytes()));
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
}

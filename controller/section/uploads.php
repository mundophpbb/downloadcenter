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
 * Uploads through the phpBB attachment manager.
 */
trait uploads
{
	protected function local_file_path($file_name)
	{
		return $this->root_path . 'files/mundophpbb/downloadcenter/' . basename((string) $file_name);
	}

	protected function handle_local_upload($request, $field = 'download_upload')
	{
		$file = $request->file($field);

		if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
		{
			return false;
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK || empty($file['size']) || (int) $file['size'] <= 0)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_FAILED'));
		}

		// The extension's own rules are checked before the file is handed to phpBB's attachment manager.
		$original_name = (string) $file['name'];
		$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed = $this->get_allowed_extensions();
		$max_bytes = $this->get_max_upload_bytes();

		if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_EXTENSION_NOT_ALLOWED', implode(', ', $allowed)));
		}

		if ($max_bytes > 0 && (int) $file['size'] > $max_bytes)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_TOO_LARGE', $this->format_file_size($max_bytes)));
		}

		return $this->handle_phpbb_attachment_upload($field);
	}

	protected function handle_phpbb_attachment_upload($field)
	{
		global $phpbb_container;

		if (!defined('ATTACHMENTS_TABLE') || !$phpbb_container || !$phpbb_container->has('attachment.manager'))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_FAILED'));
		}

		$forum_id = isset($this->config['mundophpbb_downloadcenter_support_forum_id']) ? (int) $this->config['mundophpbb_downloadcenter_support_forum_id'] : 0;
		if ($forum_id <= 0)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SUPPORT_FORUM_REQUIRED_FOR_LOCAL_UPLOAD'));
		}

		$filedata = $phpbb_container->get('attachment.manager')->upload($field, $forum_id, false, '', false);
		$errors = isset($filedata['error']) ? array_filter((array) $filedata['error']) : [];

		if (empty($filedata['post_attach']) || !empty($errors))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_FAILED') . (!empty($errors) ? '<br>' . implode('<br>', $errors) : ''));
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
			'poster_id' => (int) $this->user->data['user_id'],
			'post_msg_id' => 0,
			'topic_id' => 0,
			'download_count' => 0,
		];

		$this->db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		$attach_id = (int) $this->db->sql_nextid();

		return [
			'file_name' => 'attach:' . $attach_id,
			'file_size' => $this->format_file_size((int) $filedata['filesize']),
		];
	}

	protected function get_phpbb_attachment_file($file_name)
	{
		if (!defined('ATTACHMENTS_TABLE') || !preg_match('/^attach:(\d+)$/', (string) $file_name, $matches))
		{
			return false;
		}

		$sql = 'SELECT attach_id, physical_filename, real_filename, filesize, mimetype
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE attach_id = ' . (int) $matches[1];
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return false;
		}

		$upload_path = isset($this->config['upload_path']) ? (string) $this->config['upload_path'] : 'files';
		$row['path'] = $this->root_path . trim($upload_path, '/\\') . '/' . basename((string) $row['physical_filename']);

		return $row;
	}

	protected function get_allowed_extensions()
	{
		$raw = isset($this->config['mundophpbb_downloadcenter_allowed_extensions']) ? (string) $this->config['mundophpbb_downloadcenter_allowed_extensions'] : 'zip,rar,7z,tar,gz,tgz,bz2,pdf,txt';
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

	protected function get_max_upload_mb()
	{
		return max(1, (int) (isset($this->config['mundophpbb_downloadcenter_max_upload_mb']) ? $this->config['mundophpbb_downloadcenter_max_upload_mb'] : 20));
	}

	protected function upload_rules_text()
	{
		if ($this->use_phpbb_attachment_uploads())
		{
			return $this->user->lang('DOWNLOADCENTER_UPLOAD_RULES_PHPBB_ATTACHMENTS');
		}

		return $this->user->lang('DOWNLOADCENTER_UPLOAD_RULES', $this->get_allowed_extensions_string(), $this->format_file_size($this->get_max_upload_bytes()));
	}
}

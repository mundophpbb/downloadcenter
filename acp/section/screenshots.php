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
 * ACP screenshots.
 */
trait screenshots
{
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
		$file_name = time() . '-' . bin2hex(random_bytes(4)) . '-' . $safe_base . '.' . $extension;
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
}

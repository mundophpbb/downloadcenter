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
 * Author screenshots.
 */
trait screenshots
{
	protected function screenshots_storage_directory()
	{
		return $this->root_path . 'files/mundophpbb/downloadcenter/screenshots/';
	}

	protected function handle_screenshot_upload($request)
	{
		$file = $request->file('screenshot_upload');

		if (empty($file) || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE)
		{
			return false;
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
		}

		$original_name = (string) $file['name'];
		$tmp_name = (string) $file['tmp_name'];
		$size = (int) $file['size'];
		$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		$max_bytes = 5 * 1024 * 1024;

		if (!$this->is_safe_upload_name($original_name) || $extension === '' || !in_array($extension, $allowed, true))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_EXTENSION_NOT_ALLOWED'));
		}

		if ($size > $max_bytes)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_TOO_LARGE', $this->format_file_size($max_bytes)));
		}

		if ($tmp_name === '' || !is_uploaded_file($tmp_name))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_UPLOAD_FAILED'));
		}

		if (@getimagesize($tmp_name) === false)
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_SCREENSHOT_INVALID_IMAGE'));
		}

		$directory = $this->screenshots_storage_directory();
		if (!is_dir($directory) && !@mkdir($directory, 0755, true))
		{
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_DIRECTORY_FAILED'));
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
			trigger_error($this->user->lang('DOWNLOADCENTER_UPLOAD_MOVE_FAILED'));
		}

		return ['file_name' => $file_name];
	}

	protected function insert_screenshot($item_id, $file_name, $caption = '', $order = 0)
	{
		$data = [
			'item_id' => (int) $item_id,
			'image_file' => (string) $file_name,
			'image_caption' => (string) $caption,
			'image_order' => max(0, (int) $order),
			'image_created' => time(),
		];

		$this->db->sql_query('INSERT INTO ' . $this->table('downloadcenter_screenshots') . ' ' . $this->db->sql_build_array('INSERT', $data));
	}

	protected function assign_author_screenshots($item_id)
	{
		$sql = 'SELECT *
			FROM ' . $this->table('downloadcenter_screenshots') . '
			WHERE item_id = ' . (int) $item_id . '
			ORDER BY image_order ASC, screenshot_id ASC';
		$result = $this->db->sql_query($sql);
		$count = 0;
		while ($screenshot = $this->db->sql_fetchrow($result))
		{
			$count++;
			$this->template->assign_block_vars('edit_screenshots', [
				'SCREENSHOT_ID' => (int) $screenshot['screenshot_id'],
				'CAPTION' => $screenshot['image_caption'],
				'ORDER' => (int) $screenshot['image_order'],
				'FILENAME' => $screenshot['image_file'],
				'CREATED' => !empty($screenshot['image_created']) ? $this->user->format_date((int) $screenshot['image_created']) : '',
				'U_IMAGE' => $this->helper->route('mundophpbb_downloadcenter_screenshot', ['screenshot_id' => (int) $screenshot['screenshot_id']]),
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_var('S_HAS_EDIT_SCREENSHOTS', $count > 0);
	}

	protected function screenshot_file_path($file_name)
	{
		return $this->screenshots_storage_directory() . basename((string) $file_name);
	}

	protected function delete_screenshot_file($file_name)
	{
		$file_name = basename((string) $file_name);
		if ($file_name === '')
		{
			return false;
		}

		$path = $this->screenshot_file_path($file_name);
		if (is_file($path))
		{
			return @unlink($path);
		}

		return false;
	}

	protected function redirect_to_author_screenshots($item_id, $status)
	{
		$url = $this->helper->route('mundophpbb_downloadcenter_edit', ['item_id' => (int) $item_id]);
		$url .= (strpos($url, '?') === false ? '?' : '&') . 'screenshot_status=' . rawurlencode($status) . '#downloadcenter-screenshots';
		\redirect($url);
	}
}

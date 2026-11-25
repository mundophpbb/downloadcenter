<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\concern;

/**
 * Reuses identical attachments within one authorised item after phpBB validation.
 */
trait upload_deduplication
{
	protected function reuse_identical_upload($db, $versions_table, $item_id, array &$filedata, $directory)
	{
		if ((int) $item_id <= 0 || !defined('ATTACHMENTS_TABLE'))
		{
			return false;
		}

		$physical_name = basename((string) $filedata['physical_filename']);
		$uploaded_path = $directory . $physical_name;
		if (!is_file($uploaded_path) || is_link($uploaded_path))
		{
			return false;
		}

		// Item scope prevents automatic sharing across owners and support topics.
		$sql = 'SELECT download_file FROM ' . $versions_table . "
			WHERE item_id = " . (int) $item_id . "
				AND download_type = 'local' AND download_file <> ''";
		$result = $db->sql_query($sql);
		$ids = [];
		while ($row = $db->sql_fetchrow($result))
		{
			if (preg_match('/^attach:(\d+)$/', (string) $row['download_file'], $matches))
			{
				$ids[(int) $matches[1]] = (int) $matches[1];
			}
		}
		$db->sql_freeresult($result);

		$uploaded_hash = null;
		foreach (array_chunk(array_values($ids), 200) as $chunk)
		{
			$sql = 'SELECT attach_id, physical_filename, real_filename, filesize FROM ' . ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', $chunk) . '
					AND filesize = ' . (int) $filedata['filesize'] . "
					AND real_filename = '" . $db->sql_escape((string) $filedata['real_filename']) . "'
					AND is_orphan = 0 AND in_message = 0
				ORDER BY attach_id ASC";
			$result = $db->sql_query($sql);
			$duplicate = false;
			while ($row = $db->sql_fetchrow($result))
			{
				$existing_name = basename((string) $row['physical_filename']);
				$existing_path = $directory . $existing_name;
				if ((string) $row['real_filename'] !== (string) $filedata['real_filename']
					|| $existing_name === $physical_name || !is_file($existing_path) || is_link($existing_path)
					|| (int) filesize($existing_path) !== (int) $filedata['filesize'])
				{
					continue;
				}
				if ($uploaded_hash === null)
				{
					$uploaded_hash = @hash_file('sha256', $uploaded_path);
				}
				$existing_hash = @hash_file('sha256', $existing_path);
				if (is_string($uploaded_hash) && is_string($existing_hash) && hash_equals($existing_hash, $uploaded_hash))
				{
					$duplicate = $row;
					break;
				}
			}
			$db->sql_freeresult($result);

			if ($duplicate)
			{
				// Clean only the fresh upload. Never delete an existing attachment here.
				if (!empty($filedata['thumbnail']))
				{
					$thumbnail_path = $directory . 'thumb_' . $physical_name;
					if (is_file($thumbnail_path) && !@unlink($thumbnail_path))
					{
						return false;
					}
					$filedata['thumbnail'] = 0;
				}
				if (!@unlink($uploaded_path))
				{
					return false;
				}
				return [
					'file_name' => 'attach:' . (int) $duplicate['attach_id'],
					'file_size' => $this->format_file_size((int) $duplicate['filesize']),
				];
			}
		}

		return false;
	}
}

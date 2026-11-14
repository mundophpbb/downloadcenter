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
 * Text helpers shared by the ACP and the public controller.
 */
trait text_tools
{
	/**
	 * Remove Unicode code points that need MySQL utf8mb4.
	 *
	 * Some phpBB installations still use MySQL/MariaDB utf8/utf8mb3 tables.
	 * Those tables reject emojis and other 4-byte Unicode characters with SQL error 1366.
	 */
	protected function clean_utf8_for_storage($value)
	{
		$value = (string) $value;
		$clean = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value);

		if ($clean === null)
		{
			$clean = preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $value);
		}

		return ($clean === null) ? $value : $clean;
	}

	protected function slugify($text)
	{
		$text = strtolower(trim($text));
		$text = preg_replace('/[^a-z0-9]+/i', '-', $text);
		$text = trim($text, '-');
		return $text ?: 'item';
	}

	protected function normalize_start($start, $per_page, $total_items)
	{
		$start = max(0, (int) $start);
		$per_page = max(1, (int) $per_page);
		$total_items = max(0, (int) $total_items);

		if ($total_items > 0 && $start >= $total_items)
		{
			$start = max(0, ((int) floor(($total_items - 1) / $per_page)) * $per_page);
		}

		return $start;
	}

	protected function format_file_size($bytes)
	{
		$bytes = max(0, (int) $bytes);
		if ($bytes >= 1073741824)
		{
			return round($bytes / 1073741824, 2) . ' GB';
		}
		if ($bytes >= 1048576)
		{
			return round($bytes / 1048576, 2) . ' MB';
		}
		if ($bytes >= 1024)
		{
			return round($bytes / 1024, 2) . ' KB';
		}
		return $bytes . ' B';
	}
}

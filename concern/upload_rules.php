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
 * Upload rules shared by the ACP and the public controller.
 */
trait upload_rules
{
	protected function blocked_upload_extensions()
	{
		return [
			'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl',
			'asp', 'aspx', 'jsp', 'exe', 'sh', 'bash', 'bat', 'cmd', 'com',
			'scr', 'msi', 'dll', 'jar', 'js', 'mjs', 'html', 'htm', 'svg',
			'xml', 'xhtml', 'shtml', 'htaccess', 'htpasswd'
		];
	}

	protected function is_blocked_upload_extension($extension)
	{
		$extension = strtolower(trim((string) $extension, '. '));
		return $extension === '' || in_array($extension, $this->blocked_upload_extensions(), true);
	}

	protected function is_safe_upload_name($name)
	{
		$name = (string) $name;
		if ($name === '' || preg_match('~[\x00-\x1F\x7F\\/:*?"<>|]~', $name))
		{
			return false;
		}

		$base = basename($name);
		if ($base !== $name || $base === '' || $base === '.' || $base === '..')
		{
			return false;
		}

		$parts = explode('.', $base);
		if (count($parts) < 2)
		{
			return false;
		}

		foreach ($parts as $part)
		{
			if ($this->is_blocked_upload_extension($part))
			{
				return false;
			}
		}

		return true;
	}

	protected function get_allowed_extensions_string()
	{
		return implode(', ', $this->get_allowed_extensions());
	}

	protected function get_max_upload_bytes()
	{
		return $this->get_max_upload_mb() * 1024 * 1024;
	}

	protected function is_phpbb_attachment_reference($file_name)
	{
		return preg_match('/^attach:(\d+)$/', (string) $file_name) === 1;
	}

	protected function use_phpbb_attachment_uploads()
	{
		return true;
	}
}

<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\concern;

use mundophpbb\downloadcenter\service\archive_changelog;

/**
 * Fills an empty version changelog from the CHANGELOG file inside the version's ZIP.
 * Used by the ACP and by the public submission and author pages.
 *
 * The class using this trait must provide local_file_path() and get_phpbb_attachment_file().
 */
trait archive_changelog_fill
{
	/**
	 * @param string $changelog      Changelog typed in the form
	 * @param string $download_type  local|external
	 * @param string $download_file  Stored file name or attach:ID
	 * @param string $version_number
	 * @return array{changelog: string, source: string} source is the file read inside the ZIP, or ''
	 */
	protected function changelog_from_archive($changelog, $download_type, $download_file, $version_number)
	{
		$result = ['changelog' => (string) $changelog, 'source' => ''];

		if (trim((string) $changelog) !== '' || $download_type !== 'local' || (string) $download_file === '')
		{
			return $result;
		}

		$path = '';
		$real_name = (string) $download_file;
		if (strpos((string) $download_file, 'attach:') === 0)
		{
			$attachment = $this->get_phpbb_attachment_file($download_file);
			if ($attachment)
			{
				$path = (string) $attachment['path'];
				$real_name = (string) $attachment['real_filename'];
			}
		}
		else
		{
			$path = $this->local_file_path($download_file);
		}

		if ($path === '' || strtolower(pathinfo($real_name, PATHINFO_EXTENSION)) !== 'zip')
		{
			return $result;
		}

		$found = archive_changelog::from_zip($path, $version_number);
		if ($found['bbcode'] === '')
		{
			return $result;
		}

		$changelog = method_exists($this, 'clean_utf8_for_storage') ? $this->clean_utf8_for_storage($found['bbcode']) : $found['bbcode'];

		return ['changelog' => $changelog, 'source' => $found['file']];
	}
}

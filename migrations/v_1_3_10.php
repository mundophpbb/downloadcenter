<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\migrations;

/**
 * Version 1.3.10: download totals kept across versions and ACP redesign.
 */
class v_1_3_10 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['mundophpbb_downloadcenter_version'])
			&& version_compare($this->config['mundophpbb_downloadcenter_version'], '1.3.10', '>=');
	}

	public static function depends_on()
	{
		return ['\mundophpbb\downloadcenter\migrations\v_1_3_9'];
	}

	public function update_data()
	{
		return [
			['config.update', ['mundophpbb_downloadcenter_version', '1.3.10']],
		];
	}
}

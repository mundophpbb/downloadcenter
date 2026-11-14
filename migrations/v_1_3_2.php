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
 * Version 1.3.2: coding style: tabs; stores the new version number.
 */
class v_1_3_2 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['mundophpbb_downloadcenter_version'])
			&& version_compare($this->config['mundophpbb_downloadcenter_version'], '1.3.2', '>=');
	}

	public static function depends_on()
	{
		return ['\mundophpbb\downloadcenter\migrations\v_1_3_1'];
	}

	public function update_data()
	{
		return [
			['config.update', ['mundophpbb_downloadcenter_version', '1.3.2']],
		];
	}
}

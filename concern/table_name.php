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
 * Table name helper shared by the public controllers.
 */
trait table_name
{
	protected function table($name)
	{
		global $table_prefix;
		return $table_prefix . $name;
	}
}

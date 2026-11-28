<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\service;

class audit_log
{
	/**
	 * @param int|null $source bot_detector::SOURCE_* constant; detected when null
	 */
	public static function add($db, $table, $user, $action, $message, $item_id = 0, $version_id = 0, $source = null)
	{
		$ip = (string) $user->ip;
		$sql_ary = [
			'user_id' => (int) $user->data['user_id'],
			'username' => (string) ($user->data['username'] ?? ''),
			'item_id' => (int) $item_id,
			'version_id' => (int) $version_id,
			'log_action' => (string) $action,
			'log_message' => (string) $message,
			'user_ip' => filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '',
			'log_time' => time(),
			'log_source' => $source === null ? bot_detector::classify($user) : (int) $source,
			'user_agent' => bot_detector::user_agent($user),
		];

		$db->sql_query('INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $sql_ary));
	}
}

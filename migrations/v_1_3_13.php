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
 * Version 1.3.13: downloads made by people and by bots are told apart.
 *
 * Totals are not changed. Downloads and log entries get a source (person, bot
 * or not classified) and the browser user agent. Existing records are sorted
 * as far as the stored data allows: downloads by phpBB bot accounts become
 * "bot", downloads by registered members become "person", and guest downloads
 * stay "not classified" because their browser was never recorded.
 */
class v_1_3_13 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['mundophpbb_downloadcenter_version'])
			&& version_compare($this->config['mundophpbb_downloadcenter_version'], '1.3.13', '>=');
	}

	public static function depends_on()
	{
		return ['\mundophpbb\downloadcenter\migrations\v_1_3_12'];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'downloadcenter_downloads' => [
					'download_source' => ['TINT:1', 0],
					'user_agent'      => ['VCHAR:255', ''],
				],
				$this->table_prefix . 'downloadcenter_logs' => [
					'log_source' => ['TINT:1', 0],
					'user_agent' => ['VCHAR:255', ''],
				],
			],
			'add_index' => [
				$this->table_prefix . 'downloadcenter_downloads' => [
					'download_source' => ['download_source'],
				],
				$this->table_prefix . 'downloadcenter_logs' => [
					'log_source' => ['log_source'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'downloadcenter_downloads' => ['download_source'],
				$this->table_prefix . 'downloadcenter_logs' => ['log_source'],
			],
			'drop_columns' => [
				$this->table_prefix . 'downloadcenter_downloads' => ['download_source', 'user_agent'],
				$this->table_prefix . 'downloadcenter_logs' => ['log_source', 'user_agent'],
			],
		];
	}

	public function update_data()
	{
		return [
			['config_text.add', ['mundophpbb_downloadcenter_bot_patterns', '']],
			['custom', [[$this, 'classify_existing_records']]],
			['config.update', ['mundophpbb_downloadcenter_version', '1.3.13']],
		];
	}

	public function revert_data()
	{
		return [
			['config_text.remove', ['mundophpbb_downloadcenter_bot_patterns']],
		];
	}

	/**
	 * Sort existing downloads and download log entries.
	 * 0 = person (the column default), 1 = bot, 2 = not classified.
	 */
	public function classify_existing_records()
	{
		$bot_users = 'SELECT user_id FROM ' . $this->table_prefix . 'bots';

		$tables = [
			$this->table_prefix . 'downloadcenter_downloads' => ['download_source', ''],
			$this->table_prefix . 'downloadcenter_logs' => ['log_source', " AND log_action = 'download'"],
		];

		foreach ($tables as $table => list($column, $extra_where))
		{
			// Downloads by accounts phpBB uses for its known bots.
			$this->db->sql_query('UPDATE ' . $table . '
				SET ' . $column . ' = 1
				WHERE user_id IN (' . $bot_users . ')' . $extra_where);

			// Guest downloads: the browser was not recorded, so they cannot be sorted.
			$this->db->sql_query('UPDATE ' . $table . '
				SET ' . $column . ' = 2
				WHERE user_id = ' . ANONYMOUS . $extra_where);
		}
	}
}

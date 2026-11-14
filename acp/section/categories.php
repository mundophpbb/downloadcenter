<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\acp\section;

/**
 * ACP categories.
 */
trait categories
{
	protected function handle_categories($db, $request, $template, $user)
	{
		$table = $this->table_prefix . 'downloadcenter_categories';
		$action = $request->variable('action', '');
		$category_id = $request->variable('category_id', 0);

		if ($request->is_set_post('submit_category'))
		{
			if (!check_form_key('mundophpbb_downloadcenter_categories'))
			{
				trigger_error('FORM_INVALID');
			}

			$name = $this->clean_utf8_for_storage(trim($request->variable('category_name', '', true)));
			$desc = $this->clean_utf8_for_storage(trim($request->variable('category_desc', '', true)));
			$order = max(0, $request->variable('category_order', 0));
			$enabled = $request->variable('category_enabled', 0);

			if ($name === '')
			{
				trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_NAME_REQUIRED') . adm_back_link($this->u_action));
			}

			$data = [
				'category_name' => $name,
				'category_desc' => $desc,
				'category_slug' => $this->slugify($name),
				'category_order' => $order,
				'category_enabled' => $enabled,
			];

			if ($category_id > 0)
			{
				$sql = 'UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', $data) . ' WHERE category_id = ' . (int) $category_id;
				$db->sql_query($sql);
				$this->add_log($db, $user, 'category_updated', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_UPDATED', $name));
			}
			else
			{
				$sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $data);
				$db->sql_query($sql);
				$this->add_log($db, $user, 'category_created', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_CREATED', $name));
			}

			trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_SAVED') . adm_back_link($this->u_action));
		}

		if ($action === 'delete' && $category_id > 0)
		{
			$linked_items = $this->count_items_in_category($db, $category_id);
			if ($linked_items > 0)
			{
				trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_NOT_EMPTY', $linked_items) . adm_back_link($this->u_action));
			}

			if (confirm_box(true))
			{
				$sql = 'SELECT category_name FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
				$result = $db->sql_query($sql);
				$deleted_category = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$sql = 'DELETE FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
				$db->sql_query($sql);
				$this->add_log($db, $user, 'category_deleted', $user->lang('ACP_DOWNLOADCENTER_LOG_CATEGORY_DELETED', $deleted_category ? $deleted_category['category_name'] : (string) $category_id));
				trigger_error($user->lang('ACP_DOWNLOADCENTER_CATEGORY_DELETED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(false, $user->lang('ACP_DOWNLOADCENTER_CONFIRM_DELETE_CATEGORY'), build_hidden_fields([
					'action' => 'delete',
					'category_id' => $category_id,
				]));
			}
		}

		$edit_category = [
			'category_id' => 0,
			'category_name' => '',
			'category_desc' => '',
			'category_order' => 0,
			'category_enabled' => 1,
		];

		if ($action === 'edit' && $category_id > 0)
		{
			$sql = 'SELECT * FROM ' . $table . ' WHERE category_id = ' . (int) $category_id;
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			if ($row)
			{
				$edit_category = $row;
			}
		}

		$category_item_counts = $this->get_category_item_counts($db);

		$sql = 'SELECT * FROM ' . $table . ' ORDER BY category_order ASC, category_name ASC';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$item_count = isset($category_item_counts[(int) $row['category_id']]) ? (int) $category_item_counts[(int) $row['category_id']] : 0;
			$template->assign_block_vars('categories', [
				'CATEGORY_ID' => (int) $row['category_id'],
				'CATEGORY_NAME' => $row['category_name'],
				'CATEGORY_DESC' => $row['category_desc'],
				'CATEGORY_ORDER' => (int) $row['category_order'],
				'CATEGORY_ENABLED' => (bool) $row['category_enabled'],
				'CATEGORY_ITEM_COUNT' => $item_count,
				'S_HAS_ITEMS' => $item_count > 0,
				'U_EDIT' => $this->u_action . '&amp;action=edit&amp;category_id=' . (int) $row['category_id'],
				'U_DELETE' => $this->u_action . '&amp;action=delete&amp;category_id=' . (int) $row['category_id'],
			]);
		}
		$db->sql_freeresult($result);

		$template->assign_vars([
			'EDIT_CATEGORY_ID' => (int) $edit_category['category_id'],
			'EDIT_CATEGORY_NAME' => $edit_category['category_name'],
			'EDIT_CATEGORY_DESC' => $edit_category['category_desc'],
			'EDIT_CATEGORY_ORDER' => (int) $edit_category['category_order'],
			'EDIT_CATEGORY_ENABLED' => (bool) $edit_category['category_enabled'],
		]);
	}

	protected function get_category_item_counts($db)
	{
		$counts = [];
		$sql = 'SELECT category_id, COUNT(item_id) AS item_count
			FROM ' . $this->table_prefix . 'downloadcenter_items
			GROUP BY category_id';
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$counts[(int) $row['category_id']] = (int) $row['item_count'];
		}
		$db->sql_freeresult($result);

		return $counts;
	}

	protected function count_items_in_category($db, $category_id)
	{
		$sql = 'SELECT COUNT(item_id) AS item_count
			FROM ' . $this->table_prefix . 'downloadcenter_items
			WHERE category_id = ' . (int) $category_id;
		$result = $db->sql_query($sql);
		$count = (int) $db->sql_fetchfield('item_count');
		$db->sql_freeresult($result);

		return $count;
	}
}

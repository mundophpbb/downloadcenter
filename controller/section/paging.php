<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\controller\section;

/**
 * Public pagination.
 */
trait paging
{
	protected function pagination_url($base_url, array $params)
	{
		$query = [];

		foreach ($params as $key => $value)
		{
			if ($value === '' || $value === null || $value === 0 || $value === '0')
			{
				continue;
			}

			$query[] = urlencode($key) . '=' . urlencode((string) $value);
		}

		$separator = (strpos($base_url, '?') === false) ? '?' : '&amp;';
		return $base_url . ($query ? $separator . implode('&amp;', $query) : '');
	}

	protected function assign_pagination($base_url, $total_items, $per_page, $start)
	{
		if ($total_items <= $per_page)
		{
			return;
		}

		$total_pages = (int) ceil($total_items / $per_page);
		$current_page = (int) floor($start / $per_page) + 1;

		$this->template->assign_vars([
			'S_HAS_PAGINATION' => true,
			'PAGE_NUMBER' => $this->user->lang('DOWNLOADCENTER_PAGE_NUMBER', $current_page, $total_pages),
			'U_DC_PREVIOUS_PAGE' => $current_page > 1 ? $this->append_start_to_url($base_url, max(0, $start - $per_page)) : '',
			'U_DC_NEXT_PAGE' => $current_page < $total_pages ? $this->append_start_to_url($base_url, $start + $per_page) : '',
		]);

		$pages = array_unique(array_merge([1], range(max(1, $current_page - 2), min($total_pages, $current_page + 2)), [$total_pages]));
		$previous_page = 0;

		foreach ($pages as $page)
		{
			if ($page - $previous_page > 1)
			{
				$this->template->assign_block_vars('dc_pages', ['S_GAP' => true]);
			}

			$this->template->assign_block_vars('dc_pages', [
				'NUMBER' => $page,
				'LABEL' => $this->user->lang('DOWNLOADCENTER_PAGE_LINK', $page),
				'S_CURRENT' => $page === $current_page,
				'U_PAGE' => $this->append_start_to_url($base_url, ($page - 1) * $per_page),
			]);
			$previous_page = $page;
		}
	}

	protected function append_start_to_url($url, $start)
	{
		$separator = (strpos($url, '?') === false) ? '?' : '&amp;';
		return $url . $separator . 'start=' . $start;
	}
}

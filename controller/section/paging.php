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

		return $base_url . ($query ? '?' . implode('&amp;', $query) : '');
	}

	protected function make_page_number($total_items, $per_page, $start)
	{
		$total_items = max(0, (int) $total_items);
		$per_page = max(1, (int) $per_page);
		$start = max(0, (int) $start);

		if ($total_items === 0)
		{
			return $this->user->lang('DOWNLOADCENTER_PAGE_NUMBER', 1, 1);
		}

		$current_page = (int) floor($start / $per_page) + 1;
		$total_pages = (int) ceil($total_items / $per_page);

		return $this->user->lang('DOWNLOADCENTER_PAGE_NUMBER', $current_page, $total_pages);
	}

	protected function make_pagination($base_url, $total_items, $per_page, $start)
	{
		$total_items = max(0, (int) $total_items);
		$per_page = max(1, (int) $per_page);
		$start = max(0, (int) $start);

		if ($total_items <= $per_page)
		{
			return '';
		}

		$total_pages = (int) ceil($total_items / $per_page);
		$current_page = (int) floor($start / $per_page) + 1;
		$links = [];

		if ($current_page > 1)
		{
			$links[] = '<a href="' . $this->append_start_to_url($base_url, max(0, $start - $per_page)) . '">&laquo; ' . $this->user->lang('DOWNLOADCENTER_PREVIOUS') . '</a>';
		}

		for ($page = 1; $page <= $total_pages; $page++)
		{
			if ($page !== 1 && $page !== $total_pages && abs($page - $current_page) > 2)
			{
				if ($page === 2 || $page === $total_pages - 1)
				{
					$links[] = '<span>...</span>';
				}
				continue;
			}

			$page_start = ($page - 1) * $per_page;
			if ($page === $current_page)
			{
				$links[] = '<strong>' . $page . '</strong>';
			}
			else
			{
				$links[] = '<a href="' . $this->append_start_to_url($base_url, $page_start) . '">' . $page . '</a>';
			}
		}

		if ($current_page < $total_pages)
		{
			$links[] = '<a href="' . $this->append_start_to_url($base_url, $start + $per_page) . '">' . $this->user->lang('DOWNLOADCENTER_NEXT') . ' &raquo;</a>';
		}

		return implode(' ', $links);
	}

	protected function append_start_to_url($url, $start)
	{
		$url = preg_replace('/(&amp;|&)start=\d+/', '', $url);
		$separator = (strpos($url, '?') === false) ? '?' : '&amp;';
		return $url . $separator . 'start=' . max(0, (int) $start);
	}
}

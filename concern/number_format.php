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
 * Compact counters for public pages: 999, 1.2K, 15K, 3.4M (pt_br: 1,2 mil, 3,4 mi).
 */
trait number_format
{
	/**
	 * @param int        $number
	 * @param \phpbb\user $user
	 * @return string
	 */
	protected function format_compact_number($number, $user = null)
	{
		$number = max(0, (int) $number);

		if ($user === null)
		{
			global $user;
		}

		$lang = function ($key, $fallback) use ($user) {
			return (is_object($user) && isset($user->lang[$key])) ? $user->lang[$key] : $fallback;
		};

		if ($number < 1000)
		{
			return (string) $number;
		}

		$units = [
			1000000000 => $lang('DOWNLOADCENTER_NUMBER_BILLION', '%sB'),
			1000000 => $lang('DOWNLOADCENTER_NUMBER_MILLION', '%sM'),
			1000 => $lang('DOWNLOADCENTER_NUMBER_THOUSAND', '%sK'),
		];

		foreach ($units as $divisor => $pattern)
		{
			if ($number < $divisor)
			{
				continue;
			}

			// Truncate instead of rounding so 999,999 never shows as "1000K".
			$value = $number / $divisor;
			$decimals = ($value < 10) ? 1 : 0;
			$factor = pow(10, $decimals);
			$value = floor($value * $factor) / $factor;

			$formatted = number_format($value, $decimals, $lang('DOWNLOADCENTER_NUMBER_DECIMAL_SEP', '.'), '');
			if ($decimals > 0)
			{
				$formatted = preg_replace('/[^0-9]0$/', '', $formatted);
			}

			return sprintf($pattern, $formatted);
		}

		return (string) $number;
	}
}

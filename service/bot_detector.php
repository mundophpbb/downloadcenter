<?php
/**
 * Download Center for phpBB 3.3.x
 *
 * @package mundophpbb/downloadcenter
 * @copyright (c) 2026 Chico Gois
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\downloadcenter\service;

/**
 * Tells downloads made by people from downloads made by bots, crawlers and scripts.
 *
 * Every download is still counted in the totals. The source is only recorded
 * so the ACP can show how many of them came from real people.
 */
class bot_detector
{
	/** Download made by a person (a browser, or any registered member). */
	const SOURCE_HUMAN = 0;

	/** Download made by a bot, crawler, link preview or script. */
	const SOURCE_BOT = 1;

	/** Guest download recorded before 1.3.13, when the browser was not stored. */
	const SOURCE_UNKNOWN = 2;

	/** config_text key with the admin's extra identifiers, one per line. */
	const CONFIG_TEXT_KEY = 'mundophpbb_downloadcenter_bot_patterns';

	/**
	 * Parts of a user agent that identify automated clients. Matched case-insensitively.
	 * "bot" itself is handled separately so phone models such as CUBOT are not caught.
	 */
	const DEFAULT_PATTERNS = [
		// Generic crawler words
		'crawl', 'spider', 'slurp', 'scraper', 'archiver', 'fetcher', 'indexer', 'robot',
		// Search engines and SEO tools
		// (Browser names such as DuckDuckGo, YaBrowser or Seznam are deliberately not listed;
		// their crawlers are caught by the "...bot" rule below.)
		'mediapartners-google', 'adsbot', 'bingpreview', 'yandexmetrika', 'qwantify', 'exabot',
		'petalbot', 'bytespider', 'semrush', 'ahrefs', 'mj12bot',
		'dotbot', 'blexbot', 'serpstat', 'dataforseo', 'screaming frog', 'sitebulb',
		// AI crawlers
		'gptbot', 'chatgpt', 'oai-searchbot', 'claudebot', 'claude-web', 'anthropic', 'perplexity',
		'ccbot', 'cohere', 'diffbot', 'amazonbot', 'applebot', 'meta-externalagent', 'imagesift', 'timpibot',
		// Link previews and social networks
		'facebookexternalhit', 'facebookcatalog', 'twitterbot', 'linkedinbot', 'whatsapp', 'telegrambot',
		'discordbot', 'slackbot', 'skypeuripreview', 'embedly', 'pinterestbot', 'redditbot', 'vkshare',
		// Monitoring and testing
		'uptimerobot', 'pingdom', 'statuscake', 'site24x7', 'lighthouse', 'pagespeed', 'gtmetrix',
		'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium', 'webdriver',
		// Scripts, libraries and command-line tools
		'curl', 'wget', 'python', 'aiohttp', 'httpx', 'scrapy', 'java/', 'okhttp', 'apache-httpclient',
		'go-http-client', 'node-fetch', 'axios', 'undici', 'got (', 'guzzle', 'libwww', 'lwp::', 'winhttp',
		'powershell', 'httrack', 'offline explorer', 'webcopier', 'teleport', 'wpscan', 'nikto', 'nmap',
		'masscan', 'zgrab', 'censys', 'nuclei', 'sqlmap',
	];

	/** @var string[]|null Admin identifiers, read once per request */
	protected static $extra_patterns;

	/**
	 * Classify the current visitor.
	 *
	 * @param \phpbb\user $user
	 * @return int One of the SOURCE_* constants (never SOURCE_UNKNOWN)
	 */
	public static function classify($user)
	{
		$data = isset($user->data) && is_array($user->data) ? $user->data : [];

		// phpBB already recognised a bot from ACP > General > Spiders/Robots.
		if (!empty($data['is_bot']))
		{
			return self::SOURCE_BOT;
		}

		// Registered members are people, whatever program they download with.
		if (!empty($data['is_registered']) || (isset($data['user_id']) && (int) $data['user_id'] !== ANONYMOUS && (int) ($data['user_type'] ?? 0) !== USER_IGNORE))
		{
			return self::SOURCE_HUMAN;
		}

		return self::is_bot_user_agent(self::user_agent($user)) ? self::SOURCE_BOT : self::SOURCE_HUMAN;
	}

	/**
	 * Whether a user agent belongs to a bot, crawler or script.
	 *
	 * @param string $user_agent
	 * @return bool
	 */
	public static function is_bot_user_agent($user_agent)
	{
		return self::match($user_agent) !== '';
	}

	/**
	 * The identifier that marks the user agent as automated, or '' for a browser.
	 *
	 * @param string $user_agent
	 * @return string
	 */
	public static function match($user_agent)
	{
		$ua = strtolower(trim((string) $user_agent));

		if ($ua === '')
		{
			return 'empty';
		}

		foreach (self::get_extra_patterns() as $pattern)
		{
			if (strpos($ua, $pattern) !== false)
			{
				return $pattern;
			}
		}

		foreach (self::DEFAULT_PATTERNS as $pattern)
		{
			if (strpos($ua, $pattern) !== false)
			{
				return $pattern;
			}
		}

		// "bot" at the end of a word (Googlebot, AhrefsBot/7.0, bingbot) but not CUBOT phones.
		if (preg_match('/bot(?![a-z])/', str_replace('cubot', '', $ua)))
		{
			return 'bot';
		}

		// Every real browser sends a Mozilla/ (or old Opera/) user agent.
		if (strpos($ua, 'mozilla/') === false && strpos($ua, 'opera/') === false)
		{
			return 'not a browser';
		}

		return '';
	}

	/**
	 * The current visitor's user agent, cut to fit the database column.
	 *
	 * @param \phpbb\user $user
	 * @return string
	 */
	public static function user_agent($user)
	{
		$ua = isset($user->browser) ? (string) $user->browser : '';
		if ($ua === '' && isset($_SERVER['HTTP_USER_AGENT']))
		{
			$ua = (string) $_SERVER['HTTP_USER_AGENT'];
		}

		$ua = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $ua));

		return function_exists('utf8_substr') ? utf8_substr($ua, 0, 255) : substr($ua, 0, 255);
	}

	/**
	 * Turn the admin's text (one identifier per line) into a clean list.
	 *
	 * @param string $text
	 * @return string[]
	 */
	public static function parse_patterns($text)
	{
		$patterns = [];
		foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line)
		{
			$line = strtolower(trim($line));
			if ($line === '' || $line[0] === '#')
			{
				continue;
			}
			$line = substr($line, 0, 100);
			if (!in_array($line, $patterns, true))
			{
				$patterns[] = $line;
			}
		}

		return array_slice($patterns, 0, 200);
	}

	/**
	 * Forget the cached admin identifiers (after they are saved in the ACP).
	 */
	public static function reset()
	{
		self::$extra_patterns = null;
	}

	/**
	 * @return string[]
	 */
	protected static function get_extra_patterns()
	{
		if (self::$extra_patterns === null)
		{
			self::$extra_patterns = [];

			global $phpbb_container;
			if ($phpbb_container && $phpbb_container->has('config_text'))
			{
				try
				{
					self::$extra_patterns = self::parse_patterns((string) $phpbb_container->get('config_text')->get(self::CONFIG_TEXT_KEY));
				}
				catch (\Exception $e)
				{
					self::$extra_patterns = [];
				}
			}
		}

		return self::$extra_patterns;
	}
}

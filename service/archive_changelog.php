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
 * Reads the changelog shipped inside a ZIP file (CHANGELOG.md and similar)
 * and returns the notes of one version as BBCode.
 *
 * Nothing is guessed: when the archive has no changelog, or the changelog has
 * no section for the requested version, an empty string is returned.
 */
class archive_changelog
{
	/** File names looked for, in order of preference (compared case-insensitively). */
	const FILE_NAMES = [
		'changelog.md', 'changelog.markdown', 'changelog', 'changelog.txt',
		'changes.md', 'changes.txt', 'history.md', 'history.txt', 'releases.md',
	];

	/** Folders never searched (bundled libraries and system files). */
	const SKIPPED_FOLDERS = ['vendor', 'node_modules', '.git', '__macosx', 'tests', 'test'];

	/** Larger changelog files are ignored. */
	const MAX_FILE_BYTES = 1048576;

	/** Archives with more entries are not scanned. */
	const MAX_ENTRIES = 20000;

	/** The returned BBCode is cut to this many characters. */
	const MAX_RESULT_CHARS = 60000;

	/**
	 * Notes of a version, as BBCode, from the changelog inside a ZIP file.
	 *
	 * @param string $path    Path of the stored file on disk
	 * @param string $version Version number, e.g. "1.3.14" or "v1.3.14"
	 * @return array{bbcode: string, file: string} Empty bbcode when nothing was found
	 */
	public static function from_zip($path, $version)
	{
		$none = ['bbcode' => '', 'file' => ''];
		$version = self::normalise_version($version);

		if ($version === '' || !class_exists('ZipArchive') || !is_file($path) || is_link($path) || !is_readable($path))
		{
			return $none;
		}

		$zip = new \ZipArchive();
		if ($zip->open($path) !== true)
		{
			return $none;
		}

		try
		{
			foreach (self::candidate_entries($zip) as $entry)
			{
				$stat = $zip->statIndex($entry['index']);
				if (!$stat || (int) $stat['size'] > self::MAX_FILE_BYTES)
				{
					continue;
				}

				$text = $zip->getFromIndex($entry['index']);
				if (!is_string($text) || $text === '')
				{
					continue;
				}

				$section = self::section_for_version(self::to_utf8($text), $version);
				if ($section !== '')
				{
					$bbcode = self::markdown_to_bbcode($section);
					if ($bbcode !== '')
					{
						if (function_exists('utf8_strlen') && utf8_strlen($bbcode) > self::MAX_RESULT_CHARS)
						{
							$bbcode = rtrim(utf8_substr($bbcode, 0, self::MAX_RESULT_CHARS));
						}

						return ['bbcode' => $bbcode, 'file' => $entry['name']];
					}
				}
			}
		}
		finally
		{
			$zip->close();
		}

		return $none;
	}

	/**
	 * Changelog files in the archive, closest to the root first.
	 *
	 * @return array[] Each with index, name, depth and priority
	 */
	protected static function candidate_entries(\ZipArchive $zip)
	{
		$candidates = [];
		$count = min($zip->numFiles, self::MAX_ENTRIES);

		for ($i = 0; $i < $count; $i++)
		{
			$name = (string) $zip->getNameIndex($i);
			if ($name === '' || substr($name, -1) === '/')
			{
				continue;
			}

			$parts = explode('/', strtolower(str_replace('\\', '/', $name)));
			$base = array_pop($parts);
			$priority = array_search($base, self::FILE_NAMES, true);
			if ($priority === false || array_intersect($parts, self::SKIPPED_FOLDERS))
			{
				continue;
			}

			$candidates[] = ['index' => $i, 'name' => $name, 'depth' => count($parts), 'priority' => $priority];
		}

		usort($candidates, function ($a, $b) {
			return [$a['depth'], $a['priority'], $a['name']] <=> [$b['depth'], $b['priority'], $b['name']];
		});

		return $candidates;
	}

	/**
	 * "v1.3.14 " → "1.3.14"
	 *
	 * @param string $version
	 * @return string
	 */
	public static function normalise_version($version)
	{
		$version = trim((string) $version);
		if (preg_match('/^v(?=\d)/i', $version))
		{
			$version = substr($version, 1);
		}

		return $version;
	}

	/**
	 * The lines under the heading of a version, up to the next heading of the same level.
	 *
	 * Understood headings: Markdown "## 1.3.14", "## [1.3.14] - 2026-11-26",
	 * "### Version 1.3.14", underlined (setext) headings, and plain lines that
	 * start with the version, such as "1.3.14 (2026-11-26)" or "Version 1.3.14".
	 *
	 * @param string $text
	 * @param string $version Normalised version
	 * @return string Markdown of the section, without its heading
	 */
	public static function section_for_version($text, $version)
	{
		$lines = preg_split('/\r\n|\r|\n/', $text);
		$token = '(?<![0-9A-Za-z.])[vV]?' . preg_quote($version, '/') . '(?![0-9A-Za-z]|[.\-+][0-9A-Za-z])';

		$headings = self::headings($lines);
		$start = null;
		$level = 0;

		foreach ($headings as $index => $heading)
		{
			if (preg_match('/' . $token . '/', $heading['text']))
			{
				$start = $index;
				$level = $heading['level'];
				break;
			}
		}

		// Plain-text changelogs: a line starting with the version (optionally "Version").
		$plain = false;
		if ($start === null)
		{
			foreach ($lines as $index => $line)
			{
				if (preg_match('/^\s{0,3}(?:version|vers[aã]o|release)?\s*' . $token . '\b/iu', $line))
				{
					$start = $index;
					$plain = true;
					break;
				}
			}
		}

		if ($start === null)
		{
			return '';
		}

		$first = $plain ? $start + 1 : $headings[$start]['end'] + 1;
		$section = [];

		for ($i = $first, $total = count($lines); $i < $total; $i++)
		{
			if ($plain)
			{
				// The next version line (any version number) ends the section.
				if (preg_match('/^\s{0,3}(?:#{1,6}\s+)?(?:version|vers[aã]o|release)?\s*\[?[vV]?\d+\.\d+/iu', $lines[$i]))
				{
					break;
				}
				// Underline of the version line.
				if ($i === $first && preg_match('/^\s*[=\-~]{3,}\s*$/', $lines[$i]))
				{
					continue;
				}
			}
			else if (isset($headings[$i]) && $headings[$i]['level'] <= $level)
			{
				break;
			}

			$section[] = $lines[$i];
		}

		// Drop a trailing link reference block ("[1.3.14]: https://...") and surrounding blank lines.
		$markdown = trim(implode("\n", $section), "\n");
		$markdown = preg_replace('/(?:\n\s*\[[^\]\n]+\]:\s*\S+\s*)+$/', '', $markdown);

		return trim($markdown);
	}

	/**
	 * Markdown headings: line index => [level, text, end line index].
	 */
	protected static function headings(array $lines)
	{
		$headings = [];
		$in_code = false;

		foreach ($lines as $i => $line)
		{
			if (preg_match('/^\s{0,3}(```|~~~)/', $line))
			{
				$in_code = !$in_code;
				continue;
			}
			if ($in_code)
			{
				continue;
			}

			if (preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m))
			{
				$headings[$i] = ['level' => strlen($m[1]), 'text' => $m[2], 'end' => $i];
			}
			else if (trim($line) !== '' && isset($lines[$i + 1]) && preg_match('/^\s{0,3}(=+|-+)\s*$/', $lines[$i + 1], $m)
				&& !preg_match('/^\s*([*+\-]|\d+\.)\s/', $line))
			{
				$headings[$i] = ['level' => $m[1][0] === '=' ? 1 : 2, 'text' => trim($line), 'end' => $i + 1];
			}
		}

		return $headings;
	}

	/**
	 * Convert the Markdown of a changelog section to phpBB BBCode.
	 *
	 * Headings become bold lines, bullet and numbered lists become [list],
	 * fenced code becomes [code], and **bold**, *italic*, `code` and links are kept.
	 *
	 * @param string $markdown
	 * @return string
	 */
	public static function markdown_to_bbcode($markdown)
	{
		$lines = preg_split('/\r\n|\r|\n/', (string) $markdown);
		$out = [];
		$lists = []; // stack of [indent, closing tag]
		$in_code = false;
		$code = [];

		$close_lists = function ($indent = -1) use (&$lists, &$out) {
			while ($lists && end($lists)[0] > $indent)
			{
				$out[] = array_pop($lists)[1];
			}
		};

		foreach ($lines as $line)
		{
			if (preg_match('/^\s*(```|~~~)/', $line))
			{
				if ($in_code)
				{
					$out[] = '[code]' . implode("\n", $code) . '[/code]';
					$code = [];
					$in_code = false;
				}
				else
				{
					$close_lists();
					$in_code = true;
				}
				continue;
			}
			if ($in_code)
			{
				$code[] = $line;
				continue;
			}

			$expanded = str_replace("\t", '    ', $line);

			if (trim($expanded) === '')
			{
				$close_lists();
				if ($out && end($out) !== '')
				{
					$out[] = '';
				}
				continue;
			}

			if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/', $expanded, $m))
			{
				$close_lists();
				$out[] = '[b]' . self::inline($m[1]) . '[/b]';
				continue;
			}

			if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $expanded))
			{
				$close_lists();
				continue;
			}

			if (preg_match('/^(\s*)([*+\-]|\d+[.)])\s+(.*)$/', $expanded, $m))
			{
				$indent = strlen($m[1]);
				$ordered = !in_array($m[2], ['*', '+', '-'], true);

				$close_lists($indent);
				if (!$lists || end($lists)[0] < $indent)
				{
					$out[] = $ordered ? '[list=1]' : '[list]';
					$lists[] = [$indent, $ordered ? '[/list]' : '[/list]'];
				}

				$out[] = '[*]' . self::inline(preg_replace('/^\[[ xX]\]\s+/', '', $m[3]));
				continue;
			}

			// Text that continues a list item, or a plain paragraph line.
			if ($lists && preg_match('/^\s+\S/', $expanded))
			{
				$out[count($out) - 1] .= ' ' . self::inline(trim($expanded));
				continue;
			}

			$close_lists();
			$text = preg_replace('/^\s{0,3}>\s?/', '', $expanded);
			$out[] = self::inline(trim($text));
		}

		if ($in_code)
		{
			$out[] = '[code]' . implode("\n", $code) . '[/code]';
		}
		$close_lists();

		return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
	}

	/**
	 * Inline Markdown: `code`, **bold**, *italic*, [text](url) and <https://autolinks>.
	 */
	protected static function inline($text)
	{
		// Protect code spans so their content is left untouched.
		$codes = [];
		$text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
			$codes[] = '`' . $m[1] . '`';
			return "\x1A" . (count($codes) - 1) . "\x1A";
		}, $text);

		// Images and links. Only http(s) targets become links.
		$text = preg_replace_callback('/!?\[([^\]]*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/', function ($m) {
			$label = $m[1] !== '' ? $m[1] : $m[2];
			return preg_match('#^https?://#i', $m[2]) ? '[url=' . $m[2] . ']' . $label . '[/url]' : $label;
		}, $text);
		$text = preg_replace('#<(https?://[^>\s]+)>#i', '[url]$1[/url]', $text);

		$text = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '[b]$2[/b]', $text);
		$text = preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '[i]$1[/i]', $text);
		$text = preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/', '[i]$1[/i]', $text);
		$text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/', '[s]$1[/s]', $text);

		return preg_replace_callback("/\x1A(\d+)\x1A/", function ($m) use ($codes) {
			return $codes[(int) $m[1]];
		}, $text);
	}

	/**
	 * Strip a byte order mark and make sure the text is UTF-8.
	 */
	protected static function to_utf8($text)
	{
		if (strncmp($text, "\xEF\xBB\xBF", 3) === 0)
		{
			return substr($text, 3);
		}
		if (strncmp($text, "\xFF\xFE", 2) === 0 && function_exists('mb_convert_encoding'))
		{
			return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
		}
		if (strncmp($text, "\xFE\xFF", 2) === 0 && function_exists('mb_convert_encoding'))
		{
			return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
		}
		if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8') && function_exists('mb_convert_encoding'))
		{
			return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
		}

		return $text;
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Zabbix log file helper.
 */
class CLogHelper {

	/**
	 * Log file offsets (for incremental log read).
	 *
	 * @var array
	 */
	private static $log_offsets = [];

	/**
	 * Clear contents of log.
	 *
	 * @param string $path    log file path
	 */
	public static function clearLog($path) {
		file_put_contents($path, '');
		self::resetLogOffset($path);
	}

	/**
	 * Reset log offset.
	 *
	 * @param string $path    log file path
	 */
	public static function resetLogOffset($path) {
		self::$log_offsets[$path] = 0;
	}

	/**
	 * Read content of the log.
	 *
	 * @param string  $path          log file path
	 * @param boolean $incremental   flag to be used to enable incremental read
	 *
	 * @return string
	 *
	 * @throws Exception    on cases when log is not available
	 */
	public static function readLog($path, $incremental = false) {
		$offset = ($incremental && array_key_exists($path, self::$log_offsets))
				? self::$log_offsets[$path] : 0;

		if (($content = file_get_contents($path, false, null, $offset)) === false) {
			throw new Exception('Failed to read log "'.$path.'".');
		}

		if ($incremental) {
			$pos = strrpos($content, "\n");
			if ($pos === false) {
				$pos = strlen($content);
			}

			self::$log_offsets[$path] = $offset + $pos;
		}

		return $content;
	}

	/**
	 * Read log until specified line is present.
	 *
	 * @param string       $path          log file path
	 * @param string|array $lines         line(s) to look for
	 * @param boolean      $incremental   flag to be used to enable incremental read
	 * @param boolean      $match_regex   flag to be used to match line by regex
	 *
	 * @return string|null
	 *
	 * @throws Exception    on cases when log is not available
	 */
	public static function readLogUntil($path, $lines, $incremental = true, $match_regex = false) {
		if (!is_array($lines)) {
			$lines = [$lines];
		}

		$content = self::readLog($path, $incremental);
		$offset = -1;
		foreach ($lines as $line) {
			if (($temp = self::getLineOffset($content, $line, $match_regex)) === null) {
				continue;
			}

			$offset = ($offset !== -1) ? min([$offset, $temp]) : $temp;
		}

		if ($offset === -1) {
			return null;
		}

		$position = strpos($content, "\n", $offset);
		if ($position === false) {
			return $content;
		}

		$position += 1;

		if ($incremental) {
			$pos = strrpos($content, "\n");
			if ($pos === false) {
				$pos = strlen($content);
			}

			self::$log_offsets[$path] -= $pos - $position;
		}

		return substr($content, 0, $position);
	}

	/**
	 * Get offset of line in log content.
	 *
	 * @param string  $content      log content
	 * @param string  $line         line to look for
	 * @param bool    $match_regex  match lines by regex
	 *
	 * @return integer|null
	 */
	public static function getLineOffset($content, $line, $match_regex = false) {
		$matches = [];

		$pattern = '';

		// "  563:20220112:232318.543 ..."
		$log_pattern = ' *[0-9]+:[0-9]+:[0-9]+\.[0-9]+';

		// "2022/01/12 23:23:19.550415 ..."
		$log2_pattern = '[0-9]+\/[0-9]+\/[0-9]+ [0-9]+:[0-9]+:[0-9]+\.[0-9]+';

		if ($match_regex === false) {
			$pattern = '/^('.$log_pattern.'|'.$log2_pattern.') .*'.preg_quote($line, '/').'.*$/m';
		}
		else {
			$pattern = '/^('.$log_pattern.'|'.$log2_pattern.') .*'.$line.'.*$/m';
		}

		if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) === 1) {
			return $matches[0][1];
		}

		return null;
	}

	/**
	 * Check if line is present.
	 *
	 * @param string  $path          log file path
	 * @param string|array $lines    line(s) to look for
	 * @param boolean $incremental   flag to be used to enable incremental read
	 * @param boolean $match_regex   flag to be used to match line by regex
	 *
	 * @return boolean
	 */
	public static function isLogLinePresent($path, $lines, $incremental = true, $match_regex = false) {
		return (self::readLogUntil($path, $lines, $incremental, $match_regex) !== null);
	}
}

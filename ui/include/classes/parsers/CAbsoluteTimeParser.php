<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * A parser for absolute time or date based on passed configuration to parser.
 */
class CAbsoluteTimeParser extends CParser {

	/**
	 * Time in format, which depends on $date_only value:
	 * - FALSE: "YYYY-MM-DD hh:mm:ss"
	 * - TRUE: "YYYY-MM-DD"
	 */
	private string $date;
	private array $tokens;
	private bool $date_only = false;

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */

	/**
	 * @param array $options
	 * @param bool  $options['date_only']
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('date_only', $options)) {
			$this->date_only = $options['date_only'];
		}
	}

	public function parse($source, $pos = 0):int {
		$this->resetState();

		$p = $pos;

		if (!$this->parseAbsoluteTime($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	private function resetState() {
		$this->tokens = [];
		$this->length = 0;
		$this->match = '';
		$this->date = '';
	}

	/**
	 * Parse absolute time.
	 *
	 * @param string $source
	 * @param int $pos
	 *
	 * @return bool
	 */
	private function parseAbsoluteTime(string $source, int &$pos): bool {
		$pattern = [
			'Y' => '(?P<Y>[12][0-9]{3})',
			'm' => '(?P<m>[0-9]{1,2})',
			'd' => '(?P<d>[0-9]{1,2})',
			'H' => '(?P<H>[0-9]{1,2})',
			'i' => '(?P<i>[0-9]{1,2})',
			's' => '(?P<s>[0-9]{1,2})'
		];

		$date_pattern = $this->date_only
			? '^'.$pattern['Y'].'(-'.$pattern['m'].'(-'.$pattern['d'].')?)?$'
			: $pattern['Y'].'(-'.$pattern['m'].'(-'.$pattern['d'].
				'( +'.$pattern['H'].'(:'.$pattern['i'].'(:'.$pattern['s'].')?)?)?)?)?';

		if (!preg_match('/^'.$date_pattern .'/', substr($source, $pos), $matches)) {
			return false;
		}

		$this->tokens = array_intersect_key($matches, array_flip(['Y', 'm', 'd', 'H', 'i', 's']));

		$date = $this->buildDateString($matches);
		$datetime = date_create($date);

		if ($datetime === false) {
			return false;
		}

		$errors = $datetime->getLastErrors();

		if ($errors && ($errors['errors'] || $errors['warnings'])) {
			return false;
		}

		$this->date = $date;
		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Get DateTime object with its value set to either start or end of the period derived from the date/time specified.
	 *
	 * @param bool              $is_start
	 * @param DateTimeZone|null $timezone
	 *
	 * @return DateTime|null
	 */
	public function getDateTime(bool $is_start, ?DateTimeZone $timezone = null): ?DateTime {
		if ($this->date === '') {
			return null;
		}

		$date = new DateTime($this->date, $timezone);

		if ($is_start || $this->date_only) {
			return $date;
		}

		if (!array_key_exists('m', $this->tokens)) {
			return $date->modify('last day of December this year 23:59:59');
		}

		if (!array_key_exists('d', $this->tokens)) {
			return $date->modify('last day of this month 23:59:59');
		}

		if (!array_key_exists('H', $this->tokens)) {
			return $date->modify('23:59:59');
		}

		if (!array_key_exists('i', $this->tokens)) {
			return new DateTime($date->format('Y-m-d H:59:59'), $timezone);
		}

		if (!array_key_exists('s', $this->tokens)) {
			return new DateTime($date->format('Y-m-d H:i:59'), $timezone);
		}

		return $date;
	}

	private function buildDateString(array $matches): string {
		if($this->date_only) {
			$matches += ['m' => 1, 'd' => 1];
			return sprintf('%04d-%02d-%02d', $matches['Y'], $matches['m'], $matches['d']);
		}

		$matches += ['m' => 1, 'd' => 1, 'H' => 0, 'i' => 0, 's' => 0];
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches['Y'], $matches['m'], $matches['d'], $matches['H'],
			$matches['i'], $matches['s']
		);
	}
}

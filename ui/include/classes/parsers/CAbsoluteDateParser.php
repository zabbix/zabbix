<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * A parser for absolute date in "YYYY[-MM[-DD]]" format.
 */
class CAbsoluteDateParser extends CParser {

	/**
	 * An error message if IP range is not valid.
	 */
	private string $error;

	/**
	 * Supported options:
	 *   'min' => int  Min allowed UNIX timestamp;
	 *   'max' => int  Max allowed UNIX timestamp;
	 */
	private array $options = [
		'min' => 0,
		'max' => ZBX_MAX_DATE
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Parse the given date.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->error = _('invalid date');
		$this->length = 0;
		$this->match = '';

		$pattern_Y = '(?P<Y>[12][0-9]{3})';
		$pattern_m = '(?P<m>[0-9]{1,2})';
		$pattern_d = '(?P<d>[0-9]{1,2})';
		$pattern = '^'.$pattern_Y.'(-'.$pattern_m.'(-'.$pattern_d.')?)?$';
		$subject = substr($source, $pos);

		if (!preg_match('/'.$pattern.'/', $subject, $matches)) {
			return self::PARSE_FAIL;
		}

		$matches += ['m' => 1, 'd' => 1];
		$date = sprintf('%04d-%02d-%02d', $matches['Y'], $matches['m'], $matches['d']);
		$datetime = date_create($date);

		if ($datetime === false) {
			return self::PARSE_FAIL;
		}

		$datetime_errors = $datetime->getLastErrors();

		if ($datetime_errors !== false && $datetime_errors['warning_count'] != 0) {
			return self::PARSE_FAIL;
		}

		$unix_time = strtotime($date);

		if ($unix_time >= $this->options['max'] || $unix_time <= $this->options['min']) {
			$this->error = _('date is outside the allowed range');
			return self::PARSE_FAIL;
		}

		$this->match = $subject;
		$this->length = strlen($subject);

		return self::PARSE_SUCCESS;
	}

	public function getError(): string {
		return $this->error;
	}
}

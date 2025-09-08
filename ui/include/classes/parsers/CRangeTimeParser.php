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
 * A parser for absolute and relative time used in the date-time inputs.
 */
class CRangeTimeParser extends CParser {

	const ZBX_TIME_UNKNOWN = 0;
	const ZBX_TIME_ABSOLUTE = 1;
	const ZBX_TIME_RELATIVE = 2;

	/**
	 * @var int $time_type
	 */
	private $time_type;

	/**
	 * @var CRelativeTimeParser
	 */
	private $relative_time_parser;

	/**
	 * @var CAbsoluteTimeParser
	 */
	private $absolute_time_parser;

	public function __construct() {
		$this->relative_time_parser = new CRelativeTimeParser();
		$this->absolute_time_parser = new CAbsoluteTimeParser();
	}

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->time_type = self::ZBX_TIME_UNKNOWN;

		$p = $pos;

		if ($this->relative_time_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->relative_time_parser->getLength();
			$this->time_type = self::ZBX_TIME_RELATIVE;
		}
		elseif ($this->absolute_time_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->absolute_time_parser->getLength();
			$this->time_type = self::ZBX_TIME_ABSOLUTE;
		}
		else {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	public function getTimeType() {
		return $this->time_type;
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
		switch ($this->time_type) {
			case self::ZBX_TIME_ABSOLUTE:
				return $this->absolute_time_parser->getDateTime($is_start, $timezone);

			case self::ZBX_TIME_RELATIVE:
				return $this->relative_time_parser->getDateTime($is_start, $timezone);

			default:
				return null;
		}
	}
}

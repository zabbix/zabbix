<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * A parser for time used in the time selector.
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
	 * Timestamp is returned as initialized DateTime object. Returns null when timestamp is not valid.
	 *
	 * @param bool   $is_start  If set to true date will be modified to lowest value, example (now/w) will be returned
	 *                          as Monday of this week. When set to false precisiion will modify date to highest value,
	 *                          same example will return Sunday of this week.
	 *
	 * @return DateTime|null
	 */
	public function getDateTime($is_start) {
		if ($this->time_type == self::ZBX_TIME_UNKNOWN) {
			return null;
		}

		if ($this->time_type == self::ZBX_TIME_ABSOLUTE) {
			return $this->absolute_time_parser->getDateTime();
		}

		$date = new DateTime('now');

		foreach ($this->relative_time_parser->getTokens() as $token) {
			switch ($token['type']) {
				case CRelativeTimeParser::ZBX_TOKEN_PRECISION:
					if ($token['suffix'] === 'm' || $token['suffix'] === 'h') {
						$formats = $is_start
							? [
								'm' => 'Y-m-d H:i:00',
								'h' => 'Y-m-d H:00:00'
							]
							: [
								'm' => 'Y-m-d H:i:59',
								'h' => 'Y-m-d H:59:59'
							];

						$date = new DateTime($date->format($formats[$token['suffix']]));
					}
					else {
						$modifiers = $is_start
							? [
								'd' => '00:00:00',
								'w' => 'Monday this week 00:00:00',
								'M' => 'first day of this month 00:00:00',
								'y' => 'first day of January this year 00:00:00'
							]
							: [
								'd' => '23:59:59',
								'w' => 'Sunday this week 23:59:59',
								'M' => 'last day of this month 23:59:59',
								'y' => 'last day of December this year 23:59:59'
							];

						$date->modify($modifiers[$token['suffix']]);
					}
					break;

				case CRelativeTimeParser::ZBX_TOKEN_OFFSET:
					$units = [
						's' => 'second',
						'm' => 'minute',
						'h' => 'hour',
						'd' => 'day',
						'w' => 'week',
						'M' => 'month',
						'y' => 'year'
					];

					$date->modify($token['sign'].$token['value'].' '.$units[$token['suffix']]);
					break;
			}
		}

		return $date;
	}
}

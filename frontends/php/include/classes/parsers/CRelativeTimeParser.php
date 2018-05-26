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
 * A parser for relative time in "now[/<yMwdhm>][<+->N<yMwdhms>[/<yMwdhm>]]" format.
 */
class CRelativeTimeParser extends CParser {

	const ZBX_TOKEN_PRECISION = 0;
	const ZBX_TOKEN_OFFSET = 1;

	/**
	 * @var array $tokens  An array of tokens for relative date.
	 */
	private $tokens;

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->tokens = [];

		$p = $pos;

		if (!$this->parseRelativeTime($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse relative time.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parseRelativeTime($source, &$pos) {
		if (strncmp(substr($source, $pos), 'now', 3) != 0) {
			return false;
		}

		$pos += 3;

		while ($this->parsePrecision($source, $pos) || $this->parseOffset($source, $pos)) {
		}

		return true;
	}

	/**
	 * Parse precision.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parsePrecision($source, &$pos) {
		$pattern = '(\/[yMwdhm])';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$this->tokens[] = [
			'type' => self::ZBX_TOKEN_PRECISION,
			'suffix' => substr($matches[0], 1)
		];

		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Parse offset.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parseOffset($source, &$pos) {
		$pattern = '(?P<offset_sign>[+-])(?P<offset_value>[0-9]+)(?P<offset_suffix>[yMwdhms])?';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$this->tokens[] = [
			'type' => self::ZBX_TOKEN_OFFSET,
			'sign' => $matches['offset_sign'],
			'value' => $matches['offset_value'],
			'suffix' => array_key_exists('offset_suffix', $matches) ? $matches['offset_suffix'] : 's'
		];

		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Returns an array of tokens.
	 *
	 * @return array
	 */
	public function getTokens() {
		return $this->tokens;
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
		if ($this->match === '') {
			return null;
		}

		$date = new DateTime('now');

		foreach ($this->getTokens() as $token) {
			switch ($token['type']) {
				case CRelativeTimeParser::ZBX_TOKEN_PRECISION:
					if ($token['suffix'] === 'm' || $token['suffix'] === 'h' || $token['suffix'] === 'd') {
						$formats = $is_start
							? [
								'd' => 'Y-m-d 00:00:00',
								'm' => 'Y-m-d H:i:00',
								'h' => 'Y-m-d H:00:00'
							]
							: [
								'd' => 'Y-m-d 23:59:59',
								'm' => 'Y-m-d H:i:59',
								'h' => 'Y-m-d H:59:59'
							];

						$date = new DateTime($date->format($formats[$token['suffix']]));
					}
					else {
						$modifiers = $is_start
							? [
								'w' => 'Monday this week 00:00:00',
								'M' => 'first day of this month 00:00:00',
								'y' => 'first day of January this year 00:00:00'
							]
							: [
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

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


class CRangeTimeParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of relative times and parsed results.
	 */
	public static function testProvider() {
		return [
			// Absolute time.
			[
				'2018-04-15 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04-15 12:45:34'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:34', '2018-04-15 12:45:34']]
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04-15 12:45'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:00', '2018-04-15 12:45:00']]
			],
			[
				'2018-04-15 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04-15 12'
				],
				'datetime' => ['values' => ['2018-04-15 12:00:00', '2018-04-15 12:00:00']]
			],
			[
				'2018-04-15', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04-15'
				],
				'datetime' => ['values' => ['2018-04-15 00:00:00', '2018-04-15 00:00:00']]
			],
			[
				'2018-04 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04 12:45:34'
				],
				'datetime' => ['values' => ['2018-04-01 12:45:34', '2018-04-01 12:45:34']]
			],
			[
				'2018-04 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04 12:45'
				],
				'datetime' => ['values' => ['2018-04-01 12:45:00', '2018-04-01 12:45:00']]
			],
			[
				'2018-04 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04 12'
				],
				'datetime' => ['values' => ['2018-04-01 12:00:00', '2018-04-01 12:00:00']]
			],
			[
				'2018-04', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018-04'
				],
				'datetime' => ['values' => ['2018-04-01 00:00:00', '2018-04-01 00:00:00']]
			],
			[
				'2018 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018 12:45:34'
				],
				'datetime' => ['values' => ['2018-01-01 12:45:34', '2018-01-01 12:45:34']]
			],
			[
				'2018 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018 12:45'
				],
				'datetime' => ['values' => ['2018-01-01 12:45:00', '2018-01-01 12:45:00']]
			],
			[
				'2018 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018 12'
				],
				'datetime' => ['values' => ['2018-01-01 12:00:00', '2018-01-01 12:00:00']]
			],
			[
				'2018', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_ABSOLUTE,
					'match' => '2018'
				],
				'datetime' => ['values' => ['2018-01-01 00:00:00', '2018-01-01 00:00:00']]
			],
			// Relative time.
			[
				'now', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now'
				],
				'datetime' => ['modifiers' => [[], []]]
			],
			[
				'now/y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/y'
				],
				'datetime' => ['modifiers' => [['first day of January this year 00:00:00'], ['last day of December this year 23:59:59']]]
			],
			[
				'now/M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00'], ['last day of this month 23:59:59']]]
			],
			[
				'now/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/w'
				],
				'datetime' => ['modifiers' => [['Monday this week 00:00:00'], ['Sunday this week 23:59:59']]]
			],
			[
				'now/d', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/d'
				],
				'datetime' => ['modifiers' => [['today'], ['tomorrow', '-1 second']]]
			],
			[
				'now/h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/h'
				],
				'datetime' => ['formats' => ['H:00:00', 'H:59:59']]
			],
			[
				'now/m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/m'
				],
				'datetime' => ['formats' => ['H:i:00', 'H:i:59']]
			],
			[
				'now/s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now'
				],
				'datetime' => ['modifiers' => [[], []]]
			],
			[
				'now-1y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1y'
				],
				'datetime' => ['modifiers' => [['-1 year'], ['-1 year']]]
			],
			[
				'now-1M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1M'
				],
				'datetime' => ['modifiers' => [['-1 month'], ['-1 month']]]
			],
			[
				'now-1w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1w'
				],
				'datetime' => ['modifiers' => [['-1 week'], ['-1 week']]]
			],
			[
				'now-1h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1h'
				],
				'datetime' => ['modifiers' => [['-1 hour'], ['-1 hour']]]
			],
			[
				'now-1m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1m'
				],
				'datetime' => ['modifiers' => [['-1 minute'], ['-1 minute']]]
			],
			[
				'now-1s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1s'
				],
				'datetime' => ['modifiers' => [['-1 second'], ['-1 second']]]
			],
			[
				'now-1x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now-1'
				],
				'datetime' => ['modifiers' => [['-1 second'], ['-1 second']]]
			],
			[
				'now/M-1y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1y'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 year'], ['last day of this month 23:59:59', '-1 year']]]
			],
			[
				'now/M-1M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1M'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 month'], ['last day of this month 23:59:59', '-1 month']]]
			],
			[
				'now/M-1w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 week'], ['last day of this month 23:59:59', '-1 week']]]
			],
			[
				'now/M-1h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1h'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 hour'], ['last day of this month 23:59:59', '-1 hour']]]
			],
			[
				'now/M-1m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1m'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 minute'], ['last day of this month 23:59:59', '-1 minute']]]
			],
			[
				'now/M-1s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1s'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 second'], ['last day of this month 23:59:59', '-1 second']]]

			],
			[
				'now/M-1x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 second'], ['last day of this month 23:59:59', '-1 second']]]
			],
			[
				'now/M-1y/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1y/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 year', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 year', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1M/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1M/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 month', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 month', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1w/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1w/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 week', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 week', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1h/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1h/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 hour', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 hour', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1m/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1m/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 minute', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 minute', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1s/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1s/w'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 second', 'Monday this week 00:00:00'], ['last day of this month 23:59:59', '-1 second', 'Sunday this week 23:59:59']]]
			],
			[
				'now/M-1s/x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'time_type' => CRangeTimeParser::ZBX_TIME_RELATIVE,
					'match' => 'now/M-1s'
				],
				'datetime' => ['modifiers' => [['first day of this month 00:00:00', '-1 second'], ['last day of this month 23:59:59', '-1 second']]]
			],
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'time_type' => CRangeTimeParser::ZBX_TIME_UNKNOWN,
					'match' => ''
				],
				'datetime' => []
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 * @param array  $datetime
	 */
	public function testParse($source, $pos, array $expected, array $datetime) {
		$parser = new CRangeTimeParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'time_type' => $parser->getTimeType(),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());

		if (array_key_exists('values', $datetime)) {
			$this->assertSame($parser->getDateTime(true)->format('Y-m-d H:i:s'), $datetime['values'][0]);
			$this->assertSame($parser->getDateTime(false)->format('Y-m-d H:i:s'), $datetime['values'][1]);
		}
		elseif (array_key_exists('modifiers', $datetime) || array_key_exists('formats', $datetime)) {
			foreach ([0, 1] as $index) {
				for ($attempts = 0; $attempts < 2; $attempts++) {
					$ts = new DateTime();

					if (array_key_exists('modifiers', $datetime)) {
						foreach ($datetime['modifiers'][$index] as $modifier) {
							$ts->modify($modifier);
						}
					}

					if (array_key_exists('formats', $datetime)) {
						$ts = new DateTime($ts->format($datetime['formats'][$index]));
					}

					$expected_result = $ts->format('Y-m-d H:i:s');

					if ($expected_result === $parser->getDateTime($index == 0)->format('Y-m-d H:i:s')) {
						break;
					}
				}
				$this->assertSame($expected_result, $parser->getDateTime($index == 0)->format('Y-m-d H:i:s'));
			}
		}
		else {
			$this->assertSame($parser->getDateTime(true), null);
			$this->assertSame($parser->getDateTime(false), null);
		}
	}
}

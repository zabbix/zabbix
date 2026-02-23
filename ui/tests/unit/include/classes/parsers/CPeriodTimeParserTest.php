<?php declare(strict_types = 0);
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
 * Class containing tests for CIPParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class PeriodTimeParserTest extends TestCase {

	public static function dataProvider() {
		return [
			// Hours with and without leading zero.
			[CParser::PARSE_SUCCESS, '00:00-24:00'],
			[CParser::PARSE_SUCCESS, '01:00-23:00'],
			[CParser::PARSE_SUCCESS, '1:00-23:59'],
			[CParser::PARSE_SUCCESS, '1:00-3:00'],

			// Outside 24h range.
			[CParser::PARSE_FAIL, '24:00-23:0'],
			[CParser::PARSE_FAIL, '24:01-23:00'],
			[CParser::PARSE_FAIL, '01:00-24:01'],
			[CParser::PARSE_FAIL, '1:00-23:60'],
			[CParser::PARSE_FAIL, '1:00-3:0'],
			[CParser::PARSE_FAIL, '-3:0'],
			[CParser::PARSE_FAIL, ' '],
			[CParser::PARSE_FAIL, ''],
			[CParser::PARSE_FAIL, '1'],
			[CParser::PARSE_FAIL, '11'],
			[CParser::PARSE_FAIL, '11:'],

			// Range separator has optional spaces.
			[CParser::PARSE_SUCCESS, '00:00  - 23:00'],
			[CParser::PARSE_SUCCESS, '01:00 -  23:00'],
			[CParser::PARSE_SUCCESS, '1:00 -23:59'],
			[CParser::PARSE_SUCCESS, '1:00- 3:00'],

			// Parser in composition.
			[CParser::PARSE_SUCCESS, '__00:00-23:00', 2],
			[CParser::PARSE_SUCCESS, '__01:00-23:00', 2],
			[CParser::PARSE_SUCCESS, '__1:00-23:59', 2],
			[CParser::PARSE_SUCCESS, '__1:00-3:00', 2],

			[CParser::PARSE_SUCCESS_CONT, '___00:00-23:00__', 3],
			[CParser::PARSE_SUCCESS_CONT, '___01:00-23:00__', 3],
			[CParser::PARSE_SUCCESS_CONT, '___1:00-23:59__', 3],
			[CParser::PARSE_SUCCESS_CONT, '___1:00-3:00__', 3],

			// Parser for multibyte characters
			[CParser::PARSE_FAIL, 'āā:āā-āā:āā']
		];
	}

	/**
	 * @dataProvider dataProvider
	*/
	public function testParse(int $expect_result, string $source, int $pos = 0) {
		$parser = new CTimeRangeParser();

		$result = $parser->parse($source, $pos);

		$this->assertSame($expect_result, $result, $parser->getError());
	}
}

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


use PHPUnit\Framework\TestCase;

class CPeriodParserTest extends TestCase {

	/**
	 * An array of relative times and parsed results.
	 */
	public static function dataProvider() {
		return [
			['', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['', 0, ['lldmacros' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			[':', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['#', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['{#M}', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['{#M}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['{#M}', 0, ['lldmacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{#M}',
				'sec_num' => '{#M}',
				'time_shift' => ''
			]],
			['{{#M}.regsub("([a-z]+)", "\1")}', 0, ['lldmacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{{#M}.regsub("([a-z]+)", "\1")}',
				'sec_num' => '{{#M}.regsub("([a-z]+)", "\1")}',
				'time_shift' => ''
			]],
			['{$M}', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['{$M}', 0, ['lldmacros' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]],
			['{{$M}.regsub("([a-z]+)", \1)}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{{$M}.regsub("([a-z]+)", \1)}',
				'sec_num' => '{{$M}.regsub("([a-z]+)", \1)}',
				'time_shift' => ''
			]],
			['{$M}:', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$M}',
				'sec_num' => '{$M}',
				'time_shift' => ''
			]],
			['{$M}:{$M: context}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$M}:{$M: context}',
				'sec_num' => '{$M}',
				'time_shift' => '{$M: context}'
			]],
			['{{$M}.regsub("([a-z]+)", \1)}:{{$M: context}.regsub("([a-z]+)", \1)}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{{$M}.regsub("([a-z]+)", \1)}:{{$M: context}.regsub("([a-z]+)", \1)}',
				'sec_num' => '{{$M}.regsub("([a-z]+)", \1)}',
				'time_shift' => '{{$M: context}.regsub("([a-z]+)", \1)}'
			]],
			['{$M}:{#M}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{$M}',
				'sec_num' => '{$M}',
				'time_shift' => ''
			]],
			['{$M}:{#M}', 0, ['usermacros' => true, 'lldmacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{$M}:{#M}',
				'sec_num' => '{$M}',
				'time_shift' => '{#M}'
			]],
			['#1', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1',
				'sec_num' => '#1',
				'time_shift' => ''
			]],
			['1s', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1s',
				'sec_num' => '1s',
				'time_shift' => ''
			]],
			['1m', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1m',
				'sec_num' => '1m',
				'time_shift' => ''
			]],
			['1h', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1h',
				'sec_num' => '1h',
				'time_shift' => ''
			]],
			['1d', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1d',
				'sec_num' => '1d',
				'time_shift' => ''
			]],
			['1w', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1w',
				'sec_num' => '1w',
				'time_shift' => ''
			]],
			['1M', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1M',
				'sec_num' => '1M',
				'time_shift' => ''
			]],
			['1y', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1y',
				'sec_num' => '1y',
				'time_shift' => ''
			]],
			['#1:now', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1:now',
				'sec_num' => '#1',
				'time_shift' => 'now'
			]],
			['#1:now/y-{$TWO_WEEKS}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1:now/y-{$TWO_WEEKS}',
				'sec_num' => '#1',
				'time_shift' => 'now/y-{$TWO_WEEKS}'
			]],
			['#1:now/{$OFFSET}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1:now/{$OFFSET}',
				'sec_num' => '#1',
				'time_shift' => 'now/{$OFFSET}'
			]],
			['#1:now/{$OFFSET}-{$TWO_WEEKS}', 0, ['usermacros' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1:now/{$OFFSET}-{$TWO_WEEKS}',
				'sec_num' => '#1',
				'time_shift' => 'now/{$OFFSET}-{$TWO_WEEKS}'
			]],
			['#1:now/y-1M', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '#1:now/y-1M',
				'sec_num' => '#1',
				'time_shift' => 'now/y-1M'
			]],
			['#1:now/y-1My', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '#1:now/y-1M',
				'sec_num' => '#1',
				'time_shift' => 'now/y-1M'
			]],
			['1', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '1',
				'sec_num' => '1',
				'time_shift' => ''
			]],
			['#1abc', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '#1',
				'sec_num' => '#1',
				'time_shift' => ''
			]],
			[':now/y', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'sec_num' => '',
				'time_shift' => ''
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse(string $source, int $pos, array $options, array $expected) {
		$period_parser = new CPeriodParser($options);

		$this->assertSame($expected, [
			'rc' => $period_parser->parse($source, $pos),
			'match' => $period_parser->getMatch(),
			'sec_num' => $period_parser->getSecNum(),
			'time_shift' => $period_parser->getTimeshift()
		]);
		$this->assertSame(strlen($expected['match']), strlen($period_parser->getMatch()));
	}
}

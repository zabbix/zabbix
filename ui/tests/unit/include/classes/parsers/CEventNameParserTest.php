<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CEventNameParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CEventNameParser
	 */
	protected $parser;

	public function setUp() {
		$this->parser = new CEventNameParser();
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $expected) {
		$this->assertSame($expected, [
			'rc' => $this->parser->parse($source, $pos),
			'match' => $this->parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $this->parser->getLength());
	}

	/**
	 * An array of test data for event name parser.
	 */
	public static function testProvider() {
		return [
			[
				'Simple expression macro {?100+1-(2)}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Simple expression macro {?100+1-(2)}'
				]
			],
			[
				'Expression macro with modificator {{?100+1-(2)}.fmtnum(2)}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Expression macro with modificator {{?100+1-(2)}.fmtnum(2)}'
				]
			],
			[
				'Simple macro {HOST.HOST} support', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Simple macro {HOST.HOST} support'
				]
			],
			[
				'Timestamp {TIME} macro', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Timestamp {TIME} macro'
				]
			],
			[
				'Timestamp {{TIME}.func()} macro with modificator', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Timestamp {{TIME}.func()} macro with modificator'
				]
			],
			[
				'Macro as host name {?{{HOST.HOST}:item.func(1)}}', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Macro as host name {?{{HOST.HOST}:item.func(1)}}'
				]
			],
			[
				'Load of {HOST.HOST} server increased on {{?100*{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M)}/{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M-1M)}}.fmtnum(0)}% in {{TIME}.fmttime(%B,-1M)} ({{?{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M)}}.fmtnum(2)}) comparing to {{TIME}.fmttime(%B,-2M)} ({{?{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M-1M)}}.fmtnum(2)})', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'Load of {HOST.HOST} server increased on {{?100*{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M)}/{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M-1M)}}.fmtnum(0)}% in {{TIME}.fmttime(%B,-1M)} ({{?{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M)}}.fmtnum(2)}) comparing to {{TIME}.fmttime(%B,-2M)} ({{?{{HOST.HOST}:system.cpu.load.trendavg(1M,now/M-1M)}}.fmtnum(2)})'
				]
			],
			[
				'Empty expression macro {?}', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'Not closed expression macro {?', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'Not closed expression macro with constant {?100', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'Not closed expression macro with constant and operation{?100*', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'Unsupported macro in host name of function {?{{WRONG.MACRO}:item.func()}}', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
		];
	}
}

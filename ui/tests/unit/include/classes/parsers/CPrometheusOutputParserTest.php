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


use PHPUnit\Framework\TestCase;

class CPrometheusOutputParserTest extends TestCase {

	/**
	 * An array of Prometheus outputs and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'labelname123', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'labelname123'
				]
			],
			[
				'labelname123', 8, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'e123'
				]
			],
			[
				'{$M}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$M}'
				]
			],
			[
				'{$FS_MAX: "/home"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$FS_MAX: "/home"}'
				]
			],
			[
				'{#LLD}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#LLD}'
				]
			],
			// partial success
			[
				'label1=', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'label1'
				]
			],
			[
				'label1  ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'label1'
				]
			],
			[
				'label1  ', 4, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'l1'
				]
			],
			// fail
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'0', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'09label', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'label123', 5, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			// User macros not enabled.
			[
				'{$M}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			// LLD macros are not enabled.
			[
				'{#LLD}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
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
	public function testParse($source, $pos, $options, $expected) {
		$parser = new CPrometheusOutputParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}

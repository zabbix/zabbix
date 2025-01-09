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
				'{{$M}.regsub("([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M}.regsub("([0-9]+)", \1)}'
				]
			],
			[
				'{{$M: "context"}.regsub("([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M: "context"}.regsub("([0-9]+)", \1)}'
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
			[
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}'
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
			[
				'{$MACRO} label1  ', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$MACRO}'
				]
			],
			[
				'{#LLD_MACRO} label1  ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#LLD_MACRO}'
				]
			],
			[
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)} label1  ', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}'
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
			],
			[
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}', 0, [],
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

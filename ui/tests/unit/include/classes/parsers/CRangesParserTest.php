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

class CRangesParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'200,100', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '200,100',
					'ranges' => [['200'],['100']]
				]
			],
			[
				'500-600,200', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '500-600,200',
					'ranges' => [
						['500', '600'],
						['200']
					]
				]
			],
			[
				'500-600,200-200', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '500-600,200-200',
					'ranges' => [
						['500', '600'],
						['200', '200']
					]
				]
			],
			[
				'500,600-700', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '500,600-700',
					'ranges' => [
						['500'],
						['600', '700']
					]
				]
			],
			[
				'150,250-350,400,450-600', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '150,250-350,400,450-600',
					'ranges' => [
						['150'],
						['250', '350'],
						['400'],
						['450', '600']
					]
				]
			],
			[
				'100,{$M},{$M.A}-{$M.B},200,{$M.C}-300', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100,{$M},{$M.A}-{$M.B},200,{$M.C}-300',
					'ranges' => [
						['100'],
						['{$M}'],
						['{$M.A}', '{$M.B}'],
						['200'],
						['{$M.C}', '300']
					]
				]
			],
			[
				'100,{#M.A}-{#M.B},200,300-{#M.C},400-{#M.D}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100,{#M.A}-{#M.B},200,300-{#M.C},400-{#M.D}',
					'ranges' => [
						['100'],
						['{#M.A}', '{#M.B}'],
						['200'],
						['300', '{#M.C}'],
						['400','{#M.D}']
					]
				]
			],
			[
				'100-200,{#M}-{$M},300-{$M},{#M}-400,{#M}-{#N},{#Z}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-200,{#M}-{$M},300-{$M},{#M}-400,{#M}-{#N},{#Z}',
					'ranges' => [
						['100', '200'],
						['{#M}', '{$M}'],
						['300', '{$M}'],
						['{#M}', '400'],
						['{#M}', '{#N}'],
						['{#Z}']
					]
				]
			],
			[
				'100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")},{$M},{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-200,300', 0,
				['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '100-{{#M}.regsub("^([0-9]+)", "{#M}: \1")},{$M},'.
						'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-200,300',
					'ranges' => [
						['100', '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'],
						['{$M}'],
						['{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', '200'],
						['300']
					]
				]
			],
			// partial success
			[
				'random text.....100-200,300....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100-200,300',
					'ranges' => [
						['100', '200'],
						['300']
					]
				]
			],
			[
				'100,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100',
					'ranges' => [['100']]
				]
			],
			[
				'200,-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '200',
					'ranges' => [['200']]
				]
			],
			[
				'100,200,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100,200',
					'ranges' => [['100'], ['200']]
				]
			],
			[
				'300-400,500,600-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300-400,500,600',
					'ranges' => [
						['300', '400'],
						['500'],
						['600']
					]
				]
			],
			[
				'300-400,500,600-599', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300-400,500,600',
					'ranges' => [
						['300', '400'],
						['500'],
						['600']
					]
				]
			],
			[
				'100,200 ,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100,200 ',
					'ranges' => [['100'], ['200']]
				]
			],
			[
				'300,400, ', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '300,400',
					'ranges' => [['300'], ['400']]
				]
			],
			[
				'500,600,a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '500,600',
					'ranges' => [['500'], ['600']]
				]
			],
			[
				'700,800,,', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '700,800',
					'ranges' => [['700'], ['800']]
				]
			],
			[
				'150,,250', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '150',
					'ranges' => [['150']]
				]
			],
			[
				'{$M},100,{$M}-100,200-{$}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$M},100,{$M}-100,200',
					'ranges' => [
						['{$M}'],
						['100'],
						['{$M}', '100'],
						['200']
					]
				]
			],
			[
				'{$A}-{#B},100-{#C},{$D}-{#E},{#}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$A}-{#B},100-{#C},{$D}-{#E}',
					'ranges' => [
						['{$A}', '{#B}'],
						['100', '{#C}'],
						['{$D}', '{#E}']
					]
				]
			],
			[
				'100-{{#A}.regsub("^([0-9]+)", "{#A}: \1")},{#B},{$C}-{#D},200-{$}', 0,
				['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '100-{{#A}.regsub("^([0-9]+)", "{#A}: \1")},{#B},{$C}-{#D},200',
					'ranges' => [
						['100', '{{#A}.regsub("^([0-9]+)", "{#A}: \1")}'],
						['{#B}'],
						['{$C}', '{#D}'],
						['200']
					]
				]
			],
			// fail
			[
				'100', 3, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				',100', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				',', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			[
				'{$}-100,200-{#A}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			// User macros are not enabled.
			[
				'{$M}-100,200,300-400', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
				]
			],
			// LLD macros are not enabled.
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}-100,200,300', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'ranges' => []
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
		$parser = new CRangesParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch(),
			'ranges' => $parser->getRanges()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}

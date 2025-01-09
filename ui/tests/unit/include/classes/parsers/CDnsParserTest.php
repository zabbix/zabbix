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

class CDnsParserTest extends TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'dns.name', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'dns.name'
				]
			],
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'www.zabbix.com-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com-'
				]
			],
			[
				'.a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'-a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'_a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a'
				]
			],
			[
				'com.', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'com.'
				]
			],
			[
				'com..', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'com.'
				]
			],
			[
				'a.root-servers.net', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a.root-servers.net'
				]
			],
			[
				'x--ample.example.net', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'x--ample.example.net'
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_'
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz/.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abcdefghijklmnopqrstuvwxyz'
				]
			],
			[
				'127.0.0.1;www.zabbix.com.', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com.'
				]
			],
			[
				'127.0.0.1;www..zabbix.com', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'www.'
				]
			],
			[
				'127.0.0.1;www.zabbix.com', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com'
				]
			],
			[
				'{$MACRO1}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO1}'
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M}.regsub("^([0-9]+)", \1)}'
				]
			],
			[
				'{{$M: "context"}.regsub("^([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M: "context"}.regsub("^([0-9]+)", \1)}'
				]
			],
			[
				'&&&&zabbix.com{$MACRO2}', 4, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{$MACRO2}'
				]
			],
			[
				'zabbix.com{$MACRO3}test%%%', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com{$MACRO3}test'
				]
			],
			[
				'{#MACRO4}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO4}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
				]
			],
			[
				'&&&&zabbix.com{#MACRO5}', 4, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{#MACRO5}'
				]
			],
			[
				'zabbix.com{#MACRO6}test%%%', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com'
				]
			],
			[
				'zabbix.com{#MACRO7}test%%%', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com{#MACRO7}test'
				]
			],
			[
				'z{$A}{#B}{#B}i{$X}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'z{$A}{#B}{#B}i{$X}'
				]
			],
			[
				'z{$A}{#B}{#B}i{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'z{$A}'
				]
			],
			[
				'z{$A}%%i{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'z{$A}'
				]
			],
			[
				'%%%z{$A}bbi{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}'
				]
			],
			[
				'{{HOST.HOST}.func()}', 0, ['macros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{HOST.HOST}.func()}'
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => []],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'zabbix.com{HOST.HOST}', 0, ['macros' => []],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com'
				]
			],
			[
				'zabbix.com{HOST.HOST}', 0, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{HOST.HOST}'
				]
			],
			[
				'zabbix.com{{HOST.HOST}.regsub("(\d+)", \1)}', 0, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{{HOST.HOST}.regsub("(\d+)", \1)}'
				]
			],
			[
				'zabbix.com{HOST.HOST}dns{HOST.DNS}', 0, ['macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{HOST.HOST}dns{HOST.DNS}'
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste'
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste%%%%', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste'
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
		$dns_parser = new CDnsParser($options);

		$this->assertSame($expected, [
			'rc' => $dns_parser->parse($source, $pos),
			'match' => $dns_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $dns_parser->getLength());
	}
}

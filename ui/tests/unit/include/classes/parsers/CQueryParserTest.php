<?php declare(strict_types = 0);
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

class CQueryParserTest extends TestCase {

	public function dataProvider() {
		return [
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/h/i', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/h/i',
				'host' => 'h',
				'item' => 'i',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /h/i text', 5, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/h/i',
				'host' => 'h',
				'item' => 'i',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST1}/item[pam, "param"] text', 5, ['host_macro' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, ['host_macro' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST}/item[pam, "param"]',
				'host' => '{HOST.HOST}',
				'item' => 'item[pam, "param"]',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST}/item text', 5, ['host_macro_n' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST}/item',
				'host' => '{HOST.HOST}',
				'item' => 'item',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST1}/item text', 5, ['host_macro_n' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST1}/item',
				'host' => '{HOST.HOST1}',
				'item' => 'item',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['text /{HOST.HOST7}/item text', 5, ['host_macro_n' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST7}/item',
				'host' => '{HOST.HOST7}',
				'item' => 'item',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log,ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt',
				'host' => 'Zabbix server',
				'item' => 'logrt',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server^/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/*', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/*', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/*',
				'host' => 'Zabbix server',
				'item' => '*',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/*/key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/key', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/*/key', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/*/key',
				'host' => '*',
				'item' => 'key',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/*/*', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/*/*',
				'host' => '*',
				'item' => '*',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/key', 0, ['empty_host' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/'.'/key',
				'host' => '',
				'item' => 'key',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/*', 0, ['empty_host' => true], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => '',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/'.'/*', 0, ['calculated' => true, 'empty_host' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/'.'/*',
				'host' => '',
				'item' => '*',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = {$MACRO} and group = "group"]', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = {#MACRO} and group = "group"]', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '',
					'tokens' => []
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[{$MACRO} = {{#MACRO}.func()} and group = "group"]', 0, ['usermacros' => true, 'lldmacros' => true, 'calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[{$MACRO} = {{#MACRO}.func()} and group = "group"]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '?[{$MACRO} = {{#MACRO}.func()} and group = "group"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_USER_MACRO,
							'pos' => 74,
							'match' => '{$MACRO}',
							'length' => 8
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 83,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_LLD_MACRO,
							'pos' => 85,
							'match' => '{{#MACRO}.func()}',
							'length' => 17
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 103,
							'match' => 'and',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 107,
							'match' => 'group',
							'length' => 5
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 113,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 115,
							'match' => '"group"',
							'length' => 7
						]
					]
				]
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'filter' => [
					'match' => '?[tag = "tag" and group = "group"]',
					'tokens' => [
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 74,
							'match' => 'tag',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 78,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 80,
							'match' => '"tag"',
							'length' => 5
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 86,
							'match' => 'and',
							'length' => 3
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_KEYWORD,
							'pos' => 90,
							'match' => 'group',
							'length' => 5
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_OPERATOR,
							'pos' => 96,
							'match' => '=',
							'length' => 1
						],
						[
							'type' => CFilterParser::TOKEN_TYPE_STRING,
							'pos' => 98,
							'match' => '"group"',
							'length' => 7
						]
					]
				]
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string  $source
	 * @param int     $source
	 * @param array   $options
	 * @param array   $expected
	 */
	public function testQueryParse(string $source, int $pos, array $options, array $expected) {
		$query_parser = new CQueryParser($options);

		$this->assertSame($expected, [
			'rc' => $query_parser->parse($source, $pos),
			'match' => $query_parser->getMatch(),
			'host' => $query_parser->getHost(),
			'item' => $query_parser->getItem(),
			'filter' => $query_parser->getFilter()
		]);
		$this->assertSame(strlen($expected['match']), strlen($query_parser->getMatch()));
	}
}

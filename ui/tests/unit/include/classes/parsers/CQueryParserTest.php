<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

class CQueryParserTest extends TestCase {

	public function dataProvider() {
		return [
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]'
			]],
			['/h/i', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/h/i',
				'host' => 'h',
				'item' => 'i'
			]],
			['text /h/i text', 5, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/h/i',
				'host' => 'h',
				'item' => 'i'
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, ['host_macro' => true], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST}/item[pam, "param"]',
				'host' => '{HOST.HOST}',
				'item' => 'item[pam, "param"]'
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log,ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt',
				'host' => 'Zabbix server',
				'item' => 'logrt'
			]],
			['/Zabbix server^/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server/', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['//logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server/*', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server/*', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/*',
				'host' => 'Zabbix server',
				'item' => '*'
			]],
			['/*/key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'host' => '',
				'item' => ''
			]],
			['/*/key', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/*/key',
				'host' => '*',
				'item' => 'key'
			]],
			['/*/*', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/*/*',
				'host' => '*',
				'item' => '*'
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]'
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]', 0, ['calculated' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]?[tag = "tag" and group = "group"]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]'
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
			'item' => $query_parser->getItem()
		]);
		$this->assertSame(strlen($expected['match']), strlen($query_parser->getMatch()));
	}
}

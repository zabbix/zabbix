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
				'length' => 72,
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]'
			]],
			['/h/i', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/h/i',
				'length' => 4,
				'host' => 'h',
				'item' => 'i'
			]],
			['text /h/i text', 5, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/h/i',
				'length' => 4,
				'host' => 'h',
				'item' => 'i'
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'host' => '',
				'item' => ''
			]],
			['text /{HOST.HOST}/item[pam, "param"] text', 5, ['host_macro' => ['{HOST.HOST}']], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/{HOST.HOST}/item[pam, "param"]',
				'length' => 31,
				'host' => '{HOST.HOST}',
				'item' => 'item[pam, "param"]'
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log,ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt',
				'length' => 20,
				'host' => 'Zabbix server',
				'item' => 'logrt'
			]],
			['/Zabbix server^/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'host' => '',
				'item' => ''
			]],
			['/Zabbix server/', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'host' => '',
				'item' => ''
			]],
			['//logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'host' => '',
				'item' => ''
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
			'match' => $query_parser->result->match,
			'length' => strlen($query_parser->result->match),
			'host' => $query_parser->result->host,
			'item' => $query_parser->result->item
		]);
	}
}

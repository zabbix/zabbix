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

	protected function setUp(): void {
		$this->query_parser = new CQueryParser();
	}

	public function dataProvider() {
		return [
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/Zabbix server/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]',
				'host' => 'Zabbix server',
				'item' => 'logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]'
			]],
			['/h/i', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '/h/i',
				'host' => 'h',
				'item' => 'i'
			]],
			['/Zabbix server/logrt["/home/zabbix32/test[0-9].log,ERROR,,1000,,,120.0]', 0, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '/Zabbix server/logrt',
				'host' => 'Zabbix server',
				'item' => 'logrt'
			]],
			['/Zabbix server^/logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [
				'rc' => CParser::PARSE_FAIL
			]],
			['/Zabbix server', 0, [
				'rc' => CParser::PARSE_FAIL
			]],
			['/Zabbix server/', 0, [
				'rc' => CParser::PARSE_FAIL
			]],
			['//logrt["/home/zabbix32/test[0-9].log",ERROR,,1000,,,120.0]', 0, [
				'rc' => CParser::PARSE_FAIL
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string  $source
	 * @param int     $source
	 * @param array   $expected
	 */
	public function testQueryParse(string $source, int $pos, array $expected) {
		$this->query_parser->parse($source, $pos);

		if ($expected['rc'] == CParser::PARSE_FAIL) {
			$this->assertSame($expected, ['rc' => $this->query_parser->parse($source, $pos)]);
		}
		else {
			$this->assertSame($expected, [
				'rc' => $this->query_parser->parse($source, $pos),
				'match' => $this->query_parser->result->match,
				'host' => $this->query_parser->result->host,
				'item' => $this->query_parser->result->item
			]);
		}
	}
}

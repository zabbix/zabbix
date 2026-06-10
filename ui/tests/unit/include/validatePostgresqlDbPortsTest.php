<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class validatePostgresqlDbPortsTest extends TestCase {

	public function dataProvider() {
		return [
			// Valid test cases.
			'single valid port' => [
				'localhost:5432',
				null
			],
			'multiple valid ports' => [
				'host1:5432, host2:5433, 0:0, :8000',
				null
			],
			'multiple valid empty ports' => [
				'localhost:, localhost',
				null
			],
			'multiple hosts with some empty ports' => [
				'192.168.1.1:, 127.0.0.1:5432, localhost, ::1, [::1], [::1]:, [::1]:5433',
				null
			],
			'leading zeros in port' => [
				'localhost:000123',
				null
			],
			'spaces before port' => [
				'localhost:   5432',
				null
			],
			'malformed IPv6 treated as hosts with no ports' => [
				'::1], [::1, ::1]:8080, [::1:8080, ::1:8080',
				null
			],

			// Invalid test cases.
			'negative port' => [
				'localhost:-1',
				'-1'
			],
			'valid ipv6 with invalid negative port' => [
				'[::1]:-1',
				'-1'
			],
			'port too large' => [
				'localhost:99999',
				'99999'
			],
			'valid empty host but invalid port (too large)' => [
				':99999',
				'99999'
			],
			'non-numeric port' => [
				'localhost:abc',
				'abc'
			],
			'non-numeric port (user macros not allowed)' => [
				'localhost:{$PORT}',
				'{$PORT}'
			],
			'non-numeric port (LLD macros not allowed)' => [
				'localhost:{#PORT}',
				'{#PORT}'
			],
			'port with spaces inside' => [
				'localhost:1 2 3',
				'1 2 3'
			],
			'invalid port in first endpoint' => [
				'host1:-5,host2:5432',
				'-5'
			],
			'invalid port in second endpoint' => [
				'host1:5432,host2:-5',
				'-5'
			],
			'weird string still parsed with invalid port' => [
				'ēxā m ple:     -10',
				'-10'
			],
			'multiple invalid but returns first' => [
				'host1:-1, host2:99999',
				'-1'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string      $input
	 * @param string|null $expected
	 */
	public function testValidatePostgresqlDbPorts(string $input, string|null $expected): void {
		$result = validatePostgresqlDbPorts($input);

		$this->assertSame($expected, $result);
	}
}

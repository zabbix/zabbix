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

class PostgresqlDbBackendTest extends TestCase {

	/**
	 * Data provider for parseEndpoints tests. Each case includes a source string and the expected parsed
	 * array of endpoints (host and port).
	 *
	 * @return array
	 */
	public function parseDataprovider(): array {
		return [
			[
				'example.com',
				[
					['host' => 'example.com', 'port' => '']
				]
			],
			// Spaces after host and before port are allowed and preserved, but can fail during connection.
			[
				'example.com    :   5432',
				[
					['host' => 'example.com    ', 'port' => '   5432']
				]
			],
			// Spaces before and after comma (and before and after host) are allowed and are trimmed.
			[
				'   example.com:8000    ,    192.168.1.1   ',
				[
					['host' => 'example.com', 'port' => '8000'],
					['host' => '192.168.1.1', 'port' => '']
				]
			],
			// Bracketed IPv6 with port, spaces before and after host and port are preserved.
			[
				'[2001:db8::1]  :  443',
				[
					['host' => '2001:db8::1', 'port' => '  443']
				]
			],
			// Empty tokens, empty hosts, unbracketed IPv6, bracketed IPv6 with port etc.
			[
				',,   localhost:, 192.168.1.1:5433,     ,  ,::1, [::1]:8080, 0:0, :7, :007,  :   00013',
				[
					['host' => '', 'port' => ''],
					['host' => '', 'port' => ''],
					['host' => 'localhost', 'port'=>''],
					['host' => '192.168.1.1', 'port' => '5433'],
					['host' => '', 'port' => ''],
					['host' => '', 'port' => ''],
					['host' => '::1', 'port' => ''],
					['host' => '::1', 'port' => '8080'],
					['host' => '0', 'port' => '0'],
					['host' => '', 'port' => '7'],
					['host' => '', 'port' => '007'],
					['host' => '', 'port' => '   00013']
				]
			],
			// Invalid ports and broken IPv6 are not rejected by the parser. Malformed IPv6 are treated as host.
			[
				'::1], [::1, [::1:80, ::1]:80, 127.0.0.1:99999, host:port, host:-1, ēxā m ple: 1 2 3',
				[
					['host' => '::1]', 'port' => ''],
					['host' => '[::1', 'port' => ''],
					['host' => '[::1:80', 'port' => ''],
					['host' => '::1]:80', 'port' => ''],
					['host' => '127.0.0.1', 'port' => '99999'],
					['host' => 'host', 'port' => 'port'],
					['host' => 'host', 'port' => '-1'],
					['host' => 'ēxā m ple', 'port' => ' 1 2 3']
				]
			]
		];
	}

	/**
	 * @dataProvider parseDataprovider
	 *
	 * @param string $input
	 * @param array  $expected
	 */
	public function testParseEndpoints(string $input, array $expected): void {
		$result = PostgresqlDbBackend::parseEndpoints($input);

		$this->assertEquals($expected, $result);
	}

	/**
	 * Data provider for connect() tests. Each case includes a set of arguments to connect() and the expected
	 * host and port values that should be included in the connection string, as well as whether extras are expected.
	 *
	 * @return array
	 */
	public function connectDataProvider(): array {
		return [
			'successful single endpoint with port (no extras)' => [
				[
					'example.com:80',
					'5432',
					'user',
					'pass',
					'db',
					null
				],
				[
					'host' => "host='example.com'",
					'port' => "port='80'",
					'extras' => false,
					'doPgConnect' => 'fake-resource',
					'setError' => false
				]
			],
			'successful single endpoint without port (default used, no extras)' => [
				[
					'example.com',
					'5432',
					'user',
					'pass',
					'db',
					null
				],
				[
					'host' => "host='example.com'",
					'port' => "port='5432'",
					'extras' => false,
					'doPgConnect' => 'fake-resource',
					'setError' => false
				]
			],
			'successful multiple endpoints (default substitution + extras)' => [
				[
					'[::1],example.com:80',
					'5432',
					'user',
					'pass',
					'db',
					null
				],
				[
					'host' => "host='::1,example.com'",
					'port' => "port='5432,80'",
					'extras' => true,
					'doPgConnect' => 'fake-resource',
					'setError' => false
				]
			],
			'connection failure (single endpoint)' => [
				[
					'example.com:80',
					'5432',
					'user',
					'pass',
					'db',
					null
				],
				[
					'host' => "host='example.com'",
					'port' => "port='80'",
					'extras' => false,
					'doPgConnect' => false,
					'setError' => true
				]
			],
			'connection failure (multiple endpoints)' => [
				[
					'[::1],example.com:80',
					'5432',
					'user',
					'pass',
					'db',
					null
				],
				[
					'host' => "host='::1,example.com'",
					'port' => "port='5432,80'",
					'extras' => true,
					'doPgConnect' => false,
					'setError' => true
				]
			]
		];
	}

	/**
	 * @dataProvider connectDataProvider
	 *
	 * @param array $input
	 * @param array $expected
	 */
	public function testConnect(array $input, array $expected): void {
		$mock = $this
			->getMockBuilder(PostgresqlDbBackend::class)
			->onlyMethods(['doPgConnect', 'setError'])
			->getMock();

		$self = $this;

		/*
		 * Expectation for doPgConnect: inspect the connection string passed to it, then return the configured
		 * fake result (success resource or false).
		 */
		$mock
			->expects($this->once())
			->method('doPgConnect')
			->with($this->callback(function ($conn_string) use ($self, $expected) {
				// Assert host and port substrings.
				$self->assertStringContainsString($expected['host'], $conn_string, 'host mismatch');
				$self->assertStringContainsString($expected['port'], $conn_string, 'port mismatch');

				// Assert extras presence/absence.
				if ($expected['extras']) {
					$self->assertStringContainsString('target_session_attrs=read-write', $conn_string);
					$self->assertStringContainsString('connect_timeout=3', $conn_string);
				}
				else {
					$self->assertStringNotContainsString('target_session_attrs=read-write', $conn_string);
					$self->assertStringNotContainsString('connect_timeout=3', $conn_string);
				}

				return true;
			}))
			->willReturn($expected['doPgConnect']);

		// setError: expect called or not depending on the test row.
		if ($expected['setError']) {
			$mock
				->expects($this->once())
				->method('setError')
				->with($this->equalTo('Error connecting to database.'));
		}
		else {
			$mock
				->expects($this->never())
				->method('setError');
		}

		// Call connect() with unpacked arguments.
		$result = $mock->connect(...$input);

		// If doPgConnect() returned a resource-like value, connect() should return it, otherwise it should return null.
		if ($expected['doPgConnect']) {
			$this->assertSame($expected['doPgConnect'], $result);
		}
		else {
			$this->assertNull($result);
		}
	}
}

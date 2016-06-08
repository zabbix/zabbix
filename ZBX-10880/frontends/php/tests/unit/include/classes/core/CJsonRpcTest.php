<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CJsonRpcTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CLocalApiClient
	 */
	protected static $client;

	/**
	 * @var CJson
	 */
	protected static $json;

	public static function setUpBeforeClass() {
		self::$client = new CLocalApiClient();
		self::$json = new CJson();
	}

	public function invalidRequestProvider() {
		return [
			['[]'],
			[''],
			['"bar"']
		];
	}

	public function invalidRequestsBatchProvider() {
		return [
			['[1]'],
			['["bar"]'],
			['["bar", 1, null]']
		];
	}

	/**
	 * Test execute() with invalid request.
	 *
	 * @dataProvider invalidRequestProvider
	 *
	 * @param string $data
	 */
	public function testExecuteWithInvalidRequest($data) {
		$response = self::$json->decode((new CJsonRpc(self::$client, $data))->execute(), true);

		$this->assertArrayHasKey('error', $response);
		$this->assertArrayHasKey('code', $response['error']);
		$this->assertEquals(-32600, $response['error']['code']);
	}

	/**
	 * Test execute() with batch of invalid requests.
	 *
	 * @dataProvider invalidRequestsBatchProvider
	 *
	 * @param string $data
	 */
	public function testExecuteWithInvalidRequestsBatch($data) {
		$response_array = self::$json->decode((new CJsonRpc(self::$client, $data))->execute(), true);

		$this->assertInternalType('array', $response_array);
		$this->assertNotEmpty($response_array);

		foreach ($response_array as $response) {
			$this->assertArrayHasKey('error', $response);
			$this->assertArrayHasKey('code', $response['error']);
			$this->assertEquals(-32600, $response['error']['code']);
		}
	}
}

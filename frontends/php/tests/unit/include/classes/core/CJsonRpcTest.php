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
	 * API client.
	 *
	 * @var CApiClient
	 */
	protected static $client;

	/**
	 * CJson object.
	 *
	 * @var CJson
	 */
	protected static $json;

	/**
	 * User authentication token.
	 *
	 * @var string
	 */
	protected static $auth;

	/**
	 * Set up static values before testing CJsonRpc.
	 */
	public static function setUpBeforeClass() {
		self::$json = new CJson();

		Z::getInstance()->run(ZBase::EXEC_MODE_API);
		self::$client = API::getWrapper()->getClient();

		self::$auth = self::$json->decode(
			(new CJsonRpc(
				self::$client,
				'{"jsonrpc": "2.0", "method": "user.login","params": {"user": "Admin", "password": "zabbix"}, "id": 0}'
			))->execute()
		)->result;
	}

	/**
	 * Logout user when all tests are finished.
	 */
	public static function tearDownAfterClass() {
		Z::getInstance()->run(ZBase::EXEC_MODE_API);

		(new CJsonRpc(
			self::$client,
			'{"jsonrpc": "2.0", "method": "user.logout", "params": [], "id": 0, "auth": "'.self::$auth.'"}'
		))->execute();
	}

	/**
	 * Provides valid requests for CJSonRpc()->execute() method.
	 */
	public function validRequestProvider() {
		return [
			['item.get', '{"itemids":[]}', 1],
			['host.get', '{"hostids":[]}', 1]
		];
	}

	/**
	 * Test CJsonRpc()->execute() with valid request.
	 *
	 * @dataProvider validRequestProvider
	 *
	 * @param string $method
	 * @param string $params
	 * @param string $id
	 */
	public function testValidRequest($method, $params, $id) {
		DBConnect();

		$data = '{"jsonrpc": "2.0", "method": "'.$method.'", "auth": "'.self::$auth.'", "params": '.$params.', "id": '.
			$id.'}';

		$response = self::$json->decode((new CJsonRpc(self::$client, $data))->execute(), true);

		$this->assertInternalType('array', $response);
		$this->assertArrayHasKey('result', $response);
	}

	/**
	 * Provides valid requests in batch for CJSonRpc()->execute() method.
	 */
	public function validRequestsBatchProvider() {
		return [
			[
				// First batch.
				[
					['method' => 'item.get', 'params' => '{"itemids":[]}', 'id' => 1],
					['method' => 'host.get', 'params' => '{"hostids":[]}', 'id' => 2]
				]
			]
		];
	}

	/**
	 * Test CJsonRpc()->execute() with batch of valid requests.
	 *
	 * @dataProvider validRequestsBatchProvider
	 *
	 * @param array $batch
	 */
	public function testValidRequestsBatch($batch) {
		DBConnect();

		$length = count($batch);
		$i = 1;

		$data = '[';
		foreach ($batch as $attrs) {
			$data .= '{"jsonrpc": "2.0", "method": "'.$attrs['method'].'", "auth": "'.self::$auth.'", "params": '.
				$attrs['params'].', "id": '.$attrs['id'].'}';

			if ($i < $length) {
				$data .= ', ';
			}

			$i++;
		}
		$data .= ']';

		$response_array = self::$json->decode((new CJsonRpc(self::$client, $data))->execute(), true);

		$this->assertInternalType('array', $response_array);
		$this->assertEquals($length, count($response_array));

		foreach ($response_array as $response) {
			$this->assertInternalType('array', $response);
			$this->assertArrayHasKey('result', $response);
		}
	}

	/**
	 * Provides invalid requests for CJSonRpc()->execute() method.
	 */
	public function invalidRequestProvider() {
		return [
			['5'],
			['0'],
			['[]'],
			['""'],
			['null'],
			['"bar"']
		];
	}
	/**
	 * Test CJsonRpc()->execute() with invalid request.
	 *
	 * @dataProvider invalidRequestProvider
	 *
	 * @param string $data
	 */
	public function testInvalidRequest($data) {
		$response = self::$json->decode((new CJsonRpc(self::$client, $data))->execute(), true);

		$this->assertArrayHasKey('error', $response);
		$this->assertArrayHasKey('code', $response['error']);
		$this->assertEquals(-32600, $response['error']['code']);
	}

	/**
	 * Provides invalid requests in batch for CJSonRpc()->execute() method.
	 */
	public function invalidRequestsBatchProvider() {
		return [
			['[1]'],
			['["bar"]'],
			['["bar", 1, null]']
		];
	}

	/**
	 * Test CJsonRpc()->execute() with batch of invalid requests.
	 *
	 * @dataProvider invalidRequestsBatchProvider
	 *
	 * @param string $data
	 */
	public function testInvalidRequestsBatch($data) {
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

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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';
require_once dirname(__FILE__).'/../../include/classes/json/CJson.php';

class CJsonRpcTest extends CZabbixTest {

	/**
	 * User authentication token.
	 *
	 * @var string
	 */
	protected $auth;

	/**
	 * Authenticate and set user authentication token.
	 */
	public function setUp() {
		parent::setUp();

		$data = [
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => ['user' => 'Admin', 'password' => 'zabbix'],
			'id' => '0'
		];

		$debug = null;

		$this->auth = (new CJson)->decode(
			$this->do_post_request($data, $debug),
			true
		)['result'];
	}

	/**
	 * Provides valid requests for JSON RPC.
	 */
	public function validRequestProvider() {
		return [
			['item.get', '{"itemids":[]}', 1],
			['host.get', '{"hostids":[]}', 1]
		];
	}

	/**
	 * Test JSON RPC with valid request.
	 *
	 * @dataProvider validRequestProvider
	 *
	 * @param string $method
	 * @param string $params
	 * @param string $id
	 */
	public function testValidRequest($method, $params, $id) {
		$data = '{"jsonrpc": "2.0", "method": "'.$method.'", "auth": "'.$this->auth.'", "params": '.$params.', "id": '.
			$id.'}';

		$debug = null;
		$response = $this->api_call_raw($data, $debug);

		$this->assertInternalType('array', $response);
		$this->assertArrayHasKey('result', $response);
	}

	/**
	 * Provides valid requests in batch for JSON RPC.
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
	 * Test JSON RPC with batch of valid requests.
	 *
	 * @dataProvider validRequestsBatchProvider
	 *
	 * @param array $batch
	 */
	public function testValidRequestsBatch($batch) {
		$length = count($batch);
		$i = 1;

		$data = '[';
		foreach ($batch as $attrs) {
			$data .= '{"jsonrpc": "2.0", "method": "'.$attrs['method'].'", "auth": "'.$this->auth.'", "params": '.
				$attrs['params'].', "id": '.$attrs['id'].'}';

			if ($i < $length) {
				$data .= ', ';
			}

			$i++;
		}
		$data .= ']';

		$debug = null;
		$response_array = $this->api_call_raw($data, $debug);

		$this->assertInternalType('array', $response_array);
		$this->assertEquals($length, count($response_array));

		foreach ($response_array as $response) {
			$this->assertInternalType('array', $response);
			$this->assertArrayHasKey('result', $response);
		}
	}

	/**
	 * Provides invalid requests for JSON RPC.
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
	 * Test JSON RPC with invalid request.
	 *
	 * @dataProvider invalidRequestProvider
	 *
	 * @param string $data
	 */
	public function testInvalidRequest($data) {
		$response = $this->api_call_raw($data, $debug);

		$this->assertArrayHasKey('error', $response);
		$this->assertArrayHasKey('code', $response['error']);
		$this->assertEquals(-32600, $response['error']['code']);
	}

	/**
	 * Provides invalid requests in batch for JSON RPC.
	 */
	public function invalidRequestsBatchProvider() {
		return [
			['[1]'],
			['["bar"]'],
			['["bar", 1, null]']
		];
	}

	/**
	 * Test JSON RPC with batch of invalid requests.
	 *
	 * @dataProvider invalidRequestsBatchProvider
	 *
	 * @param string $data
	 */
	public function testInvalidRequestsBatch($data) {
		$response_array = $this->api_call_raw($data, $debug);

		$this->assertInternalType('array', $response_array);
		$this->assertNotEmpty($response_array);

		foreach ($response_array as $response) {
			$this->assertArrayHasKey('error', $response);
			$this->assertArrayHasKey('code', $response['error']);
			$this->assertEquals(-32600, $response['error']['code']);
		}
	}
}

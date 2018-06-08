<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * Provides valid requests for JSON RPC.
	 */
	public function validRequestProvider() {
		return [
			['item.get', ['itemids' => []]],
			['host.get', ['hostids' => []]]
		];
	}

	/**
	 * Test JSON RPC with valid request.
	 *
	 * @dataProvider validRequestProvider
	 *
	 * @param string $method
	 * @param array $params
	 */
	public function testValidRequest($method, $params) {
		$this->call($method, $params);
	}

	/**
	 * Provides valid requests in batch for JSON RPC.
	 */
	public function validRequestsBatchProvider() {
		return [
			[
				[
					['method' => 'item.get', 'params' => '{"itemids":[]}'],
					['method' => 'host.get', 'params' => '{"hostids":[]}']
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
		$this->authorize('Admin', 'zabbix');
		$length = count($batch);
		$i = 1;

		$data = '[';
		foreach ($batch as $attrs) {
			$data .= '{"jsonrpc": "2.0", "method": "'.$attrs['method'].'", "auth": "'.$this->session.'", "params": '.
				$attrs['params'].', "id": '.($this->request_id++).'}';

			if ($i < $length) {
				$data .= ', ';
			}

			$i++;
		}
		$data .= ']';

		$response_array = $this->callRaw($data);

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
		$this->checkResult($this->callRaw($data), -32600);
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
		$response_array = $this->callRaw($data);

		$this->assertInternalType('array', $response_array);
		$this->assertNotEmpty($response_array);

		foreach ($response_array as $response) {
			$this->checkResult($response, -32600);
		}
	}
}

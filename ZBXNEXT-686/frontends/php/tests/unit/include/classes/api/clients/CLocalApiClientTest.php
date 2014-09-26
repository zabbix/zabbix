<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CLocalApiClientTest extends CApiClientTest {

	/**
	 * Test API calls with correct parameters.
	 */
	public function testCall() {
		$this->markTestIncomplete();
	}

	public function incorrectCallProvider() {
		return array(
			// missing version
			array('user.incorrectMethod', array(), 'token', 1, null,
				-32600, 'Invalid Request.', 'JSON-rpc version is not specified.'
			),
			// incorrect version
			array('user.incorrectMethod', array(), 'token', 1, '3',
				-32600, 'Invalid Request.', 'Expecting JSON-rpc version 2.0, "3" is given.'
			),
			// missing method
			array(null, array(), 'token', 1, '2.0',
				-32600, 'Invalid Request.', 'JSON-rpc method is not defined.'
			),
			// incorrect method
			array('method', array(), 'token', 1, '2.0',
				-32600, 'Invalid Request.', 'Incorrect method "method".'
			),
			// incorrect method
			array('invalidapi.method', array(), 'token', 1, '2.0',
				-32600, 'Invalid Request.', 'Incorrect method "invalidapi.method".'
			),
			// incorrect method
			array('user.incorrectMethod', array(), 'token', 1, '2.0',
				-32600, 'Invalid Request.', 'Incorrect method "user.incorrectMethod".'
			),
			// invalid param type
			array('apiinfo.version', 'string', null, 1, '2.0',
				-32602, 'Invalid params.', 'JSON-rpc params is not an Array.'
			),
			// no auth token
			array('user.get', array(), null, 1, '2.0',
				-32602, 'Invalid params.', 'Not authorised.'
			),
			// empty auth token
			array('user.get', array(), '', 1, '2.0',
				-32602, 'Invalid params.', 'Not authorised.'
			),
			// unnecessary auth token
			array('Apiinfo.Version', array(), '', 1, '2.0',
				-32602, 'Invalid params.', 'The "Apiinfo.Version" method must be called without the "auth" parameter.'
			),
		);
	}

	/**
	 * Test API calls with incorrect parameters.
	 *
	 * @dataProvider incorrectCallProvider()
	 */
	public function testCallIncorrect($method, $params, $auth, $id, $jsonRpc, $expectedErrorCode, $expectedErrorMessage, $expectedErrorData) {
		// setup a mock user API to authenticate the user
		$userMock = $this->getMock('CUser', array('checkAuthentication'));
		$userMock->expects($this->any())->method('checkAuthentication')->will($this->returnValue(array(
			'debug_mode' => false
		)));

		$client = $this->createClient();
		$client->setServiceFactory(new CRegistryFactory(array(
			'host' => 'CHost',
			'apiinfo' => 'CAPIInfo',
			'user' => function() use ($userMock) {
				return $userMock;
			}
		)));

		$response = $client->callMethod($method, $params, $auth, $id, $jsonRpc);
		$this->assertTrue($response instanceof CApiResponse);
		$this->assertEquals($expectedErrorCode, $response->getErrorCode());
		$this->assertEquals($expectedErrorMessage, $response->getErrorMessage());
		$this->assertEquals($expectedErrorData, $response->getErrorData());
		$this->assertEquals($id, $response->getId());
		$this->assertEquals($jsonRpc, $response->getJsonRpc());
		$this->assertNull($response->getResult());
		$this->assertNull($response->getDebug());
	}

	/**
	 * Test that invalid JSON strings are handled correctly.
	 */
	public function testCallJsonIncorrectJson() {
		$response = $this->createClient()->callJson('asdf');

		$this->assertTrue($response instanceof CApiResponse);
		$this->assertEquals(-32700, $response->getErrorCode());
		$this->assertEquals('Parse error', $response->getErrorMessage());
		$this->assertEquals('Incorrect JSON string.', $response->getErrorData());
		$this->assertNull($response->getResult());
		$this->assertNull($response->getDebug());
		$this->assertNull(null, $response->getId());
		$this->assertNull(null, $response->getId());
	}

	/**
	 * Test JSON calls with correct parameters.
	 */
	public function testCallJson() {
		$this->markTestIncomplete();
	}

	protected function createClient() {
		return new CLocalApiClient(new CJson());
	}
}

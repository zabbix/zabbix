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


class CLocalApiClientTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CLocalApiClient
	 */
	protected $client;

	public function setUp() {
		$this->client = new CLocalApiClient();
	}

	/**
	 * Test API calls with correct parameters.
	 */
	public function testCall() {
		$this->markTestIncomplete();
	}

	public function incorrectCallProvider() {
		return array(
			// incorrect api
			array('Api', 'method', array(), 'token',
				ZBX_API_ERROR_PARAMETERS, 'Incorrect API "Api".'
			),
			// incorrect method
			array('user', 'incorrectMethod', array(), 'token',
				ZBX_API_ERROR_PARAMETERS, 'Incorrect method "user.incorrectMethod".'
			),
			// no auth token
			array('user', 'get', array(), null,
				ZBX_API_ERROR_NO_AUTH, 'Not authorised.'
			),
			// empty auth token
			array('user', 'get', array(), '',
				ZBX_API_ERROR_NO_AUTH, 'Not authorised.'
			),
			// unnecessary auth token
			array('Apiinfo', 'Version', array(), '',
				ZBX_API_ERROR_PARAMETERS, 'The "Apiinfo.Version" method must be called without the "auth" parameter.'
			),
		);
	}

	/**
	 * Test API calls with incorrect parameters.
	 *
	 * @dataProvider incorrectCallProvider()
	 */
	public function testCallIncorrect($api, $method, array $params, $auth, $expectedErrorCode, $expectedErrorMessage) {
		// setup a mock user API to authenticate the user
		$userMock = $this->getMock('CUser', array('checkAuthentication'));
		$userMock->expects($this->any())->method('checkAuthentication')->will($this->returnValue(array(
			'debug_mode' => false
		)));

		$this->client->setServiceFactory(new CRegistryFactory(array(
			'host' => 'CHost',
			'apiinfo' => 'CAPIInfo',
			'user' => function() use ($userMock) {
				return $userMock;
			}
		)));

		$response = $this->client->callMethod($api, $method, $params, $auth);
		$this->assertTrue($response instanceof CApiClientResponse);
		$this->assertEquals($expectedErrorCode, $response->errorCode);
		$this->assertEquals($expectedErrorMessage, $response->errorMessage);
		$this->assertNull($response->data);
		$this->assertNull($response->debug);
	}
}

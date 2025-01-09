<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once __DIR__.'/../include/CTest.php';
require_once __DIR__.'/../../include/classes/api/clients/CApiClient.php';
require_once __DIR__.'/../../include/classes/api/clients/CLocalApiClient.php';
require_once __DIR__.'/../../include/classes/api/wrappers/CApiWrapper.php';

class APITest extends CTest {

	protected function setUp(): void {
		API::setWrapper(null);
		API::setApiServiceFactory(new CApiServiceFactory());
	}

	/**
	 * Test the API::getApiService() method.
	 */
	public function testGetApiService() {
		$this->assertEquals(get_class(API::getApiService()), 'CApiService');
		$this->assertEquals(get_class(API::getApiService('item')), 'CItem');
	}

	/**
	 * Test that the API::getApi() method returns a correct result without a wrapper.
	 */
	public function testGetApiNoWrapper() {
		$this->assertEquals(get_class(API::getApi('item')), 'CItem');
	}

	/**
	 * Test that the API::getApi() method returns correct results with a wrapper.
	 */
	public function testGetGetApiWrapper() {
		$client = new CLocalApiClient();
		API::setWrapper(new CApiWrapper($client));

		$item = API::getApi('item');
		$this->assertEquals(get_class($item), 'CApiWrapper');
		$this->assertEquals($item->api, 'item');

		API::setWrapper();
	}
}

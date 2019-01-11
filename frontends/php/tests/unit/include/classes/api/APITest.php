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


class CAPITest extends PHPUnit_Framework_TestCase {

	public function setUp() {
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
	}
}

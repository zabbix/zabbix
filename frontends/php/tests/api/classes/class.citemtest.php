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

require_once dirname(__FILE__).'/../../include/class.capitest.php';

/**
 * @property CItem $api
 */
class CItemTest extends CApiTest {

	/**
	 * Sets up a test host before each test.
	 */
	public function setUp() {
		parent::setUp();

		$this->setUpTestHost();
		$this->api = API::Item();
	}

	public function providerCreateValid() {
		return array(
			// 1. a minimal valid item
			array(
				array(
					'name' => 'Test item 1',
					'key_' => 'item1',
					'description' => '',
					'delay' => 30,
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'type' => ITEM_TYPE_ZABBIX,
					'delay_flex' => ''
				)
			)
		);
	}

	/**
	 * Tests that the item interface remains unchanged after updating.
	 *
	 * @dataProvider providerCreateValid
	 *
	 * @param array $object
	 */
	public function testUpdateInterfaceUnchanged(array $object) {

		// create a test item
		$rs = $this->createTestObject($object);
		$items = $this->api->get(
			array(
				'itemids' => $rs['itemids'],
				'output' => API_OUTPUT_EXTEND,
			)
		);
		$item = reset($items);

		// update the item with some data
		$rs = $this->api->update(
			array(
				array(
					'itemid' => $item['itemid'],
					'name' => 'Test item 2',
					'interfaceid' => $item['interfaceid']
				)
			)
		);

		$this->assertArrayHasKey($this->api->pkOption(), $rs, 'Item update failed.');

		// fetch the updated item
		$updatedItems = $this->api->get(
			array(
				'itemids' => $rs['itemids'],
				'output' => API_OUTPUT_EXTEND,
			)
		);
		$updatedItem = reset($updatedItems);

		$this->assertEquals($item['interfaceid'], $updatedItem['interfaceid'], 'Item interface has changed after the update.');
	}

	/**
	 * Populates the item with some dynamically created data before saving it.
	 *
	 * @param array $object
	 *
	 * @return mixed
	 */
	protected function createTestObject(array $object) {
		$object['hostid'] = $this->testHost['hostid'];
		$interface = reset($this->testHost['interfaces']);
		$object['interfaceid'] = $interface['interfaceid'];

		return parent::createTestObject($object);
	}

}

<?php

class DiscoveredItemTest extends CApiTestCase {

	/**
	 * Test properties that can be set for discovered items.
	 */
	public function testUpdateValid() {
		$fixtures = $this->loadFixtures(array(
			'base' => null,
			'hostWithDiscoveredObjects' => array(
				'groupid' => '@fixtures.base.result.groupid@'
			)
		));
		$this->login('Admin', 'zabbix');

		$item = $this->getItem($fixtures['hostWithDiscoveredObjects']['result']['itemid']);

		foreach (array('status') as $field) {
			$response = $this->call('item.update', array(
				'itemid' => $item['itemid'],
				$field => $item[$field]
			));

			$this->assertResult(array('itemids' => array($item['itemid'])), $response);
		}
	}

	/**
	 * Test properties that cannot be set for discovered items.
	 */
	public function testUpdateInvalid() {
		$fixtures = $this->loadFixtures(array(
			'base' => null,
			'hostWithDiscoveredObjects' => array(
				'groupid' => '@fixtures.base.result.groupid@'
			)
		));
		$this->login('Admin', 'zabbix');

		$item = $this->getItem($fixtures['hostWithDiscoveredObjects']['result']['itemid']);

		// the set of properties is random and incomplete
		foreach (array('delay', 'interfaceid', 'key_', 'name', 'type', 'value_type') as $field) {
			$response = $this->call('item.update', array(
				'itemid' => $item['itemid'],
				$field => $item[$field]
			));

			$this->assertError(array(
				'code' => -32500,
				'message' => 'Application error.',
				'data' => sprintf('Cannot update "%1$s" for a discovered item.', $field)
			), $response);
		}
	}

	/**
	 * Test that we're not able to delete discovered items.
	 */
	public function testDelete() {
		$fixtures = $this->loadFixtures(array(
			'base' => null,
			'hostWithDiscoveredObjects' => array(
				'groupid' => '@fixtures.base.result.groupid@'
			)
		));
		$this->login('Admin', 'zabbix');

		$response = $this->call('item.delete', array(
			$fixtures['hostWithDiscoveredObjects']['result']['itemid']
		));

		$this->assertError(array(
			'code' => -32500,
			'message' => 'Application error.',
			'data' => 'Cannot delete a discovered item.'
		), $response);
	}

	protected function getItem($itemId) {
		$items = $this->call('item.get', array(
			'itemid' => $itemId
		));
		$item = $items->getResult();

		return $item[0];
	}
}

<?php

use Zabbix\Test\ApiTestCase;

class DiscoveredItemTest extends ApiTestCase {

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

		$item = $this->getItem($fixtures['hostWithDiscoveredObjects']['result']['itemid']);

		foreach (array('status') as $field) {
			$result = $this->callMethod('item.update', array(
				'itemid' => $item['itemid'],
				$field => $item[$field]
			));

			$this->assertResult($result, sprintf('Shouldn be able to update "%1$s".', $field));
			$this->assertResponse(array('itemids' => array($item['itemid'])), $result);
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

		$item = $this->getItem($fixtures['hostWithDiscoveredObjects']['result']['itemid']);

		// the set of properties is random and incomplete
		foreach (array('delay', 'interfaceid', 'key_', 'name', 'type', 'value_type') as $field) {
			$result = $this->callMethod('item.update', array(
				'itemid' => $item['itemid'],
				$field => $item[$field]
			));

			$this->assertError($result, sprintf('Shouldn\'t be able to update "%1$s".', $field));
			$this->assertResponse(array(
				'code' => -32500,
				'message' => 'Application error.',
				'data' => sprintf('Cannot update "%1$s" for a discovered item.', $field)
			), $result);
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

		$result = $this->callMethod('item.delete', array(
			$fixtures['hostWithDiscoveredObjects']['result']['itemid']
		));

		$this->assertError($result);

		// the message is imprecise and is likely to change
		$error = $result->getError();
		$this->assertEquals('Cannot delete a discovered item.', $error['data']);
	}

	protected function getItem($itemId) {
		$items = $this->callMethod('item.get', array(
			'itemid' => $itemId
		));
		$item = $items->getResult();

		return $item[0];
	}
}

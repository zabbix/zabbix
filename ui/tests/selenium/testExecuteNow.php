<?php
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup profiles
 *
 * @dataSource ExecuteNowAction
 */
class testExecuteNow extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	public static function getLatestDataPagetData() {
		return [
			// Simple items.
			[
				[
					'items' => ['I4-trap-log']
				]
			],
			[
				[
					'items' => ['I2-lvl1-trap-num', 'Download speed for scenario "Web scenario for execute now".']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I5-agent-txt'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I5-agent-txt', 'I4-trap-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl2-dep-log'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl2-dep-log'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I3-web-dep'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			// Non-allowed master item type and its dependent item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl1-trap-num', 'I2-lvl3-dep-txt'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Non-allowed dependent item and non-allowed simple item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl2-dep-log', 'I4-trap-log'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Non-allowed dependent item and allowed dependent item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl3-dep-txt', 'I2-lvl2-dep-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Allowed dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl3-dep-txt', 'I1-lvl2-dep-log'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl3-dep-txt', 'I5-agent-txt'],
					'message' => 'Request sent successfully'
				]
			],
			// Allowed dependent item and non-allowed simple item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl3-dep-txt', 'I4-trap-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Web scenario item and non-allowed dependent item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl2-dep-log', 'Download speed for scenario "Web scenario for execute now".'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Web scenario item and allowed dependent item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl3-dep-txt', 'Download speed for scenario "Web scenario for execute now".'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Web scenario item and allowed item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I5-agent-txt', 'Download speed for scenario "Web scenario for execute now".'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Web scenario item and dependent item from web scenario item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I3-web-dep', 'Download speed for scenario "Web scenario for execute now".'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Dependent web scenario item and allowed item type.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I3-web-dep', 'I5-agent-txt'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" button on Latest data page.
	 *
	 * @dataProvider getLatestDataPagetData
	 */
	public function testExecuteNow_LatestDataPage($data) {
		// Login and select host group for testing.
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$table = $this->query('xpath://table['.CXPathHelper::fromClass('list-table fixed').']')->asTable()->one();
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Host groups' => 'HG-for-executenow']);
		$filter_form->submit();
		$table->waitUntilReloaded();
		$this->selectItemsAndExecuteNow($data, $table);
	}

	public static function getIemContexMenuData() {
		return [
			// Simple items.
			[
				[
					'item' => 'I4-trap-log'
				]
			],
			[
				[
					'item' => 'I2-lvl1-trap-num'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'item' => 'I5-agent-txt'
				]
			],
			// Web scenario item.
			[
				[
					'item' => 'Download speed for scenario "Web scenario for execute now".'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_BAD,
					'item' => 'I3-web-dep',
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'item' => 'I1-lvl2-dep-log'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'item' => 'I2-lvl2-dep-log',
					'message' => 'Cannot send request: wrong master item type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" option in Item context menu on Latest data page.
	 *
	 * @dataProvider getIemContexMenuData
	 */
	public function testExecuteNow_ContextMenu($data) {
		// Login and select host group for testing.
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$table = $this->query('xpath://table['.CXPathHelper::fromClass('list-table fixed').']')->asTable()->waitUntilVisible()->one();
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Host groups' => 'HG-for-executenow']);
		$filter_form->submit();
		$table->waitUntilReloaded();

		$this->query('link', $data['item'])->waitUntilClickable()->one()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();

		// Disabled "Execute now" option in context menu.
		if (!array_key_exists('expected', $data)) {
			$this->assertFalse($popup->getItem('Execute now')->isEnabled());
			return;
		}

		$popup->fill('Execute now');
		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Request sent successfully');
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot execute operation', $data['message']);
		}
	}

	public static function getIemsListData() {
		return [
			// Simple items.
			[
				[
					'items' => ['I4-trap-log']
				]
			],
			[
				[
					'items' => ['I2-lvl1-trap-num']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I5-agent-txt'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I5-agent-txt', 'I4-trap-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: I1-lvl2-dep-log'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl1-trap-num: I2-lvl2-dep-log'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['Last error message of scenario "Web scenario for execute now".: I3-web-dep'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			// Non-allowed master item type and its dependent item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl1-trap-num', 'I2-lvl1-trap-num: I2-lvl3-dep-txt'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Non-allowed dependent item and non-allowed simple item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl1-trap-num: I2-lvl2-dep-log', 'I4-trap-log'],
					'message' => 'Cannot send request: wrong item type.'
				]
			],
			// Non-allowed dependent item and allowed dependent item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: I1-lvl3-dep-txt', 'I2-lvl1-trap-num: I2-lvl2-dep-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Allowed dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: I1-lvl3-dep-txt', 'I1-lvl1-agent-num: I1-lvl2-dep-log'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: I1-lvl3-dep-txt', 'I5-agent-txt'],
					'message' => 'Request sent successfully'
				]
			],
			// Allowed dependent item and non-allowed simple item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: I1-lvl3-dep-txt', 'I4-trap-log'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Dependent web scenario item and allowed item type.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['Last error message of scenario "Web scenario for execute now".: I3-web-dep', 'I5-agent-txt'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" button on Items list page.
	 *
	 * @dataProvider getIemsListData
	 */
	public function testExecuteNow_ItemsList($data) {
		$hostid = CDataHelper::get('ExecuteNowAction.hostids.Host for execute now permissions');
		$this->page->login()->open('zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host')->waitUntilReady();
		$table = $this->query('xpath://form[@name="item_list"]//table')->asTable()->one()->waitUntilPresent();
		$this->selectItemsAndExecuteNow($data, $table);
	}

	public static function getItemPageData() {
		return [
			// Simple items.
			[
				[
					'name' => 'I4-trap-log'
				]
			],
			[
				[
					'name' => 'I2-lvl1-trap-num'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'I1-lvl1-agent-num',
					'message' => 'Request sent successfully'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'I1-lvl2-dep-log',
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'I2-lvl2-dep-log',
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'I3-web-dep',
					'message' => 'Cannot send request: wrong master item type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" button on Item page.
	 *
	 * @dataProvider getItemPageData
	 */
	public function testExecuteNow_ItemPage($data) {
		$hostid = CDataHelper::get('ExecuteNowAction.hostids.Host for execute now permissions');
		$this->page->login()->open('zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host')->waitUntilReady();
		$table = $this->query('xpath://form[@name="item_list"]//table')->asTable()->one()->waitUntilPresent();
		$this->openItemAndExecuteNow($data, $table);

		COverlayDialogElement::find()->one()->close();
	}

	public static function getDiscoveryRulesListData() {
		return [
			// Simple items.
			[
				[
					'items' => ['DR2-trap']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['DR1-agent'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['DR1-agent', 'DR2-trap'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: DR3-I1-dep-agent'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['I2-lvl1-trap-num: DR4-I2-dep-trap'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'items' => ['Last error message of scenario "Web scenario for execute now".: DR5-web-dep'],
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			// Non-allowed item type and non-allowed dependent item.
			[
				[
					'expected' => TEST_BAD,
					'items' => ['DR2-trap', 'I2-lvl1-trap-num: DR4-I2-dep-trap'],
					'message' => 'Cannot send request: wrong discovery rule type.'
				]
			],
			// Non-allowed dependent item and allowed dependent item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: DR3-I1-dep-agent', 'I2-lvl1-trap-num: DR4-I2-dep-trap'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Allowed dependent item and non-allowed simple item.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['I1-lvl1-agent-num: DR3-I1-dep-agent', 'DR2-trap'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			],
			// Dependent web scenario item and allowed item type.
			[
				[
					'expected' => TEST_GOOD,
					'items' => ['Last error message of scenario "Web scenario for execute now".: DR5-web-dep', 'DR1-agent'],
					'message' => 'Request sent successfully. Some items are filtered due to access permissions or type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" button on Discovery rule list page.
	 *
	 * @dataProvider getDiscoveryRulesListData
	 */
	public function testExecuteNow_DiscoveryRulesList($data) {
		$hostid = CDataHelper::get('ExecuteNowAction.hostids.Host for execute now permissions');
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host')->waitUntilReady();
		$table = $this->query('xpath://form[@name="discovery"]//table')->asTable()->one()->waitUntilPresent();
		$this->selectItemsAndExecuteNow($data, $table);
	}

	public static function getDiscoveryRuleData() {
		return [
			[
				[
					'name' => 'DR2-trap'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'DR1-agent',
					'message' => 'Request sent successfully'
				]
			],
			// Dependent items.
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'DR3-I1-dep-agent',
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'DR4-I2-dep-trap',
					'message' => 'Cannot send request: wrong master item type.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'DR5-web-dep',
					'message' => 'Cannot send request: wrong master item type.'
				]
			]
		];
	}

	/**
	 * Check "Execute now" button on Discovery rule page.
	 *
	 * @dataProvider getDiscoveryRuleData
	 */
	public function testExecuteNow_DiscoveryRulePage($data) {
		$hostid = CDataHelper::get('ExecuteNowAction.hostids.Host for execute now permissions');
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$hostid.'&context=host')->waitUntilReady();
		$table = $this->query('xpath://form[@name="discovery"]//table')->asTable()->one()->waitUntilPresent();
		$this->openItemAndExecuteNow($data, $table, true);
	}

	/**
	 * Open item or discovery rule list page and check "Execute now" button functionality.
	 *
	 * @param array $data			data provider
	 * @param CElement $table		table element
	 */
	private function selectItemsAndExecuteNow($data, $table) {
		$selected_count = $this->query('id:selected_count')->one();

		$table->findRows('Name', $data['items'])->select();
		$this->assertSelectedCount(count($data['items']));

		// Disabled "Execute now" button.
		if (!array_key_exists('expected', $data)) {
			$this->assertTrue($this->query('button:Execute now')->one()->isEnabled(false));
			return;
		}

		$this->query('button:Execute now')->one()->click();

		if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, $data['message']);
			// After a successful "Execute now" action, the item selection is reset.
			$this->assertSelectedCount(0);
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot execute operation', $data['message']);
			$this->assertSelectedCount(count($data['items']));
		}
	}

	/**
	 * Open item or discovery rule page and check "Execute now" button functionality.
	 *
	 * @param array $data			data provider
	 * @param CElement $table		table element
	 */
	private function openItemAndExecuteNow($data, $table, $lld = false) {
		$table->query('link', $data['name'])->waitUntilClickable()->one()->click();

		if ($lld === false) {
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		}

		// Disabled "Execute now" button.
		if (!array_key_exists('expected', $data)) {
			$lld
				? $this->query('button:Execute now')->one()->isEnabled(false)
				: $this->assertTrue($dialog->getFooter()->query('button:Execute now')->one()->isEnabled(false));
			return;
		}

		$lld
			? $this->query('button:Execute now')->one()->click()
			: $dialog->getFooter()->query('button:Execute now')->one()->click();

		if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, $data['message']);
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot execute operation', $data['message']);
		}
	}
}

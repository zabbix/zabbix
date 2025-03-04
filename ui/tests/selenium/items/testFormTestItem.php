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


require_once dirname(__FILE__).'/../common/testItemTest.php';

/**
 * "Test item" function tests.
 *
 * @dataSource Proxies, GlobalMacros
 *
 * @backup items
 */
class testFormTestItem extends testItemTest {

	/**
	 * Check Test item Button enabled/disabled state depending on item type for Host.
	 *
	 * @backupOnce items
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
	 */
	public function testFormTestItem_CheckButtonStateHost() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(), 'Item for Test Button check', 'Item',
				' added', true, true, self::HOST_ID, 'items');
	}

	/**
	 * Check Test item Button enabled/disabled state depending on item type for Template.
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
	 */
	public function testFormTestItem_CheckButtonStateTemplate() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(), 'Item for Test Button check', 'Item',
				' added', false, false, self::TEMPLATE_ID, 'items');
	}

	/**
	 * Check Test item form for Host.
	 *
	 * @dataProvider getItemTestItemData
	 *
	 * @depends testFormTestItem_CheckButtonStateHost
	 */
	public function testFormTestItem_TestItemHost($data) {
		$this->checkTestItem($data, true, self::HOST_ID, 'items');
	}

	/**
	 * Check Test item form for Template.
	 *
	 * @dataProvider getItemTestItemData
	 *
	 * @depends testFormTestItem_CheckButtonStateTemplate
	 */
	public function testFormTestItem_TestItemTemplate($data) {
		$this->checkTestItem($data, false, self::TEMPLATE_ID, 'items');
	}
}

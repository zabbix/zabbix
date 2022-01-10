<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../common/testItemTest.php';

/**
 * "Test item" function tests.
 *
 * @backup items
 */
class testFormTestItem extends testItemTest{

	/**
	 * Check Test item Button enabled/disabled state depending on item type for Host.
	 *
	 * @backupOnce items
	 */
	public function testFormTestItem_CheckButtonStateHost() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(), 'Item for Test Button check', 'Item',
				' added', true, true, self::HOST_ID, 'items');
	}

	/**
	 * Check Test item Button enabled/disabled state depending on item type for Template.
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

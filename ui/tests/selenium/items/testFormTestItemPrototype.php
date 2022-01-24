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
 * "Test item prototype" function tests.
 *
 * @backup items
 */
class testFormTestItemPrototype extends testItemTest {

	const HOST_LLD_ID = 99294;		// 'Test discovery rule on Test item host'
	const TEMPLATE_LLD_ID = 99349;  // 'Test discovery rule on Test Item Template'

	/**
	 * Check Test item prototype Button enabled/disabled state depending on item type for Host.
	 *
	 * @backupOnce items
	 */
	public function testFormTestItemPrototype_CheckButtonStateHost() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(), 'Item prototype for Test Button check',
				'Item prototype', ' added', false, true, self::HOST_LLD_ID, null);
	}

	/**
	 * Check Test item prototype Button enabled/disabled state depending on item type for Template.
	 */
	public function testFormTestItemPrototype_CheckButtonStateTemplate() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(), 'Item prototype for Test Button check',
				'Item prototype', ' added', false, false, self::TEMPLATE_LLD_ID, null);
	}

	/**
	 * Check Test item prototype form for Host.
	 *
	 * @dataProvider getPrototypeTestItemData
	 *
	 * @depends testFormTestItemPrototype_CheckButtonStateHost
	 */
	public function testFormTestItemPrototype_TestItemHost($data) {
		$this->checkTestItem($data, true, self::HOST_LLD_ID, null, true);
	}

	/**
	 * Check Test item prototype form for Template.
	 *
	 * @dataProvider getPrototypeTestItemData
	 *
	 * @depends testFormTestItemPrototype_CheckButtonStateTemplate
	 */
	public function ttestFormTestItemPrototype_TestItemTemplate($data) {
		$this->checkTestItem($data, false, self::TEMPLATE_LLD_ID, null, true);
	}
}

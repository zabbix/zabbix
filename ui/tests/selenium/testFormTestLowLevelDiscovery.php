<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testItemTest.php';

/**
 * "Test item" function tests.
 *
 * @backup items
 */
class testFormTestLowLevelDiscovery extends testItemTest{

	const HOST_ID = 99136;		// 'Test item host' monitored by 'Active proxy 1'
	const TEMPLATE_ID = 99137;	//'Test Item Template'

	/**
	 * Check Test LLD Button enabled/disabled state depending on item type for host.
	 *
	 * @backup-once items
	 */
	public function testFormTestLowLevelDiscovery_CheckButtonStateHost() {
		$this->checkTestButtonState($this->getCommonTestButtonStateData(), 'LLD for Test Button check', 'Discovery rule',
				' created', true, true, self::HOST_ID, 'host_discovery');
	}

	/**
	 * Check Test LLD Button enabled/disabled state depending on item type for template.
	 */
	public function testFormTestLowLevelDiscovery_CheckButtonStateTemplate() {
		$this->checkTestButtonState($this->getCommonTestButtonStateData(), 'LLD for Test Button check', 'Discovery rule',
				' created', false, false, self::TEMPLATE_ID, 'host_discovery');
	}

	/**
	 * Check Test LLD form.
	 *
	 * @dataProvider getCommonTestItemData
	 *
	 * @depends testFormTestLowLevelDiscovery_CheckButtonStateHost
	 */
	public function testFormTestLowLevelDiscovery_TestLLDHost($data) {
		$this->checkTestItem($data, true, self::HOST_ID, 'host_discovery');
	}

	/**
	 * Check Test LLD form.
	 *
	 * @dataProvider getCommonTestItemData
	 *
	 * @depends testFormTestLowLevelDiscovery_CheckButtonStateTemplate
	 */
	public function testFormTestLowLevelDiscovery_TestLLDTemplate($data) {
		$this->checkTestItem($data, false, self::TEMPLATE_ID, 'host_discovery');
	}
}

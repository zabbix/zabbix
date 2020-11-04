<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormItemTest.php';

/**
 * @backup items
 */
class testFormItemTestTemplate extends testFormItemTest {

	const TEMPLATE_ID	= 99137;	//'Test Item Template'
	const IS_HOST		= false;
	const LLD_ID		= 99349;	// 'Test discovery rule'

	/**
	 * Creation link forming function for items or LLD.
	 *
	 * @param string	$pointer  instance pointing string: items or host_discovery
	 */
	public function getCreateLink($pointer) {
		return $pointer.'.php?form=create&hostid='.self::TEMPLATE_ID;
	}

	/**
	 * Saved item or LLD link forming function.
	 *
	 * @param string	$pointer  instance pointing string: items or host_discovery
	 */
	public function getSavedLink($pointer) {
		return $pointer.'.php?form=update&hostid='.self::TEMPLATE_ID.'&itemid=';
	}

	/**
	 * Check Test item Button enabled/disabled state depending on item type.
	 *
	 * @backup-once items
	 */
	public function testFormItemTestTemplate_CheckItemTestButtonState() {
		$this->checkTestButtonState($this->getItemTestButtonStateData(),
			'Item for Test Button check', $this->getCreateLink('items'),
			$this->getSavedLink('items'), 'Item', ' added', false, self::IS_HOST
		);
	}

	/**
	 * Check Test item prototype Button enabled/disabled state depending on item type.
	 */
	public function testFormItemTestTemplate_CheckItemPrototypeTestButtonState() {
		$prototype_create_link = 'disc_prototypes.php?form=create&parent_discoveryid='.self::LLD_ID;
		$prototype_saved_link = 'disc_prototypes.php?form=update&parent_discoveryid='.self::LLD_ID.'&itemid=';

		$this->checkTestButtonState($this->getItemTestButtonStateData(),
			'Item prototype for Test Button check', $prototype_create_link,
			$prototype_saved_link, 'Item prototype', ' added', false, self::IS_HOST
		);
	}

	/**
	 * Check Test LLD Button enabled/disabled state depending on item type.
	 */
	public function testFormItemTestTemplate_CheckLLDTestButtonState() {
		$this->checkTestButtonState($this->getCommonTestButtonStateData(),
			'LLD for Test Button check', $this->getCreateLink('host_discovery'),
			$this->getSavedLink('host_discovery'), 'Discovery rule', ' created',
			false, self::IS_HOST
		);
	}

	/**
	 * Check Test item form.
	 *
	 * @dataProvider getItemTestItemData
	 *
	 * @depends testFormItemTestTemplate_CheckItemTestButtonState
	 */
	public function testFormItemTestTemplate_TestItem($data) {
		$this->checkTestItem($this->getCreateLink('items'), $data, self::IS_HOST);
	}

	/**
	 * Check Test item prototype form.
	 *
	 * @dataProvider getPrototypeTestItemData
	 *
	 * @depends testFormItemTestTemplate_CheckItemPrototypeTestButtonState
	 */
	public function testFormItemTestTemplate_TestItemPrototype($data) {
		$prototype_create_link = 'disc_prototypes.php?form=create&parent_discoveryid='.self::LLD_ID;

		$this->checkTestItem($prototype_create_link, $data, self::IS_HOST);
	}

	/**
	 * Check Test LLD form.
	 *
	 * @dataProvider getCommonTestItemData
	 *
	 * @depends testFormItemTestTemplate_CheckLLDTestButtonState
	 */
	public function testFormItemTestTemplate_TestLLD($data) {
		$this->checkTestItem($this->getCreateLink('host_discovery'), $data, self::IS_HOST);
	}
}

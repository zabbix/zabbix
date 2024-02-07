<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__) . '/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareItemPrototypeTemplateData
 */
class testPagePrototypeItemsTemplate extends testPagePrototypes {

	public $page_name = 'item';
	public $tag = '5 Item prototype trapper with text type';

	protected $link = 'zabbix.php?action=item.prototype.list&context=template&sort=name&sortorder=ASC&';
	protected static $prototype_itemids;
	protected static $host_druleids;

	public function prepareItemPrototypeTemplateData() {
		$response = CDataHelper::createTemplates([
			[
				'host' => 'Template for prototype check',
				'groups' => [['groupid' => 1]], // template group 'Templates'
				'items' => [
					[
						'name' => 'Master item',
						'key_' => 'master_item',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => 0
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Drule for prototype check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		$template_id = $response['templateids'];
		self::$host_druleids = $response['discoveryruleids'];

		$item_prototype = CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype monitored discovered',
				'key_' => '1_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 15,
				'history' => '60d',
				'trends' => '200d'
			],
			[
				'name' => '2 Item prototype not monitored discovered',
				'key_' => '2_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_INTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 30,
				'status' => ITEM_STATUS_DISABLED,
				'history' => '70d',
				'trends' => '250d'
			],
			[
				'name' => '3 Item prototype not monitored not discovered',
				'key_' => '3_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'url' => 'test',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 45,
				'status' => ITEM_STATUS_DISABLED,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '80d',
				'trends' => '300d'
			],
			[
				'name' => '4 Item prototype monitored not discovered',
				'key_' => '4_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1+1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 60,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '90d',
				'trends' => '350d'
			],
			[
				'name' => '5 Item prototype trapper with text type',
				'key_' => '5_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '',
				'history' => 0,
				'tags' => [
					[
						'tag' => 'name_1',
						'value' => 'value_1'
					],
					[
						'tag' => 'name_2',
						'value' => 'value_2'
					]
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype);
		self::$prototype_itemids = CDataHelper::getIds('name');
		self::$entity_count = count(self::$prototype_itemids);
	}

	public function testPagePrototypeItemsTemplate_Layout() {
		$this->page->login()->open($this->link.'parent_discoveryid='.
				self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->checkLayout(true);
	}

	/**
	 * Sort item prototypes by Name, Key, Interval, History, Trends, Type, Create enabled and Discover columns.
	 *
	 * @dataProvider getItemsSortingData
	 */
	public function testPagePrototypeItemsTemplate_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=template&sort='.$data['sort'].
				'&sortorder=ASC&parent_discoveryid='.self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getItemsButtonLinkData
	 */
	public function testPagePrototypeItemsTemplate_ButtonLink($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.
				self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getItemsDeleteData
	 */
	public function testPagePrototypeItemsTemplate_Delete($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.
				self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_itemids[$name];
		}

		$this->checkDelete($data, $ids);
	}

	/**
	 * Check that empty values displayed in Trends and Interval columns. SNMP, Zabbix trappers has empty values in trends column.
	 * Dependent items has empty update interval column.
	 *
	 * @dataProvider getItemsNotDisplayedValuesData
	 */
	public function testPagePrototypeItemsTemplate_NotDisplayedValues($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.
				self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->checkNotDisplayedValues($data);
	}
}

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


require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareItemPrototypeTemplateData
 */
class testPageItemPrototypesTemplate extends testPagePrototypes {

	public $source = 'item';
	public $tag = 'Yw Item prototype trapper with text type';

	protected $link = 'zabbix.php?action=item.prototype.list&context=template&sort=name&sortorder=ASC&';
	protected static $prototype_itemids;
	protected static $host_druleid;

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
		$template_id = $response['templateids']['Template for prototype check'];
		self::$host_druleid = $response['discoveryruleids']['Template for prototype check:drule'];

		$item_prototype = CDataHelper::call('itemprototype.create', [
			[
				'name' => '3a Item prototype monitored discovered',
				'key_' => '3a_key[{#KEY}]',
				'hostid' => $template_id,
				'ruleid' => self::$host_druleid,
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '15',
				'history' => '1h',
				'trends' => '24h'
			],
			[
				'name' => '15 Item prototype not monitored discovered',
				'key_' => '15_key[{#KEY}]',
				'hostid' => $template_id,
				'ruleid' => self::$host_druleid,
				'type' => ITEM_TYPE_INTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '33m',
				'status' => ITEM_STATUS_DISABLED,
				'history' => '61m',
				'trends' => '86450s'
			],
			[
				'name' => '33b4 Item prototype not monitored not discovered',
				'key_' => '33b4_key[{#KEY}]',
				'hostid' => $template_id,
				'ruleid' => self::$host_druleid,
				'type' => ITEM_TYPE_HTTPAGENT,
				'url' => 'test',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '15h',
				'status' => ITEM_STATUS_DISABLED,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '2d',
				'trends' => '2d'
			],
			[
				'name' => 'a3 Item prototype monitored not discovered',
				'key_' => 'a3_key[{#KEY}]',
				'hostid' => $template_id,
				'ruleid' => self::$host_druleid,
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1+1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '1d',
				'discover' => ITEM_NO_DISCOVER,
				'history' => '1w',
				'trends' => '1w'
			],
			[
				'name' => 'Yw Item prototype trapper with text type',
				'key_' => 'Yw_key[{#KEY}]',
				'hostid' => $template_id,
				'ruleid' => self::$host_druleid,
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

	public function testPageItemPrototypesTemplate_Layout() {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleid)->waitUntilReady();
		$this->checkLayout(true);
	}

	/**
	 * Sort item prototypes by Name, Key, Interval, History, Trends, Type, Create enabled and Discover columns.
	 *
	 * @dataProvider getItemPrototypesSortingData
	 */
	public function testPageItemPrototypesTemplate_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=template&sort='.$data['sort'].
				'&sortorder=ASC&parent_discoveryid='.self::$host_druleid)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getItemPrototypesButtonLinkData
	 */
	public function testPageItemPrototypesTemplate_ButtonLink($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleid)->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getItemPrototypesDeleteData
	 */
	public function testPageItemPrototypesTemplate_Delete($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleid)->waitUntilReady();

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
	 * @dataProvider getItemPrototypesNotDisplayedValuesData
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
	 */
	public function testPageItemPrototypesTemplate_NotDisplayedValues($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleid)->waitUntilReady();
		$this->checkNotDisplayedValues($data);
	}
}

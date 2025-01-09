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
 * @onBefore prepareGraphPrototypeTemplateData
 */
class testPageGraphPrototypesTemplate extends testPagePrototypes {

	public $source = 'graph';

	protected $link = 'graphs.php?context=template&sort=name&sortorder=ASC&parent_discoveryid=';
	protected static $prototype_graphids;
	protected static $host_druleids;

	public function prepareGraphPrototypeTemplateData() {
		$response = CDataHelper::createTemplates([
			[
				'host' => 'Template for host prototype',
				'groups' => [
					['groupid' => 1] // template group 'Templates'
				]
			],
			[
				'host' => 'Template for prototype check',
				'groups' => [['groupid' => 1]], // template group 'Templates'
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

		CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype for graphs',
				'key_' => '1_key[{#KEY}]',
				'hostid' => $template_id['Template for prototype check'],
				'ruleid' => self::$host_druleids['Template for prototype check:drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$prototype_itemid = CDataHelper::getIds('name')['1 Item prototype for graphs'];

		CDataHelper::call('graphprototype.create', [
			[
				'name' => '2a Graph prototype discovered_{#KEY}',
				'width' => 100,
				'height' => 100,
				'graphtype' => 0,
				'gitems' => [
					[
						'itemid' => $prototype_itemid,
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => '33b4 Graph prototype not discovered_{#KEY}',
				'width' => 200,
				'height' => 200,
				'graphtype' => 1,
				'discover' => GRAPH_NO_DISCOVER,
				'gitems' => [
					[
						'itemid' => $prototype_itemid,
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'a3 Graph prototype pie discovered_{#KEY}',
				'width' => 300,
				'height' => 300,
				'graphtype' => 2,
				'gitems' => [
					[
						'itemid' => $prototype_itemid,
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => 'Yw Graph prototype exploded not discovered_{#KEY}',
				'width' => 400,
				'height' => 400,
				'graphtype' => 3,
				'discover' => GRAPH_NO_DISCOVER,
				'gitems' => [
					[
						'itemid' => $prototype_itemid,
						'color' => '00AA00'
					]
				]
			]
		]);
		self::$prototype_graphids = CDataHelper::getIds('name');
		self::$entity_count = count(self::$prototype_graphids);
	}

	public function testPageGraphPrototypesTemplate_Layout() {
		$this->page->login()->open($this->link.self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->checkLayout(true);
	}

	/**
	 * Sort graph prototypes by Name, Graph type and Discover columns.
	 *
	 * @dataProvider getGraphPrototypesSortingData
	 */
	public function testPageGraphPrototypesTemplate_Sorting($data) {
		$this->page->login()->open('graphs.php?context=template&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check link from Discover column.
	 *
	 * @dataProvider getGraphPrototypesButtonLinkData
	 */
	public function testPageGraphPrototypesTemplate_ButtonLink($data) {
		$this->page->login()->open($this->link.self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getGraphPrototypesDeleteData
	 */
	public function testPageGraphPrototypesTemplate_Delete($data) {
		$this->page->login()->open($this->link.self::$host_druleids['Template for prototype check:drule'])->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_graphids[$name];
		}

		$this->checkDelete($data, $ids);
	}
}

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
 * @onBefore prepareHostPrototypeTemplateData
 */
class testPageHostPrototypesTemplate extends testPagePrototypes {

	public $source = 'host';
	public $tag = '1 Host prototype monitored discovered {#H}';

	protected $link = 'host_prototypes.php?context=template&sort=name&sortorder=ASC&parent_discoveryid=';
	protected static $prototype_hostids;
	protected static $host_druleids;

	public function prepareHostPrototypeTemplateData() {
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
		self::$host_druleids = $response['discoveryruleids']['Template for prototype check:drule'];

		CDataHelper::call('hostprototype.create', [
			[
				'host' => '1 Host prototype monitored discovered {#H}',
				'ruleid' => self::$host_druleids,
				'groupLinks' => [
					[
						'groupid' => 4 // Zabbix server
					]
				],
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
			],
			[
				'host' => '2 Host prototype not monitored discovered {#H}',
				'ruleid' => self::$host_druleids,
				'groupLinks' => [
					[
						'groupid' => 4 // Zabbix server
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED
			],
			[
				'host' => 'a Host prototype not monitored not discovered {#H}',
				'ruleid' => self::$host_druleids,
				'groupLinks' => [
					[
						'groupid' => 4 // Zabbix server
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED,
				'discover' => HOST_NO_DISCOVER
			],
			[
				'host' => 'y Host prototype monitored not discovered {#H}',
				'ruleid' => self::$host_druleids,
				'groupLinks' => [
					[
						'groupid' => 4 // Zabbix server
					]
				],
				'discover' => HOST_NO_DISCOVER,
				'templates' => [
					'templateid' => $template_id['Template for host prototype']
				]
			]
		]);
		self::$prototype_hostids = CDataHelper::getIds('host');
		self::$entity_count = count(self::$prototype_hostids);
	}

	public function testPageHostPrototypesTemplate_Layout() {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();
		$this->checkLayout(true);
	}

	/**
	 * Sort host prototypes by Name, Create enabled and Discover column.
	 *
	 * @dataProvider getHostPrototypesSortingData
	 */
	public function testPageHostPrototypesTemplate_Sorting($data) {
		$this->page->login()->open('host_prototypes.php?context=template&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getHostPrototypesButtonLinkData
	 */
	public function testPageHostPrototypesTemplate_ButtonLink($data) {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getHostPrototypesDeleteData
	 */
	public function testPageHostPrototypesTemplate_Delete($data) {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_hostids[$name];
		}

		$this->checkDelete($data, $ids);
	}
}

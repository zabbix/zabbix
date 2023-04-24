<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TagTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup items, interface, hosts
 *
 * @onBefore prepareSelectedHostata
 */

class testDashboardGraphWidgetSelectedHosts extends CWebTest {

	/**
	 * Id of the dashboard with widgets.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * Create data for autotests which use ClockWidget.
	 *
	 * @return array
	 */
	public static function prepareSelectedHostata() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for Graph widgets selected hosts'
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

		$AGENT_INTERFACE = [
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '192.168.3.217',
				'dns' => '',
				'port' => '10050'
			]
		];

		CDataHelper::call('host.create', [
			[
				'host' => 'Host for widget 1',
				'groups' => [
					[
						'groupid' => $hostgrpid['Host group for Graph widgets selected hosts']
					]
				],
				'interfaces' => [
					'type'=> 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '192.168.3.217',
					'dns' => '',
					'port' => '10050'
				]
			],
			[
				'host' => 'Host for widget 2',
				'groups' => [
					[
						'groupid' => $hostgrpid['Host group for Graph widgets selected hosts']
					]
				],
				$AGENT_INTERFACE
			],
		]);
		$hostid = CDataHelper::getIds('host');

		// Select one Agent type interface and use it on other hosts, where items require it.
		$AGENT_INTERFACE_ID = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 1']);

		CDataHelper::call('item.create', [
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph',
				'key_' => 'system.localtime[local]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 2',
				'key_' => 'system.localtime[local2]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID,
				'delay' => '5s'
			]
		]);
		$itemid = CDataHelper::getIds('name');

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating Graph widgets',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'First page',
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
	}


	public static function getCheckData() {
		return [
			[
				[
					'Data set' => [
						'host' => 'Host for widget',
						'item' => 'Item for Graph'
					]
				]
			]
		]

	}

	/**
	 * Function checks if Graph Widget is correcly selecting and displaying hosts, their items
	 */

	public function testDashboardGraphWidgetSelectedHosts_Check() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating Graph widgets']);
		$dashboard = CDashboardElement::find()->one()->edit();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();

		// Start creating Graph widget.
		$form->fill(['Type' => 'Graph']);


	}



}

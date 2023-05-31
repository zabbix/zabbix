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

use Facebook\WebDriver\WebDriverKeys;

/**
 * @backup items, interface, hosts
 *
 * @onBefore prepareSelectedHostdata
 */

class testDashboardGraphWidgetSelectedHosts extends CWebTest {

	/**
	 * Id of the dashboard with widgets.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * @return array
	 */
	public static function prepareSelectedHostdata() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for Graph widgets selected hosts'
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

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
				'host' => 'Host for widget 3',
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
				'host' => 'Host for widget 4',
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
				'host' => 'Host for widget 5',
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
			]
		]);
		$hostid = CDataHelper::getIds('host');

		// Select one Agent type interface and use it on other hosts, where items require it.
		$AGENT_INTERFACE_ID_1 = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 1']);
		$AGENT_INTERFACE_ID_2 = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 2']);
		$AGENT_INTERFACE_ID_3 = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 3']);
		$AGENT_INTERFACE_ID_4 = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 4']);
		$AGENT_INTERFACE_ID_5 = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for widget 5']);

		CDataHelper::call('item.create', [
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 1_1',
				'key_' => 'system.cpu.util[test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_1,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 1_2',
				'key_' => 'vfs.file.get[file]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_1,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 1_3',
				'key_' => 'agent.ping',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_1,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 1_4',
				'key_' => 'agent.variant',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_1,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 1'],
				'name' => 'Item for Graph 1_5',
				'key_' => 'kernel.maxfiles',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_1,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 2'],
				'name' => 'Item for Graph 2_1',
				'key_' => 'kernel.maxfiles',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_2,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 2'],
				'name' => 'Item for Graph 2_2',
				'key_' => 'net.if.in[if,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_2,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 2'],
				'name' => 'Item for Graph 2_3',
				'key_' => 'net.tcp.service.perf[service,test,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_2,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 2'],
				'name' => 'Item for Graph 2_4',
				'key_' => 'net.tcp.listen[port]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_2,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 2'],
				'name' => 'Item for Graph 2_5',
				'key_' => 'modbus.get[endpoint,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_2,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 3'],
				'name' => 'Item for Graph 3_1',
				'key_' => 'kernel.maxfiles',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_3,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 3'],
				'name' => 'Item for Graph 3_2',
				'key_' => 'net.if.in[if,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_3,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 3'],
				'name' => 'Item for Graph 3_3',
				'key_' => 'agent.ping',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_3,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 3'],
				'name' => 'Item for Graph 3_4',
				'key_' => 'kernel.maxproc',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_3,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 3'],
				'name' => 'Item for Graph 3_5',
				'key_' => 'vfs.file.size[file,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_3,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 4'],
				'name' => 'Item for Graph 4_1',
				'key_' => 'vfs.fs.size[fs,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_4,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 4'],
				'name' => 'Item for Graph 4_2',
				'key_' => 'zabbix.stats[test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_4,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 4'],
				'name' => 'Item for Graph 4_3',
				'key_' => 'agent.ping',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_4,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 4'],
				'name' => 'Item for Graph 4_4',
				'key_' => 'service.info[service,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_4,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 4'],
				'name' => 'Item for Graph 4_5',
				'key_' => 'system.cpu.util[test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_4,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 5'],
				'name' => 'Item for Graph 5_1',
				'key_' => 'agent.ping',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_5,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 5'],
				'name' => 'Item for Graph 5_2',
				'key_' => 'vfs.file.time[file,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_5,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 5'],
				'name' => 'Item for Graph 5_3',
				'key_' => 'net.if.collisions[if]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_5,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 5'],
				'name' => 'Item for Graph 5_4',
				'key_' => 'proc.mem[test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_5,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for widget 5'],
				'name' => 'Item for Graph 5_5',
				'key_' => 'sensor[device,sensor,test]',
				'type' => 0,
				'value_type' => 3,
				'interfaceid' => $AGENT_INTERFACE_ID_5,
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

	public static function getCheckDependingData() {
		return [
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 2',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 3',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 4',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 5',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 2',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 3',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 4',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 5',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 2',
						'host' => 'Host for widget 3',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 2',
						'host' => 'Host for widget 4',
						'item' => '*'
					]
				]
			],
			[
				[
					'Data set' => [
						'host' => 'Host for widget 1',
						'host' => 'Host for widget 2',
						'host' => 'Host for widget 5',
						'item' => '*'
					]
				]
			],
		];
	}

	/**
	 * Function checks if Graph Widget is correctly selecting and displaying hosts, their items
	 *
	 * @dataProvider getCheckDependingData
	 */

	public function testDashboardGraphWidgetSelectedHosts_Check($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating Graph widgets']);
		$dashboard = CDashboardElement::find()->one()->edit();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$form->fill(['Type' => 'Graph']);

		if (CTestArrayHelper::isAssociative($data['Data set'])) {
			$data['Data set'] = [$data['Data set']];
		}

		foreach ($data['Data set'] as $data_set) {
			if (array_key_exists('item', $data_set)) {
				$mapping = [
					'item' => 'xpath://input[@placeholder="item pattern"]',
					'host' => 'xpath://input[@placeholder="host pattern"]'
				];
				var_dump('Test');
			} else {
				$mapping = [
					'host' => 'xpath://input[@placeholder="host pattern"]'
				];
				var_dump('Test2');
			}

			foreach ($mapping as $field => $selector) {
				$data_set = [$selector => $data_set[$field]] + $data_set;
				unset($data_set[$field]);
			}

			$form->fill($data_set);
			do {
				$this->query('xpath:.//div[@aria-live="assertive"]');



			} while ($this->query('id:ds_0_hosts_')->one()->asMultiselect()->getOptions()->getText());
		}

		//sleep(1);
		//$element = $form->query('xpath://div[@class="selected"]/..')->one()->waitUntilReady();
		//$this->query('xpath://input[@placeholder="host pattern"]')->one()->fill($data['Data set']['host']);
		sleep(5);
		$this->assertScreenshotExcept();


		//'xpath://input[@placeholder="host pattern"]' => 'Host for widget 1',
		//'xpath://input[@placeholder="item pattern"]' => '*'

	}



}

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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup dashboard, ha_node
 *
 * @backupConfig
 *
 * @onBefore prepareDashboardData
 */
class testDashboardSystemInformationWidget extends CWebTest {

	protected static $dashboardid;
	protected static $timestamp;

	/**
	 * Function creates a dashboard and defines the corresponding dashboard ID.
	 */
	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for SysInfo widget test',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'systeminfo',
								'name' => 'System stats view',
								'width' => 12,
								'height' => 6
							],
							[
								'type' => 'systeminfo',
								'name' => 'High availability nodes view',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 6,
								'fields' => [
									[
										'type' => 0,
										'name' => 'info_type',
										'value' => 1
									]
								]
							]
						]
					],
					[
						'name' => 'Page for creating widgets',
						'widgets' => []
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];
	}

	/**
	 * Function inserts HA cluster data into ha_node table.
	 */
	public static function prepareHANodeData() {
		global $DB;

		self::$timestamp = time();
		$nodes = [
			[
				'ha_nodeid' => 'ckv2kclpg0001pt7pseinx5is',
				'name' => 'Standby node',
				'address' => '192.168.133.195',
				'port' => 10055,
				'lastaccess' => self::$timestamp - 1,
				'status' => 0,
				'ha_sessionid' => 'ckv6hh1730000q17pci1gocjy'
			],
			[
				'ha_nodeid' => 'ckv2kfmqj0001pipjf0g4pr20',
				'name' => 'Stopped node',
				'address' => '192.168.133.192',
				'port' => 10025,
				'lastaccess' => self::$timestamp - 240,
				'status' => 1,
				'ha_sessionid' => 'ckv6gyurt0000vfpjp7b8nad4'
			],
			[
				'ha_nodeid' => 'ckvaw8yny0001l07pm1bk14y5',
				'name' => 'Unavailable node',
				'address' => '192.168.133.206',
				'port' => 10051,
				'lastaccess' => self::$timestamp - 180105,
				'status' => 2,
				'ha_sessionid' => 'ckvaw8yie0000kr7pzk6nd5ok'
			],
			[
				'ha_nodeid' => 'ckvaw9wlf0001tn7psxgh3wfo',
				'name' => 'Active node',
				'address' => $DB['SERVER'],
				'port' => $DB['PORT'],
				'lastaccess' => self::$timestamp,
				'status' => 3,
				'ha_sessionid' => 'ckvaw9wjo0000td7p8j66e74x'

			],
			[
				'ha_nodeid' => 'ckvawe0t00001h57pcotna8nz',
				'name' => '',
				'address' => '192.168.133.100',
				'port' => 10051,
				'lastaccess' => self::$timestamp - 20,
				'status' => 0,
				'ha_sessionid' => 'ckvawe0rx0000gv7pi74mzlqp'			]
		];

		foreach ($nodes as $node) {
			DBexecute('INSERT INTO ha_node (ha_nodeid, name, address, port, lastaccess, status, ha_sessionid) '.
					'VALUES ('.zbx_dbstr($node['ha_nodeid']).', '.zbx_dbstr($node['name']).', '.zbx_dbstr($node['address']).
					', '.$node['port'].', '.$node['lastaccess'].', '.$node['status'].', '.zbx_dbstr($node['ha_sessionid']).');'
			);
		}

		$file_name = dirname(__FILE__).'/../../../conf/zabbix.conf.php';
		$config = file_get_contents($file_name);
		$config = str_replace(['$ZBX_SERVER ', '$ZBX_SERVER_PORT'], ['// $ZBX_SERVER ', '// $ZBX_SERVER_PORT'], $config);

		file_put_contents($file_name, $config);
	}

	public function testDashboardSystemInformationWidget_emptyHANopdeTable() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$this->assertScreenshot(CDashboardElement::find()->waitUntilReady()->one(), 'widget_without_ha');
	}

	public function testDashboardSystemInformationWidget_Create() {
		sleep(1);
	}

	/**
	 * @onBefore prepareHANodeData
	 */
	public function testDashboardSystemInformationWidget_populatedHANodeTable() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		// Not waiting for page to load to minimise the posibility of difference between the time in widget and in constant.
		$current_time = time();

		$this->page->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		$nodes = [
			'Active node' => self::$timestamp,
			'Unavailable node' => self::$timestamp - 180105,
			'Stopped node' => self::$timestamp - 240,
			'Standby node' => self::$timestamp - 1,
			'' => self::$timestamp - 20
		];
		$skip_fields = [];

		// Compare lasaccess as time difference for each node and exclude corresponding element from screenshot.
		$nodes_table = $dashboard->getWidget('High availability nodes view')->query('xpath:.//table')->asTable()->one();
		foreach ($nodes as $name => $lastaccess_expected) {
			$row = $nodes_table->findRow('Name', $name);
			$last_seen = $row->getColumn('Last access');
			// Converting unix timestamp difference into difference in time units and comparing with lastaccess from widget.
			$this->assertEquals(convertUnitsS($current_time - $lastaccess_expected), $last_seen->getText());

			$skip_fields[] = $last_seen;

			if ($name === 'Active node') {
				global $DB;
				$skip_fields[] = $row->getColumn('Address');
				$this->assertEquals($DB['SERVER'].':'.$DB['PORT'], $row->getColumn('Address')->getText());
			}
		}

		$this->assertScreenshotExcept($dashboard, $skip_fields, 'widget_with_ha');
	}
}

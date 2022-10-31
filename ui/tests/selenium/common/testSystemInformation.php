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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

class testSystemInformation extends CWebTest {

	const FAILOVER_DELAY = 8;

	public static $active_lastaccess;
	public static $update_timestamp;
	public static $standby_lastaccess;
	public static $stopped_lastaccess;
	public static $unavailable_lastaccess;

	public static $skip_fields;

	/**
	 * Function inserts HA cluster data into ha_node table.
	 */
	public static function prepareHANodeData() {
		global $DB;
		self::$active_lastaccess = time();
		self::$standby_lastaccess = self::$active_lastaccess - 1;
		self::$stopped_lastaccess = self::$active_lastaccess - 240;
		self::$unavailable_lastaccess = self::$active_lastaccess - 180105;

		$nodes = [
			[
				'ha_nodeid' => 'ckv2kclpg0001pt7pseinx5is',
				'name' => 'Standby node',
				'address' => '192.168.133.195',
				'port' => 10055,
				'lastaccess' => self::$standby_lastaccess,
				'status' => 0,
				'ha_sessionid' => 'ckv6hh1730000q17pci1gocjy'
			],
			[
				'ha_nodeid' => 'ckv2kfmqj0001pipjf0g4pr20',
				'name' => 'Stopped node',
				'address' => '192.168.133.192',
				'port' => 10025,
				'lastaccess' => self::$stopped_lastaccess,
				'status' => 1,
				'ha_sessionid' => 'ckv6gyurt0000vfpjp7b8nad4'
			],
			[
				'ha_nodeid' => 'ckvaw8yny0001l07pm1bk14y5',
				'name' => 'Unavailable node',
				'address' => '192.168.133.206',
				'port' => 10051,
				'lastaccess' => self::$unavailable_lastaccess,
				'status' => 2,
				'ha_sessionid' => 'ckvaw8yie0000kr7pzk6nd5ok'
			],
			[
				'ha_nodeid' => 'ckvaw9wlf0001tn7psxgh3wfo',
				'name' => 'Active node',
				'address' => $DB['SERVER'],
				'port' => 0,
				'lastaccess' => self::$active_lastaccess,
				'status' => 3,
				'ha_sessionid' => 'ckvaw9wjo0000td7p8j66e74x'
			]
		];

		// Insert HA cluster data into ha_node table.
		foreach ($nodes as $node) {
			DBexecute('INSERT INTO ha_node (ha_nodeid, name, address, port, lastaccess, status, ha_sessionid) '.
					'VALUES ('.zbx_dbstr($node['ha_nodeid']).', '.zbx_dbstr($node['name']).', '.zbx_dbstr($node['address']).
					', '.$node['port'].', '.$node['lastaccess'].', '.$node['status'].', '.zbx_dbstr($node['ha_sessionid']).');'
			);
		}

		// Update Zabbix frontend config to make sure that the address of the active node is shown correctly in tests.
		$file_name = dirname(__FILE__).'/../../../conf/zabbix.conf.php';
		$config = strtr(file_get_contents($file_name), ['$ZBX_SERVER ' => '// $ZBX_SERVER ', '$ZBX_SERVER_PORT' => '// $ZBX_SERVER_PORT']);
		file_put_contents($file_name, $config);

		// Get the time when config is updated - it is needed to know how long to wait until update of Zabbix server status.
		self::$update_timestamp = time();
	}

	// Change failover delay not to wait too long for server to update its status.
	public static function changeFailoverDelay() {
		DBexecute('UPDATE config SET ha_failover_delay='.self::FAILOVER_DELAY);
	}

	/**
	 * Function that checks how a running HA cluster info is displayed in system information widget or report.
	 *
	 * @param integer $dashboardid	id of the dashboard that the widgets are located in.
	 */
	public function assertEnabledHACluster($dashboardid = null) {
		global $DB;
		self::$skip_fields = [];
		$url = (!$dashboardid) ? 'zabbix.php?action=report.status' : 'zabbix.php?action=dashboard.view&dashboardid='.$dashboardid;
		// Wait for frontend to get the new config from updated zabbix.conf.php file.
		sleep((int) ini_get('opcache.revalidate_freq') + 1);
		$this->page->login()->open($url);

		// Not waiting for page to load to minimise the possibility of difference between the time in report and in constant.
		$current_time = time();
		$this->page->waitUntilReady();

		if (!$dashboardid) {
			$nodes_table = $this->query('xpath://table[@class="list-table sticky-header sticky-footer"]')->asTable()->one();
			$server_address = $this->query('xpath://th[text()="Zabbix server is running"]/../td[2]')->one();
		}
		else {
			$dashboard = CDashboardElement::find()->waitUntilReady()->one();
			$nodes_table = $dashboard->getWidget('High availability nodes view')->query('xpath:.//table')->asTable()->one();
			$server_address = $dashboard->getWidget('System stats view')->query('xpath:.//tbody/tr[1]/td[2]')->one();
		}

		// Define expected absolute timestamps for calculating the lastaccess value.
		$nodes = [
			'Active node' => self::$active_lastaccess,
			'Unavailable node' => self::$unavailable_lastaccess,
			'Stopped node' => self::$stopped_lastaccess,
			'Standby node' => self::$standby_lastaccess
		];

		/**
		 * The below foreach cycle compares lastaccess as time difference for each node in the widget or part of report
		 * that displays the list of nodes and excludes corresponding element from screenshot.
		 */
		foreach ($nodes as $name => $lastaccess_db) {
			$row = $nodes_table->findRow('Name', $name);
			$last_seen = $row->getColumn('Last access');
			// Converting unix timestamp difference into difference in time units and comparing with lastaccess from report.
			$lastaccess_expected = convertUnitsS($current_time - $lastaccess_db);
			$lastaccess_actual = $last_seen->getText();
			/**
			 *  In case if the actual last access doesn't coincide with expected check that the difference is only 1s.
			 *  This is required because a second might have passed from defining $current_time and loading the page.
			 */
			if ($lastaccess_expected !== $lastaccess_actual) {
				$this->assertEquals(convertUnitsS($current_time - $lastaccess_db - 1), $lastaccess_actual);
			}

			self::$skip_fields[] = $last_seen;

			// Check Zabbix server address and port for each record in the HA cluster nodes table.
			if ($name === 'Active node') {
				self::$skip_fields[] = $row->getColumn('Address');
				$this->assertEquals($DB['SERVER'].':0', $row->getColumn('Address')->getText());
			}
		}

		/**
		 * Check and hide the active Zabbix server address in widget that is working in System stats mode or in the part
		 * of the report that displays the overall system statistics.
		 */
		$this->assertEquals($DB['SERVER'].':0', $server_address->getText());
		self::$skip_fields[] = $server_address;

		// Hide the footer of the report as it contains Zabbix version.
		if (!$dashboardid) {
			self::$skip_fields[] = $this->query('xpath://footer')->one();
		}

		// Check and hide the text of messages, because they contain ip addresses of the current host.
		$error_text = "Connection to Zabbix server \"".$DB['SERVER'].":0\" failed. Possible reasons:\n".
				"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
				"2. Incorrect DNS server configuration.\n".
				"Failed to parse address \"".$DB['SERVER']."\"";
		$messages = CMessageElement::find()->all();
		foreach ($messages as $message) {
			$this->assertTrue($message->hasLine($error_text));
			self::$skip_fields[] = $message;
		}
	}

	/**
	 * Function checks that Zabbix server status is updated after failover delay passes and frontend config is re-validated.
	 *
	 * @param integer $dashboardid	id of the dashboard that the widgets are located in.
	 */
	public function assertServerStatusAfterFailover($dashboardid = null) {
		$url = (!$dashboardid) ? 'zabbix.php?action=report.status' : 'zabbix.php?action=dashboard.view&dashboardid='.$dashboardid;
		$this->page->login()->open($url)->waitUntilReady();
		$table = $this->query('xpath://table[@class="list-table sticky-header"]')->asTable()->waitUntilVisible()->one();

		// Check that before failover delay passes frontend thinks that Zabbix server is running.
		$this->assertEquals('Yes', $table->findRow('Parameter', 'Zabbix server is running')->getColumn('Value')->getText());

		// Wait for failover delay to pass.
		sleep(self::$update_timestamp + self::FAILOVER_DELAY - time());

		// Check that after failover delay passes frontend re-validates Zabbix server status.
		$this->page->refresh();
		$this->assertEquals('No', $table->findRow('Parameter', 'Zabbix server is running')->getColumn('Value')->getText());
	}
}

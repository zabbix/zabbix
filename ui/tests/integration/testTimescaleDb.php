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


require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test Timescale DB extension.
 *
 * @required-components server
 * @hosts test_timescale
 * @backup history
 */
class testTimescaleDb extends CIntegrationTest {

	const HOSTNAME = 'test_timescale';
	const TRAPNAME = 'trap_timescale';
	const HIST_COUNT = 3000;
	const COMPRESSION_OLDER_THAN = 7 * 24 * 3600; /* 7d */
	const TOLERANCE_PERIOD = 60;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
			]
		];
	}

	/**
	 * Test TimescaleDb extension.
	 */
	public function checkTimescale() {
		global $DB;
		$db_extension = '';

			$sql = 'SELECT db_extension'.
				' FROM config';

			$res = DBfetch(DBselect($sql));

			if ($res) {
				$db_extension = $res['db_extension'];
			}

			if ($db_extension  == ZBX_DB_EXTENSION_TIMESCALEDB) {
				return true;
			}

			return false;
	}

	/**
	 * Test server is up with TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkServerUp() {
		if ($this->checkTimescale()) {
			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'commit;');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {

		// Create host "test_timescale"
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => $hostid,
			'name' => self::TRAPNAME,
			'key_' => self::TRAPNAME,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		return true;
	}


	/**
	 * Get number of records in history_uint table.
	 */
	public function getHistoryCount() {
		global $DB;
		
		$sql = 'SELECT count(*)'.
			' FROM history_uint';

			$res = DBfetch(DBselect($sql));

			if ($res) {
				return $res['count'];
			}

			return -1;
	}

/**
	 * Test history table TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkHistoryRecords() {
		if ($this->checkTimescale()) {
			$this->reloadConfigurationCache();

			$count_start = $this->getHistoryCount();
			$this->assertNotEquals(-1, $count_start);

			$c = time() - self::COMPRESSION_OLDER_THAN + self ::TOLERANCE_PERIOD;
			$n = 1;
			for ($i = 0; $i < self::HIST_COUNT; $i++) {
				$sender_data[$i] = ['value' => $c, 'clock' => $c, 'ns' => $n, 'host' => self::HOSTNAME, 'key' => self::TRAPNAME];
				$n += 10;
				////$c = $c + $n;
			}
			$this->sendDataValues('sender', $sender_data , self::COMPONENT_SERVER);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'trapper got');
			sleep(self ::TOLERANCE_PERIOD);
			$count_end = $this->getHistoryCount();
			$this->assertEquals($count_end - $count_start, self::HIST_COUNT);
		}
	}
}

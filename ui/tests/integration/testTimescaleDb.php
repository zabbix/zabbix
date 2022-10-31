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
	const TABLENAME = 'history_uint';
	const HIST_COUNT = 3000;
	/*
		storing old data deep in the past - 20 days, which is way longer that the minimum 7days,
		and must be guaranteed to be compressed
	*/
	const COMPRESSION_OLDER_THAN = 20 * 24 * 3600;
	static $db_extension = '';
	private static $itemid;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0
			]
		];
	}

	private function retrieveExtention() {
		self::$db_extension = '';

		$sql = 'SELECT db_extension'.
			' FROM config';

		$res = DBfetch(DBselect($sql));

		if ($res) {
			self::$db_extension = $res['db_extension'];
		}
	}

	/**
	 * Test TimescaleDb extension.
	 */
	private function clearChunks() {
		$sql = 'SELECT drop_chunks(\''.self::TABLENAME.'\', older_than => '.time().')';

		$res = DBfetch(DBselect($sql));
	}

	/**
	 * Test server is up with TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkServerUp() {
		$this->assertEquals(self::$db_extension, ZBX_DB_EXTENSION_TIMESCALEDB);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'TimescaleDB version:');
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareData() {

		$this->retrieveExtention();

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
				'status' => HOST_STATUS_NOT_MONITORED
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
		self::$itemid = $response['result']['itemids'][0];

		$response = $this->call('housekeeping.update',
			['compression_status' => 0]
		);
		$this->assertArrayHasKey(0, $response['result']);

		$this->clearChunks();

		return true;
	}

	/**
	 * Get number of records in history_uint table.
	 */
	public function getHistoryCount() {
		$res = DBfetch(DBselect('SELECT count(*) FROM '.self::TABLENAME.' WHERE itemid = '.self::$itemid));

		if ($res) {
			return $res['count'];
		}

		return -1;
	}

	/**
	 * Check compression of the chunk.
	 */
	public function getCheckCompression() {
		$req = DBselect('SELECT number_compressed_chunks FROM hypertable_compression_stats(\''.self::TABLENAME.'\')');
		$compress = DBfetch($req);
		$this->assertArrayHasKey('number_compressed_chunks', $compress);
		if ($compress['number_compressed_chunks'] == 0) {

			$res = DBfetch(DBselect('SELECT show_chunks(\''.self::TABLENAME.'\')'));
			$this->assertArrayHasKey('show_chunks', $res);

			$chunk = $res['show_chunks'];
			$res_compr = DBfetch(DBselect('SELECT compress_chunk(\''.$chunk.'\')'));
			$this->assertArrayHasKey('compress_chunk', $res_compr);

			$res2 = DBfetch(DBselect('
				SELECT number_compressed_chunks FROM hypertable_compression_stats(\''.self::TABLENAME.'\')'));
			$this->assertArrayHasKey('number_compressed_chunks', $res2);
			$this->assertEquals($res2['number_compressed_chunks'], count($res));
			$res_compr = DBfetch(DBselect('SELECT decompress_chunk(\''.$chunk.'\')'));
			$this->assertArrayHasKey('decompress_chunk', $res_compr);
		}
	}

	/**
	 * Test history table TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkHistoryRecords() {
		$this->assertEquals(self::$db_extension, ZBX_DB_EXTENSION_TIMESCALEDB);

		$this->reloadConfigurationCache();

		$count_start = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_start);

		$c = time() - self::COMPRESSION_OLDER_THAN;
		$n = 1;
		for ($i = 0; $i < self::HIST_COUNT; $i++) {
			$sender_data[$i] = ['value' => $c, 'clock' => $c, 'ns' => $n, 'host' => self::HOSTNAME,
				'key' => self::TRAPNAME];
			$n += 10;
		}
		$this->sendDataValues('sender', $sender_data , self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'trapper got');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_send_response_ext():SUCCEED', true, 5);
		$this->reloadConfigurationCache();
		sleep(1);

		$count_end = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_end);
		$this->assertEquals($count_end - $count_start, self::HIST_COUNT);

		$response = $this->call('housekeeping.update',
			['compression_status' => 1]
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('compression_status', $response['result'][0]);
		$this->reloadConfigurationCache();
		$this->executeHousekeeper();
	}

	/**
	 * Test compression TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkCompression() {
		$this->assertEquals(self::$db_extension, ZBX_DB_EXTENSION_TIMESCALEDB);

		$this->executeHousekeeper();

		$this->getCheckCompression();
	}
}

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
	private static $db_extension = null;
	private static $itemid;
	private static $tsdbVersion = null;

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

	private static function getDBExtension() {
		if (self::$db_extension == null) {
			$res = DBfetch(DBselect('SELECT db_extension FROM config'));
			if ($res)
				self::$db_extension = $res['db_extension'];
		}

		return self::$db_extension;
	}

	/**
	 * Clear all chunks in the table under test.
	 */
	private function clearChunks() {
		/* The interval is selected like so to make sure all chunks are deleted. */
		$sql = "SELECT drop_chunks('".self::TABLENAME."', created_before => now() + interval '10 years')";
		DBexecute($sql);
	}

	/**
	 * Get TimescaleDB version. For example, version "2.19.5" equals to 21905.
	 *
	 * @return int
	 */
	private static function getTimescaleDBVersion() {
		if (self::$tsdbVersion == null) {
			$sql = "SELECT extversion FROM pg_extension WHERE extname='timescaledb';";

			$res = DBfetch(DBselect($sql));

			if ($res) {
				list($major, $minor, $patch) = explode('.', $res['extversion']);

				$ver = $major * 10000;
				$ver += $minor * 100;
				$ver += $patch;

				self::$tsdbVersion = $ver;
			}
		}

		return self::$tsdbVersion;
	}

	/**
	 * Test server is up with TimescaleDb.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkServerUp() {
		$db_ext = self::getDBExtension();
		$this->assertNotNull($db_ext, "Failed to retrieve database extension");
		$this->assertEquals(ZBX_DB_EXTENSION_TIMESCALEDB, $db_ext, "TimescaleDB extension is not available");

		$timescale_ver = $this->getTimescaleDBVersion();
		$this->assertNotNull($timescale_ver, "Failed to get a valid TimescaleDB version");
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
				sprintf("TimescaleDB version: [%d]", $timescale_ver));
	}

	/**
	 * {@inheritDoc}
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
	 * Check compression of the chunk. Deprecated since TimescaleDB 2.18.
	 */
	public function getCheckCompression() {
		$req = DBselect('SELECT number_compressed_chunks FROM hypertable_compression_stats(\''.self::TABLENAME.'\')');
		$compress = DBfetch($req);
		$this->assertNotEquals($compress, null);
		$this->assertArrayHasKey('number_compressed_chunks', $compress, json_encode($compress));
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
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_send_response_json():SUCCEED', true, 5);

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);

		$count_end = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_end);
		$this->assertEquals($count_end - $count_start, self::HIST_COUNT);

		$response = $this->call('housekeeping.update',
			['compression_status' => 1]
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('compression_status', $response['result'][0]);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->executeHousekeeper();

		$this->onAfterCheckHistoryRecords();
	}

	private function onAfterCheckHistoryRecords() {
		/* There was no cleanup in the legacy test for TimescaleDB older than 2.18. */
		if ($this->getTimescaleDBVersion() < 21800)
			return;

		/* Turn off compression policy and clear all chunks for the next tests. */
		$response = $this->call('housekeeping.update',
			['compression_status' => 0] // off
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('compression_status', $response['result'][0]);

		$this->clearChunks();
	}

	/**
	 * Test compression of specific chunks by TimescaleDB.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_checkCompression() {
		/* The legacy test for TimescaleDB older than 2.18 */
		if ($this->getTimescaleDBVersion() < 21800) {
			$this->executeHousekeeper();

			$this->stopComponent(self::COMPONENT_SERVER);
			$this->startComponent(self::COMPONENT_SERVER);
			$this->getCheckCompression();

			return;
		}

		/* The current test for TimescaleDB 2.18 and newer */

		/* Get the number of records before inserting test data */
		$count_start = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_start);

		/* Add history data so that TimescaleDB creates a few chunks for the test. */
		$input = [
			/* start clock, value count */
			[time() - self::COMPRESSION_OLDER_THAN - 1 * 24 * 3600, 100],
			[time() - self::COMPRESSION_OLDER_THAN - 2 * 24 * 3600, 100],
			[time() - self::COMPRESSION_OLDER_THAN - 3 * 24 * 3600, 100]
		];

		$sender_data = [];

		foreach ($input as $item) {
			$clock = $item[0];
			$cnt = $item[1];

			for ($i = 0, $ns = 0; $i < $cnt; $i++, $ns += 10, $clock++) {
				$sender_data[] = [
					'value' => $clock,
					'clock' => $clock,
					'ns' => $ns,
					'host' => self::HOSTNAME,
					'key' => self::TRAPNAME
				];
			}
		}

		$this->sendDataValues('sender', $sender_data , self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'trapper got');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_send_response_json():SUCCEED', true, 5);

		/* Make sure all data was inserted as intended. */
		$count_end = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_end);
		$this->assertEquals(count($sender_data), $count_end - $count_start);

		/* There should be no compressed chunks at this stage yet. */
		/* It is called hypertable columnstore stats since TimescaleDB 2.18. */
		$sql = "SELECT number_compressed_chunks FROM hypertable_columnstore_stats('".self::TABLENAME."')";
		$res = DBfetch(DBselect($sql));
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$number_compressed_chunks0 = $res['number_compressed_chunks'];
		$this->assertEquals(0, $number_compressed_chunks0,
				"It is expected that there are no compressed chunks in the beginning.");

		/* Get all chunk names. */
		$res = DBfetchArray(DBselect("SELECT show_chunks('".self::TABLENAME."')"));
		$chunks = array_column($res, 'show_chunks');
		$total_chunks = count($chunks);
		$this->assertGreaterThan(0, $total_chunks);

		/* Compress specific chunks by names without using a compression policy. */
		/* It is called convert to columnstore since TimescaleDB 2.18. */
		foreach ($chunks as $chunk)
			DBexecute("CALL convert_to_columnstore('".$chunk."')");

		/* All chunks are expected to be compressed. */
		$res = DBfetch(DBselect($sql));
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$number_compressed_chunks1 = $res['number_compressed_chunks'];
		$this->assertEquals($total_chunks, $number_compressed_chunks1, "Not all chunks were compressed");

		/* Decompress specific chunks by names. */
		/* It is called convert to rowstore since TimescaleDB 2.18. */
		foreach ($chunks as $chunk)
			DBexecute("CALL convert_to_rowstore('".$chunk."')");

		/* All chunks are expected to be decompressed now. */
		$res = DBfetch(DBselect($sql));
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$number_compressed_chunks2 = $res['number_compressed_chunks'];
		$this->assertEquals(0, $number_compressed_chunks2, "Not all chunks were decompressed");
	}
}

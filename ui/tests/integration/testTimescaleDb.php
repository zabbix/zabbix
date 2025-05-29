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
	/*
		storing old data deep in the past - 20 days, which is way longer that the minimum 7days,
		and must be guaranteed to be compressed
	*/
	const COMPRESSION_OLDER_THAN = 20 * 24 * 3600;
	private static $db_extension = null;
	private static $itemid;
	private static $tsdbVersion = null;
	private static $dbChunkTimeInterval = null;

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
	 * Gets chunk time interval, which is chunk size in time units in other words.
	 */
	private static function getChunkTimeInterval() {
		if (self::$dbChunkTimeInterval == null) {
			$sql = "select integer_interval
					from timescaledb_information.dimensions
					where hypertable_name='".self::TABLENAME."'";

			if ($res = DBfetch(DBselect($sql)))
				self::$dbChunkTimeInterval = intval($res['integer_interval']);
		}

		return self::$dbChunkTimeInterval;
	}

	/**
	 * Clears all chunks in the table under test.
	 */
	private function clearChunks() {
		/* The interval is selected like so to make sure all chunks are deleted. */
		$sql = "SELECT drop_chunks('".self::TABLENAME."', created_before => now() + interval '10 years')";
		DBexecute($sql);
	}

	/**
	 * Generates some mock historical data and sends it using trapper so that TimescaleDB could generate
	 * chunks in the past.
	 */
	private function generateHistoryData() {
		$count_start = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_start);

		/* data for 3 chunks in the past old enough to be compressed */
		$input = [
			/* start clock, value count */
			[time() - self::COMPRESSION_OLDER_THAN - 1 * self::getChunkTimeInterval(), 100],
			[time() - self::COMPRESSION_OLDER_THAN - 2 * self::getChunkTimeInterval(), 100],
			[time() - self::COMPRESSION_OLDER_THAN - 3 * self::getChunkTimeInterval(), 100]
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

		$count_end = $this->getHistoryCount();
		$this->assertNotEquals(-1, $count_end);

		/* make sure all data was inserted as intended */
		$this->assertEquals(count($sender_data), $count_end - $count_start,
				"Failed to insert test all expected history data");
	}

	/**
	 * Gets TimescaleDB version in integer format. For example, version "2.19.5" equals to 21905.
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
				sprintf("TimescaleDB version: [%d]", $timescale_ver),
				"Expected TimescaleDB version was not found in Zabbix server log");
	}

	/**
	 * Gets number of records in history table under test.
	 */
	public function getHistoryCount() {
		$res = DBfetch(DBselect('SELECT count(*) FROM '.self::TABLENAME.' WHERE itemid = '.self::$itemid));

		if ($res) {
			return $res['count'];
		}

		return -1;
	}

	/**
	 * Test TimescaleDB compression policy.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testTimescaleDb_compressionPolicy() {
		$this->generateHistoryData();

		$response = $this->call('housekeeping.update',
			['compression_status' => 1]
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('compression_status', $response['result'][0]);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		/* get compression job id */
		$sql = "select job_id
			from timescaledb_information.jobs
			where proc_name='policy_compression' and hypertable_name='".self::TABLENAME."'";

		$res = DBfetch(DBselect($sql));
		$this->assertArrayHasKey('job_id', $res);
		$job_id = $res['job_id'];

		/* force running the compression job, which is normally run on schedule */
		DBexecute("call run_job(".$job_id.")");

		/* get the number of chunks */
		if ($this->getTimescaleDBVersion() >= 21800) {
			/* hypertable_columnstore_stats is available since TimescaleDB 2.18. */
			$sql = "SELECT total_chunks, number_compressed_chunks
					FROM hypertable_columnstore_stats('".self::TABLENAME."')";
		} else {
			/* hypertable_compression_stats is deprecated since TimescaleDB 2.18. */
			$sql = "SELECT total_chunks, number_compressed_chunks
					FROM hypertable_compression_stats('".self::TABLENAME."')";

		}
		$res = DBfetch(DBselect($sql));
		$this->assertArrayHasKey('total_chunks', $res);
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$total_chunks = $res['total_chunks'];
		$num_compressed_chunks = $res['number_compressed_chunks'];

		/* All of the chunks old enough for compression are expected to be compressed. */
		/* One current chunk cannot be compressed. */
		$this->assertEquals($total_chunks -1, $num_compressed_chunks, "Not all chunks were compressed");

		/* cleanup: turn off compression policy and clear all chunks for the next tests */
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
	public function testTimescaleDb_compressionOfSpecificChunks() {
		$this->generateHistoryData();

		/* There should be no compressed chunks at this stage yet. */
		if ($this->getTimescaleDBVersion() >= 21800) {
			/* hypertable_columnstore_stats is available since TimescaleDB 2.18. */
			$sql_num_compressed = "SELECT number_compressed_chunks
					FROM hypertable_columnstore_stats('".self::TABLENAME."')";
		} else {
			/* hypertable_compression_stats is deprecated since TimescaleDB 2.18. */
			$sql_num_compressed = "SELECT number_compressed_chunks
					FROM hypertable_compression_stats('".self::TABLENAME."')";
		}

		$res = DBfetch(DBselect($sql_num_compressed));
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
		foreach ($chunks as $chunk) {
			if ($this->getTimescaleDBVersion() >= 21800) {
				/* convert_to_columnstore is available since TimescaleDB 2.18. */
				$this->assertTrue(DBexecute("CALL convert_to_columnstore('".$chunk."')"));
			} else {
				/* compress_chunk is deprecated since TimescaleDB 2.18. */
				$res = DBfetch(DBselect("SELECT compress_chunk('".$chunk."')"));
				$this->assertArrayHasKey('compress_chunk', $res);
			}
		}

		/* All chunks are expected to be compressed. */
		$res = DBfetch(DBselect($sql_num_compressed));
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$number_compressed_chunks1 = $res['number_compressed_chunks'];
		$this->assertEquals($total_chunks, $number_compressed_chunks1, "Not all chunks were compressed");

		/* Decompress specific chunks by names. */
		/* It is called convert to rowstore since TimescaleDB 2.18. */
		foreach ($chunks as $chunk) {
			if ($this->getTimescaleDBVersion() >= 21800) {
				/* convert_to_rowstore is available since TimescaleDB 2.18. */
				$this->assertTrue(DBexecute("CALL convert_to_rowstore('".$chunk."')"));
			} else {
				/* decompress_chunk is deprecated since TimescaleDB 2.18. */
				$res = DBfetch(DBselect("SELECT decompress_chunk('".$chunk."')"));
				$this->assertArrayHasKey('decompress_chunk', $res);
			}
		}

		/* All chunks are expected to be decompressed now. */
		$res = DBfetch(DBselect($sql_num_compressed));
		$this->assertArrayHasKey('number_compressed_chunks', $res);
		$number_compressed_chunks2 = $res['number_compressed_chunks'];
		$this->assertEquals(0, $number_compressed_chunks2, "Not all chunks were decompressed");
	}
}

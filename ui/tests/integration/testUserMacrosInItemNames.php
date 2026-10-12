<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Test suite for macro expansion in item names and exported metadata.
 *
 * @required-components server
 * @backup hosts,globalmacro
 */
class testUserMacrosInItemNames extends CIntegrationTest {
	/** Maximum time in seconds to wait for NDJSON export files to appear. */
	private const WAIT_EXPORT_FILE_TIMEOUT = 30;
	/** Polling interval in microseconds. */
	private const WAIT_EXPORT_FILE_INTERVAL = 100000;
	private const HOSTNAME1 = 'test_user_macros_in_item_names1';
	private const HOSTNAME2 = 'test_user_macros_in_item_names2';
	private const HOSTNAME_EXPORT = 'test_ndjson_export_macros';
	private const DEFAULT_TRAPPER_ALLOWED_HOSTS = '0.0.0.0/0,::/0';

	private static $hostid1;
	private static $hostid2;
	private static $macroid;
	private static $hostid_export;
	private static $itemid_export;
	private static $export_dir = null;

	private static function getExportDir(): string {
		if (self::$export_dir === null) {
			self::$export_dir = sys_get_temp_dir()
				. '/zabbix_export_test_'
				. str_replace('.', '', (string) microtime(true));
		}

		return self::$export_dir;
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME1,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid1 = $response['result']['hostids'][0];

		// Ensure the global macro for trapper allowed hosts exists before creating any trapper items.
		$this->ensureTrapperAllowedHostsMacro();

		$response = $this->call('item.create', [
			'hostid' => self::$hostid1,
			'name' => 'Item {$TEST}',
			'key_' => 'item1',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid1,
			'name' => 'Trapper discovery',
			'key_' => 'item_discovery',
			'type' => ITEM_TYPE_TRAPPER,
			'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$ruleid = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'hostid' => self::$hostid1,
			'ruleid' => $ruleid,
			'name' => 'LLD {$TEST} {#KEY}',
			'key_' => 'trap[{#KEY}]',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$TEST}',
			'value' => 'tst'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		self::$macroid = $response['result']['globalmacroids'][0];

		$tmpl = [
			"zabbix_export" => [
				"version" => "7.0",
				"template_groups" => [
					[
						"uuid" => "7df96b18c230490a9a0a9e2307226338",
						"name" => "Templates"
					]
				],
				"templates" => [
					[
						"uuid" => "1e3947441cdf40ebb6c6f3335d2fcdbc",
						"template" => "Um1",
						"name" => "Um1",
						"groups" => [
							["name" => "Templates"]
						],
						"items" => [
							[
								"uuid" => "c8df8ff7fb15476cb2ecea02cadc447a",
								"name" => 'Template item {$TEST}',
								"type" => "TRAP",
								"key" => "tmpl.item",
								"delay" => "0"
							]
						]
					]
				]
			]
		];

		$response = $this->call('configuration.import', [
			'format' => 'json',
			'source' => json_encode($tmpl),
			'rules' => [
				'template_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'host_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'templates' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'valueMaps' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templateDashboards' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templateLinkage' =>
				[
					'createMissing' => true,
					'deleteMissing' => false
				],
				'items' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'discoveryRules' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'triggers' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'graphs' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'httptests' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				]
			]
		]);

		$response = $this->callUntilDataIsPresent('template.get', [
			'output' => ['templateid'],
			'filter' => [
				'name' => 'Um1'
			]
		], 10, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('templateid', $response['result'][0]);
		$templateid = $response['result'][0]['templateid'];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME2,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'templates' => [['templateid' => $templateid]]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid2 = $response['result']['hostids'][0];

		// Clean up stale NDJSON data from a previous run before creating new data.
		$this->cleanupNdjsonExportFiles();

		// Create host with host macros and trapper item with tag for NDJSON export test.
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME_EXPORT,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'type' => 'server'
				],
				'macros' => [
					['macro' => '{$ENV}', 'value' => 'prod'],
					['macro' => '{$FILE_NAME}', 'value' => 'config.json'],
					[
						'macro' => '{$SECRET_EXPORT}',
						'value' => 'zbx-secret-export-test-value',
						'type' => ZBX_MACRO_TYPE_SECRET
					]
				]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_export = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid_export,
			'name' => 'File {$FILE_NAME} exists',
			'key_' => 'macro.export.test',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}',
			'tags' => [
				['tag' => 'env', 'value' => '{$ENV}'],
				['tag' => 'env-{$ENV}', 'value' => 'value-{$ENV}'],
				['tag' => 'host_{HOST.HOST}', 'value' => 'type_{INVENTORY.TYPE}'],
				['tag' => 'inventory_{INVENTORY.TYPE}', 'value' => 'host_{HOST.HOST}'],
				['tag' => 'secret', 'value' => '{$SECRET_EXPORT}']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$itemid_export = $response['result']['itemids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'Macro export trigger for ' . self::HOSTNAME_EXPORT,
			'expression' => 'last(/' . self::HOSTNAME_EXPORT . '/macro.export.test)>5',
			'tags' => [
				['tag' => 'env', 'value' => '{$ENV}'],
				['tag' => 'event-{$ENV}', 'value' => 'problem-{$ENV}']
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		return true;
	}

	public function serverConfigurationProvider() {
		$export_dir = self::getExportDir();

		if (!is_dir($export_dir)) {
			if (!mkdir($export_dir, 0777, true) && !is_dir($export_dir)) {
				throw new Exception('Failed to create export directory: ' . $export_dir);
			}
		}

		if (!chmod($export_dir, 0777)) {
			throw new Exception('Failed to set permissions on export directory: ' . $export_dir);
		}

		return [
			self::COMPONENT_SERVER => [
				'ExportDir' => $export_dir,
				'ExportType' => 'events,history,trends'
			]
		];
	}

	/**
	 * Clean up the temporary export directory after all tests.
	 */
	public static function tearDownAfterClass(): void {
		$export_dir = self::getExportDir();

		if (is_dir($export_dir)) {
			$files = glob($export_dir . '/*.ndjson');
			if (is_array($files)) {
				foreach ($files as $file) {
					@unlink($file);
				}
			}

			@rmdir($export_dir);
		}
	}

	/**
	 * Check macro resolution in exported item names and tags for history,
	 * trends and problem events.
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testUserMacrosInItemNames_ndjsonExport() {
		$export_dir = self::getExportDir();
		$now = time();
		$prev_hour = $now - 3600;

		try {
			$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
			$this->cleanupNdjsonExportFiles();
			// Send values one hour apart to flush trends and create a problem event.
			$this->sendSenderValues([
				['host' => self::HOSTNAME_EXPORT, 'key' => 'macro.export.test', 'value' => '3',
					'clock' => $prev_hour
				],
				['host' => self::HOSTNAME_EXPORT, 'key' => 'macro.export.test', 'value' => '127',
					'clock' => $now
				]
			]);
			// Verify history NDJSON - first record (value=3, clock=$prev_hour).
			$history_record_1 = $this->waitForExportRecordByItemidAndClock(
				$export_dir,
				'history',
				self::$itemid_export,
				$prev_hour
			);
			$this->assertExportNameResolvedFromRecord($history_record_1, 'File config.json exists');
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_1,
				'item_tags',
				'env',
				'prod',
				['{$ENV}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_1,
				'item_tags',
				'env-prod',
				'value-prod',
				['{$ENV}']
			);
			// Verify built-in macros in history NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_1,
				'item_tags',
				'host_' . self::HOSTNAME_EXPORT,
				'type_server',
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_1,
				'item_tags',
				'inventory_server',
				'host_' . self::HOSTNAME_EXPORT,
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			// Verify secret macro in history NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_1,
				'item_tags',
				'secret',
				'******',
				['{$SECRET_EXPORT}']
			);

			// Verify history NDJSON - second record.
			$history_record_2 = $this->waitForExportRecordByItemidAndClock(
				$export_dir,
				'history',
				self::$itemid_export,
				$now
			);
			$this->assertExportNameResolvedFromRecord($history_record_2, 'File config.json exists');
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_2,
				'item_tags',
				'env',
				'prod',
				['{$ENV}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_2,
				'item_tags',
				'env-prod',
				'value-prod',
				['{$ENV}']
			);
			// Verify built-in macros in history NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_2,
				'item_tags',
				'host_' . self::HOSTNAME_EXPORT,
				'type_server',
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_2,
				'item_tags',
				'inventory_server',
				'host_' . self::HOSTNAME_EXPORT,
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			// Verify secret macro in history NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$history_record_2,
				'item_tags',
				'secret',
				'******',
				['{$SECRET_EXPORT}']
			);

			// Verify trends NDJSON.
			$trends_record = $this->waitForExportRecordByItemid($export_dir, 'trends',
					self::$itemid_export);
			$this->assertExportNameResolvedFromRecord($trends_record, 'File config.json exists');
			$this->assertExportTagPairResolvedFromRecord(
				$trends_record,
				'item_tags',
				'env',
				'prod',
				['{$ENV}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$trends_record,
				'item_tags',
				'env-prod',
				'value-prod',
				['{$ENV}']
			);
			// Verify built-in macros in trends NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$trends_record,
				'item_tags',
				'host_' . self::HOSTNAME_EXPORT,
				'type_server',
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			$this->assertExportTagPairResolvedFromRecord(
				$trends_record,
				'item_tags',
				'inventory_server',
				'host_' . self::HOSTNAME_EXPORT,
				['{HOST.HOST}', '{INVENTORY.TYPE}']
			);
			// Verify secret macro in trends NDJSON.
			$this->assertExportTagPairResolvedFromRecord(
				$trends_record,
				'item_tags',
				'secret',
				'******',
				['{$SECRET_EXPORT}']
			);
			// Verify event NDJSON.
			$event_name = 'Macro export trigger for ' . self::HOSTNAME_EXPORT;
			$problem_record = $this->waitForProblemEventByName($export_dir, $event_name);
			$this->assertEventTagResolvedFromRecord($problem_record, 'env', 'prod', '{$ENV}');
			$this->assertEventTagResolvedFromRecord($problem_record, 'event-prod',
					'problem-prod', '{$ENV}');
		}
		finally {
			$this->cleanupNdjsonExportFiles();
		}
	}

	private function cleanupNdjsonExportFiles(): void {
		$export_dir = self::getExportDir();

		$files = glob($export_dir . '/*.ndjson');
		if (is_array($files)) {
			foreach ($files as $file) {
				@unlink($file);
			}
		}
	}

	private function ensureTrapperAllowedHostsMacro(): void {
		$allowed_hosts = $this->call('usermacro.get', [
			'globalmacro' => true,
			'filter' => ['macro' => '{$TRAPPER.ALLOWED_HOSTS}'],
			'output' => ['globalmacroid', 'value']
		]);

		$this->assertArrayHasKey('result', $allowed_hosts);

		if ($allowed_hosts['result']) {
			$this->assertArrayHasKey(0, $allowed_hosts['result']);
			$this->assertArrayHasKey('globalmacroid', $allowed_hosts['result'][0]);
			$this->assertArrayHasKey('value', $allowed_hosts['result'][0]);

			if ($allowed_hosts['result'][0]['value'] !== self::DEFAULT_TRAPPER_ALLOWED_HOSTS) {
				$response = $this->call('usermacro.updateglobal', [
					'globalmacroid' => $allowed_hosts['result'][0]['globalmacroid'],
					'macro' => '{$TRAPPER.ALLOWED_HOSTS}',
					'value' => self::DEFAULT_TRAPPER_ALLOWED_HOSTS
				]);

				$this->assertArrayHasKey('result', $response);
				$this->assertArrayHasKey('globalmacroids', $response['result']);
				$this->assertArrayHasKey(0, $response['result']['globalmacroids']);
			}

			return;
		}

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$TRAPPER.ALLOWED_HOSTS}',
			'value' => self::DEFAULT_TRAPPER_ALLOWED_HOSTS
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['globalmacroids']);
	}

	/**
	 * Wait until an NDJSON record with the expected item ID and clock appears in the export files.
	 *
	 * @param string $dir     Export directory.
	 * @param string $type    Export type, for example, `history` or `trends`.
	 * @param mixed  $itemid  Expected item ID.
	 * @param int    $clock   Expected Unix timestamp.
	 *
	 * @return array Decoded NDJSON record.
	 */
	private function waitForExportRecordByItemidAndClock(string $dir, string $type, $itemid, int $clock): array {
		$deadline = microtime(true) + self::WAIT_EXPORT_FILE_TIMEOUT;

		do {
			$files = glob($dir . '/' . $type . '*.ndjson');
			if (!is_array($files)) {
				usleep(self::WAIT_EXPORT_FILE_INTERVAL);
				continue;
			}

			foreach ($files as $file) {
				$content = file_get_contents($file);

				if ($content === false || $content === '') {
					continue;
				}

				$lines = array_values(array_filter(explode("\n", $content), 'strlen'));
				$content_ends_with_newline = (strlen($content) > 0 &&
						$content[strlen($content) - 1] === "\n");

				foreach ($lines as $index => $line) {
					$data = json_decode($line, true);

					if (JSON_ERROR_NONE !== json_last_error()) {
						// Only skip the last line if it's an incomplete fragment.
						// All other JSON errors indicate malformed data and should fail.
						$is_last_line = ($index === count($lines) - 1);
						if ($is_last_line && !$content_ends_with_newline &&
								json_last_error() === JSON_ERROR_SYNTAX) {
							continue;
						}
						$this->fail('Invalid JSON in ' . $type . ' NDJSON file "'
							. $file . '": '
							. json_last_error_msg() . '. Line: ' . $line);
					}

					if (!is_array($data) || !isset($data['itemid'], $data['clock'])) {
						continue;
					}

					if ((string)$data['itemid'] === (string)$itemid &&
							(int)$data['clock'] === $clock) {
						return $data;
					}
				}
			}

			usleep(self::WAIT_EXPORT_FILE_INTERVAL);
		}
		while (microtime(true) < $deadline);

		$this->fail('Export record for itemid=' . $itemid . ' clock=' . $clock . ' was not found in '
			. $type . '*.ndjson under ' . $dir . ' within '
			. self::WAIT_EXPORT_FILE_TIMEOUT . ' seconds.');
	}

	/**
	 * Wait until an NDJSON record with the expected itemid appears in the export files.
	 * Returns the first matching decoded record data.
	 *
	 * @param string $dir     Export directory
	 * @param string $type    Export type (e.g., 'history', 'trends')
	 * @param mixed  $itemid  Expected itemid
	 * @return array          Decoded record data
	 */
	private function waitForExportRecordByItemid(string $dir, string $type, $itemid): array {
		$deadline = microtime(true) + self::WAIT_EXPORT_FILE_TIMEOUT;

		do {
			$files = glob($dir . '/' . $type . '*.ndjson');
			if (!is_array($files)) {
				usleep(self::WAIT_EXPORT_FILE_INTERVAL);
				continue;
			}

			foreach ($files as $file) {
				$content = file_get_contents($file);

				if ($content === false || $content === '') {
					continue;
				}

				$lines = array_values(array_filter(explode("\n", $content), 'strlen'));
				$content_ends_with_newline = (strlen($content) > 0 &&
						$content[strlen($content) - 1] === "\n");

				foreach ($lines as $index => $line) {
					$data = json_decode($line, true);

					if (JSON_ERROR_NONE !== json_last_error()) {
						// Only skip the last line if it's an incomplete fragment.
						// All other JSON errors indicate malformed data and should fail.
						$is_last_line = ($index === count($lines) - 1);
						if ($is_last_line && !$content_ends_with_newline &&
								json_last_error() === JSON_ERROR_SYNTAX) {
							continue;
						}
						$this->fail('Invalid JSON in ' . $type
							. ' NDJSON file "' . $file . '": '
							. json_last_error_msg() . '. Line: ' . $line);
					}

					if (!is_array($data) || !isset($data['itemid'])) {
						continue;
					}

					if ((string)$data['itemid'] === (string)$itemid) {
						return $data;
					}
				}
			}

			usleep(self::WAIT_EXPORT_FILE_INTERVAL);
		}
		while (microtime(true) < $deadline);

		$this->fail('Export record for itemid=' . $itemid . ' was not found in '
			. $type . '*.ndjson under ' . $dir . ' within '
			. self::WAIT_EXPORT_FILE_TIMEOUT . ' seconds.');
	}

	/**
	 * Wait until a problem event with the expected name appears in the export files.
	 * Returns the decoded problem event record.
	 *
	 * @param string $dir        Export directory
	 * @param string $event_name Expected problem event name
	 * @return array             Decoded problem event record
	 */
	private function waitForProblemEventByName(string $dir, string $event_name): array {
		$deadline = microtime(true) + self::WAIT_EXPORT_FILE_TIMEOUT;

		do {
			$files = glob($dir . '/problems*.ndjson');
			if (!is_array($files)) {
				usleep(self::WAIT_EXPORT_FILE_INTERVAL);
				continue;
			}

			foreach ($files as $file) {
				$content = file_get_contents($file);

				if ($content === false || $content === '') {
					continue;
				}

				$lines = array_values(array_filter(explode("\n", $content), 'strlen'));
				$content_ends_with_newline = (strlen($content) > 0 &&
						$content[strlen($content) - 1] === "\n");

				foreach ($lines as $index => $line) {
					$data = json_decode($line, true);

					if (JSON_ERROR_NONE !== json_last_error()) {
						// Only skip the last line if it's an incomplete fragment.
						// All other JSON errors indicate malformed data and should fail.
						$is_last_line = ($index === count($lines) - 1);
						if ($is_last_line && !$content_ends_with_newline &&
								json_last_error() === JSON_ERROR_SYNTAX) {
							continue;
						}
						$this->fail('Invalid JSON in problems NDJSON file "' . $file . '": '
							. json_last_error_msg() . '. Line: ' . $line);
					}

					if (!is_array($data) || !isset($data['name'])) {
						continue;
					}

					if ($data['name'] === $event_name) {
						return $data;
					}
				}
			}

			usleep(self::WAIT_EXPORT_FILE_INTERVAL);
		}
		while (microtime(true) < $deadline);

		$this->fail(
			'Problem event "' . $event_name
			. '" was not found in problems*.ndjson under '
			. $dir . ' within '
			. self::WAIT_EXPORT_FILE_TIMEOUT . ' seconds.'
		);
	}

	/**
	 * Assert that a tag in a specific decoded problem event record is resolved correctly.
	 *
	 * @param array  $data              Decoded problem event record
	 * @param string $tag_name          Expected tag name
	 * @param string $expected_value    Expected tag value
	 * @param string $unresolved_macro  Macro that should not appear in the resolved tag
	 */
	private function assertEventTagResolvedFromRecord(array $data, string $tag_name, string $expected_value,
			string $unresolved_macro): void {
		$this->assertArrayHasKey('tags', $data, 'Problem event record: "tags" field is missing.');
		$this->assertIsArray($data['tags'], 'Problem event record: "tags" must be an array.');

		$tag_found = false;

		foreach ($data['tags'] as $tag) {
			$this->assertIsArray(
				$tag,
				'Problem event record: each "tags" entry must be an array.'
			);

			if (!isset($tag['tag'], $tag['value'])) {
				continue;
			}

			if ($tag['tag'] !== $tag_name) {
				continue;
			}

			$tag_found = true;
			$this->assertSame($expected_value, $tag['value'],
				'Problem event record: tag "' . $tag_name . '" should be resolved to "'
				. $expected_value
				. '", got: "' . $tag['value'] . '".'
			);
			$this->assertStringNotContainsString($unresolved_macro, $tag['tag'],
				'Problem event record: tag name "' . $tag_name . '" should not contain unresolved "'
				. $unresolved_macro
				. '", got: "' . $tag['tag'] . '".'
			);
			$this->assertStringNotContainsString($unresolved_macro, $tag['value'],
				'Problem event record: tag value "' . $expected_value
				. '" should not contain unresolved "' . $unresolved_macro
				. '", got: "' . $tag['value'] . '".'
			);
		}

		$this->assertTrue($tag_found,
			'Problem event record: tag "' . $tag_name . '" not found.'
		);
	}


	/**
	 * Assert that the item name in a specific decoded record is resolved correctly.
	 *
	 * @param array  $data         Decoded NDJSON record
	 * @param string $expected_name Expected resolved name
	 */
	private function assertExportNameResolvedFromRecord(array $data, string $expected_name): void {
		$this->assertArrayHasKey('name', $data, 'NDJSON record: "name" field is missing.');
		$this->assertSame($expected_name, $data['name'],
			'NDJSON record: name should be "' . $expected_name . '", got: "' . $data['name'] . '".'
		);
		$this->assertStringNotContainsString('{$FILE_NAME}', $data['name'],
			'NDJSON record: name should not contain "{$FILE_NAME}".'
		);
	}

	/**
	 * Assert that a tag pair in a specific decoded record is resolved correctly.
	 *
	 * @param array  $data              Decoded NDJSON record
	 * @param string $array_key         Key of the tags array in the record (e.g., 'item_tags')
	 * @param string $expected_tag      Expected tag name
	 * @param string $expected_value    Expected tag value
	 * @param array  $unresolved_macros Macros that should not appear in the resolved tag
	 */
	private function assertExportTagPairResolvedFromRecord(array $data, string $array_key, string $expected_tag,
			string $expected_value, array $unresolved_macros): void {
		$this->assertArrayHasKey($array_key, $data, 'NDJSON record: "' . $array_key . '" field is missing.');
		$this->assertIsArray($data[$array_key], 'NDJSON record: "' . $array_key . '" must be an array.');

		$tag_found = false;

		foreach ($data[$array_key] as $tag) {
			$this->assertIsArray(
				$tag,
				'NDJSON record: each "' . $array_key . '" entry must be an array.'
			);

			if (!isset($tag['tag'], $tag['value'])) {
				continue;
			}

			if ($tag['tag'] !== $expected_tag) {
				continue;
			}

			$tag_found = true;
			$this->assertSame($expected_value, $tag['value'],
				'NDJSON record: tag "' . $expected_tag . '" should have value "' . $expected_value
				. '", got: "' . $tag['value'] . '".'
			);

			foreach ($unresolved_macros as $macro) {
				$this->assertStringNotContainsString($macro, $tag['tag'],
					'NDJSON record: tag name "' . $expected_tag . '" should not contain "'
					. $macro
					. '", got: "' . $tag['tag'] . '".'
				);
				$this->assertStringNotContainsString($macro, $tag['value'],
					'NDJSON record: tag value "' . $expected_value . '" should not contain "'
					. $macro
					. '", got: "' . $tag['value'] . '".'
				);
			}
		}

		$this->assertTrue($tag_found, 'NDJSON record: tag "' . $expected_tag
			. '" with value "' . $expected_value . '" not found.');
	}

	/**
	 * Check user macro expansion in name of normal item.
	 */
	public function testUserMacrosInItemNames_normalItem() {
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'Item tst']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Item tst', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check update of macro value.
	 *
	 * @depends testUserMacrosInItemNames_normalItem
	 */
	public function testUserMacrosInItemNames_normalItemUpdated() {
		$response = $this->call('usermacro.updateglobal', [
			'globalmacroid' => self::$macroid,
			'macro' => '{$TEST}',
			'value' => 'test'
		]);
		$this->assertArrayHasKey('globalmacroids', $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'Item test']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Item test', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check user macro expansion in name of discovered item.
	 *
	 * @depends testUserMacrosInItemNames_normalItemUpdated
	 */
	public function testUserMacrosInItemNames_lld() {
		$this->sendSenderValue(self::HOSTNAME1, 'item_discovery', ['data' => [
			[
				'{#KEY}' => '1'
			]
		]]);

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'LLD test 1']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('LLD test 1', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check user macro expansion in name of a templated item.
	 *
	 * @depends testUserMacrosInItemNames_normalItemUpdated
	 */
	public function testUserMacrosInItemNames_templatedItem() {
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid2,
			'search' => ['name_resolved' => 'Template item test']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Template item test', $response['result'][0]['name_resolved']);

		return true;
	}
}

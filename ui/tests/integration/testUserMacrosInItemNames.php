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
 * Test suite for user macro expansion in item names.
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
	private static $triggerid_export;
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
				'templates' => ['templateid' => $templateid]
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
				'macros' => [
					['macro' => '{$ENV}', 'value' => 'prod'],
					['macro' => '{$FILE_NAME}', 'value' => 'config.json']
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
				['tag' => 'env-{$ENV}', 'value' => 'value-{$ENV}']
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
		self::$triggerid_export = $response['result']['triggerids'][0];

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
			foreach (glob($export_dir . '/*.ndjson') as $file) {
				@unlink($file);
			}

			@rmdir($export_dir);
		}
	}

	/**
	 * Check user macro resolution in NDJSON export for item names and tag values in history, trends and problem events.
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
			// Verify history NDJSON.
			$this->waitForExportFile($export_dir, 'history');
			$this->assertExportNameResolved($export_dir, 'history', self::$itemid_export,
				'File config.json exists');
			$this->assertExportTagPairResolvedByItemid($export_dir, 'history', self::$itemid_export,
				'item_tags', 'env', 'prod', ['{$ENV}']);
			$this->assertExportTagPairResolvedByItemid($export_dir, 'history', self::$itemid_export,
				'item_tags', 'env-prod', 'value-prod', ['{$ENV}']);
			// Verify trends NDJSON.
			$this->waitForExportFile($export_dir, 'trends');
			$this->assertExportNameResolved($export_dir, 'trends', self::$itemid_export,
				'File config.json exists');
			$this->assertExportTagPairResolvedByItemid($export_dir, 'trends', self::$itemid_export,
				'item_tags', 'env', 'prod', ['{$ENV}']);
			$this->assertExportTagPairResolvedByItemid($export_dir, 'trends', self::$itemid_export,
				'item_tags', 'env-prod', 'value-prod', ['{$ENV}']);
			// Verify event NDJSON.
			$this->waitForExportFile($export_dir, 'problems');
			$this->assertEventTagResolved($export_dir, 'Macro export trigger for ' . self::HOSTNAME_EXPORT,
				'env', 'prod', '{$ENV}');
			$this->assertEventTagResolved($export_dir, 'Macro export trigger for ' . self::HOSTNAME_EXPORT,
				'event-prod', 'problem-prod', '{$ENV}');
		}
		finally {
			$this->cleanupNdjsonExportFiles();
		}
	}

	private function cleanupNdjsonExportFiles(): void {
		$export_dir = self::getExportDir();

		foreach (glob($export_dir . '/*.ndjson') as $file) {
			@unlink($file);
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
	 * Iterate over NDJSON files of a given type in a directory, yielding each decoded line.
	 *
	 * @param string   $dir
	 * @param string   $type      File prefix (e.g., 'history', 'trends', 'problems')
	 * @param callable $callback  Function receiving (array $data, string $file, string $type)
	 */
	private function iterateNdjsonFiles(string $dir, string $type, callable $callback): void {
		foreach (glob($dir . '/' . $type . '*.ndjson') as $file) {
			$content = file_get_contents($file);

			$this->assertNotFalse(
				$content,
				'Cannot read NDJSON file: ' . $file
			);

			$lines = array_values(array_filter(explode("\n", $content), 'strlen'));

			foreach ($lines as $line) {
				$data = $this->decodeNdjsonLine($line, $file, $type);
				$callback($data, $file, $type);
			}
		}
	}

	private function waitForExportFile($dir, $type) {
		$deadline = microtime(true) + self::WAIT_EXPORT_FILE_TIMEOUT;

		do {
			$files = glob($dir . '/' . $type . '*.ndjson');

			foreach ($files as $file) {
				if (filesize($file) > 0) {
					return;
				}
			}

			usleep(self::WAIT_EXPORT_FILE_INTERVAL);
		}
		while (microtime(true) < $deadline);

		$this->fail('Export file "' . $type . '*.ndjson" not found or empty in ' . $dir);
	}

	private function decodeNdjsonLine($line, $file, $type) {
		$data = json_decode($line, true);

		if (JSON_ERROR_NONE !== json_last_error()) {
			$this->fail('Invalid JSON in ' . $type . ' NDJSON file "' . $file . '": '
				. json_last_error_msg() . '. Line: ' . $line);
		}

		return $data;
	}

	private function assertExportTagPairResolvedByItemid($dir, $type, $itemid, $array_key, $expected_tag,
			$expected_value, array $unresolved_macros) {
		$found = false;

		$this->iterateNdjsonFiles($dir, $type, function ($data, $file, $type) use ($itemid, $array_key,
				$expected_tag, $expected_value, $unresolved_macros, &$found) {
			if (!is_array($data) || !isset($data['itemid'], $data[$array_key])) {
				return;
			}

			if ((string)$data['itemid'] !== (string)$itemid) {
				return;
			}

			$found = true;
			$tag_found = false;

			foreach ($data[$array_key] as $tag) {
				if (!isset($tag['tag'], $tag['value'])) {
					continue;
				}

				if ($tag['tag'] !== $expected_tag) {
					continue;
				}

				$tag_found = true;
				$this->assertSame($expected_value, $tag['value'],
					$type . ' NDJSON itemid=' . $itemid . ': tag "' . $expected_tag
					. '" should have value "' . $expected_value . '", got: "'
					. $tag['value'] . '".'
				);

				foreach ($unresolved_macros as $macro) {
					$this->assertStringNotContainsString($macro, $tag['tag'],
						$type . ' NDJSON itemid=' . $itemid . ': tag name "' . $expected_tag
						. '" should not contain "' . $macro . '", got: "' . $tag['tag'] . '".'
					);
					$this->assertStringNotContainsString($macro, $tag['value'],
						$type . ' NDJSON itemid=' . $itemid . ': tag value "' . $expected_value
						. '" should not contain "' . $macro . '", got: "' . $tag['value'] . '".'
					);
				}
			}

			$this->assertTrue($tag_found, $type . ' NDJSON itemid=' . $itemid . ': tag "' . $expected_tag
				. '" with value "' . $expected_value . '" not found.'
			);
		});

		$this->assertTrue($found,
			$type . ' NDJSON: no record with itemid=' . $itemid . ' found.'
		);
	}

	private function assertExportNameResolved($dir, $type, $itemid, $expected_name) {
		$found = false;

		$this->iterateNdjsonFiles($dir, $type, function ($data, $file, $type) use ($itemid, $expected_name,
				&$found) {
			if (!is_array($data) || !isset($data['itemid'])) {
				return;
			}

			if ((string)$data['itemid'] !== (string)$itemid) {
				return;
			}

			$found = true;

			if (!isset($data['name'])) {
				$this->fail($type . ' NDJSON itemid=' . $itemid . ': "name" field is missing.');
			}

			$this->assertSame($expected_name, $data['name'],
				$type . ' NDJSON itemid=' . $itemid . ': name should be "' . $expected_name
				. '", got: "' . $data['name'] . '".'
			);
			$this->assertStringNotContainsString('{$FILE_NAME}', $data['name'],
				$type . ' NDJSON itemid=' . $itemid . ': name should not contain "{$FILE_NAME}".'
			);
		});

		$this->assertTrue($found, $type . ' NDJSON: no record with itemid=' . $itemid . ' found.');
	}

	private function assertEventTagResolved($dir, $event_name, $tag_name, $expected_value, $unresolved_macro) {
		$found = false;

		$this->iterateNdjsonFiles($dir, 'problems', function ($data, $file, $type) use ($event_name, $tag_name,
				$expected_value, $unresolved_macro, &$found) {
			if (!is_array($data) || !isset($data['name'], $data['tags'])) {
				return;
			}

			if ($data['name'] !== $event_name) {
				return;
			}

			$found = true;
			$tag_found = false;

			foreach ($data['tags'] as $tag) {
				if (!isset($tag['tag'], $tag['value'])) {
					continue;
				}

				if ($tag['tag'] !== $tag_name) {
					continue;
				}

				$tag_found = true;
				$this->assertSame($expected_value, $tag['value'],
					'events NDJSON for "' . $event_name . '": tag "' . $tag_name
					. '" should be resolved to "' . $expected_value . '", got: "'
					. $tag['value'] . '".'
				);
				$this->assertStringNotContainsString($unresolved_macro, $tag['tag'],
					'events NDJSON for "' . $event_name . '": tag name "' . $tag_name
					. '" should not contain unresolved "' . $unresolved_macro . '", got: "'
					. $tag['tag'] . '".'
				);
				$this->assertStringNotContainsString($unresolved_macro, $tag['value'],
					'events NDJSON for "' . $event_name . '": tag value "' . $expected_value
					. '" should not contain unresolved "' . $unresolved_macro . '", got: "'
					. $tag['value'] . '".'
				);
			}

			$this->assertTrue($tag_found,
				'events NDJSON for "' . $event_name . '": tag "' . $tag_name . '" not found.'
			);
		});

		$this->assertTrue($found,
			'events NDJSON: no event record with name "' . $event_name . '" found.'
		);
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

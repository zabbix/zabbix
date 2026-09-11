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
 * Test suite for Connector tag filtering with macro resolution.
 *
 * @required-components server
 */
class testConnectorExport extends CIntegrationTest {
	private const WAIT_PAYLOAD_TIMEOUT = 30;
	private const WAIT_PAYLOAD_INTERVAL = 100000;
	private const WAIT_RECEIVER_TIMEOUT = 5;
	private const WAIT_RECEIVER_INTERVAL = 100000;

	private static $hostid = null;
	private static $itemid = null;
	private static $connectorid = null;
	private static $receiver_proc = null;
	private static $temp_dir = null;
	private static $output_file = null;
	private static $receiver_port = null;

	/**
	 * Server configuration with Connector processes enabled.
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'StartConnectors' => 1
			]
		];
	}

	/**
	 * Find a free local TCP port.
	 */
	private static function findFreePort(): int {
		$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if ($socket === false) {
			throw new Exception("Failed to create temporary socket: $errstr ($errno)");
		}

		$addr = stream_socket_get_name($socket, false);
		fclose($socket);
		if ($addr === false) {
			throw new Exception('Failed to get local socket address');
		}

		$pos = strrpos($addr, ':');
		if ($pos === false) {
			throw new Exception('Failed to parse port from socket address: ' . $addr);
		}
		return (int)substr($addr, $pos + 1);
	}

	/**
	 * Start the temporary HTTP receiver.
	 */
	private function startReceiver(): void {
		self::$temp_dir = sys_get_temp_dir() . '/zabbix_connector_test_' . getmypid() . '_' . uniqid();
		if (!mkdir(self::$temp_dir, 0777, true) && !is_dir(self::$temp_dir)) {
			throw new Exception('Failed to create temporary directory: ' . self::$temp_dir);
		}

		self::$output_file = self::$temp_dir . '/payload.ndjson';
		self::$receiver_port = self::findFreePort();

		$receiver_script = dirname(__FILE__) . '/testConnectorExportReceiver.php';
		$php_binary = PHP_BINARY;

		$descriptorspec = [
			1 => ['file', self::$temp_dir . '/receiver_stdout.log', 'w'],
			2 => ['file', self::$temp_dir . '/receiver_stderr.log', 'w']
		];

		$env = getenv();
		if (!is_array($env)) {
			$env = [];
		}

		$env['ZABBIX_CONNECTOR_TEST_OUTPUT'] = self::$output_file;

		$cmd = escapeshellarg($php_binary)
			. ' -S '
			. escapeshellarg('127.0.0.1:' . self::$receiver_port)
			. ' '
			. escapeshellarg($receiver_script);

		self::$receiver_proc = proc_open($cmd, $descriptorspec, $pipes, self::$temp_dir, $env);

		if (!is_resource(self::$receiver_proc)) {
			throw new Exception('Failed to start HTTP receiver process');
		}

		$deadline = microtime(true) + self::WAIT_RECEIVER_TIMEOUT;
		$started = false;

		do {
			$socket = @fsockopen('127.0.0.1', self::$receiver_port, $errno, $errstr, 1);

			if ($socket !== false) {
				fclose($socket);
				$started = true;
				break;
			}

			usleep(self::WAIT_RECEIVER_INTERVAL);
		}
		while (microtime(true) < $deadline);

		if (!$started) {
			$stderr = file_get_contents(self::$temp_dir . '/receiver_stderr.log');
			$stdout = file_get_contents(self::$temp_dir . '/receiver_stdout.log');
			throw new Exception(
				"HTTP receiver failed to start on port " . self::$receiver_port
				. ". stderr: $stderr. stdout: $stdout"
			);
		}
	}

	/**
	 * Stop the temporary HTTP receiver.
	 */
	private function stopReceiver(): void {
		if (is_resource(self::$receiver_proc)) {
			proc_terminate(self::$receiver_proc);
			proc_close(self::$receiver_proc);
			self::$receiver_proc = null;
		}
	}

	/**
	 * Clean up temporary files and directory.
	 */
	private function cleanupTempDir(): void {
		if (self::$temp_dir !== null && is_dir(self::$temp_dir)) {
			foreach (glob(self::$temp_dir . '/*') as $file) {
				@unlink($file);
			}

			@rmdir(self::$temp_dir);
		}

		self::$temp_dir = null;
		self::$output_file = null;
		self::$receiver_port = null;
	}

	/**
	 * Wait for Connector payload to arrive at the receiver.
	 */
	private function waitForPayload(): array {
		$deadline = microtime(true) + self::WAIT_PAYLOAD_TIMEOUT;

		/*
		 * The Connector Worker does not log successful HTTP delivery, while the
		 * payload is written asynchronously by the external HTTP receiver.
		 */
		do {
			if (file_exists(self::$output_file) && filesize(self::$output_file) > 0) {
				$content = file_get_contents(self::$output_file);

				if ($content !== false) {
					$lines = array_values(array_filter(explode("\n", $content), 'strlen'));

					foreach ($lines as $line) {
						$data = json_decode($line, true);

						if (is_array($data)
							&& isset($data['itemid'])
							&& (string)$data['itemid'] === (string)self::$itemid
						) {
							return $data;
						}
					}
				}
			}

			usleep(self::WAIT_PAYLOAD_INTERVAL);
		}
		while (microtime(true) < $deadline);

		$stderr = '';
		$stderr_file = self::$temp_dir . '/receiver_stderr.log';
		if (file_exists($stderr_file)) {
			$stderr = file_get_contents($stderr_file);
			if ($stderr === false) {
				$stderr = '';
			}
		}

		$msg = 'Connector payload not received within ' . self::WAIT_PAYLOAD_TIMEOUT . ' seconds';
		if ($stderr !== '') {
			$msg .= ". Receiver stderr: $stderr";
		}

		throw new Exception($msg);
	}

	/**
	 * Test Connector filter matching with macro in item tag value.
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testConnectorExport_macroInTagValue() {
		$hostname = 'test_connector_macro_tag_value_' . uniqid();

		try {
			$this->startReceiver();

			$response = $this->call('host.create', [
				[
					'host' => $hostname,
					'interfaces' => [],
					'groups' => [['groupid' => 4]],
					'status' => HOST_STATUS_MONITORED,
					'macros' => [
						['macro' => '{$CON}', 'value' => 'connector']
					]
				]
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			self::$hostid = $response['result']['hostids'][0];

			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => 'Connector macro test item',
				'key_' => 'connector.macro.test',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'trapper_hosts' => '0.0.0.0/0,::/0',
				'tags' => [
					[
						'tag' => 'transmitted',
						'value' => '{$CON}'
					]
				]
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			self::$itemid = $response['result']['itemids'][0];

			$connector_name = 'Test connector macro tag value ' . uniqid();
			$response = $this->call('connector.create', [
				'name' => $connector_name,
				'url' => 'http://127.0.0.1:' . self::$receiver_port . '/v1/history',
				'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES,
				'item_value_type' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64,
				'tags_evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'tags' => [
					[
						'tag' => 'transmitted',
						'value' => 'connector',
						'operator' => CONDITION_OPERATOR_EQUAL
					]
				],
				'authtype' => ZBX_HTTP_AUTH_NONE,
				'max_attempts' => 1,
				'attempt_interval' => '5s',
				'timeout' => '3s',
				'verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF,
				'verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'status' => ZBX_CONNECTOR_STATUS_ENABLED
			]);
			$this->assertArrayHasKey('connectorids', $response['result']);
			self::$connectorid = $response['result']['connectorids'][0];

			$this->reloadConfigurationCache(self::COMPONENT_SERVER);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'finished forced reloading of the configuration cache', true, 60, 1);

			if (false === file_put_contents(self::$output_file, '')) {
				throw new Exception('Failed to initialize receiver output file');
			}

			$now = time();
			$this->sendSenderValues([
				[
					'host' => $hostname,
					'key' => 'connector.macro.test',
					'value' => '1',
					'clock' => $now
				]
			]);

			$payload = $this->waitForPayload();

			$this->assertEquals((string)self::$itemid, (string)$payload['itemid'],
				'Payload itemid does not match test itemid');

			$this->assertArrayHasKey('item_tags', $payload, 'Payload missing item_tags');
			$tag_found = false;
			foreach ($payload['item_tags'] as $tag) {
				if (!isset($tag['tag'], $tag['value'])) {
					continue;
				}

				if ($tag['tag'] === 'transmitted' && $tag['value'] === 'connector') {
					$tag_found = true;
					break;
				}
			}
			$this->assertTrue($tag_found, 'Resolved tag "transmitted=connector" not found in payload');

			foreach ($payload['item_tags'] as $tag) {
				$this->assertArrayHasKey('tag', $tag);
				$this->assertArrayHasKey('value', $tag);
				$this->assertStringNotContainsString('{$CON}', $tag['tag'],
					'Tag name should not contain unresolved macro {$CON}');
				$this->assertStringNotContainsString('{$CON}', $tag['value'],
					'Tag value should not contain unresolved macro {$CON}');
			}
		}
		finally {
			try {
				if (self::$connectorid !== null) {
					$this->call('connector.delete', [self::$connectorid]);
				}
			}
			finally {
				self::$connectorid = null;

				try {
					if (self::$itemid !== null) {
						$this->call('item.delete', [self::$itemid]);
					}
				}
				finally {
					self::$itemid = null;

					try {
						if (self::$hostid !== null) {
							$this->call('host.delete', [self::$hostid]);
						}
					}
					finally {
						self::$hostid = null;

						try {
							$this->stopReceiver();
						}
						finally {
							$this->cleanupTempDir();
						}
					}
				}
			}
		}

		return true;
	}
}

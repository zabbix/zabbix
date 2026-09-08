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

/**
 * Test suite for autoregistration
 *
 * @backup ids,hosts,items,actions,operations,optag,host_tag
 * @backup auditlog,changelog,settings,ha_node
 */
class testAutoregistrationHostMetaDataItem extends CIntegrationTest {

	const HOSTNAME = "test_tags_host";
	const PROXY_NAME = "test_metadata_batch_proxy";
	const HOSTNAME_OVERSIZED = "test_metadata_oversized_host";
	const HOSTNAME_AFTER_OVERSIZED = "test_metadata_normal_host_after_oversized";
	static $metadata_file;

	// Action/proxy ids created by testBatchOversizedHostMetadataDoesNotBlockFollowingHost(),
	// cleaned up via clearBatchTestData().
	private $batch_test_actionids = [];
	private $batch_test_proxyids = [];

	/**
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_MetadataItem() {
		self::$metadata_file = "/tmp/zabbix_agent_metadata_file.txt" . microtime();

		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadataItem' => 'vfs.file.contents['.self::$metadata_file.']'
			],
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			]
		];
	}

	/**
	 * Testing single action of adding host tags, and then removing host tags.
	 *
	 * @required-components agent,server
	 *
	 * @backup actions,hosts,host_tag,autoreg_host
	 *
	 * @configurationDataProvider agentConfigurationProvider_MetadataItem
	 */
	public function testSingleActionRemoveTags()
	{
		$response = $this->call('action.create', [
		[
			'name' => "actionX",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				['operationtype' => OPERATION_TYPE_HOST_ADD],
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'SINGLE_TAG_X',
							'value' => 'SINGLE_VALUE_Y'
						]
					]
				]
			]
		]]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'],
				'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(1, $actionids, 'Failed to create an autoregistration action');

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);

		if (file_exists(self::$metadata_file)) {
			unlink(self::$metadata_file);
		}

		if (file_put_contents(self::$metadata_file, "\\" . microtime()) === false) {
			throw new Exception('Failed to create metadata_file');
		}

		$this->killComponent(self::COMPONENT_AGENT);
		$this->startComponent(self::COMPONENT_AGENT);


		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::HOSTNAME
				],
			'selectTags' => ['tag', 'value']
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to autoregister host before timeout');
		$this->assertCount(1, $response['result'], 'Failed to autoregister host before timeout, response result: '.
			json_encode($response['result']));

		$this->assertArrayHasKey('tags', $response['result'][0],
				'Failed to autoregister host before timeout: response result: '. json_encode($response['result']));
		$autoregHost = $response['result'][0];
		$this->assertArrayHasKey('hostid', $autoregHost, 'Failed to get host ID of the autoregistered host');
		$tags = $autoregHost['tags'];
		$expectedTags = ['tag' => 'SINGLE_TAG_X', 'value' => 'SINGLE_VALUE_Y'];
		$this->assertCount(1, $tags, 'Unexpected tags count was detected: '. json_encode($tags));
		$this->assertContains($expectedTags, $tags, json_encode($tags));


		$response = $this->call('action.update', [
		[
			'actionid' => $actionids[0],
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_REMOVE,
					'optag' => [
						[
							'tag' => 'SINGLE_TAG_X',
							'value' => 'SINGLE_VALUE_Y'
						]
					]
				]
			]
		]]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'],
				'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(1, $actionids, 'Failed to create an autoregistration action');

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		if (file_exists(self::$metadata_file)) {
			unlink(self::$metadata_file);
		}

		if (file_put_contents(self::$metadata_file, "\\" . microtime()) === false) {
			throw new Exception('Failed to create metadata_file');
		}

		$this->killComponent(self::COMPONENT_AGENT);
		$this->startComponent(self::COMPONENT_AGENT);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::HOSTNAME
				],
			'selectTags' => ['tag', 'value']
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to autoregister host before timeout');
		$this->assertCount(1, $response['result'], 'Failed to autoregister host before timeout, response result: '.
			json_encode($response['result']));

		$this->assertArrayHasKey('tags', $response['result'][0],
				'Failed to autoregister host before timeout: response result: '. json_encode($response['result']));
		$autoregHost = $response['result'][0];
		$this->assertArrayHasKey('hostid', $autoregHost, 'Failed to get host ID of the autoregistered host');
		$tags = $autoregHost['tags'];
		$this->assertCount(0, $tags, 'Unexpected tags count was detected: '. json_encode($tags));
	}

	/**
	 * Component configuration provider for server-only tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_ServerOnly() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	/**
	 * Delete data created by testBatchOversizedHostMetadataDoesNotBlockFollowingHost().
	 */
	public function clearBatchTestData(): void {
		$response = $this->call('host.get', [
			'filter' => [
				'host' => [self::HOSTNAME_OVERSIZED, self::HOSTNAME_AFTER_OVERSIZED]
			]
		]);

		if (array_key_exists('result', $response) && $response['result']) {
			CDataHelper::call('host.delete', array_column($response['result'], 'hostid'));
		}

		if ($this->batch_test_proxyids) {
			CDataHelper::call('proxy.delete', $this->batch_test_proxyids);
			$this->batch_test_proxyids = [];
		}

		if ($this->batch_test_actionids) {
			CDataHelper::call('action.delete', $this->batch_test_actionids);
			$this->batch_test_actionids = [];
		}
	}

	/**
	 * Regression test: a proxy can forward autoregistration data for several hosts in a single
	 * batch. One host with an oversized host_metadata must be rejected, but must not prevent
	 * hosts queued after it in the same batch from being autoregistered normally.
	 *
	 * @required-components server
	 *
	 * @onAfter clearBatchTestData
	 *
	 * @configurationDataProvider agentConfigurationProvider_ServerOnly
	 */
	public function testBatchOversizedHostMetadataDoesNotBlockFollowingHost()
	{
		$response = $this->call('action.create', [
		[
			'name' => "actionMetadataBatch",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				['operationtype' => OPERATION_TYPE_HOST_ADD]
			]
		]]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create an autoregistration action');
		$this->assertCount(1, $response['result']['actionids'], 'Failed to create an autoregistration action');
		$this->batch_test_actionids = $response['result']['actionids'];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a proxy');
		$this->assertArrayHasKey('proxyids', $response['result'], 'Failed to create a proxy');
		$this->batch_test_proxyids = $response['result']['proxyids'];

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);

		$clock = time();

		// One batch, sent as a single "proxy data" request: an oversized host_metadata entry
		// followed by a host with completely normal, short host_metadata.
		//
		// Note: the server's response to a "proxy data" request is always compression-flagged
		// (see zbx_send_proxy_data_response()), which this lightweight test client cannot parse
		// back into JSON - the same reason other proxy-spoofing tests (e.g.
		// testLLDHistorySyncAtScale::dispatchValues()) don't assert on this call's return value.
		// The actual outcome is verified below via host.get.
		$this->getClient(self::COMPONENT_SERVER)->sendAutoregistrationData(self::PROXY_NAME, [
			[
				'clock' => $clock,
				'host' => self::HOSTNAME_OVERSIZED,
				'host_metadata' => str_repeat('A', 70000),
				'ip' => '127.0.0.1',
				'port' => 10050
			],
			[
				'clock' => $clock,
				'host' => self::HOSTNAME_AFTER_OVERSIZED,
				'host_metadata' => 'normal_metadata',
				'ip' => '127.0.0.1',
				'port' => 10050
			]
		]);

		sleep(self::DATA_PROCESSING_DELAY);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::HOSTNAME_OVERSIZED
			]
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertCount(0, $response['result'],
				'Host with oversized host_metadata must not be autoregistered');

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::HOSTNAME_AFTER_OVERSIZED
			]
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertCount(1, $response['result'],
				'Host queued after an oversized host_metadata entry in the same batch must still be '.
				'autoregistered');
	}
}

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
 * Test suite for autoregistration
 *
 * @backup ids,hosts,items,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testAutoregistrationHostMetaDataItem extends CIntegrationTest {

	const HOSTNAME = "test_tags_host";
	static $metadata_file;

	/**
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_MetadataItem() {
		self::$metadata_file = "/tmp/zabbix_agent_metadata_file.txt".time();

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

		if (file_put_contents(self::$metadata_file, "\\".time()) === false) {
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

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				"finished forced reloading of the configuration cache", true, 60, 1);

		if (file_exists(self::$metadata_file)) {
			unlink(self::$metadata_file);
		}

		if (file_put_contents(self::$metadata_file, "\\".time()) === false) {
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
}

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
 * Test suite for autoregistration with PSK
 *
 * @required-components server, agent, agent2
 * @backup ids,hosts,items,actions,operations,optag,host_tag
 * @backup auditlog,changelog,settings,ha_node
 */
class testAutoregistrationPSK extends CIntegrationTest {

	const PSK_IDENTITY = "!\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}";
	const PSK_KEY_UPPER_CASE = "53E79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe";
	const PSK_KEY_LOWER_CASE = "53e79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe";
	const PSK_KEY_WRONG = "53D79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe";

	const PSK_FILE_UPPER_CASE = "/tmp/zabbix_agent_upper_case_psk.txt";
	const PSK_FILE_LOWER_CASE = "/tmp/zabbix_agent_lower_case_psk.txt";
	const PSK_FILE_WRONG = "/tmp/zabbix_agent_wrong_psk.txt";

	const METADATA_FILE = "/tmp/zabbix_agent_metadata_file.txt";
	const HOST_METADATA_PSK_WRONG = "METADATA_PSK_WRONG";

	const PSK_HOSTNAME = "PSK_HOSTNAME";
	const PSK_HOSTNAME2 = "PSK_HOSTNAME2";

	/**
	 * @inheritdoc
	 */
	public function prepareData() {

		if (file_put_contents(self::PSK_FILE_LOWER_CASE, self::PSK_KEY_LOWER_CASE) === false) {
			throw new Exception('Failed to create lower case PSK file for agent');
		}

		if (file_put_contents(self::PSK_FILE_UPPER_CASE, self::PSK_KEY_UPPER_CASE) === false) {
			throw new Exception('Failed to create upper case PSK file for agent');
		}

		if (file_put_contents(self::PSK_FILE_WRONG, self::PSK_KEY_WRONG) === false) {
			throw new Exception('Failed to create wrong PSK file for agent');
		}

		$response = $this->call('action.create', [
		[
			'name' => "action",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_ADD
				]
			]
		]]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'],
				'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(1, $actionids, 'Failed to create an autoregistration action');
	}

	private function updateAutoregistrationWithUpperCasePSK()
	{
		$response = $this->call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk' => self::PSK_KEY_UPPER_CASE
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(true, $response['result']);
	}

	/**
	 * Autoregisters agent1, then agent2 and then agent1 again. (by changing metadata item).
	 * Checks resulting tags on host to make sure that autoregistration was successful.
	 */
	private function coreTestCase() {

		if (file_exists(self::METADATA_FILE)) {
			unlink(self::METADATA_FILE);
		}

		if (file_put_contents(self::METADATA_FILE, "\\".time()) === false) {
			throw new Exception('Failed to create metadata_file');
		}

		$this->killComponent(self::COMPONENT_AGENT2);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->killComponent(self::COMPONENT_SERVER);

		$this->updateAutoregistrationWithUpperCasePSK();
		$this->startComponent(self::COMPONENT_SERVER);

		sleep(1);

		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->stopComponent(self::COMPONENT_SERVER);

		$response = $this->call('action.create', [
		[
			'name' => "action2",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'PSK_TAG',
							'value' => 'PSK_VALUE'
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

		$this->startComponent(self::COMPONENT_SERVER);
		sleep(1);
		$this->startComponent(self::COMPONENT_AGENT2);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::PSK_HOSTNAME2
				],
			'selectTags' => ['tag', 'value']
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to autoregister host before timeout');
		$this->assertCount(1, $response['result'], 'Failed to autoregister host before timeout, response result: '.
			json_encode($response['result']));
		$this->assertArrayHasKey('tags', $response['result'][0], 'Failed to autoregister host before timeout: response result: '.
			json_encode($response['result']));

		$autoregHost = $response['result'][0];
		$this->assertArrayHasKey('hostid', $autoregHost, 'Failed to get host ID of the autoregistered host');
		$tags = $autoregHost['tags'];
		$expectedTags = ['tag' => 'PSK_TAG', 'value' => 'PSK_VALUE'];

		$this->assertCount(1, $tags, 'Unexpected tags count was detected: '. json_encode($tags));
		$this->assertContains($expectedTags, $tags, json_encode($tags));

		$response = $this->call('action.create', [
		[
			'name' => "action3",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'PSK_TAG22',
							'value' => 'PSK_VALUE22'
						]
					]
				]
			]
		]]);

		$this->killComponent(self::COMPONENT_AGENT2);
		$this->stopComponent(self::COMPONENT_SERVER);

		if (file_put_contents(self::METADATA_FILE, "\\".time()) === false) {
			throw new Exception('Failed to create metadata_file');
		}

		$this->startComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_AGENT);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::PSK_HOSTNAME
				],
			'selectTags' => ['tag', 'value']
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to autoregister host before timeout');
		$this->assertCount(1, $response['result'], 'Failed to autoregister host before timeout, response result: '.
			json_encode($response['result']));
		$this->assertArrayHasKey('tags', $response['result'][0], 'Failed to autoregister host before timeout: response result: '.
			json_encode($response['result']));
		$autoregHost = $response['result'][0];
		$this->assertArrayHasKey('hostid', $autoregHost, 'Failed to get host ID of the autoregistered host');
		$tags = $autoregHost['tags'];
		$expectedTags = ['tag' => 'PSK_TAG22', 'value' => 'PSK_VALUE22'];
		$this->assertCount(2, $tags, 'Unexpected tags count was detected: '. json_encode($tags));
		$this->assertContains($expectedTags, $tags, json_encode($tags));
	}

	/**
	 * Both agent 1 and agent 2 have the same UPPER case PSK
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_UpperCasePSK() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_UPPER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::PSK_HOSTNAME2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_UPPER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
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
	 * @required-components agent,agent2,server
	 *
	 * @backup actions,hosts,host_tag,autoreg_host
	 *
	 * @configurationDataProvider agentConfigurationProvider_UpperCasePSK
	 */
	public function testAutoregistration_withUpperCasePSK()
	{
		$this->coreTestCase();
	}

	/**
	 * Both agent 1 and agent 2 have the same LOWER case PSK
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_withLowerCasePSK() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_LOWER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::PSK_HOSTNAME2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_LOWER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
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
	 * @required-components agent,agent2,server
	 *
	 * @backup actions,hosts,host_tag,autoreg_host
	 *
	 * @configurationDataProvider agentConfigurationProvider_withLowerCasePSK
	 */
	public function testAutoregistration_withLowerCasePSK()
	{
		$this->coreTestCase();
	}

	/**
	 * @backup actions,hosts,host_tag,autoreg_host
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_secondTimeWrongPSK() {

		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_UPPER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_WRONG,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
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
	 * Makes sure autoregistration does not happen when wrong PSK is used.
	 * Checks the resulting tags on host to make sure the autoregistration did not happen.
	 *
	 * @required-components agent,agent2,server
	 * @configurationDataProvider agentConfigurationProvider_secondTimeWrongPSK
	 */
	public function testAutoregistration_secondTimeWrongPSK()
	{
		$this->killComponent(self::COMPONENT_AGENT2);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->killComponent(self::COMPONENT_SERVER);

		$this->updateAutoregistrationWithUpperCasePSK();

		$this->startComponent(self::COMPONENT_SERVER);

		sleep(1);

		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->stopComponent(self::COMPONENT_SERVER);

		$response = $this->call('action.create', [
		[
			'name' => "action2",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'PSK_TAG',
							'value' => 'PSK_VALUE'
						]
					]
				]
			]
		]]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(1, $actionids, 'Failed to create an autoregistration action');
		$this->startComponent(self::COMPONENT_SERVER);

		sleep(1);

		$this->startComponent(self::COMPONENT_AGENT2);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'failed to accept an incoming connection', true, 120);

		$response = $this->call('host.get', [
			'filter' => [
				'host' => self::PSK_HOSTNAME
				],
			'selectTags' => ['tag', 'value']
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to autoregister host before timeout');
		$this->assertCount(1, $response['result'], 'Failed to autoregister host before timeout, response result: '.
			json_encode($response['result']));
		$this->assertArrayHasKey('tags', $response['result'][0], 'Failed to autoregister host before timeout: response result: '.
			json_encode($response['result']));

		$autoregHost = $response['result'][0];
		$this->assertArrayHasKey('hostid', $autoregHost, 'Failed to get host ID of the autoregistered host');

		$tags = $autoregHost['tags'];

		# there must be no tags, as autoregistration had to fail
		$this->assertCount(0, $tags, 'Unexpected tags count was detected: '. json_encode($tags));
	}
}

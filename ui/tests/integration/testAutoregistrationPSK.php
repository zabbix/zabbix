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
 * @required-components server, agent
 * @backup ids,hosts,items,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testAutoregistrationPSK extends CIntegrationTest {

	/* test Autoregstration with PSK */

	const PSK_IDENTITY = "535D2244f31e82fcee2cd9b7964413b797af3d2271e68a7ac2e94e102b2dcb31";
	const PSK_KEY_UPPER_CASE = "53E79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe";
	const PSK_KEY_LOWER_CASE = "53e79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe";
	const PSK_FILE_UPPER_CASE = "/tmp/zabbix_agent_upper_case_psk.txt";
	const PSK_FILE_LOWER_CASE = "/tmp/zabbix_agent_lower_case_psk.txt";

	const HOST_METADATA_PSK_LOWER_CASE = "METADATA_PSK_LOWER_CASE";
	const HOST_METADATA_PSK_UPPER_CASE = "METADATA_PSK_UPPER_CASE";

	const PSK_HOSTNAME = "PSK_HOSTNAME";

	/**
	 * @inheritdoc
	 */
	public function prepareData() {

		$response = $this->call('action.create', [
		[
			'name' => "action",
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				/* OPERATION_TYPE_HOST_ADD is intentionally missing. It is expected to be run by */
				/* Zabbix server, because OPERATION_TYPE_HOST_TAGS_ADD is present.               */
				[
					'operationtype' => OPERATION_TYPE_HOST_ADD,
				],
			]
		],]);

		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'],
				'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(1, $actionids, 'Failed to create an autoregistration action');
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider_LowerCaseFirstPSK() {
		if (file_put_contents(self::PSK_FILE_LOWER_CASE, self::PSK_KEY_LOWER_CASE) === false) {
			throw new Exception('Failed to create lower case PSK file for agent');
		}

		if (file_put_contents(self::PSK_FILE_UPPER_CASE, self::PSK_KEY_UPPER_CASE) === false) {
			throw new Exception('Failed to create upper case PSK file for agent');
		}

		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_LOWER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadata' => self::HOST_METADATA_PSK_UPPER_CASE
			],

			self::COMPONENT_AGENT2 => [
				'Hostname' => self::PSK_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::PSK_FILE_UPPER_CASE,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'HostMetadata' => self::HOST_METADATA_PSK_LOWER_CASE
			],
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			]
		];
	}

	private function updateAutoregistration()
	{
		$response = $this->call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk' => self::PSK_KEY_LOWER_CASE
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(true, $response['result']);
	}


	/**
	 * @required-components agent,agent2,server
	 * @configurationDataProvider agentConfigurationProvider_LowerCaseFirstPSK
	 */
	public function testAutoregistration_withLowerCasePSK()
	{
		$this->killComponent(self::COMPONENT_AGENT2);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->killComponent(self::COMPONENT_SERVER);

		$this->updateAutoregistration();

		$this->startComponent(self::COMPONENT_SERVER);
		sleep(1);
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		sleep(1);

		$this->startComponent(self::COMPONENT_AGENT2);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of db_register_host()', true, 120);
		#$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'but different PSK values', true, 120);
	}
}

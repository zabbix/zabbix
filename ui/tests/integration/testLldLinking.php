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
 * Test suite for LLD linking.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_trigger_linking
 * @backup history, autoreg_host
 */
class testLldLinking extends CIntegrationTest {
	const NUMBER_OF_TEMPLATES = 2;
	const TEMPLATE_NAME_PRE = 'TEMPLATE_NAME';
	const HOST_NAME = 'test_lld_linking';
	const METADATA_FILE = "/tmp/zabbix_agent_metadata_file.txt";

	private static $templateX_ID;
	private static $templateids = [];

	/**
	* Component configuration provider for server related tests.
	*
	* @return array
	*/
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051)
			]
		];
	}

	/**
	* Component configuration provider for agent related tests.
	*
	* @return array
	*/
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname'		=> self::HOST_NAME,
				'ServerActive'	=>
						'127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'DebugLevel'    => 4,
				'LogFileSize'   => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_AGENT),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent.pid',
				'HostMetadataItem' => 'vfs.file.contents['.self::METADATA_FILE.']'
			]
		];
	}

	private function metaDataItemUpdate () {

		if (file_exists(self::METADATA_FILE)) {
			unlink(self::METADATA_FILE);
		}

		if (file_put_contents(self::METADATA_FILE, "\\".time()) === false) {
			throw new Exception('Failed to create metadata_file');
		}
	}

	private function hostCreateAutoRegAndLink() {

		$response = $this->call('action.create', [
			'name' => 'create_host',
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => 0,
			'operations' => [
				[
					'operationtype' => 2
				]
			]
		]);

		$ep = json_encode($response, JSON_PRETTY_PRINT);

		$this->assertArrayHasKey('actionids', $response['result'], $ep);
		$this->assertEquals(1, count($response['result']['actionids']), $ep);

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {
			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PRE . "_" . $i,
					'groups' => [
						'groupid' => 1
					]
			]);

			$ep = json_encode($response, JSON_PRETTY_PRINT);

			$this->assertArrayHasKey('templateids', $response['result'], $ep);
			$this->assertArrayHasKey(0, $response['result']['templateids'], $ep);

			array_push(self::$templateids, $response['result']['templateids'][0]);
		}

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {
			$response = $this->call('discoveryrule.create', [
				'name' => 'LLD rule with LLD macro paths',
				'key_' => 'lld',
				'hostid' => self::$templateids[$i],
				'type' => 0,
				'delay' => '30s',
				'lld_macro_paths' => [
					[
						'lld_macro' => '{#MACRO1}',
						'path' => '$.path.1'
					]
				]
			]);
		}

		$response = $this->call('action.create', [
			'name' => 'link_templates',
			'eventsource' => 2,
			'status' => 0,
			'operations' => [
				[
					'operationtype' => 6,
					'optemplate' =>
					self::$templateids
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
	}

	private function unlinkTemplates() {

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => self::HOST_NAME
			]
			]);

		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$response = $this->call('host.update', [
			'hostid' => $hostid,
			'templates' => []
			]);

		sleep(1);
	}

	/**
	 * Test LLD linking cases.
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent
	 * @backup actions,hosts,host_tag,autoreg_host
	 */
	public function testLinkingLinking_conflict() {

		$this->killComponent(self::COMPONENT_SERVER);
		$this->killComponent(self::COMPONENT_AGENT);
		$this->hostCreateAutoRegAndLink();

		$this->metaDataItemUpdate();
		$this->startComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_AGENT);
		sleep(1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_db_copy_template_elements():FAIL', true, 120);
		/*$this->stopComponent(self::COMPONENT_AGENT);
		$this->unlinkTemplates();
		$this->metaDataItemUpdate();
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_db_copy_template_elements():FAIL', true, 120);*/
	}
}

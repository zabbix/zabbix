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
 * @backup history, autoreg_host
 */
class testLldLinking extends CIntegrationTest {
	const NUMBER_OF_TEMPLATES_TEST_1 = 2;
	const NUMBER_OF_TEMPLATES_TEST_2 = 1;
	const TEMPLATE_NAME_PRE = 'TEMPLATE_NAME';
	const HOST_NAME = 'test_lld_linking';
	const METADATA_FILE = "/tmp/zabbix_agent_metadata_file.txt";
	private static $actionId;
	private static $actionLinkTemplateId;
	private static $templateids = array();
	const LLD_RULE_MACRO_PATH = [
		'name' => 'LLD rule with LLD macro paths',
		'key_' => 'lld',
		'type' => ITEM_TYPE_ZABBIX,
		'delay' => '30s',
		'lld_macro_paths' => [
			[
				'lld_macro' => '{#FSNAME}',
				'path' => '$.fsname'
			],
			[
				'lld_macro' => '{#FSTYPE}',
				'path' => '$.fstype'
			]
		]
	];

	const LLD_RULE_FILTER = [
		'name' => 'LLD rule with filter',
		'key_' => 'lld',
		'type' => ITEM_TYPE_ZABBIX,
		'delay' => '30s',
		'filter' => [
			'evaltype' => CONDITION_EVAL_TYPE_AND,
			'conditions' => [
				[
					'macro' => '{#MACRO}',
					'value' => '@regex1'
				],
				[
					'macro' => '{#MACRO2}',
					'value' => '@regex2',
					'operator' => CONDITION_OPERATOR_NOT_REGEXP
				],
				[
					'macro' => '{#MACRO3}',
					'value'=> '',
					'operator' => CONDITION_OPERATOR_EXISTS
				],
				[
					'macro' => '{#MACRO4}',
					'value' => '',
					'operator' => CONDITION_OPERATOR_NOT_EXISTS
				]
			]
		]
	];

	const LLD_RULE_CUSTOM_QUERY_FIELDS = [
		'name' => 'API HTTP agent',
		'key_' => 'api_discovery_rule',
		'type' => ITEM_TYPE_HTTPAGENT,
		'delay' => '30s',
		'url' => 'http://127.0.0.1?discoverer.php',
		'query_fields' => [
			[
				'name' => 'mode',
				'value' => 'json'
			],
			[
				'name' => 'elements',
				'value' => '2'
			]
		],
		'headers' => [
			[
				'name' => 'X-Type',
				'value' => 'api'
			],
			[
				'name' => 'Authorization',
				'value' => 'Bearer mF_A.B5f-2.1JcM'
			]
		],
		'allow_traps' => HTTPCHECK_ALLOW_TRAPS_ON,
		'trapper_hosts'=> '127.0.0.1'
	];

	const LLD_RULE_PREPROCESSING = [
		'name' => 'Discovery rule with preprocessing',
		'key_' => 'lld.with.preprocessing',
		'type' => ITEM_TYPE_ZABBIX,
		'delay' => '60s',
		'preprocessing' => [
			'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
			'params' => '20',
			'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
			'error_handler_params' => ''
		]
	];

	const LLD_RULE_SCRIPT = [
		'name' => 'Script example',
		'key_' => 'custom.script.lldrule',
		'type' => ITEM_TYPE_SCRIPT,
		'params' => 'var request = new HttpRequest();\nreturn request.post(\"https://postman-echo.com/post\", JSON.parse(value));',
		'parameters' => [
			'name' => 'host',
			'value'=> '{HOST.CONN}'
		],
		'timeout' => '6s',
		'delay' => '30s'
	];

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
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER,
					'ListenPort', 10051)
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
				'Hostname'	=>	self::HOST_NAME,
				'ServerActive'	=>
						'127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'DebugLevel'	=>	4,
				'LogFileSize'	=>	0,
				'LogFile'	=>	self::getLogPath(self::COMPONENT_AGENT),
				'PidFile'	=>	PHPUNIT_COMPONENT_DIR.'zabbix_agent.pid',
				'HostMetadataItem'	=>	'vfs.file.contents['.self::METADATA_FILE.']'
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

	private function setupAutoregToLinkTemplates($templateNumber, $LLDParametrs) {

		$response = $this->call('action.create', [
			'name' => 'create_host',
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_ADD
				]
			]
		]);

		self::$actionId = $response['result']['actionids'][0];
		$ep = json_encode($response, JSON_PRETTY_PRINT);

		$this->assertArrayHasKey('actionids', $response['result'], $ep);
		$this->assertEquals(1, count($response['result']['actionids']), $ep);

		for ($i = 0; $i < $templateNumber; $i++) {
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

		for ($i = 0; $i < $templateNumber; $i++) {
			$params = array_merge(
				$LLDParametrs,
				['hostid' => self::$templateids[$i]]
			);
			$response = $this->call('discoveryrule.create', $params);

			$ep = json_encode($response, JSON_PRETTY_PRINT);

			$this->assertArrayHasKey('itemids', $response['result'], $ep);
			$this->assertArrayHasKey(0, $response['result']['itemids'], $ep);
		}

		$templateids_for_api_call = [];
		foreach (self::$templateids as $entry) {
			$t = ['templateid' => $entry];
			array_push($templateids_for_api_call, $t);
		}

		$response = $this->call('action.create', [
			'name' => 'link_templates',
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
					'optemplate' => $templateids_for_api_call
				]
			]
		]);
		self::$actionLinkTemplateId = $response['result']['actionids'][0];
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

		$this->assertArrayHasKey('result', $response, json_encode($response));
		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$response = $this->call('host.update', [
			'hostid' => $hostid,
			'templates' => []
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
	}

	private function deleteActionsAndTemplates () {

		$response = $this->call('action.delete',[self::$actionId]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		$response = $this->call('action.delete',[self::$actionLinkTemplateId]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		for ($i = 0; $i < count(self::$templateids); $i++) {
			$response = $this->call('template.delete', [self::$templateids[$i]]);
			$this->assertArrayHasKey('templateids', $response['result']);
			$this->assertEquals(1, count($response['result']['templateids']));
		}
		self::$templateids = [];
	}

	private function linkingTestLogic($LLDRuleType){

		$this->killComponent(self::COMPONENT_AGENT);
		$this->setupAutoregToLinkTemplates(self::NUMBER_OF_TEMPLATES_TEST_2, $LLDRuleType);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->metaDataItemUpdate();
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_db_copy_template_elements():SUCCEED', true, 120);
		$this->stopComponent(self::COMPONENT_AGENT);
		$this->unlinkTemplates();
		$this->metaDataItemUpdate();
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_db_copy_template_elements():SUCCEED', true, 120);
		$this->deleteActionsAndTemplates();
	}

	/*
	Test ensures that the Zabbix auto-registration process correctly handles template with LLD rule linking
	conflicts and re-registration behavior.

	In the first scenario, two templates are linked to a host via auto-registration. Both templates contain the same
	LLD rule with identical keys. The templates should not be linked due to the conflict in LLD rule keys.

	In the second scenario, a single template is linked to a host through auto-registration, then unlinked. The host is
	restarted with different metadata. The template gets linked this time with conflicts resolved.
	*/

	/**
	 * Test LLD linking cases with conflicts.
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent
	 * @backup actions,hosts,host_tag,autoreg_host
	 */
	public function testLinkingLLD_conflict() {

		$this->killComponent(self::COMPONENT_AGENT);
		$this->setupAutoregToLinkTemplates(self::NUMBER_OF_TEMPLATES_TEST_1,self::LLD_RULE_MACRO_PATH);
		$this->metaDataItemUpdate();
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_db_copy_template_elements():FAIL', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'cannot link template(s) "TEMPLATE_NAME_0", "TEMPLATE_NAME_1" to host "test_lld_linking": ' .
			'conflicting item key "lld" found',
			true, 120);
		$this->stopComponent(self::COMPONENT_AGENT);
		$this->unlinkTemplates();
		$this->deleteActionsAndTemplates();

		$this->setupAutoregToLinkTemplates(self::NUMBER_OF_TEMPLATES_TEST_2,self::LLD_RULE_MACRO_PATH);
		$this->metaDataItemUpdate();
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_db_copy_template_elements():SUCCEED', true, 120);
		$this->stopComponent(self::COMPONENT_AGENT);
		$this->unlinkTemplates();
		$this->metaDataItemUpdate();
		$this->startComponent(self::COMPONENT_AGENT);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_db_copy_template_elements():SUCCEED', true, 120);
		$this->deleteActionsAndTemplates();
	}

	/**
	 * Test LLD linking cases with different parameters.
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent
	 * @backup actions,hosts,host_tag,autoreg_host
	 */

	public function testLinkingLLD_manyItems() {
		$this->linkingTestLogic(self::LLD_RULE_FILTER);
		$this->linkingTestLogic(self::LLD_RULE_CUSTOM_QUERY_FIELDS);
		$this->linkingTestLogic(self::LLD_RULE_PREPROCESSING);
		$this->linkingTestLogic(self::LLD_RULE_SCRIPT);
	}
}

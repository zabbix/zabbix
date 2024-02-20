<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test suite for trigger linking.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_trigger_linking
 * @backup history
 */
class testTriggerLinking extends CIntegrationTest {
	const HOST_NAME = 'test_trigger_linking';

	const TEMPLATE_NAME_PRE = 'strata_template_name';

	const ITEM_NAME_PRE = 'strata_item_name';
	const ITEM_KEY_PRE = 'strata_item_key';
	const TAG_NAME_PRE = 'strata_tag';
	const TAG_VALUE_PRE = 'strata_value';
	const TRIGGER_DESCRIPTION_PRE = 'strata_trigger_description';
	const TRIGGER_DESCRIPTION_SAME_ALL = 'zstrata_same_description_on_all_templates';

	const TRIGGER_PRIORITY = 4;
	const TRIGGER_STATUS = 1;
	const TRIGGER_COMMENTS_PRE = 'strata_comment';
	const TRIGGER_URL_PRE = 'strata_url';
	const TRIGGER_TYPE = 1;
	const TRIGGER_RECOVERY_MODE = 1;
	const TRIGGER_CORRELATION_MODE = 1;
	const TRIGGER_CORRELATION_TAG_PRE = 'strata_correlation_tag';
	const TRIGGER_CORRELATION_TAG_FOR_NEW_TEMPLATE = 'Xtag';
	const TRIGGER_MANUAL_CLOSE = 1;
	const TRIGGER_OPDATA_PRE = 'strata_opdata';
	const TRIGGER_EVENT_NAME_PRE = 'strata_event_name';

	const NUMBER_OF_TEMPLATES = 10;
	const NUMBER_OF_TRIGGERS_PER_TEMPLATE = 20;

	/* When resolving conflicts during linking - trigger is considered unique if its description and expression match
		every template contains a set of triggers with:
		1) unique description and unique expression
		2) same description and unique expression. */

	private static $templateids = array();
	private static $stringids = array();

	/* Template X will be linked after the initial set of templates get linked and then unliked (without clear). */
	private static $templateX_ID;
	private static $templateX_name = 'templateX';

	/* Linking initial set of templates, need an ID to delete this, as during the second linking
		only templateX will be linked. */
	private static $firstActionID;

	public function createTemplates() {

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {
			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PRE . "_" . $i,
				'groups' => [
					'groupid' => 1
				]]);
			$ep = json_encode($response, JSON_PRETTY_PRINT);

			$this->assertArrayHasKey('templateids', $response['result'], $ep);
			$this->assertArrayHasKey(0, $response['result']['templateids'], $ep);

			array_push(self::$templateids, $response['result']['templateids'][0]);
		}


		/* Create special template X, that will have trigger description (but not expression) conflict
			with triggers from the first template. It will be linked in the separation action from
			all other templates. (when agent2 with new host metadata starts). */
		$response = $this->call('template.create', [
			'host' =>  self::$templateX_name,
				'groups' => [
					'groupid' => 1
				]]);
			$ep = json_encode($response, JSON_PRETTY_PRINT);

			$this->assertArrayHasKey('templateids', $response['result'], $ep);
			$this->assertArrayHasKey(0, $response['result']['templateids'], $ep);

			self::$templateX_ID = $response['result']['templateids'][0];
	}

	public function setupActions()
	{
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

		$templateids_for_api_call = [];
		foreach (self::$templateids as $entry) {
			$t = ['templateid' => $entry];
			array_push($templateids_for_api_call, $t);
		}

		$response = $this->call('action.create', [
			'name' => 'link_templates',
			'eventsource' => 2,
			'status' => 0,
			'operations' => [
				[
					'operationtype' => 6,
					'optemplate' =>
					$templateids_for_api_call
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$firstActionID = $response['result']['actionids'][0];
	}

	public function setupActions2()
	{
		$response = $this->call('action.delete', [self::$firstActionID]);
		$ep = json_encode($response, JSON_PRETTY_PRINT);
		$this->assertEquals(1, count($response['result']), $ep);

		$this->reloadConfigurationCache();

		$templateids_for_api_call_collision_description = [];
		array_push($templateids_for_api_call_collision_description, ['templateid' => self::$templateX_ID]);

		$response = $this->call('action.create', [
			'name' => 'link_templates',
			'eventsource' => 2,
			'status' => 0,
			'operations' => [
				[
					'operationtype' => 6,
					'optemplate' =>
					$templateids_for_api_call_collision_description
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
	}

	/**
	* @inheritdoc
	*/
	public function prepareData() {

		$z = 'a';
		/* There is divide by 2, since we create 2 triggers in every stage*/
		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES * self::NUMBER_OF_TRIGGERS_PER_TEMPLATE / 2; $i++)
		{
			array_push(self::$stringids, $z);
			$z++;
		}
		sort(self::$stringids);

		$this->createTemplates();

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES * self::NUMBER_OF_TRIGGERS_PER_TEMPLATE / 2; $i++) {

			$templ_counter = floor($i / self::NUMBER_OF_TEMPLATES);
			$templateid_loc = self::$templateids[$templ_counter];
			$response = $this->call('item.create', [
				'hostid' => $templateid_loc,
				'name' => self::ITEM_NAME_PRE . "_" . self::$stringids[$i],
				'key_' => self::ITEM_KEY_PRE . "_" . self::$stringids[$i],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);

			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));

			$response = $this->call('trigger.create', [
				'description' => self::TRIGGER_DESCRIPTION_PRE . "_" . self::$stringids[$i],
				'priority' => self::TRIGGER_PRIORITY,
				'status' => self::TRIGGER_STATUS,
				'comments' => self::TRIGGER_COMMENTS_PRE . "_" . self::$stringids[$i],
				'url' => self::TRIGGER_URL_PRE . "_" . self::$stringids[$i],
				'type' => self::TRIGGER_TYPE,
				'recovery_mode' => self::TRIGGER_RECOVERY_MODE,
				'correlation_mode' => self::TRIGGER_CORRELATION_MODE,
				'correlation_tag' => self::TRIGGER_CORRELATION_TAG_PRE . "_" . self::$stringids[$i],
				'manual_close' => self::TRIGGER_MANUAL_CLOSE,
				'opdata' => self::TRIGGER_OPDATA_PRE . "_" . self::$stringids[$i],
				'event_name' => self::TRIGGER_EVENT_NAME_PRE . "_" . self::$stringids[$i],
				'expression' => 'last(/' . self::TEMPLATE_NAME_PRE . "_" . $templ_counter . '/' .
				self::ITEM_KEY_PRE . "_" . self::$stringids[$i] . ')=2',
				'recovery_expression' => 'last(/' . self::TEMPLATE_NAME_PRE . "_" . $templ_counter . '/' .
				self::ITEM_KEY_PRE . "_" . self::$stringids[$i] . ')=3',
				'tags' => [
					[
						'tag' => self::TAG_NAME_PRE . "_" . self::$stringids[$i],
						'value' => self::TAG_VALUE_PRE . "_" . self::$stringids[$i]
					]
				]
			]);

			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['triggerids']);

			/* also create trigger that would have the SAME description across all templates
				(but different expression, otherwise templates will not be able to exist on host
				at the same time due to conflict) */
			$response_2 = $this->call('trigger.create', [
				'description' => self::TRIGGER_DESCRIPTION_SAME_ALL,
				'priority' => self::TRIGGER_PRIORITY,
				'status' => self::TRIGGER_STATUS,

				'comments' => self::TRIGGER_COMMENTS_PRE . "_" . self::$stringids[$i],
				'url' => self::TRIGGER_URL_PRE . "_" . self::$stringids[$i],
				'type' => self::TRIGGER_TYPE,
				'recovery_mode' => self::TRIGGER_RECOVERY_MODE,
				'correlation_mode' => self::TRIGGER_CORRELATION_MODE,
				'correlation_tag' => self::TRIGGER_CORRELATION_TAG_PRE . "_" . self::$stringids[$i],
				'manual_close' => self::TRIGGER_MANUAL_CLOSE,
				'opdata' => self::TRIGGER_OPDATA_PRE . "_" . self::$stringids[$i],
				'event_name' => self::TRIGGER_EVENT_NAME_PRE . "_" . self::$stringids[$i],
				'expression' => 'last(/' . self::TEMPLATE_NAME_PRE . "_" . $templ_counter . '/' .
				self::ITEM_KEY_PRE . "_" . self::$stringids[$i] . ')=2',
				'recovery_expression' => 'last(/' . self::TEMPLATE_NAME_PRE . "_" . $templ_counter . '/' .
				self::ITEM_KEY_PRE . "_" . self::$stringids[$i] . ')=3',

				'dependencies' => [
						['triggerid' => $response['result']['triggerids'][0]]
						],

				'tags' => [
					[
						'tag' => self::TAG_NAME_PRE . "_" . self::$stringids[$i],
						'value' => self::TAG_VALUE_PRE . "_" . self::$stringids[$i]
					]
				]
			]);

			$this->assertArrayHasKey('triggerids', $response_2['result']);
			$this->assertArrayHasKey(0, $response_2['result']['triggerids']);
		}

		$response = $this->call('item.create', [
				'hostid' => self::$templateX_ID,
				'name' => "templateX_item_name",
				'key_' => "templateX_item_key",
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('trigger.create', [
				'description' =>  self::TRIGGER_DESCRIPTION_SAME_ALL,
				'priority' => self::TRIGGER_PRIORITY,
				'status' => self::TRIGGER_STATUS,
				'type' => self::TRIGGER_TYPE,
				'recovery_mode' => self::TRIGGER_RECOVERY_MODE,
				'correlation_mode' => self::TRIGGER_CORRELATION_MODE,
				'correlation_tag' => self::TRIGGER_CORRELATION_TAG_FOR_NEW_TEMPLATE,
				'manual_close' => self::TRIGGER_MANUAL_CLOSE,
				'expression' => 'last(/' .  self::$templateX_name . '/' .
				"templateX_item_key" . ')=99',
				'recovery_expression' => 'last(/' .  self::$templateX_name . '/' .
				"templateX_item_key" . ')=999',
				'tags' => [
					[
						'tag' => "templateX_tag",
						'value' => "templateX_value"
					]
				]
			]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		$this->setupActions();

		return true;
	}

	/**
	* Component configuration provider for server related tests.
	*
	* @return array
	*/
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
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
				'Hostname'		=>  self::HOST_NAME,
				'ServerActive'	=>
						'127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'DebugLevel'    => 4,
				'LogFileSize'   => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_AGENT),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent.pid',
				'HostMetadata' => 'first'
			],

			self::COMPONENT_AGENT2 => [
				'Hostname'		=>  self::HOST_NAME,
				'ServerActive'	=>
						'127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'DebugLevel'    => 4,
				'LogFileSize'   => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_AGENT2),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent2.pid',
				'HostMetadata' => 'second'
			]
		];
	}

	public function checkTriggersCreate() {

		$response = $this->call('host.get', ['filter' => ['host' => self::HOST_NAME]]);
		$this->assertArrayHasKey(0, $response['result'], json_encode($response, JSON_PRETTY_PRINT));
		$this->assertArrayHasKey('host', $response['result'][0]);

		$response = $this->call('trigger.get', [
			'selectTags' => 'extend',
			'filter' => [
				'host' => self::HOST_NAME
			],
			'output' => [
				'triggerid',
				'description',
				'priority',
				'status',
				'templateid',
				'comments',
				'url',
				'type',
				'flags',
				'recovery_mode',
				'correlation_mode',
				'correlation_tag',
				'manual_close',
				'opdata',
				'discover',
				'event_name',
				'functions',
				'expression',
				'recovery_expression'
			],
			'selectFunctions' => 'extend',
			'sortfield' => 'description',
			'selectDependencies' => ['triggerid']
		]);

		$totalExpectedTriggers = self::NUMBER_OF_TEMPLATES * self::NUMBER_OF_TRIGGERS_PER_TEMPLATE;
		$this->assertEquals($totalExpectedTriggers, count($response['result']));

		$i = 0;
		foreach ($response['result'] as $entry) {
			$ep = json_encode($entry, JSON_PRETTY_PRINT);

			$this->assertArrayHasKey('tags', $entry, $ep);
			$this->assertArrayHasKey(0, $entry['tags'], $ep);
			$this->assertArrayHasKey('tag', $entry['tags'][0], $ep);

			if ($entry['description'] == self::TRIGGER_DESCRIPTION_SAME_ALL)
			{
				$this->assertArrayHasKey(0, $entry['dependencies']);
				continue;
			}

			$this->assertEquals(self::TAG_NAME_PRE . "_" . self::$stringids[$i], $entry['tags'][0]['tag'], $ep);

			$this->assertEquals($entry['description'], self::TRIGGER_DESCRIPTION_PRE . "_" . self::$stringids[$i], $ep);

			$this->assertEquals($entry['priority'],    self::TRIGGER_PRIORITY, $ep);
			$this->assertEquals($entry['status'],      self::TRIGGER_STATUS, $ep);
			$this->assertEquals($entry['comments'],    self::TRIGGER_COMMENTS_PRE . "_" . self::$stringids[$i], $ep);
			$this->assertEquals($entry['url'],         self::TRIGGER_URL_PRE . "_" . self::$stringids[$i], $ep);
			$this->assertEquals($entry['type'],        self::TRIGGER_TYPE, $ep);

			$this->assertEquals($entry['recovery_mode'],    self::TRIGGER_RECOVERY_MODE, $ep);
			$this->assertEquals($entry['correlation_mode'], self::TRIGGER_CORRELATION_MODE, $ep);
			$this->assertEquals($entry['correlation_tag'],  self::TRIGGER_CORRELATION_TAG_PRE . "_" .
					self::$stringids[$i], $ep);
			$this->assertEquals($entry['manual_close'],     self::TRIGGER_MANUAL_CLOSE, $ep);
			$this->assertEquals($entry['opdata'],           self::TRIGGER_OPDATA_PRE . "_" . self::$stringids[$i],
					$ep);
			$this->assertEquals($entry['event_name'],       self::TRIGGER_EVENT_NAME_PRE . "_" . self::$stringids[$i],
					$ep);
			$this->assertEquals($entry['functions'][0]['parameter'], '$', $ep);
			$this->assertEquals($entry['functions'][0]['function'], 'last', $ep);
			$this->assertEquals($entry['expression'],  "{{$entry['functions'][0]['functionid']}}=2", $ep);
			$this->assertEquals($entry['recovery_expression'],  "{{$entry['functions'][0]['functionid']}}=3", $ep);

			$i++;
		}
	}

	/**
	 * Test trigger linking cases.
	 *
	 * @configurationDataProvider agentConfigurationProvider
	 * @required-components server, agent, agent2
	 */
	public function testTriggerLinking_checkMe() {

		/* We need agent 2 only because it will have the different host metadata from the agent 1.
			This would retrigger the autoregistration with linking. Stop this for now.
			If I knew how to change host metadata of agent 1 in integration test - I would not need agent2. */
		$this->stopComponent(self::COMPONENT_AGENT2);

		$this->reloadConfigurationCache();

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, ['End of DBregister_host_active():SUCCEED']);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBcopy_template_elements', true, 120);
		$this->checkTriggersCreate();

		$this->setupActions2();

		$this->stopComponent(self::COMPONENT_AGENT);
		$this->reloadConfigurationCache();

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => [
					self::HOST_NAME
				]
			]
		]);

		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));

		$hostid =  $response['result'][0]['hostid'];

		$response = CDataHelper::call('host.update', [
			'hostid' => $hostid,
			'templates' => []
		]);
		sleep(5);
		$this->reloadConfigurationCache();
		sleep(5);

		$sql = "select templateid from hosts_templates where hostid='".$hostid."';";
		$this->assertEquals(0, CDBHelper::getCount($sql));

		$this->startComponent(self::COMPONENT_AGENT2);
		//sleep(5);
		//$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'query [txnlev:1] [insert into triggers', true, 120);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBcopy_template_elements', true, 120);
		$this->reloadConfigurationCache();
		sleep(5);

		$response = $this->call('trigger.get', [
			'selectTags' => 'extend',
			'filter' => [
				'host' => self::HOST_NAME,
				'description' => self::TRIGGER_DESCRIPTION_SAME_ALL,
				'correlation_tag' => self::TRIGGER_CORRELATION_TAG_FOR_NEW_TEMPLATE
			],
			'output' => [
				'triggerid',
				'description',
				'priority',
				'status',
				'templateid',
				'comments',
				'url',
				'type',
				'flags',
				'recovery_mode',
				'correlation_mode',
				'correlation_tag',
				'manual_close',
				'opdata',
				'discover',
				'event_name',
				'functions',
				'expression',
				'recovery_expression'
			],
			'selectFunctions' => 'extend',
			'sortfield' => 'description',
			'selectDependencies' => ['triggerid']
		]);

		$this->assertEquals(1, count($response['result']), json_encode($response['result']));

		$entry = $response['result'][0];

		$ep = json_encode($entry, JSON_PRETTY_PRINT);

		$this->assertArrayHasKey('tags', $entry, $ep);
		$this->assertArrayHasKey(0, $entry['tags'], $ep);
		$this->assertArrayHasKey('tag', $entry['tags'][0], $ep);
		$this->assertEquals('templateX_tag', $entry['tags'][0]['tag'], $ep);
		$this->assertEquals($entry['description'], self::TRIGGER_DESCRIPTION_SAME_ALL, $ep);
		$this->assertEquals($entry['priority'],    self::TRIGGER_PRIORITY, $ep);
		$this->assertEquals($entry['status'],      self::TRIGGER_STATUS, $ep);
		$this->assertEquals($entry['type'],        self::TRIGGER_TYPE, $ep);
		$this->assertEquals($entry['recovery_mode'],    self::TRIGGER_RECOVERY_MODE, $ep);
		$this->assertEquals($entry['correlation_mode'], self::TRIGGER_CORRELATION_MODE, $ep);
		$this->assertEquals($entry['manual_close'],     self::TRIGGER_MANUAL_CLOSE, $ep);
		$this->assertEquals($entry['expression'],  "{{$entry['functions'][0]['functionid']}}=99", $ep);
		$this->assertEquals($entry['recovery_expression'],  "{{$entry['functions'][0]['functionid']}}=999", $ep);

		// $x = self::getLogPath(self::COMPONENT_SERVER);
		// $this->assertEquals('a', 'b',  $x);
	}
}

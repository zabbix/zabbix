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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test suite for tag inheritance.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testTagInheritance extends CIntegrationTest {
	const TEMPLATE_NAME_PREFIX = 'template_tag_inheritance_';
	const HOST_NAME = 'host_tag_inheritance';

	const HOST_ITEM_NAME = 'item_name_tag_inheritance';
	const HOST_ITEM_KEY = 'item_key_tag_inheritance';
	const TEMPLATE_ITEM_NAME_PREFIX = 'strataX_item_name_tag_inheritance_';
	const TEMPLATE_ITEM_KEY_PREFIX = 'strataX_item_key_tag_inheritance_';

	const TEMPLATE_TAG_NAME_PREFIX = 'strataX_template_tag_tag_inheritance_';
	const TEMPLATE_TAG_VALUE_PREFIX = 'strataX_template_value_tag_inheritance_';
	const HOST_TAG_NAME = 'host_tag_tag_inheritance';
	const HOST_TAG_VALUE = 'host_value_tag_inheritance';

	const TEMPLATE_ITEM_TAG_NAME_PREFIX = 'strataX_item_tag_tag_inheritance_';
	const TEMPLATE_ITEM_TAG_VALUE_PREFIX = 'strataX_item_value_tag_inheritance_';
	const HOST_ITEM_TAG_NAME = 'host_item_tag_tag_inheritance';
	const HOST_ITEM_TAG_VALUE = 'host_item_value_tag_inheritance';

	const TEMPLATE_TRIGGER_TAG_NAME_PREFIX = 'strataX_trigger_tag_tag_inheritance_';
	const TEMPLATE_TRIGGER_TAG_VALUE_PREFIX = 'strataX_trigger_value_tag_inheritance_';
	const HOST_TRIGGER_TAG_NAME = 'host_trigger_tag_tag_inheritance';
	const HOST_TRIGGER_TAG_VALUE = 'host_trigger_value_tag_inheritance';

	const TRIGGER_DESCRIPTION_PREFIX = 'strataX_trigger_description_tag_inheritance_';
	const TRIGGER_HOST_DESCRIPTION = 'host_trigger_description_tag_inheritance';

	const TRIGGER_PRIORITY = 4;
	const TRIGGER_TYPE = 1;
	const TRIGGER_CORRELATION_MODE = 1;
	const TRIGGER_CORRELATION_TAG = 'strataX_correlation_tag';
	const TRIGGER_CORRELATION_TAG_FOR_NEW_TEMPLATE = 'Xtag';
	const TRIGGER_MANUAL_CLOSE = 1;
	const TRIGGER_OPDATA = 'strataX_opdata';

	const NUMBER_OF_TEMPLATES = 10;

	const VALUE_TO_FIRE_TRIGGER = 11;

	private static $template_ids = array();
	private static $item_ids = array();
	private static $trigger_ids = array();

	private static $host_id;
	private static $host_item_id;
	private static $host_trigger_id;

	private static $event_response;
	private static $event_ids = array();

	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051)
			]
		];
	}

	/**
	 * Initializes templates.
	 *
	 * @param bool $hasTemplate does the templates itself links to another template (end of self::$template_ids array)
	 * @param int $n postfix when naming templates and their components
	 */
	private function createTemplate($hasTemplate, $n) {
		/* First template has no templates linked to it. */
		if ($hasTemplate) {
			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PREFIX . $n,
				'tags' => [
					[
						'tag' => self::TEMPLATE_TAG_NAME_PREFIX . $n,
						'value' => self::TEMPLATE_TAG_VALUE_PREFIX . $n
					]
				],
				'templates' => [['templateid' => end(self::$template_ids)]],
				'groups' => [
					'groupid' => 1 // Templates
				]]);
		} else {
			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PREFIX . $n,
				'tags' => [
					[
						'tag' => self::TEMPLATE_TAG_NAME_PREFIX . $n,
						'value' => self::TEMPLATE_TAG_VALUE_PREFIX . $n
					]
				],
				'groups' => [
					'groupid' => 1 // Templates
				]]);
		}

		$ep = json_encode($response, JSON_PRETTY_PRINT);

		$this->assertArrayHasKey('templateids', $response['result'], $ep);
		$this->assertArrayHasKey(0, $response['result']['templateids'], $ep);

		array_push(self::$template_ids, $response['result']['templateids'][0]);

		$response = $this->call('item.create', [
			'hostid' => end(self::$template_ids),
			'name' => self::TEMPLATE_ITEM_NAME_PREFIX . $n,
			'key_' => self::TEMPLATE_ITEM_KEY_PREFIX . $n,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'tags' => [
				[
					'tag' => self::TEMPLATE_ITEM_TAG_NAME_PREFIX . $n,
					'value' => self::TEMPLATE_ITEM_TAG_VALUE_PREFIX . $n
				]
			]
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		array_push(self::$item_ids, $response['result']['itemids'][0]);

		$response = $this->call('trigger.create', [
			'description' => self::TRIGGER_DESCRIPTION_PREFIX . $n,
			'priority' => self::TRIGGER_PRIORITY,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => self::TRIGGER_TYPE,
			'recovery_mode' => ZBX_RECOVERY_MODE_NONE,
			'manual_close' => self::TRIGGER_MANUAL_CLOSE,
			'expression' => 'last(/' . self::TEMPLATE_NAME_PREFIX . $n . '/' .
				self::TEMPLATE_ITEM_KEY_PREFIX . $n . ')=' . self::VALUE_TO_FIRE_TRIGGER,
			'tags' => [
				[
					'tag' => self::TEMPLATE_TRIGGER_TAG_NAME_PREFIX . $n,
					'value' => self::TEMPLATE_TRIGGER_TAG_VALUE_PREFIX . $n
				]
			]
		]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		array_push(self::$trigger_ids, $response['result']['triggerids'][0]);
	}

	public function prepareData() {
		$this->createTemplate(false, 0);

		/* Every newly created template links to the previously created template. */
		for ($i = 1; $i < self::NUMBER_OF_TEMPLATES; $i++) {
			$this->createTemplate(true, $i);
		}

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'groups' => ['groupid' => 4], // Zabbix servers
			'status' => HOST_STATUS_MONITORED,
			'templates' => [['templateid' => end(self::$template_ids)]],
			'tags' => [
				[
					'tag' => self::HOST_TAG_NAME,
					'value' => self::HOST_TAG_VALUE
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$host_id = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$host_id,
			'name' => self::HOST_ITEM_NAME,
			'key_' => self::HOST_ITEM_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'tags' => [
				[
					'tag' => self::HOST_ITEM_TAG_NAME,
					'value' => self::HOST_ITEM_TAG_VALUE
				]
			]
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$host_item_id =  $response['result']['itemids'][0];

		$response = $this->call('trigger.create', [
			'description' => self::TRIGGER_HOST_DESCRIPTION,
			'priority' => self::TRIGGER_PRIORITY,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => self::TRIGGER_TYPE,
			'recovery_mode' => ZBX_RECOVERY_MODE_NONE,
			'manual_close' => self::TRIGGER_MANUAL_CLOSE,
			'expression' => 'last(/' . self::HOST_NAME . '/' .
				self::HOST_ITEM_KEY . ')=' . self::VALUE_TO_FIRE_TRIGGER,
			'tags' => [
				[
					'tag' => self::HOST_TRIGGER_TAG_NAME,
					'value' => self::HOST_TRIGGER_TAG_VALUE
				]
			]
		]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		self::$host_trigger_id =  $response['result']['triggerids'][0];
	}

	public function testInheritedTags() {
		$this->sendSenderValue(self::HOST_NAME, self::TEMPLATE_ITEM_KEY_PREFIX . '0', self::VALUE_TO_FIRE_TRIGGER);

		self::$event_response = $this->callUntilDataIsPresent('event.get', [
			'hostids' => [self::$host_id],
			'selectTags' => ['tag', 'value']
		], 5, 2);

		$this->assertCount(1, self::$event_response['result']);

		array_push(self::$event_ids, self::$event_response['result'][0]['eventid']);

		$expected_template_tags = [
			["tag" => "host_tag_tag_inheritance", "value" => "host_value_tag_inheritance"],
			["tag" => "strataX_item_tag_tag_inheritance_0", "value" => "strataX_item_value_tag_inheritance_0"],
			["tag" => "strataX_template_tag_tag_inheritance_0", "value" => "strataX_template_value_tag_inheritance_0"],
			["tag" => "strataX_template_tag_tag_inheritance_1", "value" => "strataX_template_value_tag_inheritance_1"],
			["tag" => "strataX_template_tag_tag_inheritance_2", "value" => "strataX_template_value_tag_inheritance_2"],
			["tag" => "strataX_template_tag_tag_inheritance_3", "value" => "strataX_template_value_tag_inheritance_3"],
			["tag" => "strataX_template_tag_tag_inheritance_4", "value" => "strataX_template_value_tag_inheritance_4"],
			["tag" => "strataX_template_tag_tag_inheritance_5", "value" => "strataX_template_value_tag_inheritance_5"],
			["tag" => "strataX_template_tag_tag_inheritance_6", "value" => "strataX_template_value_tag_inheritance_6"],
			["tag" => "strataX_template_tag_tag_inheritance_7", "value" => "strataX_template_value_tag_inheritance_7"],
			["tag" => "strataX_template_tag_tag_inheritance_8", "value" => "strataX_template_value_tag_inheritance_8"],
			["tag" => "strataX_template_tag_tag_inheritance_9", "value" => "strataX_template_value_tag_inheritance_9"],
			["tag" => "strataX_trigger_tag_tag_inheritance_0", "value" => "strataX_trigger_value_tag_inheritance_0"]];


		$result_tags = self::$event_response['result'][0]['tags'];
		sort($result_tags);
		$this->assertEquals(json_encode($expected_template_tags), json_encode($result_tags));

		$this->sendSenderValue(self::HOST_NAME, self::HOST_ITEM_KEY, self::VALUE_TO_FIRE_TRIGGER);
		self::$event_response = $this->callUntilDataIsPresent('event.get', [
			'hostids' => [self::$host_id],
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true
		], 5, 2);

		$this->assertCount(2, self::$event_response['result']);

		$expected_no_template_tags = [
			["tag" => "host_item_tag_tag_inheritance", "value" => "host_item_value_tag_inheritance"],
			["tag" => "host_tag_tag_inheritance", "value" => "host_value_tag_inheritance"],
			["tag" => "host_trigger_tag_tag_inheritance", "value" => "host_trigger_value_tag_inheritance"]
		];

		/* Need to find out the second event id and we have array of 2 event ids and know the id of the first event. */
		$res_event_ids = array_keys(self::$event_response['result']);
		if ($res_event_ids[0] == end(self::$event_ids)) {
			array_push(self::$event_ids, $res_event_ids[1]);
		} else {
			array_push(self::$event_ids, $res_event_ids[0]);
		}

		$result_tags = self::$event_response['result'][end(self::$event_ids)]['tags'];

		sort($result_tags);
		$this->assertEquals(json_encode($expected_no_template_tags), json_encode($result_tags));
	}

	public static function clearData(): void {
		DB::delete('events', ['eventid' => self::$event_ids]);

		CDataHelper::call('trigger.delete', [self::$host_trigger_id]);
		CDataHelper::call('trigger.delete', self::$trigger_ids);

		CDataHelper::call('item.delete', [self::$host_item_id]);
		CDataHelper::call('item.delete', self::$item_ids);

		CDataHelper::call('host.delete', [self::$host_id]);

		CDataHelper::call('template.delete', self::$template_ids);
	}
}

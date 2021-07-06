<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Test suite for graph linking.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_graph_linking
 * @backup history
 */
class testGraphLinking extends CIntegrationTest {
	const HOST_NAME = 'test_graph_linking';

	const TEMPLATE_NAME_PRE = 'strata_template_name';

	const ITEM_NAME_PRE = 'strata_item_name';
	const ITEM_KEY_PRE = 'strata_item_key';

	const GRAPH_HEIGHT = 33;
	const GRAPH_WIDTH = 319;
	const GRAPH_NAME_PRE = 'strata_graph_name';
	const GRAPH_TYPE = 1;
	const GRAPH_PERCENT_LEFT = 1;
	const GRAPH_PERCENT_RIGHT = 100;
	const GRAPH_SHOW_3D = 1;
	const GRAPH_SHOW_LEGEND = 1;
	const GRAPH_SHOW_WORK_PERIOD = 1;
	const GRAPH_SHOW_TRIGGERS = 1;
	const GRAPH_YAXISMAX = 417;
	const GRAPH_YAXISMIN = 18;

	const GRAPH_YMAX_TYPE = 2;
	const GRAPH_YMIN_TYPE = 1;

	const GRAPH_ITEM_COLOR = '00AA00';

	const NUMBER_OF_TEMPLATES = 5;
	const NUMBER_OF_GRAPHS_PER_TEMPLATE = 5;

	private static $templateids = array();

	public function createTemplates() {

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {

			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PRE . "_" . $i,
				'groups' => [
					'groupid' => 1
				]]);

			$this->assertArrayHasKey('templateids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['templateids']);

			array_push(self::$templateids, $response['result']['templateids'][0]);
		}
	}

	public function setupActions()
	{
		$response = $this->call('action.create', [
			'name' => 'create_host',
			'eventsource' => 2,
			'status' => 0,
			'host' => self::HOST_NAME,
			'operations' => [
				[
					'actionid' => 1,
					'operationtype' => 2
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

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
					'actionid' => 12,
					'operationtype' => 6,
					'optemplate' =>
					$templateids_for_api_call
				]
			],
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
	}

	/**
	* @inheritdoc
	*/
	public function prepareData() {

		$this->createTemplates();

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES * self::NUMBER_OF_GRAPHS_PER_TEMPLATE; $i++) {
			$templ_counter = floor($i / self::NUMBER_OF_TEMPLATES);
			$templateid_loc = self::$templateids[$templ_counter];
			$response = $this->call('item.create', [
				'hostid' => $templateid_loc,
				'name' => self::ITEM_NAME_PRE . "_" . $i,
				'key_' => self::ITEM_KEY_PRE . "_" . $i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);

			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));
			$itemid = $response['result']['itemids'][0];

			$response = $this->call('graph.create', [
				'height' => self::GRAPH_HEIGHT + $i,
				'width' => self::GRAPH_WIDTH + $i,
				'name' => self::GRAPH_NAME_PRE . '_' . $i,
				'graphtype' => self::GRAPH_TYPE,
				'percent_left' => self::GRAPH_PERCENT_LEFT + $i,
				'percent_right' => self::GRAPH_PERCENT_RIGHT - $i,
				'show_3d' => self::GRAPH_SHOW_3D,
				'show_legend' => self::GRAPH_SHOW_LEGEND,
				'show_work_period' => self::GRAPH_SHOW_WORK_PERIOD,
				'show_triggers' => self::GRAPH_SHOW_TRIGGERS,
				'yaxismax' => self::GRAPH_YAXISMAX + $i,
				'yaxismin' => self::GRAPH_YAXISMIN + $i,
				'ymax_itemid' => $itemid,
				'ymax_type' => self::GRAPH_YMAX_TYPE,
				'ymin_type' => self::GRAPH_YMIN_TYPE,

				'gitems' => [
					[
						'itemid' => $itemid,
						'color' => self::GRAPH_ITEM_COLOR
					]
				]
			]);
		}

		$this->setupActions();

		return true;
	}

	/**
	* Component configuration provider for agent related tests.
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
				'Hostname'		=>  self::HOST_NAME,
				'ServerActive'	=>
						'127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'DebugLevel'    => 4,
				'LogFileSize'   => 0,
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent.pid'
			]
		];
	}

	public function checkGraphsCreate() {

		$response = $this->call('host.get', ['filter' => ['host' => self::HOST_NAME]]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('host', $response['result'][0]);
		$hostid = $response['result'][0]['hostid'];

		$response = $this->call('item.get', ['hostids' => $hostid]);
		$this->assertArrayHasKey(0, $response['result']);
		$item_data = $response['result'];

		$itemids = array();
		foreach ($item_data as $entry) {
			array_push($itemids, $entry['itemid']);
		}
		sort($itemids);

		$response = $this->call('graph.get', [
			'selectTags' => 'extend',
			'filter' => [
				'host' => self::HOST_NAME
			],
			'output' => [
				'graphid',
				'height',
				'width',
				'name',
				'graphtype',
				'percent_left',
				'percent_right',
				'show_3d',
				'show_legend',
				'show_work_period',
				'show_triggers',
				'yaxismax',
				'yaxismin',
				'ymax_itemid',
				'ymax_type',
				'ymin_type',
				'selectGraphItems'
			],
			'selectFunctions' => 'extend',
			'sortfield' => 'graphid'
		]
		);

		$this->assertEquals(self::NUMBER_OF_TEMPLATES * self::NUMBER_OF_GRAPHS_PER_TEMPLATE,
							count($response['result']));

		$i = 0;
		foreach ($response['result'] as $entry) {

			$this->assertEquals($entry['height'], self::GRAPH_HEIGHT + $i);
			$this->assertEquals($entry['width'], self::GRAPH_WIDTH + $i);
			$this->assertEquals($entry['name'], self::GRAPH_NAME_PRE . '_' . $i);
			$this->assertEquals($entry['graphtype'], self::GRAPH_TYPE);
			$this->assertEquals($entry['percent_left'], self::GRAPH_PERCENT_LEFT + $i);
			$this->assertEquals($entry['percent_right'], self::GRAPH_PERCENT_RIGHT - $i);
			$this->assertEquals($entry['show_3d'], self::GRAPH_SHOW_3D);
			$this->assertEquals($entry['show_legend'], self::GRAPH_SHOW_LEGEND);
			$this->assertEquals($entry['show_work_period'], self::GRAPH_SHOW_WORK_PERIOD);
			$this->assertEquals($entry['show_triggers'], self::GRAPH_SHOW_TRIGGERS);
			$this->assertEquals($entry['yaxismax'], self::GRAPH_YAXISMAX + $i);
			$this->assertEquals($entry['yaxismin'], self::GRAPH_YAXISMIN + $i);
			$this->assertEquals($entry['ymax_itemid'], $itemids[$i]);
			$this->assertEquals($entry['ymax_type'], self::GRAPH_YMAX_TYPE);
			$this->assertEquals($entry['ymin_type'], self::GRAPH_YMIN_TYPE);

			$graph_item_response = $this->call('graphitem.get', [
				'output' => 'extend',
				'graphids' => $entry['graphid']
			]);

			$this->assertArrayHasKey(0, $graph_item_response['result']);
			$this->assertArrayHasKey('itemid', $graph_item_response['result'][0]);
			$this->assertEquals($graph_item_response['result'][0]['itemid'], $itemids[$i]);
			$this->assertEquals($graph_item_response['result'][0]['color'], self::GRAPH_ITEM_COLOR);

			$i++;
		}
	}

	/**
	* Test graph linking cases.
	*
	* @required-components server
	*/
	public function testGraphLinking_checkMe() {

		$this->reloadConfigurationCache();

		self::prepareComponentConfiguration(self::COMPONENT_AGENT, $this->agentConfigurationProvider());
		self::startComponent(self::COMPONENT_AGENT);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'End of DBregister_host_active():SUCCEED'
		]);

		$this->checkGraphsCreate();
		self::stopComponent(self::COMPONENT_AGENT);
	}
}

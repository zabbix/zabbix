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


require_once dirname(__FILE__).'/../common/testWidgetCommunication.php';

/**
 * @backup profiles
 *
 * @dataSource WidgetCommunication, Sla
 *
 * @onBefore prepareWidgetData
 */
class testDashboardWidgetBroadcastedData extends testWidgetCommunication {

	const TEST_BROADCASTER_REFERENCES = [
		'Hostgroups page' => 'RAHQZ',
		'Hosts page' => 'ZQHBC',
		'Items page' => 'KJRCA',
		'Maps page' => 'SALAT'
	];

	const ENTITY_DATA = [
		'Hostgroups page' => [
			'type' => 'hostgroupids',
			'text' => '_hostgroupids'
		],
		'Hosts page' => [
			'type' => 'hostids',
			'text' => '_hostids'
		],
		'Items page' => [
			'type' => 'itemids',
			'text' => '_itemid'
		],
		'Maps page' => [
			'type' => 'mapids',
			'text' => '_mapid'
		]
	];

	public static function prepareWidgetData() {
		testWidgetCommunication::getCreatedIds();

		// Discover the Test broadcaster and Test listener widget modules.
		CDataHelper::call('module.create', [
			[
				'id' => 'testbroadcaster',
				'relative_path' => 'modules/testbroadcaster',
				'status' => MODULE_STATUS_ENABLED
			],
			[
				'id' => 'testlistener',
				'relative_path' => 'modules/testlistener',
				'status' => MODULE_STATUS_ENABLED
			]
		]);

		// Add broadcaster and listener widgets for widgets involved in widget communication tests (except mixed scenarios).
		$dashboard_content = CDataHelper::call('dashboard.get', [
			'dashboardids' => self::$entityids['dashboardid'],
			'selectPages' => 'extend'
		])[0];

		$used_pages = [
			'Hostgroups page' => 'groupids',
			'Hosts page' => 'hostids',
			'Items page' => 'itemid',
			'Maps page' => 'sysmapid'
		];
		$dashboard_page = [];
		$pageids = [];

		// Create an array with only the required pages.
		foreach ($dashboard_content['pages'] as $existing_page) {
			if (in_array($existing_page['name'], array_keys($used_pages))) {
				$dashboard_page[$existing_page['name']] = $existing_page['widgets'];
				$pageids[$existing_page['name']] = $existing_page['dashboard_pageid'];
			}
		}

		// Create an array for an API call with listener widgets for every broadcaster on each of the required pages.
		$new_page_widgets = [];
		foreach ($dashboard_page as $page_name => $page_with_widgets) {
			$new_listeners = [];
			$i = 0;

			foreach ($page_with_widgets as &$widget) {
				if (str_contains($widget['name'], 'broadcaster')) {
					$new_listeners[$i]['type'] = 'testlistener';
					$new_listeners[$i]['name'] = 'Data listener for '.$widget['name'];
					$new_listeners[$i]['width'] = $widget['width'];
					$new_listeners[$i]['height'] = $widget['height'];
					$new_listeners[$i]['x'] = $widget['x'];
					$new_listeners[$i]['y'] = $widget['y'] + 20;
					$new_listeners[$i]['fields'] = [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => $used_pages[$page_name].'._reference',
							'value' => self::BROADCASTER_REFERENCES[$widget['name']]
						]
					];

					$i++;
				}

				unset($widget['widgetid']);
			}

			// Create a custom broadcaster widget for each of the impacted pages.
			$new_broadcaster = [
				[
					'type' => 'testbroadcaster',
					'name' => 'Data broadcaster for page '.$page_name,
					'width' => 20,
					'height' => 6,
					'x' => 32,
					'y' => 20,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => self::TEST_BROADCASTER_REFERENCES[$page_name]
						]
					]
				]
			];

			// Merge all old and new listener and broadcaster widgets on the page into a single array.
			$new_page_widgets[$page_name] = array_merge($page_with_widgets, $new_listeners, $new_broadcaster);
			unset($new_listeners);
		}

		// Form a final array for API call with all widgets and corresponding dashboard page IDs.
		$new_pages = [];
		$j = 0;
		foreach ($new_page_widgets as $name => $widgets) {
			$new_pages[$j]['dashboard_pageid'] = $pageids[$name];
			$new_pages[$j]['widgets'] = $widgets;

			$j++;
		}

		// Add a page for checking map, graph and graph classic widgets feedback.
		$feedback_page = [
			'name' => 'Feedback check page',
			'widgets' => [
				[
					'type' => 'testbroadcaster',
					'name' => 'Map broadcaster',
					'x' => 0,
					'y' => 0,
					'width' => 30,
					'height' => 5,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => 'NUMBY'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
							'name' => 'sysmapids.0',
							'value' => self::$entityids['mapids'][self::MAP_NAME]
						]
					]
				],
				[
					'type' => 'testbroadcaster',
					'name' => 'Item and time broadcaster',
					'x' => 0,
					'y' => 5,
					'width' => 30,
					'height' => 8,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
							'name' => 'itemids.0',
							'value' => self::$entityids['itemids']['1st host for widgets:trap.widget.communication']
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => 'QCPVH'
						]
					]
				],
				[
					'type' => 'map',
					'name' => 'Map listener to check feedback',
					'x' => 35,
					'y' => 0,
					'width' => 20,
					'height' => 5,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'sysmapid._reference',
							'value' => 'NUMBY._mapid'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => 'SAPOG'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
							'name' => 'rf_rate',
							'value' => 0
						]
					]
				],
				[
					'type' => 'graph',
					'name' => 'Graph (classic) listener to check feedback',
					'x' => 35,
					'y' => 5,
					'width' => 20,
					'height' => 4,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
							'name' => 'source_type',
							'value' => ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'itemid._reference',
							'value' => 'QCPVH._itemid'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'time_period._reference',
							'value' => 'QCPVH._timeperiod'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => 'PKKQW'
						]
					]
				],
				[
					'type' => 'svggraph',
					'name' => 'SVG graph listener to check feedback',
					'x' => 35,
					'y' => 9,
					'width' => 20,
					'height' => 4,
					'fields' => [
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'ds.0.itemids.0._reference',
							'value' => 'QCPVH._itemid'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'ds.0.color.0',
							'value' => 'FF465C'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
							'name' => 'ds.0.dataset_type',
							'value' => 0
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
							'name' => 'righty',
							'value' => 0
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'reference',
							'value' => 'ULQKH'
						],
						[
							'type' => ZBX_WIDGET_FIELD_TYPE_STR,
							'name' => 'time_period._reference',
							'value' => 'QCPVH._timeperiod'
						]
					]
				]
			]
		];
		$new_pages[$j] = $feedback_page;

		// Update existing pages on dashboard with the created new array with dashboard pages.
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$entityids['dashboardid'],
				'pages' => $new_pages
			]
		]);

		// Make all old listener widgets on the page to listen the corresponding custom proadcaster widget.
		foreach (array_keys($pageids) as $page_name) {
			// Create the broadcaster reference using the type of broadcasted data and pre-defined reference id.
			if ($used_pages[$page_name] === 'groupids') {
				$refered_parameter = 'hostgroupids';
			}
			elseif ($used_pages[$page_name] === 'sysmapid') {
				$refered_parameter = 'mapid';
			}
			else {
				$refered_parameter = $used_pages[$page_name];
			}
			$broadcaster_reference = self::TEST_BROADCASTER_REFERENCES[$page_name].'._'.$refered_parameter;

			// Update the corresponding reference for all widgets on page the name of which ends with text " listener".
			DBexecute('UPDATE widget_field SET value_str='.zbx_dbstr($broadcaster_reference).' WHERE name='.
					zbx_dbstr($used_pages[$page_name].'._reference').' AND widgetid IN (SELECT widgetid FROM widget'.
					' WHERE name LIKE \'% listener\' AND dashboard_pageid IN (SELECT dashboard_pageid'.
					' FROM dashboard_page WHERE name='.zbx_dbstr($page_name).'))'
			);
		}
	}

	public function getFeedbackPageData() {
		return [
			'Check map feedback' => [
				[
					'broadcaster' => 'Map broadcaster',
					'listener' => 'Map listener to check feedback',
					'listener_type' => 'map'
				]
			],
			'Check classic graph feedback' => [
				[
					'broadcaster' => 'Item and time broadcaster',
					'listener' => 'Graph (classic) listener to check feedback',
					'listener_type' => 'graph classic'
				]
			],
			'Check svg graph feedback' => [
				[
					'broadcaster' => 'Item and time broadcaster',
					'listener' => 'SVG graph listener to check feedback',
					'listener_type' => 'svg graph'
				]
			]
		];
	}

	/**
	 * Check that correct entity ID is sent in feedback of map, Graph and Graph (classic) widgets.
	 * Graph prototype widget is not checked due to the complexity of implementation.
	 *
	 * @dataProvider getFeedbackPageData
	 */
	public function testDashboardWidgetBroadcastedData_CheckFeedback($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid']);
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->selectPage('Feedback check page');

		$broadcaster = $dashboard->getWidget($data['broadcaster']);
		$feedback_field = $broadcaster->query('name:feedbacks')->one();
		$listener = $dashboard->getWidget($data['listener']);

		/*
		 * Check that Map and Graph classic widgets display "Awaiting data" if nothing is selected on the broadcaster.
		 * SVG graph behaves differently as it may represent multiple entities (items) from multiple datasets, which
		 * is not the case for Classic graph and Map widgets.
		 */
		if ($data['listener_type'] !== 'svg graph') {
			$this->assertEquals('Awaiting data', $listener->query('class:no-data-message')->one()->getText());
		}

		// Check that no feedback was sent when loading the page.
		$this->assertEquals('', $feedback_field->getValue());

		if ($data['listener_type'] === 'map') {
			// Select the required map on the map broadcaster.
			$broadcaster->query('button', self::MAP_NAME)->one()->click();
			$listener->waitUntilReady();

			// Locate the submap element and open the submap via the popup menu.
			$element = $listener->query('xpath:.//*[@class="map-elements"]//*[text()='
					.CXPathHelper::escapeQuotes(self::SUBMAP_NAME).']/../../preceding::*[1]'
			)->waitUntilVisible()->one();
			$element->click();
			CPopupMenuElement::find()->waitUntilReady()->one()->select('Submap');

			$expected = '_mapid | ["'.self::$entityids['mapids'][self::SUBMAP_NAME].'"]';
		}
		else {
			// Select the item and the time period to be displayed on the listener.
			$broadcaster->query('button', '2 days ago')->one()->click();
			$broadcaster->query('button', 'Trapper item')->one()->click();
			$listener->waitUntilReady();

			// Locate the graph and double-click on it in order to zoom out two times and to send feedback.
			$tag = ($data['listener_type'] === 'svg graph') ? 'svg' : 'img';
			$listener->query('tag', $tag)->one()->doubleClick();
			$listener->waitUntilReady();

			// Prepare the expected feedback, containing both formatted and unixtime "From" and "To" time periods.
			$from_unixtime = strtotime('today - 2 days - 12hours');
			$to_unixtime = strtotime('yesterday 11:59:59');
			$from_formatted = date('Y-m-d H:i:s', $from_unixtime);
			$to_formatted = date('Y-m-d H:i:s', $to_unixtime);
			$expected = '_timeperiod | {"from":"'.$from_formatted.'","to":"'.$to_formatted.
					'","from_ts":'.$from_unixtime.',"to_ts":'.$to_unixtime.'}';
		}

		// Get the feedback value from the textarea, remove the timestamp and compare with the expected string.
		$this->assertEquals($expected, substr($feedback_field->getValue(), 11));

		// Check that the map listener sends feedback when going back to parent map.
		if ($data['listener_type'] === 'map') {
			$feedback_field->clear();
			$listener->query('class:btn-back-map-icon')->waitUntilVisible()->one()->click();
			$listener->waitUntilReady();

			// Check that feedback with parent map id was sent to the broadcaster.
			$this->assertEquals('_mapid | ["'.self::$entityids['mapids'][self::MAP_NAME].'"]',
					substr($feedback_field->getValue(), 11)
			);
		}
	}

	public function getPageDefaultData() {
		return [
			'Check hostgroup page' => [
				[
					'page' => 'Hostgroups page',
					'listeners' => [
						'Data listener for Map hostgroup broadcaster' => self::FIRST_HOSTGROUP_NAME,
						'Data listener for Problem hosts hostgroup broadcaster' => self::FIRST_HOSTGROUP_NAME,
						'Data listener for Problems by severity hostgroup broadcaster' => self::FIRST_HOSTGROUP_NAME,
						'Data listener for Web monitoring hostgroup broadcaster' => self::FIRST_HOSTGROUP_NAME
					],
					'broadcaster' => 'Data broadcaster for page Hostgroups page'
				]
			],
			'Check host page' => [
				[
					'page' => 'Hosts page',
					'listeners' => [
						'Data listener for Geomap host broadcaster' => self::SECOND_HOST_NAME,
						// Map should broadcast an empty host because by default a hostgroups is selected on the map.
						'Data listener for Honeycomb host broadcaster' => self::FIRST_HOST_NAME,
						'Data listener for Map host broadcaster' => '',
						'Data listener for Top hosts host broadcaster' => self::THIRD_HOST_NAME,
						'Data listener for Host navigator broadcaster' => self::FIRST_HOST_NAME
					],
					'broadcaster' => 'Data broadcaster for page Hosts page'
				]
			],
			'Check items page' => [
				[
					'page' => 'Items page',
					'listeners' => [
						'Data listener for Honeycomb item broadcaster' => self::FIRST_HOST_NAME.':trap.widget.communication',
						'Data listener for Item history item broadcaster' => self::FIRST_HOST_NAME.':trap.widget.communication',
						'Data listener for Item navigator broadcaster' => self::FIRST_HOST_NAME.':trap.widget.communication'
					],
					'broadcaster' => 'Data broadcaster for page Items page'
				]
			],
			'Check maps page' => [
				[
					'page' => 'Maps page',
					'listeners' => [
						'Data listener for Navigation tree map broadcaster' => self::MAP_NAME
					],
					'broadcaster' => 'Data broadcaster for page Maps page'
				]
			]
		];
	}

	/**
	 * Check that broadcasters send only a single default entity ID on initial load, that listeners don't send any
	 * feedback on initial load and that no communication is happening on widget reload (switching pages).
	 *
	 * @dataProvider getPageDefaultData
	 */
	public function testDashboardWidgetBroadcastedData_WidgetLoading($data) {
		$entityids = self::$entityids[self::ENTITY_DATA[$data['page']]['type']];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid']);
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->selectPage($data['page']);

		/**
		 * Check that broadcasters have sent only expected data to the corresponding receivers.
		 * Save expected result to use it when checking that no communication is ongoing when switching pages.
		 */
		$expected = [];
		foreach ($data['listeners'] as $widget_name => $default_entity_name) {
			$expected_text = ($default_entity_name === '')
				? self::ENTITY_DATA[$data['page']]['text'].' | []'
				: self::ENTITY_DATA[$data['page']]['text'].' | ["'.$entityids[$default_entity_name].'"]';
			$expected[$widget_name] = $expected_text;

			// Remove the first 11 symbols as they represent time of loading and are not important for this check.
			$value = $dashboard->getWidget($widget_name)->query('name:broadcasts')->one()->getValue();
			$this->assertEquals($expected_text, substr($value, 11));
		}

		// Check that no feedback was send to the custom broadcaster thom the listeners.
		if (array_key_exists('broadcaster', $data)) {
			$value = $dashboard->getWidget($data['broadcaster'])->query('name:feedbacks')->one()->getValue();
			$this->assertEquals('', $value);
		}

		// Switch to a different page and back to check that no new data has been broadcasted or sent as feedback.
		$dashboard->selectPage('Feedback check page');
		$dashboard->selectPage($data['page']);

		// Check listeners in the same way as previously.
		foreach ($data['listeners'] as $widget_name => $default_entity_name) {
			$reloaded_value = $dashboard->getWidget($widget_name)->query('name:broadcasts')->one()->getValue();
			$this->assertEquals($expected[$widget_name], substr($reloaded_value, 11));
		}

		// Check broadcaster in the same way as previously.
		if (array_key_exists('broadcaster', $data)) {
			$reloaded_value = $dashboard->getWidget($data['broadcaster'])->query('name:feedbacks')->one()->getValue();
			$this->assertEquals('', $reloaded_value);
		}
	}

	public function getBroadcastData() {
		return [
			'Map hostgroup broadcaster' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME
				]
			],
			'Problem hosts hostgroup broadcaster' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME
				]
			],
			'Problems by severity hostgroup broadcaster' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problems by severity hostgroup broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME
				]
			],
			'Web monitoring hostgroup broadcaster' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME
				]
			],
			'Geomap host broadcaster' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME],
					'selected_entity' => self::THIRD_HOST_NAME
				]
			],
			'Honeycomb host broadcaster' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'select_element' => self::FIRST_HOST_NAME
				]
			],
			'Map host broadcaster' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'select_element' => self::SECOND_HOST_NAME
				]
			],
			'Top hosts host broadcaster' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'select_element' => self::THIRD_HOST_NAME
				]
			],
			'Host navigator broadcaster' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Host navigator broadcaster',
					'select_element' => self::FIRST_HOST_NAME
				]
			],
			'Honeycomb item broadcaster' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Honeycomb item broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'selected_entity' => self::SECOND_HOST_NAME.':trap.widget.communication'
				]
			],
			'Item history item broadcaster' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item history item broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'selected_entity' => self::THIRD_HOST_NAME.':trap.widget.communication'
				]
			],
			'Item navigator broadcaster' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item navigator broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'selected_entity' => self::FIRST_HOST_NAME.':trap.widget.communication'
				]
			],
			'Navigation tree map broadcaster' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'select_element' => self::SUBMAP_NAME
				]
			]
		];
	}

	/**
	 * Check how widgets send the ID of the selected entity.
	 *
	 * @dataProvider getBroadcastData
	 */
	public function testDashboardWidgetBroadcastedData_CheckBroadcasting($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid']);
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->selectPage($data['page']);

		$broadcaster = $dashboard->getWidget($data['broadcaster']);
		$value_field = $dashboard->getWidget('Data listener for '.$data['broadcaster'])->query('name:broadcasts')->one();
		$value_field->clear();

		// Select an element on the broadcaster widget to trigger a broadcast.
		$this->getWidgetElement($data['select_element'], $broadcaster)->click();

		// Check the broadcasted value on the listener.
		$selected_entity = CTestArrayHelper::get($data, 'selected_entity', $data['select_element']);
		$id_type = self::ENTITY_DATA[$data['page']]['type'];
		$entityid = self::$entityids[$id_type][$selected_entity];
		$expected_text = self::ENTITY_DATA[$data['page']]['text'].' | ["'.$entityid.'"]';

		// Cut off the timestamp and compare it with the expected value.
		$this->assertEquals($expected_text, substr($value_field->getValue(), 11));
	}
}

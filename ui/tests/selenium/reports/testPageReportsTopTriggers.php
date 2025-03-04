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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageReportsTopTriggers extends CWebTest {

	/**
	 * Attach TableBehavior and TagBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			CTagBehavior::class
		];
	}

	protected static $groupids;
	protected static $time;
	const LINK = 'zabbix.php?action=toptriggers.list';

	public function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Common group'],
			['name' => 'Empty Group for Reports->TOP 100 triggers check'],
			['name' => 'First Group for Reports->TOP 100 triggers check'],
			['name' => 'Second Group for Reports->TOP 100 triggers check'],
			['name' => 'Third Group for special ¢ⒽąŘαⒸⓣⒺⓇⓢ 🌍'],
			['name' => 'Group for problem tags check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items for top 100 triggers data test.
		CDataHelper::createHosts([
			[
				'host' => 'Empty Host for Reports - TOP 100 triggers filter checks',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.5.1',
						'dns' => '',
						'port' => '10077'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['Empty Group for Reports->TOP 100 triggers check']],
					['groupid' => self::$groupids['Common group']]
				]
			],
			[
				'host' => 'Host for Reports - TOP 100 triggers filter checks',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.5.1',
						'dns' => '',
						'port' => '10077'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['First Group for Reports->TOP 100 triggers check']],
					['groupid' => self::$groupids['Common group']]
				],
				'items' => [
					[
						'name' => 'Item for Top 100 triggers reports 🛒',
						'key_' => 'topreports',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for Reports - TOP 100 triggers filter checks 2',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['Second Group for Reports->TOP 100 triggers check']],
					['groupid' => self::$groupids['Common group']]
				],
				'items' => [
					[
						'name' => 'Item for Top 100 triggers reports2',
						'key_' => 'topreports2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for Reports - TOP 100 triggers filter checks 3',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['Second Group for Reports->TOP 100 triggers check']],
					['groupid' => self::$groupids['Common group']]
				],
				'items' => [
					[
						'name' => 'Item for Top 100 triggers reports3',
						'key_' => 'topreports3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with triggers that contains special characters or macro',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['Third Group for special ¢ⒽąŘαⒸⓣⒺⓇⓢ 🌍']],
					['groupid' => self::$groupids['Common group']]
				],
				'items' => [
					[
						'name' => 'Item for special ¢ⒽąŘαⒸⓣⒺⓇⓢ tests',
						'key_' => 'topreports4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for problem tags check',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					['groupid' => self::$groupids['Group for problem tags check']],
					['groupid' => self::$groupids['Common group']]
				],
				'items' => [
					[
						'name' => 'Item for tags',
						'key_' => 'topreports5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem Disaster',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks 3/topreports3)=5',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Severity status Average: {HOST.HOST}',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Severity status: High',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks 2/topreports2)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_HIGH,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Problem Warning',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Problem with tag',
				'expression' => 'last(/Host for problem tags check/topreports5)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING,
				'tags' => [
					[
						'tag' => 'test1',
						'value' => 'tag1'
					]
				]
			],
			[
				'description' => 'Problem with two tags',
				'expression' => 'last(/Host for problem tags check/topreports5)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING,
				'tags' => [
					[
						'tag' => 'test2',
						'value' => 'tag2'
					],
					[
						'tag' => 'target',
						'value' => 'linux'
					]
				]
			],
			[
				'description' => 'Problem with tag2',
				'expression' => 'last(/Host for problem tags check/topreports5)=1',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_INFORMATION,
				'tags' => [
					[
						'tag' => 'target',
						'value' => 'windows'
					]
				]
			],
			[
				'description' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=1',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_INFORMATION,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Not classified ❌',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=0',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'tags' => [
					[
						'tag' => 'top',
						'value' => 'trigger'
					]
				]
			],
			[
				'description' => 'Problem with tag3',
				'expression' => 'last(/Host for problem tags check/topreports5)=0',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
				'tags' => [
					[
						'tag' => 'Application'
					]
				]
			]
		]);

		// Create events and problems.
		self::$time = time();
		$trigger_data = [
			[
				'name' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
				'time' => self::$time, // current time.
				'problem_count' => '1'
			],
			[
				'name' => 'Not classified ❌',
				'time' => self::$time - 60, // now - 1 minute.
				'problem_count' => '1'
			],
			[
				'name' => 'Severity status Average: {HOST.HOST}',
				'time' => self::$time - 120, // now - 2 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Problem Warning',
				'time' => self::$time - 300, // now - 5 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Severity status: High',
				'time' => self::$time - 360, // now - 6 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Problem Disaster',
				'time' => self::$time - 600, // now - 10 minutes.
				'problem_count' => '5'
			],
			[
				'name' => 'Problem with tag',
				'time' => self::$time - 1800, // now - 30 minutes.
				'problem_count' => '3'
			],
			[
				'name' => 'Problem with two tags',
				'time' => self::$time - 2400, // now - 40 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Problem with tag2',
				'time' => self::$time - 2460, // now - 41 minutes.
				'problem_count' => '2'
			],
			[
				'name' => 'Problem with tag3',
				'time' => self::$time - 2520, // now - 42 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Severity status: High',
				'time' => self::$time - 5400, // now - 90 minutes.
				'problem_count' => '1'
			],
			[
				'name' => 'Problem Disaster',
				'time' => strtotime('yesterday'),
				'problem_count' => '1'
			],
			[
				'name' => 'Problem Warning',
				'time' => strtotime('-2 days'),
				'problem_count' => '1'
			],
			[
				'name' => 'Problem Warning',
				'time' => self::$time - 691200, // now - 8 days.
				'problem_count' => '1'
			],
			[
				'name' => 'Not classified ❌',
				'time' => self::$time - 14465000, // now - approximately 5.5 months.
				'problem_count' => '1'
			]
		];

		foreach ($trigger_data as $params) {
			for ($i = 1; $i <= $params['problem_count']; $i++) {
				CDBHelper::setTriggerProblem($params['name'], TRIGGER_VALUE_TRUE, ['clock' => $params['time']]);
			}
		}

		// Delete some hosts and problems from previous tests and data source, not to interfere this test.
		$rows = CDBHelper::getAll('SELECT * FROM hosts WHERE host='.zbx_dbstr('Host for tag permissions'));
		if ($rows !== []) {
			CDataHelper::call('host.delete', [$rows[0]['hostid']]);
		}
	}

	public function testPageReportsTopTriggers_Layout() {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->page->assertTitle('Top 100 triggers');
		$this->page->assertHeader('Top 100 triggers');

		$filter = CFilterElement::find()->one();
		$this->assertEquals('Last 1 hour', $filter->getSelectedTabName());
		$this->assertEquals('Last 1 hour', $filter->query('link:Last 1 hour')->one()->getText());

		// Check time selector fields layout.
		$this->assertEquals('now-1h', $this->query('id:from')->one()->getValue());
		$this->assertEquals('now', $this->query('id:to')->one()->getValue());

		$buttons = [
			'xpath://button[contains(@class, "btn-time-left")]' => true,
			'xpath://button[contains(@class, "btn-time-right")]' => false,
			'button:Zoom out' => true,
			'button:Apply' => true,
			'id:from_calendar' => true,
			'id:to_calendar' => true
		];
		foreach ($buttons as $selector => $enabled) {
			$this->assertTrue($this->query($selector)->one()->isEnabled($enabled));
		}

		foreach (['id:from' => 255, 'id:to' => 255] as $input => $value) {
			$this->assertEquals($value, $this->query($input)->one()->getAttribute('maxlength'));
		}

		$this->assertEquals(1, $this->query('button:Apply')->all()->filter(CElementFilter::CLICKABLE)->count());
		$this->assertTrue($filter->isExpanded());

		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved.
			$this->page->open('zabbix.php?action=report.status')->waitUntilReady();
			$this->page->open(self::LINK)->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		$filter->selectTab('Filter');
		$this->assertEquals('Filter', $filter->getSelectedTabName());
		$this->assertEquals(4, $this->query('button', ['Apply', 'Reset', 'Add', 'Remove'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$filter_form = $filter->getForm();
		$this->assertEquals(['Host groups', 'Hosts', 'Problem', 'Severity', 'Problem tags'],
				$filter_form->getLabels()->asText()
		);

		$default_state = [
			'Host groups' => '',
			'Hosts' => '',
			'Problem' => '',
			'id:filter_severities_0' => false,
			'id:filter_severities_1' => false,
			'id:filter_severities_2' => false,
			'id:filter_severities_3' => false,
			'id:filter_severities_4' => false,
			'id:filter_severities_5' => false,
			'id:filter_evaltype_0' => 'And/Or',
			'id:filter_tags_0_tag' => '',
			'id:filter_tags_0_operator' => 'Contains',
			'id:filter_tags_0_value' => ''
		];
		$filter_form->checkValue($default_state);

		// Check attributes of input elements.
		$inputs = [
			'id:filter_groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:filter_hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'Problem' => [
				'maxlength' => 255
			],
			'id:filter_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:filter_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $filter_form->getField($field)->getAttribute($attribute));
			}
		}

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['Host', 'Trigger', 'Severity', 'Number of problems'], $table->getHeadersText());
	}

	public static function getFilterData() {
		return [
			// #0 Check filter results from particular host group.
			[
				[
					'fields' => [
						'Host groups' => 'First Group for Reports->TOP 100 triggers check'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// #1 Check filter results from several host groups.
			[
				[
					'fields' => [
						'Host groups' => [
							'First Group for Reports->TOP 100 triggers check',
							'Second Group for Reports->TOP 100 triggers check',
							'Empty Group for Reports->TOP 100 triggers check'
						]
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '5'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg',
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// #2 Check filter results from particular host.
			[
				[
					'fields' => [
						'Hosts' => 'Host for Reports - TOP 100 triggers filter checks 2'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// #3 Check filter results from several hosts.
			[
				[
					'fields' => [
						'Hosts' => [
							'Host for Reports - TOP 100 triggers filter checks 2',
							'Host for Reports - TOP 100 triggers filter checks 3',
							'Empty Host for Reports - TOP 100 triggers filter checks'
						]
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '5'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// #4 No filter results if host doesn't belong to host group.
			[
				[
					'fields' => [
						'Host groups' => 'First Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Host for Reports - TOP 100 triggers filter checks 2'
					],
					'expected' => []
				]
			],
			// #5.
			[
				[
					'fields' => [
						'Host groups' => 'Empty Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Empty Host for Reports - TOP 100 triggers filter checks'
					],
					'expected' => []
				]
			],
			// #6 Search by "Problem" should not be case sensitive.
			[
				[
					'fields' => [
						'Problem' => 'problem DISASTER'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '5'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
			// #7 Wrong name in filter field "Problem".
			[
				[
					'fields' => [
						'Problem' => 'No data should be returned'
					],
					'expected' => []
				]
			],
			// #8 Trigger name with special characters.
			[
				[
					'fields' => [
						'Problem' => 'ℹ️'
					],
					'expected' => [
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg'
					]
				]
			],
			// #9 Trigger name with macro.
			[
				[
					'fields' => [
						'Problem' => 'Severity status Average: '
					],
					'expected' => [
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Severity status Average: Host with triggers that contains special characters or macro',
							'Severity' => 'Average',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Severity status Average: Host with triggers that contains special characters or macro' => 'average-bg'
					]
				]
			],
			// #10 Search by severity.
			[
				[
					'fields' => [
						'High' => true
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// #11 Search by severities.
			[
				[
					'fields' => [
						'Information' => true,
						'Not classified' => true

					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag2',
							'Severity' => 'Information',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag3',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem with tag2' => 'info-bg',
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg',
						'Problem with tag3' => 'na-bg'
					]
				]
			],
			// #12 Search by tag.
			[
				[
					'tags' => [
						['name' => 'test1', 'operator' => 'Contains', 'value' => 'tag1']
					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag',
							'Severity' => 'Warning',
							'Number of problems' => '3'
						]
					],
					'background_colors' => [
						'Problem with tag' => 'warning-bg'
					]
				]
			],
			// #13.
			[
				[
					'tags' => [
						['name' => 'Application', 'operator' => 'Exists']
					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag3',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem with tag3' => 'na-bg'
					]
				]
			],
			// #14 Search by tags.
			[
				[
					'tags' => [
						['name' => 'test2', 'operator' => 'Contains', 'value' => 'tag2'],
						['name' => 'target', 'operator' => 'Equals', 'value' => 'linux']
					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with two tags',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem with two tags' => 'warning-bg'
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'id:filter_evaltype_1' => 'Or'
					],
					'tags' => [
						['name' => 'test1', 'operator' => 'Contains', 'value' => 'tag1'],
						['name' => 'target', 'operator' => 'Equals', 'value' => 'windows']
					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag',
							'Severity' => 'Warning',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag2',
							'Severity' => 'Information',
							'Number of problems' => '2'
						]
					],
					'background_colors' => [
						'Problem with tag' => 'warning-bg',
						'Problem with tag2' => 'info-bg'
					]
				]
			],
			// #16.
			[
				[
					'tags' => [
						['name' => 'Application', 'operator' => 'Does not exist'],
						['name' => 'top', 'operator' => 'Does not equal', 'value' => 'windows'],
						['name' => 'test1', 'operator' => 'Does not equal', 'value' => 'tag1'],
						['name' => 'top', 'operator' => 'Does not contain', 'value' => 'trigg']
					],
					'expected' => [
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag2',
							'Severity' => 'Information',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with two tags',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem with tag2' => 'info-bg',
						'Problem with two tags' => 'warning-bg'
					]
				]
			],
			// #17 Search by tags with empty result.
			[
				[
					'tags' => [
						['name' => 'Zabbix', 'operator' => 'Exists']
					],
					'expected' => []
				]
			],
			// #18 Search results with several filtering parameters.
			[
				[
					'fields' => [
						'Host groups' => 'Second Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Host for Reports - TOP 100 triggers filter checks 3',
						'Problem' => 'Problem ',
						'Disaster' => true
					],
					'tags' => [
						['name' => 'top', 'operator' => 'Equals', 'value' => 'trigger']
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '5'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
			// #19 Search results without filtering parameters.
			[
				[
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '5'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag',
							'Severity' => 'Warning',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag2',
							'Severity' => 'Information',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Severity status Average: Host with triggers that contains special characters or macro',
							'Severity' => 'Average',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with two tags',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag3',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Problem with tag' => 'warning-bg',
						'Problem with tag2' => 'info-bg',
						'Severity status: High' => 'high-bg',
						'Severity status Average: Host with triggers that contains special characters or macro' => 'average-bg',
						'Problem Warning' => 'warning-bg',
						'Problem with two tags' => 'warning-bg',
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg',
						'Problem with tag3' => 'na-bg'
					]
				]
			],
			/* #20.
			 * Search by date label.
			 * Note: This test case depends on time when executed. E.g. if execution time is around 00:00 - 00:30 expected
			 * result will be different, because some of prepareData generated problems will appear in yesterday's filter.
			 */
			[
				[
					'date' => [
						'locator' => 'Yesterday'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
			// #21.
			[
				[
					'date' => [
						'locator' => 'Day before yesterday'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// #22 Search by custom time period.
			[
				[
					'date' => [
						'from' => 'now-2h',
						'to' => 'now-1h'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// #23.
			[
				[
					'date' => [
						'from' => 'now-2w',
						'to' => 'now-7d'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// #24.
			[
				[
					'date' => [
						'from' => date('Y-m-d H:i', time() - 15780000), // 6 month from now.
						'to' => date('Y-m-d H:i', time() - 13150000) // 5 month from now.
					],
					'expected' => [
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Not classified ❌' => 'na-bg'
					]
				]
			],
			// #25 Search by custom time period and without expected data.
			[
				[
					'date' => [
						'from' => 'now-10y/y',
						'to' => 'now-10y/y'
					],
					'expected' => []
				]
			],
			// #26 Search results from last month using custom time period.
			[
				[
					'date' => [
						'from' => 'now-1M',
						'to' => 'now'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '6'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag',
							'Severity' => 'Warning',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag2',
							'Severity' => 'Information',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Severity status Average: Host with triggers that contains special characters or macro',
							'Severity' => 'Average',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with two tags',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for problem tags check',
							'Trigger' => 'Problem with tag3',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Problem with tag' => 'warning-bg',
						'Problem Warning' => 'warning-bg',
						'Severity status: High' => 'high-bg',
						'Problem with tag2' => 'info-bg',
						'Severity status Average: Host with triggers that contains special characters or macro' => 'average-bg',
						'Problem with two tags' => 'warning-bg',
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg',
						'Problem with tag3' => 'na-bg'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageReportsTopTriggers_Filter($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();

		$filter = CFilterElement::find()->one();
		if ($filter->getSelectedTabName() !== 'Filter') {
			$filter->selectTab('Filter');
		}

		$filter_form = $filter->getForm();

		// If test case doesn't filter by hostgroup, then filter by common hostgroup to to get rid of external data.
		if (!CTestArrayHelper::get($data, 'fields.Host groups')) {
			$filter_form->getField('Host groups')->fill('Common group');
			$filter_form->submit();
		}

		if (array_key_exists('fields', $data)) {
			$filter_form->fill($data['fields']);
		}

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		if (array_key_exists('date', $data)) {
			if ($filter->getSelectedTabName() === 'Filter') {
				// Select tab with time period selectors.
				$this->query('xpath:.//a[@href="#tab_1"]')->one()->click();
			}

			if (array_key_exists('locator', $data['date'])) {
				$this->query('link', $data['date']['locator'])->one()->click();
			}
			else {
				foreach ($data['date'] as $locator => $value) {
					$this->query('id', $locator)->one()->fill($value);
				}

				$filter->query('id:apply')->one()->click();
			}

			$filter->waitUntilReloaded();
		}
		else {
			$filter_form->submit();
		}

		$this->page->waitUntilReady();
		$table->waitUntilReloaded();

		if (array_key_exists('background_colors', $data)) {
			foreach ($data['background_colors'] as $trigger => $color) {
				$this->assertEquals($color, $this->query('class:list-table')->asTable()->one()
						->findRow('Trigger', $trigger)->getColumn('Severity')->getAttribute('class')
				);
			}
		}

		// Check that expected Data is returned in the list.
		$this->assertTableData($data['expected']);

		// Reset filter due to not influence further tests.
		if ($filter->getSelectedTabName() === 'Filter') {
			$this->query('button:Reset')->waitUntilClickable()->one()->click();
		}
	}

	public function testPageReportsTopTriggers_ContextMenu() {
		// Create problem.
		CDBHelper::setTriggerProblem('First test trigger with tag priority', TRIGGER_VALUE_TRUE);

		$this->page->login()->open(self::LINK)->waitUntilReady();

		// Remove unnecessary filtering in case if some filter is still in place after previous cases.
		if (!$this->query('link:ЗАББИКС Сервер')->one(false)->isValid()) {
			$filter = CFilterElement::find()->one();
			$filter->selectTab('Filter');
			$filter->query('button:Reset')->one()->click();
			$this->page->waitUntilReady();
		}

		$data = [
			'trigger_menu' => [
				'VIEW' => [
					'Problems' => 'zabbix.php?action=problem.view&filter_set=1&triggerids%5B%5D=99252',
					'History' => ['Number of processes' => 'history.php?action=showgraph&itemids%5B%5D=42253']
				],
				'CONFIGURATION' => [
					'Trigger' => 'menu-popup-item',
					'Items' => ['Number of processes' => 'menu-popup-item']
				]
			],
			'host_menu' => [
				'VIEW' => [
					'Dashboards' => 'zabbix.php?action=host.dashboard.view&hostid=10084',
					'Problems' => 'zabbix.php?action=problem.view&hostids%5B%5D=10084&filter_set=1',
					'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D=10084&filter_set=1',
					'Graphs' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=10084&filter_set=1',
					'Web' => 'menu-popup-item disabled',
					'Inventory' => 'hostinventories.php?hostid=10084'
				],
				'CONFIGURATION' => [
					'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid=10084',
					'Items' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Triggers' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Graphs' => 'graphs.php?filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Discovery' => 'host_discovery.php?filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Web' => 'httpconf.php?filter_set=1&filter_hostids%5B%5D=10084&context=host'
				],
				'SCRIPTS' => [
					'Detect operating system' => 'menu-popup-item',
					'Ping' => 'menu-popup-item',
					'Traceroute' => 'menu-popup-item'
				]
			]
		];

		// Check host context menu links.
		$this->query('link:ЗАББИКС Сервер')->one()->waitUntilClickable()->click();
		$this->checkContextMenuLinks($data['host_menu']);

		// Check trigger context menu links.
		$this->query('link:First test trigger with tag priority')->one()->waitUntilClickable()->click();
		$this->checkContextMenuLinks($data['trigger_menu']);
	}

	/**
	 * Check context menu links.
	 *
	 * @param array $data	data provider with fields values
	 */
	protected function checkContextMenuLinks($data) {
		// Check popup menu.
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasTitles(array_keys($data)));

		$menu_level1_items = [];
		foreach (array_values($data) as $menu_items) {
			foreach ($menu_items as $menu_level1 => $link) {
				$menu_level1_items[] = $menu_level1;

				if (is_array($link)) {
					foreach ($link as $menu_level2 => $attribute) {
						// Check 2-level menu links.
						$item_link = $popup->getItem($menu_level1)->query('xpath:./../ul//a')->one();

						if (str_contains($attribute, 'menu-popup-item')) {
							$this->assertEquals($attribute, $item_link->getAttribute('class'));
						}
						else {
							$this->assertEquals($menu_level2, $item_link->getText());
							$this->assertStringContainsString($attribute, $item_link->getAttribute('href'));
						}
					}
				}
				else {
					// Check 1-level menu links.
					if (str_contains($link, 'menu-popup-item')) {
						$this->assertEquals($link, $popup->getItem($menu_level1)->getAttribute('class'));
					}
					else {
						$this->assertTrue($popup->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($menu_level1).
								" and contains(@href, ".CXPathHelper::escapeQuotes($link).")]")->exists()
						);
					}
				}
			}
		}

		$this->assertTrue($popup->hasItems($menu_level1_items));
		$popup->close();
	}
}

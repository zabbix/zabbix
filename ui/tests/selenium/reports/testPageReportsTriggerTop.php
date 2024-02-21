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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageReportsTriggerTop extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	protected static $groupids;
	protected static $time;
	const LINK = 'toptriggers.php';

	public function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Empty Group for Reports->TOP 100 triggers check'],
			['name' => 'First Group for Reports->TOP 100 triggers check'],
			['name' => 'Second Group for Reports->TOP 100 triggers check'],
			['name' => 'Third Group for special ¢ⒽąŘαⒸⓣⒺⓇⓢ 🌍']
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
					'groupid' => self::$groupids['Empty Group for Reports->TOP 100 triggers check']
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
					'groupid' => self::$groupids['First Group for Reports->TOP 100 triggers check']
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
					'groupid' => self::$groupids['Second Group for Reports->TOP 100 triggers check']
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
					'groupid' => self::$groupids['Second Group for Reports->TOP 100 triggers check']
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
					'groupid' => self::$groupids['Third Group for special ¢ⒽąŘαⒸⓣⒺⓇⓢ 🌍']
				],
				'items' => [
					[
						'name' => 'Item for special ¢ⒽąŘαⒸⓣⒺⓇⓢ tests',
						'key_' => 'topreports4',
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
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Severity status Average: {HOST.HOST}',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Severity status: High',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks 2/topreports2)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Problem Warning',
				'expression' => 'last(/Host for Reports - TOP 100 triggers filter checks/topreports)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=1',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Not classified ❌',
				'expression' => 'last(/Host with triggers that contains special characters or macro/topreports4)=0',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			]
		]);

		// Create events and problems.
		self::$time = time();
		$trigger_data = [
			[
				'name' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
				'time' => self::$time, // current time.
				'problem_count' => '2'
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
				'name' => 'Severity status: High',
				'time' => self::$time - 1500, // now - 25 minutes.
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
	}

	public function testPageReportsTriggerTop_Layout() {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->page->assertTitle('100 busiest triggers');
		$this->page->assertHeader('100 busiest triggers');

		$filter = CFilterElement::find()->one();
		$this->assertEquals('Last 1 hour', $filter->getSelectedTabName());
		$this->assertEquals('Last 1 hour', $filter->query('link:Last 1 hour')->one()->getText());

		// Check time selector fields layout.
		$form = $filter->asForm();
		$form->checkValue(['id:from' => 'now-1h', 'id:to' => 'now']);

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
			$this->assertEquals($value, $form->getField($input)->getAttribute('maxlength'));
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

		// Check Filter tab fields layout.
		$filter->selectTab('Filter');
		$this->assertEquals('Filter', $filter->getSelectedTabName());
		$this->assertEquals(2, $this->query('button', ['Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$filter_form = $filter->getForm();
		$this->assertEquals(['Host groups', 'Hosts', 'Severity'], $filter_form->getLabels()->asText());

		$default_state = [
			'Host groups' => '',
			'Hosts' => '',
			'id:severities_0' => false,
			'id:severities_1' => false,
			'id:severities_2' => false,
			'id:severities_3' => false,
			'id:severities_4' => false,
			'id:severities_5' => false
		];
		$filter_form->checkValue($default_state);

		// Check attributes of input elements.
		$inputs = [
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hostids__ms' => [
				'placeholder' => 'type here to search'
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $filter_form->getField($field)->getAttribute($attribute));
			}
		}

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['Host', 'Trigger', 'Severity', 'Number of status changes'], $table->getHeadersText());
	}

	public static function getFilterData() {
		return [
			// Check filter results from particular host group.
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
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// Check filter results from several host groups.
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
							'Number of status changes' => '5'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg',
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// Check filter results from particular host.
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
							'Number of status changes' => '2'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// Check filter results from several hosts.
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
							'Number of status changes' => '5'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of status changes' => '2'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// No filter results if host doesn't belong to host group.
			[
				[
					'fields' => [
						'Host groups' => 'First Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Host for Reports - TOP 100 triggers filter checks 2'
					],
					'expected' => []
				]
			],
			[
				[
					'fields' => [
						'Host groups' => 'Empty Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Empty Host for Reports - TOP 100 triggers filter checks'
					],
					'expected' => []
				]
			],
			// Search by severity.
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
							'Number of status changes' => '2'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// Search by severities.
			[
				[
					'fields' => [
						'Information' => true,
						'Not classified' => true

					],
					'expected' => [
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg'
					]
				]
			],
			// Search results with several filtering parameters.
			[
				[
					'fields' => [
						'Host groups' => 'Second Group for Reports->TOP 100 triggers check',
						'Hosts' => 'Host for Reports - TOP 100 triggers filter checks 3',
						'Disaster' => true
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of status changes' => '5'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
			// Search results without filtering parameters.
			[
				[
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 3',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of status changes' => '5'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of status changes' => '1'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of status changes' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Severity status Average: Host with triggers that contains special characters or macro',
							'Severity' => 'Average',
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg',
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg',
						'Problem Warning' => 'warning-bg',
						'Severity status Average: Host with triggers that contains special characters or macro' => 'average-bg'
					]
				]
			],
			/*
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
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
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
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
			// Search by custom time period.
			[
				[
					'date' => [
						'from' => 'now-60m',
						'to' => 'now-20m'
					],
					'expected' => [
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Severity status: High' => 'high-bg'
					]
				]
			],
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
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Warning' => 'warning-bg'
					]
				]
			],
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
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Not classified ❌' => 'na-bg'
					]
				]
			],
			// Search by custom time period and without expected data.
			[
				[
					'date' => [
						'from' => 'now-10y/y',
						'to' => 'now-10y/y'
					],
					'expected' => []
				]
			],
			// Search results from last month using custom time period.
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
							'Number of status changes' => '6'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks',
							'Trigger' => 'Problem Warning',
							'Severity' => 'Warning',
							'Number of status changes' => '3'
						],
						[
							'Host' => 'Host for Reports - TOP 100 triggers filter checks 2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️',
							'Severity' => 'Information',
							'Number of status changes' => '2'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Not classified ❌',
							'Severity' => 'Not classified',
							'Number of status changes' => '1'
						],
						[
							'Host' => 'Host with triggers that contains special characters or macro',
							'Trigger' => 'Severity status Average: Host with triggers that contains special characters or macro',
							'Severity' => 'Average',
							'Number of status changes' => '1'
						]
					],
					'background_colors' => [
						'Problem Disaster' => 'disaster-bg',
						'Problem Warning' => 'warning-bg',
						'Severity status: High' => 'high-bg',
						'ⓅⓡⓞⒷⓁⓔⓂ Information ℹ️' => 'info-bg',
						'Not classified ❌' => 'na-bg',
						'Severity status Average: Host with triggers that contains special characters or macro' => 'average-bg'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageReportsTriggerTop_Filter($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();

		$filter = CFilterElement::find()->one();
		if ($filter->getSelectedTabName() !== 'Filter' && !array_key_exists('date', $data)) {
			$filter->selectTab('Filter');
		}

		$filter_form = $filter->getForm();
		if (array_key_exists('fields', $data)) {
			$filter_form->fill($data['fields']);
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

	public function testPageReportsTriggerTop_ContextMenu() {
		// Create problem.
		CDBHelper::setTriggerProblem('First test trigger with tag priority', TRIGGER_VALUE_TRUE);

		$this->page->login()->open(self::LINK)->waitUntilReady();

		$data = [
			'trigger_menu' => [
				'TRIGGER' => [
					'Problems' => 'zabbix.php?action=problem.view&filter_set=1&triggerids%5B%5D=99252',
					'Configuration' => 'triggers.php?form=update&triggerid=99252&context=host'
				],
				'HISTORY' => [
					'Linux: Number of processes' => 'history.php?action=showgraph&itemids%5B%5D=42253'
				]
			],
			'host_menu' => [
				'HOST' => [
					'Inventory' => 'hostinventories.php?hostid=10084',
					'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D=10084&filter_set=1',
					'Problems' => 'zabbix.php?action=problem.view&filter_set=1&hostids%5B%5D=10084',
					'Graphs' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=10084&filter_set=1',
					'Dashboards' => 'zabbix.php?action=host.dashboard.view&hostid=10084',
					'Web' => 'menu-popup-item disabled',
					'Configuration' => 'zabbix.php?action=host.edit&hostid=10084'
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

		$menu_items = [];
		foreach (array_values($data) as $menu) {
			foreach ($menu as $label => $link) {
				$menu_items = $label;

				// Check menu links.
				if (str_contains($link, 'menu-popup-item')) {
					$this->assertEquals($link, $popup->getItem($label)->getAttribute('class'));
				}
				else {
					$this->assertTrue($popup->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($label).
							" and contains(@href, ".CXPathHelper::escapeQuotes($link).")]")->exists()
					);
				}
			}
		}

		$this->assertTrue($popup->hasItems($menu_items));
		$popup->close();
	}
}

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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup history_uint, profiles
 *
 * @dataSource GlobalMacros
 *
 * @onBefore prepareTestData
 */
class testPageMonitoringLatestData extends CWebTest {

	const FILTER_HOSTNAME = 'Host for items tags filtering';
	const MAINTENANCE_HOSTNAME = 'Host in maintenance';

	protected static $hostids;

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	private function getTableSelector() {
		return 'xpath://table['.CXPathHelper::fromClass('list-table fixed').']';
	}

	private function getTable() {
		return $this->query($this->getTableSelector())->asTable()->one();
	}

	public function prepareTestData() {
		// Create hostgroup for host with items and tags.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Items With tags']]);
		$hostgroup = $hostgroups['groupids'][0];

		// Prepare items data.
		$item_names = ['tag_item_1', 'tag_item_2', 'tag_item_3', 'tag_item_4'];
		$items_tags_data = [];
		foreach ($item_names as $i => $item) {
			$items_tags_data[] = [
				'name' => $item,
				'key_' => 'trapper'.$i,
				'type' => 2,
				'value_type' => 0,
				'tags' => [
					['tag' => 'tag', 'value' => 'filtering_value'],
					['tag' => 'tag_number', 'value' => strval($i)],
					['tag' => 'component', 'value' => 'name:'.$item]
				]
			];
		}

		$item_descriptions = [
			'',
			'Non-clickable description',
			'https://zabbix.com',
			'The following url should be clickable: https://zabbix.com',
			'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact',
			'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career',
			'{$_} {$NONEXISTING}',
			'{$LOCALIP} {$A}',
			'{$A} and IP number {$LOCALIP}',
			'{{$A}}',
			'{$A}'
		];

		$items_descriptions_data = [];
		foreach ($item_descriptions as $i => $description) {
			$items_descriptions_data[] = [
				'name' => 'Trapper_'.$i,
				'key_' => 'trapper_'.$i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'description' => $description
			];
		}

		// Create hosts with items and tags.
		$result = CDataHelper::createHosts([
			[
				'host' => self::FILTER_HOSTNAME,
				'groups' => ['groupid' => $hostgroup],
				'items' => $items_tags_data
			],
			[
				'host' => self::MAINTENANCE_HOSTNAME,
				'groups' => ['groupid' => 4], // Zabbix servers.
				'items' => [
					[
						'name' => 'Trapper',
						'key_' => 'trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with item descriptions',
				'groups' => ['groupid' => 4], // Zabbix servers.
				'items' => $items_descriptions_data
			]
		]);

		self::$hostids = $result['hostids'];
		$maintenace_hostid = self::$hostids[self::MAINTENANCE_HOSTNAME];

		$data_item_id = $result['itemids'][self::FILTER_HOSTNAME.':trapper0'];

		// Add data to one item to see "With data"/"Without data" subfilter.
		$time = time() - 100;
		DBexecute('INSERT INTO history (itemid, clock, value, ns) VALUES ('.zbx_dbstr($data_item_id).', '.zbx_dbstr($time).', 1, 0)');

		// Create maintenance for wrench icon checking in Latest data page.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for latest data',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'description' => 'Maintenance for icon check in Latest data',
				'active_since' => $time,
				'active_till' => time() + 31536000,
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'timeperiods' => [[]]
			]
		]);
		$maintenanceid = $maintenances['maintenanceids'][0];

		DBexecute('INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (1000000, '.
				zbx_dbstr($maintenanceid).','.zbx_dbstr($maintenace_hostid).')'
		);

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenanceid).
				', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
				' WHERE hostid='.zbx_dbstr($maintenace_hostid)
		);
	}

	public function testPageMonitoringLatestData_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=latest.view&filter_reset=1')->waitUntilReady();
		$this->page->assertTitle('Latest data');
		$this->page->assertHeader('Latest data');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals(['Host groups', 'Hosts', 'Name', 'Tags', 'Show tags', 'Tag display priority', 'State', 'Show details'],
				$form->getLabels()->asText()
		);
		$this->assertTrue($this->query('button:Apply')->one()->isClickable());

		// Subfilter is not visible if filter isn't set.
		$this->assertFalse($this->query('id:latest-data-subfilter')->exists());
		$this->assertEquals(['Filter is not set', 'Use the filter to display results'],
				explode("\n", $this->query('class:no-data-message')->one()->getText())
		);

		$form->fill(['Hosts' => self::FILTER_HOSTNAME]);
		$form->submit();

		$subfilter = $this->query('id:latest-data-subfilter')->waitUntilVisible()->asTable()->one();
		$this->assertTrue($subfilter->query('xpath:.//h4[text()="Subfilter "]/span[@class="grey" and '.
				'text()="affects only filtered data"]')->one()->isValid()
		);
		$this->assertEquals(['HOSTS', 'TAGS', 'TAG VALUES', 'DATA'], $subfilter->query('tag:h3')->all()->asText());

		foreach (['link:With data', 'link:Without data'] as $query) {
			$this->assertTrue($subfilter->query($query)->one()->isValid());
		}

		// Check table headers.
		$details_headers = [
			true => ['', 'Host', 'Name', 'Interval', 'History', 'Trends', 'Type', 'Last check', 'Last value',
				'Change', 'Tags', '', 'Info'],
			false => ['', 'Host', 'Name', 'Last check', 'Last value', 'Change', 'Tags', '', 'Info']
		];

		$table = $this->getTable();
		foreach ($details_headers as $status => $headers) {
			$this->query('name:show_details')->one()->asCheckbox()->set($status);
			$form->submit();
			$table->waitUntilReloaded();
			$this->assertEquals($headers, $table->getHeadersText());
		}

		// Check that sortable headers are clickable.
		$this->assertEquals(['Host', 'Name'], $table->getSortableHeaders()->asText());

		// Subfilter is not visible again after Reset.
		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->assertFalse($this->query('id:latest-data-subfilter')->waitUntilNotVisible()->exists());
		$this->assertEquals(['Filter is not set', 'Use the filter to display results'],
				explode("\n", $this->query('class:no-data-message')->one()->getText())
		);

		// Check filter collapse/expand.
		$filter_tab = $this->query('xpath://a[contains(@class, "tabfilter-item-link")]')->one();
		foreach ([false, true] as $status) {
			$this->assertEquals($status, $this->query('xpath://div[contains(@class, "tabfilter-collapsed")]')
					->one(false)->isValid()
			);
			$filter_tab->click();
		}
	}

	public static function getFilterData() {
		return [
			// Host groups and Show details.
			[
				[
					'filter' => [
						'Host groups' => 'Another group to check Overview',
						'Show details' => true
					],
					'result' => [
						[
							'Name' => "4_item".
							"\ntrap[4]"
						]
					]
				]
			],
			// Hosts.
			[
				[
					'filter' => [
						'Hosts' => '1_Host_to_check_Monitoring_Overview'
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => '2_item']
					]
				]
			],
			// Name.
			[
				[
					'filter' => [
						'Name' => '3_item'
					],
					'result' => [
						['Name' => '3_item']
					]
				]
			],
			// Evaluation: And/Or, Operator Exists
			[
				[
					'Tags' => [
						'Evaluation' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'DataBase',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => '2_item'],
						['Name' => '3_item'],
						['Name' => '4_item']
					]
				]
			],
			// Evaluation: Or, Operators: Equals, Contains.
			[
				[
					'Tags' => [
						'Evaluation' => 'Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Contains',
								'value' => '0'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Equals',
								'value' => 'mysql'
							]
						]
					],
					'result' => [
						['Name' => '1_item'],
						['Name' => 'tag_item_1']
					]
				]
			],
			// The same tags as previous case, but Evaluation: And. Result: Empty table.
			[
				[
					'Tags' => [
						'Evaluation' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Contains',
								'value' => '0'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Equals',
								'value' => 'mysql'
							]
						]
					],
					'result' => []
				]
			],
			// Operators: Does not contain, Does not equal, Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag_number',
								'operator' => 'Does not equal',
								'value' => '1'
							],
							[
								'tag' => 'component',
								'operator' => 'Does not contain',
								'value' => '1'
							],
							[
								'tag' => 'tag',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						['Name' => 'tag_item_3'],
						['Name' => 'tag_item_4']
					]
				]
			],
			// Operator: Does not exist and Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'tag',
								'operator' => 'Exists'
							],
							[
								'tag' => 'DataBase',
								'operator' => 'Does not exist'
							]
						]
					],
					'result' => [
						['Name' => 'tag_item_1'],
						['Name' => 'tag_item_2'],
						['Name' => 'tag_item_3'],
						['Name' => 'tag_item_4']
					]
				]
			],
			// Tags None.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => 'None',
					'result' => [
						['Name' => 'tag_item_1']
					]
				]
			],
			// Tags: 1.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '1',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1']
					]
				]
			],
			// Tags: 2.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '2',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_value']
					]
				]
			],
			// Tags: 3. Tag name: Full.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Show tags' => '3',
					'Tags name' => 'Full',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_valuetag_number: 0']
					]
				]
			],
			// Tag name: Shortened.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Tags name' => 'Shortened',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'com: name:tag_item_1tag: filtering_valuetag: 0']
					]
				]
			],
			// Tag name: None.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1'
					],
					'Tags name' => 'None',
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'name:tag_item_1filtering_value0']
					]
				]
			],
			// Tag priority: no such tags.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag_'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'component: name:tag_item_1tag: filtering_valuetag_number: 0']
					]
				]
			],
			// Tag priority: opposite alphabetic.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag_number,tag,component'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'tag_number: 0tag: filtering_valuecomponent: name:tag_item_1']
					]
				]
			],
			// Tag priority: one first.
			[
				[
					'filter' => [
						'Name' => 'tag_item_1',
						'Tag display priority' => 'tag'
					],
					'result' => [
						['Name' => 'tag_item_1', 'Tags' => 'tag: filtering_valuecomponent: name:tag_item_1tag_number: 0']
					]
				]
			]
		];
	}

	/**
	 * Test for checking filtered results by values in main filter.
	 *
	 * @dataProvider getFilterData
	 */
	public function testPageMonitoringLatestData_Filter($data) {
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->getTable()->waitUntilPresent();

		// Expand filter if it is collapsed.
		CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_RIGHT)->expand();

		// Reset filter in case if some filtering remained before ongoing test case.
		$this->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();

		// Fill filter form with data.
		$form->fill(CTestArrayHelper::get($data, 'filter'));

		// If data contains Tags and their settings, fill them separately, because tags form is more complicated.
		if (CTestArrayHelper::get($data, 'Tags')) {
			$form->getField('id:evaltype_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Tags.Evaluation', 'And/Or'));
			$form->getField('id:tags_0')->asMultifieldTable()->fill(CTestArrayHelper::get($data, 'Tags.tags', []));
		}

		$form->getField('id:show_tags_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Show tags', '3'));
		$form->getField('id:tag_name_format_0')->asSegmentedRadio()->fill(CTestArrayHelper::get($data, 'Tags name', 'Full'));

		$form->submit();
		$table->waitUntilReloaded();

		// Check filtered result.
		$this->assertTableData($data['result'], $this->getTableSelector());

		// Check Show tags filter setting.
		if (CTestArrayHelper::get($data, 'Show tags') === 'None') {
			$this->assertEquals(['', 'Host', 'Name', 'Last check', 'Last value', 'Change', '', 'Info'],
					$this->getTable()->getHeadersText()
			);
		}

		// Reset filter not to impact the results of next tests.
		$this->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();
	}

	public static function getSubfilterData() {
		return [
			// Tag values.
			[
				[
					'subfilter' => [
						'Tag values' => [
							'name:tag_item_1',
							'name:tag_item_2'
						]
					],
					'result' => [
						['Name' => 'tag_item_1'],
						['Name' => 'tag_item_2']
					],
					'check_after_clear' => true
				]
			],
			// Tag values and Data.
			[
				[
					'subfilter' => [
						'Tag values' => [
							'name:tag_item_1',
							'name:tag_item_2'
						],
						'Data' => [
							'With data'
						]
					],
					'result' => [
						['Name' => 'tag_item_1']
					]
				]
			],
			// Hosts and Tag values.
			[
				[
					'subfilter' => [
						'Hosts' => [
							'1_Host_to_check_Monitoring_Overview',
							'3_Host_to_check_Monitoring_Overview'
						],
						'Tag values' => [
							'Oracle'
						]
					],
					'result' => [
						['Name' => '3_item']
					]
				]
			]
		];
	}

	/**
	 * Test for checking filtered results clicking on subfilter.
	 *
	 * @dataProvider getSubfilterData
	 */
	public function testPageMonitoringLatestData_Subfilter($data) {
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr(self::FILTER_HOSTNAME));

		$link = (CTestArrayHelper::get($data['subfilter'], 'Data'))
			? 'zabbix.php?action=latest.view&hostids%5B%5D='.$hostid
			: 'zabbix.php?action=latest.view&name=item';

		$this->page->login()->open($link)->waitUntilReady();

		foreach ($data['subfilter'] as $header => $values) {
			foreach ($values as $value) {
				$this->query('xpath://h3[text()='.CXPathHelper::escapeQuotes($header).']/..//a[text()='.
						CXPathHelper::escapeQuotes($value).']')->waitUntilClickable()->one()->click();
				$this->page->waitUntilReady();
			}
		}

		// Check that page remained the same.
		$this->page->assertTitle('Latest data');
		$this->page->assertHeader('Latest data');

		$this->assertTableData($data['result'], $this->getTableSelector());

		// Check that subfilter remains selected after main field is cleared.
		if (CTestArrayHelper::get($data, 'check_after_clear', false)) {
			$table = $this->getTable();
			CFilterElement::find()->one()->getForm()->fill(['Name' => ''])->submit();
			$table->waitUntilReloaded();

			foreach ($data['subfilter']['Tag values'] as $subfilter) {
				$this->assertTrue($this->query('xpath://a[text()='.CXPathHelper::escapeQuotes($subfilter).']/..')
						->one()->isAttributePresent(['class' => 'subfilter subfilter-enabled'])
				);
			}

			$this->assertTableData($data['result'], $this->getTableSelector());
		}

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
	}

	public function testPageMonitoringLatestData_ClickTag() {
		$this->checkClickTag();
	}

	public function testPageMonitoringLatestData_ClickTagKiosk() {
		$this->checkClickTag(true);
	}

	/**
	 * Test for clicking on particular item tag in table and checking that items are filtered by this tag using normal and kiosk mode.
	 *
	 * @param boolean $kiosk_mode	is kiosk mode applied on the page or not
	 */
	protected function checkClickTag($kiosk_mode = false) {
		$tag = ['tag' => 'component: ', 'value' => 'storage'];
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr('ЗАББИКС Сервер'));
		$this->page->login()->open('zabbix.php?action=latest.view&hostids%5B%5D='.$hostid)->waitUntilReady();

		if ($kiosk_mode) {
			$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
			$this->page->waitUntilReady();
			$this->assertTrue($this->query('xpath://button[@title="Normal view"]')->exists());
		}

		$this->getTable()->query('button', $tag['tag'].$tag['value'])->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Check that tag value is selected in subfilter under correct header.
		$this->assertTrue($this->query('xpath://td/h3[text()="Tag values"]/..//label[text()='.
				CXPathHelper::escapeQuotes($tag['tag']).']/../..//span[@class='.
				CXPathHelper::fromClass('subfilter-enabled').']/a[text()='.
				CXPathHelper::escapeQuotes($tag['value']).']')->exists()
		);

		$data = [
			['Name' => 'Free swap space'],
			['Name' => 'Free swap space in %'],
			['Name' => 'Total swap space']
		];
		$this->assertTableData($data, $this->getTableSelector());

		if ($kiosk_mode) {
			$this->query('xpath://button[@title="Normal view"]')->one()->click();
			$this->page->waitUntilReady();
			$this->assertTrue($this->query('xpath://button[@title="Kiosk mode"]')->exists());
			$this->assertTableData($data, $this->getTableSelector());
		}
		else {
			$this->query('button:Reset')->one()->click();
			$this->page->waitUntilReady();
			$this->assertEquals(['Filter is not set', 'Use the filter to display results'],
					explode("\n", $this->query('class:no-data-message')->one()->getText())
			);
		}
	}

	/**
	 * Test that checks if host has visible name, it cannot be found by host name on Latest Data page.
	 */
	public function testPageMonitoringLatestData_NoHostNames() {
		$result = [
			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_MONITORED.')'.
					' AND name <> host', 3
			),

			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_NOT_MONITORED.')'.
					' AND name <> host', 3
			),

			CDBHelper::getRandom(
				'SELECT host'.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_TEMPLATE.')'.
					' AND name <> host', 3
			)
		];

		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		CFilterElement::find()->one()->waitUntilVisible()->getForm()->fill(['State' => 'Normal']);
		$table = $this->getTable();
		$this->query('button:Apply')->one()->click();
		$this->page->waitUntilReady();
		$table->waitUntilReloaded();

		foreach ($result as $hosts) {
			foreach ($hosts as $host) {
				/*
				 * Check if hostname is present on page, if not, go to next page.
				 * Now there are 2 pages for Latest data with state Normal.
				 */
				for ($i = 1; $i < 2; $i++) {
					$this->assertFalse($this->query('link', $host['host'])->one(false)->isValid());
					$this->query('class:arrow-right')->waitUntilClickable()->one()->click();
					$this->page->waitUntilReady();
				}

				$this->query('class:table-paging')->query('link:1')->waitUntilClickable()->one()->click();
				$this->page->waitUntilReady();
			}
		}

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
	}

	public static function getItemDescription() {
		return [
			// Item without description.
			[
				[
					'Item name' => 'Trapper_0'
				]
			],
			// Item with plain text in the description.
			[
				[
					'Item name' => 'Trapper_1',
					'description' => 'Non-clickable description'
				]
			],
			// Item with only 1 url in description.
			[
				[
					'Item name' => 'Trapper_2',
					'description' => 'https://zabbix.com'
				]
			],
			// Item with text and url in description.
			[
				[
					'Item name' => 'Trapper_3',
					'description' => 'The following url should be clickable: https://zabbix.com'
				]
			],
			// Item with multiple urls in description.
			[
				[
					'Item name' => 'Trapper_4',
					'description' => 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact'
				]
			],
			// Item with text and 2 urls in description.
			[
				[
					'Item name' => 'Trapper_5',
					'description' => 'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career'
				]
			],
			// Item with underscore in macros name and one non existing macros  in description .
			[
				[
					'Item name' => 'Trapper_6',
					'description' => 'Underscore {$NONEXISTING}'
				]
			],
			// Item with 2 macros in description.
			[
				[
					'Item name' => 'Trapper_7',
					'description' => '127.0.0.1 Some text'
				]
			],
			// Item with 2 macros and text in description.
			[
				[
					'Item name' => 'Trapper_8',
					'description' => 'Some text and IP number 127.0.0.1'
				]
			],
			// Item with macros inside curly brackets.
			[
				[
					'Item name' => 'Trapper_9',
					'description' => '{Some text}'
				]
			],
			// Item with macros in description.
			[
				[
					'Item name' => 'Trapper_10',
					'description' => 'Some text'
				]
			]
		];
	}

	/**
	 * @dataProvider getItemDescription
	 */
	public function testPageMonitoringLatestData_checkItemDescription($data) {
		// Open Latest data for host 'Host with item descriptions'
		$this->page->login()->open('zabbix.php?&action=latest.view&show_details=0&hostids%5B%5D='.
				self::$hostids['Host with item descriptions'])->waitUntilReady();

		// Find rows from the data provider and click on the description icon if such should persist.
		$row = $this->getTable()->findRow('Name', $data['Item name'], true);

		if (CTestArrayHelper::get($data,'description', false)) {
			$row->query('class:zi-alert-with-content')->one()->click()->waitUntilReady();
			$overlay = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one();

			// Verify the real description with the expected one.
			$this->assertEquals($data['description'], $overlay->getText());

			// Get urls form description.
			$urls = [];
			preg_match_all('/https?:\/\/\S+/', $data['description'], $urls);
			// Verify that each of the urls is clickable.
			foreach ($urls[0] as $url) {
				$this->assertTrue($overlay->query('xpath:./div/a[@href="'.$url.'"]')->one()->isClickable());
			}

			// Verify that the tool-tip can be closed.
			$overlay->query('xpath:./button[@title="Close"]')->one()->click();
			$this->assertFalse($overlay->isDisplayed());
		}
		// If the item has no description the description icon should not be there.
		else {
			$this->assertTrue($row->query('class:icon-description')->count() === 0);
		}
	}

	/**
	 * Maintenance icon hintbox.
	 */
	public function testPageMonitoringLatestData_checkMaintenanceIcon() {
		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill(['Hosts' => self::MAINTENANCE_HOSTNAME]);
		$form->submit();

		$this->query('xpath://button['.CXPathHelper::fromClass('zi-wrench-alt-small').']')->waitUntilClickable()->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
		$hint_text = "Maintenance for latest data [Maintenance with data collection]\n".
				"Maintenance for icon check in Latest data";
		$this->assertEquals($hint_text, $hint);
	}

	/**
	 * Check hint text for Last check and Last value columns
	 */
	public function testPageMonitoringLatestData_checkHints() {
		$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr('4_item'));
		$time = time();
		$value = '15';
		$true_time = date('Y-m-d H:i:s', $time);

		DBexecute('INSERT INTO history_uint (itemid, clock, value, ns) VALUES ('.zbx_dbstr($itemid).
				', '.zbx_dbstr($time).', '.zbx_dbstr($value).', 0)'
		);

		$this->page->login()->open('zabbix.php?action=latest.view')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->getTable()->waitUntilPresent();
		$this->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();
		$form->fill(['Name' => '4_item'])->submit();
		$table->waitUntilReloaded();

		foreach (['Last check', 'Last value'] as $column) {
			if ($column === 'Last value') {
				$this->assertEquals('15 UNIT', $this->getTable()->getRow(0)->getColumn($column)->getText());
			}

			$this->getTable()->getRow(0)->getColumn($column)->query('class:cursor-pointer')->one()->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
			$compare_hint = ($column === 'Last check') ? $true_time : $value;
			$this->assertEquals($compare_hint, $hint);
		}
	}
}

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

/**
 * @backup !profiles, !problem, !problem_tag, !service_problem, !event_symptom
 *
 * @onBefore prepareData
 *
 * @onAfter clearData
 */
class testCauseAndSymptomEvents extends CWebTest {

	/**
	 * Attach TableBehavior and MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			CMessageBehavior::class
		];
	}

	const COLLAPSE_XPATH = 'xpath:.//button[@title="Collapse"]';
	const EXPAND_XPATH = 'xpath:.//button[@title="Expand"]';
	const SYMPTOM_XPATH = 'xpath:.//span[@title="Symptom"]';
	protected static $groupids;
	protected static $triggerids;
	protected static $hostsids;

	public function prepareData() {
		// Create host groups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Cause and Symptom check'],
			['name' => 'Group for Cause and Symptom update']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items.
		self::$hostsids = CDataHelper::createHosts([
			[
				'host' => 'Host for Cause and Symptom check',
				'groups' => [
					'groupid' => self::$groupids['Group for Cause and Symptom check']
				],
				'items' => [
					[
						'name' => 'Consumed energy',
						'key_' => 'kWh',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for Cause and Symptom check2',
				'groups' => [
					'groupid' => self::$groupids['Group for Cause and Symptom check']
				],
				'items' => [
					[
						'name' => 'Accumulated energy',
						'key_' => 'kWh',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Host for Cause and Symptom update',
				'groups' => [
					'groupid' => self::$groupids['Group for Cause and Symptom update']
				],
				'items' => [
					[
						'name' => 'Accumulated energy',
						'key_' => 'kWh',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);

		// Create triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem trap>10 [Symptom]',
				'expression' => 'last(/Host for Cause and Symptom check/kWh)>10',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Problem trap>150 [Cause]',
				'expression' => 'last(/Host for Cause and Symptom check/kWh)>150',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Information trap<100 [Cause]',
				'expression' => 'last(/Host for Cause and Symptom check2/kWh)<100',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Information trap<75 [Cause]',
				'expression' => 'last(/Host for Cause and Symptom check2/kWh)<75',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Battery problem trap [Cause]',
				'expression' => 'last(/Host for Cause and Symptom update/kWh)<10',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Battery problem trap2 [Cause]',
				'expression' => 'last(/Host for Cause and Symptom update/kWh)<5',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Battery problem linked [Cause]',
				'expression' => 'last(/Host for Cause and Symptom update/kWh)<2',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Battery problem linked [Symptom]',
				'expression' => 'last(/Host for Cause and Symptom update/kWh)<3',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Battery problem linked [Symptom2]',
				'expression' => 'last(/Host for Cause and Symptom update/kWh)<4',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);
		self::$triggerids = CDataHelper::getIds('description');

		// Create problems.
		CDBHelper::setTriggerProblem(array_keys(self::$triggerids));

		// Set cause and symptom(s) for predefined problems.
		$problems = [
			'Problem trap>150 [Cause]' => ['Problem trap>10 [Symptom]'],
			'Battery problem linked [Cause]' => ['Battery problem linked [Symptom]', 'Battery problem linked [Symptom2]']
		];
		foreach ($problems as $cause => $symptoms) {
			$causeid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr($cause));

			foreach ($symptoms as $symptom) {
				$symptomid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr($symptom));
				DBexecute('UPDATE problem SET cause_eventid='.$causeid.' WHERE name='.zbx_dbstr($symptom));
				DBexecute('INSERT INTO event_symptom (eventid, cause_eventid) VALUES ('.$symptomid.','.$causeid.')');
				DBexecute('UPDATE event_symptom SET cause_eventid='.$causeid.' WHERE eventid='.$symptomid);
			}
		}
	}

	/**
	 * Test scenario checks 'Problems' and 'Event details' page layout.
	 * This test checks only elements and table data that are related to 'cause and symptom' events.
	 */
	public function testCauseAndSymptomEvents_Layout() {
		$this->page->login()->open('zabbix.php?action=problem.view&hostids[]='.
				self::$hostsids['hostids']['Host for Cause and Symptom check']
		);

		$result = [
			['Problem' => 'Problem trap>150 [Cause]'],
			['Problem' => 'Problem trap>10 [Symptom]']
		];
		$this->assertTableData($result);

		// Check collapsed symptom count.
		$table = $this->getTable();
		$cause = $table->findRow('Problem', 'Problem trap>150 [Cause]');
		$this->assertTrue($cause->query('class:entity-count')->one()->isVisible());
		$this->assertEquals(1, $cause->query('class:entity-count')->one()->getText());

		// Check 'Event details' rank value for 'Cause' event.
		$cause->getColumn('Time')->click();
		$this->page->waitUntilReady();
		$this->assertStringContainsString('tr_events.php?triggerid='.self::$triggerids['Problem trap>150 [Cause]'].
				'&eventid=', $this->page->getCurrentURL()
		);
		$event_table = $this->query('xpath://section[@id="hat_eventdetails"]')->asTable()->waitUntilPresent()->one();
		$event_table->setColumnNames(['Name', 'Value']);
		$this->assertEquals('Cause', $event_table->findRow('Name', 'Rank')->getColumn('Value')->getText());

		$this->page->navigateBack();
		$symptom = $table->findRow('Problem', 'Problem trap>10 [Symptom]');
		$this->isCollapsed($cause, $symptom, true);
		$cause->query(self::EXPAND_XPATH)->one()->click();
		$this->isCollapsed($cause, $symptom);

		// Check 'Event details' rank value for 'Symptom' event and possibility to navigate to 'Cause' event.
		$table->query('xpath:.//a[contains(@href, "tr_events.php?triggerid='.
				self::$triggerids['Problem trap>10 [Symptom]'].'&eventid")]')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals('Symptom (Problem trap>150 [Cause])', $event_table->getRow(7)->getColumn(1)->getText());
		$event_table->query('link:Problem trap>150 [Cause]')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals('Cause', $event_table->findRow('Name', 'Rank')->getColumn('Value')->getText());

		// Check collapsed and expanded state via clicking on corresponded buttons.
		$this->page->open('zabbix.php?action=problem.view&hostids[]='.
				self::$hostsids['hostids']['Host for Cause and Symptom check']
		);
		$this->isCollapsed($cause, $symptom, true);
		$cause->query(self::EXPAND_XPATH)->one()->click();
		$this->isCollapsed($cause, $symptom);
		$cause->query(self::COLLAPSE_XPATH)->one()->click();
		$this->isCollapsed($cause, $symptom, true);
	}

	/**
	 * Check 'cause and symptom' elements collapsed/expanded state.
	 *
	 * @param CTableRowElement $cause		cause row by column value
	 * @param CTableRowElement $symptom		symptom row by column value
	 * @param boolean $collapsed	are symptom events in collapsed state or not
	 */
	protected function isCollapsed($cause, $symptom, $collapsed = false) {
		$chevron = $cause->getColumn(2)->query('tag:button')->one();
		$this->assertEquals($collapsed, $chevron->hasClass('collapsed'));
		$this->assertEquals($collapsed ? 'Expand' : 'Collapse', $chevron->getAttribute('title'));

		$this->assertFalse($symptom->isVisible($collapsed));
		$this->assertFalse($symptom->getColumn(2)->query(self::SYMPTOM_XPATH)->one()->isVisible($collapsed));
	}

	public function getContextMenuData() {
		return [
			// #0 Both menu options are disabled.
			[
				[
					'locator' => 'Problem trap>150 [Cause]',
					'disabled' => ['Mark as cause', 'Mark selected as symptoms']
				]
			],
			// #1 Both menu options are disabled in expanded state.
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>150 [Cause]',
					'selected_events' => ['Problem trap>10 [Symptom]'],
					'disabled' => ['Mark as cause', 'Mark selected as symptoms']
				]
			],
			// #2 Both menu options are disabled if only cause event is selected and no symptoms are linked to that.
			[
				[
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => ['Information trap<100 [Cause]'],
					'disabled' => ['Mark as cause', 'Mark selected as symptoms']
				]
			],
			// #3 Symptom event is not selected and can be marked as cause ('Mark as cause' is enabled).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'disabled' => ['Mark selected as symptoms']
				]
			],
			// #4 Symptom event is selected and can be marked as cause ('Mark as cause' is enabled).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>10 [Symptom]'],
					'disabled' => ['Mark selected as symptoms']
				]
			],
			// #5 Cause event can be marked as symptom (reverse logic).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>150 [Cause]']
				]
			],
			// #6 Cause event can be marked as symptom (reverse logic) when linked events are selected.
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>150 [Cause]', 'Problem trap>10 [Symptom]']
				]
			],
			// #7 Cause event can be marked as symptom when two cause events are not linked.
			[
				[
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => ['Information trap<75 [Cause]'],
					'disabled' => ['Mark as cause']
				]
			],
			// #8 Cause event can be marked as symptom when two cause events are selected but not linked.
			[
				[
					'locator' => 'Information trap<75 [Cause]',
					'selected_events' => ['Information trap<75 [Cause]', 'Information trap<100 [Cause]'],
					'disabled' => ['Mark as cause']
				]
			],
			// #9 Cause event can be marked as symptom when all rows are selected.
			[
				[
					'linked_events' => true,
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => [],
					'disabled' => ['Mark as cause']
				]
			]
		];
	}

	/**
	 * @dataProvider getContextMenuData
	 */
	public function testCauseAndSymptomEvents_ContextMenu($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&groupids[]='.self::$groupids['Group for Cause and Symptom check']);

		if (array_key_exists('linked_events', $data)) {
			$this->query('class:list-table')->asTable()->waitUntilPresent()->one()->query(self::EXPAND_XPATH)->one()->click();
		}

		if (array_key_exists('selected_events', $data)) {
			$this->selectTableRows($data['selected_events'], 'Problem');
		}

		$this->query('link', $data['locator'])->one()->waitUntilClickable()->click();
		$context_menu = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($context_menu->hasTitles(['VIEW', 'CONFIGURATION', 'PROBLEM']));

		// Check that problem options are available in context menu.
		$this->assertTrue($context_menu->hasItems(['Mark as cause', 'Mark selected as symptoms']));
		$this->assertEqualsCanonicalizing(CTestArrayHelper::get($data, 'disabled', []), $context_menu->getItems()
				->filter(CElementFilter::DISABLED)->asText()
		);
	}

	public function getConvertData() {
		return [
			// #0 Disabled checkbox with two selected cause events (mass update action).
			[
				[
					'problems' => ['Battery problem trap [Cause]', 'Battery problem trap2 [Cause]'],
					'state' => false // 'Convert to cause' checkbox state.
				]
			],
			// #1 Disabled checkbox when only cause event is updating.
			[
				[
					'problems' => ['Battery problem trap2 [Cause]'],
					'state' => false
				]
			],
			// #2 Enabled checkbox when mass update is performed for selected events that contain symptoms.
			[
				[
					'select_all' => true,
					'problems' => ['Battery problem trap [Cause]', 'Battery problem trap2 [Cause]',
						'Battery problem linked [Cause]', 'Battery problem linked [Symptom]',
						'Battery problem linked [Symptom2]'
					],
					'state' => true
				]
			],
			// #3 Enabled checkbox when mass update is performed when symptom events are expanded.
			[
				[
					'select_all' => true,
					'expand' => true,
					'problems' => ['Battery problem trap [Cause]', 'Battery problem trap2 [Cause]',
						'Battery problem linked [Cause]', 'Battery problem linked [Symptom]',
						'Battery problem linked [Symptom2]'
					],
					'state' => true
				]
			],
			// #4 Disabled checkbox when cause event that contains symptom(s) is updating.
			[
				[
					'problems' => ['Battery problem linked [Cause]'],
					'state' => false
				]
			],
			// #5 Enabled checkbox when symptom event is updating.
			[
				[
					'expand' => true,
					'problems' => ['Battery problem linked [Symptom]'],
					'state' => true
				]
			],
			// #6 Enabled checkbox when symptom events are selected and update form is opened via mass update action.
			[
				[
					'expand' => true,
					'problems' => ['Battery problem linked [Symptom]', 'Battery problem linked [Symptom2]'],
					'state' => true
				]
			]
		];
	}

	/**
	 * The particular test scenario checks 'Convert to cause' checkbox state in Update Problem form.
	 *
	 * @dataProvider getConvertData
	 */
	public function testCauseAndSymptomEvents_UpdateProblem($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&hostids[]='.
				self::$hostsids['hostids']['Host for Cause and Symptom update']
		);
		$table = $this->getTable();
		$count = count($data['problems']);

		if (array_key_exists('expand', $data)) {
			$table->findRow('Problem', 'Battery problem linked [Cause]')->query(self::EXPAND_XPATH)->one()->click();
		}

		if ($count > 1) {
			$this->selectTableRows(CTestArrayHelper::get($data, 'select_all', false) ? [] : $data['problems'], 'Problem');
			$this->query('button:Mass update')->waitUntilClickable()->one()->click();
		}
		else {
			$table->findRow('Problem', $data['problems'][0])->query('link:Update')->waitUntilClickable()->one()->click();
		}

		// Check 'Problem' field value and 'Convert to cause' checkbox state.
		$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
		$problem = ($count > 1) ? $count.' problems selected.' : $data['problems'][0];
		$this->assertEquals($problem, ($form->getField('Problem')->getText()));
		$this->assertTrue($form->getField('Convert to cause')->isEnabled($data['state']));
	}

	public static function getСauseAndSymptomsData() {
		return [
			// #0 Filtering results when "Show symptoms" => false.
			[
				[
					'fields' => [
						'Show symptoms' => false,
						'Show timeline' => false
					],
					'headers' => ['', '', '', 'Time', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					],
					'result' => ['Problem trap>150 [Cause]', 'Problem trap>10 [Symptom]']
				]
			],
			// #1 Filtering results when "Show symptoms" => true.
			[
				[
					'fields' => [
						'Show symptoms' => true,
						'Show timeline' => false
					],
					'headers' => ['', '', '', 'Time', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					],
					'result' => ['Problem trap>150 [Cause]', 'Problem trap>10 [Symptom]', 'Problem trap>10 [Symptom]']
				]
			],
			// #2 Cause event is shown when symptom trigger is disabled but 'Show symptoms' => false.
			[
				[
					'fields' => [
						'Show symptoms' => false
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_DISABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_ENABLED
					],
					'headers' => ['', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					],
					'result' => ['Problem trap>150 [Cause]']
				]
			],
			// #3 Cause event is shown when symptom trigger is disabled but 'Show symptoms' => true.
			[
				[
					'fields' => [
						'Show symptoms' => true
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_DISABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_ENABLED
					],
					'headers' => ['', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					],
					'result' => ['Problem trap>150 [Cause]']
				]
			],
			// #4 Symptom event is shown when cause trigger is disabled but 'Show symptoms' => true.
			[
				[
					'fields' => [
						'Show symptoms' => true
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_ENABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_DISABLED
					],
					'headers' => ['', '', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					],
					'result' => ['Problem trap>10 [Symptom]']
				]
			],
			// #5 No data is shown when cause trigger is disabled but 'Show symptoms' => false.
			[
				[
					'fields' => [
						'Show symptoms' => false
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_ENABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_DISABLED
					],
					'headers' => ['', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					]
				]
			],
			// #6 No data is shown when cause and symptom triggers are disabled but "Show symptoms" => true.
			[
				[
					'fields' => [
						'Show symptoms' => true
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_DISABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_DISABLED
					],
					'headers' => ['', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					]
				]
			],
			// #7 No data is shown when cause and symptom triggers are disabled but "Show symptoms" => false.
			[
				[
					'fields' => [
						'Show symptoms' => false
					],
					'triggers' => [
						'Problem trap>10 [Symptom]' => TRIGGER_STATUS_DISABLED,
						'Problem trap>150 [Cause]' => TRIGGER_STATUS_DISABLED
					],
					'headers' => ['', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
						'Duration', 'Update', 'Actions', 'Tags'
					]
				]
			]
		];
	}

	public function prepareTriggersStatus() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);
		// Reset triggers state to enabled.
		$default = [
			'Problem trap>10 [Symptom]' => TRIGGER_STATUS_ENABLED,
			'Problem trap>150 [Cause]' => TRIGGER_STATUS_ENABLED
		];

		foreach (CTestArrayHelper::get($data, 'triggers', $default) as $name => $status) {
			CDataHelper::call('trigger.update', [
				[
					'triggerid' => self::$triggerids[$name],
					'status' => $status
				]
			]);
		}
	}

	/**
	 * Test scenario checks "Problems" page filtering results using disabled/enabled triggers and/or "Show symptoms" flag.
	 *
	 * @dataProvider getСauseAndSymptomsData
	 * @onBefore prepareTriggersStatus
	 */
	public function testCauseAndSymptomEvents_FilterResults($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1&sort=clock&sortorder=ASC');

		// Check headers when Cause and Symptoms problems present in table and 'Show timeline' = true (default state).
		$this->assertEquals(['', '', '', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info', 'Host', 'Problem',
				'Duration', 'Update', 'Actions', 'Tags'], $this->getTable()->getHeadersText()
		);

		$this->page->open('zabbix.php?action=problem.view&hostids[]='.
				self::$hostsids['hostids']['Host for Cause and Symptom check']
		);
		$table = $this->getTable();
		CFilterElement::find()->one()->getForm()->fill($data['fields'])->submit();
		$table->waitUntilReloaded();
		$this->assertEquals($data['headers'], $table->getHeadersText());
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []), 'Problem');

		if (array_key_exists('result', $data)) {
			// Check 'cause and symptom' icons when trigger(s) state is changed.
			if (count($data['result']) === 1) {
				$row = $table->findRow('Problem', $data['result'][0]);
				$this->assertFalse($row->query(self::COLLAPSE_XPATH)->exists());
				$this->assertFalse($row->query('class:entity-count')->exists());
				$this->assertTrue($row->query(self::SYMPTOM_XPATH)->one(false)->isVisible($data['result'][0] === 'Problem trap>10 [Symptom]'));
			}
			else {
				// Check 'cause and symptom' icons and filtering results when trigger(s) state is unchanged.

				// For Cause problem arrow icon is not present at all.
				$this->assertFalse($table->findRow('Problem', 'Problem trap>150 [Cause]')->query(self::SYMPTOM_XPATH)->exists());

				// For both cases Symptom arrow icon is not visible for the collapsed problem.
				$this->assertFalse($table->getRow(1)->query(self::SYMPTOM_XPATH)->one()->isVisible());

				// When Symptom is present in the table it is marked with Symptom arrow icon.
				if ($data['fields']['Show symptoms']) {
					$this->assertTrue($table->getRow(2)->query(self::SYMPTOM_XPATH)->one()->isVisible());
				}
			}
		}
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		// Delete Hosts.
		CDataHelper::call('host.delete', [
			self::$hostsids['hostids']['Host for Cause and Symptom check'],
			self::$hostsids['hostids']['Host for Cause and Symptom check2'],
			self::$hostsids['hostids']['Host for Cause and Symptom update']
		]);

		// Delete Host groups.
		CDataHelper::call('hostgroup.delete', [
			self::$groupids['Group for Cause and Symptom check'],
			self::$groupids['Group for Cause and Symptom update']
		]);
	}
}

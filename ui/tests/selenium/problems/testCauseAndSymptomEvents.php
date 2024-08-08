<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @backup profiles
 *
 * @onBefore prepareData
 */
class testCauseAndSymptomEvents extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class
		];
	}

	protected static $groupids;
	protected static $triggerids;
	protected static $hostsids;
	const COLLAPSE_XPATH = 'xpath:.//button[(@title="Collapse")]';
	const EXPAND_XPATH = 'xpath:.//button[(@title="Expand")]';
	const SYMPTOM_XPATH = 'xpath:.//span[(@title="Symptom")]';

	public function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Cause and Symptom check'],
			['name' => 'Group for Cause and Symptom update']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items.
		self::$hostsids = CDataHelper::createHosts([
			[
				'host' => 'Host for Cause and Symptom check',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10777'
					]
				],
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
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10772'
					]
				],
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
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10770'
					]
				],
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

		// Create trigger based on item.
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
		foreach (self::$triggerids as $name => $id) {
			CDBHelper::setTriggerProblem($name, TRIGGER_VALUE_TRUE);
		}

		// Set cause and symptom(s) for predefined problems.
		$problems = [
			'Problem trap>150 [Cause]' => ['Problem trap>10 [Symptom]'],
			'Battery problem linked [Cause]' => ['Battery problem linked [Symptom]', 'Battery problem linked [Symptom2]']
		];
		foreach ($problems as $cause => $symptoms) {
			foreach ($symptoms as $symptom) {
				$causeid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr($cause));
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
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

		$result = [
			['Problem' => 'Problem trap>150 [Cause]'],
			['Problem' => 'Problem trap>10 [Symptom]']
		];
		$this->assertTableData($result);

		// Check collapsed symptom count.
		$cause = $table->findRow('Problem', 'Problem trap>150 [Cause]');
		$cause->query('class:entity-count')->one()->isVisible();
		$this->assertEquals(1, $cause->query('class:entity-count')->one()->getText());

		// Check 'Event details' rank value for 'Cause' event.
		$cause->query('xpath:.//a[contains(@href, "tr_events.php?triggerid='.self::$triggerids['Problem trap>150 [Cause]'].
				'&eventid")]')->one()->click();
		$this->page->waitUntilReady();
		$event_table = $this->query('xpath://section[@id="hat_eventdetails"]')->asTable()->waitUntilPresent()->one();
		$this->assertEquals('Cause', $event_table->getRow(7)->getColumn(1)->getText());

		$this->page->navigateBack();
		$this->checkState(true);
		$cause->query(self::EXPAND_XPATH)->one()->click();
		$this->checkState();

		// Check 'Event details' rank value for 'Symptom' event and possibility to navigate to 'Cause' event.
		$table->query('xpath:.//a[contains(@href, "tr_events.php?triggerid='.
				self::$triggerids['Problem trap>10 [Symptom]'].'&eventid")]')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals('Symptom (Problem trap>150 [Cause])', $event_table->getRow(7)->getColumn(1)->getText());
		$this->assertTrue($event_table->query('link:Problem trap>150 [Cause]')->one()->isClickable());
		$event_table->query('link:Problem trap>150 [Cause]')->one()->Click();
		$this->assertEquals('Cause', $event_table->getRow(7)->getColumn(1)->getText());

		// Check collapsed and expanded state via clicking on corresponded buttons.
		$this->page->open('zabbix.php?action=problem.view&hostids[]='.
				self::$hostsids['hostids']['Host for Cause and Symptom check']
		);
		$this->checkState(true);
		$cause->query(self::EXPAND_XPATH)->one()->click();
		$this->checkState();
		$cause->query(self::COLLAPSE_XPATH)->one()->click();
		$this->checkState(true);
	}

	/**
	 * @param boolean $collapsed	are symptom events in collapsed state or not
	 * @param string $name			cause event name
	 */
	protected function checkState($collapsed = false, $name = 'Problem trap>150 [Cause]') {
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();
		$cause = $table->findRow('Problem', $name);

		if ($collapsed) {
			// Check collapsed state.
			$this->assertTrue($cause->query(self::EXPAND_XPATH)->one()->isVisible());
			$this->assertFalse($cause->query(self::COLLAPSE_XPATH)->exists());
		}
		else {
			// Check expanded state.
			$this->assertTrue($cause->query(self::COLLAPSE_XPATH)->one()->isVisible());
			$this->assertFalse($cause->query(self::EXPAND_XPATH)->exists());
			$this->assertTrue($table->findRow('Problem', 'Problem trap>10 [Symptom]')->query(self::SYMPTOM_XPATH)->one()->isVisible());
		}
	}

	public function getContextMenuData() {
		return [
			// #0 Both menu options are disabled.
			[
				[
					'locator' => 'Problem trap>150 [Cause]',
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item disabled'
					]
				]
			],
			// #1 Both menu options are disabled in expanded state.
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>150 [Cause]',
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item disabled'
					]
				]
			],
			// #2 Both menu options are disabled if only cause event is selected and no symptoms are linked to that.
			[
				[
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => ['Information trap<100 [Cause]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item disabled'
					]
				]
			],
			// #3 Symptom event is not selected and can be marked as cause ('Mark as cause' is enabled).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'options' => [
						'Mark as cause' => 'menu-popup-item',
						'Mark selected as symptoms' => 'menu-popup-item disabled'
					]
				]
			],
			// #4 Symptom event is selected and can be marked as cause ('Mark as cause' is enabled).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>10 [Symptom]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item',
						'Mark selected as symptoms' => 'menu-popup-item disabled'
					]
				]
			],
			// #5 Cause event can be marked as symptom (reverse logic).
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>150 [Cause]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item',
						'Mark selected as symptoms' => 'menu-popup-item'
					]
				]
			],
			// #6 Cause event can be marked as symptom (reverse logic) when linked events are selected.
			[
				[
					'linked_events' => true,
					'locator' => 'Problem trap>10 [Symptom]',
					'selected_events' => ['Problem trap>150 [Cause]', 'Problem trap>10 [Symptom]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item',
						'Mark selected as symptoms' => 'menu-popup-item'
					]
				]
			],
			// #7 Cause event can be marked as symptom when two cause events are not linked.
			[
				[
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => ['Information trap<75 [Cause]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item'
					]
				]
			],
			// #8 Cause event can be marked as symptom when two cause events are selected but not linked.
			[
				[
					'locator' => 'Information trap<75 [Cause]',
					'selected_events' => ['Information trap<75 [Cause]', 'Information trap<100 [Cause]'],
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item'
					]
				]
			],
			// #9 Cause event can be marked as symptom when all rows are selected.
			[
				[
					'linked_events' => true,
					'locator' => 'Information trap<100 [Cause]',
					'selected_events' => [],
					'options' => [
						'Mark as cause' => 'menu-popup-item disabled',
						'Mark selected as symptoms' => 'menu-popup-item'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getContextMenuData
	 */
	public function testCauseAndSymptomEvents_ContextMenu($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&groupids[]='.self::$groupids['Group for Cause and Symptom check']);
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

		if (array_key_exists('linked_events', $data)) {
			$table->query(self::EXPAND_XPATH)->one()->click();
		}

		if (array_key_exists('selected_events', $data)) {
			$this->selectTableRows($data['selected_events'], 'Problem');
		}

		$this->query('link', $data['locator'])->one()->waitUntilClickable()->click();
		$context_menu = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($context_menu->hasTitles(['VIEW', 'CONFIGURATION', 'PROBLEM']));

		// Check that problem options are available in context menu.
		$this->assertTrue($context_menu->hasItems(array_keys($data['options'])));

		foreach ($data['options'] as $problem_options => $link) {
			if ($link === 'menu-popup-item disabled') {
				$this->assertFalse($context_menu->getItem($problem_options)->isEnabled());
			}
			else {
				$this->assertTrue($context_menu->getItem($problem_options)->isEnabled());
			}
		}
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
			// #1 Disabled checkbox when only couse event is updating.
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
						'Battery problem linked [Symptom2]'],
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
						'Battery problem linked [Symptom2]'],
					'state' => true
				]
			],
			// #4 Disabled checkbox when couse event that contains symptom(s) is updating.
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
		$form = CFilterElement::find()->one()->getForm();
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();
		$count = count($data['problems']);
		$table->findRows('Problem', $data['problems']);

		if (array_key_exists('expand', $data)) {
			$table->findRow('Problem', 'Battery problem linked [Cause]')->query(self::EXPAND_XPATH)->one()->click();
		}

		if ($count > 1) {
			if (array_key_exists('select_all', $data)) {
				$this->query('id:all_eventids')->asCheckbox()->one()->click();
			}
			else {
				$table->findRows('Problem', $data['problems'])->select();
			}
			$this->query('button:Mass update')->waitUntilClickable()->one()->click();
		}
		else {
			$table->findRow('Problem', $data['problems'][0])->query('link:Update')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();

		// Check 'Problem' field value and 'Convert to cause' checkbox state.
		$problem = $count > 1 ? $count.' problems selected.' : $data['problems'][0];
		$this->assertEquals($problem, ($form->getField('Problem')->getText()));
		$this->assertTrue($form->getField('id:change_rank')->isEnabled($data['state']));
	}

	public static function getCauseSymptomsData() {
		return [
			// #0 Show symptoms false.
			[
				[
					'fields' => [
						'Hosts' => 'Host for Cause and Symptom check',
						'Show symptoms' => false,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'Problem trap>150 [Cause]'],
						['Problem' => 'Problem trap>10 [Symptom]']
					]
				]
			],
			// #1 Show symptoms true.
			[
				[
					'fields' => [
						'Hosts' => 'Host for Cause and Symptom check',
						'Show symptoms' => true,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'Problem trap>150 [Cause]'],
						['Problem' => 'Problem trap>10 [Symptom]'],
						['Problem' => 'Problem trap>10 [Symptom]']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCauseSymptomsData
	 */
	public function testCauseAndSymptomEvents_Filter($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1&sort=clock&sortorder=ASC');
		$form = CFilterElement::find()->one()->getForm();
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

		// Check headers when Cause and Symptoms problems present in table and 'Show timeline' = true.
		$this->assertEquals(['', '', '', 'Time', '', '', 'Severity', 'Recovery time', 'Status', 'Info',
				'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags'], $table->getHeadersText()
		);

		$form->fill($data['fields']);
		$form->submit();
		$table->waitUntilReloaded();
		$this->assertTableData($data['result']);

		// Check headers when Cause and Symptoms problems present in table and 'Show timeline' = false.
		$this->assertEquals(['', '', '', 'Time', 'Severity', 'Recovery time', 'Status', 'Info',
				'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags'], $table->getHeadersText()
		);

		// For Cause problem arrow icon is not present at all.
		$this->assertFalse($table->getRow(0)->query(self::SYMPTOM_XPATH)->exists());

		// For both cases Symptom arrow icon is not visible for the collapsed problem.
		$this->assertFalse($table->getRow(1)->query(self::SYMPTOM_XPATH)->one()->isVisible());

		// When Symptom is present in the table it is marked with Symptom arrow icon.
		if ($data['fields']['Show symptoms']) {
			$this->assertTrue($table->getRow(2)->query(self::SYMPTOM_XPATH)->one()->isVisible());
		}
	}
}

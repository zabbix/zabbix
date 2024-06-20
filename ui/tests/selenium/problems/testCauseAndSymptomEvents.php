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
	 * Attach TagBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class
		];
	}

	protected static $groupids;

	public function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Cause and Symptom check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items for top triggers data test.
		CDataHelper::createHosts([
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
						'key_' => 'trap',
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
				'expression' => 'last(/Host for Cause and Symptom check/trap)>10',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Problem trap>100 [Cause]',
				'expression' => 'last(/Host for Cause and Symptom check/trap)>150',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);

		// Create problems.
		CDBHelper::setTriggerProblem('Problem trap>10 [Symptom]', TRIGGER_VALUE_TRUE);
		CDBHelper::setTriggerProblem('Problem trap>100 [Cause]', TRIGGER_VALUE_TRUE);

		// Set cause and symptoms.
		$causeid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr('Problem trap>100 [Cause]'));
		$symptomid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr('Problem trap>10 [Symptom]'));
		DBexecute('UPDATE problem SET cause_eventid='.$causeid.' WHERE name='.zbx_dbstr('Problem trap>10 [Symptom]'));
		DBexecute('INSERT INTO event_symptom (eventid, cause_eventid) VALUES ('.$symptomid.','.$causeid.')');
		DBexecute('UPDATE event_symptom SET cause_eventid='.$causeid.' WHERE eventid='.$symptomid);
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
						['Problem' => 'Problem trap>100 [Cause]'],
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
						['Problem' => 'Problem trap>100 [Cause]'],
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

		$symptom_xpath = 'xpath:.//span[(@title="Symptom")]';

		// For Cause problem arrow icon is not present at all.
		$this->assertFalse($table->getRow(0)->query($symptom_xpath)->exists());

		// For both cases Symptom arrow icon is not visible for the collapsed problem.
		$this->assertFalse($table->getRow(1)->query($symptom_xpath)->one()->isVisible());

		// When Symptom is present in the table it is marked with Symptom arrow icon.
		if ($data['fields']['Show symptoms']) {
			$this->assertTrue($table->getRow(2)->query($symptom_xpath)->one()->isVisible());
		}
	}
}

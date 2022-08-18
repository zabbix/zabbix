<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup hosts, hstgrp
 *
 * @onBefore prepareProblemsData
 */
class testFormUpdateProblem extends CWebTest {

	/**
	 * Id of the host with problems.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Ids of the triggers for problems.
	 *
	 * @var array
	 */
	protected static $triggerids;

	public function prepareProblemsData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Update']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Problems Update',
			'groups' => [['groupid' => $groupid]]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char', 'log', 'unsigned', 'text'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => self::$hostid,
				'name' => $item,
				'key_' => $item,
				'type' => 2,
				'value_type' => $i
			];
		}

		$items = CDataHelper::call('item.create', $items_data);
		$this->assertArrayHasKey('itemids', $items);

		// Create triggers based on items.
		$triggers_data = [];
		foreach ($item_names as $i => $item) {
			$triggers_data[] = [
				'description' => 'Trigger for '.$item,
				'expression' => 'last(/Host for Problems Update/'.$item.')=0',
				'priority' => $i
			];
		}

		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for float',
				'expression' => 'last(/Host for Problems Update/float)=0',
				'priority' => 0
			],
			[
				'description' => 'Trigger for char',
				'expression' => 'last(/Host for Problems Update/char)=0',
				'priority' => 1,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for log',
				'expression' => 'last(/Host for Problems Update/log)=0',
				'priority' => 2
			],
			[
				'description' => 'Trigger for unsigned',
				'expression' => 'last(/Host for Problems Update/unsigned)=0',
				'priority' => 3
			],
			[
				'description' => 'Trigger for text',
				'expression' => 'last(/Host for Problems Update/text)=0',
				'priority' => 4
			]
		]);
		$this->assertArrayHasKey('triggerids', $triggers);
		self::$triggerids = CDataHelper::getIds('description');

		// Create events.
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100550, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for float']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger for float').', 0)'
		);
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100551, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for char']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger for char').', 1)'
		);
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100552, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for log']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger for log').', 2)'
		);
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100553, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for unsigned']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger for unsigned').', 3)'
		);
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100554, 0, 0, '.
		zbx_dbstr(self::$triggerids ['Trigger for text']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger for text').', 4)'
		);

		// Create problems.
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100550, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for float']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for float').', 0)'
		);
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100551, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for char']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for char').', 1)'
		);
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100552, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for log']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for log').', 2)'
		);
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity, acknowledged) VALUES (100553, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for unsigned']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for unsigned').', 3, 1)'
		);
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100554, 0, 0, '.
				zbx_dbstr(self::$triggerids ['Trigger for text']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for text').', 4)'
		);

		// Change triggers' state to Problem. Manual close is true for the problem: Trigger for char'.
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for float'));
		DBexecute('UPDATE triggers SET value = 1, manual_close = 1, WHERE description = '.zbx_dbstr('Trigger for char'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for log'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for unsigned'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for text'));

		// Suppress the problem: 'Trigger for text'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until) VALUES (10050, 100554, NULL, 0)');

		// Acknowledge the problem: 'Trigger for unsigned'.
		CDataHelper::call('event.acknowledge', [
			'eventids' => 100553,
			'action' => 6,
			'message' => 'Acknowleged event'
		]);
	}

	public function getLayoutData() {
		return [
			[
				[
					'problems' => ['Trigger for float']
				]
			],
			[
				[
					'problems' => ['Trigger for char'],
					'close_enabled' => true
				]
			],
			[
				[
					'problems' => ['Trigger for float', 'Trigger for char'],
					'labels' => ['Problem', 'Message', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
							'Acknowledge', 'Close problem', ''],
					'close_enabled' => true
				]
			],
			[
				[
					'problems' => ['Trigger for text'],
					'unsuppress_enabled' => true
				]
			],
			[
				[
					'problems' => ['Trigger for unsigned'],
					'labels' => ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Suppress',
							'Unsuppress', 'Unacknowledge', 'Close problem', ''],
					'message' => 'Acknowleged event',
					'unacknowledge' => true,


					// Add history!
					'history' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormUpdateProblem_Layout($data) {
		// Open filtered Problems list.
		$this->page->login()->open('zabbix.php?&action=problem.view&show_suppressed=1&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		$table->findRows('Problem', $data['problems'])->select();
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Update problem', $dialog->getTitle());
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();

		$count = count($data['problems']);
		$default_labels = ['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
				'Acknowledge', 'Close problem', ''];
		$this->assertEquals(CTestArrayHelper::get($data, 'labels', $default_labels), $form->getLabels()->asText());

		$problem = $count > 1 ? $count.' problems selected.' : $data['problems'][0];
		$this->assertTrue($form->query('xpath://div[@class="wordbreak" and text()='.CXPathHelper::escapeQuotes($problem).']')->exists());

		$fields = [
			'id:message' => ['value' => '', 'maxlength' => 2048, 'enabled' => true],
			'id:scope_0' => ['value' => true, 'enabled' => true],    // Only selected problem.
			'id:scope_1' => ['value' => false, 'enabled' => true],   // Selected and all other problems of related triggers.
			'id:change_severity' => ['value' => false, 'enabled' => true],
			'id:severity' => ['value' => 'Not classified', 'enabled' => false],
			'id:suppress_problem' => ['value' => false, 'enabled' => true],
			'id:suppress_time_option' => ['value' => 'Until', 'enabled' => false],
			'id:suppress_until_problem' => ['maxlength' => 19, 'value' => 'now+1d', 'enabled' => false],
			'id:unsuppress_problem' => ['value' => false, 'enabled' => CTestArrayHelper::get($data, 'unsuppress_enabled', false)],
			(CTestArrayHelper::get($data, 'unacknowledge') ? 'Unacknowledge' : 'Acknowledge') => ['value' => false, 'enabled' => true],
			'Close problem' => ['value' => false, 'enabled' => CTestArrayHelper::get($data, 'close_enabled', false)]
		];

		foreach ($fields as $field => $attribute) {
			if (array_key_exists('value', $attribute)) {
				$this->assertEquals($attribute['value'], $form->getField($field)->getValue());
			}

			if (array_key_exists('enabled', $attribute)) {
				$this->assertTrue($form->getField($field)->isEnabled($attribute['enabled']));
			}

			if (array_key_exists('maxlength', $attribute)) {
				$this->assertEquals($attribute['maxlength'], $form->getField($field)->getAttribute('maxlength'));
			}
		}

		$button_queries = [
			'xpath:.//a[@title="Help"]' => true,
			'xpath:.//button[@title="Close"]' => true,
			'xpath:.//button[@id="suppress_until_problem_calendar"]' => false,
			'button:Update' => true,
			'button:Cancel' => true
		];

		foreach ($button_queries as $query => $clickable) {
			$this->assertEquals($clickable, $dialog->query($query)->one()->isClickable());
		}


		$dialog->close();
	}
}

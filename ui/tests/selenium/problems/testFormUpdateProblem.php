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
 * @backup hosts,hstgrp
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

	public function prepareProblemsData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Update']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Problems Update',
			'groups' => [['groupid' => $groupid]],
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				]
			]
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
		$itemids = CDataHelper::getIds('name');

		// Add value to items.
		CDataHelper::addItemData($itemids['float'], 0);
		CDataHelper::addItemData($itemids['char'], '0');
		CDataHelper::addItemData($itemids['log'], '0');
		CDataHelper::addItemData($itemids['unsigned'], 0);
		CDataHelper::addItemData($itemids['text'], '0');

		// Create triggers based on items.
		$triggers_data = [];
		foreach ($item_names as $i => $item) {
			$triggers_data[] = [
				'description' => 'Trigger for '.$item,
				'expression' => 'last(/Host for Problems Update/'.$item.')=0',
				'priority' => $i
			];
		}

		$triggers = CDataHelper::call('trigger.create', $triggers_data);
		$this->assertArrayHasKey('triggerids', $triggers);
		$triggerids = CDataHelper::getIds('description');

		// Create events.
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100550, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for float']).', '.zbx_dbstr(time()).', 0, 0, '.zbx_dbstr('Trigger for float').', 0)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100551, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for char']).', '.zbx_dbstr(time()).', 0, 0, '.zbx_dbstr('Trigger for char').', 1)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100552, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for log']).', '.zbx_dbstr(time()).', 0, 0, '.zbx_dbstr('Trigger for log').', 2)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100553, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for unsigned']).', '.zbx_dbstr(time()).', 0, 0, '.zbx_dbstr('Trigger for unsigned').', 3)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (100554, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for text']).', '.zbx_dbstr(time()).', 0, 0, '.zbx_dbstr('Trigger for text').', 4)');

		// Create problems.
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100550, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for float']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for float').', 0)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100551, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for char']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for char').', 1)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100552, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for log']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for log').', 2)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100553, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for unsigned']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for unsigned').', 3)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (100554, 0, 0, '.
				zbx_dbstr($triggerids['Trigger for text']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger for text').', 4)');

		// Change triggers' state to Problem.
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for float'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for char'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for log'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for unsigned'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger for text'));
	}



	public function getProblemsCount() {
		return [
			[
				[
					'count' => 1
				]
			],
//			[
//				[
//					'count' => 3
//				]
//			]
		];
	}

	/**
	 * @dataProvider getProblemsCount
	 */
	public function testFormUpdateProblem_Layout($data) {
		// Open filtered Problems list.
		$this->page->login()->open('zabbix.php?&action=problem.view&hostids%5B%5D='.self::$hostid)->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		$problem_names = [
			'Trigger for float',
			'Trigger for char',
			'Trigger for log',
			'Trigger for unsigned',
			'Trigger for text'
		];

		// Get random problem name.
		$count = array_rand($problem_names, $data['count']);

		$names = $problem_names[$count];
		$table->findRows('Problem', $names)->select();
		$this->query('button:Mass update')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Update problem', $dialog->getTitle());
		$form = $dialog->query('id:acknowledge_form')->asForm()->one();

		$this->assertEquals(['Problem', 'Message', 'History', 'Scope', 'Change severity', 'Suppress', 'Unsuppress',
				'Acknowledge', 'Close problem', ''], $form->getLabels()->asText()
		);

		$fields = [
//			'Problem' => ['value' => 'Test trigger with tag'],
			'id:message' => ['maxlength' => 2048, 'enabled' => true],
			'id:scope_0' => ['value' => true, 'enabled' => true],    // Only selected problem.
			'id:scope_1' => ['value' => false, 'enabled' => true],   // Selected and all other problems of related triggers.
			'id:change_severity' => ['value' => false, 'enabled' => true],
			'id:severity' => ['value' => 'Not classified', 'enabled' => false],
			'id:suppress_problem' => ['value' => false, 'enabled' => false],
			'id:suppress_time_option' => ['value' => 'Until', 'enabled' => false],
			'id:suppress_until_problem' => ['maxlength' => 19, 'value' => 'now+1d', 'enabled' => false],
			'id:unsuppress_problem' => ['value' => false, 'enabled' => false],
			'Acknowledge' => ['value' => false, 'enabled' => true],
			'Close problem' => ['value' => false, 'enabled' => false]
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
	}
}

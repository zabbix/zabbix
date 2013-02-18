<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('TRIGGER_GOOD', 0);
define('TRIGGER_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceTrigger extends CWebTest {

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceTrigger_setup() {
		DBsave_tables('hosts');
	}

	public static function simple() {
		return array(
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerSimple',
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerName',
					'hostCheck' => true)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerNotRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => TRIGGER_BAD,
					'description' => 'triggerSimple',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Trigger "triggerSimple" already exists on "Inheritance test template".')
				)
			)
		);
	}

	/**
	 * @dataProvider simple
	 */
	public function testInheritanceTrigger_simpleCreate($data) {
		$this->login('templates.php');

		$template = 'Inheritance test template';
		$host = 'Template inheritance test host';
		$itemKey = 'key-item-inheritance';

		$description = $data['description'];
		$expression = '{'.$template.':'.$itemKey.'.last(0)}=0';
		$expressionHost = '{'.$host.':'.$itemKey.'.last(0)}=0';

		$this->open('templates.php');
		$this->button_click("link=$template");
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Triggers']");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('description', $description);
		$this->input_type('expression', $expression);
		$this->button_click('save');
		$this->wait();

		switch ($data['expected']) {
			case TRIGGER_GOOD:
				$this->ok('Trigger added');
				$this->checkTitle('Configuration of triggers');
				$this->ok('CONFIGURATION OF TRIGGERS');
				break;

			case TRIGGER_BAD:
				$this->checkTitle('Configuration of triggers');
				$this->ok('CONFIGURATION OF TRIGGERS');
				foreach ($data['errors'] as $msg) {
					$this->ok($msg);
				}
				$this->ok(array('Name', 'Expression', 'Description'));
				break;
		}

		if (isset($data['hostCheck'])) {
			$this->open('hosts.php');
			$this->wait();
			$this->button_click("link=$host");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Triggers']");
			$this->wait();

			$this->ok("$template: $description");
			$this->button_click("link=$description");
			$this->wait();
			$this->assertElementValue('description', $description);
			$this->assertElementValue('expression', $expressionHost);
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
				$templateid = $row['triggerid'];
			}
			// host
			$result = DBselect("SELECT description FROM triggers where description = '".$description."'  AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$templateid = $row['triggerid'];
			}
			$result = DBselect("SELECT triggerid FROM triggers where description = '".$description."'  AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}

			$this->open('hosts.php');
			$this->wait();
			$this->button_click("link=$host");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Triggers']");
			$this->wait();

			$this->checkbox_select("g_triggerid_$triggerId");
			$this->dropdown_select('go', 'Delete selected');
			$this->button_click('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->ok(array('ERROR: Cannot delete triggers', 'Cannot delete templated trigger'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}
			$this->open('templates.php');
			$this->wait();
			$this->button_click("link=$template");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Triggers']");
			$this->wait();

			$this->checkbox_select("g_triggerid_$triggerId");
			$this->dropdown_select('go', 'Delete selected');
			$this->button_click('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->ok('Triggers deleted');
			$this->nok("$template: $description");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceTrigger_teardown() {
		DBrestore_tables('hosts');
	}
}

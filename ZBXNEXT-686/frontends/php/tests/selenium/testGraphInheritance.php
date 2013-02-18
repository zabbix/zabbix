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

define('GRAPH_GOOD', 0);
define('GRAPH_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testGraphInheritance extends CWebTest {

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testGraphInheritance_setup() {
		DBsave_tables('hosts');
	}

	public static function simple() {
		return array(
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphSimple',
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphName',
					'hostCheck' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => GRAPH_GOOD,
					'graphName' => 'graphNotRemove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => GRAPH_BAD,
					'graphName' => 'graphSimple',
					'errors' => array(
						'ERROR: Cannot add graph',
						'Graph with name "graphSimple" already exists in graphs or graph prototypes')
				)
			)
		);
	}

	/**
	 * @dataProvider simple
	 */
	public function testGraphInheritance_simpleCreate($data) {
		$this->login('templates.php');

		$template = 'Inheritance test template';
		$host = 'Template inheritance test host';

		$itemName = 'itemInheritance';
		$graphName = $data['graphName'];

		$this->open('templates.php');
		$this->button_click("link=$template");
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Graphs']");
		$this->wait();
		$this->button_click('form');
		$this->wait();


		$this->input_type('name', $graphName);
		$this->zbxLaunchPopup('add_item');
		$this->button_click("link=$itemName");
		$this->selectWindow(null);
		sleep(1);

		$this->button_click('save');
		$this->wait();

		switch ($data['expected']) {
			case GRAPH_GOOD:
				$this->ok('Graph added');
				$this->checkTitle('Configuration of graphs');
				$this->ok('CONFIGURATION OF GRAPHS');
				break;

			case GRAPH_BAD:
				$this->checkTitle('Configuration of graphs');
				$this->ok('CONFIGURATION OF GRAPHS');
				foreach ($data['errors'] as $msg) {
					$this->ok($msg);
				}
				break;
		}

		if (isset($data['hostCheck'])) {
			$this->open('hosts.php');
			$this->wait();
			$this->button_click("link=$host");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Graphs']");
			$this->wait();

			$this->ok("$template: $graphName");
			$this->button_click("link=$graphName");
			$this->wait();

			$this->assertElementValue('name', $graphName);
			$this->assertElementPresent("//span[text()='$host: $itemName']");
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $graphName);
				$templateid = $row['graphid'];
			}

			// host
			$result = DBselect("SELECT name FROM graphs where name = '".$graphName."' AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $graphName);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$templateid = $row['graphid'];
			}

			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$graphId = $row['graphid'];
			}
			$this->open('hosts.php');
			$this->wait();
			$this->button_click("link=$host");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Graphs']");
			$this->wait();

			$this->assertElementPresent("group_graphid_$graphId");
			$this->assertAttribute("//input[@id='group_graphid_200014']/@disabled", 'disabled');
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$graphid = $row['graphid'];
			}

			$this->open('templates.php');
			$this->wait();
			$this->button_click("link=$template");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Graphs']");
			$this->wait();

			$this->checkbox_select("group_graphid_$graphid");
			$this->dropdown_select('go', 'Delete selected');
			$this->button_click('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->ok('Graphs deleted');
			$this->nok("$template: $graphName");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testGraphInheritance_teardown() {
		DBrestore_tables('hosts');
	}
}

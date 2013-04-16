<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('TRIGGER_GOOD', 0);
define('TRIGGER_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testFormTriggerPrototype extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testFormDiscoveryRule';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-form';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'itemDiscovery';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKey = 'item-discovery-prototype';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormTriggerPrototype_Setup() {
		DBsave_tables('triggers');
	}

	// Returns layout data
	public static function layout() {
		return array(
			array(
				array('constructor' => 'open', 'host' => 'Simple form test host')
			),
			array(
				array('constructor' => 'open_close', 'host' => 'Simple form test host')
			),
			array(
				array('constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host')
			),
			array(
				array('constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'Not classified', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'Information', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'Warning', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'Average', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'High', 'host' => 'Simple form test host')
			),
			array(
				array('severity' => 'Disaster', 'host' => 'Simple form test host')
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype2',
					'constructor' => 'open_close'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype3'
				)
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormTriggerPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
		}

		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Trigger prototypes');

		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF TRIGGER PROTOTYPES', "Trigger prototypes of ".$this->discoveryRule));

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGER PROTOTYPES');
		$this->assertElementPresent("//div[@id='tab_triggersTab' and text()='Trigger prototype']");

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$this->zbxTestClickWait("//span[text()='Expression constructor']");
					break;
				case 'open_close':
					$this->zbxTestClickWait("//span[text()='Expression constructor']");
					$this->zbxTestClickWait("//span[text()='Close expression constructor']");
					break;
			}
		}

		$this->zbxTestTextPresent('Trigger prototype');
		$this->zbxTestTextPresent('Name');
		$this->assertVisible('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '255');
		$this->assertAttribute("//input[@id='description']/@size", '50');

		if (!(isset($data['constructor'])) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(array('Expression', 'Expression constructor'));
			$this->assertVisible('expression');
			$this->assertAttribute("//textarea[@id='expression']/@rows", '7');
			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Add');

			$this->assertElementNotPresent('add_expression');
			$this->assertElementNotPresent('insert_macro');
			$this->assertElementNotPresent('exp_list');
			}
			else {
			$this->zbxTestTextPresent('Expression');
			$this->assertVisible('expr_temp');
			$this->assertAttribute("//textarea[@id='expr_temp']/@rows", '7');
			$this->assertAttribute("//textarea[@id='expr_temp']/@readonly", 'readonly');
			$this->zbxTestTextNotPresent('Expression constructor');
			$this->assertNotVisible('expression');

			if (!isset($data['form'])) {
				$this->assertVisible('add_expression');
				$this->assertAttribute("//input[@id='add_expression']/@value", 'Add');
			}
			else {
				$this->assertElementNotPresent('add_expression');
			}

			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Edit');

			$this->assertVisible('insert_macro');
			$this->assertAttribute("//input[@id='insert_macro']/@value", 'Insert macro');

			$this->zbxTestTextPresent(array('Target', 'Expression', 'Action', 'Close expression constructor'));
			$this->assertVisible('exp_list');
			$this->zbxTestTextPresent('Close expression constructor');
		}

		$this->zbxTestTextPresent('Multiple PROBLEM events generation');
		$this->assertVisible('type');
		$this->assertAttribute("//input[@id='type']/@type", 'checkbox');

		$this->zbxTestTextPresent('Description');
		$this->assertVisible('comments');
		$this->assertAttribute("//textarea[@id='comments']/@rows", '7');

		$this->zbxTestTextPresent('URL');
		$this->assertVisible('url');
		$this->assertAttribute("//input[@id='url']/@maxlength", '255');
		$this->assertAttribute("//input[@id='url']/@size", '50');

		$this->assertVisible('severity_0');
		$this->assertAttribute("//*[@id='severity_0']/@checked", 'checked');
		$this->assertElementPresent("//*[@id='severity_label_0']/span[text()='Not classified']");
		$this->assertVisible('severity_1');
		$this->assertElementPresent("//*[@id='severity_label_1']/span[text()='Information']");
		$this->assertVisible('severity_2');
		$this->assertElementPresent("//*[@id='severity_label_2']/span[text()='Warning']");
		$this->assertVisible('severity_3');
		$this->assertElementPresent("//*[@id='severity_label_3']/span[text()='Average']");
		$this->assertVisible('severity_4');
		$this->assertElementPresent("//*[@id='severity_label_4']/span[text()='High']");
		$this->assertVisible('severity_5');
		$this->assertElementPresent("//*[@id='severity_label_5']/span[text()='Disaster']");

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClick('severity_0');
					break;
				case 'Information':
					$this->zbxTestClick('severity_1');
					break;
				case 'Warning':
					$this->zbxTestClick('severity_2');
					break;
				case 'Average':
					$this->zbxTestClick('severity_3');
					break;
				case 'High':
					$this->zbxTestClick('severity_4');
					break;
				case 'Disaster':
					$this->zbxTestClick('severity_5');
					break;
				default:
					break;
			}
		}

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//input[@id='status']/@type", 'checkbox');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		if (isset($data['form'])) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');

			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');
		}
		else {
			$this->assertElementNotPresent('clone');
			$this->assertElementNotPresent('delete');
		}

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from triggers t left join functions f on f.triggerid=t.triggerid where f.itemid='23804' and t.description LIKE 'testFormTriggerPrototype%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormTriggerPrototype_SimpleUpdate($data) {
		$description = $data['description'];

		$sqlTriggers = "select * from triggers ORDER BY triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Trigger prototypes');

		$this->zbxTestClickWait('link='.$description);
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Trigger updated');
		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF TRIGGER PROTOTYPES', "Trigger prototypes of ".$this->discoveryRule));
		$this->zbxTestTextPresent("$description");

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormTriggerPrototype_Teardown() {
		DBrestore_tables('triggers');
	}
}

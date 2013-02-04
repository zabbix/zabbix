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
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

define('TRIGGER_GOOD', 0);
define('TRIGGER_BAD', 1);

class testFormTrigger extends CWebTest {

	// returns all possible trigger data
	public static function triggerData() {
		return array(
			array(
				array('constructor' => 'open')
			),
			array(
				array('constructor' => 'open_close')
			),
			array(
				array('constructor' => 'open', 'severity' => 'Warning')
			),
			array(
				array('constructor' => 'open_close', 'severity' => 'Disaster')
			),
			array(
				array('severity' => 'Not classified')
			),
			array(
				array('severity' => 'Information')
			),
			array(
				array('severity' => 'Warning')
			),
			array(
				array('severity' => 'Average')
			),
			array(
				array('severity' => 'High')
			),
			array(
				array('severity' => 'Disaster')
			)
		);
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormTrigger_setup() {
		DBsave_tables('triggers');
	}

	/**
	 * @dataProvider triggerData
	 */
	public function testFormTrigger_CheckLayout($data) {

		$this->login('triggers.php');
		$this->checkTitle('Configuration of triggers');
		$this->ok('CONFIGURATION OF TRIGGERS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of triggers');

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$this->button_click("//span[text()='Expression constructor']");
					sleep(1);
					break;
				case 'open_close':
					$this->button_click("//span[text()='Expression constructor']");
					sleep(1);
					$this->button_click("//span[text()='Close expression constructor']");
					sleep(1);
					break;
				default:
					break;
			}
		}

		$this->ok('Trigger');
		$this->ok('Name');
		$this->assertVisible('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '255');
		$this->assertAttribute("//input[@id='description']/@size", '50');

		if (!(isset($data['constructor'])) || $data['constructor'] == 'open_close') {
			$this->ok(array('Expression', 'Expression constructor'));
			$this->assertVisible('expression');
			$this->assertAttribute("//textarea[@id='expression']/@rows", '7');
			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Add');

			$this->assertElementNotPresent('add_expression');
			$this->assertElementNotPresent('insert_macro');
			$this->assertElementNotPresent('exp_list');
		} else {
			$this->ok('Expression');
			$this->assertVisible('expr_temp');
			$this->assertAttribute("//textarea[@id='expr_temp']/@rows", '7');
			$this->assertAttribute("//textarea[@id='expr_temp']/@readonly", 'readonly');
			$this->nok('Expression constructor');
			$this->assertNotVisible('expression');

			$this->assertVisible('add_expression');
			$this->assertAttribute("//input[@id='add_expression']/@value", 'Add');

			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Edit');

			$this->assertVisible('insert_macro');
			$this->assertAttribute("//input[@id='insert_macro']/@value", 'Insert macro');

			$this->ok(array('Target', 'Expression', 'Error', 'Action', 'Close expression constructor'));
			$this->assertVisible('exp_list');
			$this->ok('Close expression constructor');
		}


		$this->ok('Multiple PROBLEM events generation');
		$this->assertVisible('type');
		$this->assertAttribute("//input[@id='type']/@type", 'checkbox');// TODO not checked

		$this->ok('Description');
		$this->assertVisible('comments');
		$this->assertAttribute("//textarea[@id='comments']/@rows", '7');

		$this->ok('URL');
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
					$this->button_click('severity_0');
					break;
				case 'Information':
					$this->button_click('severity_1');
					break;
				case 'Warning':
					$this->button_click('severity_2');
					break;
				case 'Average':
					$this->button_click('severity_3');
					break;
				case 'High':
					$this->button_click('severity_4');
					break;
				case 'Disaster':
					$this->button_click('severity_5');
					break;
				default:
					break;
			}
		}

		$this->ok('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//input[@id='status']/@type", 'checkbox');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');

		$this->button_click('link=Dependencies');
		$this->ok(array('Dependencies', 'Name', 'Action', 'No dependencies defined'));
		$this->assertElementPresent('bnt1');
		$this->assertAttribute("//input[@id='bnt1']/@value", 'Add');
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormTrigger_teardown() {
		DBrestore_tables('triggers');
	}
}
?>

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

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormTrigger_setup() {
		DBsave_tables('triggers');
	}

	/**
	 * @dataProvider itemTypes
	 */
	public function testFormTrigger_CheckLayout() {

		$this->login('triggers.php');
		$this->checkTitle('Configuration of triggers');
		$this->ok('CONFIGURATION OF TRIGGERS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of triggers');

		$this->ok('Name');
		$this->ok('Expression');
		$this->ok('Expression constructor');
		$this->ok('Multiple PROBLEM events generation');
		$this->ok('Description');
		$this->ok('URL');
		$this->ok('Severity');
		$this->ok('Enabled');
		$this->ok('Not classified');
		$this->ok('Information');
		$this->ok('Warning');
		$this->ok('Average');
		$this->ok('High');
		$this->ok('Disaster');
		$this->ok('Dependencies');
		$this->ok('No dependencies defined.');
		$this->ok('Name');
		$this->ok('Action');

		$this->assertElementPresent('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '255');

		$this->assertElementPresent('expression');
		$this->assertAttribute("//*[@id='expression']/@rows", '7');

		$this->assertElementPresent("//*/span[text()='Expression constructor']");

		// expression constructor
		$this->button_click("//*/span[text()='Expression constructor']");
		sleep(1);

		$this->ok('Target');
		$this->ok('Expression');
		$this->ok('Error');
		$this->ok('Action');
		$this->ok('Close expression constructor');

		$this->assertElementPresent('insert');
		$this->assertElementPresent('insert_macro');
		$this->assertElementPresent('expr_temp');
		$this->assertAttribute("//textarea[@id='expr_temp']/@readonly", 'readonly');
		$this->assertElementPresent("//*/span[text()='Close expression constructor']");

		$this->button_click("//*/span[text()='Close expression constructor']");
		sleep(1);
		$this->nok('Insert macro');
		$this->nok('Close expression constructor');

		$this->assertElementPresent('type');
		$this->assertAttribute("//input[@id='type']/@type", 'checkbox');
		$this->assertFalse($this->isChecked('type'));

		$this->assertElementPresent('comments');
		$this->assertAttribute("//*[@id='comments']/@rows", '7');

		$this->assertElementPresent('url');
		$this->assertAttribute("//input[@id='url']/@maxlength", '255');

		$this->assertElementPresent('severity_0');
		$this->assertAttribute("//*[@id='severity_0']/@checked", 'checked');
		$this->assertElementPresent("//*[@id='severity_label_0']/span[text()='Not classified']");

		$this->assertElementPresent('severity_1');
		$this->assertElementPresent("//*[@id='severity_label_1']/span[text()='Information']");

		$this->assertElementPresent('severity_2');
		$this->assertElementPresent("//*[@id='severity_label_2']/span[text()='Warning']");

		$this->assertElementPresent('severity_3');
		$this->assertElementPresent("//*[@id='severity_label_3']/span[text()='Average']");

		$this->assertElementPresent('severity_4');
		$this->assertElementPresent("//*[@id='severity_label_4']/span[text()='High']");

		$this->assertElementPresent('severity_5');
		$this->assertElementPresent("//*[@id='severity_label_5']/span[text()='Disaster']");

		$this->assertElementPresent('status');
		$this->assertTrue($this->isChecked('status'));

		$this->assertElementPresent('save');
		$this->assertElementPresent('cancel');
		$this->button_click('link=Dependencies');
		$this->assertElementPresent('bnt1');
		$this->button_click('link=Trigger');
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormTrigger_teardown() {
		DBrestore_tables('triggers');
	}
}
?>

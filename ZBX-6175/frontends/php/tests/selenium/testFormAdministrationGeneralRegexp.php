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

class testFormAdministrationGeneralRegexp extends CWebTest {

	private $regexp = 'test_regexp1';
	private $regexp2 = 'test_regexp2';
	private $cloned_regexp = 'test_regexp1_clone';

	public function testFormAdministrationGeneralRegexp_Layout() {
		$this->login('adm.gui.php');
		$this->dropdown_select_wait('configDropDown', 'Regular expressions');
		$this->checkTitle('Configuration of regular expressions');
		$this->ok('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->ok(array('Regular expressions', 'Name', 'Expressions'));

		// clicking "New regular expression" button
		$this->button_click('form');
		$this->wait();

		$this->checkTitle('Configuration of regular expressions');
		$this->ok('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->ok('Name');
		$this->ok('Expressions');
		$this->assertElementPresent('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '128');
		$this->assertAttribute("//input[@id='name']/@size", '50');


		$this->assertAttribute("//input[@id='expressionNew']/@maxlength", '255');
		$this->assertAttribute("//input[@id='expressionNew']/@size", '50');

		$this->assertElementPresent("//select[@id='typeNew']/option[text()='Character string included']");
		$this->assertElementPresent("//select[@id='typeNew']/option[text()='Any character string included']");
		$this->assertElementPresent("//select[@id='typeNew']/option[text()='Character string not included']");
		$this->assertElementPresent("//select[@id='typeNew']/option[text()='Result is TRUE']");
		$this->assertElementPresent("//select[@id='typeNew']/option[text()='Result is FALSE']");
	}

	// Creating regexps
	public static function dataCreate() {

		// result, r.name, r.test_string, e.expression, e.expression_type, e.exp_delimiter, e.case_sensitive
		// type: 0-Character string included, 1-Any character string included, 2- Character string not included, 3-Result is TRUE, 4- Result is FALSE
		return array(
			array('TRUE', 'test_regexp1', 'first test string', 'first test string', 'Character string included', ',', 1),
			array('FALSE', 'test_regexp1_2', 'first test string', 'first test string2', 'Character string included', ',', 1),
			array('TRUE', 'test_regexp2', 'second test string', 'test string', 'Any character string included', '.', 0),
			array('FALSE', 'test_regexp2_2', 'second test string', 'second string', 'Any character string included', '.', 0),
			array('TRUE', 'test_regexp3', 'test', 'abcd test', 'Character string not included', '.', 0),
			array('FALSE', 'test_regexp3_2', 'test', 'test', 'Character string not included', '.', 0),
			array('TRUE', 'test_regexp4', 'abcd', 'abcd', 'Result is TRUE', '.', 0),
			array('FALSE', 'test_regexp4_2', 'abcd', 'qwerty', 'Result is TRUE', '.', 0),
			array('TRUE', 'test_regexp5', 'abcd', 'asdf', 'Result is FALSE', '.', 0),
			array('FALSE', 'test_regexp5_2', 'abcd', 'abcd', 'Result is FALSE', '.', 0)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationGeneralRegexp_Create($result, $name, $test_string, $expression, $expression_type, $exp_delimiter, $case_sensitive) {
		$this->login('adm.regexps.php');
		// clicking "New regular expression" button
		$this->button_click('form');
		$this->wait();

		// adding regexp
		$this->input_type('name', $name);
		$this->input_type('test_string', $test_string);
		$this->button_click('add');

		// clicking button "add_expression"
		$this->input_type('expressionNew', $expression);

		// $this->dropdown_select_wait('new_expression[expression_type]', 'Character string included');
		$this->dropdown_select('typeNew', $expression_type);
		$this->checkbox_select('case_sensitiveNew');
		$this->button_click('saveExpression');
		$this->button_click('save');
		$this->wait();
		$this->ok('Regular expression added');

		// select * from regexps r, expressions e where r.name='...' and r.regexpid=e.regexpid
		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($name).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Regular expression with such name has not been added');
	}

	public static function dataUpdate() {
		// name
		return array(
			array('test_regexp1')
		);
	}

	/**
	 * @dataProvider dataUpdate
	 */
	public function testFormAdministrationGeneralRegexp_AddExisting($name) {
		$this->login('adm.regexps.php');

		// clicking "New regular expression" button
		$this->button_click('form');
		$this->wait();

		// adding regexp
		$this->input_type('name', $name);
		$this->input_type('test_string', 'first test string');
		$this->button_click('add');

		// clicking button "add_expression"
		$this->input_type('expressionNew', 'first test string');
		$this->checkbox_select('case_sensitiveNew');
		$this->button_click('saveExpression');
		$this->button_click('save');
		$this->wait();
		$this->ok(array('ERROR: Cannot add regular expression', 'Regular expression', 'already exists.'));
	}

	public function testFormAdministrationGeneralRegexp_AddIncorrect() {
		// creating regexp without expression
		$this->login('adm.regexps.php');

		// clicking "New regular expression" button
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', '1_regexp3');
		$this->button_click('save');
		$this->wait();
		$this->ok(array('ERROR: Page received incorrect data', 'Warning. Field "expressions" is mandatory.'));
	}

	public function testFormAdministrationGeneralRegexp_Test() {
		// Testing regexp using Test button in the regexp properties form
		$this->login('adm.regexps.php');
		$this->click('link='.$this->regexp);
		$this->wait();

		$this->click('link=Test');
		// Test #1 for the result=True
		$this->waitForCondition("selenium.browserbot.getCurrentWindow().jQuery.active == 0", 3000);
		$this->ok('TRUE');
	}

	public function testFormAdministrationGeneralRegexp_Test2() {
		$this->login('adm.regexps.php');
		// test #2 for the result=False
		$this->click('link='.$this->regexp);
		$this->wait();
		$this->click('link=Test');

		$this->input_type('test_string', 'abcdef');
		$this->button_click('testExpression');
		$this->waitForCondition("selenium.browserbot.getCurrentWindow().jQuery.active == 0", 3000);

		$this->ok('FALSE');
	}

	public function testFormAdministrationGeneralRegexp_Clone() {
		// cloning regexp
		$this->login('adm.regexps.php');
		$this->click('link='.$this->regexp);
		$this->wait();
		$this->button_click('clone');
		$this->input_type('name', $this->regexp.'_clone');
		$this->button_click('save');
		$this->wait();
		$this->ok('Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->cloned_regexp).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Cloned regular expression does not exist in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Update() {
		// Updating regexp
		$this->login('adm.regexps.php');
		$this->click('link='.$this->regexp);
		$this->wait();
		$this->input_type('name', $this->regexp.'2');
		$this->button_click('save');
		$this->wait();
		$this->ok('Regular expression updated');

		//$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name=\''.$this->regexp.'2\' AND r.regexpid=e.regexpid';
		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->regexp.'2').' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Regexp name has not been changed in the DB');
	}

	public static function dataDelete() {
		return array(
			array('test_regexp2')
		);
	}

	/**
	 * @dataProvider dataDelete
	 */
	public function testFormAdministrationGeneralRegexp_Delete($name) {
		DBsave_tables('regexps');

		// deleting regexp using "Delete" button in the regexp properties form
		$this->login('adm.regexps.php');

		$this->click('link='.$this->regexp2);
		$this->wait();

		$this->chooseOkOnNextConfirmation();
		$this->button_click('delete');
		$this->getConfirmation();
		$this->wait();
		$this->ok(array('Regular expression deleted', 'CONFIGURATION OF REGULAR EXPRESSIONS', 'Regular expressions', 'Name', 'Expressions'));

		// checking that regexp "test_regexp2" has been deleted from the DB
		$sql = 'SELECT * FROM regexps r WHERE r.name='.zbx_dbstr($name);
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.regexpid=e.regexpid and r.name='.zbx_dbstr($name);

		// this check will fail as at this moment expressions are not deleted when deleting related regexp
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');

		DBrestore_tables('regexps');
	}

	public function testFormAdministrationGeneralRegexp_DeleteAll() {
		$this->login('adm.regexps.php');
		$this->checkbox_select('all_regexps');
		$this->dropdown_select('go', 'Delete selected');

		$this->chooseOkOnNextConfirmation();
		$this->click('goButton');
		$this->getConfirmation();
		$this->wait();
		$this->ok('Regular expressions deleted');

		$sql = 'SELECT * FROM regexps';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM expressions';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');
	}
}

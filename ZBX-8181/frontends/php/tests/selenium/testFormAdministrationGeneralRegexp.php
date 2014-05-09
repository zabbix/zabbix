<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class testFormAdministrationGeneralRegexp extends CWebTest {
	private $regexp = 'test_regexp1';
	private $regexp2 = 'test_regexp2';
	private $cloned_regexp = 'test_regexp1_clone';

	public function testFormAdministrationGeneralRegexp_Layout() {

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Regular expressions');
		$this->checkTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->zbxTestTextPresent(array('Regular expressions', 'Name', 'Expressions'));

		// clicking "New regular expression" button
		$this->zbxTestClickWait('form');

		$this->checkTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->zbxTestTextPresent('Regular expression');
		$this->zbxTestTextPresent('Name');
		$this->assertElementPresent('rename');
		$this->assertAttribute("//input[@id='rename']/@maxlength", '128');
		$this->assertAttribute("//input[@id='rename']/@size", '60');
		$this->assertElementPresent('test_string', 'save', 'test', 'cancel', 'new_expression', 'delete_expression');

		$this->zbxTestTextPresent(array('Test string', 'Expressions', 'Result', 'Expression', 'Expected result', 'Case sensitive', 'Edit'));
		$this->zbxTestClickWait('new_expression');
		$this->zbxTestTextPresent(array('New expression', 'Expression', 'Expression type', 'Case sensitive'));

		$this->assertAttribute("//input[@id='new_expression_expression']/@maxlength", '255');
		$this->assertAttribute("//input[@id='new_expression_expression']/@size", '60');
		$this->assertElementPresent("//select[@id='new_expression_expression_type']/option[text()='Character string included']");
		$this->assertElementPresent("//select[@id='new_expression_expression_type']/option[text()='Any character string included']");
		$this->assertElementPresent("//select[@id='new_expression_expression_type']/option[text()='Character string not included']");
		$this->assertElementPresent("//select[@id='new_expression_expression_type']/option[text()='Result is TRUE']");
		$this->assertElementPresent("//select[@id='new_expression_expression_type']/option[text()='Result is FALSE']");
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

		$this->zbxTestLogin('adm.regexps.php');
		// clicking "New regular expression" button
		$this->zbxTestClickWait('form');

		// adding regexp
		$this->input_type('rename', $name);
		$this->input_type('test_string', $test_string);
		$this->zbxTestClickWait('new_expression');

		// clicking button "add_expression"
		$this->input_type('new_expression[expression]', $expression);

		// $this->zbxTestDropdownSelectWait('new_expression[expression_type]', 'Character string included');
		$this->zbxTestDropdownSelectWait('new_expression_expression_type', $expression_type);
		$this->zbxTestCheckboxSelect('new_expression_case_sensitive');
		$this->zbxTestClickWait('add_expression');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Regular expression added');

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

		$this->zbxTestLogin('adm.regexps.php');

		// clicking "New regular expression" button
		$this->zbxTestClickWait('form');

		// adding regexp
		$this->input_type('rename', $name);
		$this->input_type('test_string', 'first test string');
		$this->zbxTestClickWait('new_expression');

		// clicking button "add_expression"
		$this->input_type('new_expression[expression]', 'first test string');
		$this->zbxTestCheckboxSelect('new_expression[case_sensitive]');
		$this->zbxTestClickWait('add_expression');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Cannot add regular expression', 'Regular expression', 'already exists.'));
	}

	public function testFormAdministrationGeneralRegexp_AddIncorrect() {

		// creating regexp without teststring
		$this->zbxTestLogin('adm.regexps.php');

		// clicking "New regular expression" button
		$this->zbxTestClickWait('form');

		$this->input_type('rename', '1_regexp3');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Test string"', 'Warning. Field "expressions" is mandatory.'));
	}

	public function testFormAdministrationGeneralRegexp_AddIncorrect2() {

		// Creating regexp without expression
		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestClickWait('form');
		$this->input_type('test_string', 'first test string');
		$this->zbxTestClickWait('new_expression');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Field "expressions" is mandatory.'));
	}

	public function testFormAdministrationGeneralRegexp_Test() {

		// Testing regexp using Test button in the regexp properties form
		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestClickWait('link='.$this->regexp);

		// Test #1 for the result=True
		$this->zbxTestClickWait('test');
		$this->checkTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('TRUE');
	}

	public function testFormAdministrationGeneralRegexp_Test2() {

		$this->zbxTestLogin('adm.regexps.php');
		// test #2 for the result=False
		$this->zbxTestClickWait('link='.$this->regexp);
		$this->input_type('test_string', 'abcdef');
		$this->zbxTestClickWait('test');

		// Should also check for the error "Incorrect expression" when clicking button "test", then will need to save this regexp
		$this->zbxTestClickWait('test');
		$this->zbxTestTextPresent('FALSE');
	}

	public function testFormAdministrationGeneralRegexp_Clone() {

		// cloning regexp
		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestClickWait('link='.$this->regexp);
		$this->zbxTestClickWait('clone');
		$this->input_type('rename', $this->regexp.'_clone');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->cloned_regexp).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Cloned regular expression does not exist in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Update() {

		// Updating regexp
		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestClickWait('link='.$this->regexp);
		$this->input_type('rename', $this->regexp.'2');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Regular expression updated');

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
		$this->zbxTestLogin('adm.regexps.php');
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClickWait('link='.$this->regexp2);
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent(array('Regular expression deleted', 'CONFIGURATION OF REGULAR EXPRESSIONS', 'Regular expressions', 'Name', 'Expressions'));

		// checking that regexp "test_regexp2" has been deleted from the DB
		$sql = 'SELECT * FROM regexps r WHERE r.name='.zbx_dbstr($name);
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.regexpid=e.regexpid and r.name='.zbx_dbstr($name);

		// this check will fail as at this moment expressions are not deleted when deleting related regexp
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');

		DBrestore_tables('regexps');
	}

	public function testFormAdministrationGeneralRegexp_DeleteAll() {

		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestCheckboxSelect('all_regexps');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Regular expressions deleted');

		$sql = 'SELECT * FROM regexps';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM expressions';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');
	}
}

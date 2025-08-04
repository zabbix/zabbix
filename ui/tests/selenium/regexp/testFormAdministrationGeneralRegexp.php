<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup regexps
 */
class testFormAdministrationGeneralRegexp extends CLegacyWebTest {

	private $regexp = 'test_regexp1';
	private $regexp2 = 'test_regexp2';
	private $cloned_regexp = 'test_regexp1_clone';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function testFormAdministrationGeneralRegexp_Layout() {
		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Regular expressions');
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestTextPresent(['Regular expressions', 'Name', 'Expressions']);

		$this->zbxTestClickButtonText('New regular expression');

		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Expressions');
		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", "maxlength", 128);

		$this->zbxTestAssertAttribute("//input[@id='expressions_0_expression']", "maxlength", 255);

		$this->zbxTestDropdownHasOptions('expressions_0_expression_type', [
			'Character string included',
			'Any character string included',
			'Character string not included',
			'Result is TRUE',
			'Result is FALSE'
			]);
	}

	public static function dataCreate() {

		// result, r.name, r.test_string, e.expression, e.expression_type, e.exp_delimiter, e.case_sensitive
		// type: 0-Character string included, 1-Any character string included, 2- Character string not included, 3-Result is TRUE, 4- Result is FALSE
		return [
			['TRUE', 'test_regexp1', 'first test string', 'first test string', 'Character string included', ',', 1],
			['FALSE', 'test_regexp1_2', 'first test string', 'first test string2', 'Character string included', ',', 1],
			['TRUE', 'test_regexp2', 'second test string', 'test string', 'Any character string included', '.', 0],
			['FALSE', 'test_regexp2_2', 'second test string', 'second string', 'Any character string included', '.', 0],
			['TRUE', 'test_regexp3', 'test', 'abcd test', 'Character string not included', '.', 0],
			['FALSE', 'test_regexp3_2', 'test', 'test', 'Character string not included', '.', 0],
			['TRUE', 'test_regexp4', 'abcd', 'abcd', 'Result is TRUE', '.', 0],
			['FALSE', 'test_regexp4_2', 'abcd', 'qwerty', 'Result is TRUE', '.', 0],
			['TRUE', 'test_regexp5', 'abcd', 'asdf', 'Result is FALSE', '.', 0],
			['FALSE', 'test_regexp5_2', 'abcd', 'abcd', 'Result is FALSE', '.', 0]
		];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationGeneralRegexp_Create($result, $name, $test_string, $expression, $expression_type, $exp_delimiter, $case_sensitive) {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickButtonText('New regular expression');

		$this->zbxTestInputType('name', $name);
		$this->zbxTestInputType('expressions_0_expression', $expression);
		$this->zbxTestDropdownSelect('expressions_0_expression_type', $expression_type);
		if ($case_sensitive == 1) {
			$this->zbxTestCheckboxSelect('expressions_0_case_sensitive');
		}
		else {
			$this->zbxTestCheckboxSelect('expressions_0_case_sensitive', false);
		}

		$this->zbxTestTabSwitchById('tab_test', 'Test');
		$this->query('xpath://textarea[@id="test_string"][@disabled]')->waitUntilNotPresent();
		$this->zbxTestInputTypeWait('test_string', $test_string);
		$this->zbxTestClick('add');
		$this->assertMessage(TEST_GOOD, 'Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($name).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Regular expression with such name has not been added');
	}

	public function testFormAdministrationGeneralRegexp_AddExisting() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickButtonText('New regular expression');

		$this->zbxTestInputType('name', $this->regexp);
		$this->zbxTestInputType('expressions_0_expression', 'first test string');
		$this->zbxTestCheckboxSelect('expressions_0_case_sensitive');
		$this->zbxTestClickWait('add');

		$this->assertMessage(TEST_BAD, 'Cannot add regular expression', 'Regular expression "'.$this->regexp.'" already exists.');
	}

	public function testFormAdministrationGeneralRegexp_AddIncorrect() {
		// creating regexp without expression
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');

		$this->zbxTestClickButtonText('New regular expression');
		$this->zbxTestInputType('name', '1_regexp3');
		$this->zbxTestClickWait('add');

		$this->assertMessage(TEST_BAD, 'Cannot add regular expression', 'Invalid parameter "/1/expressions/1/expression": cannot be empty.');
	}

	public function testFormAdministrationGeneralRegexp_TestTrue() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);

		$this->zbxTestTabSwitchById('tab_test', 'Test');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//table[@id='testResultTable']//span[@class='green']"));
		$this->zbxTestTextPresent('TRUE');
	}

	public function testFormAdministrationGeneralRegexp_TestFalse() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);
		$this->zbxTestTabSwitchById('tab_test', 'Test');

		$this->query('xpath://textarea[@id="test_string"][@disabled]')->waitUntilNotPresent();
		$this->zbxTestInputType('test_string', 'abcdef');
		$this->zbxTestClick('testExpression');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//table[@id='testResultTable']//span[@class='red']"));
		$this->zbxTestTextPresent('FALSE');
	}

	public function testFormAdministrationGeneralRegexp_Clone() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);
		$this->zbxTestClickWait('clone');
		$this->zbxTestInputType('name', $this->regexp.'_clone');
		$this->zbxTestClickWait('add');
		$this->assertMessage(TEST_GOOD, 'Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->cloned_regexp).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Cloned regular expression does not exist in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Update() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);
		$this->zbxTestInputTypeOverwrite('name', $this->regexp.'2');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Regular expression updated');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->regexp.'2').' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Regexp name has not been changed in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Delete() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkTextWait($this->regexp2);

		$this->zbxTestClickWait('delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Regular expression deleted');
		$this->zbxTestTextPresent(['Regular expressions', 'Name', 'Expressions']);

		$sql = 'SELECT * FROM regexps r WHERE r.name='.zbx_dbstr($this->regexp2);
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.regexpid=e.regexpid and r.name='.zbx_dbstr($this->regexp2);

		// this check will fail as at this moment expressions are not deleted when deleting related regexp
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');
	}

	public function testFormAdministrationGeneralRegexp_DeleteAll() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestCheckboxSelect('all-regexes');
		$this->zbxTestClickButton('regex.delete');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestTextPresent('Regular expressions deleted');

		$sql = 'SELECT * FROM regexps';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM expressions';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Regexp expressions has not been deleted from the DB');
	}
}

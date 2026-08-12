<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
		$this->zbxTestTextPresent(['Regular expressions', 'Name', 'Expressions', 'Description']);
		$this->zbxTestClickButtonText('Create regular expression');

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$this->assertEquals('New regular expression', $dialog->getTitle());
		$this->assertEquals(['Name', 'Expressions', 'Description', 'Test expression'],
				$form->getLabels(CElementFilter::VISIBLE)->asText()
		);

		$this->zbxTestAssertAttribute("//z-textarea-flexible[@id='name']", "maxlength", 128);
		$this->zbxTestAssertAttribute("//z-textarea-flexible[@id='expressions_0_expression']", "maxlength", 2048);

		$this->zbxTestDropdownHasOptions('expressions_0_expression_type', [
			'Contains string',
			'Contains any substring from list',
			'Does not contain string',
			'Matches regular expression',
			'Does not match regular expression'
			]);

		$this->zbxTestAssertAttribute("//textarea[@id='description']", "maxlength", 65535);
		$this->zbxTestAssertAttribute("//textarea[@id='test-string']", "maxlength", 65535);
	}

	public static function dataCreate() {

		// result, r.name, r.test_string, e.expression, e.expression_type, e.exp_delimiter, e.case_sensitive
		// type: 0-Contains string, 1-Contains any substring from list, 2-Does not contain string,
		// 3-Matches regular expression, 4-Does not match regular expression
		return [
			['test_regexp1', 'first test string', 'first test string', 'Contains string', 1],
			['test_regexp1_2', 'first test string', 'first test string2', 'Contains string', 1],
			['test_regexp2', 'second test string', 'test string', 'Contains any substring from list', 0],
			['test_regexp2_2', 'second test string', 'second string', 'Contains any substring from list', 0],
			['test_regexp3', 'test', 'abcd test', 'Does not contain string', 0],
			['test_regexp3_2', 'test', 'test', 'Does not contain string', 0],
			['test_regexp4', 'abcd', 'abcd', 'Matches regular expression', 0],
			['test_regexp4_2', 'abcd', 'qwerty', 'Matches regular expression', 0],
			['test_regexp5', 'abcd', 'asdf', 'Does not match regular expression', 0],
			['test_regexp5_2', 'abcd', 'abcd', 'Does not match regular expression', 0]
		];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationGeneralRegexp_Create($name, $test_string, $expression, $expression_type, $case_sensitive) {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickButtonText('Create regular expression');

		$form = $this->query('id:regexp-form')->waitUntilVisible()->asForm()->one();
		$form->fill([
			'Name' => $name,
			'id:expressions_0_expression' => $expression,
			'id:expressions_0_expression_type' => $expression_type
		]);

		if ($case_sensitive == 1) {
			$this->zbxTestCheckboxSelect('expressions_0_case_sensitive');
		}
		else {
			$this->zbxTestCheckboxSelect('expressions_0_case_sensitive', false);
		}

		$this->zbxTestInputTypeWait('test-string', $test_string);
		$this->zbxTestClickXpathWait('//button[contains(@class,"js-submit")]');
		$this->assertMessage(TEST_GOOD, 'Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($name).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Regular expression with such name has not been added');
	}

	public function testFormAdministrationGeneralRegexp_AddExisting() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickButtonText('Create regular expression');

		$form = $this->query('id:regexp-form')->waitUntilVisible()->asForm()->one();
		$fields = [
			'Name' => $this->regexp,
			'id:expressions_0_expression' => 'first test string',
			'id:expressions_0_case_sensitive' => true
		];
		$form->fill($fields)->submit();

		$this->assertInlineError($form, ['id:name' => 'This object already exists.']);
	}

	public function testFormAdministrationGeneralRegexp_AddIncorrect() {
		// creating regexp without expression
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');

		$this->zbxTestClickButtonText('Create regular expression');
		$form = $this->query('id:regexp-form')->waitUntilVisible()->asForm()->one();
		$form->fill(['Name' => '1_regexp3'])->submit();

		$this->assertInlineError($form, ['id:expressions_0_expression' => 'Expression: This field cannot be empty.']);
	}

	public function testFormAdministrationGeneralRegexp_TestTrue() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Test')->one()->click();
		$this->assertEquals('TRUE', $dialog->query('xpath://table[@id="regular-expressions-table"]'.
				'//td[@class="js-expression-result nowrap green"]')->waitUntilVisible()->one()->getText());
		$this->assertEquals('TRUE', $dialog->asForm()->getField('Combined result')->getText());
	}

	public function testFormAdministrationGeneralRegexp_TestFalse() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('id:regexp-form')->waitUntilVisible()->one();
		$this->zbxTestInputType('test-string', 'abcdef');
		$dialog->query('button:Test')->one()->click();
		$this->assertEquals('FALSE', $dialog->query('xpath://table[@id="regular-expressions-table"]'.
				'//td[@class="js-expression-result nowrap red"]')->waitUntilVisible()->one()->getText());
		$this->assertEquals('FALSE', $dialog->asForm()->getField('Combined result')->getText());
	}

	public function testFormAdministrationGeneralRegexp_Clone() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);
		$this->query('button:Clone')->waitUntilClickable()->one()->click()->waitUntilNotVisible();
		$this->page->waitUntilReady();
		$this->query('id:name')->one()->fill($this->regexp.'_clone');
		$this->zbxTestClickXpathWait('//button[contains(@class,"js-submit")]');
		$this->assertMessage(TEST_GOOD, 'Regular expression added');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->cloned_regexp).' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Cloned regular expression does not exist in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Update() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkText($this->regexp);

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->asForm()->fill(['Name' => $this->regexp.'2']);
		$this->query('button:Update')->waitUntilClickable()->one()->click();
		$this->assertMessage(TEST_GOOD, 'Regular expression updated');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.name='.zbx_dbstr($this->regexp.'2').' AND r.regexpid=e.regexpid';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Regexp name has not been changed in the DB');
	}

	public function testFormAdministrationGeneralRegexp_Delete() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestClickLinkTextWait($this->regexp2);

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Regular expression deleted');
		$this->zbxTestTextPresent(['Regular expressions', 'Name', 'Expressions']);

		$sql = 'SELECT * FROM regexps r WHERE r.name='.zbx_dbstr($this->regexp2);
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM regexps r,expressions e WHERE r.regexpid=e.regexpid and r.name='.zbx_dbstr($this->regexp2);

		// this check will fail as at this moment expressions are not deleted when deleting related regexp
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Regexp expressions has not been deleted from the DB');
	}

	public function testFormAdministrationGeneralRegexp_DeleteAll() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckHeader('Regular expressions');
		$this->zbxTestCheckboxSelect('all-regexes');
		$this->zbxTestClickXpathWait('//button[contains(@id,"js-massdelete")]');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckHeader('Regular expressions');
		$this->assertMessage(TEST_GOOD, 'Regular expressions deleted');

		$sql = 'SELECT * FROM regexps';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Regexp has not been deleted from the DB');

		$sql = 'SELECT * FROM expressions';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Regexp expressions has not been deleted from the DB');
	}
}

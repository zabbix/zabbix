<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class testPageAdministrationGeneralRegexp extends CWebTest {

	private $sqlHashRegexps = '';
	private $oldHashRegexps = '';
	private $sqlHashExpressions = '';
	private $oldHashExpressions = '';

	private function calculateHash($conditions = null) {
		$this->sqlHashRegexps =
			'SELECT * FROM regexps'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY regexpid';
		$this->oldHashRegexps = DBhash($this->sqlHashRegexps);

		$this->sqlHashExpressions =
			'SELECT * FROM expressions'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY expressionid';
		$this->oldHashExpressions = DBhash($this->sqlHashExpressions);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashRegexps, DBhash($this->sqlHashRegexps));
		$this->assertEquals($this->oldHashExpressions, DBhash($this->sqlHashExpressions));
	}

	public static function allRegexps() {
		return DBdata('SELECT regexpid FROM regexps');
	}

	public function testPageAdministrationGeneralRegexp_CheckLayout() {
		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->zbxTestTextPresent('Regular expressions');
		$this->zbxTestDropdownHasOptions('configDropDown', [
			'GUI', 'Housekeeping', 'Images', 'Icon mapping', 'Regular expressions', 'Macros', 'Value mapping',
			'Working time', 'Trigger severities', 'Trigger displaying options', 'Other'
		]);
		$this->assertElementPresent('form');

		$this->zbxTestTextPresent(['Name', 'Expressions']);

		$dbResult = DBselect('select name from regexps');

		while ($dbRow = DBfetch($dbResult)) {
			$this->zbxTestTextPresent($dbRow['name']);
		}

		$this->zbxTestDropdownHasOptions('action', ['Delete selected']);
		$this->assertElementValue('goButton', 'Go (0)');

		$this->assertElementPresent("//select[@id='action' and @disabled]");
		$this->assertElementPresent("//input[@id='goButton' and @disabled]");
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteAllCancel() {
		$this->calculateHash();

		$this->chooseCancelOnNextConfirmation();

		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestCheckboxSelect('all_regexps');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClick('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextNotPresent(['Regular expression deleted', 'Regular expressions deleted']);

		$this->verifyHash();
	}

	public function testPageAdministrationGeneralRegexp_backup_1() {
		DBsave_tables('regexps');
	}

	/**
	 * @dataProvider allRegexps
	 */
	public function testPageAdministrationGeneralRegexp_MassDelete($regexp) {
		$this->calculateHash('regexpid<>'.$regexp['regexpid']);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestCheckboxSelect('regexpids['.$regexp['regexpid'].']');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('Regular expression deleted');

		$this->assertEquals(0, DBcount('SELECT NULL FROM regexps WHERE regexpid='.$regexp['regexpid']));

		$this->verifyHash();
	}

	public function testPageAdministrationGeneralRegexp_restore_1() {
		DBrestore_tables('regexps');
	}

	public function testPageAdministrationGeneralRegexp_backup_2() {
		DBsave_tables('regexps');
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteAll() {
		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('adm.regexps.php');
		$this->zbxTestCheckboxSelect('all_regexps');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('Regular expressions deleted');

		$this->assertEquals(0, DBcount('SELECT NULL FROM regexps'));
	}

	public function testPageAdministrationGeneralRegexp_restore_2() {
		DBrestore_tables('regexps');
	}

}

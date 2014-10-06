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

class testPageAdministrationGeneralRegexp extends CWebTest {
	private $sqlHashRegexps = '';
	private $oldHashRegexps = '';

	private $sqlHashExpressions = '';
	private $oldHashExpressions = '';

	private $oldRegexpId = 20;

	private function openRegularExpressions() {
		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Regular expressions');
		$this->assertElementPresent('configDropDown');

		$this->checkTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('CONFIGURATION OF REGULAR EXPRESSIONS');
		$this->zbxTestTextPresent('Regular expressions');
		$this->zbxTestTextPresent(array('Name', 'Expressions'));
	}

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
		$this->assertEquals($this->oldHashRegexps, DBhash($this->sqlHashRegexps),
				'Chuck Norris: Data in the DB table "regexps" has been changed.');

		$this->assertEquals($this->oldHashExpressions, DBhash($this->sqlHashExpressions),
				'Chuck Norris: Data in the DB table "expressions" has been changed.');
	}

	public function testPageAdministrationGeneralRegexp_backup() {
		DBsave_tables('regexps');
	}

	public function testPageAdministrationGeneralRegexp_CheckLayout() {
		$this->openRegularExpressions();

		$this->assertElementPresent('form');
		$this->assertElementPresent('all_regexps');

		$result = DBselect('select regexpid,name from regexps');
		while ($row = DBfetch($result)) {
			$this->assertElementPresent('regexpids['.$row['regexpid'].']');
			$this->zbxTestTextPresent($row['name']);
		}

		$this->assertElementPresent('go');
		$goElements = $this->zbxGetDropDownElements('go');
		$this->assertEquals(count($goElements), 1);
		$this->assertEquals($goElements[0]['content'], 'Delete selected');

		$this->assertElementPresent('goButton');
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteEmpty() {
		$this->calculateHash();

		$this->openRegularExpressions();

		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClick('goButton');
		$this->waitForAlertPresent();
		$this->assertAlert('No elements selected!');

		$this->verifyHash();
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteCancel() {
		$this->chooseCancelOnNextConfirmation();

		$this->calculateHash();

		$this->openRegularExpressions();

		$this->zbxTestCheckboxSelect('all_regexps');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();

		$this->verifyHash();
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteOne() {
		$this->chooseOkOnNextConfirmation();

		$this->calculateHash('regexpid<>'.$this->oldRegexpId);

		$this->openRegularExpressions();

		$this->zbxTestCheckboxSelect('regexpids['.$this->oldRegexpId.']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Regular expression deleted');

		$count = DBcount('SELECT regexpid FROM regexps WHERE regexpid='.$this->oldRegexpId);
		$this->assertEquals(0, $count, 'Chuck Norris: Record(s) has not been deleted from the DB.');

		$count = DBcount('SELECT expressionid FROM expressions WHERE regexpid='.$this->oldRegexpId);
		$this->assertEquals(0, $count, 'Chuck Norris: Record(s) has not been deleted from the DB.');

		$this->verifyHash();
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteAll() {
		$this->chooseOkOnNextConfirmation();

		$this->openRegularExpressions();

		$this->zbxTestCheckboxSelect('all_regexps');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClick('goButton');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Regular expressions deleted');

		$count = DBcount('SELECT regexpid FROM regexps');
		$this->assertEquals(0, $count, 'Chuck Norris: Record(s) has not been deleted from the DB');

		$count = DBcount('SELECT expressionid FROM expressions');
		$this->assertEquals(0, $count, 'Chuck Norris: Record(s) has not been deleted from the DB');
	}

	public function testPageAdministrationGeneralRegexp_restore() {
		DBrestore_tables('regexps');
	}

}

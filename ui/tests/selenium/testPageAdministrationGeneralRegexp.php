<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageAdministrationGeneralRegexp extends CLegacyWebTest {

	private $sqlHashRegexps = '';
	private $oldHashRegexps = '';
	private $sqlHashExpressions = '';
	private $oldHashExpressions = '';

	private function calculateHash($conditions = null) {
		$this->sqlHashRegexps =
			'SELECT * FROM regexps'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY regexpid';
		$this->oldHashRegexps = CDBHelper::getHash($this->sqlHashRegexps);

		$this->sqlHashExpressions =
			'SELECT * FROM expressions'.
			($conditions ? ' WHERE '.$conditions : '').
			' ORDER BY expressionid';
		$this->oldHashExpressions = CDBHelper::getHash($this->sqlHashExpressions);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashRegexps, CDBHelper::getHash($this->sqlHashRegexps));
		$this->assertEquals($this->oldHashExpressions, CDBHelper::getHash($this->sqlHashExpressions));
	}

	public static function allRegexps() {
		return CDBHelper::getDataProvider('SELECT regexpid FROM regexps');
	}

	public function testPageAdministrationGeneralRegexp_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestCheckHeader('Regular expressions');
		$popup_menu = $this->query('id:page-title-general')->asPopupButton()->one()->getMenu();
		$this->assertEquals([
			'GUI', 'Autoregistration', 'Housekeeping', 'Audit log', 'Images', 'Icon mapping', 'Regular expressions', 'Macros',
			'Trigger displaying options', 'Geographical maps', 'Modules', 'API tokens', 'Other'
		], $popup_menu->getItems()->asText());

		$this->zbxTestAssertElementPresentXpath('//button[text()="New regular expression"]');

		$this->zbxTestTextPresent(['Name', 'Expressions']);

		$dbResult = DBselect('select name from regexps');

		while ($dbRow = DBfetch($dbResult)) {
			$this->zbxTestTextPresent($dbRow['name']);
		}

		$this->zbxTestAssertElementPresentXpath('//button[@value="regex.delete" and @disabled]');
	}

	public function testPageAdministrationGeneralRegexp_MassDeleteAllCancel() {
		$this->calculateHash();

		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckboxSelect('all-regexes');
		$this->zbxTestClickButton('regex.delete');
		$this->zbxTestDismissAlert();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextNotPresent(['Regular expression deleted', 'Regular expressions deleted']);

		$this->verifyHash();
	}

	/**
	 * @dataProvider allRegexps
	 * @backupOnce regexps
	 */
	public function testPageAdministrationGeneralRegexp_MassDelete($regexp) {
		$this->calculateHash('regexpid<>'.$regexp['regexpid']);

		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckboxSelect('regexids_'.$regexp['regexpid']);
		$this->zbxTestClickButton('regex.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('Regular expression deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM regexps WHERE regexpid='.$regexp['regexpid']));

		$this->verifyHash();
	}

	/**
	 * @backupOnce regexps
	 */
	public function testPageAdministrationGeneralRegexp_MassDeleteAll() {
		$this->zbxTestLogin('zabbix.php?action=regex.list');
		$this->zbxTestCheckboxSelect('all-regexes');
		$this->zbxTestClickButton('regex.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of regular expressions');
		$this->zbxTestTextPresent('Regular expressions deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM regexps'));
	}
}

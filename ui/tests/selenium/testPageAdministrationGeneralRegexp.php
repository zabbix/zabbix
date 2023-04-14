<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

class testPageAdministrationGeneralRegexp extends CWebTest {

	use TableTrait;
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

	/**
	 * Test the layout for the Regular expressions page.
	 */
	public function testPageAdministrationGeneralRegexp_Layout() {
		$this->page->login()->open('zabbix.php?action=regex.list');
		$this->page->assertTitle('Configuration of regular expressions');
		$this->page->assertHeader('Regular expressions');

		// Validate the dropdown menu under header.
		$popup_menu = $this->query('id:page-title-general')->asPopupButton()->one()->getMenu();
		$this->assertEquals([
			'GUI', 'Autoregistration', 'Housekeeping', 'Images', 'Icon mapping', 'Regular expressions', 'Macros',
			'Value mapping', 'Working time', 'Trigger severities', 'Trigger displaying options', 'Modules', 'Other'
		], $popup_menu->getItems()->asText());

		// Check if the New regular expression button is clickable.
		$this->assertTrue($this->query('button:New regular expression')->one()->isClickable());

		// Check the data table.
		$this->assertEquals(['', 'Name', 'Expressions'],
			$this->query('class:list-table')->asTable()->one()->getHeadersText());
		$name_list = [];
		foreach (CDBHelper::getColumn('SELECT name FROM regexps', 'name') as $name){
			$name_list[] = ["Name" => $name];
		}
		$this->assertTableHasData($name_list);

		// Check the Delete button.
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());
	}

	/**
	 * Test mass delete and cancel.
	 */
	public function testPageAdministrationGeneralRegexp_MassDeleteAllCancel() {

		$this->calculateHash();

		$this->page->login()->open('zabbix.php?action=regex.list');
		$this->query('name:all-regexes')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->dismissAlert();
		$this->page->assertTitle('Configuration of regular expressions');

		// Make sure nothing has been deleted.
		$this->assertFalse($this->query('xpath://*[text()="Regular expression deleted"]')->exists());
		$this->assertFalse($this->query('xpath://*[text()="Regular expressions deleted"]')->exists());
		$this->verifyHash();

	}
}

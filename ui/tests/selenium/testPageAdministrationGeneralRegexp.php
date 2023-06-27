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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
* @backup regexps
*/
class testPageAdministrationGeneralRegexp extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	/**
	 * Test the layout and general functionality.
	 */
	public function testPageAdministrationGeneralRegexp_Layout() {
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of regular expressions');
		$this->page->assertHeader('Regular expressions');

		// Validate the dropdown menu under header.
		$popup_menu = $this->query('id:page-title-general')->asPopupButton()->one()->getMenu();
		$this->assertEquals(['GUI', 'Autoregistration', 'Housekeeping', 'Images', 'Icon mapping', 'Regular expressions', 'Macros',
				'Value mapping', 'Working time', 'Trigger severities', 'Trigger displaying options', 'Modules', 'Other'],
				$popup_menu->getItems()->asText()
		);
		$popup_menu->close();

		// Check if the New regular expression button is clickable.
		$this->assertTrue($this->query('button:New regular expression')->one()->isClickable());

		// Check the data table.
		$this->assertEquals(['', 'Name', 'Expressions'], $this->query('class:list-table')->asTable()->one()->getHeadersText());

		$name_list = [];
		foreach (CDBHelper::getColumn('SELECT name FROM regexps', 'name') as $name){
			$name_list[] = ["Name" => $name];
		}

		$this->assertTableHasData($name_list);

		// Check regexp counter and Delete button status.
		$selected_counter = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_counter->getText());
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());

		$this->query('xpath://td/input[@type="checkbox"]')->one()->click();
		$this->assertEquals('1 selected', $selected_counter->getText());
		$this->assertTrue($this->query('button:Delete')->one()->isEnabled());
	}

	/**
	 * Test pressing mass delete button but then cancelling.
	 */
	public function testPageAdministrationGeneralRegexp_DeleteCancel() {
		$hash_regexps = CDBHelper::getHash('SELECT * FROM regexps ORDER BY regexpid');
		$hash_expressions = CDBHelper::getHash('SELECT * FROM expressions ORDER BY expressionid');

		// Cancel delete.
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();
		$this->query('name:all-regexes')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->dismissAlert();
		$this->page->assertTitle('Configuration of regular expressions');

		// Make sure nothing has been deleted.
		$this->assertEquals($hash_regexps, CDBHelper::getHash('SELECT * FROM regexps ORDER BY regexpid'));
		$this->assertEquals($hash_expressions, CDBHelper::getHash('SELECT * FROM expressions ORDER BY expressionid'));
	}

	public static function getDeleteData()
	{
		return [
			// #0 Delete one regex.
			[
				[
					'regex_name' => ['1_regexp_1'],
					'message_title' => 'Regular expression deleted'
				]
			],
			// #1 Delete several regexes.
			[
				[
					'regex_name' => ['1_regexp_2', '2_regexp_1', '2_regexp_2'],
					'message_title' => 'Regular expressions deleted'
				]
			],
			// #2 Delete ALL regexes.
			[
				[
					'regex_name' => [''],
					'message_title' => 'Regular expressions deleted'
				]
			]
		];
	}

	/**
	 * Test regexp delete functionality.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageAdministrationGeneralRegexp_Delete($data) {
		// Delete a regexp.
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();

		// The list of expected regexes to be shown after deletion.
		$expected_regexps = $this->getTableColumnData('Name');

		$regexids = [];
		foreach ($data['regex_name'] as $regex) {
			if ($regex === '') {
				$this->query('name:all-regexes')->asCheckbox()->one()->check();
			}
			else {
				$regexids[] = CDBHelper::getValue('SELECT regexpid FROM regexps WHERE name='.zbx_dbstr($regex));
				$this->query('class:list-table')->asTable()->one()->findRow('Name', $regex)->select();

				// Remove this regexp from the expected values.
				$expected_regexps = array_values(array_diff($expected_regexps, [$regex]));
			}
		}

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the result.
		$this->page->assertTitle('Configuration of regular expressions');
		$this->assertMessage(TEST_GOOD, $data['message_title']);

		if ($data['regex_name'] === ['']) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM regexps'));
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM expressions'));
			$this->assertTableData();
		}
		else {
			foreach ($regexids as $regexid) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM regexps WHERE regexpid='.zbx_dbstr($regexid)));
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM expressions WHERE regexpid='.zbx_dbstr($regexid)));
			}

			$this->assertTableDataColumn($expected_regexps);
		}
	}
}

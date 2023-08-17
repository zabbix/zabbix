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
 *
 * @onBefore prepareData
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

	public function prepareData() {
		CDataHelper::call('regexp.create', [
			[
				'name' => '0_case_1 🙂🙃 ZaBbiX зАБбИкс āēīõšŗ \n <br/>',
				'expressions' => [
					[
						'expression' => 'test 🙂🙃 ZaBbiX зАБбИкс āēīõšŗ \n <br/>',
						'expression_type' => EXPRESSION_TYPE_INCLUDED
					]
				]
			],
			[
				'name' => '0_case_2',
				'expressions' => [
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_ANY_INCLUDED,
						'case_sensitive' => 1
					],
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_NOT_INCLUDED,
						'case_sensitive' => 0
					]
				]
			],
			[
				'name' => '0_case_3',
				'expressions' => [
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_INCLUDED
					],
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_TRUE
					],
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_FALSE
					]
				]
			]
		]);
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
		$this->assertEquals(['GUI', 'Autoregistration', 'Images', 'Icon mapping', 'Regular expressions',
			'Trigger displaying options', 'Geographical maps', 'Modules', 'Connectors', 'Other'],
			$popup_menu->getItems()->asText()
		);
		$popup_menu->close();

		// Check if the New regular expression button is clickable.
		$this->assertTrue($this->query('button:New regular expression')->one()->isClickable());

		// Check the data table.
		$this->assertEquals(['', 'Name', 'Expressions'], $this->query('class:list-table')->asTable()->one()->getHeadersText());

		$expected_data = [
			[
				'Name' => '0_case_1 🙂🙃 ZaBbiX зАБбИкс āēīõšŗ \n <br/>',
				'Expressions' => '1 ⇒ test 🙂🙃 ZaBbiX зАБбИкс āēīõšŗ \n <br/> [Character string included]'
			],
			[
				'Name' => '0_case_2',
				'Expressions' => "1 ⇒ test [Any character string included]\n".
						'2 ⇒ test [Character string not included]'
			],
			[
				'Name' => '0_case_3',
				'Expressions' => "1 ⇒ test [Character string included]\n".
						"2 ⇒ test [Result is TRUE]\n".
						'3 ⇒ test [Result is FALSE]'
			]
		];

		$this->assertTableHasData($expected_data);

		// Check regexp counter and Delete button status.
		$selected_counter = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_counter->getText());
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());

		$this->query('id:all-regexes')->asCheckbox()->one()->set(true);
		$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM regexps').' selected', $selected_counter->getText());
		$this->assertTrue($this->query('button:Delete')->one()->isEnabled());
	}

	/**
	 * Test pressing mass delete button but then cancelling.
	 */
	public function testPageAdministrationGeneralRegexp_DeleteCancel() {
		$hash_sql = 'SELECT * FROM expressions e INNER JOIN regexps r ON r.regexpid = e.regexpid';
		$db_hash = CDBHelper::getHash($hash_sql);

		// Cancel delete.
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();
		$this->query('name:all-regexes')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->dismissAlert();
		$this->page->assertTitle('Configuration of regular expressions');

		// Make sure nothing has been deleted.
		$this->assertEquals($db_hash, CDBHelper::getHash($hash_sql));
	}

	public static function getDeleteData() {
		return [
			[
				[
					'regex_name' => ['1_regexp_1']
				]
			],
			[
				[
					'regex_name' => ['1_regexp_2', '2_regexp_1', '2_regexp_2']
				]
			]
		];
	}

	/**
	 * Test delete separately.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageAdministrationGeneralRegexp_Delete($data) {
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();

		// Variables for checks after deletion.
		$expected_regexps = $this->getTableColumnData('Name');
		$regex_ids = [];

		foreach ($data['regex_name'] as $regex) {
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $regex);
			$row->select();
			$regex_ids[] = $row->query('tag:input')->one()->getAttribute('value');

			// Remove this regexp from the expected values.
			$expected_regexps = array_values(array_diff($expected_regexps, [$regex]));
		}

		// Press Delete and confirm.
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Assert the results.
		$this->page->assertTitle('Configuration of regular expressions');

		$message = (count(CTestArrayHelper::get($data, 'regex_name', [])) === 1)
			? 'Regular expression deleted'
			: 'Regular expressions deleted';

		$this->assertMessage(TEST_GOOD, $message);

		$id_list = implode(', ', $regex_ids);
		$sql = 'SELECT NULL FROM expressions e CROSS JOIN regexps r'.
				' WHERE e.regexpid IN ('.$id_list.') OR r.regexpid IN ('.$id_list.')';
		$this->assertEquals(0, CDBHelper::getCount($sql));

		$this->assertTableDataColumn($expected_regexps);
	}

	/**
	 * Test delete all.
	 *
	 * @return void
	 */
	public function testPageAdministrationGeneralRegexp_DeleteAll() {
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();

		$this->query('name:all-regexes')->asCheckbox()->one()->check();

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Assert the results.
		$this->page->assertTitle('Configuration of regular expressions');
		$this->assertMessage(TEST_GOOD, 'Regular expressions deleted');

		$sql = 'SELECT NULL FROM expressions e CROSS JOIN regexps r';
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$this->assertTableData();
	}
}

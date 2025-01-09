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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup regexps
 *
 * @onBefore prepareData
 */
class testPageAdministrationGeneralRegexp extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
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
			],
			[
				'name' => '0_case_4',
				'expressions' => [
					[
						'expression' => 'test',
						'expression_type' => EXPRESSION_TYPE_INCLUDED
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
		$this->assertEquals(['GUI', 'Autoregistration', 'Timeouts', 'Images', 'Icon mapping', 'Regular expressions',
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
		$this->assertSelectedCount(0);
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());

		$this->query('id:all-regexes')->asCheckbox()->one()->set(true);
		$selected_text = $this->query('id:selected_count')->one()->getText();
		$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM regexps').' selected', $selected_text);
		$this->assertTrue($this->query('button:Delete')->one()->isEnabled());
	}

	/**
	 * Test pressing mass delete button but then cancelling.
	 */
	public function testPageAdministrationGeneralRegexp_CancelDelete() {
		$hash_sql = 'SELECT * FROM expressions e INNER JOIN regexps r ON r.regexpid = e.regexpid';
		$db_hash = CDBHelper::getHash($hash_sql);

		// Cancel delete.
		$this->page->login()->open('zabbix.php?action=regex.list')->waitUntilReady();
		$this->query('name:all-regexes')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->assertEquals('Delete selected regular expressions?', $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->assertTitle('Configuration of regular expressions');

		// Make sure nothing has been deleted.
		$this->assertEquals($db_hash, CDBHelper::getHash($hash_sql));
	}

	public static function getDeleteData() {
		return [
			[
				[
					'regex_name' => ['0_case_1 🙂🙃 ZaBbiX зАБбИкс āēīõšŗ \n <br/>']
				]
			],
			[
				[
					'regex_name' => ['0_case_2', '0_case_3', '0_case_4']
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
		$all_regexps = $this->getTableColumnData('Name');
		$ids = CDBHelper::getAll('SELECT regexpid FROM regexps WHERE name IN ('.CDBHelper::escape($data['regex_name']).')');
		$regex_ids = array_column($ids, 'regexpid');
		$expected_regexps = array_diff($all_regexps, $data['regex_name']);

		$this->selectTableRows($data['regex_name']);

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
	 */
	public function testPageAdministrationGeneralRegexp_DeleteAll(): void {
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

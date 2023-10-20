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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGUI extends CLegacyWebTest {

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function allValues() {
		return CDBHelper::getDataProvider(
			'SELECT default_theme,search_limit,max_in_table,server_check_interval'.
			' FROM config'.
			' ORDER BY configid'
		);
	}

	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralGUI_CheckLayout($allValues) {

		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestTextPresent([
			'Default theme',
			'Limit for search and filter results',
			'Max count of elements to show inside table cell',
			'Show warning if Zabbix server is down'
		]);

		$this->zbxTestDropdownHasOptions('default_theme', ['Blue', 'Dark']);

		$this->zbxTestAssertElementPresentId('search_limit');
		$this->zbxTestAssertAttribute('//input[@id="search_limit"]', 'maxlength', '6');
		$this->zbxTestAssertElementPresentId('max_in_table');
		$this->zbxTestAssertAttribute('//input[@id="max_in_table"]','maxlength', '5');

		$this->zbxTestAssertElementPresentId('server_check_interval');

		$this->zbxTestAssertElementPresentId('update');

		$this->assertEquals($allValues['default_theme'], $this->zbxTestGetValue('//z-select[@name="default_theme"]'));

		if ($allValues['server_check_interval']) {
			$this->assertTrue($this->zbxTestCheckboxSelected('server_check_interval'));
		}
		if ($allValues['server_check_interval']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('server_check_interval'));
		}

		$this->zbxTestAssertElementValue('search_limit', $allValues['search_limit']);
		$this->zbxTestAssertElementValue('max_in_table', $allValues['max_in_table']);
	}

	public function testFormAdministrationGeneralGUI_ChangeTheme() {

		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$sql_hash = 'SELECT '.CDBHelper::getTableFields('config', ['default_theme']).' FROM config ORDER BY configid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestDropdownSelect('default_theme', 'Dark');
		$this->assertEquals('dark-theme', $this->zbxTestGetValue('//z-select[@name="default_theme"]'));
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Default theme']);
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('dark-theme');
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: "Dark" theme can not be selected as default theme: it does not exist in the DB');

		$this->zbxTestDropdownSelect('default_theme', 'Blue');
		$this->assertEquals('blue-theme', $this->zbxTestGetValue('//z-select[@name="default_theme"]'));
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Default theme']);
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('blue-theme');
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: "Blue" theme can not be selected as default theme: it does not exist in the DB');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeSearchLimit() {
		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$sql_hash = 'SELECT '.CDBHelper::getTableFields('config', ['search_limit']).' FROM config ORDER BY configid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestInputType('search_limit', '1000');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1000';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Incorrect value in the DB field "search_limit"');

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '1');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '999999');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=999999';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		// Check to enter 0 value
		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '0');
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);
		$this->assertMessage(TEST_BAD, 'Cannot update configuration', 'Incorrect value "0" for "search_limit" field.');
		$this->zbxTestTextNotPresent('Configuration updated');

		// Check to enter -1 value
		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '-1');
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);
		$this->assertMessage(TEST_BAD, 'Cannot update configuration', 'Incorrect value "-1" for "search_limit" field.');
		$this->zbxTestTextNotPresent('Configuration updated');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeMaxInTable() {
		$sql_hash = 'SELECT '.CDBHelper::getTableFields('config', ['max_in_table']).' FROM config ORDER BY configid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$this->zbxTestInputTypeOverwrite('max_in_table', '1000');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Max count of elements to show inside table cell']);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM config WHERE max_in_table=1000'));

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputType('max_in_table', '1');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Max count of elements to show inside table cell']);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM config WHERE max_in_table=1'));

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('max_in_table', '99999');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Max count of elements to show inside table cell']);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM config WHERE max_in_table=99999'));

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('max_in_table', '-1');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_BAD, 'Cannot update configuration', 'Incorrect value "-1" for "max_in_table" field.');
		$this->zbxTestTextPresent(['GUI', 'Max count of elements to show inside table cell']);
		$this->zbxTestTextNotPresent('Configuration updated');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_EventCheckInterval() {
		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$sql_hash = 'SELECT '.CDBHelper::getTableFields('config', ['server_check_interval']).' FROM config ORDER BY configid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->zbxTestCheckboxSelect('server_check_interval');
		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestTextPresent('Show warning if Zabbix server is down');
		$this->assertTrue($this->zbxTestCheckboxSelected('server_check_interval'));

		$sql = 'SELECT server_check_interval FROM config WHERE server_check_interval=10';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Incorrect value in the DB field "server_check_interval"');

		$this->query('id:page-title-general')->asPopupButton()->one()->select('GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestCheckboxSelect('server_check_interval', false);

		$this->zbxTestClickWait('update');
		$this->assertMessage(TEST_GOOD, 'Configuration updated');
		$this->zbxTestTextPresent(['GUI', 'Show warning if Zabbix server is down']);
		$this->assertFalse($this->zbxTestCheckboxSelected('server_check_interval'));

		$sql = 'SELECT server_check_interval FROM config WHERE server_check_interval=0';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Incorrect value in the DB field "server_check_interval"');

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}
}

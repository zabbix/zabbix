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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

class testPageAdministrationScripts extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	private $sqlHashScripts = '';
	private $oldHashScripts = '';

	private function calculateHash($scriptid) {
		$this->sqlHashScripts = 'SELECT * FROM scripts WHERE scriptid='.$scriptid;
		$this->oldHashScripts = CDBHelper::getHash($this->sqlHashScripts);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashScripts, CDBHelper::getHash($this->sqlHashScripts));
	}

	public static function allScripts() {
		return CDBHelper::getDataProvider('SELECT scriptid,name FROM scripts');
	}

	public function testPageAdministrationScripts_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=script.list');
		$this->zbxTestCheckTitle('Configuration of scripts');

		$this->zbxTestCheckHeader('Scripts');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(
				['Name', 'Type', 'Execute on', 'Commands', 'User group', 'Host group', 'Host access']
		);

		$dbResult = DBselect('SELECT name,command FROM scripts');

		while ($dbRow = DBfetch($dbResult)) {
			$command= str_replace('>&', '&gt;&amp;', $dbRow['command']);
			$this->zbxTestTextPresent([$dbRow['name'], $command]);
		}
	}

	/**
	 * @dataProvider allScripts
	 */
	public function testPageAdministrationScripts_SimpleUpdate($script) {
		$this->calculateHash($script['scriptid']);

		$this->zbxTestLogin('zabbix.php?action=script.list');
		$this->zbxTestClickLinkText($script['name']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script updated');
		$this->zbxTestTextPresent($script['name']);

		$this->verifyHash();
	}

	/**
	 * @backup scripts
	 */
	public function testPageAdministrationScripts_MassDeleteAll() {
		$this->zbxTestLogin('zabbix.php?action=script.list');
		$this->zbxTestCheckboxSelect('all_scripts');
		$this->zbxTestClickButton('script.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->assertMessage(TEST_BAD, 'Cannot delete scripts');
	}

	/**
	 * @dataProvider allScripts
	 * @backupOnce scripts
	 */
	public function testPageAdministrationScripts_MassDelete($script) {
		$this->zbxTestLogin('zabbix.php?action=script.list');
		$this->zbxTestCheckboxSelect('scriptids_'.$script['scriptid']);
		$this->zbxTestClickButton('script.delete');
		$this->zbxTestAcceptAlert();
		if ($script['scriptid'] === '4') {
			$this->zbxTestCheckTitle('Configuration of scripts');
			$this->assertMessage(TEST_BAD, 'Cannot delete script');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid'])));
		}
		else {
			$this->zbxTestCheckTitle('Configuration of scripts');
			$this->assertMessage(TEST_GOOD, 'Script deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid'])));
		}
	}
}

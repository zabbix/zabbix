<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

/**
 * @backup config
 */
class testFormAdministrationGeneralOtherParams extends CLegacyWebTest {

	public static function allValues() {
		return CDBHelper::getDataProvider('SELECT snmptrap_logging FROM config ORDER BY configid');
	}

	public static function allGroups() {
		return CDBHelper::getDataProvider('SELECT name FROM hstgrp ORDER BY groupid');
	}

	public static function AlertUsrgrpid() {
		return CDBHelper::getDataProvider('SELECT * FROM usrgrp ORDER BY usrgrpid');
	}

	/**
	* @dataProvider AllValues
	*/
	public function testFormAdministrationGeneralOtherParams_CheckLayout($allValues) {

		$this->zbxTestLogin('zabbix.php?action=miscconfig.edit');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');

		// checkbox "snmptrap_logging"
		if ($allValues['snmptrap_logging']) {
			$this->assertTrue($this->zbxTestCheckboxSelected('snmptrap_logging'));
		}
		if ($allValues['snmptrap_logging']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('snmptrap_logging'));

			$this->zbxTestAssertElementPresentId('snmptrap_logging');
			$this->zbxTestAssertElementPresentId('default_inventory_mode');

			// ckecking presence of multiselect elements
			$this->zbxTestAssertElementPresentId('discovery_groupid');
			$this->zbxTestAssertElementPresentId('alert_usrgrpid');
		}

		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		$form->checkValue(
			[
				'Group for discovered hosts' => 'Discovered hosts',
				'User group for database down message' => 'Selenium user group in configuration'
			]
		);
	}

	public function testFormAdministrationGeneralOtherParams_OtherParams() {
		$this->zbxTestLogin('zabbix.php?action=miscconfig.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Other');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');

		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		$form->fill(
			[
				'Group for discovered hosts' => 'Linux servers',
				'User group for database down message' => 'Zabbix administrators'
			]
		);
		$this->zbxTestCheckboxSelect('snmptrap_logging');  // 1 - yes, 0 - no
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Configuration updated');

		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=1';
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$this->query('id:page-title-general')->asPopupButton()->one()->select('Other');
		$this->zbxTestCheckTitle('Other configuration parameters');

		// trying to enter max possible value
		$form->invalidate();
		$form->checkValue(
			[
				'Group for discovered hosts' => 'Linux servers',
				'User group for database down message' => 'Zabbix administrators'
			]
		);
		$this->zbxTestCheckboxSelect('snmptrap_logging', false);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Configuration updated');

		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=0';
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}
}

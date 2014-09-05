<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class testZBX6339 extends CWebTest {

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testZBX6339_Setup() {
		DBsave_tables('screens');
	}

	// Returns all screens
	public static function allScreens() {
		return DBdata(
			'SELECT s.screenid,s.name,h.name as host_name'.
			' FROM hosts h'.
				' LEFT JOIN screens s'.
					' ON h.hostid=s.templateid'.
			' WHERE s.templateid IS NOT NULL'.
				' AND h.status='.HOST_STATUS_TEMPLATE.
			' ORDER BY s.screenid'
		);
	}

	/**
	* @dataProvider allScreens
	*/
	public function testZBX6339_MassDelete($screen) {

		$screenid = $screen['screenid'];
		$name = $screen['name'];

		$host = $screen['host_name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$host);

		$this->zbxTestClickWait("//div[@class='w']//a[text()='Screens']");
		$this->zbxTestCheckTitle('Configuration of screens');

		$this->zbxTestCheckboxSelect('screens['.$screenid.']');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent(array('Screen deleted', 'CONFIGURATION OF SCREENS', $host));
	}

	/**
	 * Restore the original tables.
	 */
	public function testZBX6339_Teardown() {
		DBrestore_tables('screens');
	}
}

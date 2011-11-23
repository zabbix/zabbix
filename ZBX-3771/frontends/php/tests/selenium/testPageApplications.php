<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageApplications extends CWebTest {

	public static function getAll() {
		return DBdata('SELECT a.* FROM applications a');
	}

	/**
	 * @dataProvider getAll
	 */
	public function testPageApplications_SimpleTest($host) {
		$this->login('applications.php');

		$this->dropdown_select_wait('groupid', 'all');
		$this->assertTitle('Applications');
		$this->ok('Applications');

		// go to the list
		$this->href_click('applications.php?groupid='.$host['groupid'].'&hostid='.$host['hostid'].'&sid=');
		$this->wait();

		// we are in the list
		$this->assertTitle('Applications');
		$this->ok('CONFIGURATION OF APPLICATIONS');

		// combobox
		$this->dropdown_select('go', 'Activate selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Delete selected');
	}
}
?>

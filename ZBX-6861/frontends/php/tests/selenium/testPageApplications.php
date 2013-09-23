<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageApplications extends CWebTest {
	// Returns all hosts
	public static function allHosts() {
		return DBdata('select * from hosts where status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')');
	}

	/**
	* @dataProvider allHosts
	*/

	public function testPageApplications_CheckLayout($host) {
		$hostid = $host['hostid'];

		$this->zbxTestLogin('applications.php?groupid=0&hostid='.$hostid);

		// We are in the list of applications
		$this->checkTitle('Configuration of applications');
		$this->zbxTestTextPresent('CONFIGURATION OF APPLICATIONS');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent('Host list');
		// Header
		$this->zbxTestTextPresent(array('Applications', 'Show'));

		$this->zbxTestDropdownHasOptions('go', array('Enable selected', 'Disable selected', 'Delete selected'));
	}
}

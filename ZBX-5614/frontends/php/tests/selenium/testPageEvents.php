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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageEvents extends CWebTest {

	public function testPageEvents_Triggers_CheckLayout() {
		$this->login('events.php');

		$this->dropdown_select_wait('source', 'Trigger');
		$this->dropdown_select_wait('groupid', 'all');
		$this->dropdown_select_wait('hostid', 'all');

		$this->checkTitle('Latest events');
		$this->ok('HISTORY OF EVENTS');
		$this->ok('Group');
		$this->ok('Host');
		$this->ok('Source');
		$this->ok('Filter');
		$this->ok('Displaying');
		// table header
		if (0 == DBcount('select * from events where value_changed=1 and source='.EVENT_SOURCE_TRIGGERS)) {
			$this->ok('No events defined.');
		}
		else {
			$this->ok(array('Time', 'Description', 'Status', 'Severity', 'Duration', 'Ack', 'Actions'));
		}
	}

	public function testPageEvents_Discovery_CheckLayout() {
		$this->login('events.php');

		$this->dropdown_select_wait('source', 'Discovery');

		$this->checkTitle('Latest events');
		$this->ok('HISTORY OF EVENTS');
		$this->ok('Source');
		$this->ok('Filter');
		$this->ok('Displaying');
		// table header
		if (0 == DBcount('select * from events where source='.EVENT_SOURCE_DISCOVERY)) {
			$this->ok('No events defined.');
		}
		else {
			$this->ok(array('Time','IP','DNS','Description','Status'));
		}
	}

	public function testPageEvents_Triggers_Sorting() {
// TODO
		$this->markTestIncomplete();
	}

// Check that no real host or template names displayed
	public function testPageEvents_Triggers_NoHostNames() {
		$this->login('events.php');
		$this->dropdown_select_wait('source', 'Trigger');
		$this->checkTitle('Latest events');

		$this->checkNoRealHostnames();
	}

// Check that no real host or template names displayed
	public function testPageEvents_Discovery_NoHostNames() {
		$this->login('events.php');
		$this->dropdown_select_wait('source', 'Discovery');
		$this->checkTitle('Latest events');

		$this->checkNoRealHostnames();
	}
}
?>

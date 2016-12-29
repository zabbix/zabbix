<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testPageProblems extends CWebTest {

	public function testPageProblems_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_0'));
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Age less than', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only']);
		$this->zbxTestTextPresent(['Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);
		$this->zbxTestTextPresent('Displaying');
	}

	public function testPageEvents_NoHostNames() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageProblems_History_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');

		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestClickXpathWait("//label[@for='filter_show_2']");
		$this->zbxTestClickButtonText('Apply');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_2'));
		$this->zbxTestAssertNotVisibleId('filter_age_state');
		$this->zbxTestAssertElementPresentId('scrollbar_cntr');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only']);
		$this->zbxTestTextNotVisibleOnPage('Age less than');
		$this->zbxTestTextPresent(['Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);
		
		$this->zbxTestClickButtonText('Reset');
	}

	public function testPageEvents_History_NoHostNames() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');
		
		$this->zbxTestClickXpathWait("//label[@for='filter_show_2']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertNotVisibleId('filter_age_state');
		$this->zbxTestAssertElementPresentId('scrollbar_cntr');

		$this->zbxTestCheckNoRealHostnames();
		$this->zbxTestClickButtonText('Reset');
	}
}

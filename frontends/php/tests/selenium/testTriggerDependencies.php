<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * @backup trigger_depends
 */
class testTriggerDependencies extends CLegacyWebTest {

	/**
	* @dataProvider testTriggerDependenciesFromHost_SimpleTestProvider
	*/
	public function testTriggerDependenciesFromHost_SimpleTest($hostId, $expected) {
		$this->zbxTestLogin('triggers.php?filter_set=1&filter_hostids[0]='.$hostId);
		$this->zbxTestCheckTitle('Configuration of triggers');

		$this->zbxTestClickLinkTextWait('{HOST.NAME} has just been restarted');
		$this->zbxTestClickWait('tab_dependenciesTab');

		$this->zbxTestClick('bnt1');
		$this->zbxTestLaunchOverlayDialog('Triggers');

		$this->zbxTestDropdownSelectWait('hostid', 'Template OS FreeBSD');
		$this->zbxTestClickLinkTextWait('/etc/passwd has been changed on Template OS FreeBSD');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('bnt1'));
		$this->zbxTestTextPresent('Template OS FreeBSD: /etc/passwd has been changed on {HOST.NAME}');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent($expected);
	}

	public function testTriggerDependenciesFromHost_SimpleTestProvider() {
		return [
			['10001', 'Not all templates are linked to'],
			['10081', 'Trigger updated']
		];
	}
}

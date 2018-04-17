<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class testPageReportsTriggerTop extends CWebTest {
	public function testPageReportsNotifications_CheckLayout(){
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestTextPresent('Host groups','Hosts','Severity','Filter',
		'From', 'Till');
//		Click button 'Reset'
		$this->zbxTestClickXpath('//form[@id=\'id\']/div[2]/button[2]');
		$this->assertTrue($this->zbxTestCheckboxSelected('severities_0','severities_1',
		'severities_2','severities_3','severities_4','severities_5'));
//		Check Host groups "Select" button
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[3]', 'Select');
//		Check Hosts "Select" button
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[2]', 'Select');
//		Check date button for 'From' field
		$this->zbxTestAssertElementPresentXpath('//form[@id=\'id\']/div/div/div[2]/ul/li/div[2]/button');
//		Check date button for 'Till' field
		$this->zbxTestAssertElementPresentXpath('//form[@id=\'id\']/div/div/div[2]/ul/li[2]/div[2]/button');
//		Assert elements text
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[7]', 'Yesterday');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[6]', 'Today');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[8]', 'Current week');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[9]', 'Current month');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[10]', 'Current year');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[11]', 'Last week');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[12]', 'Last month');
		$this->zbxTestAssertElementText('(//button[@type=\'button\'])[13]', 'Last year');
		$this->zbxTestAssertElementPresentId('filter-mode');
	}

	public function testPageReportsNotifications_CheckFilters() {
		$this->zbxTestLogin('toptriggers.php');
//		Click button 'Reset'
		$this->zbxTestClickXpath('//form[@id=\'id\']/div[2]/button[2]');
//		Select Host group
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[2]');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->assertTrue(!$this->zbxTestCheckboxSelected('spanid5','spanid7','spanid2','spanid1',
		'spanid12','spanid13','spanid8','spanid9','spanid10','spanid11','spanid14','spanid6',
		'spanid4','spanid50003','spanid50001','spanid50002','spanid50000'));
		$this->zbxTestTextPresent('Discovered hosts','Hypervisors','Linux servers','Templates',
		'Templates/Applications', 'Templates/Databases','Templates/Modules','Templates/Network Devices',
		'Templates/Operating Systems', 'Templates/Servers Hardware', 'Templates/Virtualization',
		'Virtual machines', 'Zabbix servers', 'ZBX6648 All Triggers', 'ZBX6648 Disabled Triggers',
		'ZBX6648 Enabled Triggers', 'ZBX6648 Group No Hosts');
		$this->zbxTestClick('item_4');
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[27]');
		$this->zbxTestWaitWindowClose();
		$this->zbxTestAssertElementText('//div[@id=\'groupids_\']/div/ul/li/span/span', 'Zabbix servers');
//		Select host
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[3]');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		$this->assertTrue(!$this->zbxTestCheckboxSelected('spanid50007','spanid50008','spanid20006','spanid50001',
		'spanid40001','spanid15001','spanid15003','spanid10053','spanid10084'));
		$this->zbxTestTextPresent('Host-layout-test-001','Host-map-test-zbx6840', 'Host for trigger description macros',
		'Host ZBX6663', 'Simple form test host', 'Template inheritance test host',' testPageHistory_CheckLayout',
		'Visible host for template linkage', 'ЗАББИКС Сервер');
		$this->zbxTestClick('item_10084');
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[27]');
		$this->zbxTestWaitWindowClose();
		$this->zbxTestAssertElementText('//div[@id=\'hostids_\']/div/ul/li/span/span', 'ЗАББИКС Сервер');
//		Update date in 'From' field
		$this->zbxTestInputTypeOverwrite('filter_from_year', '2016');
		$this->zbxTestClickXpath('//button[@name=\'filter_set\']');
		$this->zbxTestTextPresent('Test trigger to check tag filter on problem page','Test trigger with tag');
	}
}

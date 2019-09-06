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
require_once dirname(__FILE__).'/traits/FilterTrait.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

class testPageHosts extends CLegacyWebTest {
	public $HostName = 'ЗАББИКС Сервер';
	public $HostGroup = 'Zabbix servers';
	public $HostIp = '127.0.0.1';
	public $HostPort = '10050';

	use FilterTrait;
	use TableTrait;

	public static function allHosts() {
		return CDBHelper::getDataProvider(
			'SELECT h.name,h.hostid,g.name AS group_name'.
			' FROM hosts h'.
				' LEFT JOIN hosts_groups hg'.
					' ON hg.hostid=h.hostid'.
				' LEFT JOIN hstgrp g'.
					' ON g.groupid=hg.groupid'.
			' WHERE h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
			" AND NOT h.flags = 2"
		);
	}

	public function testPageHosts_CheckLayout() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');
		$this->zbxTestDropdownSelectWait('groupid', $this->HostGroup);

		$this->zbxTestTextPresent($this->HostName);
		$this->zbxTestTextPresent('Simple form test host');
		$this->zbxTestTextNotPresent('ZBX6648 All Triggers Host');

		// Check that proxy field is disabled.
		$this->zbxTestAssertElementNotPresentId('filter_proxyids__ms');
		$this->zbxTestAssertElementPresentXpath('//div[@id="filter_proxyids_"]/..//button[@disabled]');

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Applications')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Items')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Triggers')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Graphs')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Discovery')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Web')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Interface')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Proxy')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Templates')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[contains(text(),'Status')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Availability')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Agent encryption')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Info')]");

		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");

		$this->zbxTestAssertElementPresentXpath("//button[text()='Export'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Mass update'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Enable'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Disable'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete'][@disabled]");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_SimpleUpdate($host) {
		$hostid = $host['hostid'];
		$name = $host['name'];

		$sqlHosts =
			'SELECT hostid,proxy_hostid,host,status,error,available,ipmi_authtype,ipmi_privilege,ipmi_username,'.
			'ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,'.
			'maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,'.
			'snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,'.
			'name,flags,templateid,description,tls_connect,tls_accept'.
			' FROM hosts'.
			' WHERE hostid='.$hostid;
		$oldHashHosts = CDBHelper::getHash($sqlHosts);
		$sqlItems = "select * from items where hostid=$hostid order by itemid";
		$oldHashItems = CDBHelper::getHash($sqlItems);
		$sqlApplications = "select * from applications where hostid=$hostid order by applicationid";
		$oldHashApplications = CDBHelper::getHash($sqlApplications);
		$sqlInterface = "select * from interface where hostid=$hostid order by interfaceid";
		$oldHashInterface = CDBHelper::getHash($sqlInterface);
		$sqlHostMacro = "select * from hostmacro where hostid=$hostid order by hostmacroid";
		$oldHashHostMacro = CDBHelper::getHash($sqlHostMacro);
		$sqlHostsGroups = "select * from hosts_groups where hostid=$hostid order by hostgroupid";
		$oldHashHostsGroups = CDBHelper::getHash($sqlHostsGroups);
		$sqlHostsTemplates = "select * from hosts_templates where hostid=$hostid order by hosttemplateid";
		$oldHashHostsTemplates = CDBHelper::getHash($sqlHostsTemplates);
		$sqlMaintenancesHosts = "select * from maintenances_hosts where hostid=$hostid order by maintenance_hostid";
		$oldHashMaintenancesHosts = CDBHelper::getHash($sqlMaintenancesHosts);
		$sqlHostInventory = "select * from host_inventory where hostid=$hostid";
		$oldHashHostInventory = CDBHelper::getHash($sqlHostInventory);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');

		$this->zbxTestTextPresent($name);
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$this->zbxTestTextPresent($name);

		$this->assertEquals($oldHashHosts, CDBHelper::getHash($sqlHosts));
		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
		$this->assertEquals($oldHashApplications, CDBHelper::getHash($sqlApplications));
		$this->assertEquals($oldHashInterface, CDBHelper::getHash($sqlInterface));
		$this->assertEquals($oldHashHostMacro, CDBHelper::getHash($sqlHostMacro));
		$this->assertEquals($oldHashHostsGroups, CDBHelper::getHash($sqlHostsGroups));
		$this->assertEquals($oldHashHostsTemplates, CDBHelper::getHash($sqlHostsTemplates));
		$this->assertEquals($oldHashMaintenancesHosts, CDBHelper::getHash($sqlMaintenancesHosts));
		$this->assertEquals($oldHashHostInventory, CDBHelper::getHash($sqlHostInventory));
	}


	public function testPageHosts_MassDisableAll() {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButton('host.massdisable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Hosts disabled');

		$sql = "select * from hosts where status=".HOST_STATUS_MONITORED.
			" and NOT flags = 2";
		$this->assertEquals(0, CDBHelper::getCount($sql), "Chuck Norris: all hosts disabled but DB does not match");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassDisable($host) {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestClickButton('host.massdisable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host disabled');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_NOT_MONITORED;
		$this->assertEquals(1, CDBHelper::getCount($sql), "Chuck Norris: host $hostid disabled but status is wrong in the DB");
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageHosts_MassActivate($host) {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$hostid = $host['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('hosts_'.$hostid);
		$this->zbxTestClickButton('host.massenable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host enabled');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_MONITORED;
		$this->assertEquals(1, CDBHelper::getCount($sql), "Chuck Norris: host $hostid activated but status is wrong in the DB");
	}

	public function testPageHosts_MassActivateAll() {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButton('host.massenable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Hosts enabled');

		$sql = "select host from hosts where status=".HOST_STATUS_NOT_MONITORED.
			" and NOT flags = 2";
		$this->assertEquals(0, CDBHelper::getCount($sql), "Chuck Norris: all hosts activated but DB does not match");
	}

	public function testPageHosts_FilterByName() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestInputTypeOverwrite('filter_host', $this->HostName);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextPresent($this->HostName);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterByTemplates() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonMultiselect('filter_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');
		$this->zbxTestClickXpathWait('//div[@id="overlay_dialogue"]//select/option[text()="Templates"]');
		$this->zbxTestClickXpathWait('//div[@id="overlay_dialogue"]//a[text()="Form test template"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='Simple form test host']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageHosts_FilterByProxy() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpathWait('//label[text()="Proxy"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='Host_1 with proxy']");
		$this->zbxTestAssertElementPresentXpath("//tbody//td[text()='Proxy_1 for filter']");
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='Host_2 with proxy']");
		$this->zbxTestAssertElementPresentXpath("//tbody//td[text()='Proxy_2 for filter']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 2 of 2 found']");
		$this->zbxTestClickButtonMultiselect('filter_proxyids_');
		$this->zbxTestLaunchOverlayDialog('Proxies');
		$this->zbxTestClickLinkTextWait('Proxy_1 for filter');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementPresentXpath("//tbody//a[text()='Host_1 with proxy']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageHosts_FilterNone() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestInputTypeOverwrite('filter_host', '1928379128ksdhksdjfh');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 0 of 0 found']");
		$this->zbxTestInputTypeOverwrite('filter_host', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 0 of 0 found']");
	}

	public function testPageHosts_FilterByAllFields() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestDropdownSelectWait('groupid', $this->HostGroup);
		$this->zbxTestInputTypeOverwrite('filter_host', $this->HostName);
		$this->zbxTestInputTypeOverwrite('filter_ip', $this->HostIp);
		$this->zbxTestInputTypeOverwrite('filter_port', $this->HostPort);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextPresent($this->HostName);
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageHosts_FilterReset() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public static function getFilterByTagsData() {
		return [
			// "And" and "And/Or" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'host'],
						['name' => 'test', 'operator' => 'Contains', 'value' => 'test_tag']
					],
					'result' => [
						[
							'Name' => 'Simple form test host',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['tag: HOST', 'test: test_tag', 'action: simple']
							]
						]
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'host'],
						['name' => 'test', 'operator' => 'Contains', 'value' => 'test_tag']
					],
					'result' => [
						[
							'Name' => 'Host with tags for cloning',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['tag: host', 'action: clone']
							]
						],
						[
							'Name' => 'Host with tags for updating',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['tag: host', 'action: update']
							]
						],
						[
							'Name' => 'Simple form test host',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['tag: HOST', 'test: test_tag', 'action: simple']
							]
						]
					]
				]
			],
			// "Contains" and "Equals" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'HOST'],
					],
					'result' => [
						['Name' => 'Host with tags for cloning', 'Templates' => ''],
						['Name' => 'Host with tags for updating', 'Templates' => ''],
						['Name' => 'Simple form test host', 'Templates' => 'Form test template']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Equals', 'value' => 'HOST'],
					],
					'result' => [
						['Name' => 'Simple form test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
							['name' => 'action', 'operator' => 'Contains'],
					],
					'result' => [
						['Name' => 'Host with tags for cloning'],
						['Name' => 'Host with tags for updating'],
						['Name' => 'Simple form test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Equals'],
					]
				]
			]
		];
	}

	/**
	 * Test filtering hosts by tags.
	 *
	 * @dataProvider getFilterByTagsData
	 */
	public function testPageHosts_FilterByTags($data) {
		$this->page->login()->open('hosts.php?groupid=0');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		// Reset filter from possible previous scenario.
		$form->query('button:Reset')->one()->click();

		$this->setTags($data['evaluation_type'], $data['tags']);
		$form->submit();
		$this->page->waitUntilReady();
		// Check filtered result.
		$this->assertTableData(CTestArrayHelper::get($data, 'result', []));

		// Reset filter due to not influence further tests.
		$form->query('button:Reset')->one()->click();
	}
}

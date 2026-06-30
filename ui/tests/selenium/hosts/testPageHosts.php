<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CDatatableBehavior.php';
require_once __DIR__.'/../behaviors/CTagBehavior.php';

/**
 * @dataSource TagFilter, Proxies, WebScenarios
 *
 * @backup hosts, httptest
 *
 * @onBefore prepareHostsData
 */
class testPageHosts extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior, DatatableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CDatatableBehavior::class,
			CTagBehavior::class
		];
	}

	public $HostName = 'ЗАББИКС Сервер';
	public $HostGroup = 'Zabbix servers';
	public $HostIp = '127.0.0.1';
	public $HostPort = '10050';

	public function prepareHostsData() {
		CDataHelper::createHosts([
			[
				'host' => 'Disabled status',
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => '6'
				]
			],
			[
				'host' => 'Enabled status',
				'status' => HOST_STATUS_MONITORED,
				'groups' => [
					'groupid' => '6'
				]
			]
		]);
	}

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
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->getField('Host groups')->select($this->HostGroup);
		$filter->submit();
		$table = $this->query('class:datatable')->asDatatable()->one()->waitUntilReady();

		$this->zbxTestTextPresent($this->HostName);
		$this->zbxTestTextPresent('Simple form test host');
		$this->zbxTestTextNotPresent('Empty host');

		// Check display of proxy and proxy group multiselects based on value of Monitored by field.
		$id_mapping = [
			'Any' => ['filter_proxyids_' => false, 'filter_proxy_groupids_' => false],
			'Server' => ['filter_proxyids_' => false, 'filter_proxy_groupids_' => false],
			'Proxy' => ['filter_proxyids_' => true, 'filter_proxy_groupids_' => false],
			'Proxy group' => ['filter_proxyids_' => false, 'filter_proxy_groupids_' => true]
		];
		$monitored_by = $filter->getField('Monitored by');

		foreach (array_keys($id_mapping) as $monitored_by_value) {
			$monitored_by->select($monitored_by_value);

			foreach ($id_mapping[$monitored_by_value] as $id => $displayed) {
				$this->assertTrue($filter->query('id', $id)->one()->isDisplayed($displayed));
			}
		}

		$headers = ['', 'Name', 'Items', 'Triggers', 'Graphs', 'Discovery', 'Web', 'Interface', 'Proxy', 'Templates',
			'Status', 'Availability', 'Agent encryption', 'Info', 'Tags'
		];
		$table->invalidate();
		$this->assertEquals($headers, $table->getHeadersText());

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
			'SELECT hostid,proxyid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,'.
			'ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,'.
			'name,flags,templateid,description,tls_connect,tls_accept'.
			' FROM hosts'.
			' WHERE hostid='.$hostid;
		$oldHashHosts = CDBHelper::getHash($sqlHosts);
		$sqlItems = "select * from items where hostid=$hostid order by itemid";
		$oldHashItems = CDBHelper::getHash($sqlItems);
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

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestCheckHeader('Hosts');

		$datatable = $this->query('id:hosts')->asDatatable()->one()->waitUntilReady();
		$this->query('link', $name)->waitUntilVisible()->one()->scrollIntoView(50)->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->submit();
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');
		$datatable->waitUntilReady();
		$this->assertTrue($this->query('link', $name)->waitUntilPresent()->one()->isValid());

		$this->assertEquals($oldHashHosts, CDBHelper::getHash($sqlHosts));
		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
		$this->assertEquals($oldHashInterface, CDBHelper::getHash($sqlInterface));
		$this->assertEquals($oldHashHostMacro, CDBHelper::getHash($sqlHostMacro));
		$this->assertEquals($oldHashHostsGroups, CDBHelper::getHash($sqlHostsGroups));
		$this->assertEquals($oldHashHostsTemplates, CDBHelper::getHash($sqlHostsTemplates));
		$this->assertEquals($oldHashMaintenancesHosts, CDBHelper::getHash($sqlMaintenancesHosts));
		$this->assertEquals($oldHashHostInventory, CDBHelper::getHash($sqlHostInventory));
	}

	public function getFilterByStatusData() {
		return [
			// Retrieve only Enabled host from specific host group.
			[
				[
					'filter' => [
						'Host groups' => 'Virtual machines',
						'Status' => 'Enabled'
					],
					'expected' => [
						'Enabled status'
					]
				]
			],
			// Apply filtering with no results in output.
			[
				[
					'filter' => [
						'Name' => 'Disabled status',
						'Status' => 'Enabled'
					]
				]
			],
			// Retrieve only Disabled Host which is monitored by the server.
			[
				[
					'filter' => [
						'Status' => 'Disabled',
						'Monitored by' => 'Server'
					],
					'expected' => [
						'Disabled status'
					]
				]
			],
			// Retrieve Any host with a partial name match.
			[
				[
					'filter' => [
						'Name' => 'status',
						'Status' => 'Any'
					],
					'expected' => [
						'Disabled status',
						'Enabled status'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterByStatusData
	 */
	public function testPageHosts_FilterByStatus($data) {
		$this->page->login()->open('zabbix.php?action=host.list');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Apply filtering parameters.
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();

		if (array_key_exists('expected', $data)) {
			// Using column Name check that only the expected Hosts are returned in the list.
			$this->assertDatatableDataColumn($data['expected']);
		}
		else {
			// Check that 'No data found' string is returned if no results are expected.
			$this->assertDatatableData();
		}

		// Reset filter due to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function testPageHosts_MassDisableAll() {
		DBexecute("update hosts set status=".HOST_STATUS_MONITORED." where status=".HOST_STATUS_NOT_MONITORED);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->query('button:Reset')->one()->click();
		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();

		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButtonText('Disable');
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

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->query('button:Reset')->one()->click();
		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();

		$this->zbxTestCheckboxSelect('hostids_'.$hostid);
		$this->zbxTestClickButtonText('Disable');
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

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->query('button:Reset')->one()->click();
		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();

		$this->zbxTestCheckboxSelect('hostids_'.$hostid);
		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host enabled');

		$sql = "select * from hosts where hostid=$hostid and status=".HOST_STATUS_MONITORED;
		$this->assertEquals(1, CDBHelper::getCount($sql), "Chuck Norris: host $hostid activated but status is wrong in the DB");
	}

	public function testPageHosts_MassActivateAll() {
		DBexecute("update hosts set status=".HOST_STATUS_NOT_MONITORED." where status=".HOST_STATUS_MONITORED);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->query('button:Reset')->one()->click();

		$this->query('id:hosts')->asDatatable()->one()->waitUntilReady();
		$this->zbxTestCheckboxSelect('all_hosts');
		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Hosts enabled');

		$sql = "select host from hosts where status=".HOST_STATUS_NOT_MONITORED.
			" and NOT flags = 2";
		$this->assertEquals(0, CDBHelper::getCount($sql), "Chuck Norris: all hosts activated but DB does not match");
	}

	public function testPageHosts_FilterByName() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$datatable = $this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->getField('Name')->fill($this->HostName);
		$filter->submit();
		$datatable->waitUntilReady();
		$this->zbxTestTextPresent($this->HostName);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageHosts_FilterByTemplates() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$table = $this->getDatatable();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->fill(['Templates' => ['values' =>'Template for web scenario testing', 'context' => 'Templates']]);
		$filter->submit();
		$table->waitUntilReady();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementPresentXpath('//div[@class="datatable-body"]//a[text()="Simple form test host"]');
		$this->assertDatatableStats(1);
	}

	public function getProxyFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Monitored by' => 'Proxy'
					],
					'expected' => [
						'Host_1 with proxy' => 'Proxy_1 for filter',
						'Host_2 with proxy' => 'Proxy_2 for filter',
						'Test item host' => 'Active proxy 1'
					]
				]
			],
			[
				[
					'filter' => [
						'Monitored by' => 'Proxy group'
					],
					'expected' => [
						'Host linked to proxy group' => 'Group without proxies with linked host',
						'Host linked to proxy group 2' => 'Online proxy group'
					]
				]
			],
			[
				[
					'filter' => [
						'Monitored by' => 'Proxy',
						'xpath:.//div[@id="filter_proxyids_"]/..' => 'passive_proxy1'
					],
					'expected' => [
						'disabled_host1' => 'passive_proxy1'
					]
				]
			],
			[
				[
					'filter' => [
						'Monitored by' => 'Proxy group',
						'xpath:.//div[@id="filter_proxy_groupids_"]/..' => 'Group without proxies with linked host'
					],
					'expected' => [
						'Host linked to proxy group' => 'Group without proxies with linked host'
					]
				]
			]
		];
	}
	/**
	 * @dataProvider getProxyFilterData
	 */
	public function testPageHosts_FilterMonitoredBy($data) {
		$this->page->login()->open(self::HOST_LIST_PAGE)->waitUntilReady();

		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->fill($data['filter']);
		$filter->submit();
		$this->page->waitUntilReady();
		$table = $this->query('class:datatable')->asDatatable()->one()->waitUntilReady();

		$this->assertDatatableStats(count($data['expected']));
		$this->assertEquals(array_keys($data['expected']), $this->getDatatableColumnData('Name'));

		foreach($data['expected'] as $host => $proxy) {
			$this->assertEquals($proxy, $table->findRow('Name', $host)->getColumn('Proxy')->getText());
		}
	}

	public function testPageHosts_FilterNone() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->getField('Name')->fill('1928379128ksdhksdjfh');
		$filter->submit();
		$this->page->waitUntilReady();
		$table = $this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		$this->assertDatatableStats();
		$filter->invalidate();
		$filter->getField('Name')->fill('%');
		$filter->submit();
		$table->waitUntilReady();
		$this->page->waitUntilReady();
		$this->assertDatatableStats();
	}

	public function testPageHosts_FilterByAllFields() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->getField('Host groups')->select($this->HostGroup);
		$filter->getField('Name')->fill($this->HostName);
		$filter->getField('IP')->fill($this->HostIp);
		$filter->getField('Port')->fill($this->HostPort);
		$filter->submit();
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		$this->zbxTestTextPresent($this->HostName);
		$this->assertDatatableStats(1);
	}

	public function testPageHosts_FilterReset() {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
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
							'Name' => 'Host for tags filtering',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['action: simple', 'tag: HOST', 'tag: TEMPLATE']
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
							'Name' => 'Host for tags filtering',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['action: simple', 'tag: HOST', 'tag: TEMPLATE']
							]
						],
						[
							'Name' => 'Host for tags filtering - clone',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['action: clone', 'tag: host']
							]
						],
						[
							'Name' => 'Host for tags filtering - update',
							'Tags' => [
								'selector' => 'class:tag',
								'text' => ['action: update', 'tag: host']
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
						['name' => 'tag', 'operator' => 'Contains', 'value' => 'HOST']
					],
					'result' => [
						['Name' => 'Host for tags filtering', 'Templates' => 'Template for tags filtering'],
						['Name' => 'Host for tags filtering - clone', 'Templates' => ''],
						['Name' => 'Host for tags filtering - update', 'Templates' => '']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Equals', 'value' => 'HOST']
					],
					'result' => [
						['Name' => 'Host for tags filtering']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
							['name' => 'action', 'operator' => 'Contains']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Equals']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Exists'],
						['name' => 'test', 'operator' => 'Exists']
					],
					'result' => [
						['Name' => 'Host for tags filtering']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'tag', 'operator' => 'Exists'],
						['name' => 'test', 'operator' => 'Exists']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Exists']
					],
					'result' => [
						['Name' => 'Host for tags filtering']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Exists']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist'],
						['name' => 'tag', 'operator' => 'Does not exist']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not exist'],
						['name' => 'tag', 'operator' => 'Does not exist']
					],
					'result' => [
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal', 'value' => 'test_tag']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'test', 'operator' => 'Does not equal']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not equal', 'value' => 'update'],
						['name' => 'action', 'operator' => 'Does not equal', 'value' => 'simple']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not equal', 'value' => 'update'],
						['name' => 'action', 'operator' => 'Does not equal', 'value' => 'simple']
					],
					'result' => [
						['Name' => 'Host for tags filtering - clone'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clone']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clone']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clo'],
						['name' => 'tag', 'operator' => 'Does not contain', 'value' => 'ho']
					],
					'result' => [
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clo'],
						['name' => 'tag', 'operator' => 'Does not contain', 'value' => 'ho']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - update'],
						['Name' => 'Simple form test host'],
						['Name' => 'SLA reports host'],
						['Name' => 'Template inheritance test host']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clone'],
						['name' => 'tag', 'operator' => 'Equals', 'value' => 'host']
					],
					'result' => [
						['Name' => 'Host for tags filtering - update']
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'action', 'operator' => 'Does not contain', 'value' => 'clone'],
						['name' => 'tag', 'operator' => 'Exists']
					],
					'result' => [
						['Name' => 'Host for tags filtering'],
						['Name' => 'Host for tags filtering - update']
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
		$this->page->login()->open((new CUrl('zabbix.php'))
			->setArgument('action', 'host.list')
			->setArgument('filter_groups[]', 4)
			->setArgument('filter_host', 'host')
			->setArgument('filter_port', 10051)
			->setArgument('filter_set', 1)
			->getUrl()
		);
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:filter_evaltype' => $data['evaluation_type']]);
		$this->setTags($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->query('class:datatable')->asDatatable()->one()->waitUntilReady();
		// Check filtered result.
		$this->assertDatatableData(CTestArrayHelper::get($data, 'result', []));

		// Reset filter due to not influence further tests.
		$form->query('button:Reset')->one()->click();
	}

	/**
	 * Test the Enable and Disable link in the Host list.
	 */
	public function testPageHosts_EnableDisableLink() {
		$this->page->login()->open('zabbix.php?action=host.list')->waitUntilReady();
		$host_row = $this->query('class:datatable')->asDatatable()->one()->waitUntilReady()->findRow('Name', 'Enabled status');

		foreach (['Disabled' => HOST_STATUS_NOT_MONITORED, 'Enabled' => HOST_STATUS_MONITORED] as $status => $id) {
			$host_row->invalidate();
			$host_row->getColumn('Status')->scrollIntoView(50)->query('tag:a')->one()->click();
			$this->page->waitUntilReady();
			$host_row->invalidate();
			$this->assertMessage(TEST_GOOD, 'Host '.strtolower($status));
			$this->assertEquals($status, $host_row->getColumn('Status')->getText());
			$this->assertEquals($id, CDBHelper::getValue('SELECT status FROM hosts WHERE host='.zbx_dbstr('Enabled status')));
			CMessageElement::find()->one()->close();
		}

		$this->query('tag:h1')->one()->click();
	}

	public function testPageHosts_Delete() {
		$this->page->login()->open('zabbix.php?action=host.list')->waitUntilReady();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->query('button:Reset')->one()->click();
		$filter->getField('Name')->fill('Host for t');
		$filter->submit();

		$table_rows_count = $this->query('id:hosts')->asDatatable()->one()->waitUntilReady()->getRows()->count();
		$this->assertDatatableStats($table_rows_count);
		$delete_button = $this->query('button:Delete')->one();

		// Cancel delete.
		$all_hosts = $this->query('id:all_hosts')->asCheckbox()->one();
		$all_hosts->check();
		$delete_button->click();
		$this->page->dismissAlert();
		$this->assertDatatableStats($table_rows_count);
		$this->assertSelectedCount($table_rows_count);
		$all_hosts->uncheck();

		$delete_hosts = [
			['Host for tags filtering'],
			['Host for tags filtering - clone', 'Host for tags filtering - update']
		];

		// Delete single/multiple hosts.
		foreach ($delete_hosts as $selection) {
			$host_count = count($selection);
			$this->selectDatatableRows($selection);
			$delete_button->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, ($host_count > 1) ? 'Hosts deleted' : 'Host deleted');
			CMessageElement::find()->one()->close();
			$table_rows_count = $table_rows_count - $host_count;
			$this->assertDatatableStats($table_rows_count);
			$this->assertSelectedCount(0);
		}
	}
}

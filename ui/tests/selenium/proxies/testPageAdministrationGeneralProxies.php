<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxies page.
 *
 * @backup hosts
 */
class testPageAdministrationGeneralProxies extends CWebTest {

	private $sql = 'SELECT * FROM hosts ORDER BY hostid';

	private static $enabled_hosts = [
			'enabled_host1',
			'enabled_host2',
			'enabled_host3',
			'enabled_host4',
			'enabled_host5',
			'enabled_host6',
			'enabled_host7',
			'enabled_host8'
	];

	private static $disabled_hosts = [
			'disabled_host1',
			'disabled_host2',
			'disabled_host3',
			'disabled_host4',
			'disabled_host5',
			'disabled_host6',
			'disabled_host7',
			'disabled_host8'
	];

	private static $active_proxies = [
			'active_proxy1',
			'active_proxy2',
			'active_proxy3',
			'active_proxy4',
			'active_proxy5',
			'active_proxy6',
			'active_proxy7'
	];

	private static $passive_proxies = [
			'passive_proxy1',
			'passive_proxy2',
			'passive_proxy3',
			'passive_proxy4',
			'passive_proxy5',
			'passive_proxy6',
			'passive_proxy7'
	];

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Preparing proxies and hosts for test.
	 */
	public function prepareProxyHostsData() {
		// Create enabled hosts.
		$enabled_hosts_data = [];
		foreach (self::$enabled_hosts as $host) {
			$enabled_hosts_data[] = [
				'host' => $host,
				'groups' => [['groupid' => 4]],
				'status' => 0
			];
		}

		$enabled_hosts = CDataHelper::call('host.create', $enabled_hosts_data);
		$this->assertArrayHasKey('hostids', $enabled_hosts);
		$enabled_hostids = CDataHelper::getIds('host');

		// Disabled hosts data.
		$disabled_hosts_data = [];
		foreach (self::$disabled_hosts as $host) {
			$disabled_hosts_data[] = [
				'host' => $host,
				'groups' => [['groupid' => 4]],
				'status' => 1
			];
		}
		$disabled_hosts = CDataHelper::call('host.create', $disabled_hosts_data);
		$this->assertArrayHasKey('hostids', $disabled_hosts);
		$disabled_hostids = CDataHelper::getIds('host');

		// Create active proxies.
		$active_proxy_data = [];
		foreach (self::$active_proxies as $proxy) {
			$active_proxy_data[] = [
				'host' => $proxy,
				'status' => 5
			];
		}

		$active_proxies = CDataHelper::call('proxy.create', $active_proxy_data);
		$this->assertArrayHasKey('proxyids', $active_proxies);
		$active_proxyids = CDataHelper::getIds('host');

		// Create passive proxies.
		$passive_proxy_data = [];
		foreach (self::$passive_proxies as $proxy) {
			$passive_proxy_data[] = [
				'host' => $proxy,
				'status' => 6,
				'interface' => [
					'ip'=> '127.0.0.1',
					'dns' => '',
					'useip' => '1',
					'port' => '10051'
				]
			];
		}

		$passive_proxies = CDataHelper::call('proxy.create', $passive_proxy_data);
		$this->assertArrayHasKey('proxyids', $passive_proxies);
		$passive_proxyids = CDataHelper::getIds('host');

		// Add hosts to proxies.
		CDataHelper::call('proxy.update', [
			[
				'proxyid' => $active_proxyids['active_proxy1'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host1']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy1'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host1']]
				]
			],
			[
				'proxyid' => $active_proxyids['active_proxy2'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host2']],
					['hostid' => $enabled_hostids['enabled_host3']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy2'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host4']],
					['hostid' => $enabled_hostids['enabled_host5']]
				]
			],
			[
				'proxyid' => $active_proxyids['active_proxy3'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host2']],
					['hostid' => $disabled_hostids['disabled_host3']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy3'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host4']],
					['hostid' => $disabled_hostids['disabled_host5']]
				]
			],
			[
				'proxyid' =>  $active_proxyids['active_proxy4'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host6']],
					['hostid' => $disabled_hostids['disabled_host6']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy4'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host7']],
					['hostid' => $enabled_hostids['enabled_host8']],
					['hostid' => $disabled_hostids['disabled_host7']],
					['hostid' => $disabled_hostids['disabled_host8']]
				]
			]
		]);
	}

	public function testPageAdministrationGeneralProxies_Layout() {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of proxies');
		$this->page->assertHeader('Proxies');

		// Check filter collapse/expand.
		foreach ([true, false] as $status) {
			$this->assertEquals($status, $this->query('xpath://div[contains(@class, "ui-tabs-panel")]')
					->one()->isVisible()
			);
			$this->query('xpath://a[contains(@class, "filter-trigger")]')->one()->click();
		}

		$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
		$this->assertEquals(['', 'Name', 'Mode', 'Encryption', 'Compression', 'Last seen (age)', 'Host count', 'Item count',
				'Required performance (vps)', 'Hosts'], $table->getHeadersText()
		);

		// Check that sortable header is clickable.
		$this->assertTrue($table->query('link:Name')->one()->isClickable());

		// Check buttons are disabled by default.
		foreach (['Refresh configuration', 'Enable hosts', 'Disable hosts', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isVisible());
			$this->assertFalse($this->query('button', $button)->one()->isClickable());
		}
	}

	public function testPageAdministrationGeneralProxies_CheckTableAndFilterReset() {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$form->query('button:Reset')->one()->click();
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
		$rows_count = $table->getRows()->count();
		$table_contents = $this->getTableColumnData('Name');

		// Fill filter form with data.
		$form->fill(['Name' => '1']);
		$form->submit();
		$this->page->waitUntilReady();

		$filter_result = [
			[
				'Name' => 'Active proxy 1',
				'Mode' => 'Active',
				'Encryption' => 'None',
				'Compression' => 'On',
				'Last seen (age)' => 'Never',
				'Host count' => '',
				'Item count' => '',
				'Required performance (vps)' => '',
				'Hosts' => 'Test item host'
			],
			[
				'Name' => 'Passive proxy 1',
				'Mode' => 'Passive',
				'Encryption' => 'None',
				'Compression' => 'On',
				'Last seen (age)' => 'Never',
				'Host count' => '',
				'Item count' => '',
				'Required performance (vps)' => '',
				'Hosts' => ''
			],
			[
				'Name' => 'Proxy_1 for filter',
				'Mode' => 'Active',
				'Encryption' => 'None',
				'Compression' => 'On',
				'Last seen (age)' => 'Never',
				'Host count' => '',
				'Item count' => '',
				'Required performance (vps)' => '',
				'Hosts' => 'Host_1 with proxy'
			]
		];

		// Check filtered result.
		$this->assertTableData($filter_result);

		// Reset filter and assert row count.
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($rows_count, $table->getRows()->count());
		$this->assertEquals($table_contents, $this->getTableColumnData('Name'));
	}

	public function getFilterProxyData() {
		return [
			[
				[
					'filter' => [
						'Name' => 'for filter'
					],
					'result' => [
						'Proxy_1 for filter',
						'Proxy_2 for filter'
					]
				]
			],
			[
				[
					'filter' => [
						'Mode' => 'Active'
					],
					'result' => [
						'Active proxy 1',
						'Active proxy 2',
						'Active proxy 3',
						'Active proxy to delete',
						'Proxy_1 for filter',
						'Proxy_2 for filter'
					],
					'check_stats' => true
				]
			],
			[
				[
					'filter' => [
						'Mode' => 'Passive'
					],
					'result' => [
						'Passive proxy 1',
						'Passive proxy 2',
						'Passive proxy 3',
						'Passive proxy to delete'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'filter',
						'Mode' => 'Active'
					],
					'result' => [
						'Proxy_1 for filter',
						'Proxy_2 for filter'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'filter',
						'Mode' => 'Passive'
					],
					'result' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterProxyData
	 */
	public function testPageAdministrationGeneralProxies_Filter($data) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$form->query('button:Reset')->one()->click();

		// Fill filter form with data.
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check filtered result.
		$this->assertTableDataColumn($data['result']);

		if (CTestArrayHelper::get($data, 'check_stats')) {
			$this->assertTableStats(count($data['result']));
		}

		// Reset filter not to impact the results of next tests.
		$this->query('button:Reset')->one()->click();
	}

	public static function getActionsProxyData() {
		return [
			// Refresh of one active proxy.
			[
				[
					'action' => 'Refresh configuration',
					'proxies' => [
						'active_proxy5'
					],
					'alert' => 'Refresh configuration of the selected proxy?',
					'title' => 'Request created successfully'
				]
			],
			// Refresh of one passive proxy.
			[
				[
					'action' => 'Refresh configuration',
					'proxies' => [
						'passive_proxy5'
					],
					'alert' => 'Refresh configuration of the selected proxy?',
					'title' => 'Request created successfully'
				]
			],
			// Refresh of one proxy with hosts.
			[
				[
					'action' => 'Refresh configuration',
					'proxies' => [
						'passive_proxy4'
					],
					'alert' => 'Refresh configuration of the selected proxy?',
					'title' => 'Request created successfully'
				]
			],
			// Refresh of one proxy used by discovery rule.
			[
				[
					'action' => 'Refresh configuration',
					'proxies' => [
						'Passive proxy 1'
					],
					'alert' => 'Refresh configuration of the selected proxy?',
					'title' => 'Request created successfully'
				]
			],
			// Mass refresh of several proxies.
			[
				[
					'action' => 'Refresh configuration',
					'proxies' => [
						'passive_proxy5',
						'active_proxy4',
						'Passive proxy 1'
					],
					'alert' => 'Refresh configuration of the selected proxies?',
					'title' => 'Request created successfully'
				]
			],
			// Enable 1 enabled host on 1 proxy.
			[
				[
					'action' => 'Enable hosts',
					'proxies' => [
						'active_proxy1'
					],
					'hosts' => [
						'enabled_host1'
					],
					'alert' => 'Enable hosts monitored by selected proxy?',
					'title' => 'Hosts enabled'
				]
			],
			// Disable 1 disabled host on 1 proxy.
			[
				[
					'action' => 'Disable hosts',
					'proxies' => [
						'passive_proxy1'
					],
					'hosts' => [
						'disabled_host1'
					],
					'alert' => 'Disable hosts monitored by selected proxy?',
					'title' => 'Hosts disabled'
				]
			],
			// Enable 1 enabled and 1 disabled hosts on 2 proxies.
			[
				[
					'action' => 'Enable hosts',
					'proxies' => [
						'active_proxy1',
						'passive_proxy1'
					],
					'hosts' => [
						'enabled_host1',
						'disabled_host1'
					],
					'alert' => 'Enable hosts monitored by selected proxies?',
					'title' => 'Host enabled',
					'message' => 'Updated status of host "disabled_host1".'
				]
			],
			// Enable 2 disabled and 2 enabled hosts on 2 proxies.
			[
				[
					'action' => 'Enable hosts',
					'proxies' => [
						'active_proxy3',
						'passive_proxy2'
					],
					'hosts' => [
						'disabled_host2',
						'disabled_host3',
						'enabled_host4',
						'enabled_host5'
					],
					'alert' => 'Enable hosts monitored by selected proxies?',
					'title' => 'Hosts enabled',
					'message' => [
						'Updated status of host "disabled_host2".',
						'Updated status of host "disabled_host3".'
					]
				]
			],
			// Disable 3 disabled and 3 enabled hosts on 2 proxies.
			[
				[
					'action' => 'Disable hosts',
					'proxies' => [
						'active_proxy4',
						'passive_proxy4'
					],
					'hosts' => [
						'enabled_host6',
						'disabled_host6',
						'enabled_host7',
						'enabled_host8',
						'disabled_host7',
						'disabled_host8'
					],
					'alert' => 'Disable hosts monitored by selected proxies?',
					'title' => 'Hosts disabled',
					'message' => [
						'Updated status of host "enabled_host6".',
						'Updated status of host "enabled_host7".',
						'Updated status of host "enabled_host8".'
					]
				]
			],
			// Delete active proxy.
			[
				[
					'action' => 'Delete',
					'proxies' => [
						'active_proxy5'
					],
					'alert' => 'Delete selected proxy?',
					'title' => 'Proxy deleted'
				]
			],
			// Delete passive proxy.
			[
				[
					'action' => 'Delete',
					'proxies' => [
						'passive_proxy5'
					],
					'alert' => 'Delete selected proxy?',
					'title' => 'Proxy deleted'
				]
			],
			// Mass delete of active and passive proxies.
			[
				[
					'action' => 'Delete',
					'proxies' => [
						'active_proxy6',
						'passive_proxy6'
					],
					'alert' => 'Delete selected proxies?',
					'title' => 'Proxies deleted'
				]
			],
			// Mass delete when one of the proxies monitor some host.
			[
				[
					'expected' => TEST_BAD,
					'action' => 'Delete',
					'proxies' => [
						'active_proxy1',
						'active_proxy7'
					],
					'alert' => 'Delete selected proxies?',
					'title' => 'Cannot delete proxies',
					'error' => 'Host "enabled_host1" is monitored by proxy "active_proxy1".'
				]
			],
			// Mass delete when one of the proxies monitor is used by discovery rule.
			[
				[
					'expected' => TEST_BAD,
					'action' => 'Delete',
					'proxies' => [
						'passive_proxy7',
						'Passive proxy 1'
					],
					'alert' => 'Delete selected proxies?',
					'title' => 'Cannot delete proxies',
					'error' => "Proxy \"Passive proxy 1\" is used by discovery rule \"Discovery rule for update\"."
				]
			],
			// Delete one proxy with host.
			[
				[
					'expected' => TEST_BAD,
					'action' => 'Delete',
					'proxies' => [
						'Proxy_2 for filter'
					],
					'alert' => 'Delete selected proxy?',
					'title' => 'Cannot delete proxy',
					'error' => 'Host "Host_2 with proxy" is monitored by proxy "Proxy_2 for filter".'
				]
			],
			// Delete one proxy used by discovery rule.
			[
				[
					'expected' => TEST_BAD,
					'action' => 'Delete',
					'proxies' => [
						'Passive proxy 1'
					],
					'alert' => 'Delete selected proxy?',
					'title' => 'Cannot delete proxy',
					'error' => "Proxy \"Passive proxy 1\" is used by discovery rule \"Discovery rule for update\"."
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareProxyHostsData
	 *
	 * @dataProvider getActionsProxyData
	 */
	public function testPageAdministrationGeneralProxies_Actions($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('class:list-table')->asTable()->one()->findRows('Name', $data['proxies'])->select();
		$this->query('button', $data['action'])->waitUntilClickable()->one()->click();

		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals($data['alert'], $this->page->getAlertText());
		$this->page->acceptAlert();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['title'], $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
		else {
			$this->assertMessage(TEST_GOOD, $data['title'], CTestArrayHelper::get($data, 'message', null));

			// Check DB. Status 5 stands for Active proxy and status 6 - for Passive proxy.
			$db_proxies = CDBHelper::getColumn('SELECT * FROM hosts WHERE status IN (5,6)', 'host');

			foreach ($data['proxies'] as $proxy) {
				$this->assertEquals(($data['action'] !== 'Delete'), in_array($proxy, array_values($db_proxies)));

				$exists = ($data['action'] === 'Delete')
					? array_key_exists('expected', $data)
					: true;

				$this->assertEquals($exists, $this->query('link', $proxy)->exists());
			}

			// Check that hosts are actually enabled/disabled.
			if ($data['action'] === 'Enable hosts' || $data['action'] === 'Disable hosts') {
				$hosts = CDBHelper::getAll('SELECT host, status FROM hosts WHERE proxy_hostid IS NOT NULL');

				// DB check for hosts.
				foreach ($hosts as $host) {
					if (in_array($host['host'], $data['hosts'])) {
						$this->assertEquals(($data['action'] === 'Enable hosts') ? 0 : 1, $host['status']);
					}
				}
			}
		}

		// Check that user redirected on Proxies page.
		$this->page->assertHeader('Proxies');
	}
}

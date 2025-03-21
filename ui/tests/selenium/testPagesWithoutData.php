<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once __DIR__.'/../include/CWebTest.php';

/**
 * Test for checking empty pages and tables.
 *
 * @onBefore clearData, prepareEmptyData
 *
 * @backup profiles
 */
class testPagesWithoutData extends CWebTest {

	const EMPTY_HOST = 'Empty host for multiselects test';
	const EMPTY_LLD_HOST = 'Host with empty LLD';
	const EMPTY_TEMPLATE = 'Empty template for multiselects test';
	const EMPTY_LLD_TEMPLATE = 'Template with empty LLD';
	protected static $empty_hostid;
	protected static $empty_templateid;
	protected static $host_lldid;
	protected static $template_lldid;

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	/**
	 * Function for finding and deleting data created before with previous tests.
	 */
	public function clearData() {
		// Delete SLA.
		$slaids = CDBHelper::getColumn('SELECT * FROM sla', 'slaid');
		if ($slaids !== []) {
			CDataHelper::call('sla.delete', array_values($slaids));
		}
	}

	public function prepareEmptyData() {
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for empty pages']]);
		$host_groupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::createHosts([
			[
				'host' => self::EMPTY_LLD_HOST,
				'groups' => [['groupid' => $host_groupid]],
				'discoveryrules' => [
					[
						'name' => 'Empty LLD',
						'key_' => 'lld_test',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => self::EMPTY_HOST,
				'groups' => [['groupid' => $host_groupid]]
			]
		]);

		self::$host_lldid = $hosts['discoveryruleids'][self::EMPTY_LLD_HOST.':lld_test'];
		self::$empty_hostid = $hosts['hostids'][self::EMPTY_HOST];

		$template_groups = CDataHelper::call('templategroup.create', [
			['name' => 'Template group for empty template']
		]);

		$template_groupid = $template_groups['groupids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => self::EMPTY_LLD_TEMPLATE,
				'groups' => [['groupid' => $template_groupid]],
				'discoveryrules' => [
					[
						'name' => 'Empty template LLD',
						'key_' => 'lld_test',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			],
			[
				'host' => self::EMPTY_TEMPLATE,
				'groups' => [['groupid' => $template_groupid]]
			]
		]);
		self::$template_lldid = $templates['discoveryruleids'][self::EMPTY_LLD_TEMPLATE.':lld_test'];
		self::$empty_templateid = $templates['templateids'][self::EMPTY_TEMPLATE];
	}

	public static function getEmptyPagesData() {
		return [
			//Hosts.
			// #0 Empty hosts table.
			[
				[
					'page' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'filter' => ['Name' => 'zzz']
				]
			],
			// #1 Empty hosts' items table.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #2 Empty hosts' triggers table.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #3 Empty hosts' graphs table.
			[
				[
					'url' => 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #4 Empty hosts' LLD table.
			[
				[
					'url' => 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #5 Empty hosts' Web scenarios table.
			[
				[
					'url' => 'httpconf.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #6 Item prototypes table.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='
				]
			],
			// #7 Trigger prototypes table.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='
				]
			],
			// #8 Graph prototypes table.
			[
				[
					'url' => 'graphs.php?context=host&parent_discoveryid='
				]
			],
			// #9 Host prototypes table.
			[
				[
					'url' => 'host_prototypes.php?context=host&parent_discoveryid='
				]
			],
			//Templates.
			// #10 Empty templates table.
			[
				[
					'page' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'filter' => ['Name' => 'zzz']
				]
			],
			// #11 Empty templates' items table.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #12 Empty templates' triggers table.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #13 Empty templates' graphs table.
			[
				[
					'url' => 'graphs.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #14 Empty templates' LLD table.
			[
				[
					'url' => 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #15 Empty templates' Web scenarios table.
			[
				[
					'url' => 'httpconf.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #16 Empty templates' dashboards table.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.list&templateid='
				]
			],
			//Other pages.
			// #17 Discovery rules table.
			[
				[
					'page' => 'Status of discovery',
					'url' => 'zabbix.php?action=discovery.view'
				]
			],
			// #18 Empty SLA page.
			[
				[
					'page' => 'SLA',
					'url' => 'zabbix.php?action=sla.list'
				]
			],
			// #19 Empty SLA report page.
			[
				[
					'page' => 'SLA report',
					'url' => 'zabbix.php?action=slareport.list'
				]
			],
			// #20 Empty Top 100 triggers page.
			[
				[
					'page' => 'Top 100 triggers',
					'url' => 'zabbix.php?action=toptriggers.list'
				]
			],
			// #21 Empty Maintenances page.
			[
				[
					'page' => 'Maintenance periods',
					'url' => 'zabbix.php?action=maintenance.list',
					'filter' => ['Name' => 'zzz']
				]
			]
		];
	}

	/**
	 * Test function for checking empty list tables.
	 *
	 * @dataProvider getEmptyPagesData
	 */
	public function testPagesWithoutData_CheckEmptyPages($data) {
		$context_host = str_contains($data['url'], 'context=host');

		if (in_array(CTestArrayHelper::get($data, 'page'), ['Hosts', 'Templates', 'SLA', 'SLA report',
			'Top 100 triggers', 'Maintenance periods', 'Status of discovery'])) {
			$url = $data['url'];
		}
		else {
			$url = (str_contains($data['url'], 'discoveryid='))
				? ($data['url'].($context_host ? self::$host_lldid : self::$template_lldid))
				: ($data['url'].($context_host ? self::$empty_hostid : self::$empty_templateid));
		}

		$this->page->login()->open($url);

		if (array_key_exists('filter', $data)) {
			$form = $this->query('name:zbx_filter')->asForm()->one();
			CFilterElement::find()->one()->selectTab('Filter');
			$form->fill($data['filter']);
			$form->submit();
		}

		if (CTestArrayHelper::get($data, 'page') === 'SLA report') {
			$this->assertEquals('Select SLA to display SLA report.',
					$this->query('xpath://div[@class="no-data-message"]')->one()->getText()
			);
		}
		else {
			$this->assertEquals(['No data found'],
					$this->getTable('xpath://table[@class="list-table no-data"]')->getRows()->asText()
			);
			$this->assertTableStats();
		}
	}
}

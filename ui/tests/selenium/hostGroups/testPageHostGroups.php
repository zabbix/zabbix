<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup hosts
 *
 * @onBefore preparePageHostGroupsData
 *
 * @dataSource DiscoveredHosts, HostGroups
 */
class testPageHostGroups extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const LINK = 'hostgroups.php';
	const GROUP_DISABLED = 'Group with two disabled hosts testPageHostGroups';
	const HOST1 = 'One disabled host testPageHostGroups';
	const HOST2 = 'Two disabled host testPageHostGroups';
	const TEMPLATE = 'Template with hosts in group testPageHostGroups';
	const GROUP_ENABLED = 'Group with two enabled hosts testPageHostGroups';
	const DELETE_GROUP3 = 'Group 3 for Delete test';

	/**
	 * Objects created in dataSource DiscoveredHosts.
	 */
	const DISCOVERED_GROUP = 'Group created from host prototype 1';
	const DISCOVERED_GROUP2 = 'Group created from host prototype 11';
	const DISCOVERED_HOST = 'Discovered host from prototype 1';
	const HOST_PROTOTYPE = 'Host created from host prototype {#KEY}';
	const LLD = 'LLD for Discovered host tests';

	/**
	 * Objects created in dataSource HostGroups for Delete test.
	 */
	const DELETE_ONE_HOST_GROUP = 'One group belongs to one host for Delete test';
	const DELETE_ONE_TEMPLATE_GROUP = 'One group belongs to one template for Delete test';
	const DELETE_EMPTY_GROUP = 'Group empty for Delete test';
	const DELETE_GROUP2 = 'First group to one object for Delete test';

	/**
	 * SQL query to get groups and hosts to compare hash values.
	 */
	const GROUPS_SQL = 'SELECT * FROM hstgrp g INNER JOIN hosts_groups hg ON g.groupid=hg.groupid'.
			' ORDER BY g.groupid, hg.hostgroupid';
	const HOSTS_SQL = 'SELECT * FROM hosts ORDER BY hostid';

	/**
	 * Prepare data for enable/disable hosts test.
	 */
	public static function preparePageHostGroupsData() {
		// Create three groups with disabled hosts and two groups with enabled hosts for testing.
		CDataHelper::call('hostgroup.create', [
			[
				'name' => self::GROUP_DISABLED
			],
			[
				'name' => 'Group with disabled host testPageHostGroups'
			],
			[
				'name' => 'Group2 with disabled host testPageHostGroups'
			],
			[
				'name' => 'Group with enabled host testPageHostGroups'
			],
			[
				'name' => self::GROUP_ENABLED
			],
			[
				'name' => self::DELETE_GROUP3
			]
		]);
		$groupids = CDataHelper::getIds('name');

		CDataHelper::createHosts([
			[
				'host' => self::HOST1,
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $groupids[self::GROUP_DISABLED]
				]
			],
			[
				'host' => self::HOST2,
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $groupids[self::GROUP_DISABLED]
				]
			],
			[
				'host' => 'Disabled host testPageHostGroups',
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group with disabled host testPageHostGroups']
				]
			],
			[
				'host' => 'Disabled host2 testPageHostGroups',
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $groupids['Group2 with disabled host testPageHostGroups']
				]
			],
			[
				'host' => 'Enabled host testPageHostGroups',
				'interfaces' => [],
				'groups' => [
					'groupid' => $groupids['Group with enabled host testPageHostGroups']
				]
			],
			[
				'host' => 'One enabled host testPageHostGroups',
				'interfaces' => [],
				'groups' => [
					'groupid' => $groupids[self::GROUP_ENABLED]
				]
			],
			[
				'host' => 'Two enabled host testPageHostGroups',
				'interfaces' => [],
				'groups' => [
					'groupid' => $groupids[self::GROUP_ENABLED]
				]
			]
		]);

		CDataHelper::createTemplates([
			[
				'host' => self::TEMPLATE,
				'groups' => [
					'groupid' => $groupids[self::GROUP_DISABLED]
				]
			]
		]);
	}

	public static function getLayoutData() {
		return [
			[
				[
					[
						'Name' => 'Discovered hosts',
						'Hosts' => 'Hosts',
						'Templates' => 'Templates',
						'Members' => '',
						'Info' => ''
					],
					[
						'Name' => self::LLD.': '.self::DISCOVERED_GROUP,
						'Hosts' => 'Hosts 1',
						'Templates' => 'Templates',
						'Members' => self::DISCOVERED_HOST,
						'Info' => ''
					],
					[
						'Name' => self::DELETE_ONE_TEMPLATE_GROUP,
						'Hosts' => 'Hosts',
						'Templates' => 'Templates 1',
						'Members' => 'Template for host group testing',
						'Info' => ''
					],
					[
						'Name' => self::GROUP_DISABLED,
						'Hosts' => 'Hosts 2',
						'Templates' => 'Templates 1',
						'Members' => self::TEMPLATE."\n\n".self::HOST1.', '.self::HOST2,
						'Info' => ''
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testPageHostGroups_Layout($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->page->assertHeader('Host groups');
		$this->page->assertTitle('Configuration of host groups');

		// Check filter.
		$filter = CFilterElement::find()->one();
		$form = $filter->getForm();
		$this->assertEquals(['Name'], $form->getLabels()->asText());
		$this->assertTrue($form->getField('Name')->isAttributePresent(['value' => '', 'maxlength' => '255']));

		// Check displaying and hiding the filter container.
		$this->assertTrue($filter->isExpanded());
		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved..
			$this->page->open('zabbix.php?action=report.status')->waitUntilReady();
			$this->page->open(self::LINK)->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		// Check buttons.
		$this->assertEquals(3, $this->query('button', ['Create host group', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count());
		$this->assertEquals(3, $this->query('button', ['Enable hosts', 'Disable hosts', 'Delete'])
				->all()->filter(CElementFilter::NOT_CLICKABLE)->count()
		);

		// Check table headers.
		$table = $this->getTable();
		$this->assertEquals(['' , 'Name', 'Hosts', 'Templates', 'Members', 'Info'] , $table->getHeadersText());
		$this->assertEquals(['Name'], $table->getSortableHeaders()->asText());

		// Check the displayed number of groups in the table.
		$names = $this->getGroupNames();
		$this->assertTableStats(count($names));
		$this->assertSelectedCount(0);
		$this->selectTableRows();
		$this->assertSelectedCount(count($names));
		$this->query('id:all_groups')->asCheckbox()->one()->uncheck();
		$this->assertSelectedCount(0);

		// Check table content.
		$this->assertTableHasData($data);

		// Check hintbox of discovered host group in info column.
		$hintbox_row = $table->findRow('Name', self::LLD.': '.self::DISCOVERED_GROUP);
		$hintbox_row->query('xpath://a[@class="icon-info status-yellow"]')->one()->click();
		$hintbox = $form->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilVisible();
		$this->assertEquals('The host group is not discovered anymore and will be deleted the next time discovery'.
				' rule is processed.', $hintbox->one()->getText()
		);
		$hintbox->query('class:overlay-close-btn')->one()->click()->waitUntilNotPresent();
	}

	public static function getLinksData() {
		return [
			[
				[
					'name' => self::DISCOVERED_GROUP,
					'lld' => self::LLD,
					'host' => self::DISCOVERED_HOST
				]
			],
			[
				[
					'name' => 'Group for discovered host test',
					'host' => self::DISCOVERED_HOST
				]
			],
			[
				[
					'name' => self::GROUP_DISABLED,
					'host' => self::HOST1,
					'template' => self::TEMPLATE
				]
			],
			[
				[
					'name' => self::DELETE_ONE_TEMPLATE_GROUP,
					'template' => 'Template for host group testing'
				]
			]
		];
	}

	/**
	 * Check related links of group in table row.
	 *
	 * @dataProvider getLinksData
	 */
	public function testPageHostGroups_Links($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();
		$row = $table->findRow('Name', array_key_exists('lld', $data) ? $data['lld'].': '.$data['name'] : $data['name']);

		// Check link to the host or template edit form.
		if (array_key_exists('host', $data)) {
			$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host']));
			$row->getColumn('Members')->query('link', $data['host'])->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action=host.edit&hostid='.$id, $this->page->getCurrentUrl());
			$this->assertEquals('Host', $dialog->getTitle());
			$dialog->asForm()->checkValue(['Host name' => $data['host']]);
			$dialog->close();
		}

		if (array_key_exists('template', $data)) {
			$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['template']));
			$row->getColumn('Members')->query('link', $data['template'])->one()->click();
			$this->assertStringContainsString('templates.php?form=update&templateid='.$id, $this->page->getCurrentUrl());
			$this->page->assertHeader('Templates');
			$this->query('id:templates-form')->asForm()->waitUntilVisible()->one()
					->checkValue(['Template name' => $data['template']]);
			$this->query('button:Cancel')->one()->click();
			$this->assertStringContainsString('templates.php', $this->page->getCurrentUrl());
			$this->page->open(self::LINK)->waitUntilReady();
		}

		// Check link to hosts or templates page with selected group in filer.
		$group_id = CDBHelper::getValue('SELECT groupid FROM hstgrp WHERE name='.zbx_dbstr($data['name']));
		foreach (['Hosts', 'Templates'] as $object) {
			$column = $row->getColumn($object);
			$count_tag = $column->query('tag:sup')->one(false);
			$count = $count_tag->isValid() ? $count_tag->getText() : 0;
			$column->query('link', $object)->one()->click();
			$this->assertStringContainsString((($object === 'Hosts') ? 'zabbix.php?action=host.list&' : 'templates.php?').
					'filter_set=1&filter_groups%5B0%5D='.$group_id, $this->page->getCurrentUrl()
			);
			$this->page->assertHeader($object);
			$filter_form = CFilterElement::find()->one()->getForm();
			$filter_form->checkValue(['Host groups' => $data['name']]);
			$this->assertTableStats($count);
			$this->page->open(self::LINK)->waitUntilReady();
		}

		// Check link to host prototype from host group name.
		if (array_key_exists('lld', $data)) {
			$row->getColumn('Name')->query('link', $data['lld'])->one()->click();
			$this->assertStringContainsString('host_prototypes.php?form=update&parent_discoveryid=', $this->page->getCurrentUrl());
			$this->page->assertHeader('Host prototypes');
			$this->query('id:host-prototype-form')->asForm()->waitUntilVisible()->one()
					->checkValue(['Host name' => self::HOST_PROTOTYPE]);
			$this->query('button:Cancel')->one()->click();
			$this->assertStringContainsString('host_prototypes.php?cancel=1&parent_discoveryid=', $this->page->getCurrentUrl());
			$this->page->open(self::LINK)->waitUntilReady();
		}
	}

	/**
	 * Get and sort group names.
	 *
	 * @param string $sort  sort content ascending or descending
	 */
	private function getGroupNames($sort = 'asc') {
		$names = CDBHelper::getColumn('SELECT name FROM hstgrp', 'name');

		natcasesort($names);
		if ($sort !== 'asc') {
			$names = array_reverse($names);
		}

		// Change names of discovered groups on page.
		$names[array_search(self::DISCOVERED_GROUP, $names)] = self::LLD.': '.self::DISCOVERED_GROUP;
		$names[array_search(self::DISCOVERED_GROUP2, $names)] = self::LLD.': '.self::DISCOVERED_GROUP2;

		return $names;
	}

	/**
	 * Check ascending and descending groups sorting by column Name.
	 */
	public function testPageHostGroups_Sort() {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();

		foreach (['desc', 'asc'] as $sorting) {
			$names = $this->getGroupNames($sorting);
			$table->query('link:Name')->waitUntilClickable()->one()->click();
			$table->waitUntilReloaded();
			$this->assertTableDataColumn($names);
		}
	}

	public static function getFilterData() {
		return [
			// Special symbols, utf8 and long name.
			[
				[
					'Name' => '&<>//\\[]""#@'
				]
			],
			[
				[
					'Name' => 'æ㓴🙂'
				]
			],
			[
				[
					'Name' => STRING_255
				]
			],
			// Exact match.
			[
				[
					'Name' => 'Group with disabled host testPageHostGroups',
					'expected' => ['Group with disabled host testPageHostGroups']
				]
			],
			[
				[
					'Name' => self::DELETE_ONE_TEMPLATE_GROUP,
					'expected' => [self::DELETE_ONE_TEMPLATE_GROUP]
				]
			],
			// Partial match.
			[
				[
					'Name' => 'with two enabled hosts',
					'expected' => [self::GROUP_ENABLED]
				]
			],
			[
				[
					'Name' => ' enabled ',
					'expected' => ['Group with enabled host testPageHostGroups', self::GROUP_ENABLED]
				]
			],
			[
				[
					'Name' => 'with disabled',
					'expected' => ['Group2 with disabled host testPageHostGroups', 'Group with disabled host testPageHostGroups']
				]
			],
			// Space trimming.
			[
				[
					'Name' => '   enabled   ',
					'expected' => ['Group with enabled host testPageHostGroups', self::GROUP_ENABLED]
				]
			],
			[
				[
					'Name' => '   ',
					'all' => true
				]
			],
			// Not case sensitive.
			[
				[
					'Name' => 'group2',
					'expected' => ['Group2 with disabled host testPageHostGroups']
				]
			],
			[
				[
					'Name' => 'GROUP2',
					'expected' => ['Group2 with disabled host testPageHostGroups']
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageHostGroups_Filter($data) {
		$all = $this->getGroupNames();
		if (array_key_exists('all', $data)) {
			$data['expected'] = $all;
		}

		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();
		$form = CFilterElement::find()->one()->getForm();
		$form->fill(['Name' => $data['Name']]);
		$form->submit();
		$table->waitUntilReloaded();
		$this->assertTableStats(count(CTestArrayHelper::get($data, 'expected', [])));
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

		// Reset filter.
		$form->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();
		$this->assertTableStats(count($all));
	}

	public static function getHostGroupsCancelData() {
		return [
			[
				[
					'action' => 'Enable hosts',
					'message' => 'Enable selected hosts?'
				]
			],
			[
				[
					'action' => 'Disable hosts',
					'message' => 'Disable hosts in the selected host groups?'
				]
			],
			[
				[
					'action' => 'Delete',
					'message' => 'Delete selected host groups?'
				]
			]
		];
	}

	/**
	 * @dataProvider getHostGroupsCancelData
	 */
	public function testPageHostGroups_Cancel($data) {
		$rows = [
			[
				'index' => 0,
				'count' => 1
			],
			[
				'index' => 1,
				'count' => 2
			],
			[
				'all' => true
			]
		];
		$old_grpups_hash = CDBHelper::getHash(self::GROUPS_SQL);
		$old_hosts_hash = CDBHelper::getHash(self::HOSTS_SQL);
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();

		foreach ($rows as $row) {
			if (array_key_exists('all', $row)) {
				$row['count'] = count($this->getGroupNames());
				$this->selectTableRows();
			}
			else {
				$table->getRow($row['index'])->select();
			}

			$this->assertSelectedCount($row['count']);
			$this->query('button', $data['action'])->one()->click();
			$this->assertEquals($data['message'], $this->page->getAlertText());
			$this->page->dismissAlert();
			$this->assertSelectedCount($row['count']);
		}

		$this->assertEquals($old_grpups_hash, CDBHelper::getHash(self::GROUPS_SQL));
		$this->assertEquals($old_hosts_hash, CDBHelper::getHash(self::HOSTS_SQL));
	}

	/**
	 * Check that status of host is changed in groups table when change host status in overlay dialog on host groups page.
	 */
	public function testPageHostGroups_SingleEnableDisable() {
		$data = [
			'group' => self::GROUP_DISABLED,
			// Group linked to two hosts, but only one host's status will change.
			'change_host' => self::HOST1,
			'host' => self::HOST2,
			'template' => self::TEMPLATE
		];
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();
		$hosts = $table->findRow('Name', $data['group'])->getColumn('Members');

		foreach ([true, false] as $status) {
			// Change host status.
			$hosts->query('link', $data['change_host'])->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$dialog->asForm()->fill(['Enabled' => $status])->submit();
			$dialog->ensureNotPresent();
			$table->waitUntilReloaded();
			$this->assertMessage(TEST_GOOD, 'Host updated', 'Updated status of host "'.$data['change_host'].'".');
			CMessageElement::find()->one()->close();

			// Check status in table.
			foreach ([$data['change_host'] => $status ? 'green' : 'red', $data['host'] => 'red',
					$data['template'] => 'grey'] as $host => $class) {
				$this->assertTrue($hosts->query('link', $host)->one()->hasClass($class));
			}

			// Check status in DB.
			$db_status = $status ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE status='.$db_status.
					' AND name='.CDBHelper::escape($data['change_host']))
			);
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE status='.HOST_STATUS_NOT_MONITORED.
					' AND name='.CDBHelper::escape($data['host']))
			);
		}
	}

	public static function getEnableHostsData() {
		return [
			[
				[
					'Discovered hosts' => ''
				]
			],
			[
				[
					'Group with disabled host testPageHostGroups' => ['Disabled host testPageHostGroups']
				]
			],
			[
				[
					'Group2 with disabled host testPageHostGroups' => ['Disabled host2 testPageHostGroups'],
					self::GROUP_DISABLED => [self::HOST1, self::HOST2]
				]
			]
		];
	}

	/**
	 * @dataProvider getEnableHostsData
	 */
	public function testPageHostGroups_EnableHosts($data) {
		$this->checkHostStatusChange($data);
	}

	public static function getDisableHostsData() {
		return [
			[
				[
					'Group for Host prototype' => ''
				]
			],
			[
				[
					'Group with enabled host testPageHostGroups' => ['Enabled host testPageHostGroups']
				]
			],
			[
				[
					self::LLD.': '.self::DISCOVERED_GROUP => [self::DISCOVERED_HOST],
					self::GROUP_ENABLED => ['One enabled host testPageHostGroups', 'Two enabled host testPageHostGroups']
				]
			]
		];
	}

	/**
	 * @dataProvider getDisableHostsData
	 */
	public function testPageHostGroups_DisableHosts($data) {
		$this->checkHostStatusChange($data, 'disable');
	}

	/**
	 * Check that hosts are enabled or disabled when performing an action on a host group.
	 *
	 * @param array $data     data provider
	 * @param string $status  enable or disable hosts
	 */
	private function checkHostStatusChange($data, $status = 'enable') {
		if (count($data) === 1 && array_values($data)[0] === '') {
			$old_hash = CDBHelper::getHash(self::HOSTS_SQL);
		}

		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();
		$this->selectTableRows(array_keys($data));
		$this->assertSelectedCount(count($data));
		$this->query('button', ucfirst($status).' hosts')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$table->waitUntilReloaded();
		$this->assertSelectedCount(0);

		$details = [];
		foreach ($data as $group => $hosts) {
			// Skip checks if group without hosts.
			if (!is_array($hosts)) {
				$this->assertEquals($old_hash, CDBHelper::getHash(self::HOSTS_SQL));
				continue;
			}
			$row = $table->findRow('Name', $group);

			// Check hosts status in DB.
			$db_status = ($status === 'enable') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
			$this->assertEquals(count($hosts), CDBHelper::getCount('SELECT NULL FROM hosts WHERE status='.
					$db_status.' AND name IN ('.CDBHelper::escape($hosts).')')
			);

			// Check template color on frontend.
			if ($group === self::GROUP_DISABLED) {
				$this->assertTrue($row->getColumn('Members')->query('link', self::TEMPLATE)->one()->hasClass('grey'));
			}

			foreach ($hosts as $host) {
				// Check hosts color on frontend.
				$host_link = $row->getColumn('Members')->query('link', $host)->one();
				$this->assertTrue($host_link->hasClass(($status === 'enable') ? 'green' : 'red'));

				// Prepare message details text.
				$details[] = 'Updated status of host "'.$host.'".';
			}
		}

		$message_title = (count($details) === 1) ? 'Host '.$status.'d' : 'Hosts '.$status.'d';
		$this->assertMessage(TEST_GOOD, $message_title, $details);
	}

	public static function getHostGroupsDeleteData() {
		return [
			// Delete all.
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Host group "Discovered hosts" is internal and cannot be deleted.'
				]
			],
			// One of the groups can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => [self::DELETE_ONE_HOST_GROUP, self::LLD.': '.self::DISCOVERED_GROUP],
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			// The group can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => self::DELETE_ONE_HOST_GROUP,
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'groups' => self::DELETE_ONE_TEMPLATE_GROUP,
					'error' => 'Template "Template for host group testing" cannot be without host group.'
				]
			],
			// Group used in other elements can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'Group for Maintenance',
					'error' => 'Cannot delete host group "Group for Maintenance" because maintenance'.
						' "Maintenance for host group testing" must contain at least one host or host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'Group for Correlation',
					'error' => 'Group "Group for Correlation" cannot be deleted, because it is used in a correlation condition.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'Group for Script',
					'error' => 'Host group "Group for Script" cannot be deleted, because it is used in a global script.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'Group for Host prototype',
					'error' => 'Group "Group for Host prototype" cannot be deleted, because it is used by a host prototype.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'groups' => 'Discovered hosts',
					'error' => 'Host group "Discovered hosts" is internal and cannot be deleted.'
				]
			],
			// Select one.
			[
				[
					'expected' => TEST_GOOD,
					'groups' => self::DELETE_EMPTY_GROUP
				]
			],
			// Select several.
			[
				[
					'expected' => TEST_GOOD,
					'groups' => [self::DELETE_GROUP2, self::DELETE_GROUP3, 'Group for Action']
				]
			]
		];
	}

	/**
	 * @dataProvider getHostGroupsDeleteData
	 */
	public function testPageHostGroups_Delete($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}

		if (!is_array(CTestArrayHelper::get($data, 'groups', []))) {
			$data['groups'] = [$data['groups']];
		}

		$all = $this->getGroupNames();
		$count = count(CTestArrayHelper::get($data, 'groups', $all));

		$this->page->login()->open(self::LINK)->waitUntilReady();
		$table = $this->getTable();
		$this->selectTableRows(CTestArrayHelper::get($data, 'groups'));
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$table->waitUntilReloaded();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertSelectedCount(0);
			$this->assertTableStats(count($all) - $count);
			$this->assertMessage(TEST_GOOD, (($count === 1) ? 'Group' : 'Groups').' deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE name IN ('.
					CDBHelper::escape($data['groups']).')')
			);
		}
		else {
			$this->assertSelectedCount($count);
			$this->assertMessage(TEST_BAD, 'Cannot delete group'.(($count > 1) ? 's' : ''), $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));

			// Reset selected groups.
			$this->query('button:Reset')->one()->click();
			$table->waitUntilReloaded();
			$this->assertTableStats(count($all));
		}
	}
}

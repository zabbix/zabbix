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


require_once dirname(__FILE__).'/../common/testPageGroups.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup hosts
 *
 * @onBefore preparePageHostGroupsData
 *
 * @dataSource DiscoveredHosts, HostTemplateGroups
 */
class testPageHostGroups extends testPageGroups {

	/**
	 * Attach TableBehavior to set column names and MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTableBehavior::class,
				'column_names' => ['', 'Name', 'Count', 'Hosts', 'Info']
			]
		];
	}

	protected $link = 'zabbix.php?action=hostgroup.list';
	protected $object = 'host';
	const DISCOVERED_HOST = 'Discovered host from prototype 1';
	const GROUP = 'Group with two disabled hosts testPageHostGroup';
	const HOST1 = 'One disabled host testPageHostGroup';
	const HOST2 = 'Two disabled host testPageHostGroup';

	/**
	 * Prepare data for enable/disable hosts test.
	 */
	public static function preparePageHostGroupsData() {
		// Create three groups with disabled hosts and two groups with enabled hosts for testing.
		CDataHelper::call('hostgroup.create', [
			[
				'name' => self::GROUP
			],
			[
				'name' => 'Group with disabled host testPageHostGroup'
			],
			[
				'name' => 'Group2 with disabled host testPageHostGroup'
			],
			[
				'name' => 'Group with enabled host testPageHostGroup'
			],
			[
				'name' => 'Group with two enabled hosts testPageHostGroup'
			],
			[
				'name' => 'Group 3 for Delete test'
			]
		]);
		$host_groupids = CDataHelper::getIds('name');

		CDataHelper::createHosts([
			[
				'host' => self::HOST1,
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $host_groupids[self::GROUP]
				]
			],
			[
				'host' => self::HOST2,
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $host_groupids[self::GROUP]
				]
			],
			[
				'host' => 'Disabled host testPageHostGroup',
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $host_groupids['Group with disabled host testPageHostGroup']
				]
			],
			[
				'host' => 'Disabled host2 testPageHostGroup',
				'interfaces' => [],
				'status' => HOST_STATUS_NOT_MONITORED,
				'groups' => [
					'groupid' => $host_groupids['Group2 with disabled host testPageHostGroup']
				]
			],
			[
				'host' => 'Enabled host testPageHostGroup',
				'interfaces' => [],
				'groups' => [
					'groupid' => $host_groupids['Group with enabled host testPageHostGroup']
				]
			],
			[
				'host' => 'One enabled host testPageHostGroup',
				'interfaces' => [],
				'groups' => [
					'groupid' => $host_groupids['Group with two enabled hosts testPageHostGroup']
				]
			],
			[
				'host' => 'Two enabled host testPageHostGroup',
				'interfaces' => [],
				'groups' => [
					'groupid' => $host_groupids['Group with two enabled hosts testPageHostGroup']
				]
			]
		]);
	}

	public static function getLayoutData() {
		return [
			[
				[
					[
						'Name' => 'Applications',
						'Count' => '',
						'Hosts' => '',
						'Info' => ''
					],
					[
						'Name' => self::DELETE_ONE_GROUP,
						'Count' => '1',
						'Hosts' => 'Host for host group testing',
						'Info' => ''
					],
					[
						'Name' => self::GROUP,
						'Count' => '2',
						'Hosts' => self::HOST1.', '.self::HOST2,
						'Info' => ''
					],
					[
						'Name' => self::LLD.': '.self::DISCOVERED_GROUP,
						'Count' => '1',
						'Hosts' => self::DISCOVERED_HOST,
						'Info' => ''
					],
					[
						'Name' => '1st LLD, ..., sixth LLD: グループプロトタイプ番号 1 KEY',
						'Count' => '6',
						'Hosts' => 'KEY host prototype number 0, KEY host prototype number 1, KEY host prototype number 2,'.
								' KEY host prototype number 3, KEY host prototype number 4, KEY host prototype number 5',
						'Info' => ''
					],
					[
						'Name' => 'LLD number 8, ..., sevenths LLD: Trešais grupu prototips KEY',
						'Count' => '3',
						'Hosts' => 'KEY host prototype number 6, KEY host prototype number 7, KEY host prototype number 8',
						'Info' => ''
					],
					[
						'Name' => '15th LLD 🙃^天!, 16th LLD: Two prototype group KEY',
						'Count' => '2',
						'Hosts' => 'KEY host prototype number 14, KEY host prototype number 15',
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
		$links = [
			'name' => self::DISCOVERED_GROUP,
			'lld' => self::LLD,
			'count' => '1',
			'host_template' => self::DISCOVERED_HOST
		];
		$this->checkLayout($data, $links);
	}

	public function testPageHostGroups_Sort() {
		$this->checkColumnSorting();
	}

	public static function getHostGroupsFilterData() {
		return [
			// Too many spaces in field.
			[
				[
					'Name' => '  host'
				]
			],
			[
				[
					'Name' => 'host  '
				]
			],
			// Template group name.
			[
				[
					'Name' => 'Templates'
				]
			],
			// Exact match.
			[
				[
					'Name' => 'Group with disabled host testPageHostGroup',
					'expected' => ['Group with disabled host testPageHostGroup']
				]
			],
			// Partial match.
			[
				[
					'Name' => 'with two enabled hosts',
					'expected' => ['Group with two enabled hosts testPageHostGroup']
				]
			],
			[
				[
					'Name' => ' enabled ',
					'expected' => ['Group with enabled host testPageHostGroup',
						'Group with two enabled hosts testPageHostGroup'
					]
				]
			],
			[
				[
					'Name' => 'with disabled',
					'expected' => ['Group2 with disabled host testPageHostGroup', 'Group with disabled host testPageHostGroup']
				]
			],
			// Not case sensitive.
			[
				[
					'Name' => 'group2',
					'expected' => ['Group2 with disabled host testPageHostGroup']
				]
			],
			[
				[
					'Name' => 'GROUP2',
					'expected' => ['Group2 with disabled host testPageHostGroup']
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 * @dataProvider getHostGroupsFilterData
	 */
	public function testPageHostGroups_Filter($data) {
		$this->filter($data);
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
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 * @dataProvider getHostGroupsCancelData
	 */
	public function testPageHostGroups_Cancel($data) {
		$this->cancel($data);
	}

	/**
	 * Check that status of host is changed in groups table when change host status in overlay dialog on host group page.
	 */
	public function testPageHostGroups_SingleEnableDisable() {
		$data = [
			'group' => self::GROUP,
			// Group linked to two hosts, but only one host's status will change.
			'change_host' => self::HOST1,
			'host' => self::HOST2
		];
		$this->page->login()->open($this->link)->waitUntilReady();
		$table = $this->getTable();
		$hosts = $table->findRow('Name', $data['group'])->getColumn('Hosts');

		foreach ([true, false] as $status) {
			// Change host status.
			$hosts->query('link', $data['change_host'])->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$dialog->asForm()->fill(['Enabled' => $status])->submit();
			$dialog->ensureNotPresent();

			// Check status in table.
			$table->waitUntilReloaded();
			$this->assertMessage(TEST_GOOD, 'Host updated', 'Updated status of host "'.$data['change_host'].'".');
			CMessageElement::find()->one()->close();
			$this->assertTrue($hosts->query('link', $data['change_host'])->one()->hasClass($status ? 'green' : 'red'));
			$this->assertTrue($hosts->query('link', $data['host'])->one()->hasClass('red'));

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
					'Applications' => ''
				]
			],
			[
				[
					'Group with disabled host testPageHostGroup' => ['Disabled host testPageHostGroup']
				]
			],
			[
				[
					'Group2 with disabled host testPageHostGroup' => ['Disabled host2 testPageHostGroup'],
					self::GROUP => [self::HOST1, self::HOST2]
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
					'Group with enabled host testPageHostGroup' => ['Enabled host testPageHostGroup']
				]
			],
			[
				[
					self::LLD.': '.self::DISCOVERED_GROUP => [self::DISCOVERED_HOST],
					'Group with two enabled hosts testPageHostGroup' => ['One enabled host testPageHostGroup',
						'Two enabled host testPageHostGroup']
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

	private function checkHostStatusChange($data, $status = 'enable') {
		if (count($data) === 1 && array_values($data)[0] === '') {
			$old_hash = CDBHelper::getHash(self::HOSTS_SQL);
		}

		$this->page->login()->open($this->link)->waitUntilReady();
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

			foreach ($hosts as $host) {
				// Check hosts color on frontend.
				$host_link = $row->getColumn('Hosts')->query('link', $host)->one();
				$this->assertTrue($host_link->hasClass(($status === 'enable') ? 'green' : 'red'));

				// Prepare message details text.
				$details[] = 'Updated status of host "'.$host.'".';
			}
		}

		$message_title = 'Host'.(count($details) === 1 ? ' ' : 's ').$status.'d';
		$this->assertMessage(TEST_GOOD, $message_title, $details);
	}

	public static function getHostGroupsDeleteData() {
		return [
			// Delete all.
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Host group "Discovered hosts" is group for discovered hosts and cannot be deleted.'
				]
			],
			// One of the groups can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => [self::DELETE_ONE_GROUP, self::LLD.': '.self::DISCOVERED_GROUP],
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			// The group can't be deleted.
			[
				[
					'expected' => TEST_BAD,
					'groups' => self::DELETE_ONE_GROUP,
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
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
					'error' => 'Host group "Discovered hosts" is group for discovered hosts and cannot be deleted.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 * @dataProvider getHostGroupsDeleteData
	 */
	public function testPageHostGroups_Delete($data) {
		$this->delete($data);
	}

	public static function getLLDLinksData() {
		return [
			[
				[
					'name' => 'Single prototype group KEY',
					'links' => ['17th LLD']
				]
			],
			[
				[
					'name' => 'Two prototype group KEY',
					'links' => ['15th LLD 🙃^天!', '16th LLD']
				]
			],
			[
				[
					'name' => 'Trešais grupu prototips KEY',
					'links' => ['LLD number 8', 'sevenths LLD'],
					'ellipsis' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getLLDLinksData
	 */
	public function testPageHostGroups_CheckLLDLinks($data) {
		$link_ids = CDataHelper::get('HostTemplateGroups.lld_host_prototype_ids');
		$this->page->login()->open($this->link)->waitUntilReady();

		// Locate the table cell that corresponds to the desired hostgroup name.
		$table_cell = $this->query('link', $data['name'])->one()->parents('class:nowrap')->one();

		foreach ($data['links'] as $lld_name) {
			$link = $table_cell->query('link', $lld_name)->one();
			$this->assertTrue($link->isClickable());

			$link_url = 'host_prototypes.php?form=update&parent_discoveryid='.$link_ids[$lld_name]['lld_id'].'&hostid='.
					$link_ids[$lld_name]['host_prototype_id'].'&context=host';
			$this->assertEquals($link_url, $link->getAttribute('href'));
		}

		// Check that 3 dots are added after 1st LLD name, if there's more than 2 parent LLDs and no more LLDs are shown.
		$name_parts = preg_split('/(,|:) /', $table_cell->getText());

		if (array_key_exists('ellipsis', $data)) {
			$this->assertEquals([$data['links'][0], '...', $data['links'][1], $data['name']], $name_parts);
		}
		else {
			array_push($data['links'], $data['name']);
			$this->assertEquals($data['links'], $name_parts);
		}
	}
}

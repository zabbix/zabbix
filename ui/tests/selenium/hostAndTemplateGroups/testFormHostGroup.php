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


require_once __DIR__.'/../common/testFormGroups.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGroupData
 *
 * @dataSource DiscoveredHosts, HostTemplateGroups
 */
class testFormHostGroup extends testFormGroups {

	protected $link = 'zabbix.php?action=hostgroup.list';
	protected $object = 'host';
	protected static $update_group = 'Group for Update test';

	public function testFormHostGroup_Layout() {
		$this->layout('Zabbix servers');
	}

	public function testFormHostGroup_DiscoveredLayout() {
		$this->layout(self::DISCOVERED_GROUP, true);
	}

	public static function getHostValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => self::DISCOVERED_GROUP
					],
					'error' => 'Host group "'.self::DISCOVERED_GROUP.'" already exists.'
				]
			]
		];
	}

	public static function getHostCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Zabbix servers'
					],
					'error' => 'Host group "Zabbix servers" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Templates'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => STRING_255
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 * @dataProvider getHostValidationData
	 * @dataProvider getHostCreateData
	 */
	public function testFormHostGroup_Create($data) {
		$this->checkForm($data, 'create');
	}

	public static function getHostUpdateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Zabbix servers',
						'Apply permissions and tag filters to all subgroups' => true
					],
					'error' => 'Host group "Zabbix servers" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Templates/Applications'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => str_repeat('long_', 51)
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 * @dataProvider getHostValidationData
	 * @dataProvider getHostUpdateData
	 */
	public function testFormHostGroup_Update($data) {
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormHostGroup_SimpleUpdate() {
		$this->simpleUpdate(self::DISCOVERED_GROUP, true);
	}

	public static function getHostCloneData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DISCOVERED_GROUP,
					'fields' => [
						'Group name' => self::DISCOVERED_GROUP.' cloned group'
					],
					'discovered' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 * @dataProvider getHostCloneData
	 */
	public function testFormHostGroup_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostGroup_Cancel($data) {
		$this->cancel($data);
	}

	public static function getHostDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_ONE_GROUP,
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Maintenance',
					'error' => 'Cannot delete host group "Group for Maintenance" because maintenance'.
						' "Maintenance for host group testing" must contain at least one host or host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Correlation',
					'error' => 'Group "Group for Correlation" cannot be deleted, because it is used in a correlation condition.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Script',
					'error' => 'Host group "Group for Script" cannot be deleted, because it is used in a global script.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Host prototype',
					'error' => 'Group "Group for Host prototype" cannot be deleted, because it is used by a host prototype.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovered hosts',
					'error' => 'Host group "Discovered hosts" is group for discovered hosts and cannot be deleted.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 * @dataProvider getHostDeleteData
	 */
	public function testFormHostGroup_Delete($data) {
		$this->delete($data);
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgroupsData
	 */
	public function testFormHostGroup_ApplyPermissionsToSubgroups($data) {
		$this->checkSubgroupsPermissions($data);
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
					'name' => 'グループプロトタイプ番号 1 KEY',
					'links' => ['1st LLD', '2nd LLD', '3rd LLD', 'fifth LLD', 'forth LLD'],
					'ellipsis' => true
				]
			],
			[
				[
					'name' => '5 prototype group KEY',
					'links' => ['12th LLD', 'Eleventh LLD', 'Mūsu desmitais LLD', 'Trīspadsmitais LLD', 'Četrpadsmitais LLD']
				]
			],
			[
				[
					'name' => 'Trešais grupu prototips KEY',
					'links' => ['LLD number 8', 'LLD 🙂🙃 !@#$%^&*()_+ 祝你今天过得愉快', 'sevenths LLD']
				]
			]
		];
	}

	/**
	 * @dataProvider getLLDLinksData
	 */
	public function testFormHostGroup_CheckLLDLinks($data) {
		$link_ids = CDataHelper::get('HostTemplateGroups.lld_host_prototype_ids');

		$this->page->login()->open($this->link)->waitUntilReady();
		$this->query('link', $data['name'])->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$discovered_by = $dialog->asForm()->getField('Discovered by');

		foreach ($data['links'] as $lld_name) {
			$link = $discovered_by->query('link', $lld_name)->one();
			$this->assertTrue($link->isClickable());

			$link_url = 'host_prototypes.php?form=update&parent_discoveryid='.$link_ids[$lld_name]['lld_id'].'&hostid='.
					$link_ids[$lld_name]['host_prototype_id'].'&context=host';
			$this->assertEquals($link_url, $link->getAttribute('href'));
		}

		// Check that three dots are added after the 5th LLD name, if there are more than 5 parent LLDs.
		if (CTestArrayHelper::get($data, 'ellipsis')) {
			array_push($data['links'], '...');
		}

		$this->assertEquals($data['links'], explode(', ', $discovered_by->getText()));
		$dialog->close();
	}
}

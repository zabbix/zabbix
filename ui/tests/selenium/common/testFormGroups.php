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
 * Base class for Host group form.
 */
class testFormGroups extends CWebTest {

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

	/**
	 * Objects created in dataSource DiscoveredHosts.
	 */
	const DISCOVERED_GROUP = 'Group created from host prototype 1';
	const HOST_PROTOTYPE = 'Host created from host prototype {#KEY}';
	const LLD = 'LLD for Discovered host tests';

	/**
	 * Objects created in dataSource HostGroups.
	 */
	const DELETE_GROUP = 'Group empty for Delete test';
	const DELETE_ONE_HOST_GROUP = 'One group belongs to one host for Delete test';
	const DELETE_ONE_TEMPLATE_GROUP = 'One group belongs to one template for Delete test';
	const DELETE_GROUP2 = 'First group to one object for Delete test';

	/**
	 * Host and template subgroup name for clone test scenario.
	 */
	const SUBGROUP = 'Group1/Subgroup1/Subgroup2';

	/**
	 * SQL query to get groups to compare hash values.
	 */
	const GROUPS_SQL = 'SELECT * FROM hstgrp g INNER JOIN hosts_groups hg ON g.groupid=hg.groupid'.
			' ORDER BY g.groupid, hg.hostgroupid';

	/**
	 * SQL query to get user group permissions for host groups to compare hash values.
	 */
	const PERMISSION_SQL = 'SELECT * FROM rights ORDER BY rightid';

	/**
	 * Link to page for opening group form.
	 */
	protected $link;

	/**
	 * Group form check on search page.
	 */
	protected $search = false;

	/**
	 * Host group name for update and delete test scenario.
	 */
	protected static $update_group;

	/**
	 * User group ID for subgroup permissions scenario.
	 */
	protected static $user_groupid;

	public static function prepareGroupData() {
		// Prepare data for host groups.
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group for Update test'
			],
			[
				'name' => 'Group1/Subgroup1/Subgroup2'
			]
		]);
	}

	/**
	 * Test for checking group form layout.
	 *
	 * @param string  $name        group name
	 * @param boolean $discovered  discovered host group or not
	 */
	public function layout($name, $discovered = false) {
		// Open group from groups list and check existing group form.
		$form = $this->openForm($name, $discovered, $list = true);
		$this->page->assertHeader('Host groups');
		$this->page->assertTitle('Configuration of host groups');
		$this->assertTrue($form->isRequired('Group name'));

		if ($discovered) {
			$this->assertEquals(['Discovered by', 'Group name', 'Apply permissions and tag filters to all subgroups'],
					$form->getLabels(CElementFilter::VISIBLE)->asText()
			);
			$this->assertTrue($form->getField('Group name')->isAttributePresent('readonly'));
			$this->assertEquals(self::LLD, $form->getField('Discovered by')->query('tag:a')->one()->getText());
			$form->query('link', self::LLD)->one()->click();
			$this->page->assertHeader('Host prototypes');
			$this->query('id:host')->one()->checkValue(self::HOST_PROTOTYPE);

			return;
		}

		$form->checkValue(['Group name' => $name]);
		$this->assertEquals(['Update', 'Clone', 'Delete','Cancel'], $form->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
		$this->assertEquals(['Group name', 'Apply permissions and tag filters to all subgroups'],
				$form->getLabels(CElementFilter::VISIBLE)->asText()
		);

		// There is no group creation on the search page.
		if ($this->search) {
			return;
		}

		// Check new group form.
		$this->page->open('hostgroups.php')->waitUntilReady();
		$this->query('button:Create host group')->one()->click();
		$this->page->assertHeader('Host groups');

		$form->invalidate();
		$this->assertTrue($form->getField('Group name')->isAttributePresent(['maxlength' => '255', 'value' => '']));
		$this->assertTrue($form->isRequired('Group name'));
		$this->assertEquals(['Group name'], $form->getLabels(CElementFilter::VISIBLE)->asText());
		$this->assertEquals(['Add', 'Cancel'], $form->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText());
		$form->query('button:Cancel')->one()->click();

		$form->waitUntilNotVisible();
		$this->assertEquals(PHPUNIT_URL.'hostgroups.php?cancel=1', $this->page->getCurrentUrl());
	}

	/**
	 * Function for opening group form.
	 *
	 * @param string  $name        group name to open
	 * @param boolean $discovered  discovered host group or not
	 * @param boolean $list        open group by name from list or by direct link
	 *
	 * @return CForm
	 */
	public function openForm($name = null, $discovered= false, $list = false) {
		// Open group from groups list.
		if ($this->search || $list) {
			$this->page->login()->open($this->search? $this->link : 'hostgroups.php')->waitUntilReady();
			$column_name = $this->search ? 'Host group' : 'Name';
			$table_selector = $this->search ? 'xpath://div[@id="search_hostgroup"]//table' : 'class:list-table';

			$table = $this->query($table_selector)->asTable()->one();
			$table->findRow($column_name, ($discovered && !$this->search) ? self::LLD.': '.$name : $name)
					->getColumn($column_name)->query('link', $name)->one()->click();
		}
		else {
			// Open group form by direct link.
			if ($name) {
				$groupid = CDBHelper::getValue('SELECT groupid FROM hstgrp WHERE name='.zbx_dbstr($name));
			}
			$this->page->login()->open($name ? $this->link.$groupid : 'hostgroups.php?form=create')->waitUntilReady();
		}

		return $this->query('name:hostgroupForm')->asForm()->waitUntilVisible()->one();
	}

	public static function getCreateData() {
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
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Templates'
					],
					'error' => 'Host group "Templates" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => self::DISCOVERED_GROUP
					],
					'error' => 'Host group "'.self::DISCOVERED_GROUP.'" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'message' => 'Page received incorrect data',
					'error' => 'Incorrect value for field "Group name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => ' '
					],
					'message' => 'Page received incorrect data',
					'error' => 'Incorrect value for field "Group name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Test/Test/'
					],
					'error' => 'Invalid parameter "/1/name": invalid host group name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Test/Test\/'
					],
					'error' => 'Invalid parameter "/1/name": invalid host group name.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => '~!@#$%^&*()_+=[]{}null{$A}{#B}'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'æ㓴🙂'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => '   trim    '
					],
					'trim' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Group/Subgroup1/Subgroup2'
					]
				]
			]
		];
	}

	public function getUpdateData() {
		$data = [];

		// Add 'update' word to group name and change group name in test case with trim.
		foreach ($this->getCreateData() as $group) {
			if ($group[0]['expected'] === TEST_GOOD) {
				$group[0]['fields']['Group name'] = CTestArrayHelper::get($group[0], 'trim', false)
					? '   trim update    '
					: $group[0]['fields']['Group name'].'update';
			}

			$data[] = $group;
		}

		return $data;
	}

	/**
	 * Test for checking group creation and update.
	 *
	 * @param array  $data    data provider
	 * @param ctring $action  create or update action
	 */
	protected function checkForm($data, $action) {
		$good_message = 'Group '.(($action === 'create') ? 'added' : 'updated');
		$bad_message = CTestArrayHelper::get($data, 'message',
				'Cannot '.(($action === 'create') ? 'add' : 'update').' group'
		);

		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
			$permission_old_hash = CDBHelper::getHash(self::PERMISSION_SQL);
		}

		$form = $this->openForm(($action === 'update') ? static::$update_group : null);
		$form->fill(CTestArrayHelper::get($data, 'fields', []));

		// Clear name for update scenario.
		if ($action === 'update' && !CTestArrayHelper::get($data, 'fields', false)) {
			$form->getField('Group name')->clear();
		}

		$form->submit();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, $good_message);

			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields']['Group name'] = trim($data['fields']['Group name']);
			}

			$form = $this->openForm($data['fields']['Group name']);
			$form->checkValue($data['fields']['Group name']);

			// Change group name after successful update scenario.
			if ($action === 'update') {
				static::$update_group = $data['fields']['Group name'];
			}
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
			$this->assertEquals($permission_old_hash, CDBHelper::getHash(self::PERMISSION_SQL));
			$this->assertMessage(TEST_BAD, $bad_message, $data['error']);
		}
	}

	/**
	 * Update group without changing data.
	 *
	 * @param string $name  group name to be opened for check
	 */
	public function simpleUpdate($name) {
		$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		$form = $this->openForm($name);
		$values = $form->getValues();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Group updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));

		// Check form values.
		$this->openForm($name);
		$form->invalidate();
		$this->assertEquals($values, $form->getValues());
	}

	public static function getCloneData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::SUBGROUP,
					'error' => 'Host group "'.self::SUBGROUP.'" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP,
					'fields'  => [
						'Group name' => microtime().' cloned group'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::SUBGROUP,
					'fields'  => [
						'Group name' => microtime().'/cloned/subgroup'
					]
				]
			],
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

	public function clone($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}

		$form = $this->openForm($data['name'], CTestArrayHelper::get($data, 'discovered', false));
		$form->query('button:Clone')->one()->waitUntilClickable()->click();

		// Check that the group creation form is open after cloning.
		$this->page->assertHeader('Host groups');
		$this->assertEquals(PHPUNIT_URL.'hostgroups.php', $this->page->getCurrentUrl());
		$form->invalidate();
		$this->assertEquals(['Add', 'Cancel'], $form->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText());
		$form->fill(CTestArrayHelper::get($data, 'fields', []));
		$form->submit();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Group added');
			$this->assertEquals(PHPUNIT_URL.'hostgroups.php', $this->page->getCurrentUrl());

			$form = $this->openForm($data['fields']['Group name']);
			$form->checkValue($data['fields']['Group name']);


			$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE name IN ('.
					zbx_dbstr($data['name']).', '.zbx_dbstr($data['fields']['Group name']).')')
			);
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
			$this->assertMessage(TEST_BAD, 'Cannot add group', $data['error']);
		}
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'Add'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			]
		];
	}

	/**
	 * Test for checking group actions cancelling.
	 *
	 * @param array $data  data provider with fields values
	 */
	public function cancel($data) {
		// There is no group creation on the search page.
		if ($this->search && $data['action'] === 'Add') {
			return;
		}

		$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		$new_name = microtime(true).' Cancel '.self::DELETE_GROUP;
		$form = $this->openForm(($data['action'] === 'Add') ? null : self::DELETE_GROUP);

		// Change name.
		$form->fill(['Group name' => $new_name]);

		if (in_array($data['action'], ['Clone', 'Delete'])) {
			$form->query('button', $data['action'])->one()->click();
		}

		if ($data['action'] === 'Delete') {
			$this->page->dismissAlert();
		}

		$form->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->assertHeader('Host groups');
		$this->assertEquals(PHPUNIT_URL.'hostgroups.php?cancel=1', $this->page->getCurrentUrl());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
	}

	public static function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_ONE_HOST_GROUP,
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_ONE_TEMPLATE_GROUP,
					'error' => 'Template "Template for host group testing" cannot be without host group.'
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
					'error' => 'Host group "Discovered hosts" is internal and cannot be deleted.'
				]
			],
			// Empty group without Host/Template.
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP
				]
			],
			// Host has two groups, one of them can be deleted.
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP2
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Group for Action'
				]
			]
		];
	}

	/**
	 * Test for checking group deletion.
	 *
	 * @param array $data   data provider
	 */
	public function delete($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}

		$form = $this->openForm($data['name']);
		$form->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected group?', $this->page->getAlertText());
		$this->page->acceptAlert();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Group deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE name='.zbx_dbstr($data['name'])));
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
			$this->assertMessage(TEST_BAD, 'Cannot delete group', $data['error']);
		}
	}

	public static function prepareSubgroupData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Europe'
			],
			[
				'name' => 'Europe/Latvia'
			],
			[
				'name' => 'Europe/Latvia/Riga/Zabbix'
			],
			[
				'name' => 'Europe/Test'
			],
			[
				'name' => 'Europe/Test/Zabbix'
			],
			// Groups to check inherited permissions when creating a parent or subgroup.
			[
				'name' => 'Streets'
			],
			[
				'name' => 'Cities/Cesis'
			],
			[
				'name' => 'Europe group for test on search page'
			]
		]);
		$host_groupids = CDataHelper::getIds('name');

		$response = CDataHelper::call('usergroup.create', [
			[
				'name' => 'User group to check subgroup permissions',
				'rights' => [
					[
						'permission' => PERM_DENY,
						'id' => $host_groupids['Europe']
					],
					[
						'permission' => PERM_READ,
						'id' => $host_groupids['Europe/Latvia']
					],
					[
						'permission' => PERM_READ_WRITE,
						'id' => $host_groupids['Europe/Test']
					],
					[
						'permission' => PERM_DENY,
						'id' => $host_groupids['Streets']
					],
					[
						'permission' => PERM_READ,
						'id' => $host_groupids['Cities/Cesis']
					]
				],
				'tag_filters' => [
					[
						'groupid' => $host_groupids['Europe'],
						'tag' => 'world',
						'value' => ''
					],
					[
						'groupid' => $host_groupids['Europe/Test'],
						'tag' => 'country',
						'value' => 'test'
					],
					[
						'groupid' => $host_groupids['Streets'],
						'tag' => 'street',
						'value' => ''
					],
					[
						'groupid' => $host_groupids['Cities/Cesis'],
						'tag' => 'city',
						'value' => 'Cesis'
					]
				]
			]
		]);
		self::$user_groupid = $response['usrgrpids'][0];
	}

	public static function getSubgoupsData() {
		return [
			[
				[
					'apply_permissions' => 'Europe/Test',
					'create' => 'Cities',
					// "groups_before" and "tags_before" parameters aren't used in test, but they are listed here for test clarity.
					'groups_before' => [
						'All groups' => 'None',
						'Cities/Cesis' => 'Read',
						'Europe' =>	'Deny',
						'Europe/Latvia' => 'Read',
						'Europe/Latvia/Riga/Zabbix' => 'None',
						'Europe/Test' => 'Read-write',
						'Europe/Test/Zabbix' => 'None',
						'Streets' => 'Deny'
					],
					'tags_before' => [
						['Host group' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host group' => 'Europe', 'Tags' => 'world'],
						['Host group' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host group' => 'Streets', 'Tags' => 'street']
					],
					'groups_after' => [
						'Cities/Cesis' => 'Read',
						'Europe' =>	'Deny',
						'Europe/Latvia' => 'Read',
						'Europe/Latvia/Riga/Zabbix' => 'None',
						'Europe/Test (including subgroups)' => 'Read-write',
						'Streets' => 'Deny'
					],
					'tags_after' => [
						['Host group' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host group' => 'Europe' , 'Tags' => 'world'],
						['Host group' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host group' => 'Europe/Test/Zabbix', 'Tags' => 'country: test'],
						['Host group' => 'Streets', 'Tags' => 'street']
					]
				]
			],
			[
				[
					'apply_permissions' => 'Europe',
					'create' => 'Streets/Dzelzavas',
					'groups_before' => [
						'All groups' => 'None',
						'Cities/Cesis' => 'Read',
						'Europe' =>	'Deny',
						'Europe/Latvia (including subgroups)' => 'Read',
						'Europe/Test (including subgroups)' => 'Read-write',
						'Streets' => 'Deny'
					],
					'tags_before' => [
						['Host group' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host group' => 'Europe', 'Tags' => 'world'],
						['Host group' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host group' => 'Europe/Test/Zabbix', 'Tags' => 'country: test'],
						['Host group' => 'Streets', 'Tags' => 'street']
					],
					'groups_after' => [
						'Cities/Cesis' => 'Read',
						'Europe (including subgroups)' => 'Deny',
						'Streets (including subgroups)' => 'Deny'
					],
					'tags_after' => [
						['Host group' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host group' => 'Europe', 'Tags' => 'world'],
						['Host group' => 'Europe/Latvia', 'Tags' => 'world'],
						['Host group' => 'Europe/Latvia/Riga/Zabbix', 'Tags' => 'world'],
						['Host group' => 'Europe/Test', 'Tags' => 'world'],
						['Host group' => 'Europe/Test/Zabbix', 'Tags' => 'world'],
						['Host group' => 'Streets', 'Tags' => 'street'],
						['Host group' => 'Streets/Dzelzavas', 'Tags' => 'street']
					]
				]
			]
		];
	}

	/**
	 * Apply the same level of permissions/tag filters to all nested host groups.
	 *
	 * @param array $data  data provider
	 */
	public function checkSubgroupsPermissions($data) {
		// Prepare groups array according framework function assertTableData().
		$selector = 'xpath:.//input[@checked]/following-sibling::label';
		$groups = [];
		foreach ($data['groups_after'] as $group => $permissions) {
			$groups[] = [
				'Host group' => $group,
				'Permissions' => [
					'text' => $permissions,
					'selector' => $selector
				]
			];
		}
		$data['groups_after'] = $groups;
		array_unshift($data['groups_after'], ['Host group' => 'All groups', 'Permissions' => 'None']);

		// Create new parent or subgroup to check nested permissions.
		if (array_key_exists('create', $data)) {
			$form = $this->openForm(CTestArrayHelper::get($data, 'open_form', null));
			$form->fill(['Group name' => $data['create']]);
			$form->submit();
			// Permission inheritance doesn't apply when changing the name of existing group, only when creating a new group.
			$this->assertMessage(TEST_GOOD, 'Group '.(array_key_exists('open_form', $data) ? 'updated' : 'added')
			);
		}

		// Apply permissions to subgroups.
		$form = $this->openForm($data['apply_permissions']);
		$form->fill(['Apply permissions and tag filters to all subgroups' => true]);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Group updated');

		// Check group and tag permissions in user group.
		$this->page->open('zabbix.php?action=usergroup.edit&usrgrpid='.self::$user_groupid)->waitUntilReady();
		$group_form = $this->query('id:user-group-form')->asForm()->one();
		$group_form->selectTab('Permissions');
		$this->assertTableData($data['groups_after'], 'id:group-right-table');
		$group_form->selectTab('Tag filter');
		$this->assertTableData($data['tags_after'], 'id:tag-filter-table');
	}
}

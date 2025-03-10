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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * Base class for Host and Template group form.
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
	 * Objects created in dataSource HostTemplateGroups.
	 */
	const DELETE_ONE_GROUP = 'One group belongs to one object for Delete test';
	const DELETE_GROUP = 'Group empty for Delete test';
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
	 * SQL query to get user group permissions for template and host groups to compare hash values.
	 */
	const PERMISSION_SQL = 'SELECT * FROM rights ORDER BY rightid';

	/**
	 * Link to page for opening group form.
	 */
	protected $link;

	/**
	 * Host or template group.
	 */
	protected $object;

	/**
	 * Group form check on search page.
	 */
	protected $search = false;

	/**
	 * Host and template group name for update test scenario.
	 */
	protected static $update_group;

	/**
	 * User group ID for subgroup permissions scenario.
	 */
	protected static $user_groupid;

	public static function prepareGroupData() {
		// Prepare data for template groups.
		CDataHelper::call('templategroup.create', [
			[
				'name' => 'Group for Update test'
			],
			[
				'name' => 'Templates/Update'
			],
			[
				'name' => 'Group1/Subgroup1/Subgroup2'
			]
		]);

		// Prepare data for host groups.
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group for Update test'
			],
			[
				'name' => 'Hosts/Update'
			],
			[
				'name' => 'Group1/Subgroup1/Subgroup2'
			]
		]);
	}

	/**
	 * Test for checking group form layout.
	 *
	 * @param string  $name        host or template group name
	 * @param boolean $discovered  discovered host group or not
	 */
	public function layout($name, $discovered = false) {
		// Check existing group form.
		$form = $this->openForm($name, $discovered);
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals(ucfirst($this->object).' group', $dialog->getTitle());
		$footer = $dialog->getFooter();

		$this->assertTrue($form->isRequired('Group name'));
		$form->checkValue(['Group name' => $name]);
		$this->assertEquals(['Update', 'Clone', 'Delete','Cancel'], $footer->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		if ($discovered) {
			$this->assertEquals(['Discovered by', 'Group name', 'Apply permissions and tag filters to all subgroups'],
					$form->getLabels(CElementFilter::VISIBLE)->asText()
			);
			$this->assertTrue($form->getField('Group name')->isAttributePresent('readonly'));
			$this->assertEquals(self::LLD, $form->getField('Discovered by')->query('tag:a')->one()->getText());
			$form->query('link', self::LLD)->one()->click();
			// TODO: temporarily commented out due webdriver issue #351858989, alert is not displayed while leaving page during test execution
//			$this->page->acceptAlert();
//			$this->page->waitUntilReady();
			$this->page->assertHeader('Host prototypes');
			$this->query('id:host')->one()->checkValue(self::HOST_PROTOTYPE);

			return;
		}

		$this->assertEquals(['Group name', ($this->object === 'host')
			? 'Apply permissions and tag filters to all subgroups'
			: 'Apply permissions to all subgroups'],
				$form->getLabels(CElementFilter::VISIBLE)->asText()
		);

		// There is no group creation on the search page.
		if ($this->search) {
			$dialog->close();

			return;
		}

		$dialog->close();

		// Open group create form.
		$this->query('button', 'Create '.$this->object.' group')->one()->click();
		$this->assertEquals('New '.$this->object.' group', $dialog->waitUntilReady()->getTitle());

		$form->invalidate();
		$this->assertTrue($form->getField('Group name')->isAttributePresent(['maxlength' => '255', 'value' => '']));
		$this->assertTrue($form->isRequired('Group name'));
		$this->assertEquals(['Group name'], $form->getLabels(CElementFilter::VISIBLE)->asText());
		$this->assertEquals(['Add', 'Cancel'], $footer->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText());
		$footer->query('button:Cancel')->one()->click();
		$dialog->ensureNotPresent();
	}

	/**
	 * Function for opening group form.
	 *
	 * @param string  $name        host or template group name to open
	 * @param boolean $discovered  discovered host group or not
	 *
	 * @return CForm
	 */
	public function openForm($name = null, $discovered = false) {
		$this->page->login()->open($this->link)->waitUntilReady();

		if ($name) {
			$column_name = $this->search ? ucfirst($this->object).' group' : 'Name';
			$table_selector = $this->search ? 'xpath://section[@id="search_'.$this->object.'group"]//table' : 'class:list-table';
			$table = $this->query($table_selector)->asTable()->one();
			$table->findRow($column_name, ($discovered && !$this->search) ? self::LLD.': '.$name : $name)
					->getColumn($column_name)->query('link', $name)->one()->click();
		}
		else {
			$this->query('button', 'Create '.$this->object.' group')->one()->click();
		}

		return COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
	}

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => ' '
					],
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Test/Test/'
					],
					'error' => 'Invalid parameter "/1/name": invalid host group name.',
					'template_error' => 'Invalid parameter "/1/name": invalid template group name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Test/Test\/'
					],
					'error' => 'Invalid parameter "/1/name": invalid host group name.',
					'template_error' => 'Invalid parameter "/1/name": invalid template group name.'
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
	 * @param string $action  create or update action
	 */
	protected function checkForm($data, $action) {
		$good_message = ucfirst($this->object).' group '.(($action === 'create') ? 'added' : 'updated');
		$bad_message = 'Cannot '.(($action === 'create') ? 'add' : 'update').' '.$this->object.' group';

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
			COverlayDialogElement::ensureNotPresent();
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
			$error_details = ($this->object == 'template')
				? CTestArrayHelper::get($data, 'template_error', $data['error'])
				: $data['error'];
			$this->assertMessage(TEST_BAD, $bad_message, $error_details);
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Update group without changing data.
	 *
	 * @param string  $name        group name to be opened for check
	 * @param bollean $discovered  discovered host group or not
	 */
	public function simpleUpdate($name, $discovered = false) {
		$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		$form = $this->openForm($name, $discovered);
		$values = $form->getValues();
		$form->submit();
		$this->assertMessage(TEST_GOOD, ucfirst($this->object).' group updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));

		// Check form values.
		$this->openForm($name, $discovered);
		$form->invalidate();
		$this->assertEquals($values, $form->getValues());

		COverlayDialogElement::find()->one()->close();
	}

	public static function getCloneData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_GROUP,
					'error' => ' group "'.self::DELETE_GROUP.'" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP,
					'fields' => [
						'Group name' => microtime().' cloned group'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::SUBGROUP,
					'fields' => [
						'Group name' => microtime().'/cloned/subgroup'
					]
				]
			]
		];
	}

	public function clone($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}
		$groupid = CDBHelper::getValue('SELECT groupid FROM hstgrp WHERE name='.zbx_dbstr($data['name']).
				' AND type='.constant('HOST_GROUP_TYPE_'.strtoupper($this->object).'_GROUP')
		);

		$form = $this->openForm($data['name'], CTestArrayHelper::get($data, 'discovered', false));
		$footer = COverlayDialogElement::find()->one()->waitUntilReady()->getFooter();
		$footer->query('button:Clone')->one()->waitUntilClickable()->click();
		$form->invalidate();

		// Check that the group creation form is open after cloning.
		$title = 'New '.$this->object.' group';
		$this->assertEquals($title, COverlayDialogElement::find()->one()->waitUntilReady()->getTitle());

		$this->assertEquals(PHPUNIT_URL.'zabbix.php?action=popup&popup='.$this->object.'group.edit'.'&groupid='.$groupid, $this->page->getCurrentUrl());
		$this->assertEquals(['Add', 'Cancel'], $footer->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText());
		$form->fill(CTestArrayHelper::get($data, 'fields', []));
		$form->submit();

		if ($data['expected'] === TEST_GOOD) {
			COverlayDialogElement::ensureNotPresent();
			$this->assertMessage(TEST_GOOD, ucfirst($this->object).' group added');
			$this->page->assertHeader($this->search ? 'Search: group' : ucfirst($this->object).' groups');
			$this->assertEquals(PHPUNIT_URL.$this->link, $this->page->getCurrentUrl());

			$form = $this->openForm($data['fields']['Group name']);
			$form->checkValue($data['fields']['Group name']);

			foreach ([$data['name'], $data['fields']['Group name']] as $name) {
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE name='.zbx_dbstr($name).
						' AND type='.constant('HOST_GROUP_TYPE_'.strtoupper($this->object).'_GROUP'))
				);
			}
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
			$this->assertMessage(TEST_BAD, 'Cannot add '.$this->object.' group', ucfirst($this->object).$data['error']);
		}

		COverlayDialogElement::find()->one()->close();
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
			COverlayDialogElement::find()->one()->waitUntilReady()->getFooter()->query('button', $data['action'])
					->one()->click();
		}

		if ($data['action'] === 'Delete') {
			$this->page->dismissAlert();
		}

		COverlayDialogElement::find()->one()->waitUntilReady()->getFooter()->query('button:Cancel')->waitUntilClickable()
				->one()->click();
		COverlayDialogElement::ensureNotPresent();

		$this->page->assertHeader($this->search ? 'Search: group' : ucfirst($this->object).' groups');
		$this->assertEquals(PHPUNIT_URL.$this->link, $this->page->getCurrentUrl());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));

	}

	public static function getDeleteData() {
		return [
			// Empty group without Host/Template.
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP
				]
			],
			// Host/Template has two groups, one of them can be deleted.
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DELETE_GROUP2
				]
			]
		];
	}

	/**
	 * Test for checking group deletion.
	 *
	 * @param array $data  data provider
	 */
	public function delete($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::GROUPS_SQL);
		}

		$this->openForm($data['name']);
		COverlayDialogElement::find()->one()->waitUntilReady()->getFooter()->query('button:Delete')->one()
				->waitUntilClickable()->click();
		$this->assertEquals('Delete selected '.$this->object.' group?', $this->page->getAlertText());
		$this->page->acceptAlert();

		if ($data['expected'] === TEST_GOOD) {
			COverlayDialogElement::ensureNotPresent();
			$this->assertMessage(TEST_GOOD, ucfirst($this->object).' group deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hstgrp WHERE name='.zbx_dbstr($data['name']).
					' AND type='.constant('HOST_GROUP_TYPE_'.strtoupper($this->object).'_GROUP'))
			);
		}
		else {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::GROUPS_SQL));
			$this->assertMessage(TEST_BAD, 'Cannot delete '.$this->object.' group', $data['error']);
			COverlayDialogElement::find()->one()->close();
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

		CDataHelper::call('templategroup.create', [
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
		$template_groupids = CDataHelper::getIds('name');

		$response = CDataHelper::call('usergroup.create', [
			[
				'name' => 'User group to check subgroup permissions',
				'hostgroup_rights' => [
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
				'templategroup_rights' => [
					[
						'permission' => PERM_DENY,
						'id' => $template_groupids['Europe']
					],
					[
						'permission' => PERM_READ,
						'id' => $template_groupids['Europe/Latvia']
					],
					[
						'permission' => PERM_READ_WRITE,
						'id' => $template_groupids['Europe/Test']
					],
					[
						'permission' => PERM_DENY,
						'id' => $template_groupids['Streets']
					],
					[
						'permission' => PERM_READ,
						'id' => $template_groupids['Cities/Cesis']
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

	public static function getSubgroupsData() {
		return [
			[
				[
					// All 'Europe/Test' subgroups are changing permissions.
					'apply_permissions' => 'Europe/Test',
					// Permissions do not apply to a first-level group from existing subgroup.
					'create' => 'Cities',
					// "groups_before" parameter isn't used in test, but groups are listed here for test clarity.
					'groups_before' => [
						[
							'groups' => 'Europe/Test',
							'Permissions' => 'Read-write'
						],
						[
							'groups' => ['Cities/Cesis', 'Europe/Latvia'],
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Streets'],
							'Permissions' => 'Deny'
						]
					],
					'tags_before' => [
						['Host groups' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host groups' => 'Europe', 'Tags' => 'world'],
						['Host groups' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host groups' => 'Streets', 'Tags' => 'street']
					],
					'groups_after' => [
						[
							'groups' => ['Europe/Test', 'Europe/Test/Zabbix'],
							'Permissions' => 'Read-write'
						],
						[
							'groups' => ['Cities/Cesis', 'Europe/Latvia'],
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Streets'],
							'Permissions' => 'Deny'
						]
					],
					'tags_after' => [
						['Host groups' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host groups' => 'Europe', 'Tags' => 'world'],
						['Host groups' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host groups' => 'Europe/Test/Zabbix', 'Tags' => 'country: test'],
						['Host groups' => 'Streets', 'Tags' => 'street']
					]
				]
			],
			[
				[
					// All 'Europe' subgroups are changing permissions.
					'apply_permissions' => 'Europe',
					// The new subgroup inherits the permissions of the first-level group.
					'create' => 'Streets/Dzelzavas',
					'groups_before' => [
						[
							'groups' => ['Europe/Test', 'Europe/Test/Zabbix'],
							'Permissions' => 'Read-write'
						],
						[
							'groups' => ['Cities/Cesis', 'Europe/Latvia'],
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Streets'],
							'Permissions' => 'Deny'
						]
					],
					'tags_before' => [
						['Host groups' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host groups' => 'Europe', 'Tags' => 'world'],
						['Host groups' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host groups' => 'Streets', 'Tags' => 'street']
					],
					'groups_after' => [
						[
							'groups' => 'Cities/Cesis',
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Europe/Latvia', 'Europe/Latvia/Riga/Zabbix',
								'Europe/Test', 'Europe/Test/Zabbix', 'Streets', 'Streets/Dzelzavas'],
							'Permissions' => 'Deny'
						]
					],
					'tags_after' => [
						['Host groups' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host groups' => 'Europe', 'Tags' => 'world'],
						['Host groups' => 'Europe/Latvia', 'Tags' => 'world'],
						['Host groups' => 'Europe/Latvia/Riga/Zabbix', 'Tags' => 'world'],
						['Host groups' => 'Europe/Test', 'Tags' => 'world'],
						['Host groups' => 'Europe/Test/Zabbix', 'Tags' => 'world'],
						['Host groups' => 'Streets', 'Tags' => 'street'],
						['Host groups' => 'Streets/Dzelzavas', 'Tags' => 'street']
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
		// Prepare groups array, change key to 'Host groups' or 'Template groups'.
		foreach ($data['groups_after'] as &$group_permissions) {
			$group_permissions[ucfirst($this->object).' groups'] = $group_permissions['groups'];
			unset($group_permissions['groups']);
		}
		unset($group_permissions);

		// Create new parent or subgroup to check nested permissions.
		if (array_key_exists('create', $data)) {
			$form = $this->openForm(CTestArrayHelper::get($data, 'open_form', null));
			$form->fill(['Group name' => $data['create']]);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();

			// Permission inheritance doesn't apply when changing the name of existing group, only when creating a new group.
			$this->assertMessage(TEST_GOOD,
					ucfirst($this->object).' group '.(array_key_exists('open_form', $data) ? 'updated' : 'added')
			);
		}

		// Apply permissions to subgroups.
		$form = $this->openForm($data['apply_permissions']);
		$form->fill([(($this->object === 'host')
			? 'Apply permissions and tag filters to all subgroups'
			: 'Apply permissions to all subgroups') => true
		]);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, ucfirst($this->object).' group updated');

		// Check group and tag permissions in user group.
		$this->page->open('zabbix.php?action=usergroup.edit&usrgrpid='.self::$user_groupid)->waitUntilReady();
		$group_form = $this->query('id:user-group-form')->asForm()->one();
		$group_form->selectTab(ucfirst($this->object).' permissions');
		$group_form->getField('Permissions')->asMultifieldTable()->checkValue($data['groups_after']);
		$group_form->selectTab('Problem tag filter');

		// Tag permissions do not change for template groups.
		$this->assertTableData(($this->object === 'template') ? $data['tags_before'] : $data['tags_after'],
				'id:tag-filter-table'
		);
	}
}

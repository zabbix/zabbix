<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/CAPITest.php';

/**
 * @backup media_type, auditlog, config, profiles
 *
 * @onBefore deleteAuditlog
 */
class testPageReportsAudit extends CWebTest {

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
	 * Audit log resourceid.
	 */
	protected static $id;

	/**
	 * Check audit page layout.
	 */
	public function testPageReportsAudit_Layout() {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that filter set to display Last hour data.
		$this->assertEquals('selected', $this->query('xpath://a[@data-label="Last 1 hour"]')->one()->getAttribute('class'));

		// Press to display filter.
		$this->query('id:ui-id-2')->one()->click();

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$filter_actions = ['Add', 'Delete', 'Execute', 'Failed login', 'History clear', 'Login', 'Logout', 'Update'];

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('xpath:.//div[@class="filter-forms"]/button[text()="'.$button.'"]')
					->one()->isClickable()
			);
		}

		// Check form labels.
		$this->assertEquals(['Users', 'Resource', 'Resource ID', 'Recordset ID', 'Actions'], $form->getLabels()->asText());

		// Check that resource values set as All by default.
		$this->assertTrue($form->checkValue(['Resource' => 'All']));

		// Check table headers.
		$this->assertEquals(['Time', 'User', 'IP', 'Resource', 'ID', 'Action', 'Recordset ID', 'Details'], $table->getHeadersText());

		// Find action checkboxes and check labels.
		$this->assertEquals($filter_actions, $this->query('id:filter-actions')->asCheckboxList()->one()->getLabels()->asText());

		// Check that table stats are present.
		$this->assertTableStats($table->getRows()->count());

		// Resource name with checkboxes that are enabled.
		$resource_actions =[
			'API token' => ['Add', 'Delete', 'Update'],
			'Action' => ['Add', 'Delete', 'Update'],
			'Authentication' => ['Update'],
			'Autoregistration' => ['Add', 'Delete', 'Update'],
			'Dashboard' => ['Add', 'Delete', 'Update'],
			'Discovery rule' => ['Add', 'Delete', 'Update'],
			'Event correlation' => ['Add', 'Delete', 'Update'],
			'Graph' => ['Add', 'Delete', 'Update'],
			'Graph prototype' => ['Add', 'Delete', 'Update'],
			'High availability node' => ['Add', 'Delete', 'Update'],
			'Host' => ['Add', 'Delete', 'Update'],
			'Host group' => ['Add', 'Delete', 'Update'],
			'Host prototype' => ['Add', 'Delete', 'Update'],
			'Housekeeping' => ['Update'],
			'Icon mapping' => ['Add', 'Delete', 'Update'],
			'Image' => ['Add', 'Delete', 'Update'],
			'Item' => ['Add', 'Delete', 'History clear', 'Update'],
			'Item prototype' => ['Add', 'Delete', 'Update'],
			'Macro' => ['Add', 'Delete', 'Update'],
			'Maintenance' => ['Add', 'Delete', 'Update'],
			'Map' => ['Add', 'Delete', 'Update'],
			'Media type' => ['Add', 'Delete', 'Update'],
			'Module' => ['Add', 'Delete', 'Update'],
			'Proxy' => ['Add', 'Delete', 'Update'],
			'Regular expression' => ['Add', 'Delete', 'Update'],
			'SLA' => ['Add', 'Delete', 'Update'],
			'Scheduled report' => ['Add', 'Delete', 'Update'],
			'Script' => ['Add', 'Delete', 'Execute', 'Update'],
			'Service' => ['Add', 'Delete', 'Update'],
			'Settings' => ['Update'],
			'Template' => ['Add', 'Delete', 'Update'],
			'Template dashboard' => ['Add', 'Delete', 'Update'],
			'Trigger' => ['Add', 'Delete', 'Update'],
			'Trigger prototype' => ['Add', 'Delete', 'Update'],
			'User' => ['Add', 'Delete', 'Failed login', 'Login', 'Logout', 'Update'],
			'User group' => ['Add', 'Delete', 'Update'],
			'User role' => ['Add', 'Delete', 'Update'],
			'Value map' => ['Add', 'Delete', 'Update'],
			'Web scenario' => ['Add', 'Delete', 'Update']
		];

		// Check that actions checkboxes correctly enables/disables switching resources.
		$errors = [];
		foreach ($resource_actions as $resource => $actions) {
			$form->fill(['Resource' => $resource]);
			$left_actions = array_values(array_diff($filter_actions, $actions));

			// At first, we need to check that correct checkboxes is enabled. Then we check that all others are disabled.
			foreach ([true, false] as $status) {
				if (!$status) {
					$actions = $left_actions;
				}

				foreach ($actions as $action) {
					$this->assertTrue($this->query('xpath://label[text()="'.$action.'"]/../input[@type="checkbox"]')->
							one()->isEnabled($status)
					);
				}
			}
		}
	}

	/**
	 * Create media type and check audit page.
	 */
	public function testPageReportsAudit_Add() {
		$response = CDataHelper::call('mediatype.create', [
			[
				'type' => 0,
				'name' => 'AAA',
				'smtp_server' => 'mail.example.com',
				'smtp_helo' => 'example.com',
				'smtp_email' => 'zabbix@example.com',
				'content_type' => 1
			]
		]);
		$this->assertArrayHasKey('mediatypeids', $response);
		self::$id = $response['mediatypeids'][0];

		// Find media type id and check that audit info displayed correctly on frontend.
		$create_audit = "mediatype.mediatypeid: ".self::$id.
			"\nmediatype.name: AAA".
			"\nmediatype.smtp_email: zabbix@example.com".
			"\nmediatype.smtp_helo: example.com".
			"\nmediatype.smtp_server: mail.example.com";
		$this->checkAuditValues('Media type', self::$id, ['Add' => $create_audit]);
	}

	/**
	 * Update media type and check audit page.
	 *
	 * @depends testPageReportsAudit_Add
	 */
	public function testPageReportsAudit_Update() {
		CDataHelper::call('mediatype.update', [
			[
				'mediatypeid' => self::$id,
				'name' => 'AAA_update',
				'smtp_helo' => 'updated.com',
				'smtp_email' => 'update@email.com'
			]
		]);

		// Check that audit info displayed correctly on frontend after update.
		$update_audit = "mediatype.name: AAA => AAA_update".
			"\nmediatype.smtp_email: zabbix@example.com => update@email.com".
			"\nmediatype.smtp_helo: example.com => updated.com";
		$this->checkAuditValues('Media type', self::$id, ['Update' => $update_audit]);
	}

	/**
	 * Delete media type and check audit page.
	 *
	 * @depends testPageReportsAudit_Add
	 */
	public function testPageReportsAudit_Delete() {
		CDataHelper::call('mediatype.delete', [self::$id]);

		// Check that audit info displayed correctly on frontend after delete.
		$delete_audit = 'Description: AAA_update';
		$this->checkAuditValues('Media type', self::$id, ['Delete' => $delete_audit]);
	}

	/**
	 * Clear history and trends in item and check audit page.
	 */
	public function testPageReportsAudit_HistoryClear() {
		CDataHelper::call('history.clear', [99106]);

		// Check that audit info displayed correctly on frontend.
		$clear_audit = 'Description: Dynamic widgets H3I1';
		$this->checkAuditValues('Item', 99106, ['History clear' => $clear_audit]);
	}

	/**
	 * Check that Login, Logout and Failed login works and displayed correctly.
	 */
	public function testPageReportsAudit_LoginLogoutFailed() {
		$this->page->userLogin('Admin', 'zabbixaaa');
		$this->page->userLogin('Admin', 'zabbix');
		$this->query('link:Sign out')->waitUntilVisible()->one()->click();
		$this->page->login();

		// Check that all info displayed correctly in audit.
		$user_audit = '';
		$this->checkAuditValues('User', 1, ['Failed login' => $user_audit, 'Login' => $user_audit,
				'Logout' => $user_audit]
		);
	}

	/**
	 * Check that there is no audit logs after disabling audit.
	 */
	public function testPageReportsAudit_DisabledEnabled() {
		$this->page->login();
		foreach ([false, true] as $status) {
			$this->page->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();

			// Disable audit.
			$settings_form = $this->query('id:audit-settings')->asForm()->one();
			$settings_form->fill(['Enable audit logging' => $status])->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');

			// Save audit data from table in UI and database.
			$this->page->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();
			$table = $this->query('class:list-table')->asTable()->one();
			$audit_values = $table->getRow(0)->getText();
			$hash = CDBHelper::getHash('SELECT * FROM auditlog');

			// Check information in audit page that audit is disabled/enabled.
			$audit_status = (!$status) ? 'settings.auditlog_enabled: 1 => 0' : 'settings.auditlog_enabled: 0 => 1';
			$this->assertEquals($audit_status, $this->query('class:list-table')->asTable()->one()->getRow(0)->
					getColumn('Details')->getText()
			);

			// Update media type. If audit is disabled - no new data should appear in audit page/database.
			$name = (!$status) ? 'BBB' : 'CCC';
			CDataHelper::call('mediatype.update', [
				[
					'mediatypeid' => 1,
					'name' => $name
				]
			]);

			// Compare audit after disabling/enabling audit and adding media type.
			$this->page->refresh()->waitUntilReady();

			if (!$status) {
				$this->assertEquals($audit_values, $table->getRow(0)->getText());
				$this->assertEquals($hash, CDBHelper::getHash('SELECT * FROM auditlog'));
			}
			else {
				$this->assertNotEquals($audit_values, $table->getRow(0)->getText());
				$this->assertNotEquals($hash, CDBHelper::getHash('SELECT * FROM auditlog'));
			}
		}
	}

	/**
	 * @onBeforeOnce prepareLoginData
	 */
	public static function getCheckFilterData() {
		return [
			// #0
			[
				[
					'fields' => [
						'Resource' => 'Media type',
						'Actions' => 'Add'
					],
					'result_count' => 1
				]
			],
			// #1
			[
				[
					'fields' => [
						'Resource' => 'Media type'
					],
					'result_count' => 4
				]
			],
			// #2
			[
				[
					'fields' => [
						'Resource' => 'Media type',
						'Users' => 'Admin'
					],
					'result_count' => 4
				]
			],
			// #3
			[
				[
					'fields' => [
						'Users' => 'Admin'
					],
					'result_count' => 12
				]
			],
			// #4
			[
				[
					'fields' => [
						'Users' => 'Admin',
						'Actions' => 'Failed login'
					],
					'result_count' => 1
				]
			],
			// #5
			[
				[
					'fields' => [
						'Users' => 'Admin',
						'Actions' => [
							'Add',
							'Delete',
							'Failed login',
							'Logout',
							'Login'
						]
					],
					'result_count' => 5
				]
			],
			// #6
			[
				[
					'fields' => [
						'Actions' => [
							'Add',
							'Delete',
							'Failed login',
							'History clear',
							'Logout',
							'Login',
							'Update'
						]
					]
				]
			],
			// #7
			[
				[
					'fields' => [
						'Users' => ['test-timezone', 'Admin']
					],
					'result_count' => 13
				]
			],
			// #8
			[
				[
					'fields' => [
						'Resource ID' => 99106
					],
					'result_count' => 1
				]
			],
			// #9
			[
				[
					'fields' => [
						'Users' => 'Admin',
						'Resource' => 'Item',
						'Resource ID' => 99106,
						'Actions' => 'History clear'
					],
					'result_count' => 1
				]
			],
			// #10
			[
				[
					'fields' => [
						'Recordset ID' => 'cl7irkc1h00003pde7s7xxxxx'
					],
					'no_data' => true
				]
			],
			// #11
			[
				[
					'fields' => [
						'Users' => 'guest',
						'Actions' => 'Add'
					],
					'no_data' => true
				]
			],
			// #12
			[
				[
					'fields' => [
						'Resource ID' => 77777777
					],
					'no_data' => true
				]
			],
			// #13
			[
				[
					'fields' => [
						'Users' => 'filter-create'
					],
					'no_data' => true
				]
			],
			// #14
			[
				[
					'fields' => [
						'Actions' => 'Execute'
					],
					'no_data' => true
				]
			],
			// #15
			[
				[
					'fields' => [
						'Resource' => 'Web scenario'
					],
					'no_data' => true
				]
			]
			// TODO: uncomment after ZBX-21097 fix
			// #16
//			[
//				[
//					'fields' => [
//						'Resource ID' => 'aaaaaaaa'
//					],
//					'no_data' => true
//				]
//			],
			// #17
//			[
//				[
//					'fields' => [
//						'Recordset ID' => 'aaaaaaaa'
//					],
//					'no_data' => true
//				]
//			]
		];
	}

	/**
	 * Check audit filter. This checks can be executed only after all other scenarios completed.
	 * There are used values and data that was created before in this autotest.
	 *
	 * @dataProvider getCheckFilterData
	 *
	 * @onBeforeOnce prepareLoginData
	 *
	 * @depends testPageReportsAudit_Add
	 * @depends testPageReportsAudit_Update
	 * @depends testPageReportsAudit_Delete
	 * @depends testPageReportsAudit_HistoryClear
	 * @depends testPageReportsAudit_LoginLogoutFailed
	 * @depends testPageReportsAudit_DisabledEnabled
	 */
	public function testPageReportsAudit_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$form->query('button:Reset')->one()->click();

		$form->fill($data['fields'])->submit();

		// If there is no result - "No data found" displayed in table.
		if (CTestArrayHelper::get($data, 'no_data')) {
			$this->assertEquals(['No data found.'], $table->getRows()->asText());
		}
		else {
			foreach ($data['fields'] as $column => $values) {
				if ($column === 'Users' || 'Actions') {
					$column = rtrim($column, 's');
				}

				if ($column === 'Resource ID') {
					$column = 'ID';
				}

				// If not array.
				if (!is_array($values)) {
					$values = [$values];
				}

				// Get all results from column and remove existing values.
				$table_value = $this->getTableResult($column);

				foreach ($values as $value) {
					$this->assertTrue(in_array($value, $table_value));

					// Remove existing value from the list.
					$table_value = array_values(array_diff($table_value, [$value]));
				}

				// If everything correct, there should not be left any values.
				$this->assertEquals($table_value, []);
			}

			// TODO: remove IF condition after ZBX-19918 fix. Add result_count to test case #6
			// There is some scenarios with known result amount.
			if (array_key_exists('result_count', $data)) {
				$this->assertEquals($data['result_count'], $table->getRows()->count());
			}
		}
	}

	/**
	 * Check that audit log can be filtered by recordsetid.
	 */
	public function testPageReportsAudit_CheckRecordsetFilter() {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$form->query('button:Reset')->one()->click();

		// Click on Recordset ID in first row.
		$table->getRow(0)->getColumn('Recordset ID')->query('xpath:.//a')->one()->click();
		$recordsetid = $table->getRow(0)->getColumn('Recordset ID')->getText();

		// Check that correct Recordset ID displayed in filter form.
		$this->assertTrue($form->checkValue(['Recordset ID' => $recordsetid]));

		// Compare result cout on page and in DB.
		$recordsetid_count = CDBHelper::getCount('SELECT NULL FROM auditlog WHERE recordsetid='.zbx_dbstr($recordsetid));
		$this->assertEquals($recordsetid_count, $table->getRows()->count());
	}

	/**
	 * Filter and compare audit log.
	 *
	 * @param string $resource_name		resource parameter on audit page.
	 * @param integer $resourceid		parameter resource ID.
	 * @param array $actions			action name as key and audit details as value.
	 */
	private function checkAuditValues($resource_name, $resourceid, $actions) {
		$this->page->login()->open('zabbix.php?action=auditlog.list')->waitUntilReady();

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		// Find filter form and fill with correct resource values.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->query('button:Reset')->one()->click();

		foreach ($actions as $action => $audit) {
			$form->fill(['Resource' => $resource_name, 'Resource ID' => $resourceid]);
			$form->query("xpath:.//label[text()=".CXPathHelper::escapeQuotes($action).
					']/../input[contains(@id, "filter_actions")]'
			)->asCheckbox()->one()->check();
			$form->submit()->waitUntilReloaded();

			// Check that action column has correct action value.
			$table = $this->query('class:list-table')->asTable()->one();
			$this->assertEquals($action, $table->getRow(0)->getColumn('Action')->getText());

			// Check audit details in overlay window or in details column.
			$details_link = $table->getRow(0)->getColumn('Details')->query('link:Details')->one(false);

			if ($details_link->isValid()) {
				$details_link->click();
				$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$this->assertEquals($audit, $dialog->getContent()->getText());
				$dialog->close();
			}
			else {
				$this->assertEquals($audit, $table->getRow(0)->getColumn('Details')->getText());
			}

			// Values taken from column after filtering audit.
			$columns = ['Time', 'User', 'IP', 'Resource', 'ID', 'Recordset ID'];
			$result = [];

			foreach ($columns as $column) {
				$column_value = $table->getRow(0)->getColumn($column)->getText();

				// Need to convert time to epoch format and change to string.
				if ($column === 'Time') {
					$column_value = strval(strtotime($column_value));
				}

				// Every resource has its value in database.
				if ($column === 'Resource') {
					$column_value = ($column_value === 'User') ? 0 : (($column_value === 'Item') ? 15 : 3);
				}

				$result[] = $column_value;
			}

			// Compare values from DB and audit page.
			$action_ids = [
				'Failed login' => 9,
				'Login' => 8,
				'Logout' => 4,
				'Add' => 0,
				'Update' => 1,
				'Delete' => 2,
				'History clear' => 10
			];

			$dbaudit = CDBHelper::getAll('SELECT clock, username, ip, resourcetype, resourceid, recordsetid FROM auditlog WHERE
					(resourceid, action)=('.zbx_dbstr($resourceid).','.zbx_dbstr($action_ids[$action]).') ORDER BY clock DESC LIMIT 1'
			);
			$this->assertEquals([], array_diff($result, $dbaudit[0]));
		}
	}

	/**
	 * Clear auditlog table.
	 */
	public function deleteAuditlog() {
		DBexecute('DELETE FROM auditlog');
	}

	/**
	 * Login as test-timezone user to create new data in autotest for filter scenario.
	 */
	public function prepareLoginData() {
		CAPITest::disableAuthorization();
		CDataHelper::call('user.login',
			[
				'username' => 'test-timezone',
				'password' => 'zabbix'
			]
		);
	}
}

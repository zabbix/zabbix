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

/**
 * @backup media_type, auditlog, config
 */
class testPageReportsAuditDisplay extends CWebTest {
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
	public function testPageReportsAuditDisplay_Layout() {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();

		// If the time selector is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check that filter set to display Last hour data.
		$this->assertEquals('selected', $this->query('xpath://a[@data-label="Last 1 hour"]')
			->one()->getAttribute('class')
		);

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$filter_actions = ['Add', 'Delete', 'Execute', 'Failed login', 'History clear', 'Login', 'Logout', 'Update'];

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isPresent());
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
			foreach([true, false] as $status) {
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
	public function testPageReportsAuditDisplay_Add() {
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
	 * @depends testPageReportsAuditDisplay_Add
	 */
	public function testPageReportsAuditDisplay_Update() {
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
	 * @depends testPageReportsAuditDisplay_Add
	 */
	public function testPageReportsAuditDisplay_Delete() {
		CDataHelper::call('mediatype.delete', [self::$id]);

		// Check that audit info displayed correctly on frontend after delete.
		$delete_audit = 'Description: AAA_update';
		$this->checkAuditValues('Media type', self::$id, ['Delete' => $delete_audit]);
	}

	/**
	 * Clear history and trends in item and check audit page.
	 */
	public function testPageReportsAuditDisplay_HistoryClear() {
		CDataHelper::call('history.clear', [99106]);

		// Check that audit info displayed correctly on frontend.
		$clear_audit = 'Description: Dynamic widgets H3I1';
		$this->checkAuditValues('Item', 99106, ['History clear' => $clear_audit]);
	}

	/**
	 * Check that Login, Logout and Failed login works and displayed correctly.
	 */
	public function testPageReportsAuditDisplay_LoginLogoutFailed() {
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
	public function testPageReportsAuditDisplay_DisabledEnabled() {
		$this->page->login();
		foreach ([false, true] as $status) {
			$this->page->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();

			// Disable audit.
			$settings_form = $this->query('id:audit-settings')->asForm()->one();
			$settings_form->fill(['Enable audit logging' => $status])->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');

			// Save audit data from table in UI and database.
			$this->page->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();
			$audit_values = $this->getTableRowValue();
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
				$this->assertEquals($audit_values, $this->getTableRowValue());
				$this->assertEquals($hash, CDBHelper::getHash('SELECT * FROM auditlog'));
			}
			else {
				$this->assertNotEquals($audit_values, $this->getTableRowValue());
				$this->assertNotEquals($hash, CDBHelper::getHash('SELECT * FROM auditlog'));
			}
		}
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

		foreach ($actions as $action => $audit) {
			$form->fill(['Resource' => $resource_name, 'Resource ID' => $resourceid]);
			$form->query('xpath:.//label[text()="'.$action.'"]/../input[contains(@id, "filter_actions")]')
					->asCheckbox()->one()->check();
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
				'History clear' => 10,
			];

			$dbaudit = CDBHelper::getAll('SELECT clock, username, ip, resourcetype, resourceid, recordsetid FROM auditlog WHERE 
                	(resourceid, action)=('.zbx_dbstr($resourceid).','.zbx_dbstr($action_ids[$action]).') ORDER BY clock DESC LIMIT 1');
			$this->assertEquals([], array_diff($result, $dbaudit[0]));

			// Reset audit filter.
			$this->query('name:zbx_filter')->asForm()->one()->query('button:Reset')->one()->click();
		}
	}

	/**
	 * Get table values from first row.
	 *
	 * @return array
	 */
	private function getTableRowValue() {
		$headers = $this->query('class:list-table')->asTable()->one()->getHeadersText();
		$result = [];
		$row = $this->query('class:list-table')->asTable()->one()->getRow(0);
		foreach ($headers as $header) {
			$result[] = $row->getColumn($header)->getText();
		}

		return $result;
	}
}

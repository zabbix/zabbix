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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup ids, media_type, auditlog
 */
class testAuditManual extends CWebTest {
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
	 * Check Audit page layout.
	 */
	public function testAuditManual_Layout() {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		// Find filter form and check labels.
		$this->assertEquals(['Users', 'Resource', 'Resource ID', 'Recordset ID', 'Actions'],
				$this->query('name:zbx_filter')->asForm()->one()->getLabels()->asText());

		// Find table and check table headers.
		$this->assertEquals(['Time', 'User', 'IP', 'Resource', 'ID', 'Action', 'Recordset ID', 'Details'],
				$this->query('class:list-table')->asTable()->one()->getHeadersText());

		// Find action checkboxes and check labels.
		$this->assertEquals(['Add', 'Delete', 'Execute', 'Failed login', 'History clear', 'Login', 'Logout', 'Update'],
				$this->query('id:filter-actions')->asCheckboxList()->one()->getLabels()->asText()
		);
	}

	/**
	 * Check that actions checkboxes correctly enables/disables switching resources.
	 */
	public function testAuditManual_ActionsCheckbox() {
		$this->page->login()->open('zabbix.php?action=auditlog.list&filter_rst=1')->waitUntilReady();
		$errors = [];

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

		foreach ($resource_actions as $resource => $actions) {
			$status = true;
			$this->query('name:zbx_filter')->asForm()->one()->fill(['Resource' => $resource]);
			$left_actions = array_values(array_diff(['Add', 'Delete', 'Execute', 'Failed login', 'History clear',
					'Login', 'Logout', 'Update'], $actions));

			// At first, we need to check that correct checkboxes is enabled. Then we check that all others are disabled.
			for ($i = 1; $i <= 2; $i++) {
				if ($i !== 1) {
					$actions = $left_actions;
					$status = false;
				}

				foreach ($actions as $action) {
					try {
						$this->assertTrue($this->query('xpath:.//label[text()="'.$action.'"]/../input[@type="checkbox"]')->
							one()->isEnabled($status)
						);
					} catch (Throwable $ex) {
						$ex = 'Incorrect action checkbox status: "'.$resource.'" => "'.$action.'".';
						$errors[] = $ex;
					}
				}

				if ($errors) {
					$this->fail(implode("\n", $errors));
				}
			}
		}
	}

	/**
	 * Create media type and check audit page.
	 */
	public function testAuditManual_Add() {
		$this->page->login()->open('zabbix.php?action=mediatype.edit')->waitUntilReady();
		$form = $this->query('id:media-type-form')->asForm()->waitUntilReady()->one();
		$form->fill(['Name' => 'AAA']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Media type added');

		// Find media type id and check that audit info displayed correctly on frontend.
		self::$id = CDBHelper::getValue('SELECT mediatypeid FROM media_type WHERE name='.zbx_dbstr('AAA'));
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
	 * @depends testAuditManual_Add
	 */
	public function testAuditManual_Update() {
		$this->page->login()->open('zabbix.php?action=mediatype.edit&mediatypeid='.self::$id)->waitUntilReady();
		$form = $this->query('id:media-type-form')->asForm()->waitUntilReady()->one();
		$form->fill(['Name' => 'AAA_update', 'SMTP helo' => 'updated.com', 'SMTP email' => 'update@email.com']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Media type updated');

		// Check that audit info displayed correctly on frontend after update.
		$update_audit = "mediatype.name: AAA => AAA_update".
			"\nmediatype.passwd: ****** => ******".
			"\nmediatype.smtp_email: zabbix@example.com => update@email.com".
			"\nmediatype.smtp_helo: example.com => updated.com";
		$this->checkAuditValues('Media type', self::$id, ['Update' => $update_audit]);
	}

	/**
	 * Delete media type and check audit page.
	 *
	 * @depends testAuditManual_Add
	 */
	public function testAuditManual_Delete() {
		$this->page->login()->open('zabbix.php?action=mediatype.edit&mediatypeid=' . self::$id)->waitUntilReady();
		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		CElementQuery::getPage()->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Media type deleted');

		// Check that audit info displayed correctly on frontend after delete.
		$delete_audit = 'Description: AAA_update';
		$this->checkAuditValues('Media type', self::$id, ['Delete' => $delete_audit]);
	}

	/**
	 * Clear history and trends in item and check audit page.
	 */
	public function testAuditManual_HistoryClear() {
		$this->page->login()->open('items.php?form=update&hostid=99204&itemid=99106&context=host')->waitUntilReady();
		$this->query('id:del_history')->waitUntilClickable()->one()->click();
		CElementQuery::getPage()->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'History cleared');

		// Check that audit info displayed correctly on frontend.
		$clear_audit = 'Description: Dynamic widgets H3I1';
		$this->checkAuditValues('Item', 99106, ['History clear' => $clear_audit]);
	}

	/**
	 * Check that Login, Logout and Failed login works and displayed correctly.
	 */
	public function testAuditManual_LoginLogoutFailed() {
		$this->page->userLogin('Admin', 'zabbixaaa');
		$this->page->userLogin('Admin', 'zabbix');
		$this->query('link:Sign out')->waitUntilVisible()->one()->click();
		$this->page->login();

		// Check that all info displayed correctly in audit.
		$user_audit = '';
		$this->checkAuditValues('User', 1, ['Failed login' => $user_audit, 'Login' => $user_audit, 'Logout' => $user_audit]);
	}

	/**
	 * Filter and compare audit log.
	 *
	 * @param string $resource_name		resource parameter on audit page.
	 * @param integer $resourceid		parameter resource ID.
	 * @param array $actions			action name as key and audit details as value.
	 */
	private function checkAuditValues($resource_name, $resourceid, $actions) {
		$this->page->open('zabbix.php?action=auditlog.list')->waitUntilReady();

		// If the filter is not visible - enable it.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		// Find filter form and fill with correct resource values.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		foreach($actions as $action => $audit) {
			$form->fill(['Resource' => $resource_name, 'Resource ID' => $resourceid]);
			$form->query('xpath://label[text()="'.$action.'"]/../input[contains(@id, "filter_actions")]')
					->asCheckbox()->one()->check();
			$form->submit()->waitUntilReloaded();

			// Check that action column has correct action value.
			$table = $this->query('class:list-table')->asTable()->one();
			$this->assertEquals($action, $table->getRow(0)->getColumn('Action')->getText());

			// Check audit details in overlay window or in details column.
			if ($table->getRow(0)->getColumn('Details')->query('link:Details')->exists()) {
				$table->getRow(0)->getColumn('Details')->query('link:Details')->one()->click();
				$details = COverlayDialogElement::find()->waitUntilReady()->one()->getContent()->getText();
			} else {
				$details = $table->getRow(0)->getColumn('Details')->getText();
			}
			$this->assertEquals($details, $audit);

			// Close overlay if it exists.
			if (COverlayDialogElement::find()->exists()) {
				COverlayDialogElement::find()->one()->close();
			}

			// Values taken from column after filtering audit.
			$columns = ['Time', 'User', 'IP', 'Recordset ID'];
			$result = [];
			foreach ($columns as $column) {
				$column_value = $table->getRow(0)->getColumn($column)->getText();

				// Need to convert time to epoch format and change to string.
				if ($column === 'Time') {
					$column_value = strval(strtotime($column_value));
				}
				$result[] = $column_value;
			}

			// Compare values from DB and audit page.
			switch ($action) {
				case 'Failed login':
					$actionid = 9;
					break;
				case 'Login':
					$actionid = 8;
					break;
				case 'Logout':
					$actionid = 4;
					break;
				case 'Add':
					$actionid = 0;
					break;
				case 'Update':
					$actionid = 1;
					break;
				case 'Delete':
					$actionid = 2;
					break;
				case 'History clear':
					$actionid = 10;
					break;
			}

			$dbaudit = CDBHelper::getAll('SELECT clock, username, ip, recordsetid FROM auditlog WHERE (resourceid, action)=('
					.zbx_dbstr($resourceid).','.zbx_dbstr($actionid).') ORDER BY clock DESC LIMIT 1');
			$this->assertEquals([], array_diff($result, $dbaudit[0]));

			// Reset audit filter.
			$this->query('name:zbx_filter')->asForm()->one()->query('button:Reset')->one()->click();
		}
	}
}

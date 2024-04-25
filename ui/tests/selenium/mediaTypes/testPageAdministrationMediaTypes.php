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
 * @backup media_type
 */
class testPageAdministrationMediaTypes extends CWebTest {

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

	private static $media_name = 'Email';

	/**
	 * Check basic elements on page.
	 */
	public function testPageAdministrationMediaTypes_Layout() {
		$this->page->login()->open('zabbix.php?action=mediatype.list')->waitUntilReady();

		$this->page->assertTitle('Configuration of media types');
		$this->page->assertHeader('Media types');

		$buttons = [
			'Create media type' => true,
			'Import' => true,
			'Apply' => true,
			'Reset' => true,
			'Enable' => false,
			'Disable' => false,
			'Export' => false,
			'Delete' => false
		];
		foreach ($buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check filter fields.
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter_fields = [
			'Name' => '',
			'Status' => 'Any'
		];
		$filter->checkValue($filter_fields);

		$this->assertEquals(255, $filter->getField('Name')->getAttribute('maxlength'));
		$this->assertEquals(['Any', 'Enabled', 'Disabled'], $filter->getField('Status')->asSegmentedRadio()
				->getLabels()->asText()
		);

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();

		// Remove the empty header that is associated with the Select all checkbox.
		unset($headers[0]);
		$this->assertSame(['Name', 'Type', 'Status', 'Used in actions', 'Details', 'Action'], array_values($headers));

		foreach ($headers as $header) {
			if (in_array($header, ['Name', 'Type'])) {
				$this->assertTrue($table->query('link', $header)->one()->isClickable());
			}
			else {
				$this->assertFalse($table->query('link', $header)->one(false)->isValid());
			}
		}

		// Check table stats and selected mediatype counter.
		$this->assertTableStats(CDBHelper::getCount('SELECT NULL FROM media_type'));

		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());
	}

	/**
	 * Check sorting of media types in list.
	 *
	 * @onAfterOnce resetFilter
	 */
	public function testPageAdministrationMediaTypes_Sort() {
		$this->page->login()->open('zabbix.php?action=mediatype.list&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['Name', 'Type'] as $column) {
			$values = $this->getTableColumnData($column);

			$values_asc = $values;
			$values_desc = $values;

			// Sort column contents ascending.
			usort($values_asc, function($a, $b) {
				return strcasecmp($a, $b);
			});

			// Sort column contents descending.
			usort($values_desc, function($a, $b) {
				return strcasecmp($b, $a);
			});

			// Check ascending and descending sorting in column.
			foreach ([$values_asc, $values_desc] as $reference_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($reference_values, $column);
			}
		}
	}

	public static function getFilterData() {
		return [
			// Filter by name.
			[
				[
					'filter' => [
						'Name' => 'SMS'
					],
					'result' => ['SMS']
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Jira '
					],
					'result' => ['Jira ServiceDesk']
				]
			],
			[
				[
					'filter' => [
						'Name' => ' Jira '
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'a S'
					],
					'result' => ['Jira ServiceDesk']
				]
			],
			// Filter by status.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'get_db_result' => true
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'result' => ['Test script']
				]
			],
			// Filter by name and status.
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Status' => 'Disabled'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Email',
						'Status' => 'Enabled'
					],
					'result' => ['Email', 'Email (HTML)']
				]
			]
		];
	}

	/**
	 * Check media types filtering.
	 *
	 * @dataProvider getFilterData
	 *
	 * @onAfterOnce resetFilter
	 */
	public function testPageAdministrationMediaTypes_Filter($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('button:Reset')->waitUntilClickable()->one()->click();

		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'get_db_result')) {
			foreach (CDBHelper::getAll('SELECT name FROM media_type WHERE status='.MEDIA_STATUS_ACTIVE.
					' ORDER BY LOWER(name) ASC') as $name) {
				$data['result'][] = $name['name'];
			}
		}
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	/**
	 * Disable and enable media type by link in column Status.
	 */
	public function testPageAdministrationMediaTypes_StatusLink() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by column Name.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', self::$media_name);

		$statuses = ['Enabled', 'Disabled'];
		foreach($statuses as $old_status) {
			$new_status = array_values(array_diff($statuses, [$old_status]))[0];
			// Change media type status.
			$row->query('link', $old_status)->one()->click();
			$this->page->waitUntilReady();

			// Check result on fronted.
			$this->assertMessage(TEST_GOOD, 'Media type '.lcfirst($new_status));

			if ($new_status === 'Enabled') {
				$enabled = true;
				$db_status = MEDIA_TYPE_STATUS_ACTIVE;
			}
			else {
				$enabled = false;
				$db_status = MEDIA_TYPE_STATUS_DISABLED;
			}

			// Check that Test link is disabled.
			$this->assertTrue($row->query('button:Test')->one()->isEnabled($enabled));

			// Check result in DB.
			$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM media_type WHERE '.
					'name='.zbx_dbstr(self::$media_name))
			);
		}
	}

	public static function getSelectedMediaTypeData() {
		return [
			// Select one.
			[
				[
					'rows' => ['Email'],
					'db_name' => 'Email',
					'used_by_action' => 'Trigger action 3'
				]
			],
			// Select several.
			[
				[
					'rows' => ['SMS', 'Discord'],
					'db_name' => ['SMS', 'Discord']
				]
			],
			// Select all.
			[
				[
					'select_all' => true,
					// Selected different action names in MySQL and PostgreSQL.
					'used_by_action' => ''
				]
			]
		];
	}

	/**
	 * Test disabling of media types in the list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Disable($data) {
		$this->checkStatusChangeButton($data);
	}

	/**
	 * Test enabling of media types in the list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Enable($data) {
		$this->checkStatusChangeButton($data, 'enable');
	}

	/**
	 * Check that status of selected media types is changed when clicking on the corresponding control button.
	 *
	 * @param array		$data		data provider
	 * @param string	$action		action to be performed with the selected media types
	 */
	private function checkStatusChangeButton($data, $action = 'disable') {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		// Check number of all selected media types.
		if (array_key_exists('select_all', $data)) {
			$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM media_type').' selected',
					$this->query('id:selected_count')->one()->getText()
			);
		}
		else {
			$this->assertEquals(count($data['rows']).' selected', $this->query('id:selected_count')->one()->getText());
		}

		$this->query('button', ucfirst($action))->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend.
		$message_title = (count(CTestArrayHelper::get($data, 'rows', [])) === 1)
			? 'Media type '.$action.'d'
			: 'Media types '.$action.'d';
		$this->assertMessage(TEST_GOOD, $message_title);

		// Check the results in DB.
		$status = ($action === 'enable') ? MEDIA_TYPE_STATUS_ACTIVE : MEDIA_TYPE_STATUS_DISABLED;

		if (array_key_exists('rows', $data)) {
			$this->assertEquals(count($data['rows']), CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.
					$status.' AND name IN ('.CDBHelper::escape($data['db_name']).')')
			);
		}
		else {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status<>'.$status));
		}
	}

	public static function getTestFormData() {
		return [
			// Email validation.
			[
				[
					'name' => 'Email',
					'check_title' => true,
					'check_params' => true,
					'error' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => ' '
					],
					'error' => 'Invalid email address " ".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbixzabbix.com'
					],
					'error' => 'Invalid email address "zabbixzabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbixcom'
					],
					'error' => 'Invalid email address "zabbix@zabbixcom".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbixcom'
					],
					'error' => 'Invalid email address "zabbix@zabbixcom".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => '@zabbix.com'
					],
					'error' => 'Invalid email address "@zabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix1@zabbix.com,zabbix2@zabbix.com'
					],
					'error' => 'Invalid email address "zabbix1@zabbix.com,zabbix2@zabbix.com".'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Subject' => ''
					],
					'error' => [
						'Connection to Zabbix server "localhost" refused. Possible reasons:',
						'1. Incorrect server IP/DNS in the "zabbix.conf.php";',
						'2. Security environment (for example, SELinux) is blocking the connection;',
						'3. Zabbix server daemon not running;',
						'4. Firewall is blocking TCP connection.',
						'Connection refused'
					]
				]
			],
			// Message validation.
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Message' => ''
					],
					'error' => 'Incorrect value for field "message": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Email',
					'form' => [
						'Send to' => 'zabbix@zabbix.com',
						'Message' => ' '
					],
					'error' => 'Incorrect value for field "message": cannot be empty.'
				]
			],
			// SMS media type.
			[
				[
					'name' => 'SMS',
					'form' => [
						'Send to' => 'abcd',
						'Message' => 'new message'
					],
					'error' => [
						'Connection to Zabbix server "localhost" refused. Possible reasons:',
						'1. Incorrect server IP/DNS in the "zabbix.conf.php";',
						'2. Security environment (for example, SELinux) is blocking the connection;',
						'3. Zabbix server daemon not running;',
						'4. Firewall is blocking TCP connection.',
						'Connection refused'
					]
				]
			],
			// 	Script media type.
			[
				[
					'name' => 'Test script',
					'form' => [
						'Send to' => '/../"'
					],
					'error' => [
						'Connection to Zabbix server "localhost" refused. Possible reasons:',
						'1. Incorrect server IP/DNS in the "zabbix.conf.php";',
						'2. Security environment (for example, SELinux) is blocking the connection;',
						'3. Zabbix server daemon not running;',
						'4. Firewall is blocking TCP connection.',
						'Connection refused'
					]
				]
			],
			// 	Webhook media type.
			[
				[
					'name' => 'Reference webhook',
					'webhook' => true,
					'parameters' => ['HTTPProxy', 'Message', 'Subject', 'To', 'URL', 'Response'],
					'error' => [
						'Connection to Zabbix server "localhost" refused. Possible reasons:',
						'1. Incorrect server IP/DNS in the "zabbix.conf.php";',
						'2. Security environment (for example, SELinux) is blocking the connection;',
						'3. Zabbix server daemon not running;',
						'4. Firewall is blocking TCP connection.',
						'Connection refused'
					]
				]
			]
		];
	}

	/**
	 * Check media type test form.
	 *
	 * @dataProvider getTestFormData
	 *
	 * @depends testPageAdministrationMediaTypes_Enable
	 */
	public function testPageAdministrationMediaTypes_TestMediaType($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'])->query('button:Test')
				->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		if (CTestArrayHelper::get($data, 'check_title')) {
			$this->assertEquals('Test media type "'.$data['name'].'"', $dialog->getTitle());
		}

		$form = $dialog->asForm();

		if (CTestArrayHelper::get($data, 'check_params')) {
			$fields = CTestArrayHelper::get($data, 'parameters', ['Send to', 'Subject', 'Message']);
			$this->assertEquals($fields, $form->getLabels()->asText());
		}

		if (CTestArrayHelper::get($data, 'webhook')) {
			$this->assertTrue($form->getField('Response')->isEnabled(false));
		}

		// Fill and submit testing form.
		if (array_key_exists('form', $data)) {
			$form->fill($data['form']);
		}
		$form->submit();

		// Check error message.
		$this->assertMessage(TEST_BAD, 'Media type test failed.', $data['error']);

		if (CTestArrayHelper::get($data, 'webhook')) {
			$form->checkValue(['Response' => 'false']);
			$this->assertEquals($form->query('id:webhook_response_type')->one()->getText(), 'Response type: String');
		}
	}

	/**
	 * Function removes saved media_type filters in order to avoid dependencies between this class test cases.
	 */
	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.media_types%\'');
	}

	/**
	 * Check Test form canceling functionality.
	 */
	public function testPageAdministrationMediaTypes_CancelTest() {
		$fields = [
			'Send to' => 'zabbix@zabbix.com',
			'Subject' => 'new subject',
			'Message' => 'new message'
		];

		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', self::$media_name)
				->query('button:Test')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Test media type "'.self::$media_name.'"', $dialog->getTitle());
		$dialog->asForm()->fill($fields);

		$dialog->getFooter()->query('button:Cancel')->one()->click();
		$dialog->ensureNotPresent();
	}

	/**
	 * Test deleting of media types in list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Delete($data) {
		if (array_key_exists('used_by_action', $data)) {
			$sql = 'SELECT NULL FROM media_type';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend and in DB.
		if (array_key_exists('used_by_action', $data)) {
			$message_title = (count(CTestArrayHelper::get($data, 'rows', [])) === 1)
				? 'Cannot delete media type'
				: 'Cannot delete media types';
			$this->assertMessage(TEST_BAD, $message_title, 'Media types used by action "'.$data['used_by_action']);

			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$message_title = (count($data['rows']) === 1) ? 'Media type deleted' : 'Media types deleted';
			$this->assertMessage(TEST_GOOD, $message_title);

			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE name IN ('.
					CDBHelper::escape($data['db_name']).')')
			);
		}
	}
}

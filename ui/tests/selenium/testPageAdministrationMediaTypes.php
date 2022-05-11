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

/**
 * @backup media_type
 */
class testPageAdministrationMediaTypes extends CWebTest {

	use TableTrait;

	/**
	 * Check basic elements on page.
	 */
	public function testPageAdministrationMediaTypes_Layout() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->assertEquals('Media types', $this->query('tag:h1')->one()->getText());

		foreach (['Enable', 'Disable', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled(false));
		}

		$count = CDBHelper::getCount('SELECT NULL FROM media_type');
		$this->assertEquals('Displaying '.$count.' of '.$count.' found', $this->query('class:table-stats')->one()->getText());

		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());

		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame(['', 'Name', 'Type', 'Status', 'Used in actions', 'Details', 'Action'], $table->getHeadersText());
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
						'Name' => 'none result'
					]
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
	 * @onAfter resetFilter
	 */
	public function testPageAdministrationMediaTypes_Filter($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'get_db_result', false)) {
			foreach (CDBHelper::getAll('SELECT name FROM media_type WHERE status='.MEDIA_STATUS_ACTIVE.
					' ORDER BY LOWER(name) ASC') as $name) {
				$data['result'][] = $name['name'];
			}
		}
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.media_types%\'');
	}

	/*
	 * Check sorting of media types by Name column.
	 *
	 * @onAfter resetFilter
	 */
	public function testPageAdministrationMediaTypes_TableSorting() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['DESC', 'ASC'] as $sorting) {
			// Change sorting by Name column.
			$table->query('link:Name')->one()->click();
			$this->page->waitUntilReady();
			// Get all media names from DB and check result on frontend.
			$names = CDBHelper::getAll('SELECT name FROM media_type ORDER BY LOWER(name) '.$sorting);
			$result = [];
			foreach ($names as $name) {
				$result[] = $name['name'];
			}
			$this->assertTableDataColumn($result);
		}
	}

	/**
	 * Disable and enable media type by link in column Status.
	 */
	public function testPageAdministrationMediaTypes_StatusLink() {
		$media_name = 'Email';

		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by column Name.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', $media_name);

		// Disable media type.
		$row->query('link:Enabled')->one()->click();
		$this->page->waitUntilReady();
		// Check result on fronted.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Media type disabled', $message->getTitle());
		// Check that Test link is disabled.
		$this->AssertTrue($row->query('button:Test')->one()->isEnabled(false));
		// Check result in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_DISABLED.
				' AND name='.CDBHelper::escape($media_name)
		));

		// Enable media type.
		$row->query('link:Disabled')->one()->click();
		$this->page->waitUntilReady();
		// Check result on fronted.
		$this->assertTrue($message->isGood());
		$this->assertEquals('Media type enabled', $message->getTitle());
		// Check that Test link is enabled.
		$this->AssertTrue($row->query('button:Test')->one()->isEnabled());
		// Check result in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.
				' AND name='.CDBHelper::escape($media_name)
		));
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
					'rows' => ['SMS'],
					'db_name' => ['SMS']
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
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		// Check number of all selected media types.
		if (array_key_exists('select_all', $data)) {
			$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM media_type').' selected',
					$this->query('id:selected_count')->one()->getText());
		}
		else {
			$this->assertEquals(count($data['rows']).' selected', $this->query('id:selected_count')->one()->getText());
		}

		$this->query('button:Disable')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$message_text = (array_key_exists('rows', $data) && count($data['rows']) === 1)
				? 'Media type disabled'
				: 'Media types disabled';
		$this->assertEquals($message_text, $message->getTitle());

		// Check the results in DB.
		if (array_key_exists('rows', $data)) {
			$this->assertEquals(count($data['rows']), CDBHelper::getCount(
				'SELECT NULL'.
				' FROM media_type'.
				' WHERE status='.MEDIA_TYPE_STATUS_DISABLED.
					' AND name IN ('.CDBHelper::escape($data['db_name']).')'
			));
		}
		else {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_ACTIVE));
		}
	}

	/**
	 * Test enabling of media types in the list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Enable($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		if (array_key_exists('select_all', $data)) {
			// Check number of all selected media types.
			$this->assertEquals(CDBHelper::getCount('SELECT NULL FROM media_type').' selected',
					$this->query('id:selected_count')->one()->getText());
		}
		else {
			$this->assertEquals(count($data['rows']).' selected', $this->query('id:selected_count')->one()->getText());
		}

		$this->query('button:Enable')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check the results in frontend.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$message_text = (array_key_exists('rows', $data) && count($data['rows']) === 1)
				? 'Media type enabled'
				: 'Media types enabled';
		$this->assertEquals($message_text, $message->getTitle());

		// Check the results in DB.
		if (array_key_exists('rows', $data)) {
			$this->assertEquals(count($data['rows']), CDBHelper::getCount(
				'SELECT NULL'.
				' FROM media_type'.
				' WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.
					' AND name IN ('.CDBHelper::escape($data['db_name']).')'
			));
		}
		else {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_DISABLED));
		}
	}

	public static function getTestFormData() {
		return [
			// Email validation.
			[
				[
					'name' => 'Email',
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
	 * Check Test form of media type.
	 *
	 * @dataProvider getTestFormData
	 * @depends testPageAdministrationMediaTypes_Enable
	 */
	public function testPageAdministrationMediaTypes_TestMediaType($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', $data['name']);
		$row->query('button:Test')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Test media type "'.$data['name'].'"', $dialog->getTitle());
		$form = $dialog->asForm();
		$fields = CTestArrayHelper::get($data, 'parameters', ['Send to', 'Subject', 'Message']);
		$this->assertEquals($fields, $form->getLabels()->asText());
		if (CTestArrayHelper::get($data, 'webhook', false)) {
			$this->assertTrue($form->getField('Response')->isEnabled(false));
		}

		// Fill and submit testing form.
		if (array_key_exists('form', $data)) {
			$form->fill($data['form']);
		}
		$form->submit();

		// Check error message.
		$message = $form->getOverlayMessage();
		$this->assertTrue($message->isBad());
		$this->assertEquals('Media type test failed.', $message->getTitle());
		if (is_array($data['error'])) {
			$this->assertEquals($data['error'], $message->getLines()->asText());
		}
		else {
			$this->assertTrue($message->hasLine($data['error']));
		}

		if (CTestArrayHelper::get($data, 'webhook', false)) {
			$form->checkValue(['Response' => 'false']);
			$this->assertEquals($form->query('id:webhook_response_type')->one()->getText(), 'Response type: String');
		}
	}

	/**
	 * Check Test form canceling functionality.
	 */
	public function testPageAdministrationMediaTypes_CancelTest() {
		$media = 'Email';
		$fields = [
			'Send to' => 'zabbix@zabbix.com',
			'Subject' => 'new subject',
			'Message' => 'new message'
		];

		$this->page->login()->open('zabbix.php?action=mediatype.list');

		// Get row by media Name and click on Test button.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', $media);
		$row->query('button:Test')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Test media type "'.$media.'"', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill($fields);

		$dialog->getFooter()->query('button:Cancel')->one()->click();
		$dialog->waitUntilNotVisible();
	}

	/**
	 * Test deleting of media types in list.
	 *
	 * @dataProvider getSelectedMediaTypeData
	 */
	public function testPageAdministrationMediaTypes_Delete($data) {
		$sql = 'SELECT NULL FROM media_type';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->selectTableRows(CTestArrayHelper::get($data, 'rows', []));

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();

		// Check the results in frontend and in DB.
		if (array_key_exists('used_by_action', $data)) {
			$this->assertTrue($message->isBad());
			$message_text = (array_key_exists('rows', $data) && count($data['rows']) === 1)
				? 'Cannot delete media type'
				: 'Cannot delete media types';
			$this->assertEquals($message_text, $message->getTitle());
			$this->assertTrue($message->hasLine('Media types used by action "'.$data['used_by_action']));
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$this->assertTrue($message->isGood());
			$this->assertEquals((count($data['rows']) === 1) ? 'Media type deleted' : 'Media types deleted',
				$message->getTitle()
			);
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT NULL'.
				' FROM media_type'.
				' WHERE name IN ('.CDBHelper::escape($data['db_name']).')'
			));
		}
	}
}

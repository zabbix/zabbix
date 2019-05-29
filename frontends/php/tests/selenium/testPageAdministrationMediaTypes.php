<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		$this->assertSame(['', 'Name', 'Type', 'Status', 'Used in actions', 'Details'], $table->getHeadersText());
	}

	public static function getFilterData() {
		return [
			// Filter by name.
			[
				[
					'filter' => [
						'Name' => 'SMS'
					],
					'result' => ['SMS', 'SMS via IP']
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
					'result' => ['Email', 'Jabber', 'SMS', 'SMS via IP']
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'result' => ['Script']
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
					'result' => ['Email']
				]
			]
		];
	}

	/**
	 * Check media types filtering.
	 *
	 * @dataProvider getFilterData
	 * @on-after resetFilter
	 */
	public function testPageAdministrationMediaTypes_Filter($data) {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	public function resetFilter() {
		DBexecute('DELETE FROM profiles WHERE idx LIKE \'%web.media_types%\'');
	}

	/*
	 * Check sorting of media types by Name column.
	 *
	 * @on-after resetFilter
	 */
	public function testPageAdministrationMediaTypes_TableSorting() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['DESC', 'ASC'] as $sorting) {
			// Change sorting by Name column.
			$table->query('link:Name')->one()->click();
			$this->page->waitUntilReady();
			// Get all media names from DB and check result on frontend.
			$names = CDBHelper::getAll('SELECT description FROM media_type ORDER BY LOWER(description) '.$sorting);
			$result = [];
			foreach ($names as $name) {
				$result[] = $name['description'];
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
		// Check result in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_DISABLED.
				' AND description='.CDBHelper::escape($media_name)
		));

		// Enable media type.
		$row->query('link:Disabled')->one()->click();
		$this->page->waitUntilReady();
		// Check result on fronted.
		$this->assertTrue($message->isGood());
		$this->assertEquals('Media type enabled', $message->getTitle());
		// Check result in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.
				' AND description='.CDBHelper::escape($media_name)
		));
	}

	public static function getSelectedMediaTypeData() {
		return [
			// Select one.
			[
				[
					'rows' => [
						'Name' => 'Email'
					],
					'db_description' => 'Email',
					'used_by_action' => 'Trigger action 3'
				]
			],
			// Select several.
			[
				[
					'rows' => [
						['Name' => 'Jabber'],
						['Name' => 'SMS']
					],
					'db_description' => ['Jabber', 'SMS']
				]
			],
			// Select all.
			[
				[
					'select_all' => true,
					'used_by_action' => 'Trigger action 3'
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
					' AND description IN ('.CDBHelper::escape($data['db_description']).')'
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
					' AND description IN ('.CDBHelper::escape($data['db_description']).')'
			));
		}
		else {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM media_type WHERE status='.MEDIA_TYPE_STATUS_DISABLED));
		}
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
			$this->assertEquals('Media types deleted', $message->getTitle());
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT NULL'.
				' FROM media_type'.
				' WHERE description IN ('.CDBHelper::escape($data['db_description']).')'
			));
		}
	}
}

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

require_once dirname(__FILE__).'/../common/testFormHost.php';

/**
 * @dataSource DiscoveredHosts
 *
 * @backup hosts
 *
 * @onBefore prepareUpdateData
 */
class testFormHostStandalone extends testFormHost {

	public $standalone = true;
	public $link = 'zabbix.php?action=host.edit&hostid=';

	public function testFormHostStandalone_Layout() {
		$this->checkHostLayout();
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostStandalone_Create($data) {
		$this->link = 'zabbix.php?action=host.edit';
		$this->checkHostCreate($data);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostStandalone_ValidationUpdate($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostStandalone_Update($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostStandalone_SimpleUpdate() {
		$this->checkHostSimpleUpdate();
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostStandalone_Clone($data) {
		$this->cloneHost($data, 'Clone');

		// Check that items aren't cloned from original host.
		$this->assertItemsDBCount($data['Host name'], 0);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostStandalone_FullClone($data) {
		$this->cloneHost($data, 'Full clone');

		// Check that items cloned from original host.
		$this->assertItemsDBCount($data['Host name'], 3);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostStandalone_Cancel($data) {
		$this->link = 'zabbix.php?action=host.edit';
		$this->checkCancel($data);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostStandalone_Delete($data) {
		$this->checkDelete($data);
	}

	public function testFormHostStandalone_DiscoveredHostLayout() {
		$this->checkDiscoveredHostLayout();
	}

	/**
	 * Test for checking templated items linked to discovered host and unlinking them.
	 * This test is implemented only for Standalone scenario.
	 */
	public function testFormHostStandalone_DiscoveredHostLinkedTemplates() {
		$filtered_results = [
			[
				'objects' => 'items',
				'form' => 'items',
				'objects_before_unlink' => [
					['Name' => 'Test of discovered host 1 template for unlink: Template1 item1'],
					['Name' => 'Test of discovered host 1 template for unlink: Template1 item2'],
					['Name' => 'Test of discovered host 2 template for clear: Template2 item1'],
					['Name' => 'Test of discovered host 2 template for clear: Template2 item2'],
					['Name' => 'Test of discovered host Template: Template item'],
					['Name' => 'Test of discovered host Template: Template item with tag']
				],
				'objects_after_unlink' =>  [
					// This template was unlinked but not cleared.
					['Name' => 'Template1 item1'],
					['Name' => 'Template1 item2'],

					// This template was not unlinked.
					['Name' => 'Test of discovered host Template: Template item'],
					['Name' => 'Test of discovered host Template: Template item with tag']
				]
			],
			[
				'objects' => 'triggers',
				'form' => 'triggersForm',
				'objects_before_unlink' => [
					['Name' => 'Test of discovered host 1 template for unlink: Template1 trigger'],
					['Name' => 'Test of discovered host 2 template for clear: Template2 trigger'],
					['Name' => 'Test of discovered host Template: Template trigger']
				],
				'objects_after_unlink' =>  [
					// This template was unlinked but not cleared.
					['Name' => 'Template1 trigger'],

					// This template was not unlinked.
					['Name' => 'Test of discovered host Template: Template trigger']
				]
			],
			[
				'objects' => 'graphs',
				'form' => 'graphForm',
				'objects_before_unlink' => [
					['Name' => 'Test of discovered host 1 template for unlink: Template1 graph'],
					['Name' => 'Test of discovered host 2 template for clear: Template2 graph'],
					['Name' => 'Test of discovered host Template: Template graph']
				],
				'objects_after_unlink' =>  [
					// This template was unlinked but not cleared.
					['Name' => 'Template1 graph'],

					// This template was not unlinked.
					['Name' => 'Test of discovered host Template: Template graph']
				]
			],
			[
				'objects' => 'host_discovery',
				'form' => 'discovery',
				'objects_before_unlink' => [
					['Name' => 'Test of discovered host 1 template for unlink: Template1 discovery rule'],
					['Name' => 'Test of discovered host 2 template for clear: Template2 discovery rule'],
					['Name' => 'Test of discovered host Template: Template discovery rule']
				],
				'objects_after_unlink' =>  [
					// This template was unlinked but not cleared.
					['Name' => 'Template1 discovery rule'],

					// This template was not unlinked.
					['Name' => 'Test of discovered host Template: Template discovery rule']
				]
			],
			[
				'objects' => 'httpconf',
				'form' => 'scenarios',
				'objects_before_unlink' => [
					['Name' => 'Test of discovered host Template: Template web scenario'],
					['Name' => 'Test of discovered host 1 template for unlink: Template web scenario 1'],
					['Name' => 'Test of discovered host 2 template for clear: Template web scenario 2']
				],
				'objects_after_unlink' =>  [
					// This template was not unlinked.
					['Name' => 'Test of discovered host Template: Template web scenario'],

					// This template was unlinked but not cleared.
					['Name' => 'Template web scenario 1']
				]
			]
		];

		$discovered_hostid = CDataHelper::get('DiscoveredHosts.discovered_hostid');
		$this->page->login();

		foreach ($filtered_results as $result) {
			$this->page->open($result['objects'].'.php?filter_set=1&filter_hostids%5B0%5D='.
					$discovered_hostid.'&context=host')->waitUntilReady();

			// Check objects on Discovered host before unlinking templates.
			$this->assertTableData($result['objects_before_unlink'], 'xpath://form[@name='.
					CXPathHelper::escapeQuotes($result['form']).']/table'
			);
		}

		// Open Discovered host form and unlink templates from it.
		$this->page->open($this->link.$discovered_hostid)->waitUntilReady();
		$form = $this->query('id:host-form')->waitUntilReady()->asForm()->one();
		$templates_table = $form->query('id:linked-templates')->asTable()->one()->waitUntilVisible();

		$template_actions = [
			'Test of discovered host 1 template for unlink' => 'Unlink',
			'Test of discovered host 2 template for clear' => 'Unlink and clear'
		];

		foreach ($template_actions as $name => $action) {
			$templates_table->findRow('Name', $name)->query('button', $action)->one()->waitUntilClickable()->click();
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Host updated');

		foreach ($filtered_results as $result) {
			// Open hosts objects and check items on Discovered host after unlinking some templates.
			$this->page->open($result['objects'].'.php?filter_set=1&filter_hostids%5B0%5D='.
					$discovered_hostid.'&context=host')->waitUntilReady();
			$this->assertTableData($result['objects_after_unlink'], 'xpath://form[@name='.
					CXPathHelper::escapeQuotes($result['form']).']/table'
			);
		}
	}
}

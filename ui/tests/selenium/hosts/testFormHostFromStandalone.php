<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @dataSource DiscoveredHosts, Proxies
 *
 * @backup hosts
 *
 * @onBefore prepareUpdateData
 */
class testFormHostFromStandalone extends testFormHost {

	public $standalone = true;
	public $link = 'zabbix.php?action=host.edit&hostid=';

	public function testFormHostFromStandalone_Layout() {
		$this->checkHostLayout();
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostFromStandalone_Create($data) {
		$this->link = 'zabbix.php?action=host.edit';
		$this->checkHostCreate($data);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostFromStandalone_ValidationUpdate($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostFromStandalone_Update($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostFromStandalone_SimpleUpdate() {
		$this->checkHostSimpleUpdate();
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostFromStandalone_Clone($data) {
		$this->cloneHost($data);

		// Check that items cloned from original host.
		$this->assertItemsDBCount($data['fields']['Host name'], $data['items']);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostFromStandalone_Cancel($data) {
		$this->link = 'zabbix.php?action=host.edit';
		$this->checkCancel($data);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostFromStandalone_Delete($data) {
		$this->checkDelete($data);
	}

	public function testFormHostFromStandalone_DiscoveredHostLayout() {
		$this->checkDiscoveredHostLayout();
	}

	/**
	 * Test for checking templated objects that are linked to a discovered host and for checking their unlinkage.
	 * This test is implemented only for Standalone scenario.
	 */
	public function testFormHostFromStandalone_DiscoveredHostLinkedTemplates() {
		$filtered_results = [
			[
				'type' => 'zabbix.php?action=item.list&',
				'form' => 'item_list',
				'objects' => [
					'before_unlink' => [
						'Test of discovered host 1 template for unlink: Template1 item1',
						'Test of discovered host 1 template for unlink: Template1 item2',
						'Test of discovered host 2 template for clear: Template2 item1',
						'Test of discovered host 2 template for clear: Template2 item2',
						'Test of discovered host Template: Template item',
						'Test of discovered host Template: Template item with tag'
					],
					'after_unlink' =>  [
						// This template was unlinked but not cleared.
						'Template1 item1',
						'Template1 item2',
						// This template was not unlinked.
						'Test of discovered host Template: Template item',
						'Test of discovered host Template: Template item with tag'
					]
				]
			],
			[
				'type' => 'zabbix.php?action=trigger.list&',
				'form' => 'trigger_form',
				'objects' => [
					'before_unlink' => [
						'Test of discovered host 1 template for unlink: Template1 trigger',
						'Test of discovered host 2 template for clear: Template2 trigger',
						'Test of discovered host Template: Template trigger'
					],
					'after_unlink' =>  [
						// This template was unlinked but not cleared.
						'Template1 trigger',
						// This template was not unlinked.
						'Test of discovered host Template: Template trigger'
					]
				]
			],
			[
				'type' => 'graphs.php?',
				'form' => 'graphForm',
				'objects' => [
					'before_unlink' => [
						'Test of discovered host 1 template for unlink: Template1 graph',
						'Test of discovered host 2 template for clear: Template2 graph',
						'Test of discovered host Template: Template graph'
					],
					'after_unlink' =>  [
						// This template was unlinked but not cleared.
						'Template1 graph',
						// This template was not unlinked.
						'Test of discovered host Template: Template graph'
					]
				]
			],
			[
				'type' => 'host_discovery.php?',
				'form' => 'discovery',
				'objects' => [
					'before_unlink' => [
						'Test of discovered host 1 template for unlink: Template1 discovery rule',
						'Test of discovered host 2 template for clear: Template2 discovery rule',
						'Test of discovered host Template: Template discovery rule'
					],
					'after_unlink' =>  [
						// This template was unlinked but not cleared.
						'Template1 discovery rule',
						// This template was not unlinked.
						'Test of discovered host Template: Template discovery rule'
					]
				]
			],
			[
				'type' => 'httpconf.php?',
				'form' => 'scenarios',
				'objects' => [
					'before_unlink' => [
						'Test of discovered host Template: Template web scenario',
						'Test of discovered host 1 template for unlink: Template web scenario 1',
						'Test of discovered host 2 template for clear: Template web scenario 2'
					],
					'after_unlink' =>  [
						// This template was not unlinked.
						'Test of discovered host Template: Template web scenario',
						// This template was unlinked but not cleared.
						'Template web scenario 1'
					]
				]
			]
		];

		$discovered_hostid = CDataHelper::get('DiscoveredHosts.discovered_hostid');
		$this->page->login();

		foreach ($filtered_results as $result) {
			$this->page->open($result['type'].'filter_set=1&filter_hostids%5B0%5D='.
					$discovered_hostid.'&context=host')->waitUntilReady();

			// Check objects on Discovered host before unlinking templates.
			$this->assertTableDataColumn($result['objects']['before_unlink'], 'Name', 'xpath://form[@name='.
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

		// TODO: Update the message details after ZBX-21366 is merged.
		$message_details = [
			'Templates "Test of discovered host 2 template for clear" unlinked from hosts "Discovered host from prototype 1".',
			'Templates "Test of discovered host 1 template for unlink" unlinked from hosts "Discovered host from prototype 1".'
		];

		$this->assertMessage(TEST_GOOD, 'Host updated', $message_details);

		foreach ($filtered_results as $result) {
			// Open hosts objects and check objects on Discovered host after unlinking some templates.
			$this->page->open($result['type'].'filter_set=1&filter_hostids%5B0%5D='.
					$discovered_hostid.'&context=host')->waitUntilReady();
			$this->assertTableDataColumn($result['objects']['after_unlink'], 'Name', 'xpath://form[@name='.
					CXPathHelper::escapeQuotes($result['form']).']/table'
			);
		}
	}
}

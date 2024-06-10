<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * Test for checking empty pages, overlays and tables.
 *
 * @onBefore prepareEmptyData
 *
 * @backup profiles, hstgrp
 */
class testNoData extends CWebTest {

	const EMPTY_HOST = 'Empty host for multiselects test';
	const EMPTY_TEMPLATE = 'Empty template for multiselects test';
	public static $empty_hostid;

	public function prepareEmptyData() {
		$hostgroups = CDataHelper::call('hostgroup.create', [
			['name' => 'Group for empty host']
		]);

		$host_groupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::call('host.create', [
			'host' => self::EMPTY_HOST,
			'groups' => [['groupid' => $host_groupid]]
		]);
		self::$empty_hostid = $hosts['hostids'][0];

		$template_groups = CDataHelper::call('templategroup.create', [
			['name' => 'Template group for empty template']
		]);

		$template_groupid = $template_groups['groupids'][0];

		CDataHelper::call('template.create', [
			'host' => self::EMPTY_TEMPLATE,
			'groups' => [['groupid' => $template_groupid]]
		]);
	}

	public static function getCheckEmptyStudData() {
		return [
			// #0 No filter selected, Proxy field check.
			[
				[
					'page' => 'Hosts',
					'checked_multiselects' => [
						'Templates' => 'popup_template_group',
						'Proxies' => null
					],
					// Fill this filter to enable 'Proxy' multiselect.
					'filter' => ['Monitored by' => 'Proxy']
				]
			],
			// #1 No filter selected, Proxy group field check.
			[
				[
					'page' => 'Hosts',
					'checked_multiselects' => [
						'Proxy groups' => null
					],
					// Fill this filter to enable 'Proxy groups' multiselect.
					'filter' => ['Monitored by' => 'Proxy group']
				]
			],
			// #2 Host's Items page.
			[
				[
					'page' => 'Hosts',
					'sub_object' => 'Items' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Hosts' => 'popup_host_group_ms',
						'Value mapping' => null
					]
				]
			],
			// #3 Host's Triggers page.
			[
				[
					'page' => 'Hosts',
					'sub_object' => 'Triggers' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Hosts' => 'popup_host_group_ms'
					]
				]
			],
			// #4 Host's Graphs page.
			[
				[
					'page' => 'Hosts',
					'sub_object' => 'Graphs' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Hosts' => 'popup_host_group_ms'
					]
				]
			],
			// #5 Host's LLDs page.
			[
				[
					'page' => 'Hosts',
					'sub_object' => 'Discovery' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Hosts' => 'popup_host_group_ms'
					]
				]
			],
			// #6 Host's Web scenarios page.
			[
				[
					'page' => 'Hosts',
					'sub_object' => 'Web',
					'check_table' => true,
					'checked_multiselects' => [
						'Hosts' => 'popup_host_group_ms'
					]
				]
			],
			// #7 Non-existing host filtered.
			[
				[
					'page' => 'Hosts',
					'filter' => [
						'Name' => 'zzz',
						'Monitored by' => 'Proxy group'
					],
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group',
						'Proxy groups' => null
					]
				]
			],
			// #8 Templates: No filter selected.
			[
				[
					'page' => 'Templates',
					'checked_multiselects' => [
						'Linked templates' => 'popup_template_group_ms'
					]
				]
			],
			// #9 Templates: Non-existing Template filtered.
			[
				[
					'page' => 'Templates',
					'filter' => ['Name' => 'zzz'],
					'check_table' => true,
					'checked_multiselects' => [
						'Linked templates' => 'popup_template_group_ms'
					]
				]
			],
			// #10 Templated Items page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Items' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms',
						'Value mapping' => null
					]
				]
			],
			// #11 Templated Triggers page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Triggers' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					]
				]
			],
			// #12 Templated Graphs page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Graphs' ,
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					]
				]
			],
			// #13 Templated Dashboards page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Dashboards',
					'check_table' => true,
					'no_filter' => true
				]
			],
			// #14 Templated LLD page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Discovery',
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					]
				]
			],
			// #15 Templated Web scenarios rules page.
			[
				[
					'page' => 'Templates',
					'sub_object' => 'Web',
					'check_table' => true,
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					]
				]
			],
			// #16 Discovery rules page.
			[
				[
					'page' => 'Discovery',
					'check_table' => true,
					'checked_multiselects' => [
						'Discovery rule' => null
					]
				]
			],
			// #17 Host form overlay.
			[
				[
					'page' => 'Host',
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					],
					'overlay_form' => true,
					'no_filter' => true
				]
			],
			// #18 Template form overlay.
			[
				[
					'page' => 'Template',
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					],
					'overlay_form' => true,
					'no_filter' => true
				]
			]
		];
	}

	/**
	 * Test function for checking empty list tables and empty multiselects' overlays, when there is no available data.
	 *
	 * @dataProvider getCheckEmptyStudData
	 */
	public function testNoData_CheckEmptyStud($data) {
		switch ($data['page']) {
			case 'Hosts':
			case 'Host':
				$url = 'zabbix.php?action=host.list';
				break;

			case 'Templates':
			case 'Template':
				$url = 'zabbix.php?action=template.list';
				break;

			case 'Discovery':
				$url = 'zabbix.php?action=discovery.view';
				break;
		}

		$this->page->login()->open($url);

		// Main objects are hosts and templates, but sub-objects are items, triggers, graphs, etc.
		if (array_key_exists('sub_object', $data)) {
			$this->query('class:list-table')->asTable()->waitUntilPresent()->one()
					->findRow('Name', ($data['page'] === 'Hosts') ? self::EMPTY_HOST : self::EMPTY_TEMPLATE)
					->getColumn($data['sub_object'])->query('tag:a')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// Some forms are opened in overlays, not on standalone pages.
		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			$this->query('class:list-table')->asTable()->waitUntilPresent()->one()
					->query('link', ($data['page'] === 'Host') ? self::EMPTY_HOST : self::EMPTY_TEMPLATE)
					->waitUntilClickable()->one()->click();
			$template_overlay = COverlayDialogElement::find()->waitUntilReady()->one();
			$form = $template_overlay->asForm();
		}

		// Not every page has filter element.
		if (!CTestArrayHelper::get($data, 'no_filter', false)) {
			$form = $this->query('name:zbx_filter')->asForm()->one();
		}

		// Fill filter to enable dependent multiselects.
		if (array_key_exists('filter', $data)) {
			$form->fill($data['filter']);
			$form->submit();
		}

		// Code for checking empty list table.
		if (CTestArrayHelper::get($data, 'check_table', false)) {
			$this->assertEquals('No data found', $this->query('xpath://table['.
					CXPathHelper::fromClass('list-table').']//div['.
					CXPathHelper::fromClass('no-data-message').']')->one()->getText()
			);
		}

		// Code for checking empty multiselects' overlays.
		if (array_key_exists('checked_multiselects', $data)) {
			foreach ($data['checked_multiselects'] as $field => $filter) {
				$overlay = $form->getField($field)->edit();

				$title = ($field === 'Linked templates')
					? 'Templates'
					: ($field === 'Discovery rule' ? 'Discovery rules' : $field) ;

				$this->assertEquals($title, $overlay->getTitle());

				if ($filter !== null) {
					$this->assertEquals('', $overlay->query('id', $filter)->one()->getValue());
					$this->assertEquals("Filter is not set\nUse the filter to display results",
							$overlay->query('class:no-data-message')->one()->getText()
					);
				}
				else {
					$this->assertEquals('No data found', $overlay->query('class:no-data-message')->one()->getText());
				}

				$overlay->close();
			}
		}

		// Not every page has filter element.
		if (!CTestArrayHelper::get($data, 'no_filter', false)) {
			$form->query('button:Reset')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// If form was opened in overlay it should be closed after test.
		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			$template_overlay->close();
		}
	}

	public static function getCheckEmptyItemsData() {
		return [
			[
				[
					'object' => 'item',
					'overlay_form' => true,
					'form' => 'id:item-form',
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			[
				[
					'object' => 'discovery rule',
					'form' => 'id:host-discovery-form',
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			[
				[
					'object' => 'trigger',
					'overlay_form' => true,
					'form' => 'id:trigger-form'
				]
			],
			[
				[
					'object' => 'graph',
					'form' => 'name:graphForm'
				]
			]
		];
	}

	/**
	 * Test function for checking the cases where no any item available for creating the entity like trigger, graph, etc.
	 *
	 * @dataProvider getCheckEmptyItemsData
	 */
	public function testNoData_CheckEmptyItems($data) {
		switch ($data['object']) {
			case 'item':
				$url = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'discovery rule':
				$url = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'trigger':
				$url = 'zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'graph':
				$url = 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;
		}

		$this->page->login()->open($url);
		$this->query('button:Create '.$data['object'])->one()->waitUntilClickable()->click();

		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			COverlayDialogElement::find()->all()->last()->waitUntilReady();
		}

		$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();

		switch ($data['object']) {
			case 'item':
			case 'discovery rule':
				$form->fill(['Type' => 'Dependent item']);
				$overlay = $form->getField('Master item')->edit();
				$this->checkEmptyOverlay($overlay);
				break;

			case 'trigger':
				$form->query('xpath:.//button[@id="insert-expression"]')->one()->waitUntilCLickable()->click();
				$expression_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$expression_overlay->query('button:Select')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($items_overlay);
				break;

			case 'graph':
				$form->getFieldContainer('Items')->query('button:Add')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($items_overlay);
				break;
		}

		// Close all dialogs.
		$dialogs = COverlayDialogElement::find()->all();
		$dialog_count = $dialogs->count();

		for ($i = $dialog_count - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}

	/**
	 * Function for testing opened overlay's title and contents.
	 *
	 * @param COverlayDialogElement    $overlay    tested overlay
	 */
	protected function checkEmptyOverlay($overlay) {
		$this->assertEquals('Items', $overlay->getTitle());
		$this->assertEquals([self::EMPTY_HOST],
				$overlay->query('xpath:.//div[@class="multiselect-control"]')->asMultiselect()->one()->getValue()
		);
		$this->assertEquals('No data found', $overlay->query('class:no-data-message')->one()->getText());
	}
}

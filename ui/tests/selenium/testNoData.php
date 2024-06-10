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
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';

/**
 * Test for checking empty pages, overlays and tables.
 *
 * @onBefore prepareEmptyData
 *
 * @backup profiles, hstgrp
 */
class testNoData extends CWebTest {

	const EMPTY_HOST = 'Empty host for multiselects test';
	const EMPTY_LLD_HOST = 'Host with empty LLD';
	const EMPTY_TEMPLATE = 'Empty template for multiselects test';
	public static $empty_hostid;
	public static $lld_hostid;
	public static $lldid;

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	public function prepareEmptyData() {
		$hostgroups = CDataHelper::call('hostgroup.create', [
			['name' => 'Group for empty host']
		]);
		$host_groupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::createHosts([
			[
				'host' => self::EMPTY_LLD_HOST,
				'groups' => [['groupid' => $host_groupid]],
				'discoveryrules' => [
					[
						'name' => 'Empty LLD',
						'key_' => 'lld_test',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			],
			[
				'host' => self::EMPTY_HOST,
				'groups' => [['groupid' => $host_groupid]]
			]
		]);

		self::$lld_hostid = $hosts['hostids'][self::EMPTY_LLD_HOST];
		self::$lldid = $hosts['discoveryruleids']['Host with empty LLD:lld_test'];
		self::$empty_hostid = $hosts['hostids'][self::EMPTY_HOST];

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
					'filter' => ['Monitored by' => 'Proxy'],
					'check_table' => true
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
					'overlay_form' => true
				]
			],
			// #18 Template form overlay.
			[
				[
					'page' => 'Template',
					'checked_multiselects' => [
						'Templates' => 'popup_template_group_ms'
					],
					'overlay_form' => true
				]
			],
			// #19 Item prototypes table.
			[
				[
					'page' => 'Item prototypes',
					'check_table' => true
				]
			],
			// #20 Trigger prototypes table.
			[
				[
					'page' => 'Trigger prototypes',
					'check_table' => true
				]
			],
			// #21 Graph prototypes table.
			[
				[
					'page' => 'Graph prototypes',
					'check_table' => true
				]
			],
			// #22 Host prototypes table.
			[
				[
					'page' => 'Host prototypes',
					'check_table' => true
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

			case 'Item prototypes':
				$url = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'Trigger prototypes':
				$url = 'zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'Graph prototypes':
				$url = 'graphs.php?context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'Host prototypes':
				$url = 'host_prototypes.php?context=host&parent_discoveryid='.self::$lldid;
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
			$overlay_form = $template_overlay->asForm();
		}

		// Fill filter to enable dependent multiselects.
		if (array_key_exists('filter', $data)) {
			$form = $this->query('name:zbx_filter')->asForm()->one();
			$form->fill($data['filter']);
			$form->submit();
		}

		// Code for checking empty list table.
		if (CTestArrayHelper::get($data, 'check_table', false)) {
			$this->assertEquals(['No data found'],
					$this->getTable('xpath://table[@class="list-table no-data"]')->getRows()->asText()
			);
		}

		// Code for checking empty multiselects' overlays.
		if (array_key_exists('checked_multiselects', $data)) {
			foreach ($data['checked_multiselects'] as $field => $filter) {
				$form = (CTestArrayHelper::get($data, 'overlay_form'))
					? $overlay_form
					: $this->query('name:zbx_filter')->asForm()->one();

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

		if (array_key_exists('filter', $data)) {
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
			// #0.
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
			// #1.
			[
				[
					'object' => 'discovery rule',
					'form' => 'id:host-discovery-form',
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			// #2.
			[
				[
					'object' => 'trigger',
					'overlay_form' => true,
					'form' => 'id:trigger-form'
				]
			],
			// #3.
			[
				[
					'object' => 'graph',
					'form' => 'name:graphForm'
				]
			],
			// #4.
			[
				[
					'object' => 'item prototype',
					'form' => 'id:item-form',
					'overlay_form' => true,
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			// #5.
			[
				[
					'object' => 'trigger prototype',
					'form' => 'id:trigger-prototype-form',
					'overlay_form' => true
				]
			],
			// #6.
			[
				[
					'object' => 'graph prototype',
					'form' => 'name:graphForm'
				]
			],
			// #7.
			[
				[
					'object' => 'host prototype',
					'form' => 'id:host-prototype-form'
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

			case 'trigger':
				$url = 'zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'graph':
				$url = 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'discovery rule':
				$url = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.self::$empty_hostid;
				break;

			case 'item prototype':
				$url = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'trigger prototype':
				$url = 'zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'graph prototype':
				$url = 'graphs.php?context=host&parent_discoveryid='.self::$lldid;
				break;

			case 'host prototype':
				$url = 'host_prototypes.php?context=host&parent_discoveryid='.self::$lldid;
				break;
		}

		$this->page->login()->open($url);
		$this->query('button:Create '.$data['object'])->one()->waitUntilClickable()->click();

		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			COverlayDialogElement::find()->all()->last()->waitUntilReady();
		}

		$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
		$host = (str_contains($data['object'], 'prototype'))
			? self::EMPTY_LLD_HOST
			: self::EMPTY_HOST;

		switch ($data['object']) {
			case 'item':
			case 'item prototype':
			case 'discovery rule':
				$form->fill(['Type' => 'Dependent item']);
				$form->getFieldContainer('Master item')->query('button:Select')->one()->waitUntilClickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$host]);
				break;

			case 'trigger':
			case 'trigger prototype':
				$form->query('xpath:.//button[@id="insert-expression"]')->one()->waitUntilCLickable()->click();
				$expression_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$expression_overlay->query('button:Select')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$host]);
				$form = $expression_overlay;
				break;

			case 'graph':
			case 'graph prototype':
				$form->getFieldContainer('Items')->query('button:Add')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$host]);
				break;

			case 'host prototype':
				$form->query('xpath:(.//button[text()="Select"])[1]')->one()->waitUntilCLickable()->click();
				$templates_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($templates_overlay, 'Templates', '');
		}

		if (in_array($data['object'], ['item prototype', 'trigger prototype', 'graph prototype'])) {
			$items_overlay->close();
			$button = (str_contains($data['object'], 'graph'))
				? 'Add prototype'
				: 'Select prototype';

			$form->query('button', $button)->one()->waitUntilClickable()->click();
			$prototype_overlay = COverlayDialogElement::find()->all()->last();
			$this->checkEmptyOverlay($prototype_overlay, 'Item prototypes');
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
	 * @param string                   $title      title of tested overlay
	 * @param string                   $host       hostname selected in overlay filter
	 */
	protected function checkEmptyOverlay($overlay, $title, $host = null) {
		$this->assertEquals($title, $overlay->getTitle());

		// For prototypes overlay multiselect with selected host shouldn't present.
		$filter_multiselect = $overlay->query('xpath:.//div[@class="multiselect-control"]');

		if (str_contains($title, 'prototypes')) {
			$this->assertFalse($filter_multiselect->exists());
		}
		else {
			$this->assertEquals($host, $filter_multiselect->asMultiselect()->one()->getValue());
		}

		$text = ($title === 'Templates')
			? "Filter is not set\nUse the filter to display results"
			: 'No data found';
		$this->assertEquals($text, $overlay->query('class:no-data-message')->one()->getText());
	}
}

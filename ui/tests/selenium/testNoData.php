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

	/**
	 * Test function for checking empty studs in map element.
	 */
	public function testNoData_MapElement() {
		$this->page->login()->open('sysmap.php?sysmapid=1');

		// Click on map element.
		$this->query('xpath://div[contains(@class, "sysmap_element ")]')->one()->waitUntilClickable()->click();
		$form = $this->query('id:selementForm')->asForm()->one();

		$cases = [
			'Trigger' => [
				'field' => 'New triggers',
				'title' => 'Triggers',
				'filter' => 'Host'
			],
			'Host' => [
				'field' => 'Host',
				'title' => 'Hosts',
				'filter' => 'Host group'
			]
		];

		foreach ($cases as $type => $parameters) {
			$form->fill(['Type' => $type]);
			$field = $form->getField($parameters['field']);

			// Checked field should be empty.
			$this->assertEquals('', $field->getValue());

			// Open overlay dialog.
			$field->query('button:Select')->one()->waitUntilClickable()->click();
			$overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();

			// Check filter label and overlay empty stud.
			$this->assertEquals($parameters['filter'], $overlay->query('tag:label')->one()->getText());
			$this->checkEmptyOverlay($overlay, $parameters['title'], '');
			$overlay->close();
		}
	}

	public static function getCheckEmptyStudData() {
		return [
			// #0 No filter selected, Proxy field check.
			[
				[
					'page' => 'Hosts',
					'checked_multiselects' => [
						'Templates',
						'Proxies'
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
						'Proxy groups'
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
						'Hosts',
						'Value mapping'
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
						'Hosts'
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
						'Hosts'
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
						'Hosts'
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
						'Hosts'
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
						'Templates',
						'Proxy groups'
					]
				]
			],
			// #8 Templates: No filter selected.
			[
				[
					'page' => 'Templates',
					'checked_multiselects' => [
						'Linked templates'
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
						'Linked templates'
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
						'Templates',
						'Value mapping'
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
						'Templates'
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
						'Templates'
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
						'Templates'
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
						'Templates'
					]
				]
			],
			// #16 Discovery rules page.
			[
				[
					'page' => 'Discovery',
					'check_table' => true,
					'checked_multiselects' => [
						'Discovery rule'
					]
				]
			],
			// #17 Host form overlay.
			[
				[
					'page' => 'Host',
					'checked_multiselects' => [
						'Templates'
					],
					'overlay_form' => true
				]
			],
			// #18 Template form overlay.
			[
				[
					'page' => 'Template',
					'checked_multiselects' => [
						'Templates'
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
			],
			// #23 SLA report page.
			[
				[
					'page' => 'SLA report',
					'check_table' => true,
					'checked_multiselects' => [
						'SLA',
						'Service'
					]
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

			case 'SLA report':
				$url = 'zabbix.php?action=slareport.list';
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
			if ($data['page'] === 'SLA report') {
				$this->assertEquals('Select SLA to display SLA report.',
						$this->query('xpath://div[@class="no-data-message"]')->one()->getText()
				);
			}
			else {
				$this->assertEquals(['No data found'],
					$this->getTable('xpath://table[@class="list-table no-data"]')->getRows()->asText()
				);
			}
		}

		// Code for checking empty multiselects' overlays.
		if (array_key_exists('checked_multiselects', $data)) {
			foreach ($data['checked_multiselects'] as $field) {
				$form = (CTestArrayHelper::get($data, 'overlay_form'))
					? $overlay_form
					: $this->query('name:zbx_filter')->asForm()->one();

				$overlay = $form->getField($field)->edit();

				$title = ($field === 'Linked templates')
					? 'Templates'
					: ($field === 'Discovery rule' ? 'Discovery rules' : $field);
				$this->checkEmptyOverlay($overlay, $title, '');
				$overlay->close();
			}
		}

		if (array_key_exists('filter', $data)) {
			$form->query('button:Reset')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// If form was opened in overlay it should be closed after test.
		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			$this->closeAllDialogs();
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
				$items_overlay = COverlayDialogElement::find()->all()->last();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$host]);
				$form = $expression_overlay;
				break;

			case 'graph':
			case 'graph prototype':
				$form->getFieldContainer('Items')->query('button:Add')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$host]);
				break;

			case 'host prototype':
				$form->query('xpath:(.//button[text()="Select"])[1]')->one()->waitUntilCLickable()->click();
				$templates_overlay = COverlayDialogElement::find()->all()->last();
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

		$this->closeAllDialogs();
	}

	/**
	 * Function for testing opened overlay's title and contents.
	 *
	 * @param COverlayDialogElement    $overlay    tested overlay
	 * @param string                   $title      title of tested overlay
	 * @param string                   $filter     hostname selected in overlay filter
	 */
	protected function checkEmptyOverlay($overlay, $title, $filter = null) {
		$this->assertEquals($title, $overlay->waitUntilReady()->getTitle());

		// For SLA overlays filter is not multiselect, but input.
		$filter_selector = (in_array($title, ['SLA', 'Service']))
			? $overlay->query('id:services-filter-name')
			: $overlay->query('xpath:.//div[@class="multiselect-control"]')->asMultiselect();

		// There are overlays where additional filter exists, and there are some - where it shouldn't exist.
		if (in_array($title, ['Proxies', 'Proxy groups', 'Value mapping', 'Discovery rules', 'SLA', 'Item prototypes'])) {
			$this->assertFalse($filter_selector->exists());
		}
		else {
			$this->assertEquals($filter, $filter_selector->one()->getValue());
		}

		$text = (in_array($title, ['Templates', 'Hosts', 'Triggers']))
			? "Filter is not set\nUse the filter to display results"
			: 'No data found';
		$this->assertEquals($text, $overlay->query('class:no-data-message')->one()->getText());
	}

	/**
	 * Function for closing all overlays on the page.
	 */
	protected function closeAllDialogs() {
		$dialogs = COverlayDialogElement::find()->all();
		$dialog_count = $dialogs->count();

		for ($i = $dialog_count - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}
}

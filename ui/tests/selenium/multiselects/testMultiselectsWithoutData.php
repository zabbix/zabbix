<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once __DIR__.'/../common/testMultiselectDialogs.php';

/**
 * Test for checking empty multiselects' overlays.
 *
 * @onBefore clearData, prepareEmptyData
 *
 * @backup profiles
 */
class testMultiselectsWithoutData extends testMultiselectDialogs {

	const EMPTY_HOST = 'Empty host for multiselects test';
	const EMPTY_LLD_HOST = 'Host with empty LLD';
	const EMPTY_TEMPLATE = 'Empty template for multiselects test';
	const EMPTY_LLD_TEMPLATE = 'Template with empty LLD';
	const SCRIPT = 'Script for Actions';
	const TEMPLATES_MULTISELECT = ['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]];
	const HOSTS_MULTISELECT = ['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]];
	protected static $empty_hostid;
	protected static $empty_templateid;
	protected static $host_lldid;
	protected static $template_lldid;

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	/**
	 * Function for finding and deleting data created before with previous tests.
	 */
	public function clearData() {
		$delete_data = [
			// Delete Services.
			[
				'api' => 'service.delete',
				'sql' => 'SELECT * FROM services',
				'column' => 'serviceid'
			],
			// Delete Proxies and Proxy groups, connected Hosts, Actions and Discovery rules.
			[
				'api' => 'drule.delete',
				'sql' => 'SELECT * FROM drules',
				'column' => 'druleid'
			],
			[
				'api' => 'host.delete',
				'sql' => 'SELECT * FROM hosts WHERE monitored_by=1 OR monitored_by=2',
				'column' => 'hostid'
			],
			[
				'api' => 'action.delete',
				'sql' => 'SELECT * FROM actions',
				'column' => 'actionid'
			],
			[
				'api' => 'proxy.delete',
				'sql' => 'SELECT * FROM proxy',
				'column' => 'proxyid'
			],
			[
				'api' => 'proxygroup.delete',
				'sql' => 'SELECT * FROM proxy_group',
				'column' => 'proxy_groupid'
			],
			// Delete SLA.
			[
				'api' => 'sla.delete',
				'sql' => 'SELECT * FROM sla',
				'column' => 'slaid'
			]
		];

		foreach ($delete_data as $data) {
			$ids = CDBHelper::getColumn($data['sql'], $data['column']);
			if ($ids !== []) {
				CDataHelper::call($data['api'], array_values($ids));
			}
		}
	}

	public function prepareEmptyData() {
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for empty host']]);
		$host_groupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::createHosts([
			[
				'host' => self::EMPTY_LLD_HOST,
				'groups' => [['groupid' => $host_groupid]],
				'discoveryrules' => [
					[
						'name' => 'Empty LLD',
						'key_' => 'lld_test',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => self::EMPTY_HOST,
				'groups' => [['groupid' => $host_groupid]]
			]
		]);

		self::$host_lldid = $hosts['discoveryruleids'][self::EMPTY_LLD_HOST.':lld_test'];
		self::$empty_hostid = $hosts['hostids'][self::EMPTY_HOST];

		$template_groups = CDataHelper::call('templategroup.create', [
				['name' => 'Template group for empty template']
		]);

		$template_groupid = $template_groups['groupids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => self::EMPTY_LLD_TEMPLATE,
				'groups' => [['groupid' => $template_groupid]],
				'discoveryrules' => [
					[
						'name' => 'Empty template LLD',
						'key_' => 'lld_test',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			],
			[
				'host' => self::EMPTY_TEMPLATE,
				'groups' => [['groupid' => $template_groupid]]
			]
		]);
		self::$template_lldid = $templates['discoveryruleids'][self::EMPTY_LLD_TEMPLATE.':lld_test'];
		self::$empty_templateid = $templates['templateids'][self::EMPTY_TEMPLATE];

		CDataHelper::call('script.create', [
			[
				'name' => self::SCRIPT,
				'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'command' => 'test script'
			]
		]);
	}

	/**
	 * Test function for checking empty studs in map element.
	 */
	public function testMultiselectsWithoutData_MapElement() {
		$this->page->login()->open('sysmap.php?sysmapid=1');

		// Click on map element.
		$this->query('xpath://div[contains(@class, "sysmap_element")]')->one()->waitUntilClickable()->click();
		$form = $this->query('id:selementForm')->asForm()->one();

		$overlays = [
			'Trigger' => [
				'New triggers' => ['title' => 'Triggers', 'empty' => true, 'filter' => ['Host' => '']],
				'Host' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']],
				'Host group' => ['title' => 'Host groups']
			],
			'Host' => [
				'Host' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']],
				'Host group' => ['title' => 'Host groups']
			]
		];

		foreach ($overlays as $type => $multiselect) {
			$form->fill(['Type' => $type]);

			// Checked field should be empty.
			$this->assertEquals('', $form->getField(key($multiselect))->getValue());

			// Check overlay dialog.
			$this->checkMultiselectDialogs($form, [$multiselect]);
		}
	}

	public static function getActionOverlaysData() {
		return [
			// #0 Trigger actions.
			[
				'source' => EVENT_SOURCE_TRIGGERS,
				'tabs' => [
					'Action' => [
						'Conditions' => [
							'Trigger' => [
								'Triggers' => ['title' => 'Triggers', 'empty' => true, 'filter' => ['Host' => '']]
							],
							'Host' => self::HOSTS_MULTISELECT,
							'Template' => self::TEMPLATES_MULTISELECT
						]
					],
					'Operations' => [
						'Operations' => [
							self::SCRIPT => self::HOSTS_MULTISELECT
						],
						'Recovery operations' => [
							self::SCRIPT => self::HOSTS_MULTISELECT
						],
						'Update operations' => [
							self::SCRIPT => self::HOSTS_MULTISELECT
						]
					]
				]
			],
			// #1 Service actions.
			[
				'source' => EVENT_SOURCE_SERVICE,
				'tabs' => [
					'Action' => [
						'Conditions' => [
							'Service' => [
								'Services' => ['title' => 'Services', 'empty' => true, 'filter' => ['Name' => '']]
							]
						]
					]
				]
			],
			// #2 Discovery actions.
			[
				'source' => EVENT_SOURCE_DISCOVERY,
				'tabs' => [
					'Action' => [
						'Conditions' => [
							'Proxy' => [
								'Proxy' => ['title' => 'Proxies', 'empty' => true, 'filter' => null]
							]
						]
					],
					'Operations' => [
						'Operations' => [
							'Link template' => self::TEMPLATES_MULTISELECT,
							'Unlink template' => self::TEMPLATES_MULTISELECT,
							self::SCRIPT => self::HOSTS_MULTISELECT
						]
					]
				]
			],
			// #3 Autoregistration actions.
			[
				'source' => EVENT_SOURCE_AUTOREGISTRATION,
				'tabs' => [
					'Action' => [
						'Conditions' => [
							'Proxy' => [
								'Proxy' => ['title' => 'Proxies', 'empty' => true, 'filter' => null]
							]
						]
					],
					'Operations' => [
						'Operations' => [
							'Link template' => self::TEMPLATES_MULTISELECT,
							'Unlink template' => self::TEMPLATES_MULTISELECT,
							self::SCRIPT => self::HOSTS_MULTISELECT
						]
					]
				]
			],
			// #4 Internal actions.
			[
				'source' => EVENT_SOURCE_INTERNAL,
				'tabs' => [
					'Action' => [
						'Conditions' => [
							'Host' => self::HOSTS_MULTISELECT,
							'Template' => self::TEMPLATES_MULTISELECT
						]
					]
				]
			]
		];
	}

	/**
	 * Test function for checking empty multiselects' overlays in Action creation form.
	 *
	 * @dataProvider getActionOverlaysData
	 */
	public function testMultiselectsWithoutData_ActionOverlays($source, $tabs) {
		$this->page->login()->open('zabbix.php?action=action.list&filter_rst=1&eventsource='.$source);
		$this->query('button:Create action')->one()->waitUntilClickable()->click();
		$action_form = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();

		foreach ($tabs as $tab => $fields) {
			$action_form->selectTab($tab);

			foreach ($fields as $field => $options) {
				// Open Condition or Operations dialog.
				$action_form->getFieldContainer($field)->query('button:Add')->one()->waitUntilClickable()->click();
				$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

				// Open Conditions, Operations, Recovery or Update overlays one by one and fill corresponding options.
				foreach ($options as $option => $multiselect) {
					$fields = ($field === 'Conditions')
						? ['Type' => $option]
						: ['Operation' => $option];

					$dialog->asForm()->fill($fields);
					$dialog->waitUntilReady();
					$condition_form = $dialog->query('xpath:.//form')->one()->asForm();
					$this->checkMultiselectDialogs($condition_form, [$multiselect]);
				}
				$dialog->close();
			}
		}
		COverlayDialogElement::closeAll(true);
	}

	public static function getEmptyMultiselectsData() {
		return [
			// #0 No filter selected, Proxy field check.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					// Fill this filter to enable 'Proxy' multiselect.
					'filter' => ['Monitored by' => 'Proxy'],
					'checked_multiselects' => [
						self::TEMPLATES_MULTISELECT,
						['Proxies' => ['title' => 'Proxies', 'empty' => true]]
					]
				]
			],
			// #1 No filter selected, Proxy group field check.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					// Fill this filter to enable 'Proxy groups' multiselect.
					'filter' => ['Monitored by' => 'Proxy group'],
					'checked_multiselects' => [
						['Proxy groups' => ['title' => 'Proxy groups', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #2 Host's Items page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Items',
					'checked_multiselects' => [
						self::HOSTS_MULTISELECT,
						['Value mapping' => ['title' => 'Value mapping', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #3 Host's Triggers page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Triggers',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #4 Host's Graphs page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Graphs',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #5 Host's LLDs page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Discovery',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #6 Host's Web scenarios page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Web',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #7 Non-existing host filtered.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'filter' => [
						'Name' => 'zzz',
						'Monitored by' => 'Proxy group'
					],
					'checked_multiselects' => [
						self::TEMPLATES_MULTISELECT,
						['Proxy groups' => ['title' => 'Proxy groups', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #8 Templates: No filter selected.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'checked_multiselects' => [
						['Linked templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
				]
			],
			// #9 Templates: Non-existing Template filtered.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'filter' => ['Name' => 'zzz'],
					'checked_multiselects' => [
						['Linked templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
				]
			],
			// #10 Templated Items page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Items',
					'checked_multiselects' => [
						self::TEMPLATES_MULTISELECT,
						['Value mapping' => ['title' => 'Value mapping', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #11 Templated Triggers page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Triggers',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT]
				]
			],
			// #12 Templated Graphs page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Graphs',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT]
				]
			],
			// #13 Templated LLD page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Discovery',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT]
				]
			],
			// #14 Templated Web scenarios rules page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Web',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT]
				]
			],
			// #15 Discovery rules page.
			[
				[
					'object' => 'Discovery',
					'url' => 'zabbix.php?action=discovery.view&filter_rst=1',
					'checked_multiselects' => [
						['Discovery rule' => ['title' => 'Discovery rules', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #16 Host form overlay.
			[
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT],
					'overlay_form' => true
				]
			],
			// #17 Template form overlay.
			[
				[
					'object' => 'template',
					'url' => 'zabbix.php?action=template.list',
					'checked_multiselects' => [self::TEMPLATES_MULTISELECT],
					'overlay_form' => true
				]
			],
			// #18 SLA report page.
			[
				[
					'object' => 'SLA report',
					'url' => 'zabbix.php?action=slareport.list&filter_rst=1',
					'checked_multiselects' => [
						['SLA' => ['title' => 'SLA', 'empty' => true, 'filter' => null]],
						['Service' => ['title' => 'Service', 'empty' => true, 'filter' => ['Name' => '']]]
					]
				]
			],
			// #19 Availability report page.
			[
				[
					'object' => 'Availability report',
					'url' => 'zabbix.php?action=availabilityreport.list&filter_rst=1',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #20 Top 100 triggers.
			[
				[
					'object' => 'Top 100 triggers',
					'url' => 'zabbix.php?action=toptriggers.list&filter_rst=1',
					'checked_multiselects' => [self::HOSTS_MULTISELECT]
				]
			],
			// #25 Maintenance period form overlay.
			[
				[
					'object' => 'maintenance period',
					'url' => 'zabbix.php?action=maintenance.list&filter_rst=1',
					'checked_multiselects' => [self::HOSTS_MULTISELECT],
					'overlay_form' => true
				]
			]
		];
	}

	/**
	 * Test function for checking empty multiselects' overlays, when there is no available data.
	 *
	 * @dataProvider getEmptyMultiselectsData
	 */
	public function testMultiselectsWithoutData_CheckEmptyMultiselects($data) {
		$this->page->login()->open($data['url']);

		// Main objects are hosts and templates, but sub-objects are items, triggers, graphs, etc.
		if (array_key_exists('sub_object', $data)) {
			$this->query('class:list-table')->asTable()->waitUntilPresent()->one()
					->findRow('Name', ($data['object'] === 'Hosts') ? self::EMPTY_HOST : self::EMPTY_TEMPLATE)
					->getColumn($data['sub_object'])->query('tag:a')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// Some forms are opened in overlays, not on standalone pages.
		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			$this->query('button:Create '.$data['object'])->waitUntilClickable()->one()->click();
			$overlay = COverlayDialogElement::find()->waitUntilReady()->one();
			$form = $overlay->asForm();
		}
		else {
			$form = $this->query('name:zbx_filter')->asForm()->one();
			CFilterElement::find()->one()->selectTab('Filter');
		}

		// Fill filter to enable dependent multiselects.
		if (array_key_exists('filter', $data)) {
			$form->fill($data['filter']);
			$form->submit();
			$form->invalidate();
		}

		$this->checkMultiselectDialogs($form, $data['checked_multiselects']);

		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			$overlay->close();
		}
	}

	public static function getCheckEmptyItemsData() {
		return [
			// Host objects.
			// #0.
			[
				[
					'object' => 'item',
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D=',
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
					'url' => 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D=',
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
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids%5B0%5D=',
					'overlay_form' => true,
					'form' => 'id:trigger-form'
				]
			],
			// #3.
			[
				[
					'object' => 'graph',
					'url' => 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D=',
					'form' => 'name:graphForm'
				]
			],
			// #4.
			[
				[
					'object' => 'item prototype',
					'url' => 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid=',
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
					'url' => 'zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid=',
					'form' => 'id:trigger-prototype-form',
					'overlay_form' => true
				]
			],
			// #6.
			[
				[
					'object' => 'graph prototype',
					'url' => 'graphs.php?context=host&parent_discoveryid=',
					'form' => 'name:graphForm'
				]
			],
			// #7.
			[
				[
					'object' => 'host prototype',
					'url' => 'host_prototypes.php?context=host&parent_discoveryid=',
					'form' => 'id:host-prototype-form'
				]
			],
			// Template objects.
			// #8.
			[
				[
					'object' => 'item',
					'url' => 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D=',
					'overlay_form' => true,
					'form' => 'id:item-form',
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			// #9.
			[
				[
					'object' => 'discovery rule',
					'url' => 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D=',
					'form' => 'id:host-discovery-form',
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			// #10.
			[
				[
					'object' => 'trigger',
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&context=template&filter_hostids%5B0%5D=',
					'overlay_form' => true,
					'form' => 'id:trigger-form',
					'filter_label' => 'Template'
				]
			],
			// #11.
			[
				[
					'object' => 'graph',
					'url' => 'graphs.php?filter_set=1&context=template&filter_hostids%5B0%5D=',
					'form' => 'name:graphForm'
				]
			],
			// #12.
			[
				[
					'object' => 'item prototype',
					'url' => 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid=',
					'form' => 'id:item-form',
					'overlay_form' => true,
					'fields' => [
						'Type' => 'Dependent item'
					]
				]
			],
			// #13.
			[
				[
					'object' => 'trigger prototype',
					'url' => 'zabbix.php?action=trigger.prototype.list&context=template&parent_discoveryid=',
					'form' => 'id:trigger-prototype-form',
					'overlay_form' => true,
					'filter_label' => 'Template'
				]
			],
			// #14.
			[
				[
					'object' => 'graph prototype',
					'url' => 'graphs.php?context=template&parent_discoveryid=',
					'form' => 'name:graphForm'
				]
			],
			// #15.
			[
				[
					'object' => 'host prototype',
					'url' => 'host_prototypes.php?context=template&parent_discoveryid=',
					'form' => 'id:host-prototype-form'
				]
			]
		];
	}

	/**
	 * Test function for checking the cases where no any item available for creating the entity like trigger, graph, etc.
	 *
	 * @dataProvider getCheckEmptyItemsData
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
	 */
	public function testMultiselectsWithoutData_CheckEmptyItems($data) {
		$context_host = str_contains($data['url'], 'context=host');

		$url = (str_contains($data['url'], 'discoveryid='))
			? ($data['url'].($context_host ? self::$host_lldid : self::$template_lldid))
			: ($data['url'].($context_host ? self::$empty_hostid : self::$empty_templateid));

		$this->page->login()->open($url);
		$this->query('button:Create '.$data['object'])->one()->waitUntilClickable()->click();

		if (CTestArrayHelper::get($data, 'overlay_form', false)) {
			COverlayDialogElement::find()->all()->last()->waitUntilReady();
		}

		$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
		$host = (str_contains($data['object'], 'prototype'))
			? ($context_host ? self::EMPTY_LLD_HOST : self::EMPTY_LLD_TEMPLATE)
			: ($context_host ? self::EMPTY_HOST : self::EMPTY_TEMPLATE);

		$filter_label = CTestArrayHelper::get($data, 'filter_label', 'Host');
		$filter = ['filter' => [$filter_label => '']];

		switch ($data['object']) {
			case 'item':
			case 'item prototype':
			case 'discovery rule':
				$form->fill(['Type' => 'Dependent item']);
				$this->checkMultiselectDialogs($form, [['Master item' => ['title' => 'Items', 'empty' => true, $filter]]]);
				break;

			case 'trigger':
			case 'trigger prototype':
				$form->query('xpath:.//button[@id="insert-expression"]')->one()->waitUntilCLickable()->click();
				$expression_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();

				// Item selection in trigger's expression is not multiselect element, but just input and buttons.
				$expression_overlay->query('button:Select')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();;

				$this->checkEmptyOverlay($items_overlay, 'Items', [$filter_label => [$host]]);
				$form = $expression_overlay;
				break;

			case 'graph':
			case 'graph prototype':
				$form->getFieldContainer('Items')->query('button:Add')->one()->waitUntilCLickable()->click();
				$items_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->checkEmptyOverlay($items_overlay, 'Items', [$filter_label => [$host]]);
				break;

			case 'host prototype':
				$form = $this->query($data['form'])->asForm(['normalized' => true])->one();
				$this->checkMultiselectDialogs($form, [self::TEMPLATES_MULTISELECT]);
				break;
		}

		if (in_array($data['object'], ['item prototype', 'trigger prototype', 'graph prototype'])) {
			$button = (str_contains($data['object'], 'graph'))
				? 'Add prototype'
				: 'Select prototype';

			$form->query('button', $button)->one()->waitUntilClickable()->click();
			$prototype_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$this->checkEmptyOverlay($prototype_overlay, 'Item prototypes');
		}

		COverlayDialogElement::closeAll(true);
	}

	/**
	 * Function for testing opened overlay's title and contents.
	 *
	 * @param COverlayDialogElement $overlay    tested overlay
	 * @param string                $title      title of tested overlay
	 * @param string                $filter     hostname selected in overlay filter
	 */
	protected function checkEmptyOverlay($overlay, $title, $filter = null) {
		$this->checkErrorsAndTitle($overlay, $title);
		$this->checkOverlayFilter($overlay, $title, $filter);
		$this->checkOverlayStud($overlay, $title);
		$overlay->close();
	}
}

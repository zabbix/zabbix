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
require_once dirname(__FILE__).'/common/testMultiselectDialogs.php';

/**
 * Test for checking empty pages, overlays and tables.
 *
 * @onBefore prepareEmptyData
 *
 * @backup profiles, hstgrp, scripts
 */
class testNoData extends testMultiselectDialogs {

	const EMPTY_HOST = 'Empty host for multiselects test';
	const EMPTY_LLD_HOST = 'Host with empty LLD';
	const EMPTY_TEMPLATE = 'Empty template for multiselects test';
	const EMPTY_LLD_TEMPLATE = 'Template with empty LLD';
	const SCRIPT = 'Script for Actions';
	public static $empty_hostid;
	public static $empty_templateid;
	public static $host_lldid;
	public static $template_lldid;


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
	public function testNoData_MapElement() {
		$this->page->login()->open('sysmap.php?sysmapid=1');

		// Click on map element.
		$this->query('xpath://div[contains(@class, "sysmap_element")]')->one()->waitUntilClickable()->click();
		$form = $this->query('id:selementForm')->asForm()->one();

		$overlays = [
			'Trigger' => [
				'New triggers' => ['title' => 'Triggers', 'empty' => true, 'filter' => ['Host' => '']]
			],
			'Host' => [
				'Host' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
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
				[
					'url' => 'zabbix.php?action=action.list&filter_rst=1&eventsource=0',
					'tabs' => [
						'Action' => [
							'Conditions' => [
								'Trigger' => [
									'Triggers' => ['title' => 'Triggers', 'empty' => true, 'filter' => ['Host' => '']]
								],
								'Host' => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								],
								'Template'  => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								]
							]
						],
						'Operations' => [
							'Operations' => [
								self::SCRIPT => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								]
							],
							'Recovery operations' => [
								self::SCRIPT => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								]
							],
							'Update operations' => [
								self::SCRIPT => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								]
							]
						]
					]
				]
			],
			// #1 Service actions.
			[
				[
					'url' => 'zabbix.php?action=action.list&filter_rst=1&eventsource=4',
					'tabs' => [
						'Action' => [
							'Conditions' => [
								'Service' => [
									'Services' => ['title' => 'Services', 'empty' => true, 'filter' => ['Name' => '']]
								]
							]
						]
					]
				]
			],
			// #2 Discovery actions.
			[
				[
					'url' => 'zabbix.php?action=action.list&filter_rst=1&eventsource=1',
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
								'Link template' => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								],
								'Unlink template' => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								],
								self::SCRIPT => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								]
							]
						]
					]
				]
			],
			// #3 Autoregistration actions.
			[
				[
					'url' => 'zabbix.php?action=action.list&filter_rst=1&eventsource=2',
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
								'Link template' => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								],
								'Unlink template' => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								],
								self::SCRIPT => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								]
							]
						]
					]
				]
			],
			// #4 Internal actions.
			[
				[
					'url' => 'zabbix.php?action=action.list&filter_rst=1&eventsource=3',
					'tabs' => [
						'Action' => [
							'Conditions' => [
								'Host' => [
									'Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]
								],
								'Template'  => [
									'Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]
								]
							]
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
	public function testNoData_ActionOverlays($data) {
		$this->page->login()->open($data['url']);
		$this->query('button:Create action')->one()->waitUntilClickable()->click();
		$action_form = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();

		foreach ($data['tabs'] as $tab => $fields) {
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

	public static function getCheckEmptyMultiselectsData() {
		return [
			// #0 No filter selected, Proxy field check.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					// Fill this filter to enable 'Proxy' multiselect.
					'filter' => ['Monitored by' => 'Proxy'],
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]],
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
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]],
						['Value mapping' => ['title' => 'Value mapping', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #3 Host's Triggers page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Triggers' ,
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
				]
			],
			// #4 Host's Graphs page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Graphs' ,
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
				]
			],
			// #5 Host's LLDs page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Discovery' ,
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
				]
			],
			// #6 Host's Web scenarios page.
			[
				[
					'object' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'sub_object' => 'Web',
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
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
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]],
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
					'sub_object' => 'Items' ,
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]],
						['Value mapping' => ['title' => 'Value mapping', 'empty' => true, 'filter' => null]]
					]
				]
			],
			// #11 Templated Triggers page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Triggers' ,
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
				]
			],
			// #12 Templated Graphs page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Graphs' ,
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
				]
			],
			// #13 Templated LLD page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Discovery',
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
				]
			],
			// #14 Templated Web scenarios rules page.
			[
				[
					'object' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'sub_object' => 'Web',
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					]
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
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					],
					'overlay_form' => true
				]
			],
			// #17 Template form overlay.
			[
				[
					'object' => 'template',
					'url' => 'zabbix.php?action=template.list',
					'checked_multiselects' => [
						['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]
					],
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
					'url' => 'report2.php?filter_rst=1',
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
				]
			],
			// #20 Top 100 triggers.
			[
				[
					'object' => 'Top 100 triggers',
					'url' => 'zabbix.php?action=toptriggers.list&filter_rst=1',
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					]
				]
			],
			// #25 Maintenance period form overlay.
			[
				[
					'object' => 'maintenance period',
					'url' => 'zabbix.php?action=maintenance.list&filter_rst=1',
					'checked_multiselects' => [
						['Hosts' => ['title' => 'Hosts', 'empty' => true, 'filter' => ['Host group' => '']]]
					],
					'overlay_form' => true
				]
			]
		];
	}

	/**
	 * Test function for checking empty multiselects' overlays, when there is no available data.
	 *
	 * @dataProvider getCheckEmptyMultiselectsData
	 */
	public function testNoData_CheckEmptyMultiselects($data) {
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

	public static function getCheckEmptyPagesData() {
		return [
			//Hosts.
			// #0 Empty hosts table.
			[
				[
					'page' => 'Hosts',
					'url' => 'zabbix.php?action=host.list&filter_rst=1',
					'filter' => ['Name' => 'zzz']
				]
			],
			// #1 Empty hosts' items table.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #2 Empty hosts' triggers table.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&context=host&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #3 Empty hosts' graphs table.
			[
				[
					'url' => 'graphs.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #4 Empty hosts' LLD table.
			[
				[
					'url' => 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #5 Empty hosts' Web scenarios table.
			[
				[
					'url' => 'httpconf.php?filter_set=1&context=host&filter_hostids%5B0%5D='
				]
			],
			// #6 Item prototypes table.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='
				]
			],
			// #7 Trigger prototypes table.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='
				]
			],
			// #8 Graph prototypes table.
			[
				[
					'url' => 'graphs.php?context=host&parent_discoveryid='
				]
			],
			// #9 Host prototypes table.
			[
				[
					'url' => 'host_prototypes.php?context=host&parent_discoveryid='
				]
			],
			//Templates.
			// #10 Empty templates table.
			[
				[
					'page' => 'Templates',
					'url' => 'zabbix.php?action=template.list&filter_rst=1',
					'filter' => ['Name' => 'zzz']
				]
			],
			// #11 Empty templates' items table.
			[
				[
					'url' => 'zabbix.php?action=item.list&context=template&filter_set=1&filter_hostids%5B0%5D='
				]
			],
			// #12 Empty templates' triggers table.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #13 Empty templates' graphs table.
			[
				[
					'url' => 'graphs.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #14 Empty templates' LLD table.
			[
				[
					'url' => 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #15 Empty templates' Web scenarios table.
			[
				[
					'url' => 'httpconf.php?filter_set=1&context=template&filter_hostids%5B0%5D='
				]
			],
			// #16 Empty templates' dashboards table.
			[
				[
					'url' => 'zabbix.php?action=template.dashboard.list&templateid='
				]
			],
			//Other pages.
			// #17 Discovery rules table.
			[
				[
					'page' => 'Status of discovery',
					'url' => 'zabbix.php?action=discovery.view'
				]
			],
			// #18 Empty SLA page.
			[
				[
					'page' => 'SLA',
					'url' => 'zabbix.php?action=sla.list',
				]
			],
			// #19 Empty SLA report page.
			[
				[
					'page' => 'SLA report',
					'url' => 'zabbix.php?action=slareport.list',
				]
			],
			// #20 Empty Top 100 triggers page.
			[
				[
					'page' => 'Top 100 triggers',
					'url' => 'zabbix.php?action=toptriggers.list'
				]
			],
			// #21 Empty Maintenances page.
			[
				[
					'page' => 'Maintenance periods',
					'url' => 'zabbix.php?action=maintenance.list',
					'filter' => ['Name' => 'zzz']
				]
			]
		];
	}

	/**
	 * Test function for checking empty list tables.
	 *
	 * @dataProvider getCheckEmptyPagesData
	 */
	public function testNoData_CheckEmptyPages($data) {
		$context_host = str_contains($data['url'], 'context=host');

		if  (in_array(CTestArrayHelper::get($data, 'page'), ['Hosts', 'Templates', 'SLA', 'SLA report',
				'Top 100 triggers', 'Maintenance periods', 'Status of discovery'])) {
			$url = $data['url'];
		}
		else {
			$url = (str_contains($data['url'], 'discoveryid='))
				? ($data['url'].($context_host ? self::$host_lldid :self::$template_lldid))
				: ($data['url'].($context_host ? self::$empty_hostid : self::$empty_templateid));
		}

		$this->page->login()->open($url);

		if (array_key_exists('filter', $data)) {
			$form = $this->query('name:zbx_filter')->asForm()->one();
			CFilterElement::find()->one()->selectTab('Filter');
			$form->fill($data['filter']);
			$form->submit();
		}

		if (CTestArrayHelper::get($data, 'page') === 'SLA report') {
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
	 */
	public function testNoData_CheckEmptyItems($data) {
		$context_host = str_contains($data['url'], 'context=host');

		$url = (str_contains($data['url'], 'discoveryid='))
			? ($data['url'].($context_host ? self::$host_lldid :self::$template_lldid))
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
				$this->checkMultiselectDialogs($form,
						[['Templates' => ['title' => 'Templates', 'empty' => true, 'filter' => ['Template group' => '']]]]
				);
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
	 * @param COverlayDialogElement    $overlay    tested overlay
	 * @param string                   $title      title of tested overlay
	 * @param string                   $filter     hostname selected in overlay filter
	 */
	protected function checkEmptyOverlay($overlay, $title, $filter = null) {
		$this->checkErrorsAndTitle($overlay, $title);
		$this->checkOverlayFilter($overlay, $title, $filter);
		$this->checkOverlayStud($overlay, $title);
		$overlay->close();
	}
}

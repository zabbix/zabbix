<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/common/testFormPreprocessing.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup services
 * @backup profiles
 */
class testFormTabIndicators extends CWebTest {

	use PreprocessingTrait;

	public function getTabData() {
		return [
			// Template configuration form tab data.
			[
				[
					'url' => 'templates.php?form=create',
					'form' => 'name:templatesForm',
					'tabs' => [
						[
							'name' => 'Linked templates',
							'entries' => [
								'Link new templates' => ['Empty template', 'Form test template', 'Docker']
							],
							'field_type' => 'DEV-1671', // Change field type to multiselect once DEV-1671 is merged.
							'count' => 3
						],
						[
							'name' => 'Tags',
							'entries' => [
								[
									'tag' => '!@#$%^&*()_+<>,.\/',
									'value' => '!@#$%^&*()_+<>,.\/'
								],
								[
									'tag' => 'tag1',
									'value' => 'value1'
								],
								[
									'tag' => 'tag2'
								]
							],
							'table_selector' => 'id:tags-table',
							'field_type' => 'multifield_table',
							'count' => 3
						],
						[
							'name' => 'Macros',
							'entries' => [
								[
									'macro' => '{$123}',
									'Value' => '123'
								],
								[
									'macro' => '{$ABC}'
								],
								[
									'macro' => '{$ABC123}',
									'Value' => 'ABC123',
									'description' => 'ABC-123'
								]
							],
							'table_selector' => 'id:tbl_macros',
							'field_type' => 'multifield_table',
							'count' => 3
						]
					]
				]
			],
			// Host configuration form tab data.
			[
				[
					'url' => 'hosts.php?form=create',
					'form' => 'name:hostsForm',
					'tabs' => [
						[
							'name' => 'Templates',
							'entries' => [
								'Link new templates' => ['Empty template', 'Form test template']
							],
							'field_type' => 'DEV-1671', // Change field type to multiselect once DEV-1671 is merged.
							'count' => 2
						],
						[
							'name' => 'Tags',
							'entries' => [
								[
									'tag' => '!@#$%^&*()_+<>,.\/',
									'value' => '!@#$%^&*()_+<>,.\/'
								],
								[
									'tag' => 'tag1',
									'value' => 'value1'
								],
								[
									'tag' => 'tag2'
								],
								[
									'tag' => ' '
								]
							],
							'table_selector' => 'id:tags-table',
							'field_type' => 'multifield_table',
							'count' => 4
						],
						[
							'name' => 'Macros',
							'entries' => [
								[
									'macro' => '{$123}',
									'Value' => '123'
								],
								[
									'macro' => '{$ABC}'
								],
								[
									'macro' => '{$ABC123}',
									'Value' => 'ABC123',
									'description' => 'ABC-123'
								],
								[
									'macro' => ' '
								]
							],
							'table_selector' => 'id:tbl_macros',
							'field_type' => 'multifield_table',
							'count' => 4
						],
						[
							'name' => 'Inventory',
							'entries' => [
								'selector' => 'id:inventory_mode',
								'value' => 'Manual',
								'old_value' => 'Disabled'
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'Encryption',
							'entries' => [
								'selector' => 'id:tls_connect',
								'value' => 'PSK',
								'old_value' => 'No encryption'
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// Host prototype configuration form tab data.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=31369',
					'form' => 'name:hostPrototypeForm',
					'tabs' => [
						[
							'name' => 'Groups',
							'entries' => [
								'Groups' => ['Discovered hosts', 'Empty group', 'Hypervisors']
							],
							'field_type' => 'multiselect',
							'count' => 3
						],
						[
							'name' => 'Templates',
							'entries' => [
								'Link new templates' => ['Empty template', 'Form test template', 'MySQL by Zabbix agent']
							],
							'field_type' => 'DEV-1671', // Change field type to multiselect once DEV-1671 is merged.
							'count' => 3
						],
						[
							'name' => 'Tags',
							'entries' => [
								[
									'tag' => 'tag1'
								],
								[
									'tag' => ' '
								]
							],
							'table_selector' => 'id:tags-table',
							'field_type' => 'multifield_table',
							'count' => 2
						],
						[
							'name' => 'Macros',
							'entries' => [
								[
									'macro' => '{$123}',
									'Value' => '123'
								],
								[
									'macro' => ' '
								]
							],
							'table_selector' => 'id:tbl_macros',
							'field_type' => 'multifield_table',
							'count' => 2
						],
						[
							'name' => 'Inventory',
							'entries' => [
								'selector' => 'id:inventory_mode',
								'value' => 'Automatic',
								'old_value' => 'Disabled'
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// Item configuration form tab data.
			[
				[
					'url' => 'items.php?form=create&hostid=10084',
					'form' => 'name:itemForm',
					'tabs' => [
						[
							'name' => 'Preprocessing',
							'entries' => [
								[
									'type' => 'Right trim',
									'parameter_1' => 'trim me'
								],
								[
									'type' => 'Left trim',
									'parameter_1' => 'trim me'
								],
								[
									'type' => 'Regular expression'
								]
							],
							'field_type' => 'preprocessing_steps',
							'count' => 3
						]
					]
				]
			],
			// Item prototype configuration form tab data.
			[
				[
					'url' => 'disc_prototypes.php?form=create&parent_discoveryid=31369',
					'form' => 'name:itemForm',
					'tabs' => [
						[
							'name' => 'Preprocessing',
							'entries' => [
								[
									'type' => 'Right trim',
									'parameter_1' => 'trim me'
								],
								[
									'type' => 'Left trim',
									'parameter_1' => 'trim me'
								],
								[
									'type' => 'Regular expression'
								]
							],
							'field_type' => 'preprocessing_steps',
							'count' => 3
						]
					]
				]
			],
			// Trigger configuration form tab data.
			[
				[
					'url' => 'triggers.php?hostid=40001&form=create',
					'form' => 'name:triggersForm',
					'tabs' => [
						[
							'name' => 'Tags',
							'entries' => [
								[
									'tag' => 'trigger tag1'
								],
								[
									'tag' => ' '
								]
							],
							'table_selector' => 'id:tags-table',
							'field_type' => 'multifield_table',
							'count' => 2
						],
						[
							'name' => 'Dependencies',
							'entries' => [
								[
									'selector' => 'id:all_records',
									'value' => true
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 6
						]
					]
				]
			],
			// Trigger prototype configuration form tab data.
			[
				[
					'url' => 'trigger_prototypes.php?parent_discoveryid=33800&form=create',
					'form' => 'name:triggersForm',
					'tabs' => [
						[
							'name' => 'Tags',
							'entries' => [
								[
									'tag' => 'prototype tag1'
								],
								[
									'tag' => 'prototype tag2'
								],
								[
									'tag' => ' '
								]
							],
							'table_selector' => 'id:tags-table',
							'field_type' => 'multifield_table',
							'count' => 3
						],
						[
							'name' => 'Dependencies',
							'entries' => [
								[
									'selector' => 'id:all_records',
									'value' => true
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 6
						]
					]
				]
			],
			// LLD rule configuration form tab data.
			[
				[
					'url' => 'host_discovery.php?form=create&hostid=10084',
					'form' => 'name:itemForm',
					'tabs' => [
						[
							'name' => 'Preprocessing',
							'entries' => [
								[
									'type' => 'JSONPath'
								],
								[
									'type' => 'Replace',
									'parameter_1' => 'replace me',
									'parameter_2' => 'the replacement'
								],
								[
									'type' => 'Regular expression'
								]
							],
							'field_type' => 'preprocessing_steps',
							'count' => 3
						],
						[
							'name' => 'LLD macros',
							'entries' => [
									[
										'lld_macro' => '{#LLD_MACRO}'
									],
									[
										'lld_macro' => ' '
									]
							],
							'table_selector' => 'id:lld_macro_paths',
							'field_type' => 'multifield_table',
							'count' => 2
						],
						[
							'name' => 'Filters',
							'entries' => [
								[
									'macro' => '{#MACRO}'
								],
								[
									'macro' => ' '
								]
							],
							'table_selector' => 'id:conditions',
							'field_type' => 'multifield_table',
							'count' => 2
						],
						[
							'name' => 'Overrides',
							'entries' => [
								[
									'Name' => '1st override name'
								],
								[
									'Name' => '2nd override name'
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 2
						]
					]
				]
			],
			// Web scenarios configuration form tab data.
			[
				[
					'url' => 'httpconf.php?form=create&hostid=10084',
					'form' => 'name:httpForm',
					'tabs' => [
						[
							'name' => 'Steps',
							'entries' => [
								[
									'Name' => '1st step'
								],
								[
									'Name' => '2nd step'
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 2
						],
						[
							'name' => 'Authentication',
							'entries' => [
								'selector' => 'id:authentication',
								'value' => 'Basic',
								'old_value' => 'None'
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// Proxy configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=proxy.edit',
					'form' => 'id:proxy-form',
					'tabs' => [
						[
							'name' => 'Encryption',
							'entries' => [
								'selector' => 'id:tls_in_psk',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// Authentication configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'form' => 'name:form_auth',
					'tabs' => [
						[
							'name' => 'HTTP settings',
							'entries' => [
								'selector' => 'id:http_auth_enabled',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'LDAP settings',
							'entries' => [
								'selector' => 'id:ldap_configured',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'SAML settings',
							'entries' => [
								'selector' => 'id:saml_auth_enabled',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// User configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=user.edit',
					'form' => 'name:user_form',
					'tabs' => [
						[
							'name' => 'Media',
							'entries' => [
								[
									'Send to' => '123'
								],
								[
									'Send to' => 'ABC'
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 2
						]
					]
				]
			],
			// Media type configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=mediatype.edit',
					'form' => 'id:media-type-form',
					'tabs' => [
						[
							'name' => 'Message templates',
							'table_selector' => 'id:messageTemplatesFormlist',
							'entries' => [
								[
									'Message type' => 'Problem'
								],
								[
									'Message type' => 'Discovery'
								],
								[
									'Message type' => 'Internal problem'
								]
							],
							'field_type' => 'overlay_dialogue',
							'count' => 3
						]
					]
				]
			],
			// Graph widget configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view',
					'form' => 'id:widget-dialogue-form',
					'tabs' => [
						[
							'name' => 'Data set',
							'button' => 'button:Add new data set',
							'new_entries' => 3,
							'field_type' => 'data_set',
							'initial_count' => 1,
							'count' => 4
						],
						[
							'name' => 'Displaying options',
							'entries' => [
								'selector' => 'id:source',
								'value' => 'History',
								'old_value' => 'Auto'
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'Time period',
							'entries' => [
								'selector' => 'id:graph_time',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'Legend',
							'set by default' => true,
							'entries' => [
								'selector' => 'id:legend',
								'value' => false,
								'old_value' => true
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'Problems',
							'entries' => [
								'selector' => 'id:show_problems',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						],
						[
							'name' => 'Overrides',
							'button' => 'button:Add new override',
							'new_entries' => 3,
							'field_type' => 'data_set',
							'count' => 3
						]
					]
				]
			],
			// Map configuration form tab data.
			[
				[
					'url' => 'sysmaps.php?form=update&sysmapid=1',
					'form' => 'id:sysmap-form',
					'tabs' => [
						[
							'name' => 'Sharing',
							'entries' => [
								'selector' => 'id:private',
								'value' => 'Private',
								'old_value' => 'Public'
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// User profile configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=userprofile.edit',
					'form' => 'name:user_form',
					'tabs' => [
						[
							'name' => 'Media',
							'table_selector' => 'id:userMediaFormList',
							'entries' => [
								[
									'Send to' => '123'
								],
								[
									'Send to' => 'ABC'
								]
							],
							'field_type' => 'overlay_dialogue',
							'initial_count' => 5,
							'count' => 7
						],
						[
							'name' => 'Messaging',
							'entries' => [
								'selector' => 'id:messages_enabled',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getTabData
	 */
	public function testFormTabIndicators_CheckGeneralForms($data) {
		$this->page->login()->open($data['url'])->waitUntilReady();

		// Open widget configuration form if indicator check is performed on dachboard.
		if ($data['url'] === 'zabbix.php?action=dashboard.view') {
			$this->query('class:btn-widget-edit')->one()->click();
			COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
			$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
			$form->getField('Type')->fill('Graph');
			$form->invalidate();
		}
		else {
			$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
		}
		// Determine the expected indicator values according to the flags in data provider.
		foreach ($data['tabs'] as $tab) {
			$form->selectTab($tab['name']);

			if (array_key_exists('count', $tab)) {
				$data_indicator = 'count';
				$new_value = $tab['count'];
				$old_value = CTestArrayHelper::get($tab, 'initial_count', 0);
			}
			else {
				$data_indicator = 'mark';
				$old_value = CTestArrayHelper::get($tab, 'set by default', false);
				$new_value = !$old_value;
			}

			$tab_selector = $form->query('xpath:.//a[text()="'.$tab['name'].'"]')->one();
			$this->assertTabIndicator($tab_selector, $old_value);

			// Populate fields in tab and check indicator value.
			$this->updateTabFields($tab, $form);
			// Input elements change their attribute values only after focus is removed from the element.
			$this->page->removeFocus();
			$this->assertTabIndicator($tab_selector, $new_value);

			// Clear the popullatedfields and check indicator value.
			$this->updateTabFields($tab, $form, USER_ACTION_REMOVE);
			$old_value = (CTestArrayHelper::get($tab, 'count', false)) ? 0 : $old_value;
			$this->assertTabIndicator($tab_selector, $old_value);
		}
	}

	public function testFormTabIndicators_CheckActionOperationsCounter() {
		$this->page->login()->open('actionconf.php?eventsource=0&form=Create+action')->waitUntilReady();

		// Open Operations tab and check indicator value.
		$form = $this->query('id:action-form')->asForm()->one();
		$form->selectTab('Operations');
		$tab_selector = $form->query('xpath:.//a[text()="Operations"]')->one();
		$this->assertTabIndicator($tab_selector, 0);

		// Specify an operation of each type and check indicator value.
		foreach (['Operations', 'Recovery operations', 'Update operations'] as $operation) {
			$form->getField($operation)->query('button:Add')->one()->click();
			$operations_overlay = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
			$operations_overlay->getField('Send to users')->query('button:Add')->one()->click();

			$users_overlay = COverlayDialogElement::find()->all()->asForm()->last();
			$users_overlay->query('id:item_1')->asCheckbox()->one()->check();
			$users_overlay->submit();
			$operations_overlay->submit();

			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertTabIndicator($tab_selector, 3);

		// Remove the previously created operations and check indicator value.
		$form->query('button:Remove')->all()->click();
		$this->assertTabIndicator($tab_selector, 0);
	}

	public function testFormTabIndicators_CheckUserGroupIndicators() {
		$this->page->login()->open('zabbix.php?action=usergroup.edit')->waitUntilReady();

		// Check status indicator in Permissions tab.
		$form = $this->query('id:user-group-form')->asForm()->one();
		$form->selectTab('Permissions');
		$tab_selector = $form->query('xpath:.//a[text()="Permissions"]')->one();
		$this->assertTabIndicator($tab_selector, false);

		// Add read permissions to Discovered hosts group and check indicator.
		$group_selector = $form->query('xpath:.//div[@id="new_group_right_groupids_"]/..')->asMultiselect()->one();
		$group_selector->fill('Discovered hosts');
		$permission_level = $form->query('id:new_group_right_permission')->asSegmentedRadio()->one();
		$permission_level->fill('Read');
		$add_button = $form->query('id:new-group-right-table')->query('button:Add')->one();
		$add_button->click();
		$tab_selector->waitUntilReady();
		$this->assertTabIndicator($tab_selector, true);

		// Remove read permissions from Discovered hosts group and check indicator.
		$group_selector->fill('Discovered hosts');
		$permission_level->fill('None');
		$add_button->click();
		$tab_selector->waitUntilReady();
		$this->assertTabIndicator($tab_selector, false);

		// Check status indicator in Tag filter tab.
		$form->selectTab('Tag filter');
		$tab_selector = $form->query('xpath:.//a[text()="Tag filter"]')->one();
		$this->assertTabIndicator($tab_selector, false);

		// Add tag filter for Discovered hosts group and check indicator.
		$form->query('xpath:.//div[@id="new_tag_filter_groupids_"]/..')->asMultiselect()->one()->fill('Discovered hosts');
		$form->query('id:new-tag-filter-table')->query('button:Add')->one()->click();
		$tab_selector->waitUntilReady();
		$this->assertTabIndicator($tab_selector, true);

		// Remove the tag filter for Discovered hosts group and check indicator.
		$form->query('id:tag-filter-table')->query('button:Remove')->one()->click();
		$this->assertTabIndicator($tab_selector, false);
	}

	/**
	 * Function used to create services for dependencies indicator test.
	 */
	public function prepareServiceData() {
		CDataHelper::call('service.create', [
			[
				'name' => 'Service 1',
				'algorithm' => 0,
				'showsla' => 0,
				'sortorder' => 0
			],
			[
				'name' => 'Service 2',
				'algorithm' => 0,
				'showsla' => 0,
				'sortorder' => 0
			]
		]);
	}

	/**
	 * @on-before-once prepareServiceData
	 */
	public function testFormTabIndicators_CheckServiceIndicators() {
		$this->page->login()->open('services.php?form=1&parentname=root')->waitUntilReady();

		// Check status indicator in Dependencies tab.
		$form = $this->query('id:services-form')->asForm()->one();
		$form->selectTab('Dependencies');
		$tab_selector = $form->query('xpath:.//a[text()="Dependencies"]')->one();
		$this->assertTabIndicator($tab_selector, 0);

		// Add service ependencies and check dependency count indicator.
		$dependencies_field = $form->getFieldContainer('Depends on');
		$dependencies_field->query('button:Add')->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$overlay->query('id:all_services')->asCheckbox()->one()->check();
		$overlay->query('button:Select')->one()->click();
		COverlayDialogElement::ensureNotPresent();
		$this->assertTabIndicator($tab_selector, 2);

		// Remove all dependencies and check count indicator.
		$dependencies_field->query('button:Remove')->all()->click();
		$this->assertTabIndicator($tab_selector, 0);

		// Open Time tab and check count indicator.
		$form->selectTab('Time');
		$tab_selector = $form->query('xpath:.//a[text()="Time"]')->one();
		$this->assertTabIndicator($tab_selector, 0);

		// Add a time period and check count indicator.
		$form->getField('Period type')->select('One-time downtime');
		$form->getFieldContainer('New service time')->query('button:Add')->one()->click();
		$this->assertTabIndicator($tab_selector, 1);

		// Remove the added time period and check count indicator.
		$form->getFieldContainer('Service times')->query('button:Remove')->one()->click();
		$this->assertTabIndicator($tab_selector, 0);
	}

	/*
	 * Function updates configuration fields according to the type of field to be updated.
	 */
	private function updateTabFields($tab, $form, $action = USER_ACTION_ADD) {
		switch ($tab['field_type']) {
			case 'multifield_table':
				foreach ($tab['entries'] as &$parameter) {
					$parameter['action'] = CTestArrayHelper::get($parameter, 'action', $action);
				}
				unset($parameter);

				$form->query($tab['table_selector'])->asMultifieldTable()->one()->fill($tab['entries']);

				break;

			case 'multiselect':
				if ($action === USER_ACTION_REMOVE) {
					foreach (array_keys($tab['entries']) as $field) {
						$form->getField($field)->clear();
					}
				}
				else {
					$form->fill($tab['entries']);
				}
				break;

				// REMOVE this case after DEV-1671 is Merged.
			case 'DEV-1671':
				$templates_field = $form->query('xpath:.//div[@id="add_templates_"]/..')->asMultiselect()->one();
				if ($action === USER_ACTION_REMOVE) {
					foreach (array_keys($tab['entries']) as $field) {
						$templates_field->clear();
					}
				}
				else {
					$templates_field->fill($tab['entries']);
				}
				break;

			case 'general_field':
				$value = ($action === USER_ACTION_REMOVE) ? $tab['entries']['old_value'] : $tab['entries']['value'];
				$form->query($tab['entries']['selector'])->one()->detect()->fill($value);
				break;

			case 'preprocessing_steps':
				if ($action === USER_ACTION_REMOVE) {
					$form->query('id:preprocessing')->query('button:Remove')->all()->click();
				}
				else {
					$this->addPreprocessingSteps($tab['entries']);
				}
				break;

			case 'overlay_dialogue':
				if ($action === USER_ACTION_REMOVE) {
					if (array_key_exists('table_selector', $tab)) {
						$form->query($tab['table_selector'])->query('button:Remove')->all()->click();
					}
					else {
						$form->getFieldContainer($tab['name'])->query('button:Remove')->all()->click();
					}
				}
				else {
					foreach($tab['entries'] as $entry) {
						if (array_key_exists('table_selector', $tab)) {
							$form->query($tab['table_selector'])->query('button:Add')->one()->click();
						}
						else {
							$form->getFieldContainer($tab['name'])->query('button:Add')->one()->click();
						}
						$overlay = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
						if (array_key_exists('selector', $entry)) {
							$overlay->query($entry['selector'])->one()->detect()->fill($entry['value']);
						}
						else {
							$overlay->fill($entry);
							if ($tab['name'] === 'Steps') {
								$overlay->query('id:url')->one()->fill('http://zabbix.com');
							}
						}
						$overlay->submit();

						COverlayDialogElement::ensureNotPresent();
					}
				}
				break;

			case 'data_set':
				if ($action === USER_ACTION_REMOVE) {
					$form->query('class:remove-btn')->all()->click();
				}
				else {
					for ($i = 0; $i < $tab['new_entries']; $i++) {
						$form->query($tab['button'])->one()->click();
					}
				}
				break;
		}
	}

	/*
	 * Function checks count attribute or status attribute value of the specified tab.
	 */
	private function assertTabIndicator($element, $expected) {
		if (is_bool($expected)) {
			$value = (bool) $element->getAttribute('data-indicator-value');
			$indicator = 'mark';
		}
		else {
			$value = $element->getAttribute('data-indicator-value');
			$indicator = 'count';
		}

		$this->assertEquals($indicator, $element->getAttribute('data-indicator'));
		$this->assertEquals($expected, $value);
	}
}

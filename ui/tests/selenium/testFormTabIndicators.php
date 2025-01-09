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


require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/common/testFormPreprocessing.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/behaviors/CPreprocessingBehavior.php';

/**
 * @dataSource Services, EntitiesTags
 *
 * @onBefore prepareMediaTypeData
 *
 * @backup users
 */
class testFormTabIndicators extends CWebTest {

	/**
	 * Attach PreprocessingBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CPreprocessingBehavior::class];
	}

	/**
	 * Enable media types before test.
	 */
	public function prepareMediaTypeData() {

		CDataHelper::call('user.update', [
			[
				'userid' => 1,
				'medias' => [
					[
						'mediatypeid' => 1, // Email.
						'sendto' => ['test@zabbix.com'],
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 63,
						'period' => '1-7,00:00-24:00'
					]
				]
			]
		]);
	}

	public function getTabData() {
		return [
			// #0 Template configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=template.list',
					'form' => 'name:templatesForm',
					'close_dialog' => true,
					'tabs' => [
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
							'table_selector' => 'class:tags-table',
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
						],
						[
							'name' => 'Value mapping',
							'entries' => ['1st value mapping', '2nd value mapping', '3rd value mapping'],
							'field_type' => 'value_mapping',
							'count' => 3
						]
					]
				]
			],
			// #1 Host configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=host.list',
					'form' => 'id:host-form',
					'create_button' => 'Create host',
					'tabs' => [
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
							'table_selector' => 'class:tags-table',
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
						],
						[
							'name' => 'Value mapping',
							'entries' => ['1st value mapping', '2nd value mapping', '3rd value mapping'],
							'field_type' => 'value_mapping',
							'count' => 3
						]
					]
				]
			],
			// #2 Host prototype configuration form tab data.
			[
				[
					'url' => 'host_prototypes.php?form=create&parent_discoveryid=42275&context=host',
					'form' => 'name:hostPrototypeForm',
					'tabs' => [
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
							'table_selector' => 'class:tags-table',
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
			// #3 Item configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]=10084',
					'create_button' => 'Create item',
					'form' => 'name:itemForm',
					'close_dialog' => true,
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
			// #4 Item prototype configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=item.prototype.list&parent_discoveryid=42275&context=host',
					'create_button' => 'Create item prototype',
					'form' => 'name:itemForm',
					'close_dialog' => true,
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
			// #5 Trigger configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D=40001&context=host',
					'create_button' => 'Create trigger',
					'form' => 'id:trigger-edit',
					'close_dialog' => true,
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
							'table_selector' => 'class:tags-table',
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
							'count' => 4
						]
					]
				]
			],
			// #6 Trigger prototype configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=trigger.prototype.list&parent_discoveryid=133800&context=host',
					'create_button' => 'Create trigger prototype',
					'form' => 'id:trigger-edit',
					'close_dialog' => true,
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
							'table_selector' => 'class:tags-table',
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
							'count' => 4
						]
					]
				]
			],
			// #7 LLD rule configuration form tab data.
			[
				[
					'url' => 'host_discovery.php?form=create&context=host&hostid=10084',
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
			// #8 Web scenarios configuration form tab data.
			[
				[
					'url' => 'httpconf.php?form=create&context=host&hostid=10084',
					'form' => 'name:webscenario_form',
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
			// #9 Proxy configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'create_button' => 'Create proxy',
					'form' => 'id:proxy-form',
					'close_dialog' => true,
					'tabs' => [
						[
							'name' => 'Encryption',
							'entries' => [
								'selector' => 'id:tls_accept_psk',
								'value' => true,
								'old_value' => false
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// #10 Authentication configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'form' => 'id:authentication-form',
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
								'selector' => 'id:ldap_auth_enabled',
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
			// #11 User configuration form tab data.
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
			// #12 Media type configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'create_button' => 'Create media type',
					'form' => 'id:media-type-form',
					'close_dialog' => true,
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
			// #13 Graph widget configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view',
					'form' => 'id:widget-dialogue-form',
					'widget_type' => 'Graph',
					'close_dialog' => true,
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
								'selector' => 'id:time_period_data_source',
								'value' => 'Custom',
								'old_value' => 'Dashboard'
							],
							'field_type' => 'general_field'
						],
						// There is no tab indicator if the default values are set.
						[
							'name' => 'Legend',
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
			// #14 Pie chart widget configuration form tab data.
			[
				[
					'url' => 'zabbix.php?action=dashboard.view',
					'form' => 'id:widget-dialogue-form',
					'widget_type' => 'Pie chart',
					'close_dialog' => true,
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
								'selector' => 'id:time_period_data_source',
								'value' => 'Custom',
								'old_value' => 'Dashboard'
							],
							'field_type' => 'general_field'
						],
						// There is no tab indicator if the default values are set.
						[
							'name' => 'Legend',
							'entries' => [
								'selector' => 'id:legend',
								'value' => false,
								'old_value' => true
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// #15 Map configuration form tab data.
			[
				[
					'url' => 'sysmaps.php?form=Create+map',
					'form' => 'id:sysmap-form',
					'tabs' => [
						[
							'name' => 'Sharing',
							'entries' => [
								'selector' => 'id:private',
								'value' => 'Public',
								'old_value' => 'Private'
							],
							'field_type' => 'general_field'
						]
					]
				]
			],
			// #16 User profile configuration form tab data.
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
							'initial_count' => 1,
							'count' => 3
						],
						[
							'name' => 'Frontend notifications',
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

		// Open widget configuration form if indicator check is performed on dashboard.
		if ($data['url'] === 'zabbix.php?action=dashboard.view') {
			$this->query('class:js-widget-edit')->one()->click();
			COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
			$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($data['widget_type'])]);
			$form->invalidate();
		}
		elseif ($data['url'] === 'zabbix.php?action=template.list') {
			$this->query('button:Create template')->one()->click();
			$form = COverlayDialogElement::find()->asForm()->waitUntilReady()->one();
		}
		elseif (CTestArrayHelper::get($data, 'create_button')) {
			$this->query('button', $data['create_button'])->one()->click();
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		}
		else {
			$form = $this->query($data['form'])->asForm()->one()->waitUntilVisible();
		}
		// Determine the expected indicator values according to the flags in data provider.
		foreach ($data['tabs'] as $tab) {
			$form->selectTab($tab['name']);

			if (array_key_exists('count', $tab)) {
				$new_value = $tab['count'];
				$old_value = CTestArrayHelper::get($tab, 'initial_count', 0);
			}
			else {
				// There is no tab indicator if the default values are set.
				$old_value = false;
				$new_value = !$old_value;
			}

			$tab_selector = $form->query('xpath:.//a[text()="'.$tab['name'].'"]')->one();
			$this->assertTabIndicator($tab_selector, $old_value);

			if (CTestArrayHelper::get($tab, 'name') === 'HTTP settings') {
				$form->fill(['Enable HTTP authentication' => true]);
				$this->query('button:Ok')->one()->click();
			}

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

		if (CTestArrayHelper::get($data, 'close_dialog')) {
			COverlayDialogElement::find()->one()->waitUntilReady()->close();
		}
	}

	public function testFormTabIndicators_CheckActionOperationsCounter() {
		$this->page->login()->open('zabbix.php?action=action.list&eventsource=0')->waitUntilReady();
		$this->query('button:Create action')->one()->click()->waitUntilReady();

		// Open Operations tab and check indicator value.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->selectTab('Operations');
		$tab_selector = $form->query('xpath:.//a[text()="Operations"]')->one()->waitUntilVisible();
		$this->assertTabIndicator($tab_selector, 0);

		// Specify an operation of each type and check indicator value.
		foreach (['Operations' => 'operations_0', 'Recovery operations' => 'recovery_operations_0',
						'Update operations' => 'update_operations_0'] as $operation => $row) {
			$form->getField($operation)->query('button:Add')->one()->waitUntilClickable()->click();
			$operations_overlay = COverlayDialogElement::find()->all()->last()->asForm()->waitUntilReady();
			$operations_overlay->query('xpath://div[@id="operation-message-users"]'.
					'//button[text()="Select"]')->one()->click();
			$users_overlay = COverlayDialogElement::find()->all()->asForm()->last();
			$users_overlay->query('id:item_1')->asCheckbox()->one()->check();
			$users_overlay->submit();
			$operations_overlay->submit();
			$this->query('xpath://tr[@id="'.$row.'"]')->waitUntilVisible();
		}

		$this->assertTabIndicator($tab_selector, 3);

		// Remove the previously created operations and check indicator value.
		$form->query('button:Remove')->all()->click();
		$this->assertTabIndicator($tab_selector, 0);

		$dialog->close();
	}

	public function testFormTabIndicators_CheckUserGroupIndicators() {
		$data = [
			[
				'tab_name' => 'Host permissions',
				'group_table' => 'hostgroup-right-table',
				'multiselect' => 'ms_hostgroup_right_groupids_0_',
				'segmentedradio' => 'hostgroup_right_permission_0',
				'group_name' => 'Discovered hosts'
			],
			[
				'tab_name' => 'Template permissions',
				'group_table' => 'templategroup-right-table',
				'multiselect' => 'ms_templategroup_right_groupids_0_',
				'segmentedradio' => 'templategroup_right_permission_0',
				'group_name' => 'Templates/Power'
			]
		];

		$this->page->login()->open('zabbix.php?action=usergroup.edit')->waitUntilReady();
		$tag_table = $this->query('id:tag-filter-table')->one();

		// Check status indicator in Permissions tab.
		$form = $this->query('id:user-group-form')->asForm()->one();
		foreach ($data as $permissions) {
			$permissions_table = $this->query('id', $permissions['group_table'])->one();
			$form->selectTab($permissions['tab_name']);
			$tab_selector = $form->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($permissions['tab_name']).']')->one();
			$this->assertTabIndicator($tab_selector, false);

			// Add read permissions to Discovered hosts group and check indicator.
			$permissions_table->query('button', 'Add')->one()->click();
			$group_selector = $form->query('xpath:.//div[@id="'.$permissions['multiselect'].'"]/..')->asMultiselect()->one();
			$group_selector->fill($permissions['group_name']);
			$permission_level = $form->query('id', $permissions['segmentedradio'])->asSegmentedRadio()->one();
			$permission_level->fill('Read');
			$tab_selector->waitUntilReady();
			$this->assertTabIndicator($tab_selector, true);

			// Remove 'Discovered hosts' group and check indicator.
			$permissions_table->query('button', 'Remove')->one()->click();
			$tab_selector->waitUntilReady();
			$this->assertTabIndicator($tab_selector, false);
		}

		// Check status indicator in Tag filter tab.
		$form->selectTab('Problem tag filter');
		$tab_selector = $form->query('xpath:.//a[text()="Problem tag filter"]')->one();
		$this->assertTabIndicator($tab_selector, false);

		// Add tag filter for 'Discovered hosts' group and check indicator.
		$tag_table->query('button','Add')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->asForm()->fill(['Host groups' => 'Discovered hosts']);
		$dialog->getFooter()->query('button', 'Add')->one()->click();
		COverlayDialogElement::ensureNotPresent();
		$tag_table->waitUntilReloaded();
		$this->assertTabIndicator($tab_selector, true);

		// Remove the tag filter for 'Discovered hosts' group and check indicator.
		$tag_table->query('button', 'Remove')->one()->click();
		$this->assertTabIndicator($tab_selector, false);
	}

	public function testFormTabIndicators_CheckServiceIndicators() {
		$this->page->login()->open('zabbix.php?action=service.list.edit')->waitUntilReady();

		// Check status indicator in Child services tab.
		$this->query('button:Create service')->one()->waitUntilClickable()->click();
		$main_dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one();
		$form->selectTab('Child services');
		$tab_selector = $form->query('xpath:.//a[text()="Child services"]')->one();
		$this->assertTabIndicator($tab_selector, 0);

		// Add child services and check child service count indicator.
		$child_services_tab = $form->query('id:child-services-tab')->one();
		$child_services_tab->query('button:Add')->one()->click();
		$overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$overlay->query('id:serviceid_all')->asCheckbox()->one()->check();
		$overlay->query('button:Select')->one()->click();
		$overlay->waitUntilNotVisible();
		$this->assertTabIndicator($tab_selector, CDBHelper::getCount('SELECT null FROM services'));

		// Remove all child services and check count indicator.
		$child_services_tab->query('button:Remove')->all()->click();
		$this->assertTabIndicator($tab_selector, 0);

		// Open Tags tab and check count indicator.
		$form->selectTab('Tags');
		$tab_selector = $form->query('id:tab_tags-tab')->one();
		$this->assertTabIndicator($tab_selector, 0);

		// Add Tags and check count indicator.
		$tags = [
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
		];
		$form->query('class:tags-table')->asMultifieldTable()->one()->fill($tags);
		$this->assertTabIndicator($tab_selector, 3);

		// Remove the tags and check count indicator.
		$form->query('class:tags-table')->one()->query('button:Remove')->all()->click();
		$this->assertTabIndicator($tab_selector, 0);

		$main_dialog->close();
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
					foreach ($tab['entries'] as $entry) {
						if (array_key_exists('table_selector', $tab)) {
							$form->query($tab['table_selector'])->query('button:Add')->one()->click();
						}
						else {
							$form->getFieldContainer($tab['name'])->query('button:Add')->one()->click();
						}
						$overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();
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
						$overlay->waitUntilNotVisible();
					}
				}
				break;

			case 'data_set':
				if ($action === USER_ACTION_REMOVE) {
					// In graph widget form the 1st row is covered by header with tabs if scroll is not in top position.
					COverlayDialogElement::find()->one()->scrollToTop();
					$form->query('class:js-remove')->all()->click();
				}
				else {
					for ($i = 0; $i < $tab['new_entries']; $i++) {
						$form->query($tab['button'])->one()->click();
					}
				}
				break;

			case 'value_mapping':
				if ($action === USER_ACTION_REMOVE) {
					$form->query('xpath://table[contains(@id,"valuemap-table")]//button[text()="Remove"]')->waitUntilClickable()->all()->click();
				}
				else {
					foreach ($tab['entries'] as $field_value) {
						$form->query('id:valuemap_add')->one()->click();
						$valuemap_form = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
						$valuemap_form->query('xpath:.//input[@type="text"]')->all()->fill($field_value);
						$valuemap_form->submit();
						$valuemap_form->waitUntilNotVisible();
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

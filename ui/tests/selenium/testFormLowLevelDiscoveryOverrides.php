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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CFormParametersBehavior.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup items
 */
class testFormLowLevelDiscoveryOverrides extends CWebTest {

	const HOST_ID = 40001;
	public static $id;

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			[
				'class' => CFormParametersBehavior::class,
				'table_selector' => 'id:overrides_filters',
				'table_mapping' => [
					'Macro' => [
						'name' => 'macro',
						'selector' => 'xpath:./input|./textarea',
						'class' => 'CElement'
					],
					'' => [
						'name' => 'operator',
						'selector' => 'xpath:.//select[contains(@id, "_operator")]',
						'class' => 'CDropdownElement'
					],
					'Regular expression' => [
						'name' => 'expression',
						'selector' => 'xpath:./input|./textarea',
						'class' => 'CElement'
					]
				]
			],
			'class' => CMessageBehavior::class
		];
	}

	/*
	 * Overrides data for LLD creation.
	 */
	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'overrides' => [
						[
							'fields' => [
								'Name' => '',
							],
							'error' => 'Incorrect value for field "Name": cannot be empty.'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Override without actions',
							],
							'Operations' => [
								[
									'Object' => 'Item prototype'
								]
							],
							'error' => 'At least one action is mandatory.'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Override with empty tags',
							],
							'Operations' => [
								[
									'Object' => 'Trigger prototype',
									'Tags' => [],
								]
							],
							'error' => 'Incorrect value for field "Tags": cannot be empty.'
						]
					]
				]
			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with empty tag name',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Trigger prototype',
//									'Tags' => [
//										['tag' => '', 'value' => 'value1'],
//									]
//								]
//							],
//							'error' => 'Incorrect value for field "Tag": cannot be empty.'
//						]
//					]
//				]
//			],
			[
				[
					'expected' => TEST_BAD,
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Override with empty template',
							],
							'Operations' => [
								[
									'Object' => 'Host prototype',
									'Link templates' => [],
								]
							],
							'error' => 'Incorrect value for field "Link templates": cannot be empty.'
						]
					]
				]
			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with empty delay',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => ''
//									]
//								]
//							],
//							'error' => 'Incorrect value for field "Update interval": invalid delay.'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with zero delay',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '0'
//									]
//								]
//							],
//							'error' => 'Item will not be refreshed.'.
//									'Specified update interval requires having at least one either flexible or scheduling interval.'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with 2 days delay',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '2d'
//									]
//								]
//							],
//							'error' => 'Item will not be refreshed. '.
//									'Update interval should be between 1s and 1d. Also Scheduled/Flexible intervals can be used.'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with empty interval',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '50m',
//										'Custom intervals' => [
//											['Type' => 'Flexible', 'Interval' => '', 'Period' => '1-5,01:01-13:05'],
//										]
//									],
//								]
//							],
//							'error' => 'Invalid interval "".'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with empty period',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '50m',
//										'Custom intervals' => [
//											['Type' => 'Flexible', 'Interval' => '20s', 'Period' => ''],
//										]
//									],
//								]
//							],
//							'error' => 'Invalid interval "".'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with wrong period',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '50m',
//										'Custom intervals' => [
//											['Type' => 'Flexible', 'Interval' => '20s', 'Period' => '1-2'],
//										]
//									],
//								]
//							],
//							'error' => 'Invalid interval "1-2".'
//						]
//					]
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'overrides' => [
//						[
//							'fields' => [
//								'Name' => 'Override with wrong scheduling interval',
//							],
//							'Operations' => [
//								[
//									'Object' => 'Item prototype',
//									'Update interval' => [
//										'Delay' => '50m',
//										'Custom intervals' => [
//											['Type' => 'Scheduling', 'Interval' => 'wd1-9'],
//										]
//									],
//								]
//							],
//							'error' => 'Invalid interval "wd1-9".'
//						]
//					]
//				]
//			],
			[
				[
					'expected' => TEST_GOOD,
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Minimal override',
							]
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Override_1',
								'If filter matches' => 'Stop processing'
							],
							'Filters' => [
								'Type of calculation' => 'Custom expression',
								'formula' => 'A and B',
								'filter_conditions' => [
									[
										'action' => USER_ACTION_UPDATE,
										'index' => 0,
										'macro' => '{#MACRO1}',
										'operator' => 'does not match',
										'expression' => 'expression_1'
									],
									[
										'macro' => '{#MACRO2}',
										'operator' => 'matches',
										'expression' => 'expression_2'
									]
								]
							],
							'Operations' => [
								[
									'Object' => 'Item prototype',
									'Condition' => ['operator' => 'does not match', 'value' => 'item_pattern'],
									'Create enabled' => 'No',
									'Discover' => 'No',
//									'Update interval' => [
//										'Delay' => '50m',
//										'Custom intervals' => [
//											['Type' => 'Flexible', 'Interval' => '60s', 'Period' => '1-5,01:01-13:05'],
//											['Type' => 'Scheduling', 'Interval' => 'wd1-3h10-17']
//										]
//									],
									'History storage period' => ['ophistory_history_mode' => 'Storage period', 'ophistory_history' => '500d'],
									'Trend storage period' => ['optrends_trends_mode' => 'Storage period', 'optrends_trends' => '200d']
								],
								[
									'Object' => 'Trigger prototype',
									'Condition' => ['operator' => 'contains', 'value' => 'trigger_Pattern'],
									'Create enabled' => 'No',
									'Discover' => 'No',
									'Severity' => 'Warning',
//									'Tags' => [
//										['tag' => 'tag1', 'value' => 'value1'],
//										['tag' => 'tag2', 'value' => 'value2']
//									]
								],
								[
									'Object' => 'Graph prototype',
									'Condition' => ['operator' => 'matches', 'value' => 'Graph_Pattern'],
									'Discover' => 'Yes'
								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not match', 'value' => 'Host_Pattern'],
									'Create enabled' => 'Yes',
									'Discover' => 'Yes',
									'Create enabled' => 'Yes',
									'Link templates' => 'Test Item Template',
									'Host inventory' => 'Disabled'
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Override_2',
								'If filter matches' => 'Continue overrides'
							],
							'Operations' => [
								[
									'Object' => 'Graph prototype',
									'Condition' => ['operator' => 'matches', 'value' => '2Graph_Pattern'],
									'Discover' => 'No'
								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not match', 'value' => '2Host_Pattern'],
									'Create enabled' => 'Yes',
									'Discover' => 'No',
									'Create enabled' => 'Yes',
									'Link templates' => 'Test Item Template',
									'Host inventory' => 'Automatic'
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormLowLevelDiscoveryOverrides_Create($data) {
		$this->overridesCreate($data);
		$this->checkSavedState($data);
	}

	private function overridesCreate($data) {
		$this->page->login()->open('host_discovery.php?form=create&hostid='.self::HOST_ID);
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$key = 'lld_override'.time();
		$form->fill(['Name' => 'LLD with overrides',
					'Key' => $key]);
		$form->selectTab('Overrides');
		$form->invalidate();
		$override_container = $form->getField('Overrides')->asTable();

		// Add overrides from data to lld rule.
		foreach($data['overrides'] as $i => $override){
			$override_container->query('button:Add')->one()->click();
			// Open Override overlay.
			$override_overlay = $this->query('id:lldoverride_form')->waitUntilPresent()->one()->asForm();
			// Fill Overridae name and further processing strategy.
			$override_overlay->fill($override['fields']);

			// Add Filters to override.
			if (array_key_exists('Filters', $override)) {
				$this->fillParameters($override['Filters']['filter_conditions']);

				// Add Type of calculation if there are more then 2 filters.
				if (array_key_exists('Type of calculation', $override['Filters'])) {
					$override_overlay->query('id:overrides_evaltype')->waitUntilPresent()->one()
							->asDropdown()->fill($override['Filters']['Type of calculation']);

					// Add formula if Type of calculation is Custom.
					if (array_key_exists('formula', $override['Filters'])) {
						$override_overlay->query('id:overrides_formula')->waitUntilPresent()->one()
							->fill($override['Filters']['formula']);
					}
				}
			}

			$operation_container = $override_overlay->getField('Operations')->asTable();

			if (array_key_exists('Operations', $override)) {

				// Add Operations to override.
				foreach($override['Operations'] as $j => $operation){
					$operation_container->query('button:Add')->one()->click();
					// Fill Operation form fields.
					$operation_overlay = $this->query('id:lldoperation_form')->waitUntilPresent()->one()->asForm();
					$operation_overlay->fill($operation);
					// Submit Operation.
					$operation_overlay->submit();
					$this->checkSubmittedOverlay($data['expected'], $operation_overlay, CTestArrayHelper::get($override, 'error'));

					if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
						// Check that Operation was added to Operations table.
						$condition_text = $operation['Object'].' '.$operation['Condition']['operator'].' '.$operation['Condition']['value'];
						$this->assertEquals($condition_text, $operation_container->getRow($j)->getColumn('Condition')->getText());
					}
				}
			}

			// Submit Override.
			$override_overlay->submit();
			$this->checkSubmittedOverlay($data['expected'], $override_overlay, CTestArrayHelper::get($override, 'error'));

			if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
				// Check that Override with correct name was added to Overrides table.
				$this->assertEquals($override['fields']['Name'], $override_container->getRow($i)->getColumn('Name')->getText());
				// Check that Override in table has correct processing status.
				$stop_processing = (CTestArrayHelper::get($override['fields'],
						'If filter matches') === 'Stop processing') ? 'Yes' : 'No';
				$this->assertEquals($stop_processing, $override_container->getRow($i)->getColumn('Stop processing')->getText());
			}
		}

		if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
			// Submit LLD create.
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Discovery rule created');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM items WHERE key_='.zbx_dbstr($key)));
			self::$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($key));
		}
	}

	/**
	 *
	 * @depends overridesCreate
	 */
	private function checkSavedState($data) {
		// Skip Bad cases.
		if (CTestArrayHelper::get($data, 'expected') === TEST_BAD) {
			return;
		}

		// Open saved LLD.
		$this->page->login()->open('host_discovery.php?form=update&itemid='.self::$id);
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Overrides');
		$override_container = $form->getField('Overrides')->asTable();
		// Get Overrides count.
		$overrides_count = $override_container->getRows()->count();

		// Write Override names from data to array.
		foreach ($data['overrides'] as $override) {
			$override_names[] = $override['fields']['Name'];
			$stop_processing[] = (CTestArrayHelper::get($override['fields'],
				'If filter matches') === 'Stop processing') ? 'Yes' : 'No';
		}

		// Compare Override names from table with data.
		for ($k = 0; $k < $overrides_count - 1; $k++) {
			$this->assertEquals($override_names[$k],
					$override_container->getRow($k)->getColumn('Name')->getText()
			);
			// Check that Override in table has correct processing status.
			$this->assertEquals($stop_processing[$k], $override_container->getRow($k)->getColumn('Stop processing')->getText());
		}

		foreach ($data['overrides'] as $override) {
			// Open each override dialog.
			$row = $override_container->findRow('Name', $override['fields']['Name']);
			$row->query('link', $override['fields']['Name'])->one()->click();
			$override_overlay = $this->query('id:lldoverride_form')->waitUntilPresent()->one()->asForm();

			// Check that Override fields filled with correct data.
			foreach ($override['fields'] as $field => $value) {
				$override_overlay->getField($field)->checkValue($value);
			}

			if (array_key_exists('Filters', $override)) {
				// Check that Fiters are filled correctly.
				$this->assertValues($override['Filters']['filter_conditions']);

				// Check that Evaluation type is filled correctly.
				if (array_key_exists('Type of calculation', $override['Filters'])) {
					$evaluation_type = $override_overlay->query('id:overrides_evaltype')->one()->asDropdown()->getValue();
					$this->assertEquals($override['Filters']['Type of calculation'], $evaluation_type);

					// Check that Formula is filled correctly.
					if (array_key_exists('formula', $override['Filters'])) {
						$formula = $override_overlay->query('id:overrides_formula')->one()->getValue();
						$this->assertEquals($override['Filters']['formula'], $formula);
					}
				}
			}

			$operation_container = $override_overlay->getField('Operations')->asTable();
			// Get Operations count.
			$operation_count = $operation_container->getRows()->count();

			if (array_key_exists('Operations', $override)) {

				// Write Condititons from data to array.
				$condition_text = [];
				foreach($override['Operations'] as $operation){
					$condition_text[] = $operation['Object'].' '.$operation['Condition']['operator'].' '.$operation['Condition']['value'];
				}

				// Compare Conditions from table with data.
				for ($n = 0; $n < $operation_count - 1; $n++) {
					$this->assertEquals($condition_text[$n],
							$operation_container->getRow($n)->getColumn('Condition')->getText()
					);
				}
			}

			// Close Override dialog.
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Function for checking successful/failed overlay submitting.
	 *
	 * @param string	$expected	case GOOD or BAD
	 * @param element	$overlay	COverlayDialogElement
	 * @param string	$error		error message text
	 */
	private function checkSubmittedOverlay($expected, $overlay, $error) {
		switch ($expected) {
			case TEST_GOOD:
				$overlay->waitUntilNotPresent();
				break;
			case TEST_BAD:
				$this->assertMessage(TEST_BAD, null, $error);
				break;
		}
	}
}

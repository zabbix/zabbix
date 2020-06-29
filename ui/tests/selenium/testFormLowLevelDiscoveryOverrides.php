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
require_once dirname(__FILE__).'/behaviors/MacroFilterBehavior.php';
require_once dirname(__FILE__).'/behaviors/MessageBehavior.php';

/**
 * @backup items
 */
class testFormLowLevelDiscoveryOverrides extends CWebTest {

	const HOST_ID = 40001;

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			[
				'class' => CMacroFilterBehavior::class,
				'table_selector' => 'id:overrides_filters'
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
								'Name' => 'Bad override',
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
								'Name' => 'Bad override',
							],
							'Operations' => [
								[
									'Object' => 'Trigger prototype',
									'actions' => [
										'Tags' => []
									]
								]
							],
							'error' => 'Incorrect value for field "Tags": cannot be empty.'
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
								'Name' => 'Bad override',
							],
							'Operations' => [
								[
									'Object' => 'Host prototype',
									'actions' => [
										'Link templates' => []
									]
								]
							],
							'error' => 'Incorrect value for field "Link templates": cannot be empty.'
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
									'Condition' => ['operator' => 'does not match', 'pattern' => 'item_pattern'],
									'actions' => [
										'Create enabled' => 'No',
										'Discover' => 'No',
										'Update interval' => [
											'Delay' => '50m',
											'Custom intervals' => [
												['Type' => 'Flexible', 'Interval' => '60s', 'Period' => '1-5,01:01-13:05'],
												['Type' => 'Scheduling', 'Interval' => 'wd1-3h10-17']
											]
										],
										'History storage period' => ['set' => 'Storage period', 'period' => '500d'],
										'Trend storage period' => ['set' => 'Storage period', 'period' => '200d']
									]
								],
								[
									'Object' => 'Trigger prototype',
									'Condition' => ['operator' => 'contains', 'pattern' => 'trigger_Pattern'],
									'actions' => [
										'Create enabled' => 'No',
										'Discover' => 'No',
										'Severity' => 'Warning',
//										'Tags' => [
//											['tag' => 'tag1', 'value' => 'value1'],
//											['tag' => 'tag2', 'value' => 'value2']
//										]
									]

								],
								[
									'Object' => 'Graph prototype',
									'Condition' => ['operator' => 'matches', 'pattern' => 'Graph_Pattern'],
									'actions' => [
										'Discover' => 'Yes'
									]
								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not match', 'pattern' => 'Host_Pattern'],
									'actions' => [
										'Create enabled' => 'Yes',
										'Discover' => 'Yes',
										'Create enabled' => 'Yes',
//										'Link templates' => [
//											'Test Item Template',
//											'Inheritance test template with host prototype'
//										],
										'Host inventory' => 'Disabled'
									]
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
									'Condition' => ['operator' => 'matches', 'pattern' => '2Graph_Pattern'],
									'actions' => [
										'Discover' => 'No'
									]

								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not match', 'pattern' => '2Host_Pattern'],
									'actions' => [
										'Create enabled' => 'Yes',
										'Discover' => 'No',
										'Create enabled' => 'Yes',
//										'Link templates' => [
//											'Test Item Template',
//											'Inheritance test template with host prototype'
//										],
										'Host inventory' => 'Automatic'
									]
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
		$this->page->login()->open('host_discovery.php?form=create&hostid='.self::HOST_ID);

		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill(['Name' => 'LLD with overrides',
					'Key' => 'lld_override'. microtime()]);
		$form->selectTab('Overrides');
		$form->invalidate();
		$override_container = $form->getField('Overrides')->asTable();

		foreach($data['overrides'] as $override){
			$override_container->query('button:Add')->one()->click();

			$override_overlay = $this->query('id:lldoverride_form')->waitUntilPresent()->one()->asForm();
			$override_overlay->fill($override['fields']);

			if (array_key_exists('Filters', $override)) {
				$this->fillParameters($override['Filters']['filter_conditions']);

				if (array_key_exists('Type of calculation', $override['Filters'])) {
					$override_overlay->query('id:overrides_evaltype')->waitUntilPresent()->one()
							->asDropdown()->fill($override['Filters']['Type of calculation']);

					if (array_key_exists('formula', $override['Filters'])) {
						$override_overlay->query('id:overrides_formula')->waitUntilPresent()->one()
							->fill($override['Filters']['formula']);
					}
				}
			}

			if (array_key_exists('Operations', $override)) {
				$operation_container = $override_overlay->getField('Operations')->asTable();

				foreach($override['Operations'] as $operation){
					$operation_container->query('button:Add')->one()->click();

					$operation_overlay = $this->query('id:lldoperation_form')->waitUntilPresent()->one()->asForm();
					$operation_overlay->getField('Object')->fill($operation['Object']);

					if (array_key_exists('Condition', $operation)) {
//						$operation_overlay->getFieldById('operator')->fill($operation['Condition']['operator']);
						$operation_overlay->query('xpath:.//select[@id="operator"]')->one()
							->asDropdown()->fill($operation['Condition']['operator']);
						$operation_overlay->getFieldById('value')->fill($operation['Condition']['pattern']);
					}

					if (array_key_exists('actions', $operation)) {
						foreach ($operation['actions'] as $field => $value){
							$operation_overlay->query('xpath:.//label[text()="'.
									$field.'"]/../input[@type="checkbox"]')->one()->asCheckbox()->check();
						}
					}
					$operation_overlay->submit();

					switch ($data['expected']) {
						case TEST_GOOD:
							$operation_overlay->waitUntilNotPresent();
							break;
						case TEST_BAD:
							$this->assertMessage(TEST_BAD, null, $override['error']);
							break;
					}
				}
			}

			$override_overlay->submit();

			switch ($data['expected']) {
				case TEST_GOOD:
					$override_overlay->waitUntilNotPresent();
					break;
				case TEST_BAD:
					$this->assertMessage(TEST_BAD, null, $override['error']);
					break;
			}

		}
	}
}

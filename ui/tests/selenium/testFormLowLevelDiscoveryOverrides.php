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

/**
 * @backup items
 */
class testFormLowLevelDiscoveryOverrides extends CWebTest {

	const HOST_ID = 40001;

	/*
	 * Overrides data for LLD creation.
	 */
	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'LLD with overrides',
						'Key' => 'lld_override'
					],
					'overrides' => [
						[
							'fields' => [
								'Name' => 'Override_1',
								'If filter matches' => 'Stop processing'
							],
							'Filters' => [
								'Type of calculation' => 'And',
								'filter_conditions' => [
									['macro' => '{#MACRO1}', 'operator' => 'matches', 'expression' => 'expression_1'],
									['macro' => '{#MACRO2}', 'operator' => 'does not match', 'expression' => 'expression_2']
								]
							],
							'Operations' => [
								[
									'Object' => 'Item prototype',
									'Condition' => ['operator' => 'does not equal', 'pattern' => 'item_pattern'],
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
								],
								[
									'Object' => 'Trigger prototype',
									'Condition' => ['operator' => 'contains', 'pattern' => 'trigger_Pattern'],
									'Create enabled' => 'No',
									'Discover' => 'No',
									'Severity' => 'Warning',
									'Tags' => [
										['tag' => 'tag1', 'value' => 'value1'],
										['tag' => 'tag2', 'value' => 'value2']
									]
								],
								[
									'Object' => 'Graph prototype',
									'Condition' => ['operator' => 'matches', 'pattern' => 'Graph_Pattern'],
									'Create enabled' => 'No',
									'Discover' => 'Yes'
								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not matche', 'pattern' => 'Host_Pattern'],
									'Create enabled' => 'Yes',
									'Discover' => 'Yes',
									'Create enabled' => 'Yes',
									'Link new templates' => [
										'Test Item Template',
										'Inheritance test template with host prototype'
									],
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
									'Condition' => ['operator' => 'matches', 'pattern' => '2Graph_Pattern'],
									'Create enabled' => 'Yes',
									'Discover' => 'No'
								],
								[
									'Object' => 'Host prototype',
									'Condition' => ['operator' => 'does not matche', 'pattern' => '2Host_Pattern'],
									'Create enabled' => 'Yes',
									'Discover' => 'No',
									'Create enabled' => 'Yes',
									'Link new templates' => [
										'Test Item Template',
										'Inheritance test template with host prototype'
									],
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
		$this->page->login()->open('host_discovery.php?form=create&hostid='.self::HOST_ID);

		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Overrides');
		$form->invalidate();
		$container = $form->getField('Overrides')->asTable();

		foreach($data['overrides'] as $i => $override){
			$container->query('button:Add')->one()->click();
			$overlay = $this->query('id:lldoverride_form')->waitUntilPresent()->one()->asForm();
			$overlay->fill($data['overrides'][$i]['fields']);
			$overlay->submit();
		}
	}
}

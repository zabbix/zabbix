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


class Services {

	/**
	 * Create data for service related test.
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('service.create', [
			[
				'name' => 'Child service of child service',
				'algorithm' => 1,
				'sortorder' => 1
			],
			[
				'name' => 'Child service with child service',
				'algorithm' => 1,
				'sortorder' => 2
			],
			[
				'name' => 'Parent for 2 levels of child services',
				'algorithm' => 1,
				'sortorder' => 3,
				'tags' => [
					[
						'tag' => 'test',
						'value' => 'test123'
					]
				]
			],
			[
				'name' => 'Service with multiple service tags',
				'algorithm' => 1,
				'sortorder' => 4,
				'tags' => [
					[
						'tag' => 'test',
						'value' => 'test456'
					],
					[
						'tag' => 'problem',
						'value' => 'true'
					]
				]
			],
			[
				'name' => 'Simple actions service',
				'algorithm' => 1,
				'sortorder' => 5,
				'problem_tags' => [
					[
						'tag' => 'problem',
						'operator' => 0,
						'value' => 'true'
					]
				],
				'tags' => [
					[
						'tag' => 'problem',
						'value' => 'false'
					],
					[
						'tag' => 'test',
						'value' => 'test789'
					]
				]
			],
			[
				'name' => 'Service for delete by checkbox',
				'algorithm' => 1,
				'sortorder' => 6
			],
			[
				'name' => 'Service for delete',
				'algorithm' => 1,
				'sortorder' => 7,
				'tags' => [
					[
						'tag' => 'remove_tag_2',
						'value' => 'remove_value_2'
					]
				]
			],
			[
				'name' => 'Parent for deletion from row',
				'algorithm' => 1,
				'sortorder' => 8
			],
			[
				'name' => 'Parent for child deletion from row',
				'algorithm' => 1,
				'sortorder' => 9
			],
			[
				'name' => 'Child 1',
				'algorithm' => 1,
				'sortorder' => 10
			],
			[
				'name' => 'Child 2',
				'algorithm' => 1,
				'sortorder' => 12
			],
			[
				'name' => 'Service for duplicate check',
				'algorithm' => 1,
				'sortorder' => 13
			],
			[
				'name' => 'Service for delete 2',
				'algorithm' => 1,
				'sortorder' => 14,
				'problem_tags' => [
					[
						'tag' => 'tag1',
						'operator' => 0,
						'value' => 'value1'
					],
					[
						'tag' => 'tag2',
						'operator' => 0,
						'value' => 'value2'
					],
					[
						'tag' => 'tag3',
						'operator' => 0,
						'value' => 'value3'
					],
					[
						'tag' => 'tag4',
						'operator' => 0,
						'value' => 'value4'
					]
				],
				'tags' => [
					[
						'tag' => 'remove_tag_1',
						'value' => 'remove_value_1'
					],
					[
						'tag' => 'remove_tag_2',
						'value' => 'remove_value_2'
					],
					[
						'tag' => '3rd_tag',
						'value' => '3rd_value'
					],
					[
						'tag' => '4th_tag',
						'value' => '4th_value'
					]
				]
			],
			[
				'name' => 'Service with problem tags',
				'algorithm' => 1,
				'sortorder' => 15,
				'problem_tags' => [
					[
						'tag' => 'test123',
						'value' => 'test456'
					],
					[
						'tag' => 'test',
						'value' => 'test789'
					]
				]
			],
			[
				'name' => 'Service for mass update',
				'algorithm' => 1,
				'sortorder' => 16,
				'tags' => [
					[
						'tag' => 'Replace_tag_1',
						'value' => 'replace_value_1'
					],
					[
						'tag' => 'Replace_tag_2',
						'value' => 'Replace_value_2'
					]
				]
			],
			[
				'name' => 'Clone parent',
				'algorithm' => 1,
				'sortorder' => 17
			],
			[
				'name' => 'Clone child 1',
				'algorithm' => 0,
				'sortorder' => 18,
				'weight' => 56,
				'propagation_rule' => 1,
				'propagation_value' => 3,
				'problem_tags' => [
					[
						'tag' => 'problem_tag_clone',
						'value' => 'problem_value_clone'
					]
				],
				'tags' => [
					[
						'tag' => 'tag_clone',
						'value' => 'value_clone'
					]
				]
			],
			[
				'name' => 'Clone child 2',
				'algorithm' => 1,
				'sortorder' => 19
			],
			[
				'name' => 'Clone child 3',
				'algorithm' => 1,
				'sortorder' => 20,
				'problem_tags' => [
					[
						'tag' => 'test1',
						'value' => 'value1'
					]
				]
			],
			[
				'name' => 'Parent for child creation',
				'algorithm' => 1,
				'sortorder' => 21,
				'tags' => [
					[
						'tag' => 'remove_tag_3',
						'value' => 'remove_value_3'
					]
				]
			],
			[
				'name' => 'Update service',
				'algorithm' => 1,
				'sortorder' => 22,
				'tags' => [
					[
						'tag' => 'Replace_tag_3',
						'value' => 'Replace_value_3'
					]
				],
				'status_rules' => [
					[
						'type' => 1,
						'limit_value' => 50,
						'limit_status' => 3,
						'new_status' => 4
					],
					[
						'type' => 7,
						'limit_value' => 33,
						'limit_status' => 2,
						'new_status' => 5
					]
				]
			],
			[
				'name' => 'Service with problem',
				'algorithm' => 1,
				'sortorder' => 23,
				'tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					]
				]
			],
			[
				'name' => 'Test order',
				'algorithm' => 1,
				'sortorder' => 0
			],
			[
				'name' => '1',
				'algorithm' => 1,
				'sortorder' => 2,
				'weight' => 10
			],
			[
				'name' => '2',
				'algorithm' => 1,
				'sortorder' => 2,
				'weight' => 10
			],
			[
				'name' => '3',
				'algorithm' => 1,
				'sortorder' => 2,
				'weight' => 10
			]
		]);

		$serviceids = CDataHelper::getIds('name');

		CDataHelper::call('service.update', [
			[
				'serviceid' =>  $serviceids['Child service of child service'],
				'parents' => [
					[
						'serviceid' => $serviceids['Child service with child service']
					]
				]
			],
			[
				'serviceid' => $serviceids['Child service with child service'],
				'parents' => [
					[
						'serviceid' => $serviceids['Parent for 2 levels of child services']
					]
				]
			],
			[
				'serviceid' => $serviceids['Child 1'],
				'parents' => [
					[
						'serviceid' => $serviceids['Parent for child deletion from row']
					]
				]
			],
			[
				'serviceid' => $serviceids['Child 2'],
				'parents' => [
					[
						'serviceid' => $serviceids['Parent for deletion from row']
					]
				]
			],
			[
				'serviceid' => $serviceids['Clone child 1'],
				'parents' => [
					[
						'serviceid' => $serviceids['Clone parent']
					]
				]
			],
			[
				'serviceid' => $serviceids['Clone child 2'],
				'parents' => [
					[
						'serviceid' => $serviceids['Clone parent']
					]
				]
			],
			[
				'serviceid' => $serviceids['Clone child 3'],
				'parents' => [
					[
						'serviceid' => $serviceids['Clone parent']
					]
				]
			],
			[
				'serviceid' => $serviceids['1'],
				'parents' => [
					[
						'serviceid' => $serviceids['Test order']
					]
				]
			],
			[
				'serviceid' => $serviceids['2'],
				'parents' => [
					[
						'serviceid' => $serviceids['Test order']
					]
				]
			],
			[
				'serviceid' => $serviceids['3'],
				'parents' => [
					[
						'serviceid' => $serviceids['Test order']
					]
				]
			]
		]);

		// Set service into Disaster (status 5) state.
		DBexecute("UPDATE services SET status=5 WHERE serviceid=".zbx_dbstr($serviceids['Service with problem']));

		return ['serviceids' => $serviceids];
	}
}

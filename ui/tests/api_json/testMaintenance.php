<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup maintenances
 */
class testMaintenance extends CAPITest {
	public static function getMaintenanceCreateData() {
		$n = 0;
		$def_options = [
			'active_since' => '1514757600',
			'active_till' => '1546207200',
			'groups' => [['groupid' => '2']],
			'timeperiods' => [
				[
					'timeperiod_type' => 3,
					'every' => 1,
					'dayofweek' => 64,
					'start_time' => 3600,
					'period' => 7200
				]
			]
		];

		return [
			// Success. Created maintenance without tags.
			[
				'request_data' => [
					'name' => 'M'.++$n
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance without tags.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => []
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						'tag' => 'tag'
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with condition operators.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag1',
							'operator' => 0, // CONDITION_OPERATOR_EQUAL
							'value' => 'value1'
						],
						[
							'tag' => 'tag2',
							'operator' => 1, // CONDITION_OPERATOR_NOT_EQUAL
							'value' => 'value2'
						],
						[
							'tag' => 'tag3',
							'operator' => 2, // CONDITION_OPERATOR_LIKE
							'value' => 'value3'
						],
						[
							'tag' => 'tag4',
							'operator' => 3, // CONDITION_OPERATOR_NOT_LIKE
							'value' => 'value4'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag with empty value.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => ''
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple tags.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag1',
							'operator' => 0,
							'value' => 'value1'
						],
						[
							'tag' => 'tag2',
							'operator' => 2,
							'value' => 'value2'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple tags having the same tag name.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						],
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => 2,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple tags.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						],
						[
							'tag' => 'tag1'
						],
						[
							'tag' => 'tag2',
							'value' => 'value'
						],
						[
							'tag' => 'tag3',
							'operator' => 2,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						],
						[
							'tag' => 'tag'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 2, ) already exists.'
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 2, value) already exists.'
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 0, value) already exists.'
			],
			// Fail. Possible values for "tags_evaltype" are 0 (And/Or) and 2 (Or).
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags_evaltype' => 1
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags_evaltype": value must be one of 0, 2.'
			],
			// Fail. Parameter "tag" is mandatory.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			// Fail. Parameter "tag" cannot be empty.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => ''
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						'abcd' => ''
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1": unexpected parameter "abcd".'
			],
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							999 => 'aaa'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1": unexpected parameter "999".'
			],
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'aaa' => 'bbb'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1": unexpected parameter "aaa".'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": an integer is expected.'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": an integer is expected.'
			],
			// Fail. Possible values for "operator" are 0 (Equals), 1 (Does not equal), 2 (Contains) and
			// 3 (Does not contain).
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": value must be one of 0, 1, 2, 3.'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 'aaa'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": an integer is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			// Fail. Tag value must be empty.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'maintenance_type' => 1,
					'tags' => [
						[
							'tag' => 'tag'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "tags".'
			],
			// Fail. Active since bigger active till.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1546207200',
					'active_till' => '1514757600'
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/active_till": cannot be less than or equal to the value of parameter "/1/active_since".'
			],
			// Success. Created maintenance with one group.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						'groupid' => 2
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one group.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						['groupid' => 2]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple groups.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						['groupid' => 2],
						['groupid' => 4]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Fail. Unexpected parameter in groups.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						'abcd' => '1234'
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/groups/1": unexpected parameter "abcd".'
			],
			// Fail. Wrong group.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						['groupid' => 999]
					]
				] + $def_options,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Fail. Duplicate group.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [
						['groupid' => 999],
						['groupid' => 1000],
						['groupid' => 999]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/groups/3": value (groupid)=(999) already exists.'
			],
			// Success. Created maintenance with one host.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						'hostid' => 90020
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one host.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						['hostid' => 90020]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple hosts.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						['hostid' => 90020],
						['hostid' => 90021]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Fail. Unexpected parameter in hosts.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						'abcd' => '1234'
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/hosts/1": unexpected parameter "abcd".'
			],
			// Fail. Wrong host.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						['hostid' => 999]
					]
				] + array_diff_key($def_options, array_flip(['groups'])),
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Fail. Duplicate host.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => [
						['hostid' => 999],
						['hostid' => 1000],
						['hostid' => 999]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/hosts/3": value (hostid)=(999) already exists.'
			],
			// Success. Created maintenance with triggers as its only target.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'triggers' => [
						['triggerid' => 134000]
					]
				] + array_diff_key($def_options, array_flip(['groups'])),
				'expected_error' => null
			],
			// Fail. Trigger ID is mandatory.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'triggers' => [[]]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/triggers/1": the parameter "triggerid" is missing.'
			],
			// Fail. Trigger objects may contain only triggerid.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'triggers' => [
						[
							'triggerid' => 134000,
							'description' => 'unexpected'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/triggers/1": unexpected parameter "description".'
			],
			// Fail. Duplicate triggers are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'triggers' => [
						['triggerid' => 134000],
						['triggerid' => 134000]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/triggers/2": value (triggerid)=(134000) already exists.'
			],
			// Fail. Trigger must exist and be editable.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'triggers' => [
						['triggerid' => 999999]
					]
				] + $def_options,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Fail. Triggers are not supported without data collection.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'maintenance_type' => 1,
					'triggers' => [
						['triggerid' => 134000]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/triggers": should be empty.'
			],
			// Success. Created maintenance with event name conditions.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'event_names' => [
						['value' => 'Database unavailable'],
						['operator' => 3, 'value' => 'Test event']
					]
				] + $def_options,
				'expected_error' => null
			],
			// Fail. Event name value is mandatory.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'event_names' => [[]]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/event_names/1": the parameter "value" is missing.'
			],
			// Fail. Event name value cannot be empty.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'event_names' => [
						['value' => '']
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/event_names/1/value": cannot be empty.'
			],
			// Fail. Event name operator must be Contains or Does not contain.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'event_names' => [
						['operator' => 0, 'value' => 'Problem']
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/event_names/1/operator": value must be one of 2, 3.'
			],
			// Fail. Event name conditions are not supported without data collection.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'maintenance_type' => 1,
					'event_names' => [
						['value' => 'Problem']
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/1/event_names": should be empty.'
			],
			// Fail. Empty groups.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => []
				] + $def_options,
				'expected_error' => 'At least one host group, host or trigger must be selected.'
			],
			// Fail. Empty hosts.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'hosts' => []
				] + array_diff_key($def_options, array_flip(['groups'])),
				'expected_error' => 'At least one host group, host or trigger must be selected.'
			],
			// Fail. Empty groups and hosts.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'groups' => [],
					'hosts' => []
				] + $def_options,
				'expected_error' => 'At least one host group, host or trigger must be selected.'
			],
			// Fail. No groups and hosts.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'every' => 1,
							'dayofweek' => 64,
							'start_time' => 3600,
							'period' => 7200
						]
					]
				],
				'expected_error' => 'At least one host group, host or trigger must be selected.'
			],
			// Fail. Same name.
			[
				'request_data' => [[
					'name' => 'Same name'
				] + $def_options,
				[
					'name' => 'Same name'
				] + $def_options],
				'expected_error' => 'Invalid parameter "/2": value (name)=(Same name) already exists.'
			],
			// Success. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
						]
					]
				],
				'expected_error' => null
			],
			// Success. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						'timeperiod_type' => 0
					]
				],
				'expected_error' => null
			],
			// Success. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0
						]
					]
				],
				'expected_error' => null
			],
			// Success. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'start_date' => '1514764800'
						]
					]
				],
				'expected_error' => null
			],
			// Success. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'period' => '600',
							'start_date' => '1514764800'
						]
					]
				],
				'expected_error' => null
			],
			// Fail. Empty time periods.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods": cannot be empty.'
			],
			// Fail. Wrong period type.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/timeperiod_type": value must be one of 0, 2, 3, 4.'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
							'abcd' => 0
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "abcd".'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'period' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/period": value must be one of 300-86399940.'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'period' => '2147483648'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/period": a number is too large.'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'start_date' => '-1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/start_date": an unsigned integer is expected.'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 0,
							'start_date' => '2147483648'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/start_date": a timestamp is too large.'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'start_time' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "start_time".'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'every' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "every".'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'day' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "day".'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'dayofweek' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "dayofweek".'
			],
			// Fail. One time period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'month' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "month".'
			],
			// Success. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2
						]
					]
				],
				'expected_error' => null
			],
			// Success. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'every' => 1
						]
					]
				],
				'expected_error' => null
			],
			// Success. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'period' => '600',
							'timeperiod_type' => 2,
							'start_time' => '7200',
							'every' => 1
						]
					]
				],
				'expected_error' => null
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'start_time' => '-1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/start_time": an unsigned integer is expected.'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'start_time' => '86400'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/start_time": value must be one of 00:00-23:59.'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'every' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/every": value must be one of 1-999.'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'every' => '2147483648'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/every": a number is too large.'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'start_date' => '1514764800'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "start_date".'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'day' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "day".'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'dayofweek' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "dayofweek".'
			],
			// Fail. Daily period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 2,
							'month' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "month".'
			],
			// Success. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => '64'
						]
					]
				],
				'expected_error' => null
			],
			// Success. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'period' => 7200,
							'timeperiod_type' => 3,
							'start_time' => 7200,
							'every' => 1,
							'dayofweek' => '64'
						]
					]
				],
				'expected_error' => null
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'every' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/every": value must be one of 1-99.'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": the parameter "dayofweek" is missing.'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": an integer is expected.'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => '0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": value must be one of 1-127.'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => 128
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": value must be one of 1-127.'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => 1,
							'start_date' => '1514764800'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "start_date".'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => 1,
							'day' => '30'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "day".'
			],
			// Fail. Weekly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 3,
							'dayofweek' => 1,
							'month' => '30'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "month".'
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'dayofweek' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'every' => 2,
							'dayofweek' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 2,
							'dayofweek' => 0,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 2,
							'dayofweek' => 0,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'every' => 2,
							'day' => 2,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Success. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'every' => 2,
							'day' => 0,
							'dayofweek' => 5,
							'month' => 240
						]
					]
				],
				'expected_error' => null
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'every' => 0,
							'dayofweek' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/every": value must be one of 1, 2, 3, 4, 5.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'every' => 6,
							'dayofweek' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/every": value must be one of 1, 2, 3, 4, 5.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 0,
							'month' => 240
						]
					]
				],
				'expected_error' => 'At least one day of the week or day of the month must be specified.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => '',
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/day": an integer is expected.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => '-1',
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/day": value must be one of 0-31.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 32,
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/day": value must be one of 0-31.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'dayofweek' => 1,
							'month' => 240
						]
					]
				],
				'expected_error' => 'Day of the week and day of the month cannot be specified simultaneously.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'dayofweek' => 0,
							'month' => 240
						]
					]
				],
				'expected_error' => 'At least one day of the week or day of the month must be specified.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'dayofweek' => '',
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": an integer is expected.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'dayofweek' => '-1',
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": value must be one of 0-127.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'dayofweek' => 128,
							'month' => 240
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/dayofweek": value must be one of 0-127.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": the parameter "month" is missing.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'month' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/month": an integer is expected.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'month' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/month": value must be one of 1-4095.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'month' => 4096
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1/month": value must be one of 1-4095.'
			],
			// Fail. Monthly period.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'active_since' => '1514757600',
					'active_till' => '1546207200',
					'groups' => [
						['groupid' => 2]
					],
					'timeperiods' => [
						[
							'timeperiod_type' => 4,
							'day' => 1,
							'month' => 1,
							'start_date' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/timeperiods/1": unexpected parameter "start_date".'
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceCreateData
	 */
	public function testMaintenance_Create($request_data, $expected_error = null) {
		$this->call('maintenance.create', $request_data, $expected_error);
	}

	public static function getMaintenanceUpdateValidationData(): array {
		return [
			'Non-empty triggers when changing to no data collection' => [
				'request_data' => [
					'maintenanceid' => 60003,
					'maintenance_type' => MAINTENANCE_TYPE_NODATA,
					'triggers' => [['triggerid' => 134000]]
				],
				'expected_error' => 'Invalid parameter "/1/triggers": should be empty.'
			],
			'Non-empty event names when changing to no data collection' => [
				'request_data' => [
					'maintenanceid' => 60003,
					'maintenance_type' => MAINTENANCE_TYPE_NODATA,
					'event_names' => [['value' => 'Problem']]
				],
				'expected_error' => 'Invalid parameter "/1/event_names": should be empty.'
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceUpdateValidationData
	 */
	public function testMaintenance_UpdateValidation(array $request_data, string $expected_error): void {
		$this->call('maintenance.update', $request_data, $expected_error);
	}

	public static function getMaintenanceGetData() {
		return [
			[
				'request_data' => [
					'output' => ['tags_evaltype'],
					'selectTags' => 'extend'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceGetData
	 */
	public function testMaintenance_Get($request_data, $expected_error = null) {
		$result = $this->call('maintenance.get', $request_data, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $maintenance) {
				$this->assertArrayHasKey('tags_evaltype', $maintenance);
				$this->assertArrayHasKey('tags', $maintenance);
			}
		}
	}
}

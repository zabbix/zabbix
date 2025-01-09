<?php declare(strict_types = 0);
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


require_once __DIR__.'/../include/CAPITest.php';

/**
 * @backup services
 */
class testServices extends CAPITest {

	public static function service_create_data_invalid(): array {
		return [
			[
				'service' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			[
				'service' => [null],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			[
				'service' => [true],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			[
				'service' => [0],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			[
				'service' => [''],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],

			// Required fields.
			[
				'service' => [[]],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'service' => [
					'name' => null
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				'service' => [
					'name' => true
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				'service' => [
					'name' => 0
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				'service' => [
					'name' => []
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				'service' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'service' => [
					'name' => str_repeat('a', DB::getFieldLength('services', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'service' => [
					'name' => 'foo'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "algorithm" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => null
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => true
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => []
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ''
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => -1
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_CALC_SET_OK,
						ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
						ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => 999
				],
				'expected_error' => 'Invalid parameter "/1/algorithm": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_CALC_SET_OK,
						ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
						ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "sortorder" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => null
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => true
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => []
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => ''
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => -1
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": value must be one of 0-999.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 9999
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": value must be one of 0-999.'
			],
			// Optional fields.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => null
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => true
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => []
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => ''
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => -1
				],
				'expected_error' => 'Invalid parameter "/1/weight": value must be one of 0-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => 9999999
				],
				'expected_error' => 'Invalid parameter "/1/weight": value must be one of 0-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => null
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => true
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => []
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
						'sortorder' => 0,
					'propagation_rule' => ''
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => -1
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_PROPAGATION_AS_IS,
						ZBX_SERVICE_STATUS_PROPAGATION_INCREASE,
						ZBX_SERVICE_STATUS_PROPAGATION_DECREASE,
						ZBX_SERVICE_STATUS_PROPAGATION_IGNORE,
						ZBX_SERVICE_STATUS_PROPAGATION_FIXED
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => 999
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_PROPAGATION_AS_IS,
						ZBX_SERVICE_STATUS_PROPAGATION_INCREASE,
						ZBX_SERVICE_STATUS_PROPAGATION_DECREASE,
						ZBX_SERVICE_STATUS_PROPAGATION_IGNORE,
						ZBX_SERVICE_STATUS_PROPAGATION_FIXED
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_AS_IS
				],
				'expected_error' => 'Cannot specify "propagation_rule" parameter without specifying "propagation_value" parameter for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => null
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => true
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => []
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => ''
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => -1
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => 1
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_value' => ZBX_MAX_INT32 + 1
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": a number is too large.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_INCREASE,
					'propagation_value' => 0
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_INCREASE,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_DECREASE,
					'propagation_value' => 0
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_DECREASE,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_IGNORE,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_FIXED,
					'propagation_value' => -2
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_FIXED,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],

			// Read-only fields.
			[
				'service' => [
					'serviceid' => 1
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "serviceid".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status' => ZBX_SEVERITY_OK
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "status".'
			],

			// Child services.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => null
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => true
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => ''
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => -1
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => 999
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/children/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/children/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [0]
				],
				'expected_error' => 'Invalid parameter "/1/children/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/children/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1": the parameter "serviceid" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => '1.0']
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => 999] // Non-existing service.
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => 2],
						['serviceid' => 2]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/2": value (serviceid)=(2) already exists.'
			],

			// Parent services.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => null
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => true
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => ''
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => -1
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => 999
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [0]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/parents/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1": the parameter "serviceid" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/children/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => '1.0']
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/1/serviceid": a number is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => 999] // Non-existing service.
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'parents' => [
						['serviceid' => 2],
						['serviceid' => 2]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parents/2": value (serviceid)=(2) already exists.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'children' => [
						['serviceid' => 2]
					],
					'parents' => [
						['serviceid' => 2]
					]
				],
				'expected_error' => 'Services form a circular dependency.'
			],

			// Tags.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => null
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => true
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => 0
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [1]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => str_repeat('a', DB::getFieldLength('service_tag', 'tag') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": value is too long.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			[
			'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => str_repeat('a', DB::getFieldLength('service_tag', 'value') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value is too long.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['value' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => 'bar'],
						['tag' => 'foo', 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, value)=(foo, bar) already exists.'
			],

			// Problem tags.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => null
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => true
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => 0
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => ''
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [0]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": cannot be empty.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => str_repeat('a', DB::getFieldLength('service_tag', 'tag') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/tag": value is too long.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => '1.0']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": value must be one of '.
					implode(', ', [ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => 999]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": value must be one of '.
					implode(', ', [ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'value' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'value' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'value' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'value' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/value": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'value' => str_repeat('a', DB::getFieldLength('service_tag', 'value') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/value": value is too long.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['value' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],

			// Status rules.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => null
				],
				'expected_error' => 'Invalid parameter "/1/status_rules": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => true
				],
				'expected_error' => 'Invalid parameter "/1/status_rules": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => 0
				],
				'expected_error' => 'Invalid parameter "/1/status_rules": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => ''
				],
				'expected_error' => 'Invalid parameter "/1/status_rules": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [0]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": the parameter "type" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => '1.0']
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_W_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => 999]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/type": value must be one of '.
					implode(', ', [
						ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_W_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
						ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						['type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": the parameter "limit_value" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => '1.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1000001
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
							'limit_value' => 1000001
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_W_GE,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_W_GE,
							'limit_value' => 1000001
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_W_L,
							'limit_value' => 1000001
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE,
							'limit_value' => 101
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
							'limit_value' => 101
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE,
							'limit_value' => 101
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_WP_L,
							'limit_value' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_WP_L,
							'limit_value' => 101
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_value": value must be one of 1-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": the parameter "limit_status" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => '1.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => -2
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": value must be one of '.
					implode(', ', array_merge(
						[ZBX_SEVERITY_OK],
						range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)
					)).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => TRIGGER_SEVERITY_COUNT
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/limit_status": value must be one of '.
					implode(', ', array_merge(
						[ZBX_SEVERITY_OK],
						range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)
					)).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1": the parameter "new_status" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => '1.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => ZBX_SEVERITY_OK
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": value must be one of '.
					implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => TRIGGER_SEVERITY_COUNT
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/1/new_status": value must be one of '.
					implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'status_rules' => [
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => TRIGGER_SEVERITY_NOT_CLASSIFIED
						],
						[
							'type' => ZBX_SERVICE_STATUS_RULE_TYPE_N_GE,
							'limit_value' => 1,
							'limit_status' => ZBX_SEVERITY_OK,
							'new_status' => TRIGGER_SEVERITY_NOT_CLASSIFIED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/status_rules/2": value (type, limit_value, limit_status)=('.
					ZBX_SERVICE_STATUS_RULE_TYPE_N_GE.', 1, '.ZBX_SEVERITY_OK.') already exists.'
			]
		];
	}

	public static function service_create_data_valid(): array {
		return [
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0
				],
				'expected_error' => null
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'sortorder' => 0,
					'weight' => 0,
					'propagation_rule' => 0,
					'propagation_value' => 0,
					'children' => [],
					'parents' => [],
					'tags' => [
						['tag' => 'foo', 'value' => 'bar']
					],
					'problem_tags' => [
						['tag' => 'foo', 'value' => 'bar']
					],
					'status_rules' => []
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider service_create_data_invalid
	 * @dataProvider service_create_data_valid
	 */
	public function testServices_Create(array $services, ?string $expected_error): void {
		$response = $this->call('service.create', $services, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		// Accept single and multiple entities just like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $services)) {
			$services = zbx_toArray($services);
		}

		foreach ($response['result']['serviceids'] as $index => $serviceid) {
			$db_service = CDBHelper::getRow(
				'SELECT s.serviceid,s.name'.
				' FROM services s'.
				' WHERE s.serviceid='.zbx_dbstr($serviceid)
			);

			// Required fields.
			$this->assertNotEmpty($db_service['name']);
			$this->assertSame($db_service['name'], $services[$index]['name']);
		}
	}

	public static function service_delete_data_invalid(): array {
		return [
			[
				'service' => [null],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => [true],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => [[]],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => ['1.0'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => [-1],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => [999], // Non-existing service.
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	public static function service_delete_data_valid(): array {
		return [
			[
				'service' => ['1'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider service_delete_data_invalid
	 * @dataProvider service_delete_data_valid
	 */
	public function testServices_Delete(array $services, ?string $expected_error): void {
		$response = $this->call('service.delete', $services, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		foreach ($response['result']['serviceids'] as $serviceid) {
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT s.serviceid FROM services s WHERE s.serviceid='.zbx_dbstr($serviceid)
			));
		}
	}

	public static function service_get_data(): array {
		return [
			// Input validation.
			[
				'request' => [
					'output' => [],
					'serviceids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'serviceids' => ['1.0']
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => [-1]
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => ['1.0']
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'parentids' => [-1]
				],
				'expected' => [
					'error' => 'Invalid parameter "/parentids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => ['1.0']
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'childids' => [-1]
				],
				'expected' => [
					'error' => 'Invalid parameter "/childids/1": a number is expected.',
					'result' => []
				]
			],

			[
				'request' => [
					'output' => [],
					'evaltype' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => '1.0'
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => 1
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": value must be one of '.
						implode(', ', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]).'.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'evaltype' => 999
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": value must be one of '.
						implode(', ', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]).'.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => ['tag' => 'foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": the parameter "tag" is missing.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1": unexpected parameter "0".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => 0]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'value' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'value' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'value' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'value' => 0]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => '']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => '1.0']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => -1]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": value must be one of '.
						implode(', ', [
							TAG_OPERATOR_LIKE,
							TAG_OPERATOR_EQUAL,
							TAG_OPERATOR_NOT_LIKE,
							TAG_OPERATOR_NOT_EQUAL,
							TAG_OPERATOR_EXISTS,
							TAG_OPERATOR_NOT_EXISTS
						]).'.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tags' => [
						['tag' => '', 'operator' => 999]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/tags/1/operator": value must be one of '.
						implode(', ', [
							TAG_OPERATOR_LIKE,
							TAG_OPERATOR_EQUAL,
							TAG_OPERATOR_NOT_LIKE,
							TAG_OPERATOR_NOT_EQUAL,
							TAG_OPERATOR_EXISTS,
							TAG_OPERATOR_NOT_EXISTS
						]).'.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => ['tag' => 'foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": the parameter "tag" is missing.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1": unexpected parameter "0".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => 0]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/tag": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'value' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'value' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'value' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'value' => 0]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/value": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => []]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => '']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => '1.0']
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => -1]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": value must be one of '.
						implode(', ', [
							TAG_OPERATOR_LIKE,
							TAG_OPERATOR_EQUAL,
							TAG_OPERATOR_NOT_LIKE,
							TAG_OPERATOR_NOT_EQUAL,
							TAG_OPERATOR_EXISTS,
							TAG_OPERATOR_NOT_EXISTS
						]).'.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'problem_tags' => [
						['tag' => '', 'operator' => 999]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/problem_tags/1/operator": value must be one of '.
						implode(', ', [
							TAG_OPERATOR_LIKE,
							TAG_OPERATOR_EQUAL,
							TAG_OPERATOR_NOT_LIKE,
							TAG_OPERATOR_NOT_EQUAL,
							TAG_OPERATOR_EXISTS,
							TAG_OPERATOR_NOT_EXISTS
						]).'.',
					'result' => []
				]
			],

			// Input validation, filter object.
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => ''
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => [null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/serviceid/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => [true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/serviceid/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => [[]]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/serviceid/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => ['']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => ['1.0']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => [-1]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/name": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => 0
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => [null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/name/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => [true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/name/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => [[]]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/name/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'name' => [0]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/status": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => ''
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => '1.0'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => -2
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => TRIGGER_SEVERITY_COUNT
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => [true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/status/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => ['']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => ['1.0']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => [-2]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => [TRIGGER_SEVERITY_COUNT]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/algorithm": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => ''
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => '1.0'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => -1
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => [null]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/algorithm/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => [true]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/algorithm/1": a character string, integer or floating point value is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => ['']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => ['1.0']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => [-1]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'algorithm' => [999]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],

			// Related objects.
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": the parameter "period_from" is missing.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [['period_from' => 1638316800]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1": the parameter "period_to" is missing.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [
						[
							'period_from' => 'yesterday',
							'period_to' => 1638316900
						]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1/period_from": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [
						[
							'period_from' => 1638316800,
							'period_to' => 'today'
						]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1/period_to": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [
						[
							'period_from' => 9638316800,
							'period_to' => 9638316900
						]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1/period_from": a number is too large.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusTimeline' => [
						[
							'period_from' => 1638316800,
							'period_to' => 9638316900
						]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusTimeline/1/period_to": a number is too large.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren": value must be one of "extend", "count".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectChildren' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectChildren/1": value must be one of "serviceid", "uuid", "name", "status", "algorithm", "sortorder", "weight", "propagation_rule", "propagation_value", "description", "created_at", "readonly".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents": value must be one of "extend", "count".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectParents' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectParents/1": value must be one of "serviceid", "uuid", "name", "status", "algorithm", "sortorder", "weight", "propagation_rule", "propagation_value", "description", "created_at", "readonly".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags": value must be one of "extend", "count".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectProblemTags' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectProblemTags/1": value must be one of "tag", "operator", "value".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules": value must be one of "extend", "count".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectStatusRules' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectStatusRules/1": value must be one of "type", "limit_value", "limit_status", "new_status".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags": an array or a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags": value must be one of "extend", "count".',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => [0]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags/1": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'selectTags' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectTags/1": value must be one of "tag", "value".',
					'result' => []
				]
			]
		];
	}

	/**
	 * @dataProvider service_get_data
	 */
	public function testServices_Get(array $request, array $expected): void {
		$response = $this->call('service.get', $request, $expected['error']);

		if ($expected['error'] !== null) {
			return;
		}

		$this->assertEquals($response['result'], $expected['result']);
	}

	public static function service_update_data_invalid(): array {
		return [
			[
				'service' => [[
					'name' => 'foo'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "serviceid" is missing.'
			]
		];
	}

	public static function service_update_data_valid(): array {
		return [
			[
				'service' => [[
					'serviceid' => 2
				]],
				'expected_error' => null
			],
			[
				'service' => [[
					'serviceid' => 2,
					'name' => 'bar'
				]],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider service_update_data_invalid
	 * @dataProvider service_update_data_valid
	 */
	public function testServices_Update(array $services, ?string $expected_error): void {
		$response = $this->call('service.update', $services, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$db_services = CDBHelper::getAll(
			'SELECT s.serviceid,s.name,s.status,s.algorithm,s.sortorder,s.weight,s.propagation_rule,s.propagation_value'.
			' FROM services s'.
			' WHERE '.dbConditionId('s.serviceid', $response['result']['serviceids']).
			' ORDER BY s.serviceid ASC'
		);

		foreach ($db_services as $index => $db_service) {
			$service = $services[$index];

			if (array_key_exists('name', $service)) {
				$this->assertEquals($service['name'], $db_service['name']);
			}
		}
	}
}

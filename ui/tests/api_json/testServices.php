<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
				'expected_error' => 'Invalid parameter "/1": the parameter "showsla" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => null
				],
				'expected_error' => 'Invalid parameter "/1/showsla": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => true
				],
				'expected_error' => 'Invalid parameter "/1/showsla": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => []
				],
				'expected_error' => 'Invalid parameter "/1/showsla": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => ''
				],
				'expected_error' => 'Invalid parameter "/1/showsla": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/showsla": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => -1
				],
				'expected_error' => 'Invalid parameter "/1/showsla": value must be one of '.
					implode(', ', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => 999
				],
				'expected_error' => 'Invalid parameter "/1/showsla": value must be one of '.
					implode(', ', [SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "sortorder" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => null
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => true
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => []
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => ''
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => -1
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": value must be one of 0-999.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 9999
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": value must be one of 0-999.'
			],

			// Optional fields.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => null
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => true
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => []
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => ''
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => -1
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": value must be within the range of 0-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => 999
				],
				'expected_error' => 'Invalid parameter "/1/goodsla": value must be within the range of 0-100.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => null
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => true
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => []
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => ''
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/weight": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => -1
				],
				'expected_error' => 'Invalid parameter "/1/weight": value must be one of 0-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'weight' => 9999999
				],
				'expected_error' => 'Invalid parameter "/1/weight": value must be one of 0-1000000.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => null
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => true
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => []
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => ''
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/propagation_rule": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_AS_IS
				],
				'expected_error' => 'Cannot specify "propagation_rule" parameter without specifying "propagation_value" parameter for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => null
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => true
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => []
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => ''
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => -1
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => 1
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_value' => ZBX_MAX_INT32 + 1
				],
				'expected_error' => 'Invalid parameter "/1/propagation_value": a number is too large.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'propagation_rule' => ZBX_SERVICE_STATUS_PROPAGATION_FIXED,
					'propagation_value' => 999
				],
				'expected_error' => 'Incompatible "propagation_rule" and "propagation_value" parameters for service "foo".'
			],

			// Child services.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'children' => null
				],
				'expected_error' => 'Invalid parameter "/1/children": an array is expected.'
			],

			// Parent services.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'parents' => null
				],
				'expected_error' => 'Invalid parameter "/1/parents": an array is expected.'
			],

			// Tags.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => null
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => true
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => 0
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => [1]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'tags' => [
						['tag' => 'foo', 'value' => 'bar'],
						['tag' => 'foo', 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, value)=(foo, bar) already exists.'
			],

			// Times.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => null
				],
				'expected_error' => 'Invalid parameter "/1/times": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => true
				],
				'expected_error' => 'Invalid parameter "/1/times": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => 0
				],
				'expected_error' => 'Invalid parameter "/1/times": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => ''
				],
				'expected_error' => 'Invalid parameter "/1/times": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [1]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/times/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": the parameter "type" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => null]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => true]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => []]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => '1.0']
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": value must be one of '.
					implode(', ', [
						SERVICE_TIME_TYPE_UPTIME,
						SERVICE_TIME_TYPE_DOWNTIME,
						SERVICE_TIME_TYPE_ONETIME_DOWNTIME
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						['type' => 999]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/type": value must be one of '.
					implode(', ', [
						SERVICE_TIME_TYPE_UPTIME,
						SERVICE_TIME_TYPE_DOWNTIME,
						SERVICE_TIME_TYPE_ONETIME_DOWNTIME
					]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": the parameter "ts_from" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => '1.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_DOWNTIME,
							'ts_from' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_DOWNTIME,
							'ts_from' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
							'ts_from' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": value must be one of 0-'.ZBX_MAX_INT32.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
							'ts_from' => ZBX_MAX_INT32 + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_from": a number is too large.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1": the parameter "ts_to" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => '1.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": an integer is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_DOWNTIME,
							'ts_from' => 0,
							'ts_to' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_DOWNTIME,
							'ts_from' => 0,
							'ts_to' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
							'ts_from' => 0,
							'ts_to' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": value must be one of 0-'.ZBX_MAX_INT32.'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_ONETIME_DOWNTIME,
							'ts_from' => 0,
							'ts_to' => ZBX_MAX_INT32 + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/ts_to": a number is too large.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => 0,
							'note' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/note": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => 0,
							'note' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/note": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => 0,
							'note' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/note": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => 0,
							'note' => 0
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/note": a character string is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'times' => [
						[
							'type' => SERVICE_TIME_TYPE_UPTIME,
							'ts_from' => 0,
							'ts_to' => 0,
							'note' => str_repeat('a', DB::getFieldLength('services_times', 'note') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/times/1/note": value is too long.'
			],

			// Problem tags.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => null
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => true
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => 0
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => ''
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [null]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [true]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [1]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": an array is expected.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => -1]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": value must be one of '.
					implode(', ', [SERVICE_TAG_OPERATOR_EQUAL, SERVICE_TAG_OPERATOR_LIKE]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => 999]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1/operator": value must be one of '.
					implode(', ', [SERVICE_TAG_OPERATOR_EQUAL, SERVICE_TAG_OPERATOR_LIKE]).'.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['operator' => SERVICE_TAG_OPERATOR_EQUAL]
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/1": the parameter "tag" is missing.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
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
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_EQUAL, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_EQUAL, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_LIKE, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_LIKE, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'problem_tags' => [
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_EQUAL, 'value' => 'bar'],
						['tag' => 'foo', 'operator' => SERVICE_TAG_OPERATOR_LIKE, 'value' => 'bar']
					]
				],
				'expected_error' => 'Invalid parameter "/1/problem_tags/2": value (tag, value)=(foo, bar) already exists.'
			],

			// Status rules.
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0,
					'status_rules' => null
				],
				'expected_error' => 'Invalid parameter "/1/status_rules": an array is expected.'
			]
		];
	}

	public static function service_create_data_valid(): array {
		return [
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'sortorder' => 0
				],
				'expected_error' => null
			],
			[
				'service' => [
					'name' => 'foo',
					'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK,
					'showsla' => SERVICE_SHOW_SLA_OFF,
					'goodsla' => 99.9,
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
					'times' => [[
						'type' => SERVICE_TIME_TYPE_UPTIME,
						'ts_from' => 0,
						'ts_to' => 10800
					]],
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
				'service' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'service' => ['12345'],
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

			// Input validation, filter object.
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => ''
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/serviceid": an array is expected.',
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
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'serviceid' => [false]
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
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
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
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
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
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
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
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
					'error' => 'Invalid parameter "/filter/serviceid/1": a number is expected.',
					'result' => []
				]
			],
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

		$this->assertEquals($response['result'], $expected['error']);
	}

	public static function service_getsla_data(): array {
		return [
			[
				'request' => [
					'output' => [],
					'serviceids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.',
					'result' => []
				]
			]
		];
	}

	/**
	 * @dataProvider service_getsla_data
	 */
	public function testServices_Getsla(array $request, array $expected): void {
		$response = $this->call('service.getsla', $request, $expected['error']);

		if ($expected['error'] !== null) {
			return;
		}

		$this->assertEquals($response['result'], $expected['error']);
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
			'SELECT s.serviceid,s.name,s.status,s.algorithm,s.showsla,s.goodsla,s.sortorder,s.weight,'.
				's.propagation_rule,s.propagation_value'.
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

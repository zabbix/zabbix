<?php declare(strict_types = 1);
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

require_once __DIR__.'/../../include/classes/helpers/CArrayHelper.php';
require_once __DIR__.'/../../include/classes/helpers/CTimezoneHelper.php';

/**
 * @backup sla
 */
class testSla extends CAPITest {

	public static function sla_create_data_invalid(): array {
		$timezone_list = '"'.implode('", "',
			array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))
		).'"';

		return [
			'Empty array' => [
				'sla' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Array with null' => [
				'sla' => [null],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			'Array with bool' => [
				'sla' => [true],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			'Array with int' => [
				'sla' => [0],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			'Array with empty string' => [
				'sla' => [''],
				'expected_error' => 'Invalid parameter "/1": an array is expected.'
			],
			'Name required' => [
				'sla' => [[]],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Name empty' => [
				'sla' => [
					'name' => null
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name bool' => [
				'sla' => [
					'name' => true
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name int' => [
				'sla' => [
					'name' => 0
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name array' => [
				'sla' => [
					'name' => []
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name empty string' => [
				'sla' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Name too long' => [
				'sla' => [
					'name' => str_repeat('a', DB::getFieldLength('sla', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			'Period missing' => [
				'sla' => [
					'name' => 'foo'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "period" is missing.'
			],
			'Period null' => [
				'sla' => [
					'name' => 'foo',
					'period' => null
				],
				'expected_error' => 'Invalid parameter "/1/period": an integer is expected.'
			],
			'Period bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => true
				],
				'expected_error' => 'Invalid parameter "/1/period": an integer is expected.'
			],
			'Period empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => []
				],
				'expected_error' => 'Invalid parameter "/1/period": an integer is expected.'
			],
			'Period empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ''
				],
				'expected_error' => 'Invalid parameter "/1/period": an integer is expected.'
			],
			'Period float-like' => [
				'sla' => [
					'name' => 'foo',
					'period' => '1.0'
				],
				'expected_error' => 'Invalid parameter "/1/period": an integer is expected.'
			],
			'Period negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => -1
				],
				'expected_error' => 'Invalid parameter "/1/period": value must be one of '.
					implode(', ', [
						ZBX_SLA_PERIOD_DAILY,
						ZBX_SLA_PERIOD_WEEKLY,
						ZBX_SLA_PERIOD_MONTHLY,
						ZBX_SLA_PERIOD_QUARTERLY,
						ZBX_SLA_PERIOD_ANNUALLY
					]).'.'
			],
			'Period not in' => [
				'sla' => [
					'name' => 'foo',
					'period' => 999
				],
				'expected_error' => 'Invalid parameter "/1/period": value must be one of '.
					implode(', ', [
						ZBX_SLA_PERIOD_DAILY,
						ZBX_SLA_PERIOD_WEEKLY,
						ZBX_SLA_PERIOD_MONTHLY,
						ZBX_SLA_PERIOD_QUARTERLY,
						ZBX_SLA_PERIOD_ANNUALLY
					]).'.'
			],
			'SLO missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "slo" is missing.'
			],
			'SLO null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => null
				],
				'expected_error' => 'Invalid parameter "/1/slo": a floating point value is expected.'
			],
			'Slo bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => true
				],
				'expected_error' => 'Invalid parameter "/1/slo": a floating point value is expected.'
			],
			'Slo empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => []
				],
				'expected_error' => 'Invalid parameter "/1/slo": a floating point value is expected.'
			],
			'Slo empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => ''
				],
				'expected_error' => 'Invalid parameter "/1/slo": a floating point value is expected.'
			],
			'Slo negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => -1
				],
				'expected_error' => 'Invalid parameter "/1/slo": value must be within the range of 0-100.'
			],
			'Slo too large' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 9999
				],
				'expected_error' => 'Invalid parameter "/1/slo": value must be within the range of 0-100.'
			],
			'Effective date missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "effective_date" is missing.'
			],
			'Effective date null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => null
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": an integer is expected.'
			],
			'Effective date bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => true
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": an integer is expected.'
			],
			'Effective date empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => []
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": an integer is expected.'
			],
			'Effective date empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ''
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": an integer is expected.'
			],
			'Effective date negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => -1
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": value must be one of 0-2147483647.'
			],
			'Effective date too large' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE + 1
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": a number is too large.'
			],
			'Timezone missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "timezone" is missing.'
			],
			'Timezone null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => null
				],
				'expected_error' => 'Invalid parameter "/1/timezone": a character string is expected.'
			],
			'Timezone bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => true
				],
				'expected_error' => 'Invalid parameter "/1/timezone": a character string is expected.'
			],
			'Timezone empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => []
				],
				'expected_error' => 'Invalid parameter "/1/timezone": a character string is expected.'
			],
			'Timezone empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => ''
				],
				'expected_error' => 'Invalid parameter "/1/timezone": value must be one of '.$timezone_list.'.'
			],
			'Timezone not in' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Riga district'
				],
				'expected_error' => 'Invalid parameter "/1/timezone": value must be one of '.$timezone_list.'.'
			],
			'Status null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => null
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Status bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => true
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Status empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ''
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Status empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => []
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Status negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => -1
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED]).'.'
			],
			'Status out of scope' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => 999
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED]).'.'
			],
			'Description null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => null
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Description bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => true
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Description empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => []
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Description numeric' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => -1
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Service tags missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "service_tags" is missing.'
			],
			'Service tags null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => null
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": an array is expected.'
			],
			'Service tags bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => true
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": an array is expected.'
			],
			'Service tags empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => []
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": cannot be empty.'
			],
			'Service tags empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => ''
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": an array is expected.'
			],
			'Service tags negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => -1
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": an array is expected.'
			],
			'Service tags, not multi-array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						'tag' => 'a',
						'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL,
						'value' => 'b'
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1": an array is expected.'
			],
			'Service tags, tag null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": a character string is expected.'
			],
			'Service tags, tag bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": a character string is expected.'
			],
			'Service tags, tag empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": a character string is expected.'
			],
			'Service tags, tag empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": cannot be empty.'
			],
			'Service tags, tag int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 999
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": a character string is expected.'
			],
			'Service tags, tag too long' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => str_repeat('a', DB::getFieldLength('sla_service_tag', 'tag') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/tag": value is too long.'
			],
			'Service tags, operator null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": an integer is expected.'
			],
			'Service tags, operator bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": an integer is expected.'
			],
			'Service tags, operator empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": an integer is expected.'
			],
			'Service tags, operator empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": an integer is expected.'
			],
			'Service tags, operator string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => 'like'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": an integer is expected.'
			],
			'Service tags, operator not in range' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => 999
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/operator": value must be one of '.
					implode(', ', [ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL, ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE]).'.'
			],
			'Service tags, value null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/value": a character string is expected.'
			],
			'Service tags, value bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/value": a character string is expected.'
			],
			'Service tags, value empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/value": a character string is expected.'
			],
			'Service tags, value int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 999
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/value": a character string is expected.'
			],
			'Service tags, value too long' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => str_repeat('a', DB::getFieldLength('sla_service_tag', 'value') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1/value": value is too long.'
			],
			'Service tags, duplicate' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/2": value (tag, value)=(tag, value) already exists.'
			],
			'Service tags, overlapping' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 55.1234,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL,
							'value' => 'value'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/2": value (tag, value)=(tag, value) already exists.'
			],
			'Schedule null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => null
				],
				'expected_error' => 'Invalid parameter "/1/schedule": an array is expected.'
			],
			'Schedule bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => true
				],
				'expected_error' => 'Invalid parameter "/1/schedule": an array is expected.'
			],
			'Schedule empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => ''
				],
				'expected_error' => 'Invalid parameter "/1/schedule": an array is expected.'
			],
			'Schedule int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => -1
				],
				'expected_error' => 'Invalid parameter "/1/schedule": an array is expected.'
			],
			'Schedule, not multi-array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						'period_from' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1": an array is expected.'
			],
			'Schedule period_from null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": an integer is expected.'
			],
			'Schedule period_from bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": an integer is expected.'
			],
			'Schedule period_from empty/string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": an integer is expected.'
			],
			'Schedule period_from empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": an integer is expected.'
			],
			'Schedule period_from negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": value must be one of 0-'.
					SEC_PER_WEEK.'.'
			],
			'Schedule period_from out of scope' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": value must be one of 0-'.
					SEC_PER_WEEK.'.'
			],
			'Schedule period_to missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1": the parameter "period_to" is missing.'
			],
			'Schedule period_to null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": an integer is expected.'
			],
			'Schedule period_to bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": an integer is expected.'
			],
			'Schedule period_to empty/string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": an integer is expected.'
			],
			'Schedule period_to empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": an integer is expected.'
			],
			'Schedule period_to negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": value must be one of 0-'.
					SEC_PER_WEEK.'.'
			],
			'Schedule period_to out of scope' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": value must be one of 0-'.
					SEC_PER_WEEK.'.'
			],
			'Schedule period_from greater than period_to' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK,
							'period_to' => SEC_PER_DAY
						]
					]
				],
				'expected_error' => 'Start time must be less than end time for SLA "foo".'
			],
			'Schedule duplicate' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						],
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/2": value (period_from, period_to)=('.
					SEC_PER_DAY.', '.SEC_PER_WEEK.') already exists.'
			],
			'Schedule excluded_downtimes null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => null
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes": an array is expected.'
			],
			'Schedule excluded_downtimes bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => true
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes": an array is expected.'
			],
			'Schedule excluded_downtimes empty/string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => ''
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes": an array is expected.'
			],
			'Schedule excluded_downtimes numeric' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => -1
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes": an array is expected.'
			],
			'Schedule excluded_downtimes non-multi-array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						'name' => 'Mail Server upgrade'
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": an array is expected.'
			],
			'Schedule excluded_downtimes name null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": a character string is expected.'
			],
			'Schedule excluded_downtimes name bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": a character string is expected.'
			],
			'Schedule excluded_downtimes name empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": cannot be empty.'
			],
			'Schedule excluded_downtimes name empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": a character string is expected.'
			],
			'Schedule excluded_downtimes name numeric' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 5
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": a character string is expected.'
			],
			'Schedule excluded_downtimes name too long' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => str_repeat('z', DB::getFieldLength('sla_excluded_downtime', 'name') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/name": value is too long.'
			],
			'Schedule excluded_downtimes period_from missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": the parameter "period_from" is missing.'
			],
			'Schedule excluded_downtimes period_from bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": an integer is expected.'
			],
			'Schedule excluded_downtimes period_from empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": an integer is expected.'
			],
			'Schedule excluded_downtimes period_from empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": an integer is expected.'
			],
			'Schedule excluded_downtimes period_from negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": value must be one of 0-'.
					ZBX_MAX_DATE.'.'
			],
			'Schedule excluded_downtimes period_from out of range' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => ZBX_MAX_DATE + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": a number is too large.'
			],
			'Schedule excluded_downtimes period_to missing' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": the parameter "period_to" is missing.'
			],
			'Schedule excluded_downtimes period_to null' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => null
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": an integer is expected.'
			],
			'Schedule excluded_downtimes period_to bool' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": an integer is expected.'
			],
			'Schedule excluded_downtimes period_to empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": an integer is expected.'
			],
			'Schedule excluded_downtimes period_to empty array' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": an integer is expected.'
			],
			'Schedule excluded_downtimes period_to negative int' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": value must be one of 0-'.
					ZBX_MAX_DATE.'.'
			],
			'Schedule excluded_downtimes period_to out of range' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ZBX_MAX_DATE + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": a number is too large.'
			],
			'Schedule excluded_downtimes period_from greater than period_to' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => ZBX_MAX_DATE,
							'period_to' => SEC_PER_DAY
						]
					]
				],
				'expected_error' => 'Start time must be less than end time for excluded downtime "Mail Server upgrade" of SLA "foo".'
			],
			'Schedule excluded_downtimes duplicate' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ZBX_MAX_DATE
						],
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ZBX_MAX_DATE
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/2": value (period_from, period_to)=('.
					SEC_PER_DAY.', '.ZBX_MAX_DATE.') already exists.'
			],
			'Duplicate SLAs' => [
				'sla' => [
					[
						'name' => 'foo',
						'period' => ZBX_SLA_PERIOD_ANNUALLY,
						'slo' => 99.5,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => ZBX_MAX_DATE
							]
						]
					],
					[
						'name' => 'foo',
						'period' => ZBX_SLA_PERIOD_ANNUALLY,
						'slo' => 99.5,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => ZBX_MAX_DATE
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(foo) already exists.'
			],
			'Slo too many digits' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 55.12345,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ZBX_MAX_DATE
						]
					]
				],
				'expected_error' => 'SLA "foo" SLO must have no more than 4 fractional digits.'
			]
		];
	}

	public static function sla_create_data_valid(): array {
		$name_increment = 0;

		return [
			'Minimal SLA' => [
				'sla' => [
					'name' => 'foo'.++$name_increment,
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.9999,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					]
				],
				'expected_error' => null
			],
			'Multiple minimal SLAs' => [
				'sla' => [
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_ANNUALLY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						]
					],
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_ANNUALLY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						]
					]
				],
				'expected_error' => null
			],
			'Full sla' => [
				'sla' => [
					'name' => 'foo'.++$name_increment,
					'period' => ZBX_SLA_PERIOD_QUARTERLY,
					'slo' => 99.9999,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_DISABLED,
					'description' => 'Pasta servera atjaunošana',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'Mail Server upgrade',
							'period_from' => SEC_PER_DAY,
							'period_to' => ZBX_MAX_DATE
						]
					]
				],
				'expected_error' => null
			],
			'Multiple full SLAs' => [
				'sla' => [
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_MONTHLY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'status' => ZBX_SLA_STATUS_DISABLED,
						'description' => 'Pasta servera atjaunošana',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => ZBX_MAX_DATE
							]
						]
					],
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_ANNUALLY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'status' => ZBX_SLA_STATUS_DISABLED,
						'description' => 'Pasta servera atjaunošana',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => ZBX_MAX_DATE
							]
						]
					]
				],
				'expected_error' => null
			],
			'Multiple mixed SLAs' => [
				'sla' => [
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_DAILY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'status' => ZBX_SLA_STATUS_DISABLED,
						'description' => 'Pasta servera atjaunošana',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							],
							[
								'tag' => 'foo',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_EQUAL,
								'value' => 'value'
							],
							[
								'tag' => 'foo',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'bar'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							],
							[
								'period_from' => SEC_PER_DAY - 30,
								'period_to' => SEC_PER_WEEK
							],
							[
								'period_from' => SEC_PER_DAY * 2,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							],
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY - 30,
								'period_to' => SEC_PER_WEEK
							],
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY * 4,
								'period_to' => ZBX_MAX_DATE
							]
						]
					],
					[
						'name' => 'foo'.++$name_increment,
						'period' => ZBX_SLA_PERIOD_WEEKLY,
						'slo' => 99.9999,
						'effective_date' => ZBX_MAX_DATE - 10,
						'timezone' => 'Europe/Riga',
						'status' => ZBX_SLA_STATUS_DISABLED,
						'description' => 'Pasta servera atjaunošana',
						'service_tags' => [
							[
								'tag' => 'tag',
								'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
								'value' => 'value'
							]
						],
						'schedule' => [
							[
								'period_from' => SEC_PER_DAY,
								'period_to' => SEC_PER_WEEK
							]
						],
						'excluded_downtimes' => [
							[
								'name' => 'Mail Server upgrade',
								'period_from' => SEC_PER_DAY,
								'period_to' => ZBX_MAX_DATE
							]
						]
					]
				],
				'expected_error' => null
			],
			'Service tags, value empty string' => [
				'sla' => [
					'name' => 'foo',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Schedule empty array' => [
				'sla' => [
					'name' => 'foobar',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => []
				],
				'expected_error' => null
			],
			'Schedule excluded_downtimes empty array' => [
				'sla' => [
					'name' => 'foo-buzz',
					'period' => ZBX_SLA_PERIOD_ANNUALLY,
					'slo' => 99.5,
					'effective_date' => ZBX_MAX_DATE - 10,
					'timezone' => 'Europe/Riga',
					'service_tags' => [
						[
							'tag' => 'tag',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_DAY,
							'period_to' => SEC_PER_WEEK
						]
					],
					'excluded_downtimes' => []
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider sla_create_data_invalid
	 * @dataProvider sla_create_data_valid
	 */
	public function testSla_Create(array $slas, ?string $expected_error): void {
		$response = $this->call('sla.create', $slas, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		// Accept single and multiple entities like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $slas)) {
			$slas = zbx_toArray($slas);
		}

		$required_fields = ['name', 'period', 'slo', 'effective_date', 'timezone'];

		foreach ($response['result']['slaids'] as $index => $slaid) {
			$db_sla = CDBHelper::getRow(
				'SELECT s.slaid, s.'.implode(', s.', $required_fields).
				' FROM sla s'.
				' WHERE '.dbConditionId('s.slaid', [$slaid])
			);

			foreach ($required_fields as $field) {
				if (!($field === 'period' && $db_sla[$field] == ZBX_SLA_PERIOD_DAILY)) {
					$this->assertNotEmpty($db_sla[$field],
						'Expecting field "'.$field.'" not be empty, got '.$db_sla[$field]
					);
				}

				$this->assertSame(strval($db_sla[$field]), strval($slas[$index][$field]),
					'Expecting values for "'.$field.'" to match'
				);
			}
		}
	}

	public static function sla_update_data_invalid(): array {
		$timezone_list = '"'.implode('", "',
			array_merge([ZBX_DEFAULT_TIMEZONE], array_keys(CTimezoneHelper::getList()))
		).'"';

		return [
			'Missing slaid' => [
				'sla' => [
					'name' => 'foo'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "slaid" is missing.'
			],
			'Missing slaid in set' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo'
					],
					[
						'name' => 'bar'
					]
				],
				'expected_error' => 'Invalid parameter "/2": the parameter "slaid" is missing.'
			],
			'Non-existing slaid' => [
				'sla' => [
					'slaid' => 9999,
					'name' => 'foo'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Non-unique slaid' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo'
					],
					[
						'slaid' => 50038,
						'name' => 'bar'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (slaid)=(50038) already exists.'
			],
			'Empty name' => [
				'sla' => [
					'slaid' => 50038,
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Name too long' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => str_repeat('z', DB::getFieldLength('sla', 'name') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			'Period not in' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'period' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/period": value must be one of '.implode(', ', [
					ZBX_SLA_PERIOD_DAILY,
					ZBX_SLA_PERIOD_WEEKLY,
					ZBX_SLA_PERIOD_MONTHLY,
					ZBX_SLA_PERIOD_QUARTERLY,
					ZBX_SLA_PERIOD_ANNUALLY
				]).'.'
			],
			'Slo out of range' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'slo' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/slo": value must be within the range of 0-100.'
			],
			'Slo too many decimals' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo-slo',
						'slo' => 50.12345
					]
				],
				'expected_error' => 'SLA "Sla for delete 1" SLO must have no more than 4 fractional digits.'
			],
			'Effective date range' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'effective_date' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/effective_date": value must be one of 0-'.ZBX_MAX_DATE.'.'
			],
			'Unknown timezone' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'timezone' => 'Riga district'
					]
				],
				'expected_error' => 'Invalid parameter "/1/timezone": value must be one of '.$timezone_list.'.'
			],
			'Status not in' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'timezone' => 'Europe/Riga',
						'status' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_SLA_STATUS_DISABLED, ZBX_SLA_STATUS_ENABLED]).'.'
			],
			'Description too long' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo',
						'timezone' => 'Europe/Riga',
						'status' => ZBX_SLA_STATUS_ENABLED,
						'description' => str_repeat('z', DB::getFieldLength('sla', 'description') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],
			'Service tags empty' => [
				'sla' => [
					'slaid' => 50038,
					'service_tags' => []
				],
				'expected_error' => 'Invalid parameter "/1/service_tags": cannot be empty.'
			],
			'Service tags unexpected key' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'service_tags' => [
						[
							'name' => 'foo'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/1": unexpected parameter "name".'
			],
			'Service tags duplicate' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'service_tags' => [
						[
							'tag' => 'foo',
							'value' => 'bar'
						],
						[
							'tag' => 'foo',
							'value' => 'bar'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/service_tags/2": value (tag, value)=(foo, bar) already exists.'
			],
			'Schedule not multi-array' => [
				'sla' => [
					'slaid' => 50038,
					'schedule' => [
						'period_from' => 10
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1": an array is expected.'
			],
			'Schedule empty array' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'schedule' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1": the parameter "period_from" is missing.'
			],
			'Schedule period_to missing' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'schedule' => [
						[
							'period_from' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1": the parameter "period_to" is missing.'
			],
			'Schedule period_from greater than period_to' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo-abc',
					'schedule' => [
						[
							'period_from' => 10,
							'period_to' => 0
						]
					]
				],
				'expected_error' => 'Start time must be less than end time for SLA "Sla for delete 1".'
			],
			'Schedule period_from not in range' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_from": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			'Schedule period_to not in range' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK,
							'period_to' => SEC_PER_WEEK + 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/1/period_to": value must be one of 0-'.SEC_PER_WEEK.'.'
			],
			'Schedule duplicate' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'schedule' => [
						[
							'period_from' => 0,
							'period_to' => SEC_PER_WEEK
						],
						[
							'period_from' => 0,
							'period_to' => SEC_PER_WEEK
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/schedule/2": value (period_from, period_to)=(0, '.
					SEC_PER_WEEK.') already exists.'
			],
			'Excluded downtimes not multi-array' => [
				'sla' => [
					'slaid' => 50038,
					'excluded_downtimes' => [
						'period_from' => 10
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": an array is expected.'
			],
			'Excluded downtime name missing' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'excluded_downtimes' => [
						[
							'period_from' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": the parameter "name" is missing.'
			],
			'Excluded downtime period_to missing' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'excluded_downtimes' => [
						[
							'name' => 'bar',
							'period_from' => 10
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/1": the parameter "period_to" is missing.'
			],
			'Excluded downtime period_from greater than period_to' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo-abc',
					'excluded_downtimes' => [
						[
							'name' => 'bar',
							'period_from' => 10,
							'period_to' => 0
						]
					]
				],
				'expected_error' => 'Start time must be less than end time for excluded downtime "bar" of SLA "Sla for delete 1".'
			],
			'Excluded downtime duplicate' => [
				'sla' => [
					'slaid' => 50038,
					'name' => 'foo',
					'excluded_downtimes' => [
						[
							'name' => 'bar',
							'period_from' => 0,
							'period_to' => ZBX_MAX_DATE
						],
						[
							'name' => 'bar',
							'period_from' => 0,
							'period_to' => ZBX_MAX_DATE
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/excluded_downtimes/2": value (period_from, period_to)=(0, '.
					ZBX_MAX_DATE.') already exists.'
			]
		];
	}

	public static function sla_update_data_valid(): array {
		return [
			'No non-required parameters' => [
				'sla' => [[
					'slaid' => 50038
				]],
				'expected_error' => null
			],
			'Simple update' => [
				'sla' => [[
					'slaid' => 50038,
					'name' => 'bar'
				]],
				'expected_error' => null
			],
			'Multiple updates, with UTF' => [
				'sla' => [
					[
						'slaid' => 50038,
						'name' => 'foo38'
					],
					[
						'slaid' => 50039,
						'name' => 'фубар'
					]
				],
				'expected_error' => null
			],
			'Full update' => [
				'sla' => [[
					'slaid' => 50039,
					'name' => 'Full update1',
					'period' => ZBX_SLA_PERIOD_MONTHLY,
					'slo' => 99.9999,
					'effective_date' => ZBX_MAX_DATE,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => 'Pasta servera atjaunošana',
					'service_tags' => [
						[
							'tag' => 'foo',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'bar'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK - 1,
							'period_to' => SEC_PER_WEEK
						],
						[
							'period_from' => 0,
							'period_to' => 1
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'foo',
							'period_from' => ZBX_MAX_DATE - 1,
							'period_to' => ZBX_MAX_DATE
						],
						[
							'name' => 'bar',
							'period_from' => 0,
							'period_to' => 1
						]
					]
				],[
					'slaid' => 50040,
					'name' => 'Full update2',
					'period' => ZBX_SLA_PERIOD_MONTHLY,
					'slo' => 99.9999,
					'effective_date' => ZBX_MAX_DATE,
					'timezone' => 'Europe/Riga',
					'status' => ZBX_SLA_STATUS_ENABLED,
					'description' => 'Pasta servera atjaunošana',
					'service_tags' => [
						[
							'tag' => 'foo',
							'operator' => ZBX_SLA_SERVICE_TAG_OPERATOR_LIKE,
							'value' => 'bar'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					],
					'schedule' => [
						[
							'period_from' => SEC_PER_WEEK - 1,
							'period_to' => SEC_PER_WEEK
						],
						[
							'period_from' => 0,
							'period_to' => 1
						]
					],
					'excluded_downtimes' => [
						[
							'name' => 'foo',
							'period_from' => ZBX_MAX_DATE - 1,
							'period_to' => ZBX_MAX_DATE
						],
						[
							'name' => 'bar',
							'period_from' => 0,
							'period_to' => 1
						]
					]
				]],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider sla_update_data_invalid
	 * @dataProvider sla_update_data_valid
	 */
	public function testSla_Update(array $slas, ?string $expected_error): void {
		$response = $this->call('sla.update', $slas, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$fields = ['slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'];

		$db_slas = CDBHelper::getAll(
			'SELECT s.'.implode(', s.', $fields).
			' FROM sla s'.
			' WHERE '.dbConditionId('s.slaid', $response['result']['slaids']).
			' ORDER BY s.slaid ASC'
		);

		foreach ($db_slas as $index => $db_sla) {
			$sla = $slas[$index];

			foreach ($fields as $field) {
				if (!array_key_exists($field, $sla)) {
					continue;
				}

				$this->assertEquals($sla[$field], $db_sla[$field], 'Expecting values for "'.$field.'" to match');
			}
		}
	}

	public static function sla_get_data_invalid(): array {
		return [
			'ID as bool' => [
				'request' => [
					'output' => [],
					'slaids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids": an array is expected.'
				]
			],
			'ID empty string' => [
				'request' => [
					'output' => [],
					'slaids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids": an array is expected.'
				]
			],
			'ID array null' => [
				'request' => [
					'output' => [],
					'slaids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids/1": a number is expected.'
				]
			],
			'ID array bool' => [
				'request' => [
					'output' => [],
					'slaids' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids/1": a number is expected.'
				]
			],
			'ID empty array' => [
				'request' => [
					'output' => [],
					'slaids' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids/1": a number is expected.'
				]
			],
			'ID array with empty string' => [
				'request' => [
					'output' => [],
					'slaids' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids/1": a number is expected.'
				]
			],
			'ID float' => [
				'request' => [
					'output' => [],
					'slaids' => ['1.0']
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaids/1": a number is expected.'
				]
			],
			'evaltype bool' => [
				'request' => [
					'output' => [],
					'evaltype' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.'
				]
			],
			'evaltype empty string' => [
				'request' => [
					'output' => [],
					'evaltype' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.'
				]
			],
			'evaltype array null' => [
				'request' => [
					'output' => [],
					'evaltype' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.'
				]
			],
			'evaltype array' => [
				'request' => [
					'output' => [],
					'evaltype' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": an integer is expected.'
				]
			],
			'evaltype float' => [
				'request' => [
					'output' => [],
					'evaltype' => 1.0
				],
				'expected' => [
					'error' => 'Invalid parameter "/evaltype": value must be one of '.
						implode(', ', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]).'.'
				]
			],
			'service_tags bool' => [
				'request' => [
					'output' => [],
					'service_tags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags": an array is expected.'
				]
			],
			'service_tags empty string' => [
				'request' => [
					'output' => [],
					'service_tags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags": an array is expected.'
				]
			],
			'service_tags null' => [
				'request' => [
					'output' => [],
					'service_tags' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags": an array is expected.'
				]
			],
			'service_tags float' => [
				'request' => [
					'output' => [],
					'service_tags' => 1.0
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags": an array is expected.'
				]
			],
			'service_tags no tag' => [
				'request' => [
					'output' => [],
					'service_tags' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags/1": the parameter "tag" is missing.'
				]
			],
			'service_tags tag non-string' => [
				'request' => [
					'output' => [],
					'service_tags' => [[
						'tag' => 1,
						'value' => 'bar'
					]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags/1/tag": a character string is expected.'
				]
			],
			'service_tags value non-string' => [
				'request' => [
					'output' => [],
					'service_tags' => [[
						'tag' => 'foo',
						'value' => 1
					]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags/1/value": a character string is expected.'
				]
			],
			'service_tags operator not in' => [
				'request' => [
					'output' => [],
					'service_tags' => [[
						'tag' => 'foo',
						'value' => 'bar',
						'operator' => -1
					]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/service_tags/1/operator": value must be one of '.implode(', ', [
						TAG_OPERATOR_LIKE,
						TAG_OPERATOR_EQUAL,
						TAG_OPERATOR_NOT_LIKE,
						TAG_OPERATOR_NOT_EQUAL,
						TAG_OPERATOR_EXISTS,
						TAG_OPERATOR_NOT_EXISTS
					]).'.'
				]
			],
			'Serviceids negative int' => [
				'request' => [
					'output' => [],
					'serviceids' => [-1]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids bool' => [
				'request' => [
					'output' => [],
					'serviceids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'Serviceids empty string' => [
				'request' => [
					'output' => [],
					'serviceids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'Serviceids null' => [
				'request' => [
					'output' => [],
					'serviceids' => [null]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids array with bool' => [
				'request' => [
					'output' => [],
					'serviceids' => [true]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids empty array' => [
				'request' => [
					'output' => [],
					'serviceids' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids array with empty string' => [
				'request' => [
					'output' => [],
					'serviceids' => ['']
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids float' => [
				'request' => [
					'output' => [],
					'serviceids' => ['1.0']
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Serviceids negative int' => [
				'request' => [
					'output' => [],
					'serviceids' => [-1]
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids/1": a number is expected.'
				]
			],
			'Filter slaid bool' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slaid' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/slaid": an array is expected.'
				]
			],
			'Filter name bool'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'name' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/name": an array is expected.'
				]
			],
			'Filter period bool'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'period' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/period": an array is expected.'
				]
			],
			'Filter SLO bool' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slo' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/slo": an array is expected.'
				]
			],
			'Filter effective_date bool' => [
				'request' => [
					'output' => [],
					'filter' => [
						'effective_date' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/effective_date": an array is expected.'
				]
			],
			'Filter timezone bool' => [
				'request' => [
					'output' => [],
					'filter' => [
						'timezone' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/timezone": an array is expected.'
				]
			],
			'Filter status bool' => [
				'request' => [
					'output' => [],
					'filter' => [
						'status' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/filter/status": an array is expected.'
				]
			],
			'Search name bool' => [
				'request' => [
					'output' => [],
					'search' => [
						'name' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/search/name": an array is expected.'
				]
			],
			'Search timezone bool' => [
				'request' => [
					'output' => [],
					'search' => [
						'timezone' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/search/timezone": an array is expected.'
				]
			],
			'Search description bool' => [
				'request' => [
					'output' => [],
					'search' => [
						'description' => true
					]
				],
				'expected' => [
					'error' => 'Invalid parameter "/search/description": an array is expected.'
				]
			],
			'SearchByAny null' => [
				'request' => [
					'output' => [],
					'searchByAny' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
				]
			],
			'SearchByAny empty/string' => [
				'request' => [
					'output' => [],
					'searchByAny' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
				]
			],
			'SearchByAny empty array' => [
				'request' => [
					'output' => [],
					'searchByAny' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
				]
			],
			'SearchByAny negative int' => [
				'request' => [
					'output' => [],
					'searchByAny' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
				]
			],
			'SearchByAny float' => [
				'request' => [
					'output' => [],
					'searchByAny' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
				]
			],
			'SearchWildcardsEnabled null' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
				]
			],
			'SearchWildcardsEnabled empty/string' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
				]
			],
			'SearchWildcardsEnabled empty array' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
				]
			],
			'SearchWildcardsEnabled negative int' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
				]
			],
			'SearchWildcardsEnabled float' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
				]
			],
			'SLA output null' => [
				'request' => [
					'output' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/output": an array or a character string is expected.'
				]
			],
			'SLA output bool' => [
				'request' => [
					'output' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/output": an array or a character string is expected.'
				]
			],
			'SLA output empty/string' => [
				'request' => [
					'output' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/output": value must be "extend".'
				]
			],
			'SLA output negative int' => [
				'request' => [
					'output' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/output": an array or a character string is expected.'
				]
			],
			'SLA output float' => [
				'request' => [
					'output' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/output": an array or a character string is expected.'
				]
			],
			'SLA output invalid parameter' => [
				'request' => [
					'output' => ['foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/output/1": value must be one of "'.implode('", "', [
						'slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'
					]).'".'
				]
			],
			'SLA output multi-array' => [
				'request' => [
					'output' => [[]]
				],
				'expected' => [
					'error' => 'Invalid parameter "/output/1": a character string is expected.'
				]
			],
			'selectServiceTags bool' => [
				'request' => [
					'selectServiceTags' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectServiceTags": an array or a character string is expected.'
				]
			],
			'selectServiceTags empty/string' => [
				'request' => [
					'selectServiceTags' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectServiceTags": value must be one of "'.implode('", "', [
						'extend', 'count'
					]).'".'
				]
			],
			'selectServiceTags negative int' => [
				'request' => [
					'selectServiceTags' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectServiceTags": an array or a character string is expected.'
				]
			],
			'selectServiceTags float' => [
				'request' => [
					'selectServiceTags' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectServiceTags": an array or a character string is expected.'
				]
			],
			'selectServiceTags out of scope' => [
				'request' => [
					'selectServiceTags' => ['foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectServiceTags/1": value must be one of "'.implode('", "', [
						'tag', 'operator', 'value'
					]).'".'
				]
			],
			'selectSchedule bool' => [
				'request' => [
					'selectSchedule' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectSchedule": an array or a character string is expected.'
				]
			],
			'selectSchedule empty/string' => [
				'request' => [
					'selectSchedule' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectSchedule": value must be one of "'.implode('", "', [
						'extend', 'count'
					]).'".'
				]
			],
			'selectSchedule negative int' => [
				'request' => [
					'selectSchedule' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectSchedule": an array or a character string is expected.'
				]
			],
			'selectSchedule float' => [
				'request' => [
					'selectSchedule' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectSchedule": an array or a character string is expected.'
				]
			],
			'selectSchedule out of scope' => [
				'request' => [
					'selectSchedule' => ['foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectSchedule/1": value must be one of "'.implode('", "', [
						'period_from', 'period_to'
					]).'".'
				]
			],
			'selectExcludedDowntimes bool' => [
				'request' => [
					'selectExcludedDowntimes' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectExcludedDowntimes": an array or a character string is expected.'
				]
			],
			'selectExcludedDowntimes empty/string' => [
				'request' => [
					'selectExcludedDowntimes' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectExcludedDowntimes": value must be one of "'.
						implode('", "', ['extend', 'count']).'".'
				]
			],
			'selectExcludedDowntimes negative int' => [
				'request' => [
					'selectExcludedDowntimes' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectExcludedDowntimes": an array or a character string is expected.'
				]
			],
			'selectExcludedDowntimes float' => [
				'request' => [
					'selectExcludedDowntimes' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectExcludedDowntimes": an array or a character string is expected.'
				]
			],
			'selectExcludedDowntimes out of scope' => [
				'request' => [
					'selectExcludedDowntimes' => ['foo']
				],
				'expected' => [
					'error' => 'Invalid parameter "/selectExcludedDowntimes/1": value must be one of "'.
						implode('", "', ['name', 'period_from', 'period_to']).'".'
				]
			],
			'sortfield null' => [
				'request' => [
					'sortfield' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield": an array is expected.'
				]
			],
			'sortfield bool' => [
				'request' => [
					'sortfield' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield": an array is expected.'
				]
			],
			'sortfield empty/string' => [
				'request' => [
					'sortfield' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield/1": value must be one of "'.implode('", "', [
						'slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'
					]).'".'
				]
			],
			'sortfield negative int' => [
				'request' => [
					'sortfield' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield": an array is expected.'
				]
			],
			'sortfield float' => [
				'request' => [
					'sortfield' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield": an array is expected.'
				]
			],
			'sortfield out of scope' => [
				'request' => [
					'sortfield' => ['foot']
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortfield/1": value must be one of "'.implode('", "', [
						'slaid', 'name', 'period', 'slo', 'effective_date', 'timezone', 'status', 'description'
					]).'".'
				]
			],
			'sortorder null' => [
				'request' => [
					'sortorder' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
				]
			],
			'sortorder bool' => [
				'request' => [
					'sortorder' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
				]
			],
			'sortorder empty/string' => [
				'request' => [
					'sortorder' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortorder": value must be one of "'.
						implode('", "', [ZBX_SORT_UP, ZBX_SORT_DOWN]).'".'
				]
			],
			'sortorder negative int' => [
				'request' => [
					'sortorder' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
				]
			],
			'sortorder float' => [
				'request' => [
					'sortorder' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
				]
			],
			'limit bool' => [
				'request' => [
					'limit' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": an integer is expected.'
				]
			],
			'limit empty/string' => [
				'request' => [
					'limit' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": an integer is expected.'
				]
			],
			'limit empty array' => [
				'request' => [
					'limit' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": an integer is expected.'
				]
			],
			'limit negative int' => [
				'request' => [
					'limit' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": value must be one of 1-'.ZBX_MAX_INT32.'.'
				]
			],
			'limit float' => [
				'request' => [
					'limit' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": an integer is expected.'
				]
			],
			'limit out of scope' => [
				'request' => [
					'limit' => ZBX_MAX_INT32 + 1
				],
				'expected' => [
					'error' => 'Invalid parameter "/limit": a number is too large.'
				]
			],
			'editable null' => [
				'request' => [
					'editable' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/editable": a boolean is expected.'
				]
			],
			'editable empty/string' => [
				'request' => [
					'editable' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/editable": a boolean is expected.'
				]
			],
			'editable empty array' => [
				'request' => [
					'editable' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/editable": a boolean is expected.'
				]
			],
			'editable negative int' => [
				'request' => [
					'editable' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/editable": a boolean is expected.'
				]
			],
			'editable float' => [
				'request' => [
					'editable' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/editable": a boolean is expected.'
				]
			],
			'preservekeys null' => [
				'request' => [
					'preservekeys' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
				]
			],
			'preservekeys empty/string' => [
				'request' => [
					'preservekeys' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
				]
			],
			'preservekeys empty array' => [
				'request' => [
					'preservekeys' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
				]
			],
			'preservekeys negative int' => [
				'request' => [
					'preservekeys' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
				]
			],
			'preservekeys float' => [
				'request' => [
					'preservekeys' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
				]
			]
		];
	}

	public static function sla_get_data_valid(): array {
		return [
			'ID unknown' => [
				'request' => [
					'output' => [],
					'slaids' => [50999]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'service_tags no value' => [
				'request' => [
					'output' => [],
					'service_tags' => [[
						'tag' => 'foo'
					]]
				],
				'expected' => [
					'error' => null
				]
			],
			'service_tags no operator' => [
				'request' => [
					'output' => [],
					'service_tags' => [[
						'tag' => 'foo',
						'value' => 'bar'
					]]
				],
				'expected' => [
					'error' => null
				]
			],
			'Serviceids unknown' => [
				'request' => [
					'output' => [],
					'serviceids' => [50999]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Filter slaid null' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slaid' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter slaid empty array' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slaid' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter slaid float' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slaid' => 1.0
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Filter name null'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'name' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter name empty/string'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'name' => ''
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Filter name empty/array'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'name' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter name too long'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'name' => str_repeat('a', DB::getFieldLength('sla', 'name'))
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Filter period null'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'period' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter period empty array'  => [
				'request' => [
					'output' => [],
					'filter' => [
						'period' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter SLO null' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slo' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter SLO empty array' => [
				'request' => [
					'output' => [],
					'filter' => [
						'slo' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter effective_date null' => [
				'request' => [
					'output' => [],
					'filter' => [
						'effective_date' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter effective_date empty array' => [
				'request' => [
					'output' => [],
					'filter' => [
						'effective_date' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter timezone null' => [
				'request' => [
					'output' => [],
					'filter' => [
						'timezone' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter timezone empty array' => [
				'request' => [
					'output' => [],
					'filter' => [
						'timezone' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter status null' => [
				'request' => [
					'output' => [],
					'filter' => [
						'status' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Filter status empty array' => [
				'request' => [
					'output' => [],
					'filter' => [
						'status' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search name null' => [
				'request' => [
					'output' => [],
					'search' => [
						'name' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search name empty/string' => [
				'request' => [
					'output' => [],
					'search' => [
						'name' => ''
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search name empty array' => [
				'request' => [
					'output' => [],
					'search' => [
						'name' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search name too long' => [
				'request' => [
					'output' => [],
					'search' => [
						'name' => str_repeat('z', DB::getFieldLength('sla', 'name') + 1)
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Search timezone null' => [
				'request' => [
					'output' => [],
					'search' => [
						'timezone' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search timezone empty/string' => [
				'request' => [
					'output' => [],
					'search' => [
						'timezone' => ''
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search timezone empty array' => [
				'request' => [
					'output' => [],
					'search' => [
						'timezone' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search timezone out of scope' => [
				'request' => [
					'output' => [],
					'search' => [
						'timezone' => 'Riga district'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'Search description null' => [
				'request' => [
					'output' => [],
					'search' => [
						'description' => null
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search description empty/string' => [
				'request' => [
					'output' => [],
					'search' => [
						'description' => ''
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search description empty array' => [
				'request' => [
					'output' => [],
					'search' => [
						'description' => []
					]
				],
				'expected' => [
					'error' => null
				]
			],
			'Search description out of scope' => [
				'request' => [
					'output' => [],
					'search' => [
						'description' => str_repeat('z', DB::getFieldLength('sla', 'description') + 1)
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			'SearchByAny true' => [
				'request' => [
					'output' => [],
					'searchByAny' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'SearchByAny false' => [
				'request' => [
					'output' => [],
					'searchByAny' => false
				],
				'expected' => [
					'error' => null
				]
			],
			'StartSearch true' => [
				'request' => [
					'output' => [],
					'startSearch' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'StartSearch false' => [
				'request' => [
					'output' => [],
					'startSearch' => false
				],
				'expected' => [
					'error' => null
				]
			],
			'ExcludeSearch true' => [
				'request' => [
					'output' => [],
					'excludeSearch' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'ExcludeSearch false' => [
				'request' => [
					'output' => [],
					'excludeSearch' => false
				],
				'expected' => [
					'error' => null
				]
			],
			'SearchWildcardsEnabled bool' => [
				'request' => [
					'output' => [],
					'searchWildcardsEnabled' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'SLA output empty array' => [
				'request' => [
					'output' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'countOutput true' => [
				'request' => [
					'countOutput' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'countOutput false' => [
				'request' => [
					'countOutput' => false
				],
				'expected' => [
					'error' => null
				]
			],
			'selectServiceTags null' => [
				'request' => [
					'selectServiceTags' => null
				],
				'expected' => [
					'error' => null
				]
			],
			'selectServiceTags empty array' => [
				'request' => [
					'selectServiceTags' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'selectServiceTags out of scope' => [
				'request' => [
					'selectServiceTags' => 'count'
				],
				'expected' => [
					'error' => null
				]
			],
			'selectSchedule null' => [
				'request' => [
					'selectSchedule' => null
				],
				'expected' => [
					'error' => null
				]
			],
			'selectSchedule empty array' => [
				'request' => [
					'selectSchedule' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'selectSchedule count' => [
				'request' => [
					'selectSchedule' => 'count'
				],
				'expected' => [
					'error' => null
				]
			],
			'selectExcludedDowntimes null' => [
				'request' => [
					'selectExcludedDowntimes' => null
				],
				'expected' => [
					'error' => null
				]
			],
			'selectExcludedDowntimes empty array' => [
				'request' => [
					'selectExcludedDowntimes' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'selectExcludedDowntimes count' => [
				'request' => [
					'selectExcludedDowntimes' => 'count'
				],
				'expected' => [
					'error' => null
				]
			],
			'sortfield empty array' => [
				'request' => [
					'sortfield' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'sortorder empty array' => [
				'request' => [
					'sortorder' => []
				],
				'expected' => [
					'error' => null
				]
			],
			'limit null' => [
				'request' => [
					'limit' => null
				],
				'expected' => [
					'error' => null
				]
			],
			'editable true' => [
				'request' => [
					'editable' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'editable false' => [
				'request' => [
					'editable' => false
				],
				'expected' => [
					'error' => null
				]
			],
			'preservekeys true' => [
				'request' => [
					'preservekeys' => true
				],
				'expected' => [
					'error' => null
				]
			],
			'preservekeys false' => [
				'request' => [
					'preservekeys' => false
				],
				'expected' => [
					'error' => null
				]
			]
		];
	}

	/**
	 * @dataProvider sla_get_data_invalid
	 * @dataProvider sla_get_data_valid
	 */
	public function testSla_Get(array $request, array $expected): void {
		$response = $this->call('sla.get', $request, $expected['error']);

		if ($expected['error'] !== null) {
			return;
		}

		if (!array_key_exists('result', $expected)) {
			return;
		}

		$this->assertEquals($response['result'], $expected['result']);
	}

	public static function sla_delete_data_invalid(): array {
		return [
			'ID as null' => [
				'sla' => [null],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'ID as bool' => [
				'sla' => [true],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'ID as empty array' => [
				'sla' => [[]],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'ID as empty string' => [
				'sla' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'ID as float' => [
				'sla' => ['1.0'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'ID negative int' => [
				'sla' => [-1],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Non-existing ID' => [
				'sla' => [999],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	public static function sla_delete_data_valid(): array {
		return [
			'Delete single' => [
				'sla' => [50038],
				'expected_error' => null
			],
			'Delete multiple' => [
				'sla' => [50039, 50040],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider sla_delete_data_invalid
	 * @dataProvider sla_delete_data_valid
	 */
	public function testSla_Delete(array $slas, ?string $expected_error): void {
		$response = $this->call('sla.delete', $slas, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		foreach ($response['result']['slaids'] as $slaid) {
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT s.slaid FROM sla s WHERE '.dbConditionId('s.slaid', [$slaid])
			));
		}
	}

	public static function sla_getSli_data_invalid(): array {
		return [
			'slaid missing' => [
				'request' => [
				],
				'expected' => [
					'error' => 'Invalid parameter "/": the parameter "slaid" is missing.'
				]
			],
			'slaid null' => [
				'request' => [
					'slaid' => null
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'slaid bool' => [
				'request' => [
					'slaid' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'slaid empty/string' => [
				'request' => [
					'slaid' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'slaid empty array' => [
				'request' => [
					'slaid' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'slaid negative int' => [
				'request' => [
					'slaid' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'slaid float' => [
				'request' => [
					'slaid' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/slaid": a number is expected.'
				]
			],
			'period_from null' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => null
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'period_from bool' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_from": an integer is expected.'
				]
			],
			'period_from empty/string' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_from": an integer is expected.'
				]
			],
			'period_from empty array' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_from": an integer is expected.'
				]
			],
			'period_from negative int' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_from": value must be one of 0-'.ZBX_MAX_DATE.'.'
				]
			],
			'period_from float' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_from": an integer is expected.'
				]
			],
			'period_to null' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => null
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'period_to bool' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_to": an integer is expected.'
				]
			],
			'period_to empty/string' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_to": an integer is expected.'
				]
			],
			'period_to empty array' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_to": an integer is expected.'
				]
			],
			'period_to negative int' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_to": value must be one of 0-'.ZBX_MAX_DATE.'.'
				]
			],
			'period_to float' => [
				'request' => [
					'slaid' => 50999,
					'period_to' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/period_to": an integer is expected.'
				]
			],
			'period_from greater than period_to' => [
				'request' => [
					'slaid' => 50999,
					'period_from' => ZBX_MAX_DATE,
					'period_to' => 0
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'periods null' => [
				'request' => [
					'slaid' => 50999,
					'periods' => null
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'periods bool' => [
				'request' => [
					'slaid' => 50999,
					'periods' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": an integer is expected.'
				]
			],
			'periods empty/string' => [
				'request' => [
					'slaid' => 50999,
					'periods' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": an integer is expected.'
				]
			],
			'periods empty array' => [
				'request' => [
					'slaid' => 50999,
					'periods' => []
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": an integer is expected.'
				]
			],
			'periods negative int' => [
				'request' => [
					'slaid' => 50999,
					'periods' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": value must be one of 1-100.'
				]
			],
			'periods zero' => [
				'request' => [
					'slaid' => 50999,
					'periods' => 0
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": value must be one of 1-100.'
				]
			],
			'periods out of scope' => [
				'request' => [
					'slaid' => 50999,
					'periods' => 101
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": value must be one of 1-100.'
				]
			],
			'periods float' => [
				'request' => [
					'slaid' => 50999,
					'periods' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/periods": an integer is expected.'
				]
			],
			'serviceids null' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => null
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'serviceids bool' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => true
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'serviceids empty/string' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => ''
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'serviceids empty array' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => []
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			],
			'serviceids negative int' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => -1
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'serviceids float' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => 1.1
				],
				'expected' => [
					'error' => 'Invalid parameter "/serviceids": an array is expected.'
				]
			],
			'serviceids out of scope' => [
				'request' => [
					'slaid' => 50999,
					'serviceids' => [50999]
				],
				'expected' => [
					'error' => 'No permissions to referred object or it does not exist!'
				]
			]
		];
	}

	public static function sla_getSli_data_valid(): array {
		return [
			'SLA without services' => [
				'request' => [
					'slaid' => 50041
				],
				'expected' => [
					'error' => null,
					'result' => [
						'periods' => [],
						'serviceids' => [],
						'sli' => []
					]
				]
			],
			'SLA with non-existing serviceid' => [
				'request' => [
					'slaid' => 50041,
					'serviceids' => [50999]
				],
				'expected' => [
					'error' => null,
					'result' => [
						'periods' => [],
						'serviceids' => [],
						'sli' => []
					]
				]
			]
		];
	}

	/**
	 * @dataProvider sla_getSli_data_invalid
	 * @dataProvider sla_getSli_data_valid
	 */
	public function testSla_GetSli(array $request, array $expected): void {
		$response = $this->call('sla.getSli', $request, $expected['error']);

		if ($expected['error'] !== null || !array_key_exists('result', $expected)) {
			return;
		}

		$this->assertEquals($response['result'], $expected['result']);
	}
}

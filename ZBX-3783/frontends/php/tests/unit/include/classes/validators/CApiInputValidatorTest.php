<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CApiInputValidatorTest extends PHPUnit_Framework_TestCase {

	public function dataProvider() {
		return [
			[
				['type' => API_STRING_UTF8, 'length' => 16],
				'Zabbix server',
				'/1/name',
				'Zabbix server'
			],
			[
				['type' => API_STRING_UTF8, 'length' => 16],
				'Zabbix Server++++',
				'/1/name',
				'Invalid parameter "/1/name": value is too long.'
			],
			[
				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
				'name',
				'/1/name',
				'name'
			],
			[
				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
				'',
				'/1/name',
				'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				['type' => API_STRING_UTF8],
				'',
				'/1/name',
				''
			],
			[
				['type' => API_STRING_UTF8],
				[],
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_STRING_UTF8],
				null,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL],
				null,
				'/1/name',
				null
			],
			[
				['type' => API_STRING_UTF8],
				// broken UTF-8 byte sequence
				'Заббикс '."\xd1".'сервер',
				'/1/name',
				'Invalid parameter "/1/name": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_ID],
				'-1',
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID],
				0,
				'/1/id',
				0
			],
			[
				['type' => API_ID],
				12345,
				'/1/id',
				12345
			],
			[
				['type' => API_ID],
				'012345',
				'/1/id',
				'12345'
			],
			[
				['type' => API_ID],
				'9223372036854775807',
				'/1/id',
				'9223372036854775807'
			],
			[
				['type' => API_ID],
				'00009223372036854775807',
				'/1/id',
				'9223372036854775807'
			],
			[
				['type' => API_ID],
				'-1',
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID],
				'foo',
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID],
				[],
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID],
				null,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'flags' => API_ALLOW_NULL],
				null,
				'/1/id',
				null
			],
			[
				['type' => API_ID],
				'9223372036854775808',
				'/1/id',
				'Invalid parameter "/1/id": a number is too large.'
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				[],
				'/',
				[]
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				'',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				['host' => 'Zabbix server'],
				'/',
				'Invalid parameter "/": unexpected parameter "host".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'host' => ['type' => API_STRING_UTF8]
				]],
				['host' => 'Zabbix server'],
				'/',
				['host' => 'Zabbix server']
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'host' => ['type' => API_STRING_UTF8]
				]],
				[
					'host' => 'Zabbix server',
					'name' => 'Zabbix server'
				],
				'/',
				'Invalid parameter "/": unexpected parameter "name".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'host' => ['type' => API_STRING_UTF8],
					'name' => ['type' => API_STRING_UTF8]
				]],
				[
					'host' => 'Zabbix server'
				],
				'/',
				[
					'host' => 'Zabbix server'
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'host' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
					'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
				]],
				[
					'host' => 'Zabbix server'
				],
				'/',
				'Invalid parameter "/": the parameter "name" is missing.'
			],
			[
				['type' => API_IDS],
				[],
				'/',
				[]
			],
			[
				['type' => API_IDS],
				'',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_IDS],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_IDS, 'flags' => API_NORMALIZE],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_IDS],
				46342,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_IDS, 'flags' => API_NORMALIZE],
				46342,
				'/',
				[46342]
			],
			[
				['type' => API_IDS, 'flags' => API_NORMALIZE],
				'0000046342',
				'/',
				['46342']
			],
			[
				['type' => API_IDS, 'flags' => API_ALLOW_NULL],
				null,
				'/',
				null
			],
			[
				['type' => API_IDS],
				[0, 1, 2, 3, '4', '9223372036854775807'],
				'/',
				[0, 1, 2, 3, '4', '9223372036854775807']
			],
			[
				['type' => API_IDS],
				[0, 1, 2, 3, '4', '9223372036854775807', 'foo'],
				'/',
				'Invalid parameter "/7": a number is expected.'
			],
			[
				['type' => API_IDS],
				[0, 1, 2, 3, '4', '9223372036854775807', '9223372036854775808'],
				'/',
				'Invalid parameter "/7": a number is too large.'
			],
			[
				['type' => API_IDS, 'flags' => API_UNIQ],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7],
				'/',
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7]
			],
			[
				['type' => API_IDS, 'flags' => API_UNIQ],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '3'],
				'/',
				'Invalid parameter "/10": value is not unique.'
			],
			[
				['type' => API_IDS, 'flags' => API_UNIQ],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '03'],
				'/',
				'Invalid parameter "/10": value is not unique.'
			],
			[
				['type' => API_OBJECTS],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL, 'fields' => []],
				null,
				'/',
				null
			],
			[
				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => []],
				[[], [], []],
				'/',
				[[], [], []]
			],
			[
				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => []],
				[],
				'/',
				'Invalid parameter "/": cannot be empty.'
			],
			[
				['type' => API_OBJECTS, 'fields' => []],
				['000' => []],
				'/',
				'Invalid parameter "/": unexpected parameter "000".'
			],
			[
				['type' => API_OBJECTS, 'fields' => []],
				[['host' => 'Zabbix server']],
				'/',
				'Invalid parameter "/1": unexpected parameter "host".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'host' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
					'name' => ['type' => API_STRING_UTF8]
				]],
				[
					['host' => 'Zabbix server', 'name' => 'Zabbix server'],
					['host' => 'Zabbix server']
				],
				'/',
				[
					['host' => 'Zabbix server', 'name' => 'Zabbix server'],
					['host' => 'Zabbix server']
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'host' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
					'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
				]],
				[
					['host' => 'Zabbix server', 'name' => 'Zabbix server'],
					['host' => 'Zabbix server']
				],
				'/',
				'Invalid parameter "/2": the parameter "name" is missing.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_UNIQ],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_UNIQ, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_UNIQ, 'length' => 64],
						'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64]
					]]
				]],
				[
					[
						'valuemapid' => 4,
						'name' => 'APC Battery Replacement Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown'],
							['value' => '2', 'newvalue' => 'notInstalled'],
							['value' => '3', 'newvalue' => 'ok'],
							['value' => '4', 'newvalue' => 'failed'],
							['value' => '5', 'newvalue' => 'highTemperature'],
							['value' => '6', 'newvalue' => 'replaceImmediately'],
							['value' => '7', 'newvalue' => 'lowCapacity']
						]
					],
					[
						'valuemapid' => 5,
						'name' => 'APC Battery Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown'],
							['value' => '2', 'newvalue' => 'batteryNormal'],
							['value' => '3', 'newvalue' => 'batteryLow']
						]
					]
				],
				'/',
				[
					[
						'valuemapid' => 4,
						'name' => 'APC Battery Replacement Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown'],
							['value' => '2', 'newvalue' => 'notInstalled'],
							['value' => '3', 'newvalue' => 'ok'],
							['value' => '4', 'newvalue' => 'failed'],
							['value' => '5', 'newvalue' => 'highTemperature'],
							['value' => '6', 'newvalue' => 'replaceImmediately'],
							['value' => '7', 'newvalue' => 'lowCapacity']
						]
					],
					[
						'valuemapid' => 5,
						'name' => 'APC Battery Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown'],
							['value' => '2', 'newvalue' => 'batteryNormal'],
							['value' => '3', 'newvalue' => 'batteryLow']
						]
					]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_UNIQ],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_UNIQ, 'length' => 64]
				]],
				[
					[
						'valuemapid' => 4,
						'name' => 'APC Battery Replacement Status'
					],
					[
						'valuemapid' => 5,
						'name' => 'APC Battery Status'
					],
					[
						'valuemapid' => 4,
						'name' => 'APC Battery Replacement Status'
					]
				],
				'/',
				'Invalid parameter "/3/valuemapid": value is not unique.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_UNIQ],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_UNIQ, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_UNIQ, 'length' => 64],
						'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64]
					]]
				]],
				[
					[
						'valuemapid' => 4,
						'name' => 'APC Battery Replacement Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown'],
							['value' => '2', 'newvalue' => 'notInstalled'],
							['value' => '3', 'newvalue' => 'ok'],
							['value' => '4', 'newvalue' => 'failed'],
							['value' => '5', 'newvalue' => 'highTemperature'],
							['value' => '6', 'newvalue' => 'replaceImmediately'],
							['value' => '1', 'newvalue' => 'lowCapacity']
						]
					]
				],
				'/',
				'Invalid parameter "/1/mappings/7/value": value is not unique.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_UNIQ],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_UNIQ, 'length' => 64]
				]],
				[
					'valuemapid' => 5,
					'name' => 'APC Battery Status'
				],
				'/',
				'Invalid parameter "/": unexpected parameter "valuemapid".'
			],
			[
				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED | API_UNIQ],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_UNIQ, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_UNIQ, 'length' => 64],
						'newvalue' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64]
					]]
				]],
				[
					'valuemapid' => 5,
					'name' => 'APC Battery Status',
					'mappings' => ['value' => '1', 'newvalue' => 'unknown']
				],
				'/',
				[
					[
						'valuemapid' => 5,
						'name' => 'APC Battery Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown']
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param mixed  $exprected
	 */
	public function testApiInputValidator(array $rule, $data, $path, $expected) {
		$rc = CApiInputValidator::validate($rule, $data, $path, $error);

		$this->assertTrue(is_bool($rc));

		if ($rc === true) {
			$this->assertEquals(gettype($expected), gettype($data));
			$this->assertEquals($expected, $data);
		}
		else {
			$this->assertEquals(gettype($expected), gettype($error));
			$this->assertEquals($expected, $error);
		}
	}

}

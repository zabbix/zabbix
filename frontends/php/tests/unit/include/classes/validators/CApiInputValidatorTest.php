<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	public function dataProviderInput() {
		return [
			[
				['type' => API_COLOR],
				'ffffff',
				'/1/color',
				'ffffff'
			],
			[
				['type' => API_COLOR],
				'037ACF',
				'/1/color',
				'037ACF'
			],
			[
				['type' => API_COLOR],
				'000000',
				'/1/color',
				'000000'
			],
			[
				['type' => API_COLOR],
				'',
				'/1/color',
				''
			],
			[
				['type' => API_COLOR, 'flags' => API_NOT_EMPTY],
				'',
				'/1/color',
				'Invalid parameter "/1/color": cannot be empty.'
			],
			[
				['type' => API_COLOR],
				[],
				'/1/color',
				'Invalid parameter "/1/color": a character string is expected.'
			],
			[
				['type' => API_COLOR],
				true,
				'/1/color',
				'Invalid parameter "/1/color": a character string is expected.'
			],
			[
				['type' => API_COLOR],
				null,
				'/1/color',
				'Invalid parameter "/1/color": a character string is expected.'
			],
			[
				['type' => API_COLOR],
				// broken UTF-8 byte sequence
				"\xd1".'12345',
				'/1/color',
				'Invalid parameter "/1/color": invalid byte sequence in UTF-8.'
			],
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
				true,
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
				['type' => API_STRING_UTF8, 'in' => 'xml,json'],
				'json',
				'/1/name',
				'json'
			],
			[
				['type' => API_STRING_UTF8, 'in' => 'xml,json'],
				'XML',
				'/1/name',
				'Invalid parameter "/1/name": value must be one of xml, json.'
			],
			[
				['type' => API_STRINGS_UTF8],
				['hostid', 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_STRINGS_UTF8],
				['a' => 'hostid', 'b' => 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_STRINGS_UTF8],
				[],
				'/output',
				[]
			],
			[
				['type' => API_STRINGS_UTF8, 'flags' => API_NOT_EMPTY],
				[],
				'/output',
				'Invalid parameter "/output": cannot be empty.'
			],
			[
				['type' => API_STRINGS_UTF8],
				'',
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE],
				'',
				'/output',
				['']
			],
			[
				['type' => API_STRINGS_UTF8],
				true,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_STRINGS_UTF8],
				123,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_STRINGS_UTF8],
				123.5,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_STRINGS_UTF8],
				null,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL],
				null,
				'/output',
				null
			],
			[
				['type' => API_STRINGS_UTF8],
				['hostid', []],
				'/output',
				'Invalid parameter "/output/2": a character string is expected.'
			],
			[
				['type' => API_STRINGS_UTF8],
				// broken UTF-8 byte sequence
				['abc'."\xd1".'e'],
				'/output',
				'Invalid parameter "/output/1": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'hostid,name'],
				['hostid', 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'hostid,name'],
				['hostid', 'host'],
				'/output',
				'Invalid parameter "/output/2": value must be one of hostid, name.'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'hostid,name', 'uniq' => true],
				['hostid', 'name', 'name'],
				'/output',
				'Invalid parameter "/output/3": value (name) already exists.'
			],
			[
				['type' => API_INT32],
				0,
				'/1/int',
				0
			],
			[
				['type' => API_INT32],
				12345,
				'/1/int',
				12345
			],
			[
				['type' => API_INT32],
				-12345,
				'/1/int',
				-12345
			],
			[
				['type' => API_INT32],
				'012345',
				'/1/int',
				12345
			],
			[
				['type' => API_INT32],
				'-12345',
				'/1/int',
				-12345
			],
			[
				['type' => API_INT32],
				'-012345',
				'/1/int',
				-12345
			],
			[
				['type' => API_INT32],
				'-2147483648',
				'/1/int',
				-2147483648
			],
			[
				['type' => API_INT32],
				'2147483647',
				'/1/int',
				2147483647
			],
			[
				['type' => API_INT32],
				'-2147483649',
				'/1/int',
				'Invalid parameter "/1/int": a number is too large.'
			],
			[
				['type' => API_INT32],
				'2147483648',
				'/1/int',
				'Invalid parameter "/1/int": a number is too large.'
			],
			[
				['type' => API_INT32],
				'foo',
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32],
				[],
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32],
				true,
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32],
				null,
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32, 'flags' => API_ALLOW_NULL],
				null,
				'/1/int',
				null
			],
			[
				['type' => API_INT32],
				0.0,
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32],
				1.23E+11,
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
			],
			[
				['type' => API_INT32, 'in' => '0,1,2'],
				1,
				'/1/int',
				1
			],
			[
				['type' => API_INT32, 'in' => '-1,0,1,2'],
				'01',
				'/1/int',
				1
			],
			[
				['type' => API_INT32, 'in' => '-1,0,1,2'],
				-1,
				'/1/int',
				-1
			],
			[
				['type' => API_INT32, 'in' => '-1,0,1,2'],
				'-1',
				'/1/int',
				-1
			],
			[
				['type' => API_INT32, 'in' => '-1,0,1,2'],
				'-01',
				'/1/int',
				-1
			],
			[
				['type' => API_INT32, 'in' => '-1,0,1,2'],
				-2,
				'/1/int',
				'Invalid parameter "/1/int": value must be one of -1, 0, 1, 2.'
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				0,
				'/1/int',
				0
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				60,
				'/1/int',
				60
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				120,
				'/1/int',
				120
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				900,
				'/1/int',
				900
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				1,
				'/1/int',
				'Invalid parameter "/1/int": value must be one of 0, 60-900.'
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				59,
				'/1/int',
				'Invalid parameter "/1/int": value must be one of 0, 60-900.'
			],
			[
				['type' => API_INT32, 'in' => '0,60:900'],
				901,
				'/1/int',
				'Invalid parameter "/1/int": value must be one of 0, 60-900.'
			],
			[
				['type' => API_INTS32],
				[0, 1],
				'/output',
				[0, 1]
			],
			[
				['type' => API_INTS32],
				['0', '1'],
				'/output',
				[0, 1]
			],
			[
				['type' => API_INTS32],
				['a' => 0, 'b' => 1],
				'/output',
				[0, 1]
			],
			[
				['type' => API_INTS32],
				[],
				'/output',
				[]
			],
			[
				['type' => API_INTS32, 'flags' => API_NOT_EMPTY],
				[],
				'/output',
				'Invalid parameter "/output": cannot be empty.'
			],
			[
				['type' => API_INTS32],
				'',
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_INTS32],
				true,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_INTS32],
				123,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_INTS32, 'flags' => API_NORMALIZE],
				123,
				'/output',
				[123]
			],
			[
				['type' => API_INTS32],
				123.5,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_INTS32],
				null,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_INTS32, 'flags' => API_ALLOW_NULL],
				null,
				'/output',
				null
			],
			[
				['type' => API_INTS32],
				[0, []],
				'/output',
				'Invalid parameter "/output/2": an integer is expected.'
			],
			[
				['type' => API_INTS32, 'in' => '1:100'],
				[55, 67],
				'/output',
				[55, 67]
			],
			[
				['type' => API_INTS32, 'in' => '1:100'],
				[55, 55, 101],
				'/output',
				'Invalid parameter "/output/3": value must be one of 1-100.'
			],
			[
				['type' => API_INTS32, 'uniq' => true],
				[55, 55, 101],
				'/output',
				'Invalid parameter "/output/2": value (55) already exists.'
			],
			[
				['type' => API_UINT64],
				0,
				'/1/int',
				'0'
			],
			[
				['type' => API_UINT64],
				12345,
				'/1/int',
				'12345'
			],
			[
				['type' => API_UINT64],
				-12345,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				'012345',
				'/1/int',
				'12345'
			],
			[
				['type' => API_UINT64],
				'-012345',
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				'18446744073709551615',
				'/1/int',
				'18446744073709551615'
			],
			[
				['type' => API_UINT64],
				'18446744073709551616',
				'/1/int',
				'Invalid parameter "/1/int": a number is too large.'
			],
			[
				['type' => API_UINT64],
				'foo',
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				[],
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				true,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				null,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64, 'flags' => API_ALLOW_NULL],
				null,
				'/1/int',
				null
			],
			[
				['type' => API_UINT64],
				0.0,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINT64],
				1.23E+11,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
			],
			[
				['type' => API_UINTS64],
				[0, 1],
				'/output',
				['0', '1']
			],
			[
				['type' => API_UINTS64],
				['0', '1'],
				'/output',
				['0', '1']
			],
			[
				['type' => API_UINTS64],
				['a' => 0, 'b' => 1],
				'/output',
				['0', '1']
			],
			[
				['type' => API_UINTS64],
				[],
				'/output',
				[]
			],
			[
				['type' => API_UINTS64, 'flags' => API_NOT_EMPTY],
				[],
				'/output',
				'Invalid parameter "/output": cannot be empty.'
			],
			[
				['type' => API_UINTS64],
				'',
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_UINTS64],
				true,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_UINTS64],
				123,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_UINTS64, 'flags' => API_NORMALIZE],
				123,
				'/output',
				['123']
			],
			[
				['type' => API_UINTS64],
				123.5,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_UINTS64],
				null,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_UINTS64, 'flags' => API_ALLOW_NULL],
				null,
				'/output',
				null
			],
			[
				['type' => API_UINTS64],
				[0, []],
				'/output',
				'Invalid parameter "/output/2": an unsigned integer is expected.'
			],
			[
				['type' => API_FLOAT],
				0,
				'/1/float',
				0.0
			],
			[
				['type' => API_FLOAT],
				0.5,
				'/1/float',
				0.5
			],
			[
				['type' => API_FLOAT],
				-0.5,
				'/1/float',
				-0.5
			],
			[
				['type' => API_FLOAT],
				12345,
				'/1/float',
				12345.0
			],
			[
				['type' => API_FLOAT],
				-12345,
				'/1/float',
				-12345.0
			],
			[
				['type' => API_FLOAT],
				'012345',
				'/1/float',
				12345.0
			],
			[
				['type' => API_FLOAT],
				'-12345',
				'/1/float',
				-12345.0
			],
			[
				['type' => API_FLOAT],
				'-012345',
				'/1/float',
				-12345.0
			],
			[
				['type' => API_FLOAT],
				'-2147483648',
				'/1/float',
				-2147483648.0
			],
			[
				['type' => API_FLOAT],
				'2147483647',
				'/1/float',
				2147483647.0
			],
			[
				['type' => API_FLOAT],
				'-2147483649',
				'/1/float',
				-2147483649.0
			],
			[
				['type' => API_FLOAT],
				'2147483648',
				'/1/float',
				2147483648.0
			],
			[
				['type' => API_FLOAT],
				'foo',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT],
				[],
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT],
				true,
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT],
				null,
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_NULL],
				null,
				'/1/float',
				null
			],
			[
				['type' => API_FLOAT],
				1.23E+11,
				'/1/float',
				1.23E+11,
			],
			[
				['type' => API_FLOAT],
				'1.23E+11',
				'/1/float',
				1.23E+11,
			],
			[
				['type' => API_FLOAT],
				'1.23e+11',
				'/1/float',
				1.23E+11,
			],
			[
				['type' => API_FLOAT],
				'-1.23e+11',
				'/1/float',
				-1.23E+11,
			],
			[
				['type' => API_FLOAT],
				'.23E11',
				'/1/float',
				0.23E+11,
			],
			[
				['type' => API_FLOATS],
				[0, 1],
				'/output',
				[0.0, 1.0]
			],
			[
				['type' => API_FLOATS],
				['0', '1'],
				'/output',
				[0.0, 1.0]
			],
			[
				['type' => API_FLOATS],
				['a' => 0, 'b' => 1],
				'/output',
				[0.0, 1.0]
			],
			[
				['type' => API_FLOATS],
				[],
				'/output',
				[]
			],
			[
				['type' => API_FLOATS, 'flags' => API_NOT_EMPTY],
				[],
				'/output',
				'Invalid parameter "/output": cannot be empty.'
			],
			[
				['type' => API_FLOATS],
				'',
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_FLOATS],
				true,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_FLOATS],
				123,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_FLOATS, 'flags' => API_NORMALIZE],
				123,
				'/output',
				[123.0]
			],
			[
				['type' => API_FLOATS],
				123.5,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_FLOATS],
				null,
				'/output',
				'Invalid parameter "/output": an array is expected.'
			],
			[
				['type' => API_FLOATS, 'flags' => API_ALLOW_NULL],
				null,
				'/output',
				null
			],
			[
				['type' => API_FLOATS],
				[0, []],
				'/output',
				'Invalid parameter "/output/2": a floating point value is expected.'
			],
			[
				['type' => API_ID],
				0,
				'/1/id',
				'0'
			],
			[
				['type' => API_ID, 'flags' => API_NOT_EMPTY],
				0,
				'/1/id',
				'Invalid parameter "/1/id": cannot be empty.'
			],
			[
				['type' => API_ID],
				12345,
				'/1/id',
				'12345'
			],
			[
				['type' => API_ID],
				'012345',
				'/1/id',
				'12345'
			],
			[
				['type' => API_ID],
				'00',
				'/1/id',
				'0'
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
				true,
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
				['type' => API_ID],
				0.0,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID],
				1.23E+11,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_BOOLEAN],
				true,
				'/1/createMissing',
				true
			],
			[
				['type' => API_BOOLEAN],
				false,
				'/1/createMissing',
				false
			],
			[
				['type' => API_BOOLEAN],
				'-1',
				'/1/createMissing',
				'Invalid parameter "/1/createMissing": a boolean is expected.'
			],
			[
				['type' => API_BOOLEAN],
				0,
				'/1/createMissing',
				'Invalid parameter "/1/createMissing": a boolean is expected.'
			],
			[
				['type' => API_BOOLEAN],
				[],
				'/1/createMissing',
				'Invalid parameter "/1/createMissing": a boolean is expected.'
			],
			[
				['type' => API_BOOLEAN],
				0.0,
				'/1/createMissing',
				'Invalid parameter "/1/createMissing": a boolean is expected.'
			],
			[
				['type' => API_BOOLEAN],
				null,
				'/1/createMissing',
				'Invalid parameter "/1/createMissing": a boolean is expected.'
			],
			[
				['type' => API_FLAG],
				true,
				'/1/userData',
				true
			],
			[
				['type' => API_FLAG],
				false,
				'/1/userData',
				false
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				[],
				'/',
				[]
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				true,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_OBJECT, 'fields' => []],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'fields' => []],
				null,
				'/',
				null
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
				true,
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
				['46342']
			],
			[
				['type' => API_IDS, 'flags' => API_NORMALIZE],
				'00',
				'/',
				['0']
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
				[0, 1, 2, 3, '00', '4', '9223372036854775807'],
				'/',
				['0', '1', '2', '3', '0', '4', '9223372036854775807']
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
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7],
				'/',
				['0', '1', '2', '3', '4', '9223372036854775807', '5', '6', '7']
			],
			[
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '3'],
				'/',
				'Invalid parameter "/10": value (3) already exists.'
			],
			[
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, 0.0],
				'/',
				'Invalid parameter "/10": a number is expected.'
			],
			[
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '03'],
				'/',
				'Invalid parameter "/10": value (3) already exists.'
			],
			[
				['type' => API_OBJECTS],
				true,
				'/',
				'Invalid parameter "/": an array is expected.'
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
				[[]]
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
					'name' => ['type' => API_STRING_UTF8],
					'col' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'default' => '0'],
					'row' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'default' => '1'],
					'width' => ['type' => API_INT32],
					'height' => ['type' => API_INT32]
				]],
				[
					['name' => 'Zabbix server 1'],
					['name' => 'Zabbix server 2', 'col' => 5, 'row' => 10, 'width' => 1, 'height' => 1]
				],
				'/',
				[
					['name' => 'Zabbix server 1', 'col' => 0, 'row' => 1],
					['name' => 'Zabbix server 2', 'col' => 5, 'row' => 10, 'width' => 1, 'height' => 1]
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
				['type' => API_OBJECTS, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['value']], 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64],
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
						'valuemapid' => '4',
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
						'valuemapid' => '5',
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
				['type' => API_OBJECTS, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 64]
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
				'Invalid parameter "/3": value (valuemapid)=(4) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['value']], 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64],
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
				'Invalid parameter "/1/mappings/7": value (value)=(1) already exists.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'tags' => ['type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
						'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
						'operator'	=> ['type' => API_INT32, 'in' => implode(',', [0, 2]), 'default' => 2],
						'value'		=> ['type' => API_STRING_UTF8, 'length' => 255, 'default' => '']
					]]
				]],
				[
					'tags' => [
						['tag' => 'tag', 'operator' => 0, 'value' => ''],
						['tag' => 'tag', 'operator' => 0, 'value' => '']
					]
				],
				'/',
				'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 0, ) already exists.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'tags' => ['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'fields' => [
						'tag'	=> ['type' => API_STRING_UTF8],
					]]
				]],
				[
					'tags' => null
				],
				'/',
				[
					'tags' => null
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'tags' => ['type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
						'tag'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 255],
						'operator'	=> ['type' => API_INT32, 'in' => implode(',', [0, 2]), 'default' => 2],
						'value'		=> ['type' => API_STRING_UTF8, 'length' => 255, 'default' => '']
					]]
				]],
				[
					'tags' => [
						['tag' => 'tag'],
						['tag' => 'tag']
					]
				],
				'/',
				'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 2, ) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 64]
				]],
				[
					'valuemapid' => 5,
					'name' => 'APC Battery Status'
				],
				'/',
				'Invalid parameter "/1": an array is expected.'
			],
			[
				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['valuemapid'], ['name']], 'fields' => [
					'valuemapid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
					'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 64],
					'mappings' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['value']], 'fields' => [
						'value' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 64],
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
						'valuemapid' => '5',
						'name' => 'APC Battery Status',
						'mappings' => [
							['value' => '1', 'newvalue' => 'unknown']
						]
					]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32],
						['if' => ['field' => 'type', 'in' => '3,4'], 'type' => API_STRING_UTF8],
						['if' => ['field' => 'type', 'in' => '5:9'], 'type' => API_ID]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => '125'],
					['type' => '3', 'value' => 'text'],
					['type' => '4', 'value' => 'text3'],
					['type' => '7', 'value' => '123456789012345']
				],
				'/',
				[
					['type' => 1, 'value' => -5],
					['type' => 2, 'value' => 125],
					['type' => 3, 'value' => 'text'],
					['type' => 4, 'value' => 'text3'],
					['type' => 7, 'value' => '123456789012345']
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => 'a125']
				],
				'/',
				'Invalid parameter "/2/value": an integer is expected.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,3'], 'type' => API_INT32]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => '125']
				],
				'/',
				'Incorrect validation rules.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'host' =>	['type' => API_H_NAME, 'flags' => API_REQUIRED],
					'name' =>	['type' => API_STRING_UTF8, 'default_source' => 'host'],
				]],
				[
					['host' => 'host 0'],
					['host' => 'host 1', 'name' => 'visible name 1'],
					['host' => 'host 2'],
					['host' => 'host 3'],
					['host' => 'host 4']
				],
				'/',
				[
					['host' => 'host 0', 'name' => 'host 0'],
					['host' => 'host 1', 'name' => 'visible name 1'],
					['host' => 'host 2', 'name' => 'host 2'],
					['host' => 'host 3', 'name' => 'host 3'],
					['host' => 'host 4', 'name' => 'host 4']
				]
			],
			[
				['type' => API_HG_NAME, 'length' => 16],
				'Zabbix servers',
				'/1/name',
				'Zabbix servers'
			],
			[
				['type' => API_HG_NAME, 'length' => 16],
				'Zabbix Servers+++',
				'/1/name',
				'Invalid parameter "/1/name": value is too long.'
			],
			[
				['type' => API_HG_NAME],
				'',
				'/1/name',
				'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				['type' => API_HG_NAME],
				[],
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_HG_NAME],
				true,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_HG_NAME],
				null,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_HG_NAME],
				// broken UTF-8 byte sequence
				'Заббикс '."\xd1".'сервера',
				'/1/name',
				'Invalid parameter "/1/name": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_HG_NAME],
				'Latvia/Riga',
				'/1/name',
				'Latvia/Riga'
			],
			[
				['type' => API_HG_NAME],
				'/Latvia/Riga',
				'/1/name',
				'Invalid parameter "/1/name": invalid host group name.'
			],
			[
				['type' => API_HG_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'Latvia/Riga',
				'/1/name',
				'Invalid parameter "/1/name": must contain at least one low-level discovery macro.'
			],
			[
				['type' => API_HG_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'Latvia/Riga/{#DC.NAME}',
				'/1/name',
				'Latvia/Riga/{#DC.NAME}'
			],
			[
				['type' => API_HG_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'{{#DC}.regsub(".*", "//\1")}',
				'/1/name',
				'{{#DC}.regsub(".*", "//\1")}'
			],
			[
				['type' => API_H_NAME, 'length' => 16],
				'Zabbix server',
				'/1/name',
				'Zabbix server'
			],
			[
				['type' => API_H_NAME, 'length' => 16],
				'Zabbix server++++',
				'/1/name',
				'Invalid parameter "/1/name": value is too long.'
			],
			[
				['type' => API_H_NAME],
				'',
				'/1/name',
				'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				['type' => API_H_NAME],
				[],
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_H_NAME],
				true,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_H_NAME],
				null,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_H_NAME],
				// broken UTF-8 byte sequence
				'Zabbix '."\xd1".'server',
				'/1/name',
				'Invalid parameter "/1/name": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'Linux server',
				'/1/name',
				'Invalid parameter "/1/name": must contain at least one low-level discovery macro.'
			],
			[
				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'{#PREFIX}-server',
				'/1/name',
				'{#PREFIX}-server'
			],
			[
				['type' => API_H_NAME, 'flags' => API_REQUIRED_LLD_MACRO],
				'{{#HOST}.regsub("^[a-z]+", "\1")}',
				'/1/name',
				'{{#HOST}.regsub("^[a-z]+", "\1")}'
			],
			[
				['type' => API_NUMERIC],
				'',
				'/1/numeric',
				''
			],
			[
				['type' => API_NUMERIC, 'flags' => API_NOT_EMPTY],
				'',
				'/1/numeric',
				'Invalid parameter "/1/numeric": cannot be empty.'
			],
			[
				['type' => API_NUMERIC],
				0,
				'/1/numeric',
				'0'
			],
			[
				['type' => API_NUMERIC, 'length' => 5],
				12345,
				'/1/numeric',
				'12345'
			],
			[
				['type' => API_NUMERIC, 'length' => 5],
				123456,
				'/1/numeric',
				'Invalid parameter "/1/numeric": value is too long.'
			],
			[
				['type' => API_NUMERIC],
				-12345,
				'/1/numeric',
				'-12345'
			],
			[
				['type' => API_NUMERIC],
				'00',
				'/1/numeric',
				'0'
			],
			[
				['type' => API_NUMERIC],
				'-00',
				'/1/numeric',
				'-0'
			],
			[
				['type' => API_NUMERIC],
				'0001.15',
				'/1/numeric',
				'1.15'
			],
			[
				['type' => API_NUMERIC],
				'-0000.0125',
				'/1/numeric',
				'-0.0125'
			],
			[
				['type' => API_NUMERIC],
				'012345',
				'/1/numeric',
				'12345'
			],
			[
				['type' => API_NUMERIC],
				'-012345',
				'/1/numeric',
				'-12345'
			],
			[
				['type' => API_NUMERIC],
				'-9223372036854775808',
				'/1/numeric',
				'-9223372036854775808'
			],
			[
				['type' => API_NUMERIC],
				'9223372036854775807',
				'/1/numeric',
				'9223372036854775807'
			],
			[
				['type' => API_NUMERIC],
				'-9223372036854775809',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'9223372036854775808',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'-9223372036854775808.000001',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'9223372036854775807.000001',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'.124',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is expected.'
			],
			[
				['type' => API_NUMERIC],
				'foo',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is expected.'
			],
			[
				['type' => API_NUMERIC],
				[],
				'/1/numeric',
				'Invalid parameter "/1/numeric": a character string is expected.'
			],
			[
				['type' => API_NUMERIC],
				true,
				'/1/numeric',
				'Invalid parameter "/1/numeric": a character string is expected.'
			],
			[
				['type' => API_NUMERIC],
				null,
				'/1/numeric',
				'Invalid parameter "/1/numeric": a character string is expected.'
			],
			[
				['type' => API_NUMERIC],
				'5s',
				'/1/numeric',
				'5s'
			],
			[
				['type' => API_NUMERIC],
				'5m',
				'/1/numeric',
				'5m'
			],
			[
				['type' => API_NUMERIC],
				'5h',
				'/1/numeric',
				'5h'
			],
			[
				['type' => API_NUMERIC],
				'5d',
				'/1/numeric',
				'5d'
			],
			[
				['type' => API_NUMERIC],
				'5w',
				'/1/numeric',
				'5w'
			],
			[
				['type' => API_NUMERIC],
				'5K',
				'/1/numeric',
				'5K'
			],
			[
				['type' => API_NUMERIC],
				'5M',
				'/1/numeric',
				'5M'
			],
			[
				['type' => API_NUMERIC],
				'5G',
				'/1/numeric',
				'5G'
			],
			[
				['type' => API_NUMERIC],
				'5T',
				'/1/numeric',
				'5T'
			],
			[
				['type' => API_NUMERIC],
				'8388607T',
				'/1/numeric',
				'8388607T'
			],
			[
				['type' => API_NUMERIC],
				'8388608T',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_SCRIPT_NAME, 'length' => 23],
				'Detect operating system',
				'/1/name',
				'Detect operating system'
			],
			[
				['type' => API_SCRIPT_NAME, 'length' => 23],
				'folder1/folder2\/',
				'/1/name',
				'folder1/folder2\/'
			],
			[
				['type' => API_SCRIPT_NAME, 'length' => 23],
				'Detect operating system+',
				'/1/name',
				'Invalid parameter "/1/name": value is too long.'
			],
			[
				['type' => API_SCRIPT_NAME],
				'',
				'/1/name',
				'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				['type' => API_SCRIPT_NAME],
				'a/b/c/',
				'/1/name',
				'Invalid parameter "/1/name": directory or script name cannot be empty.'
			],
			[
				['type' => API_SCRIPT_NAME],
				'a/'.'/c',
				'/1/name',
				'Invalid parameter "/1/name": directory or script name cannot be empty.'
			],
			[
				['type' => API_SCRIPT_NAME],
				[],
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_NAME],
				true,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_NAME],
				null,
				'/1/name',
				'Invalid parameter "/1/name": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_NAME],
				// broken UTF-8 byte sequence
				'Detect '."\xd1".'perating system',
				'/1/name',
				'Invalid parameter "/1/name": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_USER_MACRO, 'length' => 8],
				'{$MACRO}',
				'/1/macro',
				'{$MACRO}'
			],
			[
				['type' => API_USER_MACRO, 'length' => 19],
				'{$MACRO: "context"}',
				'/1/macro',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_USER_MACRO, 'length' => 18],
				'{$MACRO: "context"}',
				'/1/macro',
				'Invalid parameter "/1/macro": value is too long.'
			],
			[
				['type' => API_USER_MACRO],
				'{$MACRo}',
				'/1/macro',
				'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				['type' => API_USER_MACRO],
				'{$MACRO} ',
				'/1/macro',
				'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				['type' => API_USER_MACRO],
				'{$MACRO: "context"',
				'/1/macro',
				'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				['type' => API_USER_MACRO],
				true,
				'/1/macro',
				'Invalid parameter "/1/macro": a character string is expected.'
			],
			[
				['type' => API_USER_MACRO],
				[],
				'/1/macro',
				'Invalid parameter "/1/macro": a character string is expected.'
			],
			[
				['type' => API_USER_MACRO],
				null,
				'/1/macro',
				'Invalid parameter "/1/macro": a character string is expected.'
			],
			[
				['type' => API_USER_MACRO],
				// broken UTF-8 byte sequence
				'{$MACRO: '."\xd1".'ontext}',
				'/1/macro',
				'Invalid parameter "/1/macro": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_RANGE_TIME, 'length' => 6],
				'now-1d',
				'/1/time',
				'now-1d'
			],
			[
				['type' => API_RANGE_TIME, 'length' => 8],
				'now-1d-1h',
				'/1/time',
				'Invalid parameter "/1/time": value is too long.'
			],
			[
				['type' => API_RANGE_TIME],
				'{$MACRO}',
				'/1/time',
				'Invalid parameter "/1/time": a time range is expected.'
			],
			[
				['type' => API_RANGE_TIME],
				'',
				'/1/time',
				'Invalid parameter "/1/time": cannot be empty.'
			],
			[
				['type' => API_RANGE_TIME],
				[],
				'/1/time',
				'Invalid parameter "/1/time": a character string is expected.'
			],
			[
				['type' => API_RANGE_TIME],
				true,
				'/1/time',
				'Invalid parameter "/1/time": a character string is expected.'
			],
			[
				['type' => API_RANGE_TIME],
				null,
				'/1/time',
				'Invalid parameter "/1/time": a character string is expected.'
			],
			[
				['type' => API_RANGE_TIME],
				'now-5x',
				'/1/time',
				'Invalid parameter "/1/time": a time range is expected.'
			],
			[
				['type' => API_RANGE_TIME],
				// broken UTF-8 byte sequence
				'now-'."\xd1".'d',
				'/1/time',
				'Invalid parameter "/1/time": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_TIME_PERIOD, 'length' => 16],
				'1-7,00:00-24:00',
				'/1/period',
				'1-7,00:00-24:00'
			],
			[
				['type' => API_TIME_PERIOD],
				'1-5,09:00-18:00;6-7,09:00-15:00',
				'/1/period',
				'1-5,09:00-18:00;6-7,09:00-15:00'
			],
			[
				['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/period',
				'{$MACRO}'
			],
			[
				['type' => API_TIME_PERIOD],
				'{$MACRO}',
				'/1/period',
				'Invalid parameter "/1/period": a time period is expected.'
			],
			[
				['type' => API_TIME_PERIOD],
				'',
				'/1/period',
				'Invalid parameter "/1/period": cannot be empty.'
			],
			[
				['type' => API_TIME_PERIOD],
				[],
				'/1/period',
				'Invalid parameter "/1/period": a character string is expected.'
			],
			[
				['type' => API_TIME_PERIOD],
				true,
				'/1/period',
				'Invalid parameter "/1/period": a character string is expected.'
			],
			[
				['type' => API_TIME_PERIOD],
				null,
				'/1/period',
				'Invalid parameter "/1/period": a character string is expected.'
			],
			[
				['type' => API_TIME_PERIOD],
				'1,00:00-24:00a',
				'/1/period',
				'Invalid parameter "/1/period": a time period is expected.'
			],
			[
				['type' => API_TIME_PERIOD],
				// broken UTF-8 byte sequence
				'1-7'."\xd1".',00:00-24:00',
				'/1/period',
				'Invalid parameter "/1/period": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_REGEX, 'length' => 7],
				'^[a-z]$',
				'/1/expression',
				'^[a-z]$'
			],
			[
				['type' => API_REGEX, 'length' => 6],
				'^[a-z]$',
				'/1/expression',
				'Invalid parameter "/1/expression": value is too long.'
			],
			[
				['type' => API_REGEX, 'flags' => API_NOT_EMPTY],
				'^[a-z]$',
				'/1/expression',
				'^[a-z]$'
			],
			[
				['type' => API_REGEX, 'flags' => API_NOT_EMPTY],
				'',
				'/1/expression',
				'Invalid parameter "/1/expression": cannot be empty.'
			],
			[
				['type' => API_REGEX],
				'',
				'/1/expression',
				''
			],
			[
				['type' => API_REGEX],
				[],
				'/1/expression',
				'Invalid parameter "/1/expression": a character string is expected.'
			],
			[
				['type' => API_REGEX],
				true,
				'/1/expression',
				'Invalid parameter "/1/expression": a character string is expected.'
			],
			[
				['type' => API_REGEX],
				null,
				'/1/expression',
				'Invalid parameter "/1/expression": a character string is expected.'
			],
			[
				['type' => API_REGEX],
				// broken UTF-8 byte sequence
				'^'."\xd1".'$',
				'/1/expression',
				'Invalid parameter "/1/expression": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_REGEX],
				'^[a-z$',
				'/1/expression',
				'Invalid parameter "/1/expression": invalid regular expression.'
			],
			[
				['type' => API_REGEX],
				'@^[a-z$',
				'/1/expression',
				'@^[a-z$'
			],
			[
				['type' => API_REGEX],
				'/',
				'/1/expression',
				'/'
			],
			[
				['type' => API_REGEX, 'length' => 8],
				'/test/i',
				'/1/expression',
				'/test/i'
			],
			[
				['type' => API_VARIABLE_NAME, 'length' => 6],
				'{var1}',
				'/1/variables',
				'{var1}'
			],
			[
				['type' => API_VARIABLE_NAME, 'length' => 5],
				'{var1}',
				'/1/variables',
				'Invalid parameter "/1/variables": value is too long.'
			],
			[
				['type' => API_VARIABLE_NAME],
				'',
				'/1/variables',
				'Invalid parameter "/1/variables": cannot be empty.'
			],
			[
				['type' => API_VARIABLE_NAME],
				null,
				'/1/variables',
				'Invalid parameter "/1/variables": a character string is expected.'
			],
			[
				['type' => API_VARIABLE_NAME],
				'{var',
				'/1/variables',
				'Invalid parameter "/1/variables": is not enclosed in {} or is malformed.'
			],
			[
				['type' => API_HTTP_POST, 'name-length' => 255],
				[
					[
						'name' => str_repeat('Long ', 95).'name',
						'value' => 'value'
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/1/name": value is too long.'
			],
			[
				['type' => API_HTTP_POST, 'value-length' => 255],
				[
					[
						'name' => 'name',
						'value' => str_repeat('Long ', 95).'value'
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/1/value": value is too long.'
			],
			[
				['type' => API_HTTP_POST, 'name-length' => 6, 'value-length' => 19],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => 'v:a:l:u:e'
					]
				],
				'/1/posts',
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => 'v:a:l:u:e'
					]
				]
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2": the parameter "name" is missing.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom'
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2": the parameter "value" is missing.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => 'v:a:l:u:e',
						'type' => 1
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2": unexpected parameter "type".'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => null,
						'value' => 'v:a:l:u:e'
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2/name": a character string is expected.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => true
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2/value": a character string is expected.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => '',
						'value' => 'v:a:l:u:e'
					]
				],
				'/1/posts',
				'Invalid parameter "/1/posts/2/name": cannot be empty.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => ''
					]
				],
				'/1/posts',
				[
					[
						'name' => 'Host',
						'value' => 'www.zabbix.com:8080'
					],
					[
						'name' => 'Custom',
						'value' => ''
					]
				]
			],
			[
				['type' => API_HTTP_POST],
				true,
				'/1/posts',
				'Invalid parameter "/1/posts": a character string is expected.'
			],
			[
				['type' => API_HTTP_POST],
				null,
				'/1/posts',
				'Invalid parameter "/1/posts": a character string is expected.'
			],
			[
				['type' => API_HTTP_POST],
				['a', 'b'],
				'/1/posts',
				'Invalid parameter "/1/posts/1": an array is expected.'
			],
			[
				['type' => API_HTTP_POST],
				'a=raw\r post that\n should : not be altered',
				'/1/posts',
				'a=raw\r post that\n should : not be altered'
			],
			[
				['type' => API_HTTP_POST, 'length' => 10],
				'12345678901',
				'/1/posts',
				'Invalid parameter "/1/posts": value is too long.'
			],
			[
				['type' => API_HTTP_POST],
				[
					[
						'name' => 'p1',
						'value' => 'value1'
					],
					[
						'name' => 'p2',
						'value' => 'value2'
					]
				],
				'/1/posts',
				[
					[
						'name' => 'p1',
						'value' => 'value1'
					],
					[
						'name' => 'p2',
						'value' => 'value2'
					]
				]
			],
			[
				['type' => API_TIME_UNIT],
				'30h',
				'/1/time_unit',
				'30h'
			],
			[
				['type' => API_TIME_UNIT],
				'-2147483649s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a number is too large.'
			],
			[
				['type' => API_TIME_UNIT],
				'-2147483648s',
				'/1/time_unit',
				'-2147483648s'
			],
			[
				['type' => API_TIME_UNIT],
				'2147483647s',
				'/1/time_unit',
				'2147483647s'
			],
			[
				['type' => API_TIME_UNIT],
				'2147483648s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a number is too large.'
			],
			[
				['type' => API_TIME_UNIT],
				'3550w',
				'/1/time_unit',
				'3550w'
			],
			[
				['type' => API_TIME_UNIT],
				'',
				'/1/time_unit',
				''
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY],
				'',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": cannot be empty.'
			],
			[
				['type' => API_TIME_UNIT],
				'2147483648s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a number is too large.'
			],
			[
				['type' => API_TIME_UNIT],
				'3551w',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a number is too large.'
			],
			[
				['type' => API_TIME_UNIT],
				'30mm',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a time unit is expected.'
			],
			[
				['type' => API_TIME_UNIT, 'in' => '-100:100'],
				'-101s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": value must be one of -100-100.'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:100'],
				'101s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": value must be one of 1-100.'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:100'],
				'100s',
				'/1/time_unit',
				'100s'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:100'],
				'{$MACRO}',
				'/1/time_unit',
				'{$MACRO}'
			],
			[
				['type' => API_TIME_UNIT],
				'{$MACRO}',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a time unit is expected.'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_LLD_MACRO | API_ALLOW_USER_MACRO],
				'{#MACRO}',
				'/1/time_unit',
				'{#MACRO}'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO}',
				'/1/time_unit',
				'{#MACRO}'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_LLD_MACRO, 'in' => '1:100'],
				'101s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": value must be one of 1-100.'
			],
			[
				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_LLD_MACRO, 'in' => '1:100'],
				'100s',
				'/1/time_unit',
				'100s'
			],
			[
				['type' => API_TIME_UNIT],
				'{#MACRO}',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": a time unit is expected.'
			],
			[
				['type' => API_OUTPUT],
				['hostid', 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_OUTPUT],
				['a' => 'hostid', 'b' => 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_OUTPUT],
				[],
				'/output',
				[]
			],
			[
				['type' => API_OUTPUT],
				'extend',
				'/output',
				'extend'
			],
			[
				['type' => API_OUTPUT],
				'count',
				'/output',
				'Invalid parameter "/output": value must be one of extend.'
			],
			[
				['type' => API_OUTPUT, 'flags' => API_ALLOW_COUNT],
				'count',
				'/output',
				'count'
			],
			[
				['type' => API_OUTPUT],
				'',
				'/output',
				'Invalid parameter "/output": value must be one of extend.'
			],
			[
				['type' => API_OUTPUT, 'flags' => API_ALLOW_COUNT],
				'',
				'/output',
				'Invalid parameter "/output": value must be one of extend, count.'
			],
			[
				['type' => API_OUTPUT],
				true,
				'/output',
				'Invalid parameter "/output": an array or a character string is expected.'
			],
			[
				['type' => API_OUTPUT],
				123,
				'/output',
				'Invalid parameter "/output": an array or a character string is expected.'
			],
			[
				['type' => API_OUTPUT],
				123.5,
				'/output',
				'Invalid parameter "/output": an array or a character string is expected.'
			],
			[
				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL],
				null,
				'/output',
				null
			],
			[
				['type' => API_OUTPUT],
				null,
				'/output',
				'Invalid parameter "/output": an array or a character string is expected.'
			],
			[
				['type' => API_OUTPUT],
				['hostid', []],
				'/output',
				'Invalid parameter "/output/2": a character string is expected.'
			],
			[
				['type' => API_OUTPUT],
				// broken UTF-8 byte sequence
				['abc'."\xd1".'e'],
				'/output',
				'Invalid parameter "/output/1": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_OUTPUT, 'in' => 'hostid,name'],
				['hostid', 'name'],
				'/output',
				['hostid', 'name']
			],
			[
				['type' => API_OUTPUT, 'in' => 'hostid,name'],
				['hostid', 'host'],
				'/output',
				'Invalid parameter "/output/2": value must be one of hostid, name.'
			],
			[
				['type' => API_OUTPUT, 'in' => 'hostid,name'],
				['hostid', 'name', 'name'],
				'/output',
				'Invalid parameter "/output/3": value (name) already exists.'
			],
			[
				['type' => API_URL],
				'',
				'/1/url',
				''
			],
			[
				['type' => API_URL, 'flags' => API_NOT_EMPTY],
				'',
				'/1/url',
				'Invalid parameter "/1/url": cannot be empty.'
			],
			[
				['type' => API_URL],
				'http://www.zabbix.com',
				'/1/url',
				'http://www.zabbix.com'
			],
			[
				['type' => API_URL],
				'https://www.zabbix.com',
				'/1/url',
				'https://www.zabbix.com'
			],
			[
				['type' => API_URL],
				'mailto:example@example.com',
				'/1/url',
				'mailto:example@example.com'
			],
			[
				['type' => API_URL],
				'file://localhost/path',
				'/1/url',
				'file://localhost/path'
			],
			[
				['type' => API_URL],
				'ssh://username@hostname',
				'/1/url',
				'ssh://username@hostname'
			],
			[
				['type' => API_URL],
				'ftp://user@host:8080',
				'/1/url',
				'ftp://user@host:8080'
			],
			[
				['type' => API_URL],
				'tel:1-111-111-1111',
				'/1/url',
				'tel:1-111-111-1111'
			],
			[
				['type' => API_URL],
				'zabbix.php?action=dashboard.view',
				'/1/url',
				'zabbix.php?action=dashboard.view'
			],
			[
				['type' => API_URL, 'length' => 9],
				'hosts.php',
				'/1/url',
				'hosts.php'
			],
			[
				['type' => API_URL, 'length' => 8],
				'hosts.php',
				'/1/url',
				'Invalid parameter "/1/url": value is too long.'
			],
			[
				['type' => API_URL],
				[],
				'/1/url',
				'Invalid parameter "/1/url": a character string is expected.'
			],
			[
				['type' => API_URL],
				true,
				'/1/url',
				'Invalid parameter "/1/url": a character string is expected.'
			],
			[
				['type' => API_URL],
				null,
				'/1/url',
				'Invalid parameter "/1/url": a character string is expected.'
			],
			[
				['type' => API_URL],
				// broken UTF-8 byte sequence
				'hosts.'."\xd1".'hp',
				'/1/url',
				'Invalid parameter "/1/url": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_URL],
				'javascript:alert()',
				'/1/url',
				'Invalid parameter "/1/url": unacceptable URL.'
			],
			[
				['type' => API_URL],
				'/chart_bar.php?a=1&b=2',
				'/1/url',
				'Invalid parameter "/1/url": unacceptable URL.'
			],
			[
				['type' => API_URL],
				'{$URL}',
				'/1/url',
				'Invalid parameter "/1/url": unacceptable URL.'
			],
			[
				['type' => API_URL, 'flags' => API_ALLOW_USER_MACRO],
				'{$URL}',
				'/1/url',
				'{$URL}'
			],
			[
				['type' => API_URL, 'flags' => API_ALLOW_USER_MACRO],
				'javascript:{$URL}',
				'/1/url',
				'javascript:{$URL}'
			]
		];
	}

	/**
	 * @dataProvider dataProviderInput
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
			$this->assertSame(gettype($expected), gettype($data));
			$this->assertSame('string', gettype($error));
			$this->assertSame($expected, $data);
			$this->assertSame('', $error);
		}
		else {
			$this->assertSame(gettype($expected), gettype($error));
			$this->assertSame($expected, $error);
		}
	}

	public function dataProviderUniqueness() {
		return [
			[
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7],
				'/',
				true,
				''
			],
			[
				['type' => API_IDS],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '3'],
				'/',
				true,
				''
			],
			[
				['type' => API_IDS, 'uniq' => true],
				[0, 1, 2, 3, '4', '9223372036854775807', 5, 6, 7, '3'],
				'/',
				false,
				'Invalid parameter "/10": value (3) already exists.'
			],
			[
				['type' => API_STRINGS_UTF8, 'uniq' => true],
				['dashboardid', 'name', 'userid', 'private'],
				'/',
				true,
				''
			],
			[
				['type' => API_STRINGS_UTF8],
				['dashboardid', 'name', 'userid', 'private', 'dashboardid'],
				'/',
				true,
				''
			],
			[
				['type' => API_STRINGS_UTF8, 'uniq' => true],
				['dashboardid', 'name', 'userid', 'private', 'dashboardid'],
				'/',
				false,
				'Invalid parameter "/5": value (dashboardid) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']]],
				[
					['applicationid' => 1, 'hostid' => 1, 'name' => 'app1'],
					['applicationid' => 2, 'hostid' => 1, 'name' => 'app2'],
					['applicationid' => 3, 'hostid' => 1, 'name' => 'app3'],
					['applicationid' => 4, 'hostid' => 1, 'name' => 'app4'],
					['applicationid' => 5, 'hostid' => 1, 'name' => 'app5'],
					['applicationid' => 6, 'hostid' => 1, 'name' => 'app6'],
					['applicationid' => 7, 'hostid' => 1, 'name' => 'app7'],
					['applicationid' => 8, 'hostid' => 1, 'name' => 'app8'],
					['applicationid' => 9, 'hostid' => 1, 'name' => 'app9'],
					['applicationid' => 10, 'hostid' => 1, 'name' => 'app10'],
					['applicationid' => 11, 'hostid' => 2, 'name' => 'app1'],
					['applicationid' => 12, 'hostid' => 2, 'name' => 'app2'],
					['applicationid' => 13, 'hostid' => 2, 'name' => 'app3'],
					['applicationid' => 14, 'hostid' => 2, 'name' => 'app4'],
					['applicationid' => 15, 'hostid' => 2, 'name' => 'app5'],
					['applicationid' => 16, 'hostid' => 3, 'name' => 'app1'],
					['applicationid' => 17, 'hostid' => 3, 'name' => 'app2'],
					['applicationid' => 18, 'hostid' => 3, 'name' => 'app3'],
					['applicationid' => 19, 'hostid' => 3, 'name' => 'app4'],
					['applicationid' => 20, 'hostid' => 3, 'name' => 'app5']
				],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']]],
				[
					['applicationid' => 1, 'hostid' => 1, 'name' => 'app1'],
					['applicationid' => 2, 'hostid' => 1, 'name' => 'app2'],
					['applicationid' => 3, 'hostid' => 1, 'name' => 'app3'],
					['applicationid' => 4, 'hostid' => 1, 'name' => 'app4'],
					['applicationid' => 5, 'hostid' => 1, 'name' => 'app5'],
					['applicationid' => 6, 'hostid' => 1, 'name' => 'app6'],
					['applicationid' => 7, 'hostid' => 1, 'name' => 'app7'],
					['applicationid' => 8, 'hostid' => 1, 'name' => 'app8'],
					['applicationid' => 9, 'hostid' => 1, 'name' => 'app9'],
					['applicationid' => 10, 'hostid' => 1, 'name' => 'app10'],
					['applicationid' => 11, 'hostid' => 2, 'name' => 'app1'],
					['applicationid' => 12, 'hostid' => 2, 'name' => 'app2'],
					['applicationid' => 13, 'hostid' => 2, 'name' => 'app3'],
					['applicationid' => 14, 'hostid' => 2, 'name' => 'app4'],
					['applicationid' => 15, 'hostid' => 2, 'name' => 'app5'],
					['applicationid' => 16, 'hostid' => 3, 'name' => 'app1'],
					['applicationid' => 17, 'hostid' => 3, 'name' => 'app2'],
					['applicationid' => 18, 'hostid' => 3, 'name' => 'app3'],
					['applicationid' => 19, 'hostid' => 3, 'name' => 'app4'],
					['applicationid' => 20, 'hostid' => 3, 'name' => 'app5'],
					['applicationid' => 21, 'hostid' => 1, 'name' => 'app1']
				],
				'/',
				false,
				'Invalid parameter "/21": value (hostid, name)=(1, app1) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']]],
				[
					['applicationid' => 1, 'hostid' => 1, 'name' => 'app1'],
					['applicationid' => 2, 'hostid' => 1, 'name' => 'app2'],
					['applicationid' => 3, 'hostid' => 1, 'name' => 'app3'],
					['applicationid' => 4, 'hostid' => 1, 'name' => 'app4'],
					['applicationid' => 5, 'hostid' => 1, 'name' => 'app5'],
					['applicationid' => 6, 'hostid' => 1, 'name' => 'app6'],
					['applicationid' => 7, 'hostid' => 1, 'name' => 'app7'],
					['applicationid' => 8, 'hostid' => 1, 'name' => 'app8'],
					['applicationid' => 9, 'hostid' => 1, 'name' => 'app9'],
					['applicationid' => 10, 'hostid' => 1, 'name' => 'app10'],
					['applicationid' => 11, 'hostid' => 2, 'name' => 'app1'],
					['applicationid' => 12, 'hostid' => 2, 'name' => 'app2'],
					['applicationid' => 13, 'hostid' => 2, 'name' => 'app3'],
					['applicationid' => 14, 'hostid' => 2, 'name' => 'app4'],
					['applicationid' => 15, 'hostid' => 2, 'name' => 'app5'],
					['applicationid' => 16, 'hostid' => 3, 'name' => 'app1'],
					['applicationid' => 17, 'hostid' => 3, 'name' => 'app2'],
					['applicationid' => 18, 'hostid' => 3, 'name' => 'app3'],
					['applicationid' => 19, 'hostid' => 3, 'name' => 'app4'],
					['applicationid' => 1, 'hostid' => 3, 'name' => 'app5']
				],
				'/',
				false,
				'Invalid parameter "/20": value (applicationid)=(1) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['name']]],
				[
					['name' => 'app1'],
					['name' => 'app2'],
					['name' => 'app3'],
					['name' => 'app4'],
					['name' => 'app5'],
					['name' => 'app6'],
					['name' => 'app7'],
					['name' => 'app8'],
					['name' => 'app9'],
					[],
					['name' => 'app10'],
					['name' => 'app11'],
					['name' => 'app12'],
					[],
					[],
					[],
					['name' => 'app13'],
					['name' => 'app14'],
					['name' => 'app15'],
					['name' => 'app16'],
					['name' => 'app17'],
					['name' => 'app18'],
					['name' => 'app19'],
					['name' => 'app1']
				],
				'/',
				false,
				'Invalid parameter "/24": value (name)=(app1) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['hostid', 'name']]],
				[
					['hostid' => 1, 'name' => 'app1'],
					['hostid' => 1, 'name' => 'app2'],
					['hostid' => 1],
					['hostid' => 1],
					['hostid' => 1],
					['hostid' => 1, 'name' => 'app6'],
					['hostid' => 1, 'name' => 'app7'],
					['name' => 'app8'],
					['name' => 'app9'],
					['name' => 'app10'],
					['name' => 'app1'],
					['name' => 'app2'],
					['name' => 'app3'],
					['name' => 'app4'],
					['name' => 'app5'],
					['hostid' => 3],
					['hostid' => 3],
					['hostid' => 3],
					['hostid' => 3],
					['hostid' => 1, 'name' => 'app1']
				],
				'/',
				false,
				'Invalid parameter "/20": value (hostid, name)=(1, app1) already exists.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'tags' => ['type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
						'tag'		=> ['type' => API_STRING_UTF8],
						'operator'	=> ['type' => API_INT32],
						'value'		=> ['type' => API_STRING_UTF8]
					]]
				]],
				[
					'tags' => [
						['tag' => 'tag', 'operator' => 0, 'value' => ''],
						['tag' => 'tag', 'operator' => 0, 'value' => '']
					]
				],
				'/',
				false,
				'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 0, ) already exists.'
			]
		];
	}

	/**
	 * @dataProvider dataProviderUniqueness
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param bool   $rc_exprected
	 * @param mixed  $error_exprected
	 */
	public function testApiUniqueness(array $rule, $data, $path, $rc_expected, $error_expected) {
		$rc = CApiInputValidator::validateUniqueness($rule, $data, $path, $error);

		$this->assertSame(gettype($rc_expected), gettype($rc));
		$this->assertSame(gettype($error_expected), gettype($error));
		$this->assertSame($rc_expected, $rc);
		$this->assertSame($error_expected, $error);
	}
}

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


use PHPUnit\Framework\TestCase;

class CApiInputValidatorTest extends TestCase {

	protected $default_timezone;

	protected function setUp(): void {
		$settings = $this->createMock(CSettings::class);
		$settings->method('get')
			->will($this->returnValue([
				CSettingsHelper::VALIDATE_URI_SCHEMES => '1',
				CSettingsHelper::URI_VALID_SCHEMES => 'http,https,ftp,file,mailto,tel,ssh'
			]));

		$instances_map = [
			['settings', $settings]
		];
		$api_service_factory = $this->createMock('CApiServiceFactory');
		$api_service_factory->method('getObject')
			->will($this->returnValueMap($instances_map));

		API::setApiServiceFactory($api_service_factory);

		$this->default_timezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	protected function tearDown(): void {
		date_default_timezone_set($this->default_timezone);
	}

	public function dataProviderInput() {
		return [
			[
				['type' => API_CALC_FORMULA],
				'last(//agent.ping) = 1 or "text" = {$MACRO}',
				'/1/formula',
				'last(//agent.ping) = 1 or "text" = {$MACRO}'
			],
			[
				['type' => API_CALC_FORMULA, 'flags' => API_ALLOW_LLD_MACRO],
				'last(//agent.ping) = 1 or "text" = {#LLD}',
				'/1/formula',
				'last(//agent.ping) = 1 or "text" = {#LLD}'
			],
			[
				['type' => API_CALC_FORMULA],
				'10+sum(/*/counter?[tag="test:1" and group="test-hosts"],1m)',
				'/1/formula',
				'Invalid parameter "/1/formula": invalid first parameter in function "sum".'
			],
			[
				['type' => API_CALC_FORMULA],
				'10+sum(/host/*?[tag="test:1" and group="test-hosts"],1m)',
				'/1/formula',
				'Invalid parameter "/1/formula": invalid first parameter in function "sum".'
			],
			[
				['type' => API_CALC_FORMULA],
				'max(1, max(2, max(3, max(4, max(5, max(6, max(7, max(8, max(9, max(10, max(11, max(12, max(13, max(14, max(15, max(16, max(17, max(18, max(19, max(20, max(21, max(22, max(23, max(24, max(25, max(26, max(27, max(28, max(29, max(30, max(31, max(32, 33))))))))))))))))))))))))))))))))',
				'/1/formula',
				'max(1, max(2, max(3, max(4, max(5, max(6, max(7, max(8, max(9, max(10, max(11, max(12, max(13, max(14, max(15, max(16, max(17, max(18, max(19, max(20, max(21, max(22, max(23, max(24, max(25, max(26, max(27, max(28, max(29, max(30, max(31, max(32, 33))))))))))))))))))))))))))))))))'
			],
			[
				['type' => API_CALC_FORMULA],
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))',
				'/1/formula',
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))'
			],
			[
				['type' => API_CALC_FORMULA],
				'sum(last_foreach(/*/*[/,total]?[group="Any host and item is prohibited"]))',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect expression starting from "sum(last_foreach(/*/*[/,total]?[group="Any host and item is prohibited"]))".'
			],
			[
				['type' => API_CALC_FORMULA],
				'sum(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"])) + last_foreach(/host/key)',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect usage of function "last_foreach".'
			],
			[
				['type' => API_CALC_FORMULA],
				'avg(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))',
				'/1/formula',
				'avg(last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"]))'
			],
			[
				['type' => API_CALC_FORMULA],
				'last_foreach(/*/vfs.fs.size[/,total]?[group="MySQL Servers"])',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect usage of function "last_foreach".'
			],
			[
				['type' => API_CALC_FORMULA],
				'last(//agent.ping) = 1 or "text" = {#LLD}',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect expression starting from "{#LLD}".'
			],
			[
				['type' => API_CALC_FORMULA],
				'max(1, max(2, max(3, max(4, max(5, max(6, max(7, max(8, max(9, max(10, max(11, max(12, max(13, max(14, max(15, max(16, max(17, max(18, max(19, max(20, max(21, max(22, max(23, max(24, max(25, max(26, max(27, max(28, max(29, max(30, max(31, max(32, max(33, 1)))))))))))))))))))))))))))))))))',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect expression starting from "max(1, max(2, max(3, max(4, max(5, max(6, max(7, max(8, max(9, max(10, max(11, max(12, max(13, max(14, max(15, max(16, max(17, max(18, max(19, max(20, max(21, max(22, max(23, max(24, max(25, max(26, max(27, max(28, max(29, max(30, max(31, max(32, max(33, 1)))))))))))))))))))))))))))))))))".'
			],
			[
				['type' => API_CALC_FORMULA],
				'',
				'/1/formula',
				'Invalid parameter "/1/formula": cannot be empty.'
			],
			[
				['type' => API_CALC_FORMULA],
				[],
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_CALC_FORMULA],
				true,
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_CALC_FORMULA],
				null,
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_CALC_FORMULA],
				// broken UTF-8 byte sequence
				"\xd1".'12345',
				'/1/formula',
				'Invalid parameter "/1/formula": invalid byte sequence in UTF-8.'
			],
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
				['type' => API_COLOR],
				'COLOR',
				'/1/color',
				'Invalid parameter "/1/color": a hexadecimal color code (6 symbols) is expected.'
			],
			[
				['type' => API_COLORS],
				['ffffff'],
				'/1/colors',
				['ffffff']
			],
			[
				['type' => API_COLORS],
				['037ACF'],
				'/1/colors',
				['037ACF']
			],
			[
				['type' => API_COLORS],
				['000000'],
				'/1/colors',
				['000000']
			],
			[
				['type' => API_COLORS],
				[],
				'/1/colors',
				[]
			],
			[
				['type' => API_COLORS],
				['ffffff', '037ACF', '000000'],
				'/1/colors',
				['ffffff', '037ACF', '000000']
			],
			[
				['type' => API_COLORS, 'flags' => API_NOT_EMPTY],
				[],
				'/1/colors',
				'Invalid parameter "/1/colors": cannot be empty.'
			],
			[
				['type' => API_COLORS],
				'',
				'/1/colors',
				'Invalid parameter "/1/colors": an array is expected.'
			],
			[
				['type' => API_COLORS],
				true,
				'/1/colors',
				'Invalid parameter "/1/colors": an array is expected.'
			],
			[
				['type' => API_COLORS],
				null,
				'/1/colors',
				'Invalid parameter "/1/colors": an array is expected.'
			],
			[
				['type' => API_COLORS],
				// broken UTF-8 byte sequence
				["\xd1".'12345'],
				'/1/colors',
				'Invalid parameter "/1/colors/1": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_COLORS],
				['COLOR'],
				'/1/colors',
				'Invalid parameter "/1/colors/1": a hexadecimal color code (6 symbols) is expected.'
			],
			[
				['type' => API_COLORS],
				['000000', 'WRONG'],
				'/1/colors',
				'Invalid parameter "/1/colors/2": a hexadecimal color code (6 symbols) is expected.'
			],
			[
				['type' => API_COND_FORMULA],
				'A and B',
				'/1/formula',
				'A and B'
			],
			[
				['type' => API_COND_FORMULA],
				'(A and B) or C',
				'/1/formula',
				'(A and B) or C'
			],
			[
				['type' => API_COND_FORMULA],
				'A and',
				'/1/formula',
				'Invalid parameter "/1/formula": incorrect syntax near "d".'
			],
			[
				['type' => API_COND_FORMULA],
				'',
				'/1/formula',
				'Invalid parameter "/1/formula": cannot be empty.'
			],
			[
				['type' => API_COND_FORMULA],
				[],
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULA],
				true,
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULA],
				null,
				'/1/formula',
				'Invalid parameter "/1/formula": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULA],
				// broken UTF-8 byte sequence
				"\xd1".'12345',
				'/1/formula',
				'Invalid parameter "/1/formula": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_COND_FORMULAID],
				'A',
				'/1/formulaid',
				'A'
			],
			[
				['type' => API_COND_FORMULAID],
				'ABCD',
				'/1/formulaid',
				'ABCD'
			],
			[
				['type' => API_COND_FORMULAID],
				'Ab',
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": uppercase identifier expected.'
			],
			[
				['type' => API_COND_FORMULAID],
				'',
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": cannot be empty.'
			],
			[
				['type' => API_COND_FORMULAID],
				[],
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULAID],
				true,
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULAID],
				null,
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": a character string is expected.'
			],
			[
				['type' => API_COND_FORMULAID],
				// broken UTF-8 byte sequence
				"\xd1".'12345',
				'/1/formulaid',
				'Invalid parameter "/1/formulaid": invalid byte sequence in UTF-8.'
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
				'Invalid parameter "/1/name": value must be one of "xml", "json".'
			],
			[
				['type' => API_STRING_UTF8, 'in' => '\\,,.'],
				',',
				'/1/name',
				','
			],
			[
				['type' => API_STRING_UTF8, 'in' => ''],
				'abc',
				'/output',
				'Invalid parameter "/output": value must be empty.'
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
				'Invalid parameter "/output/2": value must be one of "hostid", "name".'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'hostid,name', 'uniq' => true],
				['hostid', 'name', 'name'],
				'/output',
				'Invalid parameter "/output/3": value (name) already exists.'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => '\\,,.,/,'],
				[',', '.', '/', ''],
				'/output',
				[',', '.', '/', '']
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => '\\,,.,/,'],
				['abc', '.', '/', ''],
				'/output',
				'Invalid parameter "/output/1": value must be empty or one of ",", ".", "/".'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => ''],
				['abc'],
				'/output',
				'Invalid parameter "/output/1": value must be empty.'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'a'],
				['abc'],
				'/output',
				'Invalid parameter "/output/1": value must be "a".'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'a,b'],
				['abc'],
				'/output',
				'Invalid parameter "/output/1": value must be one of "a", "b".'
			],
			[
				['type' => API_STRINGS_UTF8, 'in' => 'a,b,'],
				['abc'],
				'/output',
				'Invalid parameter "/output/1": value must be empty or one of "a", "b".'
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
				'9223372036854775808',
				'/1/int',
				'Invalid parameter "/1/int": a number is too large.'
			],
			[
				['type' => API_INT32],
				9223372036854775808,
				'/1/int',
				'Invalid parameter "/1/int": an integer is expected.'
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
				['type' => API_INT32_RANGES],
				null,
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": a character string is expected.'
			],
			[
				['type' => API_INT32_RANGES],
				[],
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": a character string is expected.'
			],
			[
				['type' => API_INT32_RANGES],
				'',
				'/1/int32_ranges',
				''
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_NOT_EMPTY],
				'',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": cannot be empty.'
			],
			[
				['type' => API_INT32_RANGES],
				'123',
				'/1/int32_ranges',
				'123'
			],
			[
				['type' => API_INT32_RANGES],
				'-123',
				'/1/int32_ranges',
				'-123'
			],
			[
				['type' => API_INT32_RANGES],
				'123.00',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": invalid range expression.'
			],
			[
				['type' => API_INT32_RANGES, 'length' => 5],
				'12-34',
				'/1/int32_ranges',
				'12-34'
			],
			[
				['type' => API_INT32_RANGES, 'length' => 5],
				'12-345',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": value is too long.'
			],
			[
				['type' => API_INT32_RANGES],
				'10-20,30-40',
				'/1/int32_ranges',
				'10-20,30-40'
			],
			[
				['type' => API_INT32_RANGES],
				'10.00-20.00',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": invalid range expression.'
			],
			[
				['type' => API_INT32_RANGES, 'in' => '0:50'],
				'10-20,30-40',
				'/1/int32_ranges',
				'10-20,30-40'
			],
			[
				['type' => API_INT32_RANGES, 'in' => '20:30'],
				'10-20,30-40',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": value must be one of 20-30.'
			],
			[
				['type' => API_INT32_RANGES],
				'{$MACRO}',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": invalid range expression.'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/int32_ranges',
				'{$MACRO}'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO1}-{$MACRO2}',
				'/1/int32_ranges',
				'{$MACRO1}-{$MACRO2}'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO, 'in' => '20:30'],
				'{$MACRO}-20,30-40',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": value must be one of 20-30.'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_USER_MACRO, 'in' => '20:40'],
				'{$MACRO}-20,30-40',
				'/1/int32_ranges',
				'{$MACRO}-20,30-40'
			],
			[
				['type' => API_INT32_RANGES],
				'{#LLD}',
				'/1/int32_ranges',
				'Invalid parameter "/1/int32_ranges": invalid range expression.'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD}',
				'/1/int32_ranges',
				'{#LLD}'
			],
			[
				['type' => API_INT32_RANGES, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD1}-{#LLD2}',
				'/1/int32_ranges',
				'{#LLD1}-{#LLD2}'
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
				18446744073709551616,
				'/1/int',
				'Invalid parameter "/1/int": an unsigned integer is expected.'
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
				1.23E+11
			],
			[
				['type' => API_FLOAT],
				'1.23E+11',
				'/1/float',
				1.23E+11
			],
			[
				['type' => API_FLOAT],
				'1.23e+11',
				'/1/float',
				1.23E+11
			],
			[
				['type' => API_FLOAT],
				'-1.23e+11',
				'/1/float',
				-1.23E+11
			],
			[
				['type' => API_FLOAT],
				'.23E11',
				'/1/float',
				0.23E+11
			],
			[
				['type' => API_FLOAT, 'in' => '0.5,1,1.5,2'],
				'0.5',
				'/1/float',
				0.5
			],
			[
				['type' => API_FLOAT, 'in' => '0.5,1,1.5,2'],
				0.5,
				'/1/float',
				0.5
			],
			[
				['type' => API_FLOAT, 'in' => '0.5,1,1.5,2'],
				1,
				'/1/float',
				1.0
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				1,
				'/1/float',
				1.0
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				'1.3',
				'/1/float',
				1.3
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				'0.5',
				'/1/float',
				0.5
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				'3.5',
				'/1/float',
				3.5
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				'0',
				'/1/float',
				'Invalid parameter "/1/float": value must be within the range of 0.5-3.5.'
			],
			[
				['type' => API_FLOAT, 'in' => '0.5:3.5'],
				4.5,
				'/1/float',
				'Invalid parameter "/1/float": value must be within the range of 0.5-3.5.'
			],
			[
				['type' => API_FLOAT, 'in' => '-123,1,1.5,2'],
				'-123',
				'/1/float',
				-123.0
			],
			[
				['type' => API_FLOAT],
				'{$MACRO}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT],
				'{#LLD_MACRO}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				108.108,
				'/1/float',
				108.108
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'108.108',
				'/1/float',
				108.108
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/float',
				'{$MACRO}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{{$MACRO}.func()}',
				'/1/float',
				'{{$MACRO}.func()}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO: with context}',
				'/1/float',
				'{$MACRO: with context}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{{$MACRO: with context}.func()}',
				'/1/float',
				'{{$MACRO: with context}.func()}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'Simple string',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}{$MACRO2}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_USER_MACRO],
				'{#LLD_MACRO}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				108.108,
				'/1/float',
				108.108
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'108.108',
				'/1/float',
				108.108
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}',
				'/1/float',
				'{#LLD_MACRO}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}',
				'/1/float',
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'Simple string',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}{#LLD_MACRO2}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_FLOAT, 'flags' => API_ALLOW_LLD_MACRO],
				'{$MACRO}',
				'/1/float',
				'Invalid parameter "/1/float": a floating point value is expected.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'from' => ['type' => API_FLOAT],
					'to' => ['type' => API_FLOAT, 'compare' => ['operator' => '>', 'field' => 'from']]
				]],
				[
					'from' => '107',
					'to' => '108'
				],
				'/',
				[
					'from' => 107.0,
					'to' => 108.0
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'from' => ['type' => API_FLOAT],
					'to' => ['type' => API_FLOAT, 'compare' => ['operator' => '>', 'field' => 'from']]
				]],
				[
					'from' => '108',
					'to' => '108.000108'
				],
				'/',
				[
					'from' => 108.0,
					'to' => 108.000108
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'from' => ['type' => API_FLOAT],
					'to' => ['type' => API_FLOAT, 'compare' => ['operator' => '>', 'field' => 'from']]
				]],
				[
					'from' => '108',
					'to' => '108'
				],
				'/',
				'Invalid parameter "/to": cannot be less than or equal to the value of parameter "/from".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'from' => ['type' => API_FLOAT],
					'to' => ['type' => API_FLOAT, 'compare' => ['operator' => '>', 'field' => 'from']]
				]],
				[
					'from' => 108.0,
					'to' => 108.0
				],
				'/',
				'Invalid parameter "/to": cannot be less than or equal to the value of parameter "/from".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'from' => ['type' => API_FLOAT],
					'to' => ['type' => API_FLOAT, 'compare' => ['operator' => '>', 'field' => 'from']]
				]],
				[
					'from' => '108.001',
					'to' => '108.0'
				],
				'/',
				'Invalid parameter "/to": cannot be less than or equal to the value of parameter "/from".'
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
				['type' => API_ID],
				'9223372036854775808',
				'/1/id',
				'Invalid parameter "/1/id": a number is too large.'
			],
			[
				['type' => API_ID],
				9223372036854775808,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
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
				['type' => API_ID, 'in' => '0'],
				0,
				'/1/id',
				'0'
			],
			[
				['type' => API_ID, 'in' => '0'],
				'0',
				'/1/id',
				'0'
			],
			[
				['type' => API_ID, 'in' => '0'],
				'000000',
				'/1/id',
				'0'
			],
			[
				['type' => API_ID, 'in' => '0'],
				true,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				null,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				[],
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				0.0,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				1.23E+11,
				'/1/id',
				'Invalid parameter "/1/id": a number is expected.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				108,
				'/1/id',
				'Invalid parameter "/1/id": value must be 0.'
			],
			[
				['type' => API_ID, 'in' => '0'],
				'108',
				'/1/id',
				'Invalid parameter "/1/id": value must be 0.'
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
				['type' => API_BOOLEAN],
				true,
				'/1/id',
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
				['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL],
				null,
				'/1/createMissing',
				null
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
				'Invalid parameter "/": should be empty.'
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
				['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'host' => ['type' => API_STRING_UTF8]
				]],
				[
					'host' => 'Zabbix server',
					'name' => 'Zabbix server'
				],
				'/',
				[
					'host' => 'Zabbix server',
					'name' => 'Zabbix server'
				]
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
					'host' => ['type' => API_ANY],
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
					'host' => ['type' => API_ANY],
					'name' => ['type' => API_STRING_UTF8]
				]],
				[
					'host' => 'Zabbix server',
					'name' => 'Zabbix server'
				],
				'/',
				[
					'host' => 'Zabbix server',
					'name' => 'Zabbix server'
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'uuid' => ['type' => API_STRING_UTF8, 'in' => '', 'unset' => true],
					'name' => ['type' => API_STRING_UTF8]
				]],
				[
					'uuid' => '',
					'name' => 'Zabbix server'
				],
				'/',
				[
					'name' => 'Zabbix server'
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'uuid' => ['type' => API_STRING_UTF8, 'in' => '', 'unset' => true],
					'name' => ['type' => API_STRING_UTF8]
				]],
				[
					'uuid' => '56079badd056419383cc26e6a4fcc7e0',
					'name' => 'Zabbix server'
				],
				'/',
				'Invalid parameter "/uuid": value must be empty.'
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
				['type' => API_OBJECT, 'fields' => [
					'roles' => ['type' => API_OBJECT, 'default' => [], 'fields' => [
						'value' => ['type' => API_STRING_UTF8, 'default' => 'test']
					]]
				]],
				[],
				'/',
				[
					'roles' => [
						'value' => 'test'
					]
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>	['type' => API_ID],
					'host' =>	['type'=> API_STRING_UTF8],
					'ruleid' =>	['type' => API_UNEXPECTED]
				]],
				[
					'hostid' => '10428',
					'host' => 'Abc host'
				],
				'/',
				[
					'hostid' => '10428',
					'host' => 'Abc host'
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>	['type' => API_ID],
					'host' =>	['type'=> API_STRING_UTF8],
					'ruleid' =>	['type' => API_UNEXPECTED]
				]],
				[
					'hostid' => '10428',
					'ruleid' => '12345'
				],
				'/',
				'Invalid parameter "/": unexpected parameter "ruleid".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>	['type' => API_ID],
					'host' =>	['type'=> API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
				]],
				[
					'hostid' => '10428',
					'host' => 'Abcd host'
				],
				'/',
				'Invalid parameter "/": cannot update readonly parameter "host" of inherited object.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>	['type' => API_ID],
					'host' =>	['type'=> API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
				]],
				[
					'hostid' => '10428',
					'host' => 'Abcd host'
				],
				'/',
				'Invalid parameter "/": cannot update readonly parameter "host" of discovered object.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>				['type' => API_ID],
					'custom_interface' =>	['type'=> API_INT32, 'flags' => API_REQUIRED, 'in' => '0,1'],
					'interface_ip' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'custom_interface', 'in' => '1'], 'type' => API_IP],
												['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					'hostid' => '10428',
					'custom_interface' => '1',
					'interface_ip' => '127.0.0.1'
				],
				'/',
				[
					'hostid' => '10428',
					'custom_interface' => 1,
					'interface_ip' => '127.0.0.1'
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>				['type' => API_ID],
					'custom_interface' =>	['type'=> API_INT32, 'flags' => API_REQUIRED, 'in' => '0,1'],
					'interface_ip' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'custom_interface', 'in' => '1'], 'type' => API_IP],
												['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					'hostid' => '10428',
					'custom_interface' => '0',
					'interface_ip' => '127.0.0.1'
				],
				'/',
				'Invalid parameter "/": unexpected parameter "interface_ip".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>				['type' => API_ID],
					'custom_interface' =>	['type'=> API_INT32, 'flags' => API_REQUIRED, 'in' => '0,1'],
					'interface_ip' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'custom_interface', 'in' => '1'], 'type' => API_IP],
												['else' => true, 'type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
					]]
				]],
				[
					'hostid' => '10428',
					'custom_interface' => '0',
					'interface_ip' => '127.0.0.1'
				],
				'/',
				'Invalid parameter "/": cannot update readonly parameter "interface_ip" of inherited object.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'hostid' =>				['type' => API_ID],
					'custom_interface' =>	['type'=> API_INT32, 'flags' => API_REQUIRED, 'in' => '0,1'],
					'interface_ip' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'custom_interface', 'in' => '1'], 'type' => API_IP],
												['else' => true, 'type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
					]]
				]],
				[
					'hostid' => '10428',
					'custom_interface' => '0',
					'interface_ip' => '127.0.0.1'
				],
				'/',
				'Invalid parameter "/": cannot update readonly parameter "interface_ip" of discovered object.'
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
				['type' => API_IDS],
				[0, 1, 2, 3, '4', '9223372036854775807', 9223372036854775808],
				'/',
				'Invalid parameter "/7": a number is expected.'
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
				['type' => API_OBJECTS, 'length' => 0],
				[],
				'/',
				[]
			],
			[
				['type' => API_OBJECTS, 'length' => 0],
				'object',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_OBJECTS, 'length' => 0],
				[[]],
				'/',
				'Invalid parameter "/": should be empty.'
			],
			[
				['type' => API_OBJECTS, 'length' => 0],
				[['field1' => 'value1']],
				'/',
				'Invalid parameter "/": should be empty.'
			],
			[
				['type' => API_OBJECTS, 'length' => 0],
				[[], [], []],
				'/',
				'Invalid parameter "/": should be empty.'
			],
			[
				['type' => API_OBJECTS, 'length' => 2, 'fields' => []],
				[[], [], []],
				'/',
				'Invalid parameter "/": maximum number of array elements is 2.'
			],
			[
				['type' => API_OBJECTS, 'length' => 3, 'fields' => []],
				[[], [], []],
				'/',
				[[], [], []]
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
				'Invalid parameter "/1": should be empty.'
			],
			[
				['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
				[['host' => 'Zabbix server']],
				'/',
				[['host' => 'Zabbix server']]
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
					'col' => ['type' => API_INT32, 'default' => '0'],
					'row' => ['type' => API_INT32, 'default' => '1'],
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
						'tag'	=> ['type' => API_STRING_UTF8]
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
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32],
						['if' => ['field' => 'type', 'in' => '3,4'], 'type' => API_STRING_UTF8],
						['else' => true, 'type' => API_BOOLEAN]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => '125'],
					['type' => '3', 'value' => 'text'],
					['type' => '4', 'value' => 'text3'],
					['type' => '7', 'value' => true]
				],
				'/',
				[
					['type' => 1, 'value' => -5],
					['type' => 2, 'value' => 125],
					['type' => 3, 'value' => 'text'],
					['type' => 4, 'value' => 'text3'],
					['type' => 7, 'value' => true]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'flags' => API_REQUIRED, 'type' => API_INT32],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => '125'],
					['type' => '7', 'value' => '123']
				],
				'/',
				'Invalid parameter "/3": unexpected parameter "value".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'flags' => API_REQUIRED, 'type' => API_INT32],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2'],
					['type' => '7']
				],
				'/',
				'Invalid parameter "/2": the parameter "value" is missing.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'flags' => API_REQUIRED, 'type' => API_INT32],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1', 'value' => '-5'],
					['type' => '2', 'value' => '125'],
					['type' => '7']
				],
				'/',
				[
					['type' => 1, 'value' => -5],
					['type' => 2, 'value' => 125],
					['type' => 7]
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
				'Incorrect API_MULTIPLE validation rules.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32]
					]]
				]],
				[
					['type' => '1'],
					['type' => '2']
				],
				'/',
				[
					['type' => 1],
					['type' => 2]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'default' => '5', 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32]
					]]
				]],
				[
					['type' => '1'],
					['type' => '2']
				],
				'/',
				[
					['type' => 1, 'value' => 5],
					['type' => 2, 'value' => 5]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32, 'flags' => API_REQUIRED]
					]]
				]],
				[
					['type' => '1']
				],
				'/',
				'Invalid parameter "/1": the parameter "value" is missing.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'flags' => API_REQUIRED]
					]]
				]],
				[
					['type' => '1'],
					['type' => '3']
				],
				'/',
				'Invalid parameter "/2": the parameter "value" is missing.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
					]]
				]],
				[
					['type' => '3']
				],
				'/',
				'Invalid parameter "/1": the parameter "value" is missing.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
					]]
				]],
				[
					['type' => '3', 'value' => '']
				],
				'/',
				'Invalid parameter "/1/value": cannot be empty.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'default' => 1, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1,2'], 'type' => API_INT32],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'default' => 'def']
					]]
				]],
				[
					['type' => '1'],
					['type' => '2', 'value' => '125'],
					['type' => '3']
				],
				'/',
				[
					['type' => 1, 'value' => 1],
					['type' => 2, 'value' => 125],
					['type' => 3, 'value' => 'def']
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_STRING_UTF8, 'in' => 'a,b'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'in' => '']
					]]
				]],
				[
					['type' => '1', 'value' => 'a'],
					['type' => '2', 'value' => 'b'],
					['type' => '3', 'value' => 'c']
				],
				'/',
				'Invalid parameter "/3/value": value must be empty.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_STRING_UTF8, 'in' => 'a,'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'in' => 'c']
					]]
				]],
				[
					['type' => '1', 'value' => 'a'],
					['type' => '2', 'value' => 'b'],
					['type' => '3', 'value' => 'c']
				],
				'/',
				'Invalid parameter "/2/value": value must be empty or "a".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_STRING_UTF8, 'in' => 'a,b'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'in' => 'd,e,f']
					]]
				]],
				[
					['type' => '1', 'value' => 'a'],
					['type' => '2', 'value' => 'b'],
					['type' => '3', 'value' => 'c']
				],
				'/',
				'Invalid parameter "/3/value": value must be one of "d", "e", "f".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_STRING_UTF8, 'in' => 'a,b'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_STRING_UTF8, 'in' => 'd,e,f,']
					]]
				]],
				[
					['type' => '1', 'value' => 'a'],
					['type' => '2', 'value' => 'b'],
					['type' => '3', 'value' => 'c']
				],
				'/',
				'Invalid parameter "/3/value": value must be empty or one of "d", "e", "f".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_INT32, 'in' => '1'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'in' => '2,3']
					]]
				]],
				[
					['type' => '1', 'value' => '1'],
					['type' => '2', 'value' => '2'],
					['type' => '3', 'value' => '3']
				],
				'/',
				'Invalid parameter "/2/value": value must be 1.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:9'],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '1:2'], 'type' => API_INT32, 'in' => '1,2'],
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'in' => '4:6']
					]]
				]],
				[
					['type' => '1', 'value' => '1'],
					['type' => '2', 'value' => '2'],
					['type' => '3', 'value' => '3']
				],
				'/',
				'Invalid parameter "/3/value": value must be one of 4-6.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'in' => '1:3', 'flags' => API_REQUIRED],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INT32, 'in' => '1,2'],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1'],
					['type' => '1', 'level' => '1', 'value' => '1']
				],
				'/',
				'Invalid parameter "/2": unexpected parameter "level".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'in' => '1:3', 'flags' => API_REQUIRED],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INT32, 'in' => '1,2'],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1'],
					['type' => '3', 'level' => '1', 'value' => '1']
				],
				'/',
				'Invalid parameter "/2": unexpected parameter "value".'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'in' => '1:3', 'flags' => API_REQUIRED],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INT32, 'in' => '1,2'],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '1'],
					['type' => '3', 'level' => '1'],
					['type' => '3', 'level' => '2', 'value' => '1']
				],
				'/',
				[
					['type' => 1],
					['type' => 3, 'level' => 1],
					['type' => 3, 'level' => 2, 'value' => 1]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'host' =>	['type' => API_H_NAME, 'flags' => API_REQUIRED],
					'name' =>	['type' => API_STRING_UTF8, 'default_source' => 'host']
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
				['type' => API_OBJECTS, 'flags' => API_PRESERVE_KEYS, 'fields' => []],
				[
					5 => [],
					102 => []
				],
				'/',
				[
					5 => [],
					102 => []
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => []],
				[
					5 => [],
					102 => []
				],
				'/',
				[
					[],
					[]
				]
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'hostid' =>	['type' => API_ID],
					'host' =>	['type'=> API_STRING_UTF8],
					'ruleid' =>	['type' => API_UNEXPECTED]
				]],
				[
					[
						'hostid' => '10428',
						'host' => 'Abc host'
					],
					[
						'hostid' => '10428',
						'ruleid' => '12345'
					]
				],
				'/',
				'Invalid parameter "/2": unexpected parameter "ruleid".'
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
				'-1.23E+500',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'1.23E+500',
				'/1/numeric',
				'Invalid parameter "/1/numeric": a number is too large.'
			],
			[
				['type' => API_NUMERIC],
				'.124',
				'/1/numeric',
				'0.124'
			],
			[
				['type' => API_NUMERIC],
				'-.124',
				'/1/numeric',
				'-0.124'
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
				'8388608T'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				[],
				'/1/menu_path',
				'Invalid parameter "/1/menu_path": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				true,
				'/1/menu_path',
				'Invalid parameter "/1/menu_path": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				null,
				'/1/menu_path',
				'Invalid parameter "/1/menu_path": a character string is expected.'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'folder1/'.'/folder2',
				'/1/menu_path',
				'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'',
				'/1/menu_path',
				''
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/',
				'/1/menu_path',
				'/'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/folder1/\/'.'/',
				'/1/menu_path',
				'/folder1/\/'.'/'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'folder1/',
				'/1/menu_path',
				'folder1/'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/folder1',
				'/1/menu_path',
				'/folder1'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/folder1/',
				'/1/menu_path',
				'/folder1/'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/folder1/folder2',
				'/1/menu_path',
				'/folder1/folder2'
			],
			[
				['type' => API_SCRIPT_MENU_PATH],
				'/folder1/folder2/',
				'/1/menu_path',
				'/folder1/folder2/'
			],
			[
				['type' => API_USER_MACROS],
				[],
				'/macros',
				[]
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO}', '{$MACRO: "context"}', '{$MACRO:regex:"regular expression"}'],
				'/macros',
				['{$MACRO}', '{$MACRO: "context"}', '{$MACRO:regex:"regular expression"}']
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE],
				'{$MACRO}',
				'/macros',
				['{$MACRO}']
			],
			[
				['type' => API_USER_MACROS, 'length' => 8],
				['{$MACRO}'],
				'/macros',
				['{$MACRO}']
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE, 'length' => 8],
				'{$MACRO}',
				'/macros',
				['{$MACRO}']
			],
			[
				['type' => API_USER_MACROS],
				'',
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS],
				true,
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS],
				null,
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS],
				'{$MACRO}',
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE],
				'',
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE],
				true,
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE],
				null,
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE],
				'abcdefg',
				'/macros',
				'Invalid parameter "/macros": an array is expected.'
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO}', ''],
				'/macros',
				'Invalid parameter "/macros/2": cannot be empty.'
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO}', '{$MACRo}'],
				'/macros',
				'Invalid parameter "/macros/2": incorrect syntax near "o}".'
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO}', '{$MACRO2'],
				'/macros',
				'Invalid parameter "/macros/2": unexpected end of macro.'
			],
			[
				['type' => API_USER_MACROS, 'length' => 8],
				['{$MACRO}', '{$MACRO2}'],
				'/macros',
				'Invalid parameter "/macros/2": value is too long.'
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
				['type' => API_USER_MACRO],
				'',
				'/1/macro',
				'Invalid parameter "/1/macro": cannot be empty.'
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
				'Invalid parameter "/1/macro": incorrect syntax near "o}".'
			],
			[
				['type' => API_USER_MACRO],
				'{$MACRO} ',
				'/1/macro',
				'Invalid parameter "/1/macro": incorrect syntax near " ".'
			],
			[
				['type' => API_USER_MACRO],
				'{$MACRO: "context"',
				'/1/macro',
				'Invalid parameter "/1/macro": unexpected end of macro.'
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
				'Invalid parameter "/1/expression": invalid regular expression.'
			],
			[
				['type' => API_REGEX],
				'@[a-z',
				'/1/expression',
				'Invalid parameter "/1/expression": invalid regular expression.'
			],
			[
				['type' => API_REGEX],
				'@[a-z]',
				'/1/expression',
				'@[a-z]'
			],
			[
				['type' => API_REGEX, 'flags' => API_ALLOW_GLOBAL_REGEX],
				'invalid [(regexp])',
				'/1/expression',
				'Invalid parameter "/1/expression": invalid regular expression.'
			],
			[
				['type' => API_REGEX],
				'Server: nginx\/(.+(?<!\r))',
				'/1/expression',
				'Server: nginx\/(.+(?<!\r))'
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
				['type' => API_REGEX, 'flags' => API_ALLOW_GLOBAL_REGEX],
				'@valid global regexp name [(regexp])',
				'/1/expression',
				'@valid global regexp name [(regexp])'
			],
			[
				['type' => API_REGEX, 'flags' => API_ALLOW_GLOBAL_REGEX],
				'/valid regexp',
				'/1/expression',
				'/valid regexp'
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
				['type' => API_TIME_UNIT, 'length' => 3],
				'15s',
				'/1/time_unit',
				'15s'
			],
			[
				['type' => API_TIME_UNIT, 'length' => 2],
				'15s',
				'/1/time_unit',
				'Invalid parameter "/1/time_unit": value is too long.'
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
				'Invalid parameter "/output": value must be "extend".'
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
				'Invalid parameter "/output": value must be "extend".'
			],
			[
				['type' => API_OUTPUT, 'flags' => API_ALLOW_COUNT],
				'',
				'/output',
				'Invalid parameter "/output": value must be one of "extend", "count".'
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
				'Invalid parameter "/output/2": value must be one of "hostid", "name".'
			],
			[
				['type' => API_OUTPUT, 'in' => 'hostid,name'],
				['hostid', 'name', 'name'],
				'/output',
				'Invalid parameter "/output/3": value (name) already exists.'
			],
			[
				['type' => API_PSK],
				'0123456789abcdef0123456789abcdef',
				'/psk',
				'0123456789abcdef0123456789abcdef'
			],
			[
				['type' => API_PSK],
				'0123456789abcdef0123456789abcde',
				'/psk',
				'Invalid parameter "/psk": minimum length is 32 characters.'
			],
			[
				['type' => API_PSK],
				'xyz',
				'/psk',
				'Invalid parameter "/psk": minimum length is 32 characters.'
			],
			[
				['type' => API_PSK],
				'0123456789abcdef0123456789abcd',
				'/psk',
				'Invalid parameter "/psk": minimum length is 32 characters.'
			],
			[
				['type' => API_PSK],
				'',
				'/psk',
				''
			],
			[
				['type' => API_PSK, 'flags' => API_NOT_EMPTY],
				'',
				'/psk',
				'Invalid parameter "/psk": cannot be empty.'
			],
			[
				['type' => API_PSK],
				[],
				'/psk',
				'Invalid parameter "/psk": a character string is expected.'
			],
			[
				['type' => API_PSK],
				true,
				'/psk',
				'Invalid parameter "/psk": a character string is expected.'
			],
			[
				['type' => API_PSK],
				123,
				'/psk',
				'Invalid parameter "/psk": a character string is expected.'
			],
			[
				['type' => API_PSK],
				123.5,
				'/psk',
				'Invalid parameter "/psk": a character string is expected.'
			],
			[
				['type' => API_PSK],
				null,
				'/psk',
				'Invalid parameter "/psk": a character string is expected.'
			],
			[
				['type' => API_PSK],
				// broken UTF-8 byte sequence
				'abc'."\xd1".'e',
				'/psk',
				'Invalid parameter "/psk": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_SORTORDER],
				null,
				'/sortorder',
				'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			[
				['type' => API_SORTORDER],
				[],
				'/sortorder',
				[]
			],
			[
				['type' => API_SORTORDER],
				'DESC',
				'/sortorder',
				'DESC'
			],
			[
				['type' => API_SORTORDER],
				'ASC',
				'/sortorder',
				'ASC'
			],
			[
				['type' => API_SORTORDER],
				['ASC'],
				'/sortorder',
				['ASC']
			],
			[
				['type' => API_SORTORDER],
				['DESC'],
				'count',
				['DESC']
			],
			[
				['type' => API_SORTORDER],
				['ASC', 'ASC', 'DESC', 'DESC'],
				'/sortorder',
				['ASC', 'ASC', 'DESC', 'DESC']
			],
			[
				['type' => API_SORTORDER],
				'',
				'/sortorder',
				'Invalid parameter "/sortorder": value must be one of "ASC", "DESC".'
			],
			[
				['type' => API_SORTORDER],
				['asc'],
				'/sortorder',
				'Invalid parameter "/sortorder/1": value must be one of "ASC", "DESC".'
			],
			[
				['type' => API_SORTORDER],
				true,
				'/sortorder',
				'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			[
				['type' => API_SORTORDER],
				123,
				'/sortorder',
				'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			[
				['type' => API_SORTORDER],
				123.5,
				'/sortorder',
				'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			[
				['type' => API_SORTORDER],
				['DESC', []],
				'/sortorder',
				'Invalid parameter "/sortorder/2": a character string is expected.'
			],
			[
				['type' => API_SORTORDER],
				// broken UTF-8 byte sequence
				'abc'."\xd1".'e',
				'/sortorder',
				'Invalid parameter "/sortorder": invalid byte sequence in UTF-8.'
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
				['type' => API_URL, 'length' => 10],
				'zabbix.php',
				'/1/url',
				'zabbix.php'
			],
			[
				['type' => API_URL, 'length' => 8],
				'zabbix.php',
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
				'/chart_bar.php?a=1&b=2'
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
			],
			[
				['type' => API_URL, 'flags' => API_ALLOW_EVENT_TAGS_MACRO],
				'text{EVENT.TAGS."JIRAID"}text',
				'/1/url',
				'text{EVENT.TAGS."JIRAID"}text'
			],
			[
				['type' => API_URL, 'flags' => API_ALLOW_MANUALINPUT_MACRO],
				'text{MANUALINPUT}text',
				'/1/url',
				'text{MANUALINPUT}text'
			],
			[
				['type' => API_IP],
				'',
				'/1/ip',
				''
			],
			[
				['type' => API_IP, 'flags' => API_NOT_EMPTY],
				'',
				'/1/ip',
				'Invalid parameter "/1/ip": cannot be empty.'
			],
			[
				['type' => API_IP],
				[],
				'/1/ip',
				'Invalid parameter "/1/ip": a character string is expected.'
			],
			[
				['type' => API_IP],
				true,
				'/1/ip',
				'Invalid parameter "/1/ip": a character string is expected.'
			],
			[
				['type' => API_IP],
				null,
				'/1/ip',
				'Invalid parameter "/1/ip": a character string is expected.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO],
				// broken UTF-8 byte sequence
				'{$MACRO: "'."\xd1".'"}',
				'/1/ip',
				'Invalid parameter "/1/ip": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO: "context"}',
				'/1/ip',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_IP],
				'0.0.0.x',
				'/1/ip',
				'Invalid parameter "/1/ip": an IP address is expected.'
			],
			[
				['type' => API_IP],
				'1.1.1.1',
				'/1/ip',
				'1.1.1.1'
			],
			[
				['type' => API_IP, 'length' => 11],
				'192.168.3.5',
				'/1/ip',
				'192.168.3.5'
			],
			[
				['type' => API_IP, 'length' => 10],
				'192.168.3.5',
				'/1/ip',
				'Invalid parameter "/1/ip": value is too long.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO],
				'{$}',
				'/1/ip',
				'Invalid parameter "/1/ip": an IP address is expected.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO1}',
				'/1/ip',
				'{$MACRO1}'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_LLD_MACRO],
				'{#}',
				'/1/ip',
				'Invalid parameter "/1/ip": an IP address is expected.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO1}',
				'/1/ip',
				'{#MACRO1}'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_MACRO],
				'{HOST.IP}',
				'/1/ip',
				'{HOST.IP}'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_MACRO],
				'{$MACRO}',
				'/1/ip',
				'Invalid parameter "/1/ip": an IP address is expected.'
			],
			[
				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO],
				'{HOST.HOST}',
				'/1/ip',
				'Invalid parameter "/1/ip": an IP address is expected.'
			],
			[
				['type' => API_IP_RANGES],
				'',
				'/1/ip_range',
				''
			],
			[
				['type' => API_IP_RANGES, 'flags' => API_NOT_EMPTY],
				'',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": cannot be empty.'
			],
			[
				['type' => API_IP_RANGES],
				[],
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": a character string is expected.'
			],
			[
				['type' => API_IP_RANGES],
				true,
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": a character string is expected.'
			],
			[
				['type' => API_IP_RANGES],
				null,
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": a character string is expected.'
			],
			[
				['type' => API_IP_RANGES],
				'0.0.0;0',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "0.0.0;0".'
			],
			[
				['type' => API_IP_RANGES],
				'1.1.1.1',
				'/1/ip_range',
				'1.1.1.1'
			],
			[
				['type' => API_IP_RANGES, 'length' => 11],
				'192.168.3.5',
				'/1/ip_range',
				'192.168.3.5'
			],
			[
				['type' => API_IP_RANGES, 'length' => 10],
				'192.168.3.5',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": value is too long.'
			],
			[
				['type' => API_IP_RANGES],
				'192.168.3.5,192.168.6.240',
				'/1/ip_range',
				'192.168.3.5,192.168.6.240'
			],
			[
				['type' => API_IP_RANGES],
				'www.example.com',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "www.example.com".'
			],
			[
				['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS],
				'www.example.com',
				'/1/ip_range',
				'www.example.com'
			],
			[
				['type' => API_IP_RANGES],
				'192.168.3.5,192.168.6.1-240',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "192.168.6.1-240".'
			],
			[
				['type' => API_IP_RANGES, 'flags' => API_ALLOW_RANGE],
				'192.168.3.5,192.168.6.1-240',
				'/1/ip_range',
				'192.168.3.5,192.168.6.1-240'
			],
			[
				['type' => API_IP_RANGES],
				'{$MACRO}',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "{$MACRO}".'
			],
			[
				['type' => API_IP_RANGES, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/ip_range',
				'{$MACRO}'
			],
			[
				['type' => API_IP_RANGES],
				'{HOST.IP}',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "{HOST.IP}".'
			],
			[
				['type' => API_IP_RANGES, 'macros' => true],
				'{HOST.IP}',
				'/1/ip_range',
				'{HOST.IP}'
			],
			[
				['type' => API_IP_RANGES, 'macros' => ['{HOST.IP}']],
				'{HOST.DNS}',
				'/1/ip_range',
				'Invalid parameter "/1/ip_range": incorrect address starting from "{HOST.DNS}".'
			],
			[
				['type' => API_IP_RANGES, 'macros' => ['{HOST.IP}']],
				'{HOST.IP}',
				'/1/ip_range',
				'{HOST.IP}'
			],
			[
				['type' => API_DNS],
				'',
				'/1/dns',
				''
			],
			[
				['type' => API_DNS, 'flags' => API_NOT_EMPTY],
				'',
				'/1/dns',
				'Invalid parameter "/1/dns": cannot be empty.'
			],
			[
				['type' => API_DNS],
				[],
				'/1/dns',
				'Invalid parameter "/1/dns": a character string is expected.'
			],
			[
				['type' => API_DNS],
				true,
				'/1/dns',
				'Invalid parameter "/1/dns": a character string is expected.'
			],
			[
				['type' => API_DNS],
				null,
				'/1/dns',
				'Invalid parameter "/1/dns": a character string is expected.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO],
				// broken UTF-8 byte sequence
				'{$MACRO: "'."\xd1".'"}',
				'/1/dns',
				'Invalid parameter "/1/dns": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO: "context"}',
				'/1/dns',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_DNS],
				'%%%',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_DNS],
				'3.3.3.3',
				'/1/dns',
				'3.3.3.3'
			],
			[
				['type' => API_DNS, 'length' => 15],
				'www.example.com',
				'/1/dns',
				'www.example.com'
			],
			[
				['type' => API_DNS, 'length' => 14],
				'www.example.com',
				'/1/dns',
				'Invalid parameter "/1/dns": value is too long.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO],
				'{$}',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO2}',
				'/1/dns',
				'{$MACRO2}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#}',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO2}',
				'/1/dns',
				'{#MACRO2}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO3}{#MACRO4}',
				'/1/dns',
				'{#MACRO3}{#MACRO4}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_MACRO],
				'{HOST.IP}',
				'/1/dns',
				'{HOST.IP}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO3}{$MACRO4}',
				'/1/dns',
				'{$MACRO3}{$MACRO4}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_MACRO],
				'{$MACRO}',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO],
				'{HOST.HOST}',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO],
				'a{HOST.HOST}b{$MACRO5}c{#MACRO5}d{HOST.NAME}e{$MACRO6}',
				'/1/dns',
				'a{HOST.HOST}b{$MACRO5}c{#MACRO5}d{HOST.NAME}e{$MACRO6}'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_MACRO],
				'a{HOST.HOST}b{HOST.IP}c',
				'/1/dns',
				'a{HOST.HOST}b{HOST.IP}c'
			],
			[
				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO],
				'a{$MACRO7}b{#MACRO6}c{HOST.NAME}d{$MACRO8}',
				'/1/dns',
				'Invalid parameter "/1/dns": a DNS name is expected.'
			],
			[
				['type' => API_PORT],
				'',
				'/1/port',
				''
			],
			[
				['type' => API_PORT, 'flags' => API_NOT_EMPTY],
				'',
				'/1/port',
				'Invalid parameter "/1/port": cannot be empty.'
			],
			[
				['type' => API_PORT],
				[],
				'/1/port',
				'Invalid parameter "/1/port": a number is expected.'
			],
			[
				['type' => API_PORT],
				true,
				'/1/port',
				'Invalid parameter "/1/port": a number is expected.'
			],
			[
				['type' => API_PORT],
				null,
				'/1/port',
				'Invalid parameter "/1/port": a number is expected.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				// broken UTF-8 byte sequence
				'{$MACRO: "'."\xd1".'"}',
				'/1/port',
				'Invalid parameter "/1/port": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO: "context"}',
				'/1/port',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_PORT],
				false,
				'/1/port',
				'Invalid parameter "/1/port": a number is expected.'
			],
			[
				['type' => API_PORT],
				'123',
				'/1/port',
				'123'
			],
			[
				['type' => API_PORT],
				456,
				'/1/port',
				'456'
			],
			[
				['type' => API_PORT, 'length' => 5],
				'65535',
				'/1/port',
				'65535'
			],
			[
				['type' => API_PORT, 'length' => 4],
				'65535',
				'/1/port',
				'Invalid parameter "/1/port": value is too long.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{$}',
				'/1/port',
				'Invalid parameter "/1/port": an integer is expected.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO9}',
				'/1/port',
				'{$MACRO9}'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{{$MACRO9}.func()}',
				'/1/port',
				'{{$MACRO9}.func()}'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{{$MACRO9: context}.func()}',
				'/1/port',
				'{{$MACRO9: context}.func()}'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#}',
				'/1/port',
				'Invalid parameter "/1/port": an integer is expected.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO7}',
				'/1/port',
				'{#MACRO7}'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_LLD_MACRO],
				'{{#MACRO7}.func()}',
				'/1/port',
				'{{#MACRO7}.func()}'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO10}{$MACRO11}',
				'/1/port',
				'Invalid parameter "/1/port": an integer is expected.'
			],
			[
				['type' => API_PORT, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO8}{#MACRO9}',
				'/1/port',
				'Invalid parameter "/1/port": an integer is expected.'
			],
			[
				['type' => API_PORT],
				'-1',
				'/1/port',
				'Invalid parameter "/1/port": value must be one of 0-65535.'
			],
			[
				['type' => API_PORT],
				'9999999999',
				'/1/port',
				'Invalid parameter "/1/port": a number is too large.'
			],
			[
				['type' => API_PORT],
				'65536',
				'/1/port',
				'Invalid parameter "/1/port": value must be one of 0-65535.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				null,
				'/1/expression',
				'Invalid parameter "/1/expression": a character string is expected.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION, 'flags' => API_NOT_EMPTY],
				'',
				'/1/expression',
				'Invalid parameter "/1/expression": cannot be empty.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				[],
				'/1/expression',
				'Invalid parameter "/1/expression": a character string is expected.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION, 'length' => 10],
				'last(/host/item) = 0',
				'/1/expression',
				'Invalid parameter "/1/expression": value is too long.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'last(/host/item = 0',
				'/1/expression',
				'Invalid parameter "/1/expression": incorrect expression starting from "last(/host/item = 0".'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'9 and 1',
				'/1/expression',
				'Invalid parameter "/1/expression": trigger expression must contain at least one /host/key reference.'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'last(/host/item) = {#LLD_MACRO}',
				'/1/expression',
				'Invalid parameter "/1/expression": incorrect expression starting from "{#LLD_MACRO}".'
			],
			[
				['type' => API_TRIGGER_EXPRESSION, 'flags' => API_ALLOW_LLD_MACRO],
				'last(/host/item) = {#LLD_MACRO}',
				'/1/expression',
				'last(/host/item) = {#LLD_MACRO}'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'last(/host/item) = 0',
				'/1/expression',
				'last(/host/item) = 0'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'last(/host/item) = {$USER_MACRO}',
				'/1/expression',
				'last(/host/item) = {$USER_MACRO}'
			],
			[
				['type' => API_TRIGGER_EXPRESSION],
				'',
				'/1/expression',
				''
			],
			[
				['type' => API_EVENT_NAME],
				null,
				'/1/event_name',
				'Invalid parameter "/1/event_name": a character string is expected.'
			],
			[
				['type' => API_EVENT_NAME],
				[],
				'/1/event_name',
				'Invalid parameter "/1/event_name": a character string is expected.'
			],
			[
				['type' => API_EVENT_NAME, 'length' => 10],
				'12345678901',
				'/1/event_name',
				'Invalid parameter "/1/event_name": value is too long.'
			],
			[
				['type' => API_EVENT_NAME, 'length' => 10],
				'1234567890',
				'/1/event_name',
				'1234567890'
			],
			[
				['type' => API_EVENT_NAME],
				'event name {?{host:item.last() = 0}',
				'/1/event_name',
				'Invalid parameter "/1/event_name": incorrect expression starting from "{host:item.last() = 0}".'
			],
			[
				['type' => API_EVENT_NAME],
				'event name {?9 and 1}',
				'/1/event_name',
				'event name {?9 and 1}'
			],
			[
				['type' => API_EVENT_NAME],
				'event name {?last(/host/item) = 0}',
				'/1/event_name',
				'event name {?last(/host/item) = 0}'
			],
			[
				['type' => API_EVENT_NAME],
				'event name {?last(/host/item) = {$USER_MACRO}}',
				'/1/event_name',
				'event name {?last(/host/item) = {$USER_MACRO}}'
			],
			[
				['type' => API_EVENT_NAME],
				'',
				'/1/event_name',
				''
			],
			[
				['type' => API_EVENT_NAME],
				'{FUNCTION.VALUE} {FUNCTION.VALUE1} {FUNCTION.RECOVERY.VALUE} {FUNCTION.RECOVERY.VALUE9}',
				'/1/event_name',
				'{FUNCTION.VALUE} {FUNCTION.VALUE1} {FUNCTION.RECOVERY.VALUE} {FUNCTION.RECOVERY.VALUE9}'
			],
			[
				['type' => API_EVENT_NAME],
				'{?{FUNCTION.VALUE} - {FUNCTION.VALUE1} - {FUNCTION.RECOVERY.VALUE} - {FUNCTION.RECOVERY.VALUE9}}',
				'/1/event_name',
				'{?{FUNCTION.VALUE} - {FUNCTION.VALUE1} - {FUNCTION.RECOVERY.VALUE} - {FUNCTION.RECOVERY.VALUE9}}'
			],
			[
				['type' => API_EVENT_NAME],
				'{?func({FUNCTION.VALUE},{FUNCTION.VALUE1},{FUNCTION.RECOVERY.VALUE},{FUNCTION.RECOVERY.VALUE9})}',
				'/1/event_name',
				'{?func({FUNCTION.VALUE},{FUNCTION.VALUE1},{FUNCTION.RECOVERY.VALUE},{FUNCTION.RECOVERY.VALUE9})}'
			],
			[
				['type' => API_JSON],
				null,
				'/1/json',
				'Invalid parameter "/1/json": a character string is expected.'
			],
			[
				['type' => API_JSON],
				true,
				'/1/json',
				'Invalid parameter "/1/json": a character string is expected.'
			],
			[
				['type' => API_JSON],
				[],
				'/1/json',
				'Invalid parameter "/1/json": a character string is expected.'
			],
			[
				['type' => API_JSON],
				123,
				'/1/json',
				'Invalid parameter "/1/json": a character string is expected.'
			],
			[
				['type' => API_JSON],
				'123',
				'/1/json',
				'123'
			],
			[
				['type' => API_JSON],
				'',
				'/1/json',
				''
			],
			[
				['type' => API_JSON, 'flags' => API_NOT_EMPTY],
				'',
				'/1/json',
				'Invalid parameter "/1/json": cannot be empty.'
			],
			[
				['type' => API_JSON],
				'{}',
				'/1/json',
				'{}'
			],
			[
				['type' => API_JSON],
				'{"key": "value"}',
				'/1/json',
				'{"key": "value"}'
			],
			[
				['type' => API_JSON],
				'{"key": false}',
				'/1/json',
				'{"key": false}'
			],
			[
				['type' => API_JSON],
				'{"key": null}',
				'/1/json',
				'{"key": null}'
			],
			[
				['type' => API_JSON],
				'{"key": NaN}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'length' => 15],
				'{"key": "value"}',
				'/1/json',
				'Invalid parameter "/1/json": value is too long.'
			],
			[
				['type' => API_JSON],
				'abc',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON],
				'{"key": value}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON],
				'{"key": 123}',
				'/1/json',
				'{"key": 123}'
			],
			[
				['type' => API_JSON],
				'{$MACRO}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/json',
				'{$MACRO}'
			],
			[
				['type' => API_JSON],
				'{"key": {$MACRO}}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'flags' => API_ALLOW_USER_MACRO],
				'{"key": {$MACRO}}',
				'/1/json',
				'{"key": {$MACRO}}'
			],
			[
				['type' => API_JSON],
				'{#LLD}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD}',
				'/1/json',
				'{#LLD}'
			],
			[
				['type' => API_JSON],
				'{"key": {#LLD}}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'flags' => API_ALLOW_LLD_MACRO],
				'{"key": {#LLD}}',
				'/1/json',
				'{"key": {#LLD}}'
			],
			[
				['type' => API_JSON],
				'{HOST.IP}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'macros_n' => ['{HOST.IP}']],
				'{HOST.IP}',
				'/1/json',
				'{HOST.IP}'
			],
			[
				['type' => API_JSON, 'macros_n' => ['{HOST.IP}']],
				'{HOST.IP2}',
				'/1/json',
				'{HOST.IP2}'
			],
			[
				['type' => API_JSON],
				'{"key": {HOST.IP}}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON, 'macros_n' => ['{HOST.IP}']],
				'{"key1": {HOST.IP1}, "key2": {HOST.IP2}}',
				'/1/json',
				'{"key1": {HOST.IP1}, "key2": {HOST.IP2}}'
			],
			[
				['type' => API_JSON],
				'[]',
				'/1/json',
				'[]'
			],
			[
				['type' => API_JSON],
				'[[]]',
				'/1/json',
				'[[]]'
			],
			[
				['type' => API_JSON],
				'[[], []]',
				'/1/json',
				'[[], []]'
			],
			[
				['type' => API_JSON],
				'[[1]]',
				'/1/json',
				'[[1]]'
			],
			[
				['type' => API_JSON],
				'[1, 2, 3]',
				'/1/json',
				'[1, 2, 3]'
			],
			[
				['type' => API_JSON],
				'[[true]]',
				'/1/json',
				'[[true]]'
			],
			[
				['type' => API_JSON],
				'[[null]]',
				'/1/json',
				'[[null]]'
			],
			[
				['type' => API_JSON],
				'{null: "value"}',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],
			[
				['type' => API_JSON],
				'[{"key": "value"}]',
				'/1/json',
				'[{"key": "value"}]'
			],
			[
				['type' => API_JSON],
				'[{"key": "value"}, {"key": "value"}]',
				'/1/json',
				'[{"key": "value"}, {"key": "value"}]'
			],
			[
				['type' => API_JSON],
				'["key": "value"]',
				'/1/json',
				'Invalid parameter "/1/json": JSON is expected.'
			],

			[
				['type' => API_JSONRPC_PARAMS],
				[],
				'/params',
				[]
			],
			[
				['type' => API_JSONRPC_PARAMS],
				'',
				'/params',
				'Invalid parameter "/params": an array or object is expected.'
			],
			[
				['type' => API_JSONRPC_PARAMS],
				1,
				'/params',
				'Invalid parameter "/params": an array or object is expected.'
			],
			[
				['type' => API_JSONRPC_PARAMS],
				true,
				'/params',
				'Invalid parameter "/params": an array or object is expected.'
			],
			[
				['type' => API_JSONRPC_PARAMS],
				'23',
				'/params',
				'Invalid parameter "/params": an array or object is expected.'
			],
			[
				['type' => API_JSONRPC_PARAMS],
				null,
				'/params',
				'Invalid parameter "/params": an array or object is expected.'
			],
			[
				['type' => API_JSONRPC_ID],
				[],
				'/id',
				'Invalid parameter "/id": a string, number or null value is expected.'
			],
			[
				['type' => API_JSONRPC_ID],
				'id',
				'/id',
				'id'
			],
			[
				['type' => API_JSONRPC_ID],
				1,
				'/id',
				1
			],
			[
				['type' => API_JSONRPC_ID],
				true,
				'/id',
				'Invalid parameter "/id": a string, number or null value is expected.'
			],
			[
				['type' => API_JSONRPC_ID],
				'23',
				'/id',
				'23'
			],
			[
				['type' => API_JSONRPC_ID],
				null,
				'/id',
				null
			],
			[
				['type' => API_DATE],
				null,
				'/1/date',
				'Invalid parameter "/1/date": a character string is expected.'
			],
			[
				['type' => API_DATE],
				'',
				'/1/date',
				''
			],
			[
				['type' => API_DATE, 'flags' => API_NOT_EMPTY],
				'',
				'/1/date',
				'Invalid parameter "/1/date": cannot be empty.'
			],
			[
				['type' => API_DATE],
				[],
				'/1/date',
				'Invalid parameter "/1/date": a character string is expected.'
			],
			[
				['type' => API_DATE],
				true,
				'/1/date',
				'Invalid parameter "/1/date": a character string is expected.'
			],
			[
				['type' => API_DATE],
				false,
				'/1/date',
				'Invalid parameter "/1/date": a character string is expected.'
			],
			[
				['type' => API_DATE],
				'aaa',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'123',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				456,
				'/1/date',
				'Invalid parameter "/1/date": a character string is expected.'
			],
			[
				['type' => API_DATE],
				'01-01-2000',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'01-2000-01',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'2000-99-01',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'2000-01-99',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'2000-01-31',
				'/1/date',
				'2000-01-31'
			],
			[
				['type' => API_DATE],
				'2000-02-29',
				'/1/date',
				'2000-02-29'
			],
			[
				['type' => API_DATE],
				'2001-02-29',
				'/1/date',
				'Invalid parameter "/1/date": a date in YYYY-MM-DD format is expected.'
			],
			[
				['type' => API_DATE],
				'1900-01-01',
				'/1/date',
				'Invalid parameter "/1/date": value must be between "1970-01-01" and "2038-01-18".'
			],
			[
				['type' => API_DATE],
				'1970-01-01',
				'/1/date',
				'1970-01-01'
			],
			[
				['type' => API_DATE],
				'2100-01-01',
				'/1/date',
				'Invalid parameter "/1/date": value must be between "1970-01-01" and "2038-01-18".'
			],
			[
				['type' => API_DATE],
				'2038-01-18',
				'/1/date',
				'2038-01-18'
			],
			[
				['type' => API_NUMERIC_RANGES],
				null,
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": a character string is expected.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				'',
				'/1/numeric_ranges',
				''
			],
			[
				['type' => API_NUMERIC_RANGES, 'flags' => API_NOT_EMPTY],
				'',
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": cannot be empty.'
			],
			[
				['type' => API_NUMERIC_RANGES, 'length' => 5],
				'12-15',
				'/1/numeric_ranges',
				'12-15'
			],
			[
				['type' => API_NUMERIC_RANGES, 'length' => 5],
				'12-150',
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": value is too long.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				[],
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": a character string is expected.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				true,
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": a character string is expected.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				false,
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": a character string is expected.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				'aaa',
				'/1/numeric_ranges',
				'Invalid parameter "/1/numeric_ranges": invalid range expression.'
			],
			[
				['type' => API_NUMERIC_RANGES],
				'123',
				'/1/numeric_ranges',
				'123'
			],
			[
				['type' => API_NUMERIC_RANGES],
				'-5',
				'/1/numeric_ranges',
				'-5'
			],
			[
				['type' => API_NUMERIC_RANGES],
				'20.0-30.0000',
				'/1/numeric_ranges',
				'20.0-30.0000'
			],
			[
				['type' => API_UUID],
				null,
				'/uuid',
				'Invalid parameter "/uuid": a character string is expected.'
			],
			[
				['type' => API_UUID],
				[],
				'/uuid',
				'Invalid parameter "/uuid": a character string is expected.'
			],
			[
				['type' => API_UUID],
				'',
				'/uuid',
				'Invalid parameter "/uuid": cannot be empty.'
			],
			[
				['type' => API_UUID],
				1,
				'/uuid',
				'Invalid parameter "/uuid": a character string is expected.'
			],
			[
				['type' => API_UUID],
				true,
				'/uuid',
				'Invalid parameter "/uuid": a character string is expected.'
			],
			[
				['type' => API_UUID],
				'23',
				'/uuid',
				'Invalid parameter "/uuid": must be 32 characters long.'
			],
			[
				['type' => API_UUID],
				'1234567890123456789012345678901234567890',
				'/uuid',
				'Invalid parameter "/uuid": must be 32 characters long.'
			],
			[
				['type' => API_UUID],
				'12345678901234567890123456789012',
				'/uuid',
				'Invalid parameter "/uuid": UUIDv4 is expected.'
			],
			[
				['type' => API_UUID],
				'2fdcb2e2995040b2bba202067f730136',
				'/uuid',
				'2fdcb2e2995040b2bba202067f730136'
			],
			[
				['type' => API_UUID],
				'2fdcb2e2-9950-40b2-bba2-02067f730136',
				'/uuid',
				'Invalid parameter "/uuid": must be 32 characters long.'
			],
			[
				['type' => API_UUID],
				'2fdcb2e2995080b2bba202067f730136',
				'/uuid',
				'Invalid parameter "/uuid": UUIDv4 is expected.'
			],
			[
				['type' => API_CUIDS],
				[],
				'/',
				[]
			],
			[
				['type' => API_CUIDS, 'flags' => API_ALLOW_NULL],
				null,
				'/',
				null
			],
			[
				['type' => API_CUIDS],
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000vld1pie3h3gj8', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000vld1pie3h3gj8', 'ckr3d7iou000uld1p4xdyp4md']
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				'ckr3d7iov0013ld1pbi9r18dg',
				'/',
				['ckr3d7iov0013ld1pbi9r18dg']
			],
			[
				['type' => API_CUIDS],
				'',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				'',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS],
				true,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				null,
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS],
				'ckr3d7iov0013ld1pbi9r18dg',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				'skr3d7iov0013ld1pbi9r18dg',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				'Ckr3d7iov0013ld1pbi9r18dg',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS, 'flags' => API_NORMALIZE],
				'ckr3d7iov0013ld1pbi9r18d',
				'/',
				'Invalid parameter "/": an array is expected.'
			],
			[
				['type' => API_CUIDS],
				['ckr3d7iou000wld1pcx24d56a', 'akr3d7iou000vld1pie3h3gj8', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/2": CUID is expected.'
			],
			[
				['type' => API_CUIDS],
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000vld1pie3h3gj8123', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/2": must be 25 characters long.'
			],
			[
				['type' => API_CUIDS],
				[1, 'ckr3d7iou000vld1pie3h3gj8123', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/1": a character string is expected.'
			],
			[
				['type' => API_CUIDS],
				[true, 'ckr3d7iou000vld1pie3h3gj8123', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/1": a character string is expected.'
			],
			[
				['type' => API_CUIDS],
				[null, 'ckr3d7iou000vld1pie3h3gj8123', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/1": a character string is expected.'
			],
			[
				['type' => API_CUIDS, 'uniq' => true],
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000vld1pie3h3gj8', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000vld1pie3h3gj8', 'ckr3d7iou000uld1p4xdyp4md']
			],
			[
				['type' => API_CUIDS, 'uniq' => true],
				['ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000wld1pcx24d56a', 'ckr3d7iou000uld1p4xdyp4md'],
				'/',
				'Invalid parameter "/2": value (ckr3d7iou000wld1pcx24d56a) already exists.'
			],
			[
				['type' => API_CUID],
				'ckr3d7iou000wld1pcx24d56a',
				'/',
				'ckr3d7iou000wld1pcx24d56a'
			],
			[
				['type' => API_CUID],
				'Skr3d7iou000wld1pcx24d56a',
				'/',
				'Invalid parameter "/": CUID is expected.'
			],
			[
				['type' => API_CUID],
				'Ckr3d7iou000wld1pcx24d56a',
				'/',
				'Invalid parameter "/": CUID is expected.'
			],
			[
				['type' => API_CUID],
				'ckr3d7iou000wld1pcx24d56a2',
				'/',
				'Invalid parameter "/": must be 25 characters long.'
			],
			[
				['type' => API_CUID],
				'ckr3d7iou000wld1pcx24d56',
				'/',
				'Invalid parameter "/": must be 25 characters long.'
			],
			[
				['type' => API_CUID],
				'ckr3d7iou000wld1pcx24d56ā',
				'/',
				'Invalid parameter "/": must be 25 characters long.'
			],
			[
				['type' => API_CUID],
				1,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_CUID],
				true,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_CUID],
				null,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_IMAGE],
				null,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_IMAGE],
				1,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_IMAGE],
				true,
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_IMAGE],
				"test",
				'/',
				'Invalid parameter "/": file format is unsupported.'
			],
			[
				['type' => API_IMAGE],
				"iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAACXBIWXMAAAigAAAIoAAXhZTqAAAAB3RJTUUH4AsMCTElZR7X8QAAAAZiS0dEAAAAAAAA+UO7fwAAAQFJREFUSMdjYBgFQxWERq5Z/e61blZoyJp17z7o+dHP4ug1q969MFINDVuz5t3bd8KhoUD63bsLQHr9uw/G6bSz2G/N2nfPjZSBPgZZLACx+P9/VPrd+dBgYMi8N06jnsWBQB8/M5LHbzEKvRrokLNAfSuADkki32KQT17qZoANJs5ibA45B3T4ynfv9ZaR7oAooA/e6CUC4xpk0E2SHQBKI+/ezwsNB0WJ/nryQyJmzfJ3r4zLoA65QZwD3s8BJtb1796bXKJeWoA4pAKvQ96+nwAM8rXvXpucpF1uQA2Rm1CLJwLLhZXvnpvsomdBtBKYRrYB43jVu1f6c0eL5iELABMWPRgtjy4PAAAALnpUWHRkYXRlOmNyZWF0ZQAAeNozMjA00zU01DU0CjGwtDKxtDI21zYwsDIwAABB6wUWx8+KcAAAAC56VFh0ZGF0ZTptb2RpZnkAAHjaMzIwNNM1NNQ1NAoxsLQysbQyNtc2MLAyMAAAQesFFu7wIvgAAABqelRYdHN2ZzpiYXNlLXVyaQAAeNoFwQEOgyAMBdAT4Z/TLLjbVCykCVBDEa7ve1Ey/wEMaphzQoJWC/p0WNdGiUH3DQlaERszbCRYoZzdJVS0Xi6xFu5Ngjvzw2778uEp0sf7ff0dfrGRXmVvI9Or/wAOAAAAAElFTkSuQmCC",
				'/',
				base64_decode("iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAACXBIWXMAAAigAAAIoAAXhZTqAAAAB3RJTUUH4AsMCTElZR7X8QAAAAZiS0dEAAAAAAAA+UO7fwAAAQFJREFUSMdjYBgFQxWERq5Z/e61blZoyJp17z7o+dHP4ug1q969MFINDVuz5t3bd8KhoUD63bsLQHr9uw/G6bSz2G/N2nfPjZSBPgZZLACx+P9/VPrd+dBgYMi8N06jnsWBQB8/M5LHbzEKvRrokLNAfSuADkki32KQT17qZoANJs5ibA45B3T4ynfv9ZaR7oAooA/e6CUC4xpk0E2SHQBKI+/ezwsNB0WJ/nryQyJmzfJ3r4zLoA65QZwD3s8BJtb1796bXKJeWoA4pAKvQ96+nwAM8rXvXpucpF1uQA2Rm1CLJwLLhZXvnpvsomdBtBKYRrYB43jVu1f6c0eL5iELABMWPRgtjy4PAAAALnpUWHRkYXRlOmNyZWF0ZQAAeNozMjA00zU01DU0CjGwtDKxtDI21zYwsDIwAABB6wUWx8+KcAAAAC56VFh0ZGF0ZTptb2RpZnkAAHjaMzIwNNM1NNQ1NAoxsLQysbQyNtc2MLAyMAAAQesFFu7wIvgAAABqelRYdHN2ZzpiYXNlLXVyaQAAeNoFwQEOgyAMBdAT4Z/TLLjbVCykCVBDEa7ve1Ey/wEMaphzQoJWC/p0WNdGiUH3DQlaERszbCRYoZzdJVS0Xi6xFu5Ngjvzw2778uEp0sf7ff0dfrGRXmVvI9Or/wAOAAAAAElFTkSuQmCC")
			],
			[
				['type' => API_EXEC_PARAMS],
				null,
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": a character string is expected.'
			],
			[
				['type' => API_EXEC_PARAMS],
				true,
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": a character string is expected.'
			],
			[
				['type' => API_EXEC_PARAMS],
				1,
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": a character string is expected.'
			],
			[
				['type' => API_EXEC_PARAMS],
				[],
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": a character string is expected.'
			],
			[
				['type' => API_EXEC_PARAMS],
				'',
				'/1/exec_params',
				''
			],
			[
				['type' => API_EXEC_PARAMS, 'flags' => API_NOT_EMPTY],
				'',
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": cannot be empty.'
			],
			[
				['type' => API_EXEC_PARAMS, 'length' => 2],
				'abc',
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": value is too long.'
			],
			[
				['type' => API_EXEC_PARAMS],
				'abc',
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": the last new line feed is missing.'
			],
			[
				['type' => API_EXEC_PARAMS],
				'ab'."\n".'c',
				'/1/exec_params',
				'Invalid parameter "/1/exec_params": the last new line feed is missing.'
			],
			[
				['type' => API_EXEC_PARAMS],
				'abc'."\n",
				'/1/exec_params',
				'abc'."\n"
			],
			[
				['type' => API_TIMESTAMP],
				0,
				'/',
				0
			],
			[
				['type' => API_TIMESTAMP],
				1234567,
				'/',
				1234567
			],
			[
				['type' => API_TIMESTAMP],
				ZBX_MAX_DATE,
				'/',
				ZBX_MAX_DATE
			],
			[
				['type' => API_TIMESTAMP],
				'01234567',
				'/',
				1234567
			],
			[
				['type' => API_TIMESTAMP],
				[],
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				true,
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				null,
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL],
				null,
				'/',
				null
			],
			[
				['type' => API_TIMESTAMP],
				'foo',
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				0.0,
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				1.23E+11,
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				'-12345',
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP],
				ZBX_MAX_DATE + 1,
				'/',
				'Invalid parameter "/": a timestamp is too large.'
			],
			[
				['type' => API_TIMESTAMP],
				'9223372036854775808',
				'/',
				'Invalid parameter "/": a timestamp is too large.'
			],
			[
				['type' => API_TIMESTAMP],
				9223372036854775808,
				'/',
				'Invalid parameter "/": an unsigned integer is expected.'
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,1,2'],
				1,
				'/',
				1
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,1,2'],
				3,
				'/',
				'Invalid parameter "/": value must be one of 1970-01-01 00:00:00, 1970-01-01 00:00:01, 1970-01-01 00:00:02.'
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				0,
				'/',
				0
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				30,
				'/',
				30
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				60,
				'/',
				60
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				90,
				'/',
				90
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				1,
				'/',
				'Invalid parameter "/": value must be one of 1970-01-01 00:00:00, 1970-01-01 00:00:30-1970-01-01 00:01:30.'
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				29,
				'/',
				'Invalid parameter "/": value must be one of 1970-01-01 00:00:00, 1970-01-01 00:00:30-1970-01-01 00:01:30.'
			],
			[
				['type' => API_TIMESTAMP, 'in' => '0,30:90'],
				91,
				'/',
				'Invalid parameter "/": value must be one of 1970-01-01 00:00:00, 1970-01-01 00:00:30-1970-01-01 00:01:30.'
			],
			[
				['type' => API_TIMESTAMP, 'format' => 'H:i', 'in' => '0,300:3600'],
				1,
				'/',
				'Invalid parameter "/": value must be one of 00:00, 00:05-01:00.'
			],
			[
				['type' => API_TIMESTAMP, 'format' => 'H:i', 'timezone' => 'UTC', 'in' => '0,300:3600'],
				1,
				'/',
				'Invalid parameter "/": value must be one of 00:00, 00:05-01:00.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'active_since' => ['type' => API_TIMESTAMP],
					'active_till' => ['type' => API_TIMESTAMP, 'compare' => ['operator' => '>', 'field' => 'active_since']]
				]],
				[
					'active_since' => '1640995200', // 2022-01-01 00:00:00
					'active_till' => '1643673599' // 2022-01-31 23:59:59
				],
				'/',
				[
					'active_since' => 1640995200, // 2022-01-01 00:00:00
					'active_till' => 1643673599 // 2022-01-31 23:59:59
				]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'active_since' => ['type' => API_TIMESTAMP],
					'active_till' => ['type' => API_TIMESTAMP, 'compare' => ['operator' => '>', 'field' => 'active_since']]
				]],
				[
					'active_since' => '1643673599', // 2022-01-31 23:59:59
					'active_till' => '1640995200' // 2022-01-01 00:00:00
				],
				'/',
				'Invalid parameter "/active_till": cannot be less than or equal to the value of parameter "/active_since".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'real_hosts' => ['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_hosts'],
					'with_hosts' => ['type' => API_BOOLEAN, 'default' => false]
				]],
				[],
				'/',
				['with_hosts' => false]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'real_hosts' => ['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_hosts'],
					'with_hosts' => ['type' => API_BOOLEAN, 'default' => false]
				]],
				['with_hosts' => true],
				'/',
				['with_hosts' => true]
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'real_hosts' =>	['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_hosts'],
					'with_hosts' =>	['type' => API_BOOLEAN, 'default' => false]
				]],
				['real_hosts' => true, 'with_hosts' => true],
				'/',
				'Deprecated parameter "/real_hosts" cannot be used with "/with_hosts".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'real_hosts' =>	['type' => API_BOOLEAN, 'flags' => API_DEPRECATED, 'replacement' => 'with_hosts'],
					'with_hosts' =>	['type' => API_BOOLEAN, 'default' => false]
				]],
				['real_hosts' => true],
				'/',
				['with_hosts' => true],
				'Parameter "/real_hosts" is deprecated.'
			],
			[
				['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['type', 'name', 'value']],
				['type' => '3', 'name2' => '2', 'value' => ['1', '2', '3', '4', '1']],
				'/',
				'Invalid parameter "/": unexpected parameter "name2".'
			],
			[
				['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['type', 'name', 'value']],
				['type' => '3', 'name' => null, 'value' => ['1', '2', '3', '4', '1']],
				'/',
				['type' => ['3'], 'name' => null, 'value' => ['1', '2', '3', '4', '1']]
			],
			[
				['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['type', 'name', 'value']],
				['type' => '3', 'name' => '2', 'value' => ['1', '2', '3', null, '1']],
				'/',
				'Invalid parameter "/value/4": a character string, integer or floating point value is expected.'
			],
			[
				['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['type', 'name', 'value']],
				['type' => 3, 'name' => 2, 'value' => ['1', 2.5, '3', '4', '1']],
				'/',
				['type' => [3], 'name' => [2], 'value' => ['1', 2.5, '3', '4', '1']]
			],
			[
				['type' => API_VALUE],
				null,
				'/1/value',
				'Invalid parameter "/1/value": a character string, integer or floating point value is expected.'
			],
			[
				['type' => API_VALUE],
				true,
				'/1/value',
				'Invalid parameter "/1/value": a character string, integer or floating point value is expected.'
			],
			[
				['type' => API_VALUE],
				[],
				'/1/value',
				'Invalid parameter "/1/value": a character string, integer or floating point value is expected.'
			],
			[
				['type' => API_VALUE],
				'',
				'/1/value',
				''
			],
			[
				['type' => API_VALUE],
				'abc',
				'/1/value',
				'abc'
			],
			[
				['type' => API_VALUE],
				1,
				'/1/value',
				1
			],
			[
				['type' => API_VALUE],
				0.5,
				'/1/value',
				0.5
			],
			[
				['type' => API_VALUE],
				// broken UTF-8 byte sequence
				"\xd1".'12345',
				'/1/value',
				'Invalid parameter "/1/value": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_ITEM_KEY],
				'key',
				'/1/item_key',
				'key'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key',
				'/1/item_key',
				'super.key'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[]',
				'/1/item_key',
				'super.key[]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[0]',
				'/1/item_key',
				'super.key[0]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[a, b, c]',
				'/1/item_key',
				'super.key[a, b, c]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[{HOST.HOST}]',
				'/1/item_key',
				'super.key[{HOST.HOST}]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[abc{HOST.HOST}def]',
				'/1/item_key',
				'super.key[abc{HOST.HOST}def]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[{#LLD_MACRO}, b, c]',
				'/1/item_key',
				'super.key[{#LLD_MACRO}, b, c]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key[{#LLD_MACRO1}abc{#LLD_MACRO2}, b, c]',
				'/1/item_key',
				'super.key[{#LLD_MACRO1}abc{#LLD_MACRO2}, b, c]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key["{#LLD_MACRO}", "b b", "c\""]',
				'/1/item_key',
				'super.key["{#LLD_MACRO}", "b b", "c\""]'
			],
			[
				['type' => API_ITEM_KEY],
				'super.key["{#LLD_MACRO1} {#LLD_MACRO2}", b, c]',
				'/1/item_key',
				'super.key["{#LLD_MACRO1} {#LLD_MACRO2}", b, c]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[{#LLD_MACRO}, b, c]',
				'/1/item_key',
				'super.key[{#LLD_MACRO}, b, c]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[a, b, c{#LLD_MACRO}d]',
				'/1/item_key',
				'super.key[a, b, c{#LLD_MACRO}d]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[{{#LLD_MACRO}.fmtnum(2)}, b, c]',
				'/1/item_key',
				'super.key[{{#LLD_MACRO}.fmtnum(2)}, b, c]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[a, b, c{{#LLD_MACRO}.fmtnum(2)}d]',
				'/1/item_key',
				'super.key[a, b, c{{#LLD_MACRO}.fmtnum(2)}d]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key["{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)}", b, c]',
				'/1/item_key',
				'super.key["{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)}", b, c]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[a, b, "{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)}"]',
				'/1/item_key',
				'super.key[a, b, "{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)}"]'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key["{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)", b, c]',
				'/1/item_key',
				'super.key["{{#LLD_MACRO}.regsub(\"(.*)_([0-9]+)\", \1)", b, c]'
			],
			[
				['type' => API_ITEM_KEY],
				null,
				'/1/item_key',
				'Invalid parameter "/1/item_key": a character string is expected.'
			],
			[
				['type' => API_ITEM_KEY],
				123,
				'/1/item_key',
				'Invalid parameter "/1/item_key": a character string is expected.'
			],
			[
				['type' => API_ITEM_KEY],
				true,
				'/1/item_key',
				'Invalid parameter "/1/item_key": a character string is expected.'
			],
			[
				['type' => API_ITEM_KEY],
				[],
				'/1/item_key',
				'Invalid parameter "/1/item_key": a character string is expected.'
			],
			[
				['type' => API_ITEM_KEY],
				'',
				'/1/item_key',
				'Invalid parameter "/1/item_key": cannot be empty.'
			],
			[
				['type' => API_ITEM_KEY, 'length' => 2],
				'key',
				'/1/item_key',
				'Invalid parameter "/1/item_key": value is too long.'
			],
			[
				['type' => API_ITEM_KEY],
				'/key',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "/key".'
			],
			[
				['type' => API_ITEM_KEY],
				'{#LLD_MACRO}',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "{#LLD_MACRO}".'
			],
			[
				['type' => API_ITEM_KEY],
				'[{#LLD_MACRO}]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "[{#LLD_MACRO}]".'
			],
			[
				['type' => API_ITEM_KEY],
				'[key',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "[key".'
			],
			[
				['type' => API_ITEM_KEY],
				'key]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "]".'
			],
			[
				['type' => API_ITEM_KEY],
				'[key]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "[key]".'
			],
			[
				['type' => API_ITEM_KEY],
				'key[',
				'/1/item_key',
				'Invalid parameter "/1/item_key": unexpected end of key.'
			],
			[
				['type' => API_ITEM_KEY],
				'key[a',
				'/1/item_key',
				'Invalid parameter "/1/item_key": unexpected end of key.'
			],
			[
				['type' => API_ITEM_KEY],
				'key[a, "]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": unexpected end of key.'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[a, b, c]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": must contain at least one low-level discovery macro.'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[a, {$MACRO}, c]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": must contain at least one low-level discovery macro.'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[{#LLD_MACRO, b, c]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": must contain at least one low-level discovery macro.'
			],
			[
				['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO],
				'super.key[{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}, b, c]',
				'/1/item_key',
				'Invalid parameter "/1/item_key": incorrect syntax near "+)", \1)}, b, c]".'
			],
			[
				['type' => API_ITEM_DELAY],
				null,
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a character string is expected.'
			],
			[
				['type' => API_ITEM_DELAY],
				'abc',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			[
				['type' => API_ITEM_DELAY],
				123,
				'/1/item_delay',
				'123'
			],
			'Delay less than zero' => [
				['type' => API_ITEM_DELAY],
				-1,
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			[
				['type' => API_ITEM_DELAY],
				'1m',
				'/1/item_delay',
				'1m'
			],
			[
				['type' => API_ITEM_DELAY, 'length' => 2],
				'10m',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": value is too long.'
			],
			'Zero delay without intervals' => [
				['type' => API_ITEM_DELAY],
				0,
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": cannot be equal to zero without custom intervals.'
			],
			'Nonsense in flexible interval' => [
				['type' => API_ITEM_DELAY],
				'0;1m/abc,10:00-18:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": incorrect syntax near ";1m/abc,10:00-18:00".'
			],
			'Nonsense in flexible period' => [
				['type' => API_ITEM_DELAY],
				'0;1m/1-7,abc',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": incorrect syntax near ";1m/1-7,abc".'
			],
			[
				['type' => API_ITEM_DELAY],
				'0;1m/1-5,10:00-18:00',
				'/1/item_delay',
				'0;1m/1-5,10:00-18:00'
			],
			'Delay too big' => [
				['type' => API_ITEM_DELAY],
				SEC_PER_DAY + 1,
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": value must be one of 0-'.SEC_PER_DAY.'.'
			],
			[
				['type' => API_ITEM_DELAY],
				'1m;30s/1-7,10:00-18:00',
				'/1/item_delay',
				'1m;30s/1-7,10:00-18:00'
			],
			[
				['type' => API_ITEM_DELAY],
				'1m;h9m/30',
				'/1/item_delay',
				'1m;h9m/30'
			],
			'No user macro flag' => [
				['type' => API_ITEM_DELAY],
				'{$MACRO}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			'User macro allowed' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/item_delay',
				'{$MACRO}'
			],
			'User macro allowed, but LLD macro entered' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'{#LLD}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			'No LLD macro flag' => [
				['type' => API_ITEM_DELAY],
				'{#LLD}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			'LLD macro allowed, but user macro entered' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_LLD_MACRO],
				'{$MACRO}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": a time unit is expected.'
			],
			[
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD}',
				'/1/item_delay',
				'{#LLD}'
			],
			'User macros in a flexible interval' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;{$M}/{$M}',
				'/1/item_delay',
				'0;{$M}/{$M}'
			],
			'User macros in a scheduled interval' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;{$M}',
				'/1/item_delay',
				'0;{$M}'
			],
			'Zero delay and blocking zero-interval' => [
				['type' => API_ITEM_DELAY],
				'0;50s/1-6,09:00-18:00;0/1-6,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Zero delay and multiple combined blocking zero-intervals' => [
				['type' => API_ITEM_DELAY],
				'0;50s/1-6,09:00-18:00;0/1-3,00:00-24:00;0/4-7,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": non-active intervals cannot fill the entire time.'
			],
			'Non-convertible due to macro in period' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;50s/1-6,09:00-18:00;0/1-5,00:00-24:00;0/{$M}',
				'/1/item_delay',
				'0;50s/1-6,09:00-18:00;0/1-5,00:00-24:00;0/{$M}'
			],
			'Non-zero delay, but whole week consists of blocking interval' => [
				['type' => API_ITEM_DELAY],
				'1m;0/1-7,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": non-active intervals cannot fill the entire time.'
			],
			'Non-zero delay, but whole week combined of blocking intervals' => [
				['type' => API_ITEM_DELAY],
				'1m;0/1-4,00:00-24:00;0/3-7,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": non-active intervals cannot fill the entire time.'
			],
			'Macro used, but delay and intervals are all zero-blocking' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;0/1-6,09:00-12:00;0/{$M}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have at least one interval greater than 0.'
			],
			'Macro in Period, but zero-week block' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;0/1-7,00:00-24:00;1/{$M}',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": non-active intervals cannot fill the entire time.'
			],
			'Macro in Interval, but zero-week block' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'0;0/1-7,00:00-24:00;{$M}/1-7,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": non-active intervals cannot fill the entire time.'
			],
			'Non-zero delay, macro in Interval' => [
				['type' => API_ITEM_DELAY, 'flags' => API_ALLOW_USER_MACRO],
				'1m;{$M}/1-4,00:00-24:00;0/3-7,00:00-23:00',
				'/1/item_delay',
				'1m;{$M}/1-4,00:00-24:00;0/3-7,00:00-23:00'
			],
			'Polling overlapped by zero-interval as a whole' => [
				['type' => API_ITEM_DELAY],
				'5m;5m/2-4,00:00-24:00;0/1-7,00:00-23:57',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Polling chunk overlapped by zero-interval as a whole, but has another active interval' => [
				['type' => API_ITEM_DELAY],
				'1m;50s/2-4,00:00-24:00;0/1-5,00:00-24:00;50s/6,09:30-12:00',
				'/1/item_delay',
				'1m;50s/2-4,00:00-24:00;0/1-5,00:00-24:00;50s/6,09:30-12:00'
			],
			'Overlap by zero-interval, but polling window available before' => [
				['type' => API_ITEM_DELAY],
				'1m;50s/1-6,09:00-12:00;0/2-6,00:00-24:00',
				'/1/item_delay',
				'1m;50s/1-6,09:00-12:00;0/2-6,00:00-24:00'
			],
			'Zero-interval, but polling active outside' => [
				['type' => API_ITEM_DELAY],
				'1m;50s/1-6,09:00-12:00;0/2-3,00:00-24:00',
				'/1/item_delay',
				'1m;50s/1-6,09:00-12:00;0/2-3,00:00-24:00'
			],
			'Polling window available between side-overlapping zero chunks' => [
				['type' => API_ITEM_DELAY],
				'1m;50s/1-6,09:00-12:00;0/1-3,00:00-24:00;0/5-6,00:00-24:00',
				'/1/item_delay',
				'1m;50s/1-6,09:00-12:00;0/1-3,00:00-24:00;0/5-6,00:00-24:00'
			],
			'Polling window available too small for interval' => [
				['type' => API_ITEM_DELAY],
				'0;2h/1-6,09:00-12:00;0/1-6,09:00-10:30',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Polling window available equal to interval' => [
				['type' => API_ITEM_DELAY],
				'0;90m/1-6,09:00-12:00;0/1-6,09:00-10:30',
				'/1/item_delay',
				'0;90m/1-6,09:00-12:00;0/1-6,09:00-10:30'
			],
			'Polling window just less than available interval (90m-1s)' => [
				['type' => API_ITEM_DELAY],
				'0;5399/1-6,09:00-12:00;0/1-6,09:00-10:30',
				'/1/item_delay',
				'0;5399/1-6,09:00-12:00;0/1-6,09:00-10:30'
			],
			'Polling available via delay, before zero-blocks' => [
				['type' => API_ITEM_DELAY],
				'5m;0/2-7,00:00-24:00;0/1,00:05-24:00',
				'/1/item_delay',
				'5m;0/2-7,00:00-24:00;0/1,00:05-24:00'
			],
			'Polling available via delay, after zero-blocks' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-6,00:00-24:00;0/7,00:00-23:55',
				'/1/item_delay',
				'5m;0/1-6,00:00-24:00;0/7,00:00-23:55'
			],
			'Polling available via delay, between zero-blocks' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-3,00:00-24:00;0/4,00:05-24:00;0/5-7,00:00-24:00',
				'/1/item_delay',
				'5m;0/1-3,00:00-24:00;0/4,00:05-24:00;0/5-7,00:00-24:00'
			],
			'Polling possible via shorter of intervals' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-6,00:00-24:00;20m/7,00:00-24:00;10m/7,00:00-24:00;0/7,00:00-01:35;0/7,01:45-24:00',
				'/1/item_delay',
				'0;0/1-6,00:00-24:00;20m/7,00:00-24:00;10m/7,00:00-24:00;0/7,00:00-01:35;0/7,01:45-24:00'
			],
			'Polling impossible via shorter of intervals' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-6,00:00-24:00;20m/7,00:00-24:00;10m/7,00:00-24:00;0/7,00:00-01:35;0/7,01:44-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Polling possible via shorter of intervals, full window' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:00-11:00',
				'/1/item_delay',
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:00-11:00'
			],
			'Polling possible via shorter of intervals, end of window' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:50-11:00',
				'/1/item_delay',
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:50-11:00'
			],
			'Polling possible via shorter of intervals, start of window' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:00-10:10',
				'/1/item_delay',
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:00-10:10'
			],
			'Polling possible via shorter of intervals, with overlap of cut-off longer one' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:40-10:50',
				'/1/item_delay',
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:40-10:50'
			],
			'Interval shorter than period allowed' => [
				['type' => API_ITEM_DELAY],
				'0;0/1-7,00:00-10:00;0/1-7,11:00-24:00;20m/1-7,10:45-24:00;10m/1-7,10:00-10:09',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": update interval "10m" is longer than period "1-7,10:00-10:09".'
			],
			'No window for delay' => [
				['type' => API_ITEM_DELAY],
				'10m;0/1-7,00:05-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Window for delay OK' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-7,00:05-24:00',
				'/1/item_delay',
				'5m;0/1-7,00:05-24:00'
			],
			'Window for smaller delay OK' => [
				['type' => API_ITEM_DELAY],
				'1m;0/1-7,00:05-24:00',
				'/1/item_delay',
				'1m;0/1-7,00:05-24:00'
			],
			'Polling via delay blocked by active flexible interval' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-7,00:05-24:00;10m/1-7,00:04-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Polling via delay not blocked by flexible intervals' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-7,00:10-24:00;10m/1-7,05:00-24:00',
				'/1/item_delay',
				'5m;0/1-7,00:10-24:00;10m/1-7,05:00-24:00'
			],
			'Delay does not fit due to several flexible intervals' => [
				['type' => API_ITEM_DELAY],
				'10m;0/1-7,00:00-00:01;0/1-7,00:10-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Delay fits between blocking zero intervals' => [
				['type' => API_ITEM_DELAY],
				'10m;0/1-3,00:00-24:00;0/4,00:00-23:50;0/5-7,00:00-24:00',
				'/1/item_delay',
				'10m;0/1-3,00:00-24:00;0/4,00:00-23:50;0/5-7,00:00-24:00'
			],
			'Delay fits between blocking mixed intervals' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-3,00:00-24:00;0/4,00:00-23:45;10m/4,23:50-24:00;0/5-7,00:00-24:00',
				'/1/item_delay',
				'5m;0/1-3,00:00-24:00;0/4,00:00-23:45;10m/4,23:50-24:00;0/5-7,00:00-24:00'
			],
			'Delay does not fit between blocking mixed intervals' => [
				['type' => API_ITEM_DELAY],
				'6m;0/1-3,00:00-24:00;0/4,00:00-23:45;5m/4,23:50-23:55;0/4,23:54-24:00;0/5-7,00:00-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Delay fits after blocking zero intervals' => [
				['type' => API_ITEM_DELAY],
				'10m;0/1-6,00:00-24:00;0/7,23:50-24:00',
				'/1/item_delay',
				'10m;0/1-6,00:00-24:00;0/7,23:50-24:00'
			],
			'Delay does not fit with after mixed intervals' => [
				['type' => API_ITEM_DELAY],
				'10m;0/1-6,00:00-24:00;0/7,23:50-24:00;20m/7,00:00-23:55;0/7,00:00-23:49',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			'Delay fits at start of mixed intervals' => [
				['type' => API_ITEM_DELAY],
				'5m;0/1-7,00:10-24:00;10m/1-7,00:07-24:00',
				'/1/item_delay',
				'5m;0/1-7,00:10-24:00;10m/1-7,00:07-24:00'
			],
			'Delay does not fit at start of mixed intervals' => [
				['type' => API_ITEM_DELAY],
				'8m;0/1-7,00:10-24:00;10m/1-7,00:07-24:00',
				'/1/item_delay',
				'Invalid parameter "/1/item_delay": must have a polling interval not blocked by non-active interval periods.'
			],
			[
				['type' => API_XML],
				null,
				'/1/xml',
				'Invalid parameter "/1/xml": a character string is expected.'
			],
			[
				['type' => API_XML],
				123,
				'/1/xml',
				'Invalid parameter "/1/xml": a character string is expected.'
			],
			[
				['type' => API_XML],
				'',
				'/1/xml',
				''
			],
			[
				['type' => API_XML, 'flags' => API_NOT_EMPTY],
				'',
				'/1/xml',
				'Invalid parameter "/1/xml": cannot be empty.'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?>',
				'/1/xml',
				'Invalid parameter "/1/xml": (4) Start tag expected, \'<\' not found [Line: 1 | Column: 39].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node>value</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node>value</node>'
			],
			[
				['type' => API_XML, 'length' => 10],
				'<?xml version="1.0" encoding="UTF-8"?><node>value</node>',
				'/1/xml',
				'Invalid parameter "/1/xml": value is too long.'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop="123">value</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node prop="123">value</node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop="string">value</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node prop="string">value</node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop=string>value</node>',
				'/1/xml',
				'Invalid parameter "/1/xml": (39) AttValue: " or \' expected [Line: 1 | Column: 50].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop="string>value</node>',
				'/1/xml',
				'Invalid parameter "/1/xml": (38) Unescaped \'<\' not allowed in attributes values [Line: 1 | Column: 63].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop="<">value</node>',
				'/1/xml',
				'Invalid parameter "/1/xml": (38) Unescaped \'<\' not allowed in attributes values [Line: 1 | Column: 51].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node prop="&lt;">value</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node prop="&lt;">value</node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node><script></node>',
				'/1/xml',
				'Invalid parameter "/1/xml": (76) Opening and ending tag mismatch: script line 1 and node [Line: 1 | Column: 60].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node><script/></node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node><script/></node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node><script /></node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node><script /></node>'
			],
			'Opening and ending tag mismatch' => [
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node></a></node>',
				'/1/xml',
				'Invalid parameter "/1/xml": (76) Opening and ending tag mismatch: node line 1 and a [Line: 1 | Column: 49].'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node>/></node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node>/></node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node>/&gt;</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node>/&gt;</node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node>"</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node>"</node>'
			],
			[
				['type' => API_XML],
				'<?xml version="1.0" encoding="UTF-8"?><node>&quot;</node>',
				'/1/xml',
				'<?xml version="1.0" encoding="UTF-8"?><node>&quot;</node>'
			],

			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				'1',
				'/1/params',
				'1'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				'1',
				'/1/params',
				'1'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				'1.0',
				'/1/params',
				'1'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				'1.08',
				'/1/params',
				'1.08'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER], 'length' => 2],
				'1.08',
				'/1/params',
				'Invalid parameter "/1/params": value is too long.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				'abc',
				'/1/params',
				'Invalid parameter "/1/params/1": a floating point value is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				"1.08\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_MULTIPLIER]],
				"1.08\n1.08",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_RTRIM]],
				' ")',
				'/1/params',
				' ")'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_LTRIM]],
				' "(',
				'/1/params',
				' "('
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_TRIM]],
				' "()',
				'/1/params',
				' "()'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_RTRIM]],
				" \"(\n \")",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_LTRIM]],
				" \"(\n \")",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_TRIM]],
				" \"(\n \")",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"^[a-z]$\n\\1",
				'/1/params',
				"^[a-z]$\n\\1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"{\$MACRO}\n\\1",
				'/1/params',
				"{\$MACRO}\n\\1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"{\#LLD_MACRO}\n\\1",
				'/1/params',
				"{\#LLD_MACRO}\n\\1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"^[a-z$\n\\1",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"^[a-z]$",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "2" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_REGSUB]],
				"^[a-z]$\n\\1\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => ZBX_PREPROC_REGSUB],
					'params' => ['type' => API_PREPROC_PARAMS, 'preproc_type' => ['field' => 'type']]
				]],
				[
					'type' => ZBX_PREPROC_REGSUB,
					'params' => "^[a-z]$\n\\1"
				],
				'/',
				[
					'type' => ZBX_PREPROC_REGSUB,
					'params' => "^[a-z]$\n\\1"
				]
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_XPATH]],
				'number(/document/item/@attribute)',
				'/1/params',
				'number(/document/item/@attribute)'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_XPATH]],
				'',
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_XPATH]],
				"number(/document/item/@attribute)\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_JSONPATH]],
				'$.object.name',
				'/1/params',
				'$.object.name'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_JSONPATH]],
				'',
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_JSONPATH]],
				"$.object.name\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"1\n",
				'/1/params',
				"1\n"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"\n10",
				'/1/params',
				"\n10"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"1\n10",
				'/1/params',
				"1\n10"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"10.01\n10",
				'/1/params',
				'Invalid parameter "/1/params/2": cannot be less than or equal to the value of parameter "/1/params/1".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"10.01",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "2" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_RANGE]],
				"1\n10\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_REGEX]],
				"^[a-z]$",
				'/1/params',
				"^[a-z]$"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_REGEX]],
				"^[a-z$",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_REGEX]],
				"@^[a-z$",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_REGEX]],
				"^[a-z$\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_REGEX]],
				"^[a-z]$",
				'/1/params',
				"^[a-z]$"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_REGEX]],
				"^[a-z$",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_REGEX]],
				"@^[a-z$",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_REGEX]],
				"^[a-z$\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_JSON]],
				'$.object.error',
				'/1/params',
				'$.object.error'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_JSON]],
				'',
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_JSON]],
				"$.object.error\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_XML]],
				'number(/document/item/@error)',
				'/1/params',
				'number(/document/item/@error)'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_XML]],
				'',
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_XML]],
				"number(/document/item/@error)\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_REGEX]],
				"^[a-z]$\n\\1",
				'/1/params',
				"^[a-z]$\n\\1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_REGEX]],
				"^[a-z$\n\\1",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid regular expression.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_REGEX]],
				"^[a-z]$",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "2" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_ERROR_FIELD_REGEX]],
				"^[a-z]$\n\\1\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1',
				'/1/params',
				'1'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'30',
				'/1/params',
				'30'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'788400000', // 25 years
				'/1/params',
				'788400000'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'30s',
				'/1/params',
				'30s'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'10m',
				'/1/params',
				'10m'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1h',
				'/1/params',
				'1h'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1w',
				'/1/params',
				'1w'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1.08',
				'/1/params',
				'Invalid parameter "/1/params/1": a time unit is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1M',
				'/1/params',
				'Invalid parameter "/1/params/1": a time unit is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'1y',
				'/1/params',
				'Invalid parameter "/1/params/1": a time unit is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'-1',
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'0',
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'788400001',
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'788400001',
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'-1m',
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				'9126d', // 25 years and 1 day
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1-788400000.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_THROTTLE_TIMED_VALUE]],
				"30\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SCRIPT]],
				'return true;',
				'/1/params',
				'return true;'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SCRIPT]],
				'',
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SCRIPT]],
				"let a = 'abc';\nreturn a;",
				'/1/params',
				"let a = 'abc';\nreturn a;"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nvalue",
				'/1/params',
				"metric{label1=\"value1\"}\nvalue\n"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nvalue\n",
				'/1/params',
				"metric{label1=\"value1\"}\nvalue\n"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"{\nvalue",
				'/1/params',
				'Invalid parameter "/1/params/1": invalid Prometheus pattern.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nvalue\n\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "4".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\nlabel1",
				'/1/params',
				"metric{label1=\"value1\"}\nlabel\nlabel1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'flags' => API_ALLOW_USER_MACRO, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\n{\$MACRO}",
				'/1/params',
				"metric{label1=\"value1\"}\nlabel\n{\$MACRO}"
			],
			[
				['type' => API_PREPROC_PARAMS, 'flags' => API_ALLOW_LLD_MACRO, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\n{#LLD_MACRO}",
				'/1/params',
				"metric{label1=\"value1\"}\nlabel\n{#LLD_MACRO}"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\n",
				'/1/params',
				'Invalid parameter "/1/params/3": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\n-label1-",
				'/1/params',
				'Invalid parameter "/1/params/3": invalid Prometheus label.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\n",
				'/1/params',
				'Invalid parameter "/1/params/3": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "3" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nlabel\nlabel1\nabc",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "4".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\nsum",
				'/1/params',
				"metric{label1=\"value1\"}\nfunction\nsum"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\nmin",
				'/1/params',
				"metric{label1=\"value1\"}\nfunction\nmin"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\nmax",
				'/1/params',
				"metric{label1=\"value1\"}\nfunction\nmax"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\navg",
				'/1/params',
				"metric{label1=\"value1\"}\nfunction\navg"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\ncount",
				'/1/params',
				"metric{label1=\"value1\"}\nfunction\ncount"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\ncook",
				'/1/params',
				'Invalid parameter "/1/params/3": value must be one of "sum", "min", "max", "avg", "count".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction\n",
				'/1/params',
				'Invalid parameter "/1/params/3": value must be one of "sum", "min", "max", "avg", "count".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nfunction",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "3" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"metric{label1=\"value1\"}\nmystery",
				'/1/params',
				'Invalid parameter "/1/params/2": value must be one of "value", "label", "function".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_PATTERN]],
				"\nlabel\nlabel1",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_TO_JSON]],
				'',
				'/1/params',
				''
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_TO_JSON]],
				'metric{label1="value1"} == 123',
				'/1/params',
				'metric{label1="value1"} == 123'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_TO_JSON]],
				'metric{%label1="value1"} == 123',
				'/1/params',
				'Invalid parameter "/1/params/1": invalid Prometheus pattern.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_PROMETHEUS_TO_JSON]],
				"metric{label1=\"value1\"} == 123\nvalue",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				"\n\n0",
				'/1/params',
				"\n\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				",\n\n0",
				'/1/params',
				",\n\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				",\n\"\n0",
				'/1/params',
				",\n\"\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				",\n\"\n1",
				'/1/params',
				",\n\"\n1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				'',
				'/1/params',
				'Invalid parameter "/1/params": the parameter "2" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "3" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				"\n\n",
				'/1/params',
				'Invalid parameter "/1/params/3": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				"\n\nS",
				'/1/params',
				'Invalid parameter "/1/params/3": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				"\n\n0\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "4".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_CSV_TO_JSON]],
				",\n\"\n2",
				'/1/params',
				'Invalid parameter "/1/params/3": value must be one of 0, 1.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"abc\n",
				'/1/params',
				"abc\n"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"abc",
				'/1/params',
				"abc\n"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"abc\ndef",
				'/1/params',
				"abc\ndef"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"\n\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_STR_REPLACE]],
				"zabbix\nzabbix\ ",
				'/1/params',
				'Invalid parameter "/1/params/2": value contains unescaped character at position 7.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"-1",
				'/1/params',
				"-1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"0\nregexp",
				'/1/params',
				"0\nregexp"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"1\nregexp",
				'/1/params',
				"1\nregexp"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"2",
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of -1, 0, 1.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"-1...",
				'/1/params',
				'Invalid parameter "/1/params/1": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"-1\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"-1\nregexp",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"0\n",
				'/1/params',
				'Invalid parameter "/1/params/2": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"1\n",
				'/1/params',
				'Invalid parameter "/1/params/2": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED]],
				"0\nregexp\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n0",
				'/1/params',
				"1.3.1.6.2.1\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n1",
				'/1/params',
				"1.3.1.6.2.1\n1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n2",
				'/1/params',
				"1.3.1.6.2.1\n2"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n3",
				'/1/params',
				"1.3.1.6.2.1\n3"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n",
				'/1/params',
				'Invalid parameter "/1/params/2": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"\n\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "3".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\n4",
				'/1/params',
				'Invalid parameter "/1/params/2": value must be one of 0, 1, 2, 3.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_VALUE]],
				"1.3.1.6.2.1\na",
				'/1/params',
				'Invalid parameter "/1/params/2": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n1",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n2",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n2"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n3",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n3"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n0",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n0"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n1",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n2",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n2"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n3",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n3"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n1\n{#FIELD3}\n1.2.3.4.5.2\n2\n{#FIELD4}\n1.2.3.4.5.3\n3",
				'/1/params',
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n1\n{#FIELD3}\n1.2.3.4.5.2\n2\n{#FIELD4}\n1.2.3.4.5.3\n3"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params/1": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n",
				'/1/params',
				'Invalid parameter "/1/params/2": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "3" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n",
				'/1/params',
				'Invalid parameter "/1/params/3": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\na",
				'/1/params',
				'Invalid parameter "/1/params/3": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n4",
				'/1/params',
				'Invalid parameter "/1/params/3": value must be one of 0, 1, 2, 3.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n",
				'/1/params',
				'Invalid parameter "/1/params/4": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "5" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n",
				'/1/params',
				'Invalid parameter "/1/params/5": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1",
				'/1/params',
				'Invalid parameter "/1/params": the parameter "6" is missing.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n",
				'/1/params',
				'Invalid parameter "/1/params/6": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n4",
				'/1/params',
				'Invalid parameter "/1/params/6": value must be one of 0, 1, 2, 3.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_WALK_TO_JSON]],
				"{#FIELD}\n1.3.1.6.2.1\n0\n{#FIELD2}\n1.2.3.4.5.1\n1\n",
				'/1/params',
				'Invalid parameter "/1/params/7": cannot be empty.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"",
				'/1/params',
				'Invalid parameter "/1/params/1": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"abc",
				'/1/params',
				'Invalid parameter "/1/params/1": an integer is expected.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"0",
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1, 2, 3.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"1",
				'/1/params',
				"1"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"2",
				'/1/params',
				"2"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"3",
				'/1/params',
				"3"
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"4",
				'/1/params',
				'Invalid parameter "/1/params/1": value must be one of 1, 2, 3.'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"1\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PREPROC_PARAMS, 'preproc_type' => ['value' => ZBX_PREPROC_SNMP_GET_VALUE]],
				"\n",
				'/1/params',
				'Invalid parameter "/1/params": unexpected parameter "2".'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'',
				'/1/prometheus_pattern',
				''
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'cpu_usage_system{cpu="cpu-total"}',
				'/1/prometheus_pattern',
				'cpu_usage_system{cpu="cpu-total"}'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'{$MACRO}{label1="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_USER_MACRO],
				'metric{{$MACRO}="value1"}==123',
				'/1/prometheus_pattern',
				'metric{{$MACRO}="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'metric{label1="{$MACRO}"}==123',
				'/1/prometheus_pattern',
				'metric{label1="{$MACRO}"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_USER_MACRO],
				'metric{label="value1"}=={$MACRO}',
				'/1/prometheus_pattern',
				'metric{label="value1"}=={$MACRO}'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'{#LLD_MACRO}{label1="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'metric{{#LLD_MACRO}="value1"}==123',
				'/1/prometheus_pattern',
				'metric{{#LLD_MACRO}="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'metric{label1="{#LLD_MACRO}"}==123',
				'/1/prometheus_pattern',
				'metric{label1="{#LLD_MACRO}"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'metric{label="value1"}=={#LLD_MACRO}',
				'/1/prometheus_pattern',
				'metric{label="value1"}=={#LLD_MACRO}'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}{label1="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'metric{{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}="value1"}==123',
				'/1/prometheus_pattern',
				'metric{{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}="value1"}==123'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'metric{label="value1"}=={{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}',
				'/1/prometheus_pattern',
				'metric{label="value1"}=={{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				null,
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": a character string is expected.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				123,
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": a character string is expected.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_NOT_EMPTY],
				'',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": cannot be empty.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'{',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'{$MACRO}',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'{#LLD_MACRO}',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'{$MACRO}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN],
				'metric{label1="{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}"}==123',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}{$MACRO2}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_PATTERN, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}{#LLD_MACRO2}{label1="value1"}==123',
				'/1/prometheus_pattern',
				'Invalid parameter "/1/prometheus_pattern": invalid Prometheus pattern.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'label1',
				'/1/prometheus_label',
				'label1'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'superLabel_1',
				'/1/prometheus_label',
				'superLabel_1'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'_superLabel_1',
				'/1/prometheus_label',
				'_superLabel_1'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'SuperLabel_1',
				'/1/prometheus_label',
				'SuperLabel_1'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				null,
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": a character string is expected.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				123,
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": a character string is expected.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": cannot be empty.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'1_label',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'label}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'{$MACRO}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}',
				'/1/prometheus_label',
				'{$MACRO}'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'{#LLD_MACRO}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}',
				'/1/prometheus_label',
				'{#LLD_MACRO}'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_LLD_MACRO],
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}',
				'/1/prometheus_label',
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}'
			],
			[
				['type' => API_PROMETHEUS_LABEL],
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO}{$MACRO2}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_LLD_MACRO],
				'{#LLD_MACRO}{#LLD_MACRO2}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_PROMETHEUS_LABEL, 'flags' => API_ALLOW_LLD_MACRO],
				'{{#LLD_MACRO}.regsub("(.*)_([0-9]+)", \1)}{{#LLD_MACRO2}.regsub("(.*)_([0-9]+)", \1)}',
				'/1/prometheus_label',
				'Invalid parameter "/1/prometheus_label": invalid Prometheus label.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'user_medias' => ['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'replacement' => 'medias', 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]],
					'medias' => ['type' => API_OBJECTS, 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]]
				]],
				[
					'user_medias' => [['status' => true], ['status' => false]],
					'medias' => [['status' => true], ['status' => false]]
				],
				'/',
				'Deprecated parameter "/user_medias" cannot be used with "/medias".'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'user_medias' => ['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'replacement' => 'medias', 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]],
					'medias' => ['type' => API_OBJECTS, 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]]
				]],
				[
					'user_medias' => [['status' => true], ['status' => 'string']]
				],
				'/',
				'Invalid parameter "/medias/2/status": a boolean is expected.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'user_medias' => ['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'replacement' => 'medias', 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]],
					'medias' => ['type' => API_OBJECTS, 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]]
				]],
				[
					'user_medias' => [['status' => true], ['status' => false]]
				],
				'/',
				[
					'medias' => [['status' => true], ['status' => false]]
				],
				'Parameter "/user_medias" is deprecated.'
			],
			[
				// API_DEPRECATED rule with 'replacement' SHOULD be defined before rules for field mentioned in 'replacement'
				['type' => API_OBJECT, 'fields' => [
					'medias' => ['type' => API_OBJECTS, 'fields' => [
						'status' =>	['type' => API_BOOLEAN]
					]],
					'user_medias' => ['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'replacement' => 'medias', 'fields' => [
						'status' =>	['type' => API_STRING_UTF8]
					]]
				]],
				[
					'user_medias' => [['status' => 'not boolean']]
				],
				'/',
				[
					'medias' => [['status' => 'not boolean']]
				],
				'Parameter "/user_medias" is deprecated.'
			],
			[
				['type' => API_NUMERIC],
				'9.99999999999999E+15',
				'/1/numeric',
				'9.99999999999999E+15'
			],
			[
				['type' => API_NUMERIC],
				'1E+16',
				'/1/numeric',
				'1E+16'
			],
			[
				['type' => API_NUMERIC],
				'-9.99999999999999E+15',
				'/1/numeric',
				'-9.99999999999999E+15'
			],
			[
				['type' => API_NUMERIC],
				'-1E+16',
				'/1/numeric',
				'-1E+16'
			],
			[
				['type' => API_NUMERIC],
				'10000000000.0001',
				'/1/numeric',
				'10000000000.0001'
			],
			[
				['type' => API_NUMERIC],
				'1.00001',
				'/1/numeric',
				'1.00001'
			],
			[
				['type' => API_NUMERIC],
				'1E-4',
				'/1/numeric',
				'1E-4'
			],
			[
				['type' => API_NUMERIC],
				'1E-5',
				'/1/numeric',
				'1E-5'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP, 'length' => 18],
				'path/to/secret:key',
				'/1/secret',
				'path/to/secret:key'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP, 'length' => 27],
				'mount%2Fpoint/to/secret:key',
				'/1/secret',
				'mount%2Fpoint/to/secret:key'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP, 'length' => 17],
				'path/to/secret:key',
				'/1/secret',
				'Invalid parameter "/1/secret": value is too long.'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				'/pathtosecret:key',
				'/1/secret',
				'Invalid parameter "/1/secret": incorrect syntax near "/pathtosecret:key".'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				'',
				'/1/secret',
				'Invalid parameter "/1/secret": cannot be empty.'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				true,
				'/1/secret',
				'Invalid parameter "/1/secret": a character string is expected.'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				[],
				'/1/secret',
				'Invalid parameter "/1/secret": a character string is expected.'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				null,
				'/1/secret',
				'Invalid parameter "/1/secret": a character string is expected.'
			],
			[
				['type' => API_VAULT_SECRET, 'provider' => ZBX_VAULT_TYPE_HASHICORP],
				// broken UTF-8 byte sequence
				'{$MACRO: '."\xd1".'ontext}',
				'/1/secret',
				'Invalid parameter "/1/secret": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_HOST_ADDRESS],
				'',
				'/1/address',
				''
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_NOT_EMPTY],
				'',
				'/1/address',
				'Invalid parameter "/1/address": cannot be empty.'
			],
			[
				['type' => API_HOST_ADDRESS],
				[],
				'/1/address',
				'Invalid parameter "/1/address": a character string is expected.'
			],
			[
				['type' => API_HOST_ADDRESS],
				true,
				'/1/address',
				'Invalid parameter "/1/address": a character string is expected.'
			],
			[
				['type' => API_HOST_ADDRESS],
				null,
				'/1/address',
				'Invalid parameter "/1/address": a character string is expected.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				// Broken UTF-8 byte sequence.
				'{$MACRO: "'."\xd1".'"}',
				'/1/address',
				'Invalid parameter "/1/address": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO: "context"}',
				'/1/address',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_HOST_ADDRESS],
				'%%%',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS],
				'3.3.3.3',
				'/1/address',
				'3.3.3.3'
			],
			[
				['type' => API_HOST_ADDRESS, 'length' => 15],
				'www.example.com',
				'/1/address',
				'www.example.com'
			],
			[
				['type' => API_HOST_ADDRESS, 'length' => 14],
				'www.example.com',
				'/1/address',
				'Invalid parameter "/1/address": value is too long.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				'{$}',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO2}',
				'/1/address',
				'{$MACRO2}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#}',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO2}',
				'/1/address',
				'{#MACRO2}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO3}{#MACRO4}',
				'/1/address',
				'{#MACRO3}{#MACRO4}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_MACRO],
				'{HOST.IP}',
				'/1/address',
				'{HOST.IP}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO3}{$MACRO4}',
				'/1/address',
				'{$MACRO3}{$MACRO4}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_MACRO],
				'{$MACRO}',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO],
				'{HOST.HOST}',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO | API_ALLOW_MACRO],
				'a{HOST.HOST}b{$MACRO5}c{#MACRO5}d{HOST.NAME}e{$MACRO6}',
				'/1/address',
				'a{HOST.HOST}b{$MACRO5}c{#MACRO5}d{HOST.NAME}e{$MACRO6}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_MACRO],
				'a{HOST.HOST}b{HOST.IP}c',
				'/1/address',
				'a{HOST.HOST}b{HOST.IP}c'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO],
				'a{$MACRO7}b{#MACRO6}c{HOST.NAME}d{$MACRO8}',
				'/1/address',
				'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			[
				['type' => API_HOST_ADDRESS],
				'0.0.0.x',
				'/1/address',
				'0.0.0.x'
			],
			[
				['type' => API_HOST_ADDRESS],
				'1.1.1.1',
				'/1/address',
				'1.1.1.1'
			],
			[
				['type' => API_HOST_ADDRESS, 'length' => 11],
				'192.168.3.5',
				'/1/address',
				'192.168.3.5'
			],
			[
				['type' => API_HOST_ADDRESS, 'length' => 10],
				'192.168.3.5',
				'/1/address',
				'Invalid parameter "/1/address": value is too long.'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_USER_MACRO],
				'{$MACRO1}',
				'/1/address',
				'{$MACRO1}'
			],
			[
				['type' => API_HOST_ADDRESS, 'flags' => API_ALLOW_LLD_MACRO],
				'{#MACRO1}',
				'/1/address',
				'{#MACRO1}'
			],
			[
				['type' => API_ESCAPED_STRING_UTF8, 'flags' => API_NOT_EMPTY],
				'',
				'/',
				'Invalid parameter "/": cannot be empty.'
			],
			[
				['type' => API_ESCAPED_STRING_UTF8, 'flags' => API_NOT_EMPTY],
				[],
				'/',
				'Invalid parameter "/": a character string is expected.'
			],
			[
				['type' => API_ESCAPED_STRING_UTF8, 'characters' => '\\nrts'],
				'\\\n\r\t\s',
				'/',
				'\\\n\r\t\s'
			],
			[
				['type' => API_ESCAPED_STRING_UTF8],
				'\\',
				'/',
				'Invalid parameter "/": value contains unescaped character at position 1.'
			],
			[
				['type' => API_ESCAPED_STRING_UTF8, 'characters' => 'nrts'],
				'\n\n\n\ ',
				'/',
				'Invalid parameter "/": value contains unescaped character at position 7.'
			],
			[
				['type' => API_NUMBER],
				'',
				'/1/number',
				'Invalid parameter "/1/number": cannot be empty.'
			],
			[
				['type' => API_NUMBER],
				[],
				'/1/number',
				'Invalid parameter "/1/number": a number is expected.'
			],
			[
				['type' => API_NUMBER],
				true,
				'/1/number',
				'Invalid parameter "/1/number": a number is expected.'
			],
			[
				['type' => API_NUMBER],
				null,
				'/1/number',
				'Invalid parameter "/1/number": a number is expected.'
			],
			[
				['type' => API_NUMBER],
				'abc',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "abc".'
			],
			[
				['type' => API_NUMBER],
				123,
				'/1/number',
				'123'
			],
			[
				['type' => API_NUMBER],
				'456',
				'/1/number',
				'456'
			],
			[
				['type' => API_NUMBER],
				'1.5',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "1.5".'
			],
			[
				['type' => API_NUMBER],
				'005',
				'/1/number',
				'5'
			],
			[
				['type' => API_NUMBER],
				'000',
				'/1/number',
				'0'
			],
			[
				['type' => API_NUMBER, 'in' => '1:100'],
				'000',
				'/1/number',
				'Invalid parameter "/1/number": value must be one of 1-100.'
			],
			[
				['type' => API_NUMBER, 'in' => '1:100'],
				'-1',
				'/1/number',
				'Invalid parameter "/1/number": value must be one of 1-100.'
			],
			[
				['type' => API_NUMBER, 'in' => '1:100'],
				'999',
				'/1/number',
				'Invalid parameter "/1/number": value must be one of 1-100.'
			],
			[
				['type' => API_NUMBER],
				'{$}',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "}".'
			],
			[
				['type' => API_NUMBER],
				'{$MACRO}',
				'/1/number',
				'{$MACRO}'
			],
			[
				['type' => API_NUMBER, 'length' => 8],
				'{$MACRO}',
				'/1/number',
				'{$MACRO}'
			],
			[
				['type' => API_NUMBER, 'length' => 7],
				'{$MACRO}',
				'/1/number',
				'Invalid parameter "/1/number": value is too long.'
			],
			[
				['type' => API_NUMBER],
				'{$MACRO1}{$MACRO2}',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "{$MACRO2}".'
			],
			[
				['type' => API_NUMBER],
				// broken UTF-8 byte sequence
				'{$MACRO: "'."\xd1".'"}',
				'/1/number',
				'Invalid parameter "/1/number": invalid byte sequence in UTF-8.'
			],
			[
				['type' => API_NUMBER],
				'{$MACRO: "context"}',
				'/1/number',
				'{$MACRO: "context"}'
			],
			[
				['type' => API_NUMBER],
				'{{$MACRO}.func()}',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "{$MACRO}.func()}".'
			],
			[
				['type' => API_NUMBER],
				'{#}',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "#}".'
			],
			[
				['type' => API_NUMBER],
				'{#MACRO}',
				'/1/number',
				'Invalid parameter "/1/number": incorrect syntax near "#MACRO}".'
			]
		];
	}

	/**
	 * @dataProvider dataProviderInput
	 *
	 * @param array       $rule
	 * @param mixed       $data
	 * @param string      $path
	 * @param mixed       $expected
	 * @param string|null $deprecation_message
	 */
	public function testApiInputValidator(array $rule, $data, $path, $expected, $deprecation_expected = null) {
		$rc = CApiInputValidator::validate($rule, $data, $path, $error);

		if ($deprecation_expected !== null) {
			$last_error = error_get_last();
			$this->assertNotNull($last_error, 'Expected a deprecation error');
			$this->assertSame(E_USER_DEPRECATED, $last_error['type'], 'Should have received a deprecation error');
			$this->assertSame($deprecation_expected, $last_error['message'], 'Deprecation error should match');
		}

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
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO1}', '{$MACRO2}', '{$MACRO3}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO1}', '{$MACRO2}', '{$MACRO3}', '{$MACRO1}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO: abc}', '{$MACRO:" abc"}', '{$MACRO:def}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO: abc}', '{$MACRO:" abc"}', '{$MACRO:def}', '{$MACRO:abc}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO:regex:"^/tmp$"}', '{$MACRO:"regex:^/tmp$"}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS],
				['{$MACRO:regex:"^/tmp$"}', '{$MACRO:"regex:^/tmp$"}', '{$MACRO:regex:^/tmp$}'],
				'/',
				true,
				''
			],
			[
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO1}', '{$MACRO2}', '{$MACRO3}', '{$MACRO1}'],
				'/',
				false,
				'Invalid parameter "/4": value ({$MACRO1}) already exists.'
			],
			[
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO: abc}', '{$MACRO:" abc"}', '{$MACRO:def}', '{$MACRO:abc}'],
				'/',
				false,
				'Invalid parameter "/4": value ({$MACRO:abc}) already exists.'
			],
			[
				['type' => API_USER_MACROS, 'uniq' => true],
				['{$MACRO:regex:"^/tmp$"}', '{$MACRO:"regex:^/tmp$"}', '{$MACRO:regex:^/tmp$}'],
				'/',
				false,
				'Invalid parameter "/3": value ({$MACRO:regex:^/tmp$}) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']], 'fields' => [
					'applicationid'	=> ['type' => API_ID],
					'hostid'		=> ['type' => API_ID],
					'name'			=> ['type' => API_STRING_UTF8]
				]],
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
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']], 'fields' => [
					'applicationid'	=> ['type' => API_ID],
					'hostid'		=> ['type' => API_ID],
					'name'			=> ['type' => API_STRING_UTF8]
				]],
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
				['type' => API_OBJECTS, 'uniq' => [['applicationid'], ['hostid', 'name']], 'fields' => [
					'applicationid'	=> ['type' => API_ID],
					'hostid'		=> ['type' => API_ID],
					'name'			=> ['type' => API_STRING_UTF8]
				]],
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
				['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
					'name'	=> ['type' => API_STRING_UTF8]
				]],
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
				['type' => API_OBJECTS, 'uniq' => [['hostid', 'name']], 'fields' => [
					'hostid'	=> ['type' => API_ID],
					'name'		=> ['type' => API_STRING_UTF8]
				]],
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
				['type' => API_OBJECTS, 'uniq' => [['hostid', 'macro']], 'fields' => [
					'hostid'	=> ['type' => API_ID],
					'macro'		=> ['type' => API_USER_MACRO]
				]],
				[
					['hostid' => 1, 'macro' => '{$MACRO: context}'],
					['hostid' => 1, 'macro' => '{$MACRO}'],
					['hostid' => 2, 'macro' => '{$MACRO}'],
					['hostid' => 2, 'macro' => '{$MACRO: context}'],
					['hostid' => 1, 'macro' => '{$MACRO: "context2"}'],
					['hostid' => 1, 'macro' => '{$MACRO:regex: context}'],
					['hostid' => 1, 'macro' => '{$MACRO: "context"}']
				],
				'/',
				false,
				'Invalid parameter "/7": value (hostid, macro)=(1, {$MACRO: "context"}) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['scriptid'], ['menu_path']], 'fields' => [
					'scriptid'	=> ['type' => API_ID],
					'menu_path'	=> ['type' => API_SCRIPT_MENU_PATH]
				]],
				[
					['scriptid' => 2, 'menu_path' => 'System / Utils / Ping'],
					['scriptid' => 3, 'menu_path' => 'System / Utils / Traceroute'],
					['scriptid' => 1, 'menu_path' => 'System / Reboot'],
					['scriptid' => 4, 'menu_path' => 'System / Console'],
					['scriptid' => 5, 'menu_path' => 'System / Docker / Containers'],
					['scriptid' => 6, 'menu_path' => 'System / Docker / Images']
				],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['scriptid'], ['menu_path']], 'fields' => [
					'scriptid'	=> ['type' => API_ID],
					'menu_path'	=> ['type' => API_SCRIPT_MENU_PATH]
				]],
				[
					['scriptid' => 2, 'menu_path' => 'System / Utils / Ping'],
					['scriptid' => 3, 'menu_path' => 'System / Utils / Traceroute'],
					['scriptid' => 1, 'menu_path' => 'System / Reboot'],
					['scriptid' => 4, 'menu_path' => 'System / Console'],
					['scriptid' => 5, 'menu_path' => 'System / Docker / Containers'],
					['scriptid' => 6, 'menu_path' => 'System/Utils/Traceroute']
				],
				'/',
				false,
				'Invalid parameter "/6": value (menu_path)=(System/Utils/Traceroute) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['scriptid'], ['menu_path']], 'fields' => [
					'scriptid'	=> ['type' => API_ID],
					'menu_path'	=> ['type' => API_SCRIPT_MENU_PATH]
				]],
				[
					['scriptid' => 2, 'menu_path' => 'System/Utils'],
					['scriptid' => 6, 'menu_path' => 'System/Utils/']
				],
				'/',
				false,
				'Invalid parameter "/2": value (menu_path)=(System/Utils/) already exists.'
			],
			[
				['type' => API_OBJECTS, 'uniq' => [['scriptid'], ['menu_path']], 'fields' => [
					'scriptid'	=> ['type' => API_ID],
					'menu_path'	=> ['type' => API_SCRIPT_MENU_PATH]
				]],
				[
					['scriptid' => 2, 'menu_path' => '/System/Utils'],
					['scriptid' => 6, 'menu_path' => 'System/Utils']
				],
				'/',
				false,
				'Invalid parameter "/2": value (menu_path)=(System/Utils) already exists.'
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
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'tags' => ['type' => API_MULTIPLE, 'rules' => [
						['else' => true, 'type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
							'tag'		=> ['type' => API_STRING_UTF8],
							'operator'	=> ['type' => API_INT32],
							'value'		=> ['type' => API_STRING_UTF8]
						]]
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
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'tags' => ['type' => API_MULTIPLE, 'rules' => [
						['else' => true, 'type' => API_OBJECTS, 'uniq' => [['tag', 'operator', 'value']], 'fields' => [
							'tag'		=> ['type' => API_STRING_UTF8],
							'operator'	=> ['type' => API_INT32],
							'value'		=> ['type' => API_STRING_UTF8]
						]]
					]]
				]],
				[
					[
						'tags' => [
							['tag' => 'tag', 'operator' => 0, 'value' => ''],
							['tag' => 'tag', 'operator' => 0, 'value' => '']
						]
					]
				],
				'/',
				false,
				'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 0, ) already exists.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'levels' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INTS32, 'flags' => API_REQUIRED, 'in' => '1,2,3', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '2'],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'levels' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INTS32, 'flags' => API_REQUIRED, 'in' => '1,2,3', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '3', 'levels' => ['1', '2']],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'levels' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INTS32, 'flags' => API_REQUIRED, 'in' => '1,2,3', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '3', 'levels' => ['1', '2', '1']],
				'/',
				false,
				'Invalid parameter "/levels/3": value (1) already exists.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'levels' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INTS32, 'flags' => API_REQUIRED, 'in' => '1,2,3', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '2'],
					['type' => '3', 'levels' => ['1', '2']],
					['type' => '3', 'levels' => ['1', '2', '1']]
				],
				'/',
				false,
				'Invalid parameter "/3/levels/3": value (1) already exists.'
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INTS32, 'in' => '1,2,3,4', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '3', 'level' => '1'],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INTS32, 'in' => '1,2,3,4', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '3', 'level' => '2', 'value' => ['1', '2', '3']],
				'/',
				true,
				''
			],
			[
				['type' => API_OBJECT, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INTS32, 'in' => '1,2,3,4', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				['type' => '3', 'level' => '2', 'value' => ['1', '2', '3', '4', '1']],
				'/',
				false,
				'Invalid parameter "/value/5": value (1) already exists.'
			],
			[
				['type' => API_OBJECTS, 'fields' => [
					'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
					'level' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => ['field' => 'type', 'in' => '3'], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3'],
						['else' => true, 'type' => API_UNEXPECTED]
					]],
					'value' =>	['type' => API_MULTIPLE, 'rules' => [
						['if' => function (array $data): bool {
							return $data['type'] == 3 && in_array($data['level'], [2, 3]);
						}, 'type' => API_INTS32, 'in' => '1,2,3,4', 'uniq' => true],
						['else' => true, 'type' => API_UNEXPECTED]
					]]
				]],
				[
					['type' => '3', 'level' => '1'],
					['type' => '3', 'level' => '2', 'value' => ['1', '2', '3']],
					['type' => '3', 'level' => '2', 'value' => ['1', '2', '3', '4', '1']]
				],
				'/',
				false,
				'Invalid parameter "/3/value/5": value (1) already exists.'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4'],
					['type' => '1', 'name' => 'Test5']
				],
				'/',
				false,
				'Invalid parameter "/5": only one object can exist within the combinations of (type)=((1)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4'],
					['type' => '1', 'name' => 'Test5']
				],
				'/',
				false,
				'Invalid parameter "/5": only one object can exist within the combinations of (type)=((1, 2)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4'],
					['type' => '2', 'name' => 'Test5']
				],
				'/',
				false,
				'Invalid parameter "/5": only one object can exist within the combinations of (type)=((1, 2)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [
						['type' => ['1', '2']],
						['type' => ['3']]
					],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [
						['type' => ['1', '2']],
						['type' => ['3']]
					],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
					]
				],
				[
					['type' => '3', 'name' => 'Test1'],
					['type' => '3', 'name' => 'Test2'],
					['type' => '4', 'name' => 'Test3'],
					['type' => '1', 'name' => 'Test4']
				],
				'/',
				false,
				'Invalid parameter "/2": only one object can exist within the combinations of (type)=((3)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'b'],
					['type' => '2', 'name' => 'Test6', 'method' => 'b']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'b'],
					['type' => '2', 'name' => 'Test6', 'method' => 'b'],
					['type' => '2', 'name' => 'Test7', 'method' => 'a']
				],
				'/',
				false,
				'Invalid parameter "/7": only one object can exist within the combinations of (type, method)=((1, 2), (a)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'b'],
					['type' => '2', 'name' => 'Test6'],
					['type' => '2', 'name' => 'Test7', 'method' => 'a']
				],
				'/',
				false,
				'Invalid parameter "/7": only one object can exist within the combinations of (type, method)=((1, 2), (a)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a', 'b']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'c'],
					['type' => '2', 'name' => 'Test6', 'method' => 'c'],
					['type' => '2', 'name' => 'Test7', 'method' => 'd']
				],
				'/',
				true,
				''
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a', 'b']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'c'],
					['type' => '1', 'name' => 'Test6', 'method' => 'b']
				],
				'/',
				false,
				'Invalid parameter "/6": only one object can exist within the combinations of (type, method)=((1, 2), (a, b)).'
			],
			[
				[
					'type' => API_OBJECTS,
					'uniq' => [['name']],
					'uniq_by_values' => [['type' => ['1', '2'], 'method' => ['a', 'b']]],
					'fields' => [
						'type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1,2,3,4,5,6,7,8,9,10'],
						'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
						'method' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => 'a,b,c,d']
					]
				],
				[
					['type' => '3', 'name' => 'Test1', 'method' => 'a'],
					['type' => '3', 'name' => 'Test2', 'method' => 'a'],
					['type' => '4', 'name' => 'Test3', 'method' => 'c'],
					['type' => '1', 'name' => 'Test4', 'method' => 'a'],
					['type' => '1', 'name' => 'Test5', 'method' => 'c'],
					['type' => '2', 'name' => 'Test6', 'method' => 'c'],
					['type' => '2', 'name' => 'Test7', 'method' => 'b']
				],
				'/',
				false,
				'Invalid parameter "/7": only one object can exist within the combinations of (type, method)=((1, 2), (a, b)).'
			]
		];
	}

	/**
	 * @dataProvider dataProviderUniqueness
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param bool   $rc_expected
	 * @param mixed  $error_expected
	 */
	public function testApiUniqueness(array $rule, $data, $path, $rc_expected, $error_expected) {
		$rc = CApiInputValidator::validateUniqueness($rule, $data, $path, $error);

		$this->assertSame(gettype($rc_expected), gettype($rc));
		$this->assertSame(gettype($error_expected), gettype($error));
		$this->assertSame($rc_expected, $rc);
		$this->assertSame($error_expected, $error);
	}
}

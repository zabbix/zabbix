<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CStringValidatorTest extends CValidatorTest {

	public function dataProviderValidParam() {
		return [
			[[
				'empty' => true,
				'maxLength' => 10,
				'regex' => '/[a-z]+/',
				'messageInvalid' => 'Not a string',
				'messageEmpty' => 'String empty',
				'messageMaxLength' => 'String too long',
				'messageRegex' => 'Incorrect string'
			]]
		];
	}

	public function dataProviderValidValues() {
		return [
			[[], 'string'],
			[[], 123],
			[[], 123.5],
			[[], 0],

			[['empty' => true], ''],

			[['maxLength' => 6], 'string'],
			[['maxLength' => 6], 123456],
			[['maxLength' => 6], 1234.5],

			[['regex' => '/^\d+$/'], 1],
			[['regex' => '/^\d+$/'], '3'],
			[['regex' => '/^\d+$/', 'empty' => true], '']
		];
	}

	public function dataProviderInvalidValues() {
		return [
			[
				['messageEmpty' => 'Empty string'],
				'',
				'Empty string'
			],
			[
				['messageInvalid' => 'Not a string'],
				null,
				'Not a string'
			],
			[
				['messageInvalid' => 'Not a string'],
				[],
				'Not a string'
			],

			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'],
				'longstring',
				'String "longstring" is longer then 6 chars'
			],
			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'],
				1234567,
				'String "1234567" is longer then 6 chars'
			],
			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%1$s" is longer then %2$s chars'],
				1234567.8,
				'String "1234567.8" is longer then 6 chars'
			],

			[
				['regex' => '/^\d+$/', 'messageRegex' => 'String "%1$s" doesn\'t match regex'],
				'string',
				'String "string" doesn\'t match regex'
			],
			[
				['regex' => '/^\d+$/', 'messageEmpty' => 'Empty string'],
				'',
				'Empty string'
			]
		];
	}

	public function dataProviderInvalidValuesWithObjects() {
		return [
			[
				['messageEmpty' => 'Empty string for "%1$s"'],
				'',
				'Empty string for "object"'
			],
			[
				['messageInvalid' => 'Not a string for "%1$s"'],
				null,
				'Not a string for "object"'
			],
			[
				['messageInvalid' => 'Not a string for "%1$s"'],
				[],
				'Not a string for "object"'
			],

			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'],
				'longstring',
				'String "longstring" is longer then 6 chars for "object"'
			],
			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'],
				1234567,
				'String "1234567" is longer then 6 chars for "object"'
			],
			[
				['maxLength' => 6, 'messageMaxLength' => 'String "%2$s" is longer then %3$s chars for "%1$s"'],
				1234567.8,
				'String "1234567.8" is longer then 6 chars for "object"'
			],

			[
				['regex' => '/^\d+$/', 'messageRegex' => 'String "%2$s" doesn\'t match regex for "%1$s"'],
				'string',
				'String "string" doesn\'t match regex for "object"'
			],
			[
				['regex' => '/^$/', 'messageEmpty' => 'Empty string'],
				'',
				'Empty string'
			]
		];
	}

	protected function createValidator(array $params = []) {
		return new CStringValidator($params);
	}
}

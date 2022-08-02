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


class CIdValidatorTest extends CValidatorTest {

	public function dataProviderValidParam() {
		return [
			[[
				'empty' => true,
				'messageEmpty' => 'Empty ID',
				'messageInvalid' => 'Incorrect ID specified'
			]]
		];
	}

	public function dataProviderValidValues() {
		return [
			[[], 1],
			[[], '1'],
			[[], '9223372036854775807'],
			[['empty' => true], 0],
			[['empty' => true], '0']
		];
	}

	public function dataProviderInvalidValues() {
		return [
			[
				['messageInvalid' => 'Invalid ID type'],
				true,
				'Invalid ID type'
			],
			[
				['messageInvalid' => 'Invalid ID type'],
				null,
				'Invalid ID type'
			],
			[
				['messageInvalid' => 'Invalid ID type'],
				[],
				'Invalid ID type'
			],
			[
				['messageInvalid' => 'Invalid ID type'],
				new stdClass(),
				'Invalid ID type'
			],
			[
				['messageEmpty' => 'Empty ID'],
				0,
				'Empty ID'
			],
			[
				['messageEmpty' => 'Empty ID'],
				'0',
				'Empty ID'
			],
			[
				['messageInvalid' => 'Invalid ID'],
				'',
				'Invalid ID'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'01',
				'Incorrect ID "01"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'1.1',
				'Incorrect ID "1.1"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'-1',
				'Incorrect ID "-1"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'9223372036854775808',
				'Incorrect ID "9223372036854775808"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'A',
				'Incorrect ID "A"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%1$s"'],
				'1A',
				'Incorrect ID "1A"'
			]
		];
	}

	public function dataProviderInvalidValuesWithObjects() {
		return [
			[
				['messageInvalid' => 'Invalid ID for "%1$s"'],
				true,
				'Invalid ID for "object"'
			],
			[
				['messageInvalid' => 'Invalid ID for "%1$s"'],
				null,
				'Invalid ID for "object"'
			],
			[
				['messageInvalid' => 'Invalid ID for "%1$s"'],
				[],
				'Invalid ID for "object"'
			],
			[
				['messageEmpty' => 'Empty ID for "%1$s"'],
				0,
				'Empty ID for "object"'
			],
			[
				['messageEmpty' => 'Empty ID for "%1$s"'],
				'0',
				'Empty ID for "object"'
			],
			[
				['messageInvalid' => 'Invalid ID for "%1$s"'],
				'',
				'Invalid ID for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'01',
				'Incorrect ID "01" for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'-1',
				'Incorrect ID "-1" for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'1.1',
				'Incorrect ID "1.1" for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'9223372036854775808',
				'Incorrect ID "9223372036854775808" for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'A',
				'Incorrect ID "A" for "object"'
			],
			[
				['messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'],
				'1A',
				'Incorrect ID "1A" for "object"'
			]
		];
	}

	protected function createValidator(array $params = []) {
		return new CIdValidator($params);
	}
}

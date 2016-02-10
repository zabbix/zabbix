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


class CLimitedSetValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return [
			[[
				'values' => [1, 2, 3],
				'messageInvalid' => 'Incorrect value'
			]]
		];
	}

	public function validValuesProvider() {
		return [
			[['values' => [1, 2, 3]], 2],
			[['values' => [1, 2, 3]], '2'],
			[['values' => ['1', '2', '3']], 2],
			[['values' => ['1', '2', '3']], '2'],
			[['values' => ['one', 'two', 'three']], 'one'],
		];
	}

	public function invalidValuesProvider() {
		return [
			[
				['messageInvalid' => 'Incorrect value type'],
				null,
				'Incorrect value type'
			],
			[
				['messageInvalid' => 'Incorrect value type'],
				true,
				'Incorrect value type'
			],
			[
				['messageInvalid' => 'Incorrect value type'],
				[],
				'Incorrect value type'
			],
			[
				['messageInvalid' => 'Incorrect value type'],
				1.1,
				'Incorrect value type'
			],
			[
				['values' => [1, 2, 3], 'messageInvalid' => 'Incorrect value "%1$s"'],
				4,
				'Incorrect value "4"'
			],
			[
				['values' => ['one', 'two', 'three'], 'messageInvalid' => 'Incorrect value "%1$s"'],
				'four',
				'Incorrect value "four"'
			],
			[
				['values' => ['one', 'two', 'three'], 'messageInvalid' => 'Incorrect value "%1$s"'],
				'FOUR',
				'Incorrect value "FOUR"'
			],
		];
	}

	public function invalidValuesWithObjectsProvider() {
		return [
			[
				['messageInvalid' => 'Incorrect value type for "%1$s"'],
				null,
				'Incorrect value type for "object"'
			],
			[
				['messageInvalid' => 'Incorrect value type for "%1$s"'],
				true,
				'Incorrect value type for "object"'
			],
			[
				['messageInvalid' => 'Incorrect value type for "%1$s"'],
				[],
				'Incorrect value type for "object"'
			],
			[
				['messageInvalid' => 'Incorrect value type for "%1$s"'],
				1.1,
				'Incorrect value type for "object"'
			],
			[
				['values' => [1, 2, 3], 'messageInvalid' => 'Incorrect value "%2$s" for "%1$s"'],
				4,
				'Incorrect value "4" for "object"'
			],
			[
				['values' => ['one', 'two', 'three'], 'messageInvalid' => 'Incorrect value "%2$s" for "%1$s"'],
				'four',
				'Incorrect value "four" for "object"'
			],
			[
				['values' => ['one', 'two', 'three'], 'messageInvalid' => 'Incorrect value "%2$s" for "%1$s"'],
				'FOUR',
				'Incorrect value "FOUR" for "object"'
			],
		];
	}

	protected function createValidator(array $params = []) {
		return new CLimitedSetValidator($params);
	}
}

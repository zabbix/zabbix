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


class CCollectionValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return [
			[[
				'empty' => true,
				'uniqueField' => 'field',
				'uniqueField2' => 'field',
				'messageInvalid' => 'Not an array',
				'messageEmpty' => 'Empty collection',
				'messageDuplicate' => 'Collection has duplicate values',
			]]
		];
	}

	public function validValuesProvider() {
		return [
			[
				[],
				[1, 2, 3]
			],
			[
				['empty' => true],
				[]
			],
			[
				['uniqueField' => 'type'],
				[
					['type' => 1],
					['type' => 2],
					['type' => 3]
				]
			],
			[
				['uniqueField' => 'type', 'uniqueField2' => 'subtype'],
				[
					['type' => 1, 'subtype' => 1],
					['type' => 1, 'subtype' => 2],
					['type' => 2, 'subtype' => 1],
					['type' => 2, 'subtype' => 2],
				]
			],
			[
				['uniqueField' => 'type', 'uniqueField2' => null],
				[
					['type' => 1],
					['type' => 2],
					['type' => 3]
				]
			]
		];
	}

	public function invalidValuesProvider() {
		return [
			[
				['messageInvalid' => 'Not an array'],
				'',
				'Not an array'
			],
			[
				['messageInvalid' => 'Not an array'],
				0,
				'Not an array'
			],
			[
				['messageInvalid' => 'Not an array'],
				null,
				'Not an array'
			],
			[
				['messageEmpty' => 'Empty collection'],
				[],
				'Empty collection'
			],
			[
				['uniqueField' => 'type', 'messageDuplicate' => 'Duplicate type "%1$s"'],
				[
					['type' => 1],
					['type' => 1],
					['type' => 3]
				],
				'Duplicate type "1"'
			],
			[
				['uniqueField' => 'type', 'uniqueField2' => 'subtype',
					'messageDuplicate' => 'Duplicate type "%1$s" and subtype "%2$s"'],
				[
					['type' => 1, 'subtype' => 1],
					['type' => 1, 'subtype' => 2],
					['type' => 2, 'subtype' => 2],
					['type' => 2, 'subtype' => 2],
				],
				'Duplicate type "2" and subtype "2"'
			],
		];
	}

	public function invalidValuesWithObjectsProvider() {
		return [
			[
			['messageInvalid' => 'Not an array for "%1$s"'],
				'',
				'Not an array for "object"'
			],
			[
				['messageInvalid' => 'Not an array for "%1$s"'],
				0,
				'Not an array for "object"'
			],
			[
				['messageInvalid' => 'Not an array for "%1$s"'],
				null,
				'Not an array for "object"'
			],
			[
				['messageEmpty' => 'Empty collection for "%1$s"'],
				[],
				'Empty collection for "object"'
			],
			[
				['uniqueField' => 'type', 'messageDuplicate' => 'Duplicate type "%2$s" for "%1$s"'],
				[
					['type' => 1],
					['type' => 1],
					['type' => 3]
				],
				'Duplicate type "1" for "object"'
			],
			[
				['uniqueField' => 'type', 'uniqueField2' => 'subtype',
					'messageDuplicate' => 'Duplicate type "%2$s" and subtype "%3$s" for "%1$s"'],
				[
					['type' => 1, 'subtype' => 1],
					['type' => 1, 'subtype' => 2],
					['type' => 2, 'subtype' => 2],
					['type' => 2, 'subtype' => 2],
				],
				'Duplicate type "2" and subtype "2" for "object"'
			],
		];
	}

	protected function createValidator(array $params = []) {
		return new CCollectionValidator($params);
	}
}

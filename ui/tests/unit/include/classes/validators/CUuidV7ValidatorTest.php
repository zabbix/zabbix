<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


namespace unit\include\classes\validators;

use CUuidV7Validator;
use PHPUnit\Framework\TestCase;

class CUuidV7ValidatorTest extends TestCase {

	/**
	 * An array of uuids v7, results and error messages.
	 */
	public function dataProvider() {
		return [
			// Valid uuids.
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94ab', true, null],
			['018f8a67-b9c4-7b12-9a1f-123456789abc', true, null],
			['018f8a67-b9c4-7c12-9b2f-abcdefabcdef', true, null],
			['018f8a67-b9c4-7d12-98ff-001122334455', true, null],
			['018f8a67-b9c4-7e12-99aa-998877665544', true, null],
			['018f8a67-b9c4-7f12-88bb-ccddeeff0011', true, null],
			['018f8a67-b9c4-7012-89ab-cdefabcdef12', true, null],

			// Valid uppercase uuids.
			['018F8A67-B9C4-7A12-8C7F-8FDBDE4C94AB', true, null],
			['018F8A67-B9C4-7B12-9A1F-123456789ABC', true, null],
			['018F8A67-B9C4-7C12-9B2F-ABCDEFABCDEF', true, null],

			// Invalid uuid's length.
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94a', false, 'must be 36 characters long'],
			['018f8a67b9c47a128c7f8fdbde4c94ab', false, 'must be 36 characters long'],
			['018f8a67-b9c4-7a12', false, 'must be 36 characters long'],
			['018f8a67-b9c4-7a12-8c7f8fdbde4c94ab00', false, 'must be 36 characters long'],
			['', false, 'must be 36 characters long'],

			// Invalid (non-hex) characters.
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94ag', false, 'a hyphenated UUID is expected'],
			['018f8a6-7b9c4-7a12-8c7f8fd-bde4c94az', false, 'a hyphenated UUID is expected'],
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94a_', false, 'a hyphenated UUID is expected'],
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94a-', false, 'a hyphenated UUID is expected'],
			['018f8a67-b9c4-7a12-8c7f-8fdbde4c94a!', false, 'a hyphenated UUID is expected'],

			// Invalid version.
			['018f8a67-b9c4-6a12-8c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-5a12-8c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-4a12-8c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-1a12-8c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-0a12-8c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],

			// Invalid variant.
			['018f8a67-b9c4-7a12-7c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-7a12-3c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-7a12-0c7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-7a12-fc7f-8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67-b9c4-7a12-dc7f-8fdbde4c94ab', false, 'a UUIDv7 is expected']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testValidateUuidV7($uuid, $expected_result, $expected_error) {
		$uuid_v7_validator = new CUuidV7Validator();
		$result = $uuid_v7_validator->validate($uuid);
		$this->assertSame($expected_result, $result);
		$this->assertSame($expected_error, $uuid_v7_validator->getError());
	}
}

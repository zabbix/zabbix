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
			['018f8a67b9c47a128c7f8fdbde4c94ab', true, null],
			['018f8a67b9c47b129a1f123456789abc', true, null],
			['018f8a67b9c47c129b2fabcdefabcdef', true, null],
			['018f8a67b9c47d1298ff001122334455', true, null],
			['018f8a67b9c47e1299aa998877665544', true, null],
			['018f8a67b9c47f1288bbccddeeff0011', true, null],
			['018f8a67b9c4701289abcdefabcdef12', true, null],

			// Valid uppercase uuids.
			['018F8A67B9C47A128C7F8FDBDE4C94AB', true, null],
			['018F8A67B9C47B129A1F123456789ABC', true, null],
			['018F8A67B9C47C129B2FABCDEFABCDEF', true, null],

			// Invalid uuid's length.
			['018f8a67b9c47a128c7f8fdbde4c94a', false, 'must be 32 characters long'],
			['018f8a67b9c47a128c7f8fdbde4c94abb', false, 'must be 32 characters long'],
			['018f8a67b9c47a12', false, 'must be 32 characters long'],
			['018f8a67b9c47a128c7f8fdbde4c94ab00', false, 'must be 32 characters long'],
			['', false, 'must be 32 characters long'],

			// Invalid (non-hex) characters.
			['018f8a67b9c47a128c7f8fdbde4c94ag', false, 'must contain only hexadecimal characters'],
			['018f8a67b9c47a128c7f8fdbde4c94az', false, 'must contain only hexadecimal characters'],
			['018f8a67b9c47a128c7f8fdbde4c94a_', false, 'must contain only hexadecimal characters'],
			['018f8a67b9c47a128c7f8fdbde4c94a-', false, 'must contain only hexadecimal characters'],
			['018f8a67b9c47a128c7f8fdbde4c94a!', false, 'must contain only hexadecimal characters'],

			// Invalid version.
			['018f8a67b9c46a128c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c45a128c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c44a128c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c41a128c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c40a128c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],

			// Invalid variant.
			['018f8a67b9c47a127c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c47a123c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c47a120c7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c47a12fc7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
			['018f8a67b9c47a12dc7f8fdbde4c94ab', false, 'a UUIDv7 is expected'],
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testValidateEmail($uuid, $expected, $error) {
		$uuid_v7_validator = new CUuidV7Validator();
		$result = $uuid_v7_validator->validate($uuid);
		$this->assertSame($result, $expected);
		$this->assertSame($uuid_v7_validator->getError(), $error);
	}
}

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

class CHostGroupNameValidatorTest extends TestCase {
	public function dataProvider(): array {
		// Host group or host group prototype name, array of options, error message (or null if no error).
		return [
			// Valid host group names.
			['valid-host-group-name',				[],						null],
			['    v4l1d   h05T  +  гроуп наме   ',	[],						null],
			['valid/nested/name',					[],						null],
			['valid-{#HOSTGROUP}-name',				[],						null],
			['ʕ·͡ᴥ·ʔ 😍',							[],						null],

			// Invalid host group names.
			['invalid/nested/name/',				[],						'Incorrect characters used for host group name.'],

			// Valid host group prototype names.
			['valid-{#HOSTGROUPPROTOTYPE}-name',	['lldmacros' => true],	null],
			['valid-{{#HOSTGROUPPROTOTYPE}.regsub("^([a-z]+)", \1)}-name',	['lldmacros' => true],  null],
			['valid/{#HOSTGROUPPROTOTYPE}/name',	['lldmacros' => true],	null],

			// // Invalid host prototype names.
			['invalid/{#HOSTGROUPPROTOTYPE}/name/',	['lldmacros' => true],	'Incorrect characters used for host group name.'],
			['invalid-host-group-prototype-name',	['lldmacros' => true],	'This field must contain at least one low-level discovery macro.']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testHostGroupName($name, $options, $expected_error): void {
		$validator = new CHostGroupNameValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}

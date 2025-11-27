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

class CHostNameValidatorTest extends TestCase {
	public function dataProvider(): array {
		// Host or host prototype name, array of options, error message (or null if no error).
		return [
			// Valid host names.
			['valid-host-name',						[],						null],
			['vAl1d  -   host.n4mE',				[],						null],

			// Invalid host names.
			['   [in]valid.host.name   ',			[],						'Incorrect characters used for host name.'],
			['invalid-{#HOST}-name',				[],						'Incorrect characters used for host name.'],

			// Valid host prototype names.
			['valid-{#HOSTPROTOTYPE}-name',			['lldmacros' => true],	null],
			['valid-{{#HOSTPROTOTYPE}.regsub("^([a-z]+)", \1)}-name',	['lldmacros' => true],	null],
			['vAl1d  -  {#HOSTPROTOTYPE} .  n4mE',	['lldmacros' => true],	null],

			// Invalid host prototype names.
			['[in]valid {#HOSTPROTOTYPE} + name',	['lldmacros' => true],	'Incorrect characters used for host name.'],
			['invalid-hostprototype-name',			['lldmacros' => true],	'This field must contain at least one low-level discovery macro.']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testHostName($name, $options, $expected_error): void {
		$validator = new CHostNameValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($name));
		$this->assertSame($expected_error, $validator->getError());
	}
}

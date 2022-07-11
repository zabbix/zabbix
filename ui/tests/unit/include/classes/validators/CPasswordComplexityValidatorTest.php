<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class CPasswordComplexityValidatorTest extends TestCase {

	/**
	 * Array of passwords, minimum password lengths and expected error messages of password complexity validation.
	 */
	public function dataProviderCheckPasswordsLength(): array {
		return [
			['', 0, ''],
			['', 1, 'must be at least 1 characters long'],
			['a', 1, ''],
			['a', 2, 'must be at least 2 characters long'],
			['abc', 2, '']
		];
	}

	/**
	 * @dataProvider dataProviderCheckPasswordsLength
	 */
	public function testPasswordsLength($password, $passwd_min_length, $expected_error): void {
		$validator = new CPasswordComplexityValidator([
			'passwd_min_length' => $passwd_min_length,
			'passwd_check_rules' => 0x00
		]);

		$expected = ($expected_error === '');
		$this->assertEquals($expected, $validator->validate($password));
		$this->assertSame($expected_error, $validator->getError());
	}

	/**
	 * Array of passwords, validation rules, context data and expected error messages of password complexity validation.
	 */
	public function dataProviderCheckPasswordsRules(): array {
		return [
			// Check upper/lower case.
			['abc', 0, [], ''],
			['Abc', 0, [], ''],
			['abc', PASSWD_CHECK_CASE, [], 'must contain at least one lowercase and one uppercase Latin letter'],
			['Abc', PASSWD_CHECK_CASE, [], ''],
			// Check digits.
			['123', 0, [], ''],
			['abc', PASSWD_CHECK_DIGITS, [], 'must contain at least one digit'],
			['123', PASSWD_CHECK_DIGITS, [], ''],
			['123', PASSWD_CHECK_DIGITS, [], ''],
			['ab3', PASSWD_CHECK_DIGITS, [], ''],
			// Check special characters.
			['!@#', 0, [], ''],
			['abc', PASSWD_CHECK_SPECIAL, [], 'must contain at least one special character'],
			['Ab1', PASSWD_CHECK_SPECIAL, [], 'must contain at least one special character'],
			['Ab!', PASSWD_CHECK_SPECIAL, [], ''],
			['!@#', PASSWD_CHECK_SPECIAL, [], ''],
			// Check context data.
			['admin', 0, ['admin'], ''],
			['5a$eAdminpassw', PASSWD_CHECK_SIMPLE, ['Admin'], 'must not contain user\'s name, surname or username'],
			// Check top used passwords.
			['qwerty', 0, [], ''],
			['qwerty', PASSWD_CHECK_SIMPLE, [], 'must not be one of common or context-specific passwords'],
			['zabbix', PASSWD_CHECK_SIMPLE, [], 'must not be one of common or context-specific passwords']
		];
	}

	/**
	 * @dataProvider dataProviderCheckPasswordsRules
	 */
	public function testPasswordsValidationRules($password, $rules, $context_data, $expected_error): void {
		$validator = new CPasswordComplexityValidator([
			'passwd_min_length' => 3,
			'passwd_check_rules' => $rules
		]);

		$validator->setContextData($context_data);

		$expected = ($expected_error === '');
		$this->assertEquals($expected, $validator->validate($password));
		$this->assertSame($expected_error, $validator->getError());
	}
}

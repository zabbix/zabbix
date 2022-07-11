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

class CEmailValidatorTest extends TestCase {

	/**
	 * An array of e-mails, results and error messages.
	 */
	public function dataProvider() {
		return [
			// Valid e-mails.
			['email@example.com', true, null],
			['email@subdomain.example.com', true, null],
			['firstname.lastname@example.com', true, null],
			['firstname+lastname@example.com', true, null],
			['firstname-lastname@example.com', true, null],
			['1234567890@example.com', true, null],
			['email@[127.0.0.1]', true, null],
			['"email"@example.com', true, null],
			['___-___@example.com', true, null],
			['admin@another-example.com', true, null],
			['Administrator <admin@example.com>', true, null],
			['much."more\ unusual"@example.com', true, null],
			['very."(),:;<>[]".VERY."very@\\very".unusual@strange.example.com', true, null],
			// Invalid e-mails.
			['plainaddress', false, 'Invalid email address "plainaddress".'],
			['email@example', false, 'Invalid email address "email@example".'],
			['example.com', false, 'Invalid email address "example.com".'],
			['.email@example.com', false, 'Invalid email address ".email@example.com".'],
			['email.@example.com', false, 'Invalid email address "email.@example.com".'],
			['email..email@example.com', false, 'Invalid email address "email..email@example.com".'],
			['email@example..com', false, 'Invalid email address "email@example..com".'],
			['email@example@example.com', false, 'Invalid email address "email@example@example.com".'],
			['email@example.com (John Doe)', false, 'Invalid email address "email@example.com (John Doe)".'],
			['Administrator (email@example.com)', false, 'Invalid email address "Administrator (email@example.com)".'],
			['@example.com', false, 'Invalid email address "@example.com".'],
			['email@-example.com', false, 'Invalid email address "email@-example.com".'],
			['Abc..123@example.com', false, 'Invalid email address "Abc..123@example.com".'],
			['#@%^%#$@#$@#.com', false, 'Invalid email address "#@%^%#$@#$@#.com".'],
			['あいうえお@example.com', false, 'Invalid email address "あいうえお@example.com".'],
			['admin@localhost', false, 'Invalid email address "admin@localhost".'],
			['admin@127.0.0.1', false, 'Invalid email address "admin@127.0.0.1".'],
			['"(),:;<>[\]@example.com', false, 'Invalid email address ""(),:;<>[\]@example.com".'],
			['just"not"right@example.com', false, 'Invalid email address "just"not"right@example.com".'],
			['this\ is"really"not\allowed@example.com', false, 'Invalid email address "this\ is"really"not\allowed@example.com".'],
			['Administrator <admin@example.com>, a, b, c', false, 'Invalid email address "Administrator <admin@example.com>, a, b, c".']
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testValidateEmail($email, $expected, $error) {
		$email_validator = new CEmailValidator();
		$result = $email_validator->validate($email);
		$this->assertSame($result, $expected);
		$this->assertSame($email_validator->getError(), $error);
	}
}

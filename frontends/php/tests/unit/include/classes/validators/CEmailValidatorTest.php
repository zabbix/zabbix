<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CEmailValidatorTest extends PHPUnit_Framework_TestCase {

	public function providerValidateEmail() {
		return [
			['admin@example.com',					true],
			['Administrator <admin@example.com>',	true],
			['cool.admin@example.com',				true],
			['cool.admin@just.example.com',			true],
			['cool+admin@example.com',				true],
			['cool-admin@example.com',				true],
			['___-___@example.com',					true],
			['noemailaddresshere',					false],
			['cool@admin@example.com',				false],
			['@example.com',						false],
			['Administrator (admin@example.com)',	false],
			['example.com',							false],
			['admin@localhost',						false],
			['admin@localhost',						false],
			['admin@127.0.0.1',						false],
		];
	}

	/**
	 * @dataProvider providerValidateEmail
	 */
	public function test_validateURL($email, $expected) {
		$this->assertEquals((new CEmailValidator())->validate($email), $expected);
	}
}

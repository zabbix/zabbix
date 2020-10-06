<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CEventNameValidatorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var CEventNameValidator $validator
	 */
	protected $validator;

	public function setUp() {
		$this->validator = new CEventNameValidator();
	}

	/**
	 * An array of e-mails, results and error messages.
	 */
	public function testProvider() {
		return [
			['', true, null],
			['Macro except expression macro are ignored {ANY_MACRO_HERE}', true, null],
			['Incorrect macro except expression macro are ignored {ANY_MACRO_HERE_ {}', true, null],
			['Simple expression macro {?100+1} test', true, null],
			['Expression macro with modificator {{?100+1-(2)}.anyfunc(2)}', true, null],
			['Macro as host name {?{{HOST.HOST}:item.func(1)}}', true, null],
			['Expression macro with incorrect syntax {?123++321}', false, 'incorrect syntax near "+321}"'],
			['{?Expression macro without closing bracket', false, 'incorrect syntax near "Expression macro without closing bracket"'],
			['Expression macro without closing bracket at the end of event name {?', false, 'unexpected end of string'],
			['Nested expression macro not supported {?100+{?20+1}}', false, 'incorrect syntax near "{?20+1}}"'],
			['Empty expression macro {?}', false, 'incorrect syntax near "}"'],
		];
	}

	/**
	 * @dataProvider testProvider
	 */
	public function testValidateEmail($event_name, $expected, $error) {
		$this->assertSame($this->validator->validate($event_name), $expected);
		$this->assertSame($error, $this->validator->getError());
	}
}

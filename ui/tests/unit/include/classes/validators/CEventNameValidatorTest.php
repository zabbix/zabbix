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

class CEventNameValidatorTest extends TestCase {

	/**
	 * @var CEventNameValidator $validator
	 */
	protected $validator;

	protected function setUp(): void {
		$this->validator = new CEventNameValidator();
	}

	/**
	 * An array of e-mails, results and error messages.
	 */
	public function dataProvider() {
		return [
			['', true, null],
			['Macro except expression macro are ignored {ANY_MACRO_HERE}', true, null],
			['Incorrect macro except expression macro are ignored {ANY_MACRO_HERE_ {}', true, null],
			['Simple expression macro {?100+1} {{?100+1}} test', true, null],
			['Expression macro with modificator {{?100+1-(2)}.anyfunc(2)}', true, null],
			['Macro as host name {?func(/{HOST.HOST}/item)}', true, null],
			['Expression macro with incorrect syntax {?123++321}', false, 'incorrect expression starting from "+321}"'],
			['Missing closing curly bracket {?123+321', false, 'unexpected end of expression macro'],
			['{?Expression macro without closing bracket', false, 'incorrect expression starting from "Expression macro without closing bracket"'],
			['Expression macro without closing bracket at the end of event name {?', false, 'incorrect expression starting from ""'],
			['Nested expression macro not supported {?100+{?20+1}}', false, 'incorrect expression starting from "{?20+1}}"'],
			['Empty expression macro {?}', false, 'incorrect expression starting from "}"'],
			['Function value macro {FUNCTION.VALUE} {FUNCTION.VALUE1} {?{FUNCTION.VALUE}+{FUNCTION.VALUE9}}', true, null],
			['Function value macro {FUNCTION.RECOVERY.VALUE} {FUNCTION.RECOVERY.VALUE1} {?{FUNCTION.RECOVERY.VALUE}+{FUNCTION.RECOVERY.VALUE9}}', true, null],
			['Function value macro {{?{FUNCTION.VALUE}}.regsub("{?", "a")}{{?{FUNCTION.VALUE}}.regsub("{?", "a")}', true, null]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testValidateEmail($event_name, $expected, $error) {
		$this->assertSame($this->validator->validate($event_name), $expected);
		$this->assertSame($error, $this->validator->getError());
	}
}

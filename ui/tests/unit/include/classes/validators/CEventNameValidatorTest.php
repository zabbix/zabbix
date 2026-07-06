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


use PHPUnit\Framework\TestCase;

class CEventNameValidatorTest extends TestCase {

	/**
	 * An array of event names, validator options and error messages
	 */
	public function dataProvider() {
		return [
			['', [], null],
			['Macro except expression macro are ignored {ANY_MACRO_HERE}', [], null],
			['Incorrect macro except expression macro are ignored {ANY_MACRO_HERE_ {}', [], null],
			['Simple expression macro {?100+1} {{?100+1}} test', [], null],
			['Expression macro with modificator {{?100+1-(2)}.anyfunc(2)}', [], null],
			['Macro as host name {?func(/{HOST.HOST}/item)}', [], null],
			['Expression macro with incorrect syntax {?123++321}', [], 'incorrect expression starting from "+321}"'],
			['Missing closing curly bracket {?123+321', [], 'unexpected end of expression macro'],
			['{?Expression macro without closing bracket',
				[], 'incorrect expression starting from "Expression macro without closing bracket"'
			],
			['Expression macro without closing bracket at the end of event name {?',
				[], 'incorrect expression starting from ""'
			],
			['Nested expression macro not supported {?100+{?20+1}}',
				[], 'incorrect expression starting from "{?20+1}}"'
			],
			['Empty expression macro {?}', [], 'incorrect expression starting from "}"'],
			['Function value macro {FUNCTION.VALUE} {FUNCTION.VALUE1} {?{FUNCTION.VALUE}+{FUNCTION.VALUE9}}',
				[], null
			],
			['Function value macro {FUNCTION.RECOVERY.VALUE} {FUNCTION.RECOVERY.VALUE1} {?{FUNCTION.RECOVERY.VALUE}+{FUNCTION.RECOVERY.VALUE9}}',
				[], null
			],
			['Function value macro {{?{FUNCTION.VALUE}}.regsub("{?", "a")}{{?{FUNCTION.VALUE}}.regsub("{?", "a")}',
				[], null
			],
			['test {?last(/Zabbix server/zabbix[wcache,index,pused])}', ['hostnames' => ['Zabbix server']], null],
			['test {?last(/{HOST.HOST}/zabbix[wcache,index,pused])}', ['hostnames' => ['Zabbix server']], null],
			['test {?last(/{HOST.HOST9}/zabbix[wcache,index,pused])}', [], null],
			['test {?last(/{HOST.HOST10}/zabbix[wcache,index,pused])}', [],
				'incorrect expression starting from "last(/{HOST.HOST10}/zabbix[wcache,index,pused])}"'
			],
			['test {?last(/{HOST.HOST9}/zabbix[wcache,index,pused])}', ['hostnames' => ['Zabbix server']], null],
			['test {?last(/Zabbix server/zabbix[wcache,index,pused])}',
				['hostnames' => ['Zabbix server2']], 'host "Zabbix server" is not allowed in event name'
			],
			['test {?last(/Zabbix server/zabbix[wcache,index,pused])}',
				['hostnames' => ['Zabbix server2'], 'message_hostnames' => 'custom message'], 'custom message'
			],
			['test {?last(//zabbix[wcache,index,pused])}',
				['hostnames' => ['Zabbix server2'], 'message_hostnames' => 'custom message'], null
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testEventNameValidator($event_name, $options, $expected_error): void {
		$validator = new CEventNameValidator($options);

		$expected_result = $expected_error === null;
		$this->assertEquals($expected_result, $validator->validate($event_name));
		$this->assertSame($expected_error, $validator->getError());
	}
}

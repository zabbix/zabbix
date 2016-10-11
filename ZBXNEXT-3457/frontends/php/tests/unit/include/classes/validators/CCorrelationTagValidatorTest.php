<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


/**
 * Class containing tests for CCorrelationTagValidator class functionality.
 */
class CCorrelationTagValidatorTest extends PHPUnit_Framework_TestCase {

	public function testProvider() {
		return [
			['tag', [
				'rc' => true,
				'error' => ''
			]],
			['{$MACRO: /}', [
				'rc' => true,
				'error' => ''
			]],
			['abc{$MACRO: /}def', [
				'rc' => true,
				'error' => ''
			]],
			['{h:k[/].f()}', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]],
			['{{ITEM.VALUE}.regsub("CLASS:([a-zA-Z0-9/]+)","\1")}', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]],
			['abc{{ITEM.VALUE}.regsub("CLASS:([a-zA-Z0-9/]+)","\1")}def', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]],
			['', [
				'rc' => false,
				'error' => 'cannot be empty'
			]],
			['/', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]],
			['aa/aa', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]],
			['abc{{ITEM.LASTVALUE}.regsub("CLASS:([a-zA-Z0-9/]+)","\1")}def', [
				'rc' => false,
				'error' => 'unacceptable characters are used'
			]]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $value
	 * @param array  $expected
	*/
	public function testTagValidator($value, $expected) {
		$validator = new CTagValidator(['item_macros' => false]);

		$rc = $validator->validate($value);

		$this->assertSame($expected, [
			'rc' => $rc,
			'error' => $validator->getError()
		]);
	}
}

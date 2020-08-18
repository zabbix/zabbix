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


class CMacrosResolverTest extends PHPUnit_Framework_TestCase {

	private $stub;

	public function setUp() {
		$get_user_macros = [
			30896 => [
				'hostids' => [
					0 => 10084
				],
				'macros' => [
					'{$TEST}' => 'test123'
				]
			],
			29164 => [
				'hostids' => [
					0 => 10084
				],
				'macros' => [
					'{$UNKNOWN_MACRO}' => '{$UNKNOWN_MACRO}'
				]
			]
		];

		/** @var $stub CMacrosResolver */
		$this->stub = $this->getMockBuilder(CMacrosResolver::class)
			->setMethods(['getUserMacros'])
			->getMock();

		$this->stub->method('getUserMacros')
			->willReturn($get_user_macros);
	}

	public function dataProviderInput() {
		return [
			[
				[
					30896 => [
						'itemid' => 30896,
						'hostid' => 10084,
						'name' => 'TEST',
						'key_' => 'test_test_test',
						'description' => 'aaaaaaaaaaa {$TEST} bbbbbbbbbbbb {$TEST}'
					]
				],
				[
					30896 => [
						'itemid' => 30896,
						'hostid' => 10084,
						'name' => 'TEST',
						'key_' => 'test_test_test',
						'description' => 'aaaaaaaaaaa test123 bbbbbbbbbbbb test123'
					]
				]
			],
			[
				[
					29164 => [
						'itemid' => 29164,
						'hostid' => 10084,
						'name' => 'TEST2',
						'key_' => 'test_test_test2',
						'description' => 'aaaaaaaaaaa {$UNKNOWN_MACRO}'
					]
				],
				[
					29164 => [
						'itemid' => 29164,
						'hostid' => 10084,
						'name' => 'TEST2',
						'key_' => 'test_test_test2',
						'description' => 'aaaaaaaaaaa {$UNKNOWN_MACRO}'
					]
				]
			]
		];

	}

	/**
	 * @dataProvider dataProviderInput
	 */
	public function testResolveItemDescriptions($items, $expected_items) {
		$resolved = $this->stub->resolveItemDescriptions($items);

		$this->assertEquals($resolved, $expected_items);
	}
}

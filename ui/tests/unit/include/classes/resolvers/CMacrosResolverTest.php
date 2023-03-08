<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

class CMacrosResolverTest extends TestCase {

	private $stub;

	protected function setUp(): void {
		$user_macros = [
			30896 => [
				'hostids' => [10084],
				'macros' => [
					'{$TMG.PROXY.CHECK.URL1}' => 'http://zabbix.com',
					'{$CITY}' => 'Tokyo',
					'{$REGEX_OK}' => '^OK\\s+',
					'{$QUOTED_STRING}' => '"string"'
				]
			]
		];

		// Such mocking approach allows to mock protected class methods, but still will not work with private methods.
		/** @var $stub CMacrosResolver */
		$this->stub = $this->getMockBuilder(CMacrosResolver::class)
			->setMethods(['getUserMacros'])
			->getMock();

		$this->stub->method('getUserMacros')
			->willReturn($user_macros);
	}

	public function dataProviderInput() {
		return [
			'expand valid user macro' => [
				'item' => [
					30896 => [
						'hostid' => 10084,
						'description' => 'Response from {$TMG.PROXY.CHECK.URL1} through proxy in {$CITY}'
					]
				],
				'expected_item' => [
					30896 => [
						'hostid' => 10084,
						'description' => 'Response from {$TMG.PROXY.CHECK.URL1} through proxy in {$CITY}',
						'description_expanded' => 'Response from http://zabbix.com through proxy in Tokyo'
					]
				]
			],
			'leave unknown macros unresolved' => [
				'item' => [
					30896 => [
						'hostid' => 10084,
						'description' => 'Number of packages in {$UNKNOWN_MACRO}'
					]
				],
				'expected_item' => [
					30896 => [
						'hostid' => 10084,
						'description' => 'Number of packages in {$UNKNOWN_MACRO}',
						'description_expanded' => 'Number of packages in {$UNKNOWN_MACRO}'
					]
				]
			]
		];
	}

	public function dataProviderFunctionsInput() {
		return [
			'query and period should be not quoted' => [
				'functions' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1y',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				],
				'expected' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1y',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				]
			],
			'backslash is escaped in macro value' => [
				'functions' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 10h, "string", {$REGEX_OK}',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				],
				'expected' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 10h, "string", "^OK\\\\s+"',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				]
			],
			'no double quotes for quoted macro' => [
				'functions' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1d, "string", "{$CITY}"',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				],
				'expected' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1d, "string", "Tokyo"',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				]
			],
			'double quotes escaped when in macro value' => [
				'functions' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1w, {$QUOTED_STRING}',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				],
				'expected' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 1w, "\\"string\\""',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				]
			],
			'only quoted parameters stay quoted' => [
				'functions' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 100h:now/h-10h, 100h, 2h, 2.1, "mad"',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				],
				'expected' => [
					30896 => [
						'parameter'	=> TRIGGER_QUERY_PLACEHOLDER.', 100h:now/h-10h, 100h, 2h, 2.1, "mad"',
						'function'	=> 'func',
						'hostid'	=> 10084
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInput
	 */
	public function testResolveItemDescriptions($item, $expected_item) {
		$resolved_item = $this->stub->resolveItemDescriptions($item);

		$this->assertEquals($resolved_item, $expected_item);
	}

	/**
	 * @dataProvider dataProviderFunctionsInput
	 */
	public function testResolveFunctionParameters($functions, $expected) {
		$resolved = $this->stub->resolveFunctionParameters($functions);

		$this->assertEquals($expected, $resolved);
	}
}

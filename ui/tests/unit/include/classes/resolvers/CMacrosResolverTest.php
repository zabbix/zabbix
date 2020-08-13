<?php

class CMacrosResolverTest extends PHPUnit_Framework_TestCase {
	public function testResolveItemDescriptions() {
		$items = [
			30896 => [
				'itemid' => 30896,
				'type' => 0,
				'hostid' => 10084,
				'name' => 'TEST',
				'key_' => 'test_test_test',
				'delay' => '1m',
				'history' => '90d',
				'trends' => '365d',
				'status' => 0,
				'value_type' => 3,
				'units' => '',
				'valuemapid' => 0,
				'description' => 'aaaaaaaaaaa {$TEST} bbbbbbbbbbbb {$TEST}',
				'state' => 0,
				'error' => '',
				'key_expanded' => 'test_test_test',
				'name_expanded' => 'TEST'
			],
			29164 => [
				'itemid' => 29164,
				'type' => 0,
				'hostid' => 10084,
				'name' => 'TEST2',
				'key_' => 'test_test_test2',
				'delay' => '1m',
				'history' => '90d',
				'trends' => '365d',
				'status' => 0,
				'value_type' => 3,
				'units' => '',
				'valuemapid' => 0,
				'description' => 'aaaaaaaaaaa',
				'state' => 0,
				'error' => '',
				'key_expanded' => 'test_test_test2',
				'name_expanded' => 'TEST2'
			]
		];

		$_items = [
			30896 => [
				'itemid' => 30896,
				'type' => 0,
				'hostid' => 10084,
				'name' => 'TEST',
				'key_' => 'test_test_test',
				'delay' => '1m',
				'history' => '90d',
				'trends' => '365d',
				'status' => 0,
				'value_type' => 3,
				'units' => '',
				'valuemapid' => 0,
				'description' => 'aaaaaaaaaaa test123 bbbbbbbbbbbb test123',
				'state' => 0,
				'error' => '',
				'key_expanded' => 'test_test_test',
				'name_expanded' => 'TEST'
			],
			29164 => [
				'itemid' => 29164,
				'type' => 0,
				'hostid' => 10084,
				'name' => 'TEST2',
				'key_' => 'test_test_test2',
				'delay' => '1m',
				'history' => '90d',
				'trends' => '365d',
				'status' => 0,
				'value_type' => 3,
				'units' => '',
				'valuemapid' => 0,
				'description' => 'aaaaaaaaaaa',
				'state' => 0,
				'error' => '',
				'key_expanded' => 'test_test_test2',
				'name_expanded' => 'TEST2'
			]
		];

		$test = [
			30896 => [
				'hostids' => [
					0 => 10084
				],
				'macros' => [
					'{$TEST}' => 'test123'
				]
			],
			29164 => [
				'hostids' => [],
				'macros' => []
			]
		];

		/** @var $stub CMacrosResolver */
		$stub = $this->createMock(CMacrosResolver::class);
		$stub->method('getUserMacros')
			->willReturn($test);

		$resolved = $stub->resolveItemDescriptions($items);

		$this->assertEquals($resolved, $_items);
	}
}

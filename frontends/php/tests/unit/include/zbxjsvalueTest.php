<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once __DIR__.'/../../../include/js.inc.php';

class CzbxjsvalueTest extends PHPUnit_Framework_TestCase {

	public static function testProvider() {
		$return_as_object = true;

		return [
			[
				$return_as_object,
				true,
				'true'
			],
			[
				!$return_as_object,
				true,
				'true'
			],
			[
				$return_as_object,
				null,
				'null'
			],
			[
				!$return_as_object,
				null,
				'null'
			],
			[
				$return_as_object,
				-10,
				'-10'
			],
			[
				!$return_as_object,
				-10,
				'-10'
			],
			[
				$return_as_object,
				100,
				'100'
			],
			[
				!$return_as_object,
				100,
				'100'
			],
			[
				$return_as_object,
				[],
				'{}'
			],
			[
				!$return_as_object,
				[],
				'[]'
			],
			[
				$return_as_object,
				'',
				"''"
			],
			[
				!$return_as_object,
				'',
				"''"
			],
			[
				$return_as_object,
				['key-with-dash' => 'value'],
				'{"key-with-dash":\'value\'}'
			],
			[
				!$return_as_object,
				['key-with-dash' => 'value'],
				'{"key-with-dash":\'value\'}'
			],
			[
				$return_as_object,
				['key-with-quotes-\'"' => 'value'],
				'{"key-with-quotes-\\\'\\"":\'value\'}'
			],
			[
				!$return_as_object,
				['key-with-quotes-\'"' => 'value'],
				'{"key-with-quotes-\\\'\\"":\'value\'}'
			],
			[
				$return_as_object,
				[-1 => ['agent_string', 'another_string']],
				'{"-1":{"0":\'agent_string\',"1":\'another_string\'}}'
			],
			[
				!$return_as_object,
				// JSON implementation will return sligtly different result :
				// 		{"-1":[\'agent_string\',\'another_string\']}
				[-1 => ['agent_string', 'another_string']],
				'[[\'agent_string\',\'another_string\']]'
			],
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param bool                  $as_object
	 * @param array|string|int|bool $source
	 * @param string                $expected
	*/
	public function testZbxJsvalue($as_object, $source, $expected) {
		$encoded = zbx_jsvalue($source, $as_object);

		$this->assertSame($expected, $encoded);
	}
}

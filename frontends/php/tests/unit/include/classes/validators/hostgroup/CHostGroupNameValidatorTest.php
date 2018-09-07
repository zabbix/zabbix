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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CHostGroupNameValidatorTest extends PHPUnit_Framework_TestCase {

	public function invalidNameProvider() {
		return [
			[
				'name' => [],
				'message' => 'must be a string'
			],
			[
				'name' => null,
				'message' => 'must be a string'
			],
			[
				'name' => false,
				'message' => 'must be a string'
			],
			[
				'name' => 1.2,
				'message' => 'must be a string'
			],
			[
				'name' => '',
				'message' => 'cannot be empty',
			],
			[
				'name' => '/',
				'message' => 'invalid group name "/"'
			],
			[
				'name' => '/A',
				'message' => 'invalid group name "/A"'
			],
			[
				'name' => '/AAA',
				'message' => 'invalid group name "/AAA"'
			],
			[
				'name' => 'A/',
				'message' => 'invalid group name "A/"'
			],
			[
				'name' => 'AAA/',
				'message' => 'invalid group name "AAA/"'
			],
			[
				'name' => '/AAA/',
				'message' => 'invalid group name "/AAA/"'
			],
			[
				'name' => 'A/B/',
				'message' => 'invalid group name "A/B/"'
			],
			[
				'name' => 'A/B/C/',
				'message' => 'invalid group name "A/B/C/"'
			],
			[
				'name' => 'A/B//C',
				'message' => 'invalid group name "A/B//C"'
			],
			[
				'name' => 'A/B/////C',
				'message' => 'invalid group name "A/B/////C"'
			],
			[
				'name' => 'A////B/C',
				'message' => 'invalid group name "A////B/C"'
			],
			[
				'name' => 'AAAA////BBB/C',
				'message' => 'invalid group name "AAAA////BBB/C"'
			],
			[
				'name' => 'AAAA/BBBB/////CCC',
				'message' => 'invalid group name "AAAA/BBBB/////CCC"'
			]
		];
	}

	/**
	 * @dataProvider invalidNameProvider
	 */
	public function testInvalidName($name, $message) {
		$validator = new CHostGroupNameValidator();

		$this->assertFalse($validator->validate($name));
		$this->assertEquals($message, $validator->getError());
	}
}

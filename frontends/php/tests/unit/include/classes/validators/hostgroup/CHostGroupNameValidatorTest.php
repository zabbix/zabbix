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
			[[
				[
					'name' => [],
					'message' => 'Incorrect value for field "name": must be a string.'
				],
				[
					'name' => null,
					'message' => 'Incorrect value for field "name": must be a string.'
				],
				[
					'name' => false,
					'message' => 'Incorrect value for field "name": must be a string.'
				],
				[
					'name' => 1.2,
					'message' => 'Incorrect value for field "name": must be a string.'
				],
				[
					'name' => '',
					'message' => 'Incorrect value for field "name": cannot be empty.',
				],
				[
					'name' => '/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/".'
				],
				[
					'name' => '/A',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/A".'
				],
				[
					'name' => '/AAA',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/AAA".'
				],
				[
					'name' => 'A/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/".'
				],
				[
					'name' => 'AAA/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/".'
				],
				[
					'name' => '/AAA/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/AAA/".'
				],
				[
					'name' => 'A/B/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/".'
				],
				[
					'name' => 'A/B/C/',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/".'
				],
				[
					'name' => 'A/B//C',
					'message' => 'Incorrect value for field "name": incorrect syntax near "//C".'
				],
				[
					'name' => 'A/B/////C',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/////C".'
				],
				[
					'name' => 'A////B/C',
					'message' => 'Incorrect value for field "name": incorrect syntax near "////B/C".'
				],
				[
					'name' => 'AAAA////BBB/C',
					'message' => 'Incorrect value for field "name": incorrect syntax near "////BBB/C".'
				],
				[
					'name' => 'AAAA/BBBB/////CCC',
					'message' => 'Incorrect value for field "name": incorrect syntax near "/////CCC".'
				],
				[
					'name' => '*',
					'message' => 'Incorrect value for field "name": incorrect syntax near "*".'
				],
				[
					'name' => 'A*',
					'message' => 'Incorrect value for field "name": incorrect syntax near "*".'
				],
				[
					'name' => 'A/*',
					'message' => 'Incorrect value for field "name": incorrect syntax near "*".'
				],
				[
					'name' => '*A',
					'message' => 'Incorrect value for field "name": incorrect syntax near "*A".'
				]
			]]
		];
	}

	/**
	 * @dataProvider invalidNameProvider
	 *
	 * @param array $groups					An array of invalid host group names and messages to validate.
	 * @param string $groups[]['name']		Invalid host group name.
	 * @param string $groups[]['message']	Expected error message.
	 */
	public function testInvalidName(array $groups) {
		$validator = new CHostGroupNameValidator();

		foreach ($groups as $group) {
			$this->assertFalse($validator->validate($group['name']));
			$this->assertEquals($group['message'], $validator->getError());
		}
	}
}

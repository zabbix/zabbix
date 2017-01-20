<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Class containing tests for CIPValidator class functionality.
 */
class CIPValidatorTest extends CValidatorTest {

	/**
	 * A set of valid constructor parameters.
	 *
	 * @return array
	 */
	public function validParamProvider() {
		return [
			[[]]
		];
	}

	/**
	 * A set of valid values.
	 *
	 * @return array
	 */
	public function validValuesProvider() {
		return [
			[[], '0.0.0.0'],
			[[], '255.255.255.255'],
			[[], '192.168.1.0'],
			[[], '2002:0:0:0:0:0:0:0'],
			[[], '2002:0:0:0:0:0:ffff:ffff'],
			[[], 'fe80:0:0:0:0:0:c0a8:100'],
			[[], 'fe80::c0a8:100']
		];
	}

	/**
	 * A set of invalid values.
	 *
	 * @return array
	 */
	public function invalidValuesProvider() {
		return [
			[[],
				null,
				'Invalid IP address "null": must be a string.'
			],
			[[],
				[],
				'Invalid IP address "array": must be a string.'
			],
			[[],
				'',
				'IP address cannot be empty.'
			],
			[[],
				'{$A}',
				'Invalid IP address "{$A}".'
			],
			[[],
				'321.654.987.456',
				'Invalid IP address "321.654.987.456".'
			],
			[[],
				'0:0:0:0:0:0:1438e:3dcc8',
				'Invalid IP address "0:0:0:0:0:0:1438e:3dcc8".'
			],
			[[],
				'0::::::7f00:',
				'Invalid IP address "0::::::7f00:".'
			],
			[[],
				'192.168.0.0/16',
				'Invalid IP address "192.168.0.0/16".'
			],
			[[],
				'192.168.0.0-255',
				'Invalid IP address "192.168.0.0-255".'
			]
		];
	}

	/**
	 * Since CIPValidator class does not use names in objects, this function is purely for correct syntax purpose.
	 *
	 * @return array
	 */
	public function invalidValuesWithObjectsProvider() {
		return [[
			[],
			null,
			'Invalid IP address "null": must be a string.'
		]];
	}

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	protected function createValidator(array $params = []) {
		return new CIPValidator($params);
	}
}

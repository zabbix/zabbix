<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class containing methods to test CIPRangeValidator class functionality.
 */
class CIPRangeValidatorTest extends CValidatorTest {

	/**
	 * A set of valid constructor parameters.
	 *
	 * @return array
	 */
	public function validParamProvider() {
		return array(
			array(array('ipMaxCount' => 65536))
		);
	}

	/**
	 * A set of valid values.
	 *
	 * @return array
	 */
	public function validValuesProvider() {
		return array(
			array(array(), '0.0.0.0'),
			array(array(), '255.255.255.255'),
			array(array(), '192.168.1.0'),
			array(array(), '2002:0:0:0:0:0:0:0'),
			array(array(), '2002:0:0:0:0:0:ffff:ffff'),
			array(array(), 'fe80:0:0:0:0:0:c0a8:100'),
			array(array(), 'fe80::c0a8:100'),
			array(array(), '0.0.0.0/0'),
			array(array(), '0.0.0.0/32'),
			array(array(), '192.168.255.0/30'),
			array(array(), '192.168.0-255.0-255'),
			array(array(), '0-255.0-255.0-255.0-255'),
			array(array(), '192.168.0.0/16,192.168.0.1'),
			array(array(), '192.168.0.1-127,192.168.2.1'),
			array(array(), 'fe80:0:0:0:0:0:c0a8:0/128'),
			array(array(), 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0'),
			array(array(), 'fe80::c0a8:0/112'),
			array(array(), 'fe80::c0a8:0/128'),
			array(array(), 'fe80:0:0:0:0:0:c0a8:0-ff'),
			array(array(), 'fe80::c0a8:0-ff'),
			array(array(), '0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff'),
			array(array('ipMaxCount' => 1), '255.255.255.254/32'),
			array(array('ipMaxCount' => 65536), '255.255.0.0/16'),
			array(array('ipMaxCount' => 65536), 'fe80:0:0:0:0:0:c0a8:0/112')
		);
	}

	/**
	 * A set of invalid values.
	 *
	 * @return array
	 */
	public function invalidValuesProvider() {
		return array(
			array(array(),
				null,
				'Invalid IP address range "null": must be a string.'
			),
			array(array(),
				array(),
				'Invalid IP address range "array": must be a string.'
			),
			array(array(),
				'',
				'IP range cannot be empty.'
			),
			array(array(),
				'192.168.0-255.0/30',
				'Invalid IP address "192.168.0-255.0/30".'
			),
			array(array(),
				'192.168.0-255.0-255/16-30',
				'Invalid IP address "192.168.0-255.0-255/16-30".'
			),
			array(array('ipMaxCount' => 65536),
				'0-255.0-255.0-255.0-255',
				'Invalid IP address range "0-255.0-255.0-255.0-255".'
			),
			array(array('ipMaxCount' => 65536),
				'0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff',
				'Invalid IP address range "0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff".'
			),
			array(array(),
				'{$A}',
				'Invalid IP address "{$A}".'
			),
			array(array(),
				'321.654.987.456',
				'Invalid IP address "321.654.987.456".'
			),
			array(array(),
				'321.654.987.456-456',
				'Invalid IP address range "321.654.987.456-456".'
			),
			array(array(),
				'192.168.443.0/432',
				'Invalid IP address "192.168.443.0/432".'
			),
			array(array('ipMaxCount' => 65536),
				'192.168.0.0/15',
				'Invalid network mask "192.168.0.0/15".'
			),
			array(array(),
				'192.168.0.0/16-30',
				'Invalid network mask "192.168.0.0/16-30".'
			),
			array(array('ipMaxCount' => 65536),
				'fe80:0:0:0:0:0:c0a8:0/111',
				'Invalid network mask "fe80:0:0:0:0:0:c0a8:0/111".'
			),
			array(array('ipMaxCount' => 65536),
				'fe80:0:0:0:0:0:c0a8:0/129',
				'Invalid network mask "fe80:0:0:0:0:0:c0a8:0/129".'
			),
			array(array('ipMaxCount' => 65536),
				'fe80::c0a8:0/111',
				'Invalid network mask "fe80::c0a8:0/111".'
			),
			array(array('ipMaxCount' => 65536),
				'fe80::c0a8:0/129',
				'Invalid network mask "fe80::c0a8:0/129".'
			)
		);
	}

	/**
	 * Since CIPRangeValidator class does not use names in objects, this function is purely for correct syntax purpose.
	 *
	 * @return array
	 */
	public function invalidValuesWithObjectsProvider() {
		return array(array(
			array(),
			null,
			'Invalid IP address range "null": must be a string.'
		));
	}

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	protected function createValidator(array $params = array()) {
		return new CIPRangeValidator($params);
	}
}

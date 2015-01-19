<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		return array(array(array('ipRangeLimit' => 65536)));
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
			array(array(), '0.0.0.0/30'),
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
			array(array('ipRangeLimit' => 4), '255.255.255.254/30'),
			array(array('ipRangeLimit' => 65536), '255.255.0.0/16'),
			array(array('ipRangeLimit' => 65536), 'fe80:0:0:0:0:0:c0a8:0/112'),
			array(array('ipRangeLimit' => 131072), '255.254.0.0/15'),
			array(array('ipRangeLimit' => 262144), '255.252.0.0/14'),
			array(array('ipRangeLimit' => 524288), '255.248.0.0/13'),
			array(array('ipRangeLimit' => 1048576), '255.240.0.0/12'),
			array(array('ipRangeLimit' => 2097152), '255.224.0.0/11'),
			array(array('ipRangeLimit' => 4194304), '255.192.0.0/10'),
			array(array('ipRangeLimit' => 8388608), '255.128.0.0/9'),
			array(array('ipRangeLimit' => 16777216), '255.0.0.0/8'),
			array(array('ipRangeLimit' => 268435456), '64.0.0.0/4'),
			array(array('ipRangeLimit' => 2147483648), '0.0.0.0/1'),
			array(array('ipRangeLimit' => 4294967296), '0.0.0.0/0')
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
				'IP address range cannot be empty.'
			),
			array(array(),
				'192.168.0-255.0/30',
				'Invalid IP address range "192.168.0-255.0/30".'
			),
			array(array(),
				'192.168.0-255.0-255/16-30',
				'Invalid IP address range "192.168.0-255.0-255/16-30".'
			),
			array(array('ipRangeLimit' => 65536),
				'0-255.0-255.0-255.0-255',
				'IP range "0-255.0-255.0-255.0-255" exceeds "65536" address limit.'
			),
			array(array('ipRangeLimit' => 65536),
				'0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff',
				'IP range "0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff"'.
				' exceeds "65536" address limit.'
			),
			array(array(),
				'{$A}',
				'Invalid IP address range "{$A}".'
			),
			array(array(),
				'321.654.987.456',
				'Invalid IP address range "321.654.987.456".'
			),
			array(array(),
				'321.654.987.456-456',
				'Invalid IP address range "321.654.987.456-456".'
			),
			array(array(),
				'192.168.443.0/432',
				'Invalid IP address range "192.168.443.0/432".'
			),
			array(array('ipRangeLimit' => 65536),
				'192.168.0.0/15',
				'IP range "192.168.0.0/15" exceeds "65536" address limit.'
			),
			array(array(),
				'192.168.0.0/16-30',
				'Invalid IP address range "192.168.0.0/16-30".'
			),
			array(array('ipRangeLimit' => 65536),
				'fe80:0:0:0:0:0:c0a8:0/111',
				'IP range "fe80:0:0:0:0:0:c0a8:0/111" exceeds "65536" address limit.'
			),
			array(array(),
				'fe80:0:0:0:0:0:c0a8:0/129',
				'Invalid IP address range "fe80:0:0:0:0:0:c0a8:0/129".'
			),
			array(array(),
				'fe80::c0a8:0/129',
				'Invalid IP address range "fe80::c0a8:0/129".'
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

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

class CDecimalStringValidatorTest extends CValidatorTest {

	public function validValuesProvider() {
		return array(
			array(array(), 0),
			array(array(), '0'),
			array(array(), '1'),
			array(array(), '1.0'),
			array(array(), 1.0),
			array(array(), 1),
			array(array(), '1e5'),
			array(array(), '1E5'),
			array(array(), '123e5'),
			array(array(), '1e55'),
			array(array(), '1e-5'),
			array(array(), '-1e5'),
			array(array(), '1.1e5'),
			array(array(), '0.1e5'),
			array(array(), '01.0e5'),
			array(array(), '1.100e5'),
			array(array(), '0.1e-5'),
			array(array(), '01.0e-5'),
			array(array(), '1.100e-5'),
			array(array(), '1e-05'),
			array(array(), '1e-50'),
			array(array(), '.1'),
			array(array(), '-.1'),
			array(array(), '.010'),
			array(array(), '-.010'),
			array(array(), '1.'),
			array(array(), '-1.'),
			array(array(), '010.'),
			array(array(), '-010.')
		);
	}

	public function validParamProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid decimal string')
			)
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'',
				'Invalid decimal ""'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'--1.0',
				'Invalid decimal "--1.0"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				array(),
				'Invalid decimal "array"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'.1e2',
				'Invalid decimal ".1e2"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'1.2e2.5',
				'Invalid decimal "1.2e2.5"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'1.e2',
				'Invalid decimal "1.e2"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'..4',
				'Invalid decimal "..4"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'4..',
				'Invalid decimal "4.."'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				'.4.',
				'Invalid decimal ".4."'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid decimal value "%2$s" for "%1$s"'),
				'',
				'Invalid decimal value "" for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal value "%2$s" for "%1$s"'),
				array(),
				'Invalid decimal value "array" for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal value "%2$s" for "%1$s"'),
				'A',
				'Invalid decimal value "A" for "object"'
			),
		);
	}

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	protected function createValidator(array $params = array()) {
		return new CDecimalStringValidator($params);
	}
}

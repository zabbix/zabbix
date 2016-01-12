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


class CIdValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'empty' => true,
				'messageEmpty' => 'Empty ID',
				'messageInvalid' => 'Incorrect ID specified'
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(array(), 1),
			array(array(), '1'),
			array(array(), '9223372036854775807'),
			array(array('empty' => true), 0),
			array(array('empty' => true), '0'),
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid ID type'),
				true,
				'Invalid ID type'
			),
			array(
				array('messageInvalid' => 'Invalid ID type'),
				null,
				'Invalid ID type'
			),
			array(
				array('messageInvalid' => 'Invalid ID type'),
				array(),
				'Invalid ID type'
			),
			array(
				array('messageInvalid' => 'Invalid ID type'),
				new stdClass(),
				'Invalid ID type'
			),
			array(
				array('messageEmpty' => 'Empty ID'),
				0,
				'Empty ID'
			),
			array(
				array('messageEmpty' => 'Empty ID'),
				'0',
				'Empty ID'
			),
			array(
				array('messageInvalid' => 'Invalid ID'),
				'',
				'Invalid ID'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'01',
				'Incorrect ID "01"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'1.1',
				'Incorrect ID "1.1"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'-1',
				'Incorrect ID "-1"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'9223372036854775808',
				'Incorrect ID "9223372036854775808"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'A',
				'Incorrect ID "A"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%1$s"'),
				'1A',
				'Incorrect ID "1A"'
			)
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid ID for "%1$s"'),
				true,
				'Invalid ID for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid ID for "%1$s"'),
				null,
				'Invalid ID for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid ID for "%1$s"'),
				array(),
				'Invalid ID for "object"'
			),
			array(
				array('messageEmpty' => 'Empty ID for "%1$s"'),
				0,
				'Empty ID for "object"'
			),
			array(
				array('messageEmpty' => 'Empty ID for "%1$s"'),
				'0',
				'Empty ID for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid ID for "%1$s"'),
				'',
				'Invalid ID for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'01',
				'Incorrect ID "01" for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'-1',
				'Incorrect ID "-1" for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'1.1',
				'Incorrect ID "1.1" for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'9223372036854775808',
				'Incorrect ID "9223372036854775808" for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'A',
				'Incorrect ID "A" for "object"'
			),
			array(
				array('messageInvalid' => 'Incorrect ID "%2$s" for "%1$s"'),
				'1A',
				'Incorrect ID "1A" for "object"'
			)
		);
	}

	protected function createValidator(array $params = array()) {
		return new CIdValidator($params);
	}
}

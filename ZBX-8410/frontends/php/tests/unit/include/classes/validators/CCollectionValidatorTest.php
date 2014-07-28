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


class CCollectionValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'empty' => true,
				'uniqueField' => 'field',
				'uniqueField2' => 'field',
				'messageInvalid' => 'Not an array',
				'messageEmpty' => 'Empty collection',
				'messageDuplicate' => 'Collection has duplicate values',
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(
				array(),
				array(1, 2, 3)
			),
			array(
				array('empty' => true),
				array()
			),
			array(
				array('uniqueField' => 'type'),
				array(
					array('type' => 1),
					array('type' => 2),
					array('type' => 3)
				)
			),
			array(
				array('uniqueField' => 'type', 'uniqueField2' => 'subtype'),
				array(
					array('type' => 1, 'subtype' => 1),
					array('type' => 1, 'subtype' => 2),
					array('type' => 2, 'subtype' => 1),
					array('type' => 2, 'subtype' => 2),
				)
			),
			array(
				array('uniqueField' => 'type', 'uniqueField2' => null),
				array(
					array('type' => 1),
					array('type' => 2),
					array('type' => 3)
				)
			)
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageInvalid' => 'Not an array'),
				'',
				'Not an array'
			),
			array(
				array('messageInvalid' => 'Not an array'),
				0,
				'Not an array'
			),
			array(
				array('messageInvalid' => 'Not an array'),
				null,
				'Not an array'
			),
			array(
				array('messageEmpty' => 'Empty collection'),
				array(),
				'Empty collection'
			),
			array(
				array('uniqueField' => 'type', 'messageDuplicate' => 'Duplicate type "%1$s"'),
				array(
					array('type' => 1),
					array('type' => 1),
					array('type' => 3)
				),
				'Duplicate type "1"'
			),
			array(
				array('uniqueField' => 'type', 'uniqueField2' => 'subtype',
					'messageDuplicate' => 'Duplicate type "%1$s" and subtype "%2$s"'),
				array(
					array('type' => 1, 'subtype' => 1),
					array('type' => 1, 'subtype' => 2),
					array('type' => 2, 'subtype' => 2),
					array('type' => 2, 'subtype' => 2),
				),
				'Duplicate type "2" and subtype "2"'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
			array('messageInvalid' => 'Not an array for "%1$s"'),
				'',
				'Not an array for "object"'
			),
			array(
				array('messageInvalid' => 'Not an array for "%1$s"'),
				0,
				'Not an array for "object"'
			),
			array(
				array('messageInvalid' => 'Not an array for "%1$s"'),
				null,
				'Not an array for "object"'
			),
			array(
				array('messageEmpty' => 'Empty collection for "%1$s"'),
				array(),
				'Empty collection for "object"'
			),
			array(
				array('uniqueField' => 'type', 'messageDuplicate' => 'Duplicate type "%2$s" for "%1$s"'),
				array(
					array('type' => 1),
					array('type' => 1),
					array('type' => 3)
				),
				'Duplicate type "1" for "object"'
			),
			array(
				array('uniqueField' => 'type', 'uniqueField2' => 'subtype',
					'messageDuplicate' => 'Duplicate type "%2$s" and subtype "%3$s" for "%1$s"'),
				array(
					array('type' => 1, 'subtype' => 1),
					array('type' => 1, 'subtype' => 2),
					array('type' => 2, 'subtype' => 2),
					array('type' => 2, 'subtype' => 2),
				),
				'Duplicate type "2" and subtype "2" for "object"'
			),
		);
	}

	protected function createValidator(array $params = array()) {
		return new CCollectionValidator($params);
	}
}

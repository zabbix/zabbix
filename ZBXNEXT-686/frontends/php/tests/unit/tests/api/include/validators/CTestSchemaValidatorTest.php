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


class CTestSchemaValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'schema' => array(),
				'messageError' => 'Error message'
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(
				array(
					'schema' => 'string'
				),
				'string'
			),
			array(
				array(
					'schema' => array(
						'param' => 'string'
					)
				),
				array('param' => 'string')
			),
			array(
				array(
					'schema' => array(
						'param' => array('string1', 'string2')
					)
				),
				array('param' => array('string1', 'string2'))
			),

			// assertions
			array(
				array(
					'schema' => array('_assert' => 'string')
				),
				'string2',
			),
			array(
				array(
					'schema' => array('_keys' => 'arr')
				),
				array(1, 2, 3)
			),
			array(
				array(
					'schema' => array('_each' => 'string')
				),
				array('string', 'string2')
			),

			// don't validate values defined as nulls in the schema
			array(
				array(
					'schema' => array('value' => null)
				),
				array('value' => 3)
			),
			array(
				array(
					'schema' => null
				),
				array('value' => 3)
			),

		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array(
					'schema' => 'string'
				),
				'string2',
				'Unexpected value "string2" for path "", expected "string"'
			),
			array(
				array(
					'schema' => array(
						'param' => 'string'
					)
				),
				array('param' => 'string2'),
				'Unexpected value "string2" for path "param", expected "string"'
			),
			array(
				array(
					'schema' => array(
						'param' => array(
							'subparam' => 'string'
						)
					)
				),
				array(
					'param' => array(
						'subparam' => 'string2'
					)
				),
				'Unexpected value "string2" for path "param->subparam", expected "string"'
			),

			array(
				array(
					'schema' => array(
						'param' => 'string'
					)
				),
				array('param2' => 'string'),
				'Unexpected key "param2" for path ""'
			),
			array(
				array(
					'schema' => array(
						'param' => array(
							'subparam' => array('string')
						)
					)
				),
				array(
					'param' => array(
						'subparam' => array('string', 'string2')
					)
				),
				'Unexpected key "1" for path "param->subparam"'
			),



			array(
				array(
					'schema' => array(
						'param' => 'string',
						'param2' => 'string'
					)
				),
				array('param' => 'string'),
				'Missing key "param2" for path ""'
			),
			array(
				array(
					'schema' => array(
						'param' => array(
							'subparam' => array('string', 'string2', 'string3')
						)
					)
				),
				array(
					'param' => array(
						'subparam' => array('string', 'string2')
					)
				),
				'Missing key "2" for path "param->subparam"'
			),

			// assertions
			array(
				array(
					'schema' => array('_assert' => 'arr')
				),
				'string2',
				'Value "string2" for path "" doesn\'t match assertion "arr"'
			),
			array(
				array(
					'schema' => array('_keys' => 'string')
				),
				array(1, 2, 3),
				'Value [0,1,2] for path "" doesn\'t match assertion "string"'
			),
			array(
				array(
					'schema' => array('_each' => 'string')
				),
				array(array()),
				'Value [] for path "0" doesn\'t match assertion "string"'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array(
					'schema' => 'string',
					'messageError' => 'Error for "%1$s": %2$s'
				),
				'string2',
				'Error for "object": Unexpected value "string2" for path "", expected "string"'
			),
		);
	}

	protected function createValidator(array $params = array()) {
		return new CTestSchemaValidator($params);
	}
}

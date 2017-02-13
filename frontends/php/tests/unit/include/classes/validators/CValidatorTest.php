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


abstract class CValidatorTest extends PHPUnit_Framework_TestCase {

	abstract public function validParamProvider();
	abstract public function validValuesProvider();
	abstract public function invalidValuesProvider();
	abstract public function invalidValuesWithObjectsProvider();

	/**
	 * Create and return a validator object using the given params.
	 *
	 * @param array $params
	 *
	 * @return CValidator
	 */
	abstract protected function createValidator(array $params = []);

	/**
	 * Test creating the validator with a valid set of parameters.
	 *
	 * @dataProvider validParamProvider
	 *
	 * @param array $params
	 */
	public function testValidParams(array $params) {
		$validator = $this->createValidator($params);
		foreach ($params as $name => $value) {
			$this->assertEquals($validator->{$name}, $value);
		}
	}

	/**
	 * Test trying to create a validator with an invalid set of parameters.
	 */
	public function testInvalidParams() {
		$this->setExpectedException('Exception',
			'Incorrect option "invalidParam" for validator "'.get_class($this->createValidator()).'".'
		);

		$this->createValidator([
			'invalidParam' => 'value'
		]);
	}

	/**
	 * Test setting valid parameters individually.
	 *
	 * @dataProvider validParamProvider
	 *
	 * @param array $params
	 */
	public function setValidParamSet(array $params) {
		$validator = $this->createValidator();
		foreach ($params as $name => $value) {
			$validator->{$name} = $value;
			$this->assertEquals($validator->{$name}, $value);
		}
	}

	/**
	 * Test trying to set an invalid parameter.
	 */
	public function testInvalidParamSet() {
		$this->setExpectedException('Exception',
			'Incorrect option "invalidParameter" for validator "'.get_class($this->createValidator()).'".'
		);

		$validator = $this->createValidator();
		$validator->invalidParameter = 'value';
	}

	/**
	 * Test validating values that are valid.
	 *
	 * @dataProvider validValuesProvider()
	 *
	 * @param array $params
	 * @param $value
	 */
	public function testValidateValid(array $params, $value) {
		$validator = $this->createValidator($params);
		$result = $validator->validate($value);

		$this->assertEquals(true, $result);
		$this->assertNull($validator->getError());
	}


	/**
	 * Test validating values that are invalid and check the generated error message.
	 *
	 * @dataProvider invalidValuesProvider()
	 *
	 * @param array 	$params
	 * @param mixed 	$value
	 * @param string 	$expectedError
	 */
	public function testValidateInvalid(array $params, $value, $expectedError) {
		$validator = $this->createValidator($params);
		$result = $validator->validate($value);

		$this->assertEquals(false, $result);
		$this->assertSame($expectedError, $validator->getError());
	}

	/**
	 * Test that a correct error message is generated when setting an object name.
	 *
	 * @dataProvider invalidValuesWithObjectsProvider()
	 *
	 * @param array 	$params
	 * @param mixed 	$value
	 * @param string 	$expectedError
	 */
	public function testValidateInvalidWithObject(array $params, $value, $expectedError) {
		$validator = $this->createValidator($params);
		$validator->setObjectName('object');
		$result = $validator->validate($value);

		$this->assertEquals(false, $result);
		$this->assertSame($expectedError, $validator->getError());
	}
}

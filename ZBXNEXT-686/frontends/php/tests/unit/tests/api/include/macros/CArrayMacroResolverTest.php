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


class CArrayMacroResolverTest extends PHPUnit_Framework_TestCase {

	public function validProvider() {
		return array(
			array(
				array(false),
				array('value' => 'correct'),
				array(false)
			),
			array(
				array('@value@'),
				array('value' => 'correct'),
				array('correct')
			),
			array(
				array('Macro in string "@value@"'),
				array('value' => 'correct'),
				array('Macro in string "correct"')
			),
			array(
				array('@array.value@'),
				array('array' => array('value' => 'correct')),
				array('correct')
			),
			array(
				array('@array[0]@'),
				array('array' => array('correct')),
				array('correct')
			),
			array(
				array('array' => array('@array.sub.value@')),
				array('array' => array(
					'sub' => array('value' => 'correct')
				)),
				array('array' => array('correct'))
			),
			array(
				array('array' => array('@array.sub.value1@', '@array.sub.value2@')),
				array('array' => array(
					'sub' => array(
						'value1' => 'correct1',
						'value2' => 'correct2'
					)
				)),
				array('array' => array('correct1', 'correct2'))
			),
			array(
				array('@value_@'),
				array('value_' => 'correct'),
				array('correct')
			),
		);
	}

	/**
	 * @dataProvider validProvider
	 *
	 * @param array $array
	 * @param array $values
	 * @param array $expectedValue
	 */
	public function testValid(array $array, array $values, array $expectedValue) {
		$resolver = new CArrayMacroResolver();
		$this->assertSame($expectedValue, $resolver->resolve($array, $values));
	}

	public function invalidProvider() {
		return array(
			array(
				array('@.@'),
				array(),
				'Incorrect macro "@.@"'
			),
			array(
				array('@value@'),
				array(),
				'Cannot resolve macro "@value@": key "value" is not set'
			),
			array(
				array('@value.sub@'),
				array('value' => array()),
				'Cannot resolve macro "@value.sub@": key "sub" is not set'
			),
			array(
				array('@value.sub@'),
				array('value' => 'string'),
				'Cannot resolve macro "@value.sub@": value of "value" is not an array'
			),
		);
	}

	/**
	 * @dataProvider invalidProvider
	 *
	 * @param array $array
	 * @param array $values
	 * @param $expectedMessage
	 */
	public function testInvalid(array $array, array $values, $expectedMessage) {
		$this->setExpectedException('Exception', $expectedMessage);

		$resolver = new CArrayMacroResolver();
		$resolver->resolve($array, $values);
	}

}

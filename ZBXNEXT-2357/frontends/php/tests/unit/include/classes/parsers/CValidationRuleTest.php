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

class CValidationRuleTest extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return array(
			array('', '',
				array(
				)
			),
			array('fatal', '',
				array(
					'fatal' => true
				)
			),
			array('required', '',
				array(
					'required' => true
				)
			),
			array('required_if type:1,2', '',
				array(
					'required_if' => array(
						'type' => array('1', '2')
					)
				)
			),
			array('required_if form:1 type:1,2', '',
				array(
					'required_if' => array(
						'form' => array('1'),
						'type' => array('1', '2')
					)
				)
			),
			array('fatal | required_if form:1 type:1,2', '',
				array(
					'fatal' => true,
					'required_if' => array(
						'form' => array('1'),
						'type' => array('1', '2')
					)
				)
			),
			array(' fatal  |  required_if  form:1    type:1,2  ', '',
				array(
					'fatal' => true,
					'required_if' => array(
						'form' => array('1'),
						'type' => array('1', '2')
					)
				)
			),
			array('in 1,2,3', '',
				array(
					'in' => array('1', '2', '3')
				)
			),
			array('in 1,2,3 | fatal', '',
				array(
					'in' => array('1', '2', '3'),
					'fatal' => true
				)
			),
			array('in 1,2,3|fatal|required_if form:1 type:1,2', '',
				array(
					'in' => array('1', '2', '3'),
					'fatal' => true,
					'required_if' => array(
						'form' => array('1'),
						'type' => array('1', '2')
					)
				)
			),
			array('db hosts.name', '',
				array(
					'db' => array(
						'table' => 'hosts',
						'field' => 'name'
					)
				)
			),
			array('in  ASC,DESC | fatal | required_if form:1 type:1,2 | db  interface.ip ', '',
				array(
					'in' => array('ASC', 'DESC'),
					'fatal' => true,
					'required_if' => array(
						'form' => array('1'),
						'type' => array('1', '2')
					),
					'db' => array(
						'table' => 'interface',
						'field' => 'ip'
					)
				)
			),
			array('fatal|required', '',
				array(
					'fatal' => true,
					'required' => true
				)
			),
			array('  fatal |  required   ', '',
				array(
					'fatal' => true,
					'required' => true
				)
			),
			array('required_if', 'Cannot parse validation rules "required_if" at position 0.', false),
			array('required_if type', 'Cannot parse validation rules "required_if type" at position 0.', false),
			array('required_if type:', 'Cannot parse validation rules "required_if type:" at position 0.', false),
			array('in', 'Cannot parse validation rules "in" at position 0.', false),
			array('in 1, 2', 'Cannot parse validation rules "in 1, 2" at position 0.', false),
			array('in 1,|fatal', 'Cannot parse validation rules "in 1,|fatal" at position 0.', false),
			array('fatal|required|fatal', 'Validation rule "fatal" already exists.', false),
			array('fatal|required2', 'Cannot parse validation rules "fatal|required2" at position 6.', false),
			array('fatal|require', 'Cannot parse validation rules "fatal|require" at position 6.', false),
			array('FATAL', 'Cannot parse validation rules "FATAL" at position 0.', false)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($rule, $error_exprected, $result_expected) {
		$parser = new CValidationRule();

		$rc = $parser->parse($rule);

		$this->assertEquals($parser->getError(), $error_exprected);
		$this->assertEquals($rc, $result_expected);
	}
}

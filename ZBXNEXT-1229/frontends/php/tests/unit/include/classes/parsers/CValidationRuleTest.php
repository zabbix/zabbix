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
			array('not_empty', '',
				array(
					'not_empty' => true
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
			array('in 1,2,3|fatal', '',
				array(
					'in' => array('1', '2', '3'),
					'fatal' => true
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
			array('array_db hosts.name', '',
				array(
					'array_db' => array(
						'table' => 'hosts',
						'field' => 'name'
					)
				)
			),
			array('in  ASC,DESC | fatal | db  interface.ip ', '',
				array(
					'in' => array('ASC', 'DESC'),
					'fatal' => true,
					'db' => array(
						'table' => 'interface',
						'field' => 'ip'
					)
				)
			),
			array('fatal|required|json', '',
				array(
					'fatal' => true,
					'required' => true,
					'json' => true
				)
			),
			array('  fatal |  required   | array_db host.name', '',
				array(
					'fatal' => true,
					'required' => true,
					'array_db' => array(
						'table' => 'host',
						'field' => 'name'
					)
				)
			),
			array('json', '',
				array(
					'json' => true
				)
			),
			array('array_id', '',
				array(
					'array_id' => true
				)
			),
			array('id', '',
				array(
					'id' => true
				)
			),
			array('in graphid,itemid,screenid,slideshowid,sysmapid|fatal|required', '',
				array(
					'in' => array('graphid', 'itemid', 'screenid', 'slideshowid', 'sysmapid'),
					'fatal' => true,
					'required' => true
				)
			),
			array('in', 'Cannot parse validation rules "in" at position 0.', false),
			array('in 1, 2', 'Cannot parse validation rules "in 1, 2" at position 0.', false),
			array('in 1,|fatal', 'Cannot parse validation rules "in 1,|fatal" at position 0.', false),
			array('fatal|required|fatal', 'Validation rule "fatal" already exists.', false),
			array('fatal|required2', 'Cannot parse validation rules "fatal|required2" at position 14.', false),
			array('fatal|require', 'Cannot parse validation rules "fatal|require" at position 6.', false),
			array('fatala', 'Cannot parse validation rules "fatala" at position 5.', false),
			array('fatal not_empty', 'Cannot parse validation rules "fatal not_empty" at position 6.', false),
			array('FATAL', 'Cannot parse validation rules "FATAL" at position 0.', false)
		);
	}

	/**
	 * @dataProvider provider
	 */
	public function test_parse($rule, $error_exprected, $result_expected) {
		$parser = new CValidationRule();

		$rc = $parser->parse($rule);

		$this->assertEquals($rc, $result_expected);
		$this->assertEquals($parser->getError(), $error_exprected);
	}
}

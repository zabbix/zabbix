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
		return [
			['', '',
				[
				]
			],
			['fatal', '',
				[
					'fatal' => true
				]
			],
			['required', '',
				[
					'required' => true
				]
			],
			['not_empty', '',
				[
					'not_empty' => true
				]
			],
			['in 1,2,3', '',
				[
					'in' => ['1', '2', '3']
				]
			],
			['in 1,2,3 | fatal', '',
				[
					'in' => ['1', '2', '3'],
					'fatal' => true
				]
			],
			['in 1,2,3|fatal', '',
				[
					'in' => ['1', '2', '3'],
					'fatal' => true
				]
			],
			['db hosts.name', '',
				[
					'db' => [
						'table' => 'hosts',
						'field' => 'name'
					]
				]
			],
			['array_db hosts.name', '',
				[
					'array_db' => [
						'table' => 'hosts',
						'field' => 'name'
					]
				]
			],
			['in  ASC,DESC | fatal | db  interface.ip ', '',
				[
					'in' => ['ASC', 'DESC'],
					'fatal' => true,
					'db' => [
						'table' => 'interface',
						'field' => 'ip'
					]
				]
			],
			['fatal|required|json', '',
				[
					'fatal' => true,
					'required' => true,
					'json' => true
				]
			],
			['  fatal |  required   | array_db host.name', '',
				[
					'fatal' => true,
					'required' => true,
					'array_db' => [
						'table' => 'host',
						'field' => 'name'
					]
				]
			],
			['json', '',
				[
					'json' => true
				]
			],
			['array_id', '',
				[
					'array_id' => true
				]
			],
			['id', '',
				[
					'id' => true
				]
			],
			['in graphid,itemid,screenid,slideshowid,sysmapid|fatal|required', '',
				[
					'in' => ['graphid', 'itemid', 'screenid', 'slideshowid', 'sysmapid'],
					'fatal' => true,
					'required' => true
				]
			],
			['in', 'Cannot parse validation rules "in" at position 0.', false],
			['in 1, 2', 'Cannot parse validation rules "in 1, 2" at position 0.', false],
			['in 1,|fatal', 'Cannot parse validation rules "in 1,|fatal" at position 0.', false],
			['fatal|required|fatal', 'Validation rule "fatal" already exists.', false],
			['fatal|required2', 'Cannot parse validation rules "fatal|required2" at position 14.', false],
			['fatal|require', 'Cannot parse validation rules "fatal|require" at position 6.', false],
			['fatala', 'Cannot parse validation rules "fatala" at position 5.', false],
			['fatal not_empty', 'Cannot parse validation rules "fatal not_empty" at position 6.', false],
			['FATAL', 'Cannot parse validation rules "FATAL" at position 0.', false]
		];
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

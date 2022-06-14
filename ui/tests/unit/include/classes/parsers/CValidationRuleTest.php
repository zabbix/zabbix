<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

use PHPUnit\Framework\TestCase;

class CValidationRuleTest extends TestCase {

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
			['int32', '',
				[
					'int32' => true
				]
			],
			['uint64', '',
				[
					'uint64' => true
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
			['fatal|required|int32', '',
				[
					'fatal' => true,
					'required' => true,
					'int32' => true
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
			['array', '',
				[
					'array' => true
				]
			],
			['ge -5', '',
				[
					'ge' => '-5'
				]
			],
			['ge -5|le 10', '',
				[
					'ge' => '-5',
					'le' => '10'
				]
			],
			['range_time', '',
				[
					'range_time' => true
				]
			],
			['abs_date', '',
				[
					'abs_date' => true
				]
			],
			['abs_time', '',
				[
					'abs_time' => true
				]
			],
			['time_unit', '',
				[
					'time_unit' => []
				]
			],
			['time_unit 60:3600', '',
				[
					'time_unit' => [
						'ranges' => [['from' => '60', 'to' => '3600']]
					]
				]
			],
			['time_unit 0,60:3600', '',
				[
					'time_unit' => [
						'ranges' => [
							['from' => '0', 'to' => '0'],
							['from' => '60', 'to' => '3600']
						]
					]
				]
			],
			['time_unit_year', '',
				[
					'time_unit' => [
						'with_year' => true
					]
				]
			],
			['time_unit_year 60:3600', '',
				[
					'time_unit' => [
						'with_year' => true,
						'ranges' => [['from' => '60', 'to' => '3600']]
					]
				]
			],
			['time_unit_year 0,60:3600,7200:9800', '',
				[
					'time_unit' => [
						'with_year' => true,
						'ranges' => [
							['from' => '0', 'to' => '0'],
							['from' => '60', 'to' => '3600'],
							['from' => '7200', 'to' => '9800']
						]
					]
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
			['in graphid,itemid,sysmapid|fatal|required', '',
				[
					'in' => ['graphid', 'itemid', 'sysmapid'],
					'fatal' => true,
					'required' => true
				]
			],
			['cuid', '',
				[
					'cuid' => true
				]
			],
			['fatal|required|json|cuid', '',
				[
					'fatal' => true,
					'required' => true,
					'json' => true,
					'cuid' => true
				]
			],
			['in', 'Cannot parse validation rules "in" at position 0.', false],
			['in 1, 2', 'Cannot parse validation rules "in 1, 2" at position 0.', false],
			['in 1,|fatal', 'Cannot parse validation rules "in 1,|fatal" at position 0.', false],
			['fatal|required|fatal', 'Validation rule "fatal" already exists.', false],
			['fatal|required2', 'Cannot parse validation rules "fatal|required2" at position 14.', false],
			['fatal|require', 'Cannot parse validation rules "fatal|require" at position 6.', false],
			['fatala', 'Cannot parse validation rules "fatala" at position 5.', false],
			['ge ', 'Cannot parse validation rules "ge " at position 0.', false],
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

		$this->assertEquals($result_expected, $rc);
		$this->assertEquals($error_exprected, $parser->getError());
	}
}

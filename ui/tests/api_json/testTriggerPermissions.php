<?php
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testTriggerPermissions extends CAPITest {

	/**
	 * The list of triggers to checking permissions.
	 *
	 *     {N} - a host with "None" permissions
	 *     {D} - a host with "Deny" permissions
	 *     {R} - a host with "Read" permissions
	 *     {W} - a host with "Read/Write" permissions
	 *     {DRW} - a host with "Deny", "Read" and "Read/Write" permissions
	 *
	 * Triggers                                         | RW | R
	 * -------------------------------------------------+----+---
	 * test-trigger-permissions-trigger-{N}             | -  | -
	 * test-trigger-permissions-trigger-{D}             | -  | -
	 * test-trigger-permissions-trigger-{R}             | -  | x
	 * test-trigger-permissions-trigger-{W}             | x  | x
	 * test-trigger-permissions-trigger-{N}-{D}         | -  | -
	 * test-trigger-permissions-trigger-{N}-{R}         | -  | -
	 * test-trigger-permissions-trigger-{N}-{W}         | -  | -
	 * test-trigger-permissions-trigger-{D}-{R}         | -  | -
	 * test-trigger-permissions-trigger-{D}-{W}         | -  | -
	 * test-trigger-permissions-trigger-{R}-{W}         | -  | x
	 * test-trigger-permissions-trigger-{N}-{D}-{R}     | -  | -
	 * test-trigger-permissions-trigger-{N}-{D}-{W}     | -  | -
	 * test-trigger-permissions-trigger-{N}-{R}-{W}     | -  | -
	 * test-trigger-permissions-trigger-{D}-{R}-{W}     | -  | -
	 * test-trigger-permissions-trigger-{N}-{D}-{R}-{W} | -  | -
	 * test-trigger-permissions-trigger-{ND}            | -  | -
	 * test-trigger-permissions-trigger-{NR}            | -  | x
	 * test-trigger-permissions-trigger-{NW}            | x  | x
	 * test-trigger-permissions-trigger-{DR}            | -  | -
	 * test-trigger-permissions-trigger-{DW}            | -  | -
	 * test-trigger-permissions-trigger-{RW}            | x  | x
	 * test-trigger-permissions-trigger-{NDR}           | -  | -
	 * test-trigger-permissions-trigger-{NDW}           | -  | -
	 * test-trigger-permissions-trigger-{NRW}           | x  | x
	 * test-trigger-permissions-trigger-{DRW}           | -  | -
	 * test-trigger-permissions-trigger-{NDRW}          | -  | -
	 * test-trigger-permissions-trigger-{N}-{ND}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{NR}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{NW}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{DR}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{DW}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{RW}        | -  | -
	 * test-trigger-permissions-trigger-{N}-{NDR}       | -  | -
	 * test-trigger-permissions-trigger-{N}-{NDW}       | -  | -
	 * test-trigger-permissions-trigger-{N}-{NRW}       | -  | -
	 * test-trigger-permissions-trigger-{N}-{DRW}       | -  | -
	 * test-trigger-permissions-trigger-{N}-{NDRW}      | -  | -
	 * test-trigger-permissions-trigger-{D}-{ND}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{NR}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{NW}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{DR}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{DW}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{RW}        | -  | -
	 * test-trigger-permissions-trigger-{D}-{NDR}       | -  | -
	 * test-trigger-permissions-trigger-{D}-{NDW}       | -  | -
	 * test-trigger-permissions-trigger-{D}-{NRW}       | -  | -
	 * test-trigger-permissions-trigger-{D}-{DRW}       | -  | -
	 * test-trigger-permissions-trigger-{D}-{NDRW}      | -  | -
	 * test-trigger-permissions-trigger-{R}-{ND}        | -  | -
	 * test-trigger-permissions-trigger-{R}-{NR}        | -  | x
	 * test-trigger-permissions-trigger-{R}-{NW}        | -  | x
	 * test-trigger-permissions-trigger-{R}-{DR}        | -  | -
	 * test-trigger-permissions-trigger-{R}-{DW}        | -  | -
	 * test-trigger-permissions-trigger-{R}-{RW}        | -  | x
	 * test-trigger-permissions-trigger-{R}-{NDR}       | -  | -
	 * test-trigger-permissions-trigger-{R}-{NDW}       | -  | -
	 * test-trigger-permissions-trigger-{R}-{NRW}       | -  | x
	 * test-trigger-permissions-trigger-{R}-{DRW}       | -  | -
	 * test-trigger-permissions-trigger-{R}-{NDRW}      | -  | -
	 * test-trigger-permissions-trigger-{W}-{ND}        | -  | -
	 * test-trigger-permissions-trigger-{W}-{NR}        | -  | x
	 * test-trigger-permissions-trigger-{W}-{NW}        | x  | x
	 * test-trigger-permissions-trigger-{W}-{DR}        | -  | -
	 * test-trigger-permissions-trigger-{W}-{DW}        | -  | -
	 * test-trigger-permissions-trigger-{W}-{RW}        | x  | x
	 * test-trigger-permissions-trigger-{W}-{NDR}       | -  | -
	 * test-trigger-permissions-trigger-{W}-{NDW}       | -  | -
	 * test-trigger-permissions-trigger-{W}-{NRW}       | x  | x
	 * test-trigger-permissions-trigger-{W}-{DRW}       | -  | -
	 * test-trigger-permissions-trigger-{W}-{NDRW}      | -  | -
	 */
	public static function getTriggerPermissions() {
		return [
			[
				'request' => [
					'output' => ['triggerid', 'description'],
					'sortfield' => 'triggerid',
					'editable' => true
				],
				'result' => [
					['triggerid' => '50104', 'description' => 'test-trigger-permissions-trigger-{W}'],
					['triggerid' => '50118', 'description' => 'test-trigger-permissions-trigger-{NW}'],
					['triggerid' => '50121', 'description' => 'test-trigger-permissions-trigger-{RW}'],
					['triggerid' => '50124', 'description' => 'test-trigger-permissions-trigger-{NRW}'],
					['triggerid' => '50163', 'description' => 'test-trigger-permissions-trigger-{W}-{NW}'],
					['triggerid' => '50166', 'description' => 'test-trigger-permissions-trigger-{W}-{RW}'],
					['triggerid' => '50169', 'description' => 'test-trigger-permissions-trigger-{W}-{NRW}']
				]
			],
			[
				'request' => [
					'output' => ['triggerid', 'description'],
					'sortfield' => 'triggerid'
				],
				'result' => [
					['triggerid' => '50103', 'description' => 'test-trigger-permissions-trigger-{R}'],
					['triggerid' => '50104', 'description' => 'test-trigger-permissions-trigger-{W}'],
					['triggerid' => '50110', 'description' => 'test-trigger-permissions-trigger-{R}-{W}'],
					['triggerid' => '50117', 'description' => 'test-trigger-permissions-trigger-{NR}'],
					['triggerid' => '50118', 'description' => 'test-trigger-permissions-trigger-{NW}'],
					['triggerid' => '50121', 'description' => 'test-trigger-permissions-trigger-{RW}'],
					['triggerid' => '50124', 'description' => 'test-trigger-permissions-trigger-{NRW}'],
					['triggerid' => '50151', 'description' => 'test-trigger-permissions-trigger-{R}-{NR}'],
					['triggerid' => '50152', 'description' => 'test-trigger-permissions-trigger-{R}-{NW}'],
					['triggerid' => '50155', 'description' => 'test-trigger-permissions-trigger-{R}-{RW}'],
					['triggerid' => '50158', 'description' => 'test-trigger-permissions-trigger-{R}-{NRW}'],
					['triggerid' => '50162', 'description' => 'test-trigger-permissions-trigger-{W}-{NR}'],
					['triggerid' => '50163', 'description' => 'test-trigger-permissions-trigger-{W}-{NW}'],
					['triggerid' => '50166', 'description' => 'test-trigger-permissions-trigger-{W}-{RW}'],
					['triggerid' => '50169', 'description' => 'test-trigger-permissions-trigger-{W}-{NRW}']
				]
			]
		];
	}

	/**
	 * @dataProvider getTriggerPermissions
	 */
	public function testTriggerPermissions_get($request, $result) {
		$this->authorize('test-trigger-permissions-user', 'zabbix');
		$this->assertSame($result, $this->call('trigger.get', $request)['result']);
	}
}

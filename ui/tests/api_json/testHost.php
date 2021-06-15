<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

/**
 * @backup hosts
 */
class testHost extends CAPITest {

	public static function host_delete() {
		return [
			[
				'hostids' => [
					'61001'
				],
				'expected_error' => 'Cannot delete host because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61001',
					'61003'
				],
				'expected_error' => 'Cannot delete selected hosts because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61003'
				],
				'expected_error' => null
			],
			[
				'hostids' => [
					'61004',
					'61005'
				],
				'expected_error' => 'Cannot delete selected hosts because maintenance "maintenance_two_hosts" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61004'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider host_delete
	*/
	public function testHost_Delete($hostids, $expected_error) {
		$result = $this->call('host.delete', $hostids, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from hosts where hostid='.zbx_dbstr($id)));
			}
		}
	}

	public static function host_select_tags() {
		return [
			'Get host with extend tags' => [
				'params' => [
					'selectTags' => 'extend',
				],
				'expected_error' => null,
			],
			/* Commented out intentionally, until more thorough validation added
			'Get host with string tag' => [
				'params' => [
					'selectTags' => 'tag',
				],
				'expected_error' => 'Invalid parameter "/": an array is expected.',
			],*/
			'Get host with non-existing tag field' => [
				'params' => [
					'selectTags' => ['_nonexist'],
				],
				'expected_error' => 'applyQueryOutputOptions: field "host_tag._nonexist" does not exist.',
			],
			'Get host tag excluding value field' => [
				'params' => [
					'hostids' => [112233],
					'selectTags' => ['tag'],
				],
				'expected_error' => null,
			],
			'Get host tag with value' => [
				'params' => [
					'hostids' => [112233],
					'selectTags' => ['tag', 'value'],
				],
				'expected_error' => null,
			],
		];
	}

	/**
	* @dataProvider host_select_tags
	*/
	public function testHost_SelectTags($params, $expected_error) {
		$result = $this->call('host.get', $params, $expected_error);

		if ($expected_error === null && array_key_exists('hostids', $params)) {
			foreach ($result['result'] as $id => $host) {
				if ($id === '112233') {
					$tags = $result[$id]['tags'][0];
					if ($params['selectTags'] === ['tag']) {
						$this->assertEquals($tags, ['tag' => 'b', 'hostid' => '112233']);
					}
					elseif ($params['selectTags'] === ['tag', 'value']) {
						$this->assertEquals($tags, ['tag' => 'b', 'value' => 'b', 'hostid' => '112233']);
					}
				}
			}
		}
	}
}

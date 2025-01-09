<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../include/CAPITest.php';

class testGraphPrototype extends CAPITest {

	public static function graph_prototype_get_data(): array {
		return [
			[
				[
					'output' => ['graphid'],
					'hostids' => [131003]
				],
				[
					[
						'graphid' => '9000'
					]
				],
				null
			],
			[
				[
					'output' => ['graphid'],
					'hostids' => [131002]
				],
				[
					// Should be no matches.
				],
				null
			]
		];
	}

	/**
	 * @dataProvider graph_prototype_get_data
	 */
	public function testGraphPrototype_Get($api_request, $expected_result, $expected_error) {
		$result = $this->call('graphprototype.get', $api_request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($result['result'], $expected_result);
		}
	}
}

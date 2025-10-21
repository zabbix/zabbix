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


use PHPUnit\Framework\TestCase;

class CSystemInfoHelperTest extends TestCase {

	public static function dataProviderSoftwareUpdate() {
		$json_data = [
			[
				'version' => '6.0',
				'end_of_full_support' => true,
				'latest_release' => [
					'created' => '1747737670',
					'release' => '6.0.15'
				]
			],
			[
				'version' => '7.0',
				'end_of_full_support' => false,
				'latest_release' => [
					'created' => '1747737670',
					'release' => '7.0.13'
				]
			],
			[
				'version' => '7.2',
				'end_of_full_support' => true,
				'latest_release' => [
					'created' => '1747738856',
					'release' => '7.2.7'
				]
			],
			[
				'version' => '7.4',
				'end_of_full_support' => false,
				'latest_release' => [
					'created' => '1747738856',
					'release' => '7.4.7'
				]
			],
			[
				'version' => '8.0',
				'end_of_full_support' => false,
				'latest_release' => [
					'created' => '1747738856',
					'release' => '8.0.0'
				]
			]
		];

		yield 'Not existing version returns no data' => [
			$json_data, '0.2.13', []
		];

		yield 'Empty versions return no data' => [
			[], '8.0.0', []
		];

		yield 'Not outdated not LTS version return it latest release' => [
			$json_data, '7.4.3', [
				'end_of_full_support' => false,
				'latest_release' => '7.4.7'
			]
		];

		yield 'Not outdated LTS version return it latest release' => [
			$json_data, '7.0.2', [
				'end_of_full_support' => false,
				'latest_release' => '7.0.13'
			]
		];

		yield 'Outdated not LTS version return latest LTS release' => [
			$json_data, '7.2.6', [
				'end_of_full_support' => true,
				'latest_release' => '8.0.0'
			]
		];

		yield 'Outated LTS version return latest LTS release' => [
			$json_data, '6.0.10', [
				'end_of_full_support' => true,
				'latest_release' => '8.0.0'
			]
		];
	}

	/**
	 * @dataProvider dataProviderSoftwareUpdate
	 */
	public function testSoftwareUpdate(array $versions, string $version, array $expected) {
		$this->assertSame(CSystemInfoHelper::getSoftwareUpdateVersionDetails($versions, $version), $expected);
	}
}

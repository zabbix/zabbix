<?php declare(strict_types = 0);
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


class C42ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[
					'hosts' => [
						[
							'inventory' => [
								'inventory_mode' => 1,
								'type' => ''
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'inventory_mode' => 'AUTOMATIC'
						]
					]
				]
			],
			[
				[
					'hosts' => [
						[
							'inventory' => [
								'inventory_mode' => 1,
								'type' => 'x'
							]
						]
					]
				],
				[
					'hosts' => [
						[
							'inventory' => [
								'type' => 'x'
							],
							'inventory_mode' => 'AUTOMATIC'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderConvert
	 *
	 * @param $data
	 * @param $expected
	 */
	public function testConvert(array $data, array $expected) {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '4.2',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '4.4',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertSame($expected, $result);
	}

	protected function createConverter() {
		return new C42ImportConverter();
	}
}

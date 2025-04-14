<?php declare(strict_types = 1);
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


class C72ImportConverterTest extends CImportConverterTest {

	public function importConverterDataProviderMaps(): array {
		return [
			[
				[],
				[]
			],
			[
				[
					'maps' => [
						[
							'selements' => [
								[
									'label' => 'element-01'
								],
								[
									'label' => 'element-02'
								]
							],
							'links' => []
						]
					]
				],
				[
					'maps' => [
						[
							'selements' => [
								[
									'label' => 'element-01',
									'zindex' => '0'
								],
								[
									'label' => 'element-02',
									'zindex' => '1'
								]
							],
							'links' => [],
							'background_scale' => 'NONE'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider importConverterDataProviderMaps
	 *
	 * @param array $source
	 * @param array $expected
	 */
	public function testConvert(array $source, array $expected): void {
		$result = $this->createConverter()->convert($this->createSource($source));
		$this->assertConvert($this->createExpectedResult($expected), $result);
	}

	protected function createSource(array $data = []): array {
		return ['zabbix_export' => ['version' => '7.2'] + $data];
	}

	protected function createExpectedResult(array $data = []): array {
		return ['zabbix_export' => ['version' => '7.4'] + $data];
	}

	protected function createConverter(): C72ImportConverter {
		return new C72ImportConverter();
	}
}

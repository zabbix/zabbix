<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class C42ImportConverterTest extends CImportConverterTest {

	public function testConvertProvider() {
		return [
			[
				[
					'screens' => [
						[
							'name' => 'Parent screen 1',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => [
								'0' => [
									'resourcetype' => 8,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 0,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Child screen'
									],
									'max_columns' => 3
								],
								'1' => [
									'resourcetype' => 7,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 1,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Clock'
									],
									'max_columns' => 3
								]
							]
						],
						[
							'name' => 'Parent screen 2',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => [
								'0' => [
									'resourcetype' => 8,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 0,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Child screen'
									],
									'max_columns' => 3
								]
							]
						],
						[
							'name' => 'Child screen',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => [
								'0' => [
									'resourcetype' => 7,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 0,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Clock'
									],
									'max_columns' => 3
								],
							]
						]
					]
				],
				[
					'screens' => [
						[
							'name' => 'Parent screen 1',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => [
								'1' => [
									'resourcetype' => 7,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 1,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Clock'
									],
									'max_columns' => 3
								]
							]
						],
						[
							'name' => 'Parent screen 2',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => []
						],
						[
							'name' => 'Child screen',
							'hsize' => 1,
							'vsize' => 4,
							'screen_items' => [
								'0' => [
									'resourcetype' => 7,
									'width' => 500,
									'height' => 100,
									'x' => 0,
									'y' => 0,
									'colspan' => 1,
									'rowspan' => 1,
									'valign' => 0,
									'halign' => 0,
									'resource' => [
										'name' => 'Clock'
									],
									'max_columns' => 3
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider testConvertProvider
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

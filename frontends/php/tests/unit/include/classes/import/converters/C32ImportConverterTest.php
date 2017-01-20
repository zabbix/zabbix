<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class C32ImportConverterTest extends CImportConverterTest {

	public function testConvertProvider() {
		return [
			[
				[
					'templates' => [
						[
							'items' => [
								[
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '10',
									'multiplier' => 1,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_OCTAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 1
								],
								[
									'data_type' => ITEM_DATA_TYPE_BOOLEAN,
									'formula' => '100',
									'multiplier' => 1,
									'delta' => 2
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '10',
											'multiplier' => 1,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_OCTAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 1
										],
										[
											'data_type' => ITEM_DATA_TYPE_BOOLEAN,
											'formula' => '100',
											'multiplier' => 1,
											'delta' => 2
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => [
								[
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '10',
									'multiplier' => 1,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_OCTAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0
								],
								[
									'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 1
								],
								[
									'data_type' => ITEM_DATA_TYPE_BOOLEAN,
									'formula' => '100',
									'multiplier' => 1,
									'delta' => 2
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '10',
											'multiplier' => 1,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_OCTAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0
										],
										[
											'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 1
										],
										[
											'data_type' => ITEM_DATA_TYPE_BOOLEAN,
											'formula' => '100',
											'multiplier' => 1,
											'delta' => 2
										]
									]
								]
							]
						]
					]
				],
				[
					'templates' => [
						[
							'items' => [
								[
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											]
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => [
								[
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									]
								],
								[
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											]
										],
										[
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											]
										]
									]
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
				'version' => '3.2',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '3.4',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}


	protected function createConverter() {
		return new C32ImportConverter();
	}

}

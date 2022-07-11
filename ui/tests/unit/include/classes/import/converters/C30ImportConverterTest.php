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


class C30ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[
					'templates' => [
						[
							'discovery_rules' => [
								[
									'trigger_prototypes' => [
										[
											'description' => 'trigger1',
											'expression' => '{Template:item1.last()}',
											'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
											'recovery_expression' => '',
											'dependencies' => [
												[
													'description' => 'trigger2',
													'expression' => '{Template:item2.last()}',
													'recovery_expression' => ''
												]
											],
											'tags' => [],
											'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
											'correlation_tag' => '',
											'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'discovery_rules' => [
								[
									'trigger_prototypes' => [
										[
											'description' => 'trigger1',
											'expression' => '{host:item1.last()}',
											'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
											'recovery_expression' => '',
											'dependencies' => [
												[
													'description' => 'trigger2',
													'expression' => '{host:item2.last()}',
													'recovery_expression' => ''
												]
											],
											'tags' => [],
											'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
											'correlation_tag' => '',
											'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
										]
									]
								]
							]
						]
					],
					'triggers' => [
						[
							'description' => 'trigger1',
							'expression' => '{host:item1.last()}',
							'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
							'recovery_expression' => '',
							'dependencies' => [
								[
									'description' => 'trigger2',
									'expression' => '{host:item2.last()}',
									'recovery_expression' => ''
								]
							],
							'tags' => [],
							'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
							'correlation_tag' => '',
							'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
						]
					],
					'maps' => [
						[
							'selements' => [
								[
									'elementtype' => 0
								],
								[
									'elementtype' => 2,
									'element' => [
										'description' => 'trigger1',
										'expression' => 'trigger1:item.last()'
									]
								]
							],
							'links' => [
								[
									'linktriggers' => [
										[
											'trigger' => [
												'description' => 'trigger2',
												'expression' => 'trigger2:item.last()'
											]
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
							'discovery_rules' => [
								[
									'trigger_prototypes' => [
										[
											'description' => 'trigger1',
											'expression' => '{Template:item1.last()}',
											'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
											'recovery_expression' => '',
											'dependencies' => [
												[
													'description' => 'trigger2',
													'expression' => '{Template:item2.last()}',
													'recovery_expression' => ''
												]
											],
											'tags' => [],
											'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
											'correlation_tag' => '',
											'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'discovery_rules' => [
								[
									'trigger_prototypes' => [
										[
											'description' => 'trigger1',
											'expression' => '{host:item1.last()}',
											'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
											'recovery_expression' => '',
											'dependencies' => [
												[
													'description' => 'trigger2',
													'expression' => '{host:item2.last()}',
													'recovery_expression' => ''
												]
											],
											'tags' => [],
											'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
											'correlation_tag' => '',
											'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
										]
									]
								]
							]
						]
					],
					'triggers' => [
						[
							'description' => 'trigger1',
							'expression' => '{host:item1.last()}',
							'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
							'recovery_expression' => '',
							'dependencies' => [
								[
									'description' => 'trigger2',
									'expression' => '{host:item2.last()}',
									'recovery_expression' => ''
								]
							],
							'tags' => [],
							'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
							'correlation_tag' => '',
							'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED
						]
					],
					'maps' => [
						[
							'selements' => [
								[
									'elementtype' => 0
								],
								[
									'elementtype' => 2,
									'element' => [
										'description' => 'trigger1',
										'expression' => 'trigger1:item.last()',
										'recovery_expression' => ''
									]
								]
							],
							'links' => [
								[
									'linktriggers' => [
										[
											'trigger' => [
												'description' => 'trigger2',
												'expression' => 'trigger2:item.last()',
												'recovery_expression' => ''
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
				'version' => '3.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '3.2',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}


	protected function createConverter() {
		return new C30ImportConverter();
	}

}

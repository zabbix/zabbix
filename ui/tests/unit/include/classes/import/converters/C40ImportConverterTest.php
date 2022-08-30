<?php declare(strict_types=1);
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


class C40ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[
					'templates' => [
						[
							'tags' => [],
							'items' => [
								[
									'preprocessing' => [
										[
											'type' => '12',
											'params' => '$.path.to.node'
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'preprocessing' => [
												[
													'type' => '12',
													'params' => '$.path.to.node'
												]
											]
										]
									],
									'master_item' => []
								]
							]
						]
					],
					'hosts' => [
						[
							'tags' => [],
							'items' => [
								[
									'preprocessing' => [
										[
											'type' => '12',
											'params' => '$.path.to.node'
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'preprocessing' => [
												[
													'type' => '12',
													'params' => '$.path.to.node'
												]
											]
										]
									],
									'master_item' => []
								]
							]
						]
					]
				],
				[
					'templates' => [
						[
							'tags' => [],
							'items' => [
								[
									'preprocessing' => [
										[
											'type' => '12',
											'params' => '$.path.to.node',
											'error_handler' => '0',
											'error_handler_params' => ''
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'preprocessing' => [
												[
													'type' => '12',
													'params' => '$.path.to.node',
													'error_handler' => '0',
													'error_handler_params' => ''
												]
											]
										]
									],
									'master_item' => [],
									'lld_macro_paths' => [],
									'preprocessing' => []
								]
							]
						]
					],
					'hosts' => [
						[
							'tags' => [],
							'items' => [
								[
									'preprocessing' => [
										[
											'type' => '12',
											'params' => '$.path.to.node',
											'error_handler' => '0',
											'error_handler_params' => ''
										]
									]
								]
							],
							'discovery_rules' => [
								[
									'item_prototypes' => [
										[
											'preprocessing' => [
												[
													'type' => '12',
													'params' => '$.path.to.node',
													'error_handler' => '0',
													'error_handler_params' => ''
												]
											]
										]
									],
									'master_item' => [],
									'lld_macro_paths' => [],
									'preprocessing' => []
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
				'version' => '4.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '4.2',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertSame($expected, $result);
	}

	protected function createConverter() {
		return new C40ImportConverter();
	}
}

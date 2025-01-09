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

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


class C60ImportConverterTest extends CImportConverterTest {

	public function importConverterDataProvider(): array {
		return [
			[
				[],
				[]
			],
			[
				[
					'groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					]
				],
				[
					'host_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					]
				]
			],
			[
				[
					'groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							]
						]
					]
				],
				[
					'template_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01']
					],
					'host_groups' => [
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							]
						]
					]
				]
			],
			[
				[
					'groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					]
				],
				[
					'template_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01']
					],
					'host_groups' => [
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					]
				]
			],
			[
				[
					'groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-01']],
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					]
				],
				[
					'template_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01']
					],
					'host_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-01']],
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					]
				]
			],
			[
				[
					'groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02'],
						['name' => 'group-03', 'uuid' => 'uuid-03'],
						['name' => 'group-04', 'uuid' => 'uuid-04']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'groups' => [
								['name' => 'group-02']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-01']],
												['group' => ['name' => 'group-02']],
												['group' => ['name' => 'group-03']]
											]
										]
									]
								]
							]
						]
					]
				],
				[
					'template_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01']
					],
					'host_groups' => [
						['name' => 'group-01', 'uuid' => 'uuid-01'],
						['name' => 'group-02', 'uuid' => 'uuid-02'],
						['name' => 'group-03', 'uuid' => 'uuid-03'],
						['name' => 'group-04', 'uuid' => 'uuid-04']
					],
					'templates' => [
						[
							'groups' => [
								['name' => 'group-01']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-02']]
											]
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'groups' => [
								['name' => 'group-02']
							],
							'discovery_rules' => [
								[
									'host_prototypes' => [
										[
											'group_links' => [
												['group' => ['name' => 'group-01']],
												['group' => ['name' => 'group-02']],
												['group' => ['name' => 'group-03']]
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
	 * @dataProvider importConverterDataProvider
	 *
	 * @param array $data
	 * @param array $expected
	 */
	public function testConvert(array $data, array $expected): void {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '6.0',
				'date' => '2022-05-16T17:45:00:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []): array {
		return [
			'zabbix_export' => array_merge([
				'version' => '6.2',
				'date' => '2022-05-16T17:45:00:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source): void {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expected, $result);
	}

	protected function createConverter(): C60ImportConverter {
		return new C60ImportConverter();
	}
}

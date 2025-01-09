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


class C70ImportConverterTest extends CImportConverterTest {

	public function providerInventoryMode(): array {
		$host_prototype_base = [
			[
				'host' => 'host_prototype.{#LLD}',
				'name' => 'host_prototype.{#LLD}',
				'group_links' => [
					['group' => ['name' => 'Zabbix servers']]
				]
			]
		];
		$source_lld_rules = [
			[
				'name' => 'lld_rule',
				'host_prototypes' => [
					$host_prototype_base
				]
			]
		];
		$expected_lld_rules = [
			[
				'name' => 'lld_rule',
				'host_prototypes' => [
					$host_prototype_base + ['inventory_mode' => CXmlConstantName::MANUAL]
				]
			]
		];

		return [
			[
				'inventory_mode unchanged if specified' => [
					'hosts' => [
						[
							'discovery_rules' => [
								[
									'name' => 'lld_rule',
									'host_prototypes' => [
										$host_prototype_base + ['inventory_mode' => CXmlConstantName::AUTOMATIC]
									]
								]
							],
							'inventory_mode' => CXmlConstantName::AUTOMATIC
						]
					]
				],
				[
					'hosts' => [
						[
							'discovery_rules' => [
								[
									'name' => 'lld_rule',
									'host_prototypes' => [
										$host_prototype_base + ['inventory_mode' => CXmlConstantName::AUTOMATIC]
									]
								]
							],
							'inventory_mode' => CXmlConstantName::AUTOMATIC
						]
					]
				]
			],
			'inventory_mode added to hosts and host>host_prototypes only, if missing' => [
				[
					'templates' => [
						[
							'discovery_rules' => $source_lld_rules
						]
					],
					'hosts' => [
						[
							'discovery_rules' => $source_lld_rules
						]
					]
				],
				[
					'templates' => [
						[
							'discovery_rules' => $source_lld_rules
						]
					],
					'hosts' => [
						[
							'discovery_rules' => $expected_lld_rules,
							'inventory_mode' => CXmlConstantName::MANUAL
						]
					]
				]
			]
		];
	}

	public function importConverterDataProviderClockWidget(): array {
		return [
			[
				[
					'templates' => [
						[
							'name' => 'template',
							'dashboards' => [
								[
									'pages' => [
										[
											'widgets' => [
												[
													'type' => 'clock',
													'fields' => [
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'clock_type',
															'value' => '1'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'date_size',
															'value' => '45'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.0',
															'value' => '1'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.1',
															'value' => '2'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.2',
															'value' => '3'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'time_size',
															'value' => '25'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'tzone_size',
															'value' => '30'
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
				],
				[
					'templates' => [
						[
							'name' => 'template',
							'dashboards' => [
								[
									'pages' => [
										[
											'widgets' => [
												[
													'type' => 'clock',
													'fields' => [
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'clock_type',
															'value' => '1'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.0',
															'value' => '1'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.1',
															'value' => '2'
														],
														[
															'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_INTEGER,
															'name' => 'show.2',
															'value' => '3'
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
				]
			]
		];
	}

	/**
	 * @dataProvider providerInventoryMode
	 * @dataProvider importConverterDataProviderClockWidget
	 *
	 * @param array $source
	 * @param array $expected
	 */
	public function testConvert(array $source, array $expected): void {
		$result = $this->createConverter()->convert($this->createSource($source));
		$this->assertConvert($this->createExpectedResult($expected), $result);
	}

	protected function createSource(array $data = []): array {
		return ['zabbix_export' => ['version' => '7.0'] + $data];
	}

	protected function createExpectedResult(array $data = []): array {
		return ['zabbix_export' => ['version' => '7.2'] + $data];
	}

	protected function createConverter(): C70ImportConverter {
		return new C70ImportConverter();
	}
}

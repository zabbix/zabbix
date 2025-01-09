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


class Sla {

	/**
	 * Create data for sla related test.
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('sla.create', [
			[
				'name' => 'Update SLA',
				'period' => 0,
				'slo' => '99.99',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'test',
						'value' => 'test123'
					]
				]
			],
			[
				'name' => 'SLA with schedule and downtime',
				'period' => 1,
				'slo' => '12.3456',
				'effective_date' => 1651363200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					],
					[
						'tag' => 'test',
						'operator' => 2,
						'value' => 'test'
					]
				],
				'excluded_downtimes' => [
					[
						'name' => 'excluded downtime',
						'period_from' => 1651363200,
						'period_to' => 1777593600
					]
				],
				'schedule' => [
					[
						'period_from' => 0,
						'period_to' => 120
					],
					[
						'period_from' => 60,
						'period_to' => 240
					]
				]
			],
			[
				'name' => 'SLA для удаления - 頑張って',
				'period' => 3,
				'slo' => '66.6',
				'effective_date' => 1651352400,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'tag',
						'value' => 'value'
					]
				],
				'excluded_downtimes' => [
					[
						'name' => 'excluded downtime',
						'period_from' => 1651352400,
						'period_to' => 1777582800
					]
				],
				'schedule' => [
					[
						'period_from' => 0,
						'period_to' => 20000
					]
				]
			],
			[
				'name' => 'Disabled SLA',
				'period' => 0,
				'slo' => '9.99',
				'effective_date' => 1577836800,
				'timezone' => 'America/Nuuk',
				'status' => 0,
				'service_tags' => [
					[
						'tag' => 'tag',
						'value' => 'value'
					],
					[
						'tag' => 'old_tag_1',
						'value' => 'new_old_value_1'
					],
					[
						'tag' => 'Unique TAG',
						'value' => 'Unique VALUE'
					]
				],
				'schedule' => [
					[
						'period_from' => 0,
						'period_to' => 61200
					],
					[
						'period_from' => 86400,
						'period_to' => 104400
					],
					[
						'period_from' => 190800,
						'period_to' => 194400
					],
					[
						'period_from' => 280800,
						'period_to' => 284400
					],
					[
						'period_from' => 370800,
						'period_to' => 374400
					],
					[
						'period_from' => 446400,
						'period_to' => 504000
					],
					[
						'period_from' => 601200,
						'period_to' => 604800
					]
				]
			],
			[
				'name' => 'Disabled SLA Annual',
				'period' => 4,
				'slo' => '13.01',
				'effective_date' => 1924991999,
				'timezone' => 'Pacific/Fiji',
				'status' => 0,
				'service_tags' => [
					[
						'tag' => 'sla',
						'value' => 'service level agreement'
					],
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					]
				],
				'schedule' => [
					[
						'period_from' => 0,
						'period_to' => 61200
					],
					[
						'period_from' => 601200,
						'period_to' => 604800
					]
				]
			],
			[
				'name' => 'SLA Daily',
				'period' => 0,
				'slo' => '11.111',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					]
				],
				'excluded_downtimes' => [
					[
						'name' => 'EXCLUDED DOWNTIME',
						'period_from' => time() - 3600,
						'period_to' => time() + 86400
					],
					[
						'name' => 'Second downtime',
						'period_from' => time() - 3600,
						'period_to' => time() + 31536000
					],
					[
						'name' => 'Downtime in the past',
						'period_from' => time() - 7200,
						'period_to' => time() - 3600
					],
					[
						'name' => 'Downtime in the future',
						'period_from' => time() + 86400,
						'period_to' => time() + 31536000
					]
				]
			],
			[
				'name' => 'SLA Monthly',
				'period' => 2,
				'slo' => '22.22',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'problem',
						'operator' => 2,
						'value' => 'e'
					]
				]
			],
			[
				'name' => 'SLA Quarterly',
				'period' => 3,
				'slo' => '33.33',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'problem',
						'operator' => 2,
						'value' => 'e'
					]
				]
			],
			[
				'name' => 'SLA Annual',
				'period' => 4,
				'slo' => '44.44',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					]
				]
			],
			[
				'name' => 'SLA Weekly',
				'period' => 1,
				'slo' => '55.5555',
				'effective_date' => 1619827200,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'problem',
						'operator' => 2,
						'value' => 'e'
					]
				]
			]
		]);

		return ['slaids' => CDataHelper::getIds('name'), 'creation_time' => time()];
	}
}

<?php
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
				'period' => 1,
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
				'effective_date' => 1651352400,
				'timezone' => 'Europe/Riga',
				'service_tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
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
					],
					[
						'period_from' => 10000,
						'period_to' => 38800
					]
				]
			],
			[
				'name' => 'SLA for delete',
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
			]
		]);

		return ['sla_ids' => CDataHelper::getIds('name')];
	}
}

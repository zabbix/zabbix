<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


/**
 * A class designed to perform actions and contain constants related to SLA.
 */
class CSlaHelper {

	public const SLA_STATUS_ANY			= -1;
	public const SLA_STATUS_ENABLED		= 0;
	public const SLA_STATUS_DISABLED	= 1;

	public const SCHEDULE_MODE_NONSTOP	= 0;
	public const SCHEDULE_MODE_CUSTOM	= 1;

	public const TAB_INDICATOR_SLA_DOWNTIMES = 'sla-downtimes';

	public const OUTPUT_FIELDS = [
		'name',
		'description',
		'effective_date',
		'status',
		'slo',
		'period',
		'timezone'
	];

	public static function periodToStr(int $period): ?string {
		static $periods;

		if ($periods === null) {
			$periods = self::periods();
		}

		return array_key_exists($period, $periods)
			? $periods[$period]
			: null;
	}

	public static function scheduleModeToStr(int $schedule_mode): ?string {
		static $schedule_modes;

		if ($schedule_modes === null) {
			$schedule_modes = [
				self::SCHEDULE_MODE_NONSTOP => _('24x7'),
				self::SCHEDULE_MODE_CUSTOM => _('Custom')
			];
		}

		return array_key_exists($schedule_mode, $schedule_modes)
			? $schedule_modes[$schedule_mode]
			: null;
	}

	public static function periods(): array {
		return [
			ZBX_SLA_PERIOD_DAILY => _('Daily'),
			ZBX_SLA_PERIOD_WEEKLY=> _('Weekly'),
			ZBX_SLA_PERIOD_MONTHLY => _('Monthly'),
			ZBX_SLA_PERIOD_QUARTERLY => _('Quarterly'),
			ZBX_SLA_PERIOD_ANNUALLY => _('Annually')
		];
	}

	/**
	 * Split periods that may span several days to periods grouped by weekday.
	 *
	 * @param array $schedule_periods					List of SLA periods, sorted by period_from.
	 * @param array $schedule_periods['period_from']	Timestamp within week.
	 * @param array $schedule_periods['period_to]		Timestamp within week.
	 *
	 * @return array
	 */
	public static function convertScheduleToWeekdayPeriods(array $schedule_periods): array {
		$schedule = [];

		foreach (range(0,6) as $weekday) {
			$schedule[$weekday] = [];
		}

		foreach ($schedule_periods as $period) {
			while (date('Y-m-d', $period['period_from']) != date('Y-m-d', $period['period_to'])) {
				$inject = [
					'period_from' => $period['period_from'],
					'period_to' => strtotime(date('Y-m-d 24:00:0', $period['period_from']))
				];

				$schedule[date('w', $inject['period_from'])][] = $inject;
				$period['period_from'] = strtotime(date('Y-m-d 00:00:00', $inject['period_to'] + 2));
			}

			$schedule[date('w', $period['period_from'])][] = $period;
		}

		// @TODO: intersections, if not weeded out by API.

		return array_filter($schedule);
	}
}

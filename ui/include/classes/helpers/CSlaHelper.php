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


final class CSlaHelper {

	public const SLA_STATUS_ANY			= -1;
	public const SLA_STATUS_ENABLED		= 0;
	public const SLA_STATUS_DISABLED	= 1;

	public const SCHEDULE_MODE_24X7		= 0;
	public const SCHEDULE_MODE_CUSTOM 	= 1;

	public static function scheduleModeToStr(int $schedule_mode): ?string {
		static $schedule_modes;

		if ($schedule_modes === null) {
			$schedule_modes = [
				self::SCHEDULE_MODE_24X7 => _('24x7'),
				self::SCHEDULE_MODE_CUSTOM => _('Custom')
			];
		}

		return array_key_exists($schedule_mode, $schedule_modes)
			? $schedule_modes[$schedule_mode]
			: null;
	}

	public static function getPeriodNames(): array {
		static $periods;

		if ($periods === null) {
			$periods = [
				ZBX_SLA_PERIOD_DAILY => _('Daily'),
				ZBX_SLA_PERIOD_WEEKLY=> _('Weekly'),
				ZBX_SLA_PERIOD_MONTHLY => _('Monthly'),
				ZBX_SLA_PERIOD_QUARTERLY => _('Quarterly'),
				ZBX_SLA_PERIOD_ANNUALLY => _('Annually')
			];
		}

		return $periods;
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
			$period['period_from'] = (int) $period['period_from'];
			$period['period_to'] = (int) $period['period_to'];

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

		return $schedule;
	}

	/**
	 * @param int    $period
	 * @param int    $period_from
	 * @param int    $period_to
	 * @param string $timezone
	 *
	 * @return CTag
	 */
	public static function getPeriodTag(int $period, int $period_from, int $period_to, string $timezone): CTag {
		$tag = new CSpan();

		try {
			$datetime_from = (new DateTime('@'.$period_from))->setTimezone(new DateTimeZone($timezone));
			$datetime_to = (new DateTime('@'.($period_to - 1)))->setTimezone(new DateTimeZone($timezone));
		}
		catch (Exception $e) {
			return $tag;
		}

		switch ($period) {
			case ZBX_SLA_PERIOD_DAILY:
				$tag->addItem($datetime_from->format(ZBX_SLA_PERIOD_DATE_FORMAT_DAILY));
				break;

			case ZBX_SLA_PERIOD_WEEKLY:
				$tag->addItem([
					$datetime_from->format(ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_FROM),
					' &#8211; ',
					$datetime_to->format(ZBX_SLA_PERIOD_DATE_FORMAT_WEEKLY_TO)
				]);
				break;

			case ZBX_SLA_PERIOD_MONTHLY:
				$tag->addItem($datetime_from->format(ZBX_SLA_PERIOD_DATE_FORMAT_MONTHLY));
				break;

			case ZBX_SLA_PERIOD_QUARTERLY:
				$tag->addItem([
					$datetime_from->format(ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_FROM),
					' &#8211; ',
					$datetime_to->format(ZBX_SLA_PERIOD_DATE_FORMAT_QUARTERLY_TO)
				]);
				break;

			case ZBX_SLA_PERIOD_ANNUALLY:
				$tag->addItem($datetime_from->format(ZBX_SLA_PERIOD_DATE_FORMAT_ANNUALLY));
				break;
		}

		return $tag;
	}

	/**
	 * @param float $slo
	 *
	 * @return CTag
	 */
	public static function getSloTag(float $slo): CTag {
		return new CSpan([round($slo, 4), '%']);
	}

	/**
	 * @param float $sli
	 * @param float $slo
	 *
	 * @return CTag
	 */
	public static function getSliTag(float $sli, float $slo): CTag {
		if ($sli == -1) {
			return (new CSpan(_('N/A')))->addClass(ZBX_STYLE_GREY);
		}

		return (new CSpan(floor($sli * 10000) / 10000))->addClass($sli >= $slo ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
	}

	/**
	 * @param int $uptime
	 *
	 * @return CTag
	 */
	public static function getUptimeTag(int $uptime): CTag {
		return (new CSpan(convertUnitsS($uptime, true)))->addClass($uptime == 0 ? ZBX_STYLE_GREY : null);
	}

	/**
	 * @param int $downtime
	 *
	 * @return CTag
	 */
	public static function getDowntimeTag(int $downtime): CTag {
		return (new CSpan(convertUnitsS($downtime, true)))->addClass($downtime == 0 ? ZBX_STYLE_GREY : null);
	}

	/**
	 * @param int $error_budget
	 *
	 * @return CTag
	 */
	public static function getErrorBudgetTag(int $error_budget): CTag {
		return (new CSpan(convertUnitsS($error_budget, true)))
			->addClass($error_budget >= 0 ? ZBX_STYLE_GREY : ZBX_STYLE_RED);
	}

	/**
	 * @param array excluded_downtime  Record.
	 * @param string timezone          SLA timezone.
	 *
	 * @return CTag
	 */
	public static function getExcludedDowntimeTag(array $excluded_downtime, string $timezone): CTag {
		$tag = new CDiv();

		try {
			$datetime_from = (new DateTime('@'.$excluded_downtime['period_from']))
				->setTimezone(new DateTimeZone($timezone));
			$datetime_to = (new DateTime('@'.($excluded_downtime['period_to'] - 1)))
				->setTimezone(new DateTimeZone($timezone));
		}
		catch (Exception $e) {
			return $tag;
		}

		$tag
			->addItem($datetime_from->format(DATE_TIME_FORMAT))
			->addItem(' ')
			->addItem($excluded_downtime['name'])
			->addItem(': ')
			->addItem(convertUnitsS($datetime_to->getTimestamp() - $datetime_from->getTimestamp(), true));

		return $tag;
	}
}

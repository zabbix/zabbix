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
	 * Convert a list of schedule periods to string representation by weekday.
	 *
	 * @param array $schedule_rows						List of SLA periods, sorted by period_from.
	 * @param array $schedule_rows[n]['period_from']	Start timestamp within week.
	 * @param array $schedule_rows[n]['period_to]		End timestamp within week, may span across days.
	 *
	 * @return array E.g. [0 => '00:00-12:40, 20:30-21:00', 1 => ...]
	 */
	public static function convertScheduleToWeekdayPeriods(array $schedule_rows): array {
		$schedule_periods = array_fill(0, 7, '');

		for ($weekday = 0; $weekday < 7; $weekday++) {
			foreach ($schedule_rows as $schedule_row) {
				$period_from = max(SEC_PER_DAY * $weekday, $schedule_row['period_from']);
				$period_to = min(SEC_PER_DAY * ($weekday + 1), $schedule_row['period_to']);

				if ($period_to <= $period_from) {
					continue;
				}

				$period_from_str = (new DateTime('@'.($period_from - SEC_PER_DAY * $weekday)))->format('H:i');
				$period_to_str = (new DateTime('@'.($period_to - SEC_PER_DAY * $weekday)))->format('H:i');

				if ($period_to_str === '00:00') {
					$period_to_str = '24:00';
				}

				if ($schedule_periods[$weekday] !== '') {
					$schedule_periods[$weekday] .= ', ';
				}

				$schedule_periods[$weekday] .= $period_from_str.'-'.$period_to_str;
			}
		}

		return $schedule_periods;
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
	 * @param array $excluded_downtime
	 *
	 * @throws Exception
	 *
	 * @return CTag
	 */
	public static function getExcludedDowntimeTag(array $excluded_downtime): CTag {
		return new CDiv([
			zbx_date2str(DATE_TIME_FORMAT, $excluded_downtime['period_from']),
			' ',
			$excluded_downtime['name'],
			': ',
			convertUnitsS($excluded_downtime['period_to'] - $excluded_downtime['period_from'])
		]);
	}
}

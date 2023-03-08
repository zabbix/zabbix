<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CMaintenanceHelper {

	public static function getTimePeriodTypeNames(): array {
		static $timeperiod_type_names;

		if ($timeperiod_type_names === null) {
			$timeperiod_type_names = [
				TIMEPERIOD_TYPE_ONETIME => _('One time only'),
				TIMEPERIOD_TYPE_DAILY => _('Daily'),
				TIMEPERIOD_TYPE_WEEKLY => _('Weekly'),
				TIMEPERIOD_TYPE_MONTHLY => _('Monthly')
			];
		}

		return $timeperiod_type_names;
	}

	public static function getTimePeriodEveryNames(): array {
		static $timeperiod_every_names;

		if ($timeperiod_every_names === null) {
			$timeperiod_every_names = [
				1 => _('first'),
				2 => _x('second', 'adjective'),
				3 => _('third'),
				4 => _('fourth'),
				5 => _x('last', 'week of month')
			];
		}

		return $timeperiod_every_names;
	}

	public static function getTimePeriodSchedule(array $timeperiod): string {
		$hours = sprintf('%02d', floor($timeperiod['start_time'] / SEC_PER_HOUR));
		$minutes = sprintf('%02d', floor(($timeperiod['start_time'] % SEC_PER_HOUR) / SEC_PER_MIN));

		switch ($timeperiod['timeperiod_type']) {
			case TIMEPERIOD_TYPE_ONETIME:
				return zbx_date2str(DATE_TIME_FORMAT, $timeperiod['start_date']);

			case TIMEPERIOD_TYPE_DAILY:
				return _n('At %1$s:%2$s every day', 'At %1$s:%2$s every %3$s days', $hours, $minutes,
					$timeperiod['every']
				);

			case TIMEPERIOD_TYPE_WEEKLY:
				$week_days = '';

				for ($i = 0; $i < 7; $i++) {
					if ($timeperiod['dayofweek'] & 1 << $i) {
						if ($week_days !== '') {
							$week_days .= ', ';
						}
						$week_days .= getDayOfWeekCaption($i + 1);
					}
				}

				return _n('At %1$s:%2$s %3$s of every week', 'At %1$s:%2$s %3$s of every %4$s weeks', $hours, $minutes,
					$week_days, $timeperiod['every']
				);

			case TIMEPERIOD_TYPE_MONTHLY:
				$months = '';

				for ($i = 0; $i < 12; $i++) {
					if ($timeperiod['month'] & 1 << $i) {
						if ($months !== '') {
							$months .= ', ';
						}
						$months .= getMonthCaption($i + 1);
					}
				}

				if ($timeperiod['dayofweek'] > 0) {
					$week_days = '';

					for ($i = 0; $i < 7; $i++) {
						if ($timeperiod['dayofweek'] & 1 << $i) {
							if ($week_days !== '') {
								$week_days .= ', ';
							}
							$week_days .= getDayOfWeekCaption($i + 1);
						}
					}

					return _s('At %1$s:%2$s on %3$s %4$s of every %5$s', $hours, $minutes,
						self::getTimePeriodEveryNames()[$timeperiod['every']], $week_days, $months
					);
				}
				else {
					return _s('At %1$s:%2$s on day %3$s of every %4$s', $hours, $minutes, $timeperiod['day'], $months);
				}
		}

		return '';
	}
}

<?php declare(strict_types = 1);
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


abstract class CControllerSlaCreateUpdate extends CController {

	/**
	 * @param string $effective_date
	 * @param string $field_name
	 *
	 * @throws Exception
	 *
	 * @return int
	 */
	protected static function validateEffectiveDate(string $effective_date, string $field_name): int {
		$datetime = DateTime::createFromFormat('!'.DATE_FORMAT, $effective_date, new DateTimeZone('UTC'));
		$last_errors = DateTime::getLastErrors();

		if ($datetime === false || $last_errors['warning_count'] > 0 || $last_errors['error_count'] > 0) {
			throw new InvalidArgumentException(
				_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('a date is expected'))
			);
		}

		if ($datetime->getTimestamp() < 0 || $datetime->getTimestamp() > ZBX_MAX_DATE) {
			throw new InvalidArgumentException(_s('Incorrect value for field "%1$s": %2$s.', $field_name,
				_s('a date not later than %1$s is expected', zbx_date2str(DATE_FORMAT, ZBX_MAX_DATE, 'UTC'))
			));
		}

		return $datetime->getTimestamp();
	}

	/**
	 * @param array $schedule_enabled
	 * @param array $schedule_periods
	 *
	 * @return array
	 */
	protected static function validateCustomSchedule(array $schedule_enabled, array $schedule_periods): array {
		$schedule = [];

		$incorrect_schedule_exception = new InvalidArgumentException(
			_s('Incorrect schedule: %1$s.',
				_('comma separated list of time periods is expected for scheduled week days')
			)
		);

		foreach (range(0, 6) as $weekday) {
			if (!array_key_exists($weekday, $schedule_enabled)) {
				continue;
			}

			if (!array_key_exists($weekday, $schedule_periods)) {
				throw new InvalidArgumentException(_('Unexpected server error.'));
			}

			if (!is_string($schedule_periods[$weekday])) {
				throw new InvalidArgumentException(_('Unexpected server error.'));
			}

			$weekday_schedule_periods = trim($schedule_periods[$weekday]);

			if ($weekday_schedule_periods === '') {
				throw $incorrect_schedule_exception;
			}

			foreach (explode(',', $weekday_schedule_periods) as $schedule_period) {
				if (!preg_match('/^\s*(?<from_h>\d{1,2}):(?<from_m>\d{2})\s*-\s*(?<to_h>\d{1,2}):(?<to_m>\d{2})\s*$/',
						$schedule_period, $matches)) {
					throw $incorrect_schedule_exception;
				}

				$from_h = $matches['from_h'];
				$from_m = $matches['from_m'];
				$to_h = $matches['to_h'];
				$to_m = $matches['to_m'];

				$day_period_from = SEC_PER_HOUR * $from_h + SEC_PER_MIN * $from_m;
				$day_period_to = SEC_PER_HOUR * $to_h + SEC_PER_MIN * $to_m;

				if ($from_m > 59 || $to_m > 59 || $day_period_from >= $day_period_to || $day_period_to > SEC_PER_DAY) {
					throw $incorrect_schedule_exception;
				}

				$schedule[] = [
					'period_from' => SEC_PER_DAY * $weekday + $day_period_from,
					'period_to' => SEC_PER_DAY * $weekday + $day_period_to
				];
			}
		}

		if (!$schedule) {
			throw new InvalidArgumentException(_s('Incorrect schedule: %1$s.', _('cannot be empty')));
		}

		return $schedule;
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CItemDelayFlexValidator extends CValidator {

	private $time_period_validator;
	/**
	 * Returns true if the all of the given values and conditions match the criteria:
	 *
	 *		Flexible intervals must have valid delay and time period. Delay must not exceed 86400 seconds and
	 *		period should correspond to time period syntax %d-%d,%d:%d-%d:%d or %d,%d:%d-%d:%d
	 *		which is validated by CTimePeriodValidator.
	 *
	 *		Scheduling intervals must have valid month days 1-31, week days 1-7, hours 0-23, minutes 0-59
	 *		and seconds 0-59. Second month day, week day, hour, minute or second should not be greater than
	 *		first.
	 *
	 * @param array $intervals						An array of intervals to validate.
	 * @param string $intervals[]['type']			Interval type: flexible or scheduling.
	 *
	 * @return bool
	 */
	public function validate($intervals) {
		if (!$intervals) {
			return true;
		}

		$this->time_period_validator = new CTimePeriodValidator();

		$result = true;

		foreach ($intervals as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
				$result = $result && $this->validateFlexibleInterval($interval);
			}
			elseif ($interval['type'] == ITEM_DELAY_FLEX_TYPE_SCHEDULING) {
				$result = $result && $this->validateSchedulingInterval($interval);
			}
		}

		return $result;
	}

	/**
	 * Validate flexible interval delay and time period.
	 *
	 * @param array $interval						Data of flexible interval array.
	 * @param string $interval[]['delay']			Flexible interval delay.
	 * @param string $interval[]['period']			Flexible interval period.
	 *
	 * return bool
	 */
	private function validateFlexibleInterval($interval) {
		if ($interval['delay'] > SEC_PER_DAY) {
			$this->setError(_s('Invalid flexible interval delay: "%1$s" exceeds maximum delay of "%2$s".',
				$interval['delay'],
				SEC_PER_DAY
			));

			return false;
		}

		if ($this->time_period_validator->validate($interval['period'])) {
			return true;
		}
		else {
			$this->setError($this->time_period_validator->getError());

			return false;
		}
	}

	/**
	 * Validate scheduling interval month days, week days, hours, minutes and seconds.
	 *
	 * @param array $interval						Data of scheduling interval array.
	 * @param string $interval[]['interval']		An array containing month days, week days, hours, minutes, seconds.
	 * @param array $interval[]['md']				An array of month days.
	 * @param string $interval[]['md'][]['from']	Month day "from".
	 * @param string $interval[]['md'][]['till']	Month day "till".
	 * @param string $interval[]['md'][]['step']	Month day "step".
	 * @param array $interval[]['wd']				An array of week days.
	 * @param string $interval[]['wd'][]['from']	Week day "from".
	 * @param string $interval[]['wd'][]['till']	Week day "till".
	 * @param string $interval[]['wd'][]['step']	Week day "step".
	 * @param array $interval[]['h']				An array of hours.
	 * @param string $interval[]['h'][]['from']		Hours "from".
	 * @param string $interval[]['h'][]['till']		Hours "till".
	 * @param string $interval[]['h'][]['step']		Hour "step".
	 * @param array $interval[]['m']				An array of minutes.
	 * @param string $interval[]['m'][]['from']		Minutes "from".
	 * @param string $interval[]['m'][]['till']		Minutes "till".
	 * @param string $interval[]['m'][]['step']		Minute "step".
	 * @param array $interval[]['s']				An array of seconds.
	 * @param string $interval[]['s'][]['from']		Seconds "from".
	 * @param string $interval[]['s'][]['till']		Seconds "till".
	 * @param string $interval[]['s'][]['step']		Second "step".
	 *
	 * return bool
	 */
	private function validateSchedulingInterval($interval) {
		// Check month day boundaries.
		if (array_key_exists('md', $interval)) {
			foreach ($interval['md'] as $month_day) {
				if ($month_day['from'] !== '') {
					$month_day_from = (int) $month_day['from'];

					if ($month_day_from < 1 || $month_day_from > 31) {
						$this->setError(_s('Invalid interval "%1$s": invalid month day "%2$s".', $interval['interval'],
							$month_day['from']
						));

						return false;
					}

					// Ending month day is optional.
					if ($month_day['till'] !== '') {
						$month_day_till = (int) $month_day['till'];

						// Should be a valid month day.
						if ($month_day_till < 1 || $month_day_till > 31) {
							$this->setError(_s('Invalid interval "%1$s": invalid month day "%2$s".',
								$interval['interval'],
								$month_day['till']
							));

							return false;
						}

						// If entered, it cannot be greater than starting month day.
						if ($month_day_from > $month_day_till) {
							$this->setError(_s(
								'Invalid interval "%1$s": starting month day must be less or equal to ending month day.',
								$interval['interval']
							));

							return false;
						}

						// Month day step is optional.
						if ($month_day['step'] !== '') {
							$month_day_step = (int) $month_day['step'];

							if ($month_day_step < 1 || $month_day_step > 30
									|| ($month_day_step > ($month_day_till - $month_day_from))
									|| ($month_day_from == $month_day_till && $month_day_step != 1)) {
								$this->setError(_s('Invalid interval "%1$s": invalid month day step "%2$s".',
									$interval['interval'],
									$month_day['step']
								));

								return false;
							}
						}
					}
				}
				elseif ($month_day['step'] !== '') {
					$month_day_step = (int) $month_day['step'];

					// If month day is ommited, month day step is mandatory.
					if ($month_day_step < 1 || $month_day_step > 30) {
						$this->setError(_s('Invalid interval "%1$s": invalid month day step "%2$s".',
							$interval['interval'],
							$month_day['step']
						));

						return false;
					}
				}
			}
		}

		// Check week day boundaries.
		if (array_key_exists('wd', $interval)) {
			foreach ($interval['wd'] as $week_day) {
				if ($week_day['from'] !== '') {
					$week_day_from = (int) $week_day['from'];

					if ($week_day_from < 1 || $week_day_from > 7) {
						$this->setError(_s('Invalid interval "%1$s": invalid week day "%2$s".', $interval['interval'],
							$week_day['from']
						));

						return false;
					}

					// Ending week day is optional.
					if ($week_day['till'] !== '') {
						$week_day_till = (int) $week_day['till'];

						// Should be a valid week day.
						if ($week_day_till < 1 || $week_day_till > 7) {
							$this->setError(_s('Invalid interval "%1$s": invalid week day "%2$s".',
								$interval['interval'],
								$week_day['till']
							));

							return false;
						}

						// If entered, it cannot be greater than starting week day.
						if ($week_day_from > $week_day_till) {
							$this->setError(_s(
								'Invalid interval "%1$s": starting week day must be less or equal to ending week day.',
								$interval['interval']
							));

							return false;
						}

						// Week day step is optional.
						if ($week_day['step'] !== '') {
							$week_day_step = (int) $week_day['step'];

							if ($week_day_step < 1 || $week_day_step > 6
									|| ($week_day_step > ($week_day_till - $week_day_from))
									|| ($week_day_from == $week_day_till && $week_day_step != 1)) {
								$this->setError(_s('Invalid interval "%1$s": invalid week day step "%2$s".',
									$interval['interval'],
									$week_day['step']
								));

								return false;
							}
						}
					}
				}
				elseif ($week_day['step'] !== '') {
					$week_day_step = (int) $week_day['step'];

					// If week day is ommited, week day step is mandatory.
					if ($week_day_step < 1 || $week_day_step > 6) {
						$this->setError(_s('Invalid interval "%1$s": invalid week day step "%2$s".',
							$interval['interval'],
							$week_day['step']
						));

						return false;
					}
				}
			}
		}

		// Check hour boundaries.
		if (array_key_exists('h', $interval)) {
			foreach ($interval['h'] as $hours) {
				if ($hours['from'] !== '') {
					$hours_from = (int) $hours['from'];

					if ($hours_from > 23) {
						$this->setError(_s('Invalid interval "%1$s": invalid hours "%2$s".', $interval['interval'],
							$hours['from']
						));

						return false;
					}

					// Ending hour is optional.
					if ($hours['till'] !== '') {
						$hours_till = (int) $hours['till'];

						// Should be a valid hour.
						if ($hours_till > 23) {
							$this->setError(_s('Invalid interval "%1$s": invalid hours "%2$s".', $interval['interval'],
								$hours['till']
							));

							return false;
						}

						// If entered, it cannot be greater than starting hour.
						if ($hours_from > $hours_till) {
							$this->setError(_s(
								'Invalid interval "%1$s": starting hour must be less or equal to ending hour.',
								$interval['interval']
							));

							return false;
						}

						// Hour step is optional.
						if ($hours['step'] !== '') {
							$hour_step = (int) $hours['step'];

							if ($hour_step < 1 || $hour_step > 23
									|| ($hour_step > ($hours_till - $hours_from))
									|| ($hours_from == $hours_till && $hour_step != 1)) {
								$this->setError(_s('Invalid interval "%1$s": invalid hour step "%2$s".',
									$interval['interval'],
									$hours['step']
								));

								return false;
							}
						}
					}
				}
				elseif ($hours['step'] !== '') {
					$hour_step = (int) $hours['step'];

					// If hour is ommited, hour step is mandatory.
					if ($hour_step < 1 || $hour_step > 23) {
						$this->setError(_s('Invalid interval "%1$s": invalid hour step "%2$s".', $interval['interval'],
							$hours['step']
						));

						return false;
					}
				}
			}
		}

		// Check minute boundaries.
		if (array_key_exists('m', $interval)) {
			foreach ($interval['m'] as $minutes) {
				if ($minutes['from'] !== '') {
					$minutes_from = (int) $minutes['from'];

					if ($minutes_from > 59) {
						$this->setError(_s('Invalid interval "%1$s": invalid minutes "%2$s".', $interval['interval'],
							$minutes['from']
						));

						return false;
					}

					// Ending minute is optional.
					if ($minutes['till'] !== '') {
						$minutes_till = (int) $minutes['till'];

						// Should be a valid minute.
						if ($minutes_till > 59) {
							$this->setError(_s('Invalid interval "%1$s": invalid minutes "%2$s".',
								$interval['interval'], $minutes['till']
							));

							return false;
						}

						// If entered, it cannot be greater than starting minute.
						if ($minutes_from > $minutes_till) {
							$this->setError(_s(
								'Invalid interval "%1$s": starting minute must be less or equal to ending minute.',
								$interval['interval']
							));

							return false;
						}

						// Minute step is optional.
						if ($minutes['step'] !== '') {
							$minute_step = (int) $minutes['step'];

							if ($minute_step < 1 || $minute_step > 59
									|| ($minute_step > ($minutes_till - $minutes_from))
									|| ($minutes_from == $minutes_till && $minute_step != 1)) {
								$this->setError(_s('Invalid interval "%1$s": invalid minute step "%2$s".',
									$interval['interval'],
									$minutes['step']
								));

								return false;
							}
						}
					}
				}
				elseif ($minutes['step'] !== '') {
					$minute_step = (int) $minutes['step'];

					// If minute is ommited, minute step is mandatory.
					if ($minute_step < 1 || $minute_step > 59) {
						$this->setError(_s('Invalid interval "%1$s": invalid minute step "%2$s".',
							$interval['interval'],
							$minutes['step']
						));

						return false;
					}
				}
			}
		}

		// Check minute boundaries.
		if (array_key_exists('s', $interval)) {
			foreach ($interval['s'] as $seconds) {
				if ($seconds['from'] !== '') {
					$seconds_from = (int) $seconds['from'];

					if ($seconds_from > 59) {
						$this->setError(_s('Invalid interval "%1$s": invalid seconds "%2$s".', $interval['interval'],
							$seconds['from']
						));

						return false;
					}

					// Ending second is optional.
					if ($seconds['till'] !== '') {
						$seconds_till = (int) $seconds['till'];

						// Should be a valid second.
						if ($seconds_till > 59) {
							$this->setError(_s('Invalid interval "%1$s": invalid seconds "%2$s".',
								$interval['interval'],
								$seconds['till']
							));

							return false;
						}

						// If entered, it cannot be greater than starting second.
						if ($seconds_from > $seconds_till) {
							$this->setError(_s(
								'Invalid interval "%1$s": starting second must be less or equal to ending second.',
								$interval['interval']
							));

							return false;
						}

						// Second step is optional.
						if ($seconds['step'] !== '') {
							$second_step = (int) $seconds['step'];

							if ($second_step < 1 || $second_step > 59
									|| ($second_step > ($seconds_till - $seconds_from))
									|| ($seconds_from == $seconds_till && $second_step != 1)) {
								$this->setError(_s('Invalid interval "%1$s": invalid second step "%2$s".',
									$interval['interval'],
									$seconds['step']
								));

								return false;
							}
						}
					}
				}
				elseif ($seconds['step'] !== '') {
					$second_step = (int) $seconds['step'];

					// If second is ommited, second step is mandatory.
					if ($second_step < 1 || $second_step > 59) {
						$this->setError(_s('Invalid interval "%1$s": invalid second step "%2$s".',
							$interval['interval'],
							$seconds['step']
						));

						return false;
					}
				}
			}
		}

		return true;
	}
}

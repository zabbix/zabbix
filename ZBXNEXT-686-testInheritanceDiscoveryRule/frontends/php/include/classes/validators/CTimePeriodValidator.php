<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class CTimePeriodValidator extends CValidator {

	/**
	 * Validate multiple time periods.
	 * Time periods is a string with format:
	 *   'day1-day2,time1-time2;interval2;interval3;...' (day2 and last ';' are optional)
	 * Examples:
	 *   5-7,00:00-09:00;1,10:00-20:00;
	 *   5,0:0-9:0
	 *
	 * @param string $periods
	 *
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function validate($periods) {
		if (zbx_empty($periods)) {
			$this->setError(_('Empty time period.'));
			return false;
		}

		if ($this->options['allow_multiple']) {
			// remove one last ';'
			if ($periods[strlen($periods) - 1] === ';') {
				$periods = substr($periods, 0, strlen($periods) - 1);;
			}

			$periods = explode(';', $periods);
		}
		else {
			$periods = array($periods);
		}

		foreach ($periods as $period) {
			if (!$this->validateSinglePeriod($period)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate single time period.
	 * Time period is a string with format:
	 *   'day1-day2,time1-time2;' (day2 and ';' are optional)
	 * Examples:
	 *   5-7,00:00-09:00
	 *   5,0:00-9:00
	 *
	 * @param string $period
	 *
	 * @return bool
	 */
	protected  function validateSinglePeriod($period) {
		$daysRegExp = '(?P<day1>[1-7])(-(?P<day2>[1-7]))?';
		$time1RegExp = '(?P<hour1>20|21|22|23|24|[0-1]\d|\d):(?P<min1>[0-5]\d)';
		$time2RegExp = '(?P<hour2>20|21|22|23|24|[0-1]\d|\d):(?P<min2>[0-5]\d)';

		if (!preg_match('#^'.$daysRegExp.','.$time1RegExp.'-'.$time2RegExp.'$#', $period, $matches)) {
			$this->setError(_s('Incorrect time period "%1$s".', $period));
			return false;
		}

		if ($matches['hour2'] == '24' && $matches['min2'] != 0) {
			$this->setError(_s('Incorrect time period "%1$s".', $period));
			return false;
		}

		if (!zbx_empty($matches['day2']) && ($matches['day1'] > $matches['day2'])) {
			$this->setError(_s('Incorrect time period "%1$s" start day must be less or equal to end day.', $period));
			return false;
		}

		if (($matches['hour1'] > $matches['hour2'])
				|| (($matches['hour1'] == $matches['hour2']) && ($matches['min1'] >= $matches['min2']))) {
			$this->setError(_s('Incorrect time period "%1$s" start time must be less than end time.', $period));
			return false;
		}

		return true;
	}

	/**
	 * Set default options.
	 * Possible options:
	 *  - allow_multiple: assume that period string contains multiple periods separated by ';'
	 */
	protected function initOptions() {
		$this->options['allow_multiple'] = true;
	}
}

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

/**
 * Date and time helper.
 */
class CDateTimeHelper {

	/**
	 * Check if time zone observe the daylight saving time.
	 *
	 * @param string $timezone		time-zone name
	 *
	 * @return boolean
	 */
	public static function isDaylightSavingTime($timezone) {
		$zone = new DateTimeZone($timezone);
		$time = time();
		$transition = $zone->getTransitions($time, $time);

		return $transition[0]['isdst'];
	}

	/**
	 * Get the UTC offset for a specific time zone.
	 *
	 * @param string $timezone		time-zone name
	 *
	 * @return string
	 */
	public static function getUTCOffset($timezone) {
		$offset = (strtotime('now UTC') - strtotime('now '.$timezone));
		$sign = $offset >= 0 ? '+' : '-';
		$offset = abs($offset);

		return sprintf('UTC%s%02d:%02d', $sign, $offset / 3600, ($offset / 60) % 60);
	}

	/**
	 * Get time zone with UTC time usable as label in time-zone drop-downs.
	 *
	 * @param string $label		time zone name or default value of time-zone dropdown
	 *
	 * @return string
	 */
	public static function getTimeZoneFormat($label) {
		$timezone = ($label === 'System' || $label === 'System default') ? 'Europe/Riga' : $label;
		$utc = CDateTimeHelper::getUTCOffset($timezone);

		return (($label === 'System' || $label === 'System default') ? $label .': ': '').'('.$utc.') '.$timezone;
	}

	/**
	 * The days are counted from specific date and time period.
	 *
	 * @param string $date		timestamp
	 * @param string $period	time period
	 *
	 * @return int
	 */
	public static function countDays($date = 'now', $period = 'P1Y') {
		return (new DateTime($date))->diff((new DateTime($date))->sub(new DateInterval($period)))->days;
	}

	/**
	 * Get the time difference in months between two moments in time.
	 *
	 * @param string|int	$from		timestamp or string that represents the oldest moments in time
	 * @param string|int	$to			timestamp or string that represents the newest moments in time
	 *
	 * @return int
	 */
	public static function countMonthsBetweenDates($from, $to = 'now') {
		foreach ([&$from, &$to] as &$moment) {
			if (is_string($moment)) {
				$moment = strtotime($moment);
			}
		}
		unset($moment);

		return ((date('Y', $to) - date('Y', $from)) * 12) + ((date('m', $to) - date('m', $from)));
	}
}

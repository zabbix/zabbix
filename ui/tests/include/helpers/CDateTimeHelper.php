<?php
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
 * Date and time helper.
 */
class CDateTimeHelper {

	/**
	 * Check if daylight saving time is now.
	 *
	 * @return boolean
	 */
	public static function isDaylightSavingTime() {
		$zone = new DateTimeZone($timezone);
		$time = time();
		$transition = $zone->getTransitions($time, $time);

		return $transition[0]['isdst'];
	}

	/**
	 * Get the UTC time for a specific time zone.
	 *
	 * @param string $timezone		time-zone name
	 *
	 * @return string
	 */
	public static function getUTCTime($timezone) {
		$offset = (strtotime('today 12:00 am UTC') - strtotime('today 12:00 am '.$timezone));
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
		$utc = CDateTimeHelper::getUTCTime($timezone);

		if ($label === 'System' || $label === 'System default') {
			return $label.': ('.$utc.') '.$timezone;
		}
		else {
			return '('.$utc.') '.$timezone;
		}
	}

	/**
	 * Days count for the case when current or past year is leap year.
	 *
	 * @return int
	 */
	public static function countDays() {
		return (new DateTime())->diff((new DateTime())->sub(new DateInterval('P1Y')))->days;
	}
}

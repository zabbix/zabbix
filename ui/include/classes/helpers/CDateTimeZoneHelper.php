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


class CDateTimeZoneHelper {

	/**
	 * List of supported date time zones.
	 *
	 * @var array
	 */
	protected static $list;

	/**
	 * Returns  formatted list of supported time zones.
	 *
	 * @return array
	 */
	public function getAllDateTimeZones(): array {
		if (!self::$list) {
			self::prepareDateTimeZones();
		}

		return array_map(function($tz) {
			return $tz['formatted'];
		}, self::$list);
	}

	/**
	 * Function returns array containing numeric time-zone offset, passed time-zone identifier and fully formatted
	 * time-zone name to use in front-end.
	 *
	 * @param string $timezone  Time-zone identifier.
	 *
	 * @return array
	 */
	public static function getDateTimeZone(string $timezone): array {
		$offset = (int) (new DateTimeZone($timezone))->getOffset(new DateTime);
		$sign = ($offset < 0) ? '-' : '+';

		return [
			'offset' => $offset,
			'timezone' => $timezone,
			'formatted' => sprintf('(UTC%s%s) %s', $sign, gmdate('H:i', abs($offset)), $timezone)
		];
	}

	/**
	 * Function returns string usable as default value label in time-zone drop-downs.
	 *
	 * @return string
	 */
	public static function getDefaultDateTimeZone(): string {
		$timezone = CSettingsHelper::get(CSettingsHelper::DEFAULT_TIMEZONE);

		if ($timezone === ZBX_DEFAULT_TIMEZONE || $timezone === TIMEZONE_DEFAULT) {
			return self::getSystemDateTimeZone(_('System default'));
		}

		$offset = (int) (new DateTimeZone($timezone))->getOffset(new DateTime);
		$sign = ($offset < 0) ? '-' : '+';

		return sprintf('%s: (UTC%s%s) %s', _('System default'), $sign, gmdate('H:i', abs($offset)), $timezone);
	}

	/**
	 * Return string, if possible, containing formatted default PHP 'date.timezone'.
	 *
	 * @param string $label  (optional) Time-zone string prefix.
	 *
	 * @return string
	 */
	public static function getSystemDateTimeZone(string $label = ''): string {
		if (!self::$list) {
			self::prepareDateTimeZones();
		}

		$system_timezone = strtolower(ini_get('date.timezone'));
		$timezones_list = array_change_key_case(self::$list, CASE_LOWER);
		$label = $label ? $label : _('System');

		return array_key_exists($system_timezone, $timezones_list)
			? $label.': '.$timezones_list[$system_timezone]['formatted']
			: $label.' (UTC)';
	}

	/**
	 * Prepare list of time-zones sorted by offset and identifier.
	 */
	protected static function prepareDateTimeZones(): void {
		self::$list = DateTimeZone::listIdentifiers();
		self::$list = array_combine(self::$list, array_map('self::getDateTimeZone', self::$list));
		CArrayHelper::sort(self::$list, ['offset', 'timezone']);
	}
}

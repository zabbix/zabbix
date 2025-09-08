<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CTimezoneHelper {

	/**
	 * Get list of supported timezones.
	 * Example: ["Europe/London" => "(UTC+00:00) Europe/London", ...]
	 *
	 * @return array
	 */
	public static function getList(): array {
		static $timezones;

		if ($timezones === null) {
			$timezones = [];

			foreach (DateTimeZone::listIdentifiers() as $timezone) {
				$offset = (new DateTimeZone($timezone))->getOffset(new DateTime());

				$timezones[$timezone] = [
					'offset' => $offset,
					'timezone' => $timezone,
					'title' => '(UTC'.($offset < 0 ? '-' : '+').gmdate('H:i', abs($offset)).') '.$timezone
				];
			}

			CArrayHelper::sort($timezones, ['offset', 'timezone']);

			$timezones = array_column($timezones, 'title', 'timezone');
		}

		return $timezones;
	}

	/**
	 * Get timezone title, optionally prefixed.
	 *
	 * @param string      $timezone
	 * @param string|null $prefix
	 *
	 * @return string
	 */
	public static function getTitle(string $timezone, ?string $prefix = null): string {
		$timezone_title = self::getList()[$timezone];

		if ($prefix === null) {
			return $timezone_title;
		}

		return $prefix.': '.$timezone_title;
	}

	/**
	 * Is timezone supported?
	 *
	 * @param string $timezone
	 *
	 * @return bool
	 */
	public static function isSupported(string $timezone): bool {
		return array_key_exists($timezone, self::getList());
	}

	/**
	 * Get system timezone.
	 * Example: "Europe/London"
	 *
	 * @return string
	 */
	public static function getSystemTimezone(): string {
		$system_timezone_lower = strtolower(ini_get('date.timezone'));

		foreach (array_keys(self::getList()) as $timezone) {
			if ($system_timezone_lower === strtolower($timezone)) {
				return $timezone;
			}
		}

		return 'UTC';
	}
}

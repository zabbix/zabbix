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


/**
 * A class designed to perform actions and contain constants related to SLA.
 */
class CSlaHelper {

	public const SLA_STATUS_ANY			= -1;
	public const SLA_STATUS_ENABLED		= 0;
	public const SLA_STATUS_DISABLED	= 1;

	public const PERIOD_DAILY		= 0;
	public const PERIOD_WEEKLY		= 1;
	public const PERIOD_MONTHLY		= 2;
	public const PERIOD_QUARTERLY	= 3;
	public const PERIOD_ANNUALLY	= 4;

	public const SCHEDULE_MODE_NONSTOP	= 0;
	public const SCHEDULE_MODE_CUSTOM	= 1;

	public static function periodToStr(int $period): ?string {
		static $period_strings;
		
		if ($period_strings === null) {
			$period_strings = [
				self::PERIOD_DAILY => _('Daily'),
				self::PERIOD_WEEKLY => _('Weekly'),
				self::PERIOD_MONTHLY => _('Monthly'),
				self::PERIOD_QUARTERLY => _('Quarterly'),
				self::PERIOD_ANNUALLY => _('Annually')
			];
		}

		return array_key_exists($period, $period_strings)
			? $period_strings[$period]
			: null;
	}

	public static function scheduleToStr(int $schedule_mode): ?string {
		static $schedule_modes;

		if ($schedule_modes === null) {
			$schedule_modes = [
				self::SCHEDULE_MODE_NONSTOP => _('24x7'),
				self::SCHEDULE_MODE_CUSTOM => _('Custom')
			];
		}

		return array_key_exists($schedule_mode, $schedule_modes)
			? $schedule_modes[$schedule_mode]
			: null;
	}
}

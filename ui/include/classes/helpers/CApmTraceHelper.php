<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CApmTraceHelper {

	public static function getName(int $type): string {
		return match ($type) {
			APM_TRACE_STATUS_OK => _('Ok'),
			APM_TRACE_STATUS_ERROR => _('Error'),
			APM_TRACE_STATUS_UNSET => _('Unset'),
			default => _('Unknown')
		};
	}

	public static function getStatuses(int $min = APM_TRACE_STATUS_OK, int $max = APM_TRACE_STATUS_UNSET): array {
		$types = [];

		foreach (range($min, $max) as $type) {
			$types[] = [
				'label' => self::getName($type),
				'value' => $type
			];
		}

		return $types;
	}
}

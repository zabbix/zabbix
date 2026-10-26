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


class CApmMetricHelper {

	public static function getName(int $type): string {
		return match ($type) {
			APM_METRIC_TYPE_GAUGE => _('Gauge'),
			APM_METRIC_TYPE_SUM => _('Summary'),
			APM_METRIC_TYPE_HISTOGRAM => _('Histogram'),
			APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM => _('Exponential histogram'),
			default => _('Unknown')
		};
	}

	public static function getTypes(int $min = APM_METRIC_TYPE_GAUGE,
			int $max = APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM): array {

		$types = [];

		foreach (range($min, $max) as $type) {
			$types[$type] = [
				'label' => self::getName($type),
				'value' => $type
			];
		}

		return $types;
	}
}

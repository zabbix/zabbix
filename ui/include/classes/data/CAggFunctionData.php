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


/**
 * Class containing information on capabilities of aggregation functions.
 */
class CAggFunctionData {

	/**
	 * Check whether value mapping can be applied on data aggregated using the specified function.
	 *
	 * @param int $function  Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG,
	 *                       AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 *
	 * @return bool
	 */
	public static function preservesValueMapping(int $function): bool {
		return in_array($function, [AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_FIRST,
			AGGREGATE_LAST
		]);
	}

	/**
	 * Check whether units are preserved on data aggregated using the specified function.
	 *
	 * @param int $function  Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG,
	 *                       AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 *
	 * @return bool
	 */
	public static function preservesUnits(int $function): bool {
		return $function != AGGREGATE_COUNT;
	}

	/**
	 * Check whether only numeric item values can be aggregated using the specified function.
	 *
	 * @param int $function   Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG,
	 *                        AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 *
	 * @return bool
	 */
	public static function requiresNumericItem(int $function): bool {
		return in_array($function, [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_SUM]);
	}

	/**
	 * Check whether the aggregation result of the specified function is always numeric.
	 *
	 * @param int $function   Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG,
	 *                        AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 *
	 * @return bool
	 */
	public static function isNumericResult(int $function): bool {
		return in_array($function, [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM]);
	}
}

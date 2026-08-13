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


class CTelemetryHelper {

	// Sections of the column configuration.
	public const SECTION_COLUMNS = 'columns';
	public const SECTION_AGGREGATED_COLUMNS = 'aggregated_columns';
	public const SECTION_CONDITIONS = 'conditions';

	/**
	 * Column names allowed in the given section for the given signal/metric type. An unknown section,
	 * signal type or metric point type yields an empty list.
	 *
	 * @return array
	 */
	public static function getSectionColumns(string $section, int $signal_type,
			int $metric_point_type = CItemTypeTelemetryQuery::METRICS_POINT_SUM): array {
		switch ($signal_type) {
			case CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES:
				$columns = [
					self::SECTION_COLUMNS => CItemTypeTelemetryQuery::TRACES_COLUMNS_COLUMN,
					self::SECTION_AGGREGATED_COLUMNS => CItemTypeTelemetryQuery::TRACES_AGGREGATED_COLUMN,
					self::SECTION_CONDITIONS => CItemTypeTelemetryQuery::TRACES_CONDITIONS_COLUMN
				];
				break;

			case CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS:
				$columns = [
					self::SECTION_COLUMNS => CItemTypeTelemetryQuery::LOGS_COLUMNS_COLUMN,
					self::SECTION_AGGREGATED_COLUMNS => CItemTypeTelemetryQuery::LOGS_AGGREGATED_COLUMN,
					self::SECTION_CONDITIONS => CItemTypeTelemetryQuery::LOGS_CONDITIONS_COLUMN
				];
				break;

			case CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS:
				$columns = [
					self::SECTION_COLUMNS =>
						CItemTypeTelemetryQuery::METRICS_COLUMNS_COLUMN[$metric_point_type] ?? [],
					self::SECTION_AGGREGATED_COLUMNS =>
						CItemTypeTelemetryQuery::METRICS_AGGREGATED_COLUMN[$metric_point_type] ?? [],
					self::SECTION_CONDITIONS =>
						CItemTypeTelemetryQuery::METRICS_CONDITIONS_COLUMN[$metric_point_type] ?? []
				];
				break;

			default:
				$columns = [];
				break;
		}

		return $columns[$section] ?? [];
	}

	/**
	 * Telemetry query item column configuration, keyed by section, then by signal_type (metrics keyed additionally
	 * by metric_point_type).
	 *
	 * @return array
	 */
	public static function getColumnConfig(): array {
		$config = [];
		$metric_point_types = [
			CItemTypeTelemetryQuery::METRICS_POINT_SUM,
			CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
			CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM,
			CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM
		];
		$signal_types = [
			CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS,
			CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS
		];

		foreach ([self::SECTION_COLUMNS, self::SECTION_AGGREGATED_COLUMNS, self::SECTION_CONDITIONS] as $section) {
			foreach ($signal_types as $signal_type) {
				if ($signal_type == CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS) {
					foreach ($metric_point_types as $metric_point_type) {
						$config[$section][$signal_type][$metric_point_type] =
							self::getSectionColumns($section, $signal_type, $metric_point_type);
					}
				}
				else {
					$config[$section][$signal_type] = self::getSectionColumns($section, $signal_type);
				}
			}
		}

		$config['complex'] = CItemTypeTelemetryQuery::COMPLEX_COLUMN_NAME;

		return $config;
	}

	/**
	 * Validation rules restricting the column name of the given section to the names allowed for the selected
	 * signal/metric type. One rule set per signal type, and per metric point type for metrics, so that together
	 * they cover every selection. Intended as the "column" field rules of a "columns", "aggregated_columns" or
	 * "conditions" row, and of the aggregated column popup.
	 *
	 * @return array
	 */
	public static function getColumnValidationRules(string $section): array {
		$function_when = $section === self::SECTION_AGGREGATED_COLUMNS
			? [['function', 'in' => [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_SUM, AGGREGATE_PCTILE]]]
			: [];

		return [
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES),
				'messages' => ['in' => _('This column is not available for the selected category.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES]],
					...$function_when
				]
			],
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS),
				'messages' => ['in' => _('This column is not available for the selected category.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS]],
					...$function_when
				]
			],
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::METRICS_POINT_SUM
				),
				'messages' => ['in' => _('This column is not available for the selected metric points.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS]],
					['../metric_point_type', 'in' => [CItemTypeTelemetryQuery::METRICS_POINT_SUM]],
					...$function_when
				]
			],
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::METRICS_POINT_GAUGE
				),
				'messages' => ['in' => _('This column is not available for the selected metric points.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS]],
					['../metric_point_type', 'in' => [CItemTypeTelemetryQuery::METRICS_POINT_GAUGE]],
					...$function_when
				]
			],
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM
				),
				'messages' => ['in' => _('This column is not available for the selected metric points.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS]],
					['../metric_point_type', 'in' => [CItemTypeTelemetryQuery::METRICS_POINT_HISTOGRAM]],
					...$function_when
				]
			],
			['string', 'required', 'not_empty',
				'in' => self::getSectionColumns($section, CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM
				),
				'messages' => ['in' => _('This column is not available for the selected metric points.')],
				'when' => [
					['../signal_type', 'in' => [CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS]],
					['../metric_point_type', 'in' => [CItemTypeTelemetryQuery::METRICS_POINT_EXPHISTOGRAM]],
					...$function_when
				]
			]
		];
	}

	/**
	 * Display labels for the aggregation functions, keyed by AGGREGATE_* constants.
	 *
	 * @return array
	 */
	public static function getFunctionLabels(): array {
		return [
			AGGREGATE_MIN => _('min'),
			AGGREGATE_MAX => _('max'),
			AGGREGATE_AVG => _('avg'),
			AGGREGATE_COUNT => _('count'),
			AGGREGATE_SUM => _('sum'),
			AGGREGATE_PCTILE => _('percentile')
		];
	}

	/**
	 * Display labels for the condition operators, keyed by CONDITION_OPERATOR_* constants.
	 *
	 * @return array
	 */
	public static function getOperatorLabels(): array {
		return [
			CONDITION_OPERATOR_EQUAL => _('equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('does not equal'),
			CONDITION_OPERATOR_LIKE => _('contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('does not contain'),
			CONDITION_OPERATOR_EXISTS => _('exists')
		];
	}

	/**
	 * Condition operators allowed per column type.
	 *
	 * @return array
	 */
	public static function getConditionOperators(): array {
		return [
			'complex' => [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_EXISTS
			],
			'simple' => [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			]
		];
	}

	/**
	 * Column options (name => name) available in the Aggregated columns table, for the given signal/metric type.
	 *
	 * @return array
	 */
	public static function getAggregatedColumnOptions(int $signal_type, int $metric_point_type): array {
		$names = self::getSectionColumns(self::SECTION_AGGREGATED_COLUMNS, $signal_type, $metric_point_type);

		return array_combine($names, $names);
	}

	/**
	 * Column options (name => name) available in the Conditions table, sorted alphabetically, for the given
	 * signal/metric type.
	 *
	 * @return array
	 */
	public static function getConditionColumnOptions(int $signal_type, int $metric_point_type): array {
		$names = self::getSectionColumns(self::SECTION_CONDITIONS, $signal_type, $metric_point_type);
		sort($names);

		return array_combine($names, $names);
	}
}

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


class CAggHelper {
	/**
	 * Prepare aggregated item value for displaying, apply value map and/or convert units if appropriate for
	 * the aggregation function.
	 *
	 * @param int|float|string $value
	 * @param int|string       $value_type                  Value type (ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR,
	 *                                                      ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
	 *                                                      ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY,
	 * 														ITEM_VALUE_TYPE_JSON).
	 * @param int              $function                    Aggregation function (AGGREGATE_NONE, AGGREGATE_MIN,
	 *                                                      AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT,
	 *                                                      AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 * @param string           $units
	 * @param array            $options
	 *        bool             $options['force_units']      Whether to keep units despite the aggregation function
	 *                                                      not supporting it.
	 *        bool             $options['trim']             Whether to trim non-numeric value to a length of 20 characters.
	 *        array            $options['valuemap']
	 *        array            $options['convert_options']  Options for unit conversion. See @convertUnitsRaw.
	 *
	 * @return array
	 */
	public static function formatValue($value, $value_type, int $function, string $units, array $options = []): array {
		$options = array_merge([
			'force_units' => false,
			'trim' => true,
			'valuemap' => [],
			'convert_options' => []
		], $options);

		$units = $options['force_units'] || CAggFunctionData::preservesUnits($function) ? $units : '';

		$is_numeric_item = in_array($value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
		$is_numeric_data = $is_numeric_item || CAggFunctionData::isNumericResult($function);

		if ($is_numeric_data) {
			$converted_value = convertUnitsRaw([
				'value' => $value,
				'units' => $units
			] + $options['convert_options']);

			$display_value = $converted_value['value'].($converted_value['units'] !== ''
				? ' '.$converted_value['units'] :
				''
			);
		}
		else {
			switch ($value_type) {
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_TEXT:
				case ITEM_VALUE_TYPE_LOG:
				case ITEM_VALUE_TYPE_JSON:
					$display_value = $options['trim'] && mb_strlen($value) > 20
						? mb_substr($value, 0, 20).'...'
						: $value;
					break;

				case ITEM_VALUE_TYPE_BINARY:
					$display_value = _('binary value');
					break;

				default:
					$display_value = _('Unknown value type');
			}
		}

		if (in_array($value_type, [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR])
				&& CAggFunctionData::preservesValueMapping($function)) {

			$mapped_value = CValueMapHelper::getMappedValue($value_type, (string) $value, $options['valuemap']);

			if ($mapped_value !== false) {
				return [
					'value' => $mapped_value.' ('.$display_value.')',
					'units' => '',
					'is_mapped' => true
				];
			}
		}

		return $is_numeric_data
			? [
				'value' => $converted_value['value'],
				'units' => $converted_value['units'],
				'is_mapped' => false
			]
			: [
				'value' => $display_value,
				'units' => $units,
				'is_mapped' => false
			];
	}
}

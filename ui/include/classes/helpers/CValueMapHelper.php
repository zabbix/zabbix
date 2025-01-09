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


class CValueMapHelper {

	/**
	 * Apply value mapping to value.
	 * If value map or mapping is not found, unchanged value is returned,
	 * otherwise mapped value returned in format: "<mapped_value> (<initial_value>)".
	 *
	 * @param int    $value_type            Item value type.
	 * @param string $value                 Value that mapping should be applied to.
	 * @param array  $valuemap              Valuemap array.
	 * @param array  $valuemap['mappings']  (optional) Valuemap mappings array.
	 *
	 * @return string
	 */
	public static function applyValueMap($value_type, string $value, array $valuemap): string {
		$newvalue = static::getMappedValue($value_type, $value, $valuemap);

		return ($newvalue !== false) ? $newvalue.' ('.$value.')' : $value;
	}

	/**
	 * Get mapping for value.
	 *
	 * @param int    $value_type            Item value type.
	 * @param string $value                 Value that mapping should be applied to.
	 * @param array  $valuemap              Valuemap array.
	 * @param array  $valuemap['mappings']  Valuemap mappings array.
	 *
	 * @return string|bool     If there is no mapping return false, return mapped value otherwise.
	 */
	public static function getMappedValue($value_type, string $value, array $valuemap) {
		$newvalue = false;

		if (array_key_exists('mappings', $valuemap)) {
			foreach ($valuemap['mappings'] as $mapping) {
				if ($mapping['type'] == VALUEMAP_MAPPING_TYPE_DEFAULT) {
					$newvalue = $mapping['newvalue'];
				}
				elseif (static::matchMapping($value_type, $value, $mapping)) {
					$newvalue = $mapping['newvalue'];

					break;
				}
			}
		}

		return $newvalue;
	}

	/**
	 * Check value match against single mapping. Return true on success, false otherwise.
	 *
	 * @param int    $value_type       Item value type.
	 * @param string $value            Value to check against mapping.
	 * @param array  $mapping          Array of single mapping.
	 * @param string $mapping['type']  Type of mapping.
	 * @param string $mapping['value]  Value of mapping.
	 */
	public static function matchMapping($value_type, string $value, array $mapping): bool {
		$match_numeric = ($value_type == ITEM_VALUE_TYPE_FLOAT || $value_type == ITEM_VALUE_TYPE_UINT64);
		$result = false;

		switch ($mapping['type']) {
			case VALUEMAP_MAPPING_TYPE_EQUAL:
				$result = $match_numeric
					? (is_numeric($mapping['value']) && floatval($value) == floatval($mapping['value']))
					: ($value === $mapping['value']);

				break;

			case VALUEMAP_MAPPING_TYPE_GREATER_EQUAL:
				$result = ($match_numeric && floatval($value) >= floatval($mapping['value']));

				break;

			case VALUEMAP_MAPPING_TYPE_LESS_EQUAL:
				$result = ($match_numeric && floatval($value) <= floatval($mapping['value']));

				break;

			case VALUEMAP_MAPPING_TYPE_IN_RANGE:
				if (!$match_numeric) {
					break;
				}

				$ranges_parser = new CRangesParser(['with_minus' => true, 'with_float' => true, 'with_suffix' => true]);

				if ($ranges_parser->parse($mapping['value']) == CParser::PARSE_SUCCESS) {
					$value = floatval($value);

					foreach ($ranges_parser->getRanges() as $ranges) {
						if ($value == floatval($ranges[0])
								|| (count($ranges) == 2 && $value >= floatval($ranges[0])
								&& $value <= floatval($ranges[1]))) {
							$result = true;

							break;
						}
					}
				}

				break;

			case VALUEMAP_MAPPING_TYPE_REGEXP:
				$result = (!$match_numeric
					&& @preg_match('/'.str_replace('/', '\/', $mapping['value']).'/', $value) == 1);

				break;

			case VALUEMAP_MAPPING_TYPE_DEFAULT:
				$result = true;

				break;
		}

		return $result;
	}
}

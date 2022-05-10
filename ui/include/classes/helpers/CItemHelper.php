<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * A helper class for Items and their derivatives.
 */
class CItemHelper {

	/**
	 * Create an index of fields involved in conditional checks needed to determine presence of further input fields.
	 *
	 * @param array $field_rules  API input rule fields.
	 *
	 * @return array  Resulting index of input fields to be fed into validation for if-conditions to work.
	 *                Note: fields used in if-closures may need to be added manually.
	 */
	public static function extractConditionFields(array $field_rules): array {
		$result = [];

		foreach ($field_rules as $field => $rule) {
			if ($rule['type'] == API_UNEXPECTED) {
				continue;
			}

			if (array_key_exists('fields', $rule)) {
				$subfields = self::extractConditionFields($rule['fields']);

				if ($subfields) {
					$result[$field] = [$subfields];
				}
			}

			if (array_key_exists('rules', $rule)) {
				foreach ($rule['rules'] as $field_rule) {
					if (array_key_exists('if', $field_rule) && is_array($field_rule['if'])) {
						if (array_key_exists('field', $field_rule['if'])) {
							$result[$field_rule['if']['field']] = true;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Checks whether a field is expected as a result of one of the multiple-rules getting triggered.
	 *
	 * @param mixed $multiple_rules  Conditional rules to check against.
	 * @param mixed $data            Fields and values on current input level.
	 * @param array|null $db_item    To be reachable in case of if-closure.
	 *
	 * @return bool  Whether field is expected to be present in input.
	 */
	private static function fieldExpected($multiple_rules, $data, ?array $db_item) {
		foreach ($multiple_rules as $multiple_rule) {
			$if_rule = array_key_exists('if', $multiple_rule) ? $multiple_rule['if'] : null;

			$rule_applies = is_array($if_rule)
				&& CApiInputValidator::isInRange($data[$if_rule['field']], $if_rule['in']);
			$rule_applies = $rule_applies || ($if_rule instanceof Closure && call_user_func($if_rule, $data));
			$rule_applies = $rule_applies || array_key_exists('else', $multiple_rule);

			if ($rule_applies) {
				return $multiple_rule['type'] != API_UNEXPECTED;
			}
		}

		return false;
	}

	/**
	 * Go through a rule's 'fields' conditions and index them as a (normalized) array, if presence expected.
	 *
	 * @param bool $as_objects     Whether (true) a collection of entries or (false) a single entry is expected.
	 * @param array $fields        List of field/type conditions.
	 * @param array $data          Fields and values on currrent input level.
	 * @param array|null $db_item  To pass in case of if-closure.
	 *
	 * @return array
	 */
	private static function extractSubFields(bool $as_objects, array $fields, array $data, ?array $db_item): array {
		$result = [];

		foreach ($fields as $field => $rule) {
			if ($rule['type'] == API_UNEXPECTED) {
				continue;
			}

			if ($as_objects) {
				foreach ($data as $index => $entry) {
					if (array_key_exists('rules', $rule)) {
						if (self::fieldExpected($rule['rules'], $entry, $db_item)) {
							$result[$index][$field] = true;
						}
					}
					else {
						$result[$index][$field] = true;
					}

					if (array_key_exists('fields', $rule) && array_key_exists($index, $result)
							&& array_key_exists($field, $result[$index])) {
						$result[$index][$field] = self::extractSubFields($rule['type'] == API_OBJECTS,
							$rule['fields'], array_key_exists($field, $entry) ? $entry[$field] : [], $db_item
						);
					}
				}
			}
			else {
				if (array_key_exists('rules', $rule)) {
					if (self::fieldExpected($rule['rules'], $data, $db_item)) {
						$result[$field] = true;
					}
				}
				else {
					$result[$field] = true;
				}

				if (array_key_exists('fields', $rule) && array_key_exists($field, $result)) {
					$result[$field] = self::extractSubFields($rule['type'] == API_OBJECTS,
						$rule['fields'], array_key_exists($field, $data) ? $data[$field] : [], $db_item
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Create an index of expected fields to apply to form input,
	 * leaving out fields not applicable to item type and other conditions.
	 *
	 * @param array $api_field_rules  Overall validation rules.
	 * @param array $data             Form input.
	 * @param array|null $db_item     In case of update.
	 *
	 * @return array  A field-index of inputs to collect.
	 */
	public static function extractExpectedFieldIndex(array $api_field_rules, array $data, ?array $db_item): array {
		$result = array_fill_keys(array_keys($api_field_rules['fields']), true);

		foreach ($api_field_rules['fields'] as $field => $rule) {
			if ($rule['type'] == API_UNEXPECTED) {
				continue;
			}

			if (array_key_exists('rules', $rule)) {
				if (!self::fieldExpected($rule['rules'], $data, $db_item)) {
					unset($result[$field]);
					continue;
				}
			}

			if (array_key_exists('fields', $rule)) {
				$result[$field] = self::extractSubFields($rule['type'] == API_OBJECTS,
					$rule['fields'], array_key_exists($field, $data) ? $data[$field] : [], $db_item
				);
			}
		}

		return $result;
	}

	/**
	 * Map the field index to form input, leaving out non-expected fields.
	 *
	 * @param array $fields_index  Structure of input fields expected.
	 * @param array $data          Form input.
	 * @param array $defaults      Item field default values.
	 *
	 * @return array  Item corresponding to the mapped out structure.
	 */
	public static function combineFromFieldIndex(array $fields_index, array $data, array $defaults = []): array {
		$fields = array_flip(array_keys($fields_index));
		$result = array_intersect_key($data, $fields) + array_intersect_key($defaults, $fields);

		foreach ($fields_index as $field => $structure) {
			if (is_array($structure) && $structure) {
				if (is_numeric(key($structure))) {
					$result[$field] = [];

					foreach ($data[$field] as $i => $entry) {
						$result[$field][] = self::combineFromFieldIndex($structure[$i], $entry);
					}
				}
				else {
					$result = self::combineFromFieldIndex($structure, $data);
				}
			}
		}

		return $result;
	}
}

<?php
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


/**
 * Application performance monitoring general API implementation.
 */
class CApmGeneral extends CApiService {

	protected static function fixOptionsForClickHouse(array $options, array $output_fields): array {
		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = array_keys($output_fields);
		}

		$output = [];

		foreach ($options['output'] as $field) {
			if (is_array($output_fields[$field])) {
				$output = array_merge($output, $output_fields[$field]);
			}
			else {
				$output[] = $output_fields[$field];
			}
		}

		$options['output'] = $output;

		if ($options['filter'] !== null) {
			$filter = [];

			foreach ($options['filter'] as $key => $value) {
				$filter[$output_fields[$key]] = $value;
			}

			$options['filter'] = $filter;
		}

		if ($options['search'] !== null) {
			$search = [];

			foreach ($options['search'] as $key => $value) {
				$search[$output_fields[$key]] = $value;
			}

			$options['search'] = $search;
		}

		$options['sortfield'] = array_map(static fn (string $value) => $output_fields[$value], $options['sortfield']);

		return $options;
	}

	protected static function fixRowForClickHouse(array $db_row, array $fields_spec): array {
		$row = [];

		foreach ($db_row as $field => $value) {
			if (is_array($fields_spec[$field])) {
				if (!array_key_exists($fields_spec[$field][0], $row)) {
					$row[$fields_spec[$field][0]] = [];
				}

				foreach ($value as $index => $sub_value) {
					$row[$fields_spec[$field][0]][$index][$fields_spec[$field][1]] = $sub_value;
				}
			}
			else {
				$row[$fields_spec[$field]] = $value;
			}
		}

		return $row;
	}
}

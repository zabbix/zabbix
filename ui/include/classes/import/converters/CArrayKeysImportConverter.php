<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Convert array keys to numeric.
 */
class CArrayKeysImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		$data['zabbix_export'] = $this->normalizeArrayKeys($data['zabbix_export'], $this->rules);

		return $data;
	}

	/**
	 * Convert array keys to numeric.
	 *
	 * @param mixed $data   Import data.
	 * @param array $rules  XML rules.
	 *
	 * @return array
	 */
	protected function normalizeArrayKeys($data, array $rules) {
		if (!is_array($data)) {
			return $data;
		}

		if ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists('ex_rules', $tag_rules)) {
					$tag_rules = call_user_func($tag_rules['ex_rules'], $data);
				}

				if (array_key_exists($tag, $data)) {
					$data[$tag] = $this->normalizeArrayKeys($data[$tag], $tag_rules);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $tag => $value) {
				$data[$tag] = $this->normalizeArrayKeys($value, $rules['rules'][$prefix]);
			}

			$data = array_values($data);
		}

		return $data;
	}
}

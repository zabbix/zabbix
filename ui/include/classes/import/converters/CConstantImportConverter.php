<?php
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
 * Convert constants to their values.
 */
class CConstantImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		$data['zabbix_export'] = $this->replaceConstant($data['zabbix_export'], $this->rules);

		return $data;
	}

	/**
	 * Convert human readable import constants to values Zabbix API can work with.
	 *
	 * @param mixed  $data   Import data.
	 * @param array  $rules  XML rules.
	 * @param string $path   XML path (for error reporting).
	 *
	 * @throws Exception if the element is invalid.
	 *
	 * @return mixed
	 */
	protected function replaceConstant($data, array $rules, $path = '') {
		if ($rules['type'] & XML_STRING) {
			if (!array_key_exists('in', $rules)) {
				return $data;
			}

			$flip = array_flip($rules['in']);

			if (!array_key_exists($data, $flip)) {
				throw new Exception(
					_s('Invalid tag "%1$s": %2$s.', $path, _s('unexpected constant value "%1$s"', $data))
				);
			}

			$data = (string) $flip[$data];
		}
		elseif ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists($tag, $data)) {
					if (array_key_exists('ex_rules', $tag_rules)) {
						$tag_rules = call_user_func($tag_rules['ex_rules'], $data);
					}

					$data[$tag] = $this->replaceConstant($data[$tag], $tag_rules, $tag);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $tag => $value) {
				$data[$tag] = $this->replaceConstant($value, $rules['rules'][$prefix]);
			}
		}

		return $data;
	}
}

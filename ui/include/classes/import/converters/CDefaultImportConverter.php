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
 * Add tags with default values.
 */
class CDefaultImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		foreach ($this->rules['rules'] as $tag => $tag_rules) {
			if (!array_key_exists($tag, $data['zabbix_export'])) {
				continue;
			}

			$data['zabbix_export'][$tag] = $this->addDefaultValue($data['zabbix_export'][$tag], $tag_rules);
		}

		return $data;
	}

	/**
	 * Add default values in place of missed tags.
	 *
	 * @param mixed $data   Import data.
	 * @param array $rules  XML rules.
	 *
	 * @return mixed
	 */
	protected function addDefaultValue($data, array $rules) {
		if ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists($tag, $data)) {
					$data[$tag] = $this->addDefaultValue($data[$tag], $tag_rules);
				}
				elseif (array_key_exists('ex_default', $tag_rules)) {
					$data[$tag] = (string) call_user_func($tag_rules['ex_default'], $data);
				}
				elseif (array_key_exists('default', $tag_rules)) {
					$data[$tag] = (string) $tag_rules['default'];
				}
				else {
					$data[$tag] = (($tag_rules['type'] & XML_STRING) ? '' : []);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $key => $value) {
				$data[$key] = $this->addDefaultValue($value, $rules['rules'][$prefix]);
			}
		}

		return $data;
	}
}

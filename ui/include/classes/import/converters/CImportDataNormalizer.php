<?php declare(strict_types = 0);
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
 * Class to normalize incoming data.
 */
class CImportDataNormalizer {

	protected $rules;

	public const EOL_LF = 0x01;
	public const LOWERCASE = 0x02;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function normalize(array $data): array {
		$data['zabbix_export'] = $this->convert($data['zabbix_export'], $this->rules);

		return $data;
	}

	/**
	 * Convert array keys to numeric and normalize strings.
	 *
	 * @param mixed $data   Import data.
	 * @param array $rules  Schema rules.
	 *
	 * @return mixed
	 */
	protected function convert($data, array $rules) {
		if (!is_array($data)) {
			if ($rules['type'] & XML_STRING) {
				$data = $this->normalizeStrings($data, $rules);

				if (array_key_exists('flags', $rules) && $rules['flags'] & self::LOWERCASE) {
					$data = mb_strtolower($data);
				}
			}

			return $data;
		}

		if ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists('ex_rules', $tag_rules)) {
					$tag_rules = call_user_func($tag_rules['ex_rules'], $data);
				}

				if (array_key_exists($tag, $data)) {
					$data[$tag] = $this->convert($data[$tag], $tag_rules);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $tag => $value) {
				$data[$tag] = $this->convert($value, $rules['rules'][$prefix]);
			}

			$data = array_values($data);
		}

		return $data;
	}

	/**
	 * Add CR to string type fields.
	 *
	 * @param string $data  Import data.
	 * @param array $rules  Schema rules.
	 *
	 * @return string
	 */
	protected function normalizeStrings(string $data, array $rules): string {
		$data = str_replace("\r\n", "\n", $data);
		$data = (array_key_exists('flags', $rules) && $rules['flags'] & self::EOL_LF)
			? $data
			: str_replace("\n", "\r\n", $data);

		return $data;
	}
}

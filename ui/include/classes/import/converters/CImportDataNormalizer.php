<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Class to normalize incoming data.
 */
class CImportDataNormalizer {

	protected $rules;

	private $value_mode = null;

	public const EOL_LF = 0x01;
	public const LOWERCASE = 0x02;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function setValueMode(string $value_mode): self {
		$this->value_mode = $value_mode;

		return $this;
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
				while ($tag_rules['type'] & XML_MULTIPLE) {
					$matched_multiple_rule = null;

					foreach ($tag_rules['rules'] as $multiple_rule) {
						if ($this->multipleRuleMatched($multiple_rule, $data)) {
							$matched_multiple_rule =
								$multiple_rule + array_intersect_key($tag_rules, array_flip(['flags']));
							break;
						}
					}

					if ($matched_multiple_rule === null) {
						// For use by developers. Do not translate.
						throw new Exception('Incorrect XML_MULTIPLE validation rules.');
					}

					$tag_rules = $matched_multiple_rule;
				}

				if ($tag_rules['type'] & XML_IGNORE_TAG) {
					continue;
				}

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
	 * @param string $data   Import data.
	 * @param array  $rules  Schema rules.
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

	private function multipleRuleMatched(array $multiple_rule, array $data): bool {
		if (array_key_exists('else', $multiple_rule)) {
			return true;
		}
		elseif (is_array($multiple_rule['if'])) {
			$field_name = $multiple_rule['if']['tag'];

			if (!array_key_exists($field_name, $data)) {
				return false;
			}

			$field_value = $data[$field_name];

			return $this->value_mode === CXmlConstantValue::class
				? array_key_exists($field_value, $multiple_rule['if']['in'])
				: in_array($field_value, $multiple_rule['if']['in']);
		}
		elseif ($multiple_rule['if'] instanceof Closure) {
			return call_user_func($multiple_rule['if'], $data);
		}

		return false;
	}
}

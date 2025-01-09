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


/**
 * Class to normalize incoming data.
 */
class CImportDataNormalizer {

	protected $rules;

	private $preview = false;

	public const EOL_LF = 0x01;
	public const LOWERCASE = 0x02;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	/**
	 * On import preview validation should not change content.
	 *
	 * @return bool
	 */
	public function isPreview(): bool {
		return $this->preview;
	}

	/**
	 * On import preview validation should not change content.
	 *
	 * @param bool $preview
	 *
	 * @return self
	 */
	public function setPreview(bool $preview): self {
		$this->preview = $preview;

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
			if (!$data) {
				return $data;
			}

			foreach ($rules['rules'] as $tag => $tag_rules) {
				$tag_rules = $this->getResultRule($tag_rules, $data, $rules['rules']);

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

	private function getResultRule(array $tag_rules, array $data, array $parent_rules): array {
		while ($tag_rules['type'] & XML_MULTIPLE) {
			$matched_multiple_rule = null;

			foreach ($tag_rules['rules'] as $multiple_rule) {
				if ($this->multipleRuleMatched($multiple_rule, $data, $parent_rules)) {
					$multiple_rule['type'] = ($tag_rules['type'] & XML_REQUIRED) | $multiple_rule['type'];
					$matched_multiple_rule =
						$multiple_rule + array_intersect_key($tag_rules, array_flip(['default']));
					break;
				}
			}

			if ($matched_multiple_rule === null) {
				// For use by developers. Do not translate.
				throw new Exception('Incorrect XML_MULTIPLE validation rules.');
			}

			$tag_rules = $matched_multiple_rule;
		}

		return $tag_rules;
	}

	private function multipleRuleMatched(array $multiple_rule, array $data, array $rules): bool {
		if (array_key_exists('else', $multiple_rule)) {
			return true;
		}
		elseif (is_array($multiple_rule['if'])) {
			$field_name = $multiple_rule['if']['tag'];

			if ($this->isPreview()) {
				if (array_key_exists($field_name, $data)) {
					return in_array($data[$field_name], $multiple_rule['if']['in']);
				}
				else {
					$tag_rules = self::getResultRule($rules[$field_name], $data, $rules);

					return array_key_exists($tag_rules['default'], $multiple_rule['if']['in']);
				}
			}
			else {
				return array_key_exists($data[$field_name], $multiple_rule['if']['in']);
			}
		}
		elseif ($multiple_rule['if'] instanceof Closure) {
			return call_user_func($multiple_rule['if'], $data);
		}

		return false;
	}
}

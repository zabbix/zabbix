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


class CControllerValueMapCheck extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	/**
	 * Get validation rules for fields. Used in rules for update/create: Host, Template.
	 *
	 * @return array
	 */
	public static function getFieldsValidationRules(): array {
		return [
			'valuemapid' => ['db valuemap.valuemapid'],
			'name' => ['db valuemap.name', 'not_empty', 'required'],
			'mappings' => ['objects', 'not_empty', 'uniq' => ['type', 'value'],
				'messages' => ['uniq' => _('Mapping type and value combination is not unique.')],
				'fields' => [
					'type' => ['db valuemap_mapping.type', 'required', 'in' => [VALUEMAP_MAPPING_TYPE_EQUAL,
						VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
						VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT
					]],
					'value' => [
						['db valuemap_mapping.value', 'required', 'when' => ['type', 'in' => [
							VALUEMAP_MAPPING_TYPE_EQUAL
						]]],
						['db valuemap_mapping.value', 'required', 'not_empty', 'when' => ['type', 'in' => [
							VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
							VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
						]]],
						['float', 'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
							VALUEMAP_MAPPING_TYPE_LESS_EQUAL
						]]],
						['db valuemap_mapping.value',
							'use' => [CRangesParser::class, ['with_minus' => true, 'with_float' => true, 'with_suffix' => true]],
							'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_IN_RANGE]],
							'messages' => ['use' => _('Invalid range.')]
						],
						['db valuemap_mapping.value', 'use' => [CRegexValidator::class, []],
							'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_REGEXP]]
						]
					],
					'newvalue' => ['db valuemap_mapping.newvalue', 'required', 'not_empty']
				]
			]
		];
	}

	/**
	 * Get validation rules based on provided existing valuemap names to prevent duplicates.
	 *
	 * @param array $valuemap_names
	 *
	 * @return array
	 */
	public static function getValidationRules(?array $valuemap_names): array {
		$rules = ['object', 'fields' => self::getFieldsValidationRules()];

		if ($valuemap_names) {
			$rules['fields']['name'] += ['not_in' => $valuemap_names];

			if (!array_key_exists('messages', $rules['fields']['name'])) {
				$rules['fields']['name']['messages'] = [];
			}
			$rules['fields']['name']['messages'] += ['not_in' => _('Given valuemap name is already taken.')];
		}

		return $rules;
	}

	protected function checkInput(): bool {
		$check_source = $this->validateInput([
			'source' => 'required|in host,template,massupdate',
			'valuemap_names' => 'array'
		]);

		if (!$check_source) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		$valuemap_names = $this->hasInput('valuemap_names') ? $this->getInput('valuemap_names') : null;

		switch ($this->getInput('source')) {
			case 'host':
			case 'template':
				$rules = self::getValidationRules($valuemap_names);

				if ($this->hasInput('valuemap_names')) {
					$rules['fields']['name'] += ['not_in' => $this->getInput('valuemap_names')];

					if (!array_key_exists('messages', $rules['fields']['name'])) {
						$rules['fields']['name']['messages'] = [];
					}
					$rules['fields']['name']['messages'] += ['not_in' => _('Given valuemap name is already taken.')];
				}

				$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
				$ret = $this->validateInput($rules) && $this->validateValueMap();

				$form_errors = $this->getValidationError();
				$response = $form_errors
					? ['form_errors' => $form_errors]
					: ['error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]];

				$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
				break;

			default:
				$fields = [
					'mappings' => 'array',
					'name' => 'string',
					'valuemapid' => 'id',
					'valuemap_names' => 'array'
				];

				$ret = $this->validateInput($fields) && $this->validateValueMap();

				if (!$ret) {
					$this->setResponse(
						new CControllerResponseData(['main_block' => json_encode([
							'error' => [
								'messages' => array_column(get_and_clear_messages(), 'message')
							]
						])])
					);
				}
				break;
		}

		return $ret;
	}

	/**
	 * Validate value map to be added.
	 *
	 * @return bool
	 */
	protected function validateValueMap(): bool {
		$name = $this->getInput('name', '');

		if ($name === '') {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Name'), _('cannot be empty')));

			return false;
		}

		$valuemap_names = $this->getInput('valuemap_names', []);

		if (in_array($name, $valuemap_names)) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Name'),
				_s('value %1$s already exists', '('.$name.')'))
			);
			return false;
		}

		$type_uniq = array_fill_keys([VALUEMAP_MAPPING_TYPE_EQUAL, VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
				VALUEMAP_MAPPING_TYPE_LESS_EQUAL, VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
			], []
		);
		$number_parser = new CNumberParser();
		$range_parser = new CRangesParser(['with_minus' => true, 'with_float' => true, 'with_suffix' => true]);
		$mappings = [];

		foreach ($this->getInput('mappings', []) as $mapping) {
			$mapping += ['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '', 'newvalue' => ''];
			$type = $mapping['type'];
			$value = $mapping['value'];

			if ($type != VALUEMAP_MAPPING_TYPE_DEFAULT && $value === '' && $mapping['newvalue'] === '') {
				continue;
			}

			if ($mapping['newvalue'] === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Mapped to'), _('cannot be empty')));

				return false;
			}
			elseif ($type == VALUEMAP_MAPPING_TYPE_REGEXP) {
				if ($value === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'), _('cannot be empty')));

					return false;
				}
				elseif (!(new CRegexValidator)->validate($value)) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'), _('invalid regular expression')));

					return false;
				}
			}
			elseif ($type == VALUEMAP_MAPPING_TYPE_IN_RANGE) {
				if ($value === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'), _('cannot be empty')));

					return false;
				}
				elseif ($range_parser->parse($value) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'), _('invalid range expression')));

					return false;
				}
			}
			elseif ($type == VALUEMAP_MAPPING_TYPE_LESS_EQUAL || $type == VALUEMAP_MAPPING_TYPE_GREATER_EQUAL) {
				if ($number_parser->parse($value) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'),
						_('a floating point value is expected')
					));

					return false;
				}

				$value = (float) $number_parser->getMatch();
				$value = strval($value);
			}

			if ($type != VALUEMAP_MAPPING_TYPE_DEFAULT && array_key_exists($value, $type_uniq[$type])) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'),
					_s('value %1$s already exists', '('.$value.')'))
				);

				return false;
			}

			$type_uniq[$type][$value] = true;
			$mappings[] = $mapping;
		}

		if (!$mappings) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Mappings'), _('cannot be empty')));
			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$data = [];
		$mappings = [];
		$default = [];
		$this->getInputs($data, ['valuemapid', 'name', 'mappings']);

		foreach ($data['mappings'] as $mapping) {
			if ($mapping['type'] != VALUEMAP_MAPPING_TYPE_DEFAULT
					&& $mapping['value'] === '' && $mapping['newvalue'] === '') {
				continue;
			}
			elseif ($mapping['type'] == VALUEMAP_MAPPING_TYPE_DEFAULT) {
				$default = $mapping;

				continue;
			}

			$mappings[] = $mapping;
		}

		if ($default) {
			$mappings[] = $default;
		}

		$data['mappings'] = $mappings;
		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($data)])));
	}
}

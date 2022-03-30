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


class CControllerPopupValueMapUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'edit' => 'in 1,0',
			'mappings' => 'array',
			'name' => 'string',
			'update' => 'in 1',
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

		return $ret;
	}

	/**
	 * Validate value map to be added.
	 *
	 * @return bool
	 */
	protected function validateValueMap(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

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
				elseif (@preg_match('/'.str_replace('/', '\/', $value).'/', '') === false) {
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

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [];
		$mappings = [];
		$default = [];
		$this->getInputs($data, ['valuemapid', 'name', 'mappings', 'edit']);

		foreach ($data['mappings'] as $mapping) {
			if ($mapping['type'] != VALUEMAP_MAPPING_TYPE_DEFAULT &&
					$mapping['value'] === '' && $mapping['newvalue'] === '') {
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

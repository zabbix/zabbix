<?php
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


class CControllerValueMapEdit extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'edit' => 'in 1',
			'mappings' => 'array',
			'name' => 'string',
			'valuemapid' => 'id',
			'valuemap_names' => 'array',
			'source' => 'required|in host,template,massupdate'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [
			'action' => 'popup.valuemap.check',
			'edit' => 0,
			'mappings' => [],
			'name' => '',
			'valuemap_names' => [],
			'valuemapid' => 0
		];
		$this->getInputs($data, array_keys($data));

		if (!$data['mappings']) {
			$data['mappings'] = [['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '', 'newvalue' => '']];
		}
		else {
			$mappings = [];
			$default = [];

			foreach ($data['mappings'] as $mapping) {
				if ($mapping['type'] == VALUEMAP_MAPPING_TYPE_DEFAULT) {
					$default = $mapping;
				}
				else {
					$mappings[] = $mapping;
				}
			}

			if ($default) {
				$mappings[] = $default;
			}

			$data['mappings'] = $mappings;
		}

		if ($this->getInput('source') === 'host') {
			$rules = CControllerHostCreate::getValidationRules()['fields']['valuemaps'];

			if ($data['valuemap_names']) {
				$rules['fields']['name'] += ['not_in' => $data['valuemap_names']];

				if (!array_key_exists('messages', $rules['fields']['name'])) {
					$rules['fields']['name']['messages'] = [];
				}
				$rules['fields']['name']['messages'] += ['not_in' => _('Given valuemap name is already taken.')];
			}

			$data += ['js_validation_rules' => (new CFormValidator($rules))->getRules()];
		}
		elseif ($this->getInput('source') === 'template') {
			$rules = CControllerTemplateCreate::getValidationRules()['fields']['valuemaps'];

			if ($data['valuemap_names']) {
				$rules['fields']['name'] += ['not_in' => $data['valuemap_names']];

				if (!array_key_exists('messages', $rules['fields']['name'])) {
					$rules['fields']['name']['messages'] = [];
				}
				$rules['fields']['name']['messages'] += ['not_in' => _('Given valuemap name is already taken.')];
			}

			$data += ['js_validation_rules' => (new CFormValidator($rules))->getRules()];
		}

		$data += [
			'has_inline_validation' => $this->getInput('source') === 'host' || $this->getInput('source') === 'template',
			'source' => $this->getInput('source'),
			'title' => _('Value mapping'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}

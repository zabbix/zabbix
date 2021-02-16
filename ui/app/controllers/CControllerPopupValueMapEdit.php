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


class CControllerPopupValueMapEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

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
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * Validate vlue map to be added.
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

		$mappings = array_filter($this->getInput('mappings', []), function ($mapping) {
			return ($mapping['value'] !== '' || $mapping['newvalue'] !== '');
		});

		if (!$mappings) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Mappings'), _('cannot be empty')));
			return false;
		}

		$values = [];

		foreach ($mappings as $mapping) {
			if ($mapping['newvalue'] === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Mapped to'), _('cannot be empty')));
				return false;
			}

			if (array_key_exists($mapping['value'], $values)) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Value'),
					_s('value %1$s already exists', '('.$mapping['value'].')'))
				);
				return false;
			}

			$values[$mapping['value']] = 1;
		}

		return true;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$this->setResponse($this->hasInput('update') ? $this->update() : $this->form());
	}

	/**
	 * Get response object with data to be returned as json data when added value map is valid.
	 *
	 * @return CControllerResponse
	 */
	protected function update(): CControllerResponse {
		$data = [];
		$this->getInputs($data, ['valuemapid', 'name', 'mappings', 'edit']);
		order_result($data['mappings'], 'value');
		$data['mappings'] = array_values($data['mappings']);

		return (new CControllerResponseData(['main_block' => json_encode($data)]))->disableView();
	}

	/**
	 * Get response object with data required to render value map edit form.
	 *
	 * @return CControllerResponse
	 */
	protected function form(): CControllerResponse {
		$data = [
			'action' => $this->getAction(),
			'edit' => 0,
			'mappings' => [],
			'name' => '',
			'valuemap_names' => [],
			'valuemapid' => 0
		];
		$this->getInputs($data, array_keys($data));

		if (!$data['mappings']) {
			$data['mappings'][] = ['value' => '', 'newvalue' => ''];
		}

		$data += [
			'title' => _('Value mapping'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		return new CControllerResponseData($data);
	}
}

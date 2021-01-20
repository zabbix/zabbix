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

	protected function checkInput() {
		$fields = [
			'edit' => 'in 1,0',
			'mappings' => 'array',
			'name' => 'string',
			'name_readonly' => 'in 1,0',
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
		if (!$this->getInput('update', 0)) {
			return true;
		}

		$name = $this->getInput('name');

		if ($name === '') {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty')));
			return false;
		}

		$names_exists = $this->getInput('valuemap_names', []);

		if (in_array($name, $names_exists)) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _s('value "%1$s" is not unique', $name)));
			return false;
		}

		$mappings = $this->getInput('mappings', []);
		$mappings = array_filter($this->getInput('mappings', []), function ($mapping) {
			$mapping += ['value' => '', 'newvalue' => ''];

			return ($mapping['value'] !== '' && $mapping['newvalue'] !== '');
		});

		if (!$mappings) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'mappings', _('cannot be empty')));
			return false;
		}

		if (count($mappings) != count(array_column($mappings, null, 'value'))) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'value', _('should be unique')));
			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$this->setResponse($this->getInput('update', 0) ? $this->update() : $this->form());
	}

	/**
	 * Get response object with data to be returned as json data when added value map is valid.
	 *
	 * @return CControllerResponse
	 */
	protected function update(): CControllerResponse {
		$data = [];
		$this->getInputs($data, ['valuemapid', 'name', 'mappings', 'edit', 'name_readonly']);

		$data['mappings'] = array_filter($data['mappings'], function ($mapping) {
			return ($mapping['value'] !== '' && $mapping['newvalue'] !== '');
		});
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
			'name_readonly' => 0,
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
		$data['name_readonly'] = (bool) $data['name_readonly'];

		return new CControllerResponseData($data);
	}
}

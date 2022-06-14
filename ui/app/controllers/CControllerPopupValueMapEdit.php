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

		$ret = $this->validateInput($fields);

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

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [
			'action' => 'popup.valuemap.update',
			'edit' => 0,
			'mappings' => [],
			'name' => '',
			'valuemap_names' => [],
			'valuemapid' => 0
		];
		$this->getInputs($data, array_keys($data));

		if (!$data['mappings']) {
			$mappings = [['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '', 'newvalue' => '']];
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
		}

		$data['mappings'] = $mappings;
		$data += [
			'title' => _('Value mapping'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}

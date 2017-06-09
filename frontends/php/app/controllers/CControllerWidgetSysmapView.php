<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetSysmapView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'		=>	'string',
			'widgetid'	=>	'required',
			'fields'	=>	'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$input_fields = getRequest('fields');

			$validationRules = [
				'widget_name' => 'string',
				'source_type' => 'fatal|required|in '.WIDGET_SYSMAP_SOURCETYPE_MAP.','.WIDGET_SYSMAP_SOURCETYPE_FILTER
			];

			if (array_key_exists('source_type', $input_fields)) {
				if ($input_fields['source_type'] == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
					$validationRules['filter_widget_reference'] = 'string';
					$validationRules['sysmap_id'] = 'db sysmaps.sysmapid';
				}
				else {
					$validationRules['sysmap_id'] = 'required|db sysmaps.sysmapid';
				}

				$validator = new CNewValidator($input_fields, $validationRules);

				$errors = $validator->getAllErrors();
				if ($errors) {
					$ret = false;

					foreach ($validator->getAllErrors() as $error) {
						info($error);
					}
				}
			}
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$error = null;
		$data = [];

		// Default values
		$default = [
			'source_type' => WIDGET_SYSMAP_SOURCETYPE_MAP,
			'filter_widget_reference' => ''
		];

		if ($this->hasInput('fields')) {
			// Use configured data, if possible
			$data = $this->getInput('fields');
		}

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_SYSMAP]),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'widgetid' => getRequest('widgetid'),
			'fields' => $data,
			'error' => $error
		]));
	}
}

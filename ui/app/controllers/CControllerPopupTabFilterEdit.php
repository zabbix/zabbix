<?php declare(strict_types = 1);
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
 * Controller to load tab filter properties dialog.
 */
class CControllerPopupTabFilterEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$rules = [
			'idx' =>					'string|required',
			'idx2' =>					'int32|required',
			'filter_name' =>			'string',
			'filter_show_counter' =>	'in 0,1',
			'filter_custom_time' =>		'in 0,1',
			'tabfilter_from' =>			'string',
			'tabfilter_to' =>			'string',
			'support_custom_time' =>	'in 0,1',
			'create' =>					'in 0,1'
		];

		$ret = $this->validateInput($rules) && $this->customValidation();

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

	protected function customValidation(): bool {
		if (!$this->getInput('support_custom_time', 0) || $this->getInput('filter_custom_time', 0)) {
			return true;
		}

		$rules = [
			'tabfilter_from' =>		'range_time|required',
			'tabfilter_to' =>		'range_time|required'
		];

		$validator = new CNewValidator($this->getInputAll(), $rules);
		$ret = !$validator->isError() && !$validator->isErrorFatal();

		foreach ($validator->getAllErrors() as $error) {
			info($error);
		}

		if ($ret) {
			$this->input += [
				'from' => $this->input['tabfilter_from'],
				'to' => $this->input['tabfilter_to']
			];
			$ret = $this->validateTimeSelectorPeriod();
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$data = [
			'form_action' => '',
			'idx' => '',
			'idx2' => '',
			'filter_name' => _('Untitled'),
			'filter_show_counter' => 0,
			'filter_custom_time' => 0,
			'tabfilter_from' => '',
			'tabfilter_to' => '',
			'support_custom_time' => 0,
			'create' => 0
		];
		$this->getInputs($data, array_keys($data));

		if (!$data['support_custom_time'] || !$data['filter_custom_time']) {
			$data = [
				'filter_custom_time' => 0,
				'tabfilter_from' => 'now-1h',
				'tabfilter_to' => 'now'
			] + $data;
		}

		$data += [
			'title' => _('Filter properties'),
			'errors' => hasErrorMessages() ? getMessages() : null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}

<?php declare(strict_types = 0);
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
 * Controller to update tab filter.
 */
class CControllerPopupTabFilterUpdate extends CController {

	protected function checkInput() {
		$rules = [
			'idx' =>					'string|required',
			'idx2' =>					'int32|required',
			'filter_name' =>			'string|not_empty',
			'filter_show_counter' =>	'in 0,1',
			'filter_custom_time' =>		'in 0,1',
			'tabfilter_from' =>			'string',
			'tabfilter_to' =>			'string',
			'support_custom_time' =>	'in 0,1',
			'create' =>					'in 0,1'
		];

		$ret = $this->validateInput($rules) && $this->customValidation();

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

	protected function customValidation(): bool {
		if (!$this->getInput('support_custom_time', 0) || !$this->getInput('filter_custom_time', 0)) {
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
		$idx = $this->getInput('idx', '');
		$idx2 = $this->getInput('idx2', '');
		$create = (int) $this->getInput('create', 0);

		$properties = [
			'filter_name' => $this->getInput('filter_name'),
			'filter_show_counter' => (int) $this->getInput('filter_show_counter', 0),
			'filter_custom_time' => (int) $this->getInput('filter_custom_time', 0)
		];

		if ($this->getInput('support_custom_time', 0) && $properties['filter_custom_time']) {
			$properties['from'] = $this->getInput('tabfilter_from', '');
			$properties['to'] = $this->getInput('tabfilter_to', '');
		}

		$filter = (new CTabFilterProfile($idx, []))->read();

		if (array_key_exists($idx2, $filter->tabfilters)) {
			$properties = $properties + $filter->tabfilters[$idx2];
		}
		else {
			$idx2 = count($filter->tabfilters);
		}

		$filter->tabfilters[$idx2] = $properties;
		$filter->update();

		$this->setResponse(new CControllerResponseData([
			'main_block' => json_encode($properties + ['idx2' => $idx2, 'create' => $create])
		]));
	}
}

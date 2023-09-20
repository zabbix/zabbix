<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Controller for manipulating a time period.
 */
class CControllerTimeSelectorCalc extends CController {

	public function init() {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'method' =>			'required|in zoomout,rangeoffset',
			'from' =>			'required|string',
			'to' =>				'required|string',
			'from_offset' =>	'int32|ge 0',
			'to_offset' =>		'int32|ge 0'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('method', '') === 'rangeoffset') {
			$validator = new CNewValidator($this->getInputAll(), [
				'from_offset' => 'required',
				'to_offset' => 'required'
			]);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			if ($validator->isErrorFatal() || $validator->isError()) {
				$ret = false;
			}
		}

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

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction() {
		$time_period = ['from' => $this->getInput('from'), 'to' => $this->getInput('to')];

		$min_period = CTimePeriodHelper::getMinPeriod();
		$max_period = CTimePeriodHelper::getMaxPeriod();

		$fields_typed_errors = CTimePeriodValidator::validateRaw($time_period, [
			'min_period' => $min_period,
			'max_period' => $max_period
		]);

		if (!$fields_typed_errors && $this->hasInput('method')) {
			switch ($this->getInput('method')) {
				case 'zoomout':
					CTimePeriodHelper::zoomOut($time_period);
					break;

				case 'rangeoffset':
					CTimePeriodHelper::rangeOffset($time_period, $this->getInput('from_offset'),
						$this->getInput('to_offset')
					);
					break;
			}

			$fields_typed_errors = CTimePeriodValidator::validateRaw($time_period, [
				'min_period' => $min_period,
				'max_period' => $max_period
			]);
		}

		$fields_errors = [];

		foreach ($fields_typed_errors as $field => $error_type) {
			switch ($error_type) {
				case CTimePeriodValidator::ERROR_TYPE_INVALID_RANGE:
				case CTimePeriodValidator::ERROR_TYPE_INVALID_MIN_PERIOD:
					$fields_errors[$field] = _n('Minimum time period to display is %1$s minute.',
						'Minimum time period to display is %1$s minutes.', (int) ($min_period / SEC_PER_MIN)
					);
					break;

				case CTimePeriodValidator::ERROR_TYPE_INVALID_MAX_PERIOD:
					$fields_errors[$field] = _n('Maximum time period to display is %1$s day.',
						'Maximum time period to display is %1$s days.', (int) round($max_period / SEC_PER_DAY)
					);
					break;

				default:
					$fields_errors[$field] = _('Invalid date.');
					break;
			}
		}

		if ($fields_errors) {
			$output = ['fields_errors' => $fields_errors];
		}
		else {
			$output = $time_period;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}

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
		$range_time_parser = new CRangeTimeParser();

		$time_period = [
			'from' => $this->getInput('from'),
			'to' => $this->getInput('to')
		];

		$has_fields_errors = false;

		foreach (['from' => 'from_ts', 'to' => 'to_ts'] as $field => $field_ts) {
			if ($range_time_parser->parse($time_period[$field]) == CParser::PARSE_SUCCESS) {
				$time_period[$field_ts] = $range_time_parser->getDateTime($field === 'from')->getTimestamp();
			}
			else {
				$has_fields_errors = true;
				break;
			}
		}

		if (!$has_fields_errors) {
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

			$period = $time_period['to_ts'] - $time_period['from_ts'] + 1;

			$min_period = CTimePeriodHelper::getMinPeriod();
			$max_period = CTimePeriodHelper::getMaxPeriod();

			$has_fields_errors = $period < $min_period || $period > $max_period + 1;
		}

		if ($has_fields_errors) {
			$output = ['has_fields_errors' => true];
		}
		else {
			$output = $time_period;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}

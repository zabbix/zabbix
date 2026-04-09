<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerSlaExcludedDowntimeValidate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'name' => ['db sla.name', 'required', 'not_empty'],
			'start_time' => ['string', 'required', 'not_empty',
				'use' => [CAbsoluteTimeValidator::class, ['min' => 0, 'max' => ZBX_MAX_DATE]]
			],
			'duration_days' => ['integer', 'required', 'min' => 0],
			'duration_hours' => ['integer', 'required', 'min' => 0, 'max' => 23],
			'duration_minutes' => [
				['integer', 'required', 'min' => 0, 'max' => 59],
				['integer', 'required', 'min' => 1, 'max' => 59, 'when' => [
					['duration_days', 'in 0'], ['duration_hours', 'in 0']
				], 'messages' => ['min' => _('Duration must be no less than a minute.')]]
			]
		]];
	}

	/**
	 * @throws Exception
	 */
	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA) && $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$parser = new CAbsoluteTimeParser();
		$parser->parse($this->getInput('start_time'));
		$datetime_from = $parser->getDateTime(true);

		$duration_days = $this->getInput('duration_days');
		$duration_hours = $this->getInput('duration_hours');
		$duration_minutes = $this->getInput('duration_minutes');

		$duration = new DateInterval("P{$duration_days}DT{$duration_hours}H{$duration_minutes}M");

		$datetime_to = clone $datetime_from;
		$datetime_to->add($duration);

		$period_from = $datetime_from->getTimestamp();
		$period_to = $datetime_to->getTimestamp();

		if ($period_to > ZBX_MAX_DATE) {
			$data = ['error' => ['messages' => [_s('Excluded downtime must not extend beyond %1$s.',
				date(ZBX_FULL_DATE_TIME, ZBX_MAX_DATE)
			)]]];
		}
		else {
			$data = [
				'body' => [
					'row_index' => $this->getInput('row_index'),
					'name' => $this->getInput('name'),
					'period_from' => $period_from,
					'period_to' => $period_to,
					'start_time' => zbx_date2str(DATE_TIME_FORMAT, $period_from),
					'duration' => convertUnitsS($period_to - $period_from, ['ignore_milliseconds' => true])
				]
			];
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}

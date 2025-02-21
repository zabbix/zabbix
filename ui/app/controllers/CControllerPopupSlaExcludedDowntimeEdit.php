<?php declare(strict_types = 0);
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


class CControllerPopupSlaExcludedDowntimeEdit extends CController {

	protected function init() {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	private static function getValidationRules() {
		return ['objects', 'fields' => [
			'edit' => ['integer', 'in' => ['0', '1']],
			'row_index' => ['integer', 'required'],
			'name' => ['string'],
			'period_from' => ['db sla_excluded_downtime.period_from'],
			'period_to' => ['db sla_excluded_downtime.period_to']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			(new CControllerResponseData(['main_block' => json_encode($response)]))->disableView();
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
		if ($this->hasInput('edit')) {
			$datetime_from = (new DateTime())->setTimestamp((int) $this->getInput('period_from'));
			$datetime_to = (new DateTime())->setTimestamp((int) $this->getInput('period_to'));
			$interval = $datetime_to->diff($datetime_from);

			$form = [
				'name' => $this->getInput('name'),
				'start_time' => $datetime_from->format(ZBX_DATE_TIME),
				'duration_days' => $interval->days,
				'duration_hours' => $interval->h,
				'duration_minutes' => $interval->i
			];
		}
		else {
			$form = [
				'name' => '',
				'start_time' => date(ZBX_DATE_TIME, strtotime('tomorrow')),
				'duration_days' => 0,
				'duration_hours' => 1,
				'duration_minutes' => 0
			];
		}

		$data = [
			'title' => $this->hasInput('edit') ? _('Excluded downtime') : _('New excluded downtime'),
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(
				CControllerSlaExcludedDowntimeValidate::getValidationRules()
			))->getRules(),
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}

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


class CControllerHousekeepingUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'hk_events_mode' => ['boolean', 'required'],
			'hk_events_trigger' => ['setting hk_events_trigger', 'required', 'not_empty',
				'when' => ['hk_events_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_events_service' => ['setting hk_events_service', 'required', 'not_empty',
				'when' => ['hk_events_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_events_internal' => ['setting hk_events_internal', 'required', 'not_empty',
				'when' => ['hk_events_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_events_discovery' => ['setting hk_events_discovery', 'required', 'not_empty',
				'when' => ['hk_events_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_events_autoreg' => ['setting hk_events_autoreg', 'required', 'not_empty',
				'when' => ['hk_events_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_services_mode' => ['boolean', 'required'],
			'hk_services' => ['setting hk_services', 'required', 'not_empty',
				'when' => ['hk_services_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_sessions_mode' => ['boolean', 'required'],
			'hk_sessions' => ['setting hk_sessions', 'required', 'not_empty',
				'when' => ['hk_sessions_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			],
			'hk_history_mode' => ['boolean', 'required'],
			'hk_history_global' => ['boolean', 'required'],
			'hk_history' => ['setting hk_history', 'required', 'not_empty',
				'when' => ['hk_history_global', 'in' => [1]],
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_HOUR, 'max' => 25 * SEC_PER_YEAR, 'accept_zero' => true]
				]
			],
			'hk_trends_mode' => ['boolean', 'required'],
			'hk_trends_global' => ['boolean', 'required'],
			'hk_trends' => ['setting hk_trends', 'required', 'not_empty',
				'when' => ['hk_trends_global', 'in' => [1]],
				'use' => [CTimeUnitValidator::class,
					['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR, 'accept_zero' => true]
				]
			],
			'compression_status' => ['boolean'],
			'compress_older' => ['setting compress_older', 'required', 'not_empty',
				'when' => ['compression_status', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => 7 * SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING);
	}

	protected function doAction(): void {
		$result = API::Housekeeping()->update($this->getInputAll());

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Configuration updated')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
